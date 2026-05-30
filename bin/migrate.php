<?php
declare(strict_types=1);

/**
 * CLI de migration de la base de données.
 *
 * Usage :
 *   php bin/migrate.php status
 *   php bin/migrate.php up [--step=N]
 *   php bin/migrate.php down
 *   php bin/migrate.php create NOM_DE_LA_MIGRATION
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cette commande doit être lancée en ligne de commande (php bin/migrate.php).\n");
}

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/migrations.php';
require __DIR__ . '/../includes/logger.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "❌ Connexion MySQL indisponible. Vérifiez config/database.php\n");
    exit(2);
}

$args = array_slice($argv, 1);
$command = $args[0] ?? 'status';

function maire_cli_print(string $msg): void
{
    echo $msg . PHP_EOL;
}

try {
    switch ($command) {
        case 'status':
            $status = maire_migrations_status($pdo);
            maire_cli_print('=== Migrations appliquées (' . count($status['applied']) . ') ===');
            foreach ($status['applied'] as $v) {
                maire_cli_print('  ✔ ' . $v);
            }
            maire_cli_print('');
            maire_cli_print('=== En attente (' . count($status['pending']) . ') ===');
            foreach ($status['pending'] as $v) {
                maire_cli_print('  ▷ ' . $v);
            }
            if (empty($status['pending'])) {
                maire_cli_print('  (à jour ✨)');
            }
            break;

        case 'up':
            $step = null;
            foreach ($args as $a) {
                if (preg_match('/^--step=(\d+)$/', $a, $m)) {
                    $step = (int) $m[1];
                }
            }
            $done = maire_migrations_run_up($pdo, $step, 'maire_cli_print');
            maire_cli_print('');
            maire_cli_print(empty($done) ? '✨ Rien à appliquer.' : '✔ ' . count($done) . ' migration(s) appliquée(s).');
            maire_log_info('Migrations up exécutées', ['count' => count($done), 'step' => $step]);
            break;

        case 'down':
            $done = maire_migrations_run_down($pdo, 'maire_cli_print');
            maire_cli_print('');
            maire_cli_print($done === null ? '✨ Rien à annuler.' : '✔ Rollback de ' . $done['version'] . ' effectué.');
            maire_log_info('Migration down exécutée', ['version' => $done['version'] ?? null]);
            break;

        case 'create':
            $nom = (string) ($args[1] ?? '');
            if ($nom === '') {
                fwrite(STDERR, "Usage : php bin/migrate.php create NOM_DE_LA_MIGRATION\n");
                exit(1);
            }
            $path = maire_migrations_create($nom);
            maire_cli_print('✔ Nouvelle migration créée : ' . $path);
            break;

        default:
            fwrite(STDERR, "Commande inconnue : $command\n");
            fwrite(STDERR, "Commandes disponibles : status | up [--step=N] | down | create NOM\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
    maire_log_exception($e, 'CLI migration error');
    exit(1);
}
