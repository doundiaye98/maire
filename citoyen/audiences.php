<?php
declare(strict_types=1);

require __DIR__ . '/../includes/citoyen-guard.php';
require_once __DIR__ . '/../includes/audiences-maire.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if ($pdo !== null && !maire_feature_disponible($pdo, 'audiences_maire')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('audiences_maire', $palierCommune, 'public');
    exit;
}

$citoyenId = (int) ($_SESSION['citoyen_id'] ?? 0);
$audiences = $pdo !== null ? maire_liste_audiences_citoyen($pdo, $citoyenId) : [];

$pageTitle = 'Mes audiences avec le Maire | Espace citoyen';
$pageDescription = 'Suivez vos demandes d’audience avec le Maire de Rufisque-Est.';
require __DIR__ . '/../includes/header.php';
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-20 maire-grain">
        <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="profil.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6">← Mon profil</a>
            <h1 class="text-4xl md:text-5xl font-black mb-3">Mes <span class="maire-text-gradient">audiences</span></h1>
            <p class="text-mairie-100">Historique de vos demandes auprès du Maire.</p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap gap-3">
                <a class="tw-btn-primary text-sm" href="../audiences-maire.php">Nouvelle demande</a>
            </div>

            <?php if ($audiences === []): ?>
                <article class="tw-card p-8 text-center">
                    <p class="text-slate-600 dark:text-slate-400 mb-4">Vous n’avez pas encore de demande d’audience.</p>
                    <a class="tw-btn-primary" href="../audiences-maire.php">Demander une audience</a>
                </article>
            <?php else: ?>
                <?php foreach ($audiences as $a):
                    $statut = (string) ($a['statut'] ?? 'en_attente');
                    ?>
                <article class="tw-card p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Réf. #<?php echo (int) $a['id']; ?></p>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) $a['objet'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <span class="std-feed-badge <?php echo maire_classe_badge_audience_statut($statut); ?>"><?php echo htmlspecialchars(maire_libelle_audience_statut($statut), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <dl class="grid sm:grid-cols-2 gap-2 text-sm text-slate-600 dark:text-slate-400">
                        <div><dt class="font-bold text-slate-800 dark:text-slate-200">Motif</dt><dd><?php echo htmlspecialchars(maire_libelle_audience_motif((string) $a['motif']), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div><dt class="font-bold text-slate-800 dark:text-slate-200">Mode</dt><dd><?php echo htmlspecialchars(maire_libelle_audience_mode((string) $a['mode_audience']), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div><dt class="font-bold text-slate-800 dark:text-slate-200">Déposée le</dt><dd><?php echo htmlspecialchars((string) $a['created_at'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <?php if (!empty($a['date_audience'])): ?>
                        <div><dt class="font-bold text-slate-800 dark:text-slate-200">Date confirmée</dt><dd><?php echo htmlspecialchars((string) $a['date_audience'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($statut === 'confirmee' && (string) ($a['mode_audience'] ?? '') === 'visio' && !empty($a['lien_visio'])): ?>
                        <p class="mt-3"><a class="tw-btn-primary text-sm" href="<?php echo htmlspecialchars((string) $a['lien_visio'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Rejoindre la visioconférence</a></p>
                    <?php endif; ?>
                    <?php if (!empty($a['admin_notes'])): ?>
                        <p class="mt-3 text-sm p-3 rounded-xl bg-mairie-50 dark:bg-mairie-900/30 border border-mairie-200/50 dark:border-mairie-800/50"><strong>Mairie :</strong> <?php echo nl2br(htmlspecialchars((string) $a['admin_notes'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
