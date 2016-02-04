<?php

function time_formatter($time) {
    $hours = floor($time / 3600);
    $timeRemainder = $time % 3600;
    $minutes = floor($timeRemainder / 60);
    $seconds = $timeRemainder % 60;

    $formattedTimeString = '';
    if ($hours > 1) {
        $formattedTimeString .= "$hours hours ";
    } else if ($hours == 1) {
        $formattedTimeString .= "$hours hour ";
    }

    if ($minutes > 1) {
        $formattedTimeString .= "$minutes minutes ";
    } else if ($minutes == 1) {
        $formattedTimeString .= "$minutes minute ";
    } else if ($hours != 0 && $minutes = 0 && $seconds != 0) {
        $formattedTimeString .= "0 minutes ";
    }

    if ($seconds > 1) {
        $formattedTimeString .= "$seconds seconds ";
    } else if ($seconds == 1) {
        $formattedTimeString .= "$seconds second ";
    }

    return $formattedTimeString;
}

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

$statusArray = array();
define('SLEEP_PERIOD', 600);

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    $statusArray['status'] = 'missing';
    $statusArray['import_status_message'] = 'Collection not found in database.';
} else if ($collectionMetadata['creator'] != $userData['user_id']) {
    exit;
}


if ($collectionMetadata['import_status_message'] == 'Complete') {
    $statusArray['status'] = 'complete';
    $statusArray['name'] = $collectionMetadata['name'];
    $startTime = $collectionMetadata['import_start_timestamp'];
    $statusArray['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $endTime = $collectionMetadata['import_end_timestamp'];
    $statusArray['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $elapsedTimeInSeconds = $endTime - $startTime;
    $statusArray['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['totalImages'] = $collectionMetadata['total_images'];
    $statusArray['successfulImages'] = $collectionMetadata['processed_images'];
    $statusArray['portraitImages'] = $collectionMetadata['portrait_images'];
    $statusArray['failedImages'] = $collectionMetadata['failed_images'];
//
//
//
//
//
//
} else if ($collectionMetadata['import_status_message'] == 'Processing' &&
    $collectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $collectionMetadata['total_images'];
    $processedImages = $collectionMetadata['processed_images'] + $collectionMetadata['portrait_images'];
    $startTime = $collectionMetadata['import_start_timestamp'];
    $currentTime = time();

    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;

    $statusArray['status'] = 'processing';
    $statusArray['name'] = $collectionMetadata['name'];
    $statusArray['processedImages'] = $processedImages;
    $statusArray['totalImages'] = $totalImages;
    $statusArray['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);

    if ($processedImages > 0) {
        if ($collectionMetadata['failed_images'] > 0) {
            $statusArray['sleepStatus'] = 1;
            $additionalWaitTime = SLEEP_PERIOD;
        } else {
            $statusArray['sleepStatus'] = 0;
            $additionalWaitTime = 0;
        }
        $lastUpdateTime = $collectionMetadata['last_update_timestamp'];
        $processingTimePerImage = ($lastUpdateTime - $startTime) / $processedImages;
        $remainingTimeInSeconds = floor(($remainingImages * $processingTimePerImage) + $additionalWaitTime);
        $statusArray['remainingTime'] = time_formatter($remainingTimeInSeconds);
        $statusArray['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
        $statusArray['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
        $endTime = time() + $remainingTimeInSeconds;
        $statusArray['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
        $timeProgressPercentage = floor(($elapsedTimeInSeconds / ($elapsedTimeInSeconds + $remainingTimeInSeconds)) * 100);
        $statusArray['timeProgressPercentage'] = $timeProgressPercentage;
    } else {
        $statusArray['sleepStatus'] = 0;
        $statusArray['remainingTime'] = 'Calculating';
        $statusArray['remainingTimeInMinutes'] = 'Calculating';
        $statusArray['totalTimeInMinutes'] = 'Calculating';
        $statusArray['endTime'] = 'Calculating';
        $statusArray['timeProgressPercentage'] = 0;
    }
//
//
//
//
//
//
} else if ($collectionMetadata['import_status_message'] == 'Sleeping' &&
    $collectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $collectionMetadata['total_images'];
    $processedImages = $collectionMetadata['processed_images'] + $collectionMetadata['portrait_images'];
    $startTime = $collectionMetadata['import_start_timestamp'];
    $currentTime = time();
    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;
    $lastUpdateTime = $collectionMetadata['last_update_timestamp'];
    $waitTimeElapsed = time() - $lastUpdateTime;
    $additionalWaitTime = SLEEP_PERIOD - $waitTimeElapsed;
    if ($processedImages > 0) {
        $processingTimePerImage = ($lastUpdateTime - $startTime) / $processedImages;
        $remainingTimeInSeconds = floor(($remainingImages * $processingTimePerImage) + $additionalWaitTime);
    } else {
        $remainingTimeInSeconds = $additionalWaitTime;
    }
    $endTime = time() + $remainingTimeInSeconds;
    $timeProgressPercentage = floor(($elapsedTimeInSeconds / ($elapsedTimeInSeconds + $remainingTimeInSeconds)) * 100);

    $statusArray['status'] = 'sleeping';
    $statusArray['name'] = $collectionMetadata['name'];
    $statusArray['processedImages'] = $processedImages;
    $statusArray['totalImages'] = $totalImages;
    $statusArray['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['sleepStatus'] = 1;
    $statusArray['remainingTime'] = time_formatter($remainingTimeInSeconds);
    $statusArray['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
    $statusArray['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
    $statusArray['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['timeProgressPercentage'] = $timeProgressPercentage;
//
//
//
//
//
//
} else if ($collectionMetadata['user_abort_import_flag'] == 1 &&
    $collectionMetadata['import_status_message'] != 'User Abort Request'
) {
    $statusArray['status'] = 'abortRequested';
    $statusArray['name'] = $collectionMetadata['name'];
} else if ($collectionMetadata['import_status_message'] == 'User Abort Request') {
    $statusArray['status'] = 'aborted';
    $statusArray['name'] = $collectionMetadata['name'];
} else {
    $statusArray['status'] = 'failed';
    $statusArray['import_status_message'] = 'Collection not found in database.';
}

unset($collectionMetadata);
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
$jsonData = json_encode($statusArray);
print $jsonData;
