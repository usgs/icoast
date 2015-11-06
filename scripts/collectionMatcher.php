<?php

require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

ignore_user_abort(true);
ini_set('memory_limit', '64M');
ini_set('max_execution_time', 3600);

/**
 * Maximum Radius in meters for which the image match function should search for a match.
 */

$userId = filter_input(INPUT_POST, 'user', FILTER_VALIDATE_INT);
$projectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT);
$checkCode = filter_input(INPUT_POST, 'checkCode');
$postMatchRadius = filter_input(INPUT_POST, 'matchRadius', FILTER_VALIDATE_INT);

$userMetadata = retrieve_entity_metadata($DBH, $userId, 'user');
if (empty($userMetadata)) {
    exit;
} else {
    if (isset($checkCode)) {
        if ($checkCode != $userMetadata['auth_check_code']) {
            exit;
        }
    } else {
        exit;
    }
}

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    exit;
} else if ($projectMetadata['creator'] != $userMetadata['user_id'] ||
        $projectMetadata ['is_complete'] == 1) {
    exit;
}
unset($projectId);
$projectIdParam['projectId'] = $projectMetadata['project_id'];


$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 40 && $importStatus != 50) {
    exit;
}

$matchRadius = 400;

if ($postMatchRadius && $postMatchRadius >= 200 && $postMatchRadius <= 1500) {
    $matchRadius = floor($postMatchRadius / 100) * 100;
} else {
    exit;
}

DEFINE('MAX_MATCH_RADIUS', $matchRadius);

// collection type of 1 = imported colleciton, 0 = existing collection.
$preCollectionType = 1;
$postCollectionType = 1;
$preCollectionId = null;
$postCollectionId = null;

if ($projectMetadata['pre_collection_id'] != null) {
    $preCollectionType = 0;
    $preCollectionId = $projectMetadata['pre_collection_id'];
} else {
    $preCollectionQuery = '
        SELECT import_collection_id
        FROM import_collections
        WHERE parent_project_id = :projectId
            AND collection_type = "pre"';
    $preCollectionResult = run_prepared_query($DBH, $preCollectionQuery, $projectIdParam);
    $preCollectionId = $preCollectionResult->fetchColumn();
}

if ($projectMetadata['post_collection_id'] != null) {
    $postCollectionType = 0;
    $postCollectionId = $projectMetadata['post_collection_id'];
} else {
    $postCollectionQuery = "
        SELECT import_collection_id
        FROM import_collections
        WHERE parent_project_id = :projectId
            AND collection_type = 'post'
            ";
    $postCollectionResult = run_prepared_query($DBH, $postCollectionQuery, $projectIdParam);
    $postCollectionId = $postCollectionResult->fetchColumn();
}

if (is_null($preCollectionId) || is_null($postCollectionId)) {
    exit;
}

$updateMatchingProgressQuery = '
    UPDATE projects
    SET matching_progress = 0
    WHERE project_id = :projectId
    LIMIT 1
    ';
$updateMatchingProgressResult = run_prepared_query($DBH, $updateMatchingProgressQuery, $projectIdParam);
if ($updateMatchingProgressResult->rowCount() == 0) {
    exit;
}
$checkRowsToResetQuery = '
    SELECT COUNT(*)
    FROM import_matches
    WHERE project_id = :projectId
    ';
$checkRowsToResetResult = run_prepared_query($DBH, $checkRowsToResetQuery, $projectIdParam);
$rowsToReset = $checkRowsToResetResult->fetchColumn();
if ($rowsToReset > 0) {
    $resetImportMatchesQuery = "
    DELETE FROM import_matches
    WHERE project_id = :projectId
    LIMIT $rowsToReset";
    $resetImportMatchesResult = run_prepared_query($DBH, $resetImportMatchesQuery, $projectIdParam);
    $resetRowCount = $resetImportMatchesResult->rowCount();
    if ($resetRowCount != $rowsToReset) {
        exit;
    }
}

/* Gather the data to process. Includes an array of all post event images containing complete
 * metadata ($postImageDataArray) and a string containing all dataset ID's of the pre collection
 * datasets for use in the match query string */

// Query the image table for id's of images in the specified post-event collection.
if ($postCollectionType == 0) {
    $postImagesQuery = '
        SELECT image_id, latitude, longitude, is_globally_disabled
        FROM images
        WHERE collection_id = :collectionId
        ORDER BY position_in_collection ASC';
} else {
    $postImagesQuery = '
        SELECT import_image_id AS image_id, latitude, longitude, 0 AS is_globally_disabled
        FROM import_images
        WHERE import_collection_id = :collectionId
            AND position_in_collection IS NOT NULL
            ORDER BY position_in_collection ASC';
}
$postImagesParams ['collectionId'] = $postCollectionId;
$postImagesResult = run_prepared_query($DBH, $postImagesQuery, $postImagesParams);
$postImages = $postImagesResult->fetchAll(PDO::FETCH_ASSOC);
$imagesToMatch = count($postImages);
/* Search for the pre-event image closest to each post-event image location. Add result to
 * $imageMatchArray. */
if ($imagesToMatch > 0) {
    $imagesProcessed = 0;
    foreach ($postImages as $postImage) {
        if ($imagesProcessed != 0 && $imagesProcessed % 50 == 0) {
            $progressPercentage = floor(($imagesProcessed / $imagesToMatch) * 100);
            $updateMatchingProgressQuery = "
                UPDATE projects
                SET matching_progress = $progressPercentage
                WHERE project_id = :projectId
                LIMIT 1
                ";
            $updateMatchingProgressResult = run_prepared_query($DBH, $updateMatchingProgressQuery, $projectIdParam);
        }
        if ($preCollectionType == 0) {
            $matchQuery = "
                SELECT image_id,
                    (6378137 * acos(cos(radians({$postImage['latitude']
                    })) * cos(radians(latitude))
                    * cos(radians(longitude) - radians( {$postImage['longitude']}) ) + sin(radians({$postImage['latitude']}))
                    * sin(radians(latitude)))) AS distance
                FROM images
                WHERE collection_id = $preCollectionId
                    AND is_globally_disabled = 0
                HAVING distance < " . MAX_MATCH_RADIUS . "
                ORDER BY distance LIMIT 1;
                ";
        } else {
            $matchQuery = "
                SELECT import_image_id AS image_id,
                    (6378137 * acos(cos(radians({$postImage['latitude']})) * cos(radians(latitude))
                    * cos(radians(longitude) - radians({$postImage['longitude']}) ) + sin(radians({$postImage['latitude'] }))
                    * sin(radians(latitude)))) AS distance
                FROM import_images
                WHERE import_collection_id = $preCollectionId
                    AND position_in_collection IS NOT null
                HAVING distance < " . MAX_MATCH_RADIUS . "
                ORDER BY distance LIMIT 1;
                ";
        }
        $matchResults = run_prepared_query($DBH, $matchQuery);
        $match = $matchResults->fetch(PDO::FETCH_ASSOC);

        $matchesInsertQuery = "
            INSERT INTO import_matches
            (project_id, is_post_collection_imported, post_collection_id, post_image_id,
            is_pre_collection_imported, pre_collection_id, pre_image_id, is_enabled)
            VALUES (";

        $matchesInsertQuery .= $projectMetadata['project_id'];
        $matchesInsertQuery .= ', ' . (int) $postCollectionType;
        $matchesInsertQuery .= ', ' . $postCollectionId;
        $matchesInsertQuery .= ', ' . $postImage['image_id'];
        $matchesInsertQuery .= ', ' . (int) $preCollectionType;
        $matchesInsertQuery .= ', ' . $preCollectionId;

        if ($match) {
            $matchesInsertQuery .= ', ' . $match['image_id'];
        } else {
            $matchesInsertQuery .= ', 0';
        }
        if ($match AND
                $postImage['is_globally_disabled'] == 0 AND
                $match['is_globally_disabled'] == 0) {
            $matchesInsertQuery .= ', 1';
        } else {
            $matchesInsertQuery .= ', 0';
        }
        $matchesInsertQuery .= ')';
        $matchesInsertResult = run_prepared_query($DBH, $matchesInsertQuery);
        if ($matchesInsertResult->rowCount() == 0) {
            print "DB error";
            exit;
        }
        $imagesProcessed ++;
    }
}

$updateMatchingProgressQuery = "
    UPDATE projects
    SET matching_progress = $matchRadius
    WHERE project_id = :projectId
    LIMIT 1
    ";
$updateMatchingProgressResult = run_prepared_query($DBH, $updateMatchingProgressQuery, $projectIdParam);
if ($updateMatchingProgressResult->rowCount() == 0) {
    exit;
}
