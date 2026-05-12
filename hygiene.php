<?php
declare(strict_types=1);
$pageTitle = 'Service Hygiène | Mairie de Rufisque-Est';
$pageDescription = "Collecte, salubrité et prévention environnementale pour tous les quartiers de Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '♻️',
    'kicker' => 'Service municipal · Environnement',
    'titreH1' => 'Service',
    'titreHilight' => 'Hygiène',
    'description' => 'Collecte, salubrité et prévention environnementale pour tous les quartiers de Rufisque-Est.',
    'heroGradient' => 'from-emerald-700 via-green-800 to-teal-900',
    'blobColor' => 'bg-emerald-400/30',
    'blobColor2' => 'bg-teal-400/30',
    'stats' => [
        ['valeur' => 12,  'suffix' => '',  'label' => 'Secteurs de collecte'],
        ['valeur' => 24,  'suffix' => 'h', 'label' => 'Délai cible'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Couverture'],
    ],
    'blocs' => [
        [
            'icone' => '🗑️',
            'titre' => 'Collecte des déchets',
            'gradient' => 'from-emerald-500 to-teal-600',
            'puces' => [
                'Calendrier de passage des équipes par secteur.',
                'Points de dépôt identifiés pour faciliter la collecte.',
                'Suivi des zones prioritaires avec intervention renforcée.',
            ],
        ],
        [
            'icone' => '🧹',
            'titre' => 'Salubrité publique',
            'gradient' => 'from-teal-500 to-cyan-600',
            'puces' => [
                'Opérations régulières de nettoyage des espaces communs.',
                'Traitement des points noirs signalés par les habitants.',
                'Coordination avec les services techniques municipaux.',
            ],
        ],
        [
            'icone' => '📢',
            'titre' => 'Sensibilisation citoyenne',
            'gradient' => 'from-green-500 to-emerald-600',
            'puces' => [
                'Campagnes locales sur les gestes de propreté quotidienne.',
                'Actions de proximité avec les délégués de quartier.',
                'Orientation des citoyens pour les signalements rapides.',
            ],
        ],
    ],
    'ctaLabel' => "Faire une demande Hygiène",
    'ctaLien' => 'citoyen/signaler.php',
]);
?>

<!-- PARCOURS CITOYEN SIMPLIFIÉ (bonus pour cette page) -->
<section class="py-16 bg-white dark:bg-slate-950">
    <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="maire-tag bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 mb-3">Comment ça marche</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Parcours citoyen <span class="maire-text-gradient">simplifié</span></h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6 relative">
            <div class="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-gradient-to-r from-emerald-300 via-teal-400 to-emerald-300" aria-hidden="true"></div>

            <article class="tw-card p-7 text-center relative">
                <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white items-center justify-center text-4xl font-black shadow-glow">1</div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Vous signalez</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">Vous signalez un besoin d'intervention.</p>
            </article>
            <article class="tw-card p-7 text-center relative">
                <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-teal-500 to-cyan-600 text-white items-center justify-center text-4xl font-black shadow-lg">2</div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Nous planifions</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">La cellule hygiène planifie et confirme l'action.</p>
            </article>
            <article class="tw-card p-7 text-center relative">
                <div class="relative inline-flex w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 text-white items-center justify-center text-4xl font-black shadow-lg">3</div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Intervention</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">Intervention terrain et retour au citoyen.</p>
            </article>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
