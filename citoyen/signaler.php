<?php
declare(strict_types=1);

/**
 * Formulaire de signalement citoyen : catégorie + titre + description
 * + photo (upload, 4 Mo max) + géolocalisation HTML5 + adresse libre.
 */
require __DIR__ . '/../includes/citoyen-guard.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/maire-rate-limit.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if ($pdo !== null && !maire_feature_disponible($pdo, 'signalements_citoyens')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('signalements_citoyens', $palierCommune, 'public');
    exit;
}

if (empty($_SESSION['citoyen_csrf'])) {
    $_SESSION['citoyen_csrf'] = bin2hex(random_bytes(32));
}

$flash = '';
$flashType = 'success';
$dataSaisie = [
    'categorie' => 'autre',
    'titre' => '',
    'description' => '',
    'latitude' => '',
    'longitude' => '',
    'adresse_libre' => '',
];
$idCreated = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['citoyen_csrf'], $csrf)) {
        $flash = 'Jeton de sécurité invalide. Recharge la page.';
        $flashType = 'danger';
    } elseif (!maire_rate_limit_allow('signalement_creation', 20, 600)) {
        $flash = 'Trop de signalements créés depuis ce réseau. Réessaie dans quelques minutes.';
        $flashType = 'danger';
    } else {
        $dataSaisie = [
            'categorie' => (string) ($_POST['categorie'] ?? 'autre'),
            'titre' => trim((string) ($_POST['titre'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'latitude' => (string) ($_POST['latitude'] ?? ''),
            'longitude' => (string) ($_POST['longitude'] ?? ''),
            'adresse_libre' => trim((string) ($_POST['adresse_libre'] ?? '')),
        ];

        $err = null;
        $fichier = isset($_FILES['photo']) ? $_FILES['photo'] : null;
        $id = maire_creer_signalement($pdo, (int) $_SESSION['citoyen_id'], $dataSaisie, $fichier, $err);
        if ($id === null) {
            $flash = $err ?? 'Enregistrement impossible.';
            $flashType = 'danger';
        } else {
            $idCreated = $id;
            $flash = 'Signalement n°' . $id . ' enregistré. La mairie va le prendre en charge.';
            $flashType = 'success';
            $dataSaisie = ['categorie' => 'autre', 'titre' => '', 'description' => '', 'latitude' => '', 'longitude' => '', 'adresse_libre' => ''];
        }
    }
}

$pageTitle = 'Espace citoyen · Nouveau signalement';
$pageDescription = 'Signalez un problème dans votre quartier : route, lampadaire, déchets, inondation…';
require __DIR__ . '/../includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-20 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-red-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-gold-400/25 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="profil.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Retour au profil
            </a>
            <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                Espace citoyen · Signalement
            </span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight mb-3">
                Signaler un <span class="maire-text-gradient">problème</span>
            </h1>
            <p class="text-lg text-mairie-100 leading-relaxed">
                Aidez la mairie à intervenir vite : décrivez le problème, ajoutez une photo et votre position si possible.
            </p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

            <?php if ($flash !== ''): ?>
                <div class="<?php echo $flashType === 'danger' ? 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'; ?> border-2 rounded-2xl p-5 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0"><?php echo $flashType === 'danger' ? '⚠️' : '✅'; ?></span>
                    <div class="flex-1">
                        <p class="font-bold mb-2"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($idCreated !== null): ?>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <a class="tw-btn-primary text-sm" href="mes-signalements.php">Voir mes signalements</a>
                                <a class="tw-btn-outline text-sm" href="signaler.php">Faire un autre signalement</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($idCreated === null): ?>
            <article class="tw-card p-7 md:p-10">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 text-white flex items-center justify-center text-xl">📢</span>
                    Détails du signalement
                </h2>

                <form method="POST" action="signaler.php" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['citoyen_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div>
                        <label for="categorie" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Catégorie *</label>
                        <select id="categorie" name="categorie" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                            <?php foreach (MAIRE_SIGNALEMENTS_CATEGORIES as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dataSaisie['categorie'] === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="titre" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Titre court *</label>
                        <input type="text" id="titre" name="titre" required maxlength="180" value="<?php echo htmlspecialchars($dataSaisie['titre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex : Lampadaire en panne devant l'école" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Description détaillée *</label>
                        <textarea id="description" name="description" required maxlength="4000" rows="5" placeholder="Décrivez précisément le problème (depuis quand, conséquences, etc.)" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition resize-y"><?php echo htmlspecialchars($dataSaisie['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div>
                        <label for="photo" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Photo <small class="text-slate-400 font-normal">(facultatif, 4 Mo max)</small></label>
                        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp,image/heic" capture="environment" class="w-full px-3 py-2.5 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:font-bold file:bg-mairie-700 file:text-white hover:file:bg-mairie-800">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Formats : JPG, PNG, WEBP, HEIC. Sur smartphone, l'appareil photo peut s'ouvrir directement.</p>
                    </div>

                    <fieldset class="rounded-2xl border-2 border-slate-200 dark:border-slate-700 p-5">
                        <legend class="px-2 text-sm font-black text-slate-700 dark:text-slate-200">📍 Localisation</legend>
                        <p class="text-xs text-slate-500 dark:text-slate-400 italic mb-3">Cliquez pour partager votre position GPS, ou décrivez l'adresse à la main.</p>
                        <div class="flex flex-wrap items-center gap-3 mb-3">
                            <button type="button" id="btnGeoloc" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-mairie-700 hover:bg-mairie-800 text-white font-bold text-sm transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0L5.343 16.657a8 8 0 1110.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Capturer ma position
                            </button>
                            <span id="geolocStatus" class="text-sm text-slate-500 dark:text-slate-400"></span>
                        </div>
                        <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($dataSaisie['latitude'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($dataSaisie['longitude'], ENT_QUOTES, 'UTF-8'); ?>">

                        <label for="adresse_libre" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5 mt-3">Adresse / repère <small class="text-slate-400 font-normal">(facultatif)</small></label>
                        <input type="text" id="adresse_libre" name="adresse_libre" maxlength="255" value="<?php echo htmlspecialchars($dataSaisie['adresse_libre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex : Rue 12, près de la pharmacie Keury Souf" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                    </fieldset>

                    <div class="flex flex-wrap gap-3 pt-3">
                        <button type="submit" class="tw-btn-primary flex-1 justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Envoyer le signalement
                        </button>
                        <a class="tw-btn-outline" href="profil.php">Annuler</a>
                    </div>
                </form>
            </article>
            <?php endif; ?>

            <!-- BLOC COMMENT ÇA MARCHE -->
            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-7">
                <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none"></div>
                <div class="relative">
                    <h2 class="text-xl font-black mb-4 flex items-center gap-2">
                        <span>💡</span> Comment ça marche
                    </h2>
                    <ol class="space-y-2 text-sm text-mairie-100">
                        <li class="flex items-start gap-2"><span class="w-6 h-6 rounded-full bg-gold-400 text-mairie-950 flex items-center justify-center text-xs font-black flex-shrink-0">1</span> Décrivez le problème (titre court + détails utiles).</li>
                        <li class="flex items-start gap-2"><span class="w-6 h-6 rounded-full bg-gold-400 text-mairie-950 flex items-center justify-center text-xs font-black flex-shrink-0">2</span> Ajoutez une photo (vu, c'est compris !).</li>
                        <li class="flex items-start gap-2"><span class="w-6 h-6 rounded-full bg-gold-400 text-mairie-950 flex items-center justify-center text-xs font-black flex-shrink-0">3</span> Capturez votre position ou décrivez l'adresse.</li>
                        <li class="flex items-start gap-2"><span class="w-6 h-6 rounded-full bg-gold-400 text-mairie-950 flex items-center justify-center text-xs font-black flex-shrink-0">4</span> La mairie est notifiée et change le statut quand elle traite votre signalement.</li>
                        <li class="flex items-start gap-2"><span class="w-6 h-6 rounded-full bg-gold-400 text-mairie-950 flex items-center justify-center text-xs font-black flex-shrink-0">5</span> Vous suivez tout depuis <a class="font-bold underline" href="mes-signalements.php">Mes signalements</a>.</li>
                    </ol>
                </div>
            </article>

        </div>
    </section>
</main>

<script>
(function () {
    var btn = document.getElementById('btnGeoloc');
    var statusEl = document.getElementById('geolocStatus');
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    if (!btn || !statusEl || !latInput || !lngInput) { return; }

    if (!('geolocation' in navigator)) {
        btn.disabled = true;
        statusEl.textContent = 'Géolocalisation non disponible sur cet appareil.';
        return;
    }

    btn.addEventListener('click', function () {
        statusEl.textContent = 'Demande en cours…';
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                latInput.value = pos.coords.latitude.toFixed(7);
                lngInput.value = pos.coords.longitude.toFixed(7);
                statusEl.textContent = '✓ Position capturée (±' + Math.round(pos.coords.accuracy) + ' m)';
                statusEl.style.color = '#059669';
                statusEl.style.fontWeight = '700';
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Position capturée';
                btn.classList.remove('bg-mairie-700', 'hover:bg-mairie-800');
                btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            },
            function (err) {
                statusEl.textContent = 'Échec : ' + (err.code === 1 ? 'autorisation refusée' : err.code === 2 ? 'position indisponible' : err.code === 3 ? 'délai dépassé' : 'erreur inconnue') + '. Vous pouvez décrire l’adresse à la main ci-dessous.';
                statusEl.style.color = '#dc2626';
                btn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
