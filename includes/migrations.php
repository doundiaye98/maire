<?php
declare(strict_types=1);

/**
 * Système de migrations de base de données — pur PHP, sans dépendance externe.
 *
 * Pourquoi ce module ?
 *   Le projet faisait coexister un schema.sql statique et des
 *   CREATE TABLE IF NOT EXISTS dispersés dans le code (« lazy schema »).
 *   Ce système le remplace par des migrations versionnées, idempotentes,
 *   historisées en BDD.
 *
 * Comment ça marche ?
 *   - Chaque migration est un fichier PHP dans migrations/ nommé
 *     {timestamp}_{nom}.php (ex : 2026_05_12_120000_create_documents.php).
 *   - Chaque fichier retourne un array : ['up' => fn(PDO), 'down' => fn(PDO), 'description' => string].
 *   - L'état est tracké dans la table schema_migrations.
 *
 * CLI :
 *   php bin/migrate.php status         # affiche les migrations appliquées et en attente
 *   php bin/migrate.php up             # applique toutes les migrations en attente
 *   php bin/migrate.php up --step=1    # applique uniquement la prochaine
 *   php bin/migrate.php down           # rollback de la dernière migration
 *   php bin/migrate.php create NOM     # crée un nouveau fichier de migration
 */

const MAIRE_MIGRATIONS_DIR = 'migrations';
const MAIRE_MIGRATIONS_TABLE = 'schema_migrations';

function maire_migrations_dir(): string
{
    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        $base = __DIR__ . DIRECTORY_SEPARATOR . '..';
    }
    return $base . DIRECTORY_SEPARATOR . MAIRE_MIGRATIONS_DIR;
}

/**
 * Garantit l'existence de la table de tracking des migrations.
 */
function maire_migrations_ensure_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . MAIRE_MIGRATIONS_TABLE . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(80) NOT NULL UNIQUE,
            description VARCHAR(190) NOT NULL DEFAULT '',
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            execution_ms INT NOT NULL DEFAULT 0,
            INDEX idx_migrations_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Liste les fichiers de migration disponibles, triés par version (croissant).
 *
 * @return array<int, array{version:string, file:string, description:string, up:callable, down:?callable}>
 */
function maire_migrations_available(): array
{
    $dir = maire_migrations_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    sort($files, SORT_STRING);

    $out = [];
    foreach ($files as $file) {
        $base = basename($file, '.php');
        $migration = require $file;
        if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
            throw new RuntimeException("Migration invalide : $file (clé 'up' requise et callable).");
        }
        $out[] = [
            'version'     => $base,
            'file'        => $file,
            'description' => (string) ($migration['description'] ?? ''),
            'up'          => $migration['up'],
            'down'        => isset($migration['down']) && is_callable($migration['down']) ? $migration['down'] : null,
        ];
    }
    return $out;
}

/**
 * Liste les versions déjà appliquées (depuis la table schema_migrations).
 *
 * @return array<int, string>
 */
function maire_migrations_applied(PDO $pdo): array
{
    maire_migrations_ensure_table($pdo);
    $rows = $pdo->query('SELECT version FROM ' . MAIRE_MIGRATIONS_TABLE . ' ORDER BY version ASC')->fetchAll(PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
}

/**
 * Applique les migrations en attente.
 *
 * @param int|null $maxSteps Limite optionnelle au nombre de migrations à appliquer (null = toutes).
 * @return array<int, array{version:string, ms:int}>
 */
function maire_migrations_run_up(PDO $pdo, ?int $maxSteps = null, ?callable $logger = null): array
{
    maire_migrations_ensure_table($pdo);
    $applied = array_flip(maire_migrations_applied($pdo));
    $available = maire_migrations_available();

    $done = [];
    $count = 0;
    foreach ($available as $m) {
        if (isset($applied[$m['version']])) {
            continue;
        }
        if ($maxSteps !== null && $count >= $maxSteps) {
            break;
        }
        $count++;

        if ($logger !== null) {
            $logger("▶ Migration : {$m['version']}" . ($m['description'] !== '' ? " — {$m['description']}" : ''));
        }

        $started = microtime(true);
        try {
            $pdo->beginTransaction();
            ($m['up'])($pdo);
            $pdo->prepare('INSERT INTO ' . MAIRE_MIGRATIONS_TABLE . ' (version, description, execution_ms) VALUES (:v, :d, :ms)')
                ->execute([
                    'v' => $m['version'],
                    'd' => mb_substr($m['description'], 0, 190),
                    'ms' => (int) ((microtime(true) - $started) * 1000),
                ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Migration {$m['version']} échouée : " . $e->getMessage(), 0, $e);
        }

        $ms = (int) ((microtime(true) - $started) * 1000);
        $done[] = ['version' => $m['version'], 'ms' => $ms];

        if ($logger !== null) {
            $logger("✔ Appliquée ({$ms} ms)");
        }
    }

    return $done;
}

/**
 * Rollback de la dernière migration appliquée.
 *
 * @return ?array{version:string, ms:int}
 */
function maire_migrations_run_down(PDO $pdo, ?callable $logger = null): ?array
{
    maire_migrations_ensure_table($pdo);
    $applied = maire_migrations_applied($pdo);
    if (empty($applied)) {
        if ($logger !== null) {
            $logger('Aucune migration à annuler.');
        }
        return null;
    }
    $lastVersion = end($applied);

    $available = maire_migrations_available();
    $target = null;
    foreach ($available as $m) {
        if ($m['version'] === $lastVersion) {
            $target = $m;
            break;
        }
    }

    if ($target === null) {
        throw new RuntimeException("Fichier introuvable pour la migration $lastVersion (fichier supprimé ?).");
    }
    if ($target['down'] === null) {
        throw new RuntimeException("La migration $lastVersion ne définit pas de bloc 'down' (rollback impossible).");
    }

    if ($logger !== null) {
        $logger("◀ Rollback : {$target['version']}");
    }

    $started = microtime(true);
    try {
        $pdo->beginTransaction();
        ($target['down'])($pdo);
        $pdo->prepare('DELETE FROM ' . MAIRE_MIGRATIONS_TABLE . ' WHERE version = :v')->execute(['v' => $target['version']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException("Rollback {$target['version']} échoué : " . $e->getMessage(), 0, $e);
    }

    $ms = (int) ((microtime(true) - $started) * 1000);
    if ($logger !== null) {
        $logger("✔ Rollback effectué ({$ms} ms)");
    }
    return ['version' => $target['version'], 'ms' => $ms];
}

/**
 * Donne un récapitulatif de l'état des migrations (pour la commande `status`).
 *
 * @return array{applied:array<int,string>, pending:array<int,string>}
 */
function maire_migrations_status(PDO $pdo): array
{
    maire_migrations_ensure_table($pdo);
    $applied = maire_migrations_applied($pdo);
    $available = maire_migrations_available();
    $appliedSet = array_flip($applied);
    $pending = [];
    foreach ($available as $m) {
        if (!isset($appliedSet[$m['version']])) {
            $pending[] = $m['version'];
        }
    }
    return ['applied' => $applied, 'pending' => $pending];
}

/**
 * Crée un nouveau fichier de migration vierge.
 */
function maire_migrations_create(string $nom): string
{
    $dir = maire_migrations_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $slug = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($nom)));
    if ($slug === '' || $slug === '_') {
        throw new InvalidArgumentException('Nom de migration invalide.');
    }
    $version = date('Y_m_d_His') . '_' . $slug;
    $path = $dir . DIRECTORY_SEPARATOR . $version . '.php';

    $stub = <<<PHP
<?php
declare(strict_types=1);

/**
 * Migration : $slug
 * Créée le : %s
 */
return [
    'description' => '$slug',
    'up' => static function (PDO \$pdo): void {
        \$pdo->exec("
            -- Vos requêtes ici (CREATE TABLE, ALTER TABLE, INSERT…)
        ");
    },
    'down' => static function (PDO \$pdo): void {
        \$pdo->exec("
            -- Requêtes inverses (DROP TABLE, ALTER TABLE…)
        ");
    },
];
PHP;

    file_put_contents($path, sprintf($stub, date('c')));
    return $path;
}
