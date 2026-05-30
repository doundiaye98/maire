<?php
declare(strict_types=1);

/**
 * Suivi des paiements d'abonnement avec validation OTP côté console éditeur.
 */
require __DIR__ . '/../includes/super-admin-account-guard.php';
require_once __DIR__ . '/../includes/otp-sms.php';

$superAdminPaiementsCsrfScope = MAIRE_CSRF_SCOPE_SUPER_ADMIN_PAIEMENTS;

if (!isset($_SESSION['editeur_paiements_otp']) || !is_array($_SESSION['editeur_paiements_otp'])) {
    $_SESSION['editeur_paiements_otp'] = [];
}

/**
 * @return array{id:int,email:string,montant_fcfa:int,mode_paiement:string,reference_paiement:string,statut:string,created_at:string,frequence:string}|null
 */
function maire_super_admin_load_paiement_abonnement(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT id, email, montant_fcfa, mode_paiement, reference_paiement, statut, created_at, frequence
            FROM paiements_abonnements
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

$flash = '';
$flashType = 'success';
$flashDevHint = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!maire_csrf_validate($superAdminPaiementsCsrfScope)) {
        $flash = maire_csrf_error_message();
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $telephoneBrut = trim((string) ($_POST['telephone'] ?? ''));
        $scope = 'abonnement_payment_' . $id;
        $paiement = maire_super_admin_load_paiement_abonnement($pdo, $id);

        if ($paiement === null) {
            $flash = 'Paiement d’abonnement introuvable.';
            $flashType = 'danger';
        } elseif ((string) ($paiement['statut'] ?? '') !== 'en_attente') {
            $flash = 'Seuls les paiements en attente peuvent être validés par OTP.';
            $flashType = 'danger';
        } elseif ($action === 'send_otp') {
            $telNorm = maire_normaliser_telephone_sn($telephoneBrut);
            if ($telNorm === null) {
                $flash = 'Numéro de mobile invalide. Format attendu : 77 123 45 67.';
                $flashType = 'danger';
            } else {
                $otpErr = null;
                if (maire_otp_envoyer($pdo, $telNorm, $scope, $otpErr)) {
                    $_SESSION['editeur_paiements_otp'][(string) $id] = $telNorm;
                    $flash = 'Code OTP envoyé au ' . $telNorm . ' pour le paiement ' . (string) ($paiement['reference_paiement'] ?? '') . '.';
                    if (
                        function_exists('maire_env')
                        && maire_env('APP_ENV', 'production') === 'development'
                        && !empty($_SESSION['maire_otp_dev_hint'])
                    ) {
                        $flashDevHint = 'Code de test (développement) : ' . (string) $_SESSION['maire_otp_dev_hint'];
                    }
                } else {
                    $flash = $otpErr ?? 'Impossible d’envoyer le code OTP.';
                    $flashType = 'danger';
                }
            }
        } elseif ($action === 'validate_otp') {
            $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
            $telephoneScope = $telephoneBrut !== ''
                ? maire_normaliser_telephone_sn($telephoneBrut)
                : maire_normaliser_telephone_sn((string) ($_SESSION['editeur_paiements_otp'][(string) $id] ?? ''));

            if ($telephoneScope === null) {
                $flash = 'Indiquez d’abord le numéro ayant reçu le code OTP.';
                $flashType = 'danger';
            } else {
                $otpErr = null;
                if (!maire_otp_verifier($pdo, $telephoneScope, $scope, $otpCode, $otpErr)) {
                    $flash = $otpErr ?? 'Code OTP invalide.';
                    $flashType = 'danger';
                    $_SESSION['editeur_paiements_otp'][(string) $id] = $telephoneScope;
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE paiements_abonnements
                            SET statut = 'valide'
                            WHERE id = :id AND statut = 'en_attente'
                        ");
                        $stmt->execute(['id' => $id]);

                        if ($stmt->rowCount() > 0) {
                            unset($_SESSION['editeur_paiements_otp'][(string) $id]);
                            $flash = 'Paiement validé après vérification OTP.';
                        } else {
                            $flash = 'Validation impossible : le paiement a déjà changé d’état.';
                            $flashType = 'danger';
                        }
                    } catch (Throwable $e) {
                        $flash = 'Erreur lors de la validation du paiement.';
                        $flashType = 'danger';
                    }
                }
            }
        } else {
            $flash = 'Action inconnue.';
            $flashType = 'danger';
        }
    }
}

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
            <p style="color:#e2e8f0;">200 derniers paiements. Les lignes <strong>en attente</strong> peuvent maintenant être validées par code OTP SMS. Total encaissé valide : <strong style="color:#fff;"><?php echo number_format((float) $totalValide, 0, ',', ' '); ?> F CFA</strong> · remboursé : <?php echo number_format((float) $totalRemb, 0, ',', ' '); ?> F CFA.</p>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container">
            <?php if ($flash !== ''): ?>
                <article class="card">
                    <p class="std-feed-badge std-feed-badge--<?php echo htmlspecialchars($flashType === 'danger' ? 'danger' : 'success', ENT_QUOTES, 'UTF-8'); ?>" style="display:block;">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php if ($flashDevHint !== ''): ?>
                        <p style="margin-top:0.75rem;font-size:0.9rem;color:#475569;">
                            <?php echo htmlspecialchars($flashDevHint, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

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
                                <th style="padding:0.5rem;text-align:left;">Validation OTP</th>
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
                                    <span class="std-feed-badge std-feed-badge--<?php echo $st === 'valide' ? 'success' : (($st === 'rembourse' || $st === 'en_attente') ? 'warning' : 'danger'); ?>">
                                        <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="padding:0.5rem;min-width:20rem;">
                                    <?php if ($st === 'en_attente'): ?>
                                        <?php $otpPhone = (string) ($_SESSION['editeur_paiements_otp'][(string) ($p['id'] ?? 0)] ?? ''); ?>
                                        <form method="POST" action="paiements.php<?php echo $filtreStatut !== '' || $filtreEmail !== '' ? '?' . http_build_query(array_filter(['statut' => $filtreStatut, 'email' => $filtreEmail], static fn($v) => $v !== null && $v !== '')) : ''; ?>" style="display:flex;flex-direction:column;gap:0.5rem;">
                                            <?php echo maire_csrf_field($superAdminPaiementsCsrfScope); ?>
                                            <input type="hidden" name="id" value="<?php echo (int) ($p['id'] ?? 0); ?>">
                                            <label style="display:block;font-size:0.8rem;font-weight:600;color:#334155;">
                                                Mobile à vérifier
                                                <input
                                                    type="tel"
                                                    name="telephone"
                                                    maxlength="20"
                                                    placeholder="77 123 45 67"
                                                    value="<?php echo htmlspecialchars($otpPhone, ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;"
                                                >
                                            </label>
                                            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                                                <button type="submit" name="action" value="send_otp" class="btn btn-outline-dark">Envoyer le code</button>
                                                <label style="display:block;flex:1 1 11rem;font-size:0.8rem;font-weight:600;color:#334155;">
                                                    Code OTP
                                                    <input
                                                        type="text"
                                                        name="otp_code"
                                                        inputmode="numeric"
                                                        pattern="\d{6}"
                                                        maxlength="6"
                                                        placeholder="6 chiffres"
                                                        style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;"
                                                    >
                                                </label>
                                                <button type="submit" name="action" value="validate_otp" class="btn btn-primary">Valider le paiement</button>
                                            </div>
                                            <small style="color:#64748b;">Le paiement reste en attente tant que le code SMS n’est pas confirmé.</small>
                                        </form>
                                    <?php elseif ($st === 'valide'): ?>
                                        <small style="color:#16a34a;font-weight:600;">Validation terminée.</small>
                                    <?php else: ?>
                                        <small style="color:#64748b;">Aucune action OTP.</small>
                                    <?php endif; ?>
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
