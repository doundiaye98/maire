<?php
declare(strict_types=1);

/**
 * Point d'entrée /api/v1/ — documentation de découverte des endpoints.
 * Pas d'authentification nécessaire ; cet endpoint sert de manifeste.
 */
require __DIR__ . '/_bootstrap.php';

$endpoint = '/api/v1/';
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . preg_replace('#/index\.php$#', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

maire_api_respond($pdo, $apiAuth !== null ? (int) $apiAuth['id'] : null, $endpoint, 200, [
    'name' => 'Mairie API',
    'version' => '1.0.0',
    'description' => 'API publique de la mairie : actualités, documents, signalements (lecture seule), consultations, paiements.',
    'authentification' => [
        'methode' => 'Bearer token ou X-API-Key',
        'obtenir' => 'Demandez une clé à l’administration de la mairie (espace admin > API).',
    ],
    'endpoints' => [
        ['method' => 'GET', 'path' => $base, 'desc' => 'Manifeste (cet endpoint)'],
        ['method' => 'GET', 'path' => $base . 'actualites', 'desc' => 'Liste des actualités publiées'],
        ['method' => 'GET', 'path' => $base . 'documents', 'desc' => 'Documents publics (PDF, formulaires)'],
        ['method' => 'GET', 'path' => $base . 'signalements', 'desc' => 'Signalements citoyens (anonymisés)', 'auth_required' => true],
        ['method' => 'GET', 'path' => $base . 'consultations', 'desc' => 'Consultations citoyennes publiques'],
        ['method' => 'GET', 'path' => $base . 'paiements', 'desc' => 'Catalogue des services payants'],
    ],
    'limits' => [
        'rate_limit_default_per_min' => 60,
        'pagination_max' => 100,
    ],
    'support' => $base . '../../contact.php',
], $apiStart);
