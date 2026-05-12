<?php
declare(strict_types=1);

/**
 * Réglages session pour limiter les blocages sous charge (nombreux visiteurs / onglets).
 * À compléter côté serveur : PHP-FPM (pm.max_children), MySQL (max_connections), cache Redis pour les sessions si > ~100 connexions/s.
 */

function maire_session_configure_ini(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (PHP_VERSION_ID >= 70400) {
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
    }
    if (PHP_VERSION_ID >= 70000) {
        ini_set('session.lazy_write', '1');
    }
}

/**
 * Libère le verrou fichier de session dès que les garde-fous ont fini (requêtes GET).
 * Les pages qui modifient encore $_SESSION après ce garde ne doivent pas l’appeler.
 */
function maire_session_release_after_portail_get(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
