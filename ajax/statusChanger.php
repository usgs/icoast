<?php

require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_GET['photoId'])) {
    settype($_GET['photoId'], 'integer');
    if (!empty($_GET['photoId'])) {
        $photoMetadata = retrieve_entity_metadata($DBH, $_GET['photoId'], 'image');
    }
}



if (isset($_GET['currentStatus']) && ($_GET['currentStatus'] == 0 || $_GET['currentStatus'] == 1)) {
    $currentStatus = $_GET['currentStatus'];
}

if (!isset($userData) || !isset($photoMetadata) || !isset($currentStatus)) {
    exit();
}

$image = imagecreatefromjpeg("../images/collections/{$photoMetadata['collection_id']}/main/{$photoMetadata['filename']}");
if ($image) {
    $imageWidth = imagesx($image);
    $imageHeight = imagesy($image);
    if ($imageWidth < $imageHeight) {
        $returnData = array(
            'success' => 1,
            'newImageStatus' => (int)$photoMetadata['is_globally_disabled']
        );
    }
    imagedestroy($image);
} else {
    $returnData = array(
        'success' => 0,
        'newImageStatus' => (int)$photoMetadata['is_globally_disabled']
    );
}
if (isset($returnData)) {
    print json_encode($returnData);
    exit;
}



if ($photoMetadata['is_globally_disabled'] == $currentStatus) {

    $updateQuery = "UPDATE images SET is_globally_disabled = ";
    if ($photoMetadata['is_globally_disabled'] == 0) {
        $updateQuery .= '1';
    } else {
        $updateQuery .= '0';
    }
    $updateQuery .= " WHERE image_id = {$photoMetadata['image_id']} LIMIT 1";

    $queryResult = $DBH->query($updateQuery);
    $affectedRows = $queryResult->rowCount();

    if ($affectedRows) {
        $newImageMetadata = retrieve_entity_metadata($DBH, $photoMetadata['image_id'], 'image');
        $returnData = array(
            'success' => 2,
            'newImageStatus' => (int)$newImageMetadata['is_globally_disabled']
        );
    } else {
        $returnData = array(
            'success' => 0,
            'newImageStatus' => (int)$photoMetadata['is_globally_disabled']
        );
    }

    print json_encode($returnData);
} else {
    $returnData = array(
        'success' => 2,
        'newImageStatus' => (int)$photoMetadata['is_globally_disabled']
    );
    print json_encode($returnData);
}
