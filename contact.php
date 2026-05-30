<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

$contactCsrfScope = MAIRE_CSRF_SCOPE_CONTACT;

$feedback = null;
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!maire_csrf_validate($contactCsrfScope)) {
        $feedback = 'Jeton de sécurité invalide. Rechargez la page puis réessayez.';
        $feedbackType = 'error';
    } else {
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
}

$pageTitle = 'Contact | Mairie de Rufisque-Est';
$pageDescription = "Une équipe municipale à votre écoute pour toutes vos demandes et signalements.";

require_once __DIR__ . '/includes/geo-commune.php';
$geoCommune = maire_geo_commune_centre();
$geoMairie = maire_geo_mairie();
$geoDmsCommune = maire_geo_format_dms($geoCommune['lat'], $geoCommune['lng']);

require_once __DIR__ . '/includes/site-paths.php';
$urlPrefixForAssets = maire_url_prefix();

$leafletBase = $urlPrefixForAssets . 'assets/vendor/leaflet/';
$pageHeadExtra = '<link rel="stylesheet" href="' . htmlspecialchars($leafletBase . 'leaflet.css', ENT_QUOTES, 'UTF-8') . '">';

$carteConfig = [
    'senegal' => [
        'south' => MAIRE_GEO_SENEGAL_SOUTH,
        'west' => MAIRE_GEO_SENEGAL_WEST,
        'north' => MAIRE_GEO_SENEGAL_NORTH,
        'east' => MAIRE_GEO_SENEGAL_EAST,
    ],
    'commune' => array_merge($geoCommune, ['dms' => $geoDmsCommune]),
    'mairie' => array_merge($geoMairie, ['dms' => maire_geo_format_dms($geoMairie['lat'], $geoMairie['lng'])]),
];
$carteConfigJson = json_encode($carteConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$pageFooterScripts = '<script src="' . htmlspecialchars($leafletBase . 'leaflet.js', ENT_QUOTES, 'UTF-8') . '"></script>'
    . '<script>window.maireCarteConfig = ' . $carteConfigJson . ';</script>'
    . '<script src="' . htmlspecialchars($urlPrefixForAssets, ENT_QUOTES, 'UTF-8') . 'assets/js/carte-senegal.js?v=2"></script>';

require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 lg:py-28 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-section-kicker mb-5 !bg-white/12 !text-white !border-white/20">
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
                    <article class="maire-panel p-7">
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
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Portail en ligne</p>
                                    <p class="text-slate-900 dark:text-white font-bold flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            En ligne
                                        </span>
                                        Informations et formulaires accessibles
                                    </p>
                                    <small class="text-xs text-slate-500 dark:text-slate-400">Les messages sont ensuite transmis au service municipal concerné.</small>
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
                            <p class="text-emerald-100 text-xs">Votre message est enregistré puis orienté vers le service compétent.</p>
                        </div>
                    </article>
                </div>

                <!-- Formulaire -->
                <article class="maire-form-shell">
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-500 to-orange-600 text-white flex items-center justify-center text-xl">✉️</span>
                        Envoyez-nous un message
                    </h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Votre message est enregistré puis transmis au service concerné.</p>

                    <form action="" method="POST" class="space-y-4">
                        <?php echo maire_csrf_field($contactCsrfScope); ?>
                        <div>
                            <label for="nom" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Nom complet *</label>
                            <input id="nom" name="nom" type="text" required class="tw-input">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Email *</label>
                            <input id="email" name="email" type="email" required class="tw-input">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Votre message *</label>
                            <textarea id="message" name="message" rows="6" required class="tw-input resize-y"></textarea>
                        </div>
                        <button class="tw-btn-primary w-full justify-center" type="submit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Envoyer le message
                        </button>
                    </form>
                </article>
            </div>

            <!-- Carte du Sénégal -->
            <article class="maire-editorial-card overflow-hidden mt-10 !p-0">
                <div class="p-7 md:p-8 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-600 to-mairie-800 text-white flex items-center justify-center text-lg">🗺️</span>
                        Rufisque-Est au Sénégal
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm max-w-3xl">
                        La commune de <strong class="text-slate-800 dark:text-slate-200">Rufisque-Est</strong> se situe à l’entrée de la presqu’île du Cap-Vert,
                        au sud-est de Dakar, dans le département de Rufisque (région de Dakar).
                    </p>
                    <p class="mt-2 text-xs font-mono text-slate-500 dark:text-slate-400">
                        Centre communal : <?php echo htmlspecialchars($geoDmsCommune, ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars(number_format($geoCommune['lat'], 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>,
                        <?php echo htmlspecialchars(number_format($geoCommune['lng'], 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        Utilisez le sélecteur en haut à droite de la carte pour basculer entre la vue <strong class="text-slate-700 dark:text-slate-300">Plan</strong> et la vue <strong class="text-slate-700 dark:text-slate-300">Satellite</strong> (imagerie Esri).
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 font-bold">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Centre communal
                        </span>
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-mairie-100 dark:bg-mairie-900/40 text-mairie-900 dark:text-mairie-200 font-bold">
                            <span class="w-2.5 h-2.5 rounded-full bg-mairie-700"></span> Mairie (Castor / Arafat II)
                        </span>
                    </div>
                </div>
                <div id="carte-senegal-commune" class="maire-carte-commune w-full bg-slate-200 dark:bg-slate-800" role="region" aria-label="Carte interactive du Sénégal avec la position de Rufisque-Est"></div>
                <div class="grid md:grid-cols-[1fr_1.2fr] gap-0 border-t border-slate-200 dark:border-slate-700">
                    <div class="p-5 md:p-6 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center">
                        <img src="<?php echo htmlspecialchars($urlPrefixForAssets, ENT_QUOTES, 'UTF-8'); ?>assets/img/carte-senegal-rufisque-est.svg"
                             alt="Carte schématique du Sénégal avec Rufisque-Est marqué sur la côte, au sud-est de Dakar"
                             class="max-h-52 w-auto"
                             width="260" height="320"
                             loading="lazy">
                    </div>
                    <div class="p-5 md:p-6 text-sm text-slate-600 dark:text-slate-400 space-y-2 border-t md:border-t-0 md:border-l border-slate-200 dark:border-slate-700">
                        <p><strong class="text-slate-900 dark:text-white">Commune d’arrondissement</strong> — l’une des trois communes de la ville de Rufisque, créée en 1996.</p>
                        <p><strong class="text-slate-900 dark:text-white">Accès</strong> — route nationale N1, proche du littoral entre Dakar et la petite côte.</p>
                        <p>
                            <a href="https://www.openstreetmap.org/relation/12982167" target="_blank" rel="noopener" class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline">Voir le périmètre communal sur OpenStreetMap →</a>
                        </p>
                    </div>
                </div>
            </article>

            <!-- FAQ + canaux rapides -->
            <div class="grid md:grid-cols-2 gap-6 mt-10">
                <article class="maire-panel p-7">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center">⚡</span>
                        Canaux rapides
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Portail :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">Formulaire de contact et informations accessibles en ligne.</span></li>
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Email officiel :</strong>&nbsp;<a href="mailto:Rufisquest02@gmail.com" class="text-slate-700 dark:text-slate-300 hover:text-mairie-700 dark:hover:text-mairie-300 break-all">Rufisquest02@gmail.com</a></li>
                        <li class="flex items-start gap-2"><span class="text-emerald-600 mt-1 flex-shrink-0">✓</span><strong class="text-slate-900 dark:text-white">Adresse :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">Castor, face pharmacie DIOR — Arafat II, Rufisque-Est.</span></li>
                    </ul>
                </article>
                <article class="maire-panel p-7">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center">❓</span>
                        FAQ citoyenne
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2"><span class="text-cyan-600 mt-1 flex-shrink-0">→</span><strong class="text-slate-900 dark:text-white">Délai de traitement :</strong>&nbsp;<span class="text-slate-700 dark:text-slate-300">variable selon la nature de la demande et son service de rattachement.</span></li>
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
