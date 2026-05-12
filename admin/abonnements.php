<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin-guard.php';
require_once __DIR__ . '/../includes/super-admin-session.php';
require_once __DIR__ . '/../includes/abonnement-actif-sync.php';
require_once __DIR__ . '/../includes/commune-abonnement.php';
require_once __DIR__ . '/../includes/compte-mairie.php';
require_once __DIR__ . '/../includes/commune-abonnement-historique.php';
require_once __DIR__ . '/../includes/feature-gates.php';

if (empty($_SESSION['abo_admin_csrf'])) {
    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
}

$feedback = null;
$isError = false;
$plansAutorises = [
    'standard_plus',
    'standard_plus_mensuel',
    'municipal_simple',
    'municipal_standard',
    'municipal_premium',
];
$communePlansForm = ['municipal_simple', 'municipal_standard', 'municipal_premium'];
$rolesAutorises = ['subscriber', 'admin'];
$dureesRenouvellement = [30, 90, 180, 365];
$abonnements = [];
$communePalierLibelle = '';
$historiqueCommune = [];

$estConsoleSecrete = maire_super_admin_session_valid();

if (!isset($pdo) || $pdo === null) {
    $feedback = 'Connexion MySQL indisponible.';
    $isError = true;
} else {
    maire_ensure_abonnements_compte_mairie_column($pdo);
    maire_ensure_abonnements_auto_renew_columns($pdo);
    maire_ensure_commune_abonnement_auto_renew_columns($pdo);
    // Migration silencieuse des anciennes URL d'administration vers la nouvelle structure /admin/.
    try {
        $pdo->exec("UPDATE standard_hub_actualites SET lien = REPLACE(lien, 'admin-abonnements.php', 'admin/abonnements.php') WHERE lien LIKE '%admin-abonnements.php%'");
        $pdo->exec("UPDATE standard_hub_actualites SET lien = REPLACE(lien, 'admin-paiements.php', 'admin/paiements.php') WHERE lien LIKE '%admin-paiements.php%'");
        $pdo->exec("UPDATE standard_hub_actualites SET lien = REPLACE(lien, 'admin-standard.php', 'admin/standard.php') WHERE lien LIKE '%admin-standard.php%'");
    } catch (Throwable $e) {
        // table absente : on ignore
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['abo_admin_csrf'] ?? '', $csrf)) {
        $feedback = 'Jeton de sécurité invalide ou session expirée. Rechargez la page.';
        $isError = true;
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'update_abo') {
            $id = (int) ($_POST['id'] ?? 0);
            $emailPost = trim((string) ($_POST['email'] ?? ''));
            $dateDebut = trim((string) ($_POST['date_debut'] ?? ''));
            $dateFin = trim((string) ($_POST['date_fin'] ?? ''));
            $actif = isset($_POST['actif']) ? 1 : 0;
            $autoRenew = isset($_POST['auto_renew']) ? 1 : 0;
            $joursRenew = (int) ($_POST['renouvellement_jours'] ?? 30);
            if (!in_array($joursRenew, $dureesRenouvellement, true)) {
                $joursRenew = 30;
            }
            $role = trim((string) ($_POST['role_utilisateur'] ?? ''));
            $plan = trim((string) ($_POST['plan'] ?? ''));
            $nouveauMdp = trim((string) ($_POST['nouveau_mot_de_passe'] ?? ''));

            if ($id <= 0 || !filter_var($emailPost, FILTER_VALIDATE_EMAIL)) {
                $feedback = 'Identifiant ou e-mail invalide.';
                $isError = true;
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
                $feedback = 'Dates au format AAAA-MM-JJ requises.';
                $isError = true;
            } elseif (!in_array($role, $rolesAutorises, true)) {
                $feedback = 'Rôle non autorisé.';
                $isError = true;
            } elseif ($plan === '' || strlen($plan) > 40) {
                $feedback = 'Plan invalide.';
                $isError = true;
            } elseif ($nouveauMdp !== '' && strlen($nouveauMdp) < 8) {
                $feedback = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
                $isError = true;
            } else {
                try {
                    $chk = $pdo->prepare('SELECT id, email, compte_mairie FROM abonnements WHERE id = :id LIMIT 1');
                    $chk->execute(['id' => $id]);
                    $row = $chk->fetch();
                    if ($row === false || strcasecmp((string) $row['email'], $emailPost) !== 0) {
                        $feedback = 'Compte introuvable ou e-mail ne correspond pas.';
                        $isError = true;
                    } elseif ((int) ($row['compte_mairie'] ?? 0) === 1) {
                        if ($role !== 'admin') {
                            $feedback = 'Le compte institutionnel de la mairie doit conserver le rôle administrateur.';
                            $isError = true;
                        } elseif ($nouveauMdp !== '') {
                            $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
                            $up = $pdo->prepare('
                                UPDATE abonnements
                                SET actif = :actif, role_utilisateur = :role, mot_de_passe_hash = :hash
                                WHERE id = :id AND compte_mairie = 1
                            ');
                            $up->execute([
                                'actif' => $actif,
                                'role' => $role,
                                'hash' => $hash,
                                'id' => $id,
                            ]);
                            maire_sync_commune_vers_compte_mairie($pdo);
                            $feedback = 'Compte institutionnel mis à jour (plan et dates suivent l’abonnement communal).';
                            $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                        } else {
                            $up = $pdo->prepare('
                                UPDATE abonnements
                                SET actif = :actif, role_utilisateur = :role
                                WHERE id = :id AND compte_mairie = 1
                            ');
                            $up->execute([
                                'actif' => $actif,
                                'role' => $role,
                                'id' => $id,
                            ]);
                            maire_sync_commune_vers_compte_mairie($pdo);
                            $feedback = 'Compte institutionnel mis à jour (plan et dates suivent l’abonnement communal).';
                            $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                        }
                    } else {
                        if ($nouveauMdp !== '') {
                            $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
                            $up = $pdo->prepare('
                                UPDATE abonnements
                                SET date_debut = :d1, date_fin = :d2, actif = :actif,
                                    role_utilisateur = :role, plan = :plan, mot_de_passe_hash = :hash,
                                    auto_renew = :ar, renouvellement_jours = :rj
                                WHERE id = :id
                            ');
                            $up->execute([
                                'd1' => $dateDebut,
                                'd2' => $dateFin,
                                'actif' => $actif,
                                'role' => $role,
                                'plan' => $plan,
                                'hash' => $hash,
                                'ar' => $autoRenew,
                                'rj' => $joursRenew,
                                'id' => $id,
                            ]);
                        } else {
                            $up = $pdo->prepare('
                                UPDATE abonnements
                                SET date_debut = :d1, date_fin = :d2, actif = :actif,
                                    role_utilisateur = :role, plan = :plan,
                                    auto_renew = :ar, renouvellement_jours = :rj
                                WHERE id = :id
                            ');
                            $up->execute([
                                'd1' => $dateDebut,
                                'd2' => $dateFin,
                                'actif' => $actif,
                                'role' => $role,
                                'plan' => $plan,
                                'ar' => $autoRenew,
                                'rj' => $joursRenew,
                                'id' => $id,
                            ]);
                        }
                        $feedback = 'Abonnement mis à jour.';
                        $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                    }
                } catch (Throwable $e) {
                    $feedback = 'Erreur lors de la mise à jour.';
                    $isError = true;
                }
            }
        }

        if ($action === 'create_abo' && $feedback === null && !$isError) {
            $email = trim((string) ($_POST['new_email'] ?? ''));
            $pass = trim((string) ($_POST['new_password'] ?? ''));
            $plan = trim((string) ($_POST['new_plan'] ?? 'municipal_standard'));
            $role = trim((string) ($_POST['new_role'] ?? 'subscriber'));
            $jours = (int) ($_POST['new_jours'] ?? 30);
            $newCompteMairie = isset($_POST['new_compte_mairie']);
            $newAutoRenew = isset($_POST['new_auto_renew']) ? 1 : 0;
            $newJoursRenew = (int) ($_POST['new_renouvellement_jours'] ?? 30);
            if (!in_array($newJoursRenew, $dureesRenouvellement, true)) {
                $newJoursRenew = 30;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $feedback = 'E-mail invalide pour la création.';
                $isError = true;
            } elseif (strlen($pass) < 8) {
                $feedback = 'Mot de passe d’au moins 8 caractères requis.';
                $isError = true;
            } elseif (!in_array($role, $rolesAutorises, true)) {
                $feedback = 'Rôle non autorisé.';
                $isError = true;
            } elseif ($plan === '' || strlen($plan) > 40) {
                $feedback = 'Plan invalide.';
                $isError = true;
            } elseif ($newCompteMairie && $role !== 'admin') {
                $feedback = 'Le compte institutionnel doit être un administrateur.';
                $isError = true;
            } elseif ($newCompteMairie && maire_get_compte_mairie_id($pdo) !== null && !$estConsoleSecrete) {
                $feedback = 'Un compte institutionnel mairie existe déjà. Désactivez-le depuis la console secrète avant d’en créer un autre.';
                $isError = true;
            } elseif (
                !$estConsoleSecrete
                && maire_palier_commune_actuel($pdo) === 'simple'
                && !$newCompteMairie
                && (int) $pdo->query('SELECT COUNT(*) FROM abonnements')->fetchColumn() >= MAIRE_AGENTS_MAX_SIMPLE
            ) {
                $feedback = 'Limite de ' . MAIRE_AGENTS_MAX_SIMPLE . ' comptes atteinte avec la formule communale Simple. Passez en Standard pour des comptes agents illimités.';
                $isError = true;
            } else {
                $jours = max(1, min(3650, $jours));
                try {
                    $debut = (new DateTimeImmutable('today'))->format('Y-m-d');
                    $fin = (new DateTimeImmutable('today'))->modify('+' . $jours . ' days')->format('Y-m-d');
                    $planIns = $plan;
                    $compteMairieVal = 0;
                    if ($newCompteMairie) {
                        maire_ensure_commune_abonnement_table($pdo);
                        $crow = maire_load_commune_abonnement_row($pdo);
                        if ($crow !== null) {
                            $crow = maire_sync_commune_abonnement_actif($pdo, $crow);
                            $planIns = (string) ($crow['plan'] ?? $plan);
                            $debut = (string) ($crow['date_debut'] ?? $debut);
                            $fin = (string) ($crow['date_fin'] ?? $fin);
                        }
                        $pdo->exec('UPDATE abonnements SET compte_mairie = 0');
                        $compteMairieVal = 1;
                    }
                    $arVal = $newCompteMairie ? 0 : $newAutoRenew;
                    $rjVal = $newCompteMairie ? 30 : $newJoursRenew;
                    $ins = $pdo->prepare('
                        INSERT INTO abonnements (email, mot_de_passe_hash, plan, role_utilisateur, actif, compte_mairie, auto_renew, renouvellement_jours, date_debut, date_fin)
                        VALUES (:email, :hash, :plan, :role, 1, :cm, :ar, :rj, :d1, :d2)
                    ');
                    $ins->execute([
                        'email' => $email,
                        'hash' => password_hash($pass, PASSWORD_DEFAULT),
                        'plan' => $planIns,
                        'role' => $role,
                        'cm' => $compteMairieVal,
                        'ar' => $arVal,
                        'rj' => $rjVal,
                        'd1' => $debut,
                        'd2' => $fin,
                    ]);
                    if ($newCompteMairie) {
                        maire_sync_commune_vers_compte_mairie($pdo);
                    }
                    $feedback = $newCompteMairie ? 'Compte institutionnel mairie créé et aligné sur l’abonnement communal.' : 'Compte créé.';
                    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                } catch (Throwable $e) {
                    $feedback = 'Création impossible (e-mail déjà utilisé ou erreur base).';
                    $isError = true;
                }
            }
        }

        if ($action === 'commune_update' && $feedback === null && !$isError) {
            $sid = (int) ($_SESSION['subscriber_id'] ?? 0);
            $srole = (string) ($_SESSION['subscriber_role'] ?? '');
            if (!maire_peut_gerer_abonnement_communal($pdo, $sid, $estConsoleSecrete, $srole)) {
                $feedback = 'Seul le compte institutionnel de la mairie (ou la console secrète) peut modifier l’abonnement communal.';
                $isError = true;
            } else {
                $cPlan = trim((string) ($_POST['commune_plan'] ?? ''));
                $cDebut = trim((string) ($_POST['commune_date_debut'] ?? ''));
                $cFin = trim((string) ($_POST['commune_date_fin'] ?? ''));
                $cActif = isset($_POST['commune_actif']) ? 1 : 0;
                $cAutoRenew = isset($_POST['commune_auto_renew']) ? 1 : 0;
                $cJoursRenew = (int) ($_POST['commune_renouvellement_jours'] ?? 365);
                if (!in_array($cJoursRenew, $dureesRenouvellement, true)) {
                    $cJoursRenew = 365;
                }
                if (!in_array($cPlan, $communePlansForm, true)) {
                    $feedback = 'Plan communal invalide.';
                    $isError = true;
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cFin)) {
                    $feedback = 'Dates communales invalides.';
                    $isError = true;
                } else {
                    try {
                        maire_ensure_commune_abonnement_table($pdo);
                        $upc = $pdo->prepare('
                            UPDATE commune_abonnement
                            SET plan = :plan, date_debut = :d1, date_fin = :d2, actif = :actif,
                                auto_renew = :ar, renouvellement_jours = :rj
                            WHERE id = 1
                        ');
                        $upc->execute([
                            'plan' => $cPlan,
                            'd1' => $cDebut,
                            'd2' => $cFin,
                            'actif' => $cActif,
                            'ar' => $cAutoRenew,
                            'rj' => $cJoursRenew,
                        ]);
                        maire_sync_commune_vers_compte_mairie($pdo);
                        $rowLog = maire_load_commune_abonnement_row($pdo);
                        if ($rowLog !== null) {
                            $rowLog = maire_sync_commune_abonnement_actif($pdo, $rowLog);
                        }
                        $idMairieLog = maire_get_compte_mairie_id($pdo);
                        $actorSrc = $estConsoleSecrete
                            ? 'super_console'
                            : (($idMairieLog !== null && $sid === $idMairieLog) ? 'compte_mairie' : 'admin_provisoire');
                        maire_log_commune_abonnement($pdo, $rowLog, 'plan_change', null, $sid > 0 ? $sid : null, $actorSrc);
                        $feedback = 'Abonnement communal enregistré : il s’applique immédiatement à tout le site (portail, état civil, etc.).';
                        $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                    } catch (Throwable $e) {
                        $feedback = 'Impossible d’enregistrer l’abonnement communal.';
                        $isError = true;
                    }
                }
            }
        }

        if ($action === 'promouvoir_compte_mairie' && $feedback === null && !$isError) {
            $sid = (int) ($_SESSION['subscriber_id'] ?? 0);
            $srole = (string) ($_SESSION['subscriber_role'] ?? '');
            if ($estConsoleSecrete) {
                $feedback = 'En console secrète, désignez le compte institutionnel avec le bloc « Console secrète » ci-dessous (liste déroulante puis Transférer / désigner).';
                $isError = false;
            } elseif ($sid <= 0 || $srole !== 'admin') {
                $feedback = 'Connexion administrateur requise pour désigner le compte institutionnel.';
                $isError = true;
            } elseif (maire_get_compte_mairie_id($pdo) !== null) {
                $feedback = 'Un compte institutionnel est déjà désigné.';
                $isError = true;
            } else {
                try {
                    $pdo->exec('UPDATE abonnements SET compte_mairie = 0');
                    $upm = $pdo->prepare('UPDATE abonnements SET compte_mairie = 1, role_utilisateur = :r WHERE id = :id LIMIT 1');
                    $upm->execute(['r' => 'admin', 'id' => $sid]);
                    maire_sync_commune_vers_compte_mairie($pdo);
                    $_SESSION['subscriber_compte_mairie'] = true;
                    $_SESSION['subscriber_role'] = 'admin';
                    $rowLog = maire_load_commune_abonnement_row($pdo);
                    if ($rowLog !== null) {
                        $rowLog = maire_sync_commune_abonnement_actif($pdo, $rowLog);
                    }
                    maire_log_commune_abonnement(
                        $pdo,
                        $rowLog,
                        'mairie_promotion',
                        'compte_institutionnel_id=' . $sid,
                        $sid,
                        'admin_provisoire'
                    );
                    $feedback = 'Ce compte est maintenant le compte institutionnel mairie : seul il pourra modifier l’abonnement communal (hors console secrète).';
                    $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                } catch (Throwable $e) {
                    $feedback = 'Impossible d’enregistrer la promotion.';
                    $isError = true;
                }
            }
        }

        if ($action === 'transfer_compte_mairie' && $feedback === null && !$isError) {
            if (!$estConsoleSecrete) {
                $feedback = 'Le transfert du compte institutionnel est réservé à la console secrète.';
                $isError = true;
            } else {
                $tid = (int) ($_POST['transfer_cible_id'] ?? 0);
                $ancienId = maire_get_compte_mairie_id($pdo);
                if ($tid <= 0) {
                    $feedback = 'Compte cible invalide.';
                    $isError = true;
                } else {
                    try {
                        $chkT = $pdo->prepare('SELECT id, email FROM abonnements WHERE id = :id LIMIT 1');
                        $chkT->execute(['id' => $tid]);
                        $trow = $chkT->fetch();
                        if ($trow === false) {
                            $feedback = 'Compte cible introuvable.';
                            $isError = true;
                        } else {
                            $pdo->exec('UPDATE abonnements SET compte_mairie = 0');
                            $upT = $pdo->prepare('UPDATE abonnements SET compte_mairie = 1, role_utilisateur = :r WHERE id = :id LIMIT 1');
                            $upT->execute(['r' => 'admin', 'id' => $tid]);
                            maire_sync_commune_vers_compte_mairie($pdo);
                            $rowLog = maire_load_commune_abonnement_row($pdo);
                            if ($rowLog !== null) {
                                $rowLog = maire_sync_commune_abonnement_actif($pdo, $rowLog);
                            }
                            $detailTr = 'ancien_id=' . ($ancienId ?? 0) . ',nouveau_id=' . $tid;
                            maire_log_commune_abonnement($pdo, $rowLog, 'mairie_transfer', $detailTr, null, 'super_console');
                            $feedback = 'Compte institutionnel transféré sur le compte #' . $tid . ' (' . (string) ($trow['email'] ?? '') . ').';
                            $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
                        }
                    } catch (Throwable $e) {
                        $feedback = 'Impossible d’effectuer le transfert.';
                        $isError = true;
                    }
                }
            }
        }
    }
}

$communeRow = null;
$idCompteMairie = null;
$emailCompteMairieInst = '';
$afficherBlocCommune = false;
$peutPromouvoirMairie = false;

if (isset($pdo) && $pdo !== null) {
    try {
        $abonnements = $pdo->query('
            SELECT id, email, plan, role_utilisateur, actif, date_debut, date_fin, created_at, compte_mairie, auto_renew, renouvellement_jours
            FROM abonnements
            ORDER BY id DESC
        ')->fetchAll();
    } catch (Throwable $e) {
        $abonnements = [];
    }
    try {
        maire_ensure_commune_abonnement_table($pdo);
        $communeRow = maire_load_commune_abonnement_row($pdo);
        if ($communeRow !== null) {
            $communeRow = maire_sync_commune_abonnement_actif($pdo, $communeRow);
            $communePalierLibelle = maire_plan_vers_palier((string) ($communeRow['plan'] ?? ''));
        }
    } catch (Throwable $e) {
        $communeRow = null;
    }

    $idCompteMairie = maire_get_compte_mairie_id($pdo);
    if ($idCompteMairie !== null) {
        maire_sync_commune_vers_compte_mairie($pdo);
        try {
            $abonnements = $pdo->query('
                SELECT id, email, plan, role_utilisateur, actif, date_debut, date_fin, created_at, compte_mairie, auto_renew, renouvellement_jours
                FROM abonnements
                ORDER BY id DESC
            ')->fetchAll();
        } catch (Throwable $e) {
            $abonnements = [];
        }
        $stMail = $pdo->prepare('SELECT email FROM abonnements WHERE id = :id LIMIT 1');
        $stMail->execute(['id' => $idCompteMairie]);
        $emailCompteMairieInst = (string) ($stMail->fetchColumn() ?: '');
    }

    $subAdminId = (int) ($_SESSION['subscriber_id'] ?? 0);
    $subAdminRole = (string) ($_SESSION['subscriber_role'] ?? '');
    $afficherBlocCommune = $estConsoleSecrete
        || $idCompteMairie === null
        || ($idCompteMairie !== null && $subAdminId === $idCompteMairie);
    $peutPromouvoirMairie = $idCompteMairie === null
        && $subAdminRole === 'admin'
        && $subAdminId > 0
        && !$estConsoleSecrete;

    try {
        maire_ensure_commune_abonnement_historique_table($pdo);
        $historiqueCommune = $pdo->query('
            SELECT id, plan, actif, date_debut, date_fin, evenement, detail, actor_subscriber_id, actor_source, created_at
            FROM commune_abonnement_historique
            ORDER BY id DESC
            LIMIT 50
        ')->fetchAll();
    } catch (Throwable $e) {
        $historiqueCommune = [];
    }
}

require __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="detail-hero">
        <div class="container">
            <span class="detail-kicker">Espace mairie · Administration</span>
            <h1>Comptes &amp; abonnement communal</h1>
            <p><strong>Abonnement communal</strong> : une formule (Simple / Standard / Premium) pour <strong>toute la commune</strong>, modifiable par le <strong>compte institutionnel mairie</strong> (ou la console secrète). Dès l’enregistrement, le site entier suit ce palier. Les autres comptes servent aux <strong>agents</strong> (dossiers, référentiel).</p>
            <?php if ($estConsoleSecrete): ?>
                <p class="alert alert-success" style="margin-top:1rem;">Session <strong>console secrète</strong> active (durée limitée). Ne communiquez pas l’URL avec paramètre <code>?k=</code>.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section-shell page-intro">
        <div class="container subscription-grid">
            <?php if ($feedback !== null): ?>
                <p class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <?php if ($peutPromouvoirMairie): ?>
                <article class="card">
                    <h2>Désigner le compte institutionnel mairie</h2>
                    <p class="std-dash-note">Aucun compte dédié n’est encore défini. Le compte avec lequel vous êtes connecté (<strong><?php echo htmlspecialchars((string) ($_SESSION['subscriber_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>) pourra devenir le <strong>seul</strong> compte autorisé à modifier l’abonnement communal (hors console secrète).</p>
                    <form method="POST" class="admin-edit-form">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="promouvoir_compte_mairie">
                        <div class="detail-actions">
                            <button class="btn btn-primary" type="submit">Utiliser mon compte comme institutionnel mairie</button>
                        </div>
                    </form>
                </article>
            <?php endif; ?>

            <?php if ($communeRow !== null): ?>
                <?php if ($afficherBlocCommune): ?>
                <article class="card">
                    <h2>Abonnement communal (effet immédiat sur tout le site)</h2>
                    <p class="std-dash-note">Palier actuel : <strong><?php echo htmlspecialchars($communePalierLibelle, ENT_QUOTES, 'UTF-8'); ?></strong>. Enregistrement = application sur le portail public, l’état civil numérique, les garde d’accès, etc.</p>
                    <form method="POST" class="admin-edit-form">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="commune_update">
                        <label for="commune_plan">Formule communale</label>
                        <select id="commune_plan" name="commune_plan" required>
                            <?php foreach ($communePlansForm as $cp): ?>
                                <option value="<?php echo htmlspecialchars($cp, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string) ($communeRow['plan'] ?? '') === $cp) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cp, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="commune_date_debut">Date début</label>
                        <input id="commune_date_debut" name="commune_date_debut" type="date" required value="<?php echo htmlspecialchars(substr((string) ($communeRow['date_debut'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>">
                        <label for="commune_date_fin">Date fin</label>
                        <input id="commune_date_fin" name="commune_date_fin" type="date" required value="<?php echo htmlspecialchars(substr((string) ($communeRow['date_fin'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>">
                        <label><input type="checkbox" name="commune_actif" value="1" <?php echo (int) ($communeRow['actif'] ?? 0) === 1 ? 'checked' : ''; ?>> Abonnement communal actif</label>
                        <fieldset class="abonnement-fieldset" style="margin-top:1rem;">
                            <legend>Renouvellement automatique</legend>
                            <p class="std-dash-note">Si la date de fin passe et que cette case est cochée, la période est prolongée automatiquement (et tracée dans le journal ci-dessous). Le compte institutionnel mairie suit immédiatement ce nouveau cycle.</p>
                            <label><input type="checkbox" name="commune_auto_renew" value="1" <?php echo (int) ($communeRow['auto_renew'] ?? 0) === 1 ? 'checked' : ''; ?>> Activer le renouvellement automatique</label>
                            <label for="commune_renouvellement_jours" style="margin-top:0.5rem;">Durée du cycle</label>
                            <select id="commune_renouvellement_jours" name="commune_renouvellement_jours">
                                <?php $cJoursActuel = (int) ($communeRow['renouvellement_jours'] ?? 365); ?>
                                <?php foreach ($dureesRenouvellement as $dur): ?>
                                    <option value="<?php echo $dur; ?>" <?php echo $cJoursActuel === $dur ? 'selected' : ''; ?>><?php echo htmlspecialchars(maire_renouvellement_libelle($dur), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </fieldset>
                        <div class="detail-actions">
                            <button class="btn btn-primary" type="submit">Enregistrer l’offre communale</button>
                        </div>
                    </form>
                </article>
                <?php else: ?>
                <article class="card">
                    <h2>Abonnement communal</h2>
                    <p class="std-dash-note">La formule et les dates qui s’appliquent à <strong>tout le site</strong> sont modifiables uniquement depuis le compte institutionnel : <strong><?php echo htmlspecialchars($emailCompteMairieInst, ENT_QUOTES, 'UTF-8'); ?></strong> (ou la console secrète). Vous pouvez continuer à gérer les comptes agents ci-dessous.</p>
                </article>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($estConsoleSecrete && $abonnements !== []): ?>
                <article class="card">
                    <h2><?php echo $idCompteMairie === null ? 'Désigner le compte institutionnel' : 'Transférer le compte institutionnel'; ?> (console secrète)</h2>
                    <p class="std-dash-note"><?php if ($idCompteMairie === null): ?>Aucun compte institutionnel n’est encore défini : choisissez un compte ci-dessous (il passera <strong>administrateur</strong> et sera aligné sur l’abonnement communal).<?php else: ?>Désigner un autre compte comme <strong>seul</strong> habilité à modifier l’abonnement communal (hors cette console). Le compte cible devient administrateur et se voit aligner sur les dates et le plan communaux.<?php endif; ?></p>
                    <form method="POST" class="admin-edit-form">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="transfer_compte_mairie">
                        <label for="transfer_cible_id">Nouveau compte institutionnel</label>
                        <select id="transfer_cible_id" name="transfer_cible_id" required>
                            <?php foreach ($abonnements as $a): ?>
                                <option value="<?php echo (int) $a['id']; ?>">
                                    #<?php echo (int) $a['id']; ?> — <?php echo htmlspecialchars((string) ($a['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo (int) ($a['compte_mairie'] ?? 0) === 1 ? ' (actuel)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="detail-actions" style="margin-top:1rem;">
                            <button class="btn btn-primary" type="submit" onclick="return confirm('Confirmer cette désignation ?');"><?php echo $idCompteMairie === null ? 'Désigner ce compte' : 'Transférer'; ?></button>
                        </div>
                    </form>
                </article>
            <?php endif; ?>

            <article class="card">
                <h2>Créer un compte agent / accès personnel</h2>
                <form method="POST" class="admin-edit-form">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="create_abo">
                    <label for="new_email">E-mail</label>
                    <input id="new_email" name="new_email" type="email" required autocomplete="off">

                    <label for="new_password">Mot de passe initial</label>
                    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">

                    <label for="new_plan">Plan</label>
                    <select id="new_plan" name="new_plan">
                        <?php foreach ($plansAutorises as $p): ?>
                            <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $p === 'municipal_standard' ? 'selected' : ''; ?>><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="new_role">Rôle</label>
                    <select id="new_role" name="new_role">
                        <option value="subscriber">subscriber</option>
                        <option value="admin">admin</option>
                    </select>

                    <label for="new_jours">Durée (jours à partir d’aujourd’hui)</label>
                    <input id="new_jours" name="new_jours" type="number" min="1" max="3650" value="30">

                    <fieldset class="abonnement-fieldset" style="margin-top:0.75rem;">
                        <legend>Renouvellement automatique</legend>
                        <p class="std-dash-note">Pour les comptes agents : prolonge automatiquement la période quand elle expire. Ignoré pour le compte institutionnel mairie (lui suit l’abonnement communal).</p>
                        <label><input type="checkbox" name="new_auto_renew" value="1"> Activer le renouvellement automatique</label>
                        <label for="new_renouvellement_jours" style="margin-top:0.5rem;">Durée du cycle</label>
                        <select id="new_renouvellement_jours" name="new_renouvellement_jours">
                            <?php foreach ($dureesRenouvellement as $dur): ?>
                                <option value="<?php echo $dur; ?>" <?php echo $dur === 30 ? 'selected' : ''; ?>><?php echo htmlspecialchars(maire_renouvellement_libelle($dur), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <label style="display:block;margin-top:0.75rem;"><input type="checkbox" name="new_compte_mairie" value="1"> Compte institutionnel mairie (rôle admin obligatoire ; un seul ; plan et dates alignés sur l’abonnement communal)</label>

                    <div class="detail-actions">
                        <button class="btn btn-primary" type="submit">Créer le compte</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>Abonnements (<?php echo count($abonnements); ?>)</h2>
                <p class="std-dash-note">Un formulaire par compte. Laissez le mot de passe vide pour ne pas le modifier.</p>
                <?php if ($abonnements === []): ?>
                    <p>Aucun abonnement.</p>
                <?php else: ?>
                    <div class="admin-list" style="margin-top:1rem;">
                        <?php foreach ($abonnements as $abo): ?>
                            <?php
                            $estMairieRow = $idCompteMairie !== null && (int) $abo['id'] === $idCompteMairie;
                            ?>
                            <article class="admin-item">
                                <form method="POST" class="admin-edit-form">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="update_abo">
                                    <input type="hidden" name="id" value="<?php echo (int) $abo['id']; ?>">
                                    <p><strong>#<?php echo (int) $abo['id']; ?></strong> — créé <?php echo htmlspecialchars((string) ($abo['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($estMairieRow): ?> <span class="std-feed-badge std-feed-badge--success">Institutionnel mairie</span><?php endif; ?></p>
                                    <label>E-mail</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars((string) $abo['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <?php if ($estMairieRow): ?>
                                        <p class="std-dash-note">Plan, période et renouvellement automatique sont ceux de l’<strong>abonnement communal</strong> (ci-dessus). Ce compte les reflète automatiquement.</p>
                                        <input type="hidden" name="plan" value="<?php echo htmlspecialchars((string) $abo['plan'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="date_debut" value="<?php echo htmlspecialchars(substr((string) ($abo['date_debut'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="date_fin" value="<?php echo htmlspecialchars(substr((string) ($abo['date_fin'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="auto_renew" value="<?php echo (int) ($abo['auto_renew'] ?? 0) === 1 ? '1' : ''; ?>">
                                        <input type="hidden" name="renouvellement_jours" value="<?php echo (int) ($abo['renouvellement_jours'] ?? 30); ?>">
                                        <p><strong>Plan (miroir)</strong> : <code><?php echo htmlspecialchars((string) $abo['plan'], ENT_QUOTES, 'UTF-8'); ?></code> · du <?php echo htmlspecialchars(substr((string) ($abo['date_debut'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?> au <?php echo htmlspecialchars(substr((string) ($abo['date_fin'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?></p>
                                        <input type="hidden" name="role_utilisateur" value="admin">
                                        <p><strong>Rôle</strong> : administrateur (fixe)</p>
                                    <?php else: ?>
                                        <label>Plan</label>
                                        <select name="plan">
                                            <?php foreach ($plansAutorises as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string) $abo['plan'] === $p) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                            <?php if (!in_array((string) $abo['plan'], $plansAutorises, true)): ?>
                                                <option value="<?php echo htmlspecialchars((string) $abo['plan'], ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars((string) $abo['plan'], ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <label>Rôle</label>
                                        <select name="role_utilisateur">
                                            <?php foreach ($rolesAutorises as $r): ?>
                                                <option value="<?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string) ($abo['role_utilisateur'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Date début</label>
                                        <input type="date" name="date_debut" value="<?php echo htmlspecialchars(substr((string) ($abo['date_debut'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <label>Date fin</label>
                                        <input type="date" name="date_fin" value="<?php echo htmlspecialchars(substr((string) ($abo['date_fin'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <fieldset class="abonnement-fieldset" style="margin-top:0.6rem;">
                                            <legend>Renouvellement automatique</legend>
                                            <label><input type="checkbox" name="auto_renew" value="1" <?php echo (int) ($abo['auto_renew'] ?? 0) === 1 ? 'checked' : ''; ?>> Activer (prolonge la période à l’expiration)</label>
                                            <label style="margin-top:0.4rem;">Durée du cycle</label>
                                            <select name="renouvellement_jours">
                                                <?php $jActuel = (int) ($abo['renouvellement_jours'] ?? 30); ?>
                                                <?php foreach ($dureesRenouvellement as $dur): ?>
                                                    <option value="<?php echo $dur; ?>" <?php echo $jActuel === $dur ? 'selected' : ''; ?>><?php echo htmlspecialchars(maire_renouvellement_libelle($dur), ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </fieldset>
                                    <?php endif; ?>
                                    <label><input type="checkbox" name="actif" value="1" <?php echo (int) ($abo['actif'] ?? 0) === 1 ? 'checked' : ''; ?>> Compte actif</label>
                                    <?php if ((int) ($abo['auto_renew'] ?? 0) === 1): ?>
                                        <p class="std-dash-note" style="margin-top:0.4rem;"><span class="std-feed-badge std-feed-badge--success">Renouvellement auto</span> Cycle : <?php echo htmlspecialchars(maire_renouvellement_libelle((int) ($abo['renouvellement_jours'] ?? 30)), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <label>Nouveau mot de passe (optionnel)</label>
                                    <input type="password" name="nouveau_mot_de_passe" placeholder="Laisser vide pour conserver" autocomplete="new-password">
                                    <div class="admin-item-actions" style="margin-top:0.75rem;">
                                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Journal de l’abonnement communal</h2>
                <p class="std-dash-note">Dernières modifications (formule, dates, désignations, transferts console).</p>
                <?php if ($historiqueCommune === []): ?>
                    <p>Aucune entrée enregistrée pour le moment.</p>
                <?php else: ?>
                    <div class="standard-table-wrap" style="margin-top:0.75rem;">
                        <table class="standard-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Événement</th>
                                    <th>Plan</th>
                                    <th>Actif</th>
                                    <th>Période</th>
                                    <th>Acteur</th>
                                    <th>Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historiqueCommune as $h): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($h['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(maire_libelle_evenement_commune((string) ($h['evenement'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code><?php echo htmlspecialchars((string) ($h['plan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><?php echo (int) ($h['actif'] ?? 0) === 1 ? 'oui' : 'non'; ?></td>
                                        <td><?php echo htmlspecialchars(substr((string) ($h['date_debut'] ?? ''), 0, 10) . ' → ' . substr((string) ($h['date_fin'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(maire_libelle_actor_source_commune((string) ($h['actor_source'] ?? '')) . ((int) ($h['actor_subscriber_id'] ?? 0) > 0 ? ' #' . (int) $h['actor_subscriber_id'] : ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($h['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2>Navigation espace mairie</h2>
                <div class="detail-actions">
                    <a class="btn btn-outline-dark" href="standard.php">Administration — référentiel &amp; fil</a>
                    <a class="btn btn-outline-dark" href="paiements.php">Paiements</a>
                    <a class="btn btn-outline-dark" href="../abonnement.php">Page publique abonnement</a>
                    <?php if ($estConsoleSecrete): ?>
                        <form method="POST" action="super-admin-exit.php" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['abo_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-outline-dark" type="submit">Quitter la console secrète</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-dark" href="../deconnexion.php">Déconnexion complète</a>
                </div>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
