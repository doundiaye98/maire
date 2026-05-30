<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-data.php';

$services = maire_public_services_catalogue();
$serviceVedette = $services[0] ?? null;
$catalogueSecondaire = array_slice($services, 1);

$pageTitle = 'Tous les services | Mairie de Rufisque-Est';
$pageDescription = 'Explorez les services publics de la Mairie de Rufisque-Est : démarches administratives, services techniques, santé, hygiène, culture et action sociale.';
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-24 lg:py-32 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/20 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/25 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-[1.05fr_0.95fr] gap-8 items-end">
                <div class="max-w-3xl">
                    <span class="maire-section-kicker mb-5 !bg-white/12 !text-white !border-white/20">Portail de services</span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.92] tracking-tight mb-5" style="text-shadow:0 10px 32px rgba(0,0,0,0.24);">
                        Une seule entrée.<br><span class="text-gold-200">Toute la mairie.</span>
                    </h1>
                    <p class="text-xl md:text-2xl text-white/90 leading-relaxed max-w-3xl">
                        Découvrez les services publics disponibles, les parcours administratifs structurants et les accès recommandés pour vos démarches du quotidien.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="division-services-techniques.php" class="tw-btn-primary">Accéder au service technique</a>
                        <a href="digitalisation-etat-civil.php" class="tw-btn-outline !bg-white/10 !text-white !border-white/20 hover:!bg-white/20">Démarrer une démarche</a>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white"><span class="maire-counter" data-target="<?php echo count($services); ?>">0</span></p>
                        <p class="maire-kpi-card__label !text-white/90">Services</p>
                    </article>
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white">Guide</p>
                        <p class="maire-kpi-card__label !text-white/90">Orientation</p>
                    </article>
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white">Portail</p>
                        <p class="maire-kpi-card__label !text-white/90">Accès communal</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <?php if ($serviceVedette !== null): ?>
                <a href="<?php echo htmlspecialchars($serviceVedette['lien'], ENT_QUOTES, 'UTF-8'); ?>" class="maire-editorial-card block mb-10 group">
                    <div class="grid lg:grid-cols-[0.95fr_1.05fr] gap-8 items-center">
                        <div>
                            <span class="maire-section-kicker mb-4"><?php echo htmlspecialchars((string) ($serviceVedette['highlight'] ?? 'A la une'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <h2 class="text-3xl md:text-5xl font-black text-slate-950 dark:text-white mb-4 leading-[0.95]"><?php echo htmlspecialchars($serviceVedette['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="text-lg text-slate-700 dark:text-slate-300 leading-relaxed mb-6"><?php echo htmlspecialchars($serviceVedette['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="maire-link-arrow">Explorer ce service
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </div>
                        </div>
                        <div class="maire-surface--dark p-7">
                            <div class="flex items-start justify-between gap-4 mb-5">
                                <span class="inline-flex w-16 h-16 rounded-3xl bg-gradient-to-br <?php echo $serviceVedette['gradient']; ?> text-white items-center justify-center text-3xl shadow-panel"><?php echo $serviceVedette['icone']; ?></span>
                                <span class="maire-tag bg-white/10 border border-white/10 text-white"><?php echo htmlspecialchars($serviceVedette['tag'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p class="text-sm uppercase tracking-[0.2em] text-cyan-100/80 font-black mb-3">Accès prioritaire</p>
                            <ul class="space-y-3 text-sm text-slate-200">
                                <li class="flex items-start gap-2"><span class="text-gold-300">•</span><span>Voirie, maintenance et équipements publics regroupés dans une même porte d’entrée.</span></li>
                                <li class="flex items-start gap-2"><span class="text-gold-300">•</span><span>Lecture simplifiée des missions et demandes les plus fréquentes.</span></li>
                                <li class="flex items-start gap-2"><span class="text-gold-300">•</span><span>Orientation rapide vers le bon canal : signalement ou contact mairie.</span></li>
                            </ul>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <div class="flex items-end justify-between gap-4 flex-wrap mb-8">
                <div class="max-w-2xl">
                    <span class="maire-section-kicker mb-3">Catalogue complet</span>
                    <h2 class="text-3xl md:text-4xl font-black text-slate-950 dark:text-white">Tous les services, classés pour aller plus vite.</h2>
                </div>
                <a href="contact.php" class="maire-pill-link">Besoin d’orientation ?</a>
            </div>

            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($catalogueSecondaire as $i => $service): ?>
                    <a href="<?php echo htmlspecialchars($service['lien'], ENT_QUOTES, 'UTF-8'); ?>"
                       class="maire-editorial-card maire-bento-card flex flex-col justify-between min-h-[250px] animate-fade-up"
                       style="animation-delay: <?php echo ($i * 0.05); ?>s;">
                        <div>
                            <div class="flex items-start justify-between gap-4 mb-5">
                                <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br <?php echo $service['gradient']; ?> text-white items-center justify-center text-2xl shadow-panel"><?php echo $service['icone']; ?></span>
                                <span class="maire-tag bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($service['tag'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-950 dark:text-white mb-3"><?php echo htmlspecialchars($service['nom'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?php echo htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="maire-link-arrow mt-6">Découvrir
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-20 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="maire-section-kicker mb-4">Parcours citoyen</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-950 dark:text-white">Un parcours clair, pensé pour aller de l’intention à l’action.</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6 relative">
                <div class="hidden md:block absolute top-12 left-[16%] right-[16%] h-px bg-gradient-to-r from-mairie-200 via-gold-400 to-mairie-200 dark:from-mairie-800 dark:via-gold-400 dark:to-mairie-800" aria-hidden="true"></div>

                <article class="maire-panel text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-3xl bg-gradient-to-br from-mairie-800 to-mairie-950 text-white items-center justify-center text-4xl font-black shadow-luxury">1</div>
                    <h3 class="text-xl font-black text-slate-950 dark:text-white mb-2">Choisissez le bon service</h3>
                    <p class="text-slate-600 dark:text-slate-400">Accédez à une fiche claire, avec missions, canaux d’accès et points d’entrée prioritaires.</p>
                </article>
                <article class="maire-panel text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-3xl bg-gradient-to-br from-gold-500 to-orange-600 text-white items-center justify-center text-4xl font-black shadow-luxury">2</div>
                    <h3 class="text-xl font-black text-slate-950 dark:text-white mb-2">Soumettez votre besoin</h3>
                    <p class="text-slate-600 dark:text-slate-400">Démarche en ligne, prise de contact ou signalement selon la nature de la demande.</p>
                </article>
                <article class="maire-panel text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-3xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white items-center justify-center text-4xl font-black shadow-luxury">3</div>
                    <h3 class="text-xl font-black text-slate-950 dark:text-white mb-2">Recevez la suite</h3>
                    <p class="text-slate-600 dark:text-slate-400">Vous êtes orienté vers le bon interlocuteur, le bon document ou le bon suivi.</p>
                </article>
            </div>

            <div class="mt-14 maire-surface--dark p-8 md:p-10">
                <div class="grid md:grid-cols-[1.35fr_0.65fr] gap-6 items-center">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-gold-300 font-black mb-3">Accès rapide</p>
                        <h3 class="text-2xl md:text-3xl font-black mb-3">Vous cherchez d’abord une démarche populaire ?</h3>
                        <p class="text-slate-200 max-w-2xl">L’état civil en ligne, les paiements communaux et les signalements restent les parcours les plus consultés. Nous les gardons visibles, immédiats et lisibles.</p>
                    </div>
                    <div class="flex flex-wrap gap-3 md:justify-end">
                        <a href="digitalisation-etat-civil.php" class="tw-btn-primary">État civil</a>
                        <a href="paiements.php" class="tw-btn-outline !bg-white/10 !text-white !border-white/15 hover:!bg-white/20">Payer en ligne</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
