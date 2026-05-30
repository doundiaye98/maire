<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/etat-civil-demande.php';

$csrfScope = MAIRE_CSRF_SCOPE_ADMIN_ETAT_CIVIL;
$flash = $_SESSION['admin_etat_civil_flash'] ?? null;
unset($_SESSION['admin_etat_civil_flash']);

$feedback = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$feedbackType = is_array($flash) ? (string) ($flash['type'] ?? 'success') : 'success';

$filtreReference = maire_normaliser_reference_etat_civil((string) ($_GET['ref'] ?? ''));
$filtreRecherche = trim((string) ($_GET['q'] ?? ''));
if ($filtreRecherche === '' && $filtreReference !== '') {
    $filtreRecherche = $filtreReference;
}

$filtreStatut = trim((string) ($_GET['statut'] ?? ''));
if ($filtreStatut !== '' && !array_key_exists($filtreStatut, MAIRE_ETAT_CIVIL_STATUTS)) {
    $filtreStatut = '';
}

$filtreType = trim((string) ($_GET['type'] ?? ''));
if ($filtreType !== '' && !array_key_exists($filtreType, MAIRE_ETAT_CIVIL_TYPES)) {
    $filtreType = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate($csrfScope)) {
        $_SESSION['admin_etat_civil_flash'] = [
            'message' => 'Jeton de sécurité invalide. Rechargez la page.',
            'type' => 'danger',
        ];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_status') {
            $result = maire_mettre_a_jour_statut_demande_etat_civil(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                (string) ($_POST['statut'] ?? ''),
                (string) ($_POST['admin_notes'] ?? '')
            );
            $_SESSION['admin_etat_civil_flash'] = [
                'message' => $result['message'],
                'type' => !$result['ok'] ? 'danger' : ($result['changed'] ? 'success' : 'warning'),
            ];
        }
    }

    $redirectParams = array_filter([
        'q' => trim((string) ($_POST['return_q'] ?? '')),
        'statut' => trim((string) ($_POST['return_statut'] ?? '')),
        'type' => trim((string) ($_POST['return_type'] ?? '')),
        'ref' => maire_normaliser_reference_etat_civil((string) ($_POST['return_ref'] ?? '')),
    ], static fn (string $value): bool => $value !== '');

    header('Location: etat-civil.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : ''), true, 303);
    exit;
}

$resume = ['total' => 0, 'aujourd_hui' => 0, 'recu' => 0, 'en_cours' => 0, 'pret' => 0];
$counts = array_fill_keys(array_keys(MAIRE_ETAT_CIVIL_STATUTS), 0);
$demandes = [];
$piecesParDemande = [];

if ($pdo === null) {
    $feedback = 'Connexion MySQL indisponible.';
    $feedbackType = 'danger';
} else {
    $resume = maire_resumer_demandes_etat_civil($pdo);
    $counts = maire_compter_demandes_etat_civil_par_statut($pdo);
    $demandes = maire_lister_demandes_etat_civil_admin($pdo, [
        'q' => $filtreRecherche,
        'statut' => $filtreStatut,
        'type' => $filtreType,
    ], 120);
    $piecesParDemande = maire_indexer_pieces_demandes_etat_civil(
        $pdo,
        array_map('intval', array_column($demandes, 'id'))
    );
}

$activeFilters = 0;
foreach ([$filtreRecherche, $filtreStatut, $filtreType] as $value) {
    if ($value !== '') {
        $activeFilters++;
    }
}

$messageTemplatesJson = json_encode(MAIRE_ETAT_CIVIL_ADMIN_NOTE_TEMPLATES, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pageTitle = 'Administration · État civil';
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
                        Bureau état civil
                    </span>
                    <h1 class="text-3xl md:text-5xl font-black leading-tight tracking-tight mb-3">Pilotage des dossiers d'état civil</h1>
                    <p class="text-base md:text-lg text-white/80 max-w-2xl">
                        Recherchez un dossier, filtrez les demandes par statut ou type, puis mettez à jour l'avancement en quelques clics.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-sm font-bold transition">
                        Tableau de bord admin
                    </a>
                    <a href="../digitalisation-etat-civil.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 text-sm font-black transition">
                        Voir le parcours public
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Total</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?php echo (int) $resume['total']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Aujourd'hui</p>
                <p class="text-2xl font-black text-mairie-700 dark:text-mairie-300 mt-1"><?php echo (int) $resume['aujourd_hui']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">À traiter</p>
                <p class="text-2xl font-black text-amber-600 dark:text-amber-300 mt-1"><?php echo (int) $counts['recu']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">En cours</p>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-300 mt-1"><?php echo (int) $counts['en_cours']; ?></p>
            </article>
            <article class="tw-card p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Prêts</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-300 mt-1"><?php echo (int) $counts['pret']; ?></p>
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
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">Trouver un dossier rapidement</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"><?php echo count($demandes); ?> résultat<?php echo count($demandes) > 1 ? 's' : ''; ?></span>
                    <span class="px-3 py-1.5 rounded-full bg-mairie-100 dark:bg-mairie-900/40 text-mairie-800 dark:text-mairie-200"><?php echo $activeFilters; ?> filtre<?php echo $activeFilters > 1 ? 's' : ''; ?> actif<?php echo $activeFilters > 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <form method="get" class="grid lg:grid-cols-[2fr_1fr_1fr_auto] gap-3 items-end">
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Référence, nom, e-mail, téléphone, CNI</span>
                    <input
                        type="search"
                        name="q"
                        value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="EC-20260526-AB12CD, Mariama, 77..., email..."
                        class="tw-input"
                    >
                </label>
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Statut</span>
                    <select name="statut" class="tw-input">
                        <option value="">Tous les statuts</option>
                        <?php foreach (MAIRE_ETAT_CIVIL_STATUTS as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreStatut === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Type</span>
                    <select name="type" class="tw-input">
                        <option value="">Tous les types</option>
                        <?php foreach (MAIRE_ETAT_CIVIL_TYPES as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreType === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="tw-btn-primary">Filtrer</button>
                    <a href="etat-civil.php" class="tw-btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <?php if ($demandes === []): ?>
                <article class="tw-card p-10 text-center">
                    <div class="mx-auto w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-3xl mb-4">📭</div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucun dossier trouvé</h2>
                    <p class="text-slate-600 dark:text-slate-400">Essaie un autre critère de recherche ou retire un filtre pour afficher davantage de demandes.</p>
                </article>
            <?php else: ?>
                <?php foreach ($demandes as $demande):
                    $id = (int) ($demande['id'] ?? 0);
                    $reference = (string) ($demande['reference_dossier'] ?? '');
                    $pieces = $piecesParDemande[$id] ?? [];
                    $isFocused = $filtreReference !== '' && $reference === $filtreReference;
                    $createdAt = (string) ($demande['created_at'] ?? '');
                    $dateDepot = $createdAt !== '' ? date('d/m/Y à H:i', strtotime($createdAt)) : '—';
                    $statut = (string) ($demande['statut'] ?? '');
                    $adminNotes = (string) ($demande['admin_notes'] ?? '');
                ?>
                    <article id="dossier-<?php echo $id; ?>" class="tw-card p-5 md:p-6 <?php echo $isFocused ? 'ring-2 ring-mairie-500 dark:ring-mairie-300 shadow-xl' : ''; ?>">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <code class="px-2.5 py-1 rounded-lg bg-mairie-50 dark:bg-mairie-950/40 text-mairie-700 dark:text-mairie-300 font-mono text-xs font-bold"><?php echo htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'); ?></code>
                                    <span class="<?php echo maire_classe_badge_statut_etat_civil($statut); ?> inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black">
                                        <?php echo htmlspecialchars(maire_libelle_statut_demande_etat_civil($statut), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if ($isFocused): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-gold-100 text-gold-900 dark:bg-gold-900/30 dark:text-gold-200 text-xs font-black">Repéré</span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white mb-1">
                                    <?php echo htmlspecialchars(maire_libelle_type_demande_etat_civil((string) ($demande['type_demande'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                    Déposé le <strong class="text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($dateDepot, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    par <strong class="text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars((string) ($demande['nom_complet'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 min-w-[240px]">
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Pièces</p>
                                    <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo (int) ($demande['pieces_count'] ?? 0); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Email</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars((string) ($demande['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Téléphone</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) (($demande['telephone'] ?? '') !== '' ? $demande['telephone'] : '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">CNI</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars((string) (($demande['cni'] ?? '') !== '' ? $demande['cni'] : '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid xl:grid-cols-[1.45fr_0.9fr] gap-4 mt-5">
                            <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 p-4">
                                <h3 class="text-sm font-black uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400 mb-3">Dossier demandeur</h3>
                                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400 font-bold">Date de naissance</dt>
                                        <dd class="text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) (($demande['date_naissance'] ?? '') !== '' ? date('d/m/Y', strtotime((string) $demande['date_naissance'])) : 'Non renseignée'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400 font-bold">Lieu de naissance</dt>
                                        <dd class="text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) (($demande['lieu_naissance'] ?? '') !== '' ? $demande['lieu_naissance'] : 'Non renseigné'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    </div>
                                </dl>

                                <div class="grid lg:grid-cols-2 gap-3 mt-4">
                                    <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3">
                                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Adresse</p>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars((string) (($demande['adresse'] ?? '') !== '' ? $demande['adresse'] : 'Aucune adresse fournie.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3">
                                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Précisions</p>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars((string) (($demande['details'] ?? '') !== '' ? $demande['details'] : 'Aucune précision fournie.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-3 mt-3">
                                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Dernier message mairie</p>
                                    <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars($adminNotes !== '' ? $adminNotes : 'Aucun message personnalisé enregistré.', ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>

                            <div class="rounded-3xl bg-slate-950 text-white p-4 border border-slate-800">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <h3 class="text-sm font-black uppercase tracking-[0.22em] text-white/70">Pièces & actions</h3>
                                    <span class="text-xs font-bold text-white/60"><?php echo count($pieces); ?> fichier<?php echo count($pieces) > 1 ? 's' : ''; ?></span>
                                </div>

                                <?php if ($pieces === []): ?>
                                    <p class="text-sm text-white/70">Aucune pièce jointe pour ce dossier.</p>
                                <?php else: ?>
                                    <ul class="space-y-2 mb-4">
                                        <?php foreach ($pieces as $piece): ?>
                                            <li>
                                                <a
                                                    class="flex items-center justify-between gap-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-2 transition"
                                                    href="../<?php echo htmlspecialchars((string) ($piece['chemin_fichier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span class="text-sm font-bold text-white/90 truncate"><?php echo htmlspecialchars((string) ($piece['nom_fichier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="text-xs text-gold-300 font-black">Ouvrir</span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="flex flex-wrap gap-2">
                                    <a class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 text-sm font-black transition" href="../suivi-etat-civil.php?ref=<?php echo urlencode($reference); ?>" target="_blank" rel="noopener noreferrer">Suivi public</a>
                                    <a class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-sm font-bold transition" href="../telecharger-recepisse.php?ref=<?php echo urlencode($reference); ?>" target="_blank" rel="noopener noreferrer">Récépissé</a>
                                </div>
                            </div>
                        </div>

                        <form method="post" class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <?php echo maire_csrf_field($csrfScope); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($filtreRecherche, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($filtreStatut, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_type" value="<?php echo htmlspecialchars($filtreType, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return_ref" value="<?php echo htmlspecialchars($filtreReference, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="flex flex-wrap items-end gap-3">
                                <label class="block min-w-[220px]">
                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Changer le statut</span>
                                    <select name="statut" class="tw-input">
                                        <?php foreach (MAIRE_ETAT_CIVIL_STATUTS as $code => $label): ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statut === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-bold text-slate-700 dark:text-slate-200">
                                    <input type="checkbox" class="js-ec-auto-template rounded border-slate-300 text-mairie-700 focus:ring-mairie-500" <?php echo $adminNotes === '' || $adminNotes === maire_modele_message_demande_etat_civil($statut) ? 'checked' : ''; ?>>
                                    Auto selon le statut
                                </label>
                                <button
                                    type="button"
                                    class="js-ec-template-current inline-flex items-center px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-bold text-slate-700 dark:text-slate-200 hover:border-mairie-500 hover:text-mairie-700 dark:hover:text-mairie-300 transition"
                                >
                                    Appliquer le modèle du statut
                                </button>
                            </div>

                            <label class="block">
                                <span class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Message de la mairie pour l'e-mail citoyen</span>
                                <textarea name="admin_notes" rows="4" maxlength="4000" class="tw-input resize-y" placeholder="Ex. Votre acte est prêt au retrait muni de votre pièce d'identité."><?php echo htmlspecialchars($adminNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <?php foreach (MAIRE_ETAT_CIVIL_STATUTS as $code => $label): ?>
                                        <?php
                                        $templateButtonClass = match ($code) {
                                            'recu' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-400 hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-900/40',
                                            'en_cours' => 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-400 hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200 dark:hover:bg-blue-900/40',
                                            'valide' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-400 hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200 dark:hover:bg-emerald-900/40',
                                            'pret' => 'border-teal-200 bg-teal-50 text-teal-800 hover:border-teal-400 hover:bg-teal-100 dark:border-teal-900/60 dark:bg-teal-950/30 dark:text-teal-200 dark:hover:bg-teal-900/40',
                                            'rejete' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-400 hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200 dark:hover:bg-rose-900/40',
                                            default => 'border-slate-200 bg-white text-slate-700 hover:border-mairie-500 hover:text-mairie-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-mairie-300',
                                        };
                                        ?>
                                        <button
                                            type="button"
                                            class="js-ec-template inline-flex items-center px-3 py-1.5 rounded-full border text-xs font-bold transition shadow-sm hover:-translate-y-0.5 <?php echo $templateButtonClass; ?>"
                                            data-template-status="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Insérer le modèle <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Modèle <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">Ce message est enregistré sur le dossier et ajouté à l'e-mail si le statut change. Si tu modifies manuellement le texte, l'auto-remplissage se coupe pour préserver ta personnalisation.</span>
                            </label>

                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="tw-btn-primary">Enregistrer le statut et le message</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <?php if (count($demandes) >= 120): ?>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-4">Affichage limité aux 120 dossiers les plus pertinents. Affine les filtres pour cibler un sous-ensemble plus précis.</p>
        <?php endif; ?>
    </section>
</main>
<script>
(function () {
    const templates = <?php echo $messageTemplatesJson ?: '{}'; ?>;
    const knownTemplates = Object.values(templates);

    function getFormState(form) {
        return {
            textarea: form.querySelector('textarea[name="admin_notes"]'),
            select: form.querySelector('select[name="statut"]'),
            autoToggle: form.querySelector('.js-ec-auto-template'),
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

    document.querySelectorAll('.js-ec-template').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.closest('form');
            if (!form) return;

            const state = getFormState(form);
            if (!state.textarea || !state.select) return;

            const requestedStatus = button.getAttribute('data-template-status') || '';
            const status = requestedStatus !== '' ? requestedStatus : state.select.value;

            if (requestedStatus !== '') {
                state.select.value = requestedStatus;
            }

            if (state.autoToggle) state.autoToggle.checked = true;
            applyTemplate(form, status, true);
            state.textarea.focus();
            state.textarea.setSelectionRange(state.textarea.value.length, state.textarea.value.length);
        });
    });

    document.querySelectorAll('.js-ec-template-current').forEach(function (button) {
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
