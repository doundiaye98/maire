<?php
declare(strict_types=1);

/**
 * Protection CSRF centralisée.
 *
 * 3 fonctions à connaître :
 *   - maire_csrf_token(string $form = 'default')    → string : récupère ou crée le token
 *   - maire_csrf_field(string $form = 'default')    → string : HTML <input type="hidden"> prêt à coller
 *   - maire_csrf_validate(string $form = 'default') → bool   : valide le POST courant
 *
 * Convention : un token par "scope" (formulaire ou groupe de formulaires).
 * Cela permet de retirer un token spécifique après usage sans casser les autres.
 *
 * Usage type dans un formulaire :
 *
 *   <form method="post" action="/cible.php">
 *       <?php echo maire_csrf_field('mon_form'); ?>
 *       ...
 *   </form>
 *
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *       if (!maire_csrf_validate('mon_form')) {
 *           http_response_code(403);
 *           exit('CSRF check failed');
 *       }
 *       // ... traitement
 *   }
 */

const MAIRE_CSRF_SESSION_KEY = 'maire_csrf_tokens';
const MAIRE_CSRF_TOKEN_LENGTH = 32; // bytes → 64 chars hex

/** Scopes standardisés pour les formulaires publics. */
const MAIRE_CSRF_SCOPE_CITOYEN = 'citoyen';
const MAIRE_CSRF_SCOPE_CONTACT = 'contact_public';
const MAIRE_CSRF_SCOPE_ABONNEMENT = 'abonnement_login';
const MAIRE_CSRF_SCOPE_CHATBOT = 'chatbot_ask';
const MAIRE_CSRF_SCOPE_AUDIENCE = 'audience_maire';
const MAIRE_CSRF_SCOPE_ETAT_CIVIL = 'etat_civil_demande';

/** Scopes standardisés — administration & éditeur. */
const MAIRE_CSRF_SCOPE_ADMIN = 'admin';
const MAIRE_CSRF_SCOPE_ADMIN_ETAT_CIVIL = 'admin_etat_civil';
const MAIRE_CSRF_SCOPE_SUPER_ADMIN = 'super_admin';
const MAIRE_CSRF_SCOPE_SUPER_ADMIN_LOGIN = 'super_admin_login';
const MAIRE_CSRF_SCOPE_SUPER_ADMIN_PAIEMENTS = 'super_admin_paiements';

/**
 * Correspondance rétroactive session/post → scope centralisé.
 *
 * @return array<string, array{session: string, post: string}>
 */
function maire_csrf_legacy_scopes(): array
{
    return [
        MAIRE_CSRF_SCOPE_CITOYEN => ['session' => 'citoyen_csrf', 'post' => 'csrf'],
        MAIRE_CSRF_SCOPE_ADMIN => ['session' => 'abo_admin_csrf', 'post' => 'csrf'],
        MAIRE_CSRF_SCOPE_SUPER_ADMIN => ['session' => 'editeur_csrf', 'post' => 'csrf'],
        MAIRE_CSRF_SCOPE_SUPER_ADMIN_LOGIN => ['session' => 'editeur_login_csrf', 'post' => 'csrf'],
        MAIRE_CSRF_SCOPE_SUPER_ADMIN_PAIEMENTS => ['session' => 'editeur_paiements_csrf', 'post' => 'csrf'],
    ];
}

/**
 * Tente une validation via l'ancien champ `csrf` + clé session legacy.
 */
function maire_csrf_try_legacy_validate(string $expectedScope): bool
{
    $map = maire_csrf_legacy_scopes();
    if (!isset($map[$expectedScope])) {
        return false;
    }
    $sessionKey = $map[$expectedScope]['session'];
    $postKey = $map[$expectedScope]['post'];
    if (!maire_csrf_legacy_check($sessionKey, $postKey)) {
        return false;
    }
    if (!empty($_SESSION[$sessionKey]) && empty($_SESSION[MAIRE_CSRF_SESSION_KEY][$expectedScope])) {
        $_SESSION[MAIRE_CSRF_SESSION_KEY][$expectedScope] = (string) $_SESSION[$sessionKey];
    }
    return true;
}

/**
 * Retourne le token CSRF pour un scope donné, en le générant si nécessaire.
 */
function maire_csrf_token(string $scope = 'default'): string
{
    maire_csrf_init_session();
    if (empty($_SESSION[MAIRE_CSRF_SESSION_KEY][$scope])) {
        $_SESSION[MAIRE_CSRF_SESSION_KEY][$scope] = bin2hex(random_bytes(MAIRE_CSRF_TOKEN_LENGTH));
    }
    return (string) $_SESSION[MAIRE_CSRF_SESSION_KEY][$scope];
}

/**
 * Retourne le HTML d'un champ caché prêt à coller dans un formulaire.
 */
function maire_csrf_field(string $scope = 'default'): string
{
    $token = maire_csrf_token($scope);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="csrf_scope" value="' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valide le token CSRF présent dans $_POST.
 *
 * Vérifie :
 *  - le scope envoyé correspond
 *  - le token envoyé est non vide
 *  - le token correspond exactement à celui en session (timing-safe)
 *
 * @param string $expectedScope Scope attendu (doit correspondre à celui passé à maire_csrf_field)
 * @param bool   $rotate        Si true, régénère le token après validation réussie (one-shot)
 */
function maire_csrf_validate(string $expectedScope = 'default', bool $rotate = false): bool
{
    maire_csrf_init_session();

    $scopeReceived = (string) ($_POST['csrf_scope'] ?? '');
    $tokenReceived = (string) ($_POST['csrf_token'] ?? '');

    $ok = false;
    if ($scopeReceived !== '' && $tokenReceived !== '' && $scopeReceived === $expectedScope) {
        $tokenExpected = (string) ($_SESSION[MAIRE_CSRF_SESSION_KEY][$expectedScope] ?? '');
        if ($tokenExpected !== '') {
            $ok = hash_equals($tokenExpected, $tokenReceived);
        }
    }
    if (!$ok) {
        $ok = maire_csrf_try_legacy_validate($expectedScope);
    }
    if ($ok && $rotate) {
        unset($_SESSION[MAIRE_CSRF_SESSION_KEY][$expectedScope]);
    }
    return $ok;
}

/**
 * Invalide manuellement un token (ex. après une action sensible réussie).
 */
function maire_csrf_invalidate(string $scope = 'default'): void
{
    maire_csrf_init_session();
    unset($_SESSION[MAIRE_CSRF_SESSION_KEY][$scope]);
}

/**
 * Compat rétro : compatibilité avec les vérifications "à l'ancienne"
 * (ex. {$_SESSION['citoyen_csrf']} déjà répandu dans le projet).
 *
 * Permet aux nouveaux helpers de cohabiter avec le code existant
 * sans avoir à tout refactorer en une fois.
 */
function maire_csrf_legacy_check(string $sessionKey, string $postKey = 'csrf'): bool
{
    if (empty($_SESSION[$sessionKey])) {
        return false;
    }
    $received = (string) ($_POST[$postKey] ?? '');
    if ($received === '') {
        return false;
    }
    return hash_equals((string) $_SESSION[$sessionKey], $received);
}

/**
 * Initialise le bucket de session pour les tokens CSRF.
 */
function maire_csrf_init_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Si le caller appelle ce helper hors d'une page utilisant header.php
        // (ex. endpoint API séparé), on tente de démarrer une session.
        // Ne pas échouer silencieusement : émettre un warning logger.
        if (function_exists('maire_log_warning')) {
            maire_log_warning('csrf_session_not_started', ['php_self' => $_SERVER['PHP_SELF'] ?? '']);
        }
        return;
    }
    if (!isset($_SESSION[MAIRE_CSRF_SESSION_KEY]) || !is_array($_SESSION[MAIRE_CSRF_SESSION_KEY])) {
        $_SESSION[MAIRE_CSRF_SESSION_KEY] = [];
    }
    foreach (maire_csrf_legacy_scopes() as $scope => $cfg) {
        $sessionKey = $cfg['session'];
        if (!empty($_SESSION[$sessionKey]) && empty($_SESSION[MAIRE_CSRF_SESSION_KEY][$scope])) {
            $_SESSION[MAIRE_CSRF_SESSION_KEY][$scope] = (string) $_SESSION[$sessionKey];
        }
    }
}

/**
 * Message utilisateur standard lors d'un échec CSRF.
 */
function maire_csrf_error_message(): string
{
    return 'Jeton de sécurité invalide. Rechargez la page puis réessayez.';
}

/**
 * Valide le CSRF ou renvoie une erreur JSON 403 (endpoints AJAX).
 */
function maire_csrf_validate_json(string $expectedScope): void
{
    if (maire_csrf_validate($expectedScope)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => maire_csrf_error_message()], JSON_UNESCAPED_UNICODE);
    exit;
}
