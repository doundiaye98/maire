<?php
declare(strict_types=1);

/**
 * Endpoint webhook appelé par Orange Money / Wave après confirmation du paiement.
 *
 * Format attendu (commun aux deux providers en simplification) :
 *   POST /paiement-webhook.php?signature=<MAIRE_PAIEMENT_WEBHOOK_SECRET>
 *   Body JSON : { "reference": "PAY-...", "statut": "paye|echec", "provider_reference": "...", "amount": 25000, ... }
 *
 * Réponses :
 *   200 { ok: true }       — accusé de réception
 *   400 { error: "..." }   — payload invalide
 *   401 { error: "..." }   — signature invalide
 *   404 { error: "..." }   — référence inconnue
 *   422 { error: "..." }   — transition refusée
 */
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/paiements.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST requis']);
    exit;
}

if ($pdo === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Base de données indisponible']);
    exit;
}

$signature = (string) ($_GET['signature'] ?? ($_SERVER['HTTP_X_MAIRE_SIGNATURE'] ?? ''));
$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON invalide']);
    exit;
}

if (!maire_paiement_webhook_authentique(getallheaders() ?: [], $payload, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Signature webhook invalide']);
    exit;
}

$ref = trim((string) ($payload['reference'] ?? ''));
$nouveauStatut = strtolower(trim((string) ($payload['statut'] ?? '')));
if ($ref === '' || !in_array($nouveauStatut, ['paye', 'echec'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Champs reference et statut (paye|echec) requis']);
    exit;
}

$paie = maire_paiement_load_by_reference($pdo, $ref);
if ($paie === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Référence inconnue']);
    exit;
}

if (!in_array((string) $paie['statut'], ['initie', 'en_attente'], true)) {
    // Idempotent : on accepte sans rejouer
    echo json_encode(['ok' => true, 'note' => 'Transaction déjà finalisée', 'statut_actuel' => $paie['statut']]);
    exit;
}

$id = (int) $paie['id'];
$ok = $nouveauStatut === 'paye'
    ? maire_paiement_marquer_paye($pdo, $id, $payload)
    : maire_paiement_marquer_echec($pdo, $id, $payload);

if (!$ok) {
    http_response_code(422);
    echo json_encode(['error' => 'Transition refusée']);
    exit;
}

echo json_encode(['ok' => true, 'reference' => $ref, 'nouveau_statut' => $nouveauStatut]);
