<?php
declare(strict_types=1);

/**
 * Suivi des paiements (lecture seule + filtrage simple).
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';

$filtreStatut = (string) ($_GET['statut'] ?? '');
$filtreEmail = trim((string) ($_GET['email'] ?? ''));
$paiements = [];
$totalValide = 0;
$totalRemb = 0;

try {
    $sql = 'SELECT p.id, p.email, p.montant_fcfa, p.mode_paiement, p.reference_paiement, p.statut, p.created_at, p.frequence
            FROM paiements_abonnements p WHERE 1=1';
    $params = [];
    if ($filtreStatut !== '' && in_array($filtreStatut, ['valide', 'rembourse', 'echec', 'en_attente'], true)) {
        $sql .= ' AND p.statut = :st';
        $params['st'] = $filtreStatut;
    }
    if ($filtreEmail !== '') {
        $sql .= ' AND p.email LIKE :em';
        $params['em'] = '%' . $filtreEmail . '%';
    }
    $sql .= ' ORDER BY p.created_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $paiements = $stmt->fetchAll();

    $totalValide = (int) $pdo->query("SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiements_abonnements WHERE statut = 'valide'")->fetchColumn();
    $totalRemb = (int) $pdo->query("SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiements_abonnements WHERE statut = 'rembourse'")->fetchColumn();
} catch (Throwable $e) {
    $paiements = [];
}

$pageTitle = 'Espace éditeur · Paiements';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero" style="background:linear-gradient(120deg,#1f2a44,#0f172a);color:#fff;">
        <div class="container">
            <span class="detail-kicker" style="color:#cbd5f5;">Console éditeur</span>
            <h1 style="color:#fff;">Suivi des paiements</h1>
            <p style="color:#e2e8f0;">200 derniers paiements (lecture seule). Total encaissé valide : <strong style="color:#fff;"><?php echo number_format((float) $totalValide, 0, ',', ' '); ?> F CFA</strong> · remboursé : <?php echo number_format((float) $totalRemb, 0, ',', ' '); ?> F CFA.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">
            <article class="card">
                <form method="GET" action="paiements.php" style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:end;">
                    <div style="flex:1 1 14rem;">
                        <label for="statut" style="display:block;font-weight:600;">Statut</label>
                        <select id="statut" name="statut" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                            <option value="">(tous)</option>
                            <option value="valide" <?php echo $filtreStatut === 'valide' ? 'selected' : ''; ?>>valide</option>
                            <option value="rembourse" <?php echo $filtreStatut === 'rembourse' ? 'selected' : ''; ?>>remboursé</option>
                            <option value="echec" <?php echo $filtreStatut === 'echec' ? 'selected' : ''; ?>>échec</option>
                            <option value="en_attente" <?php echo $filtreStatut === 'en_attente' ? 'selected' : ''; ?>>en attente</option>
                        </select>
                    </div>
                    <div style="flex:2 1 18rem;">
                        <label for="email" style="display:block;font-weight:600;">Email contient</label>
                        <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($filtreEmail, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a class="btn btn-outline-dark" href="paiements.php">Réinitialiser</a>
                    </div>
                </form>
            </article>

            <article class="card" style="overflow-x:auto;">
                <?php if (empty($paiements)): ?>
                    <p>Aucun paiement trouvé.</p>
                <?php else: ?>
                    <table class="std-table" style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f1f5f9;">
                                <th style="padding:0.5rem;text-align:left;">Date</th>
                                <th style="padding:0.5rem;text-align:left;">Email</th>
                                <th style="padding:0.5rem;text-align:right;">Montant F CFA</th>
                                <th style="padding:0.5rem;text-align:left;">Mode</th>
                                <th style="padding:0.5rem;text-align:left;">Référence</th>
                                <th style="padding:0.5rem;text-align:left;">Fréquence</th>
                                <th style="padding:0.5rem;text-align:left;">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paiements as $p): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($p['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($p['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;text-align:right;"><?php echo number_format((float) ($p['montant_fcfa'] ?? 0), 0, ',', ' '); ?></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($p['mode_paiement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;"><code><?php echo htmlspecialchars((string) ($p['reference_paiement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($p['frequence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;">
                                    <?php $st = (string) ($p['statut'] ?? ''); ?>
                                    <span class="std-feed-badge std-feed-badge--<?php echo $st === 'valide' ? 'success' : ($st === 'rembourse' ? 'warning' : 'danger'); ?>">
                                        <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </article>

            <article class="card">
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord</a>
                    <a class="btn btn-outline-dark" href="abonnement.php">Gérer l’abonnement</a>
                    <a class="btn btn-outline-dark" href="journal.php">Historique</a>
                </div>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
