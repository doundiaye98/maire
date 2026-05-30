<?php
declare(strict_types=1);

/**
 * Logger applicatif pur PHP — sans dépendance externe (pas de Monolog requis).
 *
 * Caractéristiques :
 *  - Niveaux PSR-3 (debug, info, notice, warning, error, critical, alert, emergency)
 *  - Rotation quotidienne automatique (logs/app-YYYY-MM-DD.log)
 *  - Rétention configurable (par défaut 30 jours)
 *  - Contexte JSON (clé/valeur)
 *  - Tracking optionnel d'un correlation_id (utile pour les requêtes HTTP)
 *  - Helpers raccourcis (maire_log_info, maire_log_error, …)
 *  - Silencieux en cas d'échec d'écriture (jamais d'erreur fatale en cascade)
 *  - Configurable via MAIRE_LOG_LEVEL (env / constante) pour filtrer en prod
 *
 * Usage :
 *   maire_log('info', 'Document créé', ['id' => $id, 'titre' => $titre]);
 *   maire_log_warning('Tentative login invalide', ['email' => $email]);
 *
 * Le dossier logs/ est créé automatiquement avec .htaccess (Require all denied)
 * pour empêcher l'accès HTTP direct aux fichiers de log.
 */

const MAIRE_LOG_LEVELS = [
    'debug'     => 100,
    'info'      => 200,
    'notice'    => 250,
    'warning'   => 300,
    'error'     => 400,
    'critical'  => 500,
    'alert'     => 550,
    'emergency' => 600,
];

const MAIRE_LOG_DIR = 'logs';
const MAIRE_LOG_RETENTION_DAYS = 30;

/**
 * Indique si l'application tourne en environnement de développement.
 *
 * Vérifie successivement :
 *  - la variable d'environnement APP_ENV
 *  - la variable $_ENV['APP_ENV']
 *  - la variable $_SERVER['APP_ENV']
 *
 * Considère comme « dev » : 'dev', 'development', 'local'.
 * Tout autre valeur (y compris vide ou 'production') retourne false.
 */
function maire_is_dev_env(): bool
{
    $candidate = getenv('APP_ENV');
    if ($candidate === false || $candidate === '') {
        $candidate = $_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? '');
    }
    $candidate = strtolower(trim((string) $candidate));

    return in_array($candidate, ['dev', 'development', 'local'], true);
}

/**
 * Retourne le chemin absolu du dossier logs, le crée si absent, pose un .htaccess.
 */
function maire_log_directory(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        $base = __DIR__ . DIRECTORY_SEPARATOR . '..';
    }
    $dir = $base . DIRECTORY_SEPARATOR . MAIRE_LOG_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $hta = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_dir($dir) && !file_exists($hta)) {
        @file_put_contents(
            $hta,
            "Require all denied\nOptions -Indexes\n<FilesMatch \"\\.log$\">\n  Require all denied\n</FilesMatch>\n"
        );
    }

    return $dir;
}

/**
 * Niveau minimum de log (les niveaux inférieurs sont ignorés).
 * Définissable via constante MAIRE_LOG_LEVEL ou variable d'env du même nom.
 * Par défaut : 'debug' en dev, 'info' en prod (détecté via APP_ENV).
 */
function maire_log_min_level(): int
{
    static $level = null;
    if ($level !== null) {
        return $level;
    }

    $raw = '';
    if (defined('MAIRE_LOG_LEVEL')) {
        $raw = strtolower((string) constant('MAIRE_LOG_LEVEL'));
    } elseif (($env = getenv('MAIRE_LOG_LEVEL')) !== false && $env !== '') {
        $raw = strtolower($env);
    } else {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        $raw = $appEnv === 'production' ? 'info' : 'debug';
    }

    $level = MAIRE_LOG_LEVELS[$raw] ?? MAIRE_LOG_LEVELS['info'];
    return $level;
}

/**
 * Correlation ID — identifiant unique de la requête, utile pour grouper
 * les logs d'une même action utilisateur.
 */
function maire_log_correlation_id(): string
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    try {
        $id = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $id = substr(md5(uniqid('cid', true)), 0, 12);
    }
    return $id;
}

/**
 * Écrit une ligne dans le fichier de log du jour.
 *
 * @param string $level  Niveau PSR-3 (debug|info|notice|warning|error|critical|alert|emergency)
 * @param string $message Message texte (peut contenir des placeholders {key})
 * @param array<string,mixed> $context Données structurées attachées
 */
function maire_log(string $level, string $message, array $context = []): void
{
    $level = strtolower($level);
    if (!isset(MAIRE_LOG_LEVELS[$level])) {
        $level = 'info';
    }
    if (MAIRE_LOG_LEVELS[$level] < maire_log_min_level()) {
        return;
    }

    foreach ($context as $key => $value) {
        $placeholder = '{' . $key . '}';
        if (strpos($message, $placeholder) !== false && (is_scalar($value) || $value === null)) {
            $message = str_replace($placeholder, (string) $value, $message);
        }
    }

    $line = [
        'ts'             => date('c'),
        'level'          => strtoupper($level),
        'cid'            => maire_log_correlation_id(),
        'message'        => $message,
        'context'        => $context,
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
        'uri'            => $_SERVER['REQUEST_URI'] ?? null,
        'method'         => $_SERVER['REQUEST_METHOD'] ?? null,
    ];
    if (PHP_SAPI === 'cli') {
        $line['sapi'] = 'cli';
    }

    $dir = maire_log_directory();
    $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';

    $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode(['ts' => date('c'), 'level' => 'ERROR', 'message' => 'log_encode_failure']);
    }

    @file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function maire_log_debug(string $message, array $context = []): void    { maire_log('debug', $message, $context); }
function maire_log_info(string $message, array $context = []): void     { maire_log('info', $message, $context); }
function maire_log_notice(string $message, array $context = []): void   { maire_log('notice', $message, $context); }
function maire_log_warning(string $message, array $context = []): void  { maire_log('warning', $message, $context); }
function maire_log_error(string $message, array $context = []): void    { maire_log('error', $message, $context); }
function maire_log_critical(string $message, array $context = []): void { maire_log('critical', $message, $context); }

/**
 * Logue une exception complète (message + trace).
 */
function maire_log_exception(Throwable $e, string $contextMessage = ''): void
{
    maire_log('error', $contextMessage !== '' ? $contextMessage . ' : ' . $e->getMessage() : $e->getMessage(), [
        'exception_class' => get_class($e),
        'file'            => $e->getFile(),
        'line'            => $e->getLine(),
        'trace'           => array_slice(explode("\n", $e->getTraceAsString()), 0, 20),
    ]);
}

/**
 * Purge les fichiers de log plus vieux que MAIRE_LOG_RETENTION_DAYS jours.
 * À appeler périodiquement (ex : cron quotidien) — non bloquant.
 */
function maire_log_purge_old(): int
{
    $dir = maire_log_directory();
    if (!is_dir($dir)) {
        return 0;
    }
    $cutoff = time() - (MAIRE_LOG_RETENTION_DAYS * 86400);
    $deleted = 0;
    foreach ((array) glob($dir . DIRECTORY_SEPARATOR . 'app-*.log') as $path) {
        if (!is_string($path) || !is_file($path)) {
            continue;
        }
        if ((int) @filemtime($path) < $cutoff) {
            if (@unlink($path)) {
                $deleted++;
            }
        }
    }
    return $deleted;
}

/**
 * Installe un handler PHP pour logger automatiquement les erreurs/exceptions
 * non capturées. À appeler dans un bootstrap commun (ex : header.php).
 */
function maire_log_install_handlers(): void
{
    static $installed = false;
    if ($installed) {
        return;
    }
    $installed = true;

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        $map = [
            E_ERROR             => 'error',
            E_WARNING           => 'warning',
            E_NOTICE            => 'notice',
            E_USER_ERROR        => 'error',
            E_USER_WARNING      => 'warning',
            E_USER_NOTICE       => 'notice',
            E_RECOVERABLE_ERROR => 'error',
            E_DEPRECATED        => 'notice',
            E_USER_DEPRECATED   => 'notice',
        ];
        $level = $map[$severity] ?? 'warning';
        maire_log($level, $message, [
            'severity' => $severity,
            'file'     => $file,
            'line'     => $line,
        ]);
        return false; // laisse PHP gérer normalement
    });

    set_exception_handler(static function (Throwable $e): void {
        maire_log_exception($e, 'Uncaught exception');
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array($err['type'] ?? 0, $fatal, true)) {
            maire_log('critical', 'Fatal PHP shutdown', $err);
        }
    });
}
