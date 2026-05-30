<?php
declare(strict_types=1);

/**
 * Demandes d’audience avec le maire (présentiel ou visioconférence).
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms-provider.php';
require_once __DIR__ . '/otp-sms.php';

const MAIRE_AUDIENCES_MOTIFS = [
    'cadre_vie' => 'Cadre de vie / voirie',
    'administratif' => 'Démarche administrative',
    'economique' => 'Économie / emploi / commerce',
    'social' => 'Action sociale',
    'jeunesse' => 'Jeunesse / éducation',
    'associatif' => 'Association / quartier',
    'autre' => 'Autre demande',
];

const MAIRE_AUDIENCES_MODES = [
    'presentiel' => 'À la mairie (présentiel)',
    'visio' => 'En ligne (visioconférence)',
];

const MAIRE_AUDIENCES_CRENEAUX = [
    'matin' => 'Matin (8h – 12h)',
    'apres_midi' => 'Après-midi (14h – 17h)',
    'indifferent' => 'Indifférent',
];

const MAIRE_AUDIENCES_STATUTS = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'terminee' => 'Terminée',
    'annulee' => 'Annulée',
    'refusee' => 'Refusée',
];

function maire_ensure_audiences_maire_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audiences_maire (
            id INT AUTO_INCREMENT PRIMARY KEY,
            citoyen_id INT NULL,
            prenom VARCHAR(80) NOT NULL,
            nom VARCHAR(80) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telephone VARCHAR(40) NULL,
            quartier VARCHAR(120) NULL,
            motif ENUM('cadre_vie', 'administratif', 'economique', 'social', 'jeunesse', 'associatif', 'autre') NOT NULL DEFAULT 'autre',
            objet VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            mode_audience ENUM('presentiel', 'visio') NOT NULL DEFAULT 'presentiel',
            date_souhaitee DATE NULL,
            creneau_souhaite ENUM('matin', 'apres_midi', 'indifferent') NOT NULL DEFAULT 'indifferent',
            statut ENUM('en_attente', 'confirmee', 'terminee', 'annulee', 'refusee') NOT NULL DEFAULT 'en_attente',
            date_audience DATETIME NULL,
            lien_visio VARCHAR(500) NULL,
            lieu_audience VARCHAR(255) NULL,
            admin_notes TEXT NULL,
            traite_par_email VARCHAR(190) NULL,
            traite_le TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_audiences_statut (statut),
            INDEX idx_audiences_date (date_audience),
            INDEX idx_audiences_citoyen (citoyen_id),
            INDEX idx_audiences_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    maire_ensure_audiences_maire_columns($pdo);
}

function maire_ensure_audiences_maire_columns(PDO $pdo): void
{
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM audiences_maire')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('type_reservation', $cols, true)) {
            $pdo->exec("ALTER TABLE audiences_maire ADD COLUMN type_reservation ENUM('creneau_fixe', 'demande_libre') NOT NULL DEFAULT 'demande_libre' AFTER mode_audience");
        }
        if (!in_array('creneau_id', $cols, true)) {
            $pdo->exec('ALTER TABLE audiences_maire ADD COLUMN creneau_id INT NULL AFTER type_reservation');
        }
        if (!in_array('telephone_verifie', $cols, true)) {
            $pdo->exec('ALTER TABLE audiences_maire ADD COLUMN telephone_verifie TINYINT(1) NOT NULL DEFAULT 0 AFTER telephone');
        }
    } catch (Throwable $e) {
        // table absente
    }
}

function maire_ensure_audiences_creneaux_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audiences_creneaux (
            id INT AUTO_INCREMENT PRIMARY KEY,
            debut DATETIME NOT NULL,
            fin DATETIME NOT NULL,
            mode_audience ENUM('presentiel', 'visio') NOT NULL DEFAULT 'presentiel',
            capacite INT NOT NULL DEFAULT 1,
            places_prises INT NOT NULL DEFAULT 0,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            notes_admin VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_creneau_debut (debut),
            INDEX idx_creneau_actif (actif, debut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function maire_libelle_audience_motif(string $code): string
{
    return MAIRE_AUDIENCES_MOTIFS[$code] ?? ucfirst($code);
}

function maire_libelle_audience_mode(string $code): string
{
    return MAIRE_AUDIENCES_MODES[$code] ?? ucfirst($code);
}

function maire_libelle_audience_creneau(string $code): string
{
    return MAIRE_AUDIENCES_CRENEAUX[$code] ?? ucfirst($code);
}

function maire_libelle_audience_statut(string $code): string
{
    return MAIRE_AUDIENCES_STATUTS[$code] ?? ucfirst($code);
}

function maire_libelle_type_reservation(string $code): string
{
    return match ($code) {
        'creneau_fixe' => 'Créneau réservé en ligne',
        'demande_libre' => 'Demande libre (date souhaitée)',
        default => ucfirst(str_replace('_', ' ', $code)),
    };
}

function maire_formater_creneau_audience(array $creneau): string
{
    $debut = strtotime((string) ($creneau['debut'] ?? ''));
    $fin = strtotime((string) ($creneau['fin'] ?? ''));
    if ($debut === false) {
        return '';
    }
    $s = date('d/m/Y à H:i', $debut);
    if ($fin !== false && date('Y-m-d', $fin) === date('Y-m-d', $debut)) {
        $s .= ' – ' . date('H:i', $fin);
    }
    $s .= ' · ' . maire_libelle_audience_mode((string) ($creneau['mode_audience'] ?? 'presentiel'));
    $places = max(0, (int) ($creneau['capacite'] ?? 1) - (int) ($creneau['places_prises'] ?? 0));
    if ($places > 0) {
        $s .= ' (' . $places . ' place' . ($places > 1 ? 's' : '') . ')';
    }
    return $s;
}

function maire_classe_badge_audience_statut(string $statut): string
{
    return match ($statut) {
        'en_attente' => 'std-feed-badge--warning',
        'confirmee' => 'std-feed-badge--info',
        'terminee' => 'std-feed-badge--success',
        'annulee' => 'std-feed-badge--danger',
        'refusee' => 'std-feed-badge--danger',
        default => 'std-feed-badge--info',
    };
}

/**
 * @param array<string, mixed> $data
 */
function maire_creer_demande_audience(PDO $pdo, array $data, ?int $citoyenId, ?string &$errMsg = null): ?int
{
    maire_ensure_audiences_maire_table($pdo);

    $prenom = trim((string) ($data['prenom'] ?? ''));
    $nom = trim((string) ($data['nom'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    if ($prenom === '' || $nom === '') {
        $errMsg = 'Prénom et nom requis.';
        return null;
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Adresse email invalide.';
        return null;
    }

    $motif = (string) ($data['motif'] ?? 'autre');
    if (!array_key_exists($motif, MAIRE_AUDIENCES_MOTIFS)) {
        $motif = 'autre';
    }
    $mode = (string) ($data['mode_audience'] ?? 'presentiel');
    if (!array_key_exists($mode, MAIRE_AUDIENCES_MODES)) {
        $mode = 'presentiel';
    }
    $creneau = (string) ($data['creneau_souhaite'] ?? 'indifferent');
    if (!array_key_exists($creneau, MAIRE_AUDIENCES_CRENEAUX)) {
        $creneau = 'indifferent';
    }

    $objet = trim((string) ($data['objet'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    if ($objet === '' || mb_strlen($objet) > 255) {
        $errMsg = 'Objet de la demande requis (≤ 255 caractères).';
        return null;
    }
    if ($message === '' || mb_strlen($message) > 5000) {
        $errMsg = 'Message requis (≤ 5000 caractères).';
        return null;
    }

    $dateSouhaitee = trim((string) ($data['date_souhaitee'] ?? ''));
    $dateSql = null;
    if ($dateSouhaitee !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dateSouhaitee);
        if ($dt === false || $dt->format('Y-m-d') !== $dateSouhaitee) {
            $errMsg = 'Date souhaitée invalide.';
            return null;
        }
        $today = new DateTime('today');
        if ($dt < $today) {
            $errMsg = 'La date souhaitée doit être aujourd’hui ou ultérieure.';
            return null;
        }
        $dateSql = $dateSouhaitee;
    }

    $telephone = trim((string) ($data['telephone'] ?? ''));
    $telNorm = maire_normaliser_telephone_sn($telephone);
    if ($telNorm === null) {
        $errMsg = 'Numéro de mobile requis (format Sénégal).';
        return null;
    }

    $otpScope = (string) ($data['otp_scope'] ?? 'audience_maire');
    $otpCode = (string) ($data['otp_code'] ?? '');
    if (!maire_otp_est_verifie($pdo, $telNorm, $otpScope)) {
        if ($otpCode === '' || !maire_otp_verifier($pdo, $telNorm, $otpScope, $otpCode, $errMsg)) {
            $errMsg = $errMsg ?? 'Vérifiez votre numéro avec le code SMS reçu.';
            return null;
        }
    }

    $quartier = trim((string) ($data['quartier'] ?? ''));
    $typeReservation = (string) ($data['type_reservation'] ?? 'demande_libre');
    if (!in_array($typeReservation, ['creneau_fixe', 'demande_libre'], true)) {
        $typeReservation = 'demande_libre';
    }
    $creneauId = (int) ($data['creneau_id'] ?? 0);

    $statutInitial = 'en_attente';
    $dateAudience = null;
    $lieuDefaut = 'Mairie de Rufisque-Est — Castor';

    try {
        $pdo->beginTransaction();

        if ($typeReservation === 'creneau_fixe') {
            if ($creneauId <= 0) {
                throw new RuntimeException('Choisissez un créneau disponible.');
            }
            maire_ensure_audiences_creneaux_table($pdo);
            $stC = $pdo->prepare('
                SELECT id, debut, fin, mode_audience, capacite, places_prises
                FROM audiences_creneaux
                WHERE id = :id AND actif = 1 AND debut > NOW()
                FOR UPDATE
            ');
            $stC->execute(['id' => $creneauId]);
            $creneau = $stC->fetch(PDO::FETCH_ASSOC);
            if ($creneau === false) {
                throw new RuntimeException('Ce créneau n’est plus disponible.');
            }
            if ((int) $creneau['places_prises'] >= (int) $creneau['capacite']) {
                throw new RuntimeException('Ce créneau est complet.');
            }
            $pdo->prepare('UPDATE audiences_creneaux SET places_prises = places_prises + 1 WHERE id = :id')
                ->execute(['id' => $creneauId]);
            $mode = (string) $creneau['mode_audience'];
            $dateAudience = (string) $creneau['debut'];
            $statutInitial = 'confirmee';
            $dateSql = null;
        } else {
            $creneauId = 0;
            if ($dateSql === null) {
                $errMsg = 'Indiquez une date souhaitée pour une demande libre.';
                $pdo->rollBack();
                return null;
            }
        }

        $ins = $pdo->prepare('
            INSERT INTO audiences_maire
                (citoyen_id, prenom, nom, email, telephone, telephone_verifie, quartier, motif, objet, message,
                 mode_audience, type_reservation, creneau_id, date_souhaitee, creneau_souhaite, statut,
                 date_audience, lieu_audience)
            VALUES
                (:cid, :prenom, :nom, :email, :tel, 1, :quartier, :motif, :objet, :msg,
                 :mode, :type_r, :cid_c, :date_s, :creneau, :statut, :date_a, :lieu)
        ');
        $ins->execute([
            'cid' => ($citoyenId !== null && $citoyenId > 0) ? $citoyenId : null,
            'prenom' => mb_substr($prenom, 0, 80),
            'nom' => mb_substr($nom, 0, 80),
            'email' => mb_substr($email, 0, 190),
            'tel' => $telNorm,
            'quartier' => $quartier !== '' ? mb_substr($quartier, 0, 120) : null,
            'motif' => $motif,
            'objet' => mb_substr($objet, 0, 255),
            'msg' => mb_substr($message, 0, 5000),
            'mode' => $mode,
            'type_r' => $typeReservation,
            'cid_c' => $creneauId > 0 ? $creneauId : null,
            'date_s' => $dateSql,
            'creneau' => $creneau,
            'statut' => $statutInitial,
            'date_a' => $dateAudience,
            'lieu' => $lieuDefaut,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        maire_notifier_nouvelle_audience($pdo, $id);
        if ($statutInitial === 'confirmee') {
            maire_envoyer_sms_confirmation_audience($pdo, $id);
        }
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMsg = $e->getMessage();
        return null;
    }
}

/** @return list<array<string, mixed>> */
function maire_lister_creneaux_disponibles(PDO $pdo, int $joursAvance = 60): array
{
    maire_ensure_audiences_creneaux_table($pdo);
    $limite = (new DateTimeImmutable('now'))->modify('+' . max(7, $joursAvance) . ' days')->format('Y-m-d H:i:s');
    try {
        $st = $pdo->prepare('
            SELECT id, debut, fin, mode_audience, capacite, places_prises, notes_admin
            FROM audiences_creneaux
            WHERE actif = 1
              AND debut > NOW()
              AND debut <= :limite
              AND places_prises < capacite
            ORDER BY debut ASC
            LIMIT 200
        ');
        $st->execute(['limite' => $limite]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_envoyer_sms_confirmation_audience(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT * FROM audiences_maire WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false || empty($row['telephone'])) {
        return;
    }
    $msg = 'Mairie Rufisque-Est : votre audience n°' . $id . ' est confirmée';
    if (!empty($row['date_audience'])) {
        $msg .= ' le ' . date('d/m/Y à H:i', strtotime((string) $row['date_audience']));
    }
    if ((string) ($row['mode_audience'] ?? '') === 'visio' && !empty($row['lien_visio'])) {
        $msg .= '. Lien : ' . $row['lien_visio'];
    } else {
        $msg .= '. Lieu : ' . ($row['lieu_audience'] ?? 'Mairie');
    }
    $err = null;
    maire_sms_provider_envoyer((string) $row['telephone'], $msg, $err);
}

function maire_notifier_nouvelle_audience(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT * FROM audiences_maire WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return;
    }

    $dest = (string) (getenv('MAIRE_CONTACT_EMAIL') ?: 'Rufisquest02@gmail.com');
    $sujet = '[Mairie Rufisque-Est] Nouvelle demande d’audience n°' . $id;
    $corps = "Une nouvelle demande d'audience a été déposée.\n\n"
        . 'Référence : #' . $id . "\n"
        . 'Demandeur : ' . $row['prenom'] . ' ' . $row['nom'] . "\n"
        . 'Email : ' . $row['email'] . "\n"
        . 'Mode : ' . maire_libelle_audience_mode((string) $row['mode_audience']) . "\n"
        . 'Objet : ' . $row['objet'] . "\n\n"
        . "Traiter : admin/audiences-maire.php\n";
    $err = null;
    maire_mailer_send($dest, $sujet, $corps, $err);

    $emailCit = (string) ($row['email'] ?? '');
    if ($emailCit !== '' && filter_var($emailCit, FILTER_VALIDATE_EMAIL)) {
        $confirmee = (string) ($row['statut'] ?? '') === 'confirmee';
        $accuse = "Bonjour " . $row['prenom'] . ",\n\n"
            . "Votre demande d'audience avec le Maire de Rufisque-Est est bien enregistrée (réf. #" . $id . ").\n";
        if ($confirmee && !empty($row['date_audience'])) {
            $accuse .= 'Votre créneau est confirmé le ' . $row['date_audience'] . ".\n"
                . 'Mode : ' . maire_libelle_audience_mode((string) $row['mode_audience']) . "\n";
        } else {
            $accuse .= "Le cabinet vous contactera pour confirmer la date et l'heure.\n";
        }
        $accuse .= "\nMairie de Rufisque-Est\n";
        $sujetCit = $confirmee ? 'Audience confirmée — Rufisque-Est' : 'Demande d’audience enregistrée — Rufisque-Est';
        maire_mailer_send($emailCit, $sujetCit, $accuse, $err);
    }
}

function maire_liste_audiences_citoyen(PDO $pdo, int $citoyenId, int $limit = 50): array
{
    maire_ensure_audiences_maire_table($pdo);
    try {
        $st = $pdo->prepare('
            SELECT * FROM audiences_maire
            WHERE citoyen_id = :cid
            ORDER BY created_at DESC
            LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute(['cid' => $citoyenId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_liste_audiences_par_email(PDO $pdo, string $email, int $limit = 50): array
{
    maire_ensure_audiences_maire_table($pdo);
    try {
        $st = $pdo->prepare('
            SELECT * FROM audiences_maire
            WHERE email = :email
            ORDER BY created_at DESC
            LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute(['email' => mb_strtolower(trim($email))]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param array{statut?:string, mode?:string, type?:string, q?:string} $filters
 * @return list<array<string, mixed>>
 */
function maire_liste_audiences_admin(PDO $pdo, array $filters = [], int $limit = 200): array
{
    maire_ensure_audiences_maire_table($pdo);

    $where = [];
    $params = [];

    $statut = trim((string) ($filters['statut'] ?? ''));
    if ($statut !== '' && array_key_exists($statut, MAIRE_AUDIENCES_STATUTS)) {
        $where[] = 'statut = :statut';
        $params['statut'] = $statut;
    }

    $mode = trim((string) ($filters['mode'] ?? ''));
    if ($mode !== '' && array_key_exists($mode, MAIRE_AUDIENCES_MODES)) {
        $where[] = 'mode_audience = :mode';
        $params['mode'] = $mode;
    }

    $type = trim((string) ($filters['type'] ?? ''));
    if ($type !== '' && in_array($type, ['creneau_fixe', 'demande_libre'], true)) {
        $where[] = 'type_reservation = :type_reservation';
        $params['type_reservation'] = $type;
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(
            CAST(id AS CHAR) = :search_exact
            OR prenom LIKE :search
            OR nom LIKE :search
            OR email LIKE :search
            OR telephone LIKE :search
            OR quartier LIKE :search
            OR objet LIKE :search
            OR message LIKE :search
        )';
        $params['search_exact'] = $search;
        $params['search'] = '%' . $search . '%';
    }

    try {
        $sql = 'SELECT * FROM audiences_maire';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY FIELD(statut, 'en_attente', 'confirmee', 'terminee', 'annulee', 'refusee'), created_at DESC LIMIT " . max(1, min(500, $limit));

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{total:int, aujourd_hui:int, en_attente:int, confirmees:int, visio:int, creneaux_fixes:int}
 */
function maire_resumer_audiences_admin(PDO $pdo): array
{
    maire_ensure_audiences_maire_table($pdo);

    try {
        $row = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS aujourd_hui,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
                SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) AS confirmees,
                SUM(CASE WHEN mode_audience = 'visio' THEN 1 ELSE 0 END) AS visio,
                SUM(CASE WHEN type_reservation = 'creneau_fixe' THEN 1 ELSE 0 END) AS creneaux_fixes
            FROM audiences_maire
        ")->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['total' => 0, 'aujourd_hui' => 0, 'en_attente' => 0, 'confirmees' => 0, 'visio' => 0, 'creneaux_fixes' => 0];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'aujourd_hui' => (int) ($row['aujourd_hui'] ?? 0),
            'en_attente' => (int) ($row['en_attente'] ?? 0),
            'confirmees' => (int) ($row['confirmees'] ?? 0),
            'visio' => (int) ($row['visio'] ?? 0),
            'creneaux_fixes' => (int) ($row['creneaux_fixes'] ?? 0),
        ];
    } catch (Throwable $e) {
        return ['total' => 0, 'aujourd_hui' => 0, 'en_attente' => 0, 'confirmees' => 0, 'visio' => 0, 'creneaux_fixes' => 0];
    }
}

/** @return array<string, int> */
function maire_compter_audiences_par_statut(PDO $pdo): array
{
    maire_ensure_audiences_maire_table($pdo);
    $out = array_fill_keys(array_keys(MAIRE_AUDIENCES_STATUTS), 0);
    try {
        $rows = $pdo->query('SELECT statut, COUNT(*) AS n FROM audiences_maire GROUP BY statut')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $s = (string) ($row['statut'] ?? '');
            if (array_key_exists($s, $out)) {
                $out[$s] = (int) ($row['n'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return $out;
}

function maire_mettre_a_jour_audience_admin(
    PDO $pdo,
    int $id,
    string $statut,
    ?string $dateAudience,
    ?string $lienVisio,
    ?string $lieuAudience,
    ?string $adminNotes,
    string $traiteParEmail
): bool {
    maire_ensure_audiences_maire_table($pdo);
    if (!array_key_exists($statut, MAIRE_AUDIENCES_STATUTS)) {
        return false;
    }

    $dateSql = null;
    if ($dateAudience !== null && trim($dateAudience) !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', trim($dateAudience));
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i', trim($dateAudience));
        }
        if ($dt !== false) {
            $dateSql = $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $st = $pdo->prepare('
            UPDATE audiences_maire SET
                statut = :statut,
                date_audience = :date_a,
                lien_visio = :visio,
                lieu_audience = :lieu,
                admin_notes = :notes,
                traite_par_email = :email,
                traite_le = NOW()
            WHERE id = :id
        ');
        $st->execute([
            'statut' => $statut,
            'date_a' => $dateSql,
            'visio' => $lienVisio !== null && $lienVisio !== '' ? mb_substr($lienVisio, 0, 500) : null,
            'lieu' => $lieuAudience !== null && $lieuAudience !== '' ? mb_substr($lieuAudience, 0, 255) : null,
            'notes' => $adminNotes !== null && $adminNotes !== '' ? mb_substr($adminNotes, 0, 4000) : null,
            'email' => mb_substr($traiteParEmail, 0, 190),
            'id' => $id,
        ]);
        if ($st->rowCount() > 0 && in_array($statut, ['confirmee', 'refusee', 'annulee'], true)) {
            maire_notifier_maj_audience_citoyen($pdo, $id);
        }
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_notifier_maj_audience_citoyen(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT * FROM audiences_maire WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return;
    }
    $email = (string) ($row['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $statut = maire_libelle_audience_statut((string) $row['statut']);
    $corps = "Bonjour " . $row['prenom'] . ",\n\n"
        . "Votre demande d'audience (réf. #" . $id . ") est maintenant : " . $statut . ".\n";
    if (!empty($row['date_audience'])) {
        $corps .= 'Date prévue : ' . $row['date_audience'] . "\n";
    }
    if ((string) ($row['mode_audience'] ?? '') === 'visio' && !empty($row['lien_visio'])) {
        $corps .= 'Lien visio : ' . $row['lien_visio'] . "\n";
    }
    if (!empty($row['lieu_audience'])) {
        $corps .= 'Lieu : ' . $row['lieu_audience'] . "\n";
    }
    if (!empty($row['admin_notes'])) {
        $corps .= "\nMessage de la mairie :\n" . $row['admin_notes'] . "\n";
    }
    $corps .= "\nMairie de Rufisque-Est\n";

    $err = null;
    maire_mailer_send($email, 'Mise à jour de votre audience — Rufisque-Est', $corps, $err);
    if (in_array((string) ($row['statut'] ?? ''), ['confirmee'], true) && !empty($row['telephone_verifie'])) {
        maire_envoyer_sms_confirmation_audience($pdo, $id);
    }
}

/** @return list<array<string, mixed>> */
function maire_lister_creneaux_admin(PDO $pdo, bool $futursSeulement = false, int $limit = 300): array
{
    maire_ensure_audiences_creneaux_table($pdo);
    $sql = '
        SELECT id, debut, fin, mode_audience, capacite, places_prises, actif, notes_admin, created_at
        FROM audiences_creneaux
    ';
    if ($futursSeulement) {
        $sql .= ' WHERE debut > NOW() AND actif = 1';
    }
    $sql .= ' ORDER BY debut DESC LIMIT ' . max(1, min(500, $limit));
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_admin_creer_creneau_audience(
    PDO $pdo,
    string $debut,
    string $fin,
    string $mode,
    int $capacite,
    ?string $notes,
    ?string &$errMsg = null
): ?int {
    maire_ensure_audiences_creneaux_table($pdo);
    if (!array_key_exists($mode, MAIRE_AUDIENCES_MODES)) {
        $mode = 'presentiel';
    }
    $capacite = max(1, min(20, $capacite));
    $dtDebut = DateTime::createFromFormat('Y-m-d\TH:i', $debut) ?: DateTime::createFromFormat('Y-m-d H:i', $debut);
    $dtFin = DateTime::createFromFormat('Y-m-d\TH:i', $fin) ?: DateTime::createFromFormat('Y-m-d H:i', $fin);
    if ($dtDebut === false || $dtFin === false) {
        $errMsg = 'Date/heure de début ou de fin invalide.';
        return null;
    }
    if ($dtFin <= $dtDebut) {
        $errMsg = 'La fin doit être après le début.';
        return null;
    }
    if ($dtDebut < new DateTime('now')) {
        $errMsg = 'Le créneau doit être dans le futur.';
        return null;
    }
    try {
        $st = $pdo->prepare('
            INSERT INTO audiences_creneaux (debut, fin, mode_audience, capacite, places_prises, actif, notes_admin)
            VALUES (:d, :f, :m, :c, 0, 1, :n)
        ');
        $st->execute([
            'd' => $dtDebut->format('Y-m-d H:i:s'),
            'f' => $dtFin->format('Y-m-d H:i:s'),
            'm' => $mode,
            'c' => $capacite,
            'n' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 255) : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Erreur : ' . $e->getMessage();
        return null;
    }
}

function maire_admin_desactiver_creneau_audience(PDO $pdo, int $id): bool
{
    maire_ensure_audiences_creneaux_table($pdo);
    if ($id <= 0) {
        return false;
    }
    try {
        $st = $pdo->prepare('UPDATE audiences_creneaux SET actif = 0 WHERE id = :id AND places_prises = 0');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}
