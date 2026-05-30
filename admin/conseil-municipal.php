<?php
declare(strict_types=1);

/**
 * Console admin — Gestion des sessions du conseil municipal.
 * Feature gating : streaming_conseils (Premium).
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/conseil-sessions.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'streaming_conseils')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('streaming_conseils', $palierCommune, 'admin');
    exit;
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console'));
        switch ($action) {
            case 'creer':
                $err = null;
                $id = maire_creer_session_conseil($pdo, [
                    'titre' => (string) ($_POST['titre'] ?? ''),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'date_session' => (string) ($_POST['date_session'] ?? ''),
                    'duree_minutes' => (int) ($_POST['duree_minutes'] ?? 90),
                    'statut' => (string) ($_POST['statut'] ?? 'annonce'),
                    'plateforme' => (string) ($_POST['plateforme'] ?? 'youtube'),
                    'embed_url' => (string) ($_POST['embed_url'] ?? ''),
                    'ordre_du_jour' => (string) ($_POST['ordre_du_jour'] ?? ''),
                    'proces_verbal_url' => (string) ($_POST['proces_verbal_url'] ?? ''),
                ], $email, $err);
                if ($id === null) {
                    $flash = $err ?? 'Création impossible.';
                    $flashType = 'danger';
                } else {
                    $flash = 'Session n°' . $id . ' enregistrée.';
                }
                break;

            case 'mise_a_jour':
                $err = null;
                $id = (int) ($_POST['id'] ?? 0);
                if (maire_mettre_a_jour_session_conseil($pdo, $id, [
                    'statut' => (string) ($_POST['statut'] ?? ''),
                    'plateforme' => (string) ($_POST['plateforme'] ?? ''),
                    'embed_url' => (string) ($_POST['embed_url'] ?? ''),
                ], $err)) {
                    $flash = 'Session #' . $id . ' mise à jour.';
                } else {
                    $flash = $err ?? 'Mise à jour impossible.';
                    $flashType = 'danger';
                }
                break;

            case 'supprimer':
                $id = (int) ($_POST['id'] ?? 0);
                if (maire_supprimer_session_conseil($pdo, $id)) {
                    $flash = 'Session supprimée.';
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

$sessions = $pdo !== null ? maire_liste_sessions_conseil_admin($pdo, 100) : [];
$pageTitle = 'Espace mairie · Conseil municipal';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Démocratie locale</span>
            <h1>Conseil municipal · Streaming</h1>
            <p>Annoncez vos sessions, intégrez le live YouTube/Vimeo/Twitch et conservez les replays + procès-verbaux.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : 'success'; ?>" style="margin:0;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>➕ Programmer une nouvelle session</h2>
                <form method="POST" action="conseil-municipal.php" style="display:grid;gap:0.7rem;">
                    <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                    <input type="hidden" name="action" value="creer">

                    <div>
                        <label for="titre" style="display:block;font-weight:600;">Titre *</label>
                        <input type="text" id="titre" name="titre" required maxlength="200" placeholder="Conseil municipal du 15 mai 2026" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 150px;gap:0.6rem;">
                        <div>
                            <label for="date_session" style="display:block;font-weight:600;">Date / heure *</label>
                            <input type="datetime-local" id="date_session" name="date_session" required value="<?php echo date('Y-m-d') . 'T15:00'; ?>" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                        <div>
                            <label for="duree_minutes" style="display:block;font-weight:600;">Durée (min)</label>
                            <input type="number" id="duree_minutes" name="duree_minutes" value="90" min="15" max="720" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.6rem;">
                        <div>
                            <label for="plateforme" style="display:block;font-weight:600;">Plateforme</label>
                            <select id="plateforme" name="plateforme" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <?php foreach (MAIRE_CONSEIL_PLATEFORMES as $k => $lbl): ?>
                                    <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="embed_url" style="display:block;font-weight:600;">URL du live / replay</label>
                            <input type="url" id="embed_url" name="embed_url" placeholder="https://www.youtube.com/watch?v=..." style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div>
                        <label for="statut" style="display:block;font-weight:600;">Statut initial</label>
                        <select id="statut" name="statut" style="width:auto;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <?php foreach (MAIRE_CONSEIL_STATUTS as $k => $lbl): ?>
                                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="description" style="display:block;font-weight:600;">Description (facultatif)</label>
                        <textarea id="description" name="description" rows="2" maxlength="4000" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="ordre_du_jour" style="display:block;font-weight:600;">Ordre du jour (facultatif)</label>
                        <textarea id="ordre_du_jour" name="ordre_du_jour" rows="4" maxlength="4000" placeholder="1. Budget 2026&#10;2. Aménagement de la place du marché&#10;3. ..." style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="proces_verbal_url" style="display:block;font-weight:600;">URL du procès-verbal (facultatif)</label>
                        <input type="url" id="proces_verbal_url" name="proces_verbal_url" placeholder="https://..." style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>

                    <div class="detail-actions">
                        <button type="submit" class="btn btn-primary">💾 Enregistrer la session</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Toutes les sessions (<?php echo count($sessions); ?>)</h2>
                <?php if (empty($sessions)): ?>
                    <p>Aucune session enregistrée pour l’instant.</p>
                <?php else: ?>
                    <div style="display:grid;gap:0.8rem;">
                    <?php foreach ($sessions as $s):
                        $sid = (int) $s['id'];
                        $st = (string) $s['statut']; ?>
                        <article style="border:1px solid #e2e8f0;border-radius:10px;padding:1rem;background:#fff;">
                            <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.6rem;flex-wrap:wrap;">
                                <div>
                                    <strong style="font-size:1.05rem;">#<?php echo $sid; ?> · <?php echo htmlspecialchars((string) $s['titre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <br><small style="color:#64748b;">
                                        <?php echo htmlspecialchars((string) $s['date_session'], ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo (int) $s['duree_minutes']; ?> min
                                        · <?php echo htmlspecialchars(maire_conseil_libelle_plateforme((string) $s['plateforme']), ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo (int) $s['nb_vues']; ?> vue(s)
                                    </small>
                                </div>
                                <span class="std-feed-badge <?php echo htmlspecialchars(maire_conseil_classe_badge($st), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(maire_conseil_libelle_statut($st), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </header>

                            <form method="POST" action="conseil-municipal.php" style="margin-top:0.5rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                                <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                                <input type="hidden" name="action" value="mise_a_jour">
                                <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                <select name="statut" style="padding:0.4rem;border:1px solid #cbd5e1;border-radius:6px;">
                                    <?php foreach (MAIRE_CONSEIL_STATUTS as $k => $lbl): ?>
                                        <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $k === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="plateforme" style="padding:0.4rem;border:1px solid #cbd5e1;border-radius:6px;">
                                    <?php foreach (MAIRE_CONSEIL_PLATEFORMES as $k => $lbl): ?>
                                        <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $k === (string) $s['plateforme'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="url" name="embed_url" placeholder="URL embed (laisser vide pour ne pas changer)" style="flex:1;min-width:240px;padding:0.4rem;border:1px solid #cbd5e1;border-radius:6px;">
                                <button type="submit" class="btn btn-primary" style="padding:0.4rem 0.9rem;">Appliquer</button>
                            </form>

                            <div class="detail-actions" style="margin-top:0.5rem;">
                                <a class="btn btn-outline-dark" href="../conseil-municipal.php?id=<?php echo $sid; ?>" target="_blank">Voir page publique ↗</a>
                                <form method="POST" action="conseil-municipal.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cette session ?');">
                                    <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id" value="<?php echo $sid; ?>">
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
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord</a>
                    <a class="btn btn-outline-dark" href="../conseil-municipal.php">Voir page publique</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
