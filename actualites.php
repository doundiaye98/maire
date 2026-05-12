<?php
declare(strict_types=1);

$pageTitle = 'Actualités de la mairie | Rufisque-Est';
$pageDescription = 'Consultez les dernières actualités de la Mairie de Rufisque-Est : annonces officielles, état civil, salubrité, énergie et projets municipaux.';
require_once __DIR__ . '/includes/site-data.php';
require __DIR__ . '/includes/header.php';

$actualites = getActualitesCatalogue();

$search = trim((string) ($_GET['q'] ?? ''));
$selectedCategory = trim((string) ($_GET['categorie'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 7;

$categories = array_values(array_unique(array_map(static fn(array $item): string => $item['categorie'], $actualites)));
sort($categories);

$filtered = array_values(array_filter(
    $actualites,
    static function (array $item) use ($search, $selectedCategory): bool {
        $matchCategory = $selectedCategory === '' || strcasecmp($item['categorie'], $selectedCategory) === 0;
        if (!$matchCategory) return false;
        if ($search === '') return true;
        $haystack = mb_strtolower($item['titre'] . ' ' . $item['resume'] . ' ' . $item['categorie']);
        return str_contains($haystack, mb_strtolower($search));
    }
));

$totalResults = count($filtered);
$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedActualites = array_slice($filtered, $offset, $perPage);
$actualitePrincipale = $pagedActualites[0] ?? null;
$autresActualites = array_slice($pagedActualites, 1);

function actualitesQueryString(array $overrides = []): string
{
    $params = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'categorie' => trim((string) ($_GET['categorie'] ?? '')),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    if ($params['q'] === '') unset($params['q']);
    if ($params['categorie'] === '') unset($params['categorie']);
    if ((int) $params['page'] <= 1) unset($params['page']);
    $query = http_build_query($params);
    return $query === '' ? 'actualites.php' : 'actualites.php?' . $query;
}
?>
<main class="overflow-hidden">
    <!-- HERO -->
    <section class="relative maire-hero-bg text-white py-24 maire-grain">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-32 w-[35rem] h-[35rem] bg-mairie-400/30 maire-blob blur-3xl pointer-events-none" style="animation-delay: -10s;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <span class="maire-tag bg-white/10 backdrop-blur-sm border border-white/20 text-gold-300 mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                    Information municipale
                </span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[0.95] tracking-tight mb-5">
                    Actualités<br><span class="maire-text-gradient">en direct</span>
                </h1>
                <p class="text-xl text-mairie-100 leading-relaxed max-w-2xl">
                    Retrouvez les annonces officielles et les principales informations de la mairie.
                </p>
            </div>
        </div>
    </section>

    <!-- FILTRES -->
    <section class="py-10 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form class="tw-card p-5 grid md:grid-cols-[1fr_240px_auto] gap-3 items-end" method="get" action="actualites.php">
                <div>
                    <label for="q" class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Recherche</label>
                    <input id="q" name="q" type="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ex : salubrité, état civil, énergie"
                           class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                </div>
                <div>
                    <label for="categorie" class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Catégorie</label>
                    <select id="categorie" name="categorie"
                            class="w-full px-3 py-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-mairie-500 focus:ring-2 focus:ring-mairie-200 dark:focus:ring-mairie-900 outline-none transition">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo strcasecmp($selectedCategory, $category) === 0 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="tw-btn-primary" type="submit">Filtrer</button>
                    <a class="tw-btn-outline" href="actualites.php">Reset</a>
                </div>
            </form>

            <p class="text-sm text-slate-600 dark:text-slate-400 mt-4 px-1">
                <strong class="text-slate-900 dark:text-white"><?php echo $totalResults; ?></strong> actualité<?php echo $totalResults > 1 ? 's' : ''; ?> trouvée<?php echo $totalResults > 1 ? 's' : ''; ?>
            </p>
        </div>
    </section>

    <!-- À LA UNE + GRILLE -->
    <section class="pb-20 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <?php if ($actualitePrincipale !== null): ?>
                <a href="actualite.php?id=<?php echo urlencode((string) $actualitePrincipale['id']); ?>"
                   class="maire-bento-card relative grid md:grid-cols-[1.2fr_1fr] gap-0 rounded-3xl overflow-hidden bg-white dark:bg-slate-800 shadow-xl mb-10 group">
                    <div class="relative aspect-[16/10] md:aspect-auto overflow-hidden">
                        <img loading="lazy" src="<?php echo htmlspecialchars($actualitePrincipale['image']); ?>" alt="<?php echo htmlspecialchars($actualitePrincipale['titre']); ?>"
                             class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                        <span class="absolute top-4 left-4 maire-tag bg-gold-400 text-mairie-950">⭐ À la une</span>
                    </div>
                    <div class="p-8 md:p-10 flex flex-col justify-center">
                        <span class="maire-tag bg-mairie-100 text-mairie-800 dark:bg-mairie-900/40 dark:text-mairie-200 mb-3 self-start"><?php echo htmlspecialchars($actualitePrincipale['categorie']); ?></span>
                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white mb-3 leading-tight group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition-colors">
                            <?php echo htmlspecialchars($actualitePrincipale['titre']); ?>
                        </h2>
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed mb-4 line-clamp-3"><?php echo htmlspecialchars($actualitePrincipale['resume']); ?></p>
                        <div class="flex items-center justify-between gap-4 mt-auto">
                            <small class="text-sm text-slate-500 dark:text-slate-400 font-bold">📅 <?php echo htmlspecialchars((string) date('d/m/Y', strtotime((string) $actualitePrincipale['date_publication']))); ?></small>
                            <span class="inline-flex items-center gap-1 text-mairie-700 dark:text-mairie-300 font-black group-hover:gap-2 transition-all">
                                Lire la suite
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (!empty($autresActualites)): ?>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($autresActualites as $item): ?>
                        <a href="actualite.php?id=<?php echo urlencode((string) $item['id']); ?>"
                           class="tw-card group overflow-hidden flex flex-col">
                            <div class="relative aspect-[16/10] overflow-hidden">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy" src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['titre']); ?>">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <span class="absolute top-3 left-3 maire-tag bg-white/90 backdrop-blur-md text-mairie-800"><?php echo htmlspecialchars($item['categorie']); ?></span>
                            </div>
                            <div class="p-5 flex flex-col flex-1">
                                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2 leading-tight group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition-colors line-clamp-2"><?php echo htmlspecialchars($item['titre']); ?></h3>
                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3 line-clamp-2"><?php echo htmlspecialchars($item['resume']); ?></p>
                                <div class="flex items-center justify-between mt-auto pt-3 border-t border-slate-100 dark:border-slate-700">
                                    <small class="text-xs text-slate-500 dark:text-slate-400 font-bold">📅 <?php echo htmlspecialchars((string) date('d/m/Y', strtotime((string) $item['date_publication']))); ?></small>
                                    <span class="text-xs font-black text-mairie-700 dark:text-mairie-300">Lire →</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalResults === 0): ?>
                <div class="tw-card p-12 text-center">
                    <div class="text-6xl mb-4 opacity-40">📭</div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Aucun résultat</h2>
                    <p class="text-slate-600 dark:text-slate-400 mb-4">Aucune actualité ne correspond à vos critères. Essayez une autre recherche ou réinitialisez les filtres.</p>
                    <a class="tw-btn-primary" href="actualites.php">Tout afficher</a>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <nav class="flex items-center justify-center gap-4 mt-10" aria-label="Pagination des actualités">
                    <a class="<?php echo $page <= 1 ? 'opacity-40 pointer-events-none' : ''; ?> tw-btn-outline text-sm" href="<?php echo htmlspecialchars(actualitesQueryString(['page' => max(1, $page - 1)])); ?>" <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>← Précédent</a>
                    <span class="text-sm text-slate-600 dark:text-slate-400 font-bold">Page <span class="text-slate-900 dark:text-white"><?php echo $page; ?></span> / <?php echo $totalPages; ?></span>
                    <a class="<?php echo $page >= $totalPages ? 'opacity-40 pointer-events-none' : ''; ?> tw-btn-outline text-sm" href="<?php echo htmlspecialchars(actualitesQueryString(['page' => min($totalPages, $page + 1)])); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Suivant →</a>
                </nav>
            <?php endif; ?>

            <div class="mt-12 flex flex-wrap gap-3 justify-center">
                <a class="tw-btn-primary" href="projets.php">Voir les projets</a>
                <a class="tw-btn-outline" href="contact.php">Contacter la mairie</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
