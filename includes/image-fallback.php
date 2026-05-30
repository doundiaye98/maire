<?php
declare(strict_types=1);

/**
 * Générateur de placeholders SVG en data URI, sans dépendance externe.
 *
 * Évite tout appel à des CDN externes (Unsplash, picsum, etc.) pour
 * - rester conforme RGPD (pas de fuite d'IP visiteurs vers un tiers)
 * - garantir le rendu hors-ligne / sans connexion
 * - éviter qu'une image manquante casse la page
 *
 * Usage :
 *   $src = maire_placeholder_image('education');
 *   echo '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">';
 *
 * Catégories reconnues :
 *   actualite, salubrite, etat-civil, energie, education, sante, voirie,
 *   jeunesse, culture, infrastructure, eclairage, numerique, assainissement,
 *   cadre-de-vie, projet, equipe, administration, defaut
 */

/**
 * Retourne une data-URI SVG (image inline) adaptée à la catégorie demandée.
 *
 * @param string $categorie Slug de catégorie (insensible à la casse / accents)
 * @param int    $largeur   Largeur logique du SVG (par défaut 1200)
 * @param int    $hauteur   Hauteur logique du SVG (par défaut 800)
 */
function maire_placeholder_image(string $categorie = 'defaut', int $largeur = 1200, int $hauteur = 800): string
{
    $slug = maire_placeholder_slug_normalize($categorie);
    [$gradientFrom, $gradientTo, $accent, $icon, $label] = maire_placeholder_palette($slug);

    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" preserveAspectRatio="xMidYMid slice" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8') . '">'
        . '<defs>'
        . '<linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
        . '<stop offset="0%" stop-color="' . $gradientFrom . '"/>'
        . '<stop offset="100%" stop-color="' . $gradientTo . '"/>'
        . '</linearGradient>'
        . '<radialGradient id="glow" cx="50%" cy="40%" r="70%">'
        . '<stop offset="0%" stop-color="#ffffff" stop-opacity="0.2"/>'
        . '<stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>'
        . '</radialGradient>'
        . '</defs>'
        . '<rect width="' . $largeur . '" height="' . $hauteur . '" fill="url(#g)"/>'
        . '<rect width="' . $largeur . '" height="' . $hauteur . '" fill="url(#glow)"/>'
        // Blobs décoratifs
        . '<circle cx="' . (int) ($largeur * 0.15) . '" cy="' . (int) ($hauteur * 0.2) . '" r="' . (int) ($hauteur * 0.35) . '" fill="' . $accent . '" opacity="0.12"/>'
        . '<circle cx="' . (int) ($largeur * 0.88) . '" cy="' . (int) ($hauteur * 0.85) . '" r="' . (int) ($hauteur * 0.3) . '" fill="' . $accent . '" opacity="0.18"/>'
        // Icône centrale (forme géométrique stylisée — pas d'emoji car incompatible cross-browser SVG)
        . '<g transform="translate(' . (int) ($largeur / 2) . ' ' . (int) ($hauteur * 0.42) . ')" fill="' . $accent . '" opacity="0.85">'
        . $icon
        . '</g>'
        // Label en pied
        . '<rect x="0" y="' . ((int) ($hauteur * 0.86)) . '" width="' . $largeur . '" height="' . ((int) ($hauteur * 0.14)) . '" fill="' . $accent . '" opacity="0.92"/>'
        . '<text x="' . ((int) ($largeur / 2)) . '" y="' . ((int) ($hauteur * 0.92)) . '" font-family="\'Plus Jakarta Sans\', system-ui, sans-serif" font-size="' . (max(18, (int) ($hauteur * 0.045))) . '" font-weight="900" fill="#03241e" text-anchor="middle" letter-spacing="3">'
        . htmlspecialchars(strtoupper($label), ENT_QUOTES | ENT_XML1, 'UTF-8')
        . '</text>'
        . '<text x="' . ((int) ($largeur / 2)) . '" y="' . ((int) ($hauteur * 0.97)) . '" font-family="\'Plus Jakarta Sans\', system-ui, sans-serif" font-size="' . (max(11, (int) ($hauteur * 0.025))) . '" font-weight="700" fill="#03241e" opacity="0.7" text-anchor="middle" letter-spacing="4">'
        . 'MAIRIE DE RUFISQUE-EST'
        . '</text>'
        . '</svg>';

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/**
 * Normalise un libellé de catégorie en slug ASCII minuscule.
 */
function maire_placeholder_slug_normalize(string $raw): string
{
    $lowered = mb_strtolower(trim($raw), 'UTF-8');
    $replacements = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        "'" => '-', '’' => '-', ' ' => '-', '_' => '-',
    ];
    $slug = strtr($lowered, $replacements);
    $slug = preg_replace('/[^a-z0-9-]/', '', (string) $slug) ?: 'defaut';
    return preg_replace('/-+/', '-', (string) $slug) ?: 'defaut';
}

/**
 * Retourne la palette + icône + label associés au slug.
 *
 * @return array{0:string,1:string,2:string,3:string,4:string} [from, to, accent, iconSvg, label]
 */
function maire_placeholder_palette(string $slug): array
{
    $iconBuilding = '<rect x="-60" y="-30" width="120" height="100" rx="4"/>'
        . '<polygon points="-72,-30 0,-80 72,-30"/>'
        . '<rect x="-45" y="-5" width="20" height="40" fill="#03241e" opacity="0.45"/>'
        . '<rect x="-10" y="-5" width="20" height="40" fill="#03241e" opacity="0.45"/>'
        . '<rect x="25" y="-5" width="20" height="40" fill="#03241e" opacity="0.45"/>';
    $iconLightbulb = '<circle cx="0" cy="-15" r="40"/>'
        . '<rect x="-18" y="20" width="36" height="14" rx="3"/>'
        . '<rect x="-12" y="36" width="24" height="8" rx="2"/>';
    $iconBook = '<path d="M -65 -40 L 65 -40 L 65 50 Q 0 30 -65 50 Z"/>'
        . '<path d="M 0 -40 L 0 40" stroke="#03241e" stroke-width="3" opacity="0.4" fill="none"/>';
    $iconMedical = '<rect x="-15" y="-50" width="30" height="100" rx="4"/>'
        . '<rect x="-50" y="-15" width="100" height="30" rx="4"/>';
    $iconRoad = '<path d="M -65 60 L -25 -55 L 25 -55 L 65 60 Z"/>'
        . '<rect x="-4" y="-40" width="8" height="20" fill="#03241e" opacity="0.5"/>'
        . '<rect x="-4" y="-10" width="8" height="20" fill="#03241e" opacity="0.5"/>'
        . '<rect x="-4" y="20" width="8" height="20" fill="#03241e" opacity="0.5"/>';
    $iconLeaf = '<path d="M -50 50 Q -50 -50 50 -50 Q 50 50 -50 50 Z"/>'
        . '<path d="M -50 50 Q 0 0 50 -50" stroke="#03241e" stroke-width="3" opacity="0.4" fill="none"/>';
    $iconBroom = '<rect x="-8" y="-50" width="16" height="60" rx="3"/>'
        . '<path d="M -45 15 L 45 15 L 30 55 L -30 55 Z"/>';
    $iconDocument = '<path d="M -45 -55 L 25 -55 L 50 -30 L 50 55 L -45 55 Z"/>'
        . '<path d="M 25 -55 L 25 -30 L 50 -30" fill="#03241e" opacity="0.4"/>'
        . '<rect x="-30" y="-10" width="60" height="6" rx="2" fill="#03241e" opacity="0.4"/>'
        . '<rect x="-30" y="5" width="60" height="6" rx="2" fill="#03241e" opacity="0.4"/>'
        . '<rect x="-30" y="20" width="40" height="6" rx="2" fill="#03241e" opacity="0.4"/>';
    $iconUsers = '<circle cx="-30" cy="-20" r="20"/>'
        . '<circle cx="30" cy="-20" r="20"/>'
        . '<path d="M -60 50 Q -60 5 -30 0 Q 0 5 0 50 Z"/>'
        . '<path d="M 0 50 Q 0 5 30 0 Q 60 5 60 50 Z"/>';
    $iconSport = '<circle cx="0" cy="0" r="55"/>'
        . '<polygon points="0,-50 30,-15 15,30 -15,30 -30,-15" fill="#03241e" opacity="0.4"/>';
    $iconMusic = '<rect x="-8" y="-55" width="16" height="80" rx="3"/>'
        . '<circle cx="-25" cy="30" r="20"/>'
        . '<rect x="8" y="-55" width="40" height="10" rx="3"/>';
    $iconWater = '<path d="M 0 -55 Q 45 0 30 35 Q 15 60 0 60 Q -15 60 -30 35 Q -45 0 0 -55 Z"/>';
    $iconCircuit = '<rect x="-50" y="-50" width="100" height="100" rx="8"/>'
        . '<circle cx="-25" cy="-25" r="6" fill="#03241e" opacity="0.4"/>'
        . '<circle cx="25" cy="-25" r="6" fill="#03241e" opacity="0.4"/>'
        . '<circle cx="-25" cy="25" r="6" fill="#03241e" opacity="0.4"/>'
        . '<circle cx="25" cy="25" r="6" fill="#03241e" opacity="0.4"/>'
        . '<path d="M -25 -25 L 25 -25 M 25 -25 L 25 25 M 25 25 L -25 25 M -25 25 L -25 -25" stroke="#03241e" stroke-width="3" opacity="0.4" fill="none"/>';

    // Palette : [from, to, accent, icon, label]
    $palettes = [
        'salubrite'      => ['#0a3c34', '#1e5f48', '#fbbf24', $iconBroom,    'Salubrité'],
        'hygiene'        => ['#0a3c34', '#1e5f48', '#fbbf24', $iconBroom,    'Hygiène'],
        'etat-civil'     => ['#1e3a8a', '#3b82f6', '#fbbf24', $iconDocument, 'État civil'],
        'documents'      => ['#1e3a8a', '#3b82f6', '#fbbf24', $iconDocument, 'Documents'],
        'energie'        => ['#b45309', '#f59e0b', '#fef3c7', $iconLightbulb,'Énergie'],
        'eclairage'      => ['#b45309', '#f59e0b', '#fef3c7', $iconLightbulb,'Éclairage'],
        'eclairage-public' => ['#b45309', '#f59e0b', '#fef3c7', $iconLightbulb,'Éclairage'],
        'education'      => ['#92400e', '#d97706', '#fef9c3', $iconBook,     'Éducation'],
        'sante'          => ['#7f1d1d', '#dc2626', '#fee2e2', $iconMedical,  'Santé'],
        'voirie'         => ['#374151', '#6b7280', '#fbbf24', $iconRoad,     'Voirie'],
        'infrastructure' => ['#1e3a8a', '#3730a3', '#fbbf24', $iconBuilding, 'Infrastructure'],
        'urbanisme'      => ['#1e3a8a', '#3730a3', '#fbbf24', $iconBuilding, 'Urbanisme'],
        'jeunesse'       => ['#9d174d', '#db2777', '#fce7f3', $iconSport,    'Jeunesse'],
        'culture'        => ['#581c87', '#9333ea', '#f3e8ff', $iconMusic,    'Culture'],
        'assainissement' => ['#155e75', '#06b6d4', '#cffafe', $iconWater,    'Assainissement'],
        'cadre-de-vie'   => ['#14532d', '#16a34a', '#dcfce7', $iconLeaf,     'Cadre de vie'],
        'environnement'  => ['#14532d', '#16a34a', '#dcfce7', $iconLeaf,     'Environnement'],
        'numerique'      => ['#0c4a6e', '#0284c7', '#e0f2fe', $iconCircuit,  'Numérique'],
        'projet'         => ['#0a3c34', '#1e5f48', '#fbbf24', $iconBuilding, 'Projet municipal'],
        'projets'        => ['#0a3c34', '#1e5f48', '#fbbf24', $iconBuilding, 'Projets municipaux'],
        'actualite'      => ['#0a3c34', '#1e5f48', '#fbbf24', $iconDocument, 'Actualité'],
        'actualites'     => ['#0a3c34', '#1e5f48', '#fbbf24', $iconDocument, 'Actualité'],
        'equipe'         => ['#0a3c34', '#1e5f48', '#fbbf24', $iconUsers,    'Équipe'],
        'administration' => ['#0a3c34', '#1e5f48', '#fbbf24', $iconUsers,    'Administration'],
        'action-sociale' => ['#9d174d', '#db2777', '#fce7f3', $iconUsers,    'Action sociale'],
        'defaut'         => ['#0a3c34', '#1e5f48', '#fbbf24', $iconBuilding, 'Mairie'],
    ];

    return $palettes[$slug] ?? $palettes['defaut'];
}
