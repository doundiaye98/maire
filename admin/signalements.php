<?php
declare(strict_types=1);

/**
 * Console admin pour traiter les signalements citoyens.
 * Accessible aux comptes admin mairie, agents admin, console secrète et super-admin éditeur.
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/super-admin-session.php';
require_once __DIR__ . '/../includes/signalements.php';
require_once __DIR__ . '/../includes/site-paths.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'signalements_citoyens')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('signalements_citoyens', $palierCommune, 'admin');
    exit;
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if (!maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN)) {
        $flash = 'Jeton CSRF invalide.';
        $flashType = 'danger';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $statut = (string) ($_POST['statut'] ?? '');
        $notes = trim((string) ($_POST['admin_notes'] ?? ''));
        if ($id <= 0) {
            $flash = 'Signalement invalide.';
            $flashType = 'danger';
        } elseif (!array_key_exists($statut, MAIRE_SIGNALEMENTS_STATUTS)) {
            $flash = 'Statut invalide.';
            $flashType = 'danger';
        } else {
            $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console_secrete'));
            $ok = maire_mettre_a_jour_statut_signalement(
                $pdo,
                $id,
                $statut,
                $notes !== '' ? mb_substr($notes, 0, 4000) : null,
                $email
            );
            if ($ok) {
                $flash = 'Signalement n°' . $id . ' mis à jour (' . maire_libelle_statut_signalement($statut) . ').';
            } else {
                $flash = 'Aucune modification (déjà à ce statut ?).';
                $flashType = 'warning';
            }
        }
    }
}

$filtreStatut = (string) ($_GET['statut'] ?? '');
$filtreCategorie = (string) ($_GET['categorie'] ?? '');
$signalements = $pdo !== null ? maire_liste_signalements_admin($pdo, $filtreStatut !== '' ? $filtreStatut : null, $filtreCategorie !== '' ? $filtreCategorie : null, 200) : [];
$counts = $pdo !== null ? maire_compter_signalements_par_statut($pdo) : ['nouveau' => 0, 'pris_en_charge' => 0, 'resolu' => 0, 'rejete' => 0];
$urlPrefix = maire_url_prefix();

$pageTitle = 'Espace mairie · Signalements citoyens';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Administration</span>
            <h1>Signalements citoyens</h1>
            <p>Traitez les remontées des habitants : routes, lampadaires, déchets, inondations, sécurité…</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Vue d’ensemble</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong style="color:#f59e0b;"><?php echo (int) $counts['nouveau']; ?></strong>
                        <span>Nouveaux</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#0ea5e9;"><?php echo (int) $counts['pris_en_charge']; ?></strong>
                        <span>En cours</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) $counts['resolu']; ?></strong>
                        <span>Résolus</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#dc2626;"><?php echo (int) $counts['rejete']; ?></strong>
                        <span>Rejetés</span>
                    </article>
                </div>
            </article>

            <article class="card">
                <form method="GET" action="signalements.php" style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:end;">
                    <div style="flex:1 1 12rem;">
                        <label for="statut" style="display:block;font-weight:600;">Statut</label>
                        <select id="statut" name="statut" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <option value="">(tous)</option>
                            <?php foreach (MAIRE_SIGNALEMENTS_STATUTS as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreStatut === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1 1 14rem;">
                        <label for="categorie" style="display:block;font-weight:600;">Catégorie</label>
                        <select id="categorie" name="categorie" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;">
                            <option value="">(toutes)</option>
                            <?php foreach (MAIRE_SIGNALEMENTS_CATEGORIES as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtreCategorie === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a class="btn btn-outline-dark" href="signalements.php">Réinitialiser</a>
                    </div>
                </form>
            </article>

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : ($flashType === 'warning' ? 'info' : 'success'); ?>" style="margin:0;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card">
                <?php if (empty($signalements)): ?>
                    <p>Aucun signalement à afficher pour ce filtre.</p>
                <?php else: ?>
                    <div style="display:grid;gap:1rem;">
                    <?php foreach ($signalements as $s):
                        $statut = (string) ($s['statut'] ?? 'nouveau');
                        $cat = (string) ($s['categorie'] ?? 'autre');
                        $photoUrl = maire_url_photo_signalement((string) ($s['photo_path'] ?? ''), $urlPrefix);
                    ?>
                        <article style="border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem;background:#fff;">
                            <div style="display:grid;grid-template-columns:<?php echo $photoUrl ? '120px 1fr' : '1fr'; ?>;gap:0.9rem;">
                                <?php if ($photoUrl): ?>
                                    <a href="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;">
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.6rem;flex-wrap:wrap;">
                                        <div>
                                            <strong style="font-size:1.05rem;">#<?php echo (int) $s['id']; ?> · <?php echo htmlspecialchars((string) $s['titre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <br><small style="color:#64748b;">
                                                <?php echo htmlspecialchars(maire_libelle_categorie_signalement($cat), ENT_QUOTES, 'UTF-8'); ?>
                                                · Par <strong><?php echo htmlspecialchars(trim(((string) ($s['citoyen_prenom'] ?? '')) . ' ' . ((string) ($s['citoyen_nom'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                (<?php echo htmlspecialchars((string) ($s['citoyen_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($s['citoyen_telephone'])): ?>
                                                    · <?php echo htmlspecialchars((string) $s['citoyen_telephone'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>)
                                                · Créé le <?php echo htmlspecialchars((string) $s['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                        </div>
                                        <span class="std-feed-badge <?php echo htmlspecialchars(maire_classe_badge_statut($statut), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars(maire_libelle_statut_signalement($statut), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </header>

                                    <p style="margin:0.5rem 0;white-space:pre-line;"><?php echo nl2br(htmlspecialchars((string) $s['description'], ENT_QUOTES, 'UTF-8')); ?></p>

                                    <?php if (!empty($s['adresse_libre']) || (!empty($s['latitude']) && !empty($s['longitude']))): ?>
                                        <p class="std-dash-note" style="margin:0.3rem 0;">
                                            <?php if (!empty($s['adresse_libre'])): ?>
                                                <strong>Adresse :</strong> <?php echo htmlspecialchars((string) $s['adresse_libre'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($s['latitude']) && !empty($s['longitude'])): ?>
                                                <?php $url = 'https://www.openstreetmap.org/?mlat=' . urlencode((string) $s['latitude']) . '&mlon=' . urlencode((string) $s['longitude']) . '#map=18/' . urlencode((string) $s['latitude']) . '/' . urlencode((string) $s['longitude']); ?>
                                                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">📍 GPS (<?php echo htmlspecialchars((string) $s['latitude'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars((string) $s['longitude'], ENT_QUOTES, 'UTF-8'); ?>)</a>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($s['admin_notes'])): ?>
                                        <p style="margin:0.4rem 0;padding:0.4rem 0.7rem;background:#f1f5f9;border-left:3px solid #0c4a3e;border-radius:0 6px 6px 0;">
                                            <strong>Note interne :</strong> <?php echo nl2br(htmlspecialchars((string) $s['admin_notes'], ENT_QUOTES, 'UTF-8')); ?>
                                            <?php if (!empty($s['traite_par_email'])): ?>
                                                <br><small style="color:#64748b;">Par <?php echo htmlspecialchars((string) $s['traite_par_email'], ENT_QUOTES, 'UTF-8'); ?> le <?php echo htmlspecialchars((string) ($s['traite_le'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr style="margin:0.8rem 0;border:none;border-top:1px dashed #cbd5e1;">

                            <form method="POST" action="signalements.php" style="display:grid;gap:0.5rem;">
                                <?php echo maire_csrf_field(MAIRE_CSRF_SCOPE_ADMIN); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                                    <label for="statut_<?php echo (int) $s['id']; ?>" style="font-weight:600;">Statut</label>
                                    <select id="statut_<?php echo (int) $s['id']; ?>" name="statut" required style="padding:0.4rem;border:1px solid #cbd5e1;border-radius:6px;">
                                        <?php foreach (MAIRE_SIGNALEMENTS_STATUTS as $code => $label): ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statut === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                                <textarea name="admin_notes" maxlength="4000" rows="2" placeholder="Note ou réponse adressée au citoyen…" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;"><?php echo htmlspecialchars((string) ($s['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </form>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord admin</a>
                    <a class="btn btn-outline-dark" href="abonnements.php">Comptes &amp; abonnement</a>
                    <a class="btn btn-outline-dark" href="paiements.php">Paiements</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
