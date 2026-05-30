<?php
declare(strict_types=1);

/**
 * Crée la table temoignages_citoyens pour piloter les avis affichés sur
 * la page d'accueil depuis la BDD (au lieu d'avoir des témoignages
 * fictifs hardcodés dans index.php).
 */
return [
    'description' => 'Crée la table temoignages_citoyens',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS temoignages_citoyens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(120) NOT NULL,
                role VARCHAR(150) NOT NULL DEFAULT '',
                texte TEXT NOT NULL,
                note TINYINT UNSIGNED NOT NULL DEFAULT 5,
                publie TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_temoignages_publie (publie, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS temoignages_citoyens");
    },
];
