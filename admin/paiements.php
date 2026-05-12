<?php
declare(strict_types=1);

/**
 * Console admin — Paiements en ligne.
 *
 * - Vue d'ensemble (compteurs, recettes encaissées et en attente)
 * - Filtres par statut + catégorie
 * - Liste détaillée avec actions : marquer payé / échec / annulé / rembourser
 *   (utile pour les paiements "log" et pour les corrections manuelles)
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/paiements.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'paiements_en_ligne')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('paiements_en_ligne', $palierCommune, 'admin');
    exit;
}

if ($pdo !== null) {
    maire_ensure_paiements_table($pdo);
}

if (empty($_SESSION['abo_admin_csrf'])) {
    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['abo_admin_csrf'], $csrf)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $statutCible = (string) ($_POST['statut'] ?? '');
        if ($action === 'changer_statut' && $id > 0 && array_key_exists($statutCible, MAIRE_PAIEMENTS_STATUTS)) {
            if (maire_paiement_changer_statut_admin($pdo, $id, $statutCible)) {
                $flash = 'Statut mis à jour : ' . maire_paiement_libelle_statut($statutCible) . '.';
            } else {
                $flash = 'Mise à jour impossible.';
                $flashType = 'danger';
            }
        } else {
            $flash = 'Action invalide.';
            $flashType = 'danger';
        }
    }
}

$filtreStatut = $_GET['statut'] ?? null;
$filtreCategorie = $_GET['categorie'] ?? null;
if ($filtreStatut !== null && !array_key_exists($filtreStatut, MAIRE_PAIEMENTS_STATUTS)) {
    $filtreStatut = null;
}
if ($filtreCategorie !== null && !array_key_exists($filtreCategorie, MAIRE_PAIEMENTS_CATEGORIES)) {
    $filtreCategorie = null;
}

$compteurs = $pdo !== null ? maire_paiements_compteurs($pdo) : ['total' => 0, 'initie' => 0, 'en_attente' => 0, 'paye' => 0, 'echec' => 0, 'montant_paye' => 0, 'montant_attente' => 0];
$paiements = $pdo !== null ? maire_paiements_liste($pdo, $filtreStatut, $filtreCategorie, 200) : [];

$pageTitle = 'Espace mairie · Paiements';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Trésorerie</span>
            <h1>Paiements en ligne</h1>
            <p>Suivez en temps réel les recettes encaissées (taxes, documents express, réservations) et corrigez si nécessaire.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Recettes &amp; transactions</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo maire_paiement_format_montant((float) $compteurs['montant_paye']); ?></strong>
                        <span>Encaissé</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#f59e0b;"><?php echo maire_paiement_format_montant((float) $compteurs['montant_attente']); ?></strong>
                        <span>En attente</span>
                    </article>
                    <article class="stat-chip">
                        <strong><?php echo (int) $compteurs['total']; ?></strong>
                        <span>Transactions</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) $compteurs['paye']; ?></strong>
                        <span>Payées</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#f59e0b;"><?php echo (int) ($compteurs['en_attente'] + $compteurs['initie']); ?></strong>
                        <span>En cours</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#dc2626;"><?php echo (int) $compteurs['echec']; ?></strong>
                        <span>Échecs</span>
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
                <h2>🔍 Filtres</h2>
                <form method="GET" action="paiements.php" style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:end;">
                    <div>
                        <label for="statut" style="display:block;font-weight:600;font-size:0.9rem;">Statut</label>
                        <select id="statut" name="statut" style="padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <option value="">— Tous —</option>
                            <?php foreach (MAIRE_PAIEMENTS_STATUTS as $k => $lbl): ?>
                                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreStatut === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="categorie" style="display:block;font-weight:600;font-size:0.9rem;">Catégorie</label>
                        <select id="categorie" name="categorie" style="padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <option value="">— Toutes —</option>
                            <?php foreach (MAIRE_PAIEMENTS_CATEGORIES as $k => $lbl): ?>
                                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreCategorie === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a class="btn btn-outline-dark" href="paiements.php">Réinitialiser</a>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Liste des transactions (<?php echo count($paiements); ?>)</h2>
                <?php if (empty($paiements)): ?>
                    <p>Aucune transaction pour les filtres choisis.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                        <thead>
                            <tr style="background:#f1f5f9;text-align:left;">
                                <th style="padding:0.5rem;">Référence</th>
                                <th style="padding:0.5rem;">Date</th>
                                <th style="padding:0.5rem;">Service</th>
                                <th style="padding:0.5rem;">Payeur</th>
                                <th style="padding:0.5rem;">Montant</th>
                                <th style="padding:0.5rem;">Provider</th>
                                <th style="padding:0.5rem;">Statut</th>
                                <th style="padding:0.5rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paiements as $p):
                            $statut = (string) $p['statut'];
                            $payeur = $p['citoyen_email'] ?? ($p['visiteur_nom'] ?? '—');
                            if (!empty($p['citoyen_prenom'])) {
                                $payeur = trim((string) $p['citoyen_prenom'] . ' ' . (string) $p['citoyen_nom']) . ' · ' . (string) $p['citoyen_email'];
                            }
                        ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:0.5rem;"><code><?php echo htmlspecialchars((string) $p['reference'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) $p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;">
                                    <strong><?php echo htmlspecialchars((string) $p['service_libelle'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <br><small style="color:#64748b;"><?php echo htmlspecialchars(maire_paiement_libelle_categorie((string) $p['service_categorie']), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) $payeur, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($p['visiteur_telephone'])): ?><br><small><?php echo htmlspecialchars((string) $p['visiteur_telephone'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                </td>
                                <td style="padding:0.5rem;"><strong><?php echo maire_paiement_format_montant((float) $p['montant'], (string) $p['devise']); ?></strong></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars(maire_paiement_provider_libelle((string) $p['provider']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;">
                                    <span class="std-feed-badge <?php echo htmlspecialchars(maire_paiement_classe_badge($statut), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(maire_paiement_libelle_statut($statut), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="padding:0.5rem;">
                                    <?php if (in_array($statut, ['initie', 'en_attente'], true)): ?>
                                        <form method="POST" action="paiements.php" style="display:inline-block;margin-bottom:0.2rem;">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="changer_statut">
                                            <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                            <input type="hidden" name="statut" value="paye">
                                            <button type="submit" class="btn btn-primary" style="padding:0.3rem 0.6rem;font-size:0.85rem;">✓ Marquer payé</button>
                                        </form>
                                        <form method="POST" action="paiements.php" style="display:inline-block;">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="changer_statut">
                                            <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                            <input type="hidden" name="statut" value="annule">
                                            <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;">Annuler</button>
                                        </form>
                                    <?php elseif ($statut === 'paye'): ?>
                                        <form method="POST" action="paiements.php" style="display:inline-block;" onsubmit="return confirm('Marquer ce paiement comme remboursé ?');">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="changer_statut">
                                            <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                            <input type="hidden" name="statut" value="rembourse">
                                            <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;">⤺ Rembourser</button>
                                        </form>
                                    <?php else: ?>
                                        <small style="color:#64748b;">—</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord admin</a>
                    <a class="btn btn-outline-dark" href="../paiements.php">Voir page publique</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
