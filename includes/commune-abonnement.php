<?php
declare(strict_types=1);

/**
 * Abonnement institutionnel de la commune (souscrit par la mairie).
 * Les citoyens n’ont pas d’abonnement individuel : l’accès aux modules dépend du palier communal actif.
 */

require_once __DIR__ . '/commune-abonnement-historique.php';

function maire_ensure_commune_abonnement_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS commune_abonnement (
            id INT PRIMARY KEY DEFAULT 1,
            plan VARCHAR(40) NOT NULL DEFAULT 'municipal_standard',
            actif TINYINT(1) NOT NULL DEFAULT 1,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    maire_ensure_commune_abonnement_auto_renew_columns($pdo);
    maire_ensure_commune_abonnement_suspension_columns($pdo);

    $n = (int) $pdo->query('SELECT COUNT(*) FROM commune_abonnement')->fetchColumn();
    if ($n === 0) {
        $pdo->exec("
            INSERT INTO commune_abonnement (id, plan, actif, date_debut, date_fin, auto_renew, renouvellement_jours)
            VALUES (1, 'municipal_standard', 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 0, 365)
        ");
    }
}

/**
 * Ajoute (si absentes) les colonnes pour le renouvellement automatique de l'abonnement communal.
 */
function maire_ensure_commune_abonnement_auto_renew_columns(PDO $pdo): void
{
    try {
        $pdo->exec('ALTER TABLE commune_abonnement ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
    try {
        $pdo->exec('ALTER TABLE commune_abonnement ADD COLUMN renouvellement_jours INT NOT NULL DEFAULT 365');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
}

/**
 * Colonnes liées à la suspension par l'éditeur de la plateforme (super-admin).
 * Quand suspendu_par_plateforme = 1, le palier effectif retombe à null peu importe les dates,
 * ce qui bloque l'accès portail et signale "service suspendu" sur l'espace mairie.
 */
function maire_ensure_commune_abonnement_suspension_columns(PDO $pdo): void
{
    try {
        $pdo->exec('ALTER TABLE commune_abonnement ADD COLUMN suspendu_par_plateforme TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
    try {
        $pdo->exec('ALTER TABLE commune_abonnement ADD COLUMN suspension_motif VARCHAR(255) DEFAULT NULL');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
    try {
        $pdo->exec('ALTER TABLE commune_abonnement ADD COLUMN suspension_date TIMESTAMP NULL DEFAULT NULL');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
}

/**
 * @return array{id:int,plan:string,actif:int,date_debut:string,date_fin:string,auto_renew:int,renouvellement_jours:int,suspendu_par_plateforme:int,suspension_motif:?string,suspension_date:?string}|null
 */
function maire_load_commune_abonnement_row(PDO $pdo): ?array
{
    maire_ensure_commune_abonnement_table($pdo);
    try {
        $row = $pdo->query('SELECT id, plan, actif, date_debut, date_fin, auto_renew, renouvellement_jours, suspendu_par_plateforme, suspension_motif, suspension_date FROM commune_abonnement WHERE id = 1 LIMIT 1')->fetch();
    } catch (Throwable $e) {
        try {
            $row = $pdo->query('SELECT id, plan, actif, date_debut, date_fin, auto_renew, renouvellement_jours FROM commune_abonnement WHERE id = 1 LIMIT 1')->fetch();
            if ($row !== false) {
                $row['suspendu_par_plateforme'] = 0;
                $row['suspension_motif'] = null;
                $row['suspension_date'] = null;
            }
        } catch (Throwable $e2) {
            $row = $pdo->query('SELECT id, plan, actif, date_debut, date_fin FROM commune_abonnement WHERE id = 1 LIMIT 1')->fetch();
            if ($row !== false) {
                $row['auto_renew'] = 0;
                $row['renouvellement_jours'] = 365;
                $row['suspendu_par_plateforme'] = 0;
                $row['suspension_motif'] = null;
                $row['suspension_date'] = null;
            }
        }
    }

    return $row === false ? null : $row;
}

function maire_sync_commune_abonnement_actif(PDO $pdo, array $row): array
{
    $today = date('Y-m-d');
    $debut = (string) ($row['date_debut'] ?? '');
    $fin = (string) ($row['date_fin'] ?? '');
    $id = (int) ($row['id'] ?? 1);
    $autoRenew = (int) ($row['auto_renew'] ?? 0) === 1;
    $jours = max(1, (int) ($row['renouvellement_jours'] ?? 365));
    $suspenduPlateforme = (int) ($row['suspendu_par_plateforme'] ?? 0) === 1;

    // Suspension par l'éditeur : on force actif=0 et on ne tente aucun renouvellement.
    if ($suspenduPlateforme) {
        if ((int) ($row['actif'] ?? 0) !== 0) {
            $pdo->prepare('UPDATE commune_abonnement SET actif = 0 WHERE id = :id')->execute(['id' => $id]);
            $row['actif'] = 0;
        }
        return $row;
    }

    if ($fin !== '' && $fin < $today && $autoRenew) {
        try {
            $finDt = new DateTimeImmutable($fin);
            $todayDt = new DateTimeImmutable($today);
            $nouveauDebut = $finDt->modify('+1 day');
            $nouveauFin = $nouveauDebut->modify('+' . ($jours - 1) . ' days');
            while ($nouveauFin < $todayDt) {
                $nouveauDebut = $nouveauFin->modify('+1 day');
                $nouveauFin = $nouveauDebut->modify('+' . ($jours - 1) . ' days');
            }
            $d1 = $nouveauDebut->format('Y-m-d');
            $d2 = $nouveauFin->format('Y-m-d');
            $pdo->prepare('
                UPDATE commune_abonnement
                SET date_debut = :d1, date_fin = :d2, actif = 1
                WHERE id = :id
            ')->execute(['d1' => $d1, 'd2' => $d2, 'id' => $id]);
            $row['date_debut'] = $d1;
            $row['date_fin'] = $d2;
            $row['actif'] = 1;
            $debut = $d1;
            $fin = $d2;

            if (function_exists('maire_log_commune_abonnement')) {
                $detail = sprintf('cycle=%d j., %s → %s', $jours, $d1, $d2);
                maire_log_commune_abonnement($pdo, $row, 'auto_renew', $detail, null, 'systeme');
            }
        } catch (Throwable $e) {
            // si DateTime échoue ou UPDATE refusé, on retombe sur le comportement standard
        }
    }

    if ($fin !== '' && $fin < $today) {
        $pdo->prepare('UPDATE commune_abonnement SET actif = 0 WHERE id = :id')->execute(['id' => $id]);
        $row['actif'] = 0;
    } elseif ($debut !== '' && $fin !== '' && $debut <= $today && $fin >= $today) {
        $pdo->prepare('UPDATE commune_abonnement SET actif = 1 WHERE id = :id')->execute(['id' => $id]);
        $row['actif'] = 1;
    }

    return $row;
}

/**
 * Libellé court pour une durée de cycle de renouvellement.
 */
function maire_renouvellement_libelle(int $jours): string
{
    return match (true) {
        $jours <= 31 => 'mensuel (30 j.)',
        $jours <= 92 => 'trimestriel (90 j.)',
        $jours <= 183 => 'semestriel (180 j.)',
        $jours <= 366 => 'annuel (365 j.)',
        default => $jours . ' j.',
    };
}

/**
 * Convertit le code plan en base vers un palier fonctionnel : simple | standard | premium
 */
function maire_plan_vers_palier(string $plan): string
{
    $p = strtolower($plan);

    return match (true) {
        str_contains($p, 'premium') => 'premium',
        str_contains($p, 'municipal_standard') => 'standard',
        str_contains($p, 'standard_plus') => 'standard',
        str_contains($p, 'municipal_simple') => 'simple',
        str_contains($p, 'simple') => 'simple',
        str_contains($p, 'standard') => 'standard',
        default => 'simple',
    };
}

/** Ordre des paliers pour comparaisons */
function maire_palier_rang(string $palier): int
{
    return match ($palier) {
        'premium' => 2,
        'standard' => 1,
        default => 0,
    };
}

/**
 * Palier effectif de la commune aujourd’hui, ou null si aucun accès payant actif.
 */
function maire_commune_palier_effectif(PDO $pdo): ?string
{
    if ($pdo === null) {
        return null;
    }
    $row = maire_load_commune_abonnement_row($pdo);
    if ($row === null) {
        return null;
    }
    $row = maire_sync_commune_abonnement_actif($pdo, $row);
    if ((int) ($row['actif'] ?? 0) !== 1) {
        return null;
    }
    if (!maire_abonnement_couvre_aujourdhui($row)) {
        return null;
    }

    return maire_plan_vers_palier((string) ($row['plan'] ?? 'municipal_simple'));
}

function maire_palier_couvre(string $palierCommune, string $minimumRequis): bool
{
    return maire_palier_rang($palierCommune) >= maire_palier_rang($minimumRequis);
}
