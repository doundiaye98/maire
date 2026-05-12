<?php
declare(strict_types=1);
require __DIR__ . '/includes/header.php';

$services = [
    ['icone' => '🪪', 'nom' => 'État civil', 'description' => 'Naissance, mariage, décès et légalisation de documents.', 'tag' => 'Administration', 'lien' => 'etat-civil.php', 'gradient' => 'from-emerald-500 to-teal-600'],
    ['icone' => '🏥', 'nom' => 'Santé', 'description' => 'Accès aux soins de proximité, prévention et campagnes sanitaires.', 'tag' => 'Santé publique', 'lien' => 'sante.php', 'gradient' => 'from-rose-500 to-pink-600'],
    ['icone' => '📚', 'nom' => 'Éducation', 'description' => 'Appui aux écoles, équipements pédagogiques et accompagnement scolaire.', 'tag' => 'Jeunesse', 'lien' => 'education.php', 'gradient' => 'from-amber-500 to-orange-500'],
    ['icone' => '🏗️', 'nom' => 'Urbanisme', 'description' => 'Permis de construire, lotissements et travaux.', 'tag' => 'Territoire', 'lien' => 'urbanisme.php', 'gradient' => 'from-blue-500 to-indigo-600'],
    ['icone' => '♻️', 'nom' => 'Hygiène', 'description' => 'Collecte et traitement des déchets.', 'tag' => 'Environnement', 'lien' => 'hygiene.php', 'gradient' => 'from-green-500 to-emerald-600'],
    ['icone' => '🤝', 'nom' => 'Action sociale', 'description' => 'Accompagnement des familles vulnérables.', 'tag' => 'Solidarité', 'lien' => 'action-sociale.php', 'gradient' => 'from-fuchsia-500 to-purple-600'],
    ['icone' => '🎭', 'nom' => 'Vie culturelle', 'description' => 'Promotion des arts, événements communautaires et valorisation du patrimoine local.', 'tag' => 'Culture', 'lien' => 'vie-culturelle.php', 'gradient' => 'from-violet-500 to-fuchsia-600'],
];
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 lg:py-32 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/20 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Administration locale
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Services<br><span class="maire-text-gradient">administratifs</span>
                </h1>
                <p class="text-xl text-mairie-100 leading-relaxed max-w-2xl">
                    Des démarches plus simples, plus rapides et plus proches des citoyens.
                </p>
            </div>

            <!-- KPI floutés sous le titre -->
            <div class="grid grid-cols-3 gap-3 mt-12 max-w-2xl">
                <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15">
                    <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo count($services); ?>">0</span></p>
                    <p class="text-xs text-mairie-200 uppercase tracking-wider font-bold mt-1">Services dispo.</p>
                </div>
                <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15">
                    <p class="text-3xl font-black"><span class="maire-counter" data-target="24" data-suffix="h">0</span></p>
                    <p class="text-xs text-mairie-200 uppercase tracking-wider font-bold mt-1">Délai moyen</p>
                </div>
                <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15">
                    <p class="text-3xl font-black"><span class="maire-counter" data-target="100" data-suffix="%">0</span></p>
                    <p class="text-xs text-mairie-200 uppercase tracking-wider font-bold mt-1">Accompagnement</p>
                </div>
            </div>
        </div>
    </section>

    <!-- GRILLE SERVICES BENTO -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900 relative">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="maire-tag bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200 mb-3">Catalogue</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Tous les services en un seul lieu</h2>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($services as $i => $service): ?>
                <a href="<?php echo htmlspecialchars($service['lien'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="maire-bento-card relative group rounded-3xl overflow-hidden bg-gradient-to-br <?php echo $service['gradient']; ?> text-white p-7 shadow-xl animate-fade-up min-h-[220px] flex flex-col justify-between"
                   style="animation-delay: <?php echo ($i * 0.05); ?>s;">
                    <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:bg-white/25 transition-colors" aria-hidden="true"></div>
                    <div class="relative">
                        <div class="text-5xl mb-3 filter drop-shadow-lg"><?php echo $service['icone']; ?></div>
                        <span class="inline-block text-xs font-black uppercase tracking-wider opacity-80 mb-2"><?php echo htmlspecialchars($service['tag'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <h3 class="text-2xl font-black mb-2"><?php echo htmlspecialchars($service['nom'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-sm text-white/90 leading-snug mb-4"><?php echo htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="relative inline-flex items-center gap-1 text-sm font-black group-hover:gap-2 transition-all">
                        Démarrer la demande
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- PARCOURS CITOYEN (timeline) -->
    <section class="py-20 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="maire-tag bg-gold-50 text-gold-700 dark:bg-gold-900/30 dark:text-gold-300 mb-3">Comment ça marche</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Parcours citoyen <span class="maire-text-gradient">simplifié</span></h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6 relative">
                <!-- ligne de connexion entre les 3 étapes -->
                <div class="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-gradient-to-r from-mairie-300 via-gold-400 to-mairie-300" aria-hidden="true"></div>

                <article class="tw-card p-7 text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-4xl font-black shadow-glow">1</div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Choisissez un service</h3>
                    <p class="text-slate-600 dark:text-slate-400">Accédez au service adapté à votre besoin administratif.</p>
                </article>
                <article class="tw-card p-7 text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-gold-500 to-orange-600 text-white items-center justify-center text-4xl font-black shadow-lg">2</div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Soumettez votre demande</h3>
                    <p class="text-slate-600 dark:text-slate-400">Remplissez les informations utiles en ligne ou en guichet.</p>
                </article>
                <article class="tw-card p-7 text-center relative">
                    <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white items-center justify-center text-4xl font-black shadow-lg">3</div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Recevez le suivi</h3>
                    <p class="text-slate-600 dark:text-slate-400">Suivez l'avancement de votre dossier jusqu'à la finalisation.</p>
                </article>
            </div>

            <div class="text-center mt-12">
                <a href="digitalisation-etat-civil.php" class="tw-btn-primary text-base">
                    Commencer maintenant
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
