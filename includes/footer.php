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

    <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 relative">
        <div class="footer-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
            <!-- Marque -->
            <div class="lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-mairie-100 to-white flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-mairie-900" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                    </span>
                    <h3 class="text-2xl font-bold text-white">Commune de Rufisque-Est</h3>
                </div>
                <p class="text-slate-300 leading-relaxed max-w-md">
                    Service public communal au cœur du Sénégal moderne. Démarches simplifiées, transparence renforcée, citoyens connectés.
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>contact.php"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 rounded-xl text-sm font-semibold transition-all hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Nous écrire
                    </a>
                    <a href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>signaler.php"
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
                    <a class="text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center gap-2" href="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>services.php"><span class="text-gold-400">›</span> Services</a>
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
                                    Ouvert
                                </span>
                                Toujours ouvert
                            </p>
                            <p class="text-slate-400">Accueil citoyen 7j/7</p>
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
</footer>

<button class="back-to-top fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-mairie-800 hover:bg-mairie-700 text-white shadow-glow flex items-center justify-center transition-all hover:-translate-y-1" id="backToTop" aria-label="Retour en haut">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
</button>
<script src="<?php echo htmlspecialchars($urlPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/main.js"></script>
</body>
</html>
