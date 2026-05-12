<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/documents-publics.php';

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$categorie = isset($_GET['categorie']) ? trim((string) $_GET['categorie']) : null;
$offset = ($page - 1) * $limit;

try {
    if (function_exists('maire_ensure_documents_publics_table')) {
        maire_ensure_documents_publics_table($pdo);
    }
    $where = ['publie = 1'];
    $params = [];
    if ($categorie !== null && $categorie !== '') {
        $where[] = 'categorie = :cat';
        $params['cat'] = $categorie;
    }
    $sql = 'SELECT id, categorie, titre, description, fichier_nom_original, fichier_taille, mime_type, nb_telechargements, created_at
            FROM documents_publics WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll() ?: [];

    // Construire l'URL de téléchargement
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . preg_replace('#/api/v1/.*$#', '/telecharger-document.php', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach ($rows as &$r) {
        $r['url_telechargement'] = $base . '?id=' . (int) $r['id'];
    }

    $totalSql = 'SELECT COUNT(*) FROM documents_publics WHERE ' . implode(' AND ', $where);
    $stT = $pdo->prepare($totalSql);
    $stT->execute($params);
    $total = (int) $stT->fetchColumn();

    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/documents', 200, [
        'data' => $rows,
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'categorie' => $categorie],
    ], $apiStart);
} catch (Throwable $e) {
    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/documents', 500, [
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
    ], $apiStart);
}
