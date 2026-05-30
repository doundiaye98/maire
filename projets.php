<?php
declare(strict_types=1);
$pageTitle = 'Projets municipaux | Rufisque-Est';
$pageDescription = 'Découvrez les projets réalisés et en cours par la Mairie de Rufisque-Est : infrastructure, éducation, éclairage, numérique, assainissement.';
require __DIR__ . '/includes/header.php';

$projetsRealises = [
    [
        'categorie' => 'Infrastructure',
        'titre' => 'Réhabilitation voirie de Keury Souf',
        'description' => 'Réfection de tronçons dégradés, ajout de signalisation et aménagement de trottoirs pour fluidifier la circulation.',
        'image' => maire_placeholder_image('voirie'),
        'avancement' => 100,
        'gradient' => 'from-blue-500 to-indigo-600',
    ],
    [
        'categorie' => 'Éducation',
        'titre' => 'Équipement de 5 écoles publiques',
        'description' => 'Dotation en tables-bancs, kits pédagogiques et réhabilitation légère de salles de classe dans plusieurs quartiers.',
        'image' => maire_placeholder_image('education'),
        'avancement' => 100,
        'gradient' => 'from-amber-500 to-orange-500',
    ],
    [
        'categorie' => 'Éclairage public',
        'titre' => 'Installation de lampadaires solaires',
        'description' => 'Mise en place de nouveaux points lumineux sur les axes prioritaires pour renforcer la sécurité nocturne.',
        'image' => maire_placeholder_image('eclairage'),
        'avancement' => 100,
        'gradient' => 'from-yellow-500 to-amber-600',
    ],
];

$projetsEnCours = [
    [
        'categorie' => 'Numérique',
        'titre' => 'Digitalisation des démarches citoyennes',
        'description' => "Développement des services en ligne pour l'état civil et le suivi des demandes sans déplacement.",
        'image' => maire_placeholder_image('numerique'),
        'avancement' => 72,
        'gradient' => 'from-mairie-700 to-mairie-900',
    ],
    [
        'categorie' => 'Assainissement',
        'titre' => 'Extension du réseau de drainage',
        'description' => 'Création de nouvelles canalisations pour limiter les inondations en période hivernale.',
        'image' => maire_placeholder_image('assainissement'),
        'avancement' => 61,
        'gradient' => 'from-cyan-500 to-blue-600',
    ],
    [
        'categorie' => 'Cadre de vie',
        'titre' => "Aménagement d'espaces verts de proximité",
        'description' => 'Transformation de zones sous-utilisées en espaces de détente et de loisirs pour les familles.',
        'image' => maire_placeholder_image('cadre-de-vie'),
        'avancement' => 48,
        'gradient' => 'from-emerald-500 to-teal-600',
    ],
];
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-24 lg:py-28 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid gap-8 lg:grid-cols-[1.05fr_0.95fr] lg:items-end">
                <div class="max-w-3xl">
                    <span class="maire-section-kicker mb-5 !bg-white/12 !text-white !border-white/20">Développement territorial</span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.92] tracking-tight mb-5">
                        Les projets<br><span class="text-gold-200">qui transforment</span>
                    </h1>
                    <p class="text-xl md:text-2xl text-mairie-100 leading-relaxed max-w-3xl">
                        Une lecture plus transparente des opérations déjà livrées et des chantiers en cours, pour rendre l’action municipale visible et compréhensible.
                    </p>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white"><span class="maire-counter" data-target="<?php echo count($projetsRealises); ?>">0</span></p>
                        <p class="maire-kpi-card__label !text-white/90">Livrés</p>
                    </article>
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white"><span class="maire-counter" data-target="<?php echo count($projetsEnCours); ?>">0</span></p>
                        <p class="maire-kpi-card__label !text-white/90">En cours</p>
                    </article>
                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                        <p class="maire-kpi-card__value !text-white"><span class="maire-counter" data-target="12">0</span></p>
                        <p class="maire-kpi-card__label !text-white/90">Quartiers</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="maire-editorial-card mb-10">
                <div class="grid gap-6 md:grid-cols-[1.1fr_0.9fr] md:items-center">
                    <div>
                        <span class="maire-section-kicker mb-4">Lecture publique</span>
                        <h2 class="text-3xl md:text-4xl font-black text-slate-950 dark:text-white mb-3">Distinguer ce qui est livré, ce qui avance et ce qui structure le territoire.</h2>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">La page met désormais en scène l’effort communal comme une matière lisible, avec un langage commun entre réalisations, progression et impact local.</p>
                    </div>
                    <div class="maire-surface--dark p-6">
                        <p class="text-[0.72rem] uppercase tracking-[0.22em] text-gold-300 font-black mb-3">Vision</p>
                        <p class="text-sm text-slate-200 leading-relaxed">Voirie, écoles, éclairage, numérique et cadre de vie partagent ici une même promesse: rendre visible l’investissement municipal et son bénéfice concret pour les habitants.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 mb-8">
                <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-2xl shadow-md">✅</span>
                <div>
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Projets réalisés</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Livrés et opérationnels</p>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($projetsRealises as $projet): ?>
                    <article class="maire-editorial-card overflow-hidden group !p-0">
                        <div class="relative aspect-[16/10] overflow-hidden">
                            <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" src="<?php echo htmlspecialchars($projet['image']); ?>" alt="<?php echo htmlspecialchars($projet['titre']); ?>">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                            <span class="absolute top-3 left-3 maire-tag bg-emerald-500 text-white">✓ Terminé</span>
                            <span class="absolute top-3 right-3 maire-tag bg-white/90 backdrop-blur-md text-slate-800"><?php echo htmlspecialchars($projet['categorie']); ?></span>
                        </div>
                        <div class="p-5">
                            <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2 leading-tight"><?php echo htmlspecialchars($projet['titre']); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4 line-clamp-3"><?php echo htmlspecialchars($projet['description']); ?></p>
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-2 mr-3 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full" style="width: <?php echo (int) $projet['avancement']; ?>%"></div>
                                </div>
                                <strong class="text-emerald-600 dark:text-emerald-400"><?php echo (int) $projet['avancement']; ?>%</strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex items-center gap-3 mb-8">
                <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center text-2xl shadow-md">🚧</span>
                <div>
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Projets en cours</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">En phase de réalisation</p>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($projetsEnCours as $projet): ?>
                    <article class="maire-editorial-card overflow-hidden group !p-0">
                        <div class="relative aspect-[16/10] overflow-hidden">
                            <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" src="<?php echo htmlspecialchars($projet['image']); ?>" alt="<?php echo htmlspecialchars($projet['titre']); ?>">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                            <span class="absolute top-3 left-3 maire-tag bg-amber-500 text-white">⏳ En cours</span>
                            <span class="absolute top-3 right-3 maire-tag bg-white/90 backdrop-blur-md text-slate-800"><?php echo htmlspecialchars($projet['categorie']); ?></span>
                        </div>
                        <div class="p-5">
                            <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2 leading-tight"><?php echo htmlspecialchars($projet['titre']); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4 line-clamp-3"><?php echo htmlspecialchars($projet['description']); ?></p>
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-2 mr-3 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-500 to-orange-500 rounded-full transition-all" style="width: <?php echo (int) $projet['avancement']; ?>%"></div>
                                </div>
                                <strong class="text-amber-600 dark:text-amber-400"><?php echo (int) $projet['avancement']; ?>%</strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="mt-14 relative rounded-[2rem] overflow-hidden bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-8 md:p-10 shadow-luxury">
                <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                <div class="relative grid md:grid-cols-[2fr_1fr] items-center gap-6">
                    <div>
                        <h3 class="text-2xl md:text-3xl font-black mb-2">Une idée pour votre quartier&nbsp;?</h3>
                        <p class="text-mairie-100">Proposez un projet citoyen à la mairie. Les propositions retenues sont étudiées par le conseil municipal.</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="contact.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 font-black transition-colors">
                            Proposer un projet
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
