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

if ($userData['account_type'] !=4) {
    print "Insufficient Permissions<br>Access Denied.";
//    header('Location: index.php');
    exit;
}

$query = "SELECT user_id, encrypted_email, encryption_data FROM users";
$queryParams = array();
$queryResult = run_prepared_query($DBH, $query, $queryParams);
$result = $queryResult->fetchAll(PDO::FETCH_ASSOC);

$userAnnotations = array();
$usersWithAnnotations = 0;
foreach ($result as $result) {
    $email = mysql_aes_decrypt($result['encrypted_email'], $result['encryption_data']);
    $query = "SELECT COUNT(*) FROM annotations WHERE user_id = :userId AND annotation_completed = 1";
    $queryParams['userId'] = $result['user_id'];
    $queryResult = run_prepared_query($DBH, $query, $queryParams);
    $annotationCount = $queryResult->fetchColumn();
    if ($annotationCount > 0) {
        $usersWithAnnotations++;
    }
    $userAnnotations[$email] = $annotationCount;
}

arsort($userAnnotations);

print 'Total users in system: ' . count($userAnnotations);
print '<br>Number of users with at least 1 annotation: ' . $usersWithAnnotations;

print '<pre>';
print_r($userAnnotations);
print "</pre>";




