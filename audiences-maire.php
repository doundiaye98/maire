<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session-performance.php';
require_once __DIR__ . '/includes/citoyen-session.php';
require_once __DIR__ . '/includes/audiences-maire.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';
require_once __DIR__ . '/includes/feature-gates.php';
require_once __DIR__ . '/includes/csrf.php';

maire_session_configure_ini();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($pdo !== null && !maire_feature_disponible($pdo, 'audiences_maire')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('audiences_maire', $palierCommune, 'public');
    exit;
}

$maireAudienceCsrfScope = MAIRE_CSRF_SCOPE_AUDIENCE;

$citoyenConnecte = maire_citoyen_session_valid();
$citoyenId = $citoyenConnecte ? (int) ($_SESSION['citoyen_id'] ?? 0) : null;

$creneauxDisponibles = $pdo !== null ? maire_lister_creneaux_disponibles($pdo) : [];

$dataSaisie = [
    'prenom' => $citoyenConnecte ? (string) ($_SESSION['citoyen_prenom'] ?? '') : '',
    'nom' => $citoyenConnecte ? (string) ($_SESSION['citoyen_nom'] ?? '') : '',
    'email' => $citoyenConnecte ? (string) ($_SESSION['citoyen_email'] ?? '') : '',
    'telephone' => '',
    'quartier' => '',
    'motif' => 'autre',
    'objet' => '',
    'message' => '',
    'mode_audience' => 'visio',
    'type_reservation' => $creneauxDisponibles !== [] ? 'creneau_fixe' : 'demande_libre',
    'creneau_id' => '',
    'date_souhaitee' => '',
    'creneau_souhaite' => 'indifferent',
    'otp_code' => '',
];

$flash = '';
$flashType = 'success';
$idCreated = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate($maireAudienceCsrfScope)) {
        $flash = 'Jeton de sécurité invalide. Rechargez la page.';
        $flashType = 'danger';
    } elseif (!maire_rate_limit_allow('audience_demande', 8, 600)) {
        $flash = 'Trop de demandes depuis ce réseau. Réessayez dans quelques minutes.';
        $flashType = 'danger';
    } else {
        $dataSaisie = [
            'prenom' => trim((string) ($_POST['prenom'] ?? '')),
            'nom' => trim((string) ($_POST['nom'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
            'quartier' => trim((string) ($_POST['quartier'] ?? '')),
            'motif' => (string) ($_POST['motif'] ?? 'autre'),
            'objet' => trim((string) ($_POST['objet'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'mode_audience' => (string) ($_POST['mode_audience'] ?? 'visio'),
            'type_reservation' => (string) ($_POST['type_reservation'] ?? 'demande_libre'),
            'creneau_id' => trim((string) ($_POST['creneau_id'] ?? '')),
            'date_souhaitee' => trim((string) ($_POST['date_souhaitee'] ?? '')),
            'creneau_souhaite' => (string) ($_POST['creneau_souhaite'] ?? 'indifferent'),
            'otp_code' => trim((string) ($_POST['otp_code'] ?? '')),
            'otp_scope' => $maireAudienceCsrfScope,
        ];
        $err = null;
        $id = maire_creer_demande_audience($pdo, $dataSaisie, $citoyenId, $err);
        if ($id === null) {
            $flash = $err ?? 'Enregistrement impossible.';
            $flashType = 'danger';
        } else {
            $idCreated = $id;
            $typeOk = ($dataSaisie['type_reservation'] ?? '') === 'creneau_fixe';
            $flash = $typeOk
                ? 'Votre audience n°' . $id . ' est confirmée. Un SMS de rappel vous a été envoyé.'
                : 'Votre demande d’audience n°' . $id . ' est enregistrée. Le cabinet vous confirmera la date par email et SMS.';
            $flashType = 'success';
            $dataSaisie['objet'] = '';
            $dataSaisie['message'] = '';
            $dataSaisie['otp_code'] = '';
        }
    }
}

$pageTitle = 'Audience en ligne avec le Maire | Rufisque-Est';
$pageDescription = 'Demandez une audience en présentiel ou en visioconférence avec le Maire de Rufisque-Est.';
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-24 lg:py-28 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <span class="maire-section-kicker mb-4 !bg-white/12 !text-white !border-white/20">
                <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                Relation citoyenne
            </span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight mb-4">
                Audience avec le <span class="maire-text-gradient">Maire</span>
            </h1>
            <p class="text-lg text-mairie-100 max-w-2xl leading-relaxed">
                Réservez un créneau disponible ou déposez une demande libre. Votre numéro mobile est vérifié par code SMS avant validation.
            </p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

            <?php if ($flash !== ''): ?>
                <div class="<?php echo $flashType === 'danger' ? 'bg-red-50 dark:bg-red-950/30 border-red-300 text-red-800 dark:text-red-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 text-emerald-800 dark:text-emerald-200'; ?> border-2 rounded-2xl p-5">
                    <p class="font-bold"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if ($idCreated !== null && $citoyenConnecte): ?>
                        <p class="mt-3"><a class="tw-btn-primary text-sm" href="citoyen/audiences.php">Suivre mes demandes</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($idCreated === null): ?>
            <article class="maire-form-shell">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Demande d’audience</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">3 étapes · environ 3 minutes</p>
                <?php if (!$citoyenConnecte): ?>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-5">
                        <a class="font-bold text-mairie-700 dark:text-mairie-300 underline" href="citoyen/connexion.php">Connectez-vous</a>
                        pour préremplir vos coordonnées et suivre vos demandes.
                    </p>
                <?php endif; ?>

                <?php if ($creneauxDisponibles === []): ?>
                <div class="mb-6 rounded-xl border border-amber-300/80 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700/50 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                    Aucun créneau publié pour l’instant — vous pouvez déposer une <strong>demande libre</strong> (étape 2). Le cabinet vous proposera une date.
                </div>
                <?php endif; ?>

                <nav class="maire-wizard-steps" aria-label="Étapes du formulaire">
                    <div class="maire-wizard-step is-active" data-wizard-step-indicator="1">
                        <span class="maire-wizard-step__dot">1</span>
                        <span class="maire-wizard-step__label">Vos coordonnées</span>
                    </div>
                    <div class="maire-wizard-bar" aria-hidden="true"></div>
                    <div class="maire-wizard-step" data-wizard-step-indicator="2">
                        <span class="maire-wizard-step__dot">2</span>
                        <span class="maire-wizard-step__label">Date &amp; motif</span>
                    </div>
                    <div class="maire-wizard-bar" aria-hidden="true"></div>
                    <div class="maire-wizard-step" data-wizard-step-indicator="3">
                        <span class="maire-wizard-step__dot">3</span>
                        <span class="maire-wizard-step__label">SMS &amp; envoi</span>
                    </div>
                </nav>

                <form method="post" action="audiences-maire.php" class="space-y-6" id="form-audience-maire" novalidate>
                    <?php echo maire_csrf_field($maireAudienceCsrfScope); ?>

                    <div class="maire-wizard-panel" data-wizard-step="1">
                    <p class="text-sm text-slate-600 dark:text-slate-400 -mt-2 mb-4">Indiquez comment vous joindre. Le mobile servira à la vérification à l’étape 3.</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="prenom" class="block text-sm font-bold mb-1.5">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required maxlength="80" value="<?php echo htmlspecialchars($dataSaisie['prenom'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                        </div>
                        <div>
                            <label for="nom" class="block text-sm font-bold mb-1.5">Nom *</label>
                            <input type="text" id="nom" name="nom" required maxlength="80" value="<?php echo htmlspecialchars($dataSaisie['nom'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-bold mb-1.5">Email *</label>
                            <input type="email" id="email" name="email" required maxlength="190" value="<?php echo htmlspecialchars($dataSaisie['email'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                        </div>
                        <div>
                            <label for="telephone" class="block text-sm font-bold mb-1.5">Mobile * (SMS)</label>
                            <input type="tel" id="telephone" name="telephone" required maxlength="40" value="<?php echo htmlspecialchars($dataSaisie['telephone'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input" placeholder="77 123 45 67" autocomplete="tel">
                        </div>
                    </div>

                    <div>
                        <label for="quartier" class="block text-sm font-bold mb-1.5">Quartier</label>
                        <input type="text" id="quartier" name="quartier" maxlength="120" value="<?php echo htmlspecialchars($dataSaisie['quartier'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input">
                    </div>
                    </div>

                    <div class="maire-wizard-panel" data-wizard-step="2" hidden>
                    <p class="text-sm text-slate-600 dark:text-slate-400 -mt-2 mb-4">Choisissez comment rencontrer le Maire, puis précisez le motif.</p>
                    <div class="space-y-3">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-700 dark:text-slate-300">Comment souhaitez-vous réserver ?</h3>
                        <div class="grid sm:grid-cols-2 gap-3 <?php echo $creneauxDisponibles === [] ? 'sm:grid-cols-1' : ''; ?>">
                            <?php if ($creneauxDisponibles !== []): ?>
                            <label class="maire-choice-card">
                                <input type="radio" name="type_reservation" value="creneau_fixe"
                                    <?php echo $dataSaisie['type_reservation'] === 'creneau_fixe' ? 'checked' : ''; ?>>
                                <span class="maire-choice-card__body">
                                    <span class="w-10 h-10 rounded-lg bg-mairie-100 dark:bg-mairie-900/50 text-mairie-800 dark:text-mairie-200 flex items-center justify-center text-xs font-black uppercase" aria-hidden="true">RDV</span>
                                    <span class="font-black text-slate-900 dark:text-white">Créneau publié</span>
                                    <span class="text-sm text-slate-600 dark:text-slate-400 leading-snug">Confirmation immédiate si une place est disponible.</span>
                                </span>
                            </label>
                            <?php endif; ?>
                            <label class="maire-choice-card">
                                <input type="radio" name="type_reservation" value="demande_libre"
                                    <?php echo $dataSaisie['type_reservation'] !== 'creneau_fixe' || $creneauxDisponibles === [] ? 'checked' : ''; ?>>
                                <span class="maire-choice-card__body">
                                    <span class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center text-xs font-black uppercase" aria-hidden="true">Libre</span>
                                    <span class="font-black text-slate-900 dark:text-white">Demande libre</span>
                                    <span class="text-sm text-slate-600 dark:text-slate-400 leading-snug">Proposez une date ; le cabinet vous répond sous quelques jours.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div id="bloc-creneaux-fixes" class="space-y-3 <?php echo $dataSaisie['type_reservation'] !== 'creneau_fixe' ? 'hidden' : ''; ?>">
                        <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">Choisissez un créneau *</h3>
                        <?php if ($creneauxDisponibles === []): ?>
                            <p class="text-sm text-slate-600 dark:text-slate-400 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-4">Aucun créneau publié pour le moment. Utilisez une <strong>demande libre</strong>.</p>
                        <?php else: ?>
                            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                <?php foreach ($creneauxDisponibles as $idx => $cr): ?>
                                <label class="maire-slot-card block cursor-pointer">
                                    <input type="radio" name="creneau_id" value="<?php echo (int) $cr['id']; ?>"
                                        <?php
                                        $sel = (string) $dataSaisie['creneau_id'] === (string) $cr['id']
                                            || ($dataSaisie['creneau_id'] === '' && $idx === 0);
                                        echo $sel ? 'checked' : '';
                                        ?>>
                                    <span class="maire-slot-card__body">
                                        <span class="w-2 h-2 rounded-full bg-mairie-600 shrink-0" aria-hidden="true"></span>
                                        <?php echo htmlspecialchars(maire_formater_creneau_audience($cr), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="bloc-demande-libre" class="grid sm:grid-cols-2 gap-4 <?php echo $dataSaisie['type_reservation'] === 'creneau_fixe' && $creneauxDisponibles !== [] ? 'hidden' : ''; ?>">
                        <div>
                            <label for="date_souhaitee" class="block text-sm font-bold mb-1.5">Date souhaitée *</label>
                            <input type="date" id="date_souhaitee" name="date_souhaitee" value="<?php echo htmlspecialchars($dataSaisie['date_souhaitee'], ENT_QUOTES, 'UTF-8'); ?>" min="<?php echo date('Y-m-d'); ?>" class="tw-input">
                        </div>
                        <div>
                            <label for="creneau_souhaite" class="block text-sm font-bold mb-1.5">Créneau préféré</label>
                            <select id="creneau_souhaite" name="creneau_souhaite" class="tw-input">
                                <?php foreach (MAIRE_AUDIENCES_CRENEAUX as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dataSaisie['creneau_souhaite'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="motif" class="block text-sm font-bold mb-1.5">Motif *</label>
                            <select id="motif" name="motif" required class="tw-input">
                                <?php foreach (MAIRE_AUDIENCES_MOTIFS as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dataSaisie['motif'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="bloc-mode-libre">
                            <label for="mode_audience" class="block text-sm font-bold mb-1.5">Mode souhaité *</label>
                            <select id="mode_audience" name="mode_audience" class="tw-input">
                                <?php foreach (MAIRE_AUDIENCES_MODES as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dataSaisie['mode_audience'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    </div>

                    <div class="maire-wizard-panel" data-wizard-step="3" hidden>
                    <p class="text-sm text-slate-600 dark:text-slate-400 -mt-2 mb-4">Décrivez votre demande, vérifiez votre mobile, puis validez.</p>

                    <div>
                        <label for="objet" class="block text-sm font-bold mb-1.5">Objet de l’audience *</label>
                        <input type="text" id="objet" name="objet" required maxlength="255" value="<?php echo htmlspecialchars($dataSaisie['objet'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input" placeholder="Ex. : Projet d’assainissement au quartier…">
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-bold mb-1.5">Votre message *</label>
                        <textarea id="message" name="message" required rows="5" maxlength="5000" class="tw-input resize-y" placeholder="Décrivez votre demande et le contexte…"><?php echo htmlspecialchars($dataSaisie['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="rounded-2xl border border-mairie-200/80 dark:border-mairie-700/50 bg-gradient-to-br from-mairie-50/90 to-white dark:from-mairie-950/40 dark:to-slate-800/80 p-5 space-y-4" id="bloc-otp">
                        <p class="font-black text-slate-900 dark:text-white">Vérification par SMS</p>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Un code sera envoyé au <strong id="otp-tel-preview">numéro indiqué à l’étape 1</strong>.</p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                            <button type="button" id="btn-otp-send" class="tw-btn-outline text-sm shrink-0">Envoyer le code</button>
                            <div class="flex-1 min-w-0">
                                <label for="otp_code" class="block text-sm font-bold mb-1.5">Code à 6 chiffres *</label>
                                <input type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                                    value="<?php echo htmlspecialchars($dataSaisie['otp_code'], ENT_QUOTES, 'UTF-8'); ?>" class="tw-input max-w-[12rem] tracking-[0.35em] text-center font-mono text-lg" placeholder="000000" autocomplete="one-time-code">
                            </div>
                        </div>
                        <p id="otp-status" class="text-sm text-mairie-800 dark:text-mairie-200 min-h-[1.25rem]" role="status" aria-live="polite"></p>
                    </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" id="wizard-prev" class="tw-btn-outline hidden">← Précédent</button>
                        <button type="button" id="wizard-next" class="tw-btn-primary flex-1 justify-center">Continuer →</button>
                        <button type="submit" id="wizard-submit" class="tw-btn-primary flex-1 justify-center hidden">Envoyer la demande</button>
                        <a class="tw-btn-ghost text-sm" href="maire.php">Annuler</a>
                    </div>
                </form>
            </article>

            <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-7">
                <h2 class="text-xl font-black mb-3">Comment ça se passe ?</h2>
                <ol class="space-y-2 text-sm text-mairie-100 list-decimal list-inside">
                    <li><strong>Étape 1</strong> — vos coordonnées.</li>
                    <li><strong>Étape 2</strong> — créneau publié ou date libre + motif.</li>
                    <li><strong>Étape 3</strong> — message, code SMS, validation.</li>
                    <li>Créneau fixe : confirmation immédiate. Horaires mairie : lun. – ven., 8h – 17h.</li>
                </ol>
            </article>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
(function () {
    const form = document.getElementById('form-audience-maire');
    if (!form) return;

    const TOTAL = 3;
    let step = 1;
    const panels = form.querySelectorAll('[data-wizard-step]');
    const indicators = document.querySelectorAll('[data-wizard-step-indicator]');
    const btnPrev = document.getElementById('wizard-prev');
    const btnNext = document.getElementById('wizard-next');
    const btnSubmit = document.getElementById('wizard-submit');
    const otpInput = document.getElementById('otp_code');

    const radiosType = form.querySelectorAll('input[name="type_reservation"]');
    const blocFixe = document.getElementById('bloc-creneaux-fixes');
    const blocLibre = document.getElementById('bloc-demande-libre');
    const blocMode = document.getElementById('bloc-mode-libre');
    const dateInput = document.getElementById('date_souhaitee');
    const modeSelect = document.getElementById('mode_audience');
    const btnOtp = document.getElementById('btn-otp-send');
    const otpStatus = document.getElementById('otp-status');
    const otpTelPreview = document.getElementById('otp-tel-preview');
    const telInput = document.getElementById('telephone');
    const csrfInput = form.querySelector('input[name="csrf_token"]');

    function typeActuel() {
        const r = form.querySelector('input[name="type_reservation"]:checked');
        return r ? r.value : 'demande_libre';
    }

    function syncType() {
        const t = typeActuel();
        const fixe = t === 'creneau_fixe';
        if (blocFixe) blocFixe.classList.toggle('hidden', !fixe);
        if (blocLibre) blocLibre.classList.toggle('hidden', fixe);
        if (blocMode) blocMode.classList.toggle('hidden', fixe);
        if (dateInput) dateInput.required = !fixe && step >= 2;
        if (modeSelect) modeSelect.required = !fixe && step >= 2;
        if (btnSubmit) {
            btnSubmit.textContent = fixe ? 'Confirmer ma réservation' : 'Envoyer ma demande';
        }
    }

    function showStep(n) {
        step = n;
        panels.forEach(function (p) {
            const s = parseInt(p.getAttribute('data-wizard-step'), 10);
            p.hidden = s !== n;
        });
        indicators.forEach(function (ind) {
            const s = parseInt(ind.getAttribute('data-wizard-step-indicator'), 10);
            ind.classList.toggle('is-active', s === n);
            ind.classList.toggle('is-done', s < n);
        });
        if (btnPrev) btnPrev.classList.toggle('hidden', n <= 1);
        if (btnNext) btnNext.classList.toggle('hidden', n >= TOTAL);
        if (btnSubmit) btnSubmit.classList.toggle('hidden', n < TOTAL);
        syncType();
        if (n === 3) {
            if (otpTelPreview && telInput) {
                otpTelPreview.textContent = telInput.value.trim() || 'numéro indiqué à l’étape 1';
            }
            form.querySelector('[data-wizard-step="3"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function validatePanel(n) {
        const panel = form.querySelector('[data-wizard-step="' + n + '"]');
        if (!panel) return true;
        const fields = panel.querySelectorAll('input, select, textarea');
        for (let i = 0; i < fields.length; i++) {
            const el = fields[i];
            if (el.disabled || el.type === 'hidden') continue;
            if (!el.checkValidity()) {
                el.reportValidity();
                el.focus();
                return false;
            }
        }
        if (n === 2) {
            if (typeActuel() === 'creneau_fixe') {
                const creneau = form.querySelector('input[name="creneau_id"]:checked');
                if (!creneau && blocFixe && !blocFixe.classList.contains('hidden')) {
                    alert('Veuillez choisir un créneau.');
                    return false;
                }
            }
        }
        return true;
    }

    radiosType.forEach(function (r) { r.addEventListener('change', syncType); });
    syncType();
    showStep(1);

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (!validatePanel(step)) return;
            showStep(Math.min(TOTAL, step + 1));
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            showStep(Math.max(1, step - 1));
        });
    }

    form.addEventListener('submit', function (e) {
        if (step < TOTAL) {
            e.preventDefault();
            return;
        }
        for (let s = 1; s <= TOTAL; s++) {
            if (!validatePanel(s)) {
                e.preventDefault();
                showStep(s);
                return;
            }
        }
    });

    if (btnOtp) {
        btnOtp.addEventListener('click', function () {
            const tel = telInput ? telInput.value.trim() : '';
            if (!tel) {
                if (otpStatus) otpStatus.textContent = 'Retournez à l’étape 1 pour indiquer votre mobile.';
                return;
            }
            btnOtp.disabled = true;
            if (otpStatus) otpStatus.textContent = 'Envoi en cours…';
            const body = new URLSearchParams();
            body.set('telephone', tel);
            if (csrfInput) body.set('csrf_token', csrfInput.value);
            const scopeInput = form.querySelector('input[name="csrf_scope"]');
            if (scopeInput) body.set('csrf_scope', scopeInput.value);
            fetch('api/audiences-otp.php', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
                .then(function (r) {
                    if (r.j && r.j.ok) {
                        let msg = r.j.message || 'Code envoyé.';
                        if (r.j.dev_hint) msg += ' (dev : ' + r.j.dev_hint + ')';
                        if (otpStatus) otpStatus.textContent = msg;
                        if (otpInput) otpInput.focus();
                    } else {
                        if (otpStatus) otpStatus.textContent = (r.j && r.j.error) ? r.j.error : 'Échec de l’envoi.';
                    }
                })
                .catch(function () {
                    if (otpStatus) otpStatus.textContent = 'Erreur réseau. Réessayez.';
                })
                .finally(function () { btnOtp.disabled = false; });
        });
    }

    <?php if ($flashType === 'danger' && $idCreated === null): ?>
    (function () {
        var err = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE); ?>;
        if (/code|sms|mobile|téléphone|telephone/i.test(err)) showStep(3);
        else if (/date|créneau|creneau/i.test(err)) showStep(2);
        else showStep(1);
    })();
    <?php endif; ?>
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
