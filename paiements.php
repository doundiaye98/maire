<?php
declare(strict_types=1);

/**
 * Catalogue public des services payants de la mairie.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/paiements.php';
require_once __DIR__ . '/includes/feature-gates.php';

$paiementsActifs = $pdo !== null && maire_feature_disponible($pdo, 'paiements_en_ligne');
$taxesActives    = $pdo !== null && maire_feature_disponible($pdo, 'taxes_locales_en_ligne');

$catalogue = maire_paiements_catalogue();
$parCategorie = [];
foreach ($catalogue as $code => $s) {
    $parCategorie[$s['categorie']][$code] = $s;
}

$totalServices = count($catalogue);
$categoriesMeta = [
    'taxe' => [
        'title' => 'Taxes locales',
        'eyebrow' => 'Fiscalité municipale',
        'description' => 'Réglez vos contributions communales avec un parcours plus rassurant et mieux expliqué.',
        'gradient' => 'from-amber-500 to-orange-600',
        'icon' => '📜',
        'button' => 'Payer',
        'available' => $paiementsActifs && $taxesActives,
    ],
    'document_express' => [
        'title' => 'Documents express',
        'eyebrow' => 'Documents prioritaires',
        'description' => 'Accédez rapidement aux services documentaires à délai court.',
        'gradient' => 'from-blue-500 to-indigo-600',
        'icon' => '📄',
        'button' => 'Payer',
        'available' => $paiementsActifs,
    ],
    'reservation' => [
        'title' => 'Réservations',
        'eyebrow' => 'Équipements municipaux',
        'description' => 'Réservez salles et équipements via une grille claire et un tarif lisible.',
        'gradient' => 'from-violet-500 to-fuchsia-600',
        'icon' => '🏛️',
        'button' => 'Réserver',
        'available' => $paiementsActifs,
    ],
];

$startingPrice = empty($catalogue)
    ? null
    : min(array_map(static fn(array $service): int => (int) $service['prix'], $catalogue));

$featuredServices = [];
foreach ($categoriesMeta as $code => $meta) {
    if (!empty($parCategorie[$code])) {
        $firstKey = array_key_first($parCategorie[$code]);
        if ($firstKey !== null) {
            $featuredServices[] = [
                'service' => $parCategorie[$code][$firstKey],
                'code' => (string) $firstKey,
                'meta' => $meta,
            ];
        }
    }
}

$pageTitle = 'Paiement en ligne | Rufisque-Est';
$pageDescription = 'Réglez vos taxes, documents express et réservations municipales en ligne via Orange Money ou Wave.';
require __DIR__ . '/includes/header.php';

$renderCarte = static function (string $code, array $service, array $meta): void {
    $isAvailable = (bool) $meta['available'];
    $buttonLabel = (string) $meta['button'];
    ?>
    <article class="group maire-panel relative flex h-full flex-col overflow-hidden">
        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gradient-to-br <?php echo htmlspecialchars((string) $meta['gradient'], ENT_QUOTES, 'UTF-8'); ?> opacity-15 blur-2xl" aria-hidden="true"></div>
        <div class="relative flex items-start justify-between gap-3">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br <?php echo htmlspecialchars((string) $meta['gradient'], ENT_QUOTES, 'UTF-8'); ?> text-2xl text-white shadow-lg">
                <?php echo htmlspecialchars((string) $meta['icon'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="rounded-full border px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.2em] <?php echo $isAvailable ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300'; ?>">
                <?php echo $isAvailable ? 'Ouvert' : 'Indisponible'; ?>
            </span>
        </div>

        <div class="mt-6">
            <p class="text-[0.68rem] font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8'); ?></p>
            <h3 class="mt-2 text-2xl font-black leading-tight text-slate-950 dark:text-white"><?php echo htmlspecialchars((string) $service['libelle'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars((string) $service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="mt-6 flex items-center justify-between gap-4 border-t border-slate-100 pt-5 dark:border-slate-700">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Montant</p>
                <p class="mt-1 text-2xl font-black text-slate-950 dark:text-white"><?php echo htmlspecialchars(maire_paiement_format_montant((float) $service['prix']), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php if (!empty($service['delai'])): ?>
                <div class="text-right">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Délai</p>
                    <p class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars((string) $service['delai'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-auto pt-6">
            <?php if ($isAvailable): ?>
                <a class="tw-btn-primary w-full justify-center" href="payer.php?service=<?php echo urlencode($code); ?>">
                    <?php echo htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            <?php else: ?>
                <button type="button" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400" disabled>
                    Service temporairement indisponible
                </button>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg maire-grain py-24 text-white lg:py-32 overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-white/10 to-transparent pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -top-32 right-0 h-[30rem] w-[30rem] rounded-full bg-gold-400/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 left-[-8rem] h-[28rem] w-[28rem] rounded-full bg-emerald-400/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-end">
                <div class="max-w-3xl">
                    <span class="maire-section-kicker mb-6 !bg-white/12 !text-white !border-white/20">Mobile money municipal</span>
                    <h1 class="mb-6 text-5xl font-black leading-[0.93] tracking-tight md:text-6xl lg:text-7xl">
                        Payer une démarche
                        <span class="block text-gold-200">en toute confiance</span>
                    </h1>
                    <p class="max-w-2xl text-lg leading-relaxed text-mairie-100 md:text-xl">
                        Une vitrine transactionnelle plus claire pour régler taxes locales, documents express et réservations municipales depuis Orange Money ou Wave.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#catalogue-paiements" class="tw-btn-primary bg-gold-400 text-mairie-950 hover:bg-gold-300 shadow-none">
                            Voir les services payants
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </a>
                        <a href="contact.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 font-semibold text-white backdrop-blur-sm transition hover:bg-white/15">
                            Besoin d’aide
                        </a>
                    </div>
                </div>

                <aside class="maire-surface--dark p-6 md:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[0.68rem] font-black uppercase tracking-[0.24em] text-gold-300/85">Tableau de bord</p>
                            <h2 class="mt-2 text-2xl font-black text-white">Des montants lisibles et des moyens de paiement familiers</h2>
                        </div>
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-2xl">💳</span>
                    </div>

                    <div class="mt-6 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo $totalServices; ?>">0</span></p>
                            <p class="mt-1 text-[0.68rem] font-black uppercase tracking-[0.22em] text-mairie-200">Services</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo count($categoriesMeta); ?>">0</span></p>
                            <p class="mt-1 text-[0.68rem] font-black uppercase tracking-[0.22em] text-mairie-200">Parcours</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xl font-black"><?php echo $startingPrice === null ? '—' : htmlspecialchars(maire_paiement_format_montant((float) $startingPrice), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-1 text-[0.68rem] font-black uppercase tracking-[0.22em] text-mairie-200">À partir de</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[0.68rem] font-black uppercase tracking-[0.2em] text-gold-200/80">Moyens acceptés</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full bg-orange-500 px-3 py-1 text-xs font-black text-white">Orange Money</span>
                                <span class="rounded-full bg-sky-500 px-3 py-1 text-xs font-black text-white">Wave</span>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[0.68rem] font-black uppercase tracking-[0.2em] text-gold-200/80">Garantie</p>
                            <p class="mt-3 text-sm text-mairie-100/90">Référence unique, reçu immédiat et aucune donnée bancaire conservée par la mairie.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-12 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <?php if (!$paiementsActifs): ?>
                <div class="maire-panel border-2 border-amber-300/80 bg-gradient-to-br from-amber-50 via-white to-orange-50 dark:border-amber-500/30 dark:from-amber-950/20 dark:via-slate-900 dark:to-orange-950/20">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="max-w-2xl">
                            <span class="maire-tag border border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">Activation requise</span>
                            <h2 class="mt-4 text-2xl font-black text-slate-950 dark:text-white">Les paiements en ligne ne sont pas encore activés pour cette commune</h2>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                Le parcours est prêt côté vitrine, mais nécessite l’activation de l’offre Standard ou supérieure pour devenir pleinement opérationnel.
                            </p>
                        </div>
                        <a href="contact.php" class="tw-btn-outline self-start">
                            Contacter la mairie
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="bg-slate-50 pb-20 dark:bg-slate-900" id="catalogue-paiements">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
                <div class="maire-editorial-card">
                    <span class="maire-section-kicker">Usages prioritaires</span>
                    <h2 class="mt-5 text-3xl font-black text-slate-950 md:text-4xl dark:text-white">
                        Trois portes d’entrée pour démarrer sans hésiter
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                        Les parcours les plus fréquents sont mis en avant pour limiter les frictions, clarifier le tarif et réduire le temps de décision.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <?php foreach ($featuredServices as $feature): ?>
                        <?php $service = $feature['service']; ?>
                        <?php $meta = $feature['meta']; ?>
                        <article class="maire-bento-card relative overflow-hidden rounded-[2rem] bg-gradient-to-br <?php echo htmlspecialchars((string) $meta['gradient'], ENT_QUOTES, 'UTF-8'); ?> p-7 text-white shadow-luxury">
                            <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                            <div class="relative flex h-full min-h-[17rem] flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/12 text-3xl shadow-lg">
                                        <?php echo htmlspecialchars((string) $meta['icon'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.2em] text-white/90">
                                        <?php echo htmlspecialchars((string) $meta['eyebrow'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <h3 class="mt-6 text-2xl font-black leading-tight"><?php echo htmlspecialchars((string) $service['libelle'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="mt-3 text-sm leading-relaxed text-white/85"><?php echo htmlspecialchars((string) $service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-auto pt-6">
                                    <p class="text-xs font-black uppercase tracking-[0.22em] text-white/60">Montant</p>
                                    <p class="mt-1 text-2xl font-black"><?php echo htmlspecialchars(maire_paiement_format_montant((float) $service['prix']), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-14 space-y-12">
                <?php foreach ($categoriesMeta as $categoryCode => $meta): ?>
                    <section>
                        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <span class="maire-section-kicker mb-3"><?php echo htmlspecialchars((string) $meta['eyebrow'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br <?php echo htmlspecialchars((string) $meta['gradient'], ENT_QUOTES, 'UTF-8'); ?> text-2xl text-white shadow-lg"><?php echo htmlspecialchars((string) $meta['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div>
                                        <h2 class="text-2xl font-black text-slate-950 md:text-3xl dark:text-white"><?php echo htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars((string) $meta['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php if (!$meta['available']): ?>
                                <span class="maire-tag border border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">Premium requis</span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($parCategorie[$categoryCode])): ?>
                            <div class="maire-panel text-center text-slate-500 dark:text-slate-400">Aucun service configuré pour cette catégorie.</div>
                        <?php else: ?>
                            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($parCategorie[$categoryCode] as $code => $service): ?>
                                    <?php $renderCarte((string) $code, $service, $meta); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <article class="relative mt-16 overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-900 to-mairie-950 p-8 text-white shadow-luxury md:p-10">
                <div class="absolute -bottom-12 -right-12 h-60 w-60 rounded-full bg-emerald-500/30 blur-3xl pointer-events-none" aria-hidden="true"></div>
                <div class="relative max-w-3xl">
                    <span class="maire-tag mb-3 border border-emerald-400/30 bg-emerald-500/20 text-emerald-300">
                        <span>🔐</span> Sécurité et confidentialité
                    </span>
                    <h2 class="mb-5 text-2xl font-black md:text-3xl">Vos paiements sont pensés pour rassurer avant, pendant et après la transaction</h2>
                    <ul class="space-y-3 text-mairie-100">
                        <li class="flex items-start gap-3"><span class="mt-1 flex-shrink-0 text-emerald-400">✓</span> Référence unique générée pour chaque paiement <code class="rounded bg-white/10 px-2 py-0.5 font-mono text-xs text-gold-300">PAY-...</code></li>
                        <li class="flex items-start gap-3"><span class="mt-1 flex-shrink-0 text-emerald-400">✓</span> Orange Money et Wave mis en avant comme moyens familiers côté usagers</li>
                        <li class="flex items-start gap-3"><span class="mt-1 flex-shrink-0 text-emerald-400">✓</span> Aucune donnée bancaire conservée par la mairie</li>
                        <li class="flex items-start gap-3"><span class="mt-1 flex-shrink-0 text-emerald-400">✓</span> Reçu numérique et retour utilisateur plus lisibles</li>
                    </ul>
                </div>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
