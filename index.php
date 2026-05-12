<?php
declare(strict_types=1);
require __DIR__ . '/includes/header.php';

$alerteCommune = match (trim((string) ($_GET['commune'] ?? ''))) {
    'standard_requis' => 'Ce service n’est pas encore disponible pour tous les habitants. Pour toute question, adressez-vous à la mairie.',
    'indispo' => 'Connexion à la base de données impossible. Merci de réessayer plus tard ou de contacter la mairie.',
    'config' => 'Configuration communale incomplète. Merci de contacter l’administrateur du site.',
    default => null,
};

$maireStats = [
    ['valeur' => '42', 'label' => 'Projets municipaux', 'icone' => '🏗️', 'gradient' => 'from-blue-500 to-cyan-500'],
    ['valeur' => '18', 'label' => 'Structures de santé', 'icone' => '🏥', 'gradient' => 'from-rose-500 to-pink-500'],
    ['valeur' => '31', 'label' => 'Écoles & centres', 'icone' => '🎓', 'gradient' => 'from-amber-500 to-orange-500'],
    ['valeur' => '95', 'label' => '% Satisfaction', 'icone' => '⭐', 'gradient' => 'from-emerald-500 to-teal-500'],
];

$maireServicesBento = [
    ['size' => 'lg:col-span-2 lg:row-span-2', 'icone' => '🏛️', 'titre' => 'Services administratifs',
     'desc' => 'État civil, attestations, autorisations, démarches en ligne. Tout ce dont vous avez besoin, depuis chez vous.',
     'url' => 'services.php', 'gradient' => 'from-mairie-700 via-mairie-800 to-mairie-950',
     'image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=900&q=80'],
    ['size' => 'lg:col-span-2', 'icone' => '🚨', 'titre' => 'Signaler un problème',
     'desc' => 'Routes, lampadaires, déchets — avec photo et géolocalisation.',
     'url' => 'signaler.php', 'gradient' => 'from-orange-500 to-red-600'],
    ['size' => '', 'icone' => '📄', 'titre' => 'État civil',
     'desc' => 'Actes & documents.', 'url' => 'etat-civil.php', 'gradient' => 'from-emerald-500 to-teal-600'],
    ['size' => '', 'icone' => '💳', 'titre' => 'Paiements',
     'desc' => 'Taxes, doc. express.', 'url' => 'paiements.php', 'gradient' => 'from-amber-500 to-yellow-500'],
    ['size' => 'lg:col-span-2', 'icone' => '🗳️', 'titre' => 'Consultations &amp; votes citoyens',
     'desc' => 'Participez aux décisions de votre commune en quelques clics.',
     'url' => 'consultations.php', 'gradient' => 'from-violet-600 to-fuchsia-600'],
    ['size' => '', 'icone' => '🗞️', 'titre' => 'Actualités',
     'desc' => 'Annonces officielles.', 'url' => 'actualites.php', 'gradient' => 'from-purple-500 to-pink-600'],
    ['size' => '', 'icone' => '🚧', 'titre' => 'Projets',
     'desc' => 'Chantiers en cours.', 'url' => 'projets.php', 'gradient' => 'from-sky-500 to-blue-600'],
];

$maireTemoinages = [
    ['photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80',
     'nom' => 'Fatou Diop', 'role' => 'Habitante de Keury Souf',
     'texte' => 'J\'ai déclaré une fuite d\'eau le matin, c\'était réparé l\'après-midi. Bravo !'],
    ['photo' => 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=200&q=80',
     'nom' => 'Mamadou Sow', 'role' => 'Père de famille',
     'texte' => 'Mes papiers d\'état civil obtenus en 48h sans bouger de chez moi. Énorme.'],
    ['photo' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
     'nom' => 'Aïssatou Ba', 'role' => 'Commerçante',
     'texte' => 'Payer ma taxe locale en ligne avec Wave, je ne pensais pas que c\'était possible !'],
];
?>

<main class="overflow-hidden">
    <?php if ($alerteCommune !== null): ?>
    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-6" aria-live="polite">
        <div class="rounded-2xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 p-4 flex items-start gap-3 animate-fade-in">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <p class="text-sm text-red-800 dark:text-red-200"><?php echo htmlspecialchars($alerteCommune, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>
    <?php endif; ?>

    <!-- TICKER ACTUALITÉS -->
    <div class="bg-mairie-950 text-white py-2 overflow-hidden border-y border-gold-500/30">
        <div class="flex whitespace-nowrap maire-ticker">
            <div class="flex items-center gap-12 px-6">
                <span class="inline-flex items-center gap-2 text-sm"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span><strong class="text-gold-400">EN DIRECT</strong> · Conseil municipal mardi 19 mai à 18h</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">🎉 Nouveau service · Paiement Orange Money &amp; Wave disponible</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">📅 Permanence du Maire · jeudi 14h-17h, sur RDV</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">⚡ 95% des démarches traitées en moins de 48h</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">🗳️ Consultation publique en cours · Espaces verts du quartier</span>
            </div>
            <div class="flex items-center gap-12 px-6" aria-hidden="true">
                <span class="inline-flex items-center gap-2 text-sm"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span><strong class="text-gold-400">EN DIRECT</strong> · Conseil municipal mardi 19 mai à 18h</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">🎉 Nouveau service · Paiement Orange Money &amp; Wave disponible</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">📅 Permanence du Maire · jeudi 14h-17h, sur RDV</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">⚡ 95% des démarches traitées en moins de 48h</span>
                <span class="inline-flex items-center gap-2 text-sm text-mairie-100">🗳️ Consultation publique en cours · Espaces verts du quartier</span>
            </div>
        </div>
    </div>

    <!-- ============ HERO PLEIN ÉCRAN ============ -->
    <section class="relative min-h-[92vh] flex items-center maire-mesh-bg maire-grain overflow-hidden" id="accueil" aria-labelledby="hero-title">
        <!-- Blobs morphing décor -->
        <div class="absolute -top-32 -left-32 w-[40rem] h-[40rem] bg-gradient-to-br from-mairie-500/40 to-emerald-500/20 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -right-32 w-[40rem] h-[40rem] bg-gradient-to-br from-gold-500/40 to-orange-500/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
        <div class="absolute top-1/3 left-1/2 w-96 h-96 bg-gradient-to-br from-fuchsia-500/20 to-violet-600/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -5s;" aria-hidden="true"></div>

        <!-- Grille de fond subtile -->
        <div class="absolute inset-0 opacity-[0.04] pointer-events-none" style="background-image: linear-gradient(rgba(0,0,0,1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,1) 1px, transparent 1px); background-size: 60px 60px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 relative z-10">
            <div class="grid lg:grid-cols-12 gap-10 items-center">
                <!-- COLONNE GAUCHE -->
                <div class="lg:col-span-7 text-center lg:text-left animate-fade-up">
                    <!-- Badge dynamique -->
                    <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-mairie-300/40 dark:border-mairie-600/40 mb-6 shadow-lg">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-mairie-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-mairie-600"></span>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider text-mairie-900 dark:text-mairie-100">Portail officiel · République du Sénégal</span>
                    </div>

                    <!-- Titre géant -->
                    <h1 id="hero-title" class="text-5xl sm:text-6xl md:text-7xl lg:text-[5.5rem] font-black leading-[0.95] tracking-tight text-slate-900 dark:text-white mb-6">
                        Votre ville,<br>
                        <span class="maire-text-gradient">à portée de main.</span>
                    </h1>

                    <!-- Lede -->
                    <p class="text-xl md:text-2xl text-slate-700 dark:text-slate-300 mb-8 max-w-2xl lg:max-w-none leading-relaxed mx-auto lg:mx-0 font-light">
                        Démarches administratives, signalements, paiements et consultations citoyennes —
                        <span class="font-semibold text-mairie-800 dark:text-mairie-300">tout en un clic</span>,
                        depuis votre téléphone ou votre ordinateur.
                    </p>

                    <!-- CTAs imposants -->
                    <div class="flex flex-wrap gap-4 justify-center lg:justify-start mb-10">
                        <a href="services.php" class="group relative inline-flex items-center gap-3 px-8 py-4 rounded-2xl text-white font-bold text-base shadow-2xl maire-shimmer-btn overflow-hidden hover:scale-105 transition-transform">
                            <span class="relative z-10">Commencer mes démarches</span>
                            <svg class="w-5 h-5 relative z-10 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="signaler.php" class="group inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border-2 border-mairie-800 dark:border-mairie-400 text-mairie-900 dark:text-mairie-100 font-bold text-base hover:bg-mairie-800 hover:text-white dark:hover:bg-mairie-700 transition-all hover:scale-105">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Signaler un problème
                        </a>
                    </div>

                    <!-- Mini stats sociales -->
                    <div class="flex items-center gap-6 justify-center lg:justify-start text-sm">
                        <div class="flex -space-x-2">
                            <img class="w-10 h-10 rounded-full ring-2 ring-white" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80" alt="">
                            <img class="w-10 h-10 rounded-full ring-2 ring-white" src="https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=80&q=80" alt="">
                            <img class="w-10 h-10 rounded-full ring-2 ring-white" src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=80&q=80" alt="">
                            <span class="w-10 h-10 rounded-full ring-2 ring-white bg-mairie-800 text-white flex items-center justify-center text-xs font-bold">+12k</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-1 text-amber-500">★★★★★</div>
                            <p class="text-slate-600 dark:text-slate-400 mt-0.5"><strong>12 000+</strong> habitants déjà connectés</p>
                        </div>
                    </div>
                </div>

                <!-- COLONNE DROITE : visuel -->
                <div class="lg:col-span-5 animate-fade-up" style="animation-delay: 0.2s;">
                    <div class="relative">
                        <!-- Carte mockup smartphone -->
                        <div class="relative mx-auto max-w-sm">
                            <div class="absolute -inset-8 bg-gradient-to-br from-mairie-500/30 to-gold-500/30 rounded-[3rem] blur-2xl maire-float" aria-hidden="true"></div>
                            <div class="relative bg-gradient-to-br from-mairie-900 to-mairie-950 rounded-[2.5rem] p-3 shadow-2xl border-4 border-slate-900">
                                <div class="bg-white dark:bg-slate-100 rounded-[2rem] p-5 overflow-hidden">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-2">
                                            <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-mairie-700 to-mairie-900 flex items-center justify-center text-white text-sm shadow-md">R</span>
                                            <span class="font-bold text-sm text-slate-800">Mairie</span>
                                        </div>
                                        <span class="text-xs text-slate-500">14:32</span>
                                    </div>
                                    <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Activité du jour</p>
                                    <div class="space-y-2 mt-3">
                                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-green-50 border border-green-200">
                                            <span class="w-9 h-9 rounded-lg bg-green-500 text-white flex items-center justify-center">✓</span>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-slate-800">Acte de naissance prêt</p>
                                                <p class="text-xs text-slate-500">Récupération à la mairie</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-amber-50 border border-amber-200">
                                            <span class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center">⚡</span>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-slate-800">Lampadaire signalé</p>
                                                <p class="text-xs text-slate-500">En cours de traitement</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-blue-50 border border-blue-200">
                                            <span class="w-9 h-9 rounded-lg bg-blue-500 text-white flex items-center justify-center">💳</span>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-slate-800">Taxe payée · 15 000 F</p>
                                                <p class="text-xs text-slate-500">Reçu par e-mail</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-3 gap-1.5">
                                        <div class="aspect-square rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 flex items-center justify-center text-white text-xl shadow-md">🏛️</div>
                                        <div class="aspect-square rounded-xl bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white text-xl shadow-md">🚨</div>
                                        <div class="aspect-square rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 flex items-center justify-center text-white text-xl shadow-md">💳</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Badges flottants autour du mockup -->
                        <div class="absolute -top-4 -right-4 bg-white dark:bg-slate-800 rounded-2xl px-4 py-3 shadow-2xl border border-slate-200 dark:border-slate-700 maire-float" style="animation-delay: -2s;">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">🏆</span>
                                <div>
                                    <p class="text-xs text-slate-500 uppercase font-bold">Note</p>
                                    <p class="text-sm font-black text-mairie-800 dark:text-mairie-300">4.8/5</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-4 -left-6 bg-gradient-to-br from-mairie-700 to-mairie-900 text-white rounded-2xl px-4 py-3 shadow-2xl maire-float" style="animation-delay: -4s;">
                            <p class="text-xs uppercase font-bold opacity-80">Aujourd'hui</p>
                            <p class="text-lg font-black">+128 démarches</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scroll indicator -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-slate-600 dark:text-slate-400">
                <span class="text-xs uppercase tracking-widest font-semibold">Découvrir</span>
                <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            </div>
        </div>
    </section>

    <!-- ============ COMPTEURS ANIMÉS ============ -->
    <section class="py-16 bg-white dark:bg-slate-950 relative">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <?php foreach ($maireStats as $i => $stat): ?>
                <div class="relative group" style="animation-delay: <?php echo ($i * 0.1); ?>s;">
                    <div class="absolute -inset-1 bg-gradient-to-br <?php echo $stat['gradient']; ?> rounded-3xl opacity-20 group-hover:opacity-40 blur-xl transition-opacity"></div>
                    <div class="relative tw-card p-6 lg:p-8 text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br <?php echo $stat['gradient']; ?> text-white flex items-center justify-center text-3xl shadow-lg mb-4 group-hover:scale-110 transition-transform">
                            <?php echo $stat['icone']; ?>
                        </div>
                        <div class="text-4xl lg:text-5xl font-black text-slate-900 dark:text-white">
                            <span class="maire-counter" data-target="<?php echo (int) $stat['valeur']; ?>">0</span><?php echo $stat['label'] === '% Satisfaction' ? '%' : ''; ?>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 font-medium"><?php echo htmlspecialchars(str_replace('% Satisfaction', 'Satisfaction', $stat['label']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ BENTO GRID SERVICES ============ -->
    <section class="py-24 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-mairie-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-gold-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-16 max-w-3xl mx-auto">
                <span class="inline-block text-xs font-black tracking-widest uppercase text-gold-600 dark:text-gold-400 mb-4 px-4 py-1.5 rounded-full bg-gold-50 dark:bg-gold-900/30">Services citoyens</span>
                <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white leading-tight">
                    Une plateforme.<br>
                    <span class="maire-text-gradient">Tous les services.</span>
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-400 mt-6">
                    Conçue pour les habitants de Rufisque-Est, accessible 24h/24, depuis n'importe quel appareil.
                </p>
            </div>

            <!-- BENTO GRID -->
            <div class="grid lg:grid-cols-4 lg:auto-rows-[200px] gap-4">
                <?php foreach ($maireServicesBento as $i => $svc): ?>
                <a href="<?php echo htmlspecialchars($svc['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="maire-bento-card relative group rounded-3xl overflow-hidden bg-gradient-to-br <?php echo $svc['gradient']; ?> text-white p-6 lg:p-7 shadow-xl <?php echo $svc['size']; ?> animate-fade-up"
                   style="animation-delay: <?php echo ($i * 0.05); ?>s;">
                    <?php if (!empty($svc['image'])): ?>
                        <div class="absolute inset-0 opacity-20 group-hover:opacity-30 transition-opacity">
                            <img src="<?php echo htmlspecialchars($svc['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-full h-full object-cover">
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-br <?php echo $svc['gradient']; ?> opacity-70"></div>
                    <?php endif; ?>

                    <!-- Pattern décor -->
                    <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition-colors" aria-hidden="true"></div>

                    <div class="relative h-full flex flex-col justify-between">
                        <div class="text-4xl lg:text-5xl filter drop-shadow-lg">
                            <?php echo $svc['icone']; ?>
                        </div>
                        <div>
                            <h3 class="text-xl lg:text-2xl font-black mb-1 leading-tight"><?php echo $svc['titre']; ?></h3>
                            <p class="text-sm lg:text-base text-white/85 leading-snug"><?php echo $svc['desc']; ?></p>
                            <div class="mt-3 inline-flex items-center gap-1 text-sm font-bold opacity-90 group-hover:gap-2 group-hover:opacity-100 transition-all">
                                Découvrir
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ TÉMOIGNAGES ============ -->
    <section class="py-24 bg-mairie-950 text-white relative overflow-hidden">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-gold-500/20 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-mairie-400/20 rounded-full blur-3xl maire-blob pointer-events-none" style="animation-delay: -8s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-14">
                <span class="inline-block text-xs font-black tracking-widest uppercase text-gold-400 mb-4 px-4 py-1.5 rounded-full bg-white/10 border border-white/20">Ils nous font confiance</span>
                <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white">
                    La parole aux <span class="maire-text-gradient">habitants</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($maireTemoinages as $i => $t): ?>
                <div class="relative p-7 rounded-3xl bg-white/5 backdrop-blur-sm border border-white/10 hover:bg-white/10 transition-all animate-fade-up" style="animation-delay: <?php echo ($i * 0.1); ?>s;">
                    <!-- Quote géant en fond -->
                    <span class="absolute -top-2 -left-2 text-9xl text-gold-500/20 font-serif leading-none pointer-events-none">"</span>
                    <div class="relative">
                        <div class="flex items-center gap-1 text-amber-400 mb-4">★★★★★</div>
                        <p class="text-lg leading-relaxed mb-6 text-mairie-100">« <?php echo htmlspecialchars($t['texte'], ENT_QUOTES, 'UTF-8'); ?> »</p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/10">
                            <img src="<?php echo htmlspecialchars($t['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-12 h-12 rounded-full ring-2 ring-gold-500/40">
                            <div>
                                <p class="font-bold text-white"><?php echo htmlspecialchars($t['nom'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-mairie-200"><?php echo htmlspecialchars($t['role'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ ÉQUIPE INSTITUTIONNELLE ============ -->
    <section class="py-24 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 max-w-3xl mx-auto">
                <span class="inline-block text-xs font-black tracking-widest uppercase text-mairie-700 dark:text-mairie-400 mb-4 px-4 py-1.5 rounded-full bg-mairie-50 dark:bg-mairie-900/40">Équipe municipale</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white">
                    Une équipe au <span class="maire-text-gradient">service</span> de la commune
                </h2>
            </div>

            <div class="grid md:grid-cols-2 gap-6 lg:gap-8">
                <article class="relative group rounded-3xl overflow-hidden bg-slate-100 dark:bg-slate-800 shadow-card hover:shadow-card-hover transition-all">
                    <div class="aspect-[4/3] overflow-hidden">
                        <img src="assets/img/maire-portrait.jpg"
                             alt="Portrait officiel de Monsieur le Maire de Rufisque-Est"
                             class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-mairie-950 via-mairie-950/30 to-transparent"></div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 p-7 text-white">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gold-500/90 text-mairie-950 text-xs font-black uppercase tracking-wider mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-mairie-950"></span>
                            M. le Maire
                        </span>
                        <h3 class="text-2xl lg:text-3xl font-black mb-2">Leadership communal</h3>
                        <p class="text-mairie-100">Pilotage des projets stratégiques, gouvernance locale et dialogue citoyen.</p>
                        <a href="maire.php" class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-gold-300 hover:text-gold-200 transition">
                            Voir la présentation officielle →
                        </a>
                    </div>
                </article>

                <article class="relative group rounded-3xl overflow-hidden bg-slate-100 dark:bg-slate-800 shadow-card hover:shadow-card-hover transition-all">
                    <div class="aspect-[4/3] overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80"
                             alt="Administration municipale"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 p-7 text-white">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-mairie-600 text-white text-xs font-black uppercase tracking-wider mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                            Administration
                        </span>
                        <h3 class="text-2xl lg:text-3xl font-black mb-2">Équipe technique &amp; sociale</h3>
                        <p class="text-slate-100">Coordination des services municipaux : hygiène, urbanisme, éducation et action sociale.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ CTA FINAL IMPOSANT ============ -->
    <section class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 maire-mesh-bg opacity-90" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-mairie-950/85" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative text-center">
            <h2 class="text-5xl md:text-6xl lg:text-7xl font-black text-white mb-6 leading-tight">
                Prêt à <span class="maire-text-gradient">simplifier</span><br>vos démarches&nbsp;?
            </h2>
            <p class="text-xl text-mairie-100 max-w-2xl mx-auto mb-10">
                Créez votre compte habitant en 2 minutes et accédez à tous les services de la mairie.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                <?php if (!$maireCitoyenConnecte && $maireComptesCitoyensActifs): ?>
                <a href="citoyen/inscription.php" class="group relative inline-flex items-center gap-3 px-10 py-5 rounded-2xl text-mairie-950 font-black text-lg bg-gold-400 hover:bg-gold-300 shadow-2xl hover:scale-105 transition-all">
                    Créer mon compte habitant
                    <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="services.php" class="inline-flex items-center gap-3 px-10 py-5 rounded-2xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white font-black text-lg transition-all hover:scale-105">
                    Découvrir les services
                </a>
                <?php else: ?>
                <a href="services.php" class="group inline-flex items-center gap-3 px-10 py-5 rounded-2xl text-mairie-950 font-black text-lg bg-gold-400 hover:bg-gold-300 shadow-2xl hover:scale-105 transition-all">
                    Accéder aux services
                    <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="contact.php" class="inline-flex items-center gap-3 px-10 py-5 rounded-2xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white font-black text-lg transition-all hover:scale-105">
                    Nous contacter
                </a>
                <?php endif; ?>
            </div>
            <p class="text-sm text-mairie-200 mt-10 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                Données protégées · Hébergement Sénégal · Connexion sécurisée
            </p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
