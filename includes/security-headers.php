<?php
declare(strict_types=1);

/**
 * Headers HTTP de sécurité — appliqués globalement via header.php.
 *
 * Couvre :
 *  - Content-Security-Policy (CSP) — anti XSS, anti injection externe
 *  - X-Frame-Options — anti clickjacking
 *  - X-Content-Type-Options — anti MIME-sniffing
 *  - Referrer-Policy — limite la fuite d'URL vers les sites tiers
 *  - Permissions-Policy — désactive API navigateur sensibles
 *  - Strict-Transport-Security (HSTS) — force HTTPS (seulement si déjà en HTTPS)
 *  - Cross-Origin-* — isolation des ressources
 *
 * Pourquoi PHP en complément du .htaccess ?
 * Apache peut ne pas avoir mod_headers activé (cas fréquent en mutualisé)
 * et certains hébergeurs (Nginx + PHP-FPM) ignorent .htaccess.
 *
 * Pour ajuster la CSP par page (ex. autoriser une iframe YouTube sur /actualites.php) :
 *   maire_security_headers_emit(['frame_src_extra' => ['https://www.youtube.com']]);
 */

/**
 * Émet tous les headers de sécurité. À appeler depuis header.php
 * AVANT toute sortie HTML.
 *
 * @param array{
 *     csp_enabled?: bool,
 *     csp_report_only?: bool,
 *     script_src_extra?: list<string>,
 *     style_src_extra?: list<string>,
 *     frame_src_extra?: list<string>,
 *     connect_src_extra?: list<string>,
 *     img_src_extra?: list<string>,
 *     frame_ancestors?: list<string>,
 *     hsts_enabled?: bool,
 *     hsts_max_age?: int
 * } $options
 */
function maire_security_headers_emit(array $options = []): void
{
    if (headers_sent()) {
        return;
    }

    $cspEnabled = $options['csp_enabled'] ?? true;
    $cspReportOnly = $options['csp_report_only'] ?? false;
    $hstsEnabled = $options['hsts_enabled'] ?? true;
    $hstsMaxAge = (int) ($options['hsts_max_age'] ?? 15552000); // 180 jours

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    // ── Anti-clickjacking ──
    header('X-Frame-Options: SAMEORIGIN');

    // ── Anti MIME-sniffing ──
    header('X-Content-Type-Options: nosniff');

    // ── Politique de référent ──
    // Envoie seulement l'origine vers les domaines tiers, l'URL complète en même origine
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // ── Désactivation explicite d'APIs navigateur ──
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=(self), usb=(), gyroscope=(), magnetometer=(), interest-cohort=()');

    // ── HSTS (uniquement quand HTTPS effectivement actif) ──
    if ($hstsEnabled && $isHttps) {
        header("Strict-Transport-Security: max-age={$hstsMaxAge}; includeSubDomains");
    }

    // ── Cross-Origin isolation (modéré, pas trop strict pour pas casser les CDN si présents) ──
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-site');

    // ── CSP : Content Security Policy ──
    if ($cspEnabled) {
        $options['is_https'] = $isHttps;
        $csp = maire_security_build_csp($options);
        $headerName = $cspReportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        header($headerName . ': ' . $csp);
    }

    // ── Masquer la version PHP dans le header X-Powered-By ──
    header_remove('X-Powered-By');
}

/**
 * Construit la Content-Security-Policy.
 *
 * Permet par défaut :
 *  - les scripts/styles inline (le projet en utilise abondamment pour Tailwind config / animations)
 *  - les images depuis 'self', data: et https: (pour les SVG inline + photos officielles)
 *  - les fonts Google (utilisées dans le projet)
 *  - les API du même domaine
 *
 * Bloque :
 *  - tout iframe externe sauf YouTube/Vimeo si explicitement ajouté
 *  - tout script externe non whitelisté
 *  - tout formulaire pointant ailleurs
 */
function maire_security_build_csp(array $options = []): string
{
    // Domaines tiers usuels du projet
    $googleFonts = ['https://fonts.googleapis.com', 'https://fonts.gstatic.com'];

    // Pendant la phase de migration depuis Tailwind CDN → si le bundle local n'est pas trouvé,
    // header.php charge cdn.tailwindcss.com. On l'autorise donc explicitement.
    $tailwindCdn = ['https://cdn.tailwindcss.com'];

    $scriptSrc = array_unique(array_merge(
        ["'self'", "'unsafe-inline'"], // unsafe-inline nécessaire pour la config Tailwind et les scripts inline
        $tailwindCdn,
        (array) ($options['script_src_extra'] ?? [])
    ));

    $styleSrc = array_unique(array_merge(
        ["'self'", "'unsafe-inline'"], // unsafe-inline nécessaire pour les styles inline du projet
        $googleFonts,
        (array) ($options['style_src_extra'] ?? [])
    ));

    $imgSrc = array_unique(array_merge(
        ["'self'", 'data:', 'blob:', 'https:'], // 'https:' générique car les utilisateurs peuvent uploader des photos
        (array) ($options['img_src_extra'] ?? [])
    ));

    $fontSrc = array_unique(array_merge(
        ["'self'", 'data:'],
        $googleFonts
    ));

    $connectSrc = array_unique(array_merge(
        ["'self'"],
        (array) ($options['connect_src_extra'] ?? [])
    ));

    $frameSrc = array_unique(array_merge(
        ["'self'"],
        (array) ($options['frame_src_extra'] ?? [])
    ));

    $frameAncestors = (array) ($options['frame_ancestors'] ?? ["'self'"]);

    $mediaSrc = array_unique(array_merge(
        ["'self'", 'blob:', 'data:'],
        (array) ($options['media_src_extra'] ?? [])
    ));

    $directives = [
        "default-src 'self'",
        'script-src ' . implode(' ', $scriptSrc),
        'style-src ' . implode(' ', $styleSrc),
        'img-src ' . implode(' ', $imgSrc),
        'font-src ' . implode(' ', $fontSrc),
        'connect-src ' . implode(' ', $connectSrc),
        'frame-src ' . implode(' ', $frameSrc),
        'media-src ' . implode(' ', $mediaSrc),
        'frame-ancestors ' . implode(' ', $frameAncestors),
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "worker-src 'self' blob:",
        "manifest-src 'self'",
    ];

    // En local (WAMP HTTP), forcer HTTPS casse images/CSS ; activer seulement si la page est déjà en HTTPS.
    if (!empty($options['is_https'])) {
        $directives[] = 'upgrade-insecure-requests';
    }

    return implode('; ', $directives);
}
