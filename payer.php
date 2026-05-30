<?php
declare(strict_types=1);

/**
 * Formulaire de paiement : récupère les infos client si visiteur, choix du provider,
 * crée la transaction, lance le provider, redirige vers la passerelle (ou page retour).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/citoyen-session.php';
require_once __DIR__ . '/includes/super-admin-session.php';
require_once __DIR__ . '/includes/feature-gates.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';
require_once __DIR__ . '/includes/paiements.php';
require_once __DIR__ . '/includes/site-paths.php';
require_once __DIR__ . '/includes/csrf.php';

$citoyenCsrfScope = MAIRE_CSRF_SCOPE_CITOYEN;

// Gating
if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'paiements_en_ligne')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('paiements_en_ligne', $palierCommune, 'public');
    exit;
}

$serviceCode = trim((string) ($_GET['service'] ?? ''));
$service = maire_paiements_service($serviceCode);
if ($service === null) {
    http_response_code(404);
    $pageTitle = 'Service introuvable';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="overflow-hidden">
      <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
          <div class="text-7xl mb-4 opacity-80">💸</div>
          <h1 class="text-4xl md:text-5xl font-black mb-3">Service introuvable</h1>
          <p class="text-mairie-100 mb-6">Le service demandé n'existe pas ou a été retiré du catalogue.</p>
          <a class="tw-btn-primary" href="paiements.php">Retour au catalogue</a>
        </div>
      </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Gating spécifique taxes
if ($service['categorie'] === 'taxe' && $pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'taxes_locales_en_ligne')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('taxes_locales_en_ligne', $palierCommune, 'public');
    exit;
}

$citoyenConnecte = maire_citoyen_session_valid();
$citoyenInfo = null;
if ($citoyenConnecte) {
    $citoyenInfo = maire_load_citoyen($pdo, (int) ($_SESSION['citoyen_id'] ?? 0));
}

$errors = [];
$prefill = [
    'nom' => $citoyenInfo !== null ? trim((string) $citoyenInfo['prenom'] . ' ' . (string) $citoyenInfo['nom']) : '',
    'email' => $citoyenInfo['email'] ?? '',
    'telephone' => $citoyenInfo['telephone'] ?? '',
    'details' => '',
    'provider' => MAIRE_PAIEMENT_PROVIDER_DEFAUT,
];
$paiementAdmin = maire_super_admin_session_valid();
$providerStates = [];
$providersAffichables = [];
$providersBientotDisponibles = [];
foreach (MAIRE_PAIEMENT_PROVIDERS as $code => $label) {
    $state = maire_paiement_provider_configuration($code);
    $providerStates[$code] = $state;

    if ($code === 'log' || $state['configured'] || $paiementAdmin) {
        $providersAffichables[$code] = $label;
        continue;
    }

    $providersBientotDisponibles[] = $label;
}
if ($providersAffichables === []) {
    $providersAffichables['log'] = MAIRE_PAIEMENT_PROVIDERS['log'];
}
if (!array_key_exists($prefill['provider'], $providersAffichables)) {
    $prefill['provider'] = (string) array_key_first($providersAffichables);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate($citoyenCsrfScope)) {
        $errors[] = maire_csrf_error_message();
    } elseif (!maire_rate_limit_allow('paiement_init', 6, 60)) {
        $errors[] = 'Trop de tentatives. Patientez une minute.';
    } else {
        $prefill['nom'] = trim((string) ($_POST['nom'] ?? $prefill['nom']));
        $prefill['email'] = trim((string) ($_POST['email'] ?? $prefill['email']));
        $prefill['telephone'] = trim((string) ($_POST['telephone'] ?? $prefill['telephone']));
        $prefill['details'] = trim((string) ($_POST['details'] ?? ''));
        $prefill['provider'] = (string) ($_POST['provider'] ?? MAIRE_PAIEMENT_PROVIDER_DEFAUT);

        if (!array_key_exists($prefill['provider'], $providersAffichables)) {
            $providerError = $providerStates[$prefill['provider']]['error'] ?? null;
            $errors[] = $paiementAdmin && $providerError !== null
                ? $providerError
                : 'Ce moyen de paiement est indisponible pour le moment. Choisissez un autre mode.';
        } else {
            $err = null;
            $id = maire_paiement_creer($pdo, [
                'service_code' => $serviceCode,
                'citoyen_id' => $citoyenConnecte ? (int) ($_SESSION['citoyen_id'] ?? 0) : null,
                'visiteur_nom' => $citoyenConnecte ? null : $prefill['nom'],
                'visiteur_email' => $citoyenConnecte ? null : ($prefill['email'] !== '' ? $prefill['email'] : null),
                'visiteur_telephone' => $citoyenConnecte ? null : $prefill['telephone'],
                'service_details' => $prefill['details'] !== '' ? $prefill['details'] : null,
                'provider' => $prefill['provider'],
            ], $err);

            if ($id === null) {
                $errors[] = $err ?? 'Création paiement impossible.';
            } else {
                $paie = maire_paiement_load($pdo, $id);
                $returnUrl = maire_url_absolue('paiement-retour.php?ref=' . urlencode((string) $paie['reference']));
                $cancelUrl = maire_url_absolue('paiement-retour.php?ref=' . urlencode((string) $paie['reference']) . '&simulate=ko');
                $webhookUrl = maire_url_absolue('paiement-webhook.php');
                $resp = maire_paiement_lancer_provider($pdo, $id, [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'webhook_url' => $webhookUrl,
                ]);
                if ($resp['ok'] && $resp['redirect_url'] !== null) {
                    header('Location: ' . $resp['redirect_url'], true, 302);
                    exit;
                }
                $errors[] = $resp['error'] ?? 'Le moyen de paiement a refusé la transaction. Choisissez un autre mode ou réessayez.';
            }
        }
    }
}

$pageTitle = 'Paiement — ' . $service['libelle'];
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-20 lg:py-24 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="paiements.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Catalogue des paiements
            </a>
            <span class="maire-section-kicker mb-4 !bg-white/12 !text-white !border-white/20">
                <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                <?php echo htmlspecialchars(maire_paiement_libelle_categorie((string) $service['categorie']), ENT_QUOTES, 'UTF-8'); ?> · Paiement sécurisé
            </span>
            <h1 class="text-4xl md:text-5xl font-black leading-tight tracking-tight mb-3">
                <?php echo htmlspecialchars((string) $service['libelle'], ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <p class="text-lg text-mairie-100 leading-relaxed max-w-3xl mb-5"><?php echo htmlspecialchars((string) $service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="inline-flex items-center gap-3 px-5 py-3 rounded-2xl bg-gold-400 text-mairie-950 shadow-glow">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-2xl font-black">Montant : <?php echo maire_paiement_format_montant((float) $service['prix']); ?></span>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr] items-start">
                <div class="space-y-6">

                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 dark:bg-red-950/30 border-2 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 rounded-2xl p-4 flex items-start gap-3">
                            <span class="text-2xl flex-shrink-0">⚠️</span>
                            <p class="font-bold"><?php echo htmlspecialchars(implode(' · ', $errors), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <article class="maire-form-shell">
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <div>
                                <p class="text-[0.72rem] uppercase tracking-[0.22em] text-slate-500 font-black mb-2">Étape 1</p>
                                <h2 class="text-2xl font-black text-slate-950 dark:text-white">Vos coordonnées</h2>
                            </div>
                            <span class="inline-flex w-11 h-11 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-lg shadow-panel">📋</span>
                        </div>
                        <?php if ($citoyenConnecte && $citoyenInfo !== null): ?>
                            <div class="bg-mairie-50 dark:bg-mairie-950/30 border border-mairie-200 dark:border-mairie-800 rounded-xl p-3 text-sm text-mairie-800 dark:text-mairie-200 mb-4">
                                Connecté en tant que <strong><?php echo htmlspecialchars($prefill['nom'], ENT_QUOTES, 'UTF-8'); ?></strong> · <?php echo htmlspecialchars((string) $citoyenInfo['email'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-slate-100 dark:bg-slate-800 rounded-xl p-3 text-sm text-slate-700 dark:text-slate-300 mb-4">
                                Vous pouvez payer sans compte. Pour suivre vos paiements ultérieurement,
                                <a class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="citoyen/connexion.php?apres=<?php echo urlencode('payer.php?service=' . $serviceCode); ?>">connectez-vous</a>
                                ou <a class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="citoyen/inscription.php">créez un compte habitant</a>.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="payer.php?service=<?php echo urlencode($serviceCode); ?>" class="space-y-4">
                            <?php echo maire_csrf_field($citoyenCsrfScope); ?>

                            <?php if (!$citoyenConnecte): ?>
                                <div>
                                    <label for="nom" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Nom complet *</label>
                                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($prefill['nom'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="120" class="tw-input">
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="telephone" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Téléphone *</label>
                                        <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($prefill['telephone'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="40" placeholder="+221 77 ..." class="tw-input">
                                    </div>
                                    <div>
                                        <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Email (facultatif)</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($prefill['email'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="190" class="tw-input">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div>
                                <label for="details" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Précisions (facultatif)</label>
                                <textarea id="details" name="details" rows="3" maxlength="2000" placeholder="Ex : pour le compte de M. Diop · date de réservation souhaitée · numéro d'extrait" class="tw-input resize-y"><?php echo htmlspecialchars($prefill['details'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <div>
                                <p class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">Moyen de paiement</p>
                                <div class="grid gap-2">
                                    <?php foreach ($providersAffichables as $code => $lbl):
                                        $checked = $code === $prefill['provider'];
                                        $providerIcons = ['orange_money' => '🟠', 'wave' => '🌊', 'free_money' => '📱', 'log' => '🧪'];
                                        $icon = $providerIcons[$code] ?? '💳';
                                        $providerConfig = maire_paiement_provider_configuration($code);
                                        $isConfigured = $providerConfig['configured'];
                                    ?>
                                        <label class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border-2 <?php echo $isConfigured ? 'border-slate-200 dark:border-slate-700 hover:border-mairie-500 dark:hover:border-mairie-400' : 'border-amber-300 dark:border-amber-700 hover:border-amber-400 dark:hover:border-amber-500'; ?> cursor-pointer transition-all">
                                            <input type="radio" name="provider" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?> required class="w-5 h-5 accent-mairie-700">
                                            <span class="text-2xl"><?php echo $icon; ?></span>
                                            <span class="flex-1">
                                                <strong class="block text-slate-900 dark:text-white"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if ($code === 'log'): ?>
                                                    <small class="text-xs text-slate-500 dark:text-slate-400">Mode démo : validation manuelle par la mairie</small>
                                                <?php endif; ?>
                                                <?php if ($paiementAdmin && !$isConfigured && $providerConfig['error'] !== null): ?>
                                                    <small class="mt-1 block text-xs font-semibold text-amber-700 dark:text-amber-300"><?php echo htmlspecialchars($providerConfig['error'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!$paiementAdmin && $providersBientotDisponibles !== []): ?>
                                    <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">
                                        Activation en cours : <?php echo htmlspecialchars(implode(', ', $providersBientotDisponibles), ENT_QUOTES, 'UTF-8'); ?>.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="flex flex-wrap gap-3 pt-2">
                                <button type="submit" class="tw-btn-primary flex-1 justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    Payer <?php echo maire_paiement_format_montant((float) $service['prix']); ?>
                                </button>
                                <a class="tw-btn-outline" href="paiements.php">Annuler</a>
                            </div>
                        </form>
                    </article>

                    <div class="bg-mairie-50 dark:bg-mairie-950/30 border border-mairie-200 dark:border-mairie-800 rounded-2xl p-4 text-sm text-mairie-800 dark:text-mairie-200 flex items-start gap-3">
                        <span class="text-xl flex-shrink-0">ℹ️</span>
                        <p>En validant ce paiement vous serez redirigé(e) vers la passerelle du fournisseur retenu. Vous reviendrez automatiquement sur cette page avec un statut « payé » ou « échec ».</p>
                    </div>
                </div>

                <aside class="space-y-6">
                    <article class="maire-surface--dark p-7">
                        <p class="text-[0.72rem] uppercase tracking-[0.22em] text-gold-300 font-black mb-3">Résumé</p>
                        <h2 class="text-2xl font-black mb-4">Votre paiement en un coup d’œil</h2>
                        <div class="space-y-4 text-sm">
                            <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-4">
                                <span class="text-slate-300">Service</span>
                                <strong class="text-right text-white"><?php echo htmlspecialchars((string) $service['libelle'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-4">
                                <span class="text-slate-300">Catégorie</span>
                                <strong class="text-right text-white"><?php echo htmlspecialchars(maire_paiement_libelle_categorie((string) $service['categorie']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <?php if (!empty($service['delai'])): ?>
                                <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-4">
                                    <span class="text-slate-300">Délai indicatif</span>
                                    <strong class="text-right text-white"><?php echo htmlspecialchars((string) $service['delai'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-slate-300">Montant</span>
                                <strong class="text-right text-2xl text-gold-300"><?php echo maire_paiement_format_montant((float) $service['prix']); ?></strong>
                            </div>
                        </div>
                    </article>

                    <article class="maire-panel">
                        <p class="text-[0.72rem] uppercase tracking-[0.22em] text-slate-500 font-black mb-3">Pourquoi ce nouveau parcours</p>
                        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <li class="flex items-start gap-2"><span class="text-emerald-600">•</span><span>Lecture plus simple avant validation du paiement.</span></li>
                            <li class="flex items-start gap-2"><span class="text-emerald-600">•</span><span>Distinction nette entre coordonnées, détails et provider.</span></li>
                            <li class="flex items-start gap-2"><span class="text-emerald-600">•</span><span>Résumé visible en permanence sur grand écran.</span></li>
                        </ul>
                    </article>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
