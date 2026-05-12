<?php
declare(strict_types=1);

/**
 * Gestion des clés d'API publique (Phase X).
 *
 * Le hash SHA-256 est stocké en base ; la clé brute n'est révélée qu'une fois
 * à la création (façon GitHub / Stripe). Préfixe `pk_live_` ou `pk_test_` pour
 * faciliter la lecture par les développeurs intégrateurs.
 */

function maire_ensure_api_keys_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            libelle VARCHAR(120) NOT NULL,
            cle_hash CHAR(64) NOT NULL,
            cle_prefix CHAR(8) NOT NULL,
            scopes VARCHAR(500) NOT NULL DEFAULT 'public',
            actif TINYINT(1) NOT NULL DEFAULT 1,
            rate_limit_per_min INT NOT NULL DEFAULT 60,
            nb_appels INT NOT NULL DEFAULT 0,
            derniere_utilisation TIMESTAMP NULL DEFAULT NULL,
            cree_par_email VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_api_hash (cle_hash),
            INDEX idx_api_prefix (cle_prefix),
            INDEX idx_api_actif (actif)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_key_id INT NULL,
            endpoint VARCHAR(120) NOT NULL,
            methode VARCHAR(10) NOT NULL DEFAULT 'GET',
            statut_http INT NOT NULL,
            ip_hash CHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            duree_ms INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_apilog_endpoint (endpoint),
            INDEX idx_apilog_date (created_at),
            INDEX idx_apilog_keyid (api_key_id)
        )
    ");
}

/**
 * Crée une clé API et retourne le secret en clair (à montrer une seule fois).
 *
 * @return array{id:int,cle:string} ou null
 */
function maire_creer_api_key(PDO $pdo, string $libelle, string $scopes, int $rateLimit, ?string $auteur, ?string &$errMsg = null): ?array
{
    $libelle = trim($libelle);
    if ($libelle === '' || mb_strlen($libelle) > 120) {
        $errMsg = 'Libellé requis (≤ 120 caractères).';
        return null;
    }
    $cle = 'pk_live_' . strtolower(bin2hex(random_bytes(20)));
    $hash = hash('sha256', $cle);
    $prefix = substr($cle, 0, 8);
    try {
        $st = $pdo->prepare('
            INSERT INTO api_keys (libelle, cle_hash, cle_prefix, scopes, rate_limit_per_min, cree_par_email)
            VALUES (:l, :h, :p, :s, :r, :a)
        ');
        $st->execute([
            'l' => $libelle,
            'h' => $hash,
            'p' => $prefix,
            's' => mb_substr($scopes, 0, 500),
            'r' => max(10, min(1000, $rateLimit)),
            'a' => $auteur !== null ? mb_substr($auteur, 0, 190) : null,
        ]);
        return ['id' => (int) $pdo->lastInsertId(), 'cle' => $cle];
    } catch (Throwable $e) {
        $errMsg = 'Création impossible : ' . $e->getMessage();
        return null;
    }
}

function maire_revoquer_api_key(PDO $pdo, int $id): bool
{
    try {
        $st = $pdo->prepare('UPDATE api_keys SET actif = 0 WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_supprimer_api_key(PDO $pdo, int $id): bool
{
    try {
        $st = $pdo->prepare('DELETE FROM api_keys WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_liste_api_keys(PDO $pdo): array
{
    maire_ensure_api_keys_table($pdo);
    try {
        return $pdo->query('SELECT id, libelle, cle_prefix, scopes, actif, rate_limit_per_min, nb_appels, derniere_utilisation, created_at FROM api_keys ORDER BY created_at DESC')->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Authentifie une requête entrante.
 *
 * @return array|null La ligne api_keys correspondante ou null si refus.
 */
function maire_api_authentifier(PDO $pdo, ?string $cle): ?array
{
    if ($cle === null || $cle === '') {
        return null;
    }
    maire_ensure_api_keys_table($pdo);
    $hash = hash('sha256', $cle);
    try {
        $st = $pdo->prepare('SELECT * FROM api_keys WHERE cle_hash = :h AND actif = 1 LIMIT 1');
        $st->execute(['h' => $hash]);
        $r = $st->fetch();
        if ($r === false) {
            return null;
        }
        $pdo->prepare('UPDATE api_keys SET nb_appels = nb_appels + 1, derniere_utilisation = NOW() WHERE id = :id')
            ->execute(['id' => (int) $r['id']]);
        return $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_api_extraire_cle(): ?string
{
    $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    $headers = array_change_key_case($headers, CASE_LOWER);
    if (!empty($headers['authorization'])) {
        $auth = (string) $headers['authorization'];
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
    }
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim((string) $_SERVER['HTTP_X_API_KEY']);
    }
    if (!empty($_GET['api_key']) && is_string($_GET['api_key'])) {
        return trim($_GET['api_key']);
    }
    return null;
}

function maire_api_log(PDO $pdo, ?int $apiKeyId, string $endpoint, string $methode, int $statutHttp, int $dureeMs): void
{
    try {
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|salt-maire');
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $st = $pdo->prepare('INSERT INTO api_logs (api_key_id, endpoint, methode, statut_http, ip_hash, user_agent, duree_ms) VALUES (:k, :e, :m, :st, :ip, :ua, :d)');
        $st->execute(['k' => $apiKeyId, 'e' => mb_substr($endpoint, 0, 120), 'm' => mb_substr($methode, 0, 10), 'st' => $statutHttp, 'ip' => $ipHash, 'ua' => $ua, 'd' => $dureeMs]);
    } catch (Throwable $e) {
        // tolérant
    }
}

function maire_api_compteurs(PDO $pdo): array
{
    maire_ensure_api_keys_table($pdo);
    $r = ['total_cles' => 0, 'cles_actives' => 0, 'appels_total' => 0, 'appels_24h' => 0];
    try {
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(actif = 1) AS actives, COALESCE(SUM(nb_appels), 0) AS appels FROM api_keys")->fetch();
        if ($row !== false) {
            $r['total_cles'] = (int) $row['total'];
            $r['cles_actives'] = (int) $row['actives'];
            $r['appels_total'] = (int) $row['appels'];
        }
        $r['appels_24h'] = (int) $pdo->query("SELECT COUNT(*) FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (Throwable $e) {
        // tolérant
    }
    return $r;
}
