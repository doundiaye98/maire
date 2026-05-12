<?php
declare(strict_types=1);

/**
 * Endpoint de téléchargement public d'un document municipal.
 * - Vérifie que le document existe et est publié
 * - Incrémente le compteur
 * - Sert le fichier avec les bons headers (anti-MIME-sniffing)
 * - Bloque les path traversal et l'accès aux fichiers non publiés
 */
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/documents-publics.php';

if ($pdo === null) {
    http_response_code(503);
    echo 'Service indisponible.';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Identifiant invalide.';
    exit;
}

$ok = maire_servir_document($pdo, $id, true);
if (!$ok) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<main><section class="section-shell page-intro"><div class="container"><article class="card"><h1>Document introuvable</h1><p>Ce document n’existe plus ou n’est plus disponible publiquement.</p><div class="detail-actions"><a class="btn btn-primary" href="documents.php">Retour à la bibliothèque</a></div></article></div></section></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

exit;
