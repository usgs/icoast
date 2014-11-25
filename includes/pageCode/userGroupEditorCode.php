<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];

$successMessage = '';


if (isset($_POST['submitted']) &&
        (isset($_POST['user']) && is_numeric($_POST['user'])) &&
        (isset($_POST['group']) && is_numeric($_POST['group']))) {

    $queryParams = array(
        'user' => $_POST['user'],
        'group' => $_POST['group']
    );

    $userCheckQuery = "SELECT COUNT(*) FROM user_groups WHERE user_group_id = :group AND user_id = :user";
    $userCheckResult = run_prepared_query($DBH, $userCheckQuery, $queryParams);
    if ($userCheckResult->fetchColumn() == 0) {

        $insertQuery = "INSERT INTO user_groups (user_group_id, user_id) VALUES (:group, :user)";

        $insertResult = run_prepared_query($DBH, $insertQuery, $queryParams);
        if ($insertResult->rowCount() == 1) {
            $successMessage = '<p class="userData">User Sucessfully Inserted<br />Add another?</p>';
        }
    } else {
        $successMessage = '<p class="redHighlight">User already exists in this group. No changes made to the database.<br />Try again?</p>';
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



$jQueryDocumentDotReadyCode = "$('#userSelect, #userGroupSelect').prop('selectedIndex', -1);";