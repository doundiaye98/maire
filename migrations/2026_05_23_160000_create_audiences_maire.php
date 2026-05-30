<?php
declare(strict_types=1);

return [
    'description' => 'Crée la table audiences_maire (demandes d’audience avec le maire)',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audiences_maire (
                id INT AUTO_INCREMENT PRIMARY KEY,
                citoyen_id INT NULL,
                prenom VARCHAR(80) NOT NULL,
                nom VARCHAR(80) NOT NULL,
                email VARCHAR(190) NOT NULL,
                telephone VARCHAR(40) NULL,
                quartier VARCHAR(120) NULL,
                motif ENUM('cadre_vie', 'administratif', 'economique', 'social', 'jeunesse', 'associatif', 'autre') NOT NULL DEFAULT 'autre',
                objet VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                mode_audience ENUM('presentiel', 'visio') NOT NULL DEFAULT 'presentiel',
                date_souhaitee DATE NULL,
                creneau_souhaite ENUM('matin', 'apres_midi', 'indifferent') NOT NULL DEFAULT 'indifferent',
                statut ENUM('en_attente', 'confirmee', 'terminee', 'annulee', 'refusee') NOT NULL DEFAULT 'en_attente',
                date_audience DATETIME NULL,
                lien_visio VARCHAR(500) NULL,
                lieu_audience VARCHAR(255) NULL,
                admin_notes TEXT NULL,
                traite_par_email VARCHAR(190) NULL,
                traite_le TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_audiences_statut (statut),
                INDEX idx_audiences_date (date_audience),
                INDEX idx_audiences_citoyen (citoyen_id),
                INDEX idx_audiences_created (created_at),
                CONSTRAINT fk_audience_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS audiences_maire');
    },
];
