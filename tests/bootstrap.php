<?php
declare(strict_types=1);

/**
 * Bootstrap PHPUnit — préparation de l'environnement de test.
 *
 * - Charge l'autoloader Composer
 * - Définit les variables d'environnement de test
 * - Charge les modules métier nécessaires (logger, etc.)
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (!defined('MAIRE_LOG_LEVEL')) {
    define('MAIRE_LOG_LEVEL', 'warning');
}

require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/migrations.php';

/**
 * Fabrique un PDO SQLite in-memory pour tester sans vraie BDD MySQL.
 * Utile pour les tests unitaires des fonctions DB-agnostic.
 */
function maire_test_pdo_memory(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
