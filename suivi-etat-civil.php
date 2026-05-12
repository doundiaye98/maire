<?php
declare(strict_types=1);
$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require __DIR__ . '/includes/header.php';

$reference = trim((string) ($_GET['ref'] ?? ''));
$resultat = null;
$pieces = [];
$erreur = null;

if ($reference !== '') {
    if (!isset($pdo) || $pdo === null) {
        $erreur = 'Base indisponible.';
    } else {
        $find = $pdo->prepare("
            SELECT id, reference_dossier, type_demande, nom_complet, statut, created_at
            FROM demandes_etat_civil
            WHERE reference_dossier = :reference
            LIMIT 1
        ");
        $find->execute(['reference' => $reference]);
        $resultat = $find->fetch();

        if ($resultat !== false) {
            $findPieces = $pdo->prepare("
                SELECT nom_fichier, chemin_fichier
                FROM demandes_etat_civil_pieces
                WHERE demande_id = :demande_id
                ORDER BY id DESC
            ");
            $findPieces->execute(['demande_id' => (int) $resultat['id']]);
            $pieces = $findPieces->fetchAll();
        }
    }
}
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-20 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="digitalisation-etat-civil.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Faire une nouvelle demande
            </a>
            <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Suivi en ligne · 24/7
            </span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight mb-3">
                Suivi de <span class="maire-text-gradient">dossier État civil</span>
            </h1>
            <p class="text-lg text-mairie-100 leading-relaxed">Consultez l'état d'avancement de votre demande grâce à votre référence.</p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- FORMULAIRE -->
            <article class="tw-card p-7">
                <h2 class="text-xl font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">🔍</span>
                    Vérifier mon dossier
                </h2>
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[260px]">
                        <label for="ref" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Référence dossier</label>
                        <input id="ref" name="ref" type="text" value="<?php echo htmlspecialchars($reference); ?>" placeholder="EC-YYYYMMDD-XXXXXX" required class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition font-mono">
                    </div>
                    <button class="tw-btn-primary" type="submit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Vérifier le statut
                    </button>
                </form>
            </article>

            <!-- RÉSULTAT -->
            <?php if ($erreur !== null): ?>
                <div class="bg-red-50 dark:bg-red-950/30 border-2 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 rounded-2xl p-5 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0">⚠️</span>
                    <p class="font-bold"><?php echo htmlspecialchars($erreur); ?></p>
                </div>
            <?php elseif ($reference === ''): ?>
                <div class="tw-card p-10 text-center">
                    <div class="text-5xl mb-3 opacity-40">📂</div>
                    <p class="text-slate-600 dark:text-slate-400">Saisissez votre référence pour vérifier votre dossier.</p>
                </div>
            <?php elseif ($resultat === false || $resultat === null): ?>
                <div class="bg-red-50 dark:bg-red-950/30 border-2 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 rounded-2xl p-5 flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0">❌</span>
                    <p class="font-bold">Aucun dossier trouvé avec cette référence.</p>
                </div>
            <?php else:
                $statut = (string) $resultat['statut'];
                $badgeColors = [
                    'recu' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                    'en_cours' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                    'valide' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                    'pret' => 'bg-emerald-500 text-white',
                    'rejete' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                ];
                $badgeColor = $badgeColors[$statut] ?? 'bg-slate-200 text-slate-700';
            ?>
                <article class="tw-card overflow-hidden">
                    <div class="p-7 md:p-10">
                        <div class="flex items-start justify-between gap-3 mb-5 flex-wrap">
                            <div>
                                <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white mb-1">Dossier <span class="maire-text-gradient"><?php echo htmlspecialchars((string) $resultat['reference_dossier']); ?></span></h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Déposé le <?php echo htmlspecialchars((string) date('d/m/Y à H:i', strtotime((string) $resultat['created_at']))); ?></p>
                            </div>
                            <span class="<?php echo $badgeColor; ?> inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-black uppercase tracking-wider"><?php echo htmlspecialchars($statut); ?></span>
                        </div>
                        <dl class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Type de demande</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $resultat['type_demande']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Demandeur</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $resultat['nom_complet']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Pièces jointes</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><strong><?php echo count($pieces); ?></strong> fichier<?php echo count($pieces) > 1 ? 's' : ''; ?></dd>
                            </div>
                        </dl>
                    </div>
                </article>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
