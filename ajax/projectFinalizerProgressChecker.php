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
    exit;
}
settype($projectId, 'integer');
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata) || $projectMetadata['creator'] != $userData['user_id']) {
    exit;
}
$projectIdParam['projectId'] = $projectMetadata['project_id'];
unset($projectId);

if ($projectMetadata['is_complete'] == 1) {
    $result['stage'] = 5;
} else {
    switch ($projectMetadata['finalization_stage']) {
        case 0:
            $result['stage'] = 0;
            break;
        case 1:
            $result['stage'] = 1;
            $result['progressPercentage'] = 0;

            $importCollectionIdQuery = "
             SELECT import_collection_id
             FROM import_collections
             WHERE parent_project_id = :projectId
                AND collection_type = 'pre'";
            $importCollectionIdResult = run_prepared_query($DBH, $importCollectionIdQuery, $projectIdParam);
            $importCollectionId = $importCollectionIdResult->fetchColumn();
            if ($importCollectionId) {
                $importImageCountQuery = '
                    SELECT COUNT(*)
                    FROM import_images
                    WHERE import_collection_id = :importCollectionId';
                $importImageCountParam['importCollectionId'] = $importCollectionId;
                $importImageCountResult = run_prepared_query($DBH, $importImageCountQuery, $importImageCountParam);
                $importImageCount = $importImageCountResult->fetchColumn();

                $liveCollectionId = $projectMetadata['pre_collection_id'];
                if ($liveCollectionId) {
                    $liveImageCountQuery = '
                        SELECT COUNT(*)
                        FROM images
                        WHERE collection_id = :collectionId';
                    $liveImageCountParams['collectionId'] = $liveCollectionId;
                    $liveImageCountResult = run_prepared_query($DBH, $liveImageCountQuery, $liveImageCountParams);
                    $liveImageCount = $liveImageCountResult->fetchColumn();
                    if ($liveImageCount) {
                        $progressPercentage = floor(($liveImageCount / $importImageCount) * 100);
                        $result['progressPercentage'] = $progressPercentage;
                    }
                }
            } else {
                $result['progressPercentage'] = 100;
            }
            break;
        case 2:
            $result['stage'] = 2;
            $result['progressPercentage'] = 0;

            $importCollectionIdQuery = "
             SELECT import_collection_id
             FROM import_collections
             WHERE parent_project_id = :projectId
                AND collection_type = 'post'";
            $importCollectionIdResult = run_prepared_query($DBH, $importCollectionIdQuery, $projectIdParam);
            $importCollectionId = $importCollectionIdResult->fetchColumn();
            if ($importCollectionId) {
                $importImageCountQuery = '
                    SELECT COUNT(*)
                    FROM import_images
                    WHERE import_collection_id = :importCollectionId';
                $importImageCountParam['importCollectionId'] = $importCollectionId;
                $importImageCountResult = run_prepared_query($DBH, $importImageCountQuery, $importImageCountParam);
                $importImageCount = $importImageCountResult->fetchColumn();

                $liveCollectionId = $projectMetadata['post_collection_id'];
                if ($liveCollectionId) {
                    $liveImageCountQuery = '
                        SELECT COUNT(*)
                        FROM images
                        WHERE collection_id = :collectionId';
                    $liveImageCountParams['collectionId'] = $liveCollectionId;
                    $liveImageCountResult = run_prepared_query($DBH, $liveImageCountQuery, $liveImageCountParams);
                    $liveImageCount = $liveImageCountResult->fetchColumn();
                    if ($liveImageCount) {
                        $progressPercentage = floor(($liveImageCount / $importImageCount) * 100);
                        $result['progressPercentage'] = $progressPercentage;
                    }
                }
            } else {
                $result['progressPercentage'] = 100;
            }
            break;
        case 3:
            $result['stage'] = 3;
            $result['progressPercentage'] = 0;
            $importMatchesCountQuery = '
                SELECT COUNT(*)
                FROM import_matches
                WHERE project_id = :projectId';
            $importMatchesCountResult = run_prepared_query($DBH, $importMatchesCountQuery, $projectIdParam);
            $importMatchesCount = $importMatchesCountResult->fetchColumn();
            if ($importMatchesCount) {
                $matchesCountQuery = '
                    SELECT COUNT(*)
                    FROM matches
                    WHERE post_collection_id = :postCollectionId
                        AND pre_collection_id = :preCollectionId';
                $matchesCountParams = array(
                    'postCollectionId' => $projectMetadata['post_collection_id'],
                    'preCollectionId' => $projectMetadata['pre_collection_id']
                );
                $matchesCountResult = run_prepared_query($DBH, $matchesCountQuery, $matchesCountParams);
                $matchesCount = $matchesCountResult->fetchColumn();
                if ($matchesCount) {
                    $progressPercentage = floor(($matchesCount / $importMatchesCount) * 100);
                    $result['progressPercentage'] = $progressPercentage;
                }
            } else {
                $result['progressPercentage'] = 100;
            }
            break;
        case 4:
            $result['stage'] = 4;
            $result['progressPercentage'] = 50;
            break;
        case 10:
            $result['stage'] = 10;
    }
}


print json_encode($result);
