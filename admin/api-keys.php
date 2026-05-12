<?php
declare(strict_types=1);

/**
 * Console admin — Clés d'API publique (Phase X).
 * Feature gating : api_publique (Premium).
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/api-keys.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'api_publique')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('api_publique', $palierCommune, 'admin');
    exit;
}

if (empty($_SESSION['abo_admin_csrf'])) {
    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
}

$flash = '';
$flashType = 'success';
$nouvelleCle = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['abo_admin_csrf'], $csrf)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console'));
        switch ($action) {
            case 'creer':
                $err = null;
                $cle = maire_creer_api_key(
                    $pdo,
                    (string) ($_POST['libelle'] ?? ''),
                    (string) ($_POST['scopes'] ?? 'public'),
                    (int) ($_POST['rate_limit'] ?? 60),
                    $email,
                    $err
                );
                if ($cle === null) {
                    $flash = $err ?? 'Création impossible.';
                    $flashType = 'danger';
                } else {
                    $nouvelleCle = $cle['cle'];
                    $flash = 'Clé créée avec succès. Copiez-la maintenant — elle ne sera plus jamais affichée.';
                }
                break;
            case 'revoquer':
                $id = (int) ($_POST['id'] ?? 0);
                if (maire_revoquer_api_key($pdo, $id)) {
                    $flash = 'Clé révoquée.';
                } else { $flash = 'Révocation impossible.'; $flashType = 'danger'; }
                break;
            case 'supprimer':
                $id = (int) ($_POST['id'] ?? 0);
                if (maire_supprimer_api_key($pdo, $id)) {
                    $flash = 'Clé supprimée définitivement.';
                } else { $flash = 'Suppression impossible.'; $flashType = 'danger'; }
                break;
            default:
                $flash = 'Action inconnue.';
                $flashType = 'danger';
        }
    }
}

$cles = $pdo !== null ? maire_liste_api_keys($pdo) : [];
$compteurs = $pdo !== null ? maire_api_compteurs($pdo) : ['total_cles' => 0, 'cles_actives' => 0, 'appels_total' => 0, 'appels_24h' => 0];

$pageTitle = 'Espace mairie · API publique';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Intégrations</span>
            <h1>API publique &amp; clés d’accès</h1>
            <p>Générez des clés d’API pour intégrer les données municipales (actualités, documents, signalements) à des applications tierces.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Vue d’ensemble</h2>
                <div class="services-stats" style="margin-top:0.5rem;">
                    <article class="stat-chip"><strong><?php echo (int) $compteurs['total_cles']; ?></strong><span>Clés totales</span></article>
                    <article class="stat-chip"><strong style="color:#16a34a;"><?php echo (int) $compteurs['cles_actives']; ?></strong><span>Actives</span></article>
                    <article class="stat-chip"><strong><?php echo number_format((int) $compteurs['appels_total'], 0, ',', ' '); ?></strong><span>Appels cumulés</span></article>
                    <article class="stat-chip"><strong style="color:#0c4a3e;"><?php echo number_format((int) $compteurs['appels_24h'], 0, ',', ' '); ?></strong><span>Sur 24h</span></article>
                </div>
            </article>

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : 'success'; ?>" style="margin:0;"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endif; ?>

            <?php if ($nouvelleCle !== null): ?>
                <article class="card" style="border:2px solid #16a34a;background:#f0fdf4;">
                    <h2>🔑 Votre nouvelle clé</h2>
                    <p>Copiez cette clé maintenant : elle ne sera plus affichée. Stockez-la dans un coffre-fort de secrets côté votre application.</p>
                    <pre style="background:#0c4a3e;color:#fff;padding:1rem;border-radius:8px;overflow-x:auto;font-family:'Courier New',monospace;font-size:0.95rem;"><?php echo htmlspecialchars($nouvelleCle, ENT_QUOTES, 'UTF-8'); ?></pre>
                    <p class="std-dash-note">Exemple d’appel : <code>curl -H "Authorization: Bearer <?php echo htmlspecialchars($nouvelleCle, ENT_QUOTES, 'UTF-8'); ?>" /api/v1/signalements</code></p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>➕ Générer une nouvelle clé</h2>
                <form method="POST" action="api-keys.php" style="display:grid;gap:0.6rem;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="creer">
                    <div>
                        <label for="libelle" style="display:block;font-weight:600;">Libellé *</label>
                        <input type="text" id="libelle" name="libelle" required maxlength="120" placeholder="Ex : Application mobile citoyenne v2" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:0.6rem;">
                        <div>
                            <label for="scopes" style="display:block;font-weight:600;">Portée (scopes)</label>
                            <input type="text" id="scopes" name="scopes" value="public,signalements:read,documents:read" maxlength="500" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <small style="color:#64748b;">Liste de scopes séparés par virgules (purement informatif pour le moment).</small>
                        </div>
                        <div>
                            <label for="rate_limit" style="display:block;font-weight:600;">Quota / minute</label>
                            <input type="number" id="rate_limit" name="rate_limit" value="60" min="10" max="1000" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>
                    <div class="detail-actions">
                        <button type="submit" class="btn btn-primary">🔑 Créer la clé</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Clés existantes (<?php echo count($cles); ?>)</h2>
                <?php if (empty($cles)): ?>
                    <p>Aucune clé créée pour l’instant.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
                        <thead><tr style="background:#f1f5f9;text-align:left;">
                            <th style="padding:0.5rem;">Libellé</th>
                            <th style="padding:0.5rem;">Préfixe</th>
                            <th style="padding:0.5rem;">Statut</th>
                            <th style="padding:0.5rem;">Quota/min</th>
                            <th style="padding:0.5rem;">Appels</th>
                            <th style="padding:0.5rem;">Dernier accès</th>
                            <th style="padding:0.5rem;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($cles as $k): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) $k['libelle'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;"><code><?php echo htmlspecialchars((string) $k['cle_prefix'], ENT_QUOTES, 'UTF-8'); ?>…</code></td>
                                <td style="padding:0.5rem;"><span class="std-feed-badge <?php echo (int) $k['actif'] === 1 ? 'std-feed-badge--success' : 'std-feed-badge--warning'; ?>"><?php echo (int) $k['actif'] === 1 ? 'Active' : 'Révoquée'; ?></span></td>
                                <td style="padding:0.5rem;"><?php echo (int) $k['rate_limit_per_min']; ?></td>
                                <td style="padding:0.5rem;"><?php echo number_format((int) $k['nb_appels'], 0, ',', ' '); ?></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($k['derniere_utilisation'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;">
                                    <?php if ((int) $k['actif'] === 1): ?>
                                        <form method="POST" action="api-keys.php" style="display:inline;" onsubmit="return confirm('Révoquer cette clé ? Les appels en cours échoueront immédiatement.');">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="revoquer">
                                            <input type="hidden" name="id" value="<?php echo (int) $k['id']; ?>">
                                            <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;">🚫 Révoquer</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="api-keys.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cette clé ?');">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id" value="<?php echo (int) $k['id']; ?>">
                                        <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;color:#dc2626;">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>📚 Documentation de l’API</h2>
                <p>L’API est accessible en lecture seule via <code>/api/v1/</code>. Authentification par header <code>Authorization: Bearer &lt;cle&gt;</code> ou <code>X-API-Key</code>.</p>
                <ul>
                    <li><code>GET /api/v1/</code> — manifeste (pas d’auth)</li>
                    <li><code>GET /api/v1/actualites</code> — actualités publiées</li>
                    <li><code>GET /api/v1/documents</code> — documents publics</li>
                    <li><code>GET /api/v1/consultations</code> — consultations citoyennes</li>
                    <li><code>GET /api/v1/paiements</code> — catalogue des services payants</li>
                    <li><code>GET /api/v1/signalements</code> — signalements (auth requise, anonymisés)</li>
                </ul>
                <p class="std-dash-note">Quotas par défaut : 60 requêtes/minute par clé. Format JSON avec pagination <code>?page=1&amp;limit=20</code>.</p>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
