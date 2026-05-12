<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';

$feedback = null;
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($nom === '' || $email === '' || $message === '') {
        $feedback = 'Merci de remplir tous les champs.';
        $feedbackType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = 'Adresse email invalide.';
        $feedbackType = 'error';
    } else {
        if ($pdo !== null) {
            $stmt = $pdo->prepare("INSERT INTO messages_contact (nom, email, message) VALUES (:nom, :email, :message)");
            $stmt->execute([
                'nom' => $nom,
                'email' => $email,
                'message' => $message,
            ]);
            $feedback = 'Votre message a bien été envoyé. Merci.';
        } else {
            $feedback = "Le message est reçu, mais configurez MySQL pour l'enregistrer.";
            $feedbackType = 'error';
        }
    }
}

$pageTitle = 'Contact | Mairie de Rufisque-Est';
$pageDescription = "Une équipe municipale à votre écoute pour toutes vos demandes et signalements.";
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Relation citoyenne
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Contact<br><span class="maire-text-gradient">citoyen</span>
                </h1>
                <p class="text-xl text-mairie-100 leading-relaxed max-w-2xl">
                    Une équipe municipale à votre écoute pour toutes vos demandes et signalements.
                </p>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <?php if ($feedback !== null): ?>
                <div class="<?php echo $feedbackType === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200'; ?> border-2 rounded-2xl p-4 mb-8 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0"><?php echo $feedbackType === 'success' ? '✅' : '⚠️'; ?></span>
                    <p class="font-bold"><?php echo htmlspecialchars($feedback); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-[1fr_1.4fr] gap-6">
                <!-- Coordonnées -->
                <div class="space-y-5">
                    <article class="tw-card p-7">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white mb-5 flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">📍</span>
                            Informations utiles
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-mairie-100 dark:bg-mairie-900/40 text-mairie-700 dark:text-mairie-300 flex items-center justify-center text-lg flex-shrink-0">🏛️</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Adresse</p>
                                    <p class="text-slate-900 dark:text-white font-bold">Castor, en face de la pharmacie DIOR<br><span class="font-normal text-slate-700 dark:text-slate-300">Arafat II, Rufisque-Est — Rufisque, Sénégal</span></p>
                                    <a href="https://www.google.com/maps/search/?api=1&query=Castor+pharmacie+DIOR+Arafat+II+Rufisque+Est+Senegal" target="_blank" rel="noopener" class="inline-flex items-center gap-1 mt-1 text-xs font-bold text-mairie-700 dark:text-mairie-300 hover:underline">Voir sur la carte →</a>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-lg flex-shrink-0">✉️</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</p>
                                    <a href="mailto:Rufisquest02@gmail.com" class="text-slate-900 dark:text-white font-bold hover:text-mairie-700 dark:hover:text-mairie-300 break-all">Rufisquest02@gmail.com</a>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 flex items-center justify-center text-lg flex-shrink-0">🕒</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Heures d'ouverture</p>
                                    <p class="text-slate-900 dark:text-white font-bold flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Ouvert
                                        </span>
                                        Toujours ouvert
                                    </p>
                                    <small class="text-xs text-slate-500 dark:text-slate-400">Accueil citoyen 7j/7</small>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-7">
                        <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                        <h2 class="relative text-xl font-black mb-3 flex items-center gap-2"><span>📬</span> Contact direct mairie</h2>
                        <div class="relative space-y-2 text-sm">
                            <p class="text-emerald-50">Pour toute demande administrative, signalement ou information, écrivez-nous&nbsp;:</p>
                            <p><a href="mailto:Rufisquest02@gmail.com" class="font-bold text-white text-base hover:underline break-all">Rufisquest02@gmail.com</a></p>
                            <p class="text-emerald-100 text-xs">Accueil citoyen — Toujours ouvert, 7j/7.</p>
                        </div>
                    </article>
                </div>

                <!-- Formulaire -->
                <article class="tw-card p-7 md:p-10">
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-500 to-orange-600 text-white flex items-center justify-center text-xl">✉️</span>
                        Envoyez-nous un message
                    </h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Nous vous répondons sous 24h à 48h en moyenne.</p>

                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label for="nom" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Nom complet *</label>
                            <input id="nom" name="nom" type="text" required
                                   class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Email *</label>
                            <input id="email" name="email" type="email" required
                                   class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Votre message *</label>
                            <textarea id="message" name="message" rows="6" required
                                      class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition resize-y"></textarea>
                        </div>
                        <button class="tw-btn-primary w-full justify-center" type="submit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Envoyer le message
                        </button>
                    </form>
                </article>
            </div>

            <!-- FAQ + canaux rapides -->
            <div class="grid md:grid-cols-2 gap-6 mt-10">
                <article class="tw-card p-7">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center">⚡</span>
                        Canaux rapides
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Accueil citoyen :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">Toujours ouvert, 7j/7.</span></li>
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Email officiel :</strong>&nbsp;<a href="mailto:Rufisquest02@gmail.com" class="text-slate-700 dark:text-slate-300 hover:text-mairie-700 dark:hover:text-mairie-300 break-all">Rufisquest02@gmail.com</a></li>
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Adresse :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">Castor, face pharmacie DIOR — Arafat II, Rufisque-Est.</span></li>
                    </ul>
                </article>
                <article class="tw-card p-7">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center">❓</span>
                        FAQ citoyenne
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2"><span class="text-cyan-600 mt-1 flex-shrink-0">→</span><strong class="text-slate-900 dark:text-white">Délai de réponse :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">sous 24h à 48h.</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-600 mt-1 flex-shrink-0">→</span><strong class="text-slate-900 dark:text-white">Pièces à préparer :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">CNI, justificatifs et référence dossier.</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-600 mt-1 flex-shrink-0">→</span><strong class="text-slate-900 dark:text-white">Suivi :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">disponible via votre numéro de demande.</span></li>
                    </ul>
                </article>
            </div>

            <div class="mt-12 flex flex-wrap gap-3 justify-center">
                <a class="tw-btn-outline" href="services.php">Retour aux services</a>
                <a class="tw-btn-primary" href="actualites.php">Voir les actualités</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
