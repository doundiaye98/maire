<?php
declare(strict_types=1);
$pageTitle = 'Service Urbanisme | Mairie de Rufisque-Est';
$pageDescription = 'Permis de construire, planification urbaine et conformité des travaux à Rufisque-Est.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🏗️',
    'kicker' => 'Service municipal · Territoire',
    'titreH1' => 'Service',
    'titreHilight' => 'Urbanisme',
    'titreHilightClass' => 'text-cyan-200',
    'description' => 'Organisation du territoire, suivi des permis et encadrement des aménagements.',
    'descriptionClass' => 'text-white',
    'heroGradient' => 'from-blue-700 via-indigo-800 to-blue-900',
    'blobColor' => 'bg-cyan-400/30',
    'blobColor2' => 'bg-blue-400/30',
    'stats' => [
        ['valeur' => 12, 'suffix' => '', 'label' => 'Quartiers suivis'],
        ['valeur' => 250, 'suffix' => '+', 'label' => 'Permis traités'],
        ['valeur' => 5,  'suffix' => 'j',  'label' => 'Délai moyen'],
    ],
    'blocs' => [
        [
            'icone' => '📜',
            'titre' => 'Permis et autorisations',
            'gradient' => 'from-blue-500 to-indigo-600',
            'puces' => [
                'Dépôt des demandes de permis de construire.',
                'Vérification des pièces et orientation des dossiers.',
                "Suivi des étapes jusqu'à la décision administrative.",
            ],
        ],
        [
            'icone' => '🗺️',
            'titre' => 'Planification urbaine',
            'gradient' => 'from-indigo-500 to-violet-600',
            'puces' => [
                'Information sur le développement urbain communal.',
                'Encadrement des lotissements et aménagements.',
                'Coordination avec les services techniques territoriaux.',
            ],
        ],
        [
            'icone' => '✅',
            'titre' => 'Conformité des travaux',
            'gradient' => 'from-cyan-500 to-blue-600',
            'puces' => [
                'Contrôle du respect de la réglementation locale.',
                'Accompagnement pour régulariser les situations complexes.',
                'Orientation des citoyens avant lancement des travaux.',
            ],
        ],
    ],
    'ctaLabel' => 'Lancer une demande Urbanisme',
    'ctaLien' => 'contact.php',
]);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
