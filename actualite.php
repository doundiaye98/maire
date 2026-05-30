<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site-data.php';
require_once __DIR__ . '/includes/site-paths.php';

$actualites = getActualitesCatalogue();
$actualiteId = (int) ($_GET['id'] ?? 0);

$actualite = null;
foreach ($actualites as $item) {
    if ((int) $item['id'] === $actualiteId) {
        $actualite = $item;
        break;
    }
}

if ($actualite === null) {
    $pageTitle = 'Actualité introuvable | Rufisque-Est';
    $pageDescription = "L'actualité demandée est introuvable.";
    $pageType = 'website';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="overflow-hidden">
        <section class="relative maire-hero-bg text-white py-24 maire-grain overflow-hidden">
            <div class="container mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <div class="text-7xl mb-4 opacity-80">📰</div>
                <h1 class="text-4xl md:text-5xl font-black mb-3">Actualité introuvable</h1>
                <p class="text-mairie-100 mb-6">L'actualité demandée n'existe pas ou a été retirée.</p>
                <div class="flex flex-wrap gap-3 justify-center">
                    <a class="tw-btn-primary" href="actualites.php">Voir toutes les actualités</a>
                    <a class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white font-black transition-colors" href="index.php">Retour à l'accueil</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $actualite['titre'] . ' | Actualites Rufisque-Est';
$pageDescription = $actualite['resume'];
$pageImage = $actualite['image'];
$pageType = 'article';
$publishedDateIso = date('c', strtotime((string) $actualite['date_publication']));
$logoUrl = maire_logo_url_absolue();
$pageJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $actualite['titre'],
    'description' => $actualite['resume'],
    'image' => [$actualite['image']],
    'datePublished' => $publishedDateIso,
    'dateModified' => $publishedDateIso,
    'author' => [
        '@type' => 'Organization',
        'name' => 'Mairie de Rufisque-Est',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Mairie de Rufisque-Est',
        'logo' => [
            '@type' => 'ImageObject',
            'url' => $logoUrl,
        ],
    ],
];
require __DIR__ . '/includes/header.php';

$autresActualites = array_values(array_filter(
    $actualites,
    static fn(array $item): bool => (int) $item['id'] !== (int) $actualite['id']
));
$suggestions = array_slice($autresActualites, 0, 3);
?>
<main class="overflow-hidden">
    <section class="relative maire-hero-bg text-white py-20 lg:py-24 maire-grain overflow-hidden">
        <div class="absolute -top-32 -right-32 w-[35rem] h-[35rem] bg-gold-500/25 maire-blob blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 44px 44px;" aria-hidden="true"></div>

        <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 relative z-10">
            <a href="actualites.php" class="inline-flex items-center gap-2 text-mairie-200 hover:text-white text-sm font-bold mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Toutes les actualités
            </a>
            <span class="maire-section-kicker mb-4 !bg-white/12 !text-white !border-white/20">
                <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
                Actualité — <?php echo htmlspecialchars($actualite['categorie']); ?>
            </span>
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-black leading-tight tracking-tight mb-4">
                <?php echo htmlspecialchars($actualite['titre']); ?>
            </h1>
            <p class="text-sm text-mairie-200 font-bold">📅 Publication du <?php echo htmlspecialchars((string) date('d/m/Y', strtotime((string) $actualite['date_publication']))); ?></p>
        </div>
    </section>

    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <article class="maire-editorial-card overflow-hidden !p-0">
                <div class="relative aspect-[16/9] overflow-hidden <?php echo htmlspecialchars(maire_actualite_image_media_bg($actualite), ENT_QUOTES, 'UTF-8'); ?>">
                    <img class="<?php echo htmlspecialchars(maire_actualite_image_classes($actualite), ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" src="<?php echo htmlspecialchars($actualite['image']); ?>" alt="<?php echo htmlspecialchars($actualite['titre']); ?>">
                </div>
                <div class="p-8 md:p-10">
                    <div class="grid md:grid-cols-[1fr_auto] gap-4 items-start mb-8">
                        <p class="text-xl md:text-2xl text-slate-800 dark:text-slate-100 leading-relaxed font-bold maire-text-gradient">
                            <?php echo htmlspecialchars($actualite['resume']); ?>
                        </p>
                        <div class="maire-panel !p-4 min-w-[13rem]">
                            <p class="text-[0.72rem] uppercase tracking-[0.22em] text-slate-500 font-black mb-2">Repère</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Article publié par la Mairie de Rufisque-Est dans la rubrique <?php echo htmlspecialchars($actualite['categorie']); ?>.</p>
                        </div>
                    </div>
                    <div class="maire-glow-line mb-8"></div>
                    <div class="prose prose-lg max-w-none text-slate-700 dark:text-slate-300 leading-relaxed">
                        <p><?php echo nl2br(htmlspecialchars($actualite['contenu'])); ?></p>
                    </div>
                </div>
            </article>

            <?php if (!empty($suggestions)): ?>
                <div class="mt-14">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-mairie-700 to-mairie-900 text-white flex items-center justify-center text-xl">📰</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">À lire aussi</h2>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($suggestions as $item): ?>
                            <a href="actualite.php?id=<?php echo urlencode((string) $item['id']); ?>" class="maire-editorial-card group overflow-hidden flex flex-col !p-0">
                                <div class="relative aspect-[16/10] overflow-hidden <?php echo htmlspecialchars(maire_actualite_image_media_bg($item), ENT_QUOTES, 'UTF-8'); ?>">
                                    <img class="<?php echo htmlspecialchars(maire_actualite_image_classes($item), ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['titre']); ?>">
                                    <span class="absolute top-3 left-3 maire-tag bg-white/90 backdrop-blur-md text-mairie-800"><?php echo htmlspecialchars($item['categorie']); ?></span>
                                </div>
                                <div class="p-5">
                                    <h3 class="text-base font-black text-slate-900 dark:text-white mb-2 line-clamp-2 group-hover:text-mairie-700 dark:group-hover:text-mairie-300 transition-colors"><?php echo htmlspecialchars($item['titre']); ?></h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2"><?php echo htmlspecialchars($item['resume']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-12 flex flex-wrap gap-3">
                <a class="tw-btn-primary" href="actualites.php">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Retour aux actualités
                </a>
                <a class="tw-btn-outline" href="contact.php">Contacter la mairie</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
