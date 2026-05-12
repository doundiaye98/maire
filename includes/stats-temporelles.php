<?php
declare(strict_types=1);

/**
 * Helpers d'agrégation pour les graphiques des tableaux de bord.
 * - Séries temporelles mensuelles (les n derniers mois, mois sans donnée = 0)
 * - Répartitions par dimension (catégorie, statut, palier)
 *
 * Toutes les fonctions retournent ['labels' => [...], 'data' => [...]] prêt à
 * sérialiser via `json_encode()` pour Chart.js.
 */

/**
 * Construit la liste des n derniers mois au format 'Y-m' (ordre chronologique).
 *
 * @return array<string, int>  mapping 'Y-m' => 0  (utilisé comme squelette)
 */
function maire_squelette_mois(int $n): array
{
    $n = max(1, min(36, $n));
    $out = [];
    $cur = new DateTimeImmutable('first day of this month');
    for ($i = $n - 1; $i >= 0; $i--) {
        $d = $cur->modify("-{$i} months");
        $out[$d->format('Y-m')] = 0;
    }
    return $out;
}

/**
 * Libellé court d'un mois 'Y-m' → 'Jan 2026'.
 */
function maire_libelle_mois_court(string $ym): string
{
    $mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    [$a, $m] = array_pad(explode('-', $ym, 2), 2, '01');
    $idx = max(1, min(12, (int) $m)) - 1;
    return $mois[$idx] . ' ' . $a;
}

/**
 * Série mensuelle : nombre de signalements créés / mois.
 *
 * @return array{labels: list<string>, data: list<int>}
 */
function maire_stats_signalements_par_mois(PDO $pdo, int $nMois = 6): array
{
    $squelette = maire_squelette_mois($nMois);
    try {
        $debut = array_key_first($squelette) . '-01';
        $st = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
            FROM signalements
            WHERE created_at >= :d
            GROUP BY m
        ");
        $st->execute(['d' => $debut]);
        foreach ($st->fetchAll() as $r) {
            $k = (string) ($r['m'] ?? '');
            if (isset($squelette[$k])) {
                $squelette[$k] = (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
        // table absente — on garde les zéros
    }
    return [
        'labels' => array_map('maire_libelle_mois_court', array_keys($squelette)),
        'data' => array_values($squelette),
    ];
}

/**
 * Série mensuelle : nombre de citoyens inscrits / mois.
 */
function maire_stats_citoyens_par_mois(PDO $pdo, int $nMois = 6): array
{
    $squelette = maire_squelette_mois($nMois);
    try {
        $debut = array_key_first($squelette) . '-01';
        $st = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
            FROM citoyens
            WHERE created_at >= :d
            GROUP BY m
        ");
        $st->execute(['d' => $debut]);
        foreach ($st->fetchAll() as $r) {
            $k = (string) ($r['m'] ?? '');
            if (isset($squelette[$k])) {
                $squelette[$k] = (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return [
        'labels' => array_map('maire_libelle_mois_court', array_keys($squelette)),
        'data' => array_values($squelette),
    ];
}

/**
 * Série mensuelle : encaissements en FCFA / mois (paiements valides uniquement).
 */
function maire_stats_paiements_par_mois(PDO $pdo, int $nMois = 12): array
{
    $squelette = maire_squelette_mois($nMois);
    try {
        $debut = array_key_first($squelette) . '-01';
        $st = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COALESCE(SUM(montant_fcfa), 0) AS total
            FROM paiements_abonnements
            WHERE statut = 'valide' AND created_at >= :d
            GROUP BY m
        ");
        $st->execute(['d' => $debut]);
        foreach ($st->fetchAll() as $r) {
            $k = (string) ($r['m'] ?? '');
            if (isset($squelette[$k])) {
                $squelette[$k] = (int) $r['total'];
            }
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return [
        'labels' => array_map('maire_libelle_mois_court', array_keys($squelette)),
        'data' => array_values($squelette),
    ];
}

/**
 * Série mensuelle : nombre de documents publiés / mois.
 */
function maire_stats_documents_par_mois(PDO $pdo, int $nMois = 6): array
{
    $squelette = maire_squelette_mois($nMois);
    try {
        $debut = array_key_first($squelette) . '-01';
        $st = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
            FROM documents_publics
            WHERE created_at >= :d
            GROUP BY m
        ");
        $st->execute(['d' => $debut]);
        foreach ($st->fetchAll() as $r) {
            $k = (string) ($r['m'] ?? '');
            if (isset($squelette[$k])) {
                $squelette[$k] = (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return [
        'labels' => array_map('maire_libelle_mois_court', array_keys($squelette)),
        'data' => array_values($squelette),
    ];
}

/**
 * Répartition des signalements par statut (donut).
 */
function maire_stats_signalements_par_statut(PDO $pdo): array
{
    $libelles = [
        'nouveau' => 'Nouveaux',
        'pris_en_charge' => 'En cours',
        'resolu' => 'Résolus',
        'rejete' => 'Rejetés',
    ];
    $couleurs = [
        'nouveau' => '#f59e0b',
        'pris_en_charge' => '#0ea5e9',
        'resolu' => '#16a34a',
        'rejete' => '#94a3b8',
    ];
    $compteurs = array_fill_keys(array_keys($libelles), 0);
    try {
        $st = $pdo->query('SELECT statut, COUNT(*) AS c FROM signalements GROUP BY statut');
        foreach ($st->fetchAll() as $r) {
            $k = (string) ($r['statut'] ?? '');
            if (isset($compteurs[$k])) {
                $compteurs[$k] = (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
        // tolérant
    }

    return [
        'labels' => array_values($libelles),
        'data' => array_values($compteurs),
        'colors' => array_values($couleurs),
    ];
}

/**
 * Répartition des signalements par catégorie (donut).
 */
function maire_stats_signalements_par_categorie(PDO $pdo): array
{
    $palette = ['#0c4a3e', '#f59e0b', '#0ea5e9', '#7c3aed', '#dc2626', '#16a34a', '#94a3b8'];
    $libelles = [];
    $data = [];
    try {
        $st = $pdo->query('SELECT categorie, COUNT(*) AS c FROM signalements GROUP BY categorie ORDER BY c DESC');
        foreach ($st->fetchAll() as $r) {
            $cat = (string) ($r['categorie'] ?? 'autre');
            $libelles[] = function_exists('maire_libelle_categorie_signalement')
                ? maire_libelle_categorie_signalement($cat)
                : ucfirst($cat);
            $data[] = (int) $r['c'];
        }
    } catch (Throwable $e) {
        // tolérant
    }
    $colors = [];
    for ($i = 0; $i < count($libelles); $i++) {
        $colors[] = $palette[$i % count($palette)];
    }
    return ['labels' => $libelles, 'data' => $data, 'colors' => $colors];
}

/**
 * Top N documents les plus téléchargés (bar chart horizontal).
 */
function maire_stats_top_documents(PDO $pdo, int $limit = 5): array
{
    $labels = [];
    $data = [];
    try {
        $st = $pdo->prepare("
            SELECT titre, nb_telechargements
            FROM documents_publics
            WHERE publie = 1
            ORDER BY nb_telechargements DESC, created_at DESC
            LIMIT " . max(1, min(20, $limit))
        );
        $st->execute();
        foreach ($st->fetchAll() as $r) {
            $titre = (string) ($r['titre'] ?? '');
            if (mb_strlen($titre) > 36) {
                $titre = mb_substr($titre, 0, 33) . '…';
            }
            $labels[] = $titre;
            $data[] = (int) $r['nb_telechargements'];
        }
    } catch (Throwable $e) {
        // tolérant
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Sérialise une structure pour Chart.js, échappant en HTML attribut.
 */
function maire_chart_data_attr(array $data): string
{
    return htmlspecialchars(
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ENT_QUOTES,
        'UTF-8'
    );
}
