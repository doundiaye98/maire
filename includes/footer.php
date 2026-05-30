<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/super-admin-session.php';
require_once __DIR__ . '/site-paths.php';
$urlPrefix = maire_url_prefix();
$maireFooterOffres = (($_SESSION['subscriber_role'] ?? '') === 'admin') || maire_super_admin_session_valid();
$maireAnneeFooter = date('Y');
?>
<footer class="footer mt-16 bg-gradient-to-br from-mairie-900 via-mairie-800 to-mairie-950 text-slate-100 relative overflow-hidden">
    <!-- décor lumière subtile -->
    <div class="absolute -top-32 -right-32 w-96 h-96 bg-mairie-500/20 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-gold-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

    <div class="mx-auto w-full max-w-[92rem] px-3 sm:px-4 lg:px-5 xl:px-6 py-14 relative">
        <div class="footer-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
            <!-- Marque -->
            <div class="lg:col-span-2">
                <div class="maire-logo-box mb-4">
                    <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>index.php"
                       class="maire-custom-logo-link maire-custom-logo-link--footer inline-block"
                       rel="home">
                        <img src="<?php echo htmlspecialchars(maire_logo_header_url_absolue(), ENT_QUOTES, 'UTF-8'); ?>"
                             width="298"
                             height="88"
                             class="maire-custom-logo maire-custom-logo--footer"
                             alt="Mairie de Rufisque-Est — Sénégal"
                             loading="lazy"
                             decoding="async">
                    </a>
                </div>
                <p class="text-slate-300 leading-relaxed max-w-lg">
                    Une mairie plus lisible, plus réactive et plus accessible. Démarches, informations, paiements et projets sont désormais réunis dans une même expérience publique.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 bg-white/5 text-[11px] font-black uppercase tracking-[0.18em] text-gold-200">Portail officiel</span>
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 bg-white/5 text-[11px] font-black uppercase tracking-[0.18em] text-emerald-200">Portail en ligne</span>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>contact.php"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 rounded-xl text-sm font-semibold transition-all hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Nous écrire
                    </a>
                    <a href="<?php echo htmlspecialchars(maire_citoyen_url('signaler.php'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-gold-500/90 hover:bg-gold-500 text-mairie-950 rounded-xl text-sm font-semibold transition-all hover:-translate-y-0.5 shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Signaler un problème
                    </a>
                </div>
            </div>

            <!-- Liens utiles -->
            <div>
                <h4 class="text-sm font-bold uppercase tracking-wider text-mairie-200 mb-4">Navigation</h4>
                <nav class="footer-links flex flex-col gap-2.5" aria-label="Liens utiles">
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>index.php"><span class="text-gold-400">›</span> Accueil</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>maire.php"><span class="text-gold-400">›</span> M. le Maire</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>maire.php#administration"><span class="text-gold-400">›</span> Administration &amp; équipe</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>services.php"><span class="text-gold-400">›</span> Tous les services</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>division-services-techniques.php"><span class="text-gold-400">›</span> Services techniques</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>documents.php"><span class="text-gold-400">›</span> Documents</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>actualites.php"><span class="text-gold-400">›</span> Actualités</a>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>consultations.php"><span class="text-gold-400">›</span> Consultations</a>
                    <?php if ($maireFooterOffres): ?>
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>offres.php"><span class="text-gold-400">›</span> Offres &amp; abonnements</a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Contact / horaires -->
            <div>
                <h4 class="text-sm font-bold uppercase tracking-wider text-mairie-200 mb-4">Nous trouver</h4>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gold-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <div>
                            <p class="text-slate-200 font-semibold">Castor, face pharmacie DIOR</p>
                            <p class="text-slate-400">Arafat II, Rufisque-Est — Sénégal</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gold-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-slate-200 font-semibold inline-flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    En ligne
                                </span>
                                Informations et démarches accessibles
                            </p>
                            <p class="text-slate-400">Le portail permet d’initier les démarches et de transmettre une demande.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gold-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:Rufisquest02@gmail.com" class="text-slate-300 hover:text-white break-all">Rufisquest02@gmail.com</a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bandeau bas -->
        <div class="mt-12 pt-6 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="copyright text-sm text-slate-400">
                © <?php echo $maireAnneeFooter; ?> Mairie de Rufisque-Est — République du Sénégal. Tous droits réservés.
            </p>
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/5 border border-white/10">
                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                    Service en ligne
                </span>
                <span>v1.0</span>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         SIGNATURE ÉDITEUR — UNIVERS DIASPORAS
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="relative mt-0 bg-gradient-to-r from-mairie-950 via-slate-950 to-mairie-950 border-t border-gold-500/20 overflow-hidden">
        <!-- Effet de halo doré subtil -->
        <div class="absolute inset-0 pointer-events-none opacity-40">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-32 blur-3xl"
                 style="background: radial-gradient(ellipse at center, rgba(245, 158, 11, 0.15) 0%, transparent 70%);"></div>
        </div>

        <div class="mx-auto w-full max-w-[92rem] px-3 sm:px-4 lg:px-5 xl:px-6 py-6 relative">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <!-- Côté gauche : édition -->
                <div class="flex items-center gap-3 text-center sm:text-left">
                    <span class="text-[10px] uppercase tracking-[0.25em] text-slate-500 font-bold">Édition &amp; développement</span>
                </div>

                <!-- Bloc central : logo / marque -->
                <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>editeur.php"
                   class="group inline-flex items-center gap-3 px-5 py-2.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-gold-500/30 hover:border-gold-400/60 backdrop-blur-sm transition-all duration-300 hover:shadow-[0_0_30px_rgba(251,191,36,0.15)]"
                   aria-label="En savoir plus sur Univers Diasporas, éditeur du site">
                    <!-- Logo UD : pictogramme stylisé -->
                    <span class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-gold-400 via-gold-500 to-amber-600 shadow-inner ring-1 ring-gold-300/40 group-hover:scale-110 group-hover:rotate-6 transition-all duration-500">
                        <svg class="w-5 h-5 text-mairie-950" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2L2 7v10c0 5.55 3.84 9.95 9 11 5.16-1.05 9-5.45 9-11V7l-10-5zm0 2.18L19 7.5v3.92c0 .54-.04 1.07-.11 1.6L12 9.5 5.11 13.02C5.04 12.49 5 11.96 5 11.42V7.5L12 4.18zM12 12l5.85 2.99c-.61 2.05-1.91 3.82-3.69 5L12 21l-2.16-1.01c-1.78-1.18-3.08-2.95-3.69-5L12 12z"/>
                        </svg>
                        <!-- Halo pulsé -->
                        <span class="absolute inset-0 rounded-xl ring-2 ring-gold-400/0 group-hover:ring-gold-400/40 transition-all duration-700"></span>
                    </span>

                    <span class="flex flex-col">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 leading-tight">Powered by</span>
                        <span class="font-black text-base leading-tight bg-gradient-to-r from-gold-300 via-gold-200 to-gold-400 bg-clip-text text-transparent">
                            Univers&nbsp;Diasporas
                        </span>
                    </span>

                    <svg class="w-4 h-4 text-gold-400/60 group-hover:text-gold-300 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>

                <!-- Côté droit : tagline + lien -->
                <div class="flex flex-col items-center sm:items-end gap-1">
                    <p class="text-xs text-slate-300 font-medium italic max-w-xs text-center sm:text-right">
                        « Solutions digitales pour les administrations africaines »
                    </p>
                    <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>editeur.php" class="text-[10px] uppercase tracking-widest font-bold text-gold-400 hover:text-gold-300 transition">
                        En savoir plus →
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<button class="back-to-top fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-mairie-800 hover:bg-mairie-700 text-white shadow-glow flex items-center justify-center transition-all hover:-translate-y-1" id="backToTop" aria-label="Retour en haut">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
</button>
<?php if (!empty($pageFooterScripts)): ?>
    <?php echo $pageFooterScripts; ?>
<?php endif; ?>
<?php $maireMainJsAbs = __DIR__ . '/../assets/js/main.js'; ?>
<script src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/main.js?v=<?php echo is_file($maireMainJsAbs) ? (int) filemtime($maireMainJsAbs) : time(); ?>"></script>
</body>
</html>
