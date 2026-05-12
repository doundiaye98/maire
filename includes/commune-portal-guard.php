<?php
declare(strict_types=1);

/**
 * Garde d’accès aux services numériques ouverts à toute la commune.
 * Définir avant l’inclusion : $mairePortalMinPalier = 'simple' | 'standard' | 'premium'
 * (défaut : standard — état civil numérique, portail communal, suivi, reçus).
 */

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/abonnement-actif-sync.php';
require_once __DIR__ . '/commune-abonnement.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$minPalier = $mairePortalMinPalier ?? 'standard';
if (!in_array($minPalier, ['simple', 'standard', 'premium'], true)) {
    $minPalier = 'standard';
}

if ($pdo === null) {
    header('Location: index.php?commune=indispo', true, 302);
    exit;
}

$palierCommune = maire_commune_palier_effectif($pdo);

if ($palierCommune === null) {
    $palierCommune = 'simple';
}

$GLOBALS['maire_commune_palier'] = $palierCommune;

if (!maire_palier_couvre($palierCommune, $minPalier)) {
    header('Location: index.php?commune=standard_requis', true, 302);
    exit;
}

$row = maire_load_commune_abonnement_row($pdo);
if ($row !== null) {
    $row = maire_sync_commune_abonnement_actif($pdo, $row);
}

$subscriberAccount = null;

if (!empty($_SESSION['subscriber_id'])) {
    try {
        $stmt = $pdo->prepare('
            SELECT id, email, date_debut, date_fin, plan, role_utilisateur
            FROM abonnements
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => (int) $_SESSION['subscriber_id']]);
        $abonne = $stmt->fetch();
        if ($abonne !== false) {
            maire_sync_abonnement_actif($pdo, (int) $abonne['id']);
            $stmt->execute(['id' => (int) $_SESSION['subscriber_id']]);
            $abonne = $stmt->fetch();
            if ($abonne !== false && maire_abonnement_couvre_aujourdhui($abonne)) {
                $_SESSION['subscriber_role'] = (string) ($abonne['role_utilisateur'] ?? 'subscriber');
                $_SESSION['subscriber_email'] = (string) ($abonne['email'] ?? ($_SESSION['subscriber_email'] ?? ''));
                $subscriberAccount = [
                    'id' => (int) $abonne['id'],
                    'email' => (string) ($abonne['email'] ?? ''),
                    'date_debut' => (string) ($abonne['date_debut'] ?? ''),
                    'date_fin' => (string) ($abonne['date_fin'] ?? ''),
                    'plan' => (string) ($abonne['plan'] ?? ''),
                    'role' => (string) ($abonne['role_utilisateur'] ?? ($_SESSION['subscriber_role'] ?? 'subscriber')),
                ];
            }
        }
    } catch (Throwable $e) {
        $subscriberAccount = null;
    }
}

if ($subscriberAccount === null && $row !== null) {
    $subscriberAccount = [
        'id' => 0,
        'email' => '',
        'date_debut' => (string) ($row['date_debut'] ?? ''),
        'date_fin' => (string) ($row['date_fin'] ?? ''),
        'plan' => (string) ($row['plan'] ?? 'municipal_standard'),
        'role' => 'citoyen',
    ];
}

if ($subscriberAccount === null) {
    header('Location: index.php?commune=config', true, 302);
    exit;
}

require_once __DIR__ . '/session-performance.php';
maire_session_release_after_portail_get();
