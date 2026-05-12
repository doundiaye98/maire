<?php
declare(strict_types=1);

/**
 * Compte institutionnel unique : seul ce compte (ou la console super-admin)
 * modifie l’abonnement communal ; les changements sur commune_abonnement sont
 * recopiés sur ce compte pour cohérence et effet immédiat sur tout le site.
 */

function maire_ensure_abonnements_compte_mairie_column(PDO $pdo): void
{
    try {
        $pdo->exec('ALTER TABLE abonnements ADD COLUMN compte_mairie TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        // colonne déjà présente
    }
    try {
        $pdo->exec("UPDATE abonnements SET role_utilisateur = 'admin' WHERE email = 'abonne@demo.rufisque.sn' AND role_utilisateur <> 'admin' LIMIT 1");
    } catch (Throwable $e) {
        // ignorer si la table n’existe pas encore
    }
}

function maire_get_compte_mairie_id(PDO $pdo): ?int
{
    maire_ensure_abonnements_compte_mairie_column($pdo);
    try {
        $stmt = $pdo->query('SELECT id FROM abonnements WHERE compte_mairie = 1 ORDER BY id ASC LIMIT 1');
        $id = $stmt->fetchColumn();
        if ($id === false || $id === null) {
            return null;
        }

        return (int) $id;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Qui peut enregistrer commune_abonnement : super-console, ou le compte mairie,
 * ou tout admin tant qu’aucun compte institutionnel n’est désigné (première config).
 */
function maire_peut_gerer_abonnement_communal(PDO $pdo, int $subscriberId, bool $estSuperConsole, string $subscriberRole): bool
{
    if ($estSuperConsole) {
        return true;
    }
    if ($subscriberRole !== 'admin') {
        return false;
    }
    $idMairie = maire_get_compte_mairie_id($pdo);
    if ($idMairie === null) {
        return true;
    }

    return $subscriberId === $idMairie;
}

/**
 * Recopie commune_abonnement → ligne compte_mairie (plan, dates, actif).
 */
/**
 * Enregistrement d’un paiement lié à l’abonnement communal : compte institutionnel ou console secrète.
 */
function maire_peut_enregistrer_paiement_abonnement_communal(PDO $pdo, int $subscriberId, bool $estSuperConsole): bool
{
    if ($estSuperConsole) {
        return true;
    }
    $mid = maire_get_compte_mairie_id($pdo);

    return $mid !== null && $subscriberId > 0 && $subscriberId === $mid;
}

function maire_sync_commune_vers_compte_mairie(PDO $pdo): void
{
    require_once __DIR__ . '/commune-abonnement.php';
    $idMairie = maire_get_compte_mairie_id($pdo);
    if ($idMairie === null) {
        return;
    }
    $row = maire_load_commune_abonnement_row($pdo);
    if ($row === null) {
        return;
    }
    $row = maire_sync_commune_abonnement_actif($pdo, $row);
    $pdo->prepare('
        UPDATE abonnements
        SET plan = :plan, date_debut = :d1, date_fin = :d2, actif = :actif
        WHERE id = :id AND compte_mairie = 1
    ')->execute([
        'plan' => (string) ($row['plan'] ?? 'municipal_standard'),
        'd1' => (string) ($row['date_debut'] ?? ''),
        'd2' => (string) ($row['date_fin'] ?? ''),
        'actif' => (int) ($row['actif'] ?? 0),
        'id' => $idMairie,
    ]);
}
