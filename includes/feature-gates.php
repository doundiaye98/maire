<?php
declare(strict_types=1);

/**
 * Feature-gating par palier d'abonnement communal.
 *
 * S'appuie sur `commune-abonnement.php` (palier effectif simple|standard|premium)
 * pour activer/désactiver les modules :
 *  - simple   = site vitrine + bibliothèque documents + max 2 agents
 *  - standard = + signalements citoyens, comptes habitants, notifications, agents illimités
 *  - premium  = + votes électroniques, IA, statistiques avancées, paiements en ligne
 *
 * Utilisation typique :
 *   require_once __DIR__ . '/../includes/feature-gates.php';
 *   if (!maire_feature_disponible($pdo, 'signalements_citoyens')) {
 *       maire_render_paywall_page('signalements_citoyens');
 *       exit;
 *   }
 */

require_once __DIR__ . '/abonnement-actif-sync.php';
require_once __DIR__ . '/commune-abonnement.php';

/**
 * Matrice features → palier minimum requis.
 * Toute feature absente de cette matrice est considérée comme accessible à tous (simple).
 */
const MAIRE_FEATURES_PALIER_MIN = [
    // Modules habitants
    'comptes_citoyens'        => 'standard',
    'signalements_citoyens'   => 'standard',
    'demandes_administratives'=> 'standard',
    'reservation_documents'   => 'standard',

    // Communication
    'notifications_email'     => 'standard',
    'notifications_sms'       => 'standard',
    'broadcast_urgences'      => 'standard',

    // Paiements en ligne (services communaux : documents express, réservations) — palier Standard
    'paiements_en_ligne'      => 'standard',
    // Paiement des taxes locales (intégration fiscale plus poussée) — palier Premium
    'taxes_locales_en_ligne'  => 'premium',

    // Démocratie locale
    'votes_electroniques'     => 'premium',
    'consultations_citoyennes'=> 'premium',

    // Avancé
    'ia_assistant'            => 'premium',
    'streaming_conseils'      => 'premium',
    'api_publique'            => 'premium',
    'statistiques_avancees'   => 'premium',
    'multi_agents_illimites'  => 'standard',
];

/** Libellés humains des features pour les paywalls */
const MAIRE_FEATURES_LIBELLES = [
    'comptes_citoyens'        => 'Comptes habitants',
    'signalements_citoyens'   => 'Signalements citoyens',
    'demandes_administratives'=> 'Demandes administratives en ligne',
    'reservation_documents'   => 'Réservation de documents',
    'notifications_email'     => 'Notifications email',
    'notifications_sms'       => 'Notifications SMS',
    'broadcast_urgences'      => 'Diffusion d’urgences (météo, coupures)',
    'paiements_en_ligne'      => 'Paiements en ligne',
    'taxes_locales_en_ligne'  => 'Paiement des taxes locales en ligne',
    'votes_electroniques'     => 'Votes électroniques',
    'consultations_citoyennes'=> 'Consultations citoyennes',
    'ia_assistant'            => 'Assistant IA pour citoyens',
    'streaming_conseils'      => 'Streaming des conseils municipaux',
    'api_publique'            => 'API publique d’intégration',
    'statistiques_avancees'   => 'Statistiques avancées',
    'multi_agents_illimites'  => 'Comptes agents illimités',
];

const MAIRE_AGENTS_MAX_SIMPLE = 2;

/**
 * Palier minimum exigé pour une feature (par défaut 'simple' si non listée).
 */
function maire_feature_palier_minimum(string $feature): string
{
    return MAIRE_FEATURES_PALIER_MIN[$feature] ?? 'simple';
}

function maire_feature_libelle(string $feature): string
{
    return MAIRE_FEATURES_LIBELLES[$feature] ?? ucfirst(str_replace('_', ' ', $feature));
}

/**
 * Renvoie le palier de la commune (toujours non-null : 'simple' si aucun abonnement actif).
 */
function maire_palier_commune_actuel(PDO $pdo): string
{
    $palier = maire_commune_palier_effectif($pdo);
    return $palier ?? 'simple';
}

/**
 * Indique si une feature est accessible avec le palier actuel de la commune.
 */
function maire_feature_disponible(PDO $pdo, string $feature): bool
{
    $palierCommune = maire_palier_commune_actuel($pdo);
    $minimum = maire_feature_palier_minimum($feature);
    return maire_palier_couvre($palierCommune, $minimum);
}

/**
 * Variante sans accès DB (utilise un palier déjà calculé).
 */
function maire_feature_disponible_palier(string $palierCommune, string $feature): bool
{
    $minimum = maire_feature_palier_minimum($feature);
    return maire_palier_couvre($palierCommune, $minimum);
}

/**
 * Nombre maximum d'agents (admins compris) autorisés pour le palier donné.
 */
function maire_max_agents_pour_palier(string $palier): ?int
{
    return $palier === 'simple' ? MAIRE_AGENTS_MAX_SIMPLE : null; // null = illimité
}

/**
 * Libellé humain d'un palier.
 */
function maire_palier_libelle_court(string $palier): string
{
    return match ($palier) {
        'premium' => 'Premium',
        'standard' => 'Standard',
        default => 'Simple',
    };
}

/**
 * Rend un encart HTML "paywall" expliquant pourquoi une feature est verrouillée.
 *
 * @param string $feature        clé de feature
 * @param string $palierCommune  palier actuel (simple/standard/premium)
 * @param string $contexte       'public' (citoyen visiteur) ou 'admin' (agent municipal)
 */
function maire_render_paywall(string $feature, string $palierCommune, string $contexte = 'public'): string
{
    $libelle = maire_feature_libelle($feature);
    $minimum = maire_feature_palier_minimum($feature);
    $palierAffiche = maire_palier_libelle_court($palierCommune);
    $minimumAffiche = maire_palier_libelle_court($minimum);

    $urlOffres = $contexte === 'admin' ? '../offres.php' : 'offres.php';
    $urlContact = $contexte === 'admin' ? '../contact.php' : 'contact.php';

    if ($contexte === 'admin') {
        $message = '<p>Cette fonctionnalité n’est pas incluse dans la formule communale actuelle de votre mairie.</p>'
                 . '<p>Votre commune est actuellement en formule <strong>' . htmlspecialchars($palierAffiche, ENT_QUOTES, 'UTF-8') . '</strong>. '
                 . 'La fonctionnalité <strong>' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . '</strong> requiert le palier <strong>' . htmlspecialchars($minimumAffiche, ENT_QUOTES, 'UTF-8') . '</strong> ou supérieur.</p>'
                 . '<p>Pour activer ce module, mettez à niveau l’abonnement communal depuis « Comptes &amp; abonnement ».</p>';
        $cta = '<a class="btn btn-primary" href="abonnements.php">Mettre à niveau l’abonnement</a>'
             . ' <a class="btn btn-outline-dark" href="' . htmlspecialchars($urlOffres, ENT_QUOTES, 'UTF-8') . '">Comparer les formules</a>';
    } else {
        $message = '<p>Ce service n’est pas encore activé par votre mairie.</p>'
                 . '<p>La fonctionnalité <strong>' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . '</strong> est disponible à partir de la formule communale <strong>' . htmlspecialchars($minimumAffiche, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                 . '<p>Pour demander son activation, vous pouvez contacter directement la mairie.</p>';
        $cta = '<a class="btn btn-primary" href="' . htmlspecialchars($urlContact, ENT_QUOTES, 'UTF-8') . '">Contacter la mairie</a>'
             . ' <a class="btn btn-outline-dark" href="' . htmlspecialchars($urlOffres, ENT_QUOTES, 'UTF-8') . '">Voir les formules</a>';
    }

    return '<article class="card" style="border:2px solid #f59e0b;background:#fffbeb;">'
         . '<h2 style="margin-top:0;">🔒 ' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . ' — fonctionnalité non incluse</h2>'
         . $message
         . '<div class="detail-actions" style="margin-top:0.8rem;">' . $cta . '</div>'
         . '</article>';
}

/**
 * Rend une page complète "paywall" (header + paywall + footer).
 * À appeler depuis la page qui doit être bloquée, suivi de exit;
 */
function maire_render_paywall_page(string $feature, string $palierCommune, string $contexte = 'public'): void
{
    if (!headers_sent()) {
        http_response_code(402); // Payment Required
    }
    $pageTitle = '🔒 ' . maire_feature_libelle($feature) . ' — non incluse';
    require __DIR__ . '/header.php';
    echo '<main><section class="section-shell page-intro"><div class="container">';
    echo maire_render_paywall($feature, $palierCommune, $contexte);
    echo '</div></section></main>';
    require __DIR__ . '/footer.php';
}

/**
 * Liste les features disponibles/verrouillées pour un palier donné, utile pour l'admin.
 *
 * @return array{disponibles: list<string>, verrouillees: list<string>}
 */
function maire_features_etat_pour_palier(string $palier): array
{
    $disponibles = [];
    $verrouillees = [];
    foreach (MAIRE_FEATURES_PALIER_MIN as $feature => $palierMin) {
        if (maire_palier_couvre($palier, $palierMin)) {
            $disponibles[] = $feature;
        } else {
            $verrouillees[] = $feature;
        }
    }
    return ['disponibles' => $disponibles, 'verrouillees' => $verrouillees];
}
