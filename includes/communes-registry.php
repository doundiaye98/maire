<?php
declare(strict_types=1);

/**
 * Registre multi-communes (Phase X — scaffold).
 *
 * Permet d'identifier la commune en cours selon :
 *   1. Le hostname (rufisque.mairie.sn → commune 'rufisque')
 *   2. Le path prefix (/rufisque/... → commune 'rufisque')
 *   3. Le paramètre de session (?commune=rufisque pour les tests)
 *   4. La commune marquée 'est_principale' (fallback)
 *
 * IMPORTANT — Roadmap multi-tenant complète :
 *   - Ajouter une colonne commune_id sur chaque table métier
 *     (citoyens, signalements, paiements_services, consultations, etc.)
 *   - Ajouter un middleware qui filtre toutes les requêtes par commune_id courant
 *   - Adapter le branding (logo, couleur primaire) selon la commune active
 *   - Isoler les uploads/ par sous-dossier de commune
 *
 * Ce fichier fournit déjà l'infrastructure d'identification ; le retrofit
 * métier sera fait par phases (table par table) pour limiter les régressions.
 */

function maire_ensure_communes_registry(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS communes_registry (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL,
            nom VARCHAR(120) NOT NULL,
            region VARCHAR(80) NULL,
            pays VARCHAR(60) NOT NULL DEFAULT 'Sénégal',
            hostname VARCHAR(150) NULL,
            path_prefix VARCHAR(60) NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            est_principale TINYINT(1) NOT NULL DEFAULT 0,
            contact_email VARCHAR(190) NULL,
            telephone VARCHAR(40) NULL,
            site_web VARCHAR(190) NULL,
            logo_url VARCHAR(255) NULL,
            couleur_primaire VARCHAR(20) DEFAULT '#0c4a3e',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_commune_code (code),
            INDEX idx_commune_host (hostname),
            INDEX idx_commune_actif (actif)
        )
    ");
    $pdo->exec("
        INSERT IGNORE INTO communes_registry (code, nom, region, pays, actif, est_principale, couleur_primaire)
        VALUES ('rufisque', 'Mairie de Rufisque', 'Dakar', 'Sénégal', 1, 1, '#0c4a3e')
    ");
}

function maire_load_commune_principale(PDO $pdo): ?array
{
    maire_ensure_communes_registry($pdo);
    try {
        $r = $pdo->query("SELECT * FROM communes_registry WHERE est_principale = 1 AND actif = 1 LIMIT 1")->fetch();
        if ($r !== false) {
            return $r;
        }
        $r = $pdo->query("SELECT * FROM communes_registry WHERE actif = 1 LIMIT 1")->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_load_commune_par_code(PDO $pdo, string $code): ?array
{
    try {
        $st = $pdo->prepare("SELECT * FROM communes_registry WHERE code = :c AND actif = 1 LIMIT 1");
        $st->execute(['c' => $code]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_load_commune_par_hostname(PDO $pdo, string $hostname): ?array
{
    try {
        $st = $pdo->prepare("SELECT * FROM communes_registry WHERE hostname = :h AND actif = 1 LIMIT 1");
        $st->execute(['h' => $hostname]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Identifie la commune active pour la requête HTTP courante.
 *
 * Ordre de priorité :
 *   1. $_GET['commune'] (debug / preview pour super-admin)
 *   2. session 'commune_code' (sticky pour un visiteur)
 *   3. hostname dans communes_registry
 *   4. path prefix (/code/... )
 *   5. commune marquée est_principale
 */
function maire_commune_active(PDO $pdo): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (isset($_GET['commune']) && is_string($_GET['commune'])) {
        $c = maire_load_commune_par_code($pdo, trim($_GET['commune']));
        if ($c !== null) {
            $_SESSION['commune_code'] = $c['code'];
            return $cache = $c;
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['commune_code'])) {
        $c = maire_load_commune_par_code($pdo, (string) $_SESSION['commune_code']);
        if ($c !== null) {
            return $cache = $c;
        }
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $c = maire_load_commune_par_hostname($pdo, $host);
        if ($c !== null) {
            return $cache = $c;
        }
    }

    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if (preg_match('#^/([a-z0-9_-]+)/#i', $path, $m) === 1) {
        $c = maire_load_commune_par_code($pdo, strtolower($m[1]));
        if ($c !== null) {
            return $cache = $c;
        }
    }

    return $cache = maire_load_commune_principale($pdo);
}

function maire_liste_communes(PDO $pdo, bool $seulementActives = true): array
{
    maire_ensure_communes_registry($pdo);
    try {
        $sql = 'SELECT * FROM communes_registry';
        if ($seulementActives) {
            $sql .= ' WHERE actif = 1';
        }
        $sql .= ' ORDER BY est_principale DESC, nom ASC';
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
