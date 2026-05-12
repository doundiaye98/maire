<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session-performance.php';
maire_session_configure_ini();

$dbHost = getenv('MAIRE_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('MAIRE_DB_NAME') ?: 'mairie_senegal';
$dbUser = getenv('MAIRE_DB_USER') ?: 'root';
$dbPass = '';
$envPass = getenv('MAIRE_DB_PASS');
if ($envPass !== false) {
    $dbPass = (string) $envPass;
}
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
} catch (Throwable $exception) {
    $pdo = null;
}

/*
 * Charge / pics de connexion : chaque requête PHP ouvre une connexion MySQL courte puis la ferme.
 * Pour beaucoup d’utilisateurs simultanés, augmentez côté serveur notamment :
 *   - MySQL : max_connections (ex. 200+), wait_timeout adapté
 *   - PHP-FPM : pm.max_children suffisant pour le nombre de requêtes parallèles
 *   - Sessions : en production forte affluence, stocker les sessions dans Redis/Memcached plutôt que des fichiers
 */
