<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db/example_database.php';

use \IMSGlobal\LTI;
$launch = LTI\LTI_Message_Launch::from_cache($_REQUEST['launch_id'], new Example_Database());

if (!$launch->has_ags()) {
    throw new Exception("Don't have grades!");
}

$launch_data = $launch->get_launch_data();
$coursecode = $launch_data['https://purl.imsglobal.org/spec/lti/claim/context']['label'];
$userid = $launch_data['sub'];
$data = array();

$data_file = __DIR__ . '/ags/results.json';

$data_content = file_get_contents($data_file);
if (!empty($data_content)) {
    $data = json_decode($data_content, true);
}
$data[$coursecode][$userid]['name'] = $launch_data['given_name'];
if (isset($_REQUEST['score'])) {
    $data[$coursecode][$userid]['score'] = $_REQUEST['score'];
} 

if (isset($_REQUEST['time'])) {
    $data[$coursecode][$userid]['time'] = $_REQUEST['time'];
    
}
file_put_contents($data_file, json_encode($data));
echo '{"success" : true}';
?>