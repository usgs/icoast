<?php

require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE);

if (isset($_GET['photoId'])) {
    settype($_GET['photoId'], 'integer');
    if (!empty($_GET['photoId'])) {
        $photoMetadata = retrieve_entity_metadata($DBH, $_GET['photoId'], 'image');
    }
}

if (isset($_GET['currentStatus'])) {
    $currentStatus = $_GET['currentStatus'];
}

if (!isset($userData) || !isset($photoMetadata) || !isset($currentStatus)) {
    exit();
}


if ($photoMetadata['is_globally_disabled'] == $currentStatus) {

    $updateQuery = "UPDATE images SET is_globally_disabled = ";
    if ($photoMetadata['is_globally_disabled'] == 0) {
        $updateQuery .= '1';
    } else {
        $updateQuery .= '0';
    }
    $updateQuery .= " WHERE image_id = {$photoMetadata['image_id']} LIMIT 1";
//        $updateQuery .= " WHERE image_id = 999999999 LIMIT 1";


    $queryResult = $DBH->query($updateQuery);
    $affectedRows = $queryResult->rowCount();

    if ($affectedRows) {
        $newImageMetadata = retrieve_entity_metadata($DBH, $photoMetadata['image_id'], 'image');
        $returnData = array(
            'success' => 1,
            'newImageStatus' => $newImageMetadata['is_globally_disabled']
        );
    } else {
        $returnData = array(
            'success' => 0,
            'newImageStatus' => $photoMetadata['is_globally_disabled']
        );
    }

    print json_encode($returnData);
} else {

    $returnData = array(
        'success' => 1,
        'newImageStatus' => $photoMetadata['is_globally_disabled']
    );
    print json_encode($returnData);
}
