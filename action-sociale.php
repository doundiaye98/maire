<?php
declare(strict_types=1);
$pageTitle = 'Service Action sociale | Mairie de Rufisque-Est';
$pageDescription = "Accompagnement social de proximité pour les familles et publics vulnérables à Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🤝',
    'kicker' => 'Service municipal · Solidarité',
    'titreH1' => 'Action',
    'titreHilight' => 'sociale',
    'titreHilightClass' => 'text-fuchsia-200',
    'description' => 'Accompagnement social de proximité pour les familles et publics vulnérables.',
    'descriptionClass' => 'text-white',
    'heroGradient' => 'from-fuchsia-700 via-purple-800 to-fuchsia-900',
    'blobColor' => 'bg-pink-400/30',
    'blobColor2' => 'bg-fuchsia-400/30',
    'stats' => [
        ['valeur' => 350, 'suffix' => '+', 'label' => 'Familles aidées'],
        ['valeur' => 15,  'suffix' => '',  'label' => 'Partenaires'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Écoute'],
    ],
    'blocs' => [
        [
            'icone' => '👥',
            'titre' => 'Accueil social',
            'gradient' => 'from-fuchsia-500 to-purple-600',
            'puces' => [
                'Orientation vers les aides municipales et partenaires.',
                'Écoute des besoins sociaux prioritaires des ménages.',
                'Accompagnement de première ligne pour les urgences sociales.',
            ],
        ],
        [
            'icone' => '🏠',
            'titre' => 'Suivi des familles',
            'gradient' => 'from-purple-500 to-violet-600',
            'puces' => [
                'Appui administratif pour les dossiers sociaux.',
                'Coordination avec les services de santé et éducation.',
                'Prise en charge orientée selon la situation du foyer.',
            ],
        ],
        [
            'icone' => '🤲',
            'titre' => 'Partenariats locaux',
            'gradient' => 'from-pink-500 to-fuchsia-600',
            'puces' => [
                'Travail avec associations et structures communautaires.',
                'Mise en relation avec les acteurs de solidarité.',
                'Actions communes pour renforcer la cohésion sociale.',
            ],
        ],
    ],
    'ctaLabel' => 'Faire une demande sociale',
    'ctaLien' => 'contact.php',
]);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
