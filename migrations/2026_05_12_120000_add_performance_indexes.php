<?php
declare(strict_types=1);

/**
 * Ajoute des index de performance utiles à plusieurs tables critiques.
 * Toutes les opérations sont idempotentes (IF NOT EXISTS quand MySQL >= 8,
 * sinon try/catch silencieux pour MariaDB 10.x et MySQL 5.7).
 */

function maire_migration_add_index_if_missing(PDO $pdo, string $table, string $indexName, string $columns): void
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i");
    $st->execute(['t' => $table, 'i' => $indexName]);
    if ((int) $st->fetchColumn() > 0) {
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)");
}

function maire_migration_drop_index_if_exists(PDO $pdo, string $table, string $indexName): void
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i");
    $st->execute(['t' => $table, 'i' => $indexName]);
    if ((int) $st->fetchColumn() === 0) {
        return;
    }
    $pdo->exec("ALTER TABLE `$table` DROP INDEX `$indexName`");
}

return [
    'description' => 'Ajoute des index de performance (recherche email, dates, statuts)',
    'up' => static function (PDO $pdo): void {
        $indexes = [
            ['abonnements',         'idx_abo_role_actif',  'role_utilisateur, actif'],
            ['abonnements',         'idx_abo_dates',       'date_debut, date_fin'],
            ['messages_contact',    'idx_msg_date',        'date_envoi'],
            ['paiements_abonnements','idx_paie_statut',    'statut, created_at'],
        ];
        foreach ($indexes as [$t, $i, $c]) {
            // Best effort : on ignore si la table n'existe pas encore (feature non utilisée).
            try {
                maire_migration_add_index_if_missing($pdo, $t, $i, $c);
            } catch (Throwable $e) {
                // table absente → skip
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $indexes = [
            ['abonnements',         'idx_abo_role_actif'],
            ['abonnements',         'idx_abo_dates'],
            ['messages_contact',    'idx_msg_date'],
            ['paiements_abonnements','idx_paie_statut'],
        ];
        foreach ($indexes as [$t, $i]) {
            try {
                maire_migration_drop_index_if_exists($pdo, $t, $i);
            } catch (Throwable $e) {
                // skip
            }
        }
    },
];
