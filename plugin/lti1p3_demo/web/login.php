<?php
/**
 * /login.php?iss=http://my.ltidemo.com&login_hint=12345&target_link_uri=http://my.ltidemo.com/game.php&lti_message_hint=12345
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db/example_database.php';

use \IMSGlobal\LTI;

error_log("LTI_DEBUG :: provider :: In start.php is1p3 : $is1p3");

LTI\LTI_OIDC_Login::new(new Example_Database())
    ->do_oidc_login_redirect(TOOL_HOST. "/plugin/lti1p3_demo/web/game.php", $_REQUEST)
    ->do_redirect();
?>