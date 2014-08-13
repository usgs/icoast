<?php

// Define required files and initial includes
require_once("includes/globalFunctions.php");

// -------------------------------------------------------------------------------------------------
/**
 * Function to build list boxes in the imageProcessor.php file.
 *
 * @param bool $is_dataset Set to TRUE if building a list of datasets. FALSE if building a list of
 * collections.
 */
function build_list_box($is_dataset) {
  if ($is_dataset) {
    $selectQuery = "SELECT dataset_id, name from datasets ORDER BY name";
    $selectResults = run_database_query($selectQuery);
    if ($selectResults->num_rows <= 20) {
      print "<select name=\"dataset\" size=\"$selectResults->num_rows\">\n";
    } else {
      print "<select name=\"dataset\" size=\"20\">\n";
    }
    print "<option selected value=\"0\">N/A</option>\n";
    while ($queryRow = $selectResults->fetch_assoc()) {
      print "<option value=\"{$queryRow['dataset_id']}\">{$queryRow['name']}</option>\n";
    }
    print "</select>\n";
  } else {
    $selectQuery = "SELECT collection_id, name from collections ORDER BY name";
    $selectResults = run_database_query($selectQuery);
    if ($selectResults->num_rows <= 20) {
      print "<select name=\"collection\" size=\"$selectResults->num_rows\">\n";
    } else {
      print "<select name=\"collection\" size=\"20\">\n";
    }
    print "<option selected value=\"0\">N/A</option>\n";
    while ($queryRow = $selectResults->fetch_assoc()) {
      print "<option value=\"{$queryRow['collection_id']
          }\">{$queryRow['name']
          }</option>\n";
    }
    print "</select>\n";
  }
}

// -------------------------------------------------------------------------------------------------
/**
 * Builds image list and calls the image_resizer function.
 *
 * This function collates the required image list and image information and then calls the
 * image resizer function with the data as arguments to create resized copies of the images.
 *
 * @param int $datasetId The databse row id of a dataset to process.
 * @param type $collectionId The databse row id of a collection to process.
 */
function process_images($datasetId, $collectionId) {
  require_once("includes/adminFunctions.php");
  if ($collectionId > 0) {
    // If the user selected a collection then find all datasets in the specified collection
    $datasets = find_datasets_in_collection($collectionId);
  } elseif ($datasetId > 0) {
    // If user specified a dataset then add that id to an array.
    $datasets = array($datasetId);
  }
  // Retrieve all images in the given datasets
  if ($datasets) {
    $imageIds = retrieve_image_id_pool($datasets, FALSE, FALSE);
  }
  // Retrieve full metadata for all images in the given pool.
  if ($imageIds) {
    $images = retrieve_entity_metadata($imageIds, 'image');
  }
  /* Development code to select only a few images
    $imagesCount = count($images);
    for ($i = 0; $i < 10; $i++) {
    $randomNumbers[] = rand(1, $imagesCount);
    }
    $tempImages = array();
    foreach ($randomNumbers as $imagesIndex) {
    $imagesIndex -= 1;
    $tempImages[] = $images[$imagesIndex];
    }
    $images = $tempImages;
    print '<pre>';
    print_r($images);
    print '<pre>'; */
  // Resize the images
  image_resizer($images);
}
?>
