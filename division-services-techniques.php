<?php
declare(strict_types=1);

$pageTitle = 'Division des services techniques | Mairie de Rufisque-Est';
$pageDescription = "Voirie, éclairage public, bâtiments communaux et interventions techniques de proximité pour Rufisque-Est.";
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-sectorielle.php';

maire_page_sectorielle_render([
    'icone' => '🛠️',
    'kicker' => 'Service municipal · Infrastructures',
    'titreH1' => 'Division des services',
    'titreHilight' => 'techniques',
    'titreHilightClass' => 'text-cyan-200',
    'description' => 'La division coordonne les interventions techniques communales pour améliorer durablement les routes, les équipements publics, l’éclairage et le cadre de vie.',
    'descriptionClass' => 'text-cyan-50',
    'heroGradient' => 'from-slate-800 via-mairie-900 to-cyan-950',
    'blobColor' => 'bg-cyan-400/25',
    'blobColor2' => 'bg-sky-400/20',
    'stats' => [
        ['valeur' => 12, 'suffix' => '', 'label' => 'Zones suivies'],
        ['valeur' => 48, 'suffix' => 'h', 'label' => 'Réactivité cible'],
        ['valeur' => 100, 'suffix' => '%', 'label' => 'Coordination terrain'],
    ],
    'blocs' => [
        [
            'icone' => '🛣️',
            'titre' => 'Voirie et mobilité',
            'gradient' => 'from-sky-500 to-blue-600',
            'puces' => [
                'Suivi des dégradations sur les routes, trottoirs et caniveaux.',
                'Planification des réparations prioritaires sur les axes fréquentés.',
                'Coordination des interventions pour fluidifier les déplacements quotidiens.',
            ],
        ],
        [
            'icone' => '💡',
            'titre' => 'Éclairage et équipements publics',
            'gradient' => 'from-amber-500 to-orange-600',
            'puces' => [
                'Traitement des pannes de lampadaires et points lumineux publics.',
                'Suivi des équipements techniques implantés dans les quartiers.',
                'Appui aux opérations de sécurisation des zones à forte fréquentation.',
            ],
        ],
        [
            'icone' => '🏢',
            'titre' => 'Bâtiments et maintenance communale',
            'gradient' => 'from-cyan-500 to-teal-600',
            'puces' => [
                'Entretien des bâtiments administratifs et équipements collectifs.',
                'Appui technique aux projets de réhabilitation et d’aménagement.',
                'Suivi des besoins de maintenance signalés par les services municipaux.',
            ],
        ],
    ],
    'ctaLabel' => 'Signaler un besoin technique',
    'ctaLien' => 'citoyen/signaler.php',
]);
?>

<section class="py-16 bg-white dark:bg-slate-950">
    <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1.15fr_0.85fr] gap-6 items-stretch">
            <article class="relative overflow-hidden rounded-3xl border border-slate-300 dark:border-slate-700 bg-gradient-to-br from-slate-100 via-white to-cyan-100 dark:from-slate-900 dark:via-slate-950 dark:to-cyan-950/40 p-8 shadow-xl">
                <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-cyan-300/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
                <span class="maire-tag bg-slate-900 text-white dark:bg-cyan-900/70 dark:text-cyan-100 mb-4">Priorités d’intervention</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-950 dark:text-white mb-4">
                    Un service technique pensé pour le terrain
                </h2>
                <p class="text-slate-700 dark:text-slate-200 leading-relaxed mb-6">
                    La division des services techniques agit comme cellule opérationnelle de proximité. Elle centralise les besoins remontés par les habitants, les services communaux et les quartiers, puis oriente chaque demande vers l’intervention la plus adaptée.
                </p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/80 p-5 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-300 mb-2">Interventions suivies</p>
                        <ul class="space-y-2 text-sm text-slate-800 dark:text-slate-200">
                            <li class="flex items-start gap-2"><span class="text-cyan-600 dark:text-cyan-400 font-black">01.</span><span>Nids-de-poule, déformations de chaussée et accès dégradés.</span></li>
                            <li class="flex items-start gap-2"><span class="text-cyan-600 dark:text-cyan-400 font-black">02.</span><span>Pannes d’éclairage public et besoins de remise en service.</span></li>
                            <li class="flex items-start gap-2"><span class="text-cyan-600 dark:text-cyan-400 font-black">03.</span><span>Maintenance des bâtiments, clôtures et petits ouvrages publics.</span></li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950 text-white p-5 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-cyan-100 mb-2">Méthode</p>
                        <ul class="space-y-2 text-sm text-slate-100">
                            <li>Diagnostic du besoin et qualification technique.</li>
                            <li>Priorisation selon l’urgence, l’impact et la sécurité.</li>
                            <li>Programmation terrain avec retour d’information.</li>
                        </ul>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 text-white p-8 relative overflow-hidden shadow-xl border border-slate-800">
                <div class="absolute inset-0 opacity-20 pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,0.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.12) 1px, transparent 1px); background-size: 22px 22px;" aria-hidden="true"></div>
                <div class="relative">
                    <span class="maire-tag bg-white/15 border border-white/20 text-cyan-100 mb-4">Quand nous contacter</span>
                    <h3 class="text-2xl font-black mb-4">Demandes les plus fréquentes</h3>
                    <div class="space-y-3">
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <p class="font-bold text-white">Voirie locale</p>
                            <p class="text-sm text-slate-100 mt-1">Dégradation d’une route, difficulté d’accès, besoin de reprise ponctuelle.</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <p class="font-bold text-white">Éclairage public</p>
                            <p class="text-sm text-slate-100 mt-1">Lampadaire hors service, zone insuffisamment éclairée, panne récurrente.</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <p class="font-bold text-white">Équipements communaux</p>
                            <p class="text-sm text-slate-100 mt-1">Besoin de maintenance sur un bâtiment ou un équipement collectif.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="citoyen/signaler.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-black transition-colors">
                            Faire un signalement
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="contact.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/12 hover:bg-white/20 border border-white/20 text-white font-black transition-colors">
                            Contacter la mairie
                        </a>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
