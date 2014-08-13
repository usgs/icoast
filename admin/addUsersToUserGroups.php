<?php

require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

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
//    header('Location: index.php');
    exit;
}

if ((isset($_POST['submitted']) && $_POST['submitted'] == "Add User") &&
    (isset($_POST['user']) && is_numeric($_POST['user'])) &&
    (isset($_POST['group']) && is_numeric($_POST['group']))) {

    $insertQuery = "INSERT INTO user_groups (user_group_id, user_id) VALUES (:group, :user)";
    $insertParams = array(
        'user' => $_POST['user'],
        'group'=> $_POST['group']
    );
    $insertResult = run_prepared_query($DBH, $insertQuery, $insertParams);
    if ($insertResult->rowCount() == 1) {
        print "<h1>User Sucessfully Inserted</h1>";
        print "<p>Add another?</p>";
    }


}


$groupsHTML = '';
$groupsQuery = "SELECT user_group_id, name, description FROM user_group_metadata";
$groupsResult = $DBH->query($groupsQuery);
$groups = $groupsResult->fetchAll(PDO::FETCH_ASSOC);
foreach ($groups as $group) {
    $groupId = $group['user_group_id'];
    $groupName = $group['name'];
    $groupDescription = $group['description'];
    $groupsHTML .= "<option value=\"$groupId\" title=\"$groupDescription\">$groupName</option>";
}

$usersHTML = '';
$usersQuery = "SELECT user_id, encrypted_email, encryption_data FROM users ORDER BY masked_email";
$usersResult = $DBH->query($usersQuery);
$users = $usersResult->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    $userId = $user['user_id'];
    $userEncEmail = $user['encrypted_email'];
    $userEncData = $user['encryption_data'];
    $unencryptedEmail = mysql_aes_decrypt($userEncEmail, $userEncData);
    $usersHTML .= "<option value=\"$userId\">$unencryptedEmail</option>";
}


?>

<form method="post">
    <select name="group">
        <?php print $groupsHTML; ?>
    </select>
    <select name="user">
        <?php print $usersHTML; ?>
    </select>
    <input type="submit" name="submitted" value="Add User">
</form>