<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/super-admin-session.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../abonnement.php', true, 302);
    exit;
}

if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
    header('Location: ../abonnement.php', true, 302);
    exit;
}

unset($_SESSION['maire_super_admin'], $_SESSION['maire_super_admin_ts']);

header('Location: ../abonnement.php', true, 302);
exit;
