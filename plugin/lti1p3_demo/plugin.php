<?php
/* For license terms, see /license.txt */

$cidReset = true;

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once api_get_path(SYS_PLUGIN_PATH).'lti1p3_demo/Lti1p3DemoPlugin.php';
/* Plugin config */

//the plugin title
$plugin_info['title'] = 'LTI 1.3 Advantage Demo Tool';
//the comments that go with the plugin
$plugin_info['comment'] = "Simple application developed as a way to demonstrate how to build an IMS LTI tool provider";
//the plugin version
$plugin_info['version'] = '1.0';
//the plugin author
$plugin_info['author'] = 'Christian Beeznest';

$plugin_info['plugin_class'] = 'Lti1p3DemoPlugin';
/* Plugin optional settings */


$form = new FormValidator('lti1p3_demo_form');

$plugin = Lti1p3DemoPlugin::create();

$public_key = <<<EOT
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyU64VjSdgPqaQp/iaS8E
QsCYMBBKFjevaZGraPWGho3H74OYnuTbfeo3T6ufSNpK7aImM6rgsNqh6/FVYKUy
NKcD/2AYkfu8gip3zAHJBONhu0NNEkyeKx9ugvvrrgiifTDM5JYOyOSYVcVHZhsO
ASb/+eHvq3VSa/+rbgOnqeq7t2rSc+Zt/wTWAtY8OIms6uOr8drbHdvDz4+isxlN
TBW06ILDLAUS/gH2PDskPJFwAMbnmoXGoU3j5IKiWGASNWCbUAfeiTs7XIFR/bxE
vs+oGsn6vUnZV9dJR20aTG3hsR0EYEVfjzBCBpoK3Jb3RLouLnwkZAteJFmyWqAX
AwIDAQAB
-----END PUBLIC KEY-----
EOT;

$form->addHeader($plugin->get_lang('ToolSettings'));
$form->addText('name', get_lang('Name'));
$form->addTextarea('description', $plugin->get_lang('Description'));
$form->addUrl('launch_url', get_lang('LaunchUrl'), true);

$form->addTextarea(
    'public_key',
    get_lang('PublicKey'),
    ['style' => 'font-family: monospace;', 'rows' => 5]
);
$form->addUrl('login_url', get_lang('LoginUrl'), false);
$form->addUrl('redirect_url', get_lang('RedirectUrl'), false);

$config_json = <<<EOT
{
    "<issuer>" : { // This will usually look something like 'http://example.com' 
        "client_id" : "<client_id>", // This is the id received in the 'aud' during a launch
        "auth_login_url" : "<auth_login_url>", // The platform's OIDC login endpoint
        "auth_token_url" : "<auth_token_url>", // The platform's service authorization endpoint
        "key_set_url" : "<key_set_url>", // The platform's JWKS endpoint
        "private_key_file" : "<path_to_private_key>", // Relative path to the tool's private key
        "deployment" : [
            "<deployment_id>" // The deployment_id passed by the platform during launch
        ]
    }
}
EOT;

$form->addHtml('<div class="form-group ">
                    <label for="" class="col-sm-2 control-label"></label>
                    <div class="col-sm-9">'.get_lang('ConfigFormat').'<br><pre>'.htmlentities($config_json).'</pre></div></div>');

$config_file = api_get_path(SYS_PLUGIN_PATH).'lti1p3_demo/db/configs/local.json';


$form->addTextarea(
    'config_json',
    get_lang('ConfigurationJson'),
    ['style' => 'font-family: monospace;', 'rows' => 15, 'cols' => 30]
);
   
$form->addButtonSave(get_lang('Save'), 'submit_button');

$defaults['name'] = 'Lti1p3 Demo';
$defaults['description'] = get_lang('description');
$defaults['config_json'] = file_get_contents($config_file);
$defaults['public_key'] = $public_key;
$defaults['launch_url'] = api_get_path(WEB_PLUGIN_PATH).'lti1p3_demo/web/game.php';
$defaults['login_url'] = api_get_path(WEB_PLUGIN_PATH).'lti1p3_demo/web/login.php';
$defaults['redirect_url'] = api_get_path(WEB_PLUGIN_PATH).'lti1p3_demo/web/game.php';
$form->setDefaults($defaults);

$form->freeze(['name', 'public_key', 'launch_url', 'login_url', 'redirect_url', 'description']);
$plugin_info['settings_form'] = $form;

$plugin_info['plugin_class'] = get_class($plugin);
