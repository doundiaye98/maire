<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/super-admin-session.php';
require_once __DIR__ . '/../includes/audiences-maire.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'audiences_maire')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('audiences_maire', $palierCommune, 'admin');
    exit;
}

$flash = $_SESSION['admin_audiences_flash'] ?? null;
unset($_SESSION['admin_audiences_flash']);

$feedback = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$feedbackType = is_array($flash) ? (string) ($flash['type'] ?? 'success') : 'success';

$filtreRecherche = trim((string) ($_GET['q'] ?? ''));
$filtreStatut = trim((string) ($_GET['statut'] ?? ''));
if ($filtreStatut !== '' && !array_key_exists($filtreStatut, MAIRE_AUDIENCES_STATUTS)) {
    $filtreStatut = '';
}

$filtreMode = trim((string) ($_GET['mode'] ?? ''));
if ($filtreMode !== '' && !array_key_exists($filtreMode, MAIRE_AUDIENCES_MODES)) {
    $filtreMode = '';
}

$filtreType = trim((string) ($_GET['type'] ?? ''));
if ($filtreType !== '' && !in_array($filtreType, ['creneau_fixe', 'demande_libre'], true)) {
    $filtreType = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
        $_SESSION['admin_audiences_flash'] = [
            'message' => 'Jeton CSRF invalide.',
            'type' => 'danger',
        ];
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $statut = (string) ($_POST['statut'] ?? '');
        $dateAudience = trim((string) ($_POST['date_audience'] ?? ''));
        $lienVisio = trim((string) ($_POST['lien_visio'] ?? ''));
        $lieu = trim((string) ($_POST['lieu_audience'] ?? ''));
        $notes = trim((string) ($_POST['admin_notes'] ?? ''));
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'admin'));

        if ($id <= 0) {
            $_SESSION['admin_audiences_flash'] = [
                'message' => 'Demande invalide.',
                'type' => 'danger',
            ];
        } else {
            $ok = maire_mettre_a_jour_audience_admin($pdo, $id, $statut, $dateAudience, $lienVisio, $lieu, $notes, $email);
            $_SESSION['admin_audiences_flash'] = [
                'message' => $ok
                    ? 'Audience #' . $id . ' mise à jour (' . maire_libelle_audience_statut($statut) . ').'
                    : 'Aucune modification enregistrée pour l’audience #' . $id . '.',
                'type' => $ok ? 'success' : 'warning',
            ];
        }
    }

    $redirectParams = array_filter([
        'q' => trim((string) ($_POST['return_q'] ?? '')),
        'statut' => trim((string) ($_POST['return_statut'] ?? '')),
        'mode' => trim((string) ($_POST['return_mode'] ?? '')),
        'type' => trim((string) ($_POST['return_type'] ?? '')),
    ], static fn (string $value): bool => $value !== '');

    header('Location: audiences-maire.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : ''), true, 303);
    exit;
}

$resume = ['total' => 0, 'aujourd_hui' => 0, 'en_attente' => 0, 'confirmees' => 0, 'visio' => 0, 'creneaux_fixes' => 0];
$counts = array_fill_keys(array_keys(MAIRE_AUDIENCES_STATUTS), 0);
$audiences = [];
$creneauxFuturs = [];

if ($pdo === null) {
    $feedback = 'Connexion MySQL indisponible.';
    $feedbackType = 'danger';
} else {
    $resume = maire_resumer_audiences_admin($pdo);
    $counts = maire_compter_audiences_par_statut($pdo);
    $audiences = maire_liste_audiences_admin($pdo, [
        'q' => $filtreRecherche,
        'statut' => $filtreStatut,
        'mode' => $filtreMode,
        'type' => $filtreType,
    ], 180);
    $creneauxFuturs = maire_lister_creneaux_admin($pdo, true, 80);
}

$creneauxOuverts = 0;
$placesDisponibles = 0;
foreach ($creneauxFuturs as $creneau) {
    $placesRestantes = max(0, (int) ($creneau['capacite'] ?? 0) - (int) ($creneau['places_prises'] ?? 0));
    if ((int) ($creneau['actif'] ?? 0) === 1 && $placesRestantes > 0) {
        $creneauxOuverts++;
        $placesDisponibles += $placesRestantes;
    }
}

$activeFilters = 0;
foreach ([$filtreRecherche, $filtreStatut, $filtreMode, $filtreType] as $value) {
    if ($value !== '') {
        $activeFilters++;
    }
}

$audienceNoteTemplates = [
    'en_attente' => "Votre demande a bien été reçue. Le cabinet du Maire vous recontactera après étude de votre dossier.",
    'confirmee' => "Votre audience est confirmée. Merci de vous présenter à l'heure indiquée ou de rejoindre le lien visio communiqué.",
    'terminee' => "Votre audience est clôturée. Merci pour votre disponibilité.",
    'annulee' => "Votre audience a été annulée. Merci de reprendre contact avec la mairie si vous souhaitez une nouvelle planification.",
    'refusee' => "Votre demande ne peut pas être retenue en l'état. Merci de contacter la mairie pour un autre canal de traitement si nécessaire.",
];

$noteTemplatesJson = json_encode($audienceNoteTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function maire_admin_audience_statut_badge_class(string $statut): string
{
    return match ($statut) {
        'en_attente' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        'confirmee' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
        'terminee' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
        'annulee', 'refusee' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
        default => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    };
}

function maire_admin_audience_mode_badge_class(string $mode): string
{
    return match ($mode) {
        'visio' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
        'presentiel' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
}

function maire_admin_audience_type_badge_class(string $type): string
{
    return match ($type) {
        'creneau_fixe' => 'bg-teal-100 text-teal-800 dark:bg-teal-950/40 dark:text-teal-200',
        'demande_libre' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
}

function maire_admin_datetime_local_value(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
}

$pageTitle = 'Administration · Audiences avec le Maire';
require __DIR__ . '/../includes/header.php';
?>
<main class="bg-slate-50 dark:bg-slate-950 min-h-screen pb-16">
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-mairie-900 to-slate-900 text-white">
        <div class="absolute inset-0 pointer-events-none opacity-40" aria-hidden="true">
            <div class="absolute -top-12 right-0 w-[30rem] h-[30rem] bg-gold-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 left-0 w-[30rem] h-[30rem] bg-sky-500/20 rounded-full blur-3xl"></div>
        </div>
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 relative z-10">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-black uppercase tracking-[0.22em] text-white/80 mb-4">
                        <span class="w-2 h-2 rounded-full bg-gold-400 animate-pulse"></span>
                        Cabinet du Maire
                    </span>
                    <h1 class="text-3xl md:text-5xl font-black leading-tight tracking-tight mb-3">Gestion des audiences en ligne</h1>
                    <p class="text-base md:text-lg text-white/80 max-w-2xl">
                        Pilotez les demandes, confirmez les rendez-vous, gérez les visios et répondez aux citoyens depuis une seule interface.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="audiences-creneaux.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 text-sm font-black transition">
                        Gérer les créneaux
                    </a>
                    <a href="../audiences-maire.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-sm font-bold transition">
                        Voir le parcours public
                    </a>
                    <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-sm font-bold transition">
                        Tableau de bord admin
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Demandes</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?php echo (int) $resume['total']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Aujourd’hui</p>
                <p class="text-2xl font-black text-mairie-700 dark:text-mairie-300 mt-1"><?php echo (int) $resume['aujourd_hui']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">En attente</p>
                <p class="text-2xl font-black text-amber-600 dark:text-amber-300 mt-1"><?php echo (int) $resume['en_attente']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Confirmées</p>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-300 mt-1"><?php echo (int) $resume['confirmees']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Demandes visio</p>
                <p class="text-2xl font-black text-cyan-600 dark:text-cyan-300 mt-1"><?php echo (int) $resume['visio']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Créneaux ouverts</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-300 mt-1"><?php echo (int) $creneauxOuverts; ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo (int) $placesDisponibles; ?> place<?php echo $placesDisponibles > 1 ? 's' : ''; ?> restantes</p>
            </article>
        </div>

        <?php if ($feedback !== ''): ?>
            <div class="mb-6 rounded-2xl border-2 p-4 <?php echo $feedbackType === 'danger'
                ? 'bg-red-50 border-red-300 text-red-800 dark:bg-red-950/30 dark:border-red-800 dark:text-red-200'
                : ($feedbackType === 'warning'
                    ? 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-950/30 dark:border-amber-800 dark:text-amber-200'
                    : 'bg-emerald-50 border-emerald-300 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-200'); ?>">
                <p class="font-bold"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <section class="tw-card p-5 md:p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Filtres</p>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">Trouver une audience rapidement</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"><?php echo count($audiences); ?> résultat<?php echo count($audiences) > 1 ? 's' : ''; ?></span>
                    <span class="px-3 py-1.5 rounded-full bg-mairie-100 dark:bg-mairie-900/40 text-mairie-800 dark:text-mairie-200"><?php echo $activeFilters; ?> filtre<?php echo $activeFilters > 1 ? 's' : ''; ?> actif<?php echo $activeFilters > 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <form method="get" class="grid lg:grid-cols-[2fr_1fr_1fr_1fr_auto] gap-3 items-end">
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Nom, email, téléphone, quartier, objet, ID</span>
                    <input
                        type="search"
                        name="q"
                        value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Fatou, 77..., assainissement, #12..."
                        class="tw-input"
                    >
                </label>
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Statut</span>
                    <select name="statut" class="tw-input">
                        <option value="">Tous</option>
                        <?php foreach (MAIRE_AUDIENCES_STATUTS as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreStatut === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Mode</span>
                    <select name="mode" class="tw-input">
                        <option value="">Tous</option>
                        <?php foreach (MAIRE_AUDIENCES_MODES as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreMode === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Réservation</span>
                    <select name="type" class="tw-input">
                        <option value="">Toutes</option>
                        <option value="creneau_fixe" <?php echo $filtreType === 'creneau_fixe' ? 'selected' : ''; ?>>Créneau publié</option>
                        <option value="demande_libre" <?php echo $filtreType === 'demande_libre' ? 'selected' : ''; ?>>Demande libre</option>
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="tw-btn-primary">Filtrer</button>
                    <a href="audiences-maire.php" class="tw-btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <?php if ($audiences === []): ?>
                <article class="tw-card p-10 text-center">
                    <div class="mx-auto w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-3xl mb-4">🤝</div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucune audience trouvée</h2>
                    <p class="text-slate-600 dark:text-slate-400">Essaie un autre filtre ou élargis la recherche pour afficher plus de demandes.</p>
                </article>
            <?php else: ?>
                <?php foreach ($audiences as $a):
                    $id = (int) ($a['id'] ?? 0);
                    $statut = (string) ($a['statut'] ?? '');
                    $mode = (string) ($a['mode_audience'] ?? 'presentiel');
                    $typeReservation = (string) ($a['type_reservation'] ?? 'demande_libre');
                    $dateDepot = !empty($a['created_at']) ? date('d/m/Y à H:i', strtotime((string) $a['created_at'])) : '—';
                    $dateAudience = !empty($a['date_audience']) ? date('d/m/Y à H:i', strtotime((string) $a['date_audience'])) : '';
                    $dateSouhaitee = !empty($a['date_souhaitee']) ? date('d/m/Y', strtotime((string) $a['date_souhaitee'])) : '';
                ?>
                    <article id="audience-<?php echo $id; ?>" class="tw-card p-5 md:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <code class="px-2.5 py-1 rounded-lg bg-mairie-50 dark:bg-mairie-950/40 text-mairie-700 dark:text-mairie-300 font-mono text-xs font-bold">#<?php echo $id; ?></code>
                                    <span class="<?php echo maire_admin_audience_statut_badge_class($statut); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                        <?php echo htmlspecialchars(maire_libelle_audience_statut($statut), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="<?php echo maire_admin_audience_mode_badge_class($mode); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                        <?php echo htmlspecialchars(maire_libelle_audience_mode($mode), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="<?php echo maire_admin_audience_type_badge_class($typeReservation); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                        <?php echo htmlspecialchars(maire_libelle_type_reservation($typeReservation), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if (!empty($a['telephone_verifie'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200 text-xs font-black">SMS vérifié</span>
                                    <?php endif; ?>
                                </div>

                                <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white mb-1">
                                    <?php echo htmlspecialchars((string) ($a['objet'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                    Déposée le <strong class="text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($dateDepot, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    par <strong class="text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars(trim((string) ($a['prenom'] ?? '') . ' ' . (string) ($a['nom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 min-w-[240px]">
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Email</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars((string) ($a['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Mobile</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) (($a['telephone'] ?? '') !== '' ? $a['telephone'] : '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Quartier</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) (($a['quartier'] ?? '') !== '' ? $a['quartier'] : '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Motif</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(maire_libelle_audience_motif((string) ($a['motif'] ?? 'autre')), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid xl:grid-cols-[1.3fr_0.95fr] gap-4 mt-5">
                            <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 p-4">
                                <h3 class="text-sm font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400 mb-3">Contexte citoyen</h3>
                                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400 font-bold">Date souhaitée</dt>
                                        <dd class="text-slate-900 dark:text-white"><?php echo htmlspecialchars($dateSouhaitee !== '' ? $dateSouhaitee : 'Non renseignée', ENT_QUOTES, 'UTF-8'); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400 font-bold">Créneau préféré</dt>
                                        <dd class="text-slate-900 dark:text-white"><?php echo htmlspecialchars(maire_libelle_audience_creneau((string) ($a['creneau_souhaite'] ?? 'indifferent')), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    </div>
                                </dl>

                                <?php if ($dateAudience !== ''): ?>
                                    <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3 mt-3">
                                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Audience planifiée</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($dateAudience, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3 mt-3">
                                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Message du citoyen</p>
                                    <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>

                                <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3 mt-3">
                                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Dernière note cabinet</p>
                                    <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars((string) (($a['admin_notes'] ?? '') !== '' ? $a['admin_notes'] : 'Aucune note interne pour le moment.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>

                            <div class="rounded-3xl bg-slate-950 text-white p-4 border border-slate-800">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <h3 class="text-sm font-black uppercase tracking-[0.22em] text-white/70">Pilotage</h3>
                                    <span class="text-xs font-bold text-white/60"><?php echo htmlspecialchars((string) (($a['traite_par_email'] ?? '') !== '' ? $a['traite_par_email'] : 'Non traité'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>

                                <?php if (!empty($a['lien_visio'])): ?>
                                    <a
                                        href="<?php echo htmlspecialchars((string) $a['lien_visio'], ENT_QUOTES, 'UTF-8'); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center justify-between gap-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-2 transition mb-3"
                                    >
                                        <span class="text-sm font-bold text-white/90 truncate">Ouvrir le lien visio</span>
                                        <span class="text-xs text-gold-300 font-black">Accéder</span>
                                    </a>
                                <?php endif; ?>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-2xl bg-white/5 border border-white/10 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-wider text-white/50 font-bold">Lieu</p>
                                        <p class="text-sm font-bold text-white/90"><?php echo htmlspecialchars((string) (($a['lieu_audience'] ?? '') !== '' ? $a['lieu_audience'] : '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="rounded-2xl bg-white/5 border border-white/10 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-wider text-white/50 font-bold">Réservation</p>
                                        <p class="text-sm font-bold text-white/90"><?php echo htmlspecialchars($typeReservation === 'creneau_fixe' ? 'Immédiate' : 'À confirmer', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="post" class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($filtreStatut, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_mode" value="<?php echo htmlspecialchars($filtreMode, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_type" value="<?php echo htmlspecialchars($filtreType, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="grid lg:grid-cols-4 gap-3">
                                <label class="block">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Statut</span>
                                    <select name="statut" class="tw-input">
                                        <?php foreach (MAIRE_AUDIENCES_STATUTS as $code => $label): ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statut === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Date & heure confirmées</span>
                                    <input type="datetime-local" name="date_audience" value="<?php echo htmlspecialchars(maire_admin_datetime_local_value((string) ($a['date_audience'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                                </label>
                                <label class="block">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Lieu (présentiel)</span>
                                    <input type="text" name="lieu_audience" maxlength="255" value="<?php echo htmlspecialchars((string) ($a['lieu_audience'] ?? 'Mairie de Rufisque-Est — Castor'), ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                                </label>
                                <label class="block">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Lien visioconférence</span>
                                    <input type="url" name="lien_visio" maxlength="500" placeholder="https://meet..." value="<?php echo htmlspecialchars((string) ($a['lien_visio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                                </label>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-end gap-3 mb-2">
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-bold text-slate-700 dark:text-slate-200">
                                        <input type="checkbox" class="js-audience-auto-template rounded border-slate-300 text-mairie-700 focus:ring-mairie-500" <?php echo ((string) ($a['admin_notes'] ?? '') === '' || (string) ($a['admin_notes'] ?? '') === ($audienceNoteTemplates[$statut] ?? '')) ? 'checked' : ''; ?>>
                                        Auto selon le statut
                                    </label>
                                    <button type="button" class="js-audience-template-current inline-flex items-center px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-bold text-slate-700 dark:text-slate-200 hover:border-mairie-500 hover:text-mairie-700 dark:hover:text-mairie-300 transition">
                                        Appliquer le modèle du statut
                                    </button>
                                </div>

                                <label class="block">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Message au demandeur</span>
                                    <textarea name="admin_notes" rows="4" maxlength="4000" class="tw-input resize-y" placeholder="Message envoyé par e-mail au citoyen lors de la mise à jour."><?php echo htmlspecialchars((string) ($a['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </label>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <?php foreach (MAIRE_AUDIENCES_STATUTS as $code => $label):
                                        $templateClass = match ($code) {
                                            'en_attente' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-400 hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-900/40',
                                            'confirmee' => 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-400 hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200 dark:hover:bg-blue-900/40',
                                            'terminee' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-400 hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200 dark:hover:bg-emerald-900/40',
                                            'annulee', 'refusee' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-400 hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200 dark:hover:bg-rose-900/40',
                                            default => 'border-slate-200 bg-white text-slate-700 hover:border-mairie-500 hover:text-mairie-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-mairie-300',
                                        };
                                    ?>
                                        <button
                                            type="button"
                                            class="js-audience-template inline-flex items-center px-3 py-1.5 rounded-full border text-xs font-bold transition shadow-sm hover:-translate-y-0.5 <?php echo $templateClass; ?>"
                                            data-template-status="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Modèle <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">Le message est enregistré sur la demande et ajouté à l’e-mail citoyen lors d’une confirmation, d’un refus ou d’une annulation.</span>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="tw-btn-primary">Enregistrer la mise à jour</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </section>
</main>
<script>
(function () {
    const templates = <?php echo $noteTemplatesJson ?: '{}'; ?>;
    const knownTemplates = Object.values(templates);

    function getFormState(form) {
        return {
            textarea: form.querySelector('textarea[name="admin_notes"]'),
            select: form.querySelector('select[name="statut"]'),
            autoToggle: form.querySelector('.js-audience-auto-template'),
        };
    }

    function applyTemplate(form, status, force) {
        const state = getFormState(form);
        if (!state.textarea || !state.select) return;

        const template = templates[status] || '';
        if (template === '') return;

        if (!force && state.autoToggle && !state.autoToggle.checked) {
            return;
        }

        state.textarea.value = template;
        state.textarea.dataset.autoManaged = '1';
    }

    document.querySelectorAll('form').forEach(function (form) {
        const state = getFormState(form);
        if (!state.textarea || !state.select) return;

        const initialValue = state.textarea.value.trim();
        const currentTemplate = templates[state.select.value] || '';
        const autoManaged = initialValue === '' || initialValue === currentTemplate;
        state.textarea.dataset.autoManaged = autoManaged ? '1' : '0';

        if (state.autoToggle) {
            state.autoToggle.checked = autoManaged;

            state.autoToggle.addEventListener('change', function () {
                if (state.autoToggle.checked) {
                    applyTemplate(form, state.select.value, true);
                    state.textarea.focus();
                    state.textarea.setSelectionRange(state.textarea.value.length, state.textarea.value.length);
                } else {
                    state.textarea.dataset.autoManaged = '0';
                }
            });
        }

        state.select.addEventListener('change', function () {
            if (state.autoToggle && state.autoToggle.checked) {
                applyTemplate(form, state.select.value, true);
            }
        });

        state.textarea.addEventListener('input', function () {
            const currentValue = state.textarea.value.trim();
            const matchesSelectedTemplate = currentValue !== '' && currentValue === (templates[state.select.value] || '');
            const matchesKnownTemplate = currentValue !== '' && knownTemplates.includes(currentValue);

            if (matchesSelectedTemplate) {
                state.textarea.dataset.autoManaged = '1';
                if (state.autoToggle) state.autoToggle.checked = true;
                return;
            }

            if (currentValue === '') {
                state.textarea.dataset.autoManaged = '0';
                return;
            }

            if (!matchesKnownTemplate) {
                state.textarea.dataset.autoManaged = '0';
                if (state.autoToggle) state.autoToggle.checked = false;
            }
        });
    });

    document.querySelectorAll('.js-audience-template').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.closest('form');
            if (!form) return;

            const state = getFormState(form);
            if (!state.textarea || !state.select) return;

            const requestedStatus = button.getAttribute('data-template-status') || '';
            if (requestedStatus !== '') {
                state.select.value = requestedStatus;
            }

            if (state.autoToggle) state.autoToggle.checked = true;
            applyTemplate(form, requestedStatus || state.select.value, true);
            state.textarea.focus();
            state.textarea.setSelectionRange(state.textarea.value.length, state.textarea.value.length);
        });
    });

    document.querySelectorAll('.js-audience-template-current').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.closest('form');
            if (!form) return;

            const state = getFormState(form);
            if (!state.textarea || !state.select) return;

            if (state.autoToggle) state.autoToggle.checked = true;
            applyTemplate(form, state.select.value, true);
            state.textarea.focus();
            state.textarea.setSelectionRange(state.textarea.value.length, state.textarea.value.length);
        });
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
