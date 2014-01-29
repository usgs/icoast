<?php

//print "In mapUpdater<br>";
require_once('../../iCoastSecure/DBMSConnection.php');
require_once('../includes/globalFunctions.php');
require_once('../includes/userFunctions.php');

define("IMAGES_PER_MAP", 10);
$northernLimit = (is_numeric($_GET['north']) ? $_GET['north'] : null);
$southernLimit = (is_numeric($_GET['south']) ? $_GET['south'] : null);
$easternLimit = (is_numeric($_GET['east']) ? $_GET['east'] : null);
$westernLimit = (is_numeric($_GET['west']) ? $_GET['west'] : null);
$projectId = (is_numeric($_GET['projectId']) ? $_GET['projectId'] : null);
$userId = (is_numeric($_GET['userId']) ? $_GET['userId'] : null);
$currentImageId = (is_numeric($_GET['currentImageId']) ? $_GET['currentImageId'] : null);
$imagesToDisplay = Array();


$projectData = retrieve_entity_metadata($projectId, 'project');
if ($projectData) {
  $projectDatasets = find_datasets_in_collection($projectData['post_collection_id']);
}
if ($projectDatasets) {
  $idsToQuery = where_in_string_builder($projectDatasets);
}

//$query = "SELECT dataset_id FROM datasets WHERE dataset_id IN $idsToQuery" .
//    "ORDER BY region_id, position_in_region";
//$queryResult = run_database_query($query);
//if ($queryResult->num_rows > 0) {
//  $displayOrder = $queryResult->fetch_all(MYSQL_ASSOC);
//}

$query = "SELECT image_id, filename, latitude, longitude, feature, city, state, position_in_set, dataset_id" .
    " FROM images WHERE (latitude BETWEEN $southernLimit AND $northernLimit) AND " .
    "(longitude BETWEEN $westernLimit AND $easternLimit) AND " .
    "(has_display_file = 1) AND (is_globally_disabled = 0) AND " .
    "(dataset_id IN $idsToQuery) ORDER BY dataset_id, position_in_set";
$queryResult = run_database_query($query);
if ($queryResult->num_rows > 0) {
  while ($imageMatchData = $queryResult->fetch_assoc()) {
    $imagesToDisplay[] = $imageMatchData;
  }
//  $imagesToDisplay = $queryResult->fetch_all(MYSQL_ASSOC);
}
//echo 'Unfiltered images to display<pre>';
//print_r($imagesToDisplay);
//echo '</pre>';
//for ($i = 0; $i < count($imagesToDisplay); $i++) {
//  print $i . ': ' . $imagesToDisplay[$i]['image_id'] . '<br>';
//}


$annotatedImages = array();
$query = "SELECT image_id FROM annotations WHERE user_id = $userId AND project_id = $projectId";
$queryResult = run_database_query($query);
if ($queryResult->num_rows > 0) {
  while ($imageId = $queryResult->fetch_assoc()) {
    $annotatedImages[] = $imageId['image_id'];
  }
}
//echo 'Annotated Images<pre>';
//print_r($annotatedImages);
//echo '</pre>';

$query = "SELECT post_image_id FROM matches WHERE " .
    "post_collection_id = {$projectData['post_collection_id']} AND is_enabled = 0";
$queryResult = run_database_query($query);
if ($queryResult->num_rows > 0) {
  while ($imageMatchData = $queryResult->fetch_assoc()) {
    $noMatchImageList[] = $imageMatchData['post_image_id'];
  }
}
//echo 'Non Matching Images<pre>';
//print_r($noMatchImageList);
//echo '</pre>';
//$count = 0;
for ($i = 0; $i < count($imagesToDisplay); $i++) {

//  echo $count . ": " . $i . ': ' . $imagesToDisplay[$i]['image_id'] . ' ';
  if (in_array($imagesToDisplay[$i]['image_id'], $annotatedImages) ||
      in_array($imagesToDisplay[$i]['image_id'], $noMatchImageList) ||
      $imagesToDisplay[$i]['image_id'] == $currentImageId) {
//    echo '- REMOVING ' . $imagesToDisplay[$i]['image_id'] . '<br>';
    array_splice($imagesToDisplay, $i, 1);
    $i--;
  } else {
//    echo '- NO MATCH<br>';
  }
//  $count++;
}
//echo 'Filtered Images to Display<pre>';
//print_r($imagesToDisplay);
//echo '</pre>';
//echo count($imagesToDisplay) . '<br>';
//$query = "SELECT post_image_id FROM matches WHERE " .
//    "post_collection_id = {$projectData['post_collection_id']} AND is_enabled = 0";
//$queryResult = run_database_query($query);
//if ($queryResult->num_rows > 0) {
//  while ($imageMatchData = $queryResult->fetch_assoc()) {
//    $noMatchImageList[] = $imageMatchData['post_image_id'];
//  }
//}
//echo '<pre>';
//print_r($noMatchImageList);
//echo '</pre>';
//for ($i = 0; $i < count($imagesToDisplay); $i++) {
//  echo $i . ' ' . $imagesToDisplay[$i]['image_id'] . ' ';
//  if (in_array($imagesToDisplay[$i]['image_id'], $noMatchImageList)) {
//    echo 'Removing' . $imagesToDisplay[$i]['image_id'] . '<br>';
//    array_splice($imagesToDisplay, $i, 1);
//  }
//}
////echo '<pre>';
////print_r($imagesToDisplay);
////echo '</pre>';
//$orderedImagesToDisplay = Array();
//foreach ($displayOrder as $dataset) {
//  foreach ($imagesToDisplay as $image) {
//    if ($image['dataset_id'] == $dataset['dataset_id']) {
//      $orderedImagesToDisplay[] = $image;
//    }
//  }
//}
////echo 'Ordered images to display<pre>';
////print_r($orderedImagesToDisplay);
////echo '</pre>';
//
//$finalImagesToDisplay = Array();
//$imagesWithinBoundsCount = count($orderedImagesToDisplay);
//$count = 0;
//
//$imagesPerMarker = ceil($imagesWithinBoundsCount / IMAGES_PER_MAP);
//
//for ($i = 0; $i < $imagesWithinBoundsCount; $i += $imagesPerMarker) {
//  $count++;
//  if ($i + $imagesPerMarker < $imagesWithinBoundsCount) {
//    $midImageId = $i + floor($imagesPerMarker / 2);
//    $finalImagesToDisplay["image$count"] = $orderedImagesToDisplay[$midImageId];
//  }
//}

for ($i = 0; $i < count($imagesToDisplay); $i++) {
  $imagesToDisplay[$i]['image_url'] = "images/datasets/{$imagesToDisplay[$i]['dataset_id']}/main/{$imagesToDisplay[$i]['filename']}";
  $imagesToDisplay[$i]['location_string'] = build_image_location_string($imagesToDisplay[$i]);
//  $imagesToDisplay[$i]['collation_number'] = $imagesPerMarker;
  array_splice($imagesToDisplay[$i], 4, 5);
}

echo json_encode($imagesToDisplay);



