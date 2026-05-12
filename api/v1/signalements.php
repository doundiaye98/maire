<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/signalements.php';

maire_api_require_auth($pdo, $apiAuth, '/api/v1/signalements', $apiStart);

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$statut = isset($_GET['statut']) ? trim((string) $_GET['statut']) : null;
$categorie = isset($_GET['categorie']) ? trim((string) $_GET['categorie']) : null;
$offset = ($page - 1) * $limit;

try {
    if (function_exists('maire_ensure_signalements_table')) {
        maire_ensure_signalements_table($pdo);
    }
    $where = [];
    $params = [];
    if ($statut !== null && $statut !== '') {
        $where[] = 'statut = :st';
        $params['st'] = $statut;
    }
    if ($categorie !== null && $categorie !== '') {
        $where[] = 'categorie = :cat';
        $params['cat'] = $categorie;
    }
    $sqlWhere = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    // Anonymisation : pas de citoyen_id, pas de description complète, on tronque
    $sql = 'SELECT id, categorie, statut, titre,
                   LEFT(description, 200) AS resume,
                   latitude, longitude, adresse_libre, visibilite_publique, created_at, updated_at
            FROM signalements' . $sqlWhere . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll() ?: [];

    $stT = $pdo->prepare('SELECT COUNT(*) FROM signalements' . $sqlWhere);
    $stT->execute($params);
    $total = (int) $stT->fetchColumn();

    maire_api_respond($pdo, (int) $apiAuth['id'], '/api/v1/signalements', 200, [
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'statut' => $statut,
            'categorie' => $categorie,
            'note' => 'Données anonymisées (pas de coordonnées de signaleur). Description tronquée à 200 caractères.',
        ],
    ], $apiStart);
} catch (Throwable $e) {
    maire_api_respond($pdo, (int) $apiAuth['id'], '/api/v1/signalements', 500, [
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
    ], $apiStart);
}
