<?php
declare(strict_types=1);

/**
 * Bootstrap commun pour tous les endpoints /api/v1/*.
 *
 * - Force la sortie JSON
 * - Authentifie via header Authorization: Bearer ... ou X-API-Key
 * - Applique un rate-limit basé sur la clé API (par session si non auth)
 * - Loggue chaque requête dans api_logs
 */
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/api-keys.php';
require_once __DIR__ . '/../../includes/maire-rate-limit.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['error' => 'Service indisponible']);
    exit;
}

$apiStart = microtime(true);
$apiKey = maire_api_extraire_cle();
$apiAuth = $apiKey !== null ? maire_api_authentifier($pdo, $apiKey) : null;

function maire_api_respond(PDO $pdo, ?int $keyId, string $endpoint, int $status, array $body, float $start): void
{
    http_response_code($status);
    $dureeMs = (int) round((microtime(true) - $start) * 1000);
    maire_api_log($pdo, $keyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $status, $dureeMs);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function maire_api_require_auth(PDO $pdo, ?array $auth, string $endpoint, float $start): array
{
    if ($auth === null) {
        maire_api_respond($pdo, null, $endpoint, 401, [
            'error' => 'Authentification requise',
            'hint' => 'Envoyez votre clé via le header `Authorization: Bearer <cle>` ou `X-API-Key`.',
        ], $start);
    }
    $limit = (int) ($auth['rate_limit_per_min'] ?? 60);
    if (!maire_rate_limit_allow('api_key_' . (int) $auth['id'], $limit, 60)) {
        maire_api_respond($pdo, (int) $auth['id'], $endpoint, 429, [
            'error' => 'Quota dépassé',
            'rate_limit_per_min' => $limit,
        ], $start);
    }
    return $auth;
}
