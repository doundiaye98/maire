<?php
declare(strict_types=1);

require_once __DIR__ . '/image-fallback.php';

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
            'image' => 'assets/img/Programme%20municipal%20de%20salubrite%20lance.jpg',
        ],
        [
            'id' => 2,
            'date_publication' => '2026-04-09',
            'categorie' => 'Etat civil',
            'titre' => 'Demarches d etat civil simplifiees',
            'resume' => 'Le service numerique permet de gagner du temps.',
            'contenu' => 'Les citoyens peuvent desormais pre-remplir en ligne leurs demandes d extrait et programmer un retrait en guichet. Cette evolution reduit les files d attente et facilite le suivi des dossiers grace a un numero unique.',
            'image' => 'assets/img/Demarches%20d%20etat%20civil%20simplifiees.jpg',
        ],
        [
            'id' => 3,
            'date_publication' => '2026-03-30',
            'categorie' => 'Energie',
            'titre' => 'Eclairage public solaire',
            'resume' => 'De nouveaux lampadaires sont en cours d installation.',
            'contenu' => 'Un programme d equipement progressif en lampadaires solaires est deploye sur les axes strategiques et les zones de passage. L objectif est d ameliorer la securite nocturne tout en reduisant les couts energetiques communaux.',
            'image' => 'assets/img/Eclairage%20public%20solaire.jpg',
        ],
        [
            'id' => 4,
            'date_publication' => '2026-03-20',
            'categorie' => 'Education',
            'titre' => 'Renovation de deux ecoles elementaires',
            'resume' => 'Des travaux de peinture et de mise a niveau des salles de classe ont demarre.',
            'contenu' => 'Les interventions incluent la refection des toitures legeres, la remise en etat des blocs sanitaires et l installation de nouveaux equipements pedagogiques. Les travaux sont planifies pour limiter l impact sur le calendrier scolaire.',
            'image' => 'assets/img/Renovation%20de%20deux%20ecoles%20elementaires.jpg',
        ],
        [
            'id' => 5,
            'date_publication' => '2026-03-12',
            'categorie' => 'Sante',
            'titre' => 'Campagne de consultation gratuite de quartier',
            'resume' => 'Les equipes medicales mobiles visiteront plusieurs zones pendant deux semaines.',
            'contenu' => 'Cette campagne cible prioritairement les familles vulnerables et les personnes agees avec des consultations generalistes, des depistages de base et des actions de prevention. Les relais communautaires orientent les patients vers les structures de reference si necessaire.',
            'image' => 'assets/img/sante.jpg',
        ],
        [
            'id' => 6,
            'date_publication' => '2026-03-05',
            'categorie' => 'Voirie',
            'titre' => 'Rehabilitation de la route des Pecheurs',
            'resume' => 'Le chantier prevoit un renforcement du drainage et de la signalisation.',
            'contenu' => 'Le projet de voirie comprend le reprofilage de la chaussee, l amenagement de caniveaux et la mise en place d une signalisation modernisee. Les acces riverains restent maintenus via un phasage du chantier.',
            'image' => 'assets/img/voirie.jpg',
        ],
        [
            'id' => 7,
            'date_publication' => '2026-02-22',
            'categorie' => 'Jeunesse',
            'titre' => 'Ouverture des inscriptions aux activites sportives',
            'resume' => 'Le programme communal accompagne les clubs de football, basket et athletisme.',
            'contenu' => 'Les inscriptions sont ouvertes dans les maisons de quartier avec des quotas par tranche d age. Le dispositif prevoit aussi un accompagnement logistique pour les associations sportives locales.',
            'image' => 'assets/img/jenesse.jpg',
        ],
        [
            'id' => 8,
            'date_publication' => '2026-02-15',
            'categorie' => 'Culture',
            'titre' => 'Festival culturel communal annonce',
            'resume' => 'Une semaine d expositions, de concerts et d activites pour les familles.',
            'contenu' => 'Le festival mettra en avant les artistes et artisans locaux a travers des scenes ouvertes, des ateliers et des expositions thematiques. La mairie mobilise les associations culturelles pour renforcer la participation citoyenne.',
            'image' => 'assets/img/culture.jpg',
            /** Affiche / flyer : cadrage haut en cover pour garder une image nette plein cadre */
            'image_object_position' => 'top',
        ],
    ];
}

/**
 * @param array<string, mixed> $item
 */
function maire_actualite_object_position_class(array $item): string
{
    return match (trim((string) ($item['image_object_position'] ?? ''))) {
        'top' => 'object-top',
        'bottom' => 'object-bottom',
        'left' => 'object-left',
        'right' => 'object-right',
        default => 'object-center',
    };
}

/**
 * Classes pour l'image d'actualité (cover par défaut ; contain si image_fit === 'contain').
 * Pas de scale au survol sur la photo (souvent source de flou sur les img).
 *
 * @param array<string, mixed> $item
 */
function maire_actualite_image_classes(array $item): string
{
    $fit = (string) ($item['image_fit'] ?? 'cover');
    $pos = maire_actualite_object_position_class($item);
    $base = 'absolute inset-0 w-full h-full maire-actualite-img ';
    if ($fit === 'contain') {
        return $base . 'object-contain ' . $pos;
    }

    return $base . 'object-cover ' . $pos;
}

/**
 * @param array<string, mixed> $item
 */
function maire_actualite_image_media_bg(array $item): string
{
    return ((string) ($item['image_fit'] ?? 'cover')) === 'contain'
        ? 'bg-slate-200 dark:bg-slate-900'
        : '';
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

/**
 * Catalogue éditorial des services publics affichés côté site.
 *
 * @return array<int, array{
 *   code:string,
 *   icone:string,
 *   nom:string,
 *   description:string,
 *   tag:string,
 *   lien:string,
 *   gradient:string,
 *   accent:string,
 *   highlight:?string
 * }>
 */
function maire_public_services_catalogue(): array
{
    return [
        [
            'code' => 'services_techniques',
            'icone' => '🛠️',
            'nom' => 'Division des services techniques',
            'description' => 'Voirie, eclairage public, maintenance communale et interventions techniques de proximite.',
            'tag' => 'Infrastructures',
            'lien' => 'division-services-techniques.php',
            'gradient' => 'from-slate-700 via-cyan-700 to-sky-700',
            'accent' => 'cyan',
            'highlight' => 'Prioritaire',
        ],
        [
            'code' => 'etat_civil',
            'icone' => '🪪',
            'nom' => 'Etat civil',
            'description' => 'Naissance, mariage, deces et legalisation de documents.',
            'tag' => 'Administration',
            'lien' => 'etat-civil.php',
            'gradient' => 'from-emerald-500 to-teal-600',
            'accent' => 'emerald',
            'highlight' => 'Demande en ligne',
        ],
        [
            'code' => 'sante',
            'icone' => '🏥',
            'nom' => 'Sante',
            'description' => 'Acces aux soins de proximite, prevention et campagnes sanitaires.',
            'tag' => 'Sante publique',
            'lien' => 'sante.php',
            'gradient' => 'from-rose-500 to-pink-600',
            'accent' => 'rose',
            'highlight' => null,
        ],
        [
            'code' => 'education',
            'icone' => '📚',
            'nom' => 'Education',
            'description' => 'Appui aux ecoles, equipements pedagogiques et accompagnement scolaire.',
            'tag' => 'Jeunesse',
            'lien' => 'education.php',
            'gradient' => 'from-amber-500 to-orange-500',
            'accent' => 'amber',
            'highlight' => null,
        ],
        [
            'code' => 'urbanisme',
            'icone' => '🏗️',
            'nom' => 'Urbanisme',
            'description' => 'Permis de construire, lotissements et travaux.',
            'tag' => 'Territoire',
            'lien' => 'urbanisme.php',
            'gradient' => 'from-blue-500 to-indigo-600',
            'accent' => 'blue',
            'highlight' => null,
        ],
        [
            'code' => 'hygiene',
            'icone' => '♻️',
            'nom' => 'Hygiene',
            'description' => 'Collecte, salubrite et traitement des dechets.',
            'tag' => 'Environnement',
            'lien' => 'hygiene.php',
            'gradient' => 'from-green-500 to-emerald-600',
            'accent' => 'green',
            'highlight' => null,
        ],
        [
            'code' => 'action_sociale',
            'icone' => '🤝',
            'nom' => 'Action sociale',
            'description' => 'Accompagnement des familles vulnerables et orientation sociale de proximite.',
            'tag' => 'Solidarite',
            'lien' => 'action-sociale.php',
            'gradient' => 'from-fuchsia-500 to-purple-600',
            'accent' => 'fuchsia',
            'highlight' => null,
        ],
        [
            'code' => 'vie_culturelle',
            'icone' => '🎭',
            'nom' => 'Vie culturelle',
            'description' => 'Promotion des arts, du patrimoine local et des evenements communautaires.',
            'tag' => 'Culture',
            'lien' => 'vie-culturelle.php',
            'gradient' => 'from-violet-500 to-fuchsia-600',
            'accent' => 'violet',
            'highlight' => null,
        ],
    ];
}

/**
 * @return array<string, array{
 *   code:string,
 *   icone:string,
 *   nom:string,
 *   description:string,
 *   tag:string,
 *   lien:string,
 *   gradient:string,
 *   accent:string,
 *   highlight:?string
 * }>
 */
function maire_public_services_catalogue_indexed(): array
{
    $indexed = [];
    foreach (maire_public_services_catalogue() as $service) {
        $indexed[$service['code']] = $service;
    }
    return $indexed;
}

/**
 * Statistiques de la page d'accueil — calculées à partir de la BDD si possible,
 * sinon valeur '—' (jamais de chiffres fictifs).
 *
 * @return array<int, array{valeur:string, label:string, icone:string, gradient:string}>
 */
function maire_index_stats(?PDO $pdo): array
{
    $count = static function (?PDO $pdo, string $table, string $where = ''): ?int {
        if ($pdo === null) {
            return null;
        }
        try {
            $sql = "SELECT COUNT(*) FROM $table" . ($where !== '' ? " WHERE $where" : '');
            $r = $pdo->query($sql);
            if ($r === false) {
                return null;
            }
            $n = $r->fetchColumn();
            return $n === false ? null : (int) $n;
        } catch (Throwable $e) {
            return null;
        }
    };

    $fmt = static fn (?int $n): string => $n === null ? '—' : (string) $n;

    return [
        ['valeur' => $fmt($count($pdo, 'projets')),               'label' => 'Projets municipaux',  'icone' => '🏗️', 'gradient' => 'from-blue-500 to-cyan-500'],
        ['valeur' => $fmt($count($pdo, 'standard_referentiel', "categorie='sante'")), 'label' => 'Structures de santé', 'icone' => '🏥', 'gradient' => 'from-rose-500 to-pink-500'],
        ['valeur' => $fmt($count($pdo, 'standard_referentiel', "categorie='ecole'")), 'label' => 'Écoles & centres',    'icone' => '🎓', 'gradient' => 'from-amber-500 to-orange-500'],
        ['valeur' => $fmt($count($pdo, 'services')),              'label' => 'Services en ligne',   'icone' => '⭐', 'gradient' => 'from-emerald-500 to-teal-500'],
    ];
}

/**
 * Témoignages citoyens — depuis la table 'temoignages_citoyens' si elle existe et contient des entrées
 * validées, sinon un tableau vide (à afficher comme "À venir" dans la vue).
 *
 * @return array<int, array{nom:string, role:string, texte:string, note?:int}>
 */
function maire_temoignages(?PDO $pdo): array
{
    if ($pdo === null) {
        return [];
    }
    try {
        $r = $pdo->query("SHOW TABLES LIKE 'temoignages_citoyens'");
        if ($r === false || $r->fetch() === false) {
            return [];
        }
        $st = $pdo->query("SELECT nom, role, texte, note FROM temoignages_citoyens WHERE publie = 1 ORDER BY created_at DESC LIMIT 6");
        return $st === false ? [] : ($st->fetchAll() ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Items du ticker — derniers titres d'actualités publiées + éventuelles
 * sessions de conseil municipal à venir.
 *
 * @return array<int, array{type:string, texte:string}>
 */
function maire_ticker_items(?PDO $pdo): array
{
    $items = [];
    if ($pdo === null) {
        return $items;
    }

    // Prochaine session du conseil municipal (statut annonce / en_direct)
    try {
        $st = $pdo->query("SELECT titre, date_session, statut FROM conseil_sessions WHERE statut IN ('en_direct','annonce') ORDER BY date_session ASC LIMIT 1");
        $session = $st !== false ? $st->fetch() : false;
        if ($session !== false && $session !== null) {
            $en_direct = ($session['statut'] ?? '') === 'en_direct';
            $items[] = [
                'type'  => $en_direct ? 'live' : 'agenda',
                'texte' => ($en_direct ? 'EN DIRECT · ' : '📅 ') . (string) $session['titre'] . ($session['date_session'] ? ' — ' . (string) $session['date_session'] : ''),
            ];
        }
    } catch (Throwable $e) {
        // table absente → skip
    }

    // 4 dernières actualités
    try {
        $st = $pdo->query("SELECT titre FROM actualites ORDER BY date_publication DESC LIMIT 4");
        $rows = $st !== false ? $st->fetchAll() : [];
        foreach ($rows ?: [] as $row) {
            $items[] = ['type' => 'news', 'texte' => '📰 ' . (string) $row['titre']];
        }
    } catch (Throwable $e) {
        // skip
    }

    return $items;
}
