<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin-guard.php';
require __DIR__ . '/../includes/header.php';

$feedback = null;
$isError = false;

if (!isset($pdo) || $pdo === null) {
    $feedback = 'Connexion MySQL indisponible.';
    $isError = true;
}

if (isset($pdo) && $pdo !== null) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS standard_hub_actualites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(200) NOT NULL,
            resume VARCHAR(500) NOT NULL,
            lien VARCHAR(220) DEFAULT NULL,
            badge VARCHAR(24) NOT NULL DEFAULT 'info',
            published_at DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isError) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categorie = trim((string) ($_POST['categorie'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $localisation = trim((string) ($_POST['localisation'] ?? ''));
        $niveauType = trim((string) ($_POST['niveau_ou_type'] ?? ''));
        $horaires = trim((string) ($_POST['horaires'] ?? ''));

        $categoriesAutorisees = ['ecole', 'sante', 'service'];
        if (!in_array($categorie, $categoriesAutorisees, true) || $nom === '' || $localisation === '') {
            $feedback = 'Merci de renseigner les champs obligatoires.';
            $isError = true;
        } else {
            $insert = $pdo->prepare("
                INSERT INTO standard_referentiel (categorie, nom, localisation, niveau_ou_type, horaires)
                VALUES (:categorie, :nom, :localisation, :niveau_ou_type, :horaires)
            ");
            $insert->execute([
                'categorie' => $categorie,
                'nom' => $nom,
                'localisation' => $localisation,
                'niveau_ou_type' => $niveauType !== '' ? $niveauType : null,
                'horaires' => $horaires !== '' ? $horaires : null,
            ]);
            $feedback = 'Element ajoute avec succes.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $delete = $pdo->prepare("DELETE FROM standard_referentiel WHERE id = :id");
            $delete->execute(['id' => $id]);
            $feedback = 'Element supprime.';
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $categorie = trim((string) ($_POST['categorie'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $localisation = trim((string) ($_POST['localisation'] ?? ''));
        $niveauType = trim((string) ($_POST['niveau_ou_type'] ?? ''));
        $horaires = trim((string) ($_POST['horaires'] ?? ''));

        $categoriesAutorisees = ['ecole', 'sante', 'service'];
        if ($id <= 0 || !in_array($categorie, $categoriesAutorisees, true) || $nom === '' || $localisation === '') {
            $feedback = 'Donnees invalides pour la mise a jour.';
            $isError = true;
        } else {
            $update = $pdo->prepare("
                UPDATE standard_referentiel
                SET categorie = :categorie,
                    nom = :nom,
                    localisation = :localisation,
                    niveau_ou_type = :niveau_ou_type,
                    horaires = :horaires
                WHERE id = :id
            ");
            $update->execute([
                'id' => $id,
                'categorie' => $categorie,
                'nom' => $nom,
                'localisation' => $localisation,
                'niveau_ou_type' => $niveauType !== '' ? $niveauType : null,
                'horaires' => $horaires !== '' ? $horaires : null,
            ]);
            $feedback = 'Element mis a jour avec succes.';
        }
    }

    $badgesHub = ['info', 'success', 'alert'];

    if ($action === 'hub_add') {
        $titre = trim((string) ($_POST['hub_titre'] ?? ''));
        $resume = trim((string) ($_POST['hub_resume'] ?? ''));
        $lien = trim((string) ($_POST['hub_lien'] ?? ''));
        $badge = trim((string) ($_POST['hub_badge'] ?? 'info'));
        $publishedAt = trim((string) ($_POST['hub_published_at'] ?? ''));

        if ($titre === '' || $resume === '' || $publishedAt === '' || !in_array($badge, $badgesHub, true)) {
            $feedback = 'Fil d’annonces : titre, résumé, date et badge valides requis.';
            $isError = true;
        } else {
            $insertHub = $pdo->prepare("
                INSERT INTO standard_hub_actualites (titre, resume, lien, badge, published_at)
                VALUES (:titre, :resume, :lien, :badge, :published_at)
            ");
            $insertHub->execute([
                'titre' => $titre,
                'resume' => $resume,
                'lien' => $lien !== '' ? $lien : null,
                'badge' => $badge,
                'published_at' => $publishedAt,
            ]);
            $feedback = 'Annonce ajoutée au fil communal.';
        }
    }

    if ($action === 'hub_delete') {
        $hid = (int) ($_POST['hub_id'] ?? 0);
        if ($hid > 0) {
            $delHub = $pdo->prepare("DELETE FROM standard_hub_actualites WHERE id = :id");
            $delHub->execute(['id' => $hid]);
            $feedback = 'Actualite supprimee du fil.';
        }
    }

    if ($action === 'hub_update') {
        $hid = (int) ($_POST['hub_id'] ?? 0);
        $titre = trim((string) ($_POST['hub_titre'] ?? ''));
        $resume = trim((string) ($_POST['hub_resume'] ?? ''));
        $lien = trim((string) ($_POST['hub_lien'] ?? ''));
        $badge = trim((string) ($_POST['hub_badge'] ?? 'info'));
        $publishedAt = trim((string) ($_POST['hub_published_at'] ?? ''));

        if ($hid <= 0 || $titre === '' || $resume === '' || $publishedAt === '' || !in_array($badge, $badgesHub, true)) {
            $feedback = 'Fil d’annonces : données invalides pour la mise à jour.';
            $isError = true;
        } else {
            $upHub = $pdo->prepare("
                UPDATE standard_hub_actualites
                SET titre = :titre, resume = :resume, lien = :lien, badge = :badge, published_at = :published_at
                WHERE id = :id
            ");
            $upHub->execute([
                'id' => $hid,
                'titre' => $titre,
                'resume' => $resume,
                'lien' => $lien !== '' ? $lien : null,
                'badge' => $badge,
                'published_at' => $publishedAt,
            ]);
            $feedback = 'Actualite du fil mise a jour.';
        }
    }
}

$elements = [];
$hubElements = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $elements = $pdo->query("SELECT id, categorie, nom, localisation, niveau_ou_type, horaires FROM standard_referentiel ORDER BY id DESC")->fetchAll();
    } catch (Throwable $e) {
        $elements = [];
    }
    try {
        $hubElements = $pdo->query("SELECT id, titre, resume, lien, badge, published_at FROM standard_hub_actualites ORDER BY published_at DESC, id DESC")->fetchAll();
    } catch (Throwable $e) {
        $hubElements = [];
    }
}
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Administration</span>
            <h1>Administration du portail</h1>
            <p>Référentiel (écoles, santé, services) et fil d’annonces affiché sur le portail communal.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container subscription-grid">
            <article class="card">
                <h2>Ajouter un element</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="abonnements.php">Comptes &amp; abonnements</a>
                    <a class="btn btn-outline-dark" href="paiements.php">Voir les paiements mensuels</a>
                    <a class="btn btn-outline-dark" href="#fil-standard-plus">Fil d’annonces communal</a>
                </div>
                <?php if ($feedback !== null): ?>
                    <p class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($feedback); ?>
                    </p>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <label for="categorie">Categorie</label>
                    <select id="categorie" name="categorie" required>
                        <option value="ecole">Ecole</option>
                        <option value="sante">Sante</option>
                        <option value="service">Service</option>
                    </select>

                    <label for="nom">Nom</label>
                    <input id="nom" name="nom" type="text" required>

                    <label for="localisation">Localisation / point de service</label>
                    <input id="localisation" name="localisation" type="text" required>

                    <label for="niveau_ou_type">Niveau ou type</label>
                    <input id="niveau_ou_type" name="niveau_ou_type" type="text">

                    <label for="horaires">Horaires (si service)</label>
                    <input id="horaires" name="horaires" type="text">

                    <button class="btn btn-primary" type="submit">Ajouter</button>
                </form>
            </article>

            <article class="card">
                <h2>Elements existants</h2>
                <div class="admin-list">
                    <?php foreach ($elements as $element): ?>
                        <article class="admin-item">
                            <form method="POST" class="admin-edit-form">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo (int) $element['id']; ?>">

                                <label>Categorie</label>
                                <select name="categorie" required>
                                    <option value="ecole" <?php echo (string) $element['categorie'] === 'ecole' ? 'selected' : ''; ?>>Ecole</option>
                                    <option value="sante" <?php echo (string) $element['categorie'] === 'sante' ? 'selected' : ''; ?>>Sante</option>
                                    <option value="service" <?php echo (string) $element['categorie'] === 'service' ? 'selected' : ''; ?>>Service</option>
                                </select>

                                <label>Nom</label>
                                <input name="nom" type="text" value="<?php echo htmlspecialchars((string) $element['nom']); ?>" required>

                                <label>Localisation</label>
                                <input name="localisation" type="text" value="<?php echo htmlspecialchars((string) $element['localisation']); ?>" required>

                                <label>Niveau / Type</label>
                                <input name="niveau_ou_type" type="text" value="<?php echo htmlspecialchars((string) ($element['niveau_ou_type'] ?? '')); ?>">

                                <label>Horaires</label>
                                <input name="horaires" type="text" value="<?php echo htmlspecialchars((string) ($element['horaires'] ?? '')); ?>">

                                <div class="admin-item-actions">
                                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                                </div>
                            </form>

                            <form method="POST" class="admin-delete-form" onsubmit="return confirm('Confirmer la suppression de cet element ?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $element['id']; ?>">
                                <button class="btn btn-outline-dark" type="submit">Supprimer</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
    </section>

    <section class="section-shell section-muted" id="fil-standard-plus">
        <div class="container">
            <h2 class="section-title">Fil d’annonces communal</h2>
            <p class="std-admin-intro">Ces messages s’affichent sur <strong>standard.php</strong> (section « Fil communal »). Badges : Info, Positif, Important.</p>

            <div class="subscription-grid" style="margin-top: 1rem;">
                <article class="card">
                    <h3>Nouvelle actualite</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="hub_add">
                        <label for="hub_titre">Titre</label>
                        <input id="hub_titre" name="hub_titre" type="text" maxlength="200" required>

                        <label for="hub_resume">Resume</label>
                        <textarea id="hub_resume" name="hub_resume" rows="3" maxlength="500" required></textarea>

                        <label for="hub_lien">Lien (optionnel, ex. digitalisation-etat-civil.php)</label>
                        <input id="hub_lien" name="hub_lien" type="text" maxlength="220" placeholder="page.php ou https://...">

                        <label for="hub_badge">Badge</label>
                        <select id="hub_badge" name="hub_badge" required>
                            <option value="info">Info</option>
                            <option value="success">Positif</option>
                            <option value="alert">Important</option>
                        </select>

                        <label for="hub_published_at">Date de publication</label>
                        <input id="hub_published_at" name="hub_published_at" type="date" required value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">

                        <button class="btn btn-primary" type="submit">Publier sur le fil</button>
                    </form>
                </article>

                <article class="card">
                    <h3>Messages existants (<?php echo count($hubElements); ?>)</h3>
                    <div class="admin-list">
                        <?php foreach ($hubElements as $h): ?>
                            <article class="admin-item">
                                <form method="POST" class="admin-edit-form">
                                    <input type="hidden" name="action" value="hub_update">
                                    <input type="hidden" name="hub_id" value="<?php echo (int) $h['id']; ?>">

                                    <label>Titre</label>
                                    <input name="hub_titre" type="text" maxlength="200" value="<?php echo htmlspecialchars((string) $h['titre'], ENT_QUOTES, 'UTF-8'); ?>" required>

                                    <label>Resume</label>
                                    <textarea name="hub_resume" rows="2" maxlength="500" required><?php echo htmlspecialchars((string) $h['resume'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                                    <label>Lien</label>
                                    <input name="hub_lien" type="text" maxlength="220" value="<?php echo htmlspecialchars((string) ($h['lien'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                                    <label>Badge</label>
                                    <select name="hub_badge" required>
                                        <option value="info" <?php echo (string) ($h['badge'] ?? '') === 'info' ? 'selected' : ''; ?>>Info</option>
                                        <option value="success" <?php echo (string) ($h['badge'] ?? '') === 'success' ? 'selected' : ''; ?>>Positif</option>
                                        <option value="alert" <?php echo (string) ($h['badge'] ?? '') === 'alert' ? 'selected' : ''; ?>>Important</option>
                                    </select>

                                    <label>Date</label>
                                    <input name="hub_published_at" type="date" required value="<?php echo htmlspecialchars((string) ($h['published_at'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="admin-item-actions">
                                        <button class="btn btn-primary" type="submit">Mettre a jour</button>
                                    </div>
                                </form>
                                <form method="POST" class="admin-delete-form" onsubmit="return confirm('Supprimer cette actualite du fil ?');">
                                    <input type="hidden" name="action" value="hub_delete">
                                    <input type="hidden" name="hub_id" value="<?php echo (int) $h['id']; ?>">
                                    <button class="btn btn-outline-dark" type="submit">Supprimer</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
