<?php
declare(strict_types=1);

/**
 * Pages d'actions de l'éditeur sur l'abonnement communal :
 *   - Suspendre / réactiver le service
 *   - Forcer une expiration immédiate
 *   - Prolonger la date_fin de N jours
 *   - Modifier le plan (palier)
 *   - Activer/désactiver le renouvellement automatique
 *
 * Toutes les actions sont tracées dans commune_abonnement_historique avec source 'editeur'.
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';
require_once __DIR__ . '/../includes/commune-abonnement.php';
require_once __DIR__ . '/../includes/compte-mairie.php';

$superAdminCsrfScope = MAIRE_CSRF_SCOPE_SUPER_ADMIN;

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!maire_csrf_validate($superAdminCsrfScope)) {
        $flash = maire_csrf_error_message();
        $flashType = 'danger';
    } else {
        try {
            maire_ensure_commune_abonnement_table($pdo);
            maire_ensure_commune_abonnement_suspension_columns($pdo);
            $row = maire_load_commune_abonnement_row($pdo);
            if ($row === null) {
                throw new RuntimeException('Abonnement communal introuvable.');
            }

            switch ($action) {
                case 'suspendre':
                    $motif = trim((string) ($_POST['motif'] ?? ''));
                    // Drapeau permanent : survit aux synchronisations de dates.
                    $pdo->prepare('
                        UPDATE commune_abonnement
                        SET suspendu_par_plateforme = 1,
                            suspension_motif = :m,
                            suspension_date = NOW(),
                            actif = 0
                        WHERE id = :id
                    ')->execute([
                        'm' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
                        'id' => (int) $row['id'],
                    ]);
                    $row['actif'] = 0;
                    $row['suspendu_par_plateforme'] = 1;
                    $row['suspension_motif'] = $motif;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'suspension',
                        $motif !== '' ? ('Motif : ' . $motif) : 'Suspension demandée par l’éditeur',
                        null,
                        'editeur'
                    );
                    $flash = 'Abonnement communal suspendu. Les comptes mairie/agents perdent l’accès jusqu’à réactivation.';
                    break;

                case 'reactiver':
                    // On lève le drapeau de suspension ; la sync recalculera actif depuis les dates.
                    $pdo->prepare('
                        UPDATE commune_abonnement
                        SET suspendu_par_plateforme = 0,
                            suspension_motif = NULL,
                            suspension_date = NULL,
                            actif = 1
                        WHERE id = :id
                    ')->execute(['id' => (int) $row['id']]);
                    $row['actif'] = 1;
                    $row['suspendu_par_plateforme'] = 0;
                    $row['suspension_motif'] = null;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'reactivation',
                        'Réactivation manuelle par l’éditeur',
                        null,
                        'editeur'
                    );
                    $flash = 'Abonnement communal réactivé. La synchronisation recalculera l’état actif selon les dates.';
                    break;

                case 'forcer_expiration':
                    $hier = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
                    $pdo->prepare('UPDATE commune_abonnement SET date_fin = :d, actif = 0 WHERE id = :id')
                        ->execute(['d' => $hier, 'id' => (int) $row['id']]);
                    $row['date_fin'] = $hier;
                    $row['actif'] = 0;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'suspension',
                        'Expiration forcée au ' . $hier,
                        null,
                        'editeur'
                    );
                    $flash = 'Expiration forcée à la date d’hier.';
                    break;

                case 'prolonger':
                    $joursStr = (string) ($_POST['jours'] ?? '0');
                    $jours = (int) $joursStr;
                    if ($jours < 1 || $jours > 1825) {
                        throw new RuntimeException('Nombre de jours invalide (1 à 1825).');
                    }
                    $finActuelle = (string) ($row['date_fin'] ?? '');
                    $aujourdHui = date('Y-m-d');
                    $base = ($finActuelle !== '' && $finActuelle > $aujourdHui) ? $finActuelle : $aujourdHui;
                    $nouvelleFin = (new DateTimeImmutable($base))->modify('+' . $jours . ' days')->format('Y-m-d');
                    $pdo->prepare('UPDATE commune_abonnement SET date_fin = :d, actif = 1 WHERE id = :id')
                        ->execute(['d' => $nouvelleFin, 'id' => (int) $row['id']]);
                    $row['date_fin'] = $nouvelleFin;
                    $row['actif'] = 1;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'prolongation_manuelle',
                        sprintf('+%d j., nouvelle échéance %s', $jours, $nouvelleFin),
                        null,
                        'editeur'
                    );
                    $flash = 'Échéance prolongée jusqu’au ' . $nouvelleFin . '.';
                    break;

                case 'changer_plan':
                    $nouveauPlan = (string) ($_POST['plan'] ?? '');
                    $plansValides = ['municipal_simple', 'municipal_standard', 'municipal_premium'];
                    if (!in_array($nouveauPlan, $plansValides, true)) {
                        throw new RuntimeException('Plan invalide.');
                    }
                    $pdo->prepare('UPDATE commune_abonnement SET plan = :p WHERE id = :id')
                        ->execute(['p' => $nouveauPlan, 'id' => (int) $row['id']]);
                    $row['plan'] = $nouveauPlan;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'plan_change',
                        'Plan modifié par l’éditeur → ' . $nouveauPlan,
                        null,
                        'editeur'
                    );
                    $flash = 'Plan communal modifié : ' . $nouveauPlan;
                    break;

                case 'toggle_auto_renew':
                    $autoRenew = (int) ($_POST['auto_renew'] ?? 0) === 1 ? 1 : 0;
                    $joursCycle = max(1, min(1825, (int) ($_POST['renouvellement_jours'] ?? 365)));
                    $pdo->prepare('UPDATE commune_abonnement SET auto_renew = :a, renouvellement_jours = :j WHERE id = :id')
                        ->execute(['a' => $autoRenew, 'j' => $joursCycle, 'id' => (int) $row['id']]);
                    $row['auto_renew'] = $autoRenew;
                    $row['renouvellement_jours'] = $joursCycle;
                    maire_log_commune_abonnement(
                        $pdo,
                        $row,
                        'plan_change',
                        sprintf('Renouvellement auto = %s, cycle = %d j.', $autoRenew === 1 ? 'oui' : 'non', $joursCycle),
                        null,
                        'editeur'
                    );
                    $flash = 'Renouvellement automatique mis à jour.';
                    break;

                default:
                    throw new RuntimeException('Action inconnue.');
            }
        } catch (Throwable $e) {
            $flash = 'Erreur : ' . $e->getMessage();
            $flashType = 'danger';
        }
    }
}

$communeRow = maire_load_commune_abonnement_row($pdo);
$plan = (string) ($communeRow['plan'] ?? 'municipal_standard');
$actif = (int) ($communeRow['actif'] ?? 0);
$dateDebut = (string) ($communeRow['date_debut'] ?? '');
$dateFin = (string) ($communeRow['date_fin'] ?? '');
$autoRenew = (int) ($communeRow['auto_renew'] ?? 0) === 1;
$cycleJours = (int) ($communeRow['renouvellement_jours'] ?? 365);
$suspenduPlateforme = (int) ($communeRow['suspendu_par_plateforme'] ?? 0) === 1;
$suspensionMotif = (string) ($communeRow['suspension_motif'] ?? '');
$suspensionDate = (string) ($communeRow['suspension_date'] ?? '');

$pageTitle = 'Espace éditeur · Abonnement communal';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero" style="background:linear-gradient(120deg,#1f2a44,#0f172a);color:#fff;">
        <div class="container">
            <span class="detail-kicker" style="color:#cbd5f5;">Console éditeur</span>
            <h1 style="color:#fff;">Gérer l’abonnement communal</h1>
            <p style="color:#e2e8f0;">Suspendre, réactiver, prolonger ou ajuster les paramètres du contrat.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container subscription-grid">

            <?php if ($flash !== ''): ?>
                <article class="card" style="grid-column:1/-1;">
                    <p class="std-feed-badge std-feed-badge--<?php echo htmlspecialchars($flashType === 'danger' ? 'danger' : 'success', ENT_QUOTES, 'UTF-8'); ?>" style="display:block;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>État actuel</h2>
                <ul style="list-style:none;padding:0;margin:0;">
                    <li><strong>Plan :</strong> <?php echo htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'); ?> (palier <?php echo htmlspecialchars(maire_plan_vers_palier($plan), ENT_QUOTES, 'UTF-8'); ?>)</li>
                    <li><strong>Service :</strong>
                        <?php if ($suspenduPlateforme): ?>
                            <span class="std-feed-badge std-feed-badge--danger">SUSPENDU par l’éditeur</span>
                        <?php elseif ($actif === 1): ?>
                            <span class="std-feed-badge std-feed-badge--success">Actif</span>
                        <?php else: ?>
                            <span class="std-feed-badge std-feed-badge--warning">Inactif (dates écoulées)</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>Date début :</strong> <?php echo htmlspecialchars($dateDebut, ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Date fin :</strong> <?php echo htmlspecialchars($dateFin, ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Renouvellement auto :</strong> <?php echo $autoRenew ? 'Oui' : 'Non'; ?> · cycle <?php echo htmlspecialchars(maire_renouvellement_libelle($cycleJours), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php if ($suspenduPlateforme): ?>
                        <li style="margin-top:0.4rem;color:#dc2626;">
                            <strong>Motif :</strong> <?php echo htmlspecialchars($suspensionMotif !== '' ? $suspensionMotif : '— (non précisé)', ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($suspensionDate !== ''): ?>
                                <br><small>Depuis le <?php echo htmlspecialchars($suspensionDate, ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </article>

            <article class="card" id="suspendre">
                <h2>Suspendre le service</h2>
                <p>Bloque immédiatement l’accès aux modules payants pour la commune (compte mairie + agents). Action réversible (réactivation).</p>
                <form method="POST" action="abonnement.php#suspendre">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="suspendre">
                    <label for="motif" style="display:block;font-weight:600;margin-top:0.4rem;">Motif (facultatif)</label>
                    <input type="text" id="motif" name="motif" maxlength="200" placeholder="Ex : impayé janvier 2026" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                    <div class="detail-actions" style="margin-top:0.8rem;">
                        <button type="submit" class="btn btn-primary" <?php echo $suspenduPlateforme ? 'disabled' : ''; ?> onclick="return confirm('Confirmer la suspension de l’abonnement communal ?');">Suspendre maintenant</button>
                    </div>
                </form>

                <hr style="margin:1rem 0;border:none;border-top:1px solid #e2e8f0;">
                <h3 style="margin-top:0;">Réactiver</h3>
                <p style="color:#64748b;font-size:0.9rem;">Lève le drapeau de suspension. L’abonnement redeviendra actif si la date de fin n’est pas dépassée.</p>
                <form method="POST" action="abonnement.php">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="reactiver">
                    <div class="detail-actions">
                        <button type="submit" class="btn btn-outline-dark" <?php echo (!$suspenduPlateforme && $actif === 1) ? 'disabled' : ''; ?>>Réactiver le service</button>
                    </div>
                </form>

                <hr style="margin:1rem 0;border:none;border-top:1px solid #e2e8f0;">
                <h3 style="margin-top:0;">Forcer une expiration immédiate</h3>
                <p style="color:#64748b;font-size:0.9rem;">Met la date_fin à hier et désactive l’abonnement. Utile pour matérialiser un non-renouvellement.</p>
                <form method="POST" action="abonnement.php">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="forcer_expiration">
                    <div class="detail-actions">
                        <button type="submit" class="btn btn-outline-dark" onclick="return confirm('Forcer l’expiration de l’abonnement communal ?');">Forcer l’expiration</button>
                    </div>
                </form>
            </article>

            <article class="card" id="prolonger">
                <h2>Prolonger l’échéance</h2>
                <p>Ajouter des jours à la date de fin. Pratique pour offrir un délai de paiement ou un mois supplémentaire.</p>
                <form method="POST" action="abonnement.php#prolonger">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="prolonger">
                    <label for="jours" style="display:block;font-weight:600;margin-top:0.4rem;">Jours à ajouter</label>
                    <input type="number" id="jours" name="jours" min="1" max="1825" value="30" required style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                    <div class="detail-actions" style="margin-top:0.8rem;">
                        <button type="submit" class="btn btn-primary">Prolonger</button>
                    </div>
                </form>
            </article>

            <article class="card" id="plan">
                <h2>Changer le palier souscrit</h2>
                <form method="POST" action="abonnement.php#plan">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="changer_plan">
                    <label for="plan" style="display:block;font-weight:600;margin-top:0.4rem;">Nouveau plan</label>
                    <select id="plan" name="plan" required style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                        <option value="municipal_simple" <?php echo $plan === 'municipal_simple' ? 'selected' : ''; ?>>Simple (gratuit)</option>
                        <option value="municipal_standard" <?php echo $plan === 'municipal_standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="municipal_premium" <?php echo $plan === 'municipal_premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                    <div class="detail-actions" style="margin-top:0.8rem;">
                        <button type="submit" class="btn btn-primary">Appliquer</button>
                    </div>
                </form>
            </article>

            <article class="card" id="auto">
                <h2>Renouvellement automatique</h2>
                <form method="POST" action="abonnement.php#auto">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <input type="hidden" name="action" value="toggle_auto_renew">
                    <label style="display:block;margin-top:0.4rem;">
                        <input type="checkbox" name="auto_renew" value="1" <?php echo $autoRenew ? 'checked' : ''; ?>>
                        Activer le renouvellement automatique
                    </label>
                    <label for="renouvellement_jours" style="display:block;font-weight:600;margin-top:0.4rem;">Cycle (jours)</label>
                    <input type="number" id="renouvellement_jours" name="renouvellement_jours" min="1" max="1825" value="<?php echo (int) $cycleJours; ?>" required style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                    <div class="detail-actions" style="margin-top:0.8rem;">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord</a>
                    <a class="btn btn-outline-dark" href="journal.php">Voir l’historique</a>
                    <a class="btn btn-outline-dark" href="paiements.php">Paiements</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
