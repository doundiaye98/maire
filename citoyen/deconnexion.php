<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/citoyen-session.php';

maire_citoyen_logout();

header('Location: connexion.php?besoin=deconnexion', true, 302);
exit;
