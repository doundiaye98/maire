<?php
declare(strict_types=1);
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

// ── 1) Chargement précoce des variables d'environnement (.env) ──
require_once __DIR__ . '/env-loader.php';
maire_env_bootstrap();

// ── 2) Configuration session AVANT session_start() (sinon flags ignorés) ──
require_once __DIR__ . '/session-performance.php';
maire_session_configure_ini();
if (session_status() !== PHP_SESSION_ACTIVE) {
    // cookie_secure : automatique si HTTPS détecté
    $maireIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    if ($maireIsHttps) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// ── 3) Headers HTTP de sécurité (CSP, X-Frame-Options, HSTS, etc.) ──
require_once __DIR__ . '/security-headers.php';
maire_security_headers_emit(isset($maireSecurityHeaderOptions) && is_array($maireSecurityHeaderOptions) ? $maireSecurityHeaderOptions : []);

require_once __DIR__ . '/super-admin-session.php';
require_once __DIR__ . '/site-paths.php';
require_once __DIR__ . '/citoyen-session.php';
require_once __DIR__ . '/feature-gates.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/image-fallback.php';
require_once __DIR__ . '/csrf.php';
maire_log_install_handlers();
$urlPrefix = maire_url_prefix();
$maireNavOffres = (($_SESSION['subscriber_role'] ?? '') === 'admin') || maire_super_admin_session_valid();
$maireCitoyenConnecte = maire_citoyen_session_valid();
$maireCitoyenPrenom = $maireCitoyenConnecte ? (string) ($_SESSION['citoyen_prenom'] ?? '') : '';
if (!isset($pdo) || $pdo === null) {
    @require_once __DIR__ . '/../config/database.php';
}
$maireComptesCitoyensActifs = isset($pdo) && $pdo !== null
    ? maire_feature_disponible($pdo, 'comptes_citoyens')
    : true;

$siteName = 'Mairie de Rufisque-Est';
$currentScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$canonicalUrl = $currentScheme . '://' . $host . $requestUri;
$metaTitle = (string) ($pageTitle ?? 'Mairie de Rufisque-Est - Service public local');
$metaDescription = (string) ($pageDescription ?? 'Site officiel de la Mairie de Rufisque-Est: actualites, services administratifs, projets municipaux et contact.');
$maireLogoHeaderUrl = maire_logo_header_url_absolue();
$maireLogoMobileUrl = maire_logo_mobile_url_absolue();
$maireShowSplash = !empty($maireShowSplash);
// Écran d'ouverture (accueil) : uniquement img/logo_mairie_rufisque_est.png
$maireSplashLogoPathAbs = __DIR__ . '/../' . MAIRE_LOGO_PATH;
$maireSplashLogoUrl = maire_root_url(MAIRE_LOGO_PATH);
if (is_file($maireSplashLogoPathAbs)) {
    $maireSplashLogoUrl .= (str_contains($maireSplashLogoUrl, '?') ? '&' : '?') . 'v=' . (int) filemtime($maireSplashLogoPathAbs);
}
$metaImage = (string) ($pageImage ?? maire_logo_url_absolue());
$metaType = (string) ($pageType ?? 'website');

$organizationJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'GovernmentOrganization',
    'name' => $siteName,
    'url' => $currentScheme . '://' . $host . '/',
    'logo' => maire_logo_url_absolue(),
    'email' => 'Rufisquest02@gmail.com',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Castor, en face de la pharmacie DIOR, Arafat II',
        'addressLocality' => 'Rufisque-Est',
        'addressRegion' => 'Rufisque',
        'addressCountry' => 'SN',
    ],
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => 14.7176516,
        'longitude' => -17.2550986,
    ],
    'openingHours' => 'Mo-Su 00:00-23:59',
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'contactType' => 'customer support',
        'email' => 'Rufisquest02@gmail.com',
        'availableLanguage' => ['French', 'Wolof'],
    ],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:locale" content="fr_SN">
    <meta property="og:type" content="<?php echo htmlspecialchars($metaType); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteName); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($metaImage); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($metaImage); ?>">
    <script type="application/ld+json"><?php echo json_encode($organizationJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php if (isset($pageJsonLd) && is_array($pageJsonLd)): ?>
    <script type="application/ld+json"><?php echo json_encode($pageJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
    <?php
    // ----------------------------------------------------------------------
    // Chargement du CSS Tailwind compilé localement (production-ready).
    // Fallback automatique vers le CDN si le build n'a pas été généré
    // (utile en dev tant que `npm run build` n'a pas été lancé).
    // Pour générer le build : `npm install && npm run build`.
    // ----------------------------------------------------------------------
    $tailwindBuiltAbs = __DIR__ . '/../assets/css/tailwind.css';
    $tailwindBuiltAvailable = is_file($tailwindBuiltAbs);
    if ($tailwindBuiltAvailable) {
        $tailwindCacheBust = (int) @filemtime($tailwindBuiltAbs);
        ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/tailwind.css?v=<?php echo $tailwindCacheBust; ?>">
        <?php
    } else {
        ?>
        <!-- ⚠ Tailwind CDN (DEV only) — exécutez `npm install && npm run build` pour passer en build local. -->
        <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
        <?php
    }
    ?>
    <?php if (!$tailwindBuiltAvailable): // ── Fallback uniquement quand le build local n'est pas disponible ── ?>
    <script>
        // Configuration Tailwind inline — utilisée UNIQUEMENT par le CDN dev.
        // En production (build local), cette config vit dans tailwind.config.js.
        tailwind.config = {
            darkMode: 'class',
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        mairie: {
                            50:  '#f0f9f5', 100: '#dcf0e6', 200: '#bbe1cd', 300: '#8eccac',
                            400: '#5db088', 500: '#3d9670', 600: '#2e7a5b', 700: '#1e5f48',
                            800: '#0c4a3e', 900: '#0a3c34', 950: '#03241e',
                        },
                        gold: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        serif: ['Lora', 'Georgia', 'serif'],
                    },
                    boxShadow: {
                        'glow': '0 10px 40px -10px rgba(12, 74, 62, 0.45)',
                        'card': '0 4px 20px -2px rgba(15, 23, 42, 0.08)',
                        'card-hover': '0 20px 40px -8px rgba(15, 23, 42, 0.18)',
                    },
                    backdropBlur: { xs: '2px' },
                    animation: {
                        'fade-up': 'fadeUp 0.6s ease-out',
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'shimmer': 'shimmer 2s linear infinite',
                    },
                    keyframes: {
                        fadeUp: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        shimmer: { '0%': { backgroundPosition: '-200% 0' }, '100%': { backgroundPosition: '200% 0' } },
                    },
                }
            }
        };
    </script>
    <?php endif; ?>
    <?php $maireStyleCssAbs = __DIR__ . '/../assets/css/style.css'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/style.css?v=<?php echo is_file($maireStyleCssAbs) ? (int) filemtime($maireStyleCssAbs) : time(); ?>">
    <?php if (!$tailwindBuiltAvailable): // ── Composants @apply/animations — embarqués dans tailwind.css quand le build est compilé ── ?>
    <style>
        /* Composants partagés Tailwind — réutilisables sur toutes les pages refondues */
        .tw-card {
            @apply bg-white dark:bg-slate-800 rounded-2xl shadow-card hover:shadow-card-hover transition-all duration-300 border border-slate-200/60 dark:border-slate-700/60;
        }
        .tw-btn-primary {
            @apply inline-flex items-center justify-center gap-2 bg-mairie-800 hover:bg-mairie-700 text-white font-semibold px-5 py-2.5 rounded-xl shadow-glow transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0;
        }
        .tw-btn-outline {
            @apply inline-flex items-center justify-center gap-2 bg-white dark:bg-slate-800 hover:bg-mairie-50 dark:hover:bg-slate-700 text-mairie-800 dark:text-mairie-200 font-semibold px-5 py-2.5 rounded-xl border-2 border-mairie-800 dark:border-mairie-400 transition-all duration-200;
        }
        .tw-btn-ghost {
            @apply inline-flex items-center justify-center gap-2 text-mairie-800 dark:text-mairie-200 hover:bg-mairie-50 dark:hover:bg-slate-800 font-semibold px-4 py-2 rounded-lg transition-colors;
        }
        .tw-badge {
            @apply inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full;
        }
        .tw-input {
            @apply w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-mairie-500 focus:border-transparent transition-all;
        }
        .tw-glass {
            @apply bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-white/30 dark:border-white/10;
        }
        /* Met le canvas 3D derrière tout sans empêcher Tailwind */
        .maire-bg3d { z-index: -1 !important; }

        /* === ANIMATIONS & EFFETS PARTAGÉS — utilisables sur toutes les pages refondues === */

        /* Mesh gradient animé en arrière-plan (hero, CTA…) */
        @keyframes mesh-shift {
            0%, 100% { background-position: 0% 50%, 100% 50%, 50% 0%, 50% 100%; }
            50% { background-position: 100% 50%, 0% 50%, 50% 100%, 50% 0%; }
        }
        .maire-mesh-bg {
            background:
                radial-gradient(at 20% 30%, rgba(245, 158, 11, 0.35) 0px, transparent 50%),
                radial-gradient(at 80% 20%, rgba(12, 74, 62, 0.45) 0px, transparent 50%),
                radial-gradient(at 70% 80%, rgba(217, 119, 6, 0.30) 0px, transparent 50%),
                radial-gradient(at 30% 70%, rgba(20, 184, 166, 0.35) 0px, transparent 50%);
            background-size: 200% 200%;
            animation: mesh-shift 20s ease-in-out infinite;
        }

        /* Blob morph animé (formes organiques décoratives) */
        @keyframes blob-morph {
            0%, 100% { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; transform: rotate(0deg); }
            25% { border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%; transform: rotate(90deg); }
            50% { border-radius: 50% 60% 30% 60% / 30% 60% 70% 40%; transform: rotate(180deg); }
            75% { border-radius: 60% 40% 60% 30% / 70% 30% 50% 60%; transform: rotate(270deg); }
        }
        .maire-blob { animation: blob-morph 25s ease-in-out infinite; }

        /* Shimmer animé sur bouton CTA (gradient qui défile) */
        @keyframes maire-shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .maire-shimmer-btn {
            background: linear-gradient(110deg, #0c4a3e, #1e5f48, #d97706, #1e5f48, #0c4a3e);
            background-size: 300% 100%;
            animation: maire-shimmer 4s linear infinite;
        }

        /* Bandeau ticker (actualités défilantes) */
        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .maire-ticker { animation: ticker 30s linear infinite; }

        /* Texte avec gradient — variante claire sur fond clair */
        .maire-text-gradient {
            background: linear-gradient(135deg, #0c4a3e 0%, #1e5f48 35%, #d97706 70%, #f5b73d 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        /* Variante lisible sur héros et blocs sombres (évite le texte invisible) */
        .maire-text-gradient--light,
        .maire-hero-bg .maire-text-gradient,
        section.text-white .maire-text-gradient,
        section[class*="from-mairie-9"] .maire-text-gradient,
        section[class*="from-mairie-8"] .maire-text-gradient,
        section[class*="bg-mairie-950"] .maire-text-gradient,
        section[class*="to-slate-950"] .maire-text-gradient,
        article.text-white .maire-text-gradient,
        .bg-gradient-to-br.text-white .maire-text-gradient,
        h1.text-white .maire-text-gradient,
        h2.text-white .maire-text-gradient,
        h3.text-white .maire-text-gradient,
        .dark h1[class*="dark:text-white"] .maire-text-gradient,
        .dark h2[class*="dark:text-white"] .maire-text-gradient,
        .dark h3[class*="dark:text-white"] .maire-text-gradient {
            background: linear-gradient(135deg, #fffbeb 0%, #fde68a 28%, #fbbf24 58%, #ffffff 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        /* Flottement vertical doux */
        @keyframes float-y {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .maire-float { animation: float-y 6s ease-in-out infinite; }

        /* Effet hover bento card : tilt + glow */
        .maire-bento-card { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s; }
        .maire-bento-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.3); }

        /* Compteurs animés au scroll */
        .maire-counter { display: inline-block; }

        /* Grain léger sur section */
        .maire-grain { position: relative; }
        .maire-grain::after {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' /%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.4' /%3E%3C/svg%3E");
            opacity: 0.05; pointer-events: none; mix-blend-mode: multiply;
        }

        /* === COMPOSANTS RICHES SUPPLÉMENTAIRES === */

        /* Hero standard (à utiliser sur les pages internes) */
        .maire-hero-bg {
            background: linear-gradient(135deg, #0a3c34 0%, #0c4a3e 35%, #1e5f48 70%, #2e7a5b 100%);
            position: relative;
            overflow: hidden;
        }

        .maire-hero-bg .maire-kpi-card {
            background: rgba(255, 255, 255, 0.12) !important;
            border-color: rgba(255, 255, 255, 0.22) !important;
            box-shadow: none !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .maire-hero-bg .maire-kpi-card .maire-kpi-card__value,
        .maire-hero-bg .maire-kpi-card .maire-kpi-card__label,
        .maire-hero-bg .maire-kpi-card > p {
            color: #ffffff !important;
        }
        .maire-hero-bg .maire-kpi-card .maire-kpi-card__label,
        .maire-hero-bg .maire-kpi-card p[class*="text-mairie"] {
            color: rgba(255, 255, 255, 0.88) !important;
        }
        .maire-hero-bg .maire-section-kicker {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.12) !important;
            border-color: rgba(255, 255, 255, 0.22) !important;
        }

        /* Section section-shell-style fallback (compatibilité legacy) */

        /* Tag colorisé */
        .maire-tag {
            @apply inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider;
        }
    </style>
    <?php endif; // fin du fallback CDN ?>

    <!-- SCRIPT GLOBAL : compteurs animés au scroll (réutilisable sur toutes pages) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var counters = document.querySelectorAll('.maire-counter');
            if (!counters.length || !window.IntersectionObserver) return;
            function animateCounter(el) {
                var target = parseFloat(el.dataset.target || '0');
                var suffix = el.dataset.suffix || '';
                if (!isFinite(target) || target <= 0) {
                    el.textContent = '0' + suffix;
                    return;
                }
                var duration = parseInt(el.dataset.duration || '1800', 10);
                var start = performance.now();
                function tick(now) {
                    var p = Math.min(1, (now - start) / duration);
                    var e = 1 - Math.pow(1 - p, 3);
                    var v = target * e;
                    el.textContent = (target >= 100 ? Math.floor(v) : v.toFixed(0)).toLocaleString('fr-FR') + suffix;
                    if (p < 1) requestAnimationFrame(tick);
                    else el.textContent = target.toLocaleString('fr-FR') + suffix;
                }
                requestAnimationFrame(tick);
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) { animateCounter(entry.target); io.unobserve(entry.target); }
                });
            }, { threshold: 0.4 });
            counters.forEach(function (el) { io.observe(el); });
        });
    </script>
    <link rel="manifest" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>manifest.webmanifest">
    <meta name="theme-color" content="#0c4a3e">
    <!-- PWA : la meta moderne 'mobile-web-app-capable' est reconnue par
         iOS Safari depuis la version 15 (sortie 2021), ce qui rend obsolète
         l'ancienne 'apple-mobile-web-app-capable' (warning Chrome). Les
         autres metas 'apple-…' restent légitimes (titre + status bar). -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mairie">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($maireLogoMobileUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($maireLogoMobileUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <script defer src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/bg3d.js"></script>
    <script defer src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/pwa-register.js"></script>
    <?php
    // Widget chatbot citoyen — uniquement si feature 'ia_assistant' active pour la commune.
    $maireChatbotActif = isset($pdo) && $pdo instanceof PDO && function_exists('maire_feature_disponible')
        && maire_feature_disponible($pdo, 'ia_assistant');
    if ($maireChatbotActif): ?>
    <script defer data-endpoint="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>chatbot-ask.php"
            data-csrf-token="<?php echo htmlspecialchars(maire_csrf_token(MAIRE_CSRF_SCOPE_CHATBOT), ENT_QUOTES, 'UTF-8'); ?>"
            data-csrf-scope="<?php echo htmlspecialchars(MAIRE_CSRF_SCOPE_CHATBOT, ENT_QUOTES, 'UTF-8'); ?>"
            src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/chatbot.js"></script>
    <?php endif; ?>
    <?php if (!empty($pageNeedsCharts)): ?>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script defer src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/dashboard-charts.js"></script>
    <?php endif; ?>
    <?php if (!empty($pageHeadExtra)): ?>
    <?php echo $pageHeadExtra; ?>
    <?php endif; ?>
    <?php if ($maireShowSplash): ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($maireSplashLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body class="font-sans antialiased text-slate-800 dark:text-slate-100 dark:bg-slate-950<?php echo $maireShowSplash ? ' maire-preloader-active' : ''; ?>">
<?php if ($maireShowSplash): ?>
<div class="maire-preloader" id="mairePreloader" role="presentation" aria-hidden="false">
    <div class="maire-preloader__stage">
        <img src="<?php echo htmlspecialchars($maireSplashLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
             alt="Mairie de Rufisque-Est — Sénégal"
             class="maire-preloader__logo"
             width="1360"
             height="840"
             decoding="async"
             fetchpriority="high">
    </div>
</div>
<?php else: ?>
<div class="page-loader" id="pageLoader" aria-hidden="true">
    <div class="loader-mark">
        <span>R</span>
        <small>Chargement...</small>
    </div>
</div>
<?php endif; ?>
<?php
$maireNavLinks = [
    ['url' => 'index.php', 'label' => 'Accueil', 'desktop_label' => 'Accueil', 'match' => ['index.php']],
    ['url' => 'maire.php', 'label' => 'M. le Maire', 'desktop_label' => 'Le Maire', 'match' => ['maire.php']],
    ['url' => 'audiences-maire.php', 'label' => 'Audience Maire', 'desktop_label' => 'Audience', 'match' => ['audiences-maire.php']],
    ['url' => 'services.php', 'label' => 'Tous les services', 'desktop_label' => 'Services', 'match' => ['services.php', 'etat-civil.php', 'sante.php', 'education.php', 'urbanisme.php', 'division-services-techniques.php', 'hygiene.php', 'action-sociale.php', 'vie-culturelle.php']],
    ['url' => 'division-services-techniques.php', 'label' => 'Services techniques', 'desktop_label' => 'Techniques', 'match' => ['division-services-techniques.php']],
    ['url' => 'documents.php', 'label' => 'Documents', 'desktop_label' => 'Documents', 'match' => ['documents.php']],
    ['url' => 'consultations.php', 'label' => 'Consultations', 'desktop_label' => 'Consultations', 'match' => ['consultations.php', 'consultation.php']],
    ['url' => 'conseil-municipal.php', 'label' => 'Conseil municipal', 'desktop_label' => 'Conseil', 'match' => ['conseil-municipal.php']],
    ['url' => 'paiements.php', 'label' => 'Payer en ligne', 'desktop_label' => 'Paiements', 'match' => ['paiements.php', 'payer.php', 'paiement-retour.php']],
    ['url' => 'actualites.php', 'label' => 'Actualités', 'desktop_label' => 'Actualités', 'match' => ['actualites.php', 'actualite.php']],
    ['url' => 'projets.php', 'label' => 'Projets', 'desktop_label' => 'Projets', 'match' => ['projets.php']],
];
if ($maireNavOffres) {
    $maireNavLinks[] = ['url' => 'offres.php', 'label' => 'Offres', 'desktop_label' => 'Offres', 'match' => ['offres.php']];
}
$maireNavLinks[] = ['url' => 'contact.php', 'label' => 'Contact', 'desktop_label' => 'Contact', 'match' => ['contact.php']];
$maireNavLinksDesktop = array_values(array_filter(
    $maireNavLinks,
    static fn(array $link): bool => (string) ($link['url'] ?? '') !== 'division-services-techniques.php'
));
?>
<header class="topbar sticky top-0 z-50 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md border-b border-slate-200/70 dark:border-slate-800/70 shadow-sm">
    <div class="mx-auto w-full max-w-[92rem] px-3 sm:px-4 lg:px-5 xl:px-6">
        <div class="maire-topbar-layout min-h-[5.5rem] py-2">
            <div class="maire-topbar-main flex items-center justify-between gap-2 sm:gap-3 lg:gap-4">
                <!-- Logo (modèle rufisqueouest.org : custom-logo desktop + mobile) -->
                <div class="maire-logo-box shrink-0">
                    <div class="maire-logo">
                        <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>index.php"
                           class="maire-custom-logo-link brand"
                           rel="home"
                           aria-label="Mairie de Rufisque-Est — accueil">
                            <img src="<?php echo htmlspecialchars($maireLogoHeaderUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                 width="298"
                                 height="88"
                                 class="maire-custom-logo maire-custom-logo--desktop"
                                 alt="Mairie de Rufisque-Est — Sénégal"
                                 decoding="async"
                                 fetchpriority="high">
                            <img src="<?php echo htmlspecialchars($maireLogoMobileUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                 width="200"
                                 height="200"
                                 class="maire-custom-logo maire-custom-logo--mobile"
                                 alt="Mairie de Rufisque-Est"
                                 decoding="async">
                        </a>
                    </div>
                </div>

                <nav id="mainNav" class="nav hidden xl:flex items-center justify-start flex-1 min-w-0 px-2" aria-label="Navigation principale">
                    <?php foreach ($maireNavLinksDesktop as $link):
                        $isActive = in_array($currentPage, $link['match'], true); ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix . $link['url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="<?php echo $isActive ? 'active' : ''; ?>"
                           aria-current="<?php echo $isActive ? 'page' : 'false'; ?>">
                            <?php echo htmlspecialchars((string) ($link['desktop_label'] ?? $link['label']), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="nav-actions maire-topbar-actions flex shrink-0 items-center gap-1.5 sm:gap-2 lg:ml-auto">
                    <?php if ($maireCitoyenConnecte): ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>citoyen/profil.php"
                           class="maire-account-link hidden md:inline-flex items-center gap-2 px-2.5 2xl:px-3 py-1.5 rounded-full bg-mairie-50 dark:bg-mairie-900/40 text-mairie-800 dark:text-mairie-200 text-sm font-semibold hover:bg-mairie-100 dark:hover:bg-mairie-900/60 transition-colors shrink-0"
                           title="<?php echo htmlspecialchars($maireCitoyenPrenom !== '' ? $maireCitoyenPrenom : 'Mon espace', ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="maire-account-link__icon w-6 h-6 rounded-full bg-mairie-800 text-white flex items-center justify-center text-xs">👤</span>
                            <span class="hidden xl:inline 2xl:hidden">Profil</span>
                            <span class="hidden 2xl:inline"><?php echo htmlspecialchars($maireCitoyenPrenom !== '' ? $maireCitoyenPrenom : 'Mon espace', ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>citoyen/connexion.php"
                           class="maire-account-link maire-account-link--guest hidden md:inline-flex items-center gap-2 px-2.5 2xl:px-3 py-1.5 rounded-full bg-gradient-to-r from-mairie-800 to-mairie-700 text-white text-sm font-semibold hover:from-mairie-700 hover:to-mairie-600 shadow-glow transition-all hover:-translate-y-0.5 shrink-0"
                           title="Espace citoyen">
                            <span class="maire-account-link__icon w-6 h-6 rounded-full bg-white/15 text-white flex items-center justify-center text-xs">👤</span>
                            <span class="hidden xl:inline 2xl:hidden">Espace</span>
                            <span class="hidden 2xl:inline">Espace citoyen</span>
                        </a>
                    <?php endif; ?>
                    <button class="theme-toggle maire-utility-btn p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" id="themeToggle" aria-label="Activer le mode sombre" title="Mode sombre / clair">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>
                    <button class="menu-toggle maire-utility-btn p-2 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" id="menuToggle" aria-label="Ouvrir le menu" aria-expanded="false">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>

            <div id="mobileMenu" class="maire-menu-panel hidden pb-4 pt-2 border-t border-slate-200 dark:border-slate-800">
                <div class="maire-menu-panel__surface">
                    <div class="maire-menu-panel__header">
                        <p class="maire-menu-panel__eyebrow">Navigation</p>
                        <h2 class="maire-menu-panel__title">Tous les acces de la mairie</h2>
                        <p class="maire-menu-panel__description">Retrouvez rapidement les services, actualites, projets et demarches utiles depuis un seul panneau.</p>
                    </div>

                    <nav class="maire-menu-panel__nav flex flex-col gap-1 md:grid md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($maireNavLinks as $link):
                            $isActive = in_array($currentPage, $link['match'], true); ?>
                            <a href="<?php echo htmlspecialchars($urlPrefix . $link['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="maire-menu-link <?php echo $isActive ? 'is-active' : ''; ?>">
                                <span class="maire-menu-link__label"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="maire-menu-link__meta">Acceder</span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($maireCitoyenConnecte): ?>
                            <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>citoyen/profil.php"
                               class="maire-menu-link maire-menu-link--account">
                                <span class="maire-menu-link__label">👤 <?php echo htmlspecialchars($maireCitoyenPrenom !== '' ? $maireCitoyenPrenom : 'Mon espace', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="maire-menu-link__meta">Compte citoyen</span>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>citoyen/connexion.php"
                               class="maire-menu-link maire-menu-link--account maire-menu-link--account-guest">
                                <span class="maire-menu-link__label">Espace citoyen</span>
                                <span class="maire-menu-link__meta">Se connecter</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</header>
<script>
    // Menu principal : toggle au clic sur le bouton hamburger
    (function () {
        var btn = document.getElementById('menuToggle');
        var menu = document.getElementById('mobileMenu');
        if (btn && menu) {
            btn.addEventListener('click', function () {
                var isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', String(!isOpen));
            });
        }
    })();
</script>
