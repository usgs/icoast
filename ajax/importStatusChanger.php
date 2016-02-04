<?php

require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (empty($userData)) {
    exit;
}

$photoId = filter_input(INPUT_GET, 'photoId', FILTER_VALIDATE_INT);
$photoMetadata = retrieve_entity_metadata($DBH, $_GET['photoId'], 'importImage');
if (empty($photoMetadata)) {
    exit;
}

$currentStatus = filter_input(INPUT_GET, 'currentStatus', FILTER_VALIDATE_BOOLEAN);
if (is_null($currentStatus)) {
    exit;
}

$collectionCreatorQuery = '
    SELECT creator
    FROM import_collections
    WHERE import_collection_id = :importCollectionId
    LIMIT 1';
$collectionCreatorParams['importCollectionId'] = $photoMetadata['import_collection_id'];
$collectionCreatorResult = run_prepared_query($DBH, $collectionCreatorQuery, $collectionCreatorParams);
$collectionCreator = $collectionCreatorResult->fetchColumn();

if ($collectionCreator != $userData['user_id']) {
    exit();
}

if ($photoMetadata['is_disabled'] == $currentStatus) {
    if ($photoMetadata['is_disabled'] == 0) {
        $newStatus = '1';
    } else {
        $newStatus = '0';
    }
    $updateQuery = "
      UPDATE import_images
      SET is_disabled = $newStatus
      WHERE import_image_id = {$photoMetadata['import_image_id']}
      LIMIT 1";

    $queryResult = $DBH->query($updateQuery);
    $affectedRows = $queryResult->rowCount();

    if ($affectedRows) {
        $newImageMetadata = retrieve_entity_metadata($DBH, $photoMetadata['import_image_id'], 'importImage');
        $returnData = array(
            'success' => 2,
            'newImageStatus' => (int) $newImageMetadata['is_disabled']
        );
    } else {
        $returnData = array(
            'success' => 0,
            'newImageStatus' => (int) $photoMetadata['is_disabled']
        );
    }
} else {
    $returnData = array(
        'success' => 2,
        'newImageStatus' => (int) $photoMetadata['is_disabled']
    );
}
print json_encode($returnData);
