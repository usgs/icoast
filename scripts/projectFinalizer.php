<?php

chdir(dirname(__FILE__));
require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

ignore_user_abort(true);
ini_set('memory_limit', '64M');
ini_set('max_execution_time', 3600);

$userId = filter_input(INPUT_POST, 'user', FILTER_VALIDATE_INT);
$checkCode = filter_input(INPUT_POST, 'checkCode');
$projectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT);
$makeLiveFlag = filter_input(INPUT_POST, 'makeLive', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$makeFocusFlag = filter_input(INPUT_POST, 'makeFocus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

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
$projectIdParam['projectId'] = $projectMetadata['project_id'];


$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 50) {
    exit;
}

if (is_null($makeLiveFlag) || is_null($makeFocusFlag)) {
    exit;
}


$updateFinalizationStageQuery = "
    UPDATE projects
    SET finalization_stage = 1
    WHERE project_id = :projectId
    LIMIT 1";
$updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
if ($updateFinalizationStageResult->rowCount() == 1) {
} else {
    exit;
}

$collectionTypesToProcess = array();
if ($projectMetadata['finalization_stage'] == 0) {
    $collectionTypesToProcess[] = 'pre';
    $collectionTypesToProcess[] = 'post';
} else if ($projectMetadata['finalization_stage'] == 1) {
    $collectionTypesToProcess[] = 'post';
}

$collectionMap = array();
$imageMap = array();
$imageMap[0] = 0;

while (true) {

    foreach ($collectionTypesToProcess as $collectionTypeToProcess) {
        $targetColumn = $collectionTypeToProcess . '_collection_id';

        if ($projectMetadata[$targetColumn] != null) {
            if ($collectionTypeToProcess == 'pre') {
                $stage = 2;
            } else {
                $stage = 3;
            }
            $updateFinalizationStageQuery = "
            UPDATE projects
            SET finalization_stage = $stage
            WHERE project_id = :projectId
            LIMIT 1";
            $updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
            if ($updateFinalizationStageResult->rowCount() == 1) {
                continue;
            } else {
                break 2;
            }
        } else {
            $importedCollection = null;
            $importedCollectionQuery = "
                SELECT *
                FROM import_collections
                WHERE parent_project_id = :projectId
                    AND collection_type = '$collectionTypeToProcess'
                ";
            $importedCollectionResult = run_prepared_query($DBH, $importedCollectionQuery, $projectIdParam);
            $importedCollection = $importedCollectionResult->fetch(PDO::FETCH_ASSOC);
            if (!is_null($importedCollection)) {
                $collectionIdParam['importCollectionId'] = $importedCollection['import_collection_id'];

                $insertCollectionLiveQuery = '
                    INSERT INTO collections
                    (name, description, is_globally_enabled)
                    SELECT name, description, 1 as is_globally_enabled
                    FROM import_collections
                    WHERE import_collection_id = :importCollectionId
                    ';

                $liveCollectionId = run_prepared_query($DBH, $insertCollectionLiveQuery, $collectionIdParam, true);
                if (empty($liveCollectionId)) {

                    break 2;
                }
                $collectionMap[$importedCollection['import_collection_id']] = $liveCollectionId;



                $updateProjectsQuery = "
                    UPDATE projects
                    SET $targetColumn = $liveCollectionId
                    WHERE project_id = :projectId
                    LIMIT 1
                    ";
                $updateProjectsResult = run_prepared_query($DBH, $updateProjectsQuery, $projectIdParam);
                if ($updateProjectsResult->rowCount() == 0) {

                    break 2;
                }



                if (file_exists("../images/collections/$liveCollectionId")) {
                    break 2;
                }
                $mkdirResult = mkdir("../images/collections/$liveCollectionId/main", 0775, true);
                if ($mkdirResult) {
                    $mkdirResult = mkdir("../images/collections/$liveCollectionId/thumbnails", 0775, true);
                    if (!$mkdirResult) {
                        break 2;
                    }
                } else {
                    break 2;
                }
                chmod("../images/collections/$liveCollectionId", 0775);
                chmod("../images/collections/$liveCollectionId/main", 0775);
                chmod("../images/collections/$liveCollectionId/thumbnails", 0775);
                

                $imagesToMoveListQuery = '
                    SELECT import_image_id, filename
                    FROM import_images
                    WHERE import_collection_id = :importCollectionId
                        AND position_in_collection IS NOT NULL';
                $imagesToMoveListResult = run_prepared_query($DBH, $imagesToMoveListQuery, $collectionIdParam);
                while ($imageToMove = $imagesToMoveListResult->fetch(PDO::FETCH_ASSOC)) {
                    $moveMainImageFile = rename(
                            "../images/temporaryImportFolder/{$importedCollection['import_collection_id']}/main/{$imageToMove['filename']}", "../images/collections/$liveCollectionId/main/{$imageToMove['filename']}");
                    if (!$moveMainImageFile) {
                        break 3;
                    }
                    $moveThumbnailImageFile = rename(
                            "../images/temporaryImportFolder/{$importedCollection['import_collection_id']}/thumbnails/{$imageToMove['filename']}", "../images/collections/$liveCollectionId/thumbnails/{$imageToMove['filename']
                            }");
                    if (!$moveThumbnailImageFile) {
                        break 3;
                    }
                    $insertImageLiveQuery = "
                        INSERT INTO images
                        (collection_id, position_in_collection, filename, latitude, longitude, image_time, full_url,
                            thumb_url, display_image_width, display_image_height, thumb_image_width, thumb_image_height,
                            is_globally_disabled, feature, feature_code, city, county, state)
                        SELECT   $liveCollectionId as collection_id, position_in_collection, filename, latitude, longitude,
                            image_time, full_url, thumb_url, display_image_width, display_image_height, thumb_image_width, thumb_image_height,
                            0 as is_globally_disabled, feature, feature_code, city, county, state
                            FROM import_images
                             WHERE import_image_id = :importImageId
                        ";
                    $insertImageLiveParam['importImageId'] = $imageToMove['import_image_id'];
                    $insertImageLiveId = run_prepared_query($DBH, $insertImageLiveQuery, $insertImageLiveParam, true);
                    if (empty($insertImageLiveId)) {
                        break 3;
                    }
                    $imageMap[$imageToMove['import_image_id']] = $insertImageLiveId;
                }


                $filesInFolder = glob("../images/temporaryImportFolder/{$importedCollection['import_collection_id']}/main/*");
                foreach ($filesInFolder as $fileInFolder) {
                    $fileDeleteResult = unlink($fileInFolder); // remove image from 'main' folder
                    if (!$fileDeleteResult) {
                        break 3;
                    } else {
                    }
                    $fileInFolder = str_replace('main', 'thumbnails', $fileInFolder);
                    $fileDeleteResult = unlink($fileInFolder); // remove image from 'thumbnails' folder
                    if (!$fileDeleteResult) {
                        break 3;
                    } else {
                    }
                }
                $mainFolderDeleteResult = rmdir("../images/temporaryImportFolder/{$importedCollection['import_collection_id']}/main");
                if (!$mainFolderDeleteResult) {
                    break 2;
                }
                $thumbnailFolderDeleteResult = rmdir("../images/temporaryImportFolder/{$importedCollection['import_collection_id']}/thumbnails");
                if (!$thumbnailFolderDeleteResult) {
                    break 2;
                }
                $collectionFolderDeleteResult = rmdir("../images/temporaryImportFolder/{$importedCollection['import_collection_id']}");
                if (!$collectionFolderDeleteResult) {
                    break 2;
                }

                $cleanUpImportCollectionsQuery = '
                    DELETE FROM import_collections
                    WHERE import_collection_id = :importCollectionId
                    LIMIT 1
                    ';
                $cleanUpImportCollectionsResult = run_prepared_query($DBH, $cleanUpImportCollectionsQuery, $collectionIdParam);
                if ($cleanUpImportCollectionsResult->rowCount() == 0) {

                    break 2;
                }



                $cleanUpImportImagesQuery = '
                    DELETE FROM import_images
                    WHERE import_collection_id = :importCollectionId';
                $cleanUpImportImagesResult = run_prepared_query($DBH, $cleanUpImportImagesQuery, $collectionIdParam);
                if ($cleanUpImportImagesResult->rowCount() == 0) {

                    break 2;
                }

                if ($collectionTypeToProcess == 'pre') {
                    $stage = 2;
                } else {
                    $stage = 3;
                }
                $updateFinalizationStageQuery = "
                    UPDATE projects
                    SET finalization_stage = $stage
                    WHERE project_id = :projectId
                    LIMIT 1";
                $updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
                if ($updateFinalizationStageResult->rowCount() == 1) {
                } else {

                    break 2;
                }
            } else {

                break 2;
            }
        }
    }


    $importMatchesToDelete = 0;
    $matchesToMoveQuery = '
    SELECT *
    FROM import_matches
    WHERE project_id = :projectId';
    $matchesToMoveResult = run_prepared_query($DBH, $matchesToMoveQuery, $projectIdParam);
    while ($matchToMove = $matchesToMoveResult->fetch(PDO::FETCH_ASSOC)) {
        $importMatchesToDelete++;
        if ($matchToMove['is_post_collection_imported'] == 1) {
            $matchToMove['post_image_id'] = $imageMap[$matchToMove['post_image_id']];
            $matchToMove['post_collection_id'] = $collectionMap[$matchToMove['post_collection_id']];
        }
        if ($matchToMove['is_pre_collection_imported'] == 1) {
            $matchToMove['pre_image_id'] = $imageMap[$matchToMove['pre_image_id']];
            $matchToMove['pre_collection_id'] = $collectionMap[$matchToMove ['pre_collection_id']];
        }
        $insertMatchLiveQuery = "
        INSERT IGNORE INTO matches
        (post_collection_id, post_image_id, pre_collection_id, pre_image_id, is_enabled, is_automated_match,
            user_match_count)
        VALUES (
            {$matchToMove['post_collection_id'] },
            {$matchToMove['post_image_id']},
            {$matchToMove['pre_collection_id']},
            {$matchToMove['pre_image_id']},
            {$matchToMove['is_enabled']} ,
            1,
            0
        )
    ";
        $insertMatchLiveResult = run_prepared_query($DBH, $insertMatchLiveQuery);
    }

    if ($importMatchesToDelete > 0) {
        $cleanUpImportMatchesQuery = '
            DELETE
            FROM import_matches
            WHERE project_id = :projectId
            ';
        $cleanUpImportMatchesResult = run_prepared_query($DBH, $cleanUpImportMatchesQuery, $projectIdParam);
        if ($cleanUpImportMatchesResult->rowCount() != $importMatchesToDelete) {

            break;
        }
    }
    $updateFinalizationStageQuery = "
    UPDATE projects
    SET finalization_stage = 4
    WHERE project_id = :projectId
    LIMIT 1";
    $updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
    if ($updateFinalizationStageResult->rowCount() == 1) {
    } else {

        break;
    }

    if ($makeLiveFlag) {
        $updateLiveQuery = "
        UPDATE projects
        SET is_public = 1
        WHERE project_id = :projectId
        LIMIT 1";
        $updateLiveResult = run_prepared_query($DBH, $updateLiveQuery, $projectIdParam);
        if ($updateLiveResult->rowCount() == 1) {
        } else {

            break;
        }
        if ($makeFocusFlag) {
            $updateFocusQuery = "
            UPDATE system
            SET home_page_project = :projectId
            WHERE id = 0
            LIMIT 1";
            $updateFocusResult = run_prepared_query($DBH, $updateFocusQuery, $projectIdParam);
            if ($updateFocusResult->rowCount() == 1) {
            } else {

                break;
            }
        }
    }

    $updateFinalizationStageQuery = "
    UPDATE projects
    SET finalization_stage = NULL,
        tasks_complete = NULL,
        import_complete = NULL,
        matching_progress = NULL,
        is_complete = 1
    WHERE project_id = :projectId
    LIMIT 1";
    $updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
    if ($updateFinalizationStageResult->rowCount() == 1) {
        exit;
    } else {
        break;
    }
}

$updateFinalizationStageQuery = "
    UPDATE projects
    SET finalization_stage = 10
    WHERE project_id = :projectId
    LIMIT 1";
$updateFinalizationStageResult = run_prepared_query($DBH, $updateFinalizationStageQuery, $projectIdParam);
if ($updateFinalizationStageResult->rowCount() == 1) {
    exit;
} else {
}



