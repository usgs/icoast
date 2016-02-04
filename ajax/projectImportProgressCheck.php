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
    exit;
} else if ($projectMetadata['creator'] != $userData['user_id'] ||
    $projectMetadata['is_complete'] == 1
) {
    exit;
}

function time_formatter($time)
{
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

define('SLEEP_PERIOD', 600);

$statusArray = array(
    'preCollection' => array(),
    'postCollection' => array()
);

$preCollectionQuery = '
    SELECT *
    FROM import_collections
    WHERE parent_project_id = :parentProjectId AND
        collection_type = "pre"
    LIMIT 1';
$preCollectionParams['parentProjectId'] = $projectMetadata['project_id'];
$preCollectionResult = run_prepared_query($DBH, $preCollectionQuery, $preCollectionParams);
$preCollectionMetadata = $preCollectionResult->fetch(PDO::FETCH_ASSOC);

if (empty($preCollectionMetadata)) {
    $existingPreCollectionQuery = '
        SELECT pre_collection_id
        FROM projects
        WHERE project_id = :projectId';
    $existingPreCollectionParams['projectId'] = $projectMetadata['project_id'];
    $existingPreCollectionResult = run_prepared_query($DBH, $existingPreCollectionQuery, $existingPreCollectionParams);
    $existingPreCollectionId = $existingPreCollectionResult->fetchColumn();
    if (!empty($existingPreCollectionId)) {
        $existingCollectionMetadata = retrieve_entity_metadata($DBH, $existingPreCollectionId, 'collection');
        if (!empty($existingCollectionMetadata)) {
            $statusArray['preCollection']['status'] = 'existing';
            $statusArray['preCollection']['name'] = $existingCollectionMetadata['name'];
            $statusArray['preCollection']['existingCollectionName'] = $existingCollectionMetadata['name'];
        } else {
            $existingPreCollectionId = null;
        }
    }
    if (empty($existingPreCollectionId)) {
        $statusArray['preCollection']['status'] = 'missing';
        $statusArray['preCollection']['import_status_message'] = 'Collection not found in database.';
    }
    //
//
//
//
//
//
} else if ($preCollectionMetadata['import_status_message'] == 'Complete') {
    $statusArray['preCollection']['status'] = 'complete';
    $statusArray['preCollection']['name'] = $preCollectionMetadata['name'];
    $startTime = $preCollectionMetadata['import_start_timestamp'];
    $statusArray['preCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $endTime = $preCollectionMetadata['import_end_timestamp'];
    $statusArray['preCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $elapsedTimeInSeconds = $endTime - $startTime;
    $statusArray['preCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['preCollection']['totalImages'] = $preCollectionMetadata['total_images'];
    $statusArray['preCollection']['sucessfulImages'] = $preCollectionMetadata['processed_images'];
    $statusArray['preCollection']['portraitImages'] = $preCollectionMetadata['portrait_images'];
    $statusArray['preCollection']['failedImages'] = $preCollectionMetadata['failed_images'];
    //
//
//
//
//
//
} else if ($preCollectionMetadata['import_status_message'] == 'Processing' &&
    $preCollectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $preCollectionMetadata['total_images'];
    $processedImages = $preCollectionMetadata['processed_images'] + $preCollectionMetadata['portrait_images'];
    $startTime = $preCollectionMetadata['import_start_timestamp'];
    $currentTime = time();

    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;

    $statusArray['preCollection']['status'] = 'processing';
    $statusArray['preCollection']['name'] = $preCollectionMetadata['name'];
    $statusArray['preCollection']['processedImages'] = $processedImages;
    $statusArray['preCollection']['totalImages'] = $totalImages;
    $statusArray['preCollection']['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['preCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['preCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);

    if ($processedImages > 0) {
        if ($preCollectionMetadata['failed_images'] > 0) {
            $statusArray['preCollection']['sleepStatus'] = 1;
            $additionalWaitTime = SLEEP_PERIOD;
        } else {
            $statusArray['preCollection']['sleepStatus'] = 0;
            $additionalWaitTime = 0;
        }
        $lastUpdateTime = $preCollectionMetadata['last_update_timestamp'];
        $processingTimePerImage = ($lastUpdateTime - $startTime) / $processedImages;
        $remainingTimeInSeconds = floor(($remainingImages * $processingTimePerImage) + $additionalWaitTime);
        $statusArray['preCollection']['remainingTime'] = time_formatter($remainingTimeInSeconds);
        $statusArray['preCollection']['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
        $statusArray['preCollection']['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
        $endTime = time() + $remainingTimeInSeconds;
        $statusArray['preCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
        $timeProgressPercentage = floor(($elapsedTimeInSeconds / ($elapsedTimeInSeconds + $remainingTimeInSeconds)) * 100);
        $statusArray['preCollection']['timeProgressPercentage'] = $timeProgressPercentage;
    } else {
        $statusArray['preCollection']['sleepStatus'] = 0;
        $statusArray['preCollection']['remainingTime'] = 'Calculating';
        $statusArray['preCollection']['remainingTimeInMinutes'] = 'Calculating';
        $statusArray['preCollection']['totalTimeInMinutes'] = 'Calculating';
        $statusArray['preCollection']['endTime'] = 'Calculating';
        $statusArray['preCollection']['timeProgressPercentage'] = 0;
    }
    //
//
//
//
//
//
} else if ($preCollectionMetadata['import_status_message'] == 'Sleeping' &&
    $preCollectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $preCollectionMetadata['total_images'];
    $processedImages = $preCollectionMetadata['processed_images'] + $preCollectionMetadata['portrait_images'];
    $startTime = $preCollectionMetadata['import_start_timestamp'];
    $currentTime = time();
    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;
    $lastUpdateTime = $preCollectionMetadata['last_update_timestamp'];
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

    $statusArray['preCollection']['status'] = 'sleeping';
    $statusArray['preCollection']['name'] = $preCollectionMetadata['name'];
    $statusArray['preCollection']['processedImages'] = $processedImages;
    $statusArray['preCollection']['totalImages'] = $totalImages;
    $statusArray['preCollection']['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['preCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['preCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['preCollection']['sleepStatus'] = 1;
    $statusArray['preCollection']['remainingTime'] = time_formatter($remainingTimeInSeconds);
    $statusArray['preCollection']['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
    $statusArray['preCollection']['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
    $statusArray['preCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['preCollection']['timeProgressPercentage'] = $timeProgressPercentage;


    //
//
//
//
//
//
} else if ($preCollectionMetadata['user_abort_import_flag'] == 1 &&
    $preCollectionMetadata['import_status_message'] != 'User Abort Request'
) {
    $statusArray['preCollection']['status'] = 'abortRequested';
    $statusArray['preCollection']['name'] = $preCollectionMetadata['name'];
} else if ($preCollectionMetadata['import_status_message'] == 'User Abort Request') {
    $statusArray['preCollection']['status'] = 'aborted';
    $statusArray['preCollection']['name'] = $preCollectionMetadata['name'];
} else {
    $statusArray['preCollection']['status'] = 'failed';
    $statusArray['preCollection']['import_status_message'] = 'Collection not found in database.';
}

unset($preCollectionMetadata);

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

$postCollectionQuery = '
    SELECT *
    FROM import_collections
    WHERE parent_project_id = :parentProjectId AND
        collection_type = "post"
    LIMIT 1';
$postCollectionParams['parentProjectId'] = $projectMetadata['project_id'];
$postCollectionResult = run_prepared_query($DBH, $postCollectionQuery, $postCollectionParams);
$postCollectionMetadata = $postCollectionResult->fetch(PDO::FETCH_ASSOC);

if (empty($postCollectionMetadata)) {
    $existingPreCollectionQuery = '
        SELECT post_collection_id
        FROM projects
        WHERE project_id = :projectId';
    $existingPreCollectionParams['projectId'] = $projectMetadata['project_id'];
    $existingPreCollectionResult = run_prepared_query($DBH, $existingPreCollectionQuery, $existingPreCollectionParams);
    $existingPreCollectionId = $existingPreCollectionResult->fetchColumn();
    if (!empty($existingPreCollectionId)) {
        $existingCollectionMetadata = retrieve_entity_metadata($DBH, $existingPreCollectionId, 'collection');
        if (!empty($existingCollectionMetadata)) {
            $statusArray['postCollection']['status'] = 'existing';
            $statusArray['postCollection']['name'] = $existingCollectionMetadata['name'];
            $statusArray['postCollection']['existingCollectionName'] = $existingCollectionMetadata['name'];
        } else {
            $existingPreCollectionId = null;
        }
    }
    if (empty($existingPreCollectionId)) {
        $statusArray['postCollection']['status'] = 'missing';
    }
    //
//
//
//
//
//
} else if ($postCollectionMetadata['import_status_message'] == 'Complete') {
    $statusArray['postCollection']['status'] = 'complete';
    $statusArray['postCollection']['name'] = $postCollectionMetadata['name'];
    $startTime = $postCollectionMetadata['import_start_timestamp'];
    $statusArray['postCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $endTime = $postCollectionMetadata['import_end_timestamp'];
    $statusArray['postCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $elapsedTimeInSeconds = $endTime - $startTime;
    $statusArray['postCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['postCollection']['totalImages'] = $postCollectionMetadata['total_images'];
    $statusArray['postCollection']['sucessfulImages'] = $postCollectionMetadata['processed_images'];
    $statusArray['postCollection']['portraitImages'] = $postCollectionMetadata['portrait_images'];
    $statusArray['postCollection']['failedImages'] = $postCollectionMetadata['failed_images'];
    //
//
//
//
//
//
} else if ($postCollectionMetadata['import_status_message'] == 'Processing' &&
    $postCollectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $postCollectionMetadata['total_images'];
    $processedImages = $postCollectionMetadata['processed_images'] + $postCollectionMetadata['portrait_images'];
    $startTime = $postCollectionMetadata['import_start_timestamp'];
    $currentTime = time();

    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;

    $statusArray['postCollection']['status'] = 'processing';
    $statusArray['postCollection']['name'] = $postCollectionMetadata['name'];
    $statusArray['postCollection']['processedImages'] = $processedImages;
    $statusArray['postCollection']['totalImages'] = $totalImages;
    $statusArray['postCollection']['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['postCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['postCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);

    if ($processedImages > 0) {
        if ($postCollectionMetadata['failed_images'] > 0) {
            $statusArray['postCollection']['sleepStatus'] = 1;
            $additionalWaitTime = SLEEP_PERIOD;
        } else {
            $statusArray['postCollection']['sleepStatus'] = 0;
            $additionalWaitTime = 0;
        }
        $lastUpdateTime = $postCollectionMetadata['last_update_timestamp'];
        $processingTimePerImage = ($lastUpdateTime - $startTime) / $processedImages;
        $remainingTimeInSeconds = floor(($remainingImages * $processingTimePerImage) + $additionalWaitTime);
        $statusArray['postCollection']['remainingTime'] = time_formatter($remainingTimeInSeconds);
        $statusArray['postCollection']['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
        $statusArray['postCollection']['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
        $endTime = time() + $remainingTimeInSeconds;
        $statusArray['postCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
        $timeProgressPercentage = floor(($elapsedTimeInSeconds / ($elapsedTimeInSeconds + $remainingTimeInSeconds)) * 100);
        $statusArray['postCollection']['timeProgressPercentage'] = $timeProgressPercentage;
    } else {
        $statusArray['postCollection']['sleepStatus'] = 0;
        $statusArray['postCollection']['remainingTime'] = 'Calculating';
        $statusArray['postCollection']['totalTimeInMinutes'] = 'Calculating';
        $statusArray['postCollection']['remainingTimeInMinutes'] = 'Calculating';
        $statusArray['postCollection']['endTime'] = 'Calculating';
        $statusArray['postCollection']['timeProgressPercentage'] = 0;
    }
    //
//
//
//
//
//
} else if ($postCollectionMetadata['import_status_message'] == 'Sleeping' &&
    $postCollectionMetadata['user_abort_import_flag'] == 0
) {
    $totalImages = $postCollectionMetadata['total_images'];
    $processedImages = $postCollectionMetadata['processed_images'] + $postCollectionMetadata['portrait_images'];
    $startTime = $postCollectionMetadata['import_start_timestamp'];
    $currentTime = time();

    $remainingImages = $totalImages - $processedImages;
    $elapsedTimeInSeconds = $currentTime - $startTime;

    $lastUpdateTime = $postCollectionMetadata['last_update_timestamp'];
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

    $statusArray['postCollection']['status'] = 'sleeping';
    $statusArray['postCollection']['name'] = $postCollectionMetadata['name'];
    $statusArray['postCollection']['processedImages'] = $processedImages;
    $statusArray['postCollection']['totalImages'] = $totalImages;
    $statusArray['postCollection']['imageProgressPercentage'] = floor(($processedImages / $totalImages) * 100);
    $statusArray['postCollection']['elapsedTime'] = time_formatter($elapsedTimeInSeconds);
    $statusArray['postCollection']['startTime'] = formattedTime($startTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['postCollection']['sleepStatus'] = 1;
    $statusArray['postCollection']['remainingTime'] = time_formatter($remainingTimeInSeconds);
    $statusArray['postCollection']['remainingTimeInMinutes'] = ceil($remainingTimeInSeconds / 60);
    $statusArray['postCollection']['totalTimeInMinutes'] = ceil(($elapsedTimeInSeconds + $remainingTimeInSeconds) / 60);
    $statusArray['postCollection']['endTime'] = formattedTime($endTime, $userData['time_zone'], TRUE, TRUE);
    $statusArray['postCollection']['timeProgressPercentage'] = $timeProgressPercentage;
} else if ($postCollectionMetadata['user_abort_import_flag'] == 1 &&
    $postCollectionMetadata['import_status_message'] != 'User Abort Request'
) {
    $statusArray['postCollection']['status'] = 'abortRequested';
    $statusArray['postCollection']['name'] = $postCollectionMetadata['name'];
} else if ($postCollectionMetadata['import_status_message'] == 'User Abort Request') {
    $statusArray['postCollection']['status'] = 'aborted';
    $statusArray['postCollection']['name'] = $postCollectionMetadata['name'];
} else {
    $statusArray['postCollection']['status'] = 'failed';
    $statusArray['postCollection']['import_status_message'] = 'Collection not found in database.';
}


$jsonData = json_encode($statusArray);
print $jsonData;
