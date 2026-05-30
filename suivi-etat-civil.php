<?php
declare(strict_types=1);
$mairePortalMinPalier = 'standard';
require __DIR__ . '/includes/commune-portal-guard.php';
require_once __DIR__ . '/includes/etat-civil-demande.php';
require __DIR__ . '/includes/header.php';

$reference = maire_normaliser_reference_etat_civil((string) ($_GET['ref'] ?? ''));
$resultat = null;
$erreur = null;

if ($reference !== '') {
    if (!isset($pdo) || $pdo === null) {
        $erreur = 'Base indisponible.';
    } else {
        $resultat = maire_trouver_demande_etat_civil($pdo, $reference);
    }
}
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-20 lg:py-24 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-emerald-400/30 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="digitalisation-etat-civil.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Faire une nouvelle demande
            </a>
            <span class="maire-section-kicker mb-4 !bg-white/12 !text-white !border-white/20">
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
            <article class="maire-form-shell">
                <h2 class="text-xl font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">🔍</span>
                    Vérifier mon dossier
                </h2>
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[260px]">
                        <label for="ref" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1.5">Référence dossier</label>
                        <input id="ref" name="ref" type="text" value="<?php echo htmlspecialchars($reference); ?>" placeholder="EC-YYYYMMDD-XXXXXX" required class="tw-input font-mono">
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Les minuscules et espaces sont corrigés automatiquement.</p>
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
                <div class="maire-panel p-10 text-center">
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
                $badgeColor = maire_classe_badge_statut_etat_civil($statut);
                $etapes = ['recu', 'en_cours', 'valide', 'pret'];
                $idxActuel = array_search($statut, $etapes, true);
                if ($idxActuel === false && $statut === 'rejete') {
                    $idxActuel = -1;
                }
            ?>
                <article class="maire-editorial-card overflow-hidden !p-0">
                    <div class="p-7 md:p-10">
                        <div class="flex items-start justify-between gap-3 mb-5 flex-wrap">
                            <div>
                                <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white mb-1">Dossier <span class="maire-text-gradient"><?php echo htmlspecialchars((string) $resultat['reference_dossier']); ?></span></h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Déposé le <?php echo htmlspecialchars((string) date('d/m/Y à H:i', strtotime((string) $resultat['created_at']))); ?></p>
                            </div>
                            <span class="<?php echo $badgeColor; ?> inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-black"><?php echo htmlspecialchars(maire_libelle_statut_demande_etat_civil($statut), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($statut !== 'rejete'): ?>
                        <div class="flex gap-1 mb-6" aria-label="Progression">
                            <?php foreach ($etapes as $i => $code): ?>
                                <div class="flex-1 h-2 rounded-full <?php echo ($idxActuel !== false && $i <= $idxActuel) ? 'bg-mairie-600' : 'bg-slate-200 dark:bg-slate-700'; ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <dl class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Type de demande</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars(maire_libelle_type_demande_etat_civil((string) $resultat['type_demande']), ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Demandeur</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string) $resultat['nom_complet']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-3 py-3">
                                <dt class="text-sm text-slate-500 dark:text-slate-400 font-bold">Pièces jointes</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-slate-100"><strong><?php echo (int) ($resultat['pieces_count'] ?? 0); ?></strong> fichier<?php echo ((int) ($resultat['pieces_count'] ?? 0)) > 1 ? 's' : ''; ?></dd>
                            </div>
                        </dl>
                    </div>
                </article>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
