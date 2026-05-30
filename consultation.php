<?php
declare(strict_types=1);

/**
 * Détail d'une consultation : formulaire de vote (si connecté et ouverte) ou résultats.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/citoyen-session.php';
require_once __DIR__ . '/includes/consultations.php';
require_once __DIR__ . '/includes/super-admin-session.php';
require_once __DIR__ . '/includes/feature-gates.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';
require_once __DIR__ . '/includes/stats-temporelles.php';
require_once __DIR__ . '/includes/csrf.php';

$citoyenCsrfScope = MAIRE_CSRF_SCOPE_CITOYEN;

$id = (int) ($_GET['id'] ?? 0);
$consultation = $pdo !== null ? maire_load_consultation($pdo, $id) : null;
if ($consultation === null || (string) $consultation['statut'] === 'brouillon') {
    http_response_code(404);
    $pageTitle = 'Consultation introuvable';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="overflow-hidden">
      <section class="relative maire-hero-bg text-white py-24 maire-grain overflow-hidden">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
          <div class="text-7xl mb-4 opacity-80">🗳️</div>
          <h1 class="text-4xl md:text-5xl font-black mb-3">Consultation introuvable</h1>
          <p class="text-mairie-100 mb-6">Cette consultation n'existe pas ou n'est pas publiée.</p>
          <a class="tw-btn-primary" href="consultations.php">Retour à la liste</a>
        </div>
      </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}
$options = maire_options_consultation($pdo, $id);

$citoyenConnecte = maire_citoyen_session_valid();
$idCit = $citoyenConnecte ? (int) ($_SESSION['citoyen_id'] ?? 0) : 0;
$dejaVote = $citoyenConnecte && $idCit > 0 ? maire_citoyen_a_vote($pdo, $id, $idCit) : false;
$choix = $dejaVote ? maire_options_choisies_par_citoyen($pdo, $id, $idCit) : [];

$flash = '';
$flashType = 'success';

$ouverte = (string) $consultation['statut'] === 'ouverte';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!$citoyenConnecte) {
        header('Location: citoyen/connexion.php?apres=' . urlencode('consultation.php?id=' . $id), true, 302);
        exit;
    }
    if (!maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'votes_electroniques')) {
        $palierCommune = maire_palier_commune_actuel($pdo);
        maire_render_paywall_page('votes_electroniques', $palierCommune, 'public');
        exit;
    }
    if (!maire_csrf_validate($citoyenCsrfScope)) {
        $flash = maire_csrf_error_message();
        $flashType = 'danger';
    } elseif (!maire_rate_limit_allow('vote', 5, 60)) {
        $flash = 'Trop de tentatives. Réessayez dans une minute.';
        $flashType = 'danger';
    } else {
        $picked = $_POST['option_id'] ?? [];
        if (!is_array($picked)) {
            $picked = [$picked];
        }
        $picked = array_map('intval', $picked);
        $err = null;
        if (maire_voter($pdo, $id, $idCit, $picked, $err)) {
            $flash = 'Votre vote a bien été enregistré. Merci !';
            $dejaVote = true;
            $choix = $picked;
            $consultation = maire_load_consultation($pdo, $id);
            $options = maire_options_consultation($pdo, $id);
        } else {
            $flash = $err ?? 'Vote impossible.';
            $flashType = 'danger';
        }
    }
}

$resultatsVisibles = (int) ($consultation['resultats_publics'] ?? 0) === 1
    && (!$ouverte || $dejaVote);
$resultats = $resultatsVisibles ? maire_resultats_chart($pdo, $id) : null;
$pageNeedsCharts = $resultatsVisibles;

$pageTitle = (string) $consultation['titre'];
$pageDescription = mb_substr((string) $consultation['question'], 0, 160);
require __DIR__ . '/includes/header.php';

$statutClasses = [
    'ouverte'  => 'bg-emerald-500 text-white',
    'fermee'   => 'bg-slate-500 text-white',
    'brouillon' => 'bg-amber-500 text-white',
];
$badgeColor = $statutClasses[(string) $consultation['statut']] ?? 'bg-slate-500 text-white';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 lg:py-28 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-fuchsia-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-gold-400/25 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="consultations.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Toutes les consultations
            </a>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="maire-tag <?php echo $badgeColor; ?>">
                    <?php echo htmlspecialchars(maire_libelle_statut_consultation((string) $consultation['statut']), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="maire-section-kicker !bg-white/12 !text-white !border-white/20">
                    <?php echo htmlspecialchars(maire_libelle_type_consultation((string) $consultation['type']), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight mb-4">
                <?php echo htmlspecialchars((string) $consultation['titre'], ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <p class="text-xl text-mairie-100 leading-relaxed mb-6"><?php echo nl2br(htmlspecialchars((string) $consultation['question'], ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="flex flex-wrap gap-3 text-sm text-mairie-200">
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15">
                    📅 Du <?php echo htmlspecialchars((string) $consultation['date_debut'], ENT_QUOTES, 'UTF-8'); ?> au <?php echo htmlspecialchars((string) $consultation['date_fin'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15">
                    👥 <strong class="text-white"><?php echo (int) $consultation['nb_votes_total']; ?></strong>&nbsp;participants
                </span>
            </div>
        </div>
    </section>

    <!-- CONTENU -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            <?php if ($flash !== ''): ?>
                <div class="<?php echo $flashType === 'danger' ? 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'; ?> border-2 rounded-2xl p-4 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0"><?php echo $flashType === 'danger' ? '⚠️' : '✅'; ?></span>
                    <p class="font-bold"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($consultation['description'])): ?>
                <article class="maire-panel p-7">
                    <h2 class="text-xl font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">ℹ️</span>
                        Présentation
                    </h2>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars((string) $consultation['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endif; ?>

            <?php if ($ouverte && !$dejaVote): ?>
                <?php if (!$citoyenConnecte): ?>
                    <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-8">
                        <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                        <div class="relative">
                            <h2 class="text-2xl font-black mb-2 flex items-center gap-2"><span>🔐</span> Connectez-vous pour participer</h2>
                            <p class="text-mairie-100 mb-5 max-w-2xl">Le vote est réservé aux habitants inscrits. Connectez-vous ou créez un compte gratuit en quelques secondes.</p>
                            <div class="flex flex-wrap gap-3">
                                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 font-black transition-colors" href="citoyen/connexion.php?apres=<?php echo urlencode('consultation.php?id=' . $id); ?>">Se connecter</a>
                                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 font-black transition-colors" href="citoyen/inscription.php">Créer un compte</a>
                            </div>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="maire-form-shell">
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2"><span>🗳️</span> Votre vote</h2>
                        <?php $multi = (int) ($consultation['multi_choix'] ?? 0) === 1; ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-5 italic"><?php echo $multi ? 'Plusieurs réponses possibles.' : 'Une seule réponse autorisée.'; ?></p>
                        <form method="POST" action="consultation.php?id=<?php echo (int) $id; ?>">
                            <?php echo maire_csrf_field($citoyenCsrfScope); ?>
                            <div class="grid gap-3">
                                <?php foreach ($options as $o): ?>
                                    <label class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 hover:border-mairie-500 dark:hover:border-mairie-400 hover:bg-mairie-50 dark:hover:bg-mairie-950/30 cursor-pointer transition-all group">
                                        <input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="option_id<?php echo $multi ? '[]' : ''; ?>" value="<?php echo (int) $o['id']; ?>" <?php echo $multi ? '' : 'required'; ?> class="w-5 h-5 accent-mairie-700">
                                        <span class="text-base font-bold text-slate-900 dark:text-slate-100 group-hover:text-mairie-700 dark:group-hover:text-mairie-300"><?php echo htmlspecialchars((string) $o['libelle'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="tw-btn-primary" onclick="return confirm('Confirmer votre vote ? Il ne pourra pas être modifié.');">
                                    📨 Valider mon vote
                                </button>
                            </div>
                        </form>
                    </article>
                <?php endif; ?>
            <?php elseif ($ouverte && $dejaVote): ?>
                <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/30 border-2 border-emerald-500 p-7">
                    <h2 class="text-xl font-black text-emerald-900 dark:text-emerald-200 mb-2 flex items-center gap-2"><span>✅</span> Merci pour votre vote</h2>
                    <p class="text-emerald-800 dark:text-emerald-300 mb-3">Votre participation a bien été enregistrée. Vous ne pouvez voter qu'une fois par consultation.</p>
                    <?php if (!empty($choix)): ?>
                        <p class="font-bold text-emerald-900 dark:text-emerald-200 mb-1">Votre choix :</p>
                        <ul class="space-y-1">
                            <?php foreach ($options as $o):
                                if (in_array((int) $o['id'], $choix, true)): ?>
                                    <li class="text-emerald-800 dark:text-emerald-300">→ <?php echo htmlspecialchars((string) $o['libelle'], ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endif;
                            endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <?php if ($resultatsVisibles && $resultats !== null): ?>
                <article class="maire-panel p-7">
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <span>📊</span> Résultats <span class="text-sm font-bold text-slate-500 dark:text-slate-400">(<?php echo $ouverte ? 'en cours' : 'définitifs'; ?>)</span>
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                        <strong><?php echo (int) $resultats['total']; ?></strong> vote(s) cumulé(s) sur <?php echo (int) $consultation['nb_votes_total']; ?> votant(s).
                    </p>
                    <div class="grid md:grid-cols-2 gap-6 items-center">
                        <div class="h-72">
                            <canvas
                                data-chart="doughnut"
                                data-payload="<?php echo maire_chart_data_attr($resultats); ?>"></canvas>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($options as $i => $o):
                                $nb = (int) $o['nb_votes'];
                                $pct = $resultats['total'] > 0 ? round($nb * 100 / $resultats['total']) : 0;
                                $col = $resultats['colors'][$i] ?? '#0c4a3e';
                            ?>
                                <div>
                                    <div class="flex justify-between items-baseline text-sm mb-1.5">
                                        <span class="font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars((string) $o['libelle'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-slate-900 dark:text-white"><?php echo $nb; ?> · <?php echo $pct; ?>%</strong>
                                    </div>
                                    <div class="bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all" style="width: <?php echo $pct; ?>%; background: <?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
            <?php elseif ($ouverte && !$dejaVote && (int) ($consultation['resultats_publics'] ?? 0) === 1): ?>
                <article class="maire-panel p-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 italic flex items-center gap-2">
                        <span>🔒</span> Les résultats seront visibles après que vous aurez voté ou à la clôture de la consultation.
                    </p>
                </article>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
