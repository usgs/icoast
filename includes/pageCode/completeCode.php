<?php

$pageName = "complete";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray[] = '//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js';
$javaScriptLinkArray[] = 'scripts/markerClusterPlus.js';

require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_GET['projectId']) || !isset($_GET['imageId'])) {
  header('Location: login.php');
  exit;
}

$filtered = TRUE;
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];
$projectId = $_GET['projectId'];
$postImageId = $_GET['imageId'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

if (!$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project')) {
  //  Placeholder for error management
  exit("Project $projectId not found in Database");
}
if (!$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image')) {
  //  Placeholder for error management
  exit("Image $postImageId not found in Database");
}
$projectName = $projectMetadata['name'];
$postDisplayImageURL = "images/datasets/{$postImageMetadata['dataset_id']}/main/{$postImageMetadata['filename']}";
$postImageLocation = build_image_location_string($postImageMetadata);

//--------------------------------------------------------------------------------------------------
// Determine total number of user annotations in iCoast and update user metadata is needed.
$annotationCountQuery = "SELECT COUNT(*) FROM annotations WHERE user_id = :userId AND"
        . " annotation_completed = 1";
$annotationCountParams['userId'] = $userId;
$STH = run_prepared_query($DBH, $annotationCountQuery, $annotationCountParams);
$numberOfAnnotations = $STH->fetchColumn();
if ($numberOfAnnotations == 0) {
    header('Location: welcome.php');
    exit;
}
if ($numberOfAnnotations != $userData['completed_annotation_count']) {
  $setAnnotationCountQuery = "UPDATE users SET completed_annotation_count = :numberOfAnnotations WHERE user_id = :userId";
  $setAnnotationCountParams = array(
      'userId' => $userId,
      'numberOfAnnotations' => $numberOfAnnotations,
  );
  $STH = run_prepared_query($DBH, $setAnnotationCountQuery, $setAnnotationCountParams);
  if ($STH->rowCount() == 0) {
    //  Placeholder for error management
    print 'User Annotation Count Update Error: Update did not complete sucessfully.';
    exit;
  }
}
$ordinalNumberOfAnnotations = ordinal_suffix($numberOfAnnotations);


$positionQuery = "SELECT completed_annotation_count FROM users WHERE completed_annotation_count > :numberOfAnnotations "
        . "ORDER BY completed_annotation_count DESC";
$positionParams['numberOfAnnotations'] = $numberOfAnnotations;
$STH = run_prepared_query($DBH, $positionQuery, $positionParams);
$annotaionPositions = $STH->fetchAll(PDO::FETCH_ASSOC);
$positionInICoast = count($annotaionPositions) + 1;
$ordinalPositionInICoast = ordinal_suffix($positionInICoast) . ' Place';

$jointPosition = FALSE;
$jointQuery = "SELECT COUNT(*) FROM users WHERE completed_annotation_count = $numberOfAnnotations";
$jointParams['numberOfAnnotations'] = $numberOfAnnotations;
$STH = run_prepared_query($DBH, $jointQuery, $jointParams);
if ($STH->fetchColumn() > 1) {
  $jointPosition = TRUE;
}

if ($jointPosition) {
  $ordinalPositionInICoast = "Joint " . $ordinalPositionInICoast;
} elseif ($positionInICoast == 1) {
  $ordinalPositionInICoast .= " - Top iCoast Tagger!";
}

$annotationsToNextHTML = "";
if ($positionInICoast > 1 || $jointPosition) {
  $annotationsToFirst = $annotaionPositions[0]['completed_annotation_count'] - $numberOfAnnotations + 1;
  $nextPosition = ordinal_suffix($positionInICoast - 1);
$annotationsToNextHTML = "<tr><td class=\"rowTitle\"># of Photos to Reach 1st Place:</td><td class=\"userData\">$annotationsToFirst</td></tr>";
}





//--------------------------------------------------------------------------------------------------
// Retreive annotation data for the last annotated image.
$lastAnnotationQuery = "SELECT * FROM annotations WHERE user_id = :userId AND "
        . "project_id = :projectId AND image_id = :postImageId";
$$lastAnnotationParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$STH = run_prepared_query($DBH, $lastAnnotationQuery, $$lastAnnotationParams);
$lastAnnotation = $STH->fetch(PDO::FETCH_ASSOC);
$annotationId = $lastAnnotation['annotation_id'];
$startDateTime = new DateTime($lastAnnotation['initial_session_start_time']);
$endDateTime = new DateTime($lastAnnotation['initial_session_end_time']);
$annotationInterval = $startDateTime->diff($endDateTime);
$lastAnnotationTime = $annotationInterval->format("%i min(s) %s sec(s)");

$tagCountQuery = "SELECT COUNT(*) FROM annotation_selections WHERE annotation_id = :annotationId";
$tagCountParams['annotationId'] = $annotationId;
$STH = run_prepared_query($DBH, $tagCountQuery, $tagCountParams);
$tagCount = $STH->fetchColumn();



// Determine the new Random Image for next annotation.
$newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
  //  Placeholder for error management
  exit("New random Image $postImageId not found in Database");
}
$newRandomImageLatitude = $newRandomImageMetadata['latitude'];
$newRandomImageLongitude = $newRandomImageMetadata['longitude'];
$newRandomImageDisplayURL = "images/datasets/{$newRandomImageMetadata['dataset_id']}/main/{$newRandomImageMetadata['filename']}";



//--------------------------------------------------------------------------------------------------
// Find image id's of next and previous post images
$postImageArray = find_adjacent_images($DBH, $postImageId, $projectId);
$previousImageId = $postImageArray[0]['image_id'];
$nextImageId = $postImageArray[2]['image_id'];


//--------------------------------------------------------------------------------------------------
// Build next/previous post image buttons HTML


if ($previousImageId != 0) {
  $leftCoastalNavigationButtonHTML = '<button class="clickableButton" type="button" title="Click to show the next POST-storm Photo along the LEFT of the coast." id="leftButton"><img src="images/system/leftArrow2.png" alt="Image of a left facing arrow. Used to navigate left along the coast" height="128" width="83"></button>';
} else {
  $leftCoastalNavigationButtonHTML = '<button class="clickableButton disabledClickableButton" type="button" title="No more images in the dataset in this direction. Use the Map to move along the coat to the next dataset."><img src="images/system/leftArrow2.png" alt="Image of a faded left facing arrow. Used to indicate there are no more images to the left of the last annotated image." height="128" width="83"></button>';
}
if ($nextImageId != 0) {
  $rightCoastalNavigationButtonHTML = '<button class="clickableButton" type="button" title="Click to show the next POST-storm Photo along the RIGHT of the coast." id="rightButton"><img src="images/system/rightArrow2.png" alt="Image of a right facing arrow. Used to navigate right along the coast"height="128" width="83"></button>';
} else {
  $rightCoastalNavigationButtonHTML = '<button class="clickableButton disabledClickableButton" type="button" title="No more images in the dataset in this direction. Use the Map to move along the coat to the next dataset."><img src="images/system/rightArrow2.png" alt="Image of a faded right facing arrow. Used to indicate there are no more images to the right of the last annotated image." height="128" width="83"></button>';
}

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
    $('#leftButton').click(function() {
        window.location.href = "classification.php?projectId=" + icProjectId + "&imageId=" + "$previousImageId";
    });
    $('#rightButton').click(function() {
        window.location.href = "classification.php?projectId=" + icProjectId + "&imageId=" + "$nextImageId";
    });
EOL;
