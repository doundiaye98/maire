<?php
declare(strict_types=1);

/**
 * Notifications municipales : composition, envoi mass\u00e9 (email + SMS),
 * historique avec audit par destinataire.
 *
 * Modèle :
 *  - `notifications`           : 1 ligne par broadcast
 *  - `notifications_envois`    : 1 ligne par destinataire/canal (audit)
 *  - `citoyens.accepte_notif_email` / `accepte_notif_sms` : opt-in (default 1)
 *
 * Pour l'envoi email, on tente `mail()`. En cas d'échec, on logue dans
 * `logs/notifications-email.log`. Pour le SMS, on délègue à `sms-provider.php`.
 */

require_once __DIR__ . '/sms-provider.php';

const MAIRE_NOTIFICATIONS_CATEGORIES = [
    'urgence'    => '🚨 Urgence',
    'meteo'      => '🌧 Alerte météo',
    'coupure'    => '💧 Coupure eau / électricité',
    'evenement'  => '🎉 Événement',
    'reunion'    => '🏛 Réunion publique',
    'info'       => 'ℹ Information',
    'autre'      => 'Autre',
];

const MAIRE_NOTIFICATIONS_CANAUX = [
    'email' => 'Email uniquement',
    'sms'   => 'SMS uniquement',
    'both'  => 'Email + SMS',
];

const MAIRE_NOTIFICATIONS_STATUTS = [
    'en_attente' => 'En attente',
    'envoye'     => 'Envoyé',
    'partiel'    => 'Partiel (erreurs)',
    'echec'      => 'Échec',
];

const MAIRE_NOTIFICATIONS_EMAIL_FROM = 'Rufisquest02@gmail.com';
const MAIRE_NOTIFICATIONS_EMAIL_FROM_NAME = 'Mairie de Rufisque-Est';

function maire_ensure_notifications_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categorie ENUM('urgence', 'meteo', 'coupure', 'evenement', 'reunion', 'info', 'autre') NOT NULL DEFAULT 'info',
            canal ENUM('email', 'sms', 'both') NOT NULL DEFAULT 'email',
            sujet VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            cible_quartier VARCHAR(120) DEFAULT NULL,
            nb_destinataires INT NOT NULL DEFAULT 0,
            nb_envois_ok INT NOT NULL DEFAULT 0,
            nb_envois_ko INT NOT NULL DEFAULT 0,
            statut ENUM('en_attente', 'envoye', 'partiel', 'echec') NOT NULL DEFAULT 'en_attente',
            envoye_par_email VARCHAR(190) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_notif_cat (categorie),
            INDEX idx_notif_date (created_at)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications_envois (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            citoyen_id INT DEFAULT NULL,
            canal ENUM('email', 'sms') NOT NULL,
            destinataire VARCHAR(190) NOT NULL,
            statut ENUM('ok', 'ko') NOT NULL DEFAULT 'ok',
            erreur VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_envoi_notif (notification_id),
            INDEX idx_envoi_citoyen (citoyen_id),
            CONSTRAINT fk_envoi_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
        )
    ");
    maire_ensure_citoyens_notif_columns($pdo);
}

/** Ajoute idempotentement les 2 colonnes opt-in sur `citoyens`. */
function maire_ensure_citoyens_notif_columns(PDO $pdo): void
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM citoyens")->fetchAll();
        $names = array_column($cols, 'Field');
        if (!in_array('accepte_notif_email', $names, true)) {
            $pdo->exec("ALTER TABLE citoyens ADD COLUMN accepte_notif_email TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!in_array('accepte_notif_sms', $names, true)) {
            $pdo->exec("ALTER TABLE citoyens ADD COLUMN accepte_notif_sms TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (Throwable $e) {
        // tolérant : si la table citoyens n'existe pas encore, rien à faire
    }
}

function maire_libelle_categorie_notification(string $code): string
{
    return MAIRE_NOTIFICATIONS_CATEGORIES[$code] ?? ucfirst($code);
}

function maire_libelle_canal_notification(string $code): string
{
    return MAIRE_NOTIFICATIONS_CANAUX[$code] ?? $code;
}

function maire_libelle_statut_notification(string $code): string
{
    return MAIRE_NOTIFICATIONS_STATUTS[$code] ?? $code;
}

function maire_classe_badge_categorie_notification(string $cat): string
{
    return match ($cat) {
        'urgence', 'meteo' => 'std-feed-badge--warning',
        'coupure' => 'std-feed-badge--warning',
        'evenement' => 'std-feed-badge--success',
        'reunion' => 'std-feed-badge--success',
        default => 'std-feed-badge',
    };
}

/**
 * Liste les quartiers distincts existants chez les citoyens (pour cible).
 *
 * @return list<string>
 */
function maire_liste_quartiers(PDO $pdo): array
{
    try {
        $st = $pdo->query("SELECT DISTINCT quartier FROM citoyens WHERE quartier IS NOT NULL AND quartier <> '' ORDER BY quartier ASC");
        return array_map('strval', array_column($st->fetchAll(), 'quartier'));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Sélectionne les destinataires d'une notification selon canal et filtre quartier.
 *
 * @return list<array{id:int,email:string,telephone:?string,prenom:string,nom:string,quartier:?string,accepte_notif_email:int,accepte_notif_sms:int}>
 */
function maire_selectionner_destinataires(PDO $pdo, string $canal, ?string $quartier = null): array
{
    maire_ensure_citoyens_notif_columns($pdo);
    $where = ['actif = 1'];
    $params = [];
    if ($quartier !== null && $quartier !== '') {
        $where[] = 'quartier = :q';
        $params['q'] = $quartier;
    }
    // Filtre opt-in selon canal
    if ($canal === 'email') {
        $where[] = 'accepte_notif_email = 1';
        $where[] = "email IS NOT NULL AND email <> ''";
    } elseif ($canal === 'sms') {
        $where[] = 'accepte_notif_sms = 1';
        $where[] = "telephone IS NOT NULL AND telephone <> ''";
    } else { // both : au moins un canal opt-in et identifiant valide
        $where[] = "((accepte_notif_email = 1 AND email IS NOT NULL AND email <> '') OR (accepte_notif_sms = 1 AND telephone IS NOT NULL AND telephone <> ''))";
    }
    try {
        $sql = 'SELECT id, email, telephone, prenom, nom, quartier, accepte_notif_email, accepte_notif_sms FROM citoyens WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Envoie un email (mail() PHP), avec fallback log fichier en cas d'échec.
 */
function maire_envoyer_email(string $to, string $sujet, string $message, ?string &$errMsg = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Email invalide';
        return false;
    }
    $from = MAIRE_NOTIFICATIONS_EMAIL_FROM;
    $fromName = MAIRE_NOTIFICATIONS_EMAIL_FROM_NAME;
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: Maire-Notifications/1.0',
    ];
    $body = $message . "\n\n--\n" . $fromName . "\nNe répondez pas à cet email automatique.";
    $sujetEnc = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    $ok = false;
    if (function_exists('mail')) {
        try {
            $ok = @mail($to, $sujetEnc, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            $errMsg = $e->getMessage();
            $ok = false;
        }
    }
    // Fallback log fichier (utile en dev / WAMP sans SMTP)
    maire_log_notification('email', $to, $sujet, $message, $ok ? 'ok' : 'mail_function_failed');
    if (!$ok && $errMsg === null) {
        $errMsg = 'mail() a échoué (probablement pas de SMTP configuré sur ce serveur)';
    }
    return $ok;
}

/**
 * Envoie un SMS via le provider configuré. Retourne true si livré.
 */
function maire_envoyer_sms(string $tel, string $message, ?string &$errMsg = null): bool
{
    $tel = preg_replace('/[^0-9+]/', '', $tel);
    if ($tel === null || strlen($tel) < 8) {
        $errMsg = 'Numéro invalide';
        return false;
    }
    return maire_sms_provider_envoyer($tel, $message, $errMsg);
}

/**
 * Trace un envoi (email ou SMS) dans un log fichier.
 */
function maire_log_notification(string $canal, string $destinataire, string $sujet, string $message, string $statut): void
{
    $dir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
    }
    $line = sprintf(
        "[%s] %s | %s | %s | sujet=%s | %s\n",
        date('c'),
        strtoupper($canal),
        $statut,
        $destinataire,
        str_replace(["\n", "\r"], ' ', $sujet),
        str_replace(["\n", "\r"], ' ', mb_substr($message, 0, 200))
    );
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'notifications.log', $line, FILE_APPEND);
}

/**
 * Compose une notification, sélectionne les destinataires et envoie tout.
 *
 * @param array{categorie:string,canal:string,sujet:string,message:string,cible_quartier:?string} $data
 * @return int|null id notification créée
 */
function maire_creer_et_envoyer_notification(PDO $pdo, array $data, ?string $envoyePar, ?string &$errMsg = null): ?int
{
    maire_ensure_notifications_tables($pdo);

    $categorie = (string) ($data['categorie'] ?? 'info');
    if (!array_key_exists($categorie, MAIRE_NOTIFICATIONS_CATEGORIES)) {
        $categorie = 'info';
    }
    $canal = (string) ($data['canal'] ?? 'email');
    if (!array_key_exists($canal, MAIRE_NOTIFICATIONS_CANAUX)) {
        $canal = 'email';
    }
    $sujet = trim((string) ($data['sujet'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    $quartier = trim((string) ($data['cible_quartier'] ?? ''));
    if ($quartier === '') {
        $quartier = null;
    }

    if ($sujet === '' || mb_strlen($sujet) > 180) {
        $errMsg = 'Sujet requis (≤ 180 caractères).';
        return null;
    }
    if ($message === '' || mb_strlen($message) > 5000) {
        $errMsg = 'Message requis (≤ 5000 caractères).';
        return null;
    }

    $destinataires = maire_selectionner_destinataires($pdo, $canal, $quartier);
    if (empty($destinataires)) {
        $errMsg = 'Aucun citoyen ne correspond aux critères de diffusion (pensez aux préférences de notification).';
        return null;
    }

    try {
        $ins = $pdo->prepare('INSERT INTO notifications (categorie, canal, sujet, message, cible_quartier, nb_destinataires, statut, envoye_par_email) VALUES (:c, :ca, :s, :m, :q, :n, "en_attente", :u)');
        $ins->execute([
            'c' => $categorie,
            'ca' => $canal,
            's' => mb_substr($sujet, 0, 180),
            'm' => mb_substr($message, 0, 5000),
            'q' => $quartier,
            'n' => count($destinataires),
            'u' => $envoyePar !== null ? mb_substr($envoyePar, 0, 190) : null,
        ]);
        $notifId = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $errMsg = 'Création échouée : ' . $e->getMessage();
        return null;
    }

    // Préfixer le message email pour identifier la source
    $prefixe = '[' . MAIRE_NOTIFICATIONS_EMAIL_FROM_NAME . ' · ' . trim(maire_libelle_categorie_notification($categorie)) . "]\n\n";
    $messageEmail = $prefixe . $message;
    // SMS plus court
    $messageSms = '[Mairie] ' . mb_substr($message, 0, 280);

    $nbOk = 0;
    $nbKo = 0;
    $insEnvoi = $pdo->prepare('INSERT INTO notifications_envois (notification_id, citoyen_id, canal, destinataire, statut, erreur) VALUES (:nid, :cid, :ca, :dest, :st, :err)');

    foreach ($destinataires as $d) {
        $citoyenId = (int) $d['id'];

        if (($canal === 'email' || $canal === 'both') && (int) ($d['accepte_notif_email'] ?? 0) === 1 && !empty($d['email'])) {
            $err = null;
            $ok = maire_envoyer_email((string) $d['email'], $sujet, $messageEmail, $err);
            try {
                $insEnvoi->execute([
                    'nid' => $notifId,
                    'cid' => $citoyenId,
                    'ca' => 'email',
                    'dest' => mb_substr((string) $d['email'], 0, 190),
                    'st' => $ok ? 'ok' : 'ko',
                    'err' => $ok ? null : mb_substr((string) ($err ?? ''), 0, 255),
                ]);
            } catch (Throwable $e) { /* tolérant */ }
            $ok ? $nbOk++ : $nbKo++;
        }

        if (($canal === 'sms' || $canal === 'both') && (int) ($d['accepte_notif_sms'] ?? 0) === 1 && !empty($d['telephone'])) {
            $err = null;
            $ok = maire_envoyer_sms((string) $d['telephone'], $messageSms, $err);
            try {
                $insEnvoi->execute([
                    'nid' => $notifId,
                    'cid' => $citoyenId,
                    'ca' => 'sms',
                    'dest' => mb_substr((string) $d['telephone'], 0, 190),
                    'st' => $ok ? 'ok' : 'ko',
                    'err' => $ok ? null : mb_substr((string) ($err ?? ''), 0, 255),
                ]);
            } catch (Throwable $e) { /* tolérant */ }
            $ok ? $nbOk++ : $nbKo++;
        }
    }

    $statut = match (true) {
        $nbOk > 0 && $nbKo === 0 => 'envoye',
        $nbOk > 0 && $nbKo > 0   => 'partiel',
        default                  => 'echec',
    };

    try {
        $pdo->prepare('UPDATE notifications SET nb_envois_ok = :ok, nb_envois_ko = :ko, statut = :s, sent_at = NOW() WHERE id = :id')
            ->execute(['ok' => $nbOk, 'ko' => $nbKo, 's' => $statut, 'id' => $notifId]);
    } catch (Throwable $e) { /* tolérant */ }

    return $notifId;
}

function maire_liste_notifications(PDO $pdo, int $limit = 50): array
{
    maire_ensure_notifications_tables($pdo);
    try {
        $st = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_load_notification(PDO $pdo, int $id): ?array
{
    try {
        $st = $pdo->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

function maire_liste_envois_notification(PDO $pdo, int $notificationId, int $limit = 500): array
{
    try {
        $st = $pdo->prepare('SELECT * FROM notifications_envois WHERE notification_id = :id ORDER BY id ASC LIMIT ' . max(1, min(5000, $limit)));
        $st->execute(['id' => $notificationId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function maire_compter_notifications(PDO $pdo): array
{
    maire_ensure_notifications_tables($pdo);
    $r = ['total' => 0, 'envoyees' => 0, 'echecs' => 0, 'destinataires' => 0];
    try {
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(statut IN ('envoye','partiel')) AS ok, SUM(statut = 'echec') AS ko, COALESCE(SUM(nb_destinataires), 0) AS dest FROM notifications")->fetch();
        if ($row !== false) {
            $r['total'] = (int) $row['total'];
            $r['envoyees'] = (int) $row['ok'];
            $r['echecs'] = (int) $row['ko'];
            $r['destinataires'] = (int) $row['dest'];
        }
    } catch (Throwable $e) { /* tolérant */ }
    return $r;
}

function maire_mettre_a_jour_preferences_notif_citoyen(PDO $pdo, int $citoyenId, bool $acceptEmail, bool $acceptSms): bool
{
    maire_ensure_citoyens_notif_columns($pdo);
    try {
        $st = $pdo->prepare('UPDATE citoyens SET accepte_notif_email = :e, accepte_notif_sms = :s WHERE id = :id');
        $st->execute([
            'e' => $acceptEmail ? 1 : 0,
            's' => $acceptSms ? 1 : 0,
            'id' => $citoyenId,
        ]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
