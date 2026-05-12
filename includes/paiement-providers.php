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
];

if (!defined('MAIRE_PAIEMENT_PROVIDER_DEFAUT')) {
    define('MAIRE_PAIEMENT_PROVIDER_DEFAUT', 'log');
}
if (!defined('MAIRE_PAIEMENT_DEVISE')) {
    define('MAIRE_PAIEMENT_DEVISE', 'XOF');
}
if (!defined('MAIRE_PAIEMENT_WEBHOOK_SECRET')) {
    define('MAIRE_PAIEMENT_WEBHOOK_SECRET', 'maire-demo-webhook-secret-change-me');
}

// Clés API par provider (à remplacer en production)
if (!defined('MAIRE_ORANGE_MERCHANT_KEY')) {
    define('MAIRE_ORANGE_MERCHANT_KEY', '');
}
if (!defined('MAIRE_ORANGE_AUTH_HEADER')) {
    define('MAIRE_ORANGE_AUTH_HEADER', '');
}
if (!defined('MAIRE_WAVE_API_KEY')) {
    define('MAIRE_WAVE_API_KEY', '');
}

function maire_paiement_provider_libelle(string $code): string
{
    return MAIRE_PAIEMENT_PROVIDERS[$code] ?? ucfirst($code);
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
 * Orange Money Web Payment (stub).
 * En production :
 *   POST https://api.orange.com/orange-money-webpay/dev/v1/webpayment
 *   Auth: Basic <auth_header>
 *   Body: { merchant_key, currency, order_id, amount, return_url, cancel_url, notif_url, lang }
 *   Réponse: { pay_token, payment_url, notif_token, status }
 */
function maire_paiement_provider_orange_money(array $ctx): array
{
    if (MAIRE_ORANGE_MERCHANT_KEY === '' || MAIRE_ORANGE_AUTH_HEADER === '') {
        return [
            'ok' => false,
            'redirect_url' => null,
            'provider_ref' => null,
            'payload' => ['provider' => 'orange_money', 'configured' => false],
            'error' => 'Orange Money non configuré : renseignez MAIRE_ORANGE_MERCHANT_KEY et MAIRE_ORANGE_AUTH_HEADER.',
        ];
    }
    // TODO production :
    // $body = [
    //     'merchant_key' => MAIRE_ORANGE_MERCHANT_KEY,
    //     'currency' => $ctx['devise'],
    //     'order_id' => $ctx['reference'],
    //     'amount' => $ctx['montant'],
    //     'return_url' => $ctx['return_url'],
    //     'cancel_url' => $ctx['cancel_url'],
    //     'notif_url' => $ctx['webhook_url'],
    //     'lang' => 'fr',
    //     'reference' => $ctx['libelle'],
    // ];
    // $ch = curl_init('https://api.orange.com/orange-money-webpay/dev/v1/webpayment');
    // curl_setopt_array($ch, [...]);
    // $resp = json_decode((string) curl_exec($ch), true);
    // return ['ok' => true, 'redirect_url' => $resp['payment_url'], 'provider_ref' => $resp['pay_token'], ...];
    return [
        'ok' => false,
        'redirect_url' => null,
        'provider_ref' => null,
        'payload' => ['provider' => 'orange_money', 'stub' => true],
        'error' => 'Stub Orange Money — implémenter l’appel HTTP réel avec vos identifiants.',
    ];
}

/**
 * Wave Checkout (stub).
 * En production :
 *   POST https://api.wave.com/v1/checkout/sessions
 *   Auth: Bearer <api_key>
 *   Body: { amount, currency, success_url, error_url, client_reference }
 *   Réponse: { id, wave_launch_url, ... }
 */
function maire_paiement_provider_wave(array $ctx): array
{
    if (MAIRE_WAVE_API_KEY === '') {
        return [
            'ok' => false,
            'redirect_url' => null,
            'provider_ref' => null,
            'payload' => ['provider' => 'wave', 'configured' => false],
            'error' => 'Wave non configuré : renseignez MAIRE_WAVE_API_KEY.',
        ];
    }
    // TODO production :
    // $body = [
    //     'amount' => (string) $ctx['montant'],
    //     'currency' => $ctx['devise'],
    //     'success_url' => $ctx['return_url'],
    //     'error_url' => $ctx['cancel_url'],
    //     'client_reference' => $ctx['reference'],
    // ];
    // $ch = curl_init('https://api.wave.com/v1/checkout/sessions');
    // curl_setopt_array($ch, [
    //   CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MAIRE_WAVE_API_KEY, 'Content-Type: application/json'],
    //   CURLOPT_POSTFIELDS => json_encode($body),
    //   CURLOPT_RETURNTRANSFER => true,
    // ]);
    // $resp = json_decode((string) curl_exec($ch), true);
    // return ['ok' => true, 'redirect_url' => $resp['wave_launch_url'], 'provider_ref' => $resp['id'], ...];
    return [
        'ok' => false,
        'redirect_url' => null,
        'provider_ref' => null,
        'payload' => ['provider' => 'wave', 'stub' => true],
        'error' => 'Stub Wave — implémenter l’appel HTTP réel avec votre API Key.',
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
