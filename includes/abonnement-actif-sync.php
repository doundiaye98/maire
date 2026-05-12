<?php
declare(strict_types=1);

/**
 * Ajoute (si absentes) les colonnes de renouvellement automatique sur abonnements.
 */
function maire_ensure_abonnements_auto_renew_columns(PDO $pdo): void
{
    try {
        $pdo->exec('ALTER TABLE abonnements ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
    try {
        $pdo->exec('ALTER TABLE abonnements ADD COLUMN renouvellement_jours INT NOT NULL DEFAULT 30');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
}

/**
 * Aligne la colonne actif avec la période : actif = 1 si aujourd’hui est entre date_debut et date_fin,
 * actif = 0 si l’abonnement est expiré (date_fin < aujourd’hui).
 *
 * Si auto_renew = 1 et que l’abonnement est expiré, on prolonge automatiquement de
 * renouvellement_jours (en répétant jusqu’à couvrir aujourd’hui) puis on réactive.
 * Le compte institutionnel mairie (compte_mairie=1) est piloté par l’abonnement communal :
 * son auto-renouvellement passe par maire_sync_commune_abonnement_actif et le miroir.
 */
function maire_sync_abonnement_actif(PDO $pdo, int $abonnementId): void
{
    if ($abonnementId <= 0) {
        return;
    }
    maire_ensure_abonnements_auto_renew_columns($pdo);

    try {
        $stmt = $pdo->prepare('SELECT date_debut, date_fin, auto_renew, renouvellement_jours, compte_mairie FROM abonnements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $abonnementId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        $stmt = $pdo->prepare('SELECT date_debut, date_fin FROM abonnements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $abonnementId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $row['auto_renew'] = 0;
            $row['renouvellement_jours'] = 30;
            $row['compte_mairie'] = 0;
        }
    }
    if ($row === false) {
        return;
    }
    $today = date('Y-m-d');
    $debut = (string) ($row['date_debut'] ?? '');
    $fin = (string) ($row['date_fin'] ?? '');
    $autoRenew = (int) ($row['auto_renew'] ?? 0) === 1;
    $jours = max(1, (int) ($row['renouvellement_jours'] ?? 30));
    $estCompteMairie = (int) ($row['compte_mairie'] ?? 0) === 1;

    if ($fin !== '' && $fin < $today && $autoRenew && !$estCompteMairie) {
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
                UPDATE abonnements
                SET date_debut = :d1, date_fin = :d2, actif = 1
                WHERE id = :id
            ')->execute(['d1' => $d1, 'd2' => $d2, 'id' => $abonnementId]);
            $fin = $d2;
            $debut = $d1;
        } catch (Throwable $e) {
            // Si la mise à jour échoue, on retombera sur la désactivation classique ci-dessous.
        }
    }

    if ($fin !== '' && $fin < $today) {
        $pdo->prepare('UPDATE abonnements SET actif = 0 WHERE id = :id')->execute(['id' => $abonnementId]);

        return;
    }

    if ($debut !== '' && $fin !== '' && $debut <= $today && $fin >= $today) {
        $pdo->prepare('UPDATE abonnements SET actif = 1 WHERE id = :id')->execute(['id' => $abonnementId]);
    }
}

/**
 * Indique si les dates d’effet couvrent aujourd’hui (compte agent ou ligne communale).
 */
function maire_abonnement_couvre_aujourdhui(array $row): bool
{
    $today = date('Y-m-d');
    $debut = (string) ($row['date_debut'] ?? '');
    $fin = (string) ($row['date_fin'] ?? '');

    return $debut !== '' && $fin !== '' && $debut <= $today && $fin >= $today;
}
