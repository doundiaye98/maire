<?php
declare(strict_types=1);

/**
 * Loader de variables d'environnement (.env) — pur PHP, sans dépendance externe.
 *
 * Caractéristiques :
 *  - Parse un fichier .env style "KEY=VALUE", supporte commentaires (#) et lignes vides
 *  - Supporte les guillemets simples / doubles : KEY="valeur avec espaces"
 *  - Ne remplace JAMAIS une variable déjà définie par le système (priorité à l'OS)
 *  - Charge les variables dans $_ENV, $_SERVER et getenv()
 *  - Génère également les constantes MAIRE_* attendues par le code legacy
 *
 * Usage :
 *   require __DIR__ . '/includes/env-loader.php';
 *   maire_env_load(__DIR__ . '/.env');
 *   maire_env_bridge_to_constants();
 *
 *   $apiKey = maire_env('WAVE_API_KEY', '');
 */

if (!defined('MAIRE_ENV_LOADED')) {
    define('MAIRE_ENV_LOADED', true);
}

/**
 * Charge un fichier .env s'il existe.
 *
 * @param string $path     Chemin absolu du fichier .env
 * @param bool   $override Si true, écrase les variables déjà définies (déconseillé en prod)
 * @return bool true si le fichier a été chargé, false s'il n'existe pas
 */
function maire_env_load(string $path, bool $override = false): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        // Supprime guillemets entourants si présents
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    // décode quelques séquences usuelles dans les double-quotes
                    $value = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value);
                }
            }
        }

        // Respecte les variables déjà définies par l'OS / Apache, sauf override explicite
        $alreadySet = getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER);
        if ($alreadySet && !$override) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    return true;
}

/**
 * Récupère une variable d'environnement, avec valeur par défaut.
 */
function maire_env(string $key, $default = null)
{
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
}

/**
 * Convertit une variable d'env en booléen (true/false/yes/no/1/0/on/off).
 */
function maire_env_bool(string $key, bool $default = false): bool
{
    $val = maire_env($key);
    if ($val === null) {
        return $default;
    }
    $normalized = strtolower(trim((string) $val));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Convertit une variable d'env en entier.
 */
function maire_env_int(string $key, int $default = 0): int
{
    $val = maire_env($key);
    return $val === null ? $default : (int) $val;
}

/**
 * Définit les constantes MAIRE_* attendues par le code legacy à partir des variables d'env.
 *
 * Cette fonction doit être appelée APRÈS maire_env_load(),
 * et AVANT l'inclusion de paiement-providers.php / sms-provider.php.
 */
function maire_env_bridge_to_constants(): void
{
    // ── Paiement : Wave ────────────────────────────────────────────────
    if (!defined('MAIRE_WAVE_API_KEY')) {
        define('MAIRE_WAVE_API_KEY', (string) maire_env('WAVE_API_KEY', ''));
    }
    if (!defined('MAIRE_WAVE_WEBHOOK_SECRET')) {
        define('MAIRE_WAVE_WEBHOOK_SECRET', (string) maire_env('WAVE_WEBHOOK_SECRET', ''));
    }

    // ── Paiement : Orange Money ────────────────────────────────────────
    if (!defined('MAIRE_ORANGE_MERCHANT_KEY')) {
        define('MAIRE_ORANGE_MERCHANT_KEY', (string) maire_env('ORANGE_MONEY_MERCHANT_KEY', maire_env('ORANGE_MONEY_MERCHANT_ID', '')));
    }
    if (!defined('MAIRE_ORANGE_AUTH_HEADER')) {
        define('MAIRE_ORANGE_AUTH_HEADER', (string) maire_env('ORANGE_MONEY_AUTH_HEADER', ''));
    }
    if (!defined('MAIRE_ORANGE_CLIENT_ID')) {
        define('MAIRE_ORANGE_CLIENT_ID', (string) maire_env('ORANGE_MONEY_CLIENT_ID', maire_env('ORANGE_MONEY_API_KEY', '')));
    }
    if (!defined('MAIRE_ORANGE_CLIENT_SECRET')) {
        define('MAIRE_ORANGE_CLIENT_SECRET', (string) maire_env('ORANGE_MONEY_CLIENT_SECRET', maire_env('ORANGE_MONEY_API_SECRET', '')));
    }

    // ── Paiement : Free Money ──────────────────────────────────────────
    if (!defined('MAIRE_FREE_MONEY_API_KEY')) {
        define('MAIRE_FREE_MONEY_API_KEY', (string) maire_env('FREE_MONEY_API_KEY', ''));
    }
    if (!defined('MAIRE_FREE_MONEY_API_SECRET')) {
        define('MAIRE_FREE_MONEY_API_SECRET', (string) maire_env('FREE_MONEY_API_SECRET', ''));
    }
    if (!defined('MAIRE_FREE_MONEY_ENDPOINT')) {
        define('MAIRE_FREE_MONEY_ENDPOINT', (string) maire_env('FREE_MONEY_ENDPOINT', ''));
    }

    // ── Webhook commun ─────────────────────────────────────────────────
    if (!defined('MAIRE_PAIEMENT_WEBHOOK_SECRET')) {
        $secret = (string) maire_env('PAIEMENT_WEBHOOK_SECRET', '');
        if ($secret === '') {
            $secret = 'maire-demo-webhook-secret-change-me';
        }
        define('MAIRE_PAIEMENT_WEBHOOK_SECRET', $secret);
    }

    // ── SMS ────────────────────────────────────────────────────────────
    if (!defined('MAIRE_SMS_PROVIDER')) {
        define('MAIRE_SMS_PROVIDER', (string) maire_env('SMS_PROVIDER', 'log'));
    }
    if (!defined('MAIRE_SMS_ORANGE_TOKEN')) {
        define('MAIRE_SMS_ORANGE_TOKEN', (string) maire_env('SMS_API_KEY', ''));
    }
    if (!defined('MAIRE_SMS_ORANGE_SENDER')) {
        define('MAIRE_SMS_ORANGE_SENDER', (string) maire_env('SMS_SENDER_ID', 'tel:+221000000000'));
    }
    if (!defined('MAIRE_SMS_TWILIO_SID')) {
        define('MAIRE_SMS_TWILIO_SID', (string) maire_env('SMS_API_KEY', ''));
    }
    if (!defined('MAIRE_SMS_TWILIO_TOKEN')) {
        define('MAIRE_SMS_TWILIO_TOKEN', (string) maire_env('SMS_API_SECRET', ''));
    }
    if (!defined('MAIRE_SMS_TWILIO_FROM')) {
        define('MAIRE_SMS_TWILIO_FROM', (string) maire_env('SMS_SENDER_ID', ''));
    }

    // ── SMTP / Email ───────────────────────────────────────────────────
    if (!defined('MAIRE_MAIL_HOST')) {
        define('MAIRE_MAIL_HOST', (string) maire_env('MAIL_HOST', ''));
    }
    if (!defined('MAIRE_MAIL_PORT')) {
        define('MAIRE_MAIL_PORT', maire_env_int('MAIL_PORT', 587));
    }
    if (!defined('MAIRE_MAIL_USERNAME')) {
        define('MAIRE_MAIL_USERNAME', (string) maire_env('MAIL_USERNAME', ''));
    }
    if (!defined('MAIRE_MAIL_PASSWORD')) {
        define('MAIRE_MAIL_PASSWORD', (string) maire_env('MAIL_PASSWORD', ''));
    }
    if (!defined('MAIRE_MAIL_FROM_NAME')) {
        define('MAIRE_MAIL_FROM_NAME', (string) maire_env('MAIL_FROM_NAME', 'Mairie de Rufisque-Est'));
    }
    if (!defined('MAIRE_MAIL_FROM_EMAIL')) {
        define('MAIRE_MAIL_FROM_EMAIL', (string) maire_env('MAIL_FROM_EMAIL', 'Rufisquest02@gmail.com'));
    }
    if (!defined('MAIRE_MAIL_ENCRYPTION')) {
        define('MAIRE_MAIL_ENCRYPTION', strtolower((string) maire_env('MAIL_ENCRYPTION', 'tls')));
    }

    // ── Sécurité (super-admin IP allowlist + proxys de confiance) ─────
    if (!defined('MAIRE_SUPER_ADMIN_ALLOWED_IPS')) {
        define('MAIRE_SUPER_ADMIN_ALLOWED_IPS', (string) maire_env('SUPER_ADMIN_ALLOWED_IPS', ''));
    }
    if (!defined('MAIRE_TRUSTED_PROXIES')) {
        define('MAIRE_TRUSTED_PROXIES', (string) maire_env('TRUSTED_PROXIES', ''));
    }

    // ── Base de données (lue par config/database.php si présent) ──────
    if (!defined('MAIRE_DB_HOST')) {
        define('MAIRE_DB_HOST', (string) maire_env('DB_HOST', 'localhost'));
    }
    if (!defined('MAIRE_DB_PORT')) {
        define('MAIRE_DB_PORT', maire_env_int('DB_PORT', 3306));
    }
    if (!defined('MAIRE_DB_NAME')) {
        define('MAIRE_DB_NAME', (string) maire_env('DB_NAME', 'mairie_senegal'));
    }
    if (!defined('MAIRE_DB_USER')) {
        define('MAIRE_DB_USER', (string) maire_env('DB_USER', 'root'));
    }
    if (!defined('MAIRE_DB_PASS')) {
        define('MAIRE_DB_PASS', (string) maire_env('DB_PASS', ''));
    }
}

/**
 * Auto-bootstrap : charge .env depuis la racine projet et bridge les constantes.
 * À appeler depuis includes/header.php le plus tôt possible.
 */
function maire_env_bootstrap(): void
{
    $root = dirname(__DIR__);
    maire_env_load($root . DIRECTORY_SEPARATOR . '.env');
    maire_env_bridge_to_constants();
}
