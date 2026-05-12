<?php
declare(strict_types=1);

/**
 * Console admin — Consultations & votes électroniques (palier Premium).
 *
 * - Création d'une consultation (titre, question, options, dates, multi-choix)
 * - Publication / fermeture manuelle
 * - Visualisation des résultats avec graphique Chart.js
 * - Suppression (cascade)
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/consultations.php';
require_once __DIR__ . '/../includes/stats-temporelles.php';
require_once __DIR__ . '/../includes/maire-rate-limit.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'votes_electroniques')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('votes_electroniques', $palierCommune, 'admin');
    exit;
}

if (empty($_SESSION['abo_admin_csrf'])) {
    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
}

if ($pdo !== null) {
    maire_ensure_consultations_tables($pdo);
    maire_sync_statuts_consultations($pdo);
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['abo_admin_csrf'], $csrf)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } else {
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console'));
        $action = (string) ($_POST['action'] ?? '');
        switch ($action) {
            case 'creer':
                $optsRaw = (string) ($_POST['options'] ?? '');
                $options = array_filter(array_map('trim', preg_split('/\r?\n/', $optsRaw) ?: []), fn($v) => $v !== '');
                $err = null;
                $id = maire_creer_consultation($pdo, [
                    'type' => (string) ($_POST['type'] ?? 'sondage'),
                    'titre' => (string) ($_POST['titre'] ?? ''),
                    'question' => (string) ($_POST['question'] ?? ''),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'date_debut' => (string) ($_POST['date_debut'] ?? ''),
                    'date_fin' => (string) ($_POST['date_fin'] ?? ''),
                    'multi_choix' => isset($_POST['multi_choix']),
                    'resultats_publics' => isset($_POST['resultats_publics']),
                ], $options, $email, $err);
                if ($id === null) {
                    $flash = $err ?? 'Création impossible.';
                    $flashType = 'danger';
                } else {
                    $flash = 'Consultation n°' . $id . ' créée en brouillon. Publiez-la quand elle est prête.';
                }
                break;

            case 'changer_statut':
                $cid = (int) ($_POST['id'] ?? 0);
                $nouveau = (string) ($_POST['nouveau_statut'] ?? '');
                if (maire_changer_statut_consultation($pdo, $cid, $nouveau)) {
                    $flash = 'Statut mis à jour : ' . maire_libelle_statut_consultation($nouveau) . '.';
                } else {
                    $flash = 'Changement de statut impossible.';
                    $flashType = 'danger';
                }
                break;

            case 'supprimer':
                $cid = (int) ($_POST['id'] ?? 0);
                if (maire_supprimer_consultation($pdo, $cid)) {
                    $flash = 'Consultation supprimée (votes inclus).';
                } else {
                    $flash = 'Suppression impossible.';
                    $flashType = 'danger';
                }
                break;

            default:
                $flash = 'Action inconnue.';
                $flashType = 'danger';
        }
    }
}

$consultations = $pdo !== null ? maire_liste_consultations_admin($pdo, 100) : [];
$compteurs = $pdo !== null ? maire_compter_consultations($pdo) : ['total' => 0, 'ouvertes' => 0, 'fermees' => 0, 'brouillons' => 0, 'votes' => 0];

$pageNeedsCharts = !empty($consultations);
$pageTitle = 'Espace mairie · Consultations';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Démocratie locale</span>
            <h1>Consultations &amp; votes électroniques</h1>
            <p>Organisez des votes citoyens, sondages d’opinion et consultations participatives sur les décisions municipales.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Vue d’ensemble</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) $compteurs['total']; ?></strong>
                        <span>Consultations</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) $compteurs['ouvertes']; ?></strong>
                        <span>En cours</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#64748b;"><?php echo (int) $compteurs['fermees']; ?></strong>
                        <span>Clôturées</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#94a3b8;"><?php echo (int) $compteurs['brouillons']; ?></strong>
                        <span>Brouillons</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#0c4a3e;"><?php echo number_format((int) $compteurs['votes'], 0, ',', ' '); ?></strong>
                        <span>Votes reçus</span>
                    </article>
                </div>
            </article>

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : 'success'; ?>" style="margin:0;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>➕ Créer une consultation</h2>
                <form method="POST" action="consultations.php" style="display:grid;gap:0.7rem;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="creer">

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.6rem;">
                        <div>
                            <label for="type" style="display:block;font-weight:600;">Type *</label>
                            <select id="type" name="type" required style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <?php foreach (MAIRE_CONSULTATIONS_TYPES as $code => $lbl): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="titre" style="display:block;font-weight:600;">Titre *</label>
                            <input type="text" id="titre" name="titre" required maxlength="200" placeholder="Ex : Aménagement de la place du marché" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div>
                        <label for="question" style="display:block;font-weight:600;">Question posée *</label>
                        <textarea id="question" name="question" required maxlength="1000" rows="2" placeholder="Quel aménagement souhaitez-vous voir réaliser sur la place du marché ?" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="description" style="display:block;font-weight:600;">Présentation (facultatif)</label>
                        <textarea id="description" name="description" maxlength="4000" rows="3" placeholder="Contexte, enjeux, calendrier prévu, budget, conséquences attendues…" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="options" style="display:block;font-weight:600;">Options de réponse * <small style="color:#64748b;">(une par ligne, 2 à 20)</small></label>
                        <textarea id="options" name="options" required rows="5" placeholder="Espace vert avec bancs&#10;Marché couvert&#10;Aire de jeux pour enfants&#10;Parking" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;"></textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                        <div>
                            <label for="date_debut" style="display:block;font-weight:600;">Ouverture *</label>
                            <input type="date" id="date_debut" name="date_debut" required value="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                        <div>
                            <label for="date_fin" style="display:block;font-weight:600;">Clôture *</label>
                            <input type="date" id="date_fin" name="date_fin" required value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div style="display:flex;gap:1.5rem;align-items:center;">
                        <label><input type="checkbox" name="multi_choix" value="1"> Autoriser plusieurs réponses</label>
                        <label><input type="checkbox" name="resultats_publics" value="1" checked> Rendre les résultats publics</label>
                    </div>

                    <div class="detail-actions">
                        <button type="submit" class="btn btn-primary">💾 Créer (brouillon)</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Toutes les consultations (<?php echo count($consultations); ?>)</h2>
                <?php if (empty($consultations)): ?>
                    <p>Aucune consultation enregistrée pour l’instant.</p>
                <?php else: ?>
                    <div style="display:grid;gap:1rem;">
                    <?php foreach ($consultations as $c):
                        $cid = (int) $c['id'];
                        $statut = (string) $c['statut'];
                        $resultats = maire_resultats_chart($pdo, $cid);
                    ?>
                        <article style="border:1px solid #e2e8f0;border-radius:10px;padding:1rem;background:#fff;">
                            <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.6rem;flex-wrap:wrap;">
                                <div>
                                    <strong style="font-size:1.05rem;">#<?php echo $cid; ?> · <?php echo htmlspecialchars((string) $c['titre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <br><small style="color:#64748b;">
                                        <?php echo htmlspecialchars(maire_libelle_type_consultation((string) $c['type']), ENT_QUOTES, 'UTF-8'); ?>
                                        · du <?php echo htmlspecialchars((string) $c['date_debut'], ENT_QUOTES, 'UTF-8'); ?>
                                        au <?php echo htmlspecialchars((string) $c['date_fin'], ENT_QUOTES, 'UTF-8'); ?>
                                        · <strong><?php echo (int) $c['nb_votes_total']; ?></strong> votants
                                        · <?php echo (int) $c['nb_options']; ?> options
                                        <?php if (!empty($c['cree_par_email'])): ?>· par <?php echo htmlspecialchars((string) $c['cree_par_email'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    </small>
                                </div>
                                <span class="std-feed-badge <?php echo htmlspecialchars(maire_classe_badge_statut_consultation($statut), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(maire_libelle_statut_consultation($statut), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </header>

                            <p style="margin:0.5rem 0;color:#374151;"><?php echo nl2br(htmlspecialchars((string) $c['question'], ENT_QUOTES, 'UTF-8')); ?></p>

                            <?php if ((int) $resultats['total'] > 0): ?>
                                <div style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin:0.8rem 0;">
                                    <div style="height:160px;">
                                        <canvas
                                            data-chart="doughnut"
                                            data-payload="<?php echo maire_chart_data_attr($resultats); ?>"></canvas>
                                    </div>
                                    <div>
                                        <?php
                                        $opts = maire_options_consultation($pdo, $cid);
                                        foreach ($opts as $i => $o):
                                            $nb = (int) $o['nb_votes'];
                                            $pct = $resultats['total'] > 0 ? round($nb * 100 / $resultats['total']) : 0;
                                        ?>
                                            <div style="margin-bottom:0.35rem;font-size:0.9rem;">
                                                <span><?php echo htmlspecialchars((string) $o['libelle'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <strong style="float:right;"><?php echo $nb; ?> · <?php echo $pct; ?>%</strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="detail-actions" style="margin-top:0.6rem;">
                                <a class="btn btn-outline-dark" href="../consultation.php?id=<?php echo $cid; ?>" target="_blank">Voir la page publique ↗</a>

                                <?php if ($statut === 'brouillon'): ?>
                                    <form method="POST" action="consultations.php" style="display:inline;">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="changer_statut">
                                        <input type="hidden" name="id" value="<?php echo $cid; ?>">
                                        <input type="hidden" name="nouveau_statut" value="ouverte">
                                        <button type="submit" class="btn btn-primary">📢 Publier</button>
                                    </form>
                                <?php elseif ($statut === 'ouverte'): ?>
                                    <form method="POST" action="consultations.php" style="display:inline;">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="changer_statut">
                                        <input type="hidden" name="id" value="<?php echo $cid; ?>">
                                        <input type="hidden" name="nouveau_statut" value="fermee">
                                        <button type="submit" class="btn btn-outline-dark">🔒 Clôturer</button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" action="consultations.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cette consultation et tous ses votes ?');">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id" value="<?php echo $cid; ?>">
                                    <button type="submit" class="btn btn-outline-dark" style="color:#dc2626;">🗑 Supprimer</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord admin</a>
                    <a class="btn btn-outline-dark" href="notifications.php">Notifications</a>
                    <a class="btn btn-outline-dark" href="../consultations.php">Page publique</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
