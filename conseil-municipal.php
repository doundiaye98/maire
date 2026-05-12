<?php
declare(strict_types=1);

/**
 * Page publique — Sessions du conseil municipal (annonces + live + replays).
 */
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/conseil-sessions.php';

$sessions = $pdo !== null ? maire_liste_sessions_conseil_publiques($pdo, 50) : [];
$idDetail = (int) ($_GET['id'] ?? 0);
$session = $idDetail > 0 ? maire_load_session_conseil($pdo, $idDetail) : null;
if ($session !== null) {
    maire_incrementer_vues_conseil($pdo, $idDetail);
}

$pageTitle = $session !== null ? 'Conseil municipal — ' . (string) $session['titre'] : 'Conseil municipal — Sessions en direct';
$pageDescription = "Suivez les conseils municipaux en direct ou en replay et consultez l'ordre du jour et les procès-verbaux.";
require __DIR__ . '/includes/header.php';

$statutClasses = [
    'en_direct' => 'bg-red-500 text-white animate-pulse',
    'replay' => 'bg-blue-500 text-white',
    'annonce' => 'bg-amber-500 text-white',
    'annule' => 'bg-slate-500 text-white',
    'termine' => 'bg-emerald-500 text-white',
];
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-red-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-gold-400/25 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                    Démocratie locale · Transparence
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Conseil<br><span class="maire-text-gradient">municipal</span>
                </h1>
                <p class="text-xl text-mairie-100 leading-relaxed max-w-2xl">
                    Assistez en direct aux délibérations ou revoyez les sessions passées. Procès-verbaux et ordres du jour à disposition.
                </p>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            <?php if ($session !== null):
                $st = (string) $session['statut'];
                $stClass = $statutClasses[$st] ?? 'bg-slate-500 text-white';
            ?>
                <article class="tw-card overflow-hidden">
                    <div class="p-7 md:p-10">
                        <a href="conseil-municipal.php" class="inline-flex items-center gap-2 text-mairie-700 dark:text-mairie-300 hover:text-mairie-900 text-sm font-bold mb-5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Toutes les sessions
                        </a>
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) $session['titre'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <span class="<?php echo $stClass; ?> inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider">
                                <?php echo htmlspecialchars(maire_conseil_libelle_statut($st), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-3 text-sm text-slate-600 dark:text-slate-400 mb-5">
                            <span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> <?php echo htmlspecialchars((string) $session['date_session'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>· <?php echo (int) $session['duree_minutes']; ?> min</span>
                            <span>· <?php echo htmlspecialchars(maire_conseil_libelle_plateforme((string) $session['plateforme']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <?php if (!empty($session['description'])): ?>
                            <p class="text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed mb-6"><?php echo htmlspecialchars((string) $session['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($session['embed_url']) && in_array($st, ['en_direct', 'replay'], true)): ?>
                        <div class="relative aspect-video bg-black">
                            <iframe
                                src="<?php echo htmlspecialchars((string) $session['embed_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="absolute inset-0 w-full h-full"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                                sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
                                loading="lazy"
                                title="Diffusion conseil municipal"></iframe>
                            <?php if ($st === 'en_direct'): ?>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-600 text-white text-xs font-black uppercase tracking-wider shadow-lg">
                                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span> EN DIRECT
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($st === 'annonce'): ?>
                        <div class="mx-7 md:mx-10 mb-7 p-5 bg-amber-50 dark:bg-amber-950/30 border-2 border-amber-400 rounded-2xl flex items-start gap-3">
                            <span class="text-2xl flex-shrink-0">📅</span>
                            <p class="text-amber-900 dark:text-amber-200 font-bold">Cette session sera diffusée à la date prévue. Revenez à l'heure d'ouverture pour la suivre en direct.</p>
                        </div>
                    <?php elseif ($st === 'annule'): ?>
                        <div class="mx-7 md:mx-10 mb-7 p-5 bg-red-50 dark:bg-red-950/30 border-2 border-red-500 rounded-2xl flex items-start gap-3">
                            <span class="text-2xl flex-shrink-0">❌</span>
                            <p class="text-red-900 dark:text-red-200 font-bold">Cette session a été annulée.</p>
                        </div>
                    <?php endif; ?>

                    <div class="p-7 md:p-10 pt-7 space-y-6">
                        <?php if (!empty($session['ordre_du_jour'])): ?>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center">📋</span>
                                    Ordre du jour
                                </h3>
                                <div class="p-5 bg-slate-50 dark:bg-slate-800 rounded-2xl text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars((string) $session['ordre_du_jour'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($session['proces_verbal_url'])): ?>
                            <div class="flex justify-start">
                                <a class="tw-btn-primary" href="<?php echo htmlspecialchars((string) $session['proces_verbal_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Procès-verbal
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php elseif (empty($sessions)): ?>
                <div class="tw-card p-12 text-center">
                    <div class="text-6xl mb-4 opacity-40">🎬</div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucune session pour le moment</h2>
                    <p class="text-slate-600 dark:text-slate-400">Les prochaines sessions du conseil municipal seront annoncées ici.</p>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-2xl shadow-md">🎬</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Sessions du conseil</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo count($sessions); ?> session<?php echo count($sessions) > 1 ? 's' : ''; ?></p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-5">
                    <?php foreach ($sessions as $s):
                        $st = (string) $s['statut'];
                        $stClass = $statutClasses[$st] ?? 'bg-slate-500 text-white';
                        $isLive = $st === 'en_direct';
                    ?>
                        <article class="maire-bento-card relative rounded-3xl overflow-hidden border-2 <?php echo $isLive ? 'border-red-500 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-950/30 dark:to-rose-950/30' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'; ?> p-6 group">
                            <?php if ($isLive): ?>
                                <div class="absolute -top-12 -right-12 w-40 h-40 bg-red-400/30 rounded-full blur-2xl animate-pulse pointer-events-none"></div>
                            <?php endif; ?>
                            <div class="relative">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <span class="<?php echo $stClass; ?> inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
                                        <?php if ($isLive): ?><span class="w-1.5 h-1.5 rounded-full bg-white"></span><?php endif; ?>
                                        <?php echo htmlspecialchars(maire_conseil_libelle_statut($st), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                        👁 <?php echo (int) $s['nb_vues']; ?>
                                    </span>
                                </div>
                                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2 leading-tight"><?php echo htmlspecialchars((string) $s['titre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1">📅 <?php echo htmlspecialchars((string) $s['date_session'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>· ⏱ <?php echo (int) $s['duree_minutes']; ?> min</span>
                                </p>
                                <a class="<?php echo $isLive ? 'bg-red-600 hover:bg-red-700 text-white' : 'tw-btn-primary'; ?> inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-black text-sm transition-colors w-full" href="conseil-municipal.php?id=<?php echo (int) $s['id']; ?>">
                                    <?php if ($isLive): ?>
                                        ▶ Regarder en direct
                                    <?php elseif ($st === 'replay'): ?>
                                        ⏯ Voir le replay
                                    <?php else: ?>
                                        Voir les détails
                                    <?php endif; ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
