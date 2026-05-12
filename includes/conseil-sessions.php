<?php
declare(strict_types=1);

/**
 * Sessions du conseil municipal — Phase X (streaming).
 *
 * Permet à la mairie de :
 *   - Annoncer une prochaine session (date, ordre du jour)
 *   - Embed un live (YouTube/Vimeo/Twitch) au moment de la séance
 *   - Conserver le replay et joindre le procès-verbal
 *
 * Les URLs embed sont validées et converties au format /embed/ standard pour
 * les plateformes connues (anti-XSS via iframe sandboxée).
 */

const MAIRE_CONSEIL_STATUTS = [
    'annonce'   => 'Annoncée',
    'en_direct' => 'En direct',
    'replay'    => 'Replay disponible',
    'archive'   => 'Archivée',
    'annule'    => 'Annulée',
];

const MAIRE_CONSEIL_PLATEFORMES = [
    'youtube' => 'YouTube',
    'vimeo'   => 'Vimeo',
    'twitch'  => 'Twitch',
    'autre'   => 'Autre (URL générique)',
];

function maire_ensure_conseil_sessions_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conseil_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(200) NOT NULL,
            description TEXT NULL,
            date_session DATETIME NOT NULL,
            duree_minutes INT NOT NULL DEFAULT 90,
            statut ENUM('annonce', 'en_direct', 'replay', 'archive', 'annule') NOT NULL DEFAULT 'annonce',
            plateforme ENUM('youtube', 'vimeo', 'twitch', 'autre') NOT NULL DEFAULT 'youtube',
            embed_url VARCHAR(500) NULL,
            ordre_du_jour TEXT NULL,
            proces_verbal_url VARCHAR(500) NULL,
            nb_vues INT NOT NULL DEFAULT 0,
            cree_par_email VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_conseil_statut (statut),
            INDEX idx_conseil_date (date_session)
        )
    ");
}

function maire_conseil_libelle_statut(string $code): string
{
    return MAIRE_CONSEIL_STATUTS[$code] ?? ucfirst($code);
}

function maire_conseil_libelle_plateforme(string $code): string
{
    return MAIRE_CONSEIL_PLATEFORMES[$code] ?? ucfirst($code);
}

function maire_conseil_classe_badge(string $statut): string
{
    return match ($statut) {
        'en_direct' => 'std-feed-badge--success',
        'replay'    => 'std-feed-badge',
        'annonce'   => 'std-feed-badge',
        'archive'   => 'std-feed-badge',
        'annule'    => 'std-feed-badge--warning',
        default     => 'std-feed-badge',
    };
}

/**
 * Convertit une URL YouTube/Vimeo/Twitch en URL embed normalisée.
 * Retourne null si l'URL n'est pas reconnue ou non sûre.
 */
function maire_conseil_normaliser_embed(string $url, string $plateforme): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https://#i', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    switch ($plateforme) {
        case 'youtube':
            if (preg_match('#(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/|youtube\.com/live/)([A-Za-z0-9_-]{11})#', $url, $m)) {
                return 'https://www.youtube.com/embed/' . $m[1];
            }
            return null;
        case 'vimeo':
            if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
                return 'https://player.vimeo.com/video/' . $m[1];
            }
            return null;
        case 'twitch':
            if (preg_match('#twitch\.tv/videos/(\d+)#', $url, $m)) {
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                return 'https://player.twitch.tv/?video=v' . $m[1] . '&parent=' . preg_replace('/:\d+$/', '', $host);
            }
            if (preg_match('#twitch\.tv/([A-Za-z0-9_]+)$#', $url, $m)) {
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                return 'https://player.twitch.tv/?channel=' . $m[1] . '&parent=' . preg_replace('/:\d+$/', '', $host);
            }
            return null;
        case 'autre':
            // On accepte uniquement HTTPS, on s'attend à une URL embed prête à l'emploi
            return $url;
    }
    return null;
}

/**
 * @return int|null id ou null avec $errMsg
 */
function maire_creer_session_conseil(PDO $pdo, array $data, ?string $auteur, ?string &$errMsg = null): ?int
{
    maire_ensure_conseil_sessions_table($pdo);
    $titre = trim((string) ($data['titre'] ?? ''));
    $dateSession = trim((string) ($data['date_session'] ?? ''));
    $statut = (string) ($data['statut'] ?? 'annonce');
    $plateforme = (string) ($data['plateforme'] ?? 'youtube');
    $duree = max(15, min(720, (int) ($data['duree_minutes'] ?? 90)));

    if ($titre === '' || mb_strlen($titre) > 200) {
        $errMsg = 'Titre requis (≤ 200 caractères).';
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}(:\d{2})?$/', $dateSession)) {
        $errMsg = 'Date/heure invalide (format AAAA-MM-JJ HH:MM).';
        return null;
    }
    $dateSession = str_replace('T', ' ', $dateSession);
    if (strlen($dateSession) === 16) {
        $dateSession .= ':00';
    }
    if (!array_key_exists($statut, MAIRE_CONSEIL_STATUTS)) {
        $statut = 'annonce';
    }
    if (!array_key_exists($plateforme, MAIRE_CONSEIL_PLATEFORMES)) {
        $plateforme = 'youtube';
    }

    $embedRaw = trim((string) ($data['embed_url'] ?? ''));
    $embed = null;
    if ($embedRaw !== '') {
        $embed = maire_conseil_normaliser_embed($embedRaw, $plateforme);
        if ($embed === null) {
            $errMsg = 'URL de streaming invalide pour la plateforme « ' . $plateforme . ' ». Vérifiez le format.';
            return null;
        }
    }

    $pvUrl = trim((string) ($data['proces_verbal_url'] ?? ''));
    if ($pvUrl !== '' && !filter_var($pvUrl, FILTER_VALIDATE_URL)) {
        $errMsg = 'URL du procès-verbal invalide.';
        return null;
    }

    try {
        $st = $pdo->prepare('
            INSERT INTO conseil_sessions (titre, description, date_session, duree_minutes, statut, plateforme, embed_url, ordre_du_jour, proces_verbal_url, cree_par_email)
            VALUES (:t, :d, :ds, :dr, :st, :p, :e, :oj, :pv, :a)
        ');
        $st->execute([
            't' => mb_substr($titre, 0, 200),
            'd' => isset($data['description']) ? mb_substr(trim((string) $data['description']), 0, 4000) : null,
            'ds' => $dateSession,
            'dr' => $duree,
            'st' => $statut,
            'p' => $plateforme,
            'e' => $embed,
            'oj' => isset($data['ordre_du_jour']) ? mb_substr(trim((string) $data['ordre_du_jour']), 0, 4000) : null,
            'pv' => $pvUrl !== '' ? mb_substr($pvUrl, 0, 500) : null,
            'a' => $auteur !== null ? mb_substr($auteur, 0, 190) : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Création impossible : ' . $e->getMessage();
        return null;
    }
}

function maire_mettre_a_jour_session_conseil(PDO $pdo, int $id, array $data, ?string &$errMsg = null): bool
{
    $session = maire_load_session_conseil($pdo, $id);
    if ($session === null) {
        $errMsg = 'Session introuvable.';
        return false;
    }
    $plateforme = (string) ($data['plateforme'] ?? $session['plateforme']);
    if (!array_key_exists($plateforme, MAIRE_CONSEIL_PLATEFORMES)) {
        $plateforme = (string) $session['plateforme'];
    }
    $statut = (string) ($data['statut'] ?? $session['statut']);
    if (!array_key_exists($statut, MAIRE_CONSEIL_STATUTS)) {
        $statut = (string) $session['statut'];
    }
    $embedRaw = trim((string) ($data['embed_url'] ?? ''));
    $embed = $session['embed_url'];
    if ($embedRaw !== '') {
        $embed = maire_conseil_normaliser_embed($embedRaw, $plateforme);
        if ($embed === null) {
            $errMsg = 'URL de streaming invalide.';
            return false;
        }
    }
    try {
        $st = $pdo->prepare('UPDATE conseil_sessions SET statut = :st, plateforme = :p, embed_url = :e WHERE id = :id');
        $st->execute(['st' => $statut, 'p' => $plateforme, 'e' => $embed, 'id' => $id]);
        return true;
    } catch (Throwable $e) {
        $errMsg = 'Mise à jour impossible : ' . $e->getMessage();
        return false;
    }
}

function maire_supprimer_session_conseil(PDO $pdo, int $id): bool
{
    try {
        $st = $pdo->prepare('DELETE FROM conseil_sessions WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_load_session_conseil(PDO $pdo, int $id): ?array
{
    try {
        $st = $pdo->prepare('SELECT * FROM conseil_sessions WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_liste_sessions_conseil_publiques(PDO $pdo, int $limit = 50): array
{
    maire_ensure_conseil_sessions_table($pdo);
    try {
        $st = $pdo->query("SELECT * FROM conseil_sessions WHERE statut IN ('annonce', 'en_direct', 'replay', 'archive') ORDER BY date_session DESC LIMIT " . max(1, min(200, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_liste_sessions_conseil_admin(PDO $pdo, int $limit = 100): array
{
    maire_ensure_conseil_sessions_table($pdo);
    try {
        $st = $pdo->query('SELECT * FROM conseil_sessions ORDER BY date_session DESC LIMIT ' . max(1, min(500, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_incrementer_vues_conseil(PDO $pdo, int $id): void
{
    try {
        $pdo->prepare('UPDATE conseil_sessions SET nb_vues = nb_vues + 1 WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $e) {
        // tolérant
    }
}
