<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

try {
    $rows = [];
    $cols = $pdo->query("SHOW COLUMNS FROM actualites")->fetchAll(PDO::FETCH_COLUMN);
    $has = fn($c) => in_array($c, $cols, true);

    $select = ['id'];
    foreach (['titre', 'slug', 'extrait', 'contenu', 'image', 'date_publication', 'created_at'] as $c) {
        if ($has($c)) $select[] = $c;
    }
    $where = '';
    if ($has('publie')) {
        $where = 'WHERE publie = 1';
    } elseif ($has('statut')) {
        $where = "WHERE statut = 'publie'";
    }
    $order = $has('date_publication') ? 'ORDER BY date_publication DESC' : ($has('created_at') ? 'ORDER BY created_at DESC' : '');
    $sql = 'SELECT ' . implode(',', $select) . ' FROM actualites ' . $where . ' ' . $order . " LIMIT $limit OFFSET $offset";
    $rows = $pdo->query($sql)->fetchAll() ?: [];

    $totalSql = 'SELECT COUNT(*) FROM actualites ' . $where;
    $total = (int) $pdo->query($totalSql)->fetchColumn();

    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/actualites', 200, [
        'data' => $rows,
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    ], $apiStart);
} catch (Throwable $e) {
    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/actualites', 500, [
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
    ], $apiStart);
}
