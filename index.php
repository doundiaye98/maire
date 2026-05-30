<?php
declare(strict_types=1);
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/site-data.php';
$maireShowSplash = true;
require __DIR__ . '/includes/header.php';

$alerteCommune = match (trim((string) ($_GET['commune'] ?? ''))) {
    'standard_requis' => 'Ce service n’est pas encore disponible pour tous les habitants. Pour toute question, adressez-vous à la mairie.',
    'indispo' => 'Connexion à la base de données impossible. Merci de réessayer plus tard ou de contacter la mairie.',
    'config' => 'Configuration communale incomplète. Merci de contacter l’administrateur du site.',
    default => null,
};

// Stats / témoignages / ticker : pilotés par la BDD (jamais de valeurs fictives).
$maireStats = maire_index_stats($pdo ?? null);
$maireTemoinages = maire_temoignages($pdo ?? null);
$maireTickerItems = maire_ticker_items($pdo ?? null);
$mairePublicServices = maire_public_services_catalogue_indexed();

$maireServicesBento = [
    ['size' => 'lg:col-span-2 lg:row-span-2', 'icone' => '🏛️', 'titre' => 'Services administratifs',
     'desc' => 'État civil, attestations, autorisations, démarches en ligne. Tout ce dont vous avez besoin, depuis chez vous.',
     'url' => 'services.php', 'gradient' => 'from-mairie-700 via-mairie-800 to-mairie-950',
     'image' => maire_placeholder_image('etat-civil', 900, 1200)],
    ['size' => 'lg:col-span-2', 'icone' => '🚨', 'titre' => 'Signaler un problème',
     'desc' => 'Routes, lampadaires, déchets — avec photo et géolocalisation.',
     'url' => 'citoyen/signaler.php', 'gradient' => 'from-orange-500 to-red-600'],
    ['size' => '',
     'icone' => $mairePublicServices['etat_civil']['icone'] ?? '📄',
     'titre' => $mairePublicServices['etat_civil']['nom'] ?? 'État civil',
     'desc' => 'Actes, demandes et documents.', 'url' => $mairePublicServices['etat_civil']['lien'] ?? 'etat-civil.php',
     'gradient' => $mairePublicServices['etat_civil']['gradient'] ?? 'from-emerald-500 to-teal-600'],
    ['size' => '', 'icone' => '💳', 'titre' => 'Paiements',
     'desc' => 'Taxes, doc. express.', 'url' => 'paiements.php', 'gradient' => 'from-amber-500 to-yellow-500'],
    ['size' => 'lg:col-span-2', 'icone' => '🗳️', 'titre' => 'Consultations &amp; votes citoyens',
     'desc' => 'Participez aux décisions de votre commune en quelques clics.',
     'url' => 'consultations.php', 'gradient' => 'from-violet-600 to-fuchsia-600'],
    ['size' => '', 'icone' => '🗞️', 'titre' => 'Actualités',
     'desc' => 'Annonces officielles.', 'url' => 'actualites.php', 'gradient' => 'from-purple-500 to-pink-600'],
    ['size' => '', 'icone' => '🚧', 'titre' => 'Projets',
     'desc' => 'Chantiers en cours.', 'url' => 'projets.php', 'gradient' => 'from-sky-500 to-blue-600'],
    ['size' => 'lg:col-span-4',
     'icone' => $mairePublicServices['services_techniques']['icone'] ?? '🛠️',
     'titre' => $mairePublicServices['services_techniques']['nom'] ?? 'Division des services techniques',
     'desc' => $mairePublicServices['services_techniques']['description'] ?? 'Voirie, éclairage public, maintenance communale et interventions techniques de proximité pour améliorer durablement le cadre de vie.',
     'url' => $mairePublicServices['services_techniques']['lien'] ?? 'division-services-techniques.php',
     'gradient' => $mairePublicServices['services_techniques']['gradient'] ?? 'from-slate-800 via-cyan-800 to-sky-700'],
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

    <!-- TICKER ACTUALITÉS — alimenté par la BDD (conseil municipal + dernières actualités) -->
    <?php if (!empty($maireTickerItems)): ?>
    <div class="bg-mairie-950 text-white py-2 overflow-hidden border-y border-gold-500/30">
        <div class="flex whitespace-nowrap maire-ticker">
            <?php for ($pass = 0; $pass < 2; $pass++): ?>
            <div class="flex items-center gap-12 px-6"<?php echo $pass === 1 ? ' aria-hidden="true"' : ''; ?>>
                <?php foreach ($maireTickerItems as $item):
                    $isLive = ($item['type'] ?? '') === 'live';
                ?>
                    <span class="inline-flex items-center gap-2 text-sm <?php echo $isLive ? '' : 'text-mairie-100'; ?>">
                        <?php if ($isLive): ?>
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                            <strong class="text-gold-400">
                        <?php endif; ?>
                        <?php echo htmlspecialchars((string) $item['texte'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isLive): ?></strong><?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

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
                    <div class="maire-section-kicker mb-6">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-mairie-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-mairie-600"></span>
                        </span>
                        <span>Portail officiel · République du Sénégal</span>
                    </div>

                    <!-- Titre géant -->
                    <h1 id="hero-title" class="text-5xl sm:text-6xl md:text-7xl lg:text-[5.5rem] font-black leading-[0.95] tracking-tight text-slate-900 dark:text-white mb-6">
                        Votre ville,<br>
                        <span class="maire-text-gradient">à portée de main.</span>
                    </h1>

                    <!-- Lede -->
                    <p class="text-xl md:text-2xl text-slate-700 dark:text-slate-300 mb-8 max-w-2xl lg:max-w-none leading-relaxed mx-auto lg:mx-0 font-light">
                        Démarches administratives, signalements, paiements et consultations citoyennes
                        <span class="font-semibold text-mairie-800 dark:text-mairie-300">sur un même portail</span>,
                        depuis votre téléphone ou votre ordinateur.
                    </p>

                    <!-- CTAs imposants -->
                    <div class="flex flex-wrap gap-4 justify-center lg:justify-start mb-10">
                        <a href="services.php" class="group relative inline-flex items-center gap-3 px-8 py-4 rounded-2xl text-white font-bold text-base shadow-2xl maire-shimmer-btn overflow-hidden hover:scale-105 transition-transform">
                            <span class="relative z-10">Commencer mes démarches</span>
                            <svg class="w-5 h-5 relative z-10 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="citoyen/signaler.php" class="group inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border-2 border-mairie-800 dark:border-mairie-400 text-mairie-900 dark:text-mairie-100 font-bold text-base hover:bg-mairie-800 hover:text-white dark:hover:bg-mairie-700 transition-all hover:scale-105">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Signaler un problème
                        </a>
                    </div>

                    <!-- Mini badges institutionnels (sans données fictives) -->
                    <div class="flex flex-wrap items-center gap-3 justify-center lg:justify-start text-sm">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-mairie-50 dark:bg-mairie-900/40 border border-mairie-200/60 dark:border-mairie-700/40 text-mairie-800 dark:text-mairie-200 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-mairie-600 animate-pulse"></span>
                            Service public communal
                        </span>
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gold-50 dark:bg-gold-900/30 border border-gold-200/60 dark:border-gold-700/40 text-gold-800 dark:text-gold-200 font-bold">
                            🇸🇳 République du Sénégal
                        </span>
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200/60 dark:border-emerald-700/40 text-emerald-800 dark:text-emerald-200 font-bold">
                            🌐 Portail en ligne
                        </span>
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
                                    <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Exemples de parcours</p>
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
                                <span class="text-2xl">🏛️</span>
                                <div>
                                    <p class="text-xs text-slate-500 uppercase font-bold">Statut</p>
                                    <p class="text-sm font-black text-mairie-800 dark:text-mairie-300">Portail officiel</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-4 -left-6 bg-gradient-to-br from-mairie-700 to-mairie-900 text-white rounded-2xl px-4 py-3 shadow-2xl maire-float" style="animation-delay: -4s;">
                            <p class="text-xs uppercase font-bold opacity-80">Accès</p>
                            <p class="text-lg font-black">Services en ligne</p>
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
                <?php foreach ($maireStats as $i => $stat):
                    $valeur = (string) $stat['valeur'];
                    $estNumerique = ctype_digit($valeur);
                ?>
                <div class="relative group" style="animation-delay: <?php echo ($i * 0.1); ?>s;">
                    <div class="absolute -inset-1 bg-gradient-to-br <?php echo $stat['gradient']; ?> rounded-[2rem] opacity-20 group-hover:opacity-40 blur-xl transition-opacity"></div>
                    <div class="relative maire-kpi-card text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br <?php echo $stat['gradient']; ?> text-white flex items-center justify-center text-3xl shadow-lg mb-4 group-hover:scale-110 transition-transform">
                            <?php echo $stat['icone']; ?>
                        </div>
                        <div class="text-4xl lg:text-5xl font-black text-slate-900 dark:text-white">
                            <?php if ($estNumerique): ?>
                                <span class="maire-counter" data-target="<?php echo (int) $valeur; ?>">0</span>
                            <?php else: ?>
                                <span class="text-slate-400 dark:text-slate-500"><?php echo htmlspecialchars($valeur, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 font-medium"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ PRÉSENTATION VIDÉO DE LA COMMUNE ============ -->
    <?php
    $maireVideoMp4 = __DIR__ . '/assets/video/presentation-rufisque-est.mp4';
    $maireVideoMov = __DIR__ . '/assets/video/presentation-rufisque-est.mov';
    $maireHasVideo = is_file($maireVideoMp4) || is_file($maireVideoMov);
    ?>
    <?php if ($maireHasVideo): ?>
    <section class="py-16 lg:py-20 bg-gradient-to-b from-mairie-950 via-mairie-900 to-slate-950 relative overflow-hidden">
        <div class="absolute top-0 right-1/4 w-[35rem] h-[35rem] bg-gold-500/12 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-1/4 w-[35rem] h-[35rem] bg-mairie-400/12 maire-blob blur-3xl pointer-events-none" style="animation-delay: -8s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 relative">
            <!-- En-tête de section -->
            <div class="text-center mb-8 max-w-3xl mx-auto">
                <span class="inline-flex items-center gap-2 text-xs font-black tracking-widest uppercase text-gold-300 mb-4 px-4 py-1.5 rounded-full bg-gold-500/15 border border-gold-400/30">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                    Présentation officielle
                </span>
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-black text-white leading-tight">
                    Découvrez <span class="maire-text-gradient">Rufisque-Est</span> en images
                </h2>
                <p class="text-base lg:text-lg text-mairie-200 mt-3">
                    Une commune dynamique, une équipe engagée, un projet de territoire ambitieux.
                </p>
            </div>

            <!-- LECTEUR VIDÉO PLEINE LARGEUR (centré, full container) -->
            <div class="relative rounded-3xl overflow-hidden shadow-2xl ring-1 ring-gold-500/20 bg-mairie-950 group">
                <video
                    class="block w-full h-auto bg-mairie-950"
                    style="max-height: 85vh; object-fit: contain;"
                    controls
                    preload="metadata"
                    playsinline
                    poster="assets/img/presentation-poster.svg"
                    aria-label="Vidéo de présentation officielle de la Mairie de Rufisque-Est">
                    <?php if (is_file($maireVideoMp4)): ?>
                    <source src="assets/video/presentation-rufisque-est.mp4" type="video/mp4">
                    <?php endif; ?>
                    <?php if (is_file($maireVideoMov)): ?>
                    <source src="assets/video/presentation-rufisque-est.mov" type="video/mp4">
                    <source src="assets/video/presentation-rufisque-est.mov" type="video/quicktime">
                    <?php endif; ?>
                    <p class="p-6 text-white">
                        Votre navigateur ne prend pas en charge la lecture vidéo intégrée.
                        <a class="underline font-bold text-gold-300" href="assets/video/<?php echo is_file($maireVideoMp4) ? 'presentation-rufisque-est.mp4' : 'presentation-rufisque-est.mov'; ?>" download>
                            Télécharger la vidéo
                        </a>
                    </p>
                </video>
                <!-- Badge "officiel" en superposition (clic-traversant) -->
                <div class="absolute top-4 left-4 px-3 py-1 rounded-full bg-mairie-950/80 backdrop-blur-sm border border-gold-400/30 text-[10px] md:text-xs font-black uppercase tracking-wider text-gold-300 pointer-events-none flex items-center gap-1.5 z-10">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    HD · Officiel
                </div>
            </div>

            <!-- BANDE D'INFOS COMPACTE EN DESSOUS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gold-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gold-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <div>
                        <p class="font-black text-white text-sm">Identité locale</p>
                        <p class="text-xs text-mairie-300">Histoire & patrimoine</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gold-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gold-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-black text-white text-sm">Engagement citoyen</p>
                        <p class="text-xs text-mairie-300">Au service des habitants</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gold-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gold-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <div>
                        <p class="font-black text-white text-sm">Projets d'avenir</p>
                        <p class="text-xs text-mairie-300">Développement local</p>
                    </div>
                </div>
            </div>

            <!-- CTA en bas -->
            <div class="flex flex-wrap justify-center gap-3 mt-8">
                <a href="maire.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gold-500 hover:bg-gold-400 text-mairie-950 font-bold text-sm transition shadow-lg">
                    Lire la biographie du Maire →
                </a>
                <a href="projets.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 text-white font-bold text-sm transition">
                    Voir les projets
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ BENTO GRID SERVICES ============ -->
    <section class="py-24 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-mairie-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-gold-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-16 max-w-3xl mx-auto">
                <span class="maire-section-kicker mb-4">Services citoyens</span>
                <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white leading-tight">
                    Une plateforme.<br>
                    <span class="maire-text-gradient">Tous les services.</span>
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-400 mt-6">
                    Conçue pour les habitants de Rufisque-Est, consultable en ligne depuis n'importe quel appareil.
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

    <!-- ============ TÉMOIGNAGES (BDD-driven, masqué si vide) ============ -->
    <?php if (!empty($maireTemoinages)): ?>
    <section class="py-24 bg-mairie-950 text-white relative overflow-hidden">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-gold-500/20 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-mairie-400/20 rounded-full blur-3xl maire-blob pointer-events-none" style="animation-delay: -8s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-14">
                <span class="maire-section-kicker mb-4 !bg-white/10 !text-gold-200 !border-white/15">Ils nous font confiance</span>
                <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white">
                    La parole aux <span class="maire-text-gradient">habitants</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($maireTemoinages as $i => $t):
                    $initiales = '';
                    $parts = preg_split('/\s+/', trim((string) ($t['nom'] ?? '')));
                    foreach ($parts as $p) { if ($p !== '') { $initiales .= mb_strtoupper(mb_substr($p, 0, 1)); } }
                    $initiales = mb_substr($initiales !== '' ? $initiales : '?', 0, 2);
                ?>
                <div class="relative p-7 rounded-3xl bg-white/5 backdrop-blur-sm border border-white/10 hover:bg-white/10 transition-all animate-fade-up" style="animation-delay: <?php echo ($i * 0.1); ?>s;">
                    <span class="absolute -top-2 -left-2 text-9xl text-gold-500/20 font-serif leading-none pointer-events-none">"</span>
                    <div class="relative">
                        <?php if (!empty($t['note']) && (int) $t['note'] > 0): ?>
                            <div class="flex items-center gap-1 text-amber-400 mb-4"><?php echo str_repeat('★', min(5, (int) $t['note'])); ?></div>
                        <?php endif; ?>
                        <p class="text-lg leading-relaxed mb-6 text-mairie-100">« <?php echo htmlspecialchars((string) ($t['texte'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> »</p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/10">
                            <span class="w-12 h-12 rounded-full ring-2 ring-gold-500/40 bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center font-black"><?php echo htmlspecialchars($initiales, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div>
                                <p class="font-bold text-white"><?php echo htmlspecialchars((string) ($t['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-mairie-200"><?php echo htmlspecialchars((string) ($t['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ ÉQUIPE INSTITUTIONNELLE ============ -->
    <section class="py-24 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 max-w-3xl mx-auto">
                <span class="maire-section-kicker mb-4">Équipe municipale</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white">
                    Une équipe au <span class="maire-text-gradient">service</span> de la commune
                </h2>
            </div>

            <div class="grid md:grid-cols-2 gap-6 lg:gap-8">
                <article class="relative group overflow-hidden rounded-[2rem] bg-slate-100 dark:bg-slate-800 shadow-luxury transition-all">
                    <a href="maire.php"
                       class="block absolute inset-0 z-10"
                       aria-label="Voir la présentation officielle du Maire"></a>
                    <div class="aspect-[4/3] overflow-hidden">
                        <?php
                        $portraitPath = is_file(__DIR__ . '/assets/img/maire-portrait.jpg')
                            ? 'assets/img/maire-portrait.jpg'
                            : 'assets/img/maire-portrait.svg';
                        ?>
                        <img src="<?php echo htmlspecialchars($portraitPath, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Portrait officiel d’Elimane Sakho Sembène, Maire de Rufisque-Est"
                             loading="lazy"
                             class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-mairie-950 via-mairie-950/30 to-transparent"></div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 p-7 text-white">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gold-500/90 text-mairie-950 text-xs font-black uppercase tracking-wider mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-mairie-950"></span>
                            M. le Maire
                        </span>
                        <h3 class="text-2xl lg:text-3xl font-black mb-2">Leadership communal</h3>
                        <p class="text-mairie-100 mb-3">Pilotage des projets stratégiques, gouvernance locale et dialogue citoyen.</p>
                        <span class="inline-flex items-center gap-1 text-sm font-bold text-gold-300 group-hover:text-gold-200 transition">
                            Voir la présentation officielle →
                        </span>
                    </div>
                </article>

                <article class="relative group overflow-hidden rounded-[2rem] bg-slate-100 dark:bg-slate-800 shadow-luxury transition-all">
                    <a href="maire.php#administration"
                       class="block absolute inset-0 z-10"
                       aria-label="Découvrir l’équipe administrative — technique et sociale"></a>
                    <div class="aspect-[4/3] overflow-hidden">
                        <img src="<?php echo htmlspecialchars(maire_placeholder_image('administration'), ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Administration municipale — Mairie de Rufisque-Est"
                             loading="lazy"
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
