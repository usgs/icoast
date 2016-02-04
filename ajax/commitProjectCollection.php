<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);
ini_set('max_execution_time', 1200);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_POST['collectionId'])) {
    $collectionId = $_POST['collectionId'];
} else {
    exit;
}
settype($collectionId, 'integer');
$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    exit;
}
unset($collectionId);

$projectCreatorQuery = '
    SELECT creator
    FROM projects
    WHERE project_id = :projectId';
$projectCreatorParams['projectId'] = $collectionMetadata['parent_project_id'];
$projectCreatorResult = run_prepared_query($DBH, $projectCreatorQuery, $projectCreatorParams);
$creator = $projectCreatorResult->fetchColumn();
if ($creator != $userData['user_id']) {
    exit;
}

$clearPositionInCollectionQuery = '
    UPDATE import_images
    SET position_in_collection = NULL
    WHERE import_collection_id = :importCollectionId
    ';
$collectionIdQueryParam['importCollectionId'] = $collectionMetadata['import_collection_id'];
$clearPositionInCollectionResult = run_prepared_query($DBH, $clearPositionInCollectionQuery, $collectionIdQueryParam);
if ($clearPositionInCollectionResult->rowCount() == 0) {
    print 0;
    exit;
}

$positionArray = $_POST['positionData'];
foreach ($positionArray as $position => $imageId) {
    $position++;
    settype($position, 'integer');
    settype($imageId, 'integer');
    if (!empty($position) && !empty($imageId)) {
        $updatePositionQuery = '
            UPDATE import_images
            SET position_in_collection = :position
            WHERE import_image_id = :importImageId
            LIMIT 1
            ';
        $updatePositionParams = array(
            'position' => $position,
            'importImageId' => $imageId
        );
        $updatePositionResults = run_prepared_query($DBH, $updatePositionQuery, $updatePositionParams);
        if ($updatePositionResults->rowCount() == 0) {
            print 0;
            exit;
        }
    }
}

$updateImportCollectionsQuery = '
    UPDATE import_collections
    SET sequencing_stage = 4
    WHERE import_collection_id = :importCollectionId
    LIMIT 1';
$updateImportCollectionsResult = run_prepared_query($DBH, $updateImportCollectionsQuery, $collectionIdQueryParam);
if ($updateImportCollectionsResult->rowCount() != 1) {
    print 0;
    exit;
}
print 1;