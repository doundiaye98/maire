<?php
declare(strict_types=1);

/**
 * Connexion citoyen (habitants de la commune).
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
$emailSaisi = '';

if (empty($_SESSION['citoyen_csrf'])) {
    $_SESSION['citoyen_csrf'] = bin2hex(random_bytes(32));
}

$besoin = trim((string) ($_GET['besoin'] ?? ''));
switch ($besoin) {
    case 'connexion':
        $message = 'Connectez-vous pour accéder à cette page.';
        $messageType = 'warning';
        break;
    case 'expire':
        $message = 'Votre session a expiré, merci de vous reconnecter.';
        $messageType = 'warning';
        break;
    case 'desactive':
        $message = 'Votre compte a été désactivé. Contactez la mairie.';
        $messageType = 'danger';
        break;
    case 'deconnexion':
        $message = 'Vous avez été déconnecté avec succès.';
        $messageType = 'success';
        break;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['citoyen_csrf'], $csrf)) {
        $message = 'Jeton de sécurité invalide. Recharge la page.';
        $messageType = 'danger';
    } elseif (!maire_rate_limit_allow('citoyen_login', 120, 180)) {
        $message = 'Trop de tentatives de connexion. Réessaie dans quelques minutes.';
        $messageType = 'danger';
    } else {
        $emailSaisi = trim((string) ($_POST['email'] ?? ''));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        if ($emailSaisi === '' || $motDePasse === '') {
            $message = 'E-mail et mot de passe requis.';
            $messageType = 'danger';
        } elseif (!maire_citoyen_attempt_login($pdo, $emailSaisi, $motDePasse)) {
            $message = 'Identifiants incorrects ou compte inactif.';
            $messageType = 'danger';
        } else {
            $apres = (string) ($_GET['apres'] ?? ($_POST['apres'] ?? ''));
            if ($apres === 'signaler') {
                header('Location: signaler.php', true, 302);
            } else {
                header('Location: profil.php', true, 302);
            }
            exit;
        }
    }
}

$pageTitle = 'Espace citoyen · Connexion';
$pageDescription = 'Accédez à votre espace habitant : signalements, suivi de vos demandes, notifications.';
require __DIR__ . '/../includes/header.php';

$alertClasses = [
    'danger' => 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200',
    'success' => 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200',
    'warning' => 'bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-200',
    'info' => 'bg-blue-50 dark:bg-blue-950/30 border-blue-300 dark:border-blue-800 text-blue-800 dark:text-blue-200',
];
$alertIcons = ['danger' => '⚠️', 'success' => '✅', 'warning' => '⚠️', 'info' => 'ℹ️'];
?>
<main class="overflow-hidden">
    <!-- HERO + FORM (split layout) -->
    <section class="relative maire-hero-bg text-white min-h-[80vh] flex items-center maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 relative z-10 grid lg:grid-cols-2 gap-12 items-center">
            <!-- LEFT : pitch -->
            <div>
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Espace citoyen
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Bienvenue<br><span class="maire-text-gradient">habitant·e</span>
                </h1>
                <p class="text-lg text-mairie-100 leading-relaxed mb-8 max-w-xl">
                    Connectez-vous pour signaler un problème, suivre vos demandes ou consulter votre profil.
                </p>
                <ul class="space-y-3 text-mairie-100">
                    <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-emerald-500/30 text-emerald-300 flex items-center justify-center text-sm">📍</span> Signalez les problèmes de votre quartier</li>
                    <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-emerald-500/30 text-emerald-300 flex items-center justify-center text-sm">📋</span> Suivez vos demandes et paiements</li>
                    <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-emerald-500/30 text-emerald-300 flex items-center justify-center text-sm">🗳️</span> Participez aux consultations citoyennes</li>
                    <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-emerald-500/30 text-emerald-300 flex items-center justify-center text-sm">🔔</span> Recevez les alertes municipales</li>
                </ul>
            </div>

            <!-- RIGHT : form -->
            <div class="w-full max-w-md mx-auto lg:ml-auto">
                <article class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-8 md:p-10">
                    <div class="mb-5">
                        <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-2xl shadow-md mb-4">🔐</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">Connexion</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Accédez à votre espace habitant.</p>
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

                    <form method="POST" action="connexion.php<?php echo isset($_GET['apres']) ? '?apres=' . urlencode((string) $_GET['apres']) : ''; ?>" autocomplete="on" class="space-y-4">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['citoyen_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Adresse e-mail</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($emailSaisi, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="mot_de_passe" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Mot de passe</label>
                            <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>

                        <button type="submit" class="tw-btn-primary w-full justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            Se connecter
                        </button>
                    </form>

                    <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wider font-bold text-slate-400">
                        <span class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></span>
                        Ou
                        <span class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></span>
                    </div>

                    <a class="tw-btn-outline w-full justify-center" href="inscription.php">
                        ✨ Créer un compte habitant
                    </a>

                    <div class="mt-6 p-3 rounded-xl bg-mairie-50 dark:bg-mairie-950/30 border border-mairie-200 dark:border-mairie-800 text-xs text-mairie-800 dark:text-mairie-200">
                        <p class="font-bold mb-1">🧪 Compte démo :</p>
                        <p>E-mail : <code class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 font-mono">citoyen@demo.rufisque.sn</code></p>
                        <p>Mot de passe : <code class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 font-mono">DemoCitoyen2026!</code></p>
                    </div>

                    <p class="text-xs text-center text-slate-500 dark:text-slate-400 mt-4">
                        Vous êtes agent de la mairie ? <a class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="../abonnement.php">Connexion agent</a>
                    </p>
                </article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
