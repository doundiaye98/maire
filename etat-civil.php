<?php
declare(strict_types=1);
$pageTitle = 'Service État civil | Mairie de Rufisque-Est';
$pageDescription = "Démarches d'état civil, légalisation et accompagnement administratif des citoyens de Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🪪',
    'kicker' => 'Service municipal · Administration',
    'titreH1' => 'État',
    'titreHilight' => 'civil',
    'description' => "Démarches d'état civil, légalisation et accompagnement administratif des citoyens.",
    'heroGradient' => 'from-mairie-800 via-mairie-900 to-emerald-950',
    'blobColor' => 'bg-emerald-400/30',
    'blobColor2' => 'bg-gold-400/25',
    'stats' => [
        ['valeur' => 48,  'suffix' => 'h', 'label' => 'Délai express'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Officiel'],
        ['valeur' => 5,   'suffix' => '+', 'label' => 'Démarches'],
    ],
    'blocs' => [
        [
            'icone' => '👶',
            'titre' => 'Actes de naissance',
            'gradient' => 'from-emerald-500 to-teal-600',
            'puces' => [
                'Déclaration des naissances dans les délais légaux.',
                "Demande d'extrait et de copie d'acte de naissance.",
                "Orientation pour correction d'informations administratives.",
            ],
        ],
        [
            'icone' => '💍',
            'titre' => 'Dossiers de mariage',
            'gradient' => 'from-rose-500 to-pink-600',
            'puces' => [
                "Liste des pièces à fournir pour l'ouverture du dossier.",
                'Accompagnement sur les étapes de la procédure.',
                'Information sur la planification des cérémonies civiles.',
            ],
        ],
        [
            'icone' => '📜',
            'titre' => 'Décès et légalisation',
            'gradient' => 'from-slate-500 to-slate-700',
            'puces' => [
                'Déclaration et traitement des actes de décès.',
                'Service de légalisation de documents administratifs.',
                'Orientation pour les formalités complémentaires.',
            ],
        ],
    ],
    'ctaLabel' => 'Démarrer une démarche en ligne',
    'ctaLien' => 'digitalisation-etat-civil.php',
]);
?>

<!-- BLOC DEMANDES EXPRESS -->
<section class="py-16 bg-white dark:bg-slate-950">
    <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-gold-500 via-orange-500 to-amber-600 text-white p-8 md:p-10">
            <div class="absolute -top-12 -right-12 w-60 h-60 bg-white/10 rounded-full blur-3xl maire-blob pointer-events-none"></div>
            <div class="relative grid md:grid-cols-[2fr_1fr] items-center gap-6">
                <div>
                    <span class="maire-tag bg-white/20 backdrop-blur-sm text-white mb-3">⚡ Service express</span>
                    <h2 class="text-2xl md:text-3xl font-black mb-2">Vos documents en 48h chrono</h2>
                    <p class="text-white/90">Demandez vos actes de naissance, mariage ou décès en ligne. Vous recevez le document officiel par e-mail ou en retrait à la mairie.</p>
                </div>
                <a href="digitalisation-etat-civil.php" class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl bg-white text-orange-700 font-black hover:bg-mairie-50 transition-colors shadow-lg">
                    Demande en ligne
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
