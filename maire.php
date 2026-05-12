<?php
declare(strict_types=1);
$pageTitle = 'Elimane Sakho Sembène — Maire de Rufisque-Est';
$pageDescription = 'Biographie, vision et priorités d’Elimane Sakho Sembène, Maire de Rufisque-Est : leadership de proximité, jeunesse, éducation, emploi et amélioration du cadre de vie.';
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Présentation officielle
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Elimane Sakho<br><span class="maire-text-gradient">Sembène</span>
                </h1>
                <p class="text-base md:text-lg text-gold-300 font-bold uppercase tracking-widest mb-4">
                    Maire de la commune de Rufisque-Est
                </p>
                <p class="text-xl text-mairie-100 leading-relaxed max-w-2xl">
                    Un leadership de proximité, une vision ambitieuse pour l’avenir de Rufisque-Est : modernité, solidarité, transparence et engagement citoyen.
                </p>
            </div>
        </div>
    </section>

    <!-- PHOTO + BIO -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[1fr_1.6fr] gap-8 items-start">
                <!-- Portrait -->
                <article class="maire-bento-card tw-card overflow-hidden">
                    <div class="relative aspect-[3/4] overflow-hidden">
                        <img src="assets/img/maire-portrait.jpg" alt="Portrait officiel d’Elimane Sakho Sembène, Maire de Rufisque-Est"
                             class="absolute inset-0 w-full h-full object-cover object-top">
                        <div class="absolute inset-0 bg-gradient-to-t from-mairie-950/80 via-transparent to-transparent"></div>
                        <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                            <span class="maire-tag bg-gold-400 text-mairie-950 mb-2">Officiel</span>
                            <h2 class="text-2xl font-black mb-1">Elimane Sakho Sembène</h2>
                            <p class="text-sm text-white/80">Maire de la commune de Rufisque-Est</p>
                        </div>
                    </div>
                </article>

                <!-- Bio -->
                <article class="tw-card p-8 md:p-10">
                    <span class="maire-tag bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200 mb-3">Biographie</span>
                    <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-5 leading-tight">
                        Un engagement <span class="maire-text-gradient">au service de tous</span>
                    </h2>
                    <div class="space-y-4 text-slate-700 dark:text-slate-300 leading-relaxed">
                        <p>
                            <strong class="text-slate-900 dark:text-white">Elimane Sakho Sembène</strong> est l’incarnation d’un leadership de proximité, porté par une vision ambitieuse pour l’avenir de Rufisque-Est. Homme engagé et profondément attaché à sa communauté, il consacre son parcours au service du développement local, du progrès social et du bien-être des populations.
                        </p>
                        <p>
                            Depuis son arrivée à la tête de la commune, il œuvre avec détermination pour bâtir une ville plus moderne, plus propre, plus solidaire et plus dynamique. Sa gouvernance repose sur des valeurs fortes : <strong>l’écoute, l’action, la transparence et l’engagement citoyen</strong>.
                        </p>
                        <p>
                            Reconnu pour son sens des responsabilités et sa présence constante aux côtés des habitants, Elimane Sakho Sembène place <strong>la jeunesse, l’éducation, l’emploi et l’amélioration du cadre de vie</strong> au centre de ses priorités. À travers plusieurs initiatives et projets de développement, il travaille à donner un nouveau visage à Rufisque-Est tout en préservant son identité et ses valeurs.
                        </p>
                        <p>
                            Visionnaire et homme d’action, il croit fermement que chaque commune possède un potentiel capable de transformer la vie de ses habitants lorsqu’elle est dirigée avec passion, discipline et détermination. Son ambition est claire : faire de Rufisque-Est un <strong>modèle de développement local, de stabilité et d’espoir</strong> pour les générations futures.
                        </p>
                        <p>
                            Aujourd’hui, son engagement continue d’inspirer une population qui voit en lui un acteur du changement, un bâtisseur et un défenseur du progrès collectif.
                        </p>
                    </div>
                    <div class="mt-8 grid grid-cols-3 gap-3">
                        <div class="text-center p-4 rounded-2xl bg-gradient-to-br from-mairie-50 to-emerald-50 dark:from-mairie-950/40 dark:to-emerald-950/40 border border-mairie-200/50 dark:border-mairie-800/50">
                            <p class="text-3xl font-black maire-text-gradient"><span class="maire-counter" data-target="15" data-suffix="+">0</span></p>
                            <p class="text-[10px] text-slate-600 dark:text-slate-400 uppercase tracking-wider font-bold mt-1">Ans service public</p>
                        </div>
                        <div class="text-center p-4 rounded-2xl bg-gradient-to-br from-gold-50 to-amber-50 dark:from-gold-950/40 dark:to-amber-950/40 border border-gold-200/50 dark:border-gold-800/50">
                            <p class="text-3xl font-black maire-text-gradient"><span class="maire-counter" data-target="12">0</span></p>
                            <p class="text-[10px] text-slate-600 dark:text-slate-400 uppercase tracking-wider font-bold mt-1">Quartiers</p>
                        </div>
                        <div class="text-center p-4 rounded-2xl bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-teal-950/40 border border-emerald-200/50 dark:border-emerald-800/50">
                            <p class="text-3xl font-black maire-text-gradient"><span class="maire-counter" data-target="6" data-suffix="+">0</span></p>
                            <p class="text-[10px] text-slate-600 dark:text-slate-400 uppercase tracking-wider font-bold mt-1">Projets phares</p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ADMINISTRATION — ÉQUIPE TECHNIQUE & SOCIALE -->
    <section class="py-20 bg-white dark:bg-slate-950 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-gradient-to-bl from-mairie-100/40 dark:from-mairie-900/20 to-transparent rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-[35rem] h-[35rem] bg-gradient-to-tr from-gold-100/30 dark:from-gold-900/15 to-transparent rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <!-- En-tête section -->
            <div class="max-w-3xl mb-12">
                <span class="maire-tag bg-gold-100 text-gold-800 dark:bg-gold-900/40 dark:text-gold-200 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-500"></span>
                    Organisation municipale
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white leading-tight mb-4">
                    Administration<br><span class="maire-text-gradient">Équipe technique & sociale</span>
                </h2>
                <p class="text-lg text-slate-700 dark:text-slate-300 leading-relaxed">
                    Sous l’impulsion de <strong class="text-slate-900 dark:text-white">Elimane Sakho Sembène</strong>, l’administration municipale de Rufisque-Est s’appuie sur une équipe technique et sociale engagée au service des populations. La coordination des différents services municipaux permet d’assurer un fonctionnement efficace de la commune et une meilleure prise en charge des besoins des citoyens.
                </p>
            </div>

            <!-- Grille 4 domaines -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Hygiène et assainissement -->
                <a href="hygiene.php" class="maire-bento-card group relative rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-7 shadow-xl flex flex-col">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/15 rounded-full blur-2xl group-hover:bg-white/25 transition-colors" aria-hidden="true"></div>
                    <div class="relative flex-1">
                        <div class="w-14 h-14 mb-4 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shadow-md">♻️</div>
                        <span class="inline-block text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">Cadre de vie</span>
                        <h3 class="text-xl font-black mb-3 leading-tight">Hygiène & assainissement</h3>
                        <p class="text-sm text-emerald-50 leading-relaxed">
                            Suivi des opérations de nettoyage, gestion de la salubrité publique et amélioration du cadre de vie.
                        </p>
                    </div>
                    <div class="relative mt-5 inline-flex items-center gap-1 text-sm font-black group-hover:gap-2 transition-all">
                        Voir le service
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>

                <!-- Urbanisme et aménagement -->
                <a href="urbanisme.php" class="maire-bento-card group relative rounded-3xl overflow-hidden bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-7 shadow-xl flex flex-col">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/15 rounded-full blur-2xl group-hover:bg-white/25 transition-colors" aria-hidden="true"></div>
                    <div class="relative flex-1">
                        <div class="w-14 h-14 mb-4 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shadow-md">🏗️</div>
                        <span class="inline-block text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">Territoire</span>
                        <h3 class="text-xl font-black mb-3 leading-tight">Urbanisme & aménagement</h3>
                        <p class="text-sm text-blue-50 leading-relaxed">
                            Accompagnement des projets d’aménagement, organisation de l’espace communal et modernisation des infrastructures.
                        </p>
                    </div>
                    <div class="relative mt-5 inline-flex items-center gap-1 text-sm font-black group-hover:gap-2 transition-all">
                        Voir le service
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>

                <!-- Éducation -->
                <a href="education.php" class="maire-bento-card group relative rounded-3xl overflow-hidden bg-gradient-to-br from-amber-500 to-orange-500 text-white p-7 shadow-xl flex flex-col">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/15 rounded-full blur-2xl group-hover:bg-white/25 transition-colors" aria-hidden="true"></div>
                    <div class="relative flex-1">
                        <div class="w-14 h-14 mb-4 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shadow-md">📚</div>
                        <span class="inline-block text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">Jeunesse</span>
                        <h3 class="text-xl font-black mb-3 leading-tight">Éducation</h3>
                        <p class="text-sm text-amber-50 leading-relaxed">
                            Appui aux établissements scolaires, accompagnement des initiatives éducatives et amélioration des conditions d’apprentissage.
                        </p>
                    </div>
                    <div class="relative mt-5 inline-flex items-center gap-1 text-sm font-black group-hover:gap-2 transition-all">
                        Voir le service
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>

                <!-- Action sociale -->
                <a href="action-sociale.php" class="maire-bento-card group relative rounded-3xl overflow-hidden bg-gradient-to-br from-fuchsia-500 to-purple-600 text-white p-7 shadow-xl flex flex-col">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/15 rounded-full blur-2xl group-hover:bg-white/25 transition-colors" aria-hidden="true"></div>
                    <div class="relative flex-1">
                        <div class="w-14 h-14 mb-4 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shadow-md">🤝</div>
                        <span class="inline-block text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">Solidarité</span>
                        <h3 class="text-xl font-black mb-3 leading-tight">Action sociale</h3>
                        <p class="text-sm text-fuchsia-50 leading-relaxed">
                            Soutien aux familles, aux jeunes, aux femmes et aux personnes vulnérables à travers différentes initiatives sociales et communautaires.
                        </p>
                    </div>
                    <div class="relative mt-5 inline-flex items-center gap-1 text-sm font-black group-hover:gap-2 transition-all">
                        Voir le service
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>
            </div>

            <!-- Conclusion -->
            <div class="mt-12 p-8 md:p-10 rounded-3xl bg-gradient-to-br from-mairie-50 to-emerald-50 dark:from-mairie-950/40 dark:to-emerald-950/40 border-2 border-mairie-200/60 dark:border-mairie-800/60">
                <div class="flex items-start gap-4">
                    <span class="hidden sm:inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-2xl shadow-md flex-shrink-0">🏛️</span>
                    <p class="text-base md:text-lg text-slate-700 dark:text-slate-200 leading-relaxed">
                        À travers cette organisation, la municipalité œuvre chaque jour pour une <strong class="text-mairie-800 dark:text-mairie-200">administration de proximité, réactive et tournée vers le développement humain et territorial</strong> de Rufisque-Est.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 3 CARTES PARCOURS / PRIORITÉS / MESSAGE -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-6">
                <article class="maire-bento-card tw-card p-7 relative overflow-hidden">
                    <div class="absolute -top-8 -right-8 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl"></div>
                    <div class="relative">
                        <span class="w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-md">🏛️</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-3">Valeurs de gouvernance</h3>
                        <ul class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <li class="flex items-start gap-2"><span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1">→</span> <strong>Écoute</strong> attentive et constante des habitants.</li>
                            <li class="flex items-start gap-2"><span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1">→</span> <strong>Action</strong> concrète au service du développement local.</li>
                            <li class="flex items-start gap-2"><span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1">→</span> <strong>Transparence</strong> dans la gestion publique.</li>
                            <li class="flex items-start gap-2"><span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1">→</span> <strong>Engagement citoyen</strong> au cœur de chaque décision.</li>
                        </ul>
                    </div>
                </article>

                <article class="maire-bento-card tw-card p-7 relative overflow-hidden">
                    <div class="absolute -top-8 -right-8 w-32 h-32 bg-gold-500/15 rounded-full blur-2xl"></div>
                    <div class="relative">
                        <span class="w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br from-gold-500 to-orange-600 text-white flex items-center justify-center text-2xl shadow-md">🎯</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-3">Priorités d'action</h3>
                        <ul class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <li class="flex items-start gap-2"><span class="text-gold-600 dark:text-gold-400 flex-shrink-0 mt-1">→</span> Accompagner et autonomiser <strong>la jeunesse</strong> de Rufisque-Est.</li>
                            <li class="flex items-start gap-2"><span class="text-gold-600 dark:text-gold-400 flex-shrink-0 mt-1">→</span> Renforcer <strong>l’éducation</strong> et la réussite scolaire.</li>
                            <li class="flex items-start gap-2"><span class="text-gold-600 dark:text-gold-400 flex-shrink-0 mt-1">→</span> Favoriser <strong>l’emploi</strong> et l’insertion économique.</li>
                            <li class="flex items-start gap-2"><span class="text-gold-600 dark:text-gold-400 flex-shrink-0 mt-1">→</span> Améliorer le <strong>cadre de vie</strong> : ville propre, moderne, solidaire.</li>
                        </ul>
                    </div>
                </article>

                <article class="maire-bento-card relative overflow-hidden rounded-3xl bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-7">
                    <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                    <div class="relative">
                        <span class="w-14 h-14 mb-4 rounded-2xl bg-gold-400 text-mairie-950 flex items-center justify-center text-2xl shadow-md">💬</span>
                        <h3 class="text-xl font-black mb-3">Message aux citoyens</h3>
                        <div class="space-y-3 text-sm text-mairie-100 italic relative">
                            <span class="absolute -top-4 -left-1 text-5xl text-gold-400/40 font-serif">"</span>
                            <p class="relative">Chaque commune possède un potentiel capable de transformer la vie de ses habitants lorsqu’elle est dirigée avec passion, discipline et détermination.</p>
                            <p class="relative">Mon ambition est claire : faire de Rufisque-Est un modèle de développement local, de stabilité et d’espoir pour les générations futures.</p>
                            <p class="relative not-italic text-right text-gold-300 font-bold text-xs mt-2">— Elimane Sakho Sembène</p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-12 flex flex-wrap gap-3 justify-center">
                <a class="tw-btn-primary" href="projets.php">Voir les projets prioritaires</a>
                <a class="tw-btn-outline" href="contact.php">Contacter le cabinet</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
