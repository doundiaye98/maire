<?php
declare(strict_types=1);
$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require_once __DIR__ . '/includes/super-admin-session.php';
require_once __DIR__ . '/includes/etat-civil-demande.php';
$maireCanVoirOffres = (($_SESSION['subscriber_role'] ?? '') === 'admin') || maire_super_admin_session_valid();
require __DIR__ . '/includes/header.php';

$subscriber = $subscriberAccount ?? [
    'email' => (string) ($_SESSION['subscriber_email'] ?? ''),
    'date_debut' => '',
    'date_fin' => '',
    'plan' => 'municipal_standard',
    'role' => (string) ($_SESSION['subscriber_role'] ?? 'subscriber'),
];

$maireCommunePalier = (string) ($GLOBALS['maire_commune_palier'] ?? 'standard');

$ecoles = [];
$structuresSante = [];
$servicesPremium = [];
$mesDemandesEtatCivil = [];
$hubActualites = [];
$statsDossiers = ['total' => 0, 'recu' => 0, 'verification' => 0, 'pret' => 0];
$dashParType = [];
$dashParMois = [];

$finTs = strtotime((string) ($subscriber['date_fin'] ?: date('Y-m-d')) . ' 23:59:59');
if ($finTs === false) {
    $finTs = time() + 86400 * 30;
}
$joursRestants = max(0, (int) ceil(($finTs - time()) / 86400));
$cycleJours = 30;
$pourcentageCycle = $cycleJours > 0 ? min(100, (int) round(($joursRestants / $cycleJours) * 100)) : 0;

if (isset($pdo) && $pdo !== null) {
    maire_ensure_demandes_etat_civil_tables($pdo);

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS standard_hub_actualites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titre VARCHAR(200) NOT NULL,
                resume VARCHAR(500) NOT NULL,
                lien VARCHAR(220) DEFAULT NULL,
                badge VARCHAR(24) NOT NULL DEFAULT 'info',
                published_at DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $hubActualites = $pdo->query(
            "SELECT titre, resume, lien, badge, published_at FROM standard_hub_actualites ORDER BY published_at DESC, id DESC LIMIT 8"
        )->fetchAll();
    } catch (Throwable $e) {
        $hubActualites = [];
    }

    try {
        $rowsType = $pdo->query("
            SELECT type_demande AS t, COUNT(*) AS c
            FROM demandes_etat_civil
            GROUP BY type_demande
            ORDER BY c DESC
            LIMIT 10
        ")->fetchAll();
        $maxT = 1;
        foreach ($rowsType as $r) {
            $maxT = max($maxT, (int) ($r['c'] ?? 0));
        }
        foreach ($rowsType as $r) {
            $c = (int) ($r['c'] ?? 0);
            $typeCode = trim((string) ($r['t'] ?? ''));
            if ($typeCode === '') {
                $label = '(Type non renseigne)';
            } else {
                $label = maire_libelle_type_demande_etat_civil($typeCode);
            }
            $dashParType[] = [
                'label' => $label,
                'count' => $c,
                'pct' => $maxT > 0 ? (int) round(100 * $c / $maxT) : 0,
            ];
        }
    } catch (Throwable $e) {
        $dashParType = [];
    }

    try {
        $rowsMois = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
            FROM demandes_etat_civil
            WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 4 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY ym ASC
        ")->fetchAll();
        $maxM = 1;
        foreach ($rowsMois as $r) {
            $maxM = max($maxM, (int) ($r['c'] ?? 0));
        }
        $moisFr = [
            '01' => 'Janv', '02' => 'Fev', '03' => 'Mars', '04' => 'Avr', '05' => 'Mai', '06' => 'Juin',
            '07' => 'Juil', '08' => 'Aout', '09' => 'Sept', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
        ];
        foreach ($rowsMois as $r) {
            $c = (int) ($r['c'] ?? 0);
            $ym = (string) ($r['ym'] ?? '');
            $parts = explode('-', $ym);
            $label = $ym;
            if (count($parts) === 2) {
                $label = ($moisFr[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
            }
            $dashParMois[] = [
                'label' => $label,
                'count' => $c,
                'pct' => $maxM > 0 ? (int) round(100 * $c / $maxM) : 0,
            ];
        }
    } catch (Throwable $e) {
        $dashParMois = [];
    }

    try {
        $rows = $pdo->query("SELECT categorie, nom, localisation, niveau_ou_type, horaires FROM standard_referentiel ORDER BY id DESC")->fetchAll();
        foreach ($rows as $row) {
            $categorie = (string) ($row['categorie'] ?? '');
            if ($categorie === 'ecole') {
                $ecoles[] = [
                    'nom' => (string) $row['nom'],
                    'localisation' => (string) $row['localisation'],
                    'niveau' => (string) ($row['niveau_ou_type'] ?? 'Ecole'),
                ];
            } elseif ($categorie === 'sante') {
                $structuresSante[] = [
                    'nom' => (string) $row['nom'],
                    'localisation' => (string) $row['localisation'],
                    'type' => (string) ($row['niveau_ou_type'] ?? 'Structure de sante'),
                ];
            } elseif ($categorie === 'service') {
                $servicesPremium[] = [
                    'service' => (string) $row['nom'],
                    'point_service' => (string) $row['localisation'],
                    'horaires' => (string) ($row['horaires'] ?? '08h00 - 16h00'),
                ];
            }
        }
    } catch (Throwable $exception) {
        $ecoles = [
            ['nom' => 'Ecole Elementaire Rufisque-Est 1', 'localisation' => 'Quartier Rufisque-Est Centre', 'niveau' => 'Elementaire'],
            ['nom' => 'CEM Rufisque-Est', 'localisation' => 'Axe principal Rufisque-Est', 'niveau' => 'College'],
            ['nom' => 'Lycee Municipal de Rufisque-Est', 'localisation' => 'Zone administrative', 'niveau' => 'Lycee'],
        ];
        $structuresSante = [
            ['nom' => 'Centre de Sante Rufisque-Est', 'localisation' => 'Boulevard de la Commune', 'type' => 'Centre de sante'],
            ['nom' => 'Poste de Sante Keury Souf', 'localisation' => 'Quartier Keury Souf', 'type' => 'Poste de sante'],
            ['nom' => 'Poste de Sante Darou Rahmane', 'localisation' => 'Secteur Darou Rahmane', 'type' => 'Poste de sante'],
        ];
        $servicesPremium = [
            ['service' => 'Etat civil', 'point_service' => 'Guichet principal municipal', 'horaires' => '08h00 - 16h00'],
            ['service' => 'Urbanisme', 'point_service' => 'Direction technique communale', 'horaires' => '08h30 - 16h30'],
            ['service' => 'Action sociale', 'point_service' => 'Maison des services sociaux', 'horaires' => '08h00 - 15h30'],
            ['service' => 'Hygiene', 'point_service' => 'Cellule environnement et salubrite', 'horaires' => '08h00 - 16h00'],
        ];
    }

    $emailAbonne = $subscriber['email'];
    if ($emailAbonne !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT reference_dossier, type_demande, statut, created_at
                FROM demandes_etat_civil
                WHERE email = :email
                ORDER BY created_at DESC
                LIMIT 12
            ");
            $stmt->execute(['email' => $emailAbonne]);
            $mesDemandesEtatCivil = $stmt->fetchAll();

            $agg = $pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'recu' THEN 1 ELSE 0 END) AS recu,
                    SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) AS verification,
                    SUM(CASE WHEN statut = 'pret' THEN 1 ELSE 0 END) AS pret
                FROM demandes_etat_civil
                WHERE email = :email
            ");
            $agg->execute(['email' => $emailAbonne]);
            $statsRow = $agg->fetch();
            if (is_array($statsRow)) {
                $statsDossiers = [
                    'total' => (int) ($statsRow['total'] ?? 0),
                    'recu' => (int) ($statsRow['recu'] ?? 0),
                    'verification' => (int) ($statsRow['verification'] ?? 0),
                    'pret' => (int) ($statsRow['pret'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            $mesDemandesEtatCivil = [];
        }
    }
}

$planLabel = match (true) {
    str_contains((string) $subscriber['plan'], 'municipal_premium') => 'Premium (commune)',
    str_contains((string) $subscriber['plan'], 'municipal_standard') => 'Standard (commune)',
    str_contains((string) $subscriber['plan'], 'municipal_simple') => 'Simple (commune)',
    str_contains((string) $subscriber['plan'], 'standard_plus_mensuel') => 'Compte agent (forfait mensuel)',
    str_contains((string) $subscriber['plan'], 'standard_plus') => 'Compte agent (hérité Standard+)',
    str_contains((string) $subscriber['plan'], 'plus') => 'Compte agent (hérité Standard+)',
    str_contains((string) $subscriber['plan'], 'mensuel') => 'Compte agent (mensuel)',
    default => 'Standard (commune)',
};
if (($subscriber['role'] ?? '') === 'citoyen') {
    $planLabel = 'Offre communale active';
}

function std_badge_class(string $badge): string
{
    return match ($badge) {
        'success' => 'std-feed-badge std-feed-badge--success',
        'alert' => 'std-feed-badge std-feed-badge--alert',
        default => 'std-feed-badge std-feed-badge--info',
    };
}

function std_badge_label(string $badge): string
{
    return match ($badge) {
        'success' => 'Positif',
        'alert' => 'Important',
        default => 'Info',
    };
}
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-20 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-[2fr_1fr] gap-8 items-end">
                <div>
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                        <span aria-hidden="true">🏢</span>
                        <?php echo ($subscriber['role'] ?? '') === 'citoyen' ? 'Portail communal — accès citoyen' : 'Espace agent / institutionnel'; ?>
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                        Portail<br><span class="maire-text-gradient">numérique communal</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed max-w-2xl">
                        Répertoire des écoles, santé et services, démarches d'état civil, exports et tableaux — accessibles selon les choix numériques de votre commune.
                    </p>
                </div>
                <aside class="rounded-3xl bg-white/10 backdrop-blur-md border border-white/20 p-6">
                    <p class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gold-400 text-mairie-950 font-black text-xs uppercase tracking-wide mb-3"><?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (($subscriber['email'] ?? '') !== ''): ?>
                        <p class="text-sm text-white/90 font-bold break-words mb-2"><?php echo htmlspecialchars($subscriber['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p class="text-sm text-white/70 mb-2 italic">Accès citoyen — connectez-vous avec un compte mairie pour vos dossiers.</p>
                    <?php endif; ?>
                    <p class="text-xs text-white/80 mb-3">Période communale&nbsp;: du <strong><?php echo htmlspecialchars($subscriber['date_debut'], ENT_QUOTES, 'UTF-8'); ?></strong> au <strong><?php echo htmlspecialchars($subscriber['date_fin'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-white/15">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/15 text-xs font-bold text-white">
                            <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                            <?php echo (int) $joursRestants; ?>j restant<?php echo $joursRestants > 1 ? 's' : ''; ?>
                        </span>
                        <?php if (($subscriber['role'] ?? '') === 'citoyen'): ?>
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/20 text-xs font-bold text-white transition" href="abonnement.php">Connexion personnel mairie →</a>
                        <?php else: ?>
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-gold-400 hover:bg-gold-300 text-xs font-black text-mairie-950 transition" href="abonnement.php">Renouveler →</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <!-- NAV INTERNE -->
            <nav aria-label="Sections de la page" class="flex flex-wrap gap-2 mt-8 pt-6 border-t border-white/15">
                <?php
                $sections = [
                    'std-synthese' => 'Synthèse',
                    'std-dossiers' => 'Dossiers',
                    'std-fil' => 'Fil',
                    'std-pilotage' => 'Pilotage',
                    'repertoire' => 'Répertoire',
                    'std-etat-civil' => 'État civil',
                    'std-charts' => 'Indicateurs',
                ];
                if ($maireCommunePalier === 'premium') {
                    $sections['std-premium'] = 'Modules étendus';
                }
                $sections['std-actions'] = 'Actions';
                foreach ($sections as $href => $label):
                ?>
                    <a href="#<?php echo $href; ?>" class="px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/15 border border-white/10 text-xs font-bold text-white/90 transition"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- SYNTHÈSE -->
    <section id="std-synthese" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[2fr_1fr] gap-6 mb-10">
                <article class="tw-card p-7 relative overflow-hidden">
                    <div class="absolute -top-8 -right-8 w-40 h-40 bg-gradient-to-br from-mairie-700 to-mairie-900 opacity-10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="relative">
                        <span class="maire-tag bg-mairie-100 dark:bg-mairie-900/50 text-mairie-700 dark:text-mairie-300 mb-3">📊 Tableau de bord</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Cycle communal en cours</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Renouvelez avant la fin du cycle pour conserver exports, référentiel détaillé et suivi des dossiers liés à votre adresse e-mail.</p>
                        <div class="w-full h-3 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden" role="progressbar" aria-valuenow="<?php echo (int) $pourcentageCycle; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-mairie-500 rounded-full transition-all" style="width: <?php echo (int) $pourcentageCycle; ?>%"></div>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-500 mt-2">Estimation sur <?php echo (int) $cycleJours; ?> jours — environ <strong class="text-mairie-700 dark:text-mairie-300"><?php echo (int) $pourcentageCycle; ?>%</strong> du cycle restant.</p>
                    </div>
                </article>

                <div class="grid grid-cols-3 gap-3">
                    <article class="tw-card p-4 text-center">
                        <p class="text-3xl font-black text-mairie-700 dark:text-mairie-300"><span class="maire-counter" data-target="<?php echo count($ecoles); ?>" data-suffix="">0</span></p>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 mt-1">Écoles</p>
                    </article>
                    <article class="tw-card p-4 text-center">
                        <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400"><span class="maire-counter" data-target="<?php echo count($structuresSante); ?>" data-suffix="">0</span></p>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 mt-1">Santé</p>
                    </article>
                    <article class="tw-card p-4 text-center">
                        <p class="text-3xl font-black text-gold-600 dark:text-gold-400"><span class="maire-counter" data-target="<?php echo count($servicesPremium); ?>" data-suffix="">0</span></p>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 mt-1">Services</p>
                    </article>
                </div>
            </div>

            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2"><span class="text-2xl">⚡</span> Raccourcis fréquents</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $tiles = [
                    ['📋', 'État civil numérique', 'Déposer une demande, joindre des pièces et suivre le statut.', 'digitalisation-etat-civil.php', 'from-mairie-700 to-mairie-900'],
                    ['🔎', 'Suivi par référence', 'Vérifier une référence de dossier (tous usagers).', 'suivi-etat-civil.php', 'from-emerald-700 to-emerald-900'],
                    ['🚧', 'Projets municipaux', 'Chantiers prioritaires et suivi communal.', 'projets.php', 'from-amber-600 to-orange-700'],
                    ['✉️', 'Support prioritaire', 'Écrire à la mairie pour un accompagnement.', 'contact.php', 'from-rose-600 to-pink-700'],
                ];
                foreach ($tiles as [$icone, $titre, $desc, $lien, $gradient]):
                ?>
                <a href="<?php echo $lien; ?>" class="maire-bento-card tw-card p-5 group">
                    <span class="inline-flex w-12 h-12 mb-3 rounded-2xl bg-gradient-to-br <?php echo $gradient; ?> text-white items-center justify-center text-2xl shadow-md"><?php echo $icone; ?></span>
                    <h4 class="font-black text-slate-900 dark:text-white mb-1 group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition"><?php echo $titre; ?></h4>
                    <p class="text-sm text-slate-600 dark:text-slate-400"><?php echo $desc; ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- DOSSIERS -->
    <section id="std-dossiers" class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <span class="maire-tag bg-mairie-100 dark:bg-mairie-900/50 text-mairie-700 dark:text-mairie-300 mb-2">📁 Mes démarches</span>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white">État civil</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"><strong class="text-mairie-700 dark:text-mairie-300"><?php echo (int) $statsDossiers['total']; ?></strong> dossiers</span>
                    <span class="px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-950/50 text-blue-800 dark:text-blue-200"><strong><?php echo (int) $statsDossiers['recu']; ?></strong> reçus</span>
                    <span class="px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-200"><strong><?php echo (int) $statsDossiers['verification']; ?></strong> en vérification</span>
                    <span class="px-3 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200"><strong><?php echo (int) $statsDossiers['pret']; ?></strong> prêts</span>
                </div>
            </div>

            <?php if ($mesDemandesEtatCivil === []): ?>
                <article class="tw-card p-10 text-center">
                    <div class="text-5xl mb-3">📭</div>
                    <p class="text-slate-600 dark:text-slate-400 mb-4">Aucune demande enregistrée avec votre e-mail d'abonné pour le moment.</p>
                    <a class="tw-btn-primary" href="digitalisation-etat-civil.php">Créer une première demande →</a>
                </article>
            <?php else: ?>
                <article class="tw-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left font-black">Référence</th>
                                    <th class="px-4 py-3 text-left font-black">Type</th>
                                    <th class="px-4 py-3 text-left font-black">Statut</th>
                                    <th class="px-4 py-3 text-left font-black">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($mesDemandesEtatCivil as $d): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                        <td class="px-4 py-3"><code class="px-2 py-0.5 rounded bg-mairie-50 dark:bg-mairie-950/30 text-mairie-700 dark:text-mairie-300 font-mono text-xs"><?php echo htmlspecialchars((string) ($d['reference_dossier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars(maire_libelle_type_demande_etat_civil((string) ($d['type_demande'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3"><span class="<?php echo htmlspecialchars(maire_classe_badge_statut_etat_civil((string) ($d['statut'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"><?php echo htmlspecialchars(maire_libelle_statut_demande_etat_civil((string) ($d['statut'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs"><?php echo htmlspecialchars(substr((string) ($d['created_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">Pour le détail d'un dossier, utilisez aussi <a class="text-mairie-700 dark:text-mairie-300 font-bold hover:underline" href="suivi-etat-civil.php">suivi-etat-civil.php</a> avec la référence.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- FIL -->
    <section id="std-fil" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <span class="maire-tag bg-gold-100 dark:bg-gold-900/30 text-gold-800 dark:text-gold-300 mb-2">📰 Fil communal</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Consignes & annonces</h2>
            </div>
            <?php if ($hubActualites === []): ?>
                <article class="tw-card p-10 text-center text-slate-500 dark:text-slate-400">
                    <div class="text-5xl mb-3">📡</div>
                    <p>Annonces en cours de configuration.</p>
                </article>
            <?php else: ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($hubActualites as $item):
                        $badge = (string) ($item['badge'] ?? 'info');
                        $badgeClass = match ($badge) {
                            'success' => 'bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200',
                            'alert' => 'bg-rose-100 dark:bg-rose-950/50 text-rose-800 dark:text-rose-200',
                            default => 'bg-blue-100 dark:bg-blue-950/50 text-blue-800 dark:text-blue-200',
                        };
                        $badgeLabel = match ($badge) {
                            'success' => 'Positif',
                            'alert' => 'Important',
                            default => 'Info',
                        };
                    ?>
                        <article class="tw-card p-5">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                                <time class="text-xs text-slate-500 dark:text-slate-400" datetime="<?php echo htmlspecialchars((string) ($item['published_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($item['published_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></time>
                            </div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-2"><?php echo htmlspecialchars((string) ($item['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars((string) ($item['resume'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php
                            $lien = trim((string) ($item['lien'] ?? ''));
                            if ($lien !== ''): ?>
                                <a class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="<?php echo htmlspecialchars($lien, ENT_QUOTES, 'UTF-8'); ?>">Ouvrir le lien →</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- PILOTAGE -->
    <section id="std-pilotage" class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <span class="maire-tag bg-mairie-100 dark:bg-mairie-900/50 text-mairie-700 dark:text-mairie-300 mb-2">🎯 Fonctions</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Pilotage & rapports</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <?php
                $panels = [
                    ['🎛️', 'Pilotage des services', ['Visualisation des demandes par service et par période.', 'Suivi des délais moyens de traitement.', 'Priorisation des interventions municipales.'], 'from-mairie-700 to-mairie-900'],
                    ['📈', 'Rapports avancés', ['Rapports mensuels de performance par direction.', 'Indicateurs sur la satisfaction citoyenne.', 'Export des données (CSV / impression PDF).'], 'from-emerald-700 to-emerald-900'],
                    ['🤝', 'Accompagnement dédié', ['Canal prioritaire pour les demandes institutionnelles.', 'Support opérationnel pour la transformation numérique.', 'Appui à la décision pour les projets communaux.'], 'from-gold-600 to-amber-700'],
                ];
                foreach ($panels as [$icone, $titre, $puces, $gradient]):
                ?>
                <article class="maire-bento-card tw-card p-6">
                    <span class="inline-flex w-12 h-12 mb-3 rounded-2xl bg-gradient-to-br <?php echo $gradient; ?> text-white items-center justify-center text-2xl shadow-md"><?php echo $icone; ?></span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-3"><?php echo $titre; ?></h3>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($puces as $puce): ?>
                            <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                                <span class="text-mairie-600 dark:text-mairie-400 flex-shrink-0 mt-1 font-bold">→</span>
                                <span><?php echo $puce; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- RÉPERTOIRE -->
    <section id="repertoire" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <span class="maire-tag bg-gold-100 dark:bg-gold-900/30 text-gold-800 dark:text-gold-300 mb-2">📚 Référentiel</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Répertoire intelligent</h2>
            </div>

            <div class="mb-4">
                <input id="standardSearch" type="search" placeholder="🔍 Rechercher école, hôpital, service, localisation..." autocomplete="off" class="w-full px-4 py-3 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
            </div>

            <div class="standard-tabs flex flex-wrap gap-2 mb-5">
                <button class="standard-tab active px-4 py-2 rounded-xl bg-mairie-700 text-white font-bold text-sm shadow-md" data-tab="ecoles" type="button">🏫 Écoles</button>
                <button class="standard-tab px-4 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-2 border-slate-200 dark:border-slate-700 font-bold text-sm" data-tab="sante" type="button">🏥 Santé</button>
                <button class="standard-tab px-4 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-2 border-slate-200 dark:border-slate-700 font-bold text-sm" data-tab="services" type="button">🏛️ Services</button>
            </div>

            <div class="standard-tab-panel active" data-panel="ecoles">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($ecoles as $ecole): ?>
                        <article class="tw-card p-5 standard-item" data-search="<?php echo htmlspecialchars($ecole['nom'] . ' ' . $ecole['localisation'] . ' ' . $ecole['niveau']); ?>">
                            <span class="inline-block px-2 py-0.5 rounded-full bg-mairie-100 dark:bg-mairie-900/40 text-mairie-700 dark:text-mairie-300 text-[10px] font-black uppercase tracking-wider mb-2"><?php echo htmlspecialchars($ecole['niveau']); ?></span>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1"><?php echo htmlspecialchars($ecole['nom']); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400"><strong>📍 </strong><?php echo htmlspecialchars($ecole['localisation']); ?></p>
                            <a class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="https://maps.google.com/?q=<?php echo urlencode($ecole['nom'] . ' ' . $ecole['localisation']); ?>" target="_blank" rel="noopener noreferrer">Voir la localisation →</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="standard-tab-panel hidden" data-panel="sante">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($structuresSante as $structure): ?>
                        <article class="tw-card p-5 standard-item" data-search="<?php echo htmlspecialchars($structure['nom'] . ' ' . $structure['localisation'] . ' ' . $structure['type']); ?>">
                            <span class="inline-block px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider mb-2"><?php echo htmlspecialchars($structure['type']); ?></span>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1"><?php echo htmlspecialchars($structure['nom']); ?></h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400"><strong>📍 </strong><?php echo htmlspecialchars($structure['localisation']); ?></p>
                            <a class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-emerald-700 dark:text-emerald-300 hover:underline" href="https://maps.google.com/?q=<?php echo urlencode($structure['nom'] . ' ' . $structure['localisation']); ?>" target="_blank" rel="noopener noreferrer">Voir la localisation →</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="standard-tab-panel hidden" data-panel="services" id="services">
                <div class="flex flex-wrap gap-2 mb-3">
                    <button class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" id="exportCsvBtn" type="button">📊 Exporter CSV</button>
                    <button class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" id="exportPdfBtn" type="button">📄 Exporter PDF</button>
                </div>
                <article class="tw-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="standard-table w-full text-sm" id="servicesTable">
                            <thead class="bg-slate-100 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left font-black"><button class="sort-btn hover:text-mairie-700 dark:hover:text-mairie-300" type="button" data-sort-col="0">Service</button></th>
                                    <th class="px-4 py-3 text-left font-black"><button class="sort-btn hover:text-mairie-700 dark:hover:text-mairie-300" type="button" data-sort-col="1">Point de service</button></th>
                                    <th class="px-4 py-3 text-left font-black"><button class="sort-btn hover:text-mairie-700 dark:hover:text-mairie-300" type="button" data-sort-col="2">Horaires</button></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($servicesPremium as $ligne): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition" data-search="<?php echo htmlspecialchars($ligne['service'] . ' ' . $ligne['point_service'] . ' ' . $ligne['horaires']); ?>">
                                        <td class="px-4 py-3 font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($ligne['service']); ?></td>
                                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($ligne['point_service']); ?></td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs"><?php echo htmlspecialchars($ligne['horaires']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
                <div class="flex items-center justify-center gap-3 mt-4">
                    <button class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200" id="prevPageBtn" type="button">← Précédent</button>
                    <span class="text-sm text-slate-600 dark:text-slate-400 font-bold" id="pageIndicator">Page 1</span>
                    <button class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200" id="nextPageBtn" type="button">Suivant →</button>
                </div>
            </div>
        </div>
    </section>

    <!-- ÉTAT CIVIL -->
    <section id="std-etat-civil" class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <span class="maire-tag bg-mairie-100 dark:bg-mairie-900/50 text-mairie-700 dark:text-mairie-300 mb-2">🆔 État civil</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Parcours numérique</h2>
            </div>
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <article class="maire-bento-card tw-card p-6">
                    <span class="inline-flex w-12 h-12 mb-3 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-2xl shadow-md">🌐</span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-3">Démarches en ligne</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-mairie-600 dark:text-mairie-400 font-bold mt-1">→</span><span>Pré-enregistrement numérique des demandes d'actes.</span></li>
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-mairie-600 dark:text-mairie-400 font-bold mt-1">→</span><span>Téléversement des pièces avant passage au guichet.</span></li>
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-mairie-600 dark:text-mairie-400 font-bold mt-1">→</span><span>Réduction du temps d'attente grâce au dossier préparé.</span></li>
                    </ul>
                </article>
                <article class="maire-bento-card tw-card p-6">
                    <span class="inline-flex w-12 h-12 mb-3 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 text-white items-center justify-center text-2xl shadow-md">📍</span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-3">Suivi des dossiers</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-emerald-600 dark:text-emerald-400 font-bold mt-1">→</span><span>Numéro unique de suivi pour chaque demande.</span></li>
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-emerald-600 dark:text-emerald-400 font-bold mt-1">→</span><span>Statuts : reçu, en vérification, prêt à retirer.</span></li>
                        <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300"><span class="text-emerald-600 dark:text-emerald-400 font-bold mt-1">→</span><span>Historique visible dans ce portail pour les agents connectés.</span></li>
                    </ul>
                </article>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="tw-btn-primary" href="digitalisation-etat-civil.php">Démarrer une démarche →</a>
                <?php if ($maireCanVoirOffres): ?>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="offres.php">Voir les offres municipales</a>
                <?php else: ?>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="contact.php">Contacter la mairie</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- INDICATEURS / CHARTS -->
    <section id="std-charts" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <span class="maire-tag bg-gold-100 dark:bg-gold-900/30 text-gold-800 dark:text-gold-300 mb-2">📊 Données</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Indicateurs agrégés</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">Indicateurs calculés sur l'ensemble des demandes d'état civil enregistrées sur la plateforme.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <article class="tw-card p-6">
                    <h3 class="font-black text-slate-900 dark:text-white mb-4">Demandes par type d'acte</h3>
                    <?php if ($dashParType === []): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">Aucune demande en base pour le moment. Les barres apparaîtront dès que des dossiers seront créés via la digitalisation.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($dashParType as $row): ?>
                                <div>
                                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                                        <span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-slate-500 dark:text-slate-400">(<?php echo (int) $row['count']; ?>)</span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-mairie-600 to-mairie-700 rounded-full" style="width: <?php echo (int) $row['pct']; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
                <article class="tw-card p-6">
                    <h3 class="font-black text-slate-900 dark:text-white mb-4">Volume mensuel (5 derniers mois)</h3>
                    <?php if ($dashParMois === []): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">Pas encore d'historique mensuel. Les mois avec au moins une demande s'afficheront ici.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($dashParMois as $row): ?>
                                <div>
                                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                                        <span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-slate-500 dark:text-slate-400">(<?php echo (int) $row['count']; ?>)</span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full" style="width: <?php echo (int) $row['pct']; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </div>
    </section>

    <?php if ($maireCommunePalier === 'premium'): ?>
        <section id="std-premium" class="py-16 bg-gradient-to-br from-mairie-950 via-mairie-900 to-slate-900 text-white maire-grain relative overflow-hidden">
            <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/20 maire-blob blur-3xl pointer-events-none"></div>
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="mb-6">
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-2">⭐ Premium</span>
                    <h2 class="text-3xl font-black">Modules étendus</h2>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <?php
                    $premiumPanels = [
                        ['🗺️', 'Cadastre avancé', 'Couches foncières, historique des parcelles et outils de consultation avancée (paramétrage municipal).'],
                        ['📊', 'Analytique territoriale', 'Indicateurs agrégés, jeux de données et tableaux de bord décisionnels pour les directions techniques.'],
                        ['🛡️', 'Support prioritaire', "Canal d'astreinte et montée en compétence des équipes — selon la configuration communale."],
                    ];
                    foreach ($premiumPanels as [$icone, $titre, $desc]):
                    ?>
                    <article class="rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 p-6">
                        <span class="inline-flex w-12 h-12 mb-3 rounded-2xl bg-gold-400 text-mairie-950 items-center justify-center text-2xl shadow-md"><?php echo $icone; ?></span>
                        <h3 class="font-black mb-2"><?php echo $titre; ?></h3>
                        <p class="text-sm text-white/80"><?php echo $desc; ?></p>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ACTIONS -->
    <section id="std-actions" class="py-12 bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-3 justify-center">
                <a class="tw-btn-primary" href="projets.php">Retour aux projets</a>
                <?php if ($maireCanVoirOffres): ?>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="offres.php">Comparer les paliers</a>
                <?php endif; ?>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="contact.php">Contacter le support</a>
                <?php if (($subscriber['role'] ?? '') === 'admin'): ?>
                    <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="admin/abonnements.php">Comptes & abonnements</a>
                    <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="admin/standard.php">Admin référentiel</a>
                    <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="admin/standard.php#fil-standard-plus">Fil d'annonces</a>
                    <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" href="admin/paiements.php">Admin paiements</a>
                <?php endif; ?>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-red-50 dark:bg-red-950/30 border-2 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 font-black hover:bg-red-100 dark:hover:bg-red-900/40 transition" href="deconnexion.php">↩ Se déconnecter</a>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    var tabs = document.querySelectorAll('.standard-tab');
    var panels = document.querySelectorAll('.standard-tab-panel');
    if (!tabs.length) return;
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function (t) {
                t.classList.remove('active', 'bg-mairie-700', 'text-white', 'shadow-md');
                t.classList.add('bg-white', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-200', 'border-2', 'border-slate-200', 'dark:border-slate-700');
            });
            tab.classList.add('active', 'bg-mairie-700', 'text-white', 'shadow-md');
            tab.classList.remove('bg-white', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-200', 'border-2', 'border-slate-200', 'dark:border-slate-700');
            panels.forEach(function (p) {
                if (p.getAttribute('data-panel') === target) {
                    p.classList.remove('hidden');
                    p.classList.add('active');
                } else {
                    p.classList.add('hidden');
                    p.classList.remove('active');
                }
            });
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
