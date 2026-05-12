<?php
declare(strict_types=1);

/**
 * Page de connexion dédiée aux comptes « super-admin éditeur ».
 * Volontairement distincte de /abonnement.php (login mairie).
 *
 * Cette page n'utilise PAS admin-guard / subscription-guard :
 * elle doit rester accessible sans session.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-paths.php';
require_once __DIR__ . '/../includes/super-admin-account.php';

$message = '';
$messageType = 'info';
$emailSaisie = '';

if ($pdo === null) {
    $message = 'Service indisponible : connexion à la base de données impossible.';
    $messageType = 'danger';
} else {
    try {
        maire_ensure_super_admins_table($pdo);
    } catch (Throwable $e) {
        $message = 'Table super_admins indisponible : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $messageType = 'danger';
    }
}

if (empty($_SESSION['editeur_login_csrf'])) {
    $_SESSION['editeur_login_csrf'] = bin2hex(random_bytes(32));
}

$besoin = (string) ($_GET['besoin'] ?? '');
if ($besoin !== '' && $message === '') {
    switch ($besoin) {
        case 'connexion':
            $message = 'Veuillez vous connecter à l’espace éditeur.';
            $messageType = 'warning';
            break;
        case 'expire':
            $message = 'Votre session a expiré, merci de vous reconnecter.';
            $messageType = 'warning';
            break;
        case 'desactive':
            $message = 'Votre compte éditeur a été désactivé.';
            $messageType = 'danger';
            break;
        case 'deconnexion':
            $message = 'Vous avez été déconnecté de l’espace éditeur.';
            $messageType = 'info';
            break;
        case 'indispo':
            $message = 'Service éditeur temporairement indisponible.';
            $messageType = 'danger';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrfRecu = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['editeur_login_csrf'], $csrfRecu)) {
        $message = 'Jeton de sécurité invalide. Recharge la page et réessaie.';
        $messageType = 'danger';
    } else {
        $emailSaisie = trim((string) ($_POST['email'] ?? ''));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

        if ($emailSaisie === '' || $motDePasse === '') {
            $message = 'Email et mot de passe sont requis.';
            $messageType = 'warning';
        } else {
            $compte = maire_load_super_admin_by_email($pdo, $emailSaisie);
            if ($compte === null || (int) ($compte['actif'] ?? 0) !== 1 || !password_verify($motDePasse, (string) $compte['mot_de_passe_hash'])) {
                $message = 'Identifiants éditeur incorrects ou compte désactivé.';
                $messageType = 'danger';
            } else {
                maire_super_admin_account_login(
                    $pdo,
                    (int) $compte['id'],
                    (string) $compte['email'],
                    (string) $compte['nom']
                );
                header('Location: index.php', true, 302);
                exit;
            }
        }
    }
}

$pageTitle = 'Espace éditeur · Connexion';
$pageDescription = 'Accès réservé à l’éditeur de la plateforme : suivi et suspension des abonnements.';
require __DIR__ . '/../includes/header.php';

$alertConfig = match ($messageType) {
    'danger' => ['bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200', '⚠️'],
    'warning' => ['bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-200', 'ℹ️'],
    'success' => ['bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200', '✅'],
    default => ['bg-slate-50 dark:bg-slate-900/50 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200', 'ℹ️'],
};
?>
<main class="overflow-hidden">
    <section class="relative bg-gradient-to-br from-slate-900 via-mairie-950 to-slate-950 text-white min-h-[85vh] flex items-center maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-fuchsia-500/20 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-cyan-500/20 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 relative z-10 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-fuchsia-300 mb-5">
                    <span aria-hidden="true">🛡️</span>
                    Console éditeur — accès restreint
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Espace<br><span class="bg-clip-text text-transparent bg-gradient-to-r from-fuchsia-400 via-cyan-300 to-fuchsia-300">éditeur plateforme</span>
                </h1>
                <p class="text-lg text-slate-300 leading-relaxed max-w-xl mb-6">
                    Strictement réservé aux super-administrateurs de l'entreprise éditrice. Permet le suivi des abonnements communaux et la suspension en cas de non-renouvellement.
                </p>
                <div class="grid sm:grid-cols-3 gap-3 max-w-xl">
                    <div class="p-3 rounded-2xl bg-white/5 backdrop-blur-md border border-white/10">
                        <p class="text-2xl font-black text-fuchsia-300">🏛️</p>
                        <p class="text-xs text-slate-400 mt-1 font-bold">Multi-communes</p>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 backdrop-blur-md border border-white/10">
                        <p class="text-2xl font-black text-cyan-300">⏰</p>
                        <p class="text-xs text-slate-400 mt-1 font-bold">Suivi temps réel</p>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 backdrop-blur-md border border-white/10">
                        <p class="text-2xl font-black text-amber-300">⚡</p>
                        <p class="text-xs text-slate-400 mt-1 font-bold">Suspension immédiate</p>
                    </div>
                </div>
            </div>

            <div class="w-full max-w-md mx-auto lg:ml-auto">
                <article class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl rounded-3xl shadow-2xl p-8 md:p-10 border border-white/10">
                    <div class="mb-5">
                        <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-fuchsia-600 to-cyan-700 text-white items-center justify-center text-2xl shadow-md mb-4">🔐</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">Connexion super-admin</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Identifiants éditeur uniquement.</p>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="<?php echo $alertConfig[0]; ?> border-2 rounded-2xl p-3 mb-4 flex items-start gap-2 text-sm">
                            <span class="text-lg flex-shrink-0"><?php echo $alertConfig[1]; ?></span>
                            <p class="font-bold"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" autocomplete="off" class="space-y-4">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['editeur_login_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Email éditeur</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($emailSaisie, ENT_QUOTES, 'UTF-8'); ?>" placeholder="editeur@plateforme.sn" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 dark:focus:ring-fuchsia-900 outline-none transition">
                        </div>
                        <div>
                            <label for="mot_de_passe" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Mot de passe</label>
                            <input type="password" id="mot_de_passe" name="mot_de_passe" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 dark:focus:ring-fuchsia-900 outline-none transition">
                        </div>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-fuchsia-600 to-cyan-700 hover:from-fuchsia-500 hover:to-cyan-600 text-white font-black shadow-lg transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                Se connecter
                            </button>
                            <a class="inline-flex items-center gap-2 px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 dark:hover:bg-slate-600 transition" href="../index.php">← Site</a>
                        </div>
                    </form>

                    <div class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 text-xs">
                        <p class="font-bold text-amber-700 dark:text-amber-300 mb-1">⚠️ Démonstration</p>
                        <p class="text-slate-600 dark:text-slate-400">
                            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-mono">editeur@demo.rufisque.sn</code> ·
                            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-mono">DemoEditeur2026!</code>
                        </p>
                        <p class="text-slate-500 dark:text-slate-500 mt-1">À changer lors du déploiement.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
