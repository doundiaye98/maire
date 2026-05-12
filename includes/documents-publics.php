<?php
declare(strict_types=1);

/**
 * Bibliothèque de documents publics de la mairie : formulaires à télécharger,
 * actes type, autorisations modèles, démarches, rapports.
 *
 * - Admin → upload, modification, activation, suppression
 * - Public → liste consultable + téléchargement (avec compteur)
 */

const MAIRE_DOCUMENTS_UPLOAD_DIR = 'uploads/documents';
const MAIRE_DOCUMENTS_MAX_OCTETS = 10 * 1024 * 1024; // 10 Mo

/** Extensions et types MIME acceptés */
const MAIRE_DOCUMENTS_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'jpg', 'jpeg', 'png'];
const MAIRE_DOCUMENTS_MIMES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/octet-stream', // fallback parfois renvoyé par finfo
    'image/jpeg',
    'image/png',
];

const MAIRE_DOCUMENTS_CATEGORIES = [
    'formulaire' => 'Formulaire',
    'acte' => 'Acte administratif',
    'autorisation' => 'Autorisation',
    'demarche' => 'Guide de démarche',
    'rapport' => 'Rapport / publication',
    'autre' => 'Autre',
];

function maire_ensure_documents_publics_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents_publics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categorie ENUM('formulaire', 'acte', 'autorisation', 'demarche', 'rapport', 'autre') NOT NULL DEFAULT 'autre',
            titre VARCHAR(180) NOT NULL,
            description TEXT NULL,
            fichier_path VARCHAR(255) NOT NULL,
            fichier_nom_original VARCHAR(255) NOT NULL,
            fichier_taille INT NOT NULL,
            mime_type VARCHAR(80) NOT NULL,
            nb_telechargements INT NOT NULL DEFAULT 0,
            publie TINYINT(1) NOT NULL DEFAULT 1,
            publie_par_email VARCHAR(190) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_documents_cat (categorie),
            INDEX idx_documents_publie (publie),
            INDEX idx_documents_date (created_at)
        )
    ");
}

function maire_libelle_categorie_document(string $code): string
{
    return MAIRE_DOCUMENTS_CATEGORIES[$code] ?? ucfirst($code);
}

function maire_icone_categorie_document(string $code): string
{
    return match ($code) {
        'formulaire' => '📝',
        'acte' => '📜',
        'autorisation' => '✅',
        'demarche' => '🧭',
        'rapport' => '📊',
        default => '📁',
    };
}

function maire_format_taille_fichier(int $octets): string
{
    if ($octets < 1024) {
        return $octets . ' o';
    }
    if ($octets < 1024 * 1024) {
        return number_format($octets / 1024, 1, ',', ' ') . ' Ko';
    }
    return number_format($octets / 1024 / 1024, 1, ',', ' ') . ' Mo';
}

/**
 * Chemin absolu du dossier d'upload. Le crée si absent + .htaccess restrictif.
 */
function maire_documents_upload_path(): string
{
    $dir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, MAIRE_DOCUMENTS_UPLOAD_DIR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        $hta = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($hta)) {
            // On interdit l'exécution PHP/HTML/JS dans le dossier (anti-RCE),
            // mais on autorise l'accès direct aux PDF/DOCX/XLSX pour le téléchargement.
            @file_put_contents($hta, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|html|htm|js)$\">\n  Require all denied\n</FilesMatch>\n");
        }
    }
    return $dir;
}

/**
 * Crée un document à partir d'un upload POST.
 *
 * @param array{categorie:string,titre:string,description:?string} $meta
 * @param array $fichier $_FILES['fichier']
 * @return int|null id créé, ou null avec $errMsg
 */
function maire_creer_document_public(PDO $pdo, array $meta, array $fichier, ?string $publiePar, ?string &$errMsg = null): ?int
{
    maire_ensure_documents_publics_table($pdo);

    $categorie = (string) ($meta['categorie'] ?? 'autre');
    if (!array_key_exists($categorie, MAIRE_DOCUMENTS_CATEGORIES)) {
        $categorie = 'autre';
    }
    $titre = trim((string) ($meta['titre'] ?? ''));
    $description = trim((string) ($meta['description'] ?? ''));
    if ($titre === '' || mb_strlen($titre) > 180) {
        $errMsg = 'Titre requis (≤ 180 caractères).';
        return null;
    }

    if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errMsg = 'Aucun fichier reçu.';
        return null;
    }
    if (($fichier['error'] ?? -1) !== UPLOAD_ERR_OK) {
        $errMsg = 'Erreur d’upload (code ' . ($fichier['error'] ?? '?') . ').';
        return null;
    }
    $size = (int) ($fichier['size'] ?? 0);
    if ($size <= 0 || $size > MAIRE_DOCUMENTS_MAX_OCTETS) {
        $errMsg = 'Le fichier doit faire entre 1 octet et ' . (MAIRE_DOCUMENTS_MAX_OCTETS / 1024 / 1024) . ' Mo.';
        return null;
    }
    $tmp = (string) ($fichier['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        $errMsg = 'Fichier d’upload invalide.';
        return null;
    }
    $nameOriginal = (string) ($fichier['name'] ?? 'document');
    $ext = strtolower(pathinfo($nameOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, MAIRE_DOCUMENTS_EXTENSIONS, true)) {
        $errMsg = 'Format non autorisé. Accepté : ' . implode(', ', MAIRE_DOCUMENTS_EXTENSIONS) . '.';
        return null;
    }
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if ($detected !== '') {
                $mime = $detected;
            }
        }
    }
    if (!in_array($mime, MAIRE_DOCUMENTS_MIMES, true)) {
        $errMsg = 'Type MIME refusé (' . $mime . ').';
        return null;
    }

    $dir = maire_documents_upload_path();
    try {
        $nomFichier = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    } catch (Throwable $e) {
        $nomFichier = md5(uniqid('doc', true)) . '.' . $ext;
    }
    $destAbs = $dir . DIRECTORY_SEPARATOR . $nomFichier;
    if (!@move_uploaded_file($tmp, $destAbs)) {
        $errMsg = 'Impossible de sauvegarder le fichier.';
        return null;
    }
    @chmod($destAbs, 0644);

    $cheminRelatif = MAIRE_DOCUMENTS_UPLOAD_DIR . '/' . $nomFichier;

    try {
        $ins = $pdo->prepare('
            INSERT INTO documents_publics
                (categorie, titre, description, fichier_path, fichier_nom_original, fichier_taille, mime_type, publie, publie_par_email)
            VALUES (:cat, :tit, :desc, :path, :nom, :size, :mime, 1, :pub)
        ');
        $ins->execute([
            'cat' => $categorie,
            'tit' => mb_substr($titre, 0, 180),
            'desc' => $description !== '' ? mb_substr($description, 0, 4000) : null,
            'path' => $cheminRelatif,
            'nom' => mb_substr($nameOriginal, 0, 255),
            'size' => $size,
            'mime' => mb_substr($mime, 0, 80),
            'pub' => $publiePar !== null ? mb_substr($publiePar, 0, 190) : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($destAbs);
        $errMsg = 'Erreur DB : ' . $e->getMessage();
        return null;
    }
}

function maire_liste_documents_publics(PDO $pdo, ?string $categorie = null, int $limit = 200): array
{
    maire_ensure_documents_publics_table($pdo);
    $where = ['publie = 1'];
    $params = [];
    if ($categorie !== null && $categorie !== '' && array_key_exists($categorie, MAIRE_DOCUMENTS_CATEGORIES)) {
        $where[] = 'categorie = :cat';
        $params['cat'] = $categorie;
    }
    $sql = 'SELECT id, categorie, titre, description, fichier_nom_original, fichier_taille, mime_type, nb_telechargements, created_at
            FROM documents_publics
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY categorie ASC, created_at DESC
            LIMIT ' . max(1, min(500, $limit));
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_liste_documents_admin(PDO $pdo, int $limit = 200): array
{
    maire_ensure_documents_publics_table($pdo);
    try {
        $st = $pdo->query('SELECT * FROM documents_publics ORDER BY created_at DESC LIMIT ' . max(1, min(500, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_load_document_public(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT * FROM documents_publics WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }
    return $r === false ? null : $r;
}

function maire_incrementer_telechargement_document(PDO $pdo, int $id): void
{
    try {
        $pdo->prepare('UPDATE documents_publics SET nb_telechargements = nb_telechargements + 1 WHERE id = :id')
            ->execute(['id' => $id]);
    } catch (Throwable $e) {
        // non bloquant
    }
}

function maire_basculer_publication_document(PDO $pdo, int $id, bool $publie): bool
{
    try {
        $st = $pdo->prepare('UPDATE documents_publics SET publie = :p WHERE id = :id');
        $st->execute(['p' => $publie ? 1 : 0, 'id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_supprimer_document(PDO $pdo, int $id): bool
{
    $doc = maire_load_document_public($pdo, $id);
    if ($doc === null) {
        return false;
    }
    try {
        $abs = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $doc['fichier_path']);
        if (is_file($abs)) {
            @unlink($abs);
        }
        $st = $pdo->prepare('DELETE FROM documents_publics WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_mettre_a_jour_meta_document(PDO $pdo, int $id, string $titre, ?string $description, string $categorie): bool
{
    if (!array_key_exists($categorie, MAIRE_DOCUMENTS_CATEGORIES)) {
        return false;
    }
    if (trim($titre) === '' || mb_strlen($titre) > 180) {
        return false;
    }
    try {
        $st = $pdo->prepare('UPDATE documents_publics SET titre = :t, description = :d, categorie = :c WHERE id = :id');
        $st->execute([
            't' => mb_substr(trim($titre), 0, 180),
            'd' => $description !== null && trim($description) !== '' ? mb_substr($description, 0, 4000) : null,
            'c' => $categorie,
            'id' => $id,
        ]);
        return $st->rowCount() >= 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_compter_documents_publics(PDO $pdo): array
{
    maire_ensure_documents_publics_table($pdo);
    $r = ['total' => 0, 'publies' => 0, 'hors_ligne' => 0, 'telechargements' => 0];
    try {
        $row = $pdo->query('SELECT COUNT(*) AS total, SUM(publie = 1) AS publies, SUM(publie = 0) AS hors_ligne, COALESCE(SUM(nb_telechargements),0) AS dl FROM documents_publics')->fetch();
        if ($row !== false) {
            $r['total'] = (int) $row['total'];
            $r['publies'] = (int) $row['publies'];
            $r['hors_ligne'] = (int) $row['hors_ligne'];
            $r['telechargements'] = (int) $row['dl'];
        }
    } catch (Throwable $e) {
        // table indisponible
    }
    return $r;
}

/**
 * Sert le fichier en téléchargement (Content-Disposition: attachment).
 * Vérifie l'accès, incrémente le compteur, applique les bons headers.
 * Retourne false en cas d'erreur (la page appelante peut afficher un message).
 */
function maire_servir_document(PDO $pdo, int $id, bool $publicSeulement = true): bool
{
    $doc = maire_load_document_public($pdo, $id);
    if ($doc === null) {
        return false;
    }
    if ($publicSeulement && (int) ($doc['publie'] ?? 0) !== 1) {
        return false;
    }
    $rel = (string) ($doc['fichier_path'] ?? '');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return false;
    }
    $abs = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($abs) || !is_readable($abs)) {
        return false;
    }

    $base = realpath(__DIR__ . '/..' . '/' . MAIRE_DOCUMENTS_UPLOAD_DIR);
    $real = realpath($abs);
    if ($base === false || $real === false || strpos($real, $base) !== 0) {
        return false; // path traversal
    }

    maire_incrementer_telechargement_document($pdo, $id);

    while (ob_get_level() > 0) { @ob_end_clean(); }
    $nameOut = (string) ($doc['fichier_nom_original'] ?? basename($real));
    $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($real));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nameOut) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($real);
    return true;
}
