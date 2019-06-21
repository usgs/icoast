<?php

//A template file Successfullyypage code files
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

$cssLinkArray[] = 'css/userGroupEditor.css';

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

function emailSort($a, $b)
{
    if ($a['email'] == $b['email']) {
        return 0;
    }
    return ($a['email'] < $b['email']) ? -1 : 1;
}

$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];


$projectsQuery = <<<MySQL
  SELECT 
    project_id AS projectId, 
    name, 
    description 
  FROM projects
  WHERE 
    creator = $userId
MySQL;
$projectsResult = $DBH->query($projectsQuery);
$projects = $projectsResult->fetchAll(PDO::FETCH_ASSOC);
$projectsJS = json_encode($projects);
unset($projectsQuery, $projectsResult, $projects);


$groupsQuery = <<<MySQL
  SELECT 
    ugm.user_group_id AS groupId, 
    ugm.project_id as projectId,
    ugm.name, 
    ugm.description 
  FROM user_group_metadata ugm
  LEFT JOIN projects p ON ugm.project_id = p.project_id
  WHERE
    p.creator = $userId
  ORDER BY 
    ugm.project_id ASC
MySQL;
$groupsResult = $DBH->query($groupsQuery);
$groups = $groupsResult->fetchAll(PDO::FETCH_ASSOC);
$groupArray = array();
foreach ($groups as $group) {
    $groupArray[$group['projectId']][] = $group;
}
$groupsJS = json_encode($groupArray);
unset($groupsQuery, $groupsResult, $groups, $group, $groupArray);


$usersQuery = <<<MySQL
    SELECT 
      user_id AS userId, 
      encrypted_email AS encryptedEmail, 
      encryption_data AS encryptionData 
    FROM 
      users 
    ORDER BY 
      masked_email
MySQL;
$usersResult = $DBH->query($usersQuery);
$users = $usersResult->fetchAll(PDO::FETCH_ASSOC);
$userArray = array();
foreach ($users as &$user) {
    $userArray[$user['userId']] = mysql_aes_decrypt($user['encryptedEmail'], $user['encryptionData']);
}

$usersJS = json_encode($userArray);
unset($usersQuery, $usersResult, $users, $user);

$javaScript .= <<<JS
    var projects = $projectsJS;
    var groups = $groupsJS;
    var allUsers = $usersJS;
console.log(projects);
console.log(groups);
JS;
unset($projectsJS, $groupsJS, $usersJS);


$jQueryDocumentDotReadyCode = "";