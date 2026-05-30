<?php
declare(strict_types=1);

/**
 * Codes OTP par SMS (vérification téléphone).
 */

require_once __DIR__ . '/sms-provider.php';
require_once __DIR__ . '/maire-rate-limit.php';

const MAIRE_OTP_LENGTH = 6;
const MAIRE_OTP_TTL_SECONDS = 600;
const MAIRE_OTP_MAX_ATTEMPTS = 5;
const MAIRE_OTP_VERIFIED_TTL_SECONDS = 900;

function maire_ensure_otp_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otp_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telephone VARCHAR(20) NOT NULL,
            scope VARCHAR(40) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_lookup (telephone, scope, expires_at),
            INDEX idx_otp_verified (verified_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Normalise un numéro sénégalais (+221…).
 */
function maire_normaliser_telephone_sn(?string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
    if ($digits === '') {
        return null;
    }
    if (str_starts_with($digits, '221') && strlen($digits) === 12) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '+221' . substr($digits, 1);
    }
    if (strlen($digits) === 9) {
        return '+221' . $digits;
    }
    if (str_starts_with($digits, '221') && strlen($digits) > 12) {
        return '+' . substr($digits, 0, 12);
    }
    return null;
}

function maire_otp_generer_code(): string
{
    return str_pad((string) random_int(0, 999999), MAIRE_OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Envoie un OTP par SMS. Retourne true ou false + message d'erreur.
 */
function maire_otp_envoyer(PDO $pdo, string $telephoneBrut, string $scope, ?string &$errMsg = null): bool
{
    maire_ensure_otp_table($pdo);
    $tel = maire_normaliser_telephone_sn($telephoneBrut);
    if ($tel === null) {
        $errMsg = 'Numéro de téléphone invalide (format Sénégal attendu : 77 XXX XX XX).';
        return false;
    }
    $scope = preg_replace('/[^a-z0-9_]/', '', strtolower($scope)) ?? 'default';
    if ($scope === '') {
        $scope = 'default';
    }

    if (!maire_rate_limit_allow('otp_send_' . $tel, 5, 3600)) {
        $errMsg = 'Trop de codes demandés pour ce numéro. Réessayez plus tard.';
        return false;
    }
    if (!maire_rate_limit_allow('otp_send_ip', 15, 3600)) {
        $errMsg = 'Trop de demandes depuis votre connexion. Réessayez plus tard.';
        return false;
    }

    $code = maire_otp_generer_code();
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = (new DateTimeImmutable('now'))->modify('+' . MAIRE_OTP_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');

    $pdo->prepare('DELETE FROM otp_verifications WHERE telephone = :t AND scope = :s AND verified_at IS NULL')
        ->execute(['t' => $tel, 's' => $scope]);

    $pdo->prepare('
        INSERT INTO otp_verifications (telephone, scope, code_hash, expires_at, attempts)
        VALUES (:t, :s, :h, :e, 0)
    ')->execute(['t' => $tel, 's' => $scope, 'h' => $hash, 'e' => $expires]);

    $message = 'Mairie Rufisque-Est : votre code de verification est ' . $code . '. Valide 10 min. Ne le partagez pas.';
    $smsErr = null;
    if (!maire_sms_provider_envoyer($tel, $message, $smsErr)) {
        $errMsg = $smsErr ?? 'Envoi SMS impossible. Réessayez ou contactez la mairie.';
        return false;
    }

    if (function_exists('maire_env') && maire_env('APP_ENV', 'production') === 'development') {
        $_SESSION['maire_otp_dev_hint'] = $code;
    }

    return true;
}

/**
 * Vérifie le code saisi. Marque la vérification comme réussie si OK.
 */
function maire_otp_verifier(PDO $pdo, string $telephoneBrut, string $scope, string $codeSaisi, ?string &$errMsg = null): bool
{
    maire_ensure_otp_table($pdo);
    $tel = maire_normaliser_telephone_sn($telephoneBrut);
    if ($tel === null) {
        $errMsg = 'Numéro invalide.';
        return false;
    }
    $scope = preg_replace('/[^a-z0-9_]/', '', strtolower($scope)) ?? 'default';
    $codeSaisi = preg_replace('/\D+/', '', $codeSaisi) ?? '';
    if (strlen($codeSaisi) !== MAIRE_OTP_LENGTH) {
        $errMsg = 'Code à 6 chiffres requis.';
        return false;
    }

    $st = $pdo->prepare('
        SELECT id, code_hash, expires_at, attempts
        FROM otp_verifications
        WHERE telephone = :t AND scope = :s AND verified_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ');
    $st->execute(['t' => $tel, 's' => $scope]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $errMsg = 'Aucun code actif. Cliquez sur « Recevoir le code ».';
        return false;
    }

    if ((int) ($row['attempts'] ?? 0) >= MAIRE_OTP_MAX_ATTEMPTS) {
        $errMsg = 'Trop de tentatives. Demandez un nouveau code.';
        return false;
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        $errMsg = 'Code expiré. Demandez un nouveau code.';
        return false;
    }

    $pdo->prepare('UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = :id')
        ->execute(['id' => (int) $row['id']]);

    if (!password_verify($codeSaisi, (string) $row['code_hash'])) {
        $errMsg = 'Code incorrect.';
        return false;
    }

    $pdo->prepare('UPDATE otp_verifications SET verified_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $row['id']]);

    return true;
}

/** Vérifie qu'un OTP a été validé récemment pour ce numéro et ce scope. */
function maire_otp_est_verifie(PDO $pdo, string $telephoneBrut, string $scope): bool
{
    maire_ensure_otp_table($pdo);
    $tel = maire_normaliser_telephone_sn($telephoneBrut);
    if ($tel === null) {
        return false;
    }
    $scope = preg_replace('/[^a-z0-9_]/', '', strtolower($scope)) ?? 'default';
    $since = (new DateTimeImmutable('now'))->modify('-' . MAIRE_OTP_VERIFIED_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
    $st = $pdo->prepare('
        SELECT id FROM otp_verifications
        WHERE telephone = :t AND scope = :s AND verified_at IS NOT NULL AND verified_at >= :since
        ORDER BY verified_at DESC
        LIMIT 1
    ');
    $st->execute(['t' => $tel, 's' => $scope, 'since' => $since]);
    return $st->fetchColumn() !== false;
}
