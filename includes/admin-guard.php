<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/super-admin-session.php';
require_once __DIR__ . '/site-paths.php';

if (maire_super_admin_session_valid()) {
    return;
}

require __DIR__ . '/subscription-guard.php';

if (($_SESSION['subscriber_role'] ?? 'subscriber') !== 'admin') {
    header('Location: ' . maire_login_url('admin'), true, 302);
    exit;
}
