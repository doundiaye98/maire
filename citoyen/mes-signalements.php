<?php
declare(strict_types=1);

/**
 * Liste personnelle des signalements du citoyen connecté, avec statut et photo.
 */
require __DIR__ . '/../includes/citoyen-guard.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/site-paths.php';

$idCit = maire_citoyen_current_id() ?? 0;
$signalements = $pdo !== null ? maire_liste_signalements_citoyen($pdo, $idCit, 200) : [];
$urlPrefix = maire_url_prefix();

$pageTitle = 'Espace citoyen · Mes signalements';
require __DIR__ . '/../includes/header.php';

$statutBadges = [
    'nouveau'   => 'bg-blue-500 text-white',
    'en_cours'  => 'bg-amber-500 text-white',
    'resolu'    => 'bg-emerald-500 text-white',
    'rejete'    => 'bg-red-500 text-white',
];
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-20 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="profil.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Mon profil
            </a>
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-4">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Espace citoyen · Signalements
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight mb-3">
                        Mes <span class="maire-text-gradient">signalements</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed max-w-2xl">
                        Suivi du traitement de tous vos signalements à la mairie de Rufisque-Est.
                    </p>
                </div>
                <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-gold-400 hover:bg-gold-300 text-mairie-950 font-black transition-colors shadow-glow" href="signaler.php">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nouveau signalement
                </a>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            <?php if (empty($signalements)): ?>
                <div class="tw-card p-12 text-center">
                    <div class="text-7xl mb-4 opacity-40">📭</div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucun signalement</h2>
                    <p class="text-slate-600 dark:text-slate-400 mb-6">Vous n'avez pas encore fait de signalement.</p>
                    <a class="tw-btn-primary" href="signaler.php">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Créer mon premier signalement
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($signalements as $s):
                    $statut = (string) ($s['statut'] ?? 'nouveau');
                    $cat = (string) ($s['categorie'] ?? 'autre');
                    $photoUrl = maire_url_photo_signalement((string) ($s['photo_path'] ?? ''), $urlPrefix);
                    $badgeClass = $statutBadges[$statut] ?? 'bg-slate-500 text-white';
                ?>
                    <article class="tw-card overflow-hidden">
                        <div class="grid <?php echo $photoUrl ? 'sm:grid-cols-[160px_1fr]' : 'grid-cols-1'; ?> gap-0">
                            <?php if ($photoUrl): ?>
                                <a href="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="relative aspect-square sm:aspect-auto overflow-hidden group">
                                    <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                        <span class="opacity-0 group-hover:opacity-100 transition-opacity text-white text-3xl">🔍</span>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <div class="p-5 md:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($s['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            <span class="font-mono">#<?php echo (int) ($s['id'] ?? 0); ?></span>
                                            · <?php echo htmlspecialchars(maire_libelle_categorie_signalement($cat), ENT_QUOTES, 'UTF-8'); ?>
                                            · 📅 <?php echo htmlspecialchars((string) ($s['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    </div>
                                    <span class="<?php echo $badgeClass; ?> inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
                                        <?php echo htmlspecialchars(maire_libelle_statut_signalement($statut), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line mb-3"><?php echo htmlspecialchars((string) ($s['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                                <?php if (!empty($s['adresse_libre']) || (!empty($s['latitude']) && !empty($s['longitude']))): ?>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 flex flex-wrap items-center gap-3">
                                        <?php if (!empty($s['adresse_libre'])): ?>
                                            <span>📍 <?php echo htmlspecialchars((string) $s['adresse_libre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($s['latitude']) && !empty($s['longitude'])): ?>
                                            <?php $url = 'https://www.openstreetmap.org/?mlat=' . urlencode((string) $s['latitude']) . '&mlon=' . urlencode((string) $s['longitude']) . '#map=18/' . urlencode((string) $s['latitude']) . '/' . urlencode((string) $s['longitude']); ?>
                                            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-mairie-700 dark:text-mairie-300 hover:underline">🗺️ Voir sur carte</a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($s['admin_notes'])): ?>
                                    <div class="mt-3 p-4 bg-gradient-to-br from-mairie-50 to-emerald-50 dark:from-mairie-950/30 dark:to-emerald-950/30 border-l-4 border-mairie-700 rounded-r-xl">
                                        <p class="text-xs font-black uppercase tracking-wider text-mairie-700 dark:text-mairie-300 mb-1.5 flex items-center gap-1.5">🏛️ Réponse de la mairie</p>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line"><?php echo htmlspecialchars((string) $s['admin_notes'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if (!empty($s['traite_le'])): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Mis à jour le <?php echo htmlspecialchars((string) $s['traite_le'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
