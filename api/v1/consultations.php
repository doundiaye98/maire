<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/consultations.php';

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$statut = isset($_GET['statut']) ? trim((string) $_GET['statut']) : null;
$offset = ($page - 1) * $limit;

try {
    maire_ensure_consultations_tables($pdo);
    maire_sync_statuts_consultations($pdo);

    $where = ["statut IN ('ouverte','fermee')"];
    $params = [];
    if ($statut === 'ouverte' || $statut === 'fermee') {
        $where = ['statut = :st'];
        $params = ['st' => $statut];
    }
    $sql = 'SELECT id, type, titre, question, description, date_debut, date_fin, statut, multi_choix, nb_options, nb_votes_total, created_at
            FROM consultations WHERE ' . implode(' AND ', $where) . " ORDER BY (statut = 'ouverte') DESC, date_fin DESC LIMIT $limit OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll() ?: [];

    // Pour chaque consultation, joindre les options + résultats si publics + fermée
    foreach ($rows as &$r) {
        $cid = (int) $r['id'];
        $opts = $pdo->prepare('SELECT id, libelle, ordre, nb_votes FROM consultations_options WHERE consultation_id = :c ORDER BY ordre');
        $opts->execute(['c' => $cid]);
        $r['options'] = $opts->fetchAll() ?: [];
    }

    $stT = $pdo->prepare('SELECT COUNT(*) FROM consultations WHERE ' . implode(' AND ', $where));
    $stT->execute($params);
    $total = (int) $stT->fetchColumn();

    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/consultations', 200, [
        'data' => $rows,
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'statut' => $statut],
    ], $apiStart);
} catch (Throwable $e) {
    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/consultations', 500, [
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
    ], $apiStart);
}
