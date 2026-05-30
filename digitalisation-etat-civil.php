<?php
declare(strict_types=1);

$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require_once __DIR__ . '/includes/session-performance.php';
require_once __DIR__ . '/includes/citoyen-session.php';
require_once __DIR__ . '/includes/etat-civil-demande.php';
require_once __DIR__ . '/includes/maire-rate-limit.php';
require_once __DIR__ . '/includes/csrf.php';

maire_session_configure_ini();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$csrfScope = MAIRE_CSRF_SCOPE_ETAT_CIVIL;
$citoyenConnecte = maire_citoyen_session_valid();

$dataSaisie = [
    'type_demande' => (string) ($_GET['type'] ?? ''),
    'nom_complet' => $citoyenConnecte
        ? trim((string) ($_SESSION['citoyen_prenom'] ?? '') . ' ' . (string) ($_SESSION['citoyen_nom'] ?? ''))
        : '',
    'email' => $citoyenConnecte ? (string) ($_SESSION['citoyen_email'] ?? '') : '',
    'telephone' => '',
    'cni' => '',
    'date_naissance' => '',
    'lieu_naissance' => '',
    'adresse' => '',
    'details' => '',
];

if (!array_key_exists($dataSaisie['type_demande'], MAIRE_ETAT_CIVIL_TYPES)) {
    $dataSaisie['type_demande'] = '';
}

$flash = '';
$flashType = 'success';
$demandeGeneree = null;

if (isset($_SESSION['etat_civil_demande_ok'])) {
    $demandeGeneree = $_SESSION['etat_civil_demande_ok'];
    unset($_SESSION['etat_civil_demande_ok']);
    $flash = 'Demande enregistrée avec succès.';
    $flashType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate($csrfScope)) {
        $flash = 'Jeton de sécurité invalide. Rechargez la page.';
        $flashType = 'danger';
    } elseif (!maire_rate_limit_allow('etat_civil_demande', 6, 600)) {
        $flash = 'Trop de demandes depuis cette connexion. Réessayez dans quelques minutes.';
        $flashType = 'danger';
    } else {
        $dataSaisie = [
            'type_demande' => (string) ($_POST['type_demande'] ?? ''),
            'nom_complet' => trim((string) ($_POST['nom_complet'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
            'cni' => trim((string) ($_POST['cni'] ?? '')),
            'date_naissance' => trim((string) ($_POST['date_naissance'] ?? '')),
            'lieu_naissance' => trim((string) ($_POST['lieu_naissance'] ?? '')),
            'adresse' => trim((string) ($_POST['adresse'] ?? '')),
            'details' => trim((string) ($_POST['details'] ?? '')),
        ];
        $err = null;
        $files = isset($_FILES['pieces']) ? $_FILES['pieces'] : null;
        $result = maire_creer_demande_etat_civil($pdo, $dataSaisie, $files, $err);
        if ($result === null) {
            $flash = $err ?? 'Enregistrement impossible.';
            $flashType = 'danger';
        } else {
            $_SESSION['etat_civil_demande_ok'] = $result;
            header('Location: digitalisation-etat-civil.php?ok=1#recap', true, 303);
            exit;
        }
    }
}

$pageTitle = 'État civil en ligne | Rufisque-Est';
$pageDescription = 'Déposez votre demande d’état civil en ligne : extrait de naissance, mariage, décès, légalisation.';
require __DIR__ . '/includes/header.php';

$checklistJson = json_encode(MAIRE_ETAT_CIVIL_CHECKLIST, JSON_UNESCAPED_UNICODE);
$uploadRulesJson = json_encode([
    'maxFiles' => MAIRE_ETAT_CIVIL_UPLOAD_MAX_FILES,
    'maxBytes' => MAIRE_ETAT_CIVIL_UPLOAD_MAX_BYTES,
    'acceptedTypes' => maire_etat_civil_mimes_autorises(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-20 md:py-24 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <span class="maire-section-kicker mb-4 !bg-white/12 !text-white !border-white/20">
                <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                État civil numérique
            </span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight mb-4">
                Démarches d’<span class="maire-text-gradient">état civil</span> en ligne
            </h1>
            <p class="text-lg text-mairie-100 max-w-2xl leading-relaxed mb-6">
                Déposez votre dossier en quelques minutes, joignez vos pièces et suivez l’avancement avec votre référence unique.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="#form-demande-etat-civil" class="tw-btn-primary bg-gold-500 hover:bg-gold-400 text-mairie-950 shadow-lg">Démarrer une demande</a>
                <a href="suivi-etat-civil.php" class="tw-btn-outline border-white/40 text-white hover:bg-white/10">Suivre un dossier</a>
            </div>
        </div>
    </section>

    <section class="py-12 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $steps = [
                    ['1', 'Type & coordonnées', 'Choisissez l’acte et vos contacts'],
                    ['2', 'Informations', 'Complétez le dossier'],
                    ['3', 'Pièces & envoi', 'Scannez vos justificatifs'],
                    ['✓', 'Suivi', 'Référence EC-… et reçu'],
                ];
                foreach ($steps as $s):
                ?>
                <div class="maire-kpi-card text-center">
                    <p class="text-2xl font-black text-mairie-800 dark:text-mairie-200 mb-1"><?php echo $s[0]; ?></p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($s[1], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($s[2], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-14 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Choisissez votre <span class="maire-text-gradient">démarche</span></h2>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
                <?php
                $cards = [
                    ['extrait_naissance', 'Naissance', 'Extrait de naissance', 'from-emerald-500 to-teal-600'],
                    ['dossier_mariage', 'Mariage', 'Ouverture de dossier', 'from-rose-500 to-pink-600'],
                    ['acte_deces', 'Décès', 'Acte de décès', 'from-slate-500 to-slate-700'],
                    ['legalisation', 'Légalisation', 'Copie conforme', 'from-mairie-700 to-mairie-900'],
                ];
                foreach ($cards as [$code, $tag, $titre, $grad]):
                    $isSelectedType = $dataSaisie['type_demande'] === $code;
                ?>
                <button type="button" class="maire-editorial-card maire-bento-card p-5 text-left w-full js-pick-type <?php echo $isSelectedType ? 'is-selected' : ''; ?>" data-type="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="<?php echo $isSelectedType ? 'true' : 'false'; ?>">
                    <span class="inline-block text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-gradient-to-r <?php echo $grad; ?> text-white mb-2"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                    <h3 class="font-black text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($titre, ENT_QUOTES, 'UTF-8'); ?></h3>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white dark:bg-slate-950" id="form-demande-etat-civil">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <?php if ($demandeGeneree === null): ?>
            <div class="max-w-3xl mx-auto mb-8">
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white text-center mb-2">Formulaire en ligne</h2>
                <p class="text-center text-sm text-slate-600 dark:text-slate-400">3 étapes · environ 3 minutes</p>
            </div>

            <?php if ($flash !== '' && $demandeGeneree === null): ?>
                <div class="max-w-3xl mx-auto mb-6 <?php echo $flashType === 'danger' ? 'bg-red-50 dark:bg-red-950/30 border-red-300 text-red-800' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 text-emerald-800'; ?> border-2 rounded-2xl p-4">
                    <p class="font-bold"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$citoyenConnecte): ?>
                <p class="max-w-3xl mx-auto text-sm text-slate-600 dark:text-slate-400 mb-6 text-center">
                    <a class="font-bold text-mairie-700 dark:text-mairie-300 underline" href="citoyen/connexion.php">Connectez-vous</a>
                    pour préremplir vos coordonnées.
                </p>
            <?php endif; ?>

            <div class="grid lg:grid-cols-[1fr_280px] gap-8 max-w-5xl mx-auto">
                <article class="maire-form-shell">
                    <nav class="maire-wizard-steps mb-8" aria-label="Étapes">
                        <div class="maire-wizard-step is-active" data-wizard-step-indicator="1">
                            <span class="maire-wizard-step__dot">1</span>
                            <span class="maire-wizard-step__label">Démarche</span>
                        </div>
                        <div class="maire-wizard-bar" aria-hidden="true"></div>
                        <div class="maire-wizard-step" data-wizard-step-indicator="2">
                            <span class="maire-wizard-step__dot">2</span>
                            <span class="maire-wizard-step__label">Dossier</span>
                        </div>
                        <div class="maire-wizard-bar" aria-hidden="true"></div>
                        <div class="maire-wizard-step" data-wizard-step-indicator="3">
                            <span class="maire-wizard-step__dot">3</span>
                            <span class="maire-wizard-step__label">Envoi</span>
                        </div>
                    </nav>

                    <form method="post" enctype="multipart/form-data" id="form-etat-civil" class="space-y-5" novalidate>
                        <?php echo maire_csrf_field($csrfScope); ?>

                        <div class="maire-wizard-panel" data-wizard-step="1">
                            <div>
                                <label for="type_demande" class="block text-sm font-bold mb-1.5">Type de demande *</label>
                                <select id="type_demande" name="type_demande" required class="tw-input">
                                    <option value="">Choisir…</option>
                                    <?php foreach (MAIRE_ETAT_CIVIL_TYPES as $code => $label): ?>
                                        <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dataSaisie['type_demande'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="nom_complet" class="block text-sm font-bold mb-1.5">Nom complet *</label>
                                <input id="nom_complet" name="nom_complet" type="text" required maxlength="160" class="tw-input" value="<?php echo htmlspecialchars($dataSaisie['nom_complet'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="email" class="block text-sm font-bold mb-1.5">Email *</label>
                                    <input id="email" name="email" type="email" required maxlength="190" class="tw-input" value="<?php echo htmlspecialchars($dataSaisie['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div>
                                    <label for="telephone" class="block text-sm font-bold mb-1.5">Téléphone</label>
                                    <input id="telephone" name="telephone" type="tel" maxlength="40" class="tw-input" placeholder="77 123 45 67" value="<?php echo htmlspecialchars($dataSaisie['telephone'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div>
                                <label for="cni" class="block text-sm font-bold mb-1.5">N° CNI / passeport</label>
                                <input id="cni" name="cni" type="text" maxlength="80" class="tw-input" value="<?php echo htmlspecialchars($dataSaisie['cni'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="maire-wizard-panel" data-wizard-step="2" hidden>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="date_naissance" class="block text-sm font-bold mb-1.5">Date de naissance</label>
                                    <input id="date_naissance" name="date_naissance" type="date" class="tw-input" value="<?php echo htmlspecialchars($dataSaisie['date_naissance'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div>
                                    <label for="lieu_naissance" class="block text-sm font-bold mb-1.5">Lieu de naissance</label>
                                    <input id="lieu_naissance" name="lieu_naissance" type="text" maxlength="160" class="tw-input" value="<?php echo htmlspecialchars($dataSaisie['lieu_naissance'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div>
                                <label for="adresse" class="block text-sm font-bold mb-1.5">Adresse</label>
                                <textarea id="adresse" name="adresse" rows="2" class="tw-input resize-y"><?php echo htmlspecialchars($dataSaisie['adresse'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div>
                                <label for="details" class="block text-sm font-bold mb-1.5">Précisions utiles</label>
                                <textarea id="details" name="details" rows="3" class="tw-input resize-y" placeholder="Noms des parents, numéro d’acte si connu, urgence…"><?php echo htmlspecialchars($dataSaisie['details'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <div class="maire-wizard-panel" data-wizard-step="3" hidden>
                            <div>
                                <label for="pieces" class="block text-sm font-bold mb-1.5">Pièces justificatives <span class="font-normal text-slate-500">(PDF, JPG, PNG — max 5 fichiers × 5 Mo)</span></label>
                                <input id="pieces" name="pieces[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-max-files="<?php echo MAIRE_ETAT_CIVIL_UPLOAD_MAX_FILES; ?>" data-max-bytes="<?php echo MAIRE_ETAT_CIVIL_UPLOAD_MAX_BYTES; ?>" class="tw-input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:font-bold file:bg-mairie-700 file:text-white">
                                <p id="ec-files-feedback" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Aucun fichier sélectionné.</p>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Les pièces peuvent aussi être déposées au guichet avec votre référence.</p>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                            <button type="button" id="ec-prev" class="tw-btn-outline hidden">← Précédent</button>
                            <button type="button" id="ec-next" class="tw-btn-primary flex-1 justify-center">Continuer →</button>
                            <button type="submit" id="ec-submit" class="tw-btn-primary flex-1 justify-center hidden">Enregistrer mon dossier</button>
                        </div>
                    </form>
                </article>

                <aside class="maire-panel p-5 h-fit sticky top-24">
                    <h3 class="text-sm font-black uppercase tracking-wide text-slate-700 dark:text-slate-300 mb-3">Pièces à prévoir</h3>
                    <ul id="ec-checklist" class="text-sm text-slate-600 dark:text-slate-400 space-y-2 list-disc list-inside">
                        <li>Sélectionnez un type de demande</li>
                    </ul>
                </aside>
            </div>

            <?php else: ?>
            <div id="recap" class="max-w-2xl mx-auto">
                <?php if ($flash !== ''): ?>
                    <div class="bg-emerald-50 dark:bg-emerald-950/30 border-2 border-emerald-300 rounded-2xl p-4 mb-6">
                        <p class="font-bold text-emerald-900 dark:text-emerald-100"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endif; ?>
                <article class="maire-panel p-8 text-center">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Votre référence</p>
                    <p class="text-3xl font-black font-mono text-mairie-800 dark:text-mairie-200 mb-6"><?php echo htmlspecialchars((string) $demandeGeneree['reference'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <dl class="text-left text-sm space-y-2 mb-6 text-slate-700 dark:text-slate-300">
                        <div class="flex justify-between gap-4"><dt class="font-bold">Type</dt><dd><?php echo htmlspecialchars((string) ($demandeGeneree['type_libelle'] ?? $demandeGeneree['type']), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold">Demandeur</dt><dd><?php echo htmlspecialchars((string) $demandeGeneree['nom'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold">Pièces jointes</dt><dd><?php echo !empty($demandeGeneree['pieces']) ? count($demandeGeneree['pieces']) : 0; ?></dd></div>
                    </dl>
                    <div class="flex flex-wrap justify-center gap-3">
                        <a class="tw-btn-primary" href="suivi-etat-civil.php?ref=<?php echo urlencode((string) $demandeGeneree['reference']); ?>">Suivre mon dossier</a>
                        <a class="tw-btn-outline" href="telecharger-recepisse.php?ref=<?php echo urlencode((string) $demandeGeneree['reference']); ?>">Télécharger le reçu</a>
                        <a class="tw-btn-ghost text-sm" href="digitalisation-etat-civil.php">Nouvelle demande</a>
                    </div>
                </article>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php if ($demandeGeneree === null): ?>
<style>
    .js-pick-type {
        border: 2px solid transparent;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .js-pick-type:hover,
    .js-pick-type:focus-visible {
        transform: translateY(-2px);
    }

    .js-pick-type.is-selected {
        transform: translateY(-2px);
        border-color: rgba(14, 116, 144, 0.9);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        background: rgba(240, 249, 255, 0.95);
    }

    .dark .js-pick-type.is-selected {
        background: rgba(15, 23, 42, 0.92);
        box-shadow: 0 18px 45px rgba(2, 6, 23, 0.45);
    }
</style>
<script>
(function () {
    const CHECKLIST = <?php echo $checklistJson; ?>;
    const UPLOAD_RULES = <?php echo $uploadRulesJson; ?>;
    const form = document.getElementById('form-etat-civil');
    if (!form) return;

    const TOTAL = 3;
    let step = 1;
    const panels = form.querySelectorAll('[data-wizard-step]');
    const indicators = document.querySelectorAll('[data-wizard-step-indicator]');
    const btnPrev = document.getElementById('ec-prev');
    const btnNext = document.getElementById('ec-next');
    const btnSubmit = document.getElementById('ec-submit');
    const typeSelect = document.getElementById('type_demande');
    const checklistEl = document.getElementById('ec-checklist');
    const typeButtons = document.querySelectorAll('.js-pick-type');
    const fileInput = document.getElementById('pieces');
    const fileFeedback = document.getElementById('ec-files-feedback');

    function updateChecklist() {
        const t = typeSelect ? typeSelect.value : '';
        if (!checklistEl) return;
        if (!t || !CHECKLIST[t]) {
            checklistEl.innerHTML = '<li>Sélectionnez un type de demande</li>';
            return;
        }
        checklistEl.innerHTML = CHECKLIST[t].map(function (item) {
            return '<li>' + item.replace(/</g, '&lt;') + '</li>';
        }).join('');
    }

    function updateTypeCards() {
        const selectedType = typeSelect ? typeSelect.value : '';
        typeButtons.forEach(function (btn) {
            const isSelected = btn.getAttribute('data-type') === selectedType;
            btn.classList.toggle('is-selected', isSelected);
            btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
    }

    function setFileFeedback(message, isError) {
        if (!fileFeedback) return;
        fileFeedback.textContent = message;
        fileFeedback.className = isError
            ? 'mt-2 text-xs font-bold text-red-700 dark:text-red-300'
            : 'mt-2 text-xs text-slate-500 dark:text-slate-400';
    }

    function validateFiles() {
        if (!fileInput) return true;

        const files = Array.from(fileInput.files || []);
        if (files.length === 0) {
            fileInput.setCustomValidity('');
            setFileFeedback('Aucun fichier sélectionné.', false);
            return true;
        }

        if (files.length > UPLOAD_RULES.maxFiles) {
            const message = 'Maximum ' + UPLOAD_RULES.maxFiles + ' fichiers autorisés.';
            fileInput.setCustomValidity(message);
            setFileFeedback(message, true);
            return false;
        }

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.size > UPLOAD_RULES.maxBytes) {
                const message = '"' + file.name + '" dépasse 5 Mo.';
                fileInput.setCustomValidity(message);
                setFileFeedback(message, true);
                return false;
            }
            if (file.type && !UPLOAD_RULES.acceptedTypes.includes(file.type)) {
                const message = '"' + file.name + '" n’est pas au bon format.';
                fileInput.setCustomValidity(message);
                setFileFeedback(message + ' Formats acceptés : PDF, JPG, PNG.', true);
                return false;
            }
        }

        fileInput.setCustomValidity('');
        setFileFeedback(files.length + ' fichier' + (files.length > 1 ? 's' : '') + ' prêt' + (files.length > 1 ? 's' : '') + ' à être envoyé' + (files.length > 1 ? 's' : '') + '.', false);
        return true;
    }

    function showStep(n) {
        step = n;
        panels.forEach(function (p) {
            p.hidden = parseInt(p.getAttribute('data-wizard-step'), 10) !== n;
        });
        indicators.forEach(function (ind) {
            const s = parseInt(ind.getAttribute('data-wizard-step-indicator'), 10);
            ind.classList.toggle('is-active', s === n);
            ind.classList.toggle('is-done', s < n);
        });
        if (btnPrev) btnPrev.classList.toggle('hidden', n <= 1);
        if (btnNext) btnNext.classList.toggle('hidden', n >= TOTAL);
        if (btnSubmit) btnSubmit.classList.toggle('hidden', n < TOTAL);
    }

    function validatePanel(n) {
        const panel = form.querySelector('[data-wizard-step="' + n + '"]');
        if (!panel) return true;
        const fields = panel.querySelectorAll('input, select, textarea');
        for (let i = 0; i < fields.length; i++) {
            const el = fields[i];
            if (el.disabled || el.type === 'hidden' || el.type === 'file') continue;
            if (!el.checkValidity()) {
                el.reportValidity();
                el.focus();
                return false;
            }
        }
        return true;
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            updateChecklist();
            updateTypeCards();
        });
        updateChecklist();
        updateTypeCards();
    }

    if (fileInput) {
        fileInput.addEventListener('change', validateFiles);
        validateFiles();
    }

    document.querySelectorAll('.js-pick-type').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const t = btn.getAttribute('data-type');
            if (typeSelect && t) {
                typeSelect.value = t;
                updateChecklist();
                updateTypeCards();
            }
            document.getElementById('form-demande-etat-civil')?.scrollIntoView({ behavior: 'smooth' });
            showStep(1);
        });
    });

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (!validatePanel(step)) return;
            showStep(Math.min(TOTAL, step + 1));
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () { showStep(Math.max(1, step - 1)); });
    }

    form.addEventListener('submit', function (e) {
        if (step < TOTAL) { e.preventDefault(); return; }
        if (!validateFiles()) {
            e.preventDefault();
            showStep(3);
            fileInput?.reportValidity();
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

    showStep(1);

    <?php if ($flashType === 'danger'): ?>
    showStep(<?php echo strpos($flash, 'fichier') !== false || strpos($flash, 'Format') !== false || strpos($flash, 'maximum') !== false ? 3 : (strpos($flash, 'email') !== false || strpos($flash, 'Nom') !== false ? 1 : 2); ?>);
    <?php endif; ?>
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
