<?php
declare(strict_types=1);

/**
 * Abstraction des passerelles de paiement mobile.
 *
 * - "log" (par défaut, dev / démo) : génère un lien de simulation interne qui
 *   pointe vers la page admin de validation manuelle, sans appel HTTP externe.
 * - "orange_money" : stub appelant l'API Orange Money Web Payment (à activer
 *   en production avec les clés Merchant Key / Token, après contrat avec Orange).
 * - "wave" : stub appelant l'API Wave Checkout (à activer avec votre Business
 *   Key et webhook secret après inscription Wave Business).
 *
 * Toutes les fonctions retournent un array :
 *   [
 *     'ok'           => bool,
 *     'redirect_url' => string|null,   // URL vers laquelle rediriger l'utilisateur
 *     'provider_ref' => string|null,   // référence externe (transaction id)
 *     'payload'      => array,         // données brutes du provider (loggées en BDD)
 *     'error'        => string|null,
 *   ]
 */

const MAIRE_PAIEMENT_PROVIDERS = [
    'log'          => 'Mode démonstration (validation manuelle)',
    'orange_money' => 'Orange Money',
    'wave'         => 'Wave',
    'free_money'   => 'Free Money',
];

if (!defined('MAIRE_PAIEMENT_PROVIDER_DEFAUT')) {
    define('MAIRE_PAIEMENT_PROVIDER_DEFAUT', 'log');
}
if (!defined('MAIRE_PAIEMENT_DEVISE')) {
    define('MAIRE_PAIEMENT_DEVISE', 'XOF');
}
// Les constantes MAIRE_WAVE_API_KEY, MAIRE_ORANGE_MERCHANT_KEY, etc.
// sont définies par includes/env-loader.php depuis les variables .env.
// Fallbacks défensifs pour les contextes qui n'incluent pas le loader (CLI, tests).
if (!defined('MAIRE_PAIEMENT_WEBHOOK_SECRET')) {
    define('MAIRE_PAIEMENT_WEBHOOK_SECRET', 'maire-demo-webhook-secret-change-me');
}
if (!defined('MAIRE_ORANGE_MERCHANT_KEY')) {
    define('MAIRE_ORANGE_MERCHANT_KEY', '');
}
if (!defined('MAIRE_ORANGE_AUTH_HEADER')) {
    define('MAIRE_ORANGE_AUTH_HEADER', '');
}
if (!defined('MAIRE_WAVE_API_KEY')) {
    define('MAIRE_WAVE_API_KEY', '');
}
if (!defined('MAIRE_WAVE_WEBHOOK_SECRET')) {
    define('MAIRE_WAVE_WEBHOOK_SECRET', '');
}
if (!defined('MAIRE_FREE_MONEY_API_KEY')) {
    define('MAIRE_FREE_MONEY_API_KEY', '');
}
if (!defined('MAIRE_FREE_MONEY_API_SECRET')) {
    define('MAIRE_FREE_MONEY_API_SECRET', '');
}
if (!defined('MAIRE_FREE_MONEY_ENDPOINT')) {
    define('MAIRE_FREE_MONEY_ENDPOINT', '');
}

function maire_paiement_provider_libelle(string $code): string
{
    return MAIRE_PAIEMENT_PROVIDERS[$code] ?? ucfirst($code);
}

/**
 * Retourne l'état de configuration d'un provider et, le cas échéant,
 * le message à afficher à l'utilisateur / l'admin.
 *
 * @return array{configured:bool,error:?string}
 */
function maire_paiement_provider_configuration(string $provider): array
{
    return match ($provider) {
        'orange_money' => (MAIRE_ORANGE_MERCHANT_KEY !== '' && MAIRE_ORANGE_AUTH_HEADER !== '')
            ? ['configured' => true, 'error' => null]
            : ['configured' => false, 'error' => 'Orange Money non configuré : renseignez ORANGE_MONEY_MERCHANT_KEY et ORANGE_MONEY_AUTH_HEADER dans .env.'],
        'wave' => (MAIRE_WAVE_API_KEY !== '')
            ? ['configured' => true, 'error' => null]
            : ['configured' => false, 'error' => 'Wave non configuré : renseignez WAVE_API_KEY dans .env.'],
        'free_money' => (MAIRE_FREE_MONEY_API_KEY !== '' && MAIRE_FREE_MONEY_API_SECRET !== '' && MAIRE_FREE_MONEY_ENDPOINT !== '')
            ? ['configured' => true, 'error' => null]
            : ['configured' => false, 'error' => 'Free Money non configuré : renseignez FREE_MONEY_ENDPOINT, FREE_MONEY_API_KEY et FREE_MONEY_API_SECRET dans .env.'],
        default => ['configured' => true, 'error' => null],
    };
}

/**
 * Dispatche l'initiation vers le provider choisi.
 *
 * @param array{reference:string,montant:float,devise:string,libelle:string,return_url:string,cancel_url:string,webhook_url:string,client_email:?string,client_telephone:?string} $ctx
 * @return array{ok:bool,redirect_url:?string,provider_ref:?string,payload:array,error:?string}
 */
function maire_paiement_initier(string $provider, array $ctx): array
{
    return match ($provider) {
        'orange_money' => maire_paiement_provider_orange_money($ctx),
        'wave'         => maire_paiement_provider_wave($ctx),
        'free_money'   => maire_paiement_provider_free_money($ctx),
        default        => maire_paiement_provider_log($ctx),
    };
}

/**
 * Mode démo : génère un lien vers /paiement-retour.php?ref=...&simulate=ok
 * (simulation côté client). Aucune intégration externe.
 */
function maire_paiement_provider_log(array $ctx): array
{
    $base = (string) ($ctx['return_url'] ?? '');
    $sep = (str_contains($base, '?') ? '&' : '?');
    $url = $base . $sep . 'simulate=ok';
    $providerRef = 'LOG-' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 8);
    return [
        'ok' => true,
        'redirect_url' => $url,
        'provider_ref' => $providerRef,
        'payload' => [
            'provider' => 'log',
            'amount' => $ctx['montant'] ?? null,
            'currency' => $ctx['devise'] ?? null,
            'reference' => $ctx['reference'] ?? null,
            'simulated' => true,
        ],
        'error' => null,
    ];
}

/**
 * Orange Money Web Payment.
 *
 * Doc officielle : https://developer.orange.com/apis/om-webpay/
 * Endpoint sandbox : https://api.orange.com/orange-money-webpay/dev/v1/webpayment
 * Endpoint production : https://api.orange.com/orange-money-webpay/prod/v1/webpayment
 *
 * Pré-requis :
 *   - Compte marchand Orange Money Business
 *   - MERCHANT_KEY (clé identifiant le compte marchand)
 *   - AUTH_HEADER (token Basic Auth obtenu via la console Orange Developer)
 */
function maire_paiement_provider_orange_money(array $ctx): array
{
    $config = maire_paiement_provider_configuration('orange_money');
    if (!$config['configured']) {
        return [
            'ok' => false,
            'redirect_url' => null,
            'provider_ref' => null,
            'payload' => ['provider' => 'orange_money', 'configured' => false],
            'error' => $config['error'],
        ];
    }

    if (!function_exists('curl_init')) {
        return maire_paiement_failure('orange_money', "Extension PHP cURL requise pour appeler Orange Money.");
    }

    $endpoint = (string) (maire_env('ORANGE_MONEY_ENDPOINT', 'https://api.orange.com/orange-money-webpay/prod/v1/webpayment'));
    $body = [
        'merchant_key' => MAIRE_ORANGE_MERCHANT_KEY,
        'currency' => (string) ($ctx['devise'] ?? 'XOF'),
        'order_id' => (string) ($ctx['reference'] ?? ''),
        'amount' => (float) ($ctx['montant'] ?? 0),
        'return_url' => (string) ($ctx['return_url'] ?? ''),
        'cancel_url' => (string) ($ctx['cancel_url'] ?? ''),
        'notif_url' => (string) ($ctx['webhook_url'] ?? ''),
        'lang' => 'fr',
        'reference' => mb_substr((string) ($ctx['libelle'] ?? 'Paiement'), 0, 50),
    ];

    [$httpCode, $rawResp, $curlErr] = maire_paiement_http_post(
        $endpoint,
        json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        [
            'Authorization: Basic ' . MAIRE_ORANGE_AUTH_HEADER,
            'Content-Type: application/json',
            'Accept: application/json',
        ]
    );

    if ($curlErr !== '') {
        maire_log_warning('orange_money_http_error', ['curl_err' => $curlErr, 'order_id' => $body['order_id']]);
        return maire_paiement_failure('orange_money', "Erreur réseau Orange Money : {$curlErr}");
    }
    $resp = json_decode($rawResp, true);
    if (!is_array($resp) || empty($resp['payment_url']) || empty($resp['pay_token'])) {
        maire_log_warning('orange_money_bad_response', ['http' => $httpCode, 'resp' => mb_substr($rawResp, 0, 500)]);
        return maire_paiement_failure('orange_money', "Réponse Orange Money invalide (HTTP {$httpCode}).");
    }

    return [
        'ok' => true,
        'redirect_url' => (string) $resp['payment_url'],
        'provider_ref' => (string) $resp['pay_token'],
        'payload' => array_merge(['provider' => 'orange_money'], $resp),
        'error' => null,
    ];
}

/**
 * Wave Checkout.
 *
 * Doc officielle : https://docs.wave.com/business/api/v1/
 * Endpoint : POST https://api.wave.com/v1/checkout/sessions
 *
 * Pré-requis :
 *   - Compte Wave Business (https://business.wave.com)
 *   - API Key générée dans Settings → API Keys
 *   - Webhook Secret pour vérifier les notifications de paiement
 */
function maire_paiement_provider_wave(array $ctx): array
{
    $config = maire_paiement_provider_configuration('wave');
    if (!$config['configured']) {
        return [
            'ok' => false,
            'redirect_url' => null,
            'provider_ref' => null,
            'payload' => ['provider' => 'wave', 'configured' => false],
            'error' => $config['error'],
        ];
    }
    if (!function_exists('curl_init')) {
        return maire_paiement_failure('wave', "Extension PHP cURL requise pour appeler Wave.");
    }

    $endpoint = (string) maire_env('WAVE_API_ENDPOINT', 'https://api.wave.com/v1/checkout/sessions');
    $body = [
        'amount' => (string) round((float) ($ctx['montant'] ?? 0)),
        'currency' => (string) ($ctx['devise'] ?? 'XOF'),
        'success_url' => (string) ($ctx['return_url'] ?? ''),
        'error_url' => (string) ($ctx['cancel_url'] ?? ''),
        'client_reference' => (string) ($ctx['reference'] ?? ''),
    ];
    if (!empty($ctx['client_telephone'])) {
        $body['restrict_payer_mobile'] = '+' . ltrim((string) $ctx['client_telephone'], '+');
    }

    [$httpCode, $rawResp, $curlErr] = maire_paiement_http_post(
        $endpoint,
        json_encode($body, JSON_UNESCAPED_UNICODE) ?: '',
        [
            'Authorization: Bearer ' . MAIRE_WAVE_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key: ' . (string) ($ctx['reference'] ?? bin2hex(random_bytes(8))),
        ]
    );

    if ($curlErr !== '') {
        maire_log_warning('wave_http_error', ['curl_err' => $curlErr, 'ref' => $body['client_reference']]);
        return maire_paiement_failure('wave', "Erreur réseau Wave : {$curlErr}");
    }
    $resp = json_decode($rawResp, true);
    if (!is_array($resp) || empty($resp['wave_launch_url']) || empty($resp['id'])) {
        maire_log_warning('wave_bad_response', ['http' => $httpCode, 'resp' => mb_substr($rawResp, 0, 500)]);
        return maire_paiement_failure('wave', "Réponse Wave invalide (HTTP {$httpCode}).");
    }

    return [
        'ok' => true,
        'redirect_url' => (string) $resp['wave_launch_url'],
        'provider_ref' => (string) $resp['id'],
        'payload' => array_merge(['provider' => 'wave'], $resp),
        'error' => null,
    ];
}

/**
 * Free Money (Tigo Cash / Free Sénégal).
 *
 * À ce jour, Free Money ne propose pas d'API publique standardisée comme Wave/Orange.
 * L'intégration nécessite un contrat marchand avec Free Sénégal qui fournit un
 * endpoint dédié et un protocole HTTP signé (HMAC ou Basic).
 *
 * Pré-requis :
 *   - Contrat marchand Free Sénégal (B2B)
 *   - FREE_MONEY_ENDPOINT (URL fournie par Free)
 *   - FREE_MONEY_API_KEY + FREE_MONEY_API_SECRET (HMAC SHA-256 du body)
 */
function maire_paiement_provider_free_money(array $ctx): array
{
    $config = maire_paiement_provider_configuration('free_money');
    if (!$config['configured']) {
        return [
            'ok' => false,
            'redirect_url' => null,
            'provider_ref' => null,
            'payload' => ['provider' => 'free_money', 'configured' => false],
            'error' => $config['error'],
        ];
    }
    if (!function_exists('curl_init')) {
        return maire_paiement_failure('free_money', "Extension PHP cURL requise pour appeler Free Money.");
    }

    $body = [
        'merchant_key' => MAIRE_FREE_MONEY_API_KEY,
        'amount' => (float) ($ctx['montant'] ?? 0),
        'currency' => (string) ($ctx['devise'] ?? 'XOF'),
        'reference' => (string) ($ctx['reference'] ?? ''),
        'label' => mb_substr((string) ($ctx['libelle'] ?? 'Paiement'), 0, 100),
        'return_url' => (string) ($ctx['return_url'] ?? ''),
        'cancel_url' => (string) ($ctx['cancel_url'] ?? ''),
        'notify_url' => (string) ($ctx['webhook_url'] ?? ''),
        'timestamp' => time(),
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE) ?: '';
    $signature = hash_hmac('sha256', $payload, MAIRE_FREE_MONEY_API_SECRET);

    [$httpCode, $rawResp, $curlErr] = maire_paiement_http_post(
        MAIRE_FREE_MONEY_ENDPOINT,
        $payload,
        [
            'X-Merchant-Key: ' . MAIRE_FREE_MONEY_API_KEY,
            'X-Signature: ' . $signature,
            'Content-Type: application/json',
            'Accept: application/json',
        ]
    );

    if ($curlErr !== '') {
        maire_log_warning('free_money_http_error', ['curl_err' => $curlErr, 'ref' => $body['reference']]);
        return maire_paiement_failure('free_money', "Erreur réseau Free Money : {$curlErr}");
    }
    $resp = json_decode($rawResp, true);
    if (!is_array($resp) || empty($resp['payment_url']) || empty($resp['transaction_id'])) {
        maire_log_warning('free_money_bad_response', ['http' => $httpCode, 'resp' => mb_substr($rawResp, 0, 500)]);
        return maire_paiement_failure('free_money', "Réponse Free Money invalide (HTTP {$httpCode}).");
    }

    return [
        'ok' => true,
        'redirect_url' => (string) $resp['payment_url'],
        'provider_ref' => (string) $resp['transaction_id'],
        'payload' => array_merge(['provider' => 'free_money'], $resp),
        'error' => null,
    ];
}

/**
 * Helper HTTP POST commun à tous les providers (cURL avec timeout + TLS strict).
 *
 * @return array{0:int,1:string,2:string} [http_code, raw_response, curl_error]
 */
function maire_paiement_http_post(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string) curl_error($ch);
    curl_close($ch);
    return [$code, is_string($resp) ? $resp : '', $err];
}

/**
 * Helper d'échec uniforme.
 */
function maire_paiement_failure(string $provider, string $error): array
{
    return [
        'ok' => false,
        'redirect_url' => null,
        'provider_ref' => null,
        'payload' => ['provider' => $provider, 'error' => $error],
        'error' => $error,
    ];
}

/**
 * Vérifie la signature d'un webhook de paiement.
 * En démo on accepte un secret partagé en query string.
 */
function maire_paiement_webhook_authentique(array $headers, array $payload, ?string $signatureRecue): bool
{
    if ($signatureRecue === null || $signatureRecue === '') {
        return false;
    }
    return hash_equals(MAIRE_PAIEMENT_WEBHOOK_SECRET, $signatureRecue);
}
