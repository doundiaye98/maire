<?php
declare(strict_types=1);

/**
 * Session « citoyen » — habitants de la commune.
 *
 * Distincte de :
 *   - $_SESSION['subscriber_*'] (agent ou admin mairie)
 *   - $_SESSION['editeur_*'] (super-admin éditeur)
 *   - $_SESSION['super_admin_ts'] (console secrète)
 *
 * Permet à un habitant de :
 *   - faire des signalements (route, lampadaire, etc.)
 *   - (plus tard) voter, payer des taxes, etc.
 */

const MAIRE_CITOYEN_SESSION_TTL = 14400; // 4 h d'inactivité

function maire_ensure_citoyens_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS citoyens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            mot_de_passe_hash VARCHAR(255) NOT NULL,
            prenom VARCHAR(80) NOT NULL,
            nom VARCHAR(80) NOT NULL,
            telephone VARCHAR(40) DEFAULT NULL,
            quartier VARCHAR(120) DEFAULT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_citoyens_actif (actif)
        )
    ");

    // Amorçage du compte de démonstration UNIQUEMENT en environnement de développement.
    // Identifiants : citoyen@demo.rufisque.sn / DemoCitoyen2026!
    // En production, ne pas définir APP_ENV ou la mettre à "production".
    if (function_exists('maire_is_dev_env') && maire_is_dev_env()) {
        try {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM citoyens')->fetchColumn();
            if ($n === 0) {
                $hash = password_hash('DemoCitoyen2026!', PASSWORD_DEFAULT);
                $ins = $pdo->prepare('
                    INSERT INTO citoyens (email, mot_de_passe_hash, prenom, nom, telephone, quartier, actif)
                    VALUES (:email, :hash, :p, :n, :tel, :q, 1)
                ');
                $ins->execute([
                    'email' => 'citoyen@demo.rufisque.sn',
                    'hash' => $hash,
                    'p' => 'Aminata',
                    'n' => 'Diop',
                    'tel' => null,
                    'q' => 'Keury Souf',
                ]);
            }
        } catch (Throwable $e) {
            // pas bloquant — l'inscription manuelle reste possible
        }
    }
}

/**
 * @return array{id:int,email:string,prenom:string,nom:string,telephone:?string,quartier:?string,actif:int,mot_de_passe_hash:string,last_login_at:?string,created_at:string}|null
 */
function maire_load_citoyen(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT id, email, mot_de_passe_hash, prenom, nom, telephone, quartier, actif, last_login_at, created_at FROM citoyens WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row === false ? null : $row;
}

function maire_load_citoyen_by_email(PDO $pdo, string $email): ?array
{
    try {
        $st = $pdo->prepare('SELECT id, email, mot_de_passe_hash, prenom, nom, telephone, quartier, actif FROM citoyens WHERE email = :email LIMIT 1');
        $st->execute(['email' => $email]);
        $row = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row === false ? null : $row;
}

/**
 * Crée un compte citoyen. Retourne l'id créé ou un message d'erreur dans $errMsg.
 */
function maire_creer_citoyen(PDO $pdo, array $data, ?string &$errMsg = null): ?int
{
    maire_ensure_citoyens_table($pdo);

    $email = trim((string) ($data['email'] ?? ''));
    $motDePasse = (string) ($data['mot_de_passe'] ?? '');
    $prenom = trim((string) ($data['prenom'] ?? ''));
    $nom = trim((string) ($data['nom'] ?? ''));
    $telephone = trim((string) ($data['telephone'] ?? ''));
    $quartier = trim((string) ($data['quartier'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Adresse e-mail invalide.';
        return null;
    }
    if (strlen($motDePasse) < 8) {
        $errMsg = 'Le mot de passe doit faire au moins 8 caractères.';
        return null;
    }
    if ($prenom === '' || $nom === '') {
        $errMsg = 'Prénom et nom sont requis.';
        return null;
    }
    if (mb_strlen($prenom) > 80 || mb_strlen($nom) > 80) {
        $errMsg = 'Prénom ou nom trop long.';
        return null;
    }
    if ($telephone !== '' && !preg_match('/^[\d \+\-\(\)\.]{6,40}$/', $telephone)) {
        $errMsg = 'Numéro de téléphone invalide.';
        return null;
    }

    if (maire_load_citoyen_by_email($pdo, $email) !== null) {
        $errMsg = 'Un compte citoyen existe déjà avec cet e-mail.';
        return null;
    }

    try {
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('
            INSERT INTO citoyens (email, mot_de_passe_hash, prenom, nom, telephone, quartier, actif)
            VALUES (:email, :hash, :prenom, :nom, :tel, :quartier, 1)
        ');
        $ins->execute([
            'email' => $email,
            'hash' => $hash,
            'prenom' => mb_substr($prenom, 0, 80),
            'nom' => mb_substr($nom, 0, 80),
            'tel' => $telephone !== '' ? mb_substr($telephone, 0, 40) : null,
            'quartier' => $quartier !== '' ? mb_substr($quartier, 0, 120) : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Erreur d’enregistrement : ' . $e->getMessage();
        return null;
    }
}

function maire_citoyen_attempt_login(PDO $pdo, string $email, string $motDePasse): bool
{
    maire_ensure_citoyens_table($pdo);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $motDePasse === '') {
        return false;
    }
    $row = maire_load_citoyen_by_email($pdo, $email);
    if ($row === null || (int) ($row['actif'] ?? 0) !== 1) {
        return false;
    }
    if (!password_verify($motDePasse, (string) $row['mot_de_passe_hash'])) {
        return false;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['citoyen_id'] = (int) $row['id'];
    $_SESSION['citoyen_email'] = (string) $row['email'];
    $_SESSION['citoyen_prenom'] = (string) $row['prenom'];
    $_SESSION['citoyen_nom'] = (string) $row['nom'];
    $_SESSION['citoyen_ts'] = time();

    try {
        $pdo->prepare('UPDATE citoyens SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $row['id']]);
    } catch (Throwable $e) {
        // non bloquant
    }
    return true;
}

function maire_citoyen_session_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if (empty($_SESSION['citoyen_id'])) {
        return false;
    }
    $ts = (int) ($_SESSION['citoyen_ts'] ?? 0);
    if ($ts <= 0 || (time() - $ts) > MAIRE_CITOYEN_SESSION_TTL) {
        maire_citoyen_logout();
        return false;
    }
    $_SESSION['citoyen_ts'] = time();
    return true;
}

function maire_citoyen_current_id(): ?int
{
    return maire_citoyen_session_valid() ? (int) $_SESSION['citoyen_id'] : null;
}

function maire_citoyen_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset(
        $_SESSION['citoyen_id'],
        $_SESSION['citoyen_email'],
        $_SESSION['citoyen_prenom'],
        $_SESSION['citoyen_nom'],
        $_SESSION['citoyen_ts']
    );
}
