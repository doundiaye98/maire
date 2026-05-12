<?php
declare(strict_types=1);

/**
 * Session « super-admin » utilisée par les pages /admin/* sensibles et /super-admin/*.
 *
 * Elle est ouverte uniquement via /super-admin/login.php (compte + mot de passe).
 * Cf. includes/super-admin-account.php pour la gestion du compte éditeur.
 *
 * TTL : 2 h glissantes (rafraîchies à chaque appel valide).
 */
const MAIRE_SUPER_ADMIN_TTL = 7200;

function maire_super_admin_session_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if (empty($_SESSION['maire_super_admin']) || $_SESSION['maire_super_admin'] !== true) {
        return false;
    }
    $ts = (int) ($_SESSION['maire_super_admin_ts'] ?? 0);
    if ($ts <= 0 || (time() - $ts) > MAIRE_SUPER_ADMIN_TTL) {
        unset($_SESSION['maire_super_admin'], $_SESSION['maire_super_admin_ts']);
        return false;
    }
    $_SESSION['maire_super_admin_ts'] = time();

    return true;
}

function maire_is_super_console(): bool
{
    return !empty($_SESSION['maire_super_admin']) && $_SESSION['maire_super_admin'] === true;
}
