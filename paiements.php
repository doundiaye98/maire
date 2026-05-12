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

$pageTitle = 'Paiement en ligne — Services communaux';
$pageDescription = 'Réglez vos taxes locales, documents express et réservations municipales en ligne via Orange Money ou Wave.';
require __DIR__ . '/includes/header.php';

$renderCarte = function (string $code, array $s, bool $disponible, string $variant = 'taxe', string $libelleBouton = 'Payer en ligne') {
    $gradients = [
        'taxe' => 'from-amber-500 to-orange-600',
        'document_express' => 'from-blue-500 to-indigo-600',
        'reservation' => 'from-violet-500 to-fuchsia-600',
    ];
    $icons = [
        'taxe' => '🧾',
        'document_express' => '📄',
        'reservation' => '🏛️',
    ];
    $g = $gradients[$variant] ?? 'from-mairie-700 to-mairie-900';
    $i = $icons[$variant] ?? '💰';
    ?>
    <article class="maire-bento-card tw-card overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br <?php echo $g; ?> opacity-10 rounded-bl-[100%] pointer-events-none" aria-hidden="true"></div>
        <div class="p-6">
            <div class="flex items-start justify-between mb-3">
                <span class="w-12 h-12 rounded-2xl bg-gradient-to-br <?php echo $g; ?> text-white flex items-center justify-center text-2xl shadow-md"><?php echo $i; ?></span>
                <?php if (!$disponible): ?>
                    <span class="maire-tag bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">🔒 Premium</span>
                <?php endif; ?>
            </div>
            <h3 class="text-lg font-black text-slate-900 dark:text-white mb-1"><?php echo htmlspecialchars($s['libelle'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-3 line-clamp-2"><?php echo htmlspecialchars($s['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($s['delai'])): ?>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1"><span>⏱</span> Délai : <strong><?php echo htmlspecialchars($s['delai'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php endif; ?>
            <div class="flex items-end justify-between gap-3 mt-4">
                <p class="text-2xl font-black maire-text-gradient"><?php echo maire_paiement_format_montant((float) $s['prix']); ?></p>
                <?php if ($disponible): ?>
                    <a class="tw-btn-primary text-sm" href="payer.php?service=<?php echo urlencode($code); ?>">
                        <?php echo htmlspecialchars($libelleBouton, ENT_QUOTES, 'UTF-8'); ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                <?php else: ?>
                    <button type="button" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-sm cursor-not-allowed" disabled>Indisponible</button>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-wrap items-end justify-between gap-8">
                <div class="max-w-2xl">
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Services en ligne · Mobile money
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                        Paiements<br><span class="maire-text-gradient">communaux</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed max-w-2xl">
                        Réglez en quelques secondes vos taxes, vos demandes de documents express et vos réservations d'équipements municipaux. Compatible Orange Money et Wave.
                    </p>
                </div>
                <!-- Mockup mobile money -->
                <div class="hidden lg:flex items-center gap-3 px-5 py-4 rounded-3xl bg-white/10 backdrop-blur-md border border-white/20">
                    <div class="flex flex-col gap-1.5">
                        <div class="px-3 py-1.5 rounded-lg bg-orange-500 text-white font-black text-xs">Orange Money</div>
                        <div class="px-3 py-1.5 rounded-lg bg-blue-500 text-white font-black text-xs">Wave</div>
                    </div>
                    <div class="w-px h-12 bg-white/20"></div>
                    <div>
                        <p class="text-2xl font-black"><span class="maire-counter" data-target="<?php echo $totalServices; ?>">0</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold">Services dispo.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-14">

            <?php if (!$paiementsActifs): ?>
                <div class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/30 border-2 border-amber-400 p-7">
                    <div class="absolute -top-8 -right-8 w-40 h-40 bg-amber-300/40 rounded-full blur-2xl pointer-events-none" aria-hidden="true"></div>
                    <div class="relative flex items-start gap-4">
                        <span class="text-4xl flex-shrink-0">🔒</span>
                        <div>
                            <h2 class="text-xl font-black text-amber-900 dark:text-amber-200 mb-1">Paiements indisponibles pour cette commune</h2>
                            <p class="text-amber-800 dark:text-amber-300">Le paiement en ligne nécessite la formule <strong>Standard</strong> ou supérieure. Contactez la mairie pour plus d'informations.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAXES LOCALES -->
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center text-2xl shadow-md">📜</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Taxes locales</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Payez vos taxes municipales en ligne <?php if (!$taxesActives): ?><span class="ml-1 text-amber-600 font-bold">🔒 Premium</span><?php endif; ?></p>
                    </div>
                </div>
                <?php if (empty($parCategorie['taxe'])): ?>
                    <div class="tw-card p-8 text-center text-slate-500 dark:text-slate-400">Aucune taxe en ligne configurée pour l'instant.</div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($parCategorie['taxe'] as $code => $s): ?>
                            <?php $renderCarte($code, $s, $paiementsActifs && $taxesActives, 'taxe', 'Payer'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DOCUMENTS EXPRESS -->
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-md">📄</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Documents express</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Recevez vos documents officiels en 24 à 72h.</p>
                    </div>
                </div>
                <?php if (empty($parCategorie['document_express'])): ?>
                    <div class="tw-card p-8 text-center text-slate-500 dark:text-slate-400">Aucun document express configuré.</div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($parCategorie['document_express'] as $code => $s): ?>
                            <?php $renderCarte($code, $s, $paiementsActifs, 'document_express', 'Payer'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RÉSERVATIONS -->
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center text-2xl shadow-md">🏛️</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Réservations d'équipements</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Salles, places de marché, équipements municipaux.</p>
                    </div>
                </div>
                <?php if (empty($parCategorie['reservation'])): ?>
                    <div class="tw-card p-8 text-center text-slate-500 dark:text-slate-400">Aucune réservation en ligne configurée.</div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($parCategorie['reservation'] as $code => $s): ?>
                            <?php $renderCarte($code, $s, $paiementsActifs, 'reservation', 'Réserver'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- BLOC SÉCURITÉ -->
            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-slate-900 to-mairie-950 text-white p-8 md:p-10">
                <div class="absolute -bottom-12 -right-12 w-60 h-60 bg-emerald-500/30 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                <div class="relative max-w-3xl">
                    <span class="maire-tag bg-emerald-500/20 backdrop-blur-sm border border-emerald-400/30 text-emerald-300 mb-3">
                        <span>🔐</span> Sécurité et confidentialité
                    </span>
                    <h2 class="text-2xl md:text-3xl font-black mb-5">Vos paiements sont 100% sécurisés</h2>
                    <ul class="space-y-3 text-mairie-100">
                        <li class="flex items-start gap-3"><span class="text-emerald-400 flex-shrink-0 mt-1">✓</span> Transactions chiffrées et journalisées avec référence unique <code class="px-2 py-0.5 rounded bg-white/10 text-gold-300 font-mono text-xs">PAY-...</code></li>
                        <li class="flex items-start gap-3"><span class="text-emerald-400 flex-shrink-0 mt-1">✓</span> Compatible <strong>Orange Money</strong> et <strong>Wave</strong> (mobile money)</li>
                        <li class="flex items-start gap-3"><span class="text-emerald-400 flex-shrink-0 mt-1">✓</span> Aucune donnée bancaire conservée par la mairie</li>
                        <li class="flex items-start gap-3"><span class="text-emerald-400 flex-shrink-0 mt-1">✓</span> Reçu numérique consultable immédiatement</li>
                    </ul>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
