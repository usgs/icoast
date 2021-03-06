<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$importCollectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($importCollectionMetadata) ||
    ($importCollectionMetadata && $importCollectionMetadata['creator'] != $userData['user_id'])
) {
    print 0;
    exit;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////

    if (file_exists("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main")) {
        $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/*");
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }
        rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main");
    }

    if (file_exists("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails")) {
        $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/*");
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }
        rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails");
    }
    rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}");
    if (file_exists("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}")) {
        print 0;
        exit;
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
$collectionQueryParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];

    $imageCountQuery = '
    SELECT COUNT(*)
    FROM import_images
    WHERE import_collection_id = :importCollectionId
    ';
    $imageCountResult = run_prepared_query($DBH, $imageCountQuery, $collectionQueryParams);
    $numberOfRows = $imageCountResult->fetchColumn();
    if ($numberOfRows > 0) {
        $collectionImageDeletionQuery = '
        DELETE FROM import_images
        WHERE import_collection_id = :importCollectionId
    ';
        $collectionImageDeletionResult = run_prepared_query($DBH, $collectionImageDeletionQuery, $collectionQueryParams);
        if ($collectionImageDeletionResult->rowCount() != $numberOfRows) {
            print 0;
            exit;
        }
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

$collectionDeletionQuery = "
        UPDATE import_collections
        SET import_status_message = 'Deleted'
        WHERE import_collection_id = :importCollectionId
    ";

    $collectionDeletionResult = run_prepared_query($DBH, $collectionDeletionQuery, $collectionQueryParams);

if ($collectionDeletionResult->rowCount() == 1) {
    print 1;
} else {
    print 0;
}
