<?php
declare(strict_types=1);

/**
 * Garde d'accès pour les pages /super-admin/ (espace éditeur).
 * Indépendant des gardes mairie : il vérifie uniquement la session $_SESSION['editeur_id'].
 */

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/super-admin-account.php';
require_once __DIR__ . '/csrf.php';
maire_csrf_token(MAIRE_CSRF_SCOPE_SUPER_ADMIN);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($pdo === null) {
    header('Location: login.php?besoin=indispo', true, 302);
    exit;
}

maire_ensure_super_admins_table($pdo);

if (!maire_super_admin_account_session_valid()) {
    header('Location: login.php?besoin=connexion', true, 302);
    exit;
}

$editeurAccount = maire_load_super_admin($pdo, (int) ($_SESSION['editeur_id'] ?? 0));
if ($editeurAccount === null || (int) ($editeurAccount['actif'] ?? 0) !== 1) {
    maire_super_admin_account_logout();
    header('Location: login.php?besoin=desactive', true, 302);
    exit;
}
