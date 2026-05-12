<?php
declare(strict_types=1);

/**
 * Console admin pour gérer la bibliothèque de documents publics.
 * - Upload de nouveaux documents
 * - Activation / désactivation
 * - Édition des métadonnées
 * - Suppression
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/documents-publics.php';

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
        $email = (string) ($_SESSION['subscriber_email'] ?? ($_SESSION['editeur_email'] ?? 'console_secrete'));
        switch ($action) {
            case 'upload':
                $err = null;
                $id = maire_creer_document_public(
                    $pdo,
                    [
                        'categorie' => (string) ($_POST['categorie'] ?? 'autre'),
                        'titre' => (string) ($_POST['titre'] ?? ''),
                        'description' => (string) ($_POST['description'] ?? ''),
                    ],
                    $_FILES['fichier'] ?? [],
                    $email,
                    $err
                );
                if ($id === null) {
                    $flash = $err ?? 'Upload impossible.';
                    $flashType = 'danger';
                } else {
                    $flash = 'Document n°' . $id . ' publié avec succès.';
                }
                break;

            case 'toggle':
                $docId = (int) ($_POST['id'] ?? 0);
                $publie = (string) ($_POST['nouveau_statut'] ?? '') === '1';
                if (maire_basculer_publication_document($pdo, $docId, $publie)) {
                    $flash = 'Document ' . ($publie ? 'remis en ligne' : 'retiré du public') . '.';
                } else {
                    $flash = 'Mise à jour impossible.';
                    $flashType = 'danger';
                }
                break;

            case 'maj_meta':
                $docId = (int) ($_POST['id'] ?? 0);
                $ok = maire_mettre_a_jour_meta_document(
                    $pdo,
                    $docId,
                    (string) ($_POST['titre'] ?? ''),
                    (string) ($_POST['description'] ?? ''),
                    (string) ($_POST['categorie'] ?? 'autre')
                );
                $flash = $ok ? 'Métadonnées mises à jour.' : 'Mise à jour impossible (titre/catégorie invalide).';
                if (!$ok) { $flashType = 'danger'; }
                break;

            case 'supprimer':
                $docId = (int) ($_POST['id'] ?? 0);
                if (maire_supprimer_document($pdo, $docId)) {
                    $flash = 'Document supprimé (fichier et entrée DB).';
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

$documents = $pdo !== null ? maire_liste_documents_admin($pdo, 200) : [];
$compteurs = $pdo !== null ? maire_compter_documents_publics($pdo) : ['total' => 0, 'publies' => 0, 'hors_ligne' => 0, 'telechargements' => 0];

$pageTitle = 'Espace mairie · Bibliothèque de documents';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Administration</span>
            <h1>Bibliothèque de documents publics</h1>
            <p>Publiez les formulaires, actes, autorisations et guides téléchargeables par les habitants.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Vue d’ensemble</h2>
                <div class="services-stats" style="margin-top:0.6rem;">
                    <article class="stat-chip">
                        <strong><?php echo (int) $compteurs['total']; ?></strong>
                        <span>Documents au total</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#16a34a;"><?php echo (int) $compteurs['publies']; ?></strong>
                        <span>En ligne</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#64748b;"><?php echo (int) $compteurs['hors_ligne']; ?></strong>
                        <span>Hors ligne</span>
                    </article>
                    <article class="stat-chip">
                        <strong style="color:#0c4a3e;"><?php echo number_format((int) $compteurs['telechargements'], 0, ',', ' '); ?></strong>
                        <span>Téléchargements cumulés</span>
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
                <h2>📤 Publier un nouveau document</h2>
                <form method="POST" action="documents.php" enctype="multipart/form-data" style="display:grid;gap:0.6rem;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="upload">

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.6rem;">
                        <div>
                            <label for="categorie" style="display:block;font-weight:600;">Catégorie *</label>
                            <select id="categorie" name="categorie" required style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                                <?php foreach (MAIRE_DOCUMENTS_CATEGORIES as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="titre" style="display:block;font-weight:600;">Titre du document *</label>
                            <input type="text" id="titre" name="titre" required maxlength="180" placeholder="Ex : Formulaire de demande d'extrait de naissance" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div>
                        <label for="description" style="display:block;font-weight:600;">Description (facultative)</label>
                        <textarea id="description" name="description" maxlength="4000" rows="3" placeholder="Précisez à quoi sert ce document et les pièces à joindre éventuelles" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="fichier" style="display:block;font-weight:600;">Fichier (PDF, DOC/DOCX, XLS/XLSX, ODT/ODS, JPG, PNG) *</label>
                        <input type="file" id="fichier" name="fichier" required accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.jpg,.jpeg,.png,application/pdf" style="width:100%;padding:0.4rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <small style="color:#64748b;">Taille max : 10 Mo. Le fichier sera stocké sous un nom aléatoire pour la sécurité.</small>
                    </div>

                    <div class="detail-actions">
                        <button type="submit" class="btn btn-primary">📤 Publier</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Documents existants (<?php echo count($documents); ?>)</h2>
                <?php if (empty($documents)): ?>
                    <p>Aucun document publié pour l’instant. Utilisez le formulaire ci-dessus pour commencer.</p>
                <?php else: ?>
                    <div style="display:grid;gap:0.8rem;">
                    <?php foreach ($documents as $doc):
                        $publie = (int) ($doc['publie'] ?? 0) === 1;
                        $cat = (string) ($doc['categorie'] ?? 'autre');
                    ?>
                        <article style="border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem;background:#fff;<?php echo $publie ? '' : 'opacity:0.7;'; ?>">
                            <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.6rem;flex-wrap:wrap;">
                                <div>
                                    <strong style="font-size:1.05rem;">
                                        <?php echo htmlspecialchars(maire_icone_categorie_document($cat), ENT_QUOTES, 'UTF-8'); ?>
                                        #<?php echo (int) $doc['id']; ?> · <?php echo htmlspecialchars((string) $doc['titre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                    <br><small style="color:#64748b;">
                                        <?php echo htmlspecialchars(maire_libelle_categorie_document($cat), ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo htmlspecialchars((string) ($doc['fichier_nom_original'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo htmlspecialchars(maire_format_taille_fichier((int) ($doc['fichier_taille'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                                        · <strong><?php echo (int) ($doc['nb_telechargements'] ?? 0); ?> téléchargements</strong>
                                        · Publié le <?php echo htmlspecialchars(substr((string) ($doc['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($doc['publie_par_email'])): ?>
                                            par <?php echo htmlspecialchars((string) $doc['publie_par_email'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="std-feed-badge <?php echo $publie ? 'std-feed-badge--success' : 'std-feed-badge--warning'; ?>">
                                    <?php echo $publie ? 'En ligne' : 'Hors ligne'; ?>
                                </span>
                            </header>

                            <?php if (!empty($doc['description'])): ?>
                                <p style="margin:0.5rem 0;color:#374151;"><?php echo nl2br(htmlspecialchars((string) $doc['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                            <?php endif; ?>

                            <details style="margin-top:0.6rem;">
                                <summary style="cursor:pointer;font-weight:600;">✏️ Modifier les métadonnées</summary>
                                <form method="POST" action="documents.php" style="display:grid;gap:0.5rem;margin-top:0.6rem;">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="maj_meta">
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.5rem;">
                                        <select name="categorie" required style="padding:0.45rem;border:1px solid #cbd5e1;border-radius:6px;">
                                            <?php foreach (MAIRE_DOCUMENTS_CATEGORIES as $code => $label): ?>
                                                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $cat === $code ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="titre" required maxlength="180" value="<?php echo htmlspecialchars((string) $doc['titre'], ENT_QUOTES, 'UTF-8'); ?>" style="padding:0.45rem;border:1px solid #cbd5e1;border-radius:6px;">
                                    </div>
                                    <textarea name="description" maxlength="4000" rows="2" style="padding:0.45rem;border:1px solid #cbd5e1;border-radius:6px;"><?php echo htmlspecialchars((string) ($doc['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    <button type="submit" class="btn btn-primary" style="align-self:start;">Enregistrer</button>
                                </form>
                            </details>

                            <div class="detail-actions" style="margin-top:0.8rem;">
                                <a class="btn btn-outline-dark" href="../telecharger-document.php?id=<?php echo (int) $doc['id']; ?>" download>⬇ Prévisualiser</a>

                                <form method="POST" action="documents.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <input type="hidden" name="nouveau_statut" value="<?php echo $publie ? '0' : '1'; ?>">
                                    <button type="submit" class="btn btn-outline-dark">
                                        <?php echo $publie ? '🚫 Retirer du public' : '✅ Remettre en ligne'; ?>
                                    </button>
                                </form>

                                <form method="POST" action="documents.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce document (fichier + entrée DB) ?');">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
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
                    <a class="btn btn-outline-dark" href="signalements.php">Signalements citoyens</a>
                    <a class="btn btn-outline-dark" href="abonnements.php">Comptes &amp; abonnement</a>
                    <a class="btn btn-outline-dark" href="../documents.php">Voir la page publique</a>
                </div>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
