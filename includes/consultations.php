<?php
declare(strict_types=1);

/**
 * Consultations & votes électroniques.
 *
 * - Admin crée une consultation (titre, question, options[], date de clôture)
 * - Statuts : brouillon → ouverte → fermee (manuel ou automatique à la date_fin)
 * - Citoyen authentifié vote (1 voix unique par option ; multi-choix possible)
 * - UNIQUE(consultation_id, citoyen_id, option_id) prévient le double vote
 * - Compteurs dénormalisés (nb_votes_total, nb_options) mis à jour à chaque vote
 */

const MAIRE_CONSULTATIONS_TYPES = [
    'vote'         => 'Vote',
    'sondage'      => 'Sondage',
    'consultation' => 'Consultation',
];

const MAIRE_CONSULTATIONS_STATUTS = [
    'brouillon' => 'Brouillon',
    'ouverte'   => 'Ouverte',
    'fermee'    => 'Fermée',
];

function maire_ensure_consultations_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS consultations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('vote', 'sondage', 'consultation') NOT NULL DEFAULT 'sondage',
            titre VARCHAR(200) NOT NULL,
            question TEXT NOT NULL,
            description TEXT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            statut ENUM('brouillon', 'ouverte', 'fermee') NOT NULL DEFAULT 'brouillon',
            resultats_publics TINYINT(1) NOT NULL DEFAULT 1,
            multi_choix TINYINT(1) NOT NULL DEFAULT 0,
            nb_votes_total INT NOT NULL DEFAULT 0,
            nb_options INT NOT NULL DEFAULT 0,
            cree_par_email VARCHAR(190) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_consult_statut (statut),
            INDEX idx_consult_date (date_fin)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS consultations_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            consultation_id INT NOT NULL,
            libelle VARCHAR(220) NOT NULL,
            ordre INT NOT NULL DEFAULT 0,
            nb_votes INT NOT NULL DEFAULT 0,
            INDEX idx_option_consult (consultation_id),
            CONSTRAINT fk_option_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS consultations_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            consultation_id INT NOT NULL,
            citoyen_id INT NOT NULL,
            option_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_vote (consultation_id, citoyen_id, option_id),
            INDEX idx_vote_consult (consultation_id),
            INDEX idx_vote_citoyen (citoyen_id),
            CONSTRAINT fk_vote_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
            CONSTRAINT fk_vote_option FOREIGN KEY (option_id) REFERENCES consultations_options(id) ON DELETE CASCADE,
            CONSTRAINT fk_vote_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE CASCADE
        )
    ");
}

function maire_libelle_type_consultation(string $code): string
{
    return MAIRE_CONSULTATIONS_TYPES[$code] ?? ucfirst($code);
}

function maire_libelle_statut_consultation(string $code): string
{
    return MAIRE_CONSULTATIONS_STATUTS[$code] ?? ucfirst($code);
}

function maire_classe_badge_statut_consultation(string $statut): string
{
    return match ($statut) {
        'ouverte' => 'std-feed-badge--success',
        'fermee' => 'std-feed-badge--warning',
        default => 'std-feed-badge',
    };
}

/**
 * Met à jour le statut des consultations selon date_fin (passage automatique en 'fermee').
 */
function maire_sync_statuts_consultations(PDO $pdo): void
{
    try {
        $pdo->exec("UPDATE consultations SET statut = 'fermee' WHERE statut = 'ouverte' AND date_fin < CURDATE()");
    } catch (Throwable $e) {
        // tolérant
    }
}

/**
 * Crée une consultation avec ses options.
 *
 * @param array{type:string,titre:string,question:string,description:?string,date_debut:string,date_fin:string,multi_choix:bool,resultats_publics:bool} $data
 * @param list<string> $options
 * @return int|null id créé, ou null avec $errMsg
 */
function maire_creer_consultation(PDO $pdo, array $data, array $options, ?string $auteur, ?string &$errMsg = null): ?int
{
    maire_ensure_consultations_tables($pdo);

    $type = (string) ($data['type'] ?? 'sondage');
    if (!array_key_exists($type, MAIRE_CONSULTATIONS_TYPES)) {
        $type = 'sondage';
    }
    $titre = trim((string) ($data['titre'] ?? ''));
    $question = trim((string) ($data['question'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $dateDebut = trim((string) ($data['date_debut'] ?? ''));
    $dateFin = trim((string) ($data['date_fin'] ?? ''));
    $multi = !empty($data['multi_choix']);
    $public = isset($data['resultats_publics']) ? (bool) $data['resultats_publics'] : true;

    if ($titre === '' || mb_strlen($titre) > 200) {
        $errMsg = 'Titre requis (≤ 200 caractères).';
        return null;
    }
    if ($question === '' || mb_strlen($question) > 1000) {
        $errMsg = 'Question requise (≤ 1000 caractères).';
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
        $errMsg = 'Dates invalides (format AAAA-MM-JJ).';
        return null;
    }
    if ($dateFin < $dateDebut) {
        $errMsg = 'La date de clôture doit être postérieure ou égale à la date de début.';
        return null;
    }

    // Filtrer + dédoublonner les options
    $optionsClean = [];
    foreach ($options as $opt) {
        $opt = trim((string) $opt);
        if ($opt === '' || mb_strlen($opt) > 220) {
            continue;
        }
        if (!in_array($opt, $optionsClean, true)) {
            $optionsClean[] = $opt;
        }
    }
    if (count($optionsClean) < 2) {
        $errMsg = 'Au moins 2 options de réponse distinctes sont requises.';
        return null;
    }
    if (count($optionsClean) > 20) {
        $errMsg = 'Maximum 20 options par consultation.';
        return null;
    }

    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare('
            INSERT INTO consultations
                (type, titre, question, description, date_debut, date_fin, statut, resultats_publics, multi_choix, nb_options, cree_par_email)
            VALUES (:t, :tit, :q, :d, :d1, :d2, "brouillon", :rp, :mc, :no, :auteur)
        ');
        $ins->execute([
            't' => $type,
            'tit' => mb_substr($titre, 0, 200),
            'q' => mb_substr($question, 0, 1000),
            'd' => $description !== '' ? mb_substr($description, 0, 4000) : null,
            'd1' => $dateDebut,
            'd2' => $dateFin,
            'rp' => $public ? 1 : 0,
            'mc' => $multi ? 1 : 0,
            'no' => count($optionsClean),
            'auteur' => $auteur !== null ? mb_substr($auteur, 0, 190) : null,
        ]);
        $cid = (int) $pdo->lastInsertId();

        $insOpt = $pdo->prepare('INSERT INTO consultations_options (consultation_id, libelle, ordre) VALUES (:cid, :lib, :ord)');
        foreach ($optionsClean as $i => $lib) {
            $insOpt->execute(['cid' => $cid, 'lib' => $lib, 'ord' => $i]);
        }

        $pdo->commit();
        return $cid;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMsg = 'Création échouée : ' . $e->getMessage();
        return null;
    }
}

function maire_changer_statut_consultation(PDO $pdo, int $id, string $nouveauStatut): bool
{
    if (!array_key_exists($nouveauStatut, MAIRE_CONSULTATIONS_STATUTS)) {
        return false;
    }
    try {
        $st = $pdo->prepare('UPDATE consultations SET statut = :s WHERE id = :id');
        $st->execute(['s' => $nouveauStatut, 'id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_supprimer_consultation(PDO $pdo, int $id): bool
{
    try {
        $st = $pdo->prepare('DELETE FROM consultations WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function maire_load_consultation(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT * FROM consultations WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_options_consultation(PDO $pdo, int $consultationId): array
{
    try {
        $st = $pdo->prepare('SELECT id, libelle, ordre, nb_votes FROM consultations_options WHERE consultation_id = :cid ORDER BY ordre ASC, id ASC');
        $st->execute(['cid' => $consultationId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Le citoyen a-t-il déjà voté pour cette consultation ?
 */
function maire_citoyen_a_vote(PDO $pdo, int $consultationId, int $citoyenId): bool
{
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM consultations_votes WHERE consultation_id = :cid AND citoyen_id = :uid');
        $st->execute(['cid' => $consultationId, 'uid' => $citoyenId]);
        return ((int) $st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Options votées par le citoyen pour cette consultation.
 *
 * @return list<int>
 */
function maire_options_choisies_par_citoyen(PDO $pdo, int $consultationId, int $citoyenId): array
{
    try {
        $st = $pdo->prepare('SELECT option_id FROM consultations_votes WHERE consultation_id = :cid AND citoyen_id = :uid');
        $st->execute(['cid' => $consultationId, 'uid' => $citoyenId]);
        return array_map('intval', array_column($st->fetchAll(), 'option_id'));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Enregistre le vote d'un citoyen (1 ou plusieurs options selon multi_choix).
 * Transactionnel : tout ou rien.
 *
 * @param list<int> $optionIds
 * @return bool true si succès, false sinon (avec $errMsg renseigné)
 */
function maire_voter(PDO $pdo, int $consultationId, int $citoyenId, array $optionIds, ?string &$errMsg = null): bool
{
    $consult = maire_load_consultation($pdo, $consultationId);
    if ($consult === null) {
        $errMsg = 'Consultation introuvable.';
        return false;
    }
    if ((string) ($consult['statut'] ?? '') !== 'ouverte') {
        $errMsg = 'Cette consultation n’est pas ouverte au vote.';
        return false;
    }
    $today = date('Y-m-d');
    if ((string) ($consult['date_debut'] ?? '') > $today || (string) ($consult['date_fin'] ?? '') < $today) {
        $errMsg = 'Le vote est fermé pour cette consultation.';
        return false;
    }
    if (maire_citoyen_a_vote($pdo, $consultationId, $citoyenId)) {
        $errMsg = 'Vous avez déjà voté pour cette consultation.';
        return false;
    }

    $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds), fn($v) => $v > 0)));
    if (empty($optionIds)) {
        $errMsg = 'Veuillez sélectionner au moins une option.';
        return false;
    }
    $multi = (int) ($consult['multi_choix'] ?? 0) === 1;
    if (!$multi && count($optionIds) > 1) {
        $errMsg = 'Une seule option autorisée pour cette consultation.';
        return false;
    }

    // Valider que toutes les options appartiennent à la consultation
    $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
    try {
        $stChk = $pdo->prepare("SELECT COUNT(*) FROM consultations_options WHERE consultation_id = ? AND id IN ($placeholders)");
        $stChk->execute(array_merge([$consultationId], $optionIds));
        if ((int) $stChk->fetchColumn() !== count($optionIds)) {
            $errMsg = 'Options invalides.';
            return false;
        }
    } catch (Throwable $e) {
        $errMsg = 'Erreur de validation : ' . $e->getMessage();
        return false;
    }

    try {
        $pdo->beginTransaction();
        $insVote = $pdo->prepare('INSERT INTO consultations_votes (consultation_id, citoyen_id, option_id) VALUES (:cid, :uid, :oid)');
        $incOpt = $pdo->prepare('UPDATE consultations_options SET nb_votes = nb_votes + 1 WHERE id = :oid');
        foreach ($optionIds as $oid) {
            $insVote->execute(['cid' => $consultationId, 'uid' => $citoyenId, 'oid' => $oid]);
            $incOpt->execute(['oid' => $oid]);
        }
        // nb_votes_total = nombre de citoyens distincts ayant voté (pas la somme des options en multi-choix)
        $pdo->prepare('
            UPDATE consultations
            SET nb_votes_total = (SELECT COUNT(DISTINCT citoyen_id) FROM consultations_votes WHERE consultation_id = :cid)
            WHERE id = :cid
        ')->execute(['cid' => $consultationId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMsg = 'Vote impossible : ' . $e->getMessage();
        return false;
    }
}

/**
 * Liste des consultations visibles publiquement (ouvertes ou fermées).
 */
function maire_liste_consultations_publiques(PDO $pdo, int $limit = 50): array
{
    maire_ensure_consultations_tables($pdo);
    maire_sync_statuts_consultations($pdo);
    try {
        $st = $pdo->query("SELECT * FROM consultations WHERE statut IN ('ouverte', 'fermee') ORDER BY (statut = 'ouverte') DESC, date_fin DESC LIMIT " . max(1, min(200, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_liste_consultations_admin(PDO $pdo, int $limit = 100): array
{
    maire_ensure_consultations_tables($pdo);
    maire_sync_statuts_consultations($pdo);
    try {
        $st = $pdo->query('SELECT * FROM consultations ORDER BY created_at DESC LIMIT ' . max(1, min(500, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Compteurs globaux pour le dashboard admin.
 */
function maire_compter_consultations(PDO $pdo): array
{
    maire_ensure_consultations_tables($pdo);
    maire_sync_statuts_consultations($pdo);
    $r = ['total' => 0, 'ouvertes' => 0, 'fermees' => 0, 'brouillons' => 0, 'votes' => 0];
    try {
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(statut = 'ouverte') AS o, SUM(statut = 'fermee') AS f, SUM(statut = 'brouillon') AS b, COALESCE(SUM(nb_votes_total), 0) AS v FROM consultations")->fetch();
        if ($row !== false) {
            $r['total'] = (int) $row['total'];
            $r['ouvertes'] = (int) $row['o'];
            $r['fermees'] = (int) $row['f'];
            $r['brouillons'] = (int) $row['b'];
            $r['votes'] = (int) $row['v'];
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return $r;
}

/**
 * Résultats au format Chart.js (donut), couleurs déterministes.
 *
 * @return array{labels: list<string>, data: list<int>, colors: list<string>, total: int}
 */
function maire_resultats_chart(PDO $pdo, int $consultationId): array
{
    $palette = ['#0c4a3e', '#0ea5e9', '#f59e0b', '#7c3aed', '#dc2626', '#16a34a', '#94a3b8', '#ec4899'];
    $options = maire_options_consultation($pdo, $consultationId);
    $labels = [];
    $data = [];
    $colors = [];
    $total = 0;
    foreach ($options as $i => $o) {
        $lib = (string) ($o['libelle'] ?? '');
        if (mb_strlen($lib) > 50) {
            $lib = mb_substr($lib, 0, 47) . '…';
        }
        $labels[] = $lib;
        $data[] = (int) $o['nb_votes'];
        $colors[] = $palette[$i % count($palette)];
        $total += (int) $o['nb_votes'];
    }
    return ['labels' => $labels, 'data' => $data, 'colors' => $colors, 'total' => $total];
}
