<?php
declare(strict_types=1);

/**
 * Console admin — Base de connaissances du chatbot (FAQ).
 * Feature gating : ia_assistant (Premium).
 */
require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/feature-gates.php';
require_once __DIR__ . '/../includes/chatbot.php';

if ($pdo !== null && !maire_super_admin_session_valid() && !maire_feature_disponible($pdo, 'ia_assistant')) {
    $palierCommune = maire_palier_commune_actuel($pdo);
    maire_render_paywall_page('ia_assistant', $palierCommune, 'admin');
    exit;
}

if (empty($_SESSION['abo_admin_csrf'])) {
    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
}

if ($pdo !== null) {
    maire_ensure_chatbot_tables($pdo);
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
        try {
            switch ($action) {
                case 'creer':
                    $st = $pdo->prepare('INSERT INTO chatbot_faq (categorie, question, reponse, mots_cles, lien_action, libelle_action, priorite, actif) VALUES (:c, :q, :r, :m, :l, :la, :p, 1)');
                    $st->execute([
                        'c' => mb_substr(trim((string) ($_POST['categorie'] ?? 'general')), 0, 60),
                        'q' => mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 300),
                        'r' => mb_substr(trim((string) ($_POST['reponse'] ?? '')), 0, 4000),
                        'm' => mb_substr(trim((string) ($_POST['mots_cles'] ?? '')), 0, 500),
                        'l' => trim((string) ($_POST['lien_action'] ?? '')) ?: null,
                        'la' => trim((string) ($_POST['libelle_action'] ?? '')) ?: null,
                        'p' => max(0, min(10, (int) ($_POST['priorite'] ?? 5))),
                    ]);
                    $flash = 'Entrée FAQ ajoutée.';
                    break;
                case 'toggle':
                    $id = (int) ($_POST['id'] ?? 0);
                    $pdo->prepare('UPDATE chatbot_faq SET actif = 1 - actif WHERE id = :id')->execute(['id' => $id]);
                    $flash = 'Statut basculé.';
                    break;
                case 'supprimer':
                    $id = (int) ($_POST['id'] ?? 0);
                    $pdo->prepare('DELETE FROM chatbot_faq WHERE id = :id')->execute(['id' => $id]);
                    $flash = 'Entrée supprimée.';
                    break;
            }
        } catch (Throwable $e) {
            $flash = 'Erreur : ' . $e->getMessage();
            $flashType = 'danger';
        }
    }
}

$rows = $pdo !== null ? ($pdo->query('SELECT * FROM chatbot_faq ORDER BY priorite DESC, id DESC')->fetchAll() ?: []) : [];
$stats = $pdo !== null ? maire_chatbot_compteurs($pdo) : ['conversations' => 0, 'questions_repondues' => 0, 'taux_succes' => 0.0, 'top_faq' => []];

$pageTitle = 'Espace mairie · Assistant IA — FAQ';
require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Assistant IA</span>
            <h1>Base de connaissances du chatbot</h1>
            <p>Pilotez les réponses automatiques de l’assistant citoyen affiché en bas à droite de toutes les pages publiques.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">

            <article class="card">
                <h2>Statistiques de conversation</h2>
                <div class="services-stats" style="margin-top:0.5rem;">
                    <article class="stat-chip"><strong><?php echo (int) $stats['conversations']; ?></strong><span>Conversations</span></article>
                    <article class="stat-chip"><strong style="color:#16a34a;"><?php echo (int) $stats['questions_repondues']; ?></strong><span>Réponses trouvées</span></article>
                    <article class="stat-chip"><strong style="color:#0c4a3e;"><?php echo number_format((float) $stats['taux_succes'], 1, ',', ' '); ?>%</strong><span>Taux de succès</span></article>
                    <article class="stat-chip"><strong><?php echo count($rows); ?></strong><span>Entrées FAQ</span></article>
                </div>
            </article>

            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="alert alert-<?php echo $flashType === 'danger' ? 'error' : 'success'; ?>" style="margin:0;"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>➕ Ajouter une question/réponse</h2>
                <form method="POST" action="chatbot-faq.php" style="display:grid;gap:0.6rem;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="creer">

                    <div style="display:grid;grid-template-columns:1fr 3fr 100px;gap:0.6rem;">
                        <div>
                            <label for="categorie" style="display:block;font-weight:600;">Catégorie</label>
                            <input type="text" id="categorie" name="categorie" maxlength="60" value="general" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                        <div>
                            <label for="question" style="display:block;font-weight:600;">Question type *</label>
                            <input type="text" id="question" name="question" required maxlength="300" placeholder="Comment renouveler ma carte d'identité ?" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                        <div>
                            <label for="priorite" style="display:block;font-weight:600;">Priorité</label>
                            <input type="number" id="priorite" name="priorite" min="0" max="10" value="5" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div>
                        <label for="reponse" style="display:block;font-weight:600;">Réponse *</label>
                        <textarea id="reponse" name="reponse" required rows="3" maxlength="4000" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                    </div>

                    <div>
                        <label for="mots_cles" style="display:block;font-weight:600;">Mots-clés * <small style="color:#64748b;">(séparés par virgules, en minuscules)</small></label>
                        <input type="text" id="mots_cles" name="mots_cles" required maxlength="500" placeholder="carte identite,renouvellement,renouveler,piece identite" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>

                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:0.6rem;">
                        <div>
                            <label for="lien_action" style="display:block;font-weight:600;">URL d’action (facultatif)</label>
                            <input type="text" id="lien_action" name="lien_action" maxlength="255" placeholder="/maire/contact.php" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                        <div>
                            <label for="libelle_action" style="display:block;font-weight:600;">Libellé du bouton</label>
                            <input type="text" id="libelle_action" name="libelle_action" maxlength="80" placeholder="Contacter la mairie" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        </div>
                    </div>

                    <div class="detail-actions">
                        <button type="submit" class="btn btn-primary">💾 Ajouter à la FAQ</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Toutes les entrées FAQ (<?php echo count($rows); ?>)</h2>
                <?php if (empty($rows)): ?>
                    <p>Aucune entrée FAQ. Le chatbot répondra qu’il ne sait pas.</p>
                <?php else: ?>
                    <div style="display:grid;gap:0.6rem;">
                    <?php foreach ($rows as $r): ?>
                        <article style="border:1px solid #e2e8f0;border-radius:10px;padding:0.8rem;background:#fff;<?php echo (int) $r['actif'] === 0 ? 'opacity:0.6;' : ''; ?>">
                            <header style="display:flex;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                <strong>#<?php echo (int) $r['id']; ?> · <?php echo htmlspecialchars((string) $r['question'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo htmlspecialchars((string) $r['categorie'], ENT_QUOTES, 'UTF-8'); ?> · prio <?php echo (int) $r['priorite']; ?> · <?php echo (int) $r['nb_consultations']; ?> vues</small>
                            </header>
                            <p style="margin:0.4rem 0;color:#374151;"><?php echo htmlspecialchars((string) $r['reponse'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p style="margin:0;font-size:0.85rem;color:#64748b;">🔑 mots-clés : <em><?php echo htmlspecialchars((string) $r['mots_cles'], ENT_QUOTES, 'UTF-8'); ?></em></p>
                            <div class="detail-actions" style="margin-top:0.5rem;">
                                <form method="POST" action="chatbot-faq.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;"><?php echo (int) $r['actif'] === 1 ? '⏸ Désactiver' : '▶ Activer'; ?></button>
                                </form>
                                <form method="POST" action="chatbot-faq.php" style="display:inline;" onsubmit="return confirm('Supprimer cette entrée FAQ ?');">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="btn btn-outline-dark" style="padding:0.3rem 0.6rem;font-size:0.85rem;color:#dc2626;">🗑</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
