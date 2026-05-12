<?php
declare(strict_types=1);

/**
 * Gestion des signalements citoyens (route cassée, lampadaire, déchets, etc.).
 *
 * Le citoyen authentifié crée le signalement avec photo + géoloc.
 * L'admin mairie (compte admin ou éditeur) le traite via /admin/signalements.php.
 */

const MAIRE_SIGNALEMENTS_UPLOAD_DIR = 'uploads/signalements';
const MAIRE_SIGNALEMENTS_MAX_OCTETS = 4 * 1024 * 1024; // 4 Mo
/** Extensions et types MIME acceptés pour la photo */
const MAIRE_SIGNALEMENTS_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
const MAIRE_SIGNALEMENTS_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

const MAIRE_SIGNALEMENTS_CATEGORIES = [
    'route' => 'Route ou voirie',
    'lampadaire' => 'Éclairage public',
    'dechet' => 'Déchets / propreté',
    'inondation' => 'Inondation',
    'eau' => 'Eau / fuite',
    'securite' => 'Sécurité',
    'autre' => 'Autre',
];

const MAIRE_SIGNALEMENTS_STATUTS = [
    'nouveau' => 'Nouveau',
    'pris_en_charge' => 'Pris en charge',
    'resolu' => 'Résolu',
    'rejete' => 'Rejeté',
];

function maire_ensure_signalements_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS signalements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            citoyen_id INT NOT NULL,
            categorie ENUM('route', 'lampadaire', 'dechet', 'inondation', 'eau', 'securite', 'autre') NOT NULL DEFAULT 'autre',
            titre VARCHAR(180) NOT NULL,
            description TEXT NOT NULL,
            photo_path VARCHAR(255) DEFAULT NULL,
            latitude DECIMAL(10, 7) DEFAULT NULL,
            longitude DECIMAL(10, 7) DEFAULT NULL,
            adresse_libre VARCHAR(255) DEFAULT NULL,
            statut ENUM('nouveau', 'pris_en_charge', 'resolu', 'rejete') NOT NULL DEFAULT 'nouveau',
            admin_notes TEXT DEFAULT NULL,
            traite_par_email VARCHAR(190) DEFAULT NULL,
            traite_le TIMESTAMP NULL DEFAULT NULL,
            visibilite_publique TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_signalements_citoyen (citoyen_id),
            INDEX idx_signalements_statut (statut),
            INDEX idx_signalements_categorie (categorie),
            INDEX idx_signalements_date (created_at)
        )
    ");
}

function maire_libelle_categorie_signalement(string $code): string
{
    return MAIRE_SIGNALEMENTS_CATEGORIES[$code] ?? ucfirst($code);
}

function maire_libelle_statut_signalement(string $code): string
{
    return MAIRE_SIGNALEMENTS_STATUTS[$code] ?? ucfirst($code);
}

function maire_classe_badge_statut(string $statut): string
{
    return match ($statut) {
        'nouveau' => 'std-feed-badge--warning',
        'pris_en_charge' => 'std-feed-badge--info',
        'resolu' => 'std-feed-badge--success',
        'rejete' => 'std-feed-badge--danger',
        default => 'std-feed-badge--info',
    };
}

/**
 * Chemin absolu du dossier d'upload. Le crée si absent.
 */
function maire_signalements_upload_path(): string
{
    $dir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, MAIRE_SIGNALEMENTS_UPLOAD_DIR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        // .htaccess minimal pour empêcher l'exécution PHP dans le dossier d'uploads
        $hta = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($hta)) {
            @file_put_contents($hta, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|html|htm|js)$\">\n  Require all denied\n</FilesMatch>\n");
        }
    }
    return $dir;
}

/**
 * Traite l'upload de la photo : valide MIME + taille + extension, déplace dans uploads/signalements/.
 * Retourne le chemin relatif (utilisable en URL) ou null si pas de fichier / erreur.
 */
function maire_traiter_upload_photo_signalement(array $fichier, ?string &$errMsg = null): ?string
{
    if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($fichier['error'] ?? -1) !== UPLOAD_ERR_OK) {
        $errMsg = 'Erreur lors de l’upload de la photo (code ' . ($fichier['error'] ?? '?') . ').';
        return null;
    }
    $size = (int) ($fichier['size'] ?? 0);
    if ($size <= 0 || $size > MAIRE_SIGNALEMENTS_MAX_OCTETS) {
        $errMsg = 'La photo doit faire entre 1 octet et ' . (MAIRE_SIGNALEMENTS_MAX_OCTETS / 1024 / 1024) . ' Mo.';
        return null;
    }
    $tmp = (string) ($fichier['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        $errMsg = 'Fichier d’upload invalide.';
        return null;
    }
    $name = (string) ($fichier['name'] ?? 'photo');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, MAIRE_SIGNALEMENTS_EXTENSIONS, true)) {
        $errMsg = 'Format de photo non autorisé. Accepté : ' . implode(', ', MAIRE_SIGNALEMENTS_EXTENSIONS) . '.';
        return null;
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    if ($mime !== '' && !in_array($mime, MAIRE_SIGNALEMENTS_MIMES, true)) {
        $errMsg = 'Type MIME refusé (' . $mime . ').';
        return null;
    }

    $dir = maire_signalements_upload_path();
    try {
        $nomFichier = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    } catch (Throwable $e) {
        $nomFichier = md5(uniqid('sig', true)) . '.' . $ext;
    }
    $destAbs = $dir . DIRECTORY_SEPARATOR . $nomFichier;
    if (!@move_uploaded_file($tmp, $destAbs)) {
        $errMsg = 'Impossible de sauvegarder la photo sur le serveur.';
        return null;
    }
    @chmod($destAbs, 0644);

    return MAIRE_SIGNALEMENTS_UPLOAD_DIR . '/' . $nomFichier;
}

/**
 * Crée un signalement. Renvoie l'id ou null avec $errMsg.
 *
 * @param array{categorie:string,titre:string,description:string,latitude:?string,longitude:?string,adresse_libre:?string} $data
 * @param array|null $fichierPhoto $_FILES['photo'] ou null
 */
function maire_creer_signalement(PDO $pdo, int $citoyenId, array $data, ?array $fichierPhoto, ?string &$errMsg = null): ?int
{
    maire_ensure_signalements_table($pdo);

    $categorie = (string) ($data['categorie'] ?? 'autre');
    if (!array_key_exists($categorie, MAIRE_SIGNALEMENTS_CATEGORIES)) {
        $categorie = 'autre';
    }
    $titre = trim((string) ($data['titre'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    if ($titre === '' || mb_strlen($titre) > 180) {
        $errMsg = 'Titre requis (≤ 180 caractères).';
        return null;
    }
    if ($description === '' || mb_strlen($description) > 4000) {
        $errMsg = 'Description requise (≤ 4000 caractères).';
        return null;
    }

    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $latitude = (is_string($latitude) && $latitude !== '' && is_numeric($latitude)) ? (float) $latitude : null;
    $longitude = (is_string($longitude) && $longitude !== '' && is_numeric($longitude)) ? (float) $longitude : null;
    if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
        $errMsg = 'Latitude hors plage.';
        return null;
    }
    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
        $errMsg = 'Longitude hors plage.';
        return null;
    }
    $adresseLibre = trim((string) ($data['adresse_libre'] ?? ''));
    if (mb_strlen($adresseLibre) > 255) {
        $adresseLibre = mb_substr($adresseLibre, 0, 255);
    }

    $photoPath = null;
    if (is_array($fichierPhoto) && ($fichierPhoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = null;
        $photoPath = maire_traiter_upload_photo_signalement($fichierPhoto, $err);
        if ($photoPath === null && $err !== null) {
            $errMsg = $err;
            return null;
        }
    }

    try {
        $ins = $pdo->prepare('
            INSERT INTO signalements
                (citoyen_id, categorie, titre, description, photo_path, latitude, longitude, adresse_libre, statut)
            VALUES
                (:cid, :cat, :tit, :desc, :photo, :lat, :lng, :adr, "nouveau")
        ');
        $ins->execute([
            'cid' => $citoyenId,
            'cat' => $categorie,
            'tit' => mb_substr($titre, 0, 180),
            'desc' => mb_substr($description, 0, 4000),
            'photo' => $photoPath,
            'lat' => $latitude,
            'lng' => $longitude,
            'adr' => $adresseLibre !== '' ? $adresseLibre : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Erreur d’enregistrement : ' . $e->getMessage();
        return null;
    }
}

/**
 * Liste des signalements d'un citoyen donné, du plus récent au plus ancien.
 */
function maire_liste_signalements_citoyen(PDO $pdo, int $citoyenId, int $limit = 100): array
{
    maire_ensure_signalements_table($pdo);
    try {
        $st = $pdo->prepare('
            SELECT id, categorie, titre, description, photo_path, latitude, longitude, adresse_libre,
                   statut, admin_notes, traite_le, created_at, updated_at
            FROM signalements
            WHERE citoyen_id = :cid
            ORDER BY created_at DESC
            LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute(['cid' => $citoyenId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Liste pour l'admin, avec filtres optionnels (statut, catégorie).
 */
function maire_liste_signalements_admin(PDO $pdo, ?string $statut = null, ?string $categorie = null, int $limit = 200): array
{
    maire_ensure_signalements_table($pdo);
    $where = [];
    $params = [];
    if ($statut !== null && $statut !== '' && array_key_exists($statut, MAIRE_SIGNALEMENTS_STATUTS)) {
        $where[] = 's.statut = :statut';
        $params['statut'] = $statut;
    }
    if ($categorie !== null && $categorie !== '' && array_key_exists($categorie, MAIRE_SIGNALEMENTS_CATEGORIES)) {
        $where[] = 's.categorie = :categorie';
        $params['categorie'] = $categorie;
    }
    $sql = '
        SELECT s.*, c.prenom AS citoyen_prenom, c.nom AS citoyen_nom, c.email AS citoyen_email, c.telephone AS citoyen_telephone
        FROM signalements s
        LEFT JOIN citoyens c ON c.id = s.citoyen_id
    ';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY s.created_at DESC LIMIT ' . max(1, min(500, $limit));
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_compter_signalements_par_statut(PDO $pdo): array
{
    maire_ensure_signalements_table($pdo);
    $counts = array_fill_keys(array_keys(MAIRE_SIGNALEMENTS_STATUTS), 0);
    try {
        $rows = $pdo->query('SELECT statut, COUNT(*) AS n FROM signalements GROUP BY statut')->fetchAll();
        foreach ($rows as $r) {
            $counts[(string) $r['statut']] = (int) $r['n'];
        }
    } catch (Throwable $e) {
        // base indisponible : on renvoie tout à 0
    }
    return $counts;
}

function maire_mettre_a_jour_statut_signalement(PDO $pdo, int $signalementId, string $statut, ?string $notes, ?string $traiteParEmail): bool
{
    if (!array_key_exists($statut, MAIRE_SIGNALEMENTS_STATUTS)) {
        return false;
    }
    try {
        $st = $pdo->prepare('
            UPDATE signalements
            SET statut = :s,
                admin_notes = :n,
                traite_par_email = :u,
                traite_le = NOW()
            WHERE id = :id
        ');
        $st->execute([
            's' => $statut,
            'n' => $notes,
            'u' => $traiteParEmail,
            'id' => $signalementId,
        ]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Pour afficher la photo en URL accessible depuis n'importe quel sous-dossier.
 */
function maire_url_photo_signalement(?string $photoPath, string $urlPrefix = ''): ?string
{
    if ($photoPath === null || $photoPath === '') {
        return null;
    }
    return $urlPrefix . ltrim($photoPath, '/');
}
