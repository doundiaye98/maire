<?php
declare(strict_types=1);

/**
 * Envoi d’un code OTP par SMS pour la réservation d’audience.
 * POST : telephone, csrf (scope audience_maire)
 */
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session-performance.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/otp-sms.php';
require_once __DIR__ . '/../includes/feature-gates.php';

maire_session_configure_ini();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Service indisponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!maire_feature_disponible($pdo, 'audiences_maire')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Fonctionnalité non disponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scope = MAIRE_CSRF_SCOPE_AUDIENCE;
if (!maire_csrf_validate($scope)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jeton de sécurité invalide. Rechargez la page.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_POST['csrf_scope'])) {
    $_POST['csrf_scope'] = $scope;
}
$telephone = trim((string) ($_POST['telephone'] ?? ''));
$err = null;
if (!maire_otp_envoyer($pdo, $telephone, $scope, $err)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $err ?? 'Envoi impossible'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = ['ok' => true, 'message' => 'Code envoyé par SMS.'];
if (function_exists('maire_env') && maire_env('APP_ENV', 'production') === 'development' && !empty($_SESSION['maire_otp_dev_hint'])) {
    $payload['dev_hint'] = (string) $_SESSION['maire_otp_dev_hint'];
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
