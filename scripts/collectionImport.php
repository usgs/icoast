<?php

chdir(dirname(__FILE__));
require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

ignore_user_abort(true);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 172800);

function abort_import($DBH = null, $failureReason = null, $importCollectionId = null) {
    if ($DBH != null && $failureReason != null && $importCollectionId != null) {
        $updateCollectionQuery = '
        UPDATE import_collections
        SET
            import_key = null,
            session_id = null,
            import_end_timestamp = :endTimestamp,
            import_status_message = :failureReason
        WHERE import_collection_id = :importCollectionId
        LIMIT 1
    ';
        $updateCollectionParams = array(
            'endTimestamp' => time(),
            'failureReason' => $failureReason,
            'importCollectionId' => $importCollectionId
        );
        run_prepared_query($DBH, $updateCollectionQuery, $updateCollectionParams);
    }
    exit;
}

function user_requested_abort($DBH, $importCollectionMetadata, $processedImageCount) {
    $deleteImportedImagesQuery = "
        DELETE FROM import_images
        WHERE import_collection_id = :importCollectionId
        LIMIT $processedImageCount
    ";
    $deleteImportedImagesParams = array(
        'importCollectionId' => $importCollectionMetadata['import_collection_id']
    );
    run_prepared_query($DBH, $deleteImportedImagesQuery, $deleteImportedImagesParams);
    $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/*");
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            unlink($file); // delete file
        }
    }
    rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main");
    $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/*");
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            unlink($file); // delete file
        }
    }
    rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails");
    rmdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}");

    abort_import($DBH, 'User Abort Request', $importCollectionMetadata['import_collection_id']);
}

function failedImageUpdate($collectionId, $count) {
    if (isset($GLOBALS['DBH'])) {
        global $DBH;
    } else {
        exit;
    }
    $updateFailedCountQuery = '
        UPDATE import_collections
        SET failed_images = :failedImageCount,
            last_update_timestamp = :updateTimestamp
        WHERE import_collection_id = :importCollectionId
        LIMIT 1
        ';
    $updateFailedCountParams = array(
        'updateTimestamp' => time(),
        'failedImageCount' => $count,
        'importCollectionId' => $collectionId
    );
    run_prepared_query($DBH, $updateFailedCountQuery, $updateFailedCountParams);
}

function userAbortCheck($importCollectionMetadata, $processedImageCount) {
    if (isset($GLOBALS['DBH'])) {
        global $DBH;
    } else {
        exit;
    }
    $abortQuery = '
        SELECT user_abort_import_flag
        FROM import_collections
        WHERE import_collection_id = :importCollectionId';
    $abortParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];
    $abortResult = run_prepared_query($DBH, $abortQuery, $abortParams);
    $abortStatus = $abortResult->fetchColumn();
    if ($abortStatus == 1) {
        user_requested_abort($DBH, $importCollectionMetadata, $processedImageCount);
    }
}

$importCollectionId = filter_input(INPUT_POST, 'importCollectionId', FILTER_VALIDATE_INT);
$importKey = filter_input(INPUT_POST, 'importKey');
$userId = filter_input(INPUT_POST, 'user', FILTER_VALIDATE_INT);
$checkCode = filter_input(INPUT_POST, 'checkCode');


$userMetadata = retrieve_entity_metadata($DBH, $userId, 'user');
if (empty($userMetadata)) {
    abort_import($DBH, "No user ID or invalid user ID supplied.");
} else {
    if (isset($checkCode)) {
        if ($checkCode != $userMetadata['auth_check_code']) {
            abort_import($DBH, "User authentication failed.");
        }
    } else {
        abort_import($DBH, "No user authentication data supplied.");
    }
}



//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Check update permission and project state validity
if (isset($importCollectionId)) {
    $importCollectionMetadata = retrieve_entity_metadata($DBH, $importCollectionId, 'importCollection');
    if ($importCollectionMetadata) {
        if (isset($importKey) &&
                (!empty($importKey)) &&
                $importCollectionMetadata['import_key'] == $importKey) {
            if (!empty($importCollectionMetadata['session_id']) && !empty($importCollectionMetadata['import_start_timestamp'])) {
                $elapsedTime = time() - $importCollectionMetadata['import_start_timestamp'];
                if ($elapsedTime < 10) {
                    session_id($importCollectionMetadata['session_id']);
                    session_start();
                    $removeImportKeyAndSessionQuery = '
                        UPDATE import_collections
                        SET import_key = NULL,
                            session_id = NULL
                        WHERE import_collection_id = :importCollectionId
                        LIMIT 1
                        ';
                    $removeImportKeyAndSessionParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];
                    run_prepared_query($DBH, $removeImportKeyAndSessionQuery, $removeImportKeyAndSessionParams);
                    if (!isset($_SESSION)) {
                        abort_import($DBH, "Session Creation Failure (session_id = {$importCollectionMetadata['session_id']})", $importCollectionMetadata['import_collection_id']);
                    } else if (count($_SESSION) == 0) {
                        abort_import($DBH, "Session Empty (session_save_path = " . session_save_path() . ")", $importCollectionMetadata['import_collection_id']);
                    }
                } else {
                    abort_import($DBH, "Import initiation timeout (" . time() . " -  {$importCollectionMetadata['import_start_timestamp']} = $elapsedTime secs elapsed, 10 secs allowed)", $importCollectionMetadata['import_collection_id']);
                }
            } else {
                $message = '';
                if (empty($importCollectionMetadata['session_id'])) {
                    $message .='Database session_id field is empty. ';
                }
                if (empty($importCollectionMetadata['import_start_timestamp'])) {
                    $message .='Database import_start_timestamp field is empty.';
                }
                abort_import($DBH, $message, $importCollectionMetadata['import_collection_id']);
            }
        } else {
            $qStringKey = 'NOT PRESENT';
            $dbKey = 'NOT PRESENT';
            if (!empty($importKey)) {
                $qStringKey = $importKey;
            }
            if (!empty($importCollectionMetadata['import_key'])) {
                $dbKey = $importCollectionMetadata['import_key'];
            }
            abort_import($DBH, "Missing or invalid import key. Query String Key = $qStringKey. Database Key = $dbKey", $importCollectionMetadata['import_collection_id']);
        }
    } else {
        abort_import($DBH, "Cannot load collection metadata (ID: $importCollectionId).");
    }
} else {
    abort_import($DBH, "No collection ID found.");
}

if (isset($_SESSION['collectionType'])) {
    switch ($_SESSION['collectionType']) {
        case 'pre':
            $databaseColumn = 'pre_import_collection_id';
            $expectedImportStatus = 1;
            break;
        case 'post':
            $databaseColumn = 'post_import_collection_id';
            $expectedImportStatus = 2;
            break;
        default:
            abort_import($DBH, "Invalid collection type provided ({$_SESSION['collectionType']})", $importCollectionMetadata['import_collection_id']);
    }
} else {
    abort_import($DBH, 'Missing Stage Level', $importCollectionMetadata['import_collection_id']);
}

$importStatus = project_creation_stage($importCollectionMetadata['parent_project_id']);
if ($importStatus != $expectedImportStatus) {
    exit;
}
$logFilename = '../images/logs/import' . $importCollectionMetadata['import_collection_id'] . '.txt';
if (file_exists($logFilename)) {
    unlink($logFilename);
}
//file_put_contents($logFilename, 'Start'. "\r\n", FILE_APPEND);
//chmod($logFilename, 0777);

$setProcessingFlagQuery = '
    UPDATE import_collections
    SET import_status_message = "Processing"
    WHERE import_collection_id = :importCollectionId
    LIMIT 1
    ';
$setProcessingFlagParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];
run_prepared_query($DBH, $setProcessingFlagQuery, $setProcessingFlagParams);

define(SLEEP_PERIOD, 600);
define(RESIZED_DISPLAY_WIDTH, 800);
define(RESIZED_THUMBNAIL_WIDTH, 154);
define(JPEG_QUALITY, 75);
define(MAX_FEATURE_MATCH_RADIUS, 1600); // 1 mile range on features
define(MAX_POPULOUS_MATCH_RADIUS, 9650); // 6 mile range on populated city match

$imageArray = $_SESSION['imageArray'];
session_unset();
session_destroy();
$imagesToProcess = count($imageArray);
$retryCount = 0;
$processedImageCount = 0;
$portraitImageCount = 0;

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// => Create a temporary folder for the dataset images. If not create one.
if (!file_exists("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main")) {
    if (!mkdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main", 0775, true)) {
        abort_import($DBH, 'Main Folder Creation Failure', $importCollectionMetadata['import_collection_id']);
    }
} else {
    $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/*");
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            unlink($file); // delete file
        }
    }
}
if (!file_exists("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails")) {
    if (!mkdir("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails", 0775, true)) {
        abort_import($DBH, 'Thumbnail Folder Creation Failure', $importCollectionMetadata['import_collection_id']);
    }
} else {
    $files = glob("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/*");
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            unlink($file); // delete file
        }
    }
}
chmod("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}", 0775);
chmod("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main", 0775);
chmod("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails", 0775);
//file_put_contents($logFilename, 'Existing Folder check complete'. "\r\n", FILE_APPEND);

do {
    $failedImagesToRetry = array();
    $retryCount++;
//     file_put_contents($logFilename, 'Loop 1'. "\r\n", FILE_APPEND);
    if ($retryCount == 3) {
//         file_put_contents($logFilename, 'retrying = 3'. "\r\n", FILE_APPEND);
        $setProcessingFlagQuery = '
            UPDATE import_collections
            SET import_status_message = "Sleeping"
            WHERE import_collection_id = :importCollectionId
            LIMIT 1
            ';
        $setProcessingFlagParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];
        run_prepared_query($DBH, $setProcessingFlagQuery, $setProcessingFlagParams);
        for ($i = 0; $i < SLEEP_PERIOD / 5; $i++) {
            userAbortCheck($importCollectionMetadata, $processedImageCount);
            sleep(5);
        }
        $setProcessingFlagQuery = '
            UPDATE import_collections
            SET import_status_message = "Processing"
            WHERE import_collection_id = :importCollectionId
            LIMIT 1
            ';
        $setProcessingFlagParams['importCollectionId'] = $importCollectionMetadata['import_collection_id'];
        run_prepared_query($DBH, $setProcessingFlagQuery, $setProcessingFlagParams);
    }
    foreach ($imageArray as $image) {
//        $initalTimeStamp = microtime(true);
//        file_put_contents($logFilename, "Image {$image['filename']} : $initalTimeStamp\r\n", FILE_APPEND);

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////
// => Check and resize the image
// If possible convert the Internet URL into a relative local file path
//         file_put_contents($logFilename, "Processing {$image['import_image_id']}". "\r\n", FILE_APPEND);
        userAbortCheck($importCollectionMetadata, $processedImageCount);

        $localPath = FALSE;
        $serverStringStartPosition = stripos($image['imageURL'], 'coastal.er.usgs.gov/');
        if ($serverStringStartPosition !== false) {
            $serverStringLength = strlen('coastal.er.usgs.gov/');
            $importImagePath = '../../' . substr($image['imageURL'], $serverStringStartPosition + $serverStringLength);
            $localPath = TRUE;
//            file_put_contents($logFilename, "Using local file path: $importImagePath\r\n", FILE_APPEND);
        } else {
            $importImagePath = $image['imageURL'];
//            file_put_contents($logFilename, "Using URL file path: $importImagePath\r\n", FILE_APPEND);
        }
// Remove after development
        $importImagePath = '../../' . substr($image['imageURL'], 0 + 27);
        $localPath = TRUE;
        DEBUG_OUTPUT ? $debugArray['csvImport'][$rowIndex]['importImagePath'] = $importImagePath : false;

        // Use PHP GD to download the image (check it exists).
        if (!@$originalImage = imagecreatefromjpeg($importImagePath)) {
            if ($localPath) {
                $importImagePath = $image['imageURL'];
//                file_put_contents($logFilename, "Local path failed switching to URL path: $importImagePath\r\n", FILE_APPEND);
                if (!@$originalImage = imagecreatefromjpeg($importImagePath)) {
//                    file_put_contents($logFilename, "URL not found. Adding to retry.\r\n", FILE_APPEND);
                    $failedImagesToRetry[] = $image;
                    failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
                    continue; // Image file not found. Try again later. Skip this image/row.
                }
            } else {
//                file_put_contents($logFilename, "URL not found. Adding to retry.\r\n", FILE_APPEND);
                $failedImagesToRetry[] = $image;
                failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
                continue; // Image file not found. Try again later. Skip this image/row.
            }
        }
//        $imageDownloadStamp = microtime(true);
//        $actionElapsedTime = $imageDownloadStamp - $initalTimeStamp;
//        $totalElapsedTime = $imageDownloadStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Images Downloaded: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);
        // Determine original image dimensions.
        $originalImageWidth = imagesx($originalImage);
        $originalImageHeight = imagesy($originalImage);

        // Check the image is Landscape. Skip if not.
        if ($originalImageWidth < $originalImageHeight) {
            $portraitImageCount++;
            $updatePortraitCountQuery = '
                UPDATE import_collections
                SET portrait_images = :portraitImageCount,
                last_update_timestamp = :updateTimestamp
                WHERE import_collection_id = :importCollectionId
                LIMIT 1
                ';
            $updatePortraitCountParams = array(
                'updateTimestamp' => time(),
                'portraitImageCount' => $portraitImageCount,
                'importCollectionId' => $importCollectionMetadata['import_collection_id']
            );
            run_prepared_query($DBH, $updatePortraitCountQuery, $updatePortraitCountParams);
            continue; // Image is portrait. Skip this image/row.
        }
        $imageAspectRatio = $originalImageHeight / $originalImageWidth;

        // Calculate dimesions of display image and create a blank canvas of the correct size.
        $image['displayImageHeight'] = floor(RESIZED_DISPLAY_WIDTH * $imageAspectRatio);
        $displayImage = imagecreatetruecolor(RESIZED_DISPLAY_WIDTH, $image['displayImageHeight']);
        // Copy the original to the new display image canvas resizing as it copies.
        if (!imagecopyresampled($displayImage, $originalImage, 0, 0, 0, 0, RESIZED_DISPLAY_WIDTH, $image['displayImageHeight'], $originalImageWidth, $originalImageHeight)) {
            $failedImagesToRetry[] = $image;
            failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
            continue; // Image resize failed. Try again later. Skip this image/row.
        }

        // Calculate dimesions of thumbnail image and create a blank canvas of the correct size.
        $image['thumbImageHeight'] = floor(RESIZED_THUMBNAIL_WIDTH * $imageAspectRatio);
        $thumbnailImage = imagecreatetruecolor(RESIZED_THUMBNAIL_WIDTH, $image['thumbImageHeight']);
        // Copy the original to the new display image canvas resizing as it copies.
        if (!imagecopyresampled($thumbnailImage, $originalImage, 0, 0, 0, 0, RESIZED_THUMBNAIL_WIDTH, $image['thumbImageHeight'], $originalImageWidth, $originalImageHeight)) {
            $failedImagesToRetry[] = $image;
            failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
            continue; // Image resize failed. Try again later. Skip this image/row.
        }

        // Save the new display image to the disk.
        if (!imagejpeg($displayImage, "../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/{$image['filename']}", JPEG_QUALITY)) {
            $failedImagesToRetry[] = $image;
            failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
            continue; // Image save failed. Try again later. Skip this image/row.
        }

        // Save the new image to the disk.
        if (!imagejpeg($thumbnailImage, "../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/{$image['filename']}", JPEG_QUALITY)) {
            $failedImagesToRetry[] = $image;
            failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
            unlink("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/{$image['filename']}");
            continue; // Image save failed. Try again later. Skip this image/row.
        }
        chmod("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/{$image['filename']}", 0775);
        chmod("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/{$image['filename']}", 0775);

        // Release the memory used in the resizing process
        imagedestroy($originalImage);
        imagedestroy($displayImage);
        imagedestroy($thumbnailImage);
        // file_put_contents($logFilename, 'Image Creation COmplete'. "\r\n", FILE_APPEND);
//        $imageTimeStamp = microtime(true);
//        $actionElapsedTime = $imageTimeStamp - $initalTimeStamp;
//        $totalElapsedTime = $imageTimeStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Images Created: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// => Reverse geocode the image using the geonames and geonames_counties tables.

        $geonamesSelectParams = array(
            'latitude' => $image['latitude'],
            'longitude' => $image['longitude']
        );

        // First look for any features local to the image.
        $geonamesSelectQuery = "SELECT name, feature_code, county_code, state,
	    (6378137 * acos(cos(radians(:latitude)) * cos(radians(latitude))
		* cos(radians(longitude) - radians(:longitude) ) + sin(radians(:latitude))
		    * sin(radians(latitude)))) AS distance FROM geonames
		    WHERE feature_code NOT IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3')
		    HAVING distance < " . MAX_FEATURE_MATCH_RADIUS . " ORDER BY distance LIMIT 1;";

        $geonamesSelectResult = run_prepared_query($DBH, $geonamesSelectQuery, $geonamesSelectParams);
        $feature = $geonamesSelectResult->fetch(PDO::FETCH_ASSOC);

        // If a feature is found add it to the $csvRow array.
        if (!empty($feature)) {
            $image['feature'] = $feature['name'];
            $image['featureCode'] = $feature['feature_code'];
        } else {
            $image['feature'] = '';
            $image['featureCode'] = '';
        }
//        $featureTimeStamp = microtime(true);
//        $actionElapsedTime = $featureTimeStamp - $imageTimeStamp;
//        $totalElapsedTime = $featureTimeStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Feature Search: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);

        // Next find the closest city to the image.
        $geonamesSelectQuery = "SELECT name, county_code, state,
	    (6378137 * acos(cos(radians(:latitude)) * cos(radians(latitude))
		* cos(radians(longitude) - radians(:longitude) ) + sin(radians(:latitude))
		    * sin(radians(latitude)))) AS distance FROM geonames
		    WHERE feature_code IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3') AND population = '1'
		    HAVING distance < " . MAX_POPULOUS_MATCH_RADIUS . " ORDER BY distance LIMIT 1;";

        $geonamesSelectResult = run_prepared_query($DBH, $geonamesSelectQuery, $geonamesSelectParams);
        $city = $geonamesSelectResult->fetch(PDO::FETCH_ASSOC);
//        $cityTimeStamp = microtime(true);
//        $actionElapsedTime = $cityTimeStamp - $featureTimeStamp;
//        $totalElapsedTime = $cityTimeStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Major City Search: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);

        // Add the nearest city to the $csvRow array.
        if ($city) {
            // file_put_contents($logFilename, "MaX POP", FILE_APPEND);
            foreach ($city as $param => $value) {
                // file_put_contents($logFilename, $param . "=>" . $value . "\r\n", FILE_APPEND);
            }
            $image['city'] = $city['name'];
            $image['state'] = $city['state'];

            // Determine County
            $geonamesCountiesSelectQuery = "SELECT county_name FROM geonames_counties
                WHERE state = :state AND county_code = :countyCode";
            $geonamesCountiesSelectParams = array(
                'state' => $city['state'],
                'countyCode' => $city['county_code']
            );
            $geonamesCountiesSelectResults = run_prepared_query($DBH, $geonamesCountiesSelectQuery, $geonamesCountiesSelectParams);
            $county = $geonamesCountiesSelectResults->fetchColumn();
            if ($county) {
                // file_put_contents($logFilename, "County = $county\r\n", FILE_APPEND);
                $image['county'] = $county;
            }
        } else {
            // file_put_contents($logFilename, "Unlimited", FILE_APPEND);
            // If no major city in range then find the closest minor city/neighborhood to the image.
            $geonamesSelectQuery = "SELECT name, county_code, state,
                (6378137 * acos(cos(radians(:latitude)) * cos(radians(latitude))
                * cos(radians(longitude) - radians(:longitude) ) + sin(radians(:latitude))
                * sin(radians(latitude)))) AS distance FROM geonames
                WHERE feature_code IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3')
                ORDER BY distance LIMIT 1;";
            $geonamesSelectResult = run_prepared_query($DBH, $geonamesSelectQuery, $geonamesSelectParams);
            $city = $geonamesSelectResult->fetch(PDO::FETCH_ASSOC);
//            $minorCityTimeStamp = microtime(true);
//            $actionElapsedTime = $minorCityTimeStamp - $cityTimeStamp;
//            $totalElapsedTime = $minorCityTimeStamp - $initalTimeStamp;
//            file_put_contents($logFilename, "Minor City Search: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);
            // Add the nearest city to the $csvRow array.
            if ($city) {
                foreach ($city as $param => $value) {
                    // file_put_contents($logFilename, $param . "=>" . $value . "\r\n", FILE_APPEND);
                }
                $image['city'] = $city['name'];
                $image['state'] = $city['state'];

                // Determine County
                $geonamesCountiesSelectQuery = "SELECT county_name FROM geonames_counties
                    WHERE state = :state AND county_code = :countyCode";
                $geonamesCountiesSelectParams = array(
                    'state' => $city['state'],
                    'countyCode' => $city['county_code']
                );
                $geonamesCountiesSelectResults = run_prepared_query($DBH, $geonamesCountiesSelectQuery, $geonamesCountiesSelectParams);
                $county = $geonamesCountiesSelectResults->fetchColumn();
                if ($county) {
                    // file_put_contents($logFilename, "County = $county\r\n" , FILE_APPEND);
                    $image['county'] = $county;
                }
            }
        }
        if (!$city) {
//            file_put_contents($logFilename, 'No City' . "\r\n", FILE_APPEND);
        }
        // file_put_contents($logFilename, 'GeoCoding complete'. "\r\n", FILE_APPEND);
//        $georefTimeStamp = microtime(true);
//        $actionElapsedTime = $georefTimeStamp - $imageTimeStamp;
//        $totalElapsedTime = $georefTimeStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Geo Reference Total: $actionElapsedTime : $totalElapsedTime\r\n", FILE_APPEND);
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// => Insert image into the import_images table;
        $imagesInsertQuery = "INSERT INTO import_images
        (import_collection_id, location_sort_order, filename, latitude, longitude, timestamp, image_time, full_url, thumb_url,
        display_image_width, display_image_height, thumb_image_width, thumb_image_height, region, feature, feature_code, city, county, state)
        VALUES (:importCollectionId, :locationSortOrder, :filename, :latitude, :longitude, :timestamp, :imageTime, :fullURL, :thumbURL,
        :displayImageWidth, :displayImageHeight, :thumbImageWidth, :thumbImageHeight, :region, :feature, :featureCode, :city, :county, :state)";
        $imagesInsertParams = array(
            'importCollectionId' => $importCollectionMetadata['import_collection_id'],
            'locationSortOrder' => $image['locationSortOrder'],
            'filename' => $image['filename'],
            'latitude' => $image['latitude'],
            'longitude' => $image['longitude'],
            'timestamp' => $image['timestamp'],
            'imageTime' => $image['dateTime'],
            'fullURL' => $image['imageURL'],
            'thumbURL' => $image['thumbnailURL'],
            'displayImageWidth' => RESIZED_DISPLAY_WIDTH,
            'displayImageHeight' => $image['displayImageHeight'],
            'thumbImageWidth' => RESIZED_THUMBNAIL_WIDTH,
            'thumbImageHeight' => $image['thumbImageHeight'],
            'region' => $image['region'],
            'feature' => $image['feature'],
            'featureCode' => $image['featureCode'],
            'city' => $image['city'],
            'county' => $image['county'],
            'state' => $image['state']
        );
//        foreach ($imagesInsertParams as $param => $value) {
//             file_put_contents($logFilename, $param . "=>" . $value . "\r\n", FILE_APPEND);
//        }
        $insertId = run_prepared_query($DBH, $imagesInsertQuery, $imagesInsertParams, true);
        if ($insertId) {
//             file_put_contents($logFilename, 'IMage DB insert success'. "\r\n", FILE_APPEND);
            $processedImageCount++;
            $updateProcessedCountQuery = '
                UPDATE import_collections
                SET processed_images = :processedImageCount,
                last_update_timestamp = :updateTimestamp
                WHERE import_collection_id = :importCollectionId
                LIMIT 1
            ';
            $updateProcessedCountParams = array(
                'updateTimestamp' => time(),
                'processedImageCount' => $processedImageCount,
                'importCollectionId' => $importCollectionMetadata['import_collection_id']
            );
            run_prepared_query($DBH, $updateProcessedCountQuery, $updateProcessedCountParams);
        } else {
//             file_put_contents($logFilename, 'IMage DB insert failed'. "\r\n", FILE_APPEND);
            unlink("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/main/{$image['filename']}");
            unlink("../images/temporaryImportFolder/{$importCollectionMetadata['import_collection_id']}/thumbnails/{$image['filename']}");
            $failedImagesToRetry[] = $image;
            failedImageUpdate($importCollectionMetadata['import_collection_id'], count($failedImagesToRetry));
        }
//        $dbInsertTimeStamp = microtime(true);
//        $actionElapsedTime = $dbInsertTimeStamp - $georefTimeStamp;
//        $totalElapsedTime = $dbInsertTimeStamp - $initalTimeStamp;
//        file_put_contents($logFilename, "Row Inserted: $actionElapsedTime : $totalElapsedTime\r\n\n", FILE_APPEND);
    }
    $imageArray = $failedImagesToRetry;
} while (!empty($failedImagesToRetry) && $retryCount <= 2);
// file_put_contents($logFilename, 'Looping complete'. "\r\n", FILE_APPEND);

$updateCountQuery = '
    UPDATE import_collections
    SET failed_images = :failedImageCount,
        import_end_timestamp = :endTimestamp,
        import_status_message = "Complete"
    WHERE import_collection_id = :importCollectionId
    LIMIT 1
';
$updateCountParams = array(
    'failedImageCount' => count($failedImagesToRetry),
    'endTimestamp' => time(),
    'importCollectionId' => $importCollectionMetadata['import_collection_id']
);
run_prepared_query($DBH, $updateCountQuery, $updateCountParams);
// file_put_contents($logFilename, 'Done'. "\r\n", FILE_APPEND);


