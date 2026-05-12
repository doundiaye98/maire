<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

session_unset();
session_destroy();

header('Location: abonnement.php');
exit;
