<?php

require_once('../includes/globalFunctions.php');
require_once('../includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$imageId = $_GET['imageId'];
$userId = $_GET['userId'];
if (isset($_GET['projectId'])) {
    $projectId = $_GET['projectId'];
} else {
    $projectId = null;
}

$adjacentImageArray = find_adjacent_images($DBH, $imageId, $projectId, $userId, 1, 20);
$previousImageId = $adjacentImageArray[0]['image_id'];
if ($previousImageId != 0 && !$previousImageMetadata = retrieve_entity_metadata($DBH, $previousImageId, 'image')) {
    //  Placeholder for error management
    exit("Previous Image $previousImageId not found in Database");
} else if ($previousImageId == 0) {
    $returnData['left']['newRandomImageId'] = 0;
} else {
    $returnData['left']['newRandomImageId'] = $previousImageMetadata['image_id'];
    $returnData['left']['newRandomImageLatitude'] = $previousImageMetadata['latitude'];
    $returnData['left']['newRandomImageLongitude'] = $previousImageMetadata['longitude'];
    $returnData['left']['newRandomImageDisplayURL'] = "images/collections/{$previousImageMetadata['collection_id']}/main/{$previousImageMetadata['filename']}";
    $returnData['left']['newRandomImageLocation'] = build_image_location_string($previousImageMetadata);
}

$nextImageId = $adjacentImageArray[2]['image_id'];
if ($nextImageId != 0 && !$nextImageMetadata = retrieve_entity_metadata($DBH, $nextImageId, 'image')) {
    //  Placeholder for error management
    exit("Previous Image $nextImageId not found in Database");
} else if ($nextImageId == 0) {
    $returnData['right']['newRandomImageId'] = 0;
} else {
    $returnData['right']['newRandomImageId'] = $nextImageMetadata['image_id'];
    $returnData['right']['newRandomImageLatitude'] = $nextImageMetadata['latitude'];
    $returnData['right']['newRandomImageLongitude'] = $nextImageMetadata['longitude'];
    $returnData['right']['newRandomImageDisplayURL'] = "images/collections/{$nextImageMetadata['collection_id']}/main/{$nextImageMetadata['filename']}";
    $returnData['right']['newRandomImageLocation'] = build_image_location_string($nextImageMetadata);
}
echo json_encode($returnData);

