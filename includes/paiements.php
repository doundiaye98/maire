<?php
declare(strict_types=1);

require_once __DIR__ . '/paiement-providers.php';

/**
 * Paiements en ligne pour services communaux.
 *
 * Étapes :
 *   1. maire_paiement_creer()           -> insère ligne, statut "initie"
 *   2. maire_paiement_lancer_provider() -> appel API + redirect
 *   3. callback / webhook               -> maire_paiement_marquer_paye() ou _echec()
 *
 * Le statut est dénormalisé : la BDD reste source de vérité. Le payload provider
 * est sérialisé en JSON dans la colonne TEXT (auditabilité).
 */

const MAIRE_PAIEMENTS_STATUTS = [
    'initie'     => 'Initié',
    'en_attente' => 'En attente',
    'paye'       => 'Payé',
    'echec'      => 'Échec',
    'annule'     => 'Annulé',
    'rembourse'  => 'Remboursé',
];

const MAIRE_PAIEMENTS_CATEGORIES = [
    'taxe'              => 'Taxe locale',
    'document_express'  => 'Document express',
    'reservation'       => 'Réservation',
    'autre'             => 'Autre service',
];

/**
 * Catalogue des services payants proposés (modifiable).
 *
 * @var array<string, array{code:string,categorie:string,libelle:string,prix:int,description:string,delai?:string}>
 */
const MAIRE_PAIEMENTS_CATALOGUE = [
    'taxe_habitation' => [
        'code' => 'taxe_habitation',
        'categorie' => 'taxe',
        'libelle' => 'Taxe d’habitation annuelle',
        'prix' => 25000,
        'description' => 'Taxe locale annuelle réglée par les résidents du territoire communal.',
    ],
    'taxe_ordures' => [
        'code' => 'taxe_ordures',
        'categorie' => 'taxe',
        'libelle' => 'Taxe enlèvement des ordures (TEOM)',
        'prix' => 15000,
        'description' => 'Contribution annuelle au service de collecte et traitement des déchets ménagers.',
    ],
    'taxe_marche' => [
        'code' => 'taxe_marche',
        'categorie' => 'taxe',
        'libelle' => 'Droit de place — marché communal',
        'prix' => 5000,
        'description' => 'Droit mensuel d’occupation d’une place au marché central pour les commerçants.',
    ],
    'doc_extrait_naissance' => [
        'code' => 'doc_extrait_naissance',
        'categorie' => 'document_express',
        'libelle' => 'Extrait de naissance — express 48h',
        'prix' => 3000,
        'description' => 'Délivrance d’un extrait de naissance en 48 heures ouvrées.',
        'delai' => '48 heures',
    ],
    'doc_certificat_residence' => [
        'code' => 'doc_certificat_residence',
        'categorie' => 'document_express',
        'libelle' => 'Certificat de résidence — express 24h',
        'prix' => 2500,
        'description' => 'Certificat de résidence délivré en 24 heures sur présentation d’un justificatif.',
        'delai' => '24 heures',
    ],
    'doc_certificat_mariage' => [
        'code' => 'doc_certificat_mariage',
        'categorie' => 'document_express',
        'libelle' => 'Copie d’acte de mariage — express 72h',
        'prix' => 4000,
        'description' => 'Copie certifiée conforme d’un acte de mariage en 72 heures.',
        'delai' => '72 heures',
    ],
    'reservation_salle_fetes' => [
        'code' => 'reservation_salle_fetes',
        'categorie' => 'reservation',
        'libelle' => 'Réservation salle des fêtes — 1 journée',
        'prix' => 75000,
        'description' => 'Location de la salle des fêtes communale pour 24 heures (cérémonies, réunions).',
    ],
    'reservation_stade' => [
        'code' => 'reservation_stade',
        'categorie' => 'reservation',
        'libelle' => 'Réservation stade municipal — 2 heures',
        'prix' => 20000,
        'description' => 'Créneau de 2h sur le stade municipal pour pratique sportive associative.',
    ],
];

function maire_ensure_paiements_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS paiements_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(40) NOT NULL,
            citoyen_id INT NULL,
            visiteur_nom VARCHAR(120) NULL,
            visiteur_email VARCHAR(190) NULL,
            visiteur_telephone VARCHAR(40) NULL,
            service_categorie ENUM('taxe', 'document_express', 'reservation', 'autre') NOT NULL DEFAULT 'autre',
            service_code VARCHAR(60) NOT NULL,
            service_libelle VARCHAR(200) NOT NULL,
            service_details TEXT NULL,
            montant DECIMAL(12, 2) NOT NULL,
            devise CHAR(3) NOT NULL DEFAULT 'XOF',
            provider VARCHAR(30) NOT NULL DEFAULT 'log',
            provider_reference VARCHAR(120) NULL,
            statut ENUM('initie', 'en_attente', 'paye', 'echec', 'annule', 'rembourse') NOT NULL DEFAULT 'initie',
            payload_init TEXT NULL,
            payload_callback TEXT NULL,
            paye_le TIMESTAMP NULL DEFAULT NULL,
            expire_le TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_paiement_ref (reference),
            INDEX idx_paie_statut (statut),
            INDEX idx_paie_citoyen (citoyen_id),
            INDEX idx_paie_categorie (service_categorie),
            INDEX idx_paie_provider (provider)
        )
    ");
}

function maire_paiement_libelle_statut(string $code): string
{
    return MAIRE_PAIEMENTS_STATUTS[$code] ?? ucfirst($code);
}

function maire_paiement_libelle_categorie(string $code): string
{
    return MAIRE_PAIEMENTS_CATEGORIES[$code] ?? ucfirst($code);
}

function maire_paiement_classe_badge(string $statut): string
{
    return match ($statut) {
        'paye' => 'std-feed-badge--success',
        'en_attente', 'initie' => 'std-feed-badge',
        'echec', 'annule' => 'std-feed-badge--warning',
        default => 'std-feed-badge',
    };
}

/**
 * @return array<string, array{code:string,categorie:string,libelle:string,prix:int,description:string,delai?:string}>
 */
function maire_paiements_catalogue(?string $categorie = null): array
{
    if ($categorie === null) {
        return MAIRE_PAIEMENTS_CATALOGUE;
    }
    return array_filter(MAIRE_PAIEMENTS_CATALOGUE, fn($s) => $s['categorie'] === $categorie);
}

function maire_paiements_service(string $code): ?array
{
    return MAIRE_PAIEMENTS_CATALOGUE[$code] ?? null;
}

/**
 * Génère une référence unique de paiement type PAY-20260511-XYZA12.
 */
function maire_paiement_generer_reference(): string
{
    return 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Crée une transaction en BDD (statut "initie"). Renvoie l'id.
 *
 * @param array{
 *   service_code:string,
 *   citoyen_id?:?int,
 *   visiteur_nom?:?string,
 *   visiteur_email?:?string,
 *   visiteur_telephone?:?string,
 *   service_details?:?string,
 *   provider?:string,
 * } $data
 */
function maire_paiement_creer(PDO $pdo, array $data, ?string &$errMsg = null): ?int
{
    maire_ensure_paiements_table($pdo);
    $service = maire_paiements_service((string) ($data['service_code'] ?? ''));
    if ($service === null) {
        $errMsg = 'Service inconnu dans le catalogue.';
        return null;
    }
    $provider = (string) ($data['provider'] ?? MAIRE_PAIEMENT_PROVIDER_DEFAUT);
    if (!array_key_exists($provider, MAIRE_PAIEMENT_PROVIDERS)) {
        $errMsg = 'Moyen de paiement non supporté.';
        return null;
    }
    $citoyenId = isset($data['citoyen_id']) && (int) $data['citoyen_id'] > 0 ? (int) $data['citoyen_id'] : null;
    $nom = isset($data['visiteur_nom']) ? trim((string) $data['visiteur_nom']) : null;
    $email = isset($data['visiteur_email']) ? trim((string) $data['visiteur_email']) : null;
    $tel = isset($data['visiteur_telephone']) ? trim((string) $data['visiteur_telephone']) : null;

    if ($citoyenId === null) {
        if ($nom === null || $nom === '' || $tel === null || $tel === '') {
            $errMsg = 'Nom complet et téléphone requis pour un paiement sans compte.';
            return null;
        }
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errMsg = 'Adresse e-mail invalide.';
            return null;
        }
    }

    $reference = maire_paiement_generer_reference();
    try {
        $st = $pdo->prepare('
            INSERT INTO paiements_services
                (reference, citoyen_id, visiteur_nom, visiteur_email, visiteur_telephone,
                 service_categorie, service_code, service_libelle, service_details,
                 montant, devise, provider, statut, expire_le)
            VALUES
                (:ref, :cid, :nom, :em, :tel,
                 :cat, :code, :lib, :det,
                 :mt, :dev, :prov, "initie", DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ');
        $st->execute([
            'ref' => $reference,
            'cid' => $citoyenId,
            'nom' => $nom !== null ? mb_substr($nom, 0, 120) : null,
            'em' => $email !== null ? mb_substr($email, 0, 190) : null,
            'tel' => $tel !== null ? mb_substr($tel, 0, 40) : null,
            'cat' => $service['categorie'],
            'code' => mb_substr($service['code'], 0, 60),
            'lib' => mb_substr($service['libelle'], 0, 200),
            'det' => isset($data['service_details']) ? mb_substr((string) $data['service_details'], 0, 4000) : null,
            'mt' => (float) $service['prix'],
            'dev' => MAIRE_PAIEMENT_DEVISE,
            'prov' => $provider,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Création paiement impossible : ' . $e->getMessage();
        return null;
    }
}

function maire_paiement_load(PDO $pdo, int $id): ?array
{
    try {
        $st = $pdo->prepare('SELECT * FROM paiements_services WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_paiement_load_by_reference(PDO $pdo, string $reference): ?array
{
    try {
        $st = $pdo->prepare('SELECT * FROM paiements_services WHERE reference = :ref LIMIT 1');
        $st->execute(['ref' => $reference]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Appelle le provider pour lancer la transaction.
 * Met à jour la référence externe + statut "en_attente".
 *
 * @return array{ok:bool,redirect_url:?string,error:?string}
 */
function maire_paiement_lancer_provider(PDO $pdo, int $id, array $urls): array
{
    $paie = maire_paiement_load($pdo, $id);
    if ($paie === null) {
        return ['ok' => false, 'redirect_url' => null, 'error' => 'Paiement introuvable.'];
    }
    $provider = (string) $paie['provider'];
    $ctx = [
        'reference' => (string) $paie['reference'],
        'montant' => (float) $paie['montant'],
        'devise' => (string) $paie['devise'],
        'libelle' => (string) $paie['service_libelle'],
        'return_url' => (string) ($urls['return_url'] ?? ''),
        'cancel_url' => (string) ($urls['cancel_url'] ?? ''),
        'webhook_url' => (string) ($urls['webhook_url'] ?? ''),
        'client_email' => (string) ($paie['visiteur_email'] ?? ''),
        'client_telephone' => (string) ($paie['visiteur_telephone'] ?? ''),
    ];
    $resp = maire_paiement_initier($provider, $ctx);

    try {
        $st = $pdo->prepare('
            UPDATE paiements_services
            SET provider_reference = :pr,
                statut = :st,
                payload_init = :pl
            WHERE id = :id
        ');
        $st->execute([
            'pr' => $resp['provider_ref'] !== null ? mb_substr((string) $resp['provider_ref'], 0, 120) : null,
            'st' => $resp['ok'] ? 'en_attente' : 'echec',
            'pl' => json_encode($resp['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'id' => $id,
        ]);
    } catch (Throwable $e) {
        // tolérant : on retourne quand même la réponse provider
    }

    return [
        'ok' => $resp['ok'],
        'redirect_url' => $resp['redirect_url'],
        'error' => $resp['error'],
    ];
}

function maire_paiement_marquer_paye(PDO $pdo, int $id, array $payloadCallback = []): bool
{
    try {
        $st = $pdo->prepare('
            UPDATE paiements_services
            SET statut = "paye",
                paye_le = NOW(),
                payload_callback = :pl
            WHERE id = :id AND statut IN ("initie", "en_attente")
        ');
        $st->execute([
            'pl' => json_encode($payloadCallback, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'id' => $id,
        ]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_paiement_marquer_echec(PDO $pdo, int $id, array $payloadCallback = []): bool
{
    try {
        $st = $pdo->prepare('
            UPDATE paiements_services
            SET statut = "echec",
                payload_callback = :pl
            WHERE id = :id AND statut IN ("initie", "en_attente")
        ');
        $st->execute([
            'pl' => json_encode($payloadCallback, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'id' => $id,
        ]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_paiement_changer_statut_admin(PDO $pdo, int $id, string $nouveauStatut): bool
{
    if (!array_key_exists($nouveauStatut, MAIRE_PAIEMENTS_STATUTS)) {
        return false;
    }
    try {
        $sql = 'UPDATE paiements_services SET statut = :st';
        if ($nouveauStatut === 'paye') {
            $sql .= ', paye_le = IFNULL(paye_le, NOW())';
        }
        $sql .= ' WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute(['st' => $nouveauStatut, 'id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array{total:int,initie:int,en_attente:int,paye:int,echec:int,montant_paye:float,montant_attente:float}
 */
function maire_paiements_compteurs(PDO $pdo): array
{
    maire_ensure_paiements_table($pdo);
    $r = ['total' => 0, 'initie' => 0, 'en_attente' => 0, 'paye' => 0, 'echec' => 0, 'montant_paye' => 0.0, 'montant_attente' => 0.0];
    try {
        $row = $pdo->query("
            SELECT COUNT(*) AS total,
                   SUM(statut = 'initie') AS i,
                   SUM(statut = 'en_attente') AS a,
                   SUM(statut = 'paye') AS p,
                   SUM(statut = 'echec') AS e,
                   COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END), 0) AS mt_paye,
                   COALESCE(SUM(CASE WHEN statut IN ('initie', 'en_attente') THEN montant ELSE 0 END), 0) AS mt_attente
            FROM paiements_services
        ")->fetch();
        if ($row !== false) {
            $r['total'] = (int) $row['total'];
            $r['initie'] = (int) $row['i'];
            $r['en_attente'] = (int) $row['a'];
            $r['paye'] = (int) $row['p'];
            $r['echec'] = (int) $row['e'];
            $r['montant_paye'] = (float) $row['mt_paye'];
            $r['montant_attente'] = (float) $row['mt_attente'];
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return $r;
}

function maire_paiements_liste(PDO $pdo, ?string $statut = null, ?string $categorie = null, int $limit = 100): array
{
    maire_ensure_paiements_table($pdo);
    $where = [];
    $params = [];
    if ($statut !== null && array_key_exists($statut, MAIRE_PAIEMENTS_STATUTS)) {
        $where[] = 'statut = :st';
        $params['st'] = $statut;
    }
    if ($categorie !== null && array_key_exists($categorie, MAIRE_PAIEMENTS_CATEGORIES)) {
        $where[] = 'service_categorie = :cat';
        $params['cat'] = $categorie;
    }
    $sql = 'SELECT p.*, c.email AS citoyen_email, c.prenom AS citoyen_prenom, c.nom AS citoyen_nom
            FROM paiements_services p
            LEFT JOIN citoyens c ON c.id = p.citoyen_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.created_at DESC LIMIT ' . max(1, min(500, $limit));
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_paiements_pour_citoyen(PDO $pdo, int $citoyenId, int $limit = 20): array
{
    try {
        $st = $pdo->prepare('SELECT * FROM paiements_services WHERE citoyen_id = :id ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)));
        $st->execute(['id' => $citoyenId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_paiement_format_montant(float $montant, string $devise = 'XOF'): string
{
    return number_format($montant, 0, ',', ' ') . ' ' . $devise;
}
