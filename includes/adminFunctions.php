<?php

require_once('includes/globalFunctions.php');

// -------------------------------------------------------------------------------------------------
/**
 * Function to find the project_id of all projects a user has admin control of.
 *
 * This function accepts the user name of a user and checks the project_administrators table of the iCoast DB
 * to see if this user has been granted admin rights to any projects. Results are returned in the form of a
 * single dimension indexed array where each value is the id of an administered project. An empty array is
 * returned if no projects are administered by the user.
 *
 * @param object $DBH A PDO object representing a database connection.
 * @param int $userId Database ID of the user to be queried.
 * @return array Returns a single dimension indexed array. Each value is the database project ID of an
 *      administered project. Empty array if no projects found.
 */
function find_administered_projects($DBH, $userId) {
    $userAdministeredProjects = array();
    $userAdministeredProjectsQuery = "SELECT project_id FROM project_administrators WHERE user_id = :userId";
    $userAdministeredProjectsParams['userId'] = $userId;
    $userAdministeredProjectsResult = run_prepared_query($DBH, $userAdministeredProjectsQuery, $userAdministeredProjectsParams);
    while ($row = $userAdministeredProjectsResult->fetch(PDO::FETCH_ASSOC)) {
        $userAdministeredProjects[] = $row['project_id'];
    }
    return $userAdministeredProjects;
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to find a match for all post event images in the specified collections and add results
 * to the matches table in the iCoast DB.
 *
 * This function accepts the database id's of both pre and post event image collections and then
 * searches the pre image collection for any images that match the location of each post image
 * using a distance specified in meters in the constant 'MAX_MATCH_RADIUS'. Only one match (closest
 * is found per post image. All results are inserted into the 'matches' table in the iCoast DB. If
 * an entry for the post image already exists it will be updated with the new result.
 *
 * @todo Refine the match query to restrict the rows searched per query (lat/lon range).
 * @param int $postCollectionId Database ID of the post event collection
 * @param int $preCollectionId Database ID of the post event collection
 * @return boolean Returns TRUE on successful completion <b>OR</b><br>
 * Returns FALSE on failure
 */
function image_match($postCollectionId, $preCollectionId) {

    // Define PHP settings, constants, and variables
    ini_set('max_execution_time', 600);
    /**
     * Maximum Radius in meters for which the image match function should search for a match.
     */
    DEFINE('MAX_MATCH_RADIUS', 400);
    $imageMatchArray = array();

    /* Gather the data to process. Includes an array of all post event images containing complete
     * metadata ($postImageSet) and a string containing all dataset ID's of the pre collection
     * datasets for use in the match query string */
    if (is_numeric($postCollectionId) AND (is_numeric($preCollectionId))) {
        // Find datasets included in the post-event collection.
        $postCollectionDatasets = find_datasets_in_collection($postCollectionId);
        if ($postCollectionDatasets) {
            // Query the image table for id's of images in the specified post-event collection/datasets.
            $postImageIds = retrieve_image_id_pool($postCollectionDatasets, FALSE, FALSE);
        }
        if ($postImageIds) {
            // Retrieve the metadata for the supplied post image id list.
            $postImageSet = retrieve_entity_metadata($postImageIds, 'image');
        }
        // Find datasets included in the pre-event collection.
        $preCollectionDatasets = find_datasets_in_collection($preCollectionId);
        if ($preCollectionDatasets) {
            //  Make pre dataset array into a string to query against.
            $preCollectionDatasets = where_in_string_builder($preCollectionDatasets);
        }

        /* Search for the pre-event image closest to each post-event image location. Add result to
         * $imageMatchArray. */
        if ($postImageSet AND $preCollectionDatasets) {
            foreach ($postImageSet as $imageId) {
                $imagesSelectQuery = "SELECT image_id, is_globally_disabled, has_display_file,
        (6378137 * acos(cos(radians({$imageId['latitude']})) * cos(radians(latitude))
		* cos(radians(longitude) - radians({$imageId['longitude']}) ) + sin(radians({$imageId['latitude']}))
		    * sin(radians(latitude)))) AS distance FROM images
		    WHERE dataset_id IN $preCollectionDatasets HAVING distance < " . MAX_MATCH_RADIUS .
                        " ORDER BY distance LIMIT 1;";
                $imagesSelectQueryResults = run_database_query($imagesSelectQuery);
                if ($imagesSelectQueryResults) {
                    // If a match is found add it to the $imageMatchArray else use  0 as the matching
                    // pre-image id and set disabled and displayFile flags accordingly. Add to $imageMatchArray.
                    if ($imagesSelectQueryResults->num_rows > 0) {
                        $match = $imagesSelectQueryResults->fetch_assoc();
                        $imageMatchArray[] = array(
                            'postCollectionId' => $postCollectionId,
                            'postImageId' => $imageId['image_id'],
                            'postImageDisabled' => $imageId['is_globally_disabled'],
                            'postImageHasDisplayFile' => $imageId['has_display_file'],
                            'preCollectionId' => $preCollectionId,
                            'preImageId' => $match['image_id'],
                            'preImageDisabled' => $match['is_globally_disabled'],
                            'preImageHasDisplayFile' => $match['has_display_file']);
                    } else {
                        $imageMatchArray[] = array(
                            'postCollectionId' => $postCollectionId,
                            'postImageId' => $imageId['image_id'],
                            'postImageDisabled' => $imageId['is_globally_disabled'],
                            'postImageHasDisplayFile' => $imageId['has_display_file'],
                            'preCollectionId' => $preCollectionId,
                            'preImageId' => 0,
                            'preImageDisabled' => 1,
                            'preImageHasDisplayFile' => 0);
                    }
                }
            }

            /* Add image matches to the matches table by looping though $imageMatchArray and building
             * a single SQL query. If combination of postCollectionId, postImageId, and preCollectionId
             * already exists (ON_DUPLICATE_KEY UPDATE) in the DB then just update the preImageId with
             * the new match and set the enabled flag. */
            If (count($imageMatchArray) > 0) {
                $matchesInsertQuery = "INSERT INTO matches (post_collection_id, post_image_id,
	    pre_collection_id, pre_image_id, is_enabled, is_automated_match, user_match_count) VALUES ";
                foreach ($imageMatchArray as $row) {
                    // Determine if the match should be enabled by checking if a match was found and if both
                    // images are not globally disabled and have display files.
                    if ($row['preImageId'] > 0 AND
                            $row['postImageDisabled'] == 0 AND
                            $row['preImageDisabled'] == 0 AND
                            $row['postImageHasDisplayFile'] == 1 AND
                            $row['postImageHasDisplayFile'] == 1) {
                        $isEnabled = 1;
                    } else {
                        $isEnabled = 0;
                    }
                    $matchesInsertQuery .= "(" . $row['postCollectionId'];
                    $matchesInsertQuery .= ", " . $row['postImageId'];
                    $matchesInsertQuery .= ", " . $row['preCollectionId'];
                    $matchesInsertQuery .= ", " . $row['preImageId'] . ",$isEnabled,1,0),";
                }
                $matchesInsertQuery = substr_replace($matchesInsertQuery, "", -1);
                $matchesInsertQuery .= " ON DUPLICATE KEY UPDATE pre_image_id = VALUES(pre_image_id),
      is_enabled = VALUES(is_enabled)";
                // print "RETURNING: Result of insert query (true or false)";
                return run_database_query($matchesInsertQuery);
            } else {
                return TRUE;
            }
        }
    }
    //print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Convert a length in meters to degrees longitude and latitude.
 *
 * Function to both convert/return a given fixed length in meters into the variably sized units of
 * longitude and latitude and return information additional about the conversion based in a given
 * latitude. Calculation can be explained and formnula seen at<br>
 * {@link http://en.wikipedia.org/wiki/Longitude#Length_of_a_degree_of_longitude Wikipedia
 * Longitude}<br>{@link http://en.wikipedia.org/wiki/Latitude#The_length_of_a_degree_of_latitude
 * Wikipedia Latitude}.
 *
 * @param int|double|decimal $latitudeInDegrees Latitude of the position on the earth.
 * @param int|double|decimal $distanceRequiredInMeters Optional. Default = 0. The fixed length in
 * meters to convert.
 * @return array|bool Returns a 1D associative array where element keys are:
 * - distInLat Distance in meters converted to degrees latitude.
 * - distInLon Distance in meters converted to degrees longitude.
 * - mtrsIn1DegLat The number of meters in one degree of latitude at the given latitude.
 * - mtrsIn1DegLon The number of meters in one degree of longitude at the given latitude.
 * - oneMtrInLat The distance in degrees latitude of one meter at the given latitude.
 * - oneMtrInLon The distance in degrees longitude of one meter at the given latitude. <br>
 * and values are numeric values. <b>OR</b><br>
 * On failure returns boolean FALSE.
 */
function meters_to_degrees($latitudeInDegrees, $distanceRequiredInMeters = 0) {

    if (is_numeric($latitudeInDegrees) && is_numeric($distanceRequiredInMeters) AND
            $latitudeInDegrees <= 90 AND $latitudeInDegrees >= -90) {
        // Longitude Calculations
        Define('A', 6378137); // Equitorial radius of earth in meters.
        Define('E_POW_2', 0.0066943800042608); // Eccentricity of Earth (an ellipsoid) raised to the
        // power of 2, using equitorial and polar radii of 6378137 meters and 6356752.3142 meters
        // respectively as per WGS84 Datum specifications.
        $latitudeInRadians = $latitudeInDegrees * pi() / 180; // Convert degrees to radians
        $metersInOneDegreeLongitude = (pi() * A * cos($latitudeInRadians)) /
                (180 * pow(1 - E_POW_2 * pow(sin($latitudeInRadians), 2), 0.5));
        $oneMeterInLongitude = 1 / $metersInOneDegreeLongitude;
        $longitudeForDistance = $oneMeterInLongitude * $distanceRequiredInMeters;
        // Latitude Calculations
        $metersInOneDegreeLatitude = 111132.954 - 559.822 * cos(2 * $latitudeInRadians) +
                1.175 * cos(4 * $latitudeInRadians);
        $oneMeterInLatitude = 1 / $metersInOneDegreeLatitude;
        $latitudeForDistance = $oneMeterInLatitude * $distanceRequiredInMeters;
        // Build return array
        $returnArray = array(
            'distInLat' => $latitudeForDistance,
            'distInLon' => $longitudeForDistance,
            'mtrsIn1DegLat' => $metersInOneDegreeLatitude,
            'mtrsIn1DegLon' => $metersInOneDegreeLongitude,
            'oneMtrInLat' => $oneMeterInLatitude,
            'oneMtrInLon' => $oneMeterInLongitude);
        /* Debugging Output
          print '<pre>';
          print_r($returnArray);
          print '</pre>'; */
        return $returnArray;
    }
    //print "RETURNING: FALSE";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Downloads and creates resized copies of images.
 *
 * Function that accepts an array of image data and loops through each image downloading it,
 * resizing it and saving it locally. Updates the iCoast database with flags to indicate if the
 * process was successful and if the image is usable.
 *
 * @param array $imagesToResize A 2D array (where 1st dimension element values
 * hold an array for each image, and 2nd dimension element keys have at least 'dataset_id',
 * 'filename', 'full_url', and 'image_id' names with accompanying values from the images table in
 * the iCoast DB.)
 */
function image_resizer($imagesToResize) {
    // Define variables, constants, and PHP settings
    ini_set('max_execution_time', 86400);
    define('RESIZED_WIDTH', 800);
    define('JPEG_QUALITY', 75);
    $logOutput = "";

    if (is_array($imagesToResize)) {
        // Process each image from the users selection ready for front-end display.
        $tryCount = 0; // Count number of resize attempts.
        do {
            $tryCount++; // Increment resize attempt counter.
            if ($tryCount > 1) {
                sleep(600); // Wait 10 minutes between retrys.
            }
            $failedImagesToRetry = array(); // Reset the failed image list.
            foreach ($imagesToResize as $image) {
                /* Development code to force a particular image
                  $singleImage = array(
                  'image_id' => 18765,
                  'filename' => '2011_0831_151955d2.jpg',
                  'full_url' => 'http://coastal.er.usgs.gov/hurricanes/oblique/images/2011/0831/2011_0831_151955d2.jpg',
                  'dataset_id' => 7);
                  print '<pre>';
                  print_r($singleImage);
                  print '<pre>'; */
                // Check array contains the relevant fields and types. If not skip the image.
                if (!isset($image['dataset_id']) && !isset($image['filename']) && !isset($image['full_url']) && !isset($image['$image_id']) && !is_numeric($image['dataset_id']) &&
                        !is_numeric($image['$image_id']) && !is_string($image['filename']) &&
                        !is_string($image['full_url'])) {
                    continue;
                }


                // Check to see if the resized file already exists. If so skip the file and update database.
                if (file_exists("displayImages/{$image['dataset_id']}/{$image['filename']}")) {
                    //print "<b>Display image for {$singleImage['filename']} already created.</b><br>";
                    $imagesUpdateQuery = "UPDATE images SET has_display_file = 1 WHERE image_id = " .
                            $image['image_id'];
                    run_database_query($imagesUpdateQuery);
                    continue;
                }
                // Use PHP GD to download and, resize the image maintaining aspect ratio. Store the new
                // image in the "displayImages" subdirectory within another folder named after the
                // images owning dataset ID.
                if (!$originalImage = imagecreatefromjpeg($image['full_url'])) {
                    // If image can't be opened add it to the failedImage array.
                    //print "<p><b>Error opening {$singleImage['filename']}. Adding to retry list.</b><br>";
                    $failedImagesToRetry[] = $image;
                    continue;
                }
                // Determine old and new dimensions.
                $originalWidth = imagesx($originalImage);
                $originalHeight = imagesy($originalImage);
                $ratio = $originalHeight / $originalWidth;
                $resizedHeight = floor(RESIZED_WIDTH * $ratio);
                // Resize image to new dimensions
                $resizedImage = imagecreatetruecolor(RESIZED_WIDTH, $resizedHeight);
                if (!imagecopyresampled($resizedImage, $originalImage, 0, 0, 0, 0, RESIZED_WIDTH, $resizedHeight, $originalWidth, $originalHeight)) {
                    // If resize faile add the image to the failedImage array.
                    // print "<p><b>Error resizing image {$singleImage['filename']}.
                    // Adding to retry list.</b><br>";
                    $failedImagesToRetry[] = $image;
                    continue;
                }
                // Ensure a folder exists for the dataset. If not create one.
                if (!file_exists("displayImages/{$image['dataset_id']}")) {
                    if (!mkdir("displayImages/{$image['dataset_id']}")) {
                        exit("Unable to create a dataset directory.");
                    }
                }
                // Save the image.
                if (imagejpeg($resizedImage, "displayImages/{$image['dataset_id']}/{$image['filename']}", JPEG_QUALITY)) {
                    // print "{$singleImage['filename']} outputted.<br>";
                    $imagesUpdateQuery = "UPDATE images SET has_display_file = 1 WHERE image_id = " .
                            $image['image_id'];
                    run_database_query($imagesUpdateQuery);

                    // If the image filename ends in either "d2", "d3", or "d4" then flag that the image
                    // should be globally disabled. Karen Morgan has advised these images are images along the
                    // coast and irrelivant to this project.
                    if (strpos($image['filename'], "d2.jpg") OR
                            strpos($image['filename'], "d3.jpg") OR
                            strpos($image['filename'], "d4.jpg")) {
                        // print "<p><b>Disabling {$singleImage['filename']} due to d number</b><br>";
                        $imagesUpdateQuery = "UPDATE images SET is_globally_disabled = 1 WHERE image_id = " .
                                $image['image_id'];
                        run_database_query($imagesUpdateQuery);
                    }
                    // If the image is of portrait orientation the flag that the image should be disabled as
                    // it will be irrelivant for this project.
                    if ($originalWidth < $originalHeight) {
                        //print "<p><b>Disabling {$singleImage['filename']} due to portrait orientation.
                        // <br> {$singleImage['full_url']}<br>Width = $originalWidth. Height = $originalHeight
                        // </b></p>";
                        $imagesUpdateQuery = "UPDATE images SET is_globally_disabled = 1 WHERE image_id = " .
                                $image['image_id'];
                        run_database_query($imagesUpdateQuery);
                    }
                } else {
                    // Save of resized image failed. Add to failedImages array.
                    // print "<p><b>Resized image save failure for {$singleImage['filename']}. Adding to
                    // retry list.</b></p>";
                    $failedImagesToRetry[] = $image;
                }
                // Release the memory used in the resizing process
                imagedestroy($originalImage);
                imagedestroy($resizedImage);
            }
            // Copy the failed images into the images array for retry.
            $imagesToResize = $failedImagesToRetry;
        } while (!empty($failedImagesToRetry) AND $tryCount <= 10);
        // If some images remain in failed state after 10 attempts then flag as disabled and log to
        // file.
        if (!empty($failedImagesToRetry)) {
            $logOutput .= "The following files failed to process.\n";
            foreach ($failedImagesToRetry as $image) {
                $logOutput .= $image['image_id'] . ',' . $image['filename'] . ',' .
                        $image['full_url'] . "\n";
                $imagesUpdateQuery = "UPDATE images SET is_globally_disabled = 1 WHERE image_id = " .
                        $image['image_id'];
                run_database_query($imagesUpdateQuery);
            }
        }
        // Write log file.
        if (!empty($logOutput)) {
            file_put_contents("logs/imageProcessorLog.txt", $logOutput);
        } else {
            file_put_contents("logs/imageProcessorLog.txt", "Image Processing ran sucessfully.");
        }
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to find all projects an admin has rights to.
 *
 * This function accepts a user id and returns the projects that the user has admin permissions to.
 *
 * @param int $postCollectionId Database ID of the post event collection
 * @param int $preCollectionId Database ID of the post event collection
 * @return boolean Returns TRUE on successful completion <b>OR</b><br>
 * Returns FALSE on failure
 */
function admin_access() {

}

?>
