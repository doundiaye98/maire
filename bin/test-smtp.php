<?php
declare(strict_types=1);

/**
 * Test rapide de la configuration SMTP en mode CLI.
 *
 * Usage :
 *   php bin/test-smtp.php destinataire@example.com
 *
 * Lit le .env, charge le mailer, tente l'envoi d'un email de test
 * et affiche le statut + le détail de l'erreur en cas d'échec.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande (php bin/test-smtp.php destinataire@x.sn)\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/includes/env-loader.php';
maire_env_load($root . '/.env');
maire_env_bridge_to_constants();
require_once $root . '/includes/logger.php';
require_once $root . '/includes/mailer.php';

$destinataire = $argv[1] ?? '';
if ($destinataire === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "❌ Usage : php bin/test-smtp.php destinataire@example.com\n");
    exit(2);
}

echo "─────────────────────────────────────────────\n";
echo " Test SMTP — Mairie de Rufisque-Est\n";
echo "─────────────────────────────────────────────\n";
echo " Host         : " . (defined('MAIRE_MAIL_HOST') ? MAIRE_MAIL_HOST : '(non défini)') . "\n";
echo " Port         : " . (defined('MAIRE_MAIL_PORT') ? MAIRE_MAIL_PORT : '(non défini)') . "\n";
echo " Username     : " . (defined('MAIRE_MAIL_USERNAME') ? MAIRE_MAIL_USERNAME : '(non défini)') . "\n";
echo " Password     : " . (defined('MAIRE_MAIL_PASSWORD') && MAIRE_MAIL_PASSWORD !== '' ? '(défini, ' . strlen(MAIRE_MAIL_PASSWORD) . ' caractères)' : '(VIDE)') . "\n";
echo " Encryption   : " . (defined('MAIRE_MAIL_ENCRYPTION') ? MAIRE_MAIL_ENCRYPTION : '(non défini)') . "\n";
echo " From         : " . (defined('MAIRE_MAIL_FROM_EMAIL') ? MAIRE_MAIL_FROM_EMAIL : '(non défini)') . "\n";
echo " Destinataire : {$destinataire}\n";
echo "─────────────────────────────────────────────\n\n";

if (!maire_mailer_smtp_configured()) {
    echo "⚠️  SMTP non complètement configuré (MAIL_HOST, MAIL_USERNAME ou MAIL_PASSWORD vide).\n";
    echo "    Le test va utiliser mail() natif (souvent KO en local sur Windows).\n\n";
}

echo "Envoi en cours...\n";
$debut = microtime(true);
$err = null;
$ok = maire_mailer_send(
    $destinataire,
    'Test SMTP — Mairie de Rufisque-Est',
    "Bonjour,\n\n"
    . "Cet email est un test de configuration SMTP envoyé depuis le serveur de la Mairie de Rufisque-Est.\n\n"
    . "Si vous lisez ce message, la configuration SMTP fonctionne correctement.\n\n"
    . "Détails techniques :\n"
    . "  - Date d'envoi : " . date('Y-m-d H:i:s') . "\n"
    . "  - Host SMTP    : " . (defined('MAIRE_MAIL_HOST') ? MAIRE_MAIL_HOST : 'mail()') . "\n"
    . "  - Port         : " . (defined('MAIRE_MAIL_PORT') ? MAIRE_MAIL_PORT : '-') . "\n\n"
    . "--\n"
    . "Mairie de Rufisque-Est\n"
    . "Castor, en face de la pharmacie DIOR, Arafat II, Rufisque-Est\n",
    $err
);
$duree = round((microtime(true) - $debut) * 1000);

if ($ok) {
    echo "\n✅ SUCCÈS — Email envoyé en {$duree} ms\n";
    echo "   Vérifie ta boîte de réception (et le dossier spam au besoin).\n";
    exit(0);
}

echo "\n❌ ÉCHEC après {$duree} ms\n";
echo "   Erreur : " . ($err ?? 'inconnue') . "\n\n";
echo "Causes fréquentes :\n";
echo "  1. MAIL_PASSWORD vide ou incorrect dans .env\n";
echo "  2. Mot de passe normal Gmail utilisé au lieu du \"mot de passe d'application\"\n";
echo "     → https://myaccount.google.com/apppasswords\n";
echo "  3. Validation en 2 étapes non activée sur le compte Gmail\n";
echo "  4. Pare-feu local bloquant le port 587\n";
echo "  5. OpenSSL absent dans PHP (vérifier : php -m | grep openssl)\n";
exit(1);
