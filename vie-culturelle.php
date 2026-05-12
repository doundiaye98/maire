<?php
declare(strict_types=1);
$pageTitle = 'Vie culturelle | Mairie de Rufisque-Est';
$pageDescription = 'Promotion des arts, du patrimoine local et des événements culturels citoyens à Rufisque-Est.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🎭',
    'kicker' => 'Service municipal · Culture',
    'titreH1' => 'Vie',
    'titreHilight' => 'culturelle',
    'description' => 'Promotion des arts, du patrimoine local et des événements culturels citoyens.',
    'heroGradient' => 'from-violet-700 via-fuchsia-800 to-purple-900',
    'blobColor' => 'bg-violet-400/30',
    'blobColor2' => 'bg-fuchsia-400/30',
    'stats' => [
        ['valeur' => 30, 'suffix' => '+', 'label' => 'Événements/an'],
        ['valeur' => 25, 'suffix' => '', 'label' => 'Associations'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Accès libre'],
    ],
    'blocs' => [
        [
            'icone' => '🎨',
            'titre' => 'Programmation culturelle',
            'gradient' => 'from-violet-500 to-fuchsia-600',
            'puces' => [
                'Calendrier des activités et manifestations locales.',
                'Valorisation des initiatives artistiques de quartier.',
                'Promotion des rendez-vous culturels de la commune.',
            ],
        ],
        [
            'icone' => '🏛️',
            'titre' => 'Patrimoine et identité',
            'gradient' => 'from-fuchsia-500 to-pink-600',
            'puces' => [
                'Protection et promotion du patrimoine communal.',
                'Mise en lumière des expressions culturelles locales.',
                'Actions de transmission intergénérationnelle.',
            ],
        ],
        [
            'icone' => '🤝',
            'titre' => 'Partenariats culturels',
            'gradient' => 'from-purple-500 to-violet-600',
            'puces' => [
                'Soutien aux associations et acteurs culturels.',
                'Coopération avec écoles, maisons de jeunes et collectifs.',
                'Développement de projets culturels participatifs.',
            ],
        ],
    ],
    'ctaLabel' => 'Proposer une action culturelle',
    'ctaLien' => 'contact.php',
]);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
