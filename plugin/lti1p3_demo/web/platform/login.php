<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db/example_database.php';

$iss = (isset($_SESSION['iss'])?key($_SESSION['iss']):'test');


use \Firebase\JWT\JWT;
$message_jwt = [
    "iss" => $iss,
    "aud" => ['d42df408-70f5-4b60-8274-6c98d3b9468d'],
    "sub" => '4d0b3941-83f5-47fe-bd8a-66b39aa0651d', //'0ae836b9-7fc9-4060-006f-27b2066ac545',
    "exp" => time() + 600,
    "iat" => time(),
    "name" => "Marget Elke",
    "given_name" => "Marget",
    "family_name" => "Elke",
    "nonce" => uniqid("nonce"),
    "https://purl.imsglobal.org/spec/lti/claim/deployment_id" => '8c49a5fa-f955-405e-865f-3d7e959e809f',
    "https://purl.imsglobal.org/spec/lti/claim/message_type" => "LtiResourceLinkRequest",
    "https://purl.imsglobal.org/spec/lti/claim/version" => "1.3.0",
    "https://purl.imsglobal.org/spec/lti/claim/target_link_uri" => TOOL_HOST . "/game.php",
    "https://purl.imsglobal.org/spec/lti/claim/roles" => [
        "http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor"
    ],
    "https://purl.imsglobal.org/spec/lti/claim/context" => [
        "id" => "c1d887f0-a1a3-4bca-ae25-c375edcc131a",
        "label" => "ECON 101",
        "title" => "Economics as a Social Science",
        "type" => ["CourseOffering"]
    ],
    "https://purl.imsglobal.org/spec/lti/claim/tool_platform" => [
        "contact_email" => "support@example.org",
        "description" => "An Example Tool Platform",
        "name" => "Example Tool Platform",
        "url" => "https://example.org",
        "product_family_code" => "example.org",
        "version" => "1.0"
    ],
    "https://purl.imsglobal.org/spec/lti/claim/launch_presentation" => [
        "document_target" => "iframe",
        "height" => 320,
        "width" => 240
    ],    
    "https://purl.imsglobal.org/spec/lti/claim/resource_link" => [
        "id" => "7b3c5109-b402-4eac-8f61-bdafa301cbb4",
    ],
    "https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice" => [
        "context_memberships_url" => "http://my.ltidemo.com/platform/services/nrps",
        "service_versions" => ["2.0"]
    ], 
    "https://purl.imsglobal.org/spec/lti-ags/claim/endpoint" => [
        "scope" => [
          "https://purl.imsglobal.org/spec/lti-ags/scope/lineitem",
          "https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly",
          "https://purl.imsglobal.org/spec/lti-ags/scope/score"
        ],
        "lineitems" => "http://my.ltidemo.com/platform/services/ags/lineitems.php",
    ]
];

$database = new Example_Database();
$jwt = JWT::encode(
    $message_jwt,
    file_get_contents(__DIR_.'/../../db/keys/platform.key'),
    'RS256',
    'fcec4f14-28a5-4697-87c3-e9ac361dada5'
);
?>

<form id="auto_submit" action="<?php echo $_REQUEST['redirect_uri']; ?>" method="POST">
    <input type="hidden" name="id_token" value="<?php echo $jwt ?>" />
    <input type="hidden" name="state" value="<?php echo $_REQUEST['state']; ?>" />
</form>
<script>
    document.getElementById('auto_submit').submit();
</script>
