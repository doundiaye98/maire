<?php
declare(strict_types=1);

/**
 * Journal des changements sur l’abonnement communal et événements liés au compte institutionnel.
 */

function maire_ensure_commune_abonnement_historique_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS commune_abonnement_historique (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan VARCHAR(40) NOT NULL,
            actif TINYINT(1) NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            evenement VARCHAR(40) NOT NULL DEFAULT 'plan_change',
            detail VARCHAR(500) DEFAULT NULL,
            actor_subscriber_id INT NULL,
            actor_source VARCHAR(32) NOT NULL DEFAULT 'inconnu',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_hist_created (created_at)
        )
    ");
}

/**
 * @param 'plan_change'|'mairie_promotion'|'mairie_transfer'|'auto_renew'|'suspension'|'reactivation'|'prolongation_manuelle' $evenement
 * @param 'super_console'|'compte_mairie'|'admin_provisoire'|'systeme'|'editeur' $actorSource
 */
function maire_log_commune_abonnement(
    PDO $pdo,
    ?array $communeRow,
    string $evenement,
    ?string $detail,
    ?int $actorSubscriberId,
    string $actorSource
): void {
    if ($communeRow === null) {
        return;
    }
    maire_ensure_commune_abonnement_historique_table($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO commune_abonnement_historique
            (plan, actif, date_debut, date_fin, evenement, detail, actor_subscriber_id, actor_source)
        VALUES (:plan, :actif, :d1, :d2, :evt, :detail, :actor, :src)
    ');
    $stmt->execute([
        'plan' => (string) ($communeRow['plan'] ?? ''),
        'actif' => (int) ($communeRow['actif'] ?? 0),
        'd1' => (string) ($communeRow['date_debut'] ?? ''),
        'd2' => (string) ($communeRow['date_fin'] ?? ''),
        'evt' => $evenement,
        'detail' => $detail,
        'actor' => $actorSubscriberId,
        'src' => $actorSource,
    ]);
}

function maire_libelle_evenement_commune(string $code): string
{
    return match ($code) {
        'mairie_promotion' => 'Désignation du compte institutionnel',
        'mairie_transfer' => 'Transfert du compte institutionnel (console secrète)',
        'auto_renew' => 'Renouvellement automatique',
        'suspension' => 'Suspension par l’éditeur',
        'reactivation' => 'Réactivation par l’éditeur',
        'prolongation_manuelle' => 'Prolongation manuelle (éditeur)',
        default => 'Modification de l’abonnement communal',
    };
}

function maire_libelle_actor_source_commune(string $code): string
{
    return match ($code) {
        'super_console' => 'Console secrète',
        'compte_mairie' => 'Compte institutionnel mairie',
        'admin_provisoire' => 'Administrateur (config. initiale)',
        'systeme' => 'Système (renouvellement auto)',
        'editeur' => 'Éditeur (super-admin)',
        default => $code,
    };
}
