<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/paiements.php';

try {
    $catalogue = maire_paiements_catalogue();
    $services = [];
    foreach ($catalogue as $code => $s) {
        $services[] = [
            'code' => $s['code'],
            'libelle' => $s['libelle'],
            'categorie' => $s['categorie'],
            'description' => $s['description'],
            'montant' => (int) $s['prix'],
            'devise' => 'XOF',
            'delai' => $s['delai'] ?? null,
            'url_paiement' => '/payer.php?service=' . urlencode($code),
        ];
    }

    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/paiements', 200, [
        'data' => $services,
        'meta' => [
            'total' => count($services),
            'categories' => array_values(array_unique(array_map(fn($s) => $s['categorie'], $services))),
            'note' => 'Catalogue en lecture seule. L’initiation d’un paiement doit passer par /payer.php côté navigateur.',
        ],
    ], $apiStart);
} catch (Throwable $e) {
    maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, '/api/v1/paiements', 500, [
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
    ], $apiStart);
}
