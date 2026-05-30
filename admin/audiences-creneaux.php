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

$flash = $_SESSION['admin_audiences_creneaux_flash'] ?? null;
unset($_SESSION['admin_audiences_creneaux_flash']);

$feedback = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$feedbackType = is_array($flash) ? (string) ($flash['type'] ?? 'success') : 'success';
$oldInput = is_array($flash) && isset($flash['old']) && is_array($flash['old']) ? $flash['old'] : [];

$formData = [
    'debut' => (string) ($oldInput['debut'] ?? date('Y-m-d\TH:i', strtotime('+1 hour'))),
    'fin' => (string) ($oldInput['fin'] ?? date('Y-m-d\TH:i', strtotime('+1 hour +30 minutes'))),
    'mode_audience' => (string) ($oldInput['mode_audience'] ?? 'presentiel'),
    'capacite' => (string) ($oldInput['capacite'] ?? '1'),
    'notes_admin' => (string) ($oldInput['notes_admin'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
        $_SESSION['admin_audiences_creneaux_flash'] = [
            'message' => 'Jeton CSRF invalide.',
            'type' => 'danger',
        ];
    } elseif (((string) ($_POST['action'] ?? '')) === 'desactiver') {
        $id = (int) ($_POST['creneau_id'] ?? 0);
        $ok = maire_admin_desactiver_creneau_audience($pdo, $id);
        $_SESSION['admin_audiences_creneaux_flash'] = [
            'message' => $ok
                ? 'Créneau n°' . $id . ' désactivé.'
                : 'Impossible de désactiver ce créneau (réservations en cours ou identifiant invalide).',
            'type' => $ok ? 'success' : 'warning',
        ];
    } else {
        $old = [
            'debut' => trim((string) ($_POST['debut'] ?? '')),
            'fin' => trim((string) ($_POST['fin'] ?? '')),
            'mode_audience' => (string) ($_POST['mode_audience'] ?? 'presentiel'),
            'capacite' => (string) ($_POST['capacite'] ?? '1'),
            'notes_admin' => trim((string) ($_POST['notes_admin'] ?? '')),
        ];

        $err = null;
        $id = maire_admin_creer_creneau_audience(
            $pdo,
            $old['debut'],
            $old['fin'],
            $old['mode_audience'],
            (int) $old['capacite'],
            $old['notes_admin'],
            $err
        );

        $_SESSION['admin_audiences_creneaux_flash'] = [
            'message' => $id === null ? ($err ?? 'Création impossible.') : 'Créneau n°' . $id . ' publié.',
            'type' => $id === null ? 'danger' : 'success',
            'old' => $id === null ? $old : [],
        ];
    }

    $redirectParams = array_filter([
        'mode' => trim((string) ($_POST['return_mode'] ?? '')),
        'etat' => trim((string) ($_POST['return_etat'] ?? '')),
        'q' => trim((string) ($_POST['return_q'] ?? '')),
    ], static fn (string $value): bool => $value !== '');

    header('Location: audiences-creneaux.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : ''), true, 303);
    exit;
}

$filtreMode = trim((string) ($_GET['mode'] ?? ''));
if ($filtreMode !== '' && !array_key_exists($filtreMode, MAIRE_AUDIENCES_MODES)) {
    $filtreMode = '';
}

$filtreEtat = trim((string) ($_GET['etat'] ?? ''));
if ($filtreEtat !== '' && !in_array($filtreEtat, ['ouvert', 'complet', 'inactif', 'passe'], true)) {
    $filtreEtat = '';
}

$filtreRecherche = trim((string) ($_GET['q'] ?? ''));

function maire_admin_creneau_etat(array $creneau): string
{
    $debutTs = strtotime((string) ($creneau['debut'] ?? ''));
    $actif = (int) ($creneau['actif'] ?? 0) === 1;
    $placesRestantes = max(0, (int) ($creneau['capacite'] ?? 0) - (int) ($creneau['places_prises'] ?? 0));

    if (!$actif) {
        return 'inactif';
    }
    if ($debutTs !== false && $debutTs <= time()) {
        return 'passe';
    }
    if ($placesRestantes <= 0) {
        return 'complet';
    }

    return 'ouvert';
}

function maire_admin_creneau_etat_label(string $etat): string
{
    return match ($etat) {
        'ouvert' => 'Ouvert',
        'complet' => 'Complet',
        'inactif' => 'Inactif',
        'passe' => 'Passé',
        default => ucfirst($etat),
    };
}

function maire_admin_creneau_etat_badge_class(string $etat): string
{
    return match ($etat) {
        'ouvert' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
        'complet' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        'inactif' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
        'passe' => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        default => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
}

function maire_admin_creneau_mode_badge_class(string $mode): string
{
    return match ($mode) {
        'visio' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
        'presentiel' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
}

$creneaux = $pdo !== null ? maire_lister_creneaux_admin($pdo, false, 250) : [];
$creneauxFiltres = [];

$resume = [
    'total' => 0,
    'futurs' => 0,
    'ouverts' => 0,
    'complets' => 0,
    'inactifs' => 0,
    'places_restantes' => 0,
];

foreach ($creneaux as $creneau) {
    $etat = maire_admin_creneau_etat($creneau);
    $mode = (string) ($creneau['mode_audience'] ?? '');
    $placesRestantes = max(0, (int) ($creneau['capacite'] ?? 0) - (int) ($creneau['places_prises'] ?? 0));

    $resume['total']++;
    if ($etat !== 'passe') {
        $resume['futurs']++;
    }
    if ($etat === 'ouvert') {
        $resume['ouverts']++;
    } elseif ($etat === 'complet') {
        $resume['complets']++;
    } elseif ($etat === 'inactif') {
        $resume['inactifs']++;
    }
    $resume['places_restantes'] += $placesRestantes;

    if ($filtreMode !== '' && $mode !== $filtreMode) {
        continue;
    }
    if ($filtreEtat !== '' && $etat !== $filtreEtat) {
        continue;
    }
    if ($filtreRecherche !== '') {
        $haystack = implode(' ', [
            (string) ($creneau['id'] ?? ''),
            (string) ($creneau['notes_admin'] ?? ''),
            maire_formater_creneau_audience($creneau),
            maire_libelle_audience_mode($mode),
            maire_admin_creneau_etat_label($etat),
        ]);
        if (stripos($haystack, $filtreRecherche) === false) {
            continue;
        }
    }

    $creneau['_etat'] = $etat;
    $creneau['_places_restantes'] = $placesRestantes;
    $creneauxFiltres[] = $creneau;
}

$activeFilters = 0;
foreach ([$filtreMode, $filtreEtat, $filtreRecherche] as $value) {
    if ($value !== '') {
        $activeFilters++;
    }
}

$pageTitle = 'Administration · Créneaux d’audience';
require __DIR__ . '/../includes/header.php';
?>
<main class="bg-slate-50 dark:bg-slate-950 min-h-screen pb-16">
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-mairie-900 to-slate-900 text-white">
        <div class="absolute inset-0 pointer-events-none opacity-40" aria-hidden="true">
            <div class="absolute -top-12 right-0 w-[28rem] h-[28rem] bg-gold-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 left-0 w-[28rem] h-[28rem] bg-emerald-500/20 rounded-full blur-3xl"></div>
        </div>
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 relative z-10">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-black uppercase tracking-[0.22em] text-white/80 mb-4">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Planning des audiences
                    </span>
                    <h1 class="text-3xl md:text-5xl font-black leading-tight tracking-tight mb-3">Créneaux d’audience en ligne</h1>
                    <p class="text-base md:text-lg text-white/80 max-w-2xl">
                        Publiez les rendez-vous réservables, suivez le remplissage et gardez la maîtrise des disponibilités du cabinet.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="audiences-maire.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-sm font-bold transition">
                        Retour aux demandes
                    </a>
                    <a href="../audiences-maire.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 text-sm font-black transition">
                        Voir le parcours public
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Créneaux</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?php echo (int) $resume['total']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">À venir</p>
                <p class="text-2xl font-black text-mairie-700 dark:text-mairie-300 mt-1"><?php echo (int) $resume['futurs']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Ouverts</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-300 mt-1"><?php echo (int) $resume['ouverts']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Complets</p>
                <p class="text-2xl font-black text-amber-600 dark:text-amber-300 mt-1"><?php echo (int) $resume['complets']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Inactifs</p>
                <p class="text-2xl font-black text-rose-600 dark:text-rose-300 mt-1"><?php echo (int) $resume['inactifs']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Places libres</p>
                <p class="text-2xl font-black text-cyan-600 dark:text-cyan-300 mt-1"><?php echo (int) $resume['places_restantes']; ?></p>
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

        <div class="grid xl:grid-cols-[380px_1fr] gap-6">
            <section class="tw-card p-5 md:p-6 h-fit">
                <div class="mb-4">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Publication</p>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">Nouveau créneau</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Publie un créneau en ligne pour confirmation immédiate côté citoyen.</p>
                </div>

                <form method="post" class="space-y-4">
                    <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Début</span>
                            <input type="datetime-local" name="debut" required value="<?php echo htmlspecialchars($formData['debut'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                        </label>
                        <label class="block">
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Fin</span>
                            <input type="datetime-local" name="fin" required value="<?php echo htmlspecialchars($formData['fin'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                        </label>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Mode</span>
                            <select name="mode_audience" class="tw-input">
                                <?php foreach (MAIRE_AUDIENCES_MODES as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['mode_audience'] === $code ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Capacité</span>
                            <input type="number" name="capacite" min="1" max="20" value="<?php echo htmlspecialchars($formData['capacite'], ENT_QUOTES, 'UTF-8'); ?>" required class="tw-input">
                        </label>
                    </div>

                    <label class="block">
                        <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Note interne</span>
                        <textarea name="notes_admin" rows="3" maxlength="255" class="tw-input resize-y" placeholder="Ex. : Bureau du Maire, salle du conseil, lien Meet interne..."><?php echo htmlspecialchars($formData['notes_admin'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <button type="submit" class="tw-btn-primary w-full">Publier le créneau</button>
                </form>
            </section>

            <section class="space-y-6">
                <section class="tw-card p-5 md:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Filtres</p>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white">Piloter les créneaux publiés</h2>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-bold">
                            <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"><?php echo count($creneauxFiltres); ?> résultat<?php echo count($creneauxFiltres) > 1 ? 's' : ''; ?></span>
                            <span class="px-3 py-1.5 rounded-full bg-mairie-100 dark:bg-mairie-900/40 text-mairie-800 dark:text-mairie-200"><?php echo $activeFilters; ?> filtre<?php echo $activeFilters > 1 ? 's' : ''; ?> actif<?php echo $activeFilters > 1 ? 's' : ''; ?></span>
                        </div>
                    </div>

                    <form method="get" class="grid lg:grid-cols-[1.8fr_1fr_1fr_auto] gap-3 items-end">
                        <label class="block">
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Recherche</span>
                            <input type="search" name="q" value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ID, note interne, date, mode..." class="tw-input">
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
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">État</span>
                            <select name="etat" class="tw-input">
                                <option value="">Tous</option>
                                <?php foreach (['ouvert', 'complet', 'inactif', 'passe'] as $etat): ?>
                                    <option value="<?php echo htmlspecialchars($etat, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreEtat === $etat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(maire_admin_creneau_etat_label($etat), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="tw-btn-primary">Filtrer</button>
                            <a href="audiences-creneaux.php" class="tw-btn-outline">Réinitialiser</a>
                        </div>
                    </form>
                </section>

                <?php if ($creneauxFiltres === []): ?>
                    <article class="tw-card p-10 text-center">
                        <div class="mx-auto w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-3xl mb-4">🗓️</div>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucun créneau trouvé</h2>
                        <p class="text-slate-600 dark:text-slate-400">Essaie un autre filtre ou publie un nouveau créneau dans le panneau de gauche.</p>
                    </article>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($creneauxFiltres as $c):
                            $etat = (string) ($c['_etat'] ?? 'ouvert');
                            $placesRestantes = (int) ($c['_places_restantes'] ?? 0);
                            $capacite = max(1, (int) ($c['capacite'] ?? 1));
                            $placesPrises = (int) ($c['places_prises'] ?? 0);
                            $occupation = (int) round(($placesPrises / $capacite) * 100);
                            $canDeactivate = (int) ($c['actif'] ?? 0) === 1 && $placesPrises === 0 && $etat !== 'passe';
                        ?>
                            <article class="tw-card p-5 md:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <code class="px-2.5 py-1 rounded-lg bg-mairie-50 dark:bg-mairie-950/40 text-mairie-700 dark:text-mairie-300 font-mono text-xs font-bold">#<?php echo (int) ($c['id'] ?? 0); ?></code>
                                            <span class="<?php echo maire_admin_creneau_etat_badge_class($etat); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                                <?php echo htmlspecialchars(maire_admin_creneau_etat_label($etat), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="<?php echo maire_admin_creneau_mode_badge_class((string) ($c['mode_audience'] ?? 'presentiel')); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                                <?php echo htmlspecialchars(maire_libelle_audience_mode((string) ($c['mode_audience'] ?? 'presentiel')), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                        <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white mb-1">
                                            <?php echo htmlspecialchars(maire_formater_creneau_audience($c), ENT_QUOTES, 'UTF-8'); ?>
                                        </h2>
                                        <p class="text-sm text-slate-600 dark:text-slate-400">
                                            Créé le
                                            <strong class="text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) ($c['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 min-w-[240px]">
                                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Prises</p>
                                            <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo $placesPrises; ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Restantes</p>
                                            <p class="text-lg font-black text-emerald-600 dark:text-emerald-300"><?php echo $placesRestantes; ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Capacité</p>
                                            <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo $capacite; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid lg:grid-cols-[1.2fr_0.8fr] gap-4 mt-5">
                                    <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 p-4">
                                        <h3 class="text-sm font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400 mb-3">Capacité & logistique</h3>
                                        <div class="mb-3">
                                            <div class="flex items-center justify-between gap-3 text-sm mb-1">
                                                <span class="font-bold text-slate-700 dark:text-slate-200">Occupation</span>
                                                <span class="text-slate-500 dark:text-slate-400"><?php echo $occupation; ?>%</span>
                                            </div>
                                            <div class="h-3 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                                <div class="h-full rounded-full <?php echo $etat === 'complet' ? 'bg-amber-500' : ($etat === 'ouvert' ? 'bg-emerald-500' : 'bg-slate-500'); ?>" style="width: <?php echo min(100, max(0, $occupation)); ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3">
                                            <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Note interne</p>
                                            <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars((string) (($c['notes_admin'] ?? '') !== '' ? $c['notes_admin'] : 'Aucune note interne.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>

                                    <div class="rounded-3xl bg-slate-950 text-white p-4 border border-slate-800">
                                        <h3 class="text-sm font-black uppercase tracking-[0.22em] text-white/70 mb-3">Actions</h3>
                                        <div class="space-y-3">
                                            <a href="audiences-maire.php" class="flex items-center justify-between gap-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-2 transition">
                                                <span class="text-sm font-bold text-white/90">Voir les demandes liées</span>
                                                <span class="text-xs text-gold-300 font-black">Ouvrir</span>
                                            </a>

                                            <?php if ($canDeactivate): ?>
                                                <form method="post" onsubmit="return confirm('Désactiver ce créneau ?');">
                                                    <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                                                    <input type="hidden" name="action" value="desactiver">
                                                    <input type="hidden" name="creneau_id" value="<?php echo (int) ($c['id'] ?? 0); ?>">
                                                    <input type="hidden" name="return_mode" value="<?php echo htmlspecialchars($filtreMode, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_etat" value="<?php echo htmlspecialchars($filtreEtat, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-rose-400 text-rose-200 hover:bg-rose-500/10 font-bold text-sm transition">
                                                        Désactiver ce créneau
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div class="rounded-2xl bg-white/5 border border-white/10 px-3 py-3">
                                                    <p class="text-sm text-white/70">
                                                        <?php echo $etat === 'passe'
                                                            ? 'Ce créneau est déjà passé.'
                                                            : ($etat === 'inactif'
                                                                ? 'Ce créneau est déjà inactif.'
                                                                : 'Ce créneau ne peut plus être désactivé car des réservations existent.'); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
