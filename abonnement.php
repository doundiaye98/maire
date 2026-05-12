<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/abonnement-actif-sync.php';
require_once __DIR__ . '/includes/commune-abonnement.php';
require_once __DIR__ . '/includes/compte-mairie.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($pdo !== null) {
    maire_ensure_abonnements_compte_mairie_column($pdo);
}

$message = null;
$error = false;
$alerteAcces = null;
$alerteAccesEstErreur = true;
switch (trim((string) ($_GET['besoin'] ?? ''))) {
    case 'admin':
        $alerteAcces = 'Cette page est réservée aux comptes avec le rôle administrateur. Déconnectez-vous si besoin, puis reconnectez-vous avec un compte disposant de ce rôle.';
        break;
    case 'connexion':
        $alerteAcces = 'Vous devez être connecté avec un compte personnel de mairie pour accéder à cette page.';
        break;
    case 'expire':
        $alerteAcces = 'Votre accès n’est plus dans une période valide. Un administrateur peut ajuster les dates dans Comptes & abonnement communal.';
        $alerteAccesEstErreur = false;
        break;
    default:
        break;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $motDePasse = trim((string) ($_POST['mot_de_passe'] ?? ''));

    if ($pdo === null) {
        $message = 'Connexion MySQL indisponible. Vérifiez la configuration de la base.';
        $error = true;
    } elseif ($email === '' || $motDePasse === '') {
        $message = 'Merci de renseigner l’e-mail et le mot de passe.';
        $error = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Adresse e-mail invalide.';
        $error = true;
    } elseif (!maire_rate_limit_allow('login_abonnement', 120, 180)) {
        $message = 'Trop de tentatives de connexion depuis cette adresse réseau. Merci de patienter quelques minutes avant de réessayer.';
        $error = true;
    } else {
        try {
            $find = $pdo->prepare('SELECT id, mot_de_passe_hash, actif, date_fin, date_debut, role_utilisateur, plan, compte_mairie FROM abonnements WHERE email = :email LIMIT 1');
            $find->execute(['email' => $email]);
            $abonne = $find->fetch();
        } catch (Throwable $exception) {
            $find = $pdo->prepare('SELECT id, mot_de_passe_hash, actif, date_fin, date_debut, role_utilisateur, plan FROM abonnements WHERE email = :email LIMIT 1');
            $find->execute(['email' => $email]);
            $abonne = $find->fetch();
        }

        if ($abonne === false) {
            $message = 'Identifiants incorrects ou compte inconnu.';
            $error = true;
        } else {
            $motDePasseValide = password_verify($motDePasse, (string) $abonne['mot_de_passe_hash']);
            if (!$motDePasseValide) {
                $message = 'Identifiants incorrects ou compte inconnu.';
                $error = true;
            } else {
                maire_sync_commune_vers_compte_mairie($pdo);
                maire_sync_abonnement_actif($pdo, (int) $abonne['id']);
                $find->execute(['email' => $email]);
                $abonne = $find->fetch();
                $communeRowLogin = maire_load_commune_abonnement_row($pdo);
                $serviceSuspendu = $communeRowLogin !== null && (int) ($communeRowLogin['suspendu_par_plateforme'] ?? 0) === 1;
                if ($serviceSuspendu) {
                    $motifSuspension = (string) ($communeRowLogin['suspension_motif'] ?? '');
                    $message = 'Service suspendu par l’éditeur du site' . ($motifSuspension !== '' ? ' (motif : ' . $motifSuspension . ')' : '') . '. Merci de contacter l’éditeur pour la réactivation.';
                    $error = true;
                } elseif ($abonne === false || !maire_abonnement_couvre_aujourdhui($abonne)) {
                    $message = 'Votre accès personnel n’est plus dans sa période de validité. La prolongation et la création des comptes agents sont gérées depuis l’administration (Comptes & abonnement communal).';
                    $error = true;
                } else {
                    $_SESSION['subscriber_id'] = (int) $abonne['id'];
                    $_SESSION['subscriber_email'] = $email;
                    $_SESSION['subscriber_role'] = (string) ($abonne['role_utilisateur'] ?? 'subscriber');
                    $estCompteMairie = isset($abonne['compte_mairie'])
                        ? ((int) $abonne['compte_mairie'] === 1)
                        : (maire_get_compte_mairie_id($pdo) === (int) $abonne['id']);
                    $_SESSION['subscriber_compte_mairie'] = $estCompteMairie;
                    if ($estCompteMairie && ($_SESSION['subscriber_role'] ?? '') === 'admin') {
                        maire_sync_commune_vers_compte_mairie($pdo);
                        header('Location: admin/abonnements.php', true, 302);
                    } else {
                        header('Location: standard.php', true, 302);
                    }
                    exit;
                }
            }
        }
    }
}

require __DIR__ . '/includes/header.php';

$estConnecteAbonne = !empty($_SESSION['subscriber_id']);
?>
<main class="overflow-hidden">
    <?php if ($estConnecteAbonne): ?>
        <div class="bg-emerald-50 dark:bg-emerald-950/30 border-y border-emerald-200 dark:border-emerald-800 py-3">
            <div class="container mx-auto max-w-7xl px-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-emerald-800 dark:text-emerald-200"><span class="text-lg">✅</span> Vous êtes connecté&nbsp;: <strong><?php echo htmlspecialchars((string) ($_SESSION['subscriber_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <a class="tw-btn-primary text-sm" href="standard.php">Ouvrir l'espace numérique →</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- HERO + LOGIN -->
    <section class="relative maire-hero-bg text-white min-h-[85vh] flex items-center maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 relative z-10 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span aria-hidden="true">🏛️</span>
                    Commune de Rufisque-Est
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Espace<br><span class="maire-text-gradient">personnel mairie</span>
                </h1>
                <p class="text-lg text-mairie-100 leading-relaxed mb-6 max-w-xl">
                    <strong>Les habitants n'ont pas de compte à créer ici.</strong> Cette page sert au personnel municipal (agents et administrateurs) disposant d'un compte créé dans l'administration.
                </p>
                <div class="p-5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 max-w-xl">
                    <h3 class="font-black text-gold-300 text-sm mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Bon à savoir
                    </h3>
                    <p class="text-sm text-mairie-100">
                        Le <strong>compte institutionnel mairie</strong> est le seul habilité à changer la formule communale. Les autres comptes servent au quotidien (agents, accès admin).
                    </p>
                </div>
                <p class="text-xs text-mairie-200 mt-4">
                    Habitant&nbsp;? <a class="font-bold text-gold-300 hover:underline" href="citoyen/connexion.php">Connexion citoyen</a>
                </p>
            </div>

            <div class="w-full max-w-md mx-auto lg:ml-auto">
                <article class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-8 md:p-10">
                    <div class="mb-5">
                        <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white items-center justify-center text-2xl shadow-md mb-4">🔐</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">Identifiants agent</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Connectez-vous avec votre compte mairie.</p>
                    </div>

                    <?php if ($alerteAcces !== null): ?>
                        <div class="<?php echo $alerteAccesEstErreur ? 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-200'; ?> border-2 rounded-2xl p-3 mb-4 flex items-start gap-2 text-sm">
                            <span class="text-lg flex-shrink-0"><?php echo $alerteAccesEstErreur ? '⚠️' : 'ℹ️'; ?></span>
                            <p class="font-bold"><?php echo htmlspecialchars($alerteAcces, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($message !== null): ?>
                        <div class="<?php echo $error ? 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'; ?> border-2 rounded-2xl p-3 mb-4 flex items-start gap-2 text-sm">
                            <span class="text-lg flex-shrink-0"><?php echo $error ? '⚠️' : '✅'; ?></span>
                            <p class="font-bold"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($pdo === null): ?>
                        <div class="bg-red-50 dark:bg-red-950/30 border-2 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 rounded-2xl p-3 mb-4 text-xs">
                            <p class="font-bold">⚠️ Base de données inaccessible.</p>
                            <p class="mt-1">Vérifiez MySQL et la configuration dans <code class="font-mono">config/database.php</code>.</p>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" autocomplete="on" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">E-mail professionnel</label>
                            <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required placeholder="agent@mairie.sn" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="mot_de_passe" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Mot de passe</label>
                            <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>

                        <button class="tw-btn-primary w-full justify-center" type="submit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            Se connecter
                        </button>
                    </form>

                    <div class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 text-xs text-center text-slate-500 dark:text-slate-400 space-y-1">
                        <p>Besoin d'une formule ou d'un devis ? <a class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline" href="contact.php">Nous contacter</a></p>
                        <p>Éditeur du site ? <a class="font-bold text-slate-600 dark:text-slate-400 hover:underline" href="super-admin/login.php">Espace gestion éditeur</a></p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
