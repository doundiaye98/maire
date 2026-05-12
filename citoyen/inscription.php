<?php
declare(strict_types=1);

/**
 * Inscription d'un nouveau compte citoyen.
 * Accessible sans authentification.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-paths.php';
require_once __DIR__ . '/../includes/citoyen-session.php';
require_once __DIR__ . '/../includes/maire-rate-limit.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if (maire_citoyen_session_valid()) {
    header('Location: profil.php', true, 302);
    exit;
}

if ($pdo !== null && !maire_feature_disponible($pdo, 'comptes_citoyens')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('comptes_citoyens', $palierCommune, 'public');
    exit;
}

$message = '';
$messageType = 'info';
$dataSaisie = ['email' => '', 'prenom' => '', 'nom' => '', 'telephone' => '', 'quartier' => ''];

if (empty($_SESSION['citoyen_csrf'])) {
    $_SESSION['citoyen_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['citoyen_csrf'], $csrf)) {
        $message = 'Jeton de sécurité invalide. Recharge la page et réessaie.';
        $messageType = 'danger';
    } elseif (!maire_rate_limit_allow('citoyen_inscription', 30, 600)) {
        $message = 'Trop de tentatives d’inscription depuis ce réseau. Réessaie dans quelques minutes.';
        $messageType = 'danger';
    } else {
        $dataSaisie = [
            'email' => trim((string) ($_POST['email'] ?? '')),
            'prenom' => trim((string) ($_POST['prenom'] ?? '')),
            'nom' => trim((string) ($_POST['nom'] ?? '')),
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
            'quartier' => trim((string) ($_POST['quartier'] ?? '')),
        ];
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $motDePasseConfirm = (string) ($_POST['mot_de_passe_confirm'] ?? '');
        if ($motDePasse !== $motDePasseConfirm) {
            $message = 'Les deux mots de passe ne correspondent pas.';
            $messageType = 'danger';
        } else {
            $err = null;
            $id = maire_creer_citoyen($pdo, array_merge($dataSaisie, ['mot_de_passe' => $motDePasse]), $err);
            if ($id === null) {
                $message = $err ?? 'Inscription impossible.';
                $messageType = 'danger';
            } else {
                if (maire_citoyen_attempt_login($pdo, $dataSaisie['email'], $motDePasse)) {
                    header('Location: profil.php?bienvenue=1', true, 302);
                    exit;
                }
                $message = 'Compte créé. Vous pouvez maintenant vous connecter.';
                $messageType = 'success';
            }
        }
    }
}

$pageTitle = 'Espace citoyen · Créer mon compte';
$pageDescription = 'Inscription pour les habitants de Rufisque-Est : signalements, suivi de demandes, consultations.';
require __DIR__ . '/../includes/header.php';

$alertClasses = [
    'danger' => 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200',
    'success' => 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200',
    'info' => 'bg-blue-50 dark:bg-blue-950/30 border-blue-300 dark:border-blue-800 text-blue-800 dark:text-blue-200',
];
$alertIcons = ['danger' => '⚠️', 'success' => '✅', 'info' => 'ℹ️'];
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-16 maire-grain min-h-screen flex items-center">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 relative z-10 grid lg:grid-cols-[1fr_1.2fr] gap-10 items-center">
            <div>
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Espace citoyen · Inscription
                </span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-[0.95] tracking-tight mb-5">
                    Rejoignez votre<br><span class="maire-text-gradient">commune</span>
                </h1>
                <p class="text-lg text-mairie-100 leading-relaxed mb-6">
                    Inscrivez-vous pour signaler un problème dans votre quartier, suivre vos demandes et recevoir les actualités de votre mairie.
                </p>
                <ul class="space-y-2 text-sm text-mairie-100">
                    <li class="flex items-center gap-2"><span class="text-emerald-400 font-black">✓</span> 100% gratuit</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-400 font-black">✓</span> Données protégées</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-400 font-black">✓</span> Aucune publicité</li>
                </ul>
            </div>

            <article class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-7 md:p-9">
                <div class="mb-5">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-gold-500 to-orange-600 text-white items-center justify-center text-2xl shadow-md mb-3">✨</span>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">Créer mon compte</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Quelques informations et c'est parti !</p>
                </div>

                <?php if ($message !== ''):
                    $cls = $alertClasses[$messageType] ?? $alertClasses['info'];
                    $ic = $alertIcons[$messageType] ?? 'ℹ️';
                ?>
                    <div class="<?php echo $cls; ?> border-2 rounded-2xl p-3 mb-4 flex items-start gap-2">
                        <span class="text-lg flex-shrink-0"><?php echo $ic; ?></span>
                        <p class="text-sm font-bold"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="inscription.php" autocomplete="on" class="space-y-3">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['citoyen_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label for="prenom" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required maxlength="80" value="<?php echo htmlspecialchars($dataSaisie['prenom'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="nom" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Nom *</label>
                            <input type="text" id="nom" name="nom" required maxlength="80" value="<?php echo htmlspecialchars($dataSaisie['nom'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Adresse e-mail *</label>
                        <input type="email" id="email" name="email" required maxlength="190" value="<?php echo htmlspecialchars($dataSaisie['email'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label for="telephone" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Téléphone <small class="text-slate-400 font-normal">(facultatif)</small></label>
                            <input type="tel" id="telephone" name="telephone" maxlength="40" value="<?php echo htmlspecialchars($dataSaisie['telephone'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="tel" placeholder="+221 77..." class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="quartier" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Quartier <small class="text-slate-400 font-normal">(facultatif)</small></label>
                            <input type="text" id="quartier" name="quartier" maxlength="120" value="<?php echo htmlspecialchars($dataSaisie['quartier'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Keury Souf, Darou..." class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <div>
                            <label for="mot_de_passe" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Mot de passe * <small class="text-slate-400 font-normal">(≥ 8 caractères)</small></label>
                            <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8" autocomplete="new-password" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="mot_de_passe_confirm" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Confirmation *</label>
                            <input type="password" id="mot_de_passe_confirm" name="mot_de_passe_confirm" required minlength="8" autocomplete="new-password" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                    </div>

                    <button type="submit" class="tw-btn-primary w-full justify-center mt-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Créer mon compte
                    </button>
                </form>

                <p class="text-xs text-center text-slate-500 dark:text-slate-400 mt-5">
                    Déjà inscrit ? <a class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="connexion.php">Se connecter</a>
                </p>
                <p class="text-[11px] text-center text-slate-400 mt-2">🔒 Vos informations restent strictement réservées à la mairie.</p>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
