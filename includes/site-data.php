<?php
declare(strict_types=1);

function getActualitesCatalogue(): array
{
    return [
        [
            'id' => 1,
            'date_publication' => '2026-04-15',
            'categorie' => 'Salubrite',
            'titre' => 'Programme municipal de salubrite lance',
            'resume' => 'Debut des operations de nettoyage et de sensibilisation.',
            'contenu' => 'La commune lance une nouvelle phase d intervention dans plusieurs quartiers avec des equipes de terrain, des rotations de collecte renforcees et des seances de sensibilisation citoyenne. Les delegues de quartier participent a la coordination hebdomadaire pour remonter les besoins prioritaires.',
            'image' => 'https://images.unsplash.com/photo-1528323273322-d81458248d40?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 2,
            'date_publication' => '2026-04-09',
            'categorie' => 'Etat civil',
            'titre' => 'Demarches d etat civil simplifiees',
            'resume' => 'Le service numerique permet de gagner du temps.',
            'contenu' => 'Les citoyens peuvent desormais pre-remplir en ligne leurs demandes d extrait et programmer un retrait en guichet. Cette evolution reduit les files d attente et facilite le suivi des dossiers grace a un numero unique.',
            'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 3,
            'date_publication' => '2026-03-30',
            'categorie' => 'Energie',
            'titre' => 'Eclairage public solaire',
            'resume' => 'De nouveaux lampadaires sont en cours d installation.',
            'contenu' => 'Un programme d equipement progressif en lampadaires solaires est deploye sur les axes strategiques et les zones de passage. L objectif est d ameliorer la securite nocturne tout en reduisant les couts energetiques communaux.',
            'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 4,
            'date_publication' => '2026-03-20',
            'categorie' => 'Education',
            'titre' => 'Renovation de deux ecoles elementaires',
            'resume' => 'Des travaux de peinture et de mise a niveau des salles de classe ont demarre.',
            'contenu' => 'Les interventions incluent la refection des toitures legeres, la remise en etat des blocs sanitaires et l installation de nouveaux equipements pedagogiques. Les travaux sont planifies pour limiter l impact sur le calendrier scolaire.',
            'image' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 5,
            'date_publication' => '2026-03-12',
            'categorie' => 'Sante',
            'titre' => 'Campagne de consultation gratuite de quartier',
            'resume' => 'Les equipes medicales mobiles visiteront plusieurs zones pendant deux semaines.',
            'contenu' => 'Cette campagne cible prioritairement les familles vulnerables et les personnes agees avec des consultations generalistes, des depistages de base et des actions de prevention. Les relais communautaires orientent les patients vers les structures de reference si necessaire.',
            'image' => 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 6,
            'date_publication' => '2026-03-05',
            'categorie' => 'Voirie',
            'titre' => 'Rehabilitation de la route des Pecheurs',
            'resume' => 'Le chantier prevoit un renforcement du drainage et de la signalisation.',
            'contenu' => 'Le projet de voirie comprend le reprofilage de la chaussee, l amenagement de caniveaux et la mise en place d une signalisation modernisee. Les acces riverains restent maintenus via un phasage du chantier.',
            'image' => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 7,
            'date_publication' => '2026-02-22',
            'categorie' => 'Jeunesse',
            'titre' => 'Ouverture des inscriptions aux activites sportives',
            'resume' => 'Le programme communal accompagne les clubs de football, basket et athletisme.',
            'contenu' => 'Les inscriptions sont ouvertes dans les maisons de quartier avec des quotas par tranche d age. Le dispositif prevoit aussi un accompagnement logistique pour les associations sportives locales.',
            'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1200&q=80',
        ],
        [
            'id' => 8,
            'date_publication' => '2026-02-15',
            'categorie' => 'Culture',
            'titre' => 'Festival culturel communal annonce',
            'resume' => 'Une semaine d expositions, de concerts et d activites pour les familles.',
            'contenu' => 'Le festival mettra en avant les artistes et artisans locaux a travers des scenes ouvertes, des ateliers et des expositions thematiques. La mairie mobilise les associations culturelles pour renforcer la participation citoyenne.',
            'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=1200&q=80',
        ],
    ];
}

function getActualites(?PDO $pdo): array
{
    if ($pdo !== null) {
        $query = $pdo->query("SELECT titre, resume, date_publication FROM actualites ORDER BY date_publication DESC LIMIT 6");
        $rows = $query->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
    }

    return [
        [
            'titre' => 'Lancement du programme de proprete urbaine',
            'resume' => 'La mairie demarre une campagne hebdomadaire de nettoyage avec les quartiers et les associations.',
            'date_publication' => '2026-04-15',
        ],
        [
            'titre' => 'Ouverture des inscriptions a l etat civil en ligne',
            'resume' => 'Les demandes d extraits de naissance et de mariage peuvent desormais etre preparees en ligne.',
            'date_publication' => '2026-04-09',
        ],
        [
            'titre' => 'Rehabilitation de l eclairage public',
            'resume' => 'Les nouveaux points lumineux solaires sont installes progressivement dans plusieurs zones.',
            'date_publication' => '2026-03-30',
        ],
    ];
}

function getServices(?PDO $pdo): array
{
    if ($pdo !== null) {
        $query = $pdo->query("SELECT nom, description, icone FROM services ORDER BY id DESC LIMIT 8");
        $rows = $query->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
    }

    return [
        ['nom' => 'Etat civil', 'description' => 'Naissance, mariage, deces, legalisation de documents.', 'icone' => 'SC'],
        ['nom' => 'Urbanisme', 'description' => 'Permis de construire, lotissements et suivi des travaux.', 'icone' => 'UR'],
        ['nom' => 'Action sociale', 'description' => 'Accompagnement des familles vulnerables et aides locales.', 'icone' => 'AS'],
        ['nom' => 'Hygiene et salubrite', 'description' => 'Collecte des dechets et sensibilisation environnementale.', 'icone' => 'HS'],
    ];
}
