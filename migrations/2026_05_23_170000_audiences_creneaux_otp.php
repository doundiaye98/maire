<?php
declare(strict_types=1);

return [
    'description' => 'Créneaux d’audience, OTP SMS et colonnes de réservation',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS otp_verifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telephone VARCHAR(20) NOT NULL,
                scope VARCHAR(40) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                verified_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_otp_lookup (telephone, scope, expires_at),
                INDEX idx_otp_verified (verified_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audiences_creneaux (
                id INT AUTO_INCREMENT PRIMARY KEY,
                debut DATETIME NOT NULL,
                fin DATETIME NOT NULL,
                mode_audience ENUM('presentiel', 'visio') NOT NULL DEFAULT 'presentiel',
                capacite INT NOT NULL DEFAULT 1,
                places_prises INT NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                notes_admin VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_creneau_debut (debut),
                INDEX idx_creneau_actif (actif, debut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $cols = $pdo->query('SHOW COLUMNS FROM audiences_maire')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('type_reservation', $cols, true)) {
            $pdo->exec("ALTER TABLE audiences_maire ADD COLUMN type_reservation ENUM('creneau_fixe', 'demande_libre') NOT NULL DEFAULT 'demande_libre' AFTER mode_audience");
        }
        if (!in_array('creneau_id', $cols, true)) {
            $pdo->exec('ALTER TABLE audiences_maire ADD COLUMN creneau_id INT NULL AFTER type_reservation');
        }
        if (!in_array('telephone_verifie', $cols, true)) {
            $pdo->exec('ALTER TABLE audiences_maire ADD COLUMN telephone_verifie TINYINT(1) NOT NULL DEFAULT 0 AFTER telephone');
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE audiences_maire DROP COLUMN IF EXISTS telephone_verifie');
        $pdo->exec('ALTER TABLE audiences_maire DROP COLUMN IF EXISTS creneau_id');
        $pdo->exec('ALTER TABLE audiences_maire DROP COLUMN IF EXISTS type_reservation');
        $pdo->exec('DROP TABLE IF EXISTS audiences_creneaux');
        $pdo->exec('DROP TABLE IF EXISTS otp_verifications');
    },
];
