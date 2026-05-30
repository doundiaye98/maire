<?php
declare(strict_types=1);

/**
 * Helpers pour les pages sectorielles (Éducation, Santé, Urbanisme, Hygiène, etc.)
 * Affichage cohérent : hero + 3 cartes d'info + CTA.
 */

if (!function_exists('maire_page_sectorielle_render')) {
    /**
     * @param array $config {
     *     @type string $icone   Émoji représentant le service.
     *     @type string $kicker  Texte au-dessus du titre.
     *     @type string $titreH1 Titre principal (peut contenir un span avec gradient).
     *     @type string $titreHilight Mot mis en valeur dans le titre (sera passé en gradient).
     *     @type string $titreHilightClass Classe CSS optionnelle du mot mis en valeur.
     *     @type string $description Sous-titre.
     *     @type string $descriptionClass Classe CSS optionnelle du sous-titre.
     *     @type string $heroGradient Classes Tailwind du dégradé hero (ex : "from-emerald-700 to-teal-900").
     *     @type string $blobColor   Classe couleur Tailwind pour les blobs (ex : "bg-emerald-400/30").
     *     @type array  $blocs Liste de 3 blocs : ['icone', 'titre', 'gradient', 'puces' => string[]].
     *     @type string $ctaLabel CTA principal.
     *     @type string $ctaLien   Lien CTA.
     *     @type array  $stats Optionnel : ['valeur', 'suffix', 'label'] x N.
     * }
     */
    function maire_page_sectorielle_render(array $config): void
    {
        $heroBg = $config['heroGradient'] ?? 'from-mairie-800 to-mairie-950';
        $blobColor = $config['blobColor'] ?? 'bg-gold-500/25';
        $blobColor2 = $config['blobColor2'] ?? 'bg-mairie-400/30';
        $stats = $config['stats'] ?? [];
        $titleHighlightClass = $config['titreHilightClass'] ?? 'maire-text-gradient';
        $descriptionClass = $config['descriptionClass'] ?? 'text-white';
        ?>
        <main class="overflow-hidden">
            <!-- HERO -->
            <section class="relative bg-gradient-to-br <?php echo htmlspecialchars($heroBg, ENT_QUOTES, 'UTF-8'); ?> text-white py-24 lg:py-28 maire-grain overflow-hidden">
                <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] <?php echo htmlspecialchars($blobColor, ENT_QUOTES, 'UTF-8'); ?> maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
                <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] <?php echo htmlspecialchars($blobColor2, ENT_QUOTES, 'UTF-8'); ?> maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
                <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 42px 42px;" aria-hidden="true"></div>

                <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="grid xl:grid-cols-[1.25fr_0.75fr] gap-8 items-end">
                        <div class="max-w-4xl">
                            <span class="maire-section-kicker mb-5 !bg-white/12 !text-white !border-white/20">
                                <span class="text-xl"><?php echo $config['icone'] ?? '🏛️'; ?></span>
                                <?php echo htmlspecialchars($config['kicker'] ?? 'Service municipal', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.92] tracking-tight mb-5" style="text-shadow:0 10px 32px rgba(0,0,0,0.28);">
                                <?php echo htmlspecialchars($config['titreH1'] ?? 'Service municipal', ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($config['titreHilight'])): ?><br><span class="<?php echo htmlspecialchars($titleHighlightClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($config['titreHilight'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            </h1>
                            <p class="text-xl md:text-2xl leading-relaxed max-w-3xl <?php echo htmlspecialchars($descriptionClass, ENT_QUOTES, 'UTF-8'); ?>" style="text-shadow:0 4px 18px rgba(0,0,0,0.20);">
                                <?php echo htmlspecialchars($config['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>

                        <?php if (!empty($stats)): ?>
                            <div class="grid grid-cols-<?php echo min(3, count($stats)); ?> gap-3 min-w-[280px]">
                                <?php foreach ($stats as $stat): ?>
                                    <article class="maire-kpi-card !bg-white/12 !border-white/20 !shadow-none">
                                        <p class="maire-kpi-card__value !text-white"><span class="maire-counter" data-target="<?php echo htmlspecialchars((string) $stat['valeur'], ENT_QUOTES, 'UTF-8'); ?>" data-suffix="<?php echo htmlspecialchars($stat['suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">0</span></p>
                                        <p class="maire-kpi-card__label !text-white/90"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- BLOCS D'INFORMATION -->
            <section class="py-18 bg-slate-50 dark:bg-slate-900">
                <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-2xl mb-10">
                        <span class="maire-section-kicker mb-4">Parcours & missions</span>
                        <h2 class="text-3xl md:text-4xl font-black text-slate-950 dark:text-white leading-tight">Une lecture claire du service, des interventions et des démarches associées.</h2>
                    </div>
                    <div class="grid md:grid-cols-3 gap-6">
                        <?php foreach (($config['blocs'] ?? []) as $bloc):
                            $gradient = $bloc['gradient'] ?? 'from-mairie-700 to-mairie-900';
                        ?>
                            <article class="maire-editorial-card maire-bento-card">
                                <div class="absolute -top-8 -right-8 w-40 h-40 bg-gradient-to-br <?php echo htmlspecialchars($gradient, ENT_QUOTES, 'UTF-8'); ?> opacity-15 rounded-full blur-2xl pointer-events-none"></div>
                                <div class="relative">
                                    <span class="inline-flex w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br <?php echo htmlspecialchars($gradient, ENT_QUOTES, 'UTF-8'); ?> text-white items-center justify-center text-2xl shadow-md">
                                        <?php echo $bloc['icone'] ?? '✓'; ?>
                                    </span>
                                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-4 leading-tight">
                                        <?php echo htmlspecialchars($bloc['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <ul class="space-y-2 text-sm">
                                        <?php foreach (($bloc['puces'] ?? []) as $puce): ?>
                                            <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                                                <span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1 font-bold">→</span>
                                                <span><?php echo htmlspecialchars($puce, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- CTA -->
                    <div class="mt-14 relative rounded-[2rem] overflow-hidden bg-gradient-to-br <?php echo htmlspecialchars($heroBg, ENT_QUOTES, 'UTF-8'); ?> text-white p-8 md:p-10 shadow-luxury">
                        <div class="absolute -top-12 -right-12 w-60 h-60 <?php echo htmlspecialchars($blobColor, ENT_QUOTES, 'UTF-8'); ?> rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 32px 32px;" aria-hidden="true"></div>
                        <div class="relative grid md:grid-cols-[2fr_1fr] items-center gap-6">
                            <div>
                                <h3 class="text-2xl md:text-3xl font-black mb-2">Besoin d'aide ou de précisions&nbsp;?</h3>
                                <p class="text-white/85 max-w-2xl">Contactez l'équipe municipale pour toutes vos démarches concernant ce service, ou laissez-vous guider vers le bon interlocuteur.</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <a href="<?php echo htmlspecialchars($config['ctaLien'] ?? 'contact.php', ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 font-black transition-colors">
                                    <?php echo htmlspecialchars($config['ctaLabel'] ?? 'Faire une demande', ENT_QUOTES, 'UTF-8'); ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                                <a href="services.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 font-black transition-colors">
                                    Tous les services
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        <?php
    }
}
