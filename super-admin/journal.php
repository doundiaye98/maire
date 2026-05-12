<?php
declare(strict_types=1);

/**
 * Historique complet des événements sur l'abonnement communal,
 * y compris les actions de l'éditeur (source = 'editeur').
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';
require_once __DIR__ . '/../includes/commune-abonnement-historique.php';

$evenements = [];
try {
    maire_ensure_commune_abonnement_historique_table($pdo);
    $stmt = $pdo->query('
        SELECT id, plan, actif, date_debut, date_fin, evenement, detail, actor_source, created_at
        FROM commune_abonnement_historique
        ORDER BY created_at DESC
        LIMIT 200
    ');
    $evenements = $stmt->fetchAll();
} catch (Throwable $e) {
    $evenements = [];
}

$pageTitle = 'Espace éditeur · Journal';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero" style="background:linear-gradient(120deg,#1f2a44,#0f172a);color:#fff;">
        <div class="container">
            <span class="detail-kicker" style="color:#cbd5f5;">Console éditeur</span>
            <h1 style="color:#fff;">Journal des événements</h1>
            <p style="color:#e2e8f0;">200 derniers événements survenus sur l’abonnement communal (toutes sources confondues).</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card" style="overflow-x:auto;">
                <?php if (empty($evenements)): ?>
                    <p>Aucun événement enregistré pour l’instant.</p>
                <?php else: ?>
                    <table class="std-table" style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f1f5f9;">
                                <th style="padding:0.5rem;text-align:left;">Date</th>
                                <th style="padding:0.5rem;text-align:left;">Événement</th>
                                <th style="padding:0.5rem;text-align:left;">Source</th>
                                <th style="padding:0.5rem;text-align:left;">Plan</th>
                                <th style="padding:0.5rem;text-align:left;">Période</th>
                                <th style="padding:0.5rem;text-align:left;">Actif</th>
                                <th style="padding:0.5rem;text-align:left;">Détail</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($evenements as $ev): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:0.5rem;white-space:nowrap;"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;">
                                    <strong><?php echo htmlspecialchars(maire_libelle_evenement_commune((string) ($ev['evenement'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <small style="color:#64748b;"><?php echo htmlspecialchars((string) ($ev['evenement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td style="padding:0.5rem;">
                                    <?php $src = (string) ($ev['actor_source'] ?? ''); ?>
                                    <span class="std-feed-badge std-feed-badge--<?php echo $src === 'editeur' ? 'success' : 'warning'; ?>">
                                        <?php echo htmlspecialchars(maire_libelle_actor_source_commune($src), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($ev['plan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;white-space:nowrap;"><?php echo htmlspecialchars((string) ($ev['date_debut'] ?? '') . ' → ' . (string) ($ev['date_fin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:0.5rem;"><?php echo ((int) ($ev['actif'] ?? 0) === 1) ? 'Oui' : 'Non'; ?></td>
                                <td style="padding:0.5rem;"><?php echo htmlspecialchars((string) ($ev['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
                    <a class="btn btn-outline-dark" href="paiements.php">Paiements</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
