<?php
/* For licensing terms, see /license.txt */
die('Remove the "die()" statement on line '.__LINE__.' to execute this script'.PHP_EOL);
if (PHP_SAPI != 'cli') {
    die('This script can only be executed from the command line');
}

require_once __DIR__.'/../../public/main/inc/global.inc.php';

$userInfo = UserManager::logInAsFirstAdmin();

if (api_is_platform_admin()) {
    echo 'Logged as admin user: '.$userInfo['complete_name'];
} else {
    echo 'NOT logged as admin ';
}
