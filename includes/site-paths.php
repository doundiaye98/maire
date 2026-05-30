<?php
declare(strict_types=1);

/**
 * Calcul des chemins URL relatifs au sein du site.
 *
 * Structure du site :
 *   /                       → racine : pages de services publiques (citoyens), login (abonnement.php), espace agent (standard.php)
 *   /presentation/          → pages institutionnelles publiques (M. le Maire, projets, actualités)
 *   /admin/                 → administration générale (tout compte admin, gestion des agents et du référentiel)
 *   /mairie/                → espace réservé au compte institutionnel mairie (abonnement & paiement communal)
 *   /super-admin/           → espace éditeur (entreprise qui héberge le site) — suivi & suspension des abonnements
 *
 * Ces helpers permettent aux ressources (assets, liens menu, redirections de garde)
 * de fonctionner aussi bien depuis la racine que depuis n'importe quel sous-dossier.
 */

/** Liste des sous-dossiers de premier niveau reconnus comme « pages internes ». */
const MAIRE_KNOWN_SUBFOLDERS = ['admin', 'citoyen', 'mairie', 'presentation', 'super-admin'];

/** Logo complet (Open Graph, documents officiels). */
const MAIRE_LOGO_PATH = 'img/logo_mairie_rufisque_est.png';

/** Logo en-tête recadré (affichage type rufisqueouest.org, ~298×88). */
const MAIRE_LOGO_HEADER_PATH = 'img/logo_mairie_rufisque_est-header.png';

/** Emblème seul pour menu mobile. */
const MAIRE_LOGO_MOBILE_PATH = 'img/logo_mairie_rufisque_est-mobile.png';

function maire_logo_url_absolue(string $relativePath = MAIRE_LOGO_PATH): string
{
    return maire_url_absolue($relativePath);
}

function maire_logo_header_url_absolue(): string
{
    return maire_url_absolue(MAIRE_LOGO_HEADER_PATH);
}

function maire_logo_mobile_url_absolue(): string
{
    return maire_url_absolue(MAIRE_LOGO_MOBILE_PATH);
}

/**
 * Retourne le sous-dossier de premier niveau courant ('admin', 'mairie', 'presentation')
 * ou null si on est à la racine du projet.
 */
function maire_current_subfolder(): ?string
{
    $script = (string) ($_SERVER['PHP_SELF'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));
    foreach (MAIRE_KNOWN_SUBFOLDERS as $folder) {
        if (preg_match('#/' . preg_quote($folder, '#') . '/?$#', $dir) === 1) {
            return $folder;
        }
    }

    return null;
}

/**
 * Retourne le préfixe URL à utiliser pour atteindre la racine du site
 * depuis la page actuellement exécutée.
 *   - Depuis une page racine : '' (pas de préfixe)
 *   - Depuis /admin/*, /mairie/*, /presentation/* : '../'
 */
function maire_url_prefix(): string
{
    return maire_current_subfolder() === null ? '' : '../';
}

/**
 * Construit l'URL de la page de connexion (toujours à la racine du projet),
 * avec un éventuel paramètre besoin=… pour expliquer la raison du renvoi.
 */
function maire_login_url(string $besoin = ''): string
{
    $url = maire_url_prefix() . 'abonnement.php';
    if ($besoin !== '') {
        $url .= '?besoin=' . urlencode($besoin);
    }

    return $url;
}

/**
 * Construit l'URL relative d'une page hébergée dans un sous-dossier reconnu,
 * peu importe d'où l'on appelle.
 *
 * Exemple :
 *   maire_subfolder_url('admin', 'abonnements.php')
 *     - depuis racine             : 'admin/abonnements.php'
 *     - depuis /admin/*           : 'abonnements.php'
 *     - depuis /mairie/*          : '../admin/abonnements.php'
 *     - depuis /presentation/*    : '../admin/abonnements.php'
 */
function maire_subfolder_url(string $folder, string $page): string
{
    $folder = trim($folder, '/');
    $page = ltrim($page, '/');
    if (!in_array($folder, MAIRE_KNOWN_SUBFOLDERS, true)) {
        return $page;
    }

    if (maire_current_subfolder() === $folder) {
        return $page;
    }

    return maire_url_prefix() . $folder . '/' . $page;
}

/** Raccourcis lisibles : */
function maire_admin_url(string $page): string
{
    return maire_subfolder_url('admin', $page);
}

function maire_mairie_url(string $page): string
{
    return maire_subfolder_url('mairie', $page);
}

function maire_presentation_url(string $page): string
{
    return maire_subfolder_url('presentation', $page);
}

function maire_super_admin_url(string $page): string
{
    return maire_subfolder_url('super-admin', $page);
}

function maire_citoyen_url(string $page): string
{
    return maire_subfolder_url('citoyen', $page);
}

/** URL d'une page située à la racine du projet (utile depuis un sous-dossier). */
function maire_root_url(string $page): string
{
    return maire_url_prefix() . ltrim($page, '/');
}

/**
 * Construit une URL absolue (avec scheme + host) vers une page racine du site.
 * Indispensable pour les passerelles de paiement et webhooks (return_url, notif_url).
 */
function maire_url_absolue(string $page): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    // Remonte au répertoire racine du projet (en ignorant le sous-dossier actuel).
    $base = str_replace('\\', '/', dirname($script));
    $sub = maire_current_subfolder();
    if ($sub !== null) {
        // Strip trailing /admin, /mairie, etc.
        $base = preg_replace('#/' . preg_quote($sub, '#') . '/?$#', '', $base) ?? $base;
    }
    if ($base === '' || $base === '.') {
        $base = '/';
    }
    if (substr($base, -1) !== '/') {
        $base .= '/';
    }
    return $scheme . '://' . $host . $base . ltrim($page, '/');
}
