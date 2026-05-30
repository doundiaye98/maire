<?php
declare(strict_types=1);

/**
 * Migration baseline — adopte le schéma existant.
 *
 * Cette migration ne crée rien : elle sert uniquement à marquer le
 * schéma déjà installé via database/schema.sql comme « point zéro ».
 *
 * Pour les NOUVELLES installations, lancer d'abord :
 *   mysql -u root mairie_senegal < database/schema.sql
 * puis :
 *   php bin/migrate.php up
 *
 * Pour une installation déjà existante (legacy), il suffit de lancer
 * `php bin/migrate.php up` pour enregistrer cette baseline.
 */
return [
    'description' => 'Baseline — schéma initial de mairie_senegal (database/schema.sql)',
    'up' => static function (PDO $pdo): void {
        // No-op : le schéma a été créé en dehors du système de migrations.
        // On vérifie juste qu'une table critique existe pour valider la baseline.
        $r = $pdo->query("SHOW TABLES LIKE 'abonnements'")->fetch();
        if ($r === false) {
            throw new RuntimeException(
                "Schéma de base manquant : la table 'abonnements' n'existe pas. " .
                "Importez d'abord database/schema.sql avant d'appliquer les migrations."
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        // Rollback impossible (= reset complet de la base).
        throw new RuntimeException(
            "Rollback de la baseline impossible. Pour réinitialiser : DROP DATABASE puis ré-import du schéma."
        );
    },
];
