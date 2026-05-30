<?php
declare(strict_types=1);

/**
 * Compte « super-administrateur éditeur ».
 *
 * Distinct du compte institutionnel mairie : géré par l'entreprise qui a créé le site,
 * il sert à suivre l'abonnement de la commune et à le suspendre en cas de non-renouvellement.
 *
 * L'authentification se fait exclusivement via /super-admin/login.php
 * (compte e-mail + mot de passe + rate-limit).
 */

const MAIRE_EDITEUR_SESSION_TTL = 7200; // 2 h d'inactivité

/**
 * Crée la table super_admins si elle n'existe pas (idempotent).
 *
 * En environnement de développement uniquement (APP_ENV=development|dev|local),
 * amorce un compte de démonstration : editeur@demo.rufisque.sn / DemoEditeur2026!
 * En production, l'administrateur doit créer manuellement le premier compte
 * via le seed SQL ou un INSERT direct.
 */
function maire_ensure_super_admins_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            nom VARCHAR(120) NOT NULL DEFAULT '',
            mot_de_passe_hash VARCHAR(255) NOT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    if (!function_exists('maire_is_dev_env') || !maire_is_dev_env()) {
        return;
    }

    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM super_admins')->fetchColumn();
        if ($n === 0) {
            $hash = password_hash('DemoEditeur2026!', PASSWORD_DEFAULT);
            $ins = $pdo->prepare("
                INSERT INTO super_admins (email, nom, mot_de_passe_hash, actif)
                VALUES (:email, :nom, :hash, 1)
            ");
            $ins->execute([
                'email' => 'editeur@demo.rufisque.sn',
                'nom' => 'Éditeur (démonstration)',
                'hash' => $hash,
            ]);
        }
    } catch (Throwable $e) {
        // table non disponible, on laisse le login échouer proprement plus tard
    }
}

/**
 * Charge un compte éditeur par id.
 *
 * @return array{id:int,email:string,nom:string,actif:int,mot_de_passe_hash:string,last_login_at:?string,created_at:string}|null
 */
function maire_load_super_admin(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, email, nom, mot_de_passe_hash, actif, last_login_at, created_at FROM super_admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row === false ? null : $row;
}

/**
 * Recherche par email (pour login).
 */
function maire_load_super_admin_by_email(PDO $pdo, string $email): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT id, email, nom, mot_de_passe_hash, actif FROM super_admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row === false ? null : $row;
}

/**
 * Initialise les variables de session pour un super-admin éditeur authentifié.
 */
function maire_super_admin_account_login(PDO $pdo, int $id, string $email, string $nom): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['editeur_id'] = $id;
    $_SESSION['editeur_email'] = $email;
    $_SESSION['editeur_nom'] = $nom;
    $_SESSION['editeur_ts'] = time();

    try {
        $pdo->prepare('UPDATE super_admins SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $e) {
        // non bloquant
    }
}

/**
 * Vérifie la validité de la session super-admin éditeur (présence + TTL d'inactivité).
 */
function maire_super_admin_account_session_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if (empty($_SESSION['editeur_id'])) {
        return false;
    }
    $ts = (int) ($_SESSION['editeur_ts'] ?? 0);
    if ($ts <= 0 || (time() - $ts) > MAIRE_EDITEUR_SESSION_TTL) {
        maire_super_admin_account_logout();

        return false;
    }
    $_SESSION['editeur_ts'] = time();

    return true;
}

/**
 * Détruit la session super-admin éditeur (sans toucher aux sessions mairie).
 */
function maire_super_admin_account_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset(
        $_SESSION['editeur_id'],
        $_SESSION['editeur_email'],
        $_SESSION['editeur_nom'],
        $_SESSION['editeur_ts']
    );
}
