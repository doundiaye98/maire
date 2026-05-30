<?php
declare(strict_types=1);

/**
 * Profil du citoyen connecté : informations, changement de mot de passe,
 * accès rapide aux signalements et services.
 */
require __DIR__ . '/../includes/citoyen-guard.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/audiences-maire.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/paiements.php';
require_once __DIR__ . '/../includes/csrf.php';

$citoyenCsrfScope = MAIRE_CSRF_SCOPE_CITOYEN;

if ($pdo !== null) {
    maire_ensure_citoyens_notif_columns($pdo);
}

$flash = '';
$flashType = 'success';
$bienvenue = isset($_GET['bienvenue']) ? '1' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = (string) ($_POST['action'] ?? '');
    if (!maire_csrf_validate($citoyenCsrfScope)) {
        $flash = maire_csrf_error_message();
        $flashType = 'danger';
    } else {
        $idCit = maire_citoyen_current_id() ?? 0;
        switch ($action) {
            case 'maj_profil':
                $prenom = trim((string) ($_POST['prenom'] ?? ''));
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $telephone = trim((string) ($_POST['telephone'] ?? ''));
                $quartier = trim((string) ($_POST['quartier'] ?? ''));
                if ($prenom === '' || $nom === '') {
                    $flash = 'Prénom et nom requis.';
                    $flashType = 'danger';
                    break;
                }
                try {
                    $pdo->prepare('
                        UPDATE citoyens
                        SET prenom = :p, nom = :n, telephone = :t, quartier = :q
                        WHERE id = :id
                    ')->execute([
                        'p' => mb_substr($prenom, 0, 80),
                        'n' => mb_substr($nom, 0, 80),
                        't' => $telephone !== '' ? mb_substr($telephone, 0, 40) : null,
                        'q' => $quartier !== '' ? mb_substr($quartier, 0, 120) : null,
                        'id' => $idCit,
                    ]);
                    $_SESSION['citoyen_prenom'] = $prenom;
                    $_SESSION['citoyen_nom'] = $nom;
                    $flash = 'Profil mis à jour.';
                } catch (Throwable $e) {
                    $flash = 'Erreur lors de la mise à jour : ' . $e->getMessage();
                    $flashType = 'danger';
                }
                break;

            case 'maj_notif':
                $acceptEmail = isset($_POST['accepte_email']);
                $acceptSms = isset($_POST['accepte_sms']);
                if (maire_mettre_a_jour_preferences_notif_citoyen($pdo, $idCit, $acceptEmail, $acceptSms)) {
                    $flash = 'Préférences de notification mises à jour.';
                } else {
                    $flash = 'Impossible de mettre à jour les préférences.';
                    $flashType = 'danger';
                }
                break;

            case 'maj_mdp':
                $actuel = (string) ($_POST['mdp_actuel'] ?? '');
                $nouveau = (string) ($_POST['mdp_nouveau'] ?? '');
                $confirm = (string) ($_POST['mdp_confirm'] ?? '');
                $compte = maire_load_citoyen($pdo, $idCit);
                if ($compte === null) {
                    $flash = 'Compte introuvable.';
                    $flashType = 'danger';
                } elseif (!password_verify($actuel, (string) $compte['mot_de_passe_hash'])) {
                    $flash = 'Mot de passe actuel incorrect.';
                    $flashType = 'danger';
                } elseif (strlen($nouveau) < 8) {
                    $flash = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
                    $flashType = 'danger';
                } elseif ($nouveau !== $confirm) {
                    $flash = 'La confirmation ne correspond pas.';
                    $flashType = 'danger';
                } else {
                    try {
                        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
                        $pdo->prepare('UPDATE citoyens SET mot_de_passe_hash = :h WHERE id = :id')
                            ->execute(['h' => $hash, 'id' => $idCit]);
                        $flash = 'Mot de passe mis à jour.';
                    } catch (Throwable $e) {
                        $flash = 'Erreur : ' . $e->getMessage();
                        $flashType = 'danger';
                    }
                }
                break;
        }
    }
}

$idCit = maire_citoyen_current_id() ?? 0;
$compte = $pdo !== null ? maire_load_citoyen($pdo, $idCit) : null;

$signalements = [];
$compteursStatut = ['nouveau' => 0, 'pris_en_charge' => 0, 'resolu' => 0, 'rejete' => 0];
if ($pdo !== null && $compte !== null) {
    $signalements = maire_liste_signalements_citoyen($pdo, $idCit, 5);
    foreach (maire_liste_signalements_citoyen($pdo, $idCit, 500) as $s) {
        $st = (string) ($s['statut'] ?? '');
        if (isset($compteursStatut[$st])) {
            $compteursStatut[$st]++;
        }
    }
}

$pageTitle = 'Espace citoyen · Mon profil';
require __DIR__ . '/../includes/header.php';
?>
<main class="bg-slate-50 dark:bg-slate-950 min-h-screen pb-16">
    <!-- HERO CITOYEN -->
    <section class="relative bg-gradient-to-br from-mairie-800 via-mairie-700 to-mairie-900 text-white overflow-hidden">
        <div class="absolute inset-0 pointer-events-none opacity-25" aria-hidden="true">
            <div class="absolute top-0 right-0 w-96 h-96 bg-gold-500/40 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-mairie-300/30 rounded-full blur-3xl"></div>
        </div>
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 relative">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-white/15 backdrop-blur-sm border border-white/20 flex items-center justify-center text-3xl shadow-glow">
                    👋
                </div>
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-xs font-semibold uppercase tracking-wider text-mairie-100 mb-2">
                        Espace citoyen
                    </span>
                    <h1 class="text-3xl md:text-4xl font-bold">Bonjour, <?php echo htmlspecialchars((string) ($compte['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> !</h1>
                    <p class="text-mairie-100 mt-1">Votre tableau de bord : profil, signalements et accès rapides aux services.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">

        <?php if ($bienvenue): ?>
            <div class="tw-card p-5 mb-4 border-l-4 border-l-green-500 flex items-start gap-3 animate-fade-in">
                <span class="text-2xl">🎉</span>
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">Bienvenue dans votre espace citoyen !</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Vous pouvez maintenant signaler un problème, suivre vos demandes et recevoir les notifications de la mairie.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($flash !== ''): ?>
            <div class="tw-card p-4 mb-4 border-l-4 <?php echo $flashType === 'danger' ? 'border-l-red-500 bg-red-50 dark:bg-red-950/30' : 'border-l-green-500 bg-green-50 dark:bg-green-950/30'; ?> animate-fade-in">
                <p class="<?php echo $flashType === 'danger' ? 'text-red-800 dark:text-red-200' : 'text-green-800 dark:text-green-200'; ?> font-medium">
                    <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- KPI signalements -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="tw-card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Nouveaux</p>
                        <p class="text-2xl font-bold text-orange-500 mt-1"><?php echo (int) $compteursStatut['nouveau']; ?></p>
                    </div>
                    <span class="text-2xl">🆕</span>
                </div>
            </div>
            <div class="tw-card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">En cours</p>
                        <p class="text-2xl font-bold text-sky-500 mt-1"><?php echo (int) $compteursStatut['pris_en_charge']; ?></p>
                    </div>
                    <span class="text-2xl">⏳</span>
                </div>
            </div>
            <div class="tw-card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Résolus</p>
                        <p class="text-2xl font-bold text-green-500 mt-1"><?php echo (int) $compteursStatut['resolu']; ?></p>
                    </div>
                    <span class="text-2xl">✅</span>
                </div>
            </div>
            <div class="tw-card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Rejetés</p>
                        <p class="text-2xl font-bold text-red-500 mt-1"><?php echo (int) $compteursStatut['rejete']; ?></p>
                    </div>
                    <span class="text-2xl">❌</span>
                </div>
            </div>
        </div>

        <?php if ($pdo !== null && maire_feature_disponible($pdo, 'audiences_maire')): ?>
        <div class="tw-card p-5 mb-6 bg-gradient-to-r from-gold-50 to-white dark:from-gold-950/20 dark:to-slate-800 flex flex-wrap items-center justify-between gap-4 border-gold-200 dark:border-gold-800/50">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-2xl shadow-md">🤝</span>
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">Audience avec le Maire</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Demande en présentiel ou en visioconférence</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a class="tw-btn-primary text-sm" href="../audiences-maire.php">Nouvelle demande</a>
                <a class="tw-btn-outline text-sm" href="audiences.php">Mes audiences</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bandeau CTA signalement -->
        <div class="tw-card p-5 mb-6 bg-gradient-to-r from-mairie-50 to-white dark:from-mairie-900/30 dark:to-slate-800 flex flex-wrap items-center justify-between gap-4 border-mairie-200 dark:border-mairie-700">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white flex items-center justify-center text-2xl shadow-md">🚨</span>
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">Vous avez un problème à signaler ?</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Route abîmée, lampadaire hors service, dépôts sauvages…</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a class="tw-btn-primary text-sm" href="signaler.php">
                    Nouveau signalement
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </a>
                <a class="tw-btn-outline text-sm" href="mes-signalements.php">Voir tous</a>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">

            <!-- Derniers signalements -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white flex items-center justify-center shadow-md">📋</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Derniers signalements</h2>
                </div>
                <?php if (empty($signalements)): ?>
                    <div class="text-center py-8 text-slate-400 dark:text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <p class="text-sm">Vous n'avez pas encore fait de signalement.</p>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php foreach (array_slice($signalements, 0, 5) as $s): ?>
                        <?php
                        $statut = (string) ($s['statut'] ?? '');
                        $badgeClass = match ($statut) {
                            'nouveau' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
                            'pris_en_charge' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                            'resolu' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
                            'rejete' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                        };
                        ?>
                        <li class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <strong class="text-slate-900 dark:text-white text-sm flex-1"><?php echo htmlspecialchars((string) ($s['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="tw-badge <?php echo $badgeClass; ?> flex-shrink-0">
                                    <?php echo htmlspecialchars(maire_libelle_statut_signalement($statut), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <?php echo htmlspecialchars(maire_libelle_categorie_signalement((string) ($s['categorie'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars((string) ($s['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>

            <!-- Mes informations -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">👤</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Mes informations</h2>
                </div>
                <form method="POST" action="profil.php" class="space-y-3">
                    <?php echo maire_csrf_field($citoyenCsrfScope); ?>
                    <input type="hidden" name="action" value="maj_profil">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="prenom" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Prénom</label>
                            <input type="text" id="prenom" name="prenom" required maxlength="80"
                                   value="<?php echo htmlspecialchars((string) ($compte['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                   class="tw-input">
                        </div>
                        <div>
                            <label for="nom" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom</label>
                            <input type="text" id="nom" name="nom" required maxlength="80"
                                   value="<?php echo htmlspecialchars((string) ($compte['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                   class="tw-input">
                        </div>
                    </div>
                    <div>
                        <label for="telephone" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" maxlength="40"
                               value="<?php echo htmlspecialchars((string) ($compte['telephone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               class="tw-input">
                    </div>
                    <div>
                        <label for="quartier" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Quartier</label>
                        <input type="text" id="quartier" name="quartier" maxlength="120"
                               value="<?php echo htmlspecialchars((string) ($compte['quartier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               class="tw-input">
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">E-mail :</span>
                        <strong class="text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars((string) ($compte['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="text-xs text-slate-400">(non modifiable)</span>
                    </div>
                    <button type="submit" class="tw-btn-primary w-full">Enregistrer les modifications</button>
                </form>
            </article>

            <!-- Préférences notifications -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center shadow-md">📨</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Préférences de notification</h2>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Comment la mairie peut vous contacter en cas d'urgence ou d'information importante.</p>
                <form method="POST" action="profil.php" class="space-y-3">
                    <?php echo maire_csrf_field($citoyenCsrfScope); ?>
                    <input type="hidden" name="action" value="maj_notif">
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer transition-colors">
                        <input type="checkbox" name="accepte_email" value="1"
                               <?php echo (int) ($compte['accepte_notif_email'] ?? 1) === 1 ? 'checked' : ''; ?>
                               class="mt-1 w-5 h-5 rounded text-mairie-600 focus:ring-mairie-500 cursor-pointer">
                        <div class="flex-1">
                            <span class="block font-semibold text-slate-900 dark:text-white">📧 Notifications par e-mail</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Alertes, événements, coupures…</span>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer transition-colors <?php echo empty($compte['telephone']) ? 'opacity-60' : ''; ?>">
                        <input type="checkbox" name="accepte_sms" value="1"
                               <?php echo (int) ($compte['accepte_notif_sms'] ?? 1) === 1 ? 'checked' : ''; ?>
                               <?php echo empty($compte['telephone']) ? 'disabled' : ''; ?>
                               class="mt-1 w-5 h-5 rounded text-mairie-600 focus:ring-mairie-500 cursor-pointer">
                        <div class="flex-1">
                            <span class="block font-semibold text-slate-900 dark:text-white">📱 Notifications par SMS</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Urgences uniquement</span>
                            <?php if (empty($compte['telephone'])): ?>
                                <span class="block text-xs text-red-600 dark:text-red-400 mt-1">⚠ Ajoutez votre téléphone pour activer ce canal.</span>
                            <?php endif; ?>
                        </div>
                    </label>
                    <button type="submit" class="tw-btn-primary w-full">Enregistrer mes préférences</button>
                </form>
            </article>

            <!-- Mes paiements -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white flex items-center justify-center shadow-md">💳</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Mes paiements</h2>
                </div>
                <?php $mesPaiements = maire_paiements_pour_citoyen($pdo, (int) $compte['id'], 10); ?>
                <?php if (empty($mesPaiements)): ?>
                    <div class="text-center py-6 text-slate-400 dark:text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <p class="text-sm">Vous n'avez pas encore effectué de paiement.</p>
                        <a href="../paiements.php" class="text-mairie-600 dark:text-mairie-400 text-sm font-semibold hover:underline">Découvrir les services payants →</a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto -mx-2">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                <th class="py-2 px-2 font-semibold">Date</th>
                                <th class="py-2 px-2 font-semibold">Service</th>
                                <th class="py-2 px-2 font-semibold">Montant</th>
                                <th class="py-2 px-2 font-semibold">Statut</th>
                                <th class="py-2 px-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($mesPaiements as $p):
                            $st = (string) $p['statut'];
                            $paiBadge = match ($st) {
                                'paye' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
                                'echec' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                                'en_attente' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            };
                        ?>
                            <tr>
                                <td class="py-2 px-2 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars(substr((string) $p['created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-2 px-2 text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars((string) $p['service_libelle'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-2 px-2 font-semibold text-slate-900 dark:text-white whitespace-nowrap"><?php echo maire_paiement_format_montant((float) $p['montant'], (string) $p['devise']); ?></td>
                                <td class="py-2 px-2"><span class="tw-badge <?php echo $paiBadge; ?>"><?php echo htmlspecialchars(maire_paiement_libelle_statut($st), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="py-2 px-2"><a href="../paiement-retour.php?ref=<?php echo urlencode((string) $p['reference']); ?>" class="text-mairie-600 dark:text-mairie-400 font-semibold hover:underline text-xs">Voir →</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
                <a class="tw-btn-outline w-full text-sm mt-4" href="../paiements.php">+ Nouveau paiement</a>
            </article>

            <!-- Mot de passe -->
            <article class="tw-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white flex items-center justify-center shadow-md">🔒</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Changer mon mot de passe</h2>
                </div>
                <form method="POST" action="profil.php" autocomplete="off" class="space-y-3">
                    <?php echo maire_csrf_field($citoyenCsrfScope); ?>
                    <input type="hidden" name="action" value="maj_mdp">
                    <div>
                        <label for="mdp_actuel" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Mot de passe actuel</label>
                        <input type="password" id="mdp_actuel" name="mdp_actuel" required class="tw-input">
                    </div>
                    <div>
                        <label for="mdp_nouveau" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Nouveau mot de passe <span class="text-xs text-slate-400">(8 caractères min.)</span></label>
                        <input type="password" id="mdp_nouveau" name="mdp_nouveau" required minlength="8" class="tw-input">
                    </div>
                    <div>
                        <label for="mdp_confirm" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="mdp_confirm" name="mdp_confirm" required minlength="8" class="tw-input">
                    </div>
                    <button type="submit" class="tw-btn-primary w-full">Mettre à jour le mot de passe</button>
                </form>
            </article>

            <!-- Navigation rapide -->
            <article class="tw-card p-6 lg:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center shadow-md">🧭</span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Accès rapides</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <a href="../index.php" class="block p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-mairie-50 dark:hover:bg-mairie-900/30 border border-slate-200 dark:border-slate-700 transition-colors text-center">
                        <div class="text-2xl mb-1">🏠</div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Accueil</div>
                    </a>
                    <a href="../services.php" class="block p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-mairie-50 dark:hover:bg-mairie-900/30 border border-slate-200 dark:border-slate-700 transition-colors text-center">
                        <div class="text-2xl mb-1">🏛️</div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Services</div>
                    </a>
                    <a href="../actualites.php" class="block p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-mairie-50 dark:hover:bg-mairie-900/30 border border-slate-200 dark:border-slate-700 transition-colors text-center">
                        <div class="text-2xl mb-1">🗞️</div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Actualités</div>
                    </a>
                    <a href="deconnexion.php" class="block p-3 rounded-xl bg-red-50 dark:bg-red-950/30 hover:bg-red-100 dark:hover:bg-red-900/40 border border-red-200 dark:border-red-800 transition-colors text-center">
                        <div class="text-2xl mb-1">🚪</div>
                        <div class="text-sm font-semibold text-red-700 dark:text-red-300">Déconnexion</div>
                    </a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
