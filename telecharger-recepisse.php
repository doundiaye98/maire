<?php
declare(strict_types=1);
$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require_once __DIR__ . '/includes/etat-civil-demande.php';

$reference = maire_normaliser_reference_etat_civil((string) ($_GET['ref'] ?? ''));
if ($reference === '' || !isset($pdo) || $pdo === null) {
    http_response_code(400);
    echo 'Reference invalide.';
    exit;
}

$demande = maire_trouver_demande_etat_civil($pdo, $reference);

if ($demande === false) {
    http_response_code(404);
    echo 'Dossier introuvable.';
    exit;
}

$lines = [];
$lines[] = 'MAIRIE DE RUFISQUE-EST';
$lines[] = 'Recepisse numerique de demande - Etat civil';
$lines[] = str_repeat('-', 48);
$lines[] = 'Reference dossier : ' . (string) $demande['reference_dossier'];
$lines[] = 'Type demande     : ' . maire_libelle_type_demande_etat_civil((string) $demande['type_demande']);
$lines[] = 'Demandeur        : ' . (string) $demande['nom_complet'];
$lines[] = 'Email            : ' . (string) $demande['email'];
$lines[] = 'Telephone        : ' . ((string) $demande['telephone'] !== '' ? (string) $demande['telephone'] : 'Non renseigne');
$lines[] = 'Statut           : ' . maire_libelle_statut_demande_etat_civil((string) $demande['statut']);
$lines[] = 'Date de depot    : ' . (string) date('d/m/Y H:i', strtotime((string) $demande['created_at']));
$lines[] = str_repeat('-', 48);
$lines[] = 'Ce recepisse est genere automatiquement par l application.';

$content = implode("\n", $lines);
$filename = 'recepisse_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $demande['reference_dossier']) . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $content;
exit;
