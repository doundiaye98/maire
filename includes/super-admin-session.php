<?php
declare(strict_types=1);

/**
 * Session « super-admin » utilisée par les pages /admin/* sensibles et /super-admin/*.
 *
 * Elle est ouverte uniquement via /super-admin/login.php (compte + mot de passe)
 * ET — si configuré — depuis une IP de la liste blanche (SUPER_ADMIN_ALLOWED_IPS).
 *
 * Cf. includes/super-admin-account.php pour la gestion du compte éditeur.
 *
 * TTL : 2 h glissantes (rafraîchies à chaque appel valide).
 */
const MAIRE_SUPER_ADMIN_TTL = 7200;

function maire_super_admin_session_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if (empty($_SESSION['maire_super_admin']) || $_SESSION['maire_super_admin'] !== true) {
        return false;
    }
    $ts = (int) ($_SESSION['maire_super_admin_ts'] ?? 0);
    if ($ts <= 0 || (time() - $ts) > MAIRE_SUPER_ADMIN_TTL) {
        unset($_SESSION['maire_super_admin'], $_SESSION['maire_super_admin_ts']);
        return false;
    }
    // Validation IP à chaque requête : si l'admin change de réseau pendant
    // la session, elle est invalidée. Évite le vol de cookie sur réseau Wi-Fi public.
    if (!maire_super_admin_ip_allowed()) {
        unset($_SESSION['maire_super_admin'], $_SESSION['maire_super_admin_ts']);
        if (function_exists('maire_log_warning')) {
            maire_log_warning('super_admin_ip_rejected', [
                'ip' => maire_client_ip(),
                'path' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
        }
        return false;
    }
    $_SESSION['maire_super_admin_ts'] = time();

    return true;
}

function maire_is_super_console(): bool
{
    return !empty($_SESSION['maire_super_admin']) && $_SESSION['maire_super_admin'] === true;
}

/**
 * Retourne l'IP réelle du client en gérant prudemment les reverse-proxy.
 *
 * Ne fait confiance à X-Forwarded-For que si la liste d'IPs proxy de confiance
 * est définie (MAIRE_TRUSTED_PROXIES), sinon retourne uniquement REMOTE_ADDR.
 */
function maire_client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $trusted = (string) (defined('MAIRE_TRUSTED_PROXIES') ? MAIRE_TRUSTED_PROXIES : '');
    if ($trusted === '' && function_exists('maire_env')) {
        $trusted = (string) maire_env('TRUSTED_PROXIES', '');
    }
    if ($trusted === '') {
        return $remote;
    }

    $trustedList = array_filter(array_map('trim', explode(',', $trusted)));
    if (!in_array($remote, $trustedList, true)) {
        return $remote;
    }

    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded === '') {
        return $remote;
    }
    $first = trim((string) explode(',', $forwarded)[0]);
    return filter_var($first, FILTER_VALIDATE_IP) !== false ? $first : $remote;
}

/**
 * Vérifie si l'IP du client est autorisée à accéder à la console super-admin.
 *
 * Retourne true si :
 *   - la variable SUPER_ADMIN_ALLOWED_IPS est vide (= pas de restriction)
 *   - OU l'IP du client correspond à une entrée de la liste (IPv4/IPv6 exact ou range CIDR)
 *
 * Format accepté dans .env :
 *   SUPER_ADMIN_ALLOWED_IPS=41.219.0.0/16, 2001:db8::/32, 88.123.45.67, 127.0.0.1
 */
function maire_super_admin_ip_allowed(): bool
{
    $allowList = function_exists('maire_env') ? (string) maire_env('SUPER_ADMIN_ALLOWED_IPS', '') : '';
    if ($allowList === '') {
        return true; // pas de restriction configurée
    }
    $clientIp = maire_client_ip();
    if ($clientIp === '') {
        return false;
    }
    foreach (explode(',', $allowList) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        if (maire_ip_matches($clientIp, $entry)) {
            return true;
        }
    }
    return false;
}

/**
 * Compare une IP à un pattern (exact ou CIDR).
 *
 * Supporte :
 *  - IP exacte (IPv4 ou IPv6)
 *  - CIDR IPv4 : 192.168.1.0/24
 *  - CIDR IPv6 : 2001:db8::/32
 */
function maire_ip_matches(string $ip, string $pattern): bool
{
    if (!str_contains($pattern, '/')) {
        return $ip === $pattern;
    }
    [$subnet, $bits] = explode('/', $pattern, 2);
    $bits = (int) $bits;

    $ipBin = @inet_pton($ip);
    $subBin = @inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
        return false;
    }
    if ($bits < 0 || $bits > strlen($ipBin) * 8) {
        return false;
    }

    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
        return false;
    }
    if ($remainder === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
    return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subBin[$bytes]) & ord($mask));
}
