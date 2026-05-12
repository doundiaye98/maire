<?php
declare(strict_types=1);

/**
 * Liste publique des consultations citoyennes.
 */
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/consultations.php';

$consultations = $pdo !== null ? maire_liste_consultations_publiques($pdo, 100) : [];

$ouvertes = array_filter($consultations, fn($c) => ($c['statut'] ?? '') === 'ouverte');
$fermees  = array_filter($consultations, fn($c) => ($c['statut'] ?? '') === 'fermee');

$totalVotes = 0;
foreach ($consultations as $c) { $totalVotes += (int) ($c['nb_votes_total'] ?? 0); }

$pageTitle = 'Consultations citoyennes';
$pageDescription = "Participez aux votes et sondages organisés par la mairie : consultations citoyennes, choix d'aménagements, sondages d'opinion.";
require __DIR__ . '/includes/header.php';
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-fuchsia-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-gold-400/25 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-wrap items-end justify-between gap-8">
                <div class="max-w-2xl">
                    <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                        Démocratie participative
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                        Consultations<br><span class="maire-text-gradient">&amp; votes citoyens</span>
                    </h1>
                    <p class="text-lg text-mairie-100 leading-relaxed">
                        Donnez votre avis sur les décisions municipales : sondages, consultations citoyennes, votes d'aménagement.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 min-w-[340px]">
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo count($ouvertes); ?>">0</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Ouvertes</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo count($fermees); ?>">0</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Clôturées</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20">
                        <p class="text-3xl font-black"><span class="maire-counter" data-target="<?php echo $totalVotes; ?>">0</span></p>
                        <p class="text-[10px] text-mairie-200 uppercase tracking-wider font-bold mt-1">Votes exprimés</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONSULTATIONS OUVERTES -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-2xl shadow-md">🔓</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Consultations en cours</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo count($ouvertes); ?> ouverte<?php echo count($ouvertes) > 1 ? 's' : ''; ?> au vote</p>
                    </div>
                </div>
                <span class="hidden sm:inline-flex maire-tag bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Votez maintenant
                </span>
            </div>

            <?php if (empty($ouvertes)): ?>
                <div class="tw-card p-12 text-center">
                    <div class="text-6xl mb-4 opacity-40">🗳️</div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Aucune consultation ouverte</h3>
                    <p class="text-slate-600 dark:text-slate-400">La mairie lance régulièrement de nouvelles consultations. Revenez bientôt !</p>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 gap-5">
                    <?php foreach ($ouvertes as $c):
                        $typeLabel = maire_libelle_type_consultation((string) $c['type']);
                    ?>
                        <article class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/30 border-2 border-emerald-200 dark:border-emerald-800 p-6 hover:shadow-xl transition-all maire-bento-card">
                            <div class="absolute -top-12 -right-12 w-40 h-40 bg-emerald-300/40 rounded-full blur-2xl pointer-events-none" aria-hidden="true"></div>
                            <div class="relative">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <span class="maire-tag bg-emerald-500 text-white">Ouverte</span>
                                    <span class="text-xs text-emerald-700 dark:text-emerald-300 font-bold">⏱ <?php echo htmlspecialchars((string) $c['date_fin'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">
                                    <?php echo htmlspecialchars((string) $c['titre'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <p class="text-sm text-slate-700 dark:text-slate-300 mb-4 line-clamp-3"><?php echo htmlspecialchars((string) $c['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-400 mb-4">
                                    <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>· <?php echo (int) $c['nb_options']; ?> options</span>
                                    <span>· <strong><?php echo (int) $c['nb_votes_total']; ?></strong> votants</span>
                                </div>
                                <a class="tw-btn-primary text-sm w-full justify-center" href="consultation.php?id=<?php echo (int) $c['id']; ?>">
                                    Participer au vote
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- CONSULTATIONS CLÔTURÉES -->
            <div class="mt-16">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center text-2xl shadow-md">🔒</span>
                    <div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white">Consultations clôturées</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo count($fermees); ?> archive<?php echo count($fermees) > 1 ? 's' : ''; ?></p>
                    </div>
                </div>

                <?php if (empty($fermees)): ?>
                    <div class="tw-card p-8 text-center">
                        <p class="text-slate-500 dark:text-slate-400">Aucune consultation passée à afficher.</p>
                    </div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach (array_slice($fermees, 0, 24) as $c): ?>
                        <article class="tw-card p-5 group">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="maire-tag bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300">Fermée</span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">📅 <?php echo htmlspecialchars((string) $c['date_fin'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h3 class="font-black text-slate-900 dark:text-white mb-1 line-clamp-2 group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition-colors">
                                <?php echo htmlspecialchars((string) $c['titre'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3"><?php echo htmlspecialchars(maire_libelle_type_consultation((string) $c['type']), ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $c['nb_votes_total']; ?> participants</p>
                            <?php if ((int) ($c['resultats_publics'] ?? 0) === 1): ?>
                                <a class="text-sm font-bold text-mairie-700 dark:text-mairie-300 hover:underline inline-flex items-center gap-1" href="consultation.php?id=<?php echo (int) $c['id']; ?>">
                                    Voir les résultats
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">Résultats non publics</span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
