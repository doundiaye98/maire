<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/super-admin-account.php';

maire_super_admin_account_logout();

header('Location: login.php?besoin=deconnexion', true, 302);
exit;
