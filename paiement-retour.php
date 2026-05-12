<?php
declare(strict_types=1);

/**
 * Page de retour après paiement (succès ou échec).
 * En mode "log" (démo), accepte ?simulate=ok pour marquer comme payé,
 * ou ?simulate=ko pour marquer en échec. Sinon affiche le statut courant.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/paiements.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
$simulate = strtolower(trim((string) ($_GET['simulate'] ?? '')));

if ($ref === '' || $pdo === null) {
    http_response_code(400);
    $pageTitle = 'Référence manquante';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="overflow-hidden">
      <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
          <div class="text-7xl mb-4 opacity-80">⚠️</div>
          <h1 class="text-4xl md:text-5xl font-black mb-3">Référence manquante</h1>
          <p class="text-mairie-100 mb-6">Cette page nécessite une référence de paiement valide.</p>
          <a class="tw-btn-primary" href="paiements.php">Retour aux paiements</a>
        </div>
      </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$paie = maire_paiement_load_by_reference($pdo, $ref);
if ($paie === null) {
    http_response_code(404);
    $pageTitle = 'Paiement introuvable';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="overflow-hidden">
      <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
          <div class="text-7xl mb-4 opacity-80">🔍</div>
          <h1 class="text-4xl md:text-5xl font-black mb-3">Paiement introuvable</h1>
          <p class="text-mairie-100 mb-6">Aucune transaction trouvée pour cette référence.</p>
          <a class="tw-btn-primary" href="paiements.php">Retour aux paiements</a>
        </div>
      </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Simulation côté provider "log" (uniquement)
if ($simulate !== '' && (string) $paie['provider'] === 'log' && in_array((string) $paie['statut'], ['initie', 'en_attente'], true)) {
    if ($simulate === 'ok') {
        maire_paiement_marquer_paye($pdo, (int) $paie['id'], [
            'simulated' => true,
            'mode' => 'return_url',
            'timestamp' => date('c'),
        ]);
    } elseif ($simulate === 'ko') {
        maire_paiement_marquer_echec($pdo, (int) $paie['id'], [
            'simulated' => true,
            'mode' => 'cancel_url',
            'timestamp' => date('c'),
        ]);
    }
    $paie = maire_paiement_load_by_reference($pdo, $ref);
}

$statut = (string) $paie['statut'];
$paye = $statut === 'paye';
$echec = in_array($statut, ['echec', 'annule'], true);

$pageTitle = $paye ? 'Paiement confirmé' : ($echec ? 'Paiement échoué' : 'Paiement en attente');
require __DIR__ . '/includes/header.php';

if ($paye) {
    $heroIcon = '✅'; $heroTitle = 'Paiement confirmé'; $heroBg = 'from-emerald-600 via-emerald-700 to-teal-800';
} elseif ($echec) {
    $heroIcon = '❌'; $heroTitle = 'Paiement échoué'; $heroBg = 'from-red-600 via-red-700 to-rose-800';
} else {
    $heroIcon = '⏳'; $heroTitle = 'Paiement en attente'; $heroBg = 'from-amber-600 via-orange-600 to-orange-800';
}
?>
<main class="overflow-hidden">
    <!-- HERO STATUT -->
    <section class="relative bg-gradient-to-br <?php echo $heroBg; ?> text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-white/10 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="text-8xl mb-6 maire-float"><?php echo $heroIcon; ?></div>
            <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-white/90 mb-4">Retour passerelle de paiement</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight mb-4"><?php echo htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-lg text-white/90">Référence : <code class="px-3 py-1.5 rounded-lg bg-white/15 backdrop-blur-md font-mono"><?php echo htmlspecialchars((string) $paie['reference'], ENT_QUOTES, 'UTF-8'); ?></code></p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

            <?php if ($paye): ?>
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/30 border-2 border-emerald-500 rounded-2xl p-7">
                    <h2 class="text-xl font-black text-emerald-900 dark:text-emerald-200 mb-2">Merci, votre règlement est enregistré</h2>
                    <p class="text-emerald-800 dark:text-emerald-300">La mairie a bien reçu votre paiement. Un reçu numérique est accessible ci-dessous. Pour les documents express, vous serez recontacté(e) dans le délai indiqué.</p>
                </div>
            <?php elseif ($echec): ?>
                <div class="bg-red-50 dark:bg-red-950/30 border-2 border-red-500 rounded-2xl p-7">
                    <h2 class="text-xl font-black text-red-900 dark:text-red-200 mb-2">Le paiement n'a pas abouti</h2>
                    <p class="text-red-800 dark:text-red-300 mb-4">Vous pouvez réessayer ou choisir un autre moyen de paiement.</p>
                    <a class="tw-btn-primary" href="payer.php?service=<?php echo urlencode((string) $paie['service_code']); ?>">Réessayer</a>
                </div>
            <?php else: ?>
                <div class="bg-amber-50 dark:bg-amber-950/30 border-2 border-amber-500 rounded-2xl p-7">
                    <h2 class="text-xl font-black text-amber-900 dark:text-amber-200 mb-2">Votre paiement est en cours de traitement</h2>
                    <p class="text-amber-800 dark:text-amber-300">La passerelle confirme habituellement le paiement en quelques secondes. Rafraîchissez cette page dans un instant pour vérifier le statut.</p>
                </div>
            <?php endif; ?>

            <article class="tw-card p-7">
                <h2 class="text-xl font-black text-slate-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">📃</span>
                    Reçu de transaction
                </h2>
                <dl class="divide-y divide-slate-200 dark:divide-slate-700">
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Référence</dt>
                        <dd class="col-span-2 text-sm"><code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono"><?php echo htmlspecialchars((string) $paie['reference'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Service</dt>
                        <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $paie['service_libelle'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Catégorie</dt>
                        <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars(maire_paiement_libelle_categorie((string) $paie['service_categorie']), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Montant</dt>
                        <dd class="col-span-2 text-lg font-black maire-text-gradient"><?php echo maire_paiement_format_montant((float) $paie['montant'], (string) $paie['devise']); ?></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Moyen de paiement</dt>
                        <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars(maire_paiement_provider_libelle((string) $paie['provider']), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Statut</dt>
                        <dd class="col-span-2">
                            <span class="<?php echo $paye ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : ($echec ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'); ?> inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider">
                                <?php echo htmlspecialchars(maire_paiement_libelle_statut($statut), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </dd>
                    </div>
                    <?php if ($paie['paye_le'] !== null): ?>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Payé le</dt>
                        <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $paie['paye_le'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-3 gap-3 py-3">
                        <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Initié le</dt>
                        <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $paie['created_at'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-3 mt-6 pt-5 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" class="tw-btn-outline" onclick="window.print();">
                        🖨 Imprimer le reçu
                    </button>
                    <a class="tw-btn-primary" href="paiements.php">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Autres services
                    </a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
