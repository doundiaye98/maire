<?php
declare(strict_types=1);

/**
 * Garde d'accès pour les pages /citoyen/ nécessitant une authentification.
 * Redirige vers /citoyen/connexion.php si pas de session citoyen valide.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/citoyen-session.php';

if (!maire_citoyen_session_valid()) {
    $pageCourante = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'profil.php'));
    if ($pageCourante === '' || $pageCourante === '.' || str_contains($pageCourante, '/')) {
        $pageCourante = 'profil.php';
    }
    $apresConnexion = 'citoyen/' . $pageCourante;
    $queryCourante = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($queryCourante !== '') {
        $apresConnexion .= '?' . $queryCourante;
    }

    header('Location: connexion.php?besoin=connexion&apres=' . urlencode($apresConnexion), true, 302);
    exit;
}

if ($pdo !== null) {
    $citoyenAccount = maire_load_citoyen($pdo, (int) ($_SESSION['citoyen_id'] ?? 0));
    if ($citoyenAccount === null || (int) ($citoyenAccount['actif'] ?? 0) !== 1) {
        maire_citoyen_logout();
        header('Location: connexion.php?besoin=desactive', true, 302);
        exit;
    }
}
