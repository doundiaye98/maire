<?php
declare(strict_types=1);
$pageTitle = 'Service Éducation | Mairie de Rufisque-Est';
$pageDescription = "Appui aux établissements scolaires, accompagnement pédagogique et soutien à la jeunesse scolarisée de Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '📚',
    'kicker' => 'Service municipal · Jeunesse',
    'titreH1' => 'Service',
    'titreHilight' => 'Éducation',
    'titreHilightClass' => 'text-amber-200',
    'description' => 'Appui aux établissements scolaires et accompagnement durable de la jeunesse.',
    'descriptionClass' => 'text-white',
    'heroGradient' => 'from-amber-700 via-orange-700 to-amber-900',
    'blobColor' => 'bg-gold-400/30',
    'blobColor2' => 'bg-orange-400/30',
    'stats' => [
        ['valeur' => 5,  'suffix' => '+', 'label' => 'Écoles équipées'],
        ['valeur' => 1200, 'suffix' => '+', 'label' => 'Élèves accompagnés'],
        ['valeur' => 12, 'suffix' => '', 'label' => 'Partenaires'],
    ],
    'blocs' => [
        [
            'icone' => '🏫',
            'titre' => 'Soutien aux écoles',
            'gradient' => 'from-amber-500 to-orange-600',
            'puces' => [
                "Identification des besoins en équipements scolaires.",
                "Coordination avec les équipes éducatives locales.",
                "Suivi des priorités d'entretien et de réhabilitation.",
            ],
        ],
        [
            'icone' => '🎓',
            'titre' => 'Accompagnement pédagogique',
            'gradient' => 'from-yellow-500 to-amber-600',
            'puces' => [
                'Actions de renforcement pour les apprenants.',
                "Programmes d'appui à la réussite scolaire.",
                "Mobilisation de partenaires autour de l'éducation.",
            ],
        ],
        [
            'icone' => '✨',
            'titre' => 'Vie éducative locale',
            'gradient' => 'from-orange-500 to-rose-600',
            'puces' => [
                'Initiatives municipales pour la jeunesse scolarisée.',
                'Encouragement des activités citoyennes et scolaires.',
                "Promotion de l'égalité des chances à l'école.",
            ],
        ],
    ],
    'ctaLabel' => 'Faire une demande Éducation',
    'ctaLien' => 'contact.php',
]);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
