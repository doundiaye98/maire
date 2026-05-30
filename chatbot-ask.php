<?php
declare(strict_types=1);

/**
 * Endpoint AJAX du chatbot citoyen (Phase X).
 * Renvoie une réponse JSON à partir d'une question utilisateur.
 *
 * Méthode : POST application/x-www-form-urlencoded
 * Champs : question (string, max 500 chars)
 * Sortie : { trouve, reponse, score, lien_action, libelle_action, suggestions }
 *
 * Rate-limit : 30 questions/minute par IP+session.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/chatbot.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/citoyen-session.php';
require_once __DIR__ . '/includes/super-admin-session.php';
require_once __DIR__ . '/includes/feature-gates.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST requis']);
    exit;
}

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['error' => 'Service indisponible']);
    exit;
}

// Gating (IA assistant = Premium)
if (!maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'ia_assistant')) {
    http_response_code(402);
    echo json_encode(['error' => 'Assistant IA non disponible pour cette commune (palier Premium requis).']);
    exit;
}

if (!maire_rate_limit_allow('chatbot', 30, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Trop de questions. Patientez une minute.']);
    exit;
}

maire_csrf_validate_json(MAIRE_CSRF_SCOPE_CHATBOT);

$question = trim((string) ($_POST['question'] ?? ''));
if ($question === '' || mb_strlen($question) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Question requise (1 à 500 caractères).']);
    exit;
}

$resp = maire_chatbot_repondre($pdo, $question);
$token = maire_chatbot_session_token();
$citoyenId = maire_citoyen_session_valid() ? (int) ($_SESSION['citoyen_id'] ?? 0) : null;
maire_chatbot_log_conversation($pdo, $token, $citoyenId, $question, $resp);

echo json_encode([
    'trouve' => (bool) $resp['trouve'],
    'reponse' => (string) $resp['reponse'],
    'score' => (float) $resp['score'],
    'lien_action' => $resp['lien_action'],
    'libelle_action' => $resp['libelle_action'],
    'suggestions' => $resp['suggestions'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
