<?php
declare(strict_types=1);

/**
 * Console admin — composer & envoyer des notifications mass\u00e9es aux citoyens.
 * Verrouill\u00e9 \u00e0 partir du palier Standard (feature `notifications_email`).
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/maire-rate-limit.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'notifications_email')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('notifications_email', $palierCommune, 'admin');
    exit;
}

if ($pdo !== null) {
    maire_ensure_notifications_tables($pdo);
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } elseif (!maire_rate_limit_allow('notif_send', 6, 60)) {
        $flash = 'Trop d’envois rapprochés. Attendez une minute avant de relancer.';
        $flashType = 'danger';
    } else {
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console'));
        $err = null;
        $id = maire_creer_et_envoyer_notification(
            $pdo,
            [
                'categorie' => (string) ($_POST['categorie'] ?? 'info'),
                'canal' => (string) ($_POST['canal'] ?? 'email'),
                'sujet' => (string) ($_POST['sujet'] ?? ''),
                'message' => (string) ($_POST['message'] ?? ''),
                'cible_quartier' => (string) ($_POST['cible_quartier'] ?? ''),
            ],
            $email,
            $err
        );
        if ($id === null) {
            $flash = $err ?? 'Envoi impossible.';
            $flashType = 'danger';
        } else {
            $notif = maire_load_notification($pdo, $id);
            $flash = sprintf(
                'Notification n°%d envoyée — %d destinataires · %d OK · %d échec(s).',
                $id,
                (int) ($notif['nb_destinataires'] ?? 0),
                (int) ($notif['nb_envois_ok'] ?? 0),
                (int) ($notif['nb_envois_ko'] ?? 0)
            );
            if ((int) ($notif['nb_envois_ko'] ?? 0) > 0) {
                $flashType = 'danger';
            }
        }
    }
}

$historique = $pdo !== null ? maire_liste_notifications($pdo, 30) : [];
$compteurs = $pdo !== null ? maire_compter_notifications($pdo) : ['total' => 0, 'envoyees' => 0, 'echecs' => 0, 'destinataires' => 0];
$quartiers = $pdo !== null ? maire_liste_quartiers($pdo) : [];
$providerLibelle = maire_sms_provider_actuel_libelle();

$pageTitle = 'Espace mairie · Notifications';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Communication</span>
            <h1>Notifications aux habitants</h1>
            <p>Envoyez des alertes (urgence météo, coupure d’eau), des invitations à des événements ou des informations en email + SMS.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Vue d’ensemble</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) $compteurs['total']; ?></strong>
                        <span>Diffusions au total</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) $compteurs['envoyees']; ?></strong>
                        <span>Envoyées avec succès</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#dc2626;"><?php echo (int) $compteurs['echecs']; ?></strong>
                        <span>En échec</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo number_format((int) $compteurs['destinataires'], 0, ',', ' '); ?></strong>
                        <span>Destinataires touchés</span>
                    </article>
                </div>
                <p class="std-dash-note" style="margin-top:0.8rem;">
                    Provider SMS actuel : <strong><?php echo htmlspecialchars($providerLibelle, ENT_QUOTES, 'UTF-8'); ?></strong>.
                    En mode journal, les SMS sont consignés dans <code>logs/sms-outbox.log</code> pour validation sans coût.
                </p>
            </article>

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : 'success'; ?>" style="margin:0;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>📤 Composer un nouveau message</h2>
                <form method="POST" action="notifications.php" style="display:grid;gap:0.7rem;">
                    <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.6rem;">
                        <div>
                            <label for="categorie" style="display:block;font-weight:600;">Catégorie *</label>
                            <select id="categorie" name="categorie" required style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <?php foreach (MAIRE_NOTIFICATIONS_CATEGORIES as $code => $lbl): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="canal" style="display:block;font-weight:600;">Canal *</label>
                            <select id="canal" name="canal" required style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <?php foreach (MAIRE_NOTIFICATIONS_CANAUX as $code => $lbl): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="cible_quartier" style="display:block;font-weight:600;">Cible (quartier)</label>
                            <select id="cible_quartier" name="cible_quartier" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <option value="">— Tous les quartiers —</option>
                                <?php foreach ($quartiers as $q): ?>
                                    <option value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="sujet" style="display:block;font-weight:600;">Sujet (titre) *</label>
                        <input type="text" id="sujet" name="sujet" required maxlength="180" placeholder="Ex : Coupure d'eau prévue mercredi matin" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>

                    <div>
                        <label for="message" style="display:block;font-weight:600;">Message *</label>
                        <textarea id="message" name="message" required maxlength="5000" rows="5" placeholder="Soyez clair et bref. Le SMS sera tronqué à 280 caractères." style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                        <small style="color:#64748b;">Le message email inclut automatiquement la signature de la mairie.</small>
                    </div>

                    <div class="detail-actions" style="margin-top:0.4rem;">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmer l\'envoi à tous les destinataires sélectionnés ?');">📨 Envoyer maintenant</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Historique des envois (<?php echo count($historique); ?>)</h2>
                <?php if (empty($historique)): ?>
                    <p>Aucune notification envoyée pour l’instant.</p>
                <?php else: ?>
                    <div style="display:grid;gap:0.8rem;">
                        <?php foreach ($historique as $n):
                            $cat = (string) ($n['categorie'] ?? 'info');
                            $statut = (string) ($n['statut'] ?? 'en_attente');
                        ?>
                            <article style="border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem;background:#fff;">
                                <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.6rem;flex-wrap:wrap;">
                                    <div>
                                        <strong>#<?php echo (int) $n['id']; ?> · <?php echo htmlspecialchars((string) $n['sujet'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br><small style="color:#64748b;">
                                            <?php echo htmlspecialchars(maire_libelle_categorie_notification($cat), ENT_QUOTES, 'UTF-8'); ?>
                                            · canal <?php echo htmlspecialchars((string) $n['canal'], ENT_QUOTES, 'UTF-8'); ?>
                                            · <?php echo htmlspecialchars(substr((string) ($n['created_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($n['cible_quartier'])): ?>· quartier <?php echo htmlspecialchars((string) $n['cible_quartier'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                            <?php if (!empty($n['envoye_par_email'])): ?>· par <?php echo htmlspecialchars((string) $n['envoye_par_email'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                        </small>
                                    </div>
                                    <span class="std-feed-badge <?php echo $statut === 'envoye' ? 'std-feed-badge--success' : ($statut === 'echec' ? 'std-feed-badge--error' : 'std-feed-badge--warning'); ?>">
                                        <?php echo htmlspecialchars(maire_libelle_statut_notification($statut), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </header>

                                <p style="margin:0.5rem 0;color:#374151;white-space:pre-line;"><?php echo htmlspecialchars((string) $n['message'], ENT_QUOTES, 'UTF-8'); ?></p>

                                <small style="color:#64748b;">
                                    Destinataires : <strong><?php echo (int) ($n['nb_destinataires'] ?? 0); ?></strong>
                                    · <span style="color:#16a34a;"><?php echo (int) ($n['nb_envois_ok'] ?? 0); ?> OK</span>
                                    <?php if ((int) ($n['nb_envois_ko'] ?? 0) > 0): ?>
                                        · <span style="color:#dc2626;"><?php echo (int) ($n['nb_envois_ko'] ?? 0); ?> échec(s)</span>
                                    <?php endif; ?>
                                </small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord admin</a>
                    <a class="btn btn-outline-dark" href="signalements.php">Signalements</a>
                    <a class="btn btn-outline-dark" href="documents.php">Documents</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
