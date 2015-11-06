<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_GET['projectId'])) {
    $projectId = $_GET['projectId'];
} else {
    $projectId = null;
}
settype($projectId, 'integer');
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    print 0;
    exit;
} else if ($projectMetadata['creator'] != $userData['user_id'] ||
        $projectMetadata['is_complete'] == 1) {
    print 0;
    exit;
}

if (isset($_GET['collectionType'])) {
    $collectionType = $_GET['collectionType'];
} else {
    $collectionType = null;
}
if ($collectionType != 'pre' &&
        $collectionType != 'post') {
    print 0;
    exit;
}

if (isset($_GET['newCollection'])) {
    $isNewCollection = $_GET['newCollection'];
} else {
    $isNewCollection = null;
}
if ($isNewCollection != 1 &&
        $isNewCollection != 0) {
    print 0;
    exit;
}

if ($isNewCollection) {

    $collectionIdQuery = '
    SELECT *
    FROM import_collections
    WHERE parent_project_id = :parentProjectId
        AND collection_type = :collectionType
    LIMIT 1
';
    $collectionIdParams = array(
        'parentProjectId' => $projectId,
        'collectionType' => $collectionType
    );
    $collectionIdResult = run_prepared_query($DBH, $collectionIdQuery, $collectionIdParams);
    $importCollectionMetadata = $collectionIdResult->fetch(PDO::FETCH_ASSOC);
    if (empty($importCollectionMetadata)) {
        print 0;
        exit;
    }
    $collectionQueryParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];

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

    $collectionDeletionQuery = '
        DELETE FROM import_collections
        WHERE import_collection_id = :importCollectionId
        LIMIT 1
    ';

    $collectionDeletionResult = run_prepared_query($DBH, $collectionDeletionQuery, $collectionQueryParams);
} else {
    $collectionDeletionQuery = "
        UPDATE projects
        SET {$collectionType}_collection_id = NULL
        WHERE project_id = :projectId
        LIMIT 1";
    $collectionDeletionParams['projectId'] = $projectMetadata['project_id'];
    $collectionDeletionResult = run_prepared_query($DBH, $collectionDeletionQuery, $collectionDeletionParams);
}
if ($collectionDeletionResult->rowCount() == 1) {
    print 1;
} else {
    print 0;
}
