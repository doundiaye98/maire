<?php
declare(strict_types=1);

/**
 * Tableau de bord de l'espace mairie (/admin/).
 * Verrouillé par admin-guard : seul un compte admin (mairie ou autre) ou la console secrète y accède.
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/super-admin-session.php';
require_once __DIR__ . '/../includes/commune-abonnement.php';
require_once __DIR__ . '/../includes/compte-mairie.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/documents-publics.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/stats-temporelles.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/consultations.php';
require_once __DIR__ . '/../includes/paiements.php';
require_once __DIR__ . '/../includes/conseil-sessions.php';
require_once __DIR__ . '/../includes/chatbot.php';
require_once __DIR__ . '/../includes/api-keys.php';

$estConsoleSecrete = maire_super_admin_session_valid();
$emailUtilisateur = (string) ($_SESSION['subscriber_email'] ?? '');
$roleUtilisateur = (string) ($_SESSION['subscriber_role'] ?? '');
$estCompteMairie = !empty($_SESSION['subscriber_compte_mairie']);

$communeRow = null;
$communePalierLibelle = '—';
$idCompteMairie = null;
$emailMairieInst = '';
$nbAbonnements = 0;
$abonnementsExpirentBientot = 0;

if (isset($pdo) && $pdo !== null) {
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
    $idCompteMairie = maire_get_compte_mairie_id($pdo);
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
        $nbAbonnements = (int) $pdo->query('SELECT COUNT(*) FROM abonnements')->fetchColumn();
    } catch (Throwable $e) {
        $nbAbonnements = 0;
    }
    try {
        $stExp = $pdo->prepare("SELECT COUNT(*) FROM abonnements WHERE date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $stExp->execute();
        $abonnementsExpirentBientot = (int) $stExp->fetchColumn();
    } catch (Throwable $e) {
        $abonnementsExpirentBientot = 0;
    }

    $compteursSignalements = maire_compter_signalements_par_statut($pdo);
    $compteursDocuments = maire_compter_documents_publics($pdo);
    $palierEffectifCommune = maire_palier_commune_actuel($pdo);
    $featuresEtat = maire_features_etat_pour_palier($palierEffectifCommune);

    $chartSignalementsMois = maire_stats_signalements_par_mois($pdo, 6);
    $chartCitoyensMois = maire_stats_citoyens_par_mois($pdo, 6);
    $chartTopDocs = maire_stats_top_documents($pdo, 5);
    $chartSignalementsCateg = maire_stats_signalements_par_categorie($pdo);

    $compteursNotif = maire_compter_notifications($pdo);
    $compteursConsult = maire_compter_consultations($pdo);
    $compteursPaiements = maire_paiements_compteurs($pdo);
} else {
    $compteursNotif = ['total' => 0, 'envoyees' => 0, 'echecs' => 0, 'destinataires' => 0];
    $compteursConsult = ['total' => 0, 'ouvertes' => 0, 'fermees' => 0, 'brouillons' => 0, 'votes' => 0];
    $compteursPaiements = ['total' => 0, 'initie' => 0, 'en_attente' => 0, 'paye' => 0, 'echec' => 0, 'montant_paye' => 0.0, 'montant_attente' => 0.0];
    $compteursSignalements = ['nouveau' => 0, 'pris_en_charge' => 0, 'resolu' => 0, 'rejete' => 0];
    $compteursDocuments = ['total' => 0, 'publies' => 0, 'hors_ligne' => 0, 'telechargements' => 0];
    $palierEffectifCommune = 'simple';
    $featuresEtat = maire_features_etat_pour_palier('simple');
    $chartSignalementsMois = ['labels' => [], 'data' => []];
    $chartCitoyensMois = ['labels' => [], 'data' => []];
    $chartTopDocs = ['labels' => [], 'data' => []];
    $chartSignalementsCateg = ['labels' => [], 'data' => [], 'colors' => []];
}

$pageNeedsCharts = true;

$dateFinCommune = $communeRow !== null ? substr((string) ($communeRow['date_fin'] ?? ''), 0, 10) : '';
$autoRenewCommune = $communeRow !== null && (int) ($communeRow['auto_renew'] ?? 0) === 1;

require __DIR__ . '/../includes/header.php';

$maireBadgeRole = '';
$maireBadgeColor = '';
if ($estCompteMairie) {
    $maireBadgeRole = 'Compte institutionnel mairie';
    $maireBadgeColor = 'bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200';
} elseif ($estConsoleSecrete) {
    $maireBadgeRole = 'Console secrète';
    $maireBadgeColor = 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200';
} elseif ($roleUtilisateur === 'admin') {
    $maireBadgeRole = 'Administrateur';
    $maireBadgeColor = 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
}
?>
<main class="bg-slate-50 dark:bg-slate-950 min-h-screen pb-16">
    <!-- HERO ADMIN -->
    <section class="relative bg-gradient-to-br from-mairie-900 via-mairie-800 to-mairie-950 text-white overflow-hidden">
        <div class="absolute inset-0 pointer-events-none opacity-30" aria-hidden="true">
            <div class="absolute top-0 right-0 w-96 h-96 bg-gold-500/30 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-mairie-400/30 rounded-full blur-3xl"></div>
        </div>
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 relative">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-xs font-semibold uppercase tracking-wider text-mairie-100 mb-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Espace mairie
                    </span>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">Tableau de bord administration</h1>
                    <p class="text-mairie-100">
                        Bienvenue
                        <strong class="text-white"><?php echo htmlspecialchars($emailUtilisateur !== '' ? $emailUtilisateur : 'utilisateur', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($maireBadgeRole !== ''): ?>
                            <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $maireBadgeColor; ?>">
                                <?php echo htmlspecialchars($maireBadgeRole, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="../index.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 text-sm font-semibold transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Site public
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
        <!-- KPI strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Palier actif</p>
                <p class="text-xl font-bold text-mairie-800 dark:text-mairie-300 mt-1 truncate"><?php echo htmlspecialchars($communePalierLibelle, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Échéance</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo htmlspecialchars($dateFinCommune !== '' ? $dateFinCommune : '—', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Renouv. auto</p>
                <p class="text-xl font-bold mt-1 <?php echo $autoRenewCommune ? 'text-green-600' : 'text-red-500'; ?>">
                    <?php echo $autoRenewCommune ? '✓ Oui' : '✗ Non'; ?>
                </p>
            </div>
            <div class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Comptes</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo (int) $nbAbonnements; ?></p>
            </div>
            <div class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Expirent &lt; 7 j</p>
                <p class="text-xl font-bold mt-1 <?php echo $abonnementsExpirentBientot > 0 ? 'text-orange-500' : 'text-slate-500'; ?>">
                    <?php echo (int) $abonnementsExpirentBientot; ?>
                </p>
            </div>
        </div>

        <!-- Bandeau compte institutionnel -->
        <?php if ($idCompteMairie !== null && $emailMairieInst !== ''): ?>
            <div class="mb-6 p-4 rounded-2xl bg-mairie-50 dark:bg-mairie-900/30 border border-mairie-200 dark:border-mairie-700 flex items-start gap-3">
                <svg class="w-5 h-5 text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-sm">
                    <p class="font-semibold text-mairie-900 dark:text-mairie-100">Compte institutionnel mairie désigné</p>
                    <p class="text-mairie-700 dark:text-mairie-300 mt-0.5">
                        <code class="px-1.5 py-0.5 bg-mairie-100 dark:bg-mairie-800 rounded text-xs"><?php echo htmlspecialchars($emailMairieInst, ENT_QUOTES, 'UTF-8'); ?></code>
                        — seul ce compte (ou la console secrète) peut modifier la formule communale.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="text-sm">
                    <p class="font-semibold text-amber-900 dark:text-amber-100">Aucun compte institutionnel mairie n'est désigné</p>
                    <p class="text-amber-700 dark:text-amber-300 mt-0.5">Ouvrez « Comptes &amp; abonnement » pour le faire.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Graphes -->
        <div class="tw-card p-6 mb-6">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">📊</span>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Activité en temps réel — 6 derniers mois</h2>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Suivi mensuel des signalements citoyens, nouvelles inscriptions habitants et documents téléchargés.</p>
            <div class="maire-chart-grid grid sm:grid-cols-2 gap-4">
                <div class="maire-chart-card bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-100">Signalements / mois</h3>
                    <small class="text-slate-500 dark:text-slate-400">Évolution mensuelle des remontées citoyennes</small>
                    <div class="maire-chart-canvas-wrap mt-3" style="height:220px;">
                        <canvas data-chart="line" data-label="Signalements" data-color="#f59e0b"
                                data-payload="<?php echo maire_chart_data_attr($chartSignalementsMois); ?>"></canvas>
                    </div>
                </div>
                <div class="maire-chart-card bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-100">Nouveaux habitants / mois</h3>
                    <small class="text-slate-500 dark:text-slate-400">Inscriptions sur l'Espace citoyen</small>
                    <div class="maire-chart-canvas-wrap mt-3" style="height:220px;">
                        <canvas data-chart="line" data-label="Inscriptions" data-color="#0c4a3e"
                                data-payload="<?php echo maire_chart_data_attr($chartCitoyensMois); ?>"></canvas>
                    </div>
                </div>
                <div class="maire-chart-card bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-100">Répartition signalements</h3>
                    <small class="text-slate-500 dark:text-slate-400">Par catégorie</small>
                    <div class="maire-chart-canvas-wrap mt-3" style="height:220px;">
                        <canvas data-chart="doughnut"
                                data-payload="<?php echo maire_chart_data_attr($chartSignalementsCateg); ?>"></canvas>
                    </div>
                </div>
                <div class="maire-chart-card bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-100">Top 5 documents téléchargés</h3>
                    <small class="text-slate-500 dark:text-slate-400">Bibliothèque municipale</small>
                    <div class="maire-chart-canvas-wrap mt-3" style="height:260px;">
                        <canvas data-chart="bar-h" data-label="Téléchargements" data-color="#0ea5e9"
                                data-payload="<?php echo maire_chart_data_attr($chartTopDocs); ?>"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grille des cartes modules -->
        <div class="grid lg:grid-cols-2 xl:grid-cols-3 gap-4">

            <!-- Carte : Formule communale active (2 colonnes) -->
            <article class="tw-card p-6 lg:col-span-2 xl:col-span-3 border-2 border-mairie-700/20 dark:border-mairie-500/30">
                <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-2xl">💎</span>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Formule communale active</h2>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Palier actuel :
                            <strong class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                                <?php echo $palierEffectifCommune === 'premium' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200' : ($palierEffectifCommune === 'standard' ? 'bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'); ?>">
                                <?php echo htmlspecialchars(maire_palier_libelle_court($palierEffectifCommune), ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </p>
                    </div>
                    <?php if (!empty($featuresEtat['verrouillees'])): ?>
                        <div class="flex gap-2">
                            <a class="tw-btn-primary text-sm" href="abonnements.php">Mettre à niveau</a>
                            <a class="tw-btn-outline text-sm" href="../offres.php">Comparer</a>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-2">Modules débloqués pour votre commune</p>
                <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($featuresEtat['disponibles'] as $feat): ?>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-100 dark:border-green-900/50 text-sm">
                            <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span class="text-green-800 dark:text-green-200"><?php echo htmlspecialchars(maire_feature_libelle($feat), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($featuresEtat['verrouillees'] as $feat):
                        $palierMin = maire_feature_palier_minimum($feat);
                    ?>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-sm opacity-60">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            <span class="text-slate-600 dark:text-slate-400 truncate">
                                <?php echo htmlspecialchars(maire_feature_libelle($feat), ENT_QUOTES, 'UTF-8'); ?>
                                <span class="text-xs text-slate-400">(<?php echo htmlspecialchars(maire_palier_libelle_court($palierMin), ENT_QUOTES, 'UTF-8'); ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <!-- Comptes & abonnement -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">👥</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Comptes &amp; abonnement</h2>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Formule de la commune, renouvellement automatique, gestion des comptes agents.</p>
                <?php if ($palierEffectifCommune === 'simple'): ?>
                    <div class="mb-3 p-2 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                        <span>Formule Simple : limite de <strong><?php echo MAIRE_AGENTS_MAX_SIMPLE; ?></strong> comptes agents.</span>
                    </div>
                <?php endif; ?>
                <a class="tw-btn-primary w-full text-sm" href="abonnements.php">Gérer les comptes</a>
            </article>

            <!-- Paiements abonnements -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md">💰</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Paiements abonnements</h2>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Suivi des paiements de l'abonnement communal et historique comptable des agents.</p>
                <a class="tw-btn-primary w-full text-sm" href="paiements.php">Ouvrir les paiements</a>
            </article>

            <!-- Référentiel -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 text-white flex items-center justify-center shadow-md">📚</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Référentiel &amp; fil</h2>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Écoles, centres de santé, services municipaux, et publications sur le fil communal.</p>
                <div class="flex flex-col gap-2">
                    <a class="tw-btn-primary w-full text-sm" href="standard.php">Ouvrir le référentiel</a>
                    <a class="tw-btn-outline w-full text-sm" href="standard.php#fil-standard-plus">Fil d'annonces</a>
                </div>
            </article>

            <!-- Signalements citoyens -->
            <?php $signalementsActifs = maire_feature_disponible_palier($palierEffectifCommune, 'signalements_citoyens'); ?>
            <article class="tw-card p-6 <?php echo !$signalementsActifs ? 'opacity-70' : ''; ?>">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white flex items-center justify-center shadow-md">🚨</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex-1">Signalements</h2>
                    <?php if (!$signalementsActifs): ?>
                        <span class="tw-badge bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">🔒 Standard</span>
                    <?php endif; ?>
                </div>
                <?php if ($signalementsActifs): ?>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 rounded-lg bg-orange-50 dark:bg-orange-950/30 text-center">
                            <div class="text-xl font-bold text-orange-600 dark:text-orange-400"><?php echo (int) ($compteursSignalements['nouveau'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Nouveaux</div>
                        </div>
                        <div class="p-2 rounded-lg bg-sky-50 dark:bg-sky-950/30 text-center">
                            <div class="text-xl font-bold text-sky-600 dark:text-sky-400"><?php echo (int) ($compteursSignalements['pris_en_charge'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">En cours</div>
                        </div>
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/30 text-center">
                            <div class="text-xl font-bold text-green-600 dark:text-green-400"><?php echo (int) ($compteursSignalements['resolu'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Résolus</div>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Remontées habitants (routes, lampadaires, déchets…) avec photo et géolocalisation.</p>
                    <a class="tw-btn-primary w-full text-sm" href="signalements.php">Traiter les signalements</a>
                <?php else: ?>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Permettez à vos habitants de signaler problèmes avec photo et géolocalisation.</p>
                    <a class="tw-btn-primary w-full text-sm" href="abonnements.php">Passer en Standard</a>
                <?php endif; ?>
            </article>

            <!-- Notifications -->
            <?php $notifActives = maire_feature_disponible_palier($palierEffectifCommune, 'notifications_email'); ?>
            <article class="tw-card p-6 <?php echo !$notifActives ? 'opacity-70' : ''; ?>">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center shadow-md">📧</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex-1">Notifications</h2>
                    <?php if (!$notifActives): ?>
                        <span class="tw-badge bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">🔒 Standard</span>
                    <?php endif; ?>
                </div>
                <?php if ($notifActives): ?>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-center">
                            <div class="text-xl font-bold text-slate-700 dark:text-slate-200"><?php echo (int) ($compteursNotif['total'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Diffusions</div>
                        </div>
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/30 text-center">
                            <div class="text-xl font-bold text-green-600 dark:text-green-400"><?php echo (int) ($compteursNotif['envoyees'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Réussis</div>
                        </div>
                        <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/30 text-center">
                            <div class="text-xl font-bold text-blue-600 dark:text-blue-400"><?php echo number_format((int) ($compteursNotif['destinataires'] ?? 0), 0, ',', ' '); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Touchés</div>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Envoyez urgences, événements ou informations en email + SMS.</p>
                    <a class="tw-btn-primary w-full text-sm" href="notifications.php">Composer une notification</a>
                <?php else: ?>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Diffusez urgences météo, coupures, événements à toute la commune.</p>
                    <a class="tw-btn-primary w-full text-sm" href="abonnements.php">Passer en Standard</a>
                <?php endif; ?>
            </article>

            <!-- Paiements en ligne -->
            <?php $paiementsActifs = maire_feature_disponible_palier($palierEffectifCommune, 'paiements_en_ligne'); ?>
            <article class="tw-card p-6 <?php echo !$paiementsActifs ? 'opacity-70' : ''; ?>">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white flex items-center justify-center shadow-md">💳</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex-1">Paiements en ligne</h2>
                    <?php if (!$paiementsActifs): ?>
                        <span class="tw-badge bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">🔒 Standard</span>
                    <?php endif; ?>
                </div>
                <?php if ($paiementsActifs): ?>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/30">
                            <div class="text-xs text-slate-600 dark:text-slate-400">Encaissé</div>
                            <div class="text-base font-bold text-green-600 dark:text-green-400 truncate"><?php echo maire_paiement_format_montant((float) $compteursPaiements['montant_paye']); ?></div>
                        </div>
                        <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/30">
                            <div class="text-xs text-slate-600 dark:text-slate-400">En attente</div>
                            <div class="text-base font-bold text-amber-600 dark:text-amber-400 truncate"><?php echo maire_paiement_format_montant((float) $compteursPaiements['montant_attente']); ?></div>
                        </div>
                        <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                            <div class="text-xs text-slate-600 dark:text-slate-400">Payées</div>
                            <div class="text-base font-bold text-slate-700 dark:text-slate-200"><?php echo (int) $compteursPaiements['paye']; ?></div>
                        </div>
                        <div class="p-2 rounded-lg bg-red-50 dark:bg-red-950/30">
                            <div class="text-xs text-slate-600 dark:text-slate-400">Échecs</div>
                            <div class="text-base font-bold text-red-600 dark:text-red-400"><?php echo (int) $compteursPaiements['echec']; ?></div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a class="tw-btn-primary w-full text-sm" href="paiements.php">Gérer les paiements</a>
                        <a class="tw-btn-outline w-full text-sm" href="../paiements.php">Voir catalogue public</a>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Encaissez recettes (taxes, documents, réservations) via Orange Money &amp; Wave.</p>
                    <a class="tw-btn-primary w-full text-sm" href="abonnements.php">Passer en Standard</a>
                <?php endif; ?>
            </article>

            <!-- Consultations & votes -->
            <?php $consultActives = maire_feature_disponible_palier($palierEffectifCommune, 'votes_electroniques'); ?>
            <article class="tw-card p-6 <?php echo !$consultActives ? 'opacity-70' : ''; ?>">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white flex items-center justify-center shadow-md">🗳️</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex-1">Consultations</h2>
                    <?php if (!$consultActives): ?>
                        <span class="tw-badge bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">🔒 Premium</span>
                    <?php endif; ?>
                </div>
                <?php if ($consultActives): ?>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/30 text-center">
                            <div class="text-xl font-bold text-green-600 dark:text-green-400"><?php echo (int) ($compteursConsult['ouvertes'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">En cours</div>
                        </div>
                        <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-center">
                            <div class="text-xl font-bold text-slate-700 dark:text-slate-200"><?php echo (int) ($compteursConsult['total'] ?? 0); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Total</div>
                        </div>
                        <div class="p-2 rounded-lg bg-mairie-50 dark:bg-mairie-950/30 text-center">
                            <div class="text-xl font-bold text-mairie-700 dark:text-mairie-300"><?php echo number_format((int) ($compteursConsult['votes'] ?? 0), 0, ',', ' '); ?></div>
                            <div class="text-xs text-slate-600 dark:text-slate-400">Votes</div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a class="tw-btn-primary w-full text-sm" href="consultations.php">Gérer les consultations</a>
                        <a class="tw-btn-outline w-full text-sm" href="../consultations.php">Voir page publique</a>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Engagez les habitants par des votes électroniques sécurisés (1 voix par compte).</p>
                    <a class="tw-btn-primary w-full text-sm" href="abonnements.php">Passer en Premium</a>
                <?php endif; ?>
            </article>

            <!-- Bibliothèque documents -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 text-white flex items-center justify-center shadow-md">📄</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Bibliothèque</h2>
                </div>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-center">
                        <div class="text-xl font-bold text-slate-700 dark:text-slate-200"><?php echo (int) ($compteursDocuments['total'] ?? 0); ?></div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">Documents</div>
                    </div>
                    <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/30 text-center">
                        <div class="text-xl font-bold text-green-600 dark:text-green-400"><?php echo (int) ($compteursDocuments['publies'] ?? 0); ?></div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">En ligne</div>
                    </div>
                    <div class="p-2 rounded-lg bg-mairie-50 dark:bg-mairie-950/30 text-center">
                        <div class="text-xl font-bold text-mairie-700 dark:text-mairie-300"><?php echo number_format((int) ($compteursDocuments['telechargements'] ?? 0), 0, ',', ' '); ?></div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">Téléch.</div>
                    </div>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Publiez formulaires, actes administratifs et guides téléchargeables.</p>
                <div class="flex flex-col gap-2">
                    <a class="tw-btn-primary w-full text-sm" href="documents.php">Gérer la bibliothèque</a>
                    <a class="tw-btn-outline w-full text-sm" href="../documents.php">Voir page publique</a>
                </div>
            </article>

            <!-- Outils Phase X -->
            <article class="tw-card p-6 lg:col-span-2 bg-gradient-to-br from-mairie-50 to-white dark:from-mairie-900/30 dark:to-slate-800 border-mairie-200 dark:border-mairie-700">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-2xl">🚀</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Outils Phase X — Fonctionnalités avancées</h2>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Modules haut niveau pour les communes modernes.</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <a href="conseil-municipal.php" class="block p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-mairie-500 hover:shadow-md transition-all group">
                        <div class="text-2xl mb-1">📺</div>
                        <div class="font-bold text-slate-900 dark:text-white group-hover:text-mairie-700">Conseil municipal</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Streaming des séances</div>
                    </a>
                    <a href="chatbot-faq.php" class="block p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-mairie-500 hover:shadow-md transition-all group">
                        <div class="text-2xl mb-1">🤖</div>
                        <div class="font-bold text-slate-900 dark:text-white group-hover:text-mairie-700">Assistant IA</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">FAQ du chatbot</div>
                    </a>
                    <a href="api-keys.php" class="block p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-mairie-500 hover:shadow-md transition-all group">
                        <div class="text-2xl mb-1">🔑</div>
                        <div class="font-bold text-slate-900 dark:text-white group-hover:text-mairie-700">API publique</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Clés &amp; documentation</div>
                    </a>
                </div>
            </article>

            <!-- Navigation rapide -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center shadow-md">🧭</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Navigation</h2>
                </div>
                <div class="flex flex-col gap-2">
                    <a class="tw-btn-outline w-full text-sm" href="../standard.php">Espace agent (vue connectée)</a>
                    <a class="tw-btn-outline w-full text-sm" href="../index.php">Retour site public</a>
                    <?php if ($estConsoleSecrete): ?>
                        <?php if (empty($_SESSION['abo_admin_csrf'])) { $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32)); } ?>
                        <form method="POST" action="super-admin-exit.php">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="tw-btn-outline w-full text-sm" type="submit">Quitter la console secrète</button>
                        </form>
                    <?php endif; ?>
                    <a class="inline-flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl border-2 border-red-500 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 font-semibold text-sm transition-colors" href="../deconnexion.php">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Déconnexion complète
                    </a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
