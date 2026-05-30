<?php
declare(strict_types=1);
$pageTitle = 'Service Santé | Mairie de Rufisque-Est';
$pageDescription = "Orientation sanitaire, soins de proximité et prévention pour les habitants de Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🏥',
    'kicker' => 'Service municipal · Santé publique',
    'titreH1' => 'Service',
    'titreHilight' => 'Santé',
    'titreHilightClass' => 'text-rose-200',
    'description' => 'Un accompagnement clair pour orienter chaque citoyen vers la bonne prise en charge sanitaire.',
    'descriptionClass' => 'text-white',
    'heroGradient' => 'from-rose-700 via-rose-800 to-pink-900',
    'blobColor' => 'bg-pink-400/30',
    'blobColor2' => 'bg-red-400/30',
    'stats' => [
        ['valeur' => 24, 'suffix' => 'h/24', 'label' => 'Accueil urgences'],
        ['valeur' => 8,  'suffix' => '', 'label' => 'Centres partenaires'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Orientation'],
    ],
    'blocs' => [
        [
            'icone' => '🚑',
            'titre' => 'Urgences et orientation rapide',
            'gradient' => 'from-red-500 to-rose-600',
            'puces' => [
                'Accueil et orientation vers la structure sanitaire la plus adaptée.',
                'Assistance prioritaire pour situations urgentes signalées à la mairie.',
                'Coordination avec les services de santé territoriaux.',
            ],
        ],
        [
            'icone' => '🩺',
            'titre' => 'Consultation et soins de proximité',
            'gradient' => 'from-rose-500 to-pink-600',
            'puces' => [
                'Information sur les parcours de consultation générale et spécialisée.',
                'Orientation des familles vers les points de soins de quartier.',
                'Accompagnement administratif pour faciliter les démarches de santé.',
            ],
        ],
        [
            'icone' => '🌿',
            'titre' => 'Prévention et santé communautaire',
            'gradient' => 'from-pink-500 to-fuchsia-600',
            'puces' => [
                'Campagnes de sensibilisation : hygiène, nutrition et santé maternelle.',
                'Activités de prévention organisées avec les acteurs locaux.',
                'Suivi de proximité pour renforcer la santé publique communale.',
            ],
        ],
    ],
    'ctaLabel' => 'Demander une orientation santé',
    'ctaLien' => 'contact.php',
]);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
