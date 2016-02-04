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
$collectionId = filter_input(INPUT_POST, 'collectionId', FILTER_VALIDATE_INT);

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

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    exit;
} else if ($collectionMetadata['creator'] != $userMetadata['user_id']) {
    exit;
}
$collectionIdParam['collectionId'] = $collectionId;

if ($collectionMetadata['sequencing_stage != 4']) {
    exit;
}


while (true) {

    $insertCollectionLiveQuery = <<<MYSQL
        INSERT INTO collections
        (
          import_collection_id,
          creator,
          name,
          description,
          is_globally_enabled
        )
        VALUES
        (
          {$collectionMetadata['import_collection_id']},
          $userId,
          '{$collectionMetadata['name']}',
          '{$collectionMetadata['description']}',
          1
        )
MYSQL;

    $liveCollectionId = run_prepared_query($DBH, $insertCollectionLiveQuery, array(), true);
    if (empty($liveCollectionId)) {

        break;
    }


    if (file_exists("../images/collections/$liveCollectionId")) {
        break;
    }
    $mkdirResult = mkdir("../images/collections/$liveCollectionId/main", 0775, true);
    if ($mkdirResult) {
        $mkdirResult = mkdir("../images/collections/$liveCollectionId/thumbnails", 0775, true);
        if (!$mkdirResult) {
            break;
        }
    } else {
        break;
    }
    chmod("../images/collections/$liveCollectionId", 0775);
    chmod("../images/collections/$liveCollectionId/main", 0775);
    chmod("../images/collections/$liveCollectionId/thumbnails", 0775);

    $imagesToMoveListQuery = '
                    SELECT import_image_id, filename
                    FROM import_images
                    WHERE import_collection_id = :collectionId
                        AND position_in_collection IS NOT NULL';
    $imagesToMoveListResult = run_prepared_query($DBH, $imagesToMoveListQuery, $collectionIdParam);
    while ($imageToMove = $imagesToMoveListResult->fetch(PDO::FETCH_ASSOC)) {
        $moveMainImageFile = rename(
            "../images/temporaryImportFolder/$collectionId/main/{$imageToMove['filename']}", "../images/collections/$liveCollectionId/main/{$imageToMove['filename']}");
        if (!$moveMainImageFile) {
            break 2;
        }
        $moveThumbnailImageFile = rename(
            "../images/temporaryImportFolder/$collectionId/thumbnails/{$imageToMove['filename']}", "../images/collections/$liveCollectionId/thumbnails/{$imageToMove['filename']
                            }");
        if (!$moveThumbnailImageFile) {
            break 2;
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
            break 2;
        }
    }


    $filesInFolder = glob("../images/temporaryImportFolder/$collectionId/main/*");
    foreach ($filesInFolder as $fileInFolder) {
        $fileDeleteResult = unlink($fileInFolder); // remove image from 'main' folder
        if (!$fileDeleteResult) {
            break 2;
        }
        $fileInFolder = str_replace('main', 'thumbnails', $fileInFolder);
        $fileDeleteResult = unlink($fileInFolder); // remove image from 'thumbnails' folder
        if (!$fileDeleteResult) {
            break 2;
        }
    }
    $mainFolderDeleteResult = rmdir("../images/temporaryImportFolder/$collectionId/main");
    if (!$mainFolderDeleteResult) {
        break;
    }

    $thumbnailFolderDeleteResult = rmdir("../images/temporaryImportFolder/$collectionId/thumbnails");
    if (!$thumbnailFolderDeleteResult) {
        break;
    }
    $collectionFolderDeleteResult = rmdir("../images/temporaryImportFolder/$collectionId");
    if (!$collectionFolderDeleteResult) {
        break;
    }

    $cleanUpImportCollectionsQuery = '
                    DELETE FROM import_collections
                    WHERE import_collection_id = :collectionId
                    LIMIT 1
                    ';
    $cleanUpImportCollectionsResult = run_prepared_query($DBH, $cleanUpImportCollectionsQuery, $collectionIdParam);
    if ($cleanUpImportCollectionsResult->rowCount() == 0) {
        break;
    }


    $cleanUpImportImagesQuery = '
                    DELETE FROM import_images
                    WHERE import_collection_id = :collectionId';
    $cleanUpImportImagesResult = run_prepared_query($DBH, $cleanUpImportImagesQuery, $collectionIdParam);
    if ($cleanUpImportImagesResult->rowCount() == 0) {
        break;
    }
    break;
}



