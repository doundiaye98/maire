<?php
declare(strict_types=1);

$pageTitle = 'Univers Diasporas — Éditeur du site | Mairie de Rufisque-Est';
$pageDescription = 'Univers Diasporas — Éditeur de la plateforme numérique de la Mairie de Rufisque-Est. Solutions digitales sur-mesure pour les administrations africaines : sites institutionnels, gestion citoyenne, paiements mobiles, plateformes SaaS multi-communes.';

require __DIR__ . '/includes/header.php';
?>

<main class="overflow-hidden">

    <!-- ═══════════════════════════════════════════════════════════════════
         HERO — INTRODUCTION ÉDITEUR
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="relative bg-gradient-to-br from-mairie-950 via-slate-950 to-mairie-950 text-white py-24 lg:py-32 overflow-hidden">
        <!-- Halos décoratifs -->
        <div class="absolute -top-40 -right-40 w-[40rem] h-[40rem] bg-gold-500/15 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-40 -left-40 w-[40rem] h-[40rem] bg-mairie-500/15 maire-blob blur-3xl pointer-events-none" style="animation-delay: -8s;" aria-hidden="true"></div>

        <!-- Pattern de fond subtil -->
        <div class="absolute inset-0 maire-grain opacity-30 pointer-events-none" aria-hidden="true"></div>

        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Colonne gauche : texte -->
                <div>
                    <span class="inline-flex items-center gap-2 text-xs font-black tracking-[0.25em] uppercase text-gold-300 mb-6 px-4 py-1.5 rounded-full bg-gold-500/15 border border-gold-400/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Éditeur officiel
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-[1.05] mb-6">
                        <span class="block text-mairie-200 text-2xl md:text-3xl font-medium mb-3 tracking-wide">Une réalisation</span>
                        <span class="bg-gradient-to-r from-gold-300 via-gold-200 to-gold-400 bg-clip-text text-transparent">
                            Univers&nbsp;Diasporas
                        </span>
                    </h1>
                    <p class="text-lg lg:text-xl text-mairie-100 leading-relaxed mb-8 max-w-xl">
                        Studio digital spécialisé dans la <strong class="text-white">transformation numérique des administrations africaines</strong> :
                        sites institutionnels, gestion citoyenne, paiements mobiles, et plateformes SaaS multi-communes.
                    </p>

                    <!-- Chiffres clés -->
                    <div class="grid grid-cols-3 gap-4 mb-10 max-w-md">
                        <div>
                            <p class="text-3xl font-black bg-gradient-to-br from-gold-300 to-gold-500 bg-clip-text text-transparent">100%</p>
                            <p class="text-xs text-mairie-300 uppercase tracking-wider font-bold mt-1">Made in Senegal</p>
                        </div>
                        <div>
                            <p class="text-3xl font-black bg-gradient-to-br from-gold-300 to-gold-500 bg-clip-text text-transparent">24/7</p>
                            <p class="text-xs text-mairie-300 uppercase tracking-wider font-bold mt-1">Support</p>
                        </div>
                        <div>
                            <p class="text-3xl font-black bg-gradient-to-br from-gold-300 to-gold-500 bg-clip-text text-transparent">PME</p>
                            <p class="text-xs text-mairie-300 uppercase tracking-wider font-bold mt-1">à institutions</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="contact.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gold-500 hover:bg-gold-400 text-mairie-950 font-bold shadow-lg hover:shadow-glow transition-all hover:scale-105">
                            Démarrer un projet
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="#expertise" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 text-white font-bold transition">
                            Notre expertise
                        </a>
                    </div>
                </div>

                <!-- Colonne droite : carte signature visuelle -->
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-gold-500/20 via-mairie-500/20 to-transparent rounded-[2.5rem] blur-2xl"></div>
                    <div class="relative rounded-[2.5rem] overflow-hidden bg-gradient-to-br from-mairie-900 via-mairie-950 to-slate-950 border border-gold-500/30 p-10 lg:p-12 backdrop-blur-sm">
                        <!-- Logo central -->
                        <div class="flex flex-col items-center text-center">
                            <div class="relative mb-6">
                                <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-gold-400 to-amber-600 blur-xl opacity-50"></div>
                                <div class="relative w-28 h-28 lg:w-32 lg:h-32 rounded-3xl bg-gradient-to-br from-gold-300 via-gold-500 to-amber-600 shadow-2xl ring-2 ring-gold-300/50 flex items-center justify-center">
                                    <svg class="w-14 h-14 lg:w-16 lg:h-16 text-mairie-950" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2L2 7v10c0 5.55 3.84 9.95 9 11 5.16-1.05 9-5.45 9-11V7l-10-5zm0 2.18L19 7.5v3.92c0 .54-.04 1.07-.11 1.6L12 9.5 5.11 13.02C5.04 12.49 5 11.96 5 11.42V7.5L12 4.18zM12 12l5.85 2.99c-.61 2.05-1.91 3.82-3.69 5L12 21l-2.16-1.01c-1.78-1.18-3.08-2.95-3.69-5L12 12z"/>
                                    </svg>
                                </div>
                            </div>
                            <h2 class="text-3xl lg:text-4xl font-black bg-gradient-to-r from-gold-200 via-white to-gold-300 bg-clip-text text-transparent mb-2">
                                Univers Diasporas
                            </h2>
                            <p class="text-gold-300 font-bold uppercase tracking-[0.3em] text-xs mb-6">Digital Studio</p>
                            <div class="w-16 h-px bg-gradient-to-r from-transparent via-gold-400 to-transparent mb-6"></div>
                            <p class="italic text-mairie-200 text-sm leading-relaxed max-w-xs">
                                « Connecter les institutions africaines à leurs citoyens à travers la technologie. »
                            </p>
                        </div>

                        <!-- Coin or décoratif -->
                        <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gold-500/15 border border-gold-400/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                            <span class="text-[9px] uppercase tracking-widest font-black text-gold-300">Premium</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════
         EXPERTISE — DOMAINES D'INTERVENTION
         ═══════════════════════════════════════════════════════════════════ -->
    <section id="expertise" class="py-24 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 max-w-3xl mx-auto">
                <span class="inline-block text-xs font-black tracking-widest uppercase text-mairie-700 dark:text-mairie-400 mb-4 px-4 py-1.5 rounded-full bg-mairie-50 dark:bg-mairie-900/40">
                    Notre savoir-faire
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white">
                    Une expertise complète
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-400 mt-4">
                    De la conception au déploiement, nous accompagnons les institutions à chaque étape.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Carte 1 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-gold-400 to-amber-600 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-mairie-950" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Sites institutionnels</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Plateformes officielles pour mairies, ministères, ambassades. Design moderne, SEO optimisé, accessibilité conforme WCAG.
                    </p>
                </article>

                <!-- Carte 2 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-mairie-500 to-mairie-700 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Paiements mobiles</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Intégration native Wave, Orange Money, Free Money. Webhooks sécurisés, réconciliation automatique, conformité Banque Centrale.
                    </p>
                </article>

                <!-- Carte 3 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Gestion citoyenne</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Comptes habitants, signalements géolocalisés, notifications, demandes en ligne. L'administration au plus proche des usagers.
                    </p>
                </article>

                <!-- Carte 4 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-700 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Sécurité &amp; conformité</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Audit OWASP, RGPD, CDP Sénégal. CSP, anti-CSRF, rate limiting, chiffrement, logs structurés et traçabilité complète.
                    </p>
                </article>

                <!-- Carte 5 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">SaaS multi-tenant</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Plateforme mutualisée pour groupements de communes. Isolation des données, facturation par tenant, console super-admin.
                    </p>
                </article>

                <!-- Carte 6 -->
                <article class="group p-7 rounded-3xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 hover:border-gold-400 dark:hover:border-gold-500 hover:shadow-card-hover transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-red-700 flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Performance &amp; PWA</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Service workers, mode hors-ligne, score Lighthouse 95+, adaptation aux connexions 3G/4G d'Afrique de l'Ouest.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════
         RÉFÉRENCES — LE SITE ACTUEL EN EXEMPLE
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="py-24 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 max-w-3xl mx-auto">
                <span class="inline-block text-xs font-black tracking-widest uppercase text-gold-600 dark:text-gold-400 mb-4 px-4 py-1.5 rounded-full bg-gold-50 dark:bg-gold-900/30">
                    Cas client
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white">
                    Vous parcourez actuellement <br>
                    <span class="maire-text-gradient">une de nos réalisations</span>
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-400 mt-4">
                    La plateforme de la Mairie de Rufisque-Est illustre notre approche : moderne, accessible et adaptée aux besoins africains.
                </p>
            </div>

            <div class="grid md:grid-cols-4 gap-4 lg:gap-6">
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-900 shadow-card">
                    <p class="text-4xl lg:text-5xl font-black maire-text-gradient">15+</p>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mt-2">Modules métier</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">Citoyens, paiements, signalements…</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-900 shadow-card">
                    <p class="text-4xl lg:text-5xl font-black maire-text-gradient">9.2<span class="text-2xl">/10</span></p>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mt-2">Score sécurité</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">OWASP Top 10 couvert</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-900 shadow-card">
                    <p class="text-4xl lg:text-5xl font-black maire-text-gradient">3<span class="text-2xl">×</span></p>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mt-2">Paiements mobiles</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">Wave + Orange + Free Money</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-900 shadow-card">
                    <p class="text-4xl lg:text-5xl font-black maire-text-gradient">24/7</p>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mt-2">Disponibilité</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">PWA + mode hors-ligne</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════
         CTA FINAL — CONTACT
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="py-24 relative overflow-hidden bg-gradient-to-br from-mairie-950 via-mairie-900 to-slate-950 text-white">
        <div class="absolute inset-0 maire-mesh-bg opacity-30" aria-hidden="true"></div>

        <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 relative text-center">
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                Votre administration<br>
                <span class="bg-gradient-to-r from-gold-300 via-gold-200 to-gold-400 bg-clip-text text-transparent">
                    mérite&nbsp;mieux.
                </span>
            </h2>
            <p class="text-lg lg:text-xl text-mairie-100 max-w-2xl mx-auto mb-10 leading-relaxed">
                Parlons de votre projet : digitalisation d'une commune, plateforme citoyenne, intégration de paiements, refonte de site institutionnel.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="contact.php" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-gold-500 hover:bg-gold-400 text-mairie-950 font-black text-lg shadow-2xl hover:scale-105 transition-all">
                    Démarrer un projet
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="index.php" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white font-black text-lg transition-all hover:scale-105">
                    Revenir au site
                </a>
            </div>

            <div class="mt-12 pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-center gap-6 text-sm text-mairie-200">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                    Contact dédié éditeur
                </span>
                <span class="hidden sm:inline">•</span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Réalisation locale, déploiement international
                </span>
            </div>
        </div>
    </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
