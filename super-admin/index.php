<?php
declare(strict_types=1);

/**
 * Tableau de bord de l'espace éditeur (/super-admin/).
 *
 * Réservé aux comptes de la table super_admins. Affiche :
 *   - l'état de l'abonnement communal (palier, actif, jours restants)
 *   - les indicateurs de santé du compte (paiements récents, nb agents)
 *   - les actions rapides (suspendre / réactiver / prolonger / voir détails)
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';
require_once __DIR__ . '/../includes/commune-abonnement.php';
require_once __DIR__ . '/../includes/compte-mairie.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/documents-publics.php';
require_once __DIR__ . '/../includes/stats-temporelles.php';

$communeRow = null;
$communePalierLibelle = '—';
$idCompteMairie = null;
$emailMairieInst = '';
$nbAbonnementsTotal = 0;
$nbAdmins = 0;
$nbAgents = 0;
$abonnementsExpirentBientot = 0;
$paiementsValideMois = 0;
$paiementsTotalAnnee = 0;
$dernierPaiement = null;

try {
    maire_ensure_commune_abonnement_table($pdo);
    $communeRow = maire_load_commune_abonnement_row($pdo);
    if ($communeRow !== null) {
        $communeRow = maire_sync_commune_abonnement_actif($pdo, $communeRow);
        $communePalierLibelle = maire_plan_vers_palier((string) ($communeRow['plan'] ?? ''));
    }
} catch (Throwable $e) {
    $communeRow = null;
}

try {
    $idCompteMairie = maire_get_compte_mairie_id($pdo);
} catch (Throwable $e) {
    $idCompteMairie = null;
}
if ($idCompteMairie !== null) {
    try {
        $st = $pdo->prepare('SELECT email FROM abonnements WHERE id = :id LIMIT 1');
        $st->execute(['id' => $idCompteMairie]);
        $emailMairieInst = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $emailMairieInst = '';
    }
}

try {
    $nbAbonnementsTotal = (int) $pdo->query('SELECT COUNT(*) FROM abonnements')->fetchColumn();
    $nbAdmins = (int) $pdo->query("SELECT COUNT(*) FROM abonnements WHERE role = 'admin'")->fetchColumn();
    $nbAgents = $nbAbonnementsTotal - $nbAdmins;
} catch (Throwable $e) {
    // tolérant
}

try {
    $stExp = $pdo->prepare("SELECT COUNT(*) FROM abonnements WHERE date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stExp->execute();
    $abonnementsExpirentBientot = (int) $stExp->fetchColumn();
} catch (Throwable $e) {
    $abonnementsExpirentBientot = 0;
}

try {
    $stP1 = $pdo->prepare("SELECT COUNT(*) FROM paiements_abonnements WHERE statut = 'valide' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stP1->execute();
    $paiementsValideMois = (int) $stP1->fetchColumn();

    $stP2 = $pdo->prepare("SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiements_abonnements WHERE statut = 'valide' AND YEAR(created_at) = YEAR(CURDATE())");
    $stP2->execute();
    $paiementsTotalAnnee = (int) $stP2->fetchColumn();

    $stP3 = $pdo->prepare("SELECT email, montant_fcfa, mode_paiement, created_at, statut FROM paiements_abonnements ORDER BY created_at DESC LIMIT 1");
    $stP3->execute();
    $dernierPaiement = $stP3->fetch() ?: null;
} catch (Throwable $e) {
    // tolérant
}

try {
    $nbCitoyens = (int) $pdo->query('SELECT COUNT(*) FROM citoyens')->fetchColumn();
} catch (Throwable $e) {
    $nbCitoyens = 0;
}
try {
    $compteursSignalements = maire_compter_signalements_par_statut($pdo);
} catch (Throwable $e) {
    $compteursSignalements = ['nouveau' => 0, 'pris_en_charge' => 0, 'resolu' => 0, 'rejete' => 0];
}
try {
    $compteursDocuments = maire_compter_documents_publics($pdo);
} catch (Throwable $e) {
    $compteursDocuments = ['total' => 0, 'publies' => 0, 'hors_ligne' => 0, 'telechargements' => 0];
}

$chartPaiementsMois = maire_stats_paiements_par_mois($pdo, 12);
$chartCitoyensMoisSA = maire_stats_citoyens_par_mois($pdo, 12);
$chartSignalementsStatut = maire_stats_signalements_par_statut($pdo);
$chartDocsMois = maire_stats_documents_par_mois($pdo, 12);

$pageNeedsCharts = true;

$dateFinCommune = $communeRow !== null ? substr((string) ($communeRow['date_fin'] ?? ''), 0, 10) : '';
$autoRenewCommune = $communeRow !== null && (int) ($communeRow['auto_renew'] ?? 0) === 1;
$actifCommune = $communeRow !== null && (int) ($communeRow['actif'] ?? 0) === 1;
$suspenduPlateforme = $communeRow !== null && (int) ($communeRow['suspendu_par_plateforme'] ?? 0) === 1;
$suspensionMotif = (string) ($communeRow['suspension_motif'] ?? '');
$suspensionDate = (string) ($communeRow['suspension_date'] ?? '');
$joursRestants = null;
if ($dateFinCommune !== '') {
    try {
        $fin = new DateTimeImmutable($dateFinCommune);
        $now = new DateTimeImmutable(date('Y-m-d'));
        $diff = (int) $fin->diff($now)->format('%r%a');
        $joursRestants = -$diff;
    } catch (Throwable $e) {
        $joursRestants = null;
    }
}

$cycleLibelle = $communeRow !== null ? maire_renouvellement_libelle((int) ($communeRow['renouvellement_jours'] ?? 365)) : '—';
$emailEditeur = (string) ($_SESSION['editeur_email'] ?? '');
$nomEditeur = (string) ($_SESSION['editeur_nom'] ?? '');

$pageTitle = 'Espace éditeur · Tableau de bord';
$pageDescription = 'Suivi et gestion des abonnements communaux par l’éditeur de la plateforme.';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero" style="background:linear-gradient(120deg,#1f2a44,#0f172a);color:#fff;">
        <div class="container">
            <span class="detail-kicker" style="color:#cbd5f5;">Console éditeur</span>
            <h1 style="color:#fff;">Tableau de bord — suivi des abonnements</h1>
            <p style="color:#e2e8f0;">
                Connecté en tant que <strong style="color:#fff;"><?php echo htmlspecialchars($nomEditeur !== '' ? $nomEditeur : $emailEditeur, ENT_QUOTES, 'UTF-8'); ?></strong>
                · <span class="std-feed-badge std-feed-badge--success">Super-administrateur éditeur</span>
            </p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container subscription-grid">

            <?php if ($suspenduPlateforme): ?>
                <article class="card" style="grid-column:1/-1;border:2px solid #dc2626;background:#fef2f2;">
                    <h2 style="color:#b91c1c;margin-top:0;">⚠ Abonnement communal SUSPENDU</h2>
                    <p style="margin:0;">
                        Vous avez suspendu cet abonnement<?php if ($suspensionDate !== ''): ?>
                            le <strong><?php echo htmlspecialchars($suspensionDate, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php endif; ?>.
                        <?php if ($suspensionMotif !== ''): ?>
                            Motif : <em><?php echo htmlspecialchars($suspensionMotif, ENT_QUOTES, 'UTF-8'); ?></em>.
                        <?php endif; ?>
                        Les agents de la commune ne peuvent plus se connecter à leur espace tant que le service reste suspendu.
                    </p>
                    <div class="detail-actions" style="margin-top:0.6rem;">
                        <a class="btn btn-primary" href="abonnement.php#suspendre">Réactiver le service</a>
                    </div>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>État de l’abonnement communal</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong style="text-transform:capitalize;"><?php echo htmlspecialchars($communePalierLibelle, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Palier souscrit</span>
                    </article>
                    <article class="stat-chip">
                        <?php
                        $couleurEtat = $suspenduPlateforme ? '#dc2626' : ($actifCommune ? '#16a34a' : '#f59e0b');
                        $libelleEtat = $suspenduPlateforme ? 'Suspendu (éditeur)' : ($actifCommune ? 'Actif' : 'Inactif (dates)');
                        ?>
                        <strong style="color:<?php echo $couleurEtat; ?>;"><?php echo $libelleEtat; ?></strong>
                        <span>État du service</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo htmlspecialchars($dateFinCommune !== '' ? $dateFinCommune : '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Échéance commune</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:<?php echo ($joursRestants !== null && $joursRestants < 7) ? '#dc2626' : ($joursRestants !== null && $joursRestants < 30 ? '#f59e0b' : 'inherit'); ?>;">
                            <?php echo $joursRestants === null ? '—' : ($joursRestants >= 0 ? $joursRestants . ' j.' : 'Expiré'); ?>
                        </strong>
                        <span>Jours restants</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo $autoRenewCommune ? 'Oui' : 'Non'; ?></strong>
                        <span>Renouvellement auto</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo htmlspecialchars($cycleLibelle, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Cycle de facturation</span>
                    </article>
                </div>

                <?php if ($idCompteMairie !== null && $emailMairieInst !== ''): ?>
                    <p class="std-dash-note" style="margin-top:0.8rem;">
                        Compte institutionnel mairie désigné : <strong><?php echo htmlspecialchars($emailMairieInst, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                <?php else: ?>
                    <p class="std-feed-badge std-feed-badge--warning" style="display:block;margin-top:0.6rem;">
                        Aucun compte institutionnel mairie n’est désigné — la commune ne peut pas s’auto-gérer.
                    </p>
                <?php endif; ?>

                <div class="detail-actions" style="margin-top:1rem;">
                    <a class="btn btn-primary" href="abonnement.php">Gérer l’abonnement (suspendre / prolonger…)</a>
                    <a class="btn btn-outline-dark" href="journal.php">Voir l’historique</a>
                </div>
            </article>

            <article class="card">
                <h2>Indicateurs comptes &amp; paiements</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) $nbAbonnementsTotal; ?></strong>
                        <span>Comptes (mairie + agents)</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo (int) $nbAdmins; ?></strong>
                        <span>Administrateurs</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo (int) $nbAgents; ?></strong>
                        <span>Agents</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:<?php echo $abonnementsExpirentBientot > 0 ? '#f59e0b' : 'inherit'; ?>;"><?php echo (int) $abonnementsExpirentBientot; ?></strong>
                        <span>Agents expirent &lt; 7 j.</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo (int) $paiementsValideMois; ?></strong>
                        <span>Paiements valides 30 j.</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo number_format((float) $paiementsTotalAnnee, 0, ',', ' '); ?></strong>
                        <span>F CFA encaissés (année)</span>
                    </article>
                </div>

                <?php if ($dernierPaiement !== null): ?>
                    <p class="std-dash-note" style="margin-top:0.8rem;">
                        Dernier paiement : <strong><?php echo htmlspecialchars((string) ($dernierPaiement['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        · <?php echo number_format((float) ($dernierPaiement['montant_fcfa'] ?? 0), 0, ',', ' '); ?> F CFA
                        · <?php echo htmlspecialchars((string) ($dernierPaiement['mode_paiement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars((string) ($dernierPaiement['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        · statut <em><?php echo htmlspecialchars((string) ($dernierPaiement['statut'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></em>
                    </p>
                <?php else: ?>
                    <p class="std-dash-note" style="margin-top:0.8rem;">Aucun paiement enregistré pour l’instant.</p>
                <?php endif; ?>

                <div class="detail-actions" style="margin-top:1rem;">
                    <a class="btn btn-primary" href="paiements.php">Suivi des paiements</a>
                </div>
            </article>

            <article class="card">
                <h2>Engagement citoyen</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) $nbCitoyens; ?></strong>
                        <span>Comptes habitants</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#f59e0b;"><?php echo (int) ($compteursSignalements['nouveau'] ?? 0); ?></strong>
                        <span>Signalements nouveaux</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#0ea5e9;"><?php echo (int) ($compteursSignalements['pris_en_charge'] ?? 0); ?></strong>
                        <span>En cours</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) ($compteursSignalements['resolu'] ?? 0); ?></strong>
                        <span>Résolus</span>
                    </article>
                </div>
                <p class="std-dash-note" style="margin-top:0.6rem;">
                    Indicateurs d’usage du module signalement par les habitants de la commune cliente.
                </p>
            </article>

            <article class="card">
                <h2>Bibliothèque documentaire</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) ($compteursDocuments['total'] ?? 0); ?></strong>
                        <span>Documents au total</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) ($compteursDocuments['publies'] ?? 0); ?></strong>
                        <span>En ligne</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#64748b;"><?php echo (int) ($compteursDocuments['hors_ligne'] ?? 0); ?></strong>
                        <span>Hors ligne</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#0c4a3e;"><?php echo number_format((int) ($compteursDocuments['telechargements'] ?? 0), 0, ',', ' '); ?></strong>
                        <span>Téléchargements cumulés</span>
                    </article>
                </div>
                <p class="std-dash-note" style="margin-top:0.6rem;">
                    Formulaires, actes, autorisations et guides téléchargeables par les habitants.
                </p>
            </article>

            <article class="card" style="grid-column:1/-1;">
                <h2 style="color:#0c4a3e;">📈 Analyse temps réel — 12 derniers mois</h2>
                <p class="std-dash-note" style="margin:0 0 0.6rem;">
                    Vision éditeur : encaissements, engagement citoyen, qualité de service.
                </p>
                <div class="maire-chart-grid">
                    <div class="maire-chart-card">
                        <h3>Encaissements (FCFA) / mois</h3>
                        <small>Paiements validés des abonnements</small>
                        <div class="maire-chart-canvas-wrap">
                            <canvas
                                data-chart="bar"
                                data-label="FCFA"
                                data-color="#16a34a"
                                data-payload="<?php echo maire_chart_data_attr($chartPaiementsMois); ?>"></canvas>
                        </div>
                    </div>
                    <div class="maire-chart-card">
                        <h3>Inscriptions habitants / mois</h3>
                        <small>Comptes citoyens créés sur la commune</small>
                        <div class="maire-chart-canvas-wrap">
                            <canvas
                                data-chart="line"
                                data-label="Inscriptions"
                                data-color="#0c4a3e"
                                data-payload="<?php echo maire_chart_data_attr($chartCitoyensMoisSA); ?>"></canvas>
                        </div>
                    </div>
                    <div class="maire-chart-card">
                        <h3>Documents publiés / mois</h3>
                        <small>Activité éditoriale de la mairie</small>
                        <div class="maire-chart-canvas-wrap">
                            <canvas
                                data-chart="bar"
                                data-label="Documents"
                                data-color="#0ea5e9"
                                data-payload="<?php echo maire_chart_data_attr($chartDocsMois); ?>"></canvas>
                        </div>
                    </div>
                    <div class="maire-chart-card">
                        <h3>Signalements par statut</h3>
                        <small>Qualité de traitement des remontées</small>
                        <div class="maire-chart-canvas-wrap">
                            <canvas
                                data-chart="doughnut"
                                data-payload="<?php echo maire_chart_data_attr($chartSignalementsStatut); ?>"></canvas>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card">
                <h2>Outils de gestion</h2>
                <p>Actions directes sur l’abonnement de la commune cliente.</p>
                <div class="detail-actions">
                    <a class="btn btn-primary" href="abonnement.php#suspendre">Suspendre / Réactiver</a>
                    <a class="btn btn-outline-dark" href="abonnement.php#prolonger">Prolonger une échéance</a>
                    <a class="btn btn-outline-dark" href="journal.php">Historique éditeur</a>
                    <a class="btn btn-outline-dark" href="compte.php">Mon compte éditeur</a>
                </div>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="../index.php">Vue site public</a>
                    <a class="btn btn-outline-dark" href="logout.php">Se déconnecter</a>
                </div>
                <p class="std-dash-note" style="margin-top:0.8rem;">
                    Cet espace est isolé : il n’ouvre pas l’espace mairie. Pour intervenir
                    côté mairie, utilisez si nécessaire la console secrète (clé URL).
                </p>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
