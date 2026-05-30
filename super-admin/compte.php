<?php
declare(strict_types=1);

/**
 * Page de gestion du compte éditeur courant : changer le mot de passe.
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';

$superAdminCsrfScope = MAIRE_CSRF_SCOPE_SUPER_ADMIN;

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!maire_csrf_validate($superAdminCsrfScope)) {
        $flash = maire_csrf_error_message();
        $flashType = 'danger';
    } else {
        $mdpActuel = (string) ($_POST['mdp_actuel'] ?? '');
        $mdpNouveau = (string) ($_POST['mdp_nouveau'] ?? '');
        $mdpConfirm = (string) ($_POST['mdp_confirm'] ?? '');

        if ($mdpNouveau === '' || strlen($mdpNouveau) < 10) {
            $flash = 'Le nouveau mot de passe doit faire au moins 10 caractères.';
            $flashType = 'danger';
        } elseif ($mdpNouveau !== $mdpConfirm) {
            $flash = 'Les deux nouveaux mots de passe ne correspondent pas.';
            $flashType = 'danger';
        } else {
            $idEditeur = (int) ($_SESSION['editeur_id'] ?? 0);
            $compte = maire_load_super_admin($pdo, $idEditeur);
            if ($compte === null || !password_verify($mdpActuel, (string) $compte['mot_de_passe_hash'])) {
                $flash = 'Mot de passe actuel incorrect.';
                $flashType = 'danger';
            } else {
                $hash = password_hash($mdpNouveau, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE super_admins SET mot_de_passe_hash = :h WHERE id = :id')
                    ->execute(['h' => $hash, 'id' => $idEditeur]);
                $flash = 'Mot de passe mis à jour. Pensez à le noter en lieu sûr.';
            }
        }
    }
}

$idEditeur = (int) ($_SESSION['editeur_id'] ?? 0);
$compte = maire_load_super_admin($pdo, $idEditeur);

$pageTitle = 'Espace éditeur · Mon compte';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero" style="background:linear-gradient(120deg,#1f2a44,#0f172a);color:#fff;">
        <div class="container">
            <span class="detail-kicker" style="color:#cbd5f5;">Console éditeur</span>
            <h1 style="color:#fff;">Mon compte éditeur</h1>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container subscription-grid">

            <article class="card">
                <h2>Informations</h2>
                <ul style="list-style:none;padding:0;margin:0;">
                    <li><strong>Email :</strong> <?php echo htmlspecialchars((string) ($compte['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Nom :</strong> <?php echo htmlspecialchars((string) ($compte['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Compte créé le :</strong> <?php echo htmlspecialchars((string) ($compte['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Dernière connexion :</strong> <?php echo htmlspecialchars((string) ($compte['last_login_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></li>
                </ul>
            </article>

            <article class="card">
                <h2>Changer mon mot de passe</h2>
                <?php if ($flash !== ''): ?>
                    <p class="std-feed-badge std-feed-badge--<?php echo htmlspecialchars($flashType === 'danger' ? 'danger' : 'success', ENT_QUOTES, 'UTF-8'); ?>" style="display:block;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="compte.php" autocomplete="off">
                    <?php echo maire_csrf_field($superAdminCsrfScope); ?>
                    <label for="mdp_actuel" style="display:block;font-weight:600;margin-top:0.4rem;">Mot de passe actuel</label>
                    <input type="password" id="mdp_actuel" name="mdp_actuel" required style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">

                    <label for="mdp_nouveau" style="display:block;font-weight:600;margin-top:0.4rem;">Nouveau mot de passe (≥10 caractères)</label>
                    <input type="password" id="mdp_nouveau" name="mdp_nouveau" required minlength="10" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">

                    <label for="mdp_confirm" style="display:block;font-weight:600;margin-top:0.4rem;">Confirmer le nouveau mot de passe</label>
                    <input type="password" id="mdp_confirm" name="mdp_confirm" required minlength="10" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;">

                    <div class="detail-actions" style="margin-top:0.8rem;">
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Navigation</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="index.php">← Tableau de bord</a>
                    <a class="btn btn-outline-dark" href="logout.php">Se déconnecter</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
