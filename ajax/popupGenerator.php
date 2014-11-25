<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

if (isset($_GET['photoId'])) {
    settype($_GET['photoId'], 'integer');
    if (!empty($_GET['photoId'])) {
        $photoMetadata = retrieve_entity_metadata($DBH, $_GET['photoId'], 'image');
    }
}

if (!$photoMetadata) {
    exit;
}
$returnImageData = array (
    'location' => build_image_location_string($photoMetadata, TRUE),
    'thumbnailURL' => $photoMetadata['thumb_url']
);
print json_encode($returnImageData);
