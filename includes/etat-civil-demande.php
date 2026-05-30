<?php
declare(strict_types=1);

/**
 * Demandes d’état civil numériques (dépôt + pièces jointes).
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/site-paths.php';

const MAIRE_ETAT_CIVIL_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const MAIRE_ETAT_CIVIL_UPLOAD_MAX_FILES = 5;

const MAIRE_ETAT_CIVIL_TYPES = [
    'extrait_naissance' => 'Extrait de naissance',
    'dossier_mariage' => 'Dossier de mariage',
    'acte_deces' => 'Acte de décès',
    'legalisation' => 'Légalisation de document',
];

const MAIRE_ETAT_CIVIL_STATUTS = [
    'recu' => 'Reçu',
    'en_cours' => 'En cours de traitement',
    'valide' => 'Validé',
    'pret' => 'Prêt à retirer',
    'rejete' => 'Rejeté',
];

const MAIRE_ETAT_CIVIL_ADMIN_NOTE_TEMPLATES = [
    'recu' => "Votre dossier a bien été reçu. Il est en cours d'enregistrement par le service d'état civil.",
    'en_cours' => "Votre dossier est en cours de traitement. Nous vous recontacterons si une pièce complémentaire est nécessaire.",
    'valide' => "Votre dossier a été validé. La finalisation de votre demande est en cours.",
    'pret' => "Votre dossier est prêt. Merci de vous présenter à la mairie avec votre pièce d'identité et votre référence de dossier.",
    'rejete' => "Votre dossier ne peut pas être traité en l'état. Merci de vérifier vos pièces ou de contacter la mairie pour compléter votre demande.",
];

/** @var array<string, list<string>> */
const MAIRE_ETAT_CIVIL_CHECKLIST = [
    'extrait_naissance' => [
        'Pièce d’identité du demandeur (CNI ou passeport)',
        'Informations exactes de l’acte (nom, date et lieu de naissance)',
    ],
    'dossier_mariage' => [
        'Pièces d’identité des deux futurs époux',
        'Certificats de célibat ou documents équivalents',
        'Témoins : coordonnées si déjà désignés',
    ],
    'acte_deces' => [
        'Pièce d’identité du déclarant',
        'Certificat médical de décès',
        'Livret de famille si disponible',
    ],
    'legalisation' => [
        'Document original à légaliser (scan lisible)',
        'Copie de la pièce d’identité du demandeur',
    ],
];

function maire_normaliser_reference_etat_civil(string $reference): string
{
    $reference = strtoupper(trim($reference));
    return preg_replace('/\s+/', '', $reference) ?? $reference;
}

function maire_libelle_type_demande_etat_civil(string $code): string
{
    return MAIRE_ETAT_CIVIL_TYPES[$code] ?? $code;
}

function maire_libelle_statut_demande_etat_civil(string $code): string
{
    return MAIRE_ETAT_CIVIL_STATUTS[$code] ?? ucfirst(str_replace('_', ' ', $code));
}

function maire_classe_badge_statut_etat_civil(string $statut): string
{
    return match ($statut) {
        'recu' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'en_cours' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        'valide', 'pret' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'rejete' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        default => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    };
}

function maire_ensure_demandes_etat_civil_tables(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demandes_etat_civil (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_dossier VARCHAR(40) NOT NULL UNIQUE,
            type_demande VARCHAR(50) NOT NULL,
            nom_complet VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telephone VARCHAR(40) DEFAULT NULL,
            cni VARCHAR(80) DEFAULT NULL,
            date_naissance DATE DEFAULT NULL,
            lieu_naissance VARCHAR(160) DEFAULT NULL,
            adresse TEXT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            statut VARCHAR(40) NOT NULL DEFAULT 'recu',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ec_statut (statut),
            INDEX idx_ec_created (created_at),
            INDEX idx_ec_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    maire_ensure_demandes_etat_civil_columns($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demandes_etat_civil_pieces (
            id INT AUTO_INCREMENT PRIMARY KEY,
            demande_id INT NOT NULL,
            nom_fichier VARCHAR(220) NOT NULL,
            chemin_fichier VARCHAR(300) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ec_piece_demande (demande_id),
            CONSTRAINT fk_demande_piece FOREIGN KEY (demande_id)
                REFERENCES demandes_etat_civil(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $checked = true;
}

function maire_ensure_demandes_etat_civil_columns(PDO $pdo): void
{
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM demandes_etat_civil')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admin_notes', $cols, true)) {
            $pdo->exec('ALTER TABLE demandes_etat_civil ADD COLUMN admin_notes TEXT NULL AFTER details');
        }
    } catch (Throwable $e) {
        // table absente
    }
}

function maire_generer_reference_etat_civil(): string
{
    return 'EC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * @return list<string> MIME autorisés
 */
function maire_etat_civil_mimes_autorises(): array
{
    return ['application/pdf', 'image/jpeg', 'image/png'];
}

function maire_compter_fichiers_soumis_etat_civil(?array $files): int
{
    if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
        return 0;
    }

    $count = 0;
    foreach ($files['name'] as $name) {
        if (trim((string) $name) !== '') {
            $count++;
        }
    }

    return $count;
}

/**
 * @param array<string, mixed> $data
 * @param array<string, mixed>|null $files $_FILES['pieces'] structure
 * @return array{reference: string, id: int, pieces: list<string>}|null
 */
function maire_creer_demande_etat_civil(PDO $pdo, array $data, ?array $files, ?string &$errMsg = null): ?array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $typeDemande = (string) ($data['type_demande'] ?? '');
    if (!array_key_exists($typeDemande, MAIRE_ETAT_CIVIL_TYPES)) {
        $errMsg = 'Type de demande invalide.';
        return null;
    }

    $nomComplet = trim((string) ($data['nom_complet'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $telephone = trim((string) ($data['telephone'] ?? ''));
    $cni = trim((string) ($data['cni'] ?? ''));
    $dateNaissance = trim((string) ($data['date_naissance'] ?? ''));
    $lieuNaissance = trim((string) ($data['lieu_naissance'] ?? ''));
    $adresse = trim((string) ($data['adresse'] ?? ''));
    $details = trim((string) ($data['details'] ?? ''));

    if ($nomComplet === '' || mb_strlen($nomComplet) > 160) {
        $errMsg = 'Nom complet requis (160 caractères max.).';
        return null;
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Adresse email invalide.';
        return null;
    }

    $dateSql = null;
    if ($dateNaissance !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dateNaissance);
        if ($dt === false || $dt->format('Y-m-d') !== $dateNaissance) {
            $errMsg = 'Date de naissance invalide.';
            return null;
        }
        $dateSql = $dateNaissance;
    }

    $reference = maire_generer_reference_etat_civil();
    $uploadedNames = [];

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare("
            INSERT INTO demandes_etat_civil
            (reference_dossier, type_demande, nom_complet, email, telephone, cni, date_naissance, lieu_naissance, adresse, details)
            VALUES
            (:reference_dossier, :type_demande, :nom_complet, :email, :telephone, :cni, :date_naissance, :lieu_naissance, :adresse, :details)
        ");
        $insert->execute([
            'reference_dossier' => $reference,
            'type_demande' => $typeDemande,
            'nom_complet' => mb_substr($nomComplet, 0, 160),
            'email' => mb_substr($email, 0, 190),
            'telephone' => $telephone !== '' ? mb_substr($telephone, 0, 40) : null,
            'cni' => $cni !== '' ? mb_substr($cni, 0, 80) : null,
            'date_naissance' => $dateSql,
            'lieu_naissance' => $lieuNaissance !== '' ? mb_substr($lieuNaissance, 0, 160) : null,
            'adresse' => $adresse !== '' ? mb_substr($adresse, 0, 4000) : null,
            'details' => $details !== '' ? mb_substr($details, 0, 4000) : null,
        ]);

        $demandeId = (int) $pdo->lastInsertId();
        $uploadedNames = maire_traiter_uploads_etat_civil($pdo, $demandeId, $reference, $files, $errMsg);
        if ($uploadedNames === null) {
            $pdo->rollBack();
            return null;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMsg = 'Enregistrement impossible. Réessayez plus tard.';
        return null;
    }

    maire_notifier_demande_etat_civil($pdo, $demandeId, $reference);

    return [
        'reference' => $reference,
        'id' => $demandeId,
        'type' => $typeDemande,
        'type_libelle' => maire_libelle_type_demande_etat_civil($typeDemande),
        'nom' => $nomComplet,
        'email' => $email,
        'telephone' => $telephone,
        'pieces' => $uploadedNames,
    ];
}

/**
 * @param array<string, mixed>|null $files
 * @return list<string>|null noms originaux, ou null si erreur
 */
function maire_traiter_uploads_etat_civil(PDO $pdo, int $demandeId, string $reference, ?array $files, ?string &$errMsg = null): ?array
{
    if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $submittedCount = maire_compter_fichiers_soumis_etat_civil($files);
    if ($submittedCount > MAIRE_ETAT_CIVIL_UPLOAD_MAX_FILES) {
        $errMsg = 'Vous pouvez envoyer au maximum ' . MAIRE_ETAT_CIVIL_UPLOAD_MAX_FILES . ' fichiers.';
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/etat-civil';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errMsg = 'Dossier de dépôt indisponible.';
        return null;
    }

    $mimesOk = maire_etat_civil_mimes_autorises();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploaded = [];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $errorCode = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errMsg = 'Erreur lors de l’envoi d’un fichier.';
            return null;
        }

        $tmpName = (string) ($files['tmp_name'][$i] ?? '');
        $originalName = (string) ($files['name'][$i] ?? '');
        $size = (int) ($files['size'][$i] ?? 0);
        if ($tmpName === '' || $originalName === '' || $size <= 0 || $size > MAIRE_ETAT_CIVIL_UPLOAD_MAX_BYTES) {
            $errMsg = 'Chaque fichier doit faire moins de 5 Mo.';
            return null;
        }

        $mime = $finfo->file($tmpName) ?: '';
        if (!in_array($mime, $mimesOk, true)) {
            $errMsg = 'Format non accepté : PDF, JPG ou PNG uniquement.';
            return null;
        }

        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $storedName = $reference . '_' . ($i + 1) . '.' . $ext;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $errMsg = 'Impossible d’enregistrer les pièces jointes.';
            return null;
        }

        $relativePath = 'uploads/etat-civil/' . $storedName;
        $pdo->prepare('
            INSERT INTO demandes_etat_civil_pieces (demande_id, nom_fichier, chemin_fichier)
            VALUES (:demande_id, :nom_fichier, :chemin_fichier)
        ')->execute([
            'demande_id' => $demandeId,
            'nom_fichier' => mb_substr($originalName, 0, 220),
            'chemin_fichier' => $relativePath,
        ]);
        $uploaded[] = $originalName;
    }

    return $uploaded;
}

function maire_notifier_demande_etat_civil(PDO $pdo, int $demandeId, string $reference): void
{
    $st = $pdo->prepare('SELECT * FROM demandes_etat_civil WHERE id = :id LIMIT 1');
    $st->execute(['id' => $demandeId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return;
    }

    $dest = (string) (getenv('MAIRE_CONTACT_EMAIL') ?: 'Rufisquest02@gmail.com');
    $corpsAdmin = "Nouvelle demande d'état civil en ligne.\n\n"
        . 'Référence : ' . $reference . "\n"
        . 'Type : ' . maire_libelle_type_demande_etat_civil((string) $row['type_demande']) . "\n"
        . 'Demandeur : ' . $row['nom_complet'] . "\n"
        . 'Email : ' . $row['email'] . "\n\n"
        . 'Traiter : ' . maire_url_absolue('admin/etat-civil.php') . '?ref=' . rawurlencode($reference) . "\n";

    $err = null;
    maire_mailer_send($dest, '[Mairie] Demande état civil ' . $reference, $corpsAdmin, $err);

    $email = (string) ($row['email'] ?? '');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $corpsCit = "Bonjour " . $row['nom_complet'] . ",\n\n"
            . "Votre demande d'état civil est enregistrée.\n"
            . 'Référence : ' . $reference . "\n"
            . 'Type : ' . maire_libelle_type_demande_etat_civil((string) $row['type_demande']) . "\n"
            . "Statut : Reçu — le service vous contactera si besoin.\n\n"
            . 'Suivi : ' . maire_url_absolue('suivi-etat-civil.php') . '?ref=' . rawurlencode($reference) . "\n\n"
            . "Mairie de Rufisque-Est\n";
        maire_mailer_send($email, 'Demande état civil enregistrée — ' . $reference, $corpsCit, $err);
    }
}

function maire_message_statut_demande_etat_civil(string $statut): string
{
    return match ($statut) {
        'recu' => "Votre dossier a bien été reçu par la mairie.",
        'en_cours' => "Votre demande est en cours de traitement par le service d'état civil.",
        'valide' => "Votre dossier est validé. La préparation finale est en cours.",
        'pret' => "Votre dossier est prêt. Vous pouvez vous rapprocher de la mairie pour le retrait ou suivre les indications communiquées.",
        'rejete' => "Votre dossier ne peut pas être traité en l'état. Merci de consulter le suivi en ligne puis de contacter la mairie si besoin.",
        default => 'Le statut de votre dossier a été mis à jour.',
    };
}

function maire_modele_message_demande_etat_civil(string $statut): string
{
    return MAIRE_ETAT_CIVIL_ADMIN_NOTE_TEMPLATES[$statut] ?? '';
}

function maire_notifier_maj_statut_demande_etat_civil(PDO $pdo, int $id, string $ancienStatut = ''): array
{
    $st = $pdo->prepare('
        SELECT id, reference_dossier, type_demande, nom_complet, email, statut, admin_notes
        FROM demandes_etat_civil
        WHERE id = :id
        LIMIT 1
    ');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return ['sent' => false, 'reason' => 'missing'];
    }

    $email = (string) ($row['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'reason' => 'invalid_email'];
    }

    $reference = (string) ($row['reference_dossier'] ?? '');
    $newStatus = (string) ($row['statut'] ?? '');
    $adminNotes = trim((string) ($row['admin_notes'] ?? ''));
    $body = "Bonjour " . (string) ($row['nom_complet'] ?? '') . ",\n\n"
        . "Le statut de votre demande d'état civil a été mis à jour.\n"
        . 'Référence : ' . $reference . "\n"
        . 'Type : ' . maire_libelle_type_demande_etat_civil((string) ($row['type_demande'] ?? '')) . "\n";

    if ($ancienStatut !== '' && $ancienStatut !== $newStatus) {
        $body .= 'Ancien statut : ' . maire_libelle_statut_demande_etat_civil($ancienStatut) . "\n";
    }

    $body .= 'Nouveau statut : ' . maire_libelle_statut_demande_etat_civil($newStatus) . "\n\n"
        . maire_message_statut_demande_etat_civil($newStatus) . "\n";

    if ($adminNotes !== '') {
        $body .= "\nMessage de la mairie :\n" . $adminNotes . "\n";
    }

    $body .= "\nSuivi : " . maire_url_absolue('suivi-etat-civil.php') . '?ref=' . rawurlencode($reference) . "\n\n"
        . "Mairie de Rufisque-Est\n";

    $err = null;
    $sent = maire_mailer_send(
        $email,
        'Mise à jour de votre demande d’état civil — ' . $reference,
        $body,
        $err
    );

    return [
        'sent' => $sent,
        'reason' => $sent ? 'sent' : 'mailer_failed',
        'error' => $err,
    ];
}

/**
 * @return array<string, mixed>|false
 */
function maire_trouver_demande_etat_civil(PDO $pdo, string $reference)
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $reference = maire_normaliser_reference_etat_civil($reference);
    if ($reference === '') {
        return false;
    }

    $st = $pdo->prepare('
        SELECT
            d.id,
            d.reference_dossier,
            d.type_demande,
            d.nom_complet,
            d.email,
            d.telephone,
            d.statut,
            d.created_at,
            (
                SELECT COUNT(*)
                FROM demandes_etat_civil_pieces p
                WHERE p.demande_id = d.id
            ) AS pieces_count
        FROM demandes_etat_civil d
        WHERE d.reference_dossier = :reference
        LIMIT 1
    ');
    $st->execute(['reference' => $reference]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? false : $row;
}

/**
 * @return array{total:int, aujourd_hui:int, recu:int, en_cours:int, pret:int}
 */
function maire_resumer_demandes_etat_civil(PDO $pdo): array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $row = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS aujourd_hui,
            SUM(CASE WHEN statut = 'recu' THEN 1 ELSE 0 END) AS recu,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) AS en_cours,
            SUM(CASE WHEN statut = 'pret' THEN 1 ELSE 0 END) AS pret
        FROM demandes_etat_civil
    ")->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return ['total' => 0, 'aujourd_hui' => 0, 'recu' => 0, 'en_cours' => 0, 'pret' => 0];
    }

    return [
        'total' => (int) ($row['total'] ?? 0),
        'aujourd_hui' => (int) ($row['aujourd_hui'] ?? 0),
        'recu' => (int) ($row['recu'] ?? 0),
        'en_cours' => (int) ($row['en_cours'] ?? 0),
        'pret' => (int) ($row['pret'] ?? 0),
    ];
}

/**
 * @return array<string, int>
 */
function maire_compter_demandes_etat_civil_par_statut(PDO $pdo): array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $counts = array_fill_keys(array_keys(MAIRE_ETAT_CIVIL_STATUTS), 0);
    $rows = $pdo->query("
        SELECT statut, COUNT(*) AS total
        FROM demandes_etat_civil
        GROUP BY statut
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $statut = (string) ($row['statut'] ?? '');
        if (array_key_exists($statut, $counts)) {
            $counts[$statut] = (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

/**
 * @param array{statut?:string, type?:string, q?:string} $filters
 * @return list<array<string, mixed>>
 */
function maire_lister_demandes_etat_civil_admin(PDO $pdo, array $filters = [], int $limit = 120): array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $where = [];
    $params = [];

    $statut = trim((string) ($filters['statut'] ?? ''));
    if ($statut !== '' && array_key_exists($statut, MAIRE_ETAT_CIVIL_STATUTS)) {
        $where[] = 'd.statut = :statut';
        $params['statut'] = $statut;
    }

    $type = trim((string) ($filters['type'] ?? ''));
    if ($type !== '' && array_key_exists($type, MAIRE_ETAT_CIVIL_TYPES)) {
        $where[] = 'd.type_demande = :type_demande';
        $params['type_demande'] = $type;
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(
            d.reference_dossier = :reference_exact
            OR d.reference_dossier LIKE :search
            OR d.nom_complet LIKE :search
            OR d.email LIKE :search
            OR d.telephone LIKE :search
            OR d.cni LIKE :search
        )';
        $params['reference_exact'] = maire_normaliser_reference_etat_civil($search);
        $params['search'] = '%' . $search . '%';
    }

    $sql = "
        SELECT
            d.id,
            d.reference_dossier,
            d.type_demande,
            d.nom_complet,
            d.email,
            d.telephone,
            d.cni,
            d.date_naissance,
            d.lieu_naissance,
            d.adresse,
            d.details,
            d.admin_notes,
            d.statut,
            d.created_at,
            (
                SELECT COUNT(*)
                FROM demandes_etat_civil_pieces p
                WHERE p.demande_id = d.id
            ) AS pieces_count
        FROM demandes_etat_civil d
    ";

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= "
        ORDER BY FIELD(d.statut, 'recu', 'en_cours', 'valide', 'pret', 'rejete'), d.created_at DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

/**
 * @param list<int> $demandeIds
 * @return array<int, list<array{nom_fichier:string, chemin_fichier:string}>>
 */
function maire_indexer_pieces_demandes_etat_civil(PDO $pdo, array $demandeIds): array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    $ids = array_values(array_unique(array_filter(array_map('intval', $demandeIds), static fn (int $id): bool => $id > 0)));
    $grouped = [];

    foreach ($ids as $id) {
        $grouped[$id] = [];
    }

    if ($ids === []) {
        return $grouped;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT demande_id, nom_fichier, chemin_fichier
        FROM demandes_etat_civil_pieces
        WHERE demande_id IN ($placeholders)
        ORDER BY demande_id ASC, id DESC
    ");
    $stmt->execute($ids);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $demandeId = (int) ($row['demande_id'] ?? 0);
        if (!isset($grouped[$demandeId])) {
            $grouped[$demandeId] = [];
        }
        $grouped[$demandeId][] = [
            'nom_fichier' => (string) ($row['nom_fichier'] ?? ''),
            'chemin_fichier' => (string) ($row['chemin_fichier'] ?? ''),
        ];
    }

    return $grouped;
}

/**
 * @return array{ok:bool, changed:bool, status_changed:bool, notes_changed:bool, message:string, reference:string, old_status:string, new_status:string}
 */
function maire_mettre_a_jour_statut_demande_etat_civil(PDO $pdo, int $id, string $statut, ?string $adminNotes = null): array
{
    maire_ensure_demandes_etat_civil_tables($pdo);

    if ($id <= 0) {
        return [
            'ok' => false,
            'changed' => false,
            'message' => 'Demande invalide.',
            'reference' => '',
            'old_status' => '',
            'new_status' => '',
        ];
    }

    if (!array_key_exists($statut, MAIRE_ETAT_CIVIL_STATUTS)) {
        return [
            'ok' => false,
            'changed' => false,
            'message' => 'Statut invalide.',
            'reference' => '',
            'old_status' => '',
            'new_status' => $statut,
        ];
    }

    $find = $pdo->prepare('
        SELECT reference_dossier, statut, admin_notes
        FROM demandes_etat_civil
        WHERE id = :id
        LIMIT 1
    ');
    $find->execute(['id' => $id]);
    $current = $find->fetch(PDO::FETCH_ASSOC);

    if ($current === false) {
        return [
            'ok' => false,
            'changed' => false,
            'message' => 'Dossier introuvable.',
            'reference' => '',
            'old_status' => '',
            'new_status' => $statut,
        ];
    }

    $currentStatus = (string) ($current['statut'] ?? '');
    $reference = (string) ($current['reference_dossier'] ?? '');
    $currentNotes = trim((string) ($current['admin_notes'] ?? ''));
    $normalizedNotes = $adminNotes !== null ? trim($adminNotes) : null;
    $statusChanged = $currentStatus !== $statut;
    $notesChanged = $normalizedNotes !== null && $normalizedNotes !== $currentNotes;

    if (!$statusChanged && !$notesChanged) {
        return [
            'ok' => true,
            'changed' => false,
            'status_changed' => false,
            'notes_changed' => false,
            'message' => 'Aucune modification à enregistrer pour le dossier ' . $reference . '.',
            'reference' => $reference,
            'old_status' => $currentStatus,
            'new_status' => $statut,
        ];
    }

    $fields = ['statut = :statut'];
    $params = [
        'id' => $id,
        'statut' => $statut,
    ];
    if ($normalizedNotes !== null) {
        $fields[] = 'admin_notes = :admin_notes';
        $params['admin_notes'] = $normalizedNotes !== '' ? mb_substr($normalizedNotes, 0, 4000) : null;
    }

    $update = $pdo->prepare('
        UPDATE demandes_etat_civil
        SET ' . implode(', ', $fields) . '
        WHERE id = :id
        LIMIT 1
    ');
    $update->execute($params);

    $changed = $update->rowCount() > 0;
    $messageParts = [];
    if ($statusChanged) {
        $messageParts[] = 'Statut du dossier ' . $reference . ' mis à jour : ' . maire_libelle_statut_demande_etat_civil($statut) . '.';
    }
    if ($notesChanged) {
        $messageParts[] = 'Le message personnalisé de la mairie a été enregistré.';
    }
    $message = implode(' ', $messageParts);
    $mailStatus = ['sent' => false, 'reason' => 'unchanged'];

    if ($changed && $statusChanged) {
        $mailStatus = maire_notifier_maj_statut_demande_etat_civil($pdo, $id, $currentStatus);
        if (($mailStatus['reason'] ?? '') === 'sent') {
            $message .= ' Un e-mail de notification a été envoyé au citoyen.';
        } elseif (($mailStatus['reason'] ?? '') === 'invalid_email') {
            $message .= ' Aucun e-mail n’a été envoyé car l’adresse du citoyen est invalide ou absente.';
        } elseif (($mailStatus['reason'] ?? '') === 'mailer_failed') {
            $message .= ' Le statut est enregistré, mais l’e-mail de notification n’a pas pu être envoyé.';
        }
    }

    return [
        'ok' => true,
        'changed' => $changed,
        'status_changed' => $statusChanged,
        'notes_changed' => $notesChanged,
        'message' => $message,
        'reference' => $reference,
        'old_status' => $currentStatus,
        'new_status' => $statut,
        'mail_sent' => (bool) ($mailStatus['sent'] ?? false),
        'mail_reason' => (string) ($mailStatus['reason'] ?? ''),
        'mail_error' => (string) ($mailStatus['error'] ?? ''),
    ];
}
