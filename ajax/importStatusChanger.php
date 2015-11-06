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
        $photoMetadata = retrieve_entity_metadata($DBH, $_GET['photoId'], 'importImage');
    }
}

if (isset($_GET['currentStatus']) && ($_GET['currentStatus'] == 0 || $_GET['currentStatus'] == 1)) {
    $currentStatus = $_GET['currentStatus'];
}

$parentProjectCreatorQuery = '
    SELECT p.creator
    FROM import_collections ic
    INNER JOIN projects p ON ic.parent_project_id = p.project_id
    WHERE ic.import_collection_id = :importCollectionId
    LIMIT 1';
$parentProjectCreatorParams['importCollectionId'] = $photoMetadata['import_collection_id'];
$parentProjectCreatorResult = run_prepared_query($DBH, $parentProjectCreatorQuery, $parentProjectCreatorParams);
$parentProjectCreator = $parentProjectCreatorResult->fetchColumn();

if (!isset($userData) ||
        !isset($photoMetadata) ||
        !isset($currentStatus) ||
        $parentProjectCreator != $userData['user_id']) {
    exit();
}

if ($photoMetadata['is_disabled'] == $currentStatus) {

    $updateQuery = "UPDATE import_images SET is_disabled = ";
    if ($photoMetadata['is_disabled'] == 0) {
        $updateQuery .= '1';
    } else {
        $updateQuery .= '0';
    }
    $updateQuery .= " WHERE import_image_id = {$photoMetadata['import_image_id']} LIMIT 1";

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
