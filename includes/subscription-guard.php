<?php
declare(strict_types=1);

/**
 * Garde pour comptes personnels (agents / abonnés individuels).
 * L’accès citoyen aux services « commune entière » passe par commune-portal-guard.php.
 */
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/abonnement-actif-sync.php';
require_once __DIR__ . '/compte-mairie.php';
require_once __DIR__ . '/site-paths.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($pdo !== null) {
    maire_ensure_abonnements_compte_mairie_column($pdo);
}

if ($pdo === null || empty($_SESSION['subscriber_id'])) {
    header('Location: ' . maire_login_url('connexion'), true, 302);
    exit;
}

$subscriberAccount = null;

try {
    $stmt = $pdo->prepare("
        SELECT id, email, date_debut, date_fin, plan, role_utilisateur, compte_mairie
        FROM abonnements
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => (int) $_SESSION['subscriber_id']]);
    $abonne = $stmt->fetch();
} catch (Throwable $exception) {
    $stmt = $pdo->prepare("
        SELECT id, email, date_debut, date_fin, plan, role_utilisateur
        FROM abonnements
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => (int) $_SESSION['subscriber_id']]);
    $abonne = $stmt->fetch();
}

if ($abonne === false) {
    session_unset();
    session_destroy();
    header('Location: ' . maire_login_url('connexion'), true, 302);
    exit;
}

maire_sync_abonnement_actif($pdo, (int) $abonne['id']);

if (!maire_abonnement_couvre_aujourdhui($abonne)) {
    session_unset();
    session_destroy();
    header('Location: ' . maire_login_url('expire'), true, 302);
    exit;
}

$_SESSION['subscriber_role'] = (string) ($abonne['role_utilisateur'] ?? 'subscriber');
$_SESSION['subscriber_email'] = (string) ($abonne['email'] ?? ($_SESSION['subscriber_email'] ?? ''));
$idAbo = (int) ($abonne['id'] ?? 0);
$_SESSION['subscriber_compte_mairie'] = isset($abonne['compte_mairie'])
    ? ((int) $abonne['compte_mairie'] === 1)
    : (maire_get_compte_mairie_id($pdo) === $idAbo);

$subscriberAccount = [
    'id' => (int) $abonne['id'],
    'email' => (string) ($abonne['email'] ?? ''),
    'date_debut' => (string) ($abonne['date_debut'] ?? ''),
    'date_fin' => (string) ($abonne['date_fin'] ?? ''),
    'plan' => (string) ($abonne['plan'] ?? 'municipal_standard'),
    'role' => (string) ($abonne['role_utilisateur'] ?? ($_SESSION['subscriber_role'] ?? 'subscriber')),
];
