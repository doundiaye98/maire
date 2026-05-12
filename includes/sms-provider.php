<?php
declare(strict_types=1);

/**
 * Abstraction d'envoi SMS.
 *
 * Provider sélectionné via la constante MAIRE_SMS_PROVIDER :
 *   - 'log'      (défaut)  : écrit dans logs/sms-outbox.log (idéal en dev/préprod)
 *   - 'orange'             : Orange SMS API (Sénégal) — stub à compléter avec clés
 *   - 'twilio'             : Twilio — stub à compléter avec credentials
 *   - 'wari'               : Wari SMS — stub à compléter
 *
 * Pour activer un vrai provider en production, ajouter dans `config/database.php` :
 *
 *   define('MAIRE_SMS_PROVIDER', 'orange');
 *   define('MAIRE_SMS_ORANGE_TOKEN', '...');
 *   define('MAIRE_SMS_ORANGE_SENDER', 'tel:+221...');
 *
 * Tous les providers réels respectent un timeout de 10 s et renvoient false en cas d'erreur.
 */

if (!defined('MAIRE_SMS_PROVIDER')) {
    define('MAIRE_SMS_PROVIDER', 'log');
}

function maire_sms_provider_envoyer(string $tel, string $message, ?string &$errMsg = null): bool
{
    $provider = strtolower((string) MAIRE_SMS_PROVIDER);
    switch ($provider) {
        case 'orange':
            return maire_sms_provider_orange($tel, $message, $errMsg);
        case 'twilio':
            return maire_sms_provider_twilio($tel, $message, $errMsg);
        case 'wari':
            return maire_sms_provider_wari($tel, $message, $errMsg);
        case 'log':
        default:
            return maire_sms_provider_log($tel, $message, $errMsg);
    }
}

/**
 * Provider "log" — écrit dans logs/sms-outbox.log et retourne true (succès simulé).
 * Idéal pour dev/préprod sans budget SMS, pour valider la chaîne complète.
 */
function maire_sms_provider_log(string $tel, string $message, ?string &$errMsg = null): bool
{
    $dir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
    }
    $line = sprintf("[%s] SMS-LOG | to=%s | %s\n", date('c'), $tel, str_replace(["\n", "\r"], ' ', $message));
    $ok = @file_put_contents($dir . DIRECTORY_SEPARATOR . 'sms-outbox.log', $line, FILE_APPEND) !== false;
    if (!$ok) {
        $errMsg = 'Impossible d’écrire dans le log SMS';
    }
    return $ok;
}

/**
 * Provider Orange Sénégal — stub. À compléter quand la clé API est disponible.
 * Doc : https://developer.orange.com/apis/sms-sn
 */
function maire_sms_provider_orange(string $tel, string $message, ?string &$errMsg = null): bool
{
    if (!defined('MAIRE_SMS_ORANGE_TOKEN') || !defined('MAIRE_SMS_ORANGE_SENDER')) {
        $errMsg = 'Provider Orange non configuré (MAIRE_SMS_ORANGE_TOKEN/SENDER manquants).';
        return false;
    }
    $tel = preg_replace('/[^0-9+]/', '', $tel) ?? '';
    if (!str_starts_with($tel, '+')) {
        $tel = '+221' . ltrim($tel, '0');
    }
    $url = 'https://api.orange.com/smsmessaging/v1/outbound/' . rawurlencode((string) MAIRE_SMS_ORANGE_SENDER) . '/requests';
    $payload = json_encode([
        'outboundSMSMessageRequest' => [
            'address' => 'tel:' . $tel,
            'senderAddress' => (string) MAIRE_SMS_ORANGE_SENDER,
            'outboundSMSTextMessage' => ['message' => $message],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MAIRE_SMS_ORANGE_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        $errMsg = 'Orange API : ' . ($err !== '' ? $err : ('HTTP ' . $code));
        return false;
    }
    return true;
}

/**
 * Provider Twilio — stub.
 * Doc : https://www.twilio.com/docs/sms/api
 */
function maire_sms_provider_twilio(string $tel, string $message, ?string &$errMsg = null): bool
{
    if (!defined('MAIRE_SMS_TWILIO_SID') || !defined('MAIRE_SMS_TWILIO_TOKEN') || !defined('MAIRE_SMS_TWILIO_FROM')) {
        $errMsg = 'Provider Twilio non configuré (SID/TOKEN/FROM manquants).';
        return false;
    }
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string) MAIRE_SMS_TWILIO_SID) . '/Messages.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'To' => $tel,
            'From' => (string) MAIRE_SMS_TWILIO_FROM,
            'Body' => $message,
        ]),
        CURLOPT_USERPWD => MAIRE_SMS_TWILIO_SID . ':' . MAIRE_SMS_TWILIO_TOKEN,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $code >= 400) {
        $errMsg = 'Twilio API : ' . ($err !== '' ? $err : ('HTTP ' . $code));
        return false;
    }
    return true;
}

/**
 * Provider Wari — stub.
 * À compléter selon l'API officielle Wari SMS Sénégal.
 */
function maire_sms_provider_wari(string $tel, string $message, ?string &$errMsg = null): bool
{
    if (!defined('MAIRE_SMS_WARI_TOKEN') || !defined('MAIRE_SMS_WARI_ENDPOINT')) {
        $errMsg = 'Provider Wari non configuré (TOKEN/ENDPOINT manquants).';
        return false;
    }
    $ch = curl_init((string) MAIRE_SMS_WARI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['to' => $tel, 'message' => $message]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MAIRE_SMS_WARI_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $code >= 400) {
        $errMsg = 'Wari API : ' . ($err !== '' ? $err : ('HTTP ' . $code));
        return false;
    }
    return true;
}

function maire_sms_provider_actuel_libelle(): string
{
    return match (strtolower((string) MAIRE_SMS_PROVIDER)) {
        'orange' => 'Orange Sénégal',
        'twilio' => 'Twilio',
        'wari' => 'Wari',
        default => 'Mode journal (log fichier)',
    };
}
