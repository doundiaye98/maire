<?php
declare(strict_types=1);
$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require __DIR__ . '/includes/header.php';

$feedback = null;
$feedbackError = false;
$demandeGeneree = null;
$piecesUploadees = [];

if (isset($pdo) && $pdo !== null) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demandes_etat_civil (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_dossier VARCHAR(40) NOT NULL UNIQUE,
            type_demande VARCHAR(50) NOT NULL,
            nom_complet VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telephone VARCHAR(40) DEFAULT NULL,
            cni VARCHAR(80) DEFAULT NULL,
            date_naissance DATE DEFAULT NULL,
            lieu_naissance VARCHAR(160) DEFAULT NULL,
            adresse TEXT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            statut VARCHAR(40) NOT NULL DEFAULT 'recu',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demandes_etat_civil_pieces (
            id INT AUTO_INCREMENT PRIMARY KEY,
            demande_id INT NOT NULL,
            nom_fichier VARCHAR(220) NOT NULL,
            chemin_fichier VARCHAR(300) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_demande_piece FOREIGN KEY (demande_id)
                REFERENCES demandes_etat_civil(id) ON DELETE CASCADE
        )
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typeDemande = trim((string) ($_POST['type_demande'] ?? ''));
    $nomComplet = trim((string) ($_POST['nom_complet'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telephone = trim((string) ($_POST['telephone'] ?? ''));
    $cni = trim((string) ($_POST['cni'] ?? ''));
    $dateNaissance = trim((string) ($_POST['date_naissance'] ?? ''));
    $lieuNaissance = trim((string) ($_POST['lieu_naissance'] ?? ''));
    $adresse = trim((string) ($_POST['adresse'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));

    if ($typeDemande === '' || $nomComplet === '' || $email === '') {
        $feedback = 'Merci de renseigner les champs obligatoires.';
        $feedbackError = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = 'Adresse email invalide.';
        $feedbackError = true;
    } elseif (!isset($pdo) || $pdo === null) {
        $feedback = 'Base de donnees indisponible. Reessayez plus tard.';
        $feedbackError = true;
    } else {
        $reference = 'EC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $insert = $pdo->prepare("
            INSERT INTO demandes_etat_civil
            (reference_dossier, type_demande, nom_complet, email, telephone, cni, date_naissance, lieu_naissance, adresse, details)
            VALUES
            (:reference_dossier, :type_demande, :nom_complet, :email, :telephone, :cni, :date_naissance, :lieu_naissance, :adresse, :details)
        ");
        $insert->execute([
            'reference_dossier' => $reference,
            'type_demande' => $typeDemande,
            'nom_complet' => $nomComplet,
            'email' => $email,
            'telephone' => $telephone !== '' ? $telephone : null,
            'cni' => $cni !== '' ? $cni : null,
            'date_naissance' => $dateNaissance !== '' ? $dateNaissance : null,
            'lieu_naissance' => $lieuNaissance !== '' ? $lieuNaissance : null,
            'adresse' => $adresse !== '' ? $adresse : null,
            'details' => $details !== '' ? $details : null,
        ]);

        $demandeId = (int) $pdo->lastInsertId();
        $uploadDir = __DIR__ . '/uploads/etat-civil';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['pieces']) && is_array($_FILES['pieces']['name'])) {
            $count = count($_FILES['pieces']['name']);
            for ($i = 0; $i < $count; $i++) {
                $errorCode = (int) ($_FILES['pieces']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpName = (string) ($_FILES['pieces']['tmp_name'][$i] ?? '');
                $originalName = (string) ($_FILES['pieces']['name'][$i] ?? '');
                $size = (int) ($_FILES['pieces']['size'][$i] ?? 0);
                if ($tmpName === '' || $originalName === '' || $size <= 0 || $size > 5 * 1024 * 1024) {
                    continue;
                }

                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $storedName = $reference . '_' . ($i + 1) . '_' . $safeName;
                $targetPath = $uploadDir . '/' . $storedName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $relativePath = 'uploads/etat-civil/' . $storedName;
                    $insertPiece = $pdo->prepare("
                        INSERT INTO demandes_etat_civil_pieces (demande_id, nom_fichier, chemin_fichier)
                        VALUES (:demande_id, :nom_fichier, :chemin_fichier)
                    ");
                    $insertPiece->execute([
                        'demande_id' => $demandeId,
                        'nom_fichier' => $originalName,
                        'chemin_fichier' => $relativePath,
                    ]);
                    $piecesUploadees[] = $originalName;
                }
            }
        }

        $demandeGeneree = [
            'reference' => $reference,
            'type' => $typeDemande,
            'nom' => $nomComplet,
            'email' => $email,
            'telephone' => $telephone,
            'pieces' => $piecesUploadees,
        ];
        $feedback = 'Demande enregistree avec succes. Votre dossier est genere dans l application.';
    }
}
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-wrap items-end justify-between gap-8">
                <div class="max-w-2xl">
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        État civil · Portail communal
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                        Digitalisation<br><span class="maire-text-gradient">de l'État civil</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed">
                        Une expérience numérique moderne pour déposer, suivre et finaliser vos démarches d'état civil.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 min-w-[340px]">
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black"><span class="maire-counter" data-target="3">0</span><span class="text-lg ml-1">min</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Pré-demande</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black">24<span class="text-lg">/7</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Accès suivi</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black"><span class="maire-counter" data-target="72" data-suffix="h">0</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Délai cible</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES DISPONIBLES -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <span class="maire-tag bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200 mb-3">Catalogue</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Services <span class="maire-text-gradient">disponibles</span></h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                <article class="maire-bento-card tw-card p-7">
                    <span class="inline-flex w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white items-center justify-center text-2xl shadow-md">👶</span>
                    <span class="maire-tag bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200 mb-2">Naissance</span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Demande d'extrait de naissance</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Pré-enregistrement du dossier, vérification des pièces et suivi en ligne.</p>
                </article>
                <article class="maire-bento-card tw-card p-7">
                    <span class="inline-flex w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 text-white items-center justify-center text-2xl shadow-md">💍</span>
                    <span class="maire-tag bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200 mb-2">Mariage</span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Ouverture de dossier de mariage</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Soumission des informations des deux parties et contrôle administratif.</p>
                </article>
                <article class="maire-bento-card tw-card p-7">
                    <span class="inline-flex w-14 h-14 mb-4 rounded-2xl bg-gradient-to-br from-slate-500 to-slate-700 text-white items-center justify-center text-2xl shadow-md">📜</span>
                    <span class="maire-tag bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200 mb-2">Décès &amp; légalisation</span>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Actes de décès et légalisation</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Démarche guidée avec checklist et suivi des étapes jusqu'au retrait.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- PARCOURS GUIDÉ -->
    <section class="py-16 bg-white dark:bg-slate-950">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="maire-tag bg-gold-50 text-gold-700 dark:bg-gold-900/30 dark:text-gold-300 mb-3">Comment ça marche</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Parcours 100% <span class="maire-text-gradient">guidé</span></h2>
            </div>
            <div class="grid md:grid-cols-4 gap-5 relative">
                <div class="hidden md:block absolute top-12 left-[12.5%] right-[12.5%] h-0.5 bg-gradient-to-r from-mairie-300 via-gold-400 to-emerald-300" aria-hidden="true"></div>
                <?php
                $steps = [
                    ['n' => 1, 'titre' => 'Pré-demande', 'desc' => "Vous remplissez le formulaire numérique et choisissez le type d'acte.", 'gradient' => 'from-mairie-700 to-mairie-900'],
                    ['n' => 2, 'titre' => 'Dépôt des pièces', 'desc' => "Upload des justificatifs, vérification automatique des champs obligatoires.", 'gradient' => 'from-blue-500 to-indigo-600'],
                    ['n' => 3, 'titre' => 'Suivi intelligent', 'desc' => "État du dossier en direct : reçu, en cours, validé, prêt à retirer.", 'gradient' => 'from-gold-500 to-orange-600'],
                    ['n' => 4, 'titre' => 'Retrait simplifié', 'desc' => "Notification de disponibilité et retrait au guichet avec référence unique.", 'gradient' => 'from-emerald-500 to-teal-600'],
                ];
                foreach ($steps as $s):
                ?>
                <article class="tw-card p-6 text-center relative">
                    <div class="relative inline-flex w-20 h-20 mb-4 rounded-2xl bg-gradient-to-br <?php echo $s['gradient']; ?> text-white items-center justify-center text-3xl font-black shadow-glow"><?php echo $s['n']; ?></div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white mb-2"><?php echo $s['titre']; ?></h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400"><?php echo $s['desc']; ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FORMULAIRE -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900" id="form-demande-etat-civil">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <span class="maire-tag bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 mb-3">Formulaire en ligne</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white">Soumettre une <span class="maire-text-gradient">demande</span></h2>
            </div>

            <?php if ($feedback !== null): ?>
                <div class="<?php echo $feedbackError ? 'bg-red-50 dark:bg-red-950/30 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'; ?> border-2 rounded-2xl p-4 mb-6 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0"><?php echo $feedbackError ? '⚠️' : '✅'; ?></span>
                    <p class="font-bold"><?php echo htmlspecialchars($feedback); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-[1.4fr_1fr] gap-6">
                <article class="tw-card p-7 md:p-10">
                    <form method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="type_demande" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Type de demande *</label>
                            <select id="type_demande" name="type_demande" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                                <option value="">Choisir...</option>
                                <option value="extrait_naissance">👶 Extrait de naissance</option>
                                <option value="dossier_mariage">💍 Dossier de mariage</option>
                                <option value="acte_deces">📜 Acte de décès</option>
                                <option value="legalisation">🛡 Légalisation</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="nom_complet" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Nom complet *</label>
                            <input id="nom_complet" name="nom_complet" type="text" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Email *</label>
                            <input id="email" name="email" type="email" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="telephone" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Téléphone</label>
                            <input id="telephone" name="telephone" type="text" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="cni" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Numéro CNI</label>
                            <input id="cni" name="cni" type="text" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div>
                            <label for="date_naissance" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Date de naissance</label>
                            <input id="date_naissance" name="date_naissance" type="date" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="lieu_naissance" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Lieu de naissance</label>
                            <input id="lieu_naissance" name="lieu_naissance" type="text" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="adresse" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Adresse</label>
                            <textarea id="adresse" name="adresse" rows="2" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition resize-y"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="details" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Détails de la demande</label>
                            <textarea id="details" name="details" rows="3" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition resize-y"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="pieces" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Pièces justificatives <small class="font-normal text-slate-500">(PDF/JPG/PNG, max 5MB/fichier)</small></label>
                            <input id="pieces" name="pieces[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 outline-none transition file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:font-bold file:bg-mairie-700 file:text-white hover:file:bg-mairie-800">
                        </div>
                        <div class="sm:col-span-2 mt-2">
                            <button class="tw-btn-primary w-full justify-center" type="submit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Générer mon dossier
                            </button>
                        </div>
                    </form>
                </article>

                <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-mairie-800 to-mairie-950 text-white p-7">
                    <div class="absolute -top-12 -right-12 w-60 h-60 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none"></div>
                    <div class="relative">
                        <h3 class="text-xl font-black mb-4 flex items-center gap-2">
                            <span class="w-10 h-10 rounded-xl bg-gold-400 text-mairie-950 flex items-center justify-center">📃</span>
                            Récapitulatif
                        </h3>
                        <?php if ($demandeGeneree !== null): ?>
                            <div class="space-y-2 text-sm" id="demandeReceipt">
                                <p><span class="text-mairie-300 font-bold">Référence :</span> <code class="px-2 py-0.5 rounded bg-white/10 font-mono"><?php echo htmlspecialchars($demandeGeneree['reference']); ?></code></p>
                                <p><span class="text-mairie-300 font-bold">Type :</span> <?php echo htmlspecialchars($demandeGeneree['type']); ?></p>
                                <p><span class="text-mairie-300 font-bold">Demandeur :</span> <?php echo htmlspecialchars($demandeGeneree['nom']); ?></p>
                                <p><span class="text-mairie-300 font-bold">Email :</span> <?php echo htmlspecialchars($demandeGeneree['email']); ?></p>
                                <p><span class="text-mairie-300 font-bold">Téléphone :</span> <?php echo htmlspecialchars($demandeGeneree['telephone'] !== '' ? $demandeGeneree['telephone'] : 'Non renseigné'); ?></p>
                                <p><span class="text-mairie-300 font-bold">Statut :</span> <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gold-400/30 text-gold-300 text-xs font-black uppercase">Reçu</span></p>
                                <p><span class="text-mairie-300 font-bold">Date :</span> <?php echo date('d/m/Y H:i'); ?></p>
                                <p><span class="text-mairie-300 font-bold">Pièces :</span> <?php echo htmlspecialchars(!empty($demandeGeneree['pieces']) ? implode(', ', $demandeGeneree['pieces']) : 'Aucune'); ?></p>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-5">
                                <button class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white text-xs font-bold transition-colors" type="button" onclick="window.print()">🖨 Imprimer</button>
                                <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white text-xs font-bold transition-colors" href="telecharger-recepisse.php?ref=<?php echo urlencode($demandeGeneree['reference']); ?>">📄 PDF</a>
                                <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 text-xs font-black transition-colors" href="suivi-etat-civil.php?ref=<?php echo urlencode($demandeGeneree['reference']); ?>">Suivre →</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 opacity-70">
                                <div class="text-5xl mb-3">📋</div>
                                <p class="text-sm text-mairie-200">Une fois la demande soumise, votre référence de dossier sera affichée ici avec un récépissé imprimable.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
