<?php
declare(strict_types=1);
require __DIR__ . '/includes/admin-guard.php';
$pageTitle = 'Formules pour mairies — Modernisez votre gouvernance';
$pageDescription = "Solutions numériques adaptées aux mairies sénégalaises : portail public, état civil numérique, paiement de taxes, analytique territoriale.";
require __DIR__ . '/includes/header.php';

$tiers = [
    [
        'code' => 'simple',
        'badge' => 'Gratuit',
        'badgeClass' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        'titre' => 'Simple',
        'prix' => '0',
        'devise' => 'FCFA / mois',
        'tagline' => "L'essentiel pour débuter la transition numérique.",
        'gradient' => 'from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900',
        'border' => 'border-slate-300 dark:border-slate-700',
        'features' => [
            ['ok' => true,  'label' => 'Gestion documentaire de base'],
            ['ok' => true,  'label' => 'Profil municipal public'],
            ['ok' => true,  'label' => 'Accès aux actualités nationales'],
            ['ok' => false, 'label' => 'Suivi des citoyens avancé'],
        ],
        'ctaPrimary' => ['Choisir — portail public', 'index.php'],
    ],
    [
        'code' => 'standard',
        'badge' => '⭐ Recommandé',
        'badgeClass' => 'bg-gold-400 text-mairie-950',
        'titre' => 'Standard',
        'prix' => '45 000',
        'devise' => 'FCFA / mois',
        'tagline' => 'Gestion complète des services municipaux courants.',
        'gradient' => 'from-mairie-800 to-mairie-950',
        'textInverted' => true,
        'border' => 'border-gold-400 ring-4 ring-gold-400/20',
        'features' => [
            ['ok' => true, 'label' => 'Tout le plan Simple'],
            ['ok' => true, 'label' => 'Suivi citoyens & registre civil'],
            ['ok' => true, 'label' => "Plateforme d'e-gouvernance"],
            ['ok' => true, 'label' => 'Paiement des taxes locales en ligne'],
        ],
        'ctaPrimary' => ['Demander la formule', 'contact.php'],
        'ctaSecondary' => ['Connexion agent', 'abonnement.php'],
    ],
    [
        'code' => 'premium',
        'badge' => '👑 Premium',
        'badgeClass' => 'bg-gradient-to-r from-purple-500 to-fuchsia-600 text-white',
        'titre' => 'Premium',
        'prix' => '95 000',
        'devise' => 'FCFA / mois',
        'tagline' => 'Suite intégrée pour les grandes municipalités.',
        'gradient' => 'from-white to-slate-50 dark:from-slate-800 dark:to-slate-900',
        'border' => 'border-fuchsia-300 dark:border-fuchsia-800',
        'features' => [
            ['ok' => true, 'label' => 'Tout le plan Standard'],
            ['ok' => true, 'label' => 'Gestion avancée du cadastre'],
            ['ok' => true, 'label' => 'Analytique & big data territorial'],
            ['ok' => true, 'label' => 'Support technique dédié 24/7'],
        ],
        'ctaPrimary' => ['Demander la formule', 'contact.php'],
        'ctaSecondary' => ['Connexion agent', 'abonnement.php'],
    ],
];
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                <span aria-hidden="true">🏛️</span>
                Commune de Rufisque-Est · Sceau municipal
            </span>
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                Modernisez votre<br><span class="maire-text-gradient">gouvernance locale</span>
            </h1>
            <p class="text-xl text-mairie-100 leading-relaxed max-w-3xl mx-auto">
                Des solutions numériques adaptées aux besoins de chaque municipalité sénégalaise pour une administration plus transparente et efficace.
            </p>
        </div>
    </section>

    <!-- COMMENT ÇA MARCHE -->
    <section class="py-12 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-50 to-gold-50 dark:from-mairie-950/40 dark:to-gold-950/40 border-2 border-mairie-200 dark:border-mairie-800 p-7 md:p-10">
                <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-300/30 rounded-full blur-3xl maire-blob"></div>
                <div class="relative">
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-xl">ℹ️</span>
                        Comment ça marche pour Rufisque-Est&nbsp;?
                    </h2>
                    <p class="text-slate-700 dark:text-slate-300 leading-relaxed mb-3">
                        <strong>C'est la mairie qui souscrit</strong> à une formule (Simple, Standard ou Premium) pour <strong>toute la commune</strong>. Une fois cette formule activée, <strong>les habitants n'ont pas d'abonnement à payer</strong> : ils utilisent gratuitement les services prévus par le palier choisi.
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400 italic">
                        Les comptes avec e-mail et mot de passe servent surtout au personnel municipal (suivi des dossiers, administration du référentiel).
                    </p>
                </div>
            </article>
        </div>
    </section>

    <!-- PRICING -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="maire-tag bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200 mb-3">Tarification mairie</span>
                <h2 class="text-3xl md:text-5xl font-black text-slate-900 dark:text-white">Choisissez votre <span class="maire-text-gradient">formule</span></h2>
                <p class="text-slate-600 dark:text-slate-400 mt-3 max-w-xl mx-auto">Toutes les formules incluent l'hébergement souverain, le support et les mises à jour.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6 items-stretch">
                <?php foreach ($tiers as $t):
                    $inv = !empty($t['textInverted']);
                    $textBase = $inv ? 'text-white' : 'text-slate-900 dark:text-white';
                    $textMuted = $inv ? 'text-mairie-100' : 'text-slate-600 dark:text-slate-400';
                ?>
                <article class="maire-bento-card relative rounded-3xl overflow-hidden bg-gradient-to-br <?php echo $t['gradient']; ?> border-2 <?php echo $t['border']; ?> p-8 flex flex-col <?php echo $inv ? 'shadow-2xl scale-105' : ''; ?>">
                    <?php if ($inv): ?>
                        <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none"></div>
                    <?php endif; ?>
                    <div class="relative">
                        <span class="<?php echo $t['badgeClass']; ?> inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider mb-4"><?php echo $t['badge']; ?></span>
                        <h3 class="text-3xl md:text-4xl font-black <?php echo $textBase; ?> mb-1"><?php echo $t['titre']; ?></h3>
                        <p class="<?php echo $textMuted; ?> text-sm mb-5"><?php echo $t['tagline']; ?></p>
                        <div class="mb-6">
                            <span class="text-5xl md:text-6xl font-black <?php echo $inv ? 'maire-text-gradient' : $textBase; ?>"><?php echo $t['prix']; ?></span>
                            <span class="<?php echo $textMuted; ?> ml-1"><?php echo $t['devise']; ?></span>
                        </div>
                        <ul class="space-y-3 mb-7">
                            <?php foreach ($t['features'] as $f): ?>
                                <li class="flex items-start gap-2 text-sm">
                                    <?php if ($f['ok']): ?>
                                        <span class="<?php echo $inv ? 'text-gold-400' : 'text-emerald-500'; ?> flex-shrink-0 font-black">✓</span>
                                    <?php else: ?>
                                        <span class="<?php echo $inv ? 'text-white/30' : 'text-slate-300 dark:text-slate-600'; ?> flex-shrink-0 font-black">✗</span>
                                    <?php endif; ?>
                                    <span class="<?php echo $f['ok'] ? $textBase : ($inv ? 'text-white/40' : 'text-slate-400 dark:text-slate-500 line-through'); ?>"><?php echo $f['label']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="flex flex-col gap-2 mt-auto">
                            <a class="<?php echo $inv ? 'bg-gold-400 hover:bg-gold-300 text-mairie-950' : 'tw-btn-primary'; ?> inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-black transition-colors" href="<?php echo $t['ctaPrimary'][1]; ?>">
                                <?php echo $t['ctaPrimary'][0]; ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                            <?php if (!empty($t['ctaSecondary'])): ?>
                                <a class="<?php echo $inv ? 'bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white' : 'tw-btn-outline'; ?> inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold transition-colors text-sm" href="<?php echo $t['ctaSecondary'][1]; ?>">
                                    <?php echo $t['ctaSecondary'][0]; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SOUVERAINETÉ + TRUST -->
    <section class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-800 via-mairie-900 to-mairie-950 text-white p-8 md:p-12 mb-10">
                <div class="absolute -top-12 -right-12 w-80 h-80 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none"></div>
                <div class="relative max-w-3xl">
                    <span class="maire-tag bg-gold-400/20 backdrop-blur-sm border border-gold-400/40 text-gold-300 mb-3">🔒 Hébergement souverain</span>
                    <h2 class="text-3xl md:text-4xl font-black mb-4">Souveraineté <span class="maire-text-gradient">des données</span></h2>
                    <p class="text-mairie-100 text-lg leading-relaxed">
                        Toutes vos données citoyennes sont hébergées sur le territoire national, garantissant une conformité totale avec les lois sénégalaises sur la protection des données personnelles.
                    </p>
                </div>
            </article>

            <div class="grid md:grid-cols-3 gap-6">
                <article class="tw-card p-7 text-center">
                    <span class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white items-center justify-center text-3xl shadow-md mb-4">🛡️</span>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Sécurité</h3>
                    <p class="text-xs uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300 mb-2">RGPD Sénégal</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Traitement et conservation alignés sur le cadre national de protection des données.</p>
                </article>
                <article class="tw-card p-7 text-center">
                    <span class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white items-center justify-center text-3xl shadow-md mb-4">⚡</span>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Déploiement rapide</h3>
                    <p class="text-xs uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300 mb-2">Sous 48h</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Configuration de votre espace municipal en moins de 48 heures.</p>
                </article>
                <article class="tw-card p-7 text-center">
                    <span class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white items-center justify-center text-3xl shadow-md mb-4">👥</span>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Formation des élus</h3>
                    <p class="text-xs uppercase tracking-wider font-bold text-blue-700 dark:text-blue-300 mb-2">Accompagnement</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Accompagnement personnalisé pour vos équipes administratives.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- COMPARATIF -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Comparatif des <span class="maire-text-gradient">formules</span></h2>
            </div>
            <div class="tw-card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gradient-to-r from-mairie-800 to-mairie-950 text-white">
                        <tr>
                            <th class="text-left p-4 font-black uppercase tracking-wider text-xs">Fonctionnalité</th>
                            <th class="text-center p-4 font-black uppercase tracking-wider text-xs">Simple</th>
                            <th class="text-center p-4 font-black uppercase tracking-wider text-xs bg-gold-400/30">Standard</th>
                            <th class="text-center p-4 font-black uppercase tracking-wider text-xs">Premium</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php
                        $rows = [
                            ['Portail & profil municipal', '✓', '✓', '✓'],
                            ['Suivi citoyens & registre civil numérique', '—', '✓', '✓'],
                            ['E-gouvernance & requêtes', '—', '✓', '✓'],
                            ['Paiement des taxes en ligne', '—', '✓', '✓'],
                            ['Cadastre avancé & analytique', '—', '—', '✓'],
                            ['Support 24/7', '—', '—', '✓'],
                        ];
                        foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="p-4 font-bold text-slate-900 dark:text-slate-100"><?php echo $r[0]; ?></td>
                            <td class="p-4 text-center text-lg"><span class="<?php echo $r[1] === '✓' ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600'; ?>"><?php echo $r[1]; ?></span></td>
                            <td class="p-4 text-center text-lg bg-gold-50/50 dark:bg-gold-950/20"><span class="<?php echo $r[2] === '✓' ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600'; ?>"><?php echo $r[2]; ?></span></td>
                            <td class="p-4 text-center text-lg"><span class="<?php echo $r[3] === '✓' ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600'; ?>"><?php echo $r[3]; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-10 flex flex-wrap gap-3 justify-center">
                <a class="tw-btn-primary" href="contact.php">Contacter la mairie</a>
                <a class="tw-btn-outline" href="abonnement.php">Connexion personnel de mairie</a>
            </div>
        </div>
    </section>

    <!-- DEVIS SUR MESURE -->
    <section class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-gold-500 via-orange-500 to-amber-600 text-white p-8 md:p-12">
                <div class="absolute -top-12 -right-12 w-80 h-80 bg-white/10 rounded-full blur-3xl maire-blob pointer-events-none"></div>
                <div class="relative grid md:grid-cols-[2fr_1fr] items-center gap-6">
                    <div>
                        <h2 class="text-3xl md:text-4xl font-black mb-2">Besoin d'un devis sur mesure&nbsp;?</h2>
                        <p class="text-white/90 text-lg">Pour les grandes agglomérations ou les besoins spécifiques d'intercommunalité, nos experts sont à votre disposition.</p>
                    </div>
                    <a href="contact.php" class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl bg-white text-orange-700 font-black text-lg hover:bg-mairie-50 transition-colors shadow-lg">
                        Contactez-nous
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
