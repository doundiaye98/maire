<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/super-admin-session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../abonnement.php', true, 302);
    exit;
}

$csrf = (string) ($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['abo_admin_csrf'] ?? '', $csrf)) {
    header('Location: ../abonnement.php', true, 302);
    exit;
}

unset($_SESSION['maire_super_admin'], $_SESSION['maire_super_admin_ts']);

header('Location: ../abonnement.php', true, 302);
exit;
