<?php
declare(strict_types=1);

/**
 * Bibliothèque publique de documents municipaux.
 * Accessible à tous (citoyens, agents, visiteurs).
 */
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/documents-publics.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$filtreCategorie = (string) ($_GET['cat'] ?? '');
$documents = $pdo !== null ? maire_liste_documents_publics($pdo, $filtreCategorie !== '' ? $filtreCategorie : null, 300) : [];

$documentsParCategorie = [];
foreach ($documents as $doc) {
    $cat = (string) ($doc['categorie'] ?? 'autre');
    $documentsParCategorie[$cat][] = $doc;
}

$totalDocuments = count($documents);
$totalTelechargements = 0;
foreach ($documents as $d) {
    $totalTelechargements += (int) ($d['nb_telechargements'] ?? 0);
}

$estAdminConnecte = (($_SESSION['subscriber_role'] ?? '') === 'admin');

/**
 * Documents-types proposés par défaut (affichés tant que la mairie n'a pas
 * uploadé ses fichiers réels). Chaque entrée renvoie vers le formulaire
 * contact pour faire une demande explicite.
 */
$documentsTypes = [
    'formulaire' => [
        ['titre' => 'Demande d’extrait de naissance', 'description' => 'Formulaire à remplir pour obtenir un extrait d’acte de naissance auprès du service État civil.'],
        ['titre' => 'Demande d’extrait de mariage', 'description' => 'Modèle officiel pour solliciter un extrait d’acte de mariage célébré à Rufisque-Est.'],
        ['titre' => 'Demande de certificat de résidence', 'description' => 'Document à renseigner pour attester de votre domicile dans la commune.'],
    ],
    'acte' => [
        ['titre' => 'Copie intégrale d’acte d’état civil', 'description' => 'Acte certifié conforme remis sur demande motivée auprès du guichet État civil.'],
        ['titre' => 'Certificat de non-imposition', 'description' => 'Attestation délivrée pour les démarches sociales, scolaires ou administratives.'],
    ],
    'autorisation' => [
        ['titre' => 'Autorisation d’occupation du domaine public', 'description' => 'Pour organiser un événement, installer un stand ou occuper temporairement la voirie.'],
        ['titre' => 'Demande de permis de construire', 'description' => 'Formulaire d’instruction des projets d’urbanisme et de construction.'],
    ],
    'demarche' => [
        ['titre' => 'Guide des démarches citoyennes', 'description' => 'Brochure récapitulative des démarches courantes auprès de la mairie.'],
        ['titre' => 'Guide d’accompagnement État civil', 'description' => 'Procédure pas-à-pas pour les actes de naissance, mariage et décès.'],
    ],
    'rapport' => [
        ['titre' => 'Rapport annuel de la commune', 'description' => 'Bilan des actions municipales et chiffres clés de l’année écoulée.'],
    ],
];

$nbDocumentsTypes = 0;
foreach ($documentsTypes as $items) { $nbDocumentsTypes += count($items); }

$pageTitle = 'Documents municipaux à télécharger';
$pageDescription = 'Téléchargez librement les formulaires, actes administratifs, autorisations et guides de démarches de la Mairie de Rufisque-Est.';
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-wrap items-end justify-between gap-8">
                <div class="max-w-2xl">
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Espace habitants
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                        Documents<br><span class="maire-text-gradient">à télécharger</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed">
                        Formulaires officiels, actes administratifs, autorisations modèles et guides pratiques mis à disposition par la mairie.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 min-w-[280px]">
                    <div class="p-5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-4xl font-black"><span class="maire-counter" data-target="<?php echo $totalDocuments; ?>">0</span></p>
                        <p class="text-xs text-mairie-200 uppercase tracking-wider font-bold mt-1">Documents</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-4xl font-black"><span class="maire-counter" data-target="<?php echo $totalTelechargements; ?>">0</span></p>
                        <p class="text-xs text-mairie-200 uppercase tracking-wider font-bold mt-1">Téléchargements</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FILTRE CATÉGORIES + LISTE -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <!-- Filtres chips -->
            <form method="GET" action="documents.php" class="tw-card p-5 mb-8" id="docFilter">
                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold mb-3">Filtrer par catégorie</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="cat" value=""
                            class="<?php echo $filtreCategorie === '' ? 'bg-mairie-800 text-white shadow-glow' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'; ?> px-4 py-2 rounded-full text-sm font-bold transition-all">
                        🗂️ Toutes
                    </button>
                    <?php foreach (MAIRE_DOCUMENTS_CATEGORIES as $code => $label):
                        $actif = $filtreCategorie === $code;
                    ?>
                        <button type="submit" name="cat" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                class="<?php echo $actif ? 'bg-mairie-800 text-white shadow-glow' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'; ?> px-4 py-2 rounded-full text-sm font-bold transition-all">
                            <?php echo htmlspecialchars(maire_icone_categorie_document($code) . ' ' . $label, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>

            <?php if (empty($documents)): ?>
                <!-- (A) BANDEAU ADMIN — visible uniquement aux admins connectés -->
                <?php if ($estAdminConnecte): ?>
                    <div class="mb-8 p-6 rounded-3xl bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-xl flex flex-wrap items-center gap-4 justify-between relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/15 rounded-full blur-2xl pointer-events-none" aria-hidden="true"></div>
                        <div class="relative flex items-start gap-3">
                            <span class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl flex-shrink-0">⚡</span>
                            <div>
                                <p class="font-black text-lg leading-tight">Espace admin — bibliothèque vide</p>
                                <p class="text-sm text-amber-50">Uploadez les premiers documents officiels depuis la console d’administration.</p>
                            </div>
                        </div>
                        <a href="admin/documents.php" class="relative inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white text-orange-700 hover:bg-amber-50 font-black transition-colors shadow-md">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Uploader des documents
                        </a>
                    </div>
                <?php endif; ?>

                <!-- État vide — engageant et informatif -->
                <div class="tw-card p-8 md:p-12 mb-8 relative overflow-hidden">
                    <div class="absolute -top-20 -right-20 w-72 h-72 bg-mairie-100/60 dark:bg-mairie-900/20 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                    <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-gold-100/50 dark:bg-gold-900/15 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                    <div class="relative text-center max-w-2xl mx-auto">
                        <div class="text-6xl mb-3">📭</div>
                        <span class="maire-tag bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Bibliothèque en cours de constitution
                        </span>
                        <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-3 leading-tight">
                            La bibliothèque <span class="maire-text-gradient">arrive bientôt</span>
                        </h2>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                            La mairie de Rufisque-Est prépare la mise en ligne progressive de ses formulaires officiels, actes administratifs et guides pratiques. En attendant, vous pouvez nous contacter directement pour obtenir un document précis.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <a href="contact.php" class="tw-btn-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Demander un document
                            </a>
                            <a href="services.php" class="tw-btn-outline">Voir les services</a>
                        </div>
                    </div>
                </div>

                <!-- Aperçu des catégories à venir -->
                <div class="mb-8">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-2xl shadow-md">📚</span>
                        <div>
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white">Ce que vous trouverez ici</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Aperçu des catégories de documents qui seront publiées</p>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php
                        $apercuCategories = [
                            'formulaire' => ['Formulaires', 'Demandes d’extrait, dossiers d’état civil, fiches d’inscription…', 'from-emerald-500 to-teal-600'],
                            'acte' => ['Actes administratifs', 'Arrêtés municipaux, délibérations, communiqués officiels.', 'from-blue-500 to-indigo-600'],
                            'autorisation' => ['Autorisations', 'Permis de construire, d’occupation du domaine public, de voirie.', 'from-amber-500 to-orange-500'],
                            'demarche' => ['Guides de démarche', 'Fiches pratiques pas-à-pas pour les démarches courantes.', 'from-fuchsia-500 to-purple-600'],
                            'rapport' => ['Rapports & publications', 'Bilans, budgets, rapports d’activité municipale.', 'from-rose-500 to-pink-600'],
                            'autre' => ['Autres documents', 'Communications, modèles divers et ressources utiles.', 'from-slate-500 to-slate-700'],
                        ];
                        foreach ($apercuCategories as $code => [$titre, $desc, $gradient]):
                        ?>
                            <article class="maire-bento-card relative rounded-3xl overflow-hidden bg-gradient-to-br <?php echo $gradient; ?> text-white p-6 shadow-xl">
                                <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/15 rounded-full blur-2xl pointer-events-none" aria-hidden="true"></div>
                                <div class="relative">
                                    <div class="w-12 h-12 mb-3 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shadow-md">
                                        <?php echo htmlspecialchars(maire_icone_categorie_document($code), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <h3 class="text-lg font-black mb-2 leading-tight"><?php echo htmlspecialchars($titre, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="text-sm text-white/90 leading-snug"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-black uppercase tracking-widest bg-white/20 px-2 py-1 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                        Prochainement
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- (B) DOCUMENTS-TYPES — liste détaillée demandables auprès de la mairie -->
                <div class="mb-10">
                    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                        <div class="flex items-center gap-3">
                            <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-gold-500 to-orange-600 text-white flex items-center justify-center text-2xl shadow-md">📋</span>
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white">Documents proposés par la mairie</h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo $nbDocumentsTypes; ?> documents-types · regroupés par catégorie</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 text-xs font-black uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Sur demande
                        </span>
                    </div>

                    <?php foreach ($documentsTypes as $catCode => $docs):
                        $libelle = maire_libelle_categorie_document($catCode);
                        $icone = maire_icone_categorie_document($catCode);
                    ?>
                        <div class="mb-6 last:mb-0">
                            <h3 class="text-sm font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span class="text-base"><?php echo htmlspecialchars($icone, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php echo htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="text-slate-400 dark:text-slate-500 font-normal normal-case">· <?php echo count($docs); ?></span>
                            </h3>
                            <div class="grid md:grid-cols-2 gap-3">
                                <?php foreach ($docs as $doc): ?>
                                    <article class="tw-card p-4 flex items-start gap-3 group">
                                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-100 to-mairie-50 dark:from-slate-700 dark:to-mairie-900/40 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xl opacity-90"><?php echo htmlspecialchars($icone, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-black text-slate-900 dark:text-white leading-snug text-sm"><?php echo htmlspecialchars((string) $doc['titre'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed"><?php echo htmlspecialchars((string) $doc['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <div class="mt-2 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Sur demande
                                            </div>
                                        </div>
                                        <a class="tw-btn-outline text-xs flex-shrink-0 py-1.5 px-3" href="contact.php?sujet=<?php echo urlencode($doc['titre']); ?>">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                            <span class="hidden sm:inline">Demander</span>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- FAQ rapide -->
                <div class="tw-card p-7 md:p-8">
                    <h2 class="text-xl font-black text-slate-900 dark:text-white mb-5 flex items-center gap-2">
                        <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center">❓</span>
                        Foire aux questions
                    </h2>
                    <div class="grid md:grid-cols-2 gap-x-8 gap-y-5 text-sm">
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1">Comment obtenir un document précis maintenant ?</h3>
                            <p class="text-slate-600 dark:text-slate-400">Contactez la mairie par e-mail à <a href="mailto:Rufisquest02@gmail.com" class="font-bold text-mairie-700 dark:text-mairie-300 hover:underline">Rufisquest02@gmail.com</a>, ou rendez-vous à l’accueil — Toujours ouvert, 7j/7.</p>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1">Quand cette bibliothèque sera-t-elle remplie ?</h3>
                            <p class="text-slate-600 dark:text-slate-400">L’équipe administrative publie les documents au fil de leur numérisation. Revenez régulièrement pour suivre les nouveautés.</p>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1">Les téléchargements sont-ils gratuits ?</h3>
                            <p class="text-slate-600 dark:text-slate-400">Oui, l’ensemble des documents municipaux mis en ligne sont libres d’accès et téléchargeables sans inscription.</p>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1">Quel format pour les fichiers ?</h3>
                            <p class="text-slate-600 dark:text-slate-400">Principalement PDF (formulaires, actes), parfois Word/Excel pour les modèles éditables. Tous sont scannés contre les virus avant publication.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($estAdminConnecte): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-mairie-50 dark:bg-mairie-950/40 border-2 border-mairie-200 dark:border-mairie-800 flex flex-wrap items-center gap-3 justify-between">
                        <p class="text-sm text-mairie-900 dark:text-mairie-200 font-bold flex items-center gap-2"><span class="text-base">🛠️</span> Mode admin : gérez la bibliothèque depuis la console.</p>
                        <a href="admin/documents.php" class="tw-btn-primary text-sm">Ouvrir la console</a>
                    </div>
                <?php endif; ?>
                <?php foreach ($documentsParCategorie as $catCode => $docs): ?>
                    <div class="mb-10">
                        <div class="flex items-center gap-3 mb-5">
                            <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-2xl shadow-md">
                                <?php echo htmlspecialchars(maire_icone_categorie_document($catCode), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white">
                                    <?php echo htmlspecialchars(maire_libelle_categorie_document($catCode), ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo count($docs); ?> document<?php echo count($docs) > 1 ? 's' : ''; ?> disponible<?php echo count($docs) > 1 ? 's' : ''; ?></p>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <?php foreach ($docs as $doc): ?>
                                <article class="tw-card p-5 flex items-start gap-4 group">
                                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-red-100 to-orange-100 dark:from-red-900/30 dark:to-orange-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-3xl">📄</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-black text-slate-900 dark:text-white group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition-colors truncate">
                                            <?php echo htmlspecialchars((string) ($doc['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <?php if (!empty($doc['description'])): ?>
                                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 line-clamp-2"><?php echo htmlspecialchars((string) $doc['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-2">
                                            <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><?php echo htmlspecialchars(maire_format_taille_fichier((int) ($doc['fichier_taille'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><strong><?php echo (int) ($doc['nb_telechargements'] ?? 0); ?></strong></span>
                                            <span class="text-slate-400">·</span>
                                            <span><?php echo htmlspecialchars(substr((string) ($doc['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <a class="tw-btn-primary text-sm flex-shrink-0" href="telecharger-document.php?id=<?php echo (int) $doc['id']; ?>" download>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span class="hidden sm:inline">Télécharger</span>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Bloc "manque un document ?" -->
            <div class="mt-12 p-8 rounded-3xl bg-gradient-to-br from-mairie-800 to-mairie-950 text-white relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-80 h-80 bg-gold-500/30 rounded-full blur-3xl maire-blob pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-6">
                    <div class="max-w-2xl">
                        <h2 class="text-2xl md:text-3xl font-black mb-2">Vous ne trouvez pas un document&nbsp;?</h2>
                        <p class="text-mairie-100">Contactez directement la mairie pour en faire la demande — nous l'ajouterons ou vous l'enverrons par e-mail.</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="contact.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 font-black transition-colors">
                            Contacter la mairie
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="services.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 font-black transition-colors">
                            Voir services
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
