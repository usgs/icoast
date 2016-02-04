<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$collectionIdParam['collectionId'] = $collectionId;

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    $finalizedCollectionQuery = <<<MYSQL
      SELECT creator
      FROM collections
      WHERE import_collection_id = :collectionId
MYSQL;
    $finalizedCollectionResult = run_prepared_query($DBH, $finalizedCollectionQuery, $collectionIdParam);
    if ($finalizedCollectionResult) {
        $finalizedCollectionCreator = $finalizedCollectionResult->fetchColumn();
        if ($finalizedCollectionCreator != $userData['user_id']) {
            exit;
        } else {
            $result['stage'] = 2;

        }
    }
} else {
    $result['stage'] = 1;
    $result['progressPercentage'] = 0;

    $importImageCountQuery = '
            SELECT COUNT(*)
            FROM import_images
            WHERE import_collection_id = :collectionId';
    $importImageCountResult = run_prepared_query($DBH, $importImageCountQuery, $collectionIdParam);
    $importImageCount = $importImageCountResult->fetchColumn();

    $liveCollectionIdQuery = <<<MYSQL
          SELECT collection_id
          FROM collections
          WHERE import_collection_id = :collectionId
MYSQL;
    $liveCollectionIdResult = run_prepared_query($DBH, $liveCollectionIdQuery, $collectionIdParam);
    $liveCollectionId['liveCollectionId'] = $liveCollectionIdResult->fetchColumn();


    $liveImageCountQuery = '
            SELECT COUNT(*)
            FROM images
            WHERE collection_id = :liveCollectionId';
    $liveImageCountResult = run_prepared_query($DBH, $liveImageCountQuery, $liveCollectionId);
    $liveImageCount = $liveImageCountResult->fetchColumn();
    if ($liveImageCount) {
        $progressPercentage = floor(($liveImageCount / $importImageCount) * 100);
        $result['progressPercentage'] = $progressPercentage;
    }
}


print json_encode($result);
