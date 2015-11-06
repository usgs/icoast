<?php

require '../includes/globalFunctions.php';
require '../includes/userFunctions.php';
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$filtered = TRUE;
$projectId = $_GET['projectId'];
$userId = $_GET['userId'];

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
$data['newRandomImageId'] = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
if ($data['newRandomImageId'] == 'allPoolAnnotated' || $data['newRandomImageId'] == 'poolEmpty') {
    $data['newRandomImageId'] = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id']);
}
if ($data['newRandomImageId'] == 'allPoolAnnotated' || $data['newRandomImageId'] == 'poolEmpty' || $data['newRandomImageId'] === FALSE) {
    exit("An error was detected while generating a new image. $newRandomImageId");
}

if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $data['newRandomImageId'], 'image')) {
    //  Placeholder for error management
    exit("Image {$data['newRandomImageId']} not found in Database");
}
$data['newProjectName'] = $projectMetadata['name'];
$data['newRandomImageLatitude'] = $newRandomImageMetadata['latitude'];
$data['newRandomImageLongitude'] = $newRandomImageMetadata['longitude'];
$data['newRandomImageLocation'] = build_image_location_string($newRandomImageMetadata);
$data['newRandomImageDisplayURL'] = "images/collections/{$newRandomImageMetadata['collection_id']}/main/{$newRandomImageMetadata['filename']}";

echo json_encode($data);
