<?php
declare(strict_types=1);

/**
 * Fichier de secrets de l'application.
 *
 * 1. Copiez ce fichier vers config/secrets.php (déjà listé dans .gitignore).
 * 2. Remplacez chaque valeur par une chaîne aléatoire d'au moins 32 caractères.
 *
 * - super_admin_key : jeton interne réservé. Peut être utilisé plus tard
 *   pour signer des liens administratifs (réinitialisation mot de passe,
 *   import en masse…). Aucune page publique ne l'expose actuellement.
 *
 * Ne commitez jamais secrets.php. En production, préférez HTTPS.
 */
return [
    'super_admin_key' => 'REMPLACEZ_PAR_UNE_CLE_ALEATOIRE_32_CHARS_MIN',
];
