<?php

$pageName = "start";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray[] = 'scripts/markerClusterPlus.js';

require 'includes/userFunctions.php';
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;


if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_POST['projectId'])) {
  header('Location: login.php');
  exit;
}

$filtered = TRUE;
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];
$projectId = $_POST['projectId'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
$newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
// Find post image metadata $postImageMetadata
if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
  //  Placeholder for error management
  exit("Image $newRandomImageId not found in Database");
}
$newRandomImageLatitude = $newRandomImageMetadata['latitude'];
$newRandomImageLongitude = $newRandomImageMetadata['longitude'];
$newRandomImageLocation = build_image_location_string($newRandomImageMetadata, TRUE);
$newRandomImageDisplayURL = "images/datasets/{$newRandomImageMetadata['dataset_id']}/main/{$newRandomImageMetadata['filename']}";

require("includes/mapNavigator.php");

$javaScript = "$mapScript";

$jQueryDocumentDotReadyCode = <<<EOL
    $mapDocumentReadyScript
    $('#mapButton').click(function() {
      $('#mapWrapper').fadeToggle(400, function() {
        dynamicSizing();
        google.maps.event.trigger(icMap, "resize");
        icMap.setCenter(icCurrentImageLatLon);
        icMarkersShown = false;
        toggleMarkers();
        icCurrentImageMarker.setMap(icMap);
      });
    });
    $('#randomButton').click(function() {
      window.location.href = "classification.php?projectId=" + icProjectId + "&imageId=" + "$newRandomImageId";
    });
EOL;
