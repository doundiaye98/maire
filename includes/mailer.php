<?php
declare(strict_types=1);

/**
 * Mailer SMTP authentifié — pur PHP (stream sockets), sans PHPMailer.
 *
 * Supporte :
 *  - SMTP plain (port 25 / 587 / 2525)
 *  - STARTTLS (port 587 — recommandé pour Gmail, Office 365, OVH, etc.)
 *  - SMTPS direct (port 465 — TLS dès la connexion)
 *  - Authentification AUTH LOGIN (base64)
 *  - En-têtes UTF-8 (sujet encodé base64, body 7-bit safe)
 *  - Fallback automatique vers mail() si SMTP n'est pas configuré
 *
 * Variables d'env attendues (chargées via env-loader.php) :
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587
 *   MAIL_USERNAME=Rufisquest02@gmail.com
 *   MAIL_PASSWORD=xxxx_app_password_xxxx
 *   MAIL_FROM_NAME="Mairie de Rufisque-Est"
 *   MAIL_FROM_EMAIL=Rufisquest02@gmail.com
 *   MAIL_ENCRYPTION=tls       # 'tls' (STARTTLS sur 587) | 'ssl' (port 465) | 'none'
 *
 * Usage :
 *   require_once __DIR__ . '/includes/mailer.php';
 *   maire_mailer_send('destinataire@x.sn', 'Sujet', 'Corps du message');
 */

/**
 * Envoie un email. Retourne true en cas de succès.
 *
 * @param string      $to         Adresse destinataire
 * @param string      $subject    Sujet (UTF-8)
 * @param string      $body       Corps texte (UTF-8, peut contenir des sauts de ligne)
 * @param string|null $errMsg     Référence sortante : message d'erreur si échec
 * @param array       $opts       Options : ['reply_to' => '...', 'html' => bool, 'from_email' => '...', 'from_name' => '...']
 */
function maire_mailer_send(string $to, string $subject, string $body, ?string &$errMsg = null, array $opts = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Adresse destinataire invalide.';
        return false;
    }

    $host = defined('MAIRE_MAIL_HOST') ? (string) MAIRE_MAIL_HOST : '';
    $user = defined('MAIRE_MAIL_USERNAME') ? (string) MAIRE_MAIL_USERNAME : '';
    $pass = defined('MAIRE_MAIL_PASSWORD') ? (string) MAIRE_MAIL_PASSWORD : '';

    // SMTP non configuré → fallback vers mail() natif (utile en dev local WAMP)
    if ($host === '' || $user === '' || $pass === '') {
        return maire_mailer_send_via_native($to, $subject, $body, $errMsg, $opts);
    }

    return maire_mailer_send_via_smtp($to, $subject, $body, $errMsg, $opts);
}

/**
 * Fallback : envoi via mail() PHP natif. Souvent KO sur WAMP local sans SMTP relay.
 */
function maire_mailer_send_via_native(string $to, string $subject, string $body, ?string &$errMsg, array $opts): bool
{
    $fromEmail = (string) ($opts['from_email'] ?? (defined('MAIRE_MAIL_FROM_EMAIL') ? MAIRE_MAIL_FROM_EMAIL : 'no-reply@localhost'));
    $fromName  = (string) ($opts['from_name']  ?? (defined('MAIRE_MAIL_FROM_NAME')  ? MAIRE_MAIL_FROM_NAME  : 'Mairie'));
    $isHtml    = (bool)   ($opts['html'] ?? false);
    $replyTo   = isset($opts['reply_to']) ? (string) $opts['reply_to'] : $fromEmail;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . maire_mailer_encode_address($fromName, $fromEmail),
        'Reply-To: ' . $replyTo,
        'X-Mailer: Maire-Mailer/1.0',
    ];
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    try {
        $ok = @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));
    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        return false;
    }
    if (!$ok && $errMsg === null) {
        $errMsg = "mail() natif a échoué (SMTP non configuré sur ce serveur).";
    }
    return $ok;
}

/**
 * Envoi via socket SMTP authentifié.
 */
function maire_mailer_send_via_smtp(string $to, string $subject, string $body, ?string &$errMsg, array $opts): bool
{
    $host = (string) MAIRE_MAIL_HOST;
    $port = (int) (defined('MAIRE_MAIL_PORT') ? MAIRE_MAIL_PORT : 587);
    $user = (string) MAIRE_MAIL_USERNAME;
    $pass = (string) MAIRE_MAIL_PASSWORD;
    $encryption = strtolower(defined('MAIRE_MAIL_ENCRYPTION') ? (string) MAIRE_MAIL_ENCRYPTION : 'tls');

    $fromEmail = (string) ($opts['from_email'] ?? (defined('MAIRE_MAIL_FROM_EMAIL') ? MAIRE_MAIL_FROM_EMAIL : $user));
    $fromName  = (string) ($opts['from_name']  ?? (defined('MAIRE_MAIL_FROM_NAME')  ? MAIRE_MAIL_FROM_NAME  : 'Mairie'));
    $isHtml    = (bool)   ($opts['html'] ?? false);
    $replyTo   = isset($opts['reply_to']) ? (string) $opts['reply_to'] : $fromEmail;

    // SSL direct (port 465) : préfixer hôte par ssl://
    $remote = ($encryption === 'ssl') ? 'ssl://' . $host : $host;

    $errno = 0;
    $errstr = '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);
    $fp = @stream_socket_client(
        $remote . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($fp === false) {
        $errMsg = "Connexion SMTP impossible vers {$host}:{$port} — {$errstr} ({$errno})";
        return false;
    }
    stream_set_timeout($fp, 15);

    $readResponse = static function ($fp): string {
        $resp = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $resp .= $line;
            if (strlen($line) < 4 || (isset($line[3]) && $line[3] === ' ')) break;
        }
        return $resp;
    };
    $writeCommand = static function ($fp, string $cmd) use ($readResponse): string {
        fwrite($fp, $cmd . "\r\n");
        return $readResponse($fp);
    };
    $expect = static function (string $response, string $expectedCode, ?string &$errMsg, string $stage): bool {
        if (!str_starts_with(trim($response), $expectedCode)) {
            $errMsg = "SMTP {$stage} a échoué : " . trim($response);
            return false;
        }
        return true;
    };

    $greeting = $readResponse($fp);
    if (!$expect($greeting, '220', $errMsg, 'banner')) { fclose($fp); return false; }

    $ehloHost = parse_url((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $resp = $writeCommand($fp, 'EHLO ' . $ehloHost);
    if (!$expect($resp, '250', $errMsg, 'EHLO')) { fclose($fp); return false; }

    // STARTTLS sur 587 (et autres ports non-SSL)
    if ($encryption === 'tls') {
        $resp = $writeCommand($fp, 'STARTTLS');
        if (!$expect($resp, '220', $errMsg, 'STARTTLS')) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            $errMsg = 'Activation TLS échouée.';
            fclose($fp);
            return false;
        }
        $resp = $writeCommand($fp, 'EHLO ' . $ehloHost);
        if (!$expect($resp, '250', $errMsg, 'EHLO (post-STARTTLS)')) { fclose($fp); return false; }
    }

    // Authentification AUTH LOGIN (base64)
    $resp = $writeCommand($fp, 'AUTH LOGIN');
    if (!$expect($resp, '334', $errMsg, 'AUTH LOGIN')) { fclose($fp); return false; }
    $resp = $writeCommand($fp, base64_encode($user));
    if (!$expect($resp, '334', $errMsg, 'AUTH user')) { fclose($fp); return false; }
    $resp = $writeCommand($fp, base64_encode($pass));
    if (!$expect($resp, '235', $errMsg, 'AUTH password')) { fclose($fp); return false; }

    // Enveloppe
    $resp = $writeCommand($fp, 'MAIL FROM:<' . $fromEmail . '>');
    if (!$expect($resp, '250', $errMsg, 'MAIL FROM')) { fclose($fp); return false; }
    $resp = $writeCommand($fp, 'RCPT TO:<' . $to . '>');
    if (!$expect($resp, '250', $errMsg, 'RCPT TO')) { fclose($fp); return false; }

    // DATA + corps
    $resp = $writeCommand($fp, 'DATA');
    if (!$expect($resp, '354', $errMsg, 'DATA')) { fclose($fp); return false; }

    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $messageId = '<' . bin2hex(random_bytes(8)) . '@' . $ehloHost . '>';
    $date = date('r');
    $contentType = $isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';

    $message  = "Date: {$date}\r\n";
    $message .= 'From: ' . maire_mailer_encode_address($fromName, $fromEmail) . "\r\n";
    $message .= 'To: <' . $to . ">\r\n";
    $message .= 'Reply-To: <' . $replyTo . ">\r\n";
    $message .= "Message-ID: {$messageId}\r\n";
    $message .= 'Subject: ' . $subjectEncoded . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= 'Content-Type: ' . $contentType . "\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n";
    $message .= "X-Mailer: Maire-Mailer/1.0\r\n";
    $message .= "\r\n";
    // Échappement RFC 5321 : ligne commençant par "." → ".."
    $body = preg_replace('/(^|\r?\n)\./', '$1..', $body) ?? $body;
    $message .= $body;
    $message .= "\r\n.";

    $resp = $writeCommand($fp, $message);
    if (!$expect($resp, '250', $errMsg, 'envoi corps')) { fclose($fp); return false; }

    $writeCommand($fp, 'QUIT');
    fclose($fp);
    return true;
}

/**
 * Encode "Nom Affiché" <email> pour les en-têtes From / To.
 */
function maire_mailer_encode_address(string $name, string $email): string
{
    $name = trim($name);
    if ($name === '') {
        return '<' . $email . '>';
    }
    if (preg_match('/^[\x20-\x7E]+$/', $name)) {
        // ASCII pur : guillemets simples
        return '"' . str_replace('"', '\\"', $name) . '" <' . $email . '>';
    }
    return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
}

/**
 * Retourne true si SMTP est configuré (host + user + pass renseignés).
 */
function maire_mailer_smtp_configured(): bool
{
    return defined('MAIRE_MAIL_HOST') && (string) MAIRE_MAIL_HOST !== ''
        && defined('MAIRE_MAIL_USERNAME') && (string) MAIRE_MAIL_USERNAME !== ''
        && defined('MAIRE_MAIL_PASSWORD') && (string) MAIRE_MAIL_PASSWORD !== '';
}
