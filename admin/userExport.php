<?php
require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
require_once($dbmsConnectionPathDeep);

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    print "No Cookie Data<br>Please login to iCoast first.";
//    header('Location: index.php');
    exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
if (!$userData) {
    print "Failed iCoast Authentication<br>Please logout and then back in to iCoast.";
    exit;
}
$authCheckCode = generate_cookie_credentials($DBH, $userId);

if ($userData['account_type'] != 4) {
    print "Insufficient Permissions<br>Access Denied.";
    header('Location: index.php');
    exit;
}

$iCoastUsers = array();
$tableHtml = '';

//print "Query";
$userQuery = "SELECT * FROM users";
foreach ($DBH->query($userQuery) as $row) {
//    print "<pre>";
//    print_r($row);
//    print "</pre>";
    $clear_text_email = mysql_aes_decrypt($row['encrypted_email'], $row['encryption_data']);
    $crowd_type_text = crowdTypeConverter($row['crowd_type'], $row['other_crowd_type']);
    $tableHtml.= <<<EOT
    <tr>
        <td>$clear_text_email</td>
        <td>$crowd_type_text</td>
        <td>{$row['affiliation']}</td>
    </tr>
EOT;
}

?>


<table>
    <thead>
    <td>Email</td>
    <td>Crowd Type</td>
    <td>Affiliation</td>
</thead>
<tbody>
    <?php print $tableHtml; ?>
</tbody>
</table>



