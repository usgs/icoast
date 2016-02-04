<?php

require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

ignore_user_abort(true);
ini_set('memory_limit', '64M');
ini_set('max_execution_time', 3600);

$userId = filter_input(INPUT_POST, 'user', FILTER_VALIDATE_INT);
$checkCode = filter_input(INPUT_POST, 'checkCode');
$collectionId = filter_input(INPUT_POST, 'collectionId', FILTER_VALIDATE_INT);

$userMetadata = retrieve_entity_metadata($DBH, $userId, 'user');
if (empty($userMetadata)) {
    exit;
} else {
    if (empty($checkCode) || ($checkCode && $checkCode != $userMetadata['auth_check_code'])) {
        exit;
    }
}

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    exit;
}

$importStatus = project_creation_stage($collectionMetadata['parent_project_id']);
if ($importStatus != 31 && $importStatus != 36) {
    exit;
}

$projectCreatorQuery = '
    SELECT creator
    FROM projects
    WHERE project_id = :projectId';
$projectCreatorParams['projectId'] = $collectionMetadata['parent_project_id'];
$projectCreatorResult = run_prepared_query($DBH, $projectCreatorQuery, $projectCreatorParams);
$creator = $projectCreatorResult->fetchColumn();
if ($creator != $userMetadata['user_id']) {
    exit;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////        FUNCTIONS       //////////////////////////////////////////////////////
function distance_sort($x, $y)
{
    return $x['distance'] > $y['distance'];
}

function distanceBearingTimeCalculator($fromImage, $toImage, $searchRadiusInMeters = null)
{
// If from and to images are the same then return false.
    if ($fromImage['sortOrder'] == $toImage['sortOrder']) {
        return false;
    }
    if (!is_null($searchRadiusInMeters)) {
// Roughly define latitude/longitude limits that are $searchRadiusInMeters away from $fromImage
// Used as a crude filter to return false images that are clearly beyond $searchRadiusInMeters.
        $searchRadiusInDegreesLongitude = $searchRadiusInMeters * 0.0000128;
        $searchRadiusInDegreesLatitude = $searchRadiusInMeters * 0.00000903;
        $northernLatitudeThreashold = $fromImage['latitude'] + $searchRadiusInDegreesLatitude;
        $southernLatitudeThreashold = $fromImage['latitude'] - $searchRadiusInDegreesLatitude;
        $easternLongitudeThreashold = $fromImage['longitude'] + $searchRadiusInDegreesLongitude;
        $westernLongitudeThreashold = $fromImage['longitude'] - $searchRadiusInDegreesLongitude;
        if ($toImage['latitude'] > $northernLatitudeThreashold ||
            $toImage['latitude'] < $southernLatitudeThreashold ||
            $toImage['longitude'] > $easternLongitudeThreashold ||
            $toImage['longitude'] < $westernLongitudeThreashold
        ) {
            return false;
        }
    }
// Prepare $fromImage values for bearing & distance calulations
    $fromImageLatitudeRadians = deg2rad($fromImage['latitude']);
    $fromImageLongitudeRadians = deg2rad($fromImage['longitude']);
    $fromImageTimestamp = $fromImage['timestamp'];

// Prepare $toImage image values for bearing & distance calulations
    $toImageLatitudeRadians = deg2rad($toImage['latitude']);
    $toImageLongitudeRadians = deg2rad($toImage['longitude']);
    $toImageTimestamp = $toImage['timestamp'];

// Calculate Distance using Spherical Law of Cosines
    $distance = 6378137 * acos(cos($fromImageLatitudeRadians) *
            cos($toImageLatitudeRadians) *
            cos($toImageLongitudeRadians - $fromImageLongitudeRadians) +
            sin($fromImageLatitudeRadians) *
            sin($toImageLatitudeRadians));
// If precision distance calulation is within search radius then calculate the bearing and time values
// and return an array with the results. Id outside search radius then return false.
    if (is_null($searchRadiusInMeters) || $distance < $searchRadiusInMeters) {
        $resultArray['sortOrder'] = $toImage['sortOrder'];
        $resultArray['distance'] = $distance;
        $resultArray['timeDifference'] = $toImageTimestamp - $fromImageTimestamp;
        $bearingCalcInput1 = sin($toImageLongitudeRadians - $fromImageLongitudeRadians) *
            cos($toImageLatitudeRadians);
        $bearingCalcInput2 = cos($fromImageLatitudeRadians) * sin($toImageLatitudeRadians) -
            sin($fromImageLatitudeRadians) * cos($toImageLatitudeRadians) *
            cos($toImageLongitudeRadians - $fromImageLongitudeRadians);
        $intermediateBearingCalculation = rad2deg(atan2($bearingCalcInput1, $bearingCalcInput2));
        $resultArray['bearing'] = ($intermediateBearingCalculation + 360) % 360;
        return $resultArray;
    } else {
        return false;
    }
}

function update_database($importImageId, $importCollectionId, $positionInCollection, $sortOrder, $totalImages)
{
    if (isset($GLOBALS['DBH'])) {
        global $DBH;
    } else {
        return FALSE;
    }

    $updateImageQuery = "
        UPDATE import_images
        SET position_in_collection = $positionInCollection
        WHERE import_image_id = $importImageId
        LIMIT 1";
    $updateImageResult = run_prepared_query($DBH, $updateImageQuery);
    if ($updateImageResult->rowCount() != 1) {
        $resetAllCollectionImagesQuery = "
            UPDATE import_images
            SET position_in_collection = NULL
            WHERE import_collection_id = $importCollectionId";
        run_prepared_query($DBH, $resetAllCollectionImagesQuery);
        exit;
    }
    if ($sortOrder % 20 == 0 || $sortOrder == $totalImages) {
        $progressPercentage = floor(($sortOrder / $totalImages) * 100);
        $updateSequencingProgressQuery = "
            UPDATE import_collections
            SET sequencing_progress = $progressPercentage
            WHERE import_collection_id = $importCollectionId
            LIMIT 1";
        run_prepared_query($DBH, $updateSequencingProgressQuery);
    }
    return ++$positionInCollection;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////        END FUNCTIONS       //////////////////////////////////////////////////
// System constants and variables
define('DEBUG_OUTPUT', false);
define(INITIAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE, 2000);
define(FINAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE, 10000);
define(INITIAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE, 300);
define(FINAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE, 500);
define(MINIMUM_NEXT_IMAGE_DISTANCE, 50);
define(MAX_PREVIOUS_IMAGE_DISTANCE, 1000);
define(MAX_FEATURE_MATCH_RADIUS, 1600); // 1 mile range on features
define(MAX_POPULOUS_MATCH_RADIUS, 9650); // 6 mile range on populated city match
$relationalImageArrayRadius = max(INITIAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE, INITIAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE, FINAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE);

$loadImagesQuery = '
    SELECT import_image_id, latitude, longitude, timestamp, region
    FROM import_images
    WHERE import_collection_id = :collectionId
        AND is_disabled = 0
        ORDER BY location_sort_order ASC';
$loadImagesParams['collectionId'] = $collectionMetadata['import_collection_id'];
$loadImagesResult = run_prepared_query($DBH, $loadImagesQuery, $loadImagesParams);
$imageArray = array();
$i = 0;
while ($image = $loadImagesResult->fetch(PDO::FETCH_ASSOC)) {
    $imageArray[$i] = $image;
    $imageArray[$i]['sortOrder'] = $i;
    $imageArray[$i]['previousImage'] = null;
    $imageArray[$i]['nextImage'] = null;
    $imageArray[$i]['duplicates'] = array();
    $i++;
}
if ($i == 0) {
    exit;
}
$totalImages = count($imageArray);
$lastImageArrayIndex = $totalImages - 1;

$resetImagesParams['importCollectionId'] = $collectionMetadata['import_collection_id'];
$checkIfResetRequiredQuery = '
    SELECT COUNT(*)
    FROM import_images
    WHERE import_collection_id = :importCollectionId
        AND position_in_collection IS NOT NULL';
$checkIfResetRequiredResult = run_prepared_query($DBH, $checkIfResetRequiredQuery, $resetImagesParams);
$rowsToReset = $checkIfResetRequiredResult->fetchColumn();
if ($rowsToReset > 0) {
    $resetAllCollectionImagesQuery = '
        UPDATE import_images
        SET position_in_collection = NULL
        WHERE import_collection_id = :importCollectionId
            AND position_in_collection IS NOT NULL';
    $resetAllCollectionImagesParams['importCollectionId'] = $collectionMetadata['import_collection_id'];
    $resetAllCollectionImagesResult = run_prepared_query($DBH, $resetAllCollectionImagesQuery, $resetImagesParams);
    if ($resetAllCollectionImagesResult->rowCount() != $rowsToReset) {
        exit;
    }
}


$updateSequencingProgressQuery = '
    UPDATE import_collections
    SET sequencing_progress = 0, sequencing_stage = 2
    WHERE import_collection_id = :collectionId
    LIMIT 1';
$updateSequencingProgressParams['collectionId'] = $collectionMetadata['import_collection_id'];
$updateSequencingProgressResult = run_prepared_query($DBH, $updateSequencingProgressQuery, $updateSequencingProgressParams);
if ($updateSequencingProgressResult->rowCount() != 1) {
    exit;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// => Start loop to generate a single line of images along all regions by finding next and previous images

DEBUG_OUTPUT ? print '<h1>Find neighbour</h1>' : false;
$targetImage = 0;
$positionInCollection = 1;
while (true) {
    DEBUG_OUTPUT ? print "<h3>Image $targetImage - {$imageArray[$targetImage]['import_image_id']}</h3><p>" : false;
    if ($targetImage > $lastImageArrayIndex) {
        DEBUG_OUTPUT ? print 'No more images in $imageArray. Breaking from next image search.' : false;
        break;
    }

    if (isset($imageArray[$targetImage]['sequenced']) || isset($imageArray[$targetImage]['disabled'])) {
        DEBUG_OUTPUT ? print "Image $targetImage has already been sequenced or is disabled. Skipping next image " .
            "search" : false;
        $targetImage++;
        continue;
    }

// Define variables used in neighbour calulation
    $relationalImageArray = array();
    $previousImageArray = array();
    $previousImageBearingArray = array();
    $previousImageDistanceArray = array();
    $nextImageShortlist = array();


//////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build an array of data about the relationship to $targetImage of other images within a specified radius of
// $targetImage's location. Includes distance and bearing FROM $targetImage as well as the time difference
// in seconds between them.)
// Loop through all images
    for ($i = 0; $i <= $lastImageArrayIndex; $i++) {
        $relationshipResult = distanceBearingTimeCalculator(
            $imageArray[$targetImage], $imageArray[$i], $relationalImageArrayRadius);
        if ($relationshipResult) {
            $relationalImageArray[$i] = $relationshipResult;
        }
    } // END for ($i = 0; $i <= $lastImageArrayIndex; $i++) Loop to build $relationalImageArray
    if (DEBUG_OUTPUT) {
//            print '<h4>relationalImageArray</h4><pre>';
//            print_r($relationalImageArray);
//            print '</pre>';
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////
// Try to determine the expected direction of the next image
    $previousImageId = $imageArray[$targetImage]['previousImage'];
// If there is a first previous image set the inital loop variables with data already known about that image.
    if (!is_null($previousImageId)) {
        $previousImageRelationshipData['distance'] = $imageArray[$targetImage]['previousImageDistance'];
        $previousImageRelationshipData['bearing'] = ($imageArray[$targetImage]['previousImageBearing'] + 180) % 360;
// Loop through the previous images of $targetImage until total distance behind >=
// MAX_PREVIOUS_IMAGE_DISTANCE
        while ($previousImageRelationshipData['distance'] + array_sum($previousImageDistanceArray) <
            MAX_PREVIOUS_IMAGE_DISTANCE) {
// Build the arrays needed for previous image analysis.
            $previousImageArray[] = $previousImageId;
            $previousImageBearingArray[] = $previousImageRelationshipData['bearing'];
            $previousImageDistanceArray[] = $previousImageRelationshipData['distance'];
            DEBUG_OUTPUT ? print "Adding image $previousImageId details (BEARING: {$previousImageRelationshipData['bearing']}, DISTANCE: {$previousImageRelationshipData['distance']}m) to average values<br/>" : false;

// Set the ID variables ready for the next previous image loop
            $currentImageId = $previousImageId;
            $previousImageId = $imageArray[$previousImageId]['previousImage'];
// If the next previous image has a previous image itself then calulate the loop variables
// otherwise break the search loop.
            if (!is_null($previousImageId)) {
                $previousImageRelationshipData = distanceBearingTimeCalculator($imageArray[$previousImageId], $imageArray[$currentImageId]);
            } else {
                break;
            }
        }
    }


//////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////
// If there is previous image data then interpolate from that the expected direction of the next image and
// then search the relationalImageArray for images in that direction.
    if (count($previousImageArray) > 0) {
// If there is more than 1 previous image then a weighted average (favouring the closest course
// changes) of expected bearing change is calculated, otherwise the bearing from the only prior image
// is used.
        if (count($previousImageBearingArray) > 1) {
            $distanceTotal = 0;
            $weightedCourseChangeTotal = 0;
            $weightTotal = 0;
// Calculate the maximum allowed previous image course change before the image and all prior images must be ignored.
            $maximumAllowedPreviousImageCourseChange = min((0.035 * MAX_PREVIOUS_IMAGE_DISTANCE) + 5, 75);
            for ($i = 0; $i < count($previousImageBearingArray) - 1; $i++) {
                $weightedCourseChange = 0;
                $distanceTotal += $previousImageDistanceArray[$i];
                $weight = pow(MAX_PREVIOUS_IMAGE_DISTANCE - $distanceTotal, 2); // Exponential weighting.
                $weightTotal += $weight;
// Compensate if the two comparing images are on different sides of North (0 degrees)
                if (max($previousImageBearingArray[$i], $previousImageBearingArray[$i + 1]) > 270 &&
                    min($previousImageBearingArray[$i], $previousImageBearingArray[$i + 1]) < 90
                ) {
                    if ($previousImageBearingArray[$i] > 270) {
                        $tempBearing1 = $previousImageBearingArray[$i] - 360;
                        $tempBearing2 = $previousImageBearingArray[$i + 1];
                    } else {
                        $tempBearing1 = $previousImageBearingArray[$i];
                        $tempBearing2 = $previousImageBearingArray[$i + 1] - 360;
                    }
                } else {
                    $tempBearing1 = $previousImageBearingArray[$i];
                    $tempBearing2 = $previousImageBearingArray[$i + 1];
                }
                $courseChange = $tempBearing1 - $tempBearing2;
                if ($courseChange > (-1 * $maximumAllowedPreviousImageCourseChange) &&
                    $courseChange < $maximumAllowedPreviousImageCourseChange
                ) {
                    $weightedCourseChange = $courseChange * $weight;
                    $weightedCourseChangeTotal += $weightedCourseChange;
                    DEBUG_OUTPUT ? print "IMAGE: {$previousImageArray[$i]}, COURSE CHANGE: $courseChange, "
                        . "WEIGHT: $weight, WEIGHT TOTAL: $weightTotal, "
                        . "WEIGHTED COURSE CHANGE: $weightedCourseChange, WEIGHTED COURSE CHANGE TOTAL: $weightedCourseChangeTotal<br>" : false;
                } else {
                    DEBUG_OUTPUT ? print "IGNORED IMAGE: {$previousImageArray[$i]}, COURSE CHANGE: $courseChange. "
                        . "COURSE CHANGE EXCESSIVE. This and all subsequent previous images will be ignored.<br>" : false;
                    break;
                }
            }
            if ($weightTotal > 0) {
                $expectedCourseChange = $weightedCourseChangeTotal / $weightTotal;
                DEBUG_OUTPUT ? print "ExpectedCourseChange = $weightedCourseChangeTotal / $weightTotal = " .
                    "$expectedCourseChange<br/>" : false;
            } else {
                $expectedCourseChange = 0;
                DEBUG_OUTPUT ? print "ExpectedCourseChange = 0 (Weight Total was 0 (no previous images could be used))<br/>" : false;
            }
        } else {
            $expectedCourseChange = 0;
            DEBUG_OUTPUT ? print "ExpectedCourseChange = 0 (Only 1 previous image)<br/>" : false;
        }

// Calculate the unadjusted (the bearing from $targetImage's previous image to $targetImage) and
// adjusted (the bearing from $targetImage's previous image to $targetImage +/- the expected course
// change based in other previous images up to MAX_PREVIOUS_IMAGE_DISTANCE total distance away )
// expected bearing of the next image.
        $previousImageId = $imageArray[$targetImage]['previousImage'];
        $unadjustedBearing = ($imageArray[$targetImage]['previousImageBearing'] + 180) % 360;
        $adjustedBearing = $unadjustedBearing + $expectedCourseChange;
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
        if ($adjustedBearing >= 360) {
            $adjustedBearing = $adjustedBearing - 360;
        } else if ($adjustedBearing < 0) {
            $adjustedBearing = $adjustedBearing + 360;
        }
        DEBUG_OUTPUT ? print "unadjustedBearing = $unadjustedBearing: adjustedBearing = $unadjustedBearing "
            . "+ $expectedCourseChange = $adjustedBearing<br/>" : false;

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin search of relationalImageArray for a shortlist of all images in the estimated direction
// (+/- distance based margin of error). Add results to $nextImageShortlist array.
        foreach ($relationalImageArray as $nearbyImage) {
// Skip the image if it is too far away, has been marked as a duplicate of another image
// (less than MINIMUM_NEXT_IMAGE_DISTANCE from another image), has already been sequenced or is disabled.
            if ($nearbyImage['distance'] > INITIAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE ||
                isset($imageArray[$nearbyImage['sortOrder']]['sequenced']) ||
                isset($imageArray[$nearbyImage['sortOrder']]['disabled']) ||
                isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
            ) {
                continue; // Skip this nearby image.
            }
// If the image is less than MINIMUM_NEXT_IMAGE_DISTANCE and is not already marked as a duplicate
// of another image them mark it as a duplicate of $targetImage and then skip the image.
            if ($nearbyImage['distance'] < MINIMUM_NEXT_IMAGE_DISTANCE &&
                !isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
            ) {
                $imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'] = $targetImage;
                $imageArray[$targetImage]['duplicates'][] = $nearbyImage['sortOrder'];
                continue;
            }

// Allow for unexpected course changes by creating a distance based widening cone in which a next
// image may be found. Cone boundaries are centered on the unadjusted and extended in the
// direction of the adjusted expected course.
// Boundary increases by 3.5 degrees for every 100m of distance between $targetImage and
// $nearbyImage starting at 5 degrees (maximum 75 degree cone).
            $maximumBearingDifference = (0.035 * $nearbyImage['distance']) + 5;
            $maximumBearingDifference > 75 ? $maximumBearingDifference = 75 : $maximumBearingDifference;

// Skew the search cone in the direction of an expected course change.
            if ($expectedCourseChange >= 0) {
                $clockwiseBearingLimit = ceil($adjustedBearing + $maximumBearingDifference);
                $anticlockwiseBearingLimit = floor($unadjustedBearing - $maximumBearingDifference);
            } else {
                $clockwiseBearingLimit = ceil($unadjustedBearing + $maximumBearingDifference);
                $anticlockwiseBearingLimit = floor($adjustedBearing - $maximumBearingDifference);
            }
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
            if ($clockwiseBearingLimit >= 360) {
                $clockwiseBearingLimit = $clockwiseBearingLimit - 360;
            }
            if ($anticlockwiseBearingLimit < 0) {
                $anticlockwiseBearingLimit = $anticlockwiseBearingLimit + 360;
            }
            DEBUG_OUTPUT ? print "Distance: {$nearbyImage['distance']}, Search Cone : $anticlockwiseBearingLimit - $clockwiseBearingLimit<br/>" : false;
// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone.
            if ($clockwiseBearingLimit < $anticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
                if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit ||
                    $nearbyImage['bearing'] <= $clockwiseBearingLimit
                ) {
                    DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                        . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                        . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// To allow a difference between the expected bearing and the nearby image bearing to be
// calculated both must be in the same degree range (if either are beyond 0 degrees
// they must have 360 degrees added.
                    if ($nearbyImage['bearing'] <= $clockwiseBearingLimit) {
                        $tempNearbyImageBearing = $nearbyImage['bearing'] + 360;
                    } else {
                        $tempNearbyImageBearing = $nearbyImage['bearing'];
                    }
                    if ($adjustedBearing <= $clockwiseBearingLimit) {
                        $tempAdjustedBearing = $adjustedBearing + 360;
                    } else {
                        $tempAdjustedBearing = $adjustedBearing;
                    }
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                    $nearbyImage['bearingDifference'] = round(abs($tempNearbyImageBearing - $tempAdjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                    $nextImageShortlist[] = $nearbyImage;
                }
            } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries.
                if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit &&
                    $nearbyImage['bearing'] <= $clockwiseBearingLimit
                ) {
                    DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                        . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                        . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                    $nearbyImage['bearingDifference'] = round(abs($nearbyImage['bearing'] - $adjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                    $nextImageShortlist[] = $nearbyImage;
                }
            }
        } // END ($relationalImageArray as $nearbyImage)
//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => Look through the next image shortlist to find the best next image available using distance,
// => difference in actual bearing from expected bearing and time difference as decision factors.
        if (count($nextImageShortlist) > 0) {
// Clear the nextImage variable;
            $nextImage = null;
            $bearingDifferenceShortlist = array();
            $smallestBearingDifference = null;

// Create a distance sorted version of the nextImageShortlist
            $distanceShortlist = $nextImageShortlist;
            usort($distanceShortlist, distance_sort);
            if (DEBUG_OUTPUT) {
                print "<h4>Distance Shortlist</h4>";
                foreach ($distanceShortlist as $key => $item) {
                    print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
                }
            }
// Determine the maximum distance a bearing difference based image can be from $targetImage
// (1.5 times the distance of the closes image by distance alone)
            $distanceBasedMaxDistance = $distanceShortlist[0]['distance'] * 1.5;

// Create a bearing difference shortlist based on nextImageShortlist where images are grouped with
// others with the same bearing difference and these are then sorted by distance. Array is
// multi-dimensional. First dimension is the bearing difference, second dimension holds image data
// arrays. Limit images to those less than $distanceBasedMaxDistance away from $targetImage
// Create the multidimensional array grouping images with the same bearing difference
            foreach ($nextImageShortlist as $image) {
                if ($image['distance'] < $distanceBasedMaxDistance) {
                    $bearingDifferenceShortlist[$image['bearingDifference']][] = $image;
                }
            }
// Sort the array by key value (bearing difference) ascending (lowest first)
            ksort($bearingDifferenceShortlist, SORT_NUMERIC);
// Sort each set of grouped images by distance. Smallest first. Record the key value of the first
// group returned as this will be the smallest amount ofbearing difference.
            foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $imagesGroupedByCourseChange) {
                if (is_null($smallestBearingDifference)) {
                    $smallestBearingDifference = $maximumBearingDifference;
                }
// Remove any bearing difference groups that are greater than the bearing difference of the
// closest image - 5 degrees.
                if ($maximumBearingDifference <= $distanceShortlist[0]['bearingDifference'] - 5) {
                    usort($imagesGroupedByCourseChange, distance_sort);
                    $bearingDifferenceShortlist[$maximumBearingDifference] = $imagesGroupedByCourseChange;
                } else {
                    unset($bearingDifferenceShortlist[$maximumBearingDifference]);
                }
            }
            if (DEBUG_OUTPUT) {
                print "<h4>Bearing Difference Shortlist</h4>";
                foreach ($bearingDifferenceShortlist as $deviation => $data) {
                    print "$deviation degrees expected course deviation<br>";
                    foreach ($data as $key => $item) {
                        print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
                    }
                }
            }

// If the closest image by distance is also the closest one with the smallest bearing difference
// then set it as the next image. Otherwise if the closest image with the smallest bearing
// difference is less that 1.5 times the distance away of the closest distance based image and
// it has a smaller time difference from $targetImage then choose it over the closest image.
// Default to the closest image if all other conditions fail to find a next image.
            if ($distanceShortlist[0]['sortOrder'] == $bearingDifferenceShortlist[$smallestBearingDifference][0]['sortOrder']) {
                $nextImage = $distanceShortlist[0];
                DEBUG_OUTPUT ? print "<br><b>Distance and Bearing Difference Agree.</b><br>" : false;
            } else {
// If the smallest bearing difference is less than the bearing difference of the closest
// image by distance alone then check those images out for possible better next image option.
//Loop through all of the smaller bearing differences
                foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $groupedImages) {
                    if ($maximumBearingDifference == $distanceShortlist[0]['bearingDifference']) {
                        break;
                    }
// Loop through each image in the bearing difference group. See if time difference is
// less or no more than 60 seconds ahead. If so use it as the next image.
                    for ($i = 0; $i < count($groupedImages); $i++) {
                        if (abs($groupedImages[$i]['timeDifference']) <= (abs($distanceShortlist[0]['timeDifference']) + 60) &&
                            $groupedImages[$i]['sortOrder'] != $distanceShortlist[0]['sortOrder']
                        ) {
                            $nextImage = $groupedImages[$i];
                            DEBUG_OUTPUT ? print "<br><b>Distance is bettered by course difference.</b> "
                                . "Using Course Difference<br>" : false;
                            break 2;
                        }
                    }
                }
            }
// Default to closest image by distance alone if no other image has been selected.
            if (is_null($nextImage)) {
                DEBUG_OUTPUT ? print "<br><b>No agreement between distance and bearing difference arrays and no "
                    . "bearing based image could better the distance based image. Defaulting to closest image."
                    . "</b><br>" : false;
                $nextImage = $distanceShortlist[0];
            }

//////////////////////////////////////////////////////////////////////////////////////////////////
// => Set the next and previous image details in $targetImage and $nextImage respectivley.
            $imageArray[$targetImage]['nextImage'] = $nextImage['sortOrder'];
            $imageArray[$targetImage]['nextImageDistance'] = $nextImage['distance'];
            $imageArray[$targetImage]['nextImageBearing'] = $nextImage['bearing'];
            $imageArray[$targetImage]['sequenced'] = true;
            $imageArray[$nextImage['sortOrder']]['previousImage'] = $targetImage;
            $imageArray[$nextImage['sortOrder']]['previousImageDistance'] = $nextImage['distance'];
            $imageArray[$nextImage['sortOrder']]['previousImageBearing'] = ($nextImage['bearing'] + 180) % 360;

            $positionInCollection = update_database(
                $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);
// Determine the boundaries for the 180 degree search cone that will disable all images between
// $targetImage and $nextImage
            $disableImageClockwiseBearingLimit = $nextImage['bearing'] + 90;
            $disableImageAnticlockwiseBearingLimit = $nextImage['bearing'] - 90;
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
            if ($disableImageClockwiseBearingLimit >= 360) {
                $disableImageClockwiseBearingLimit = $disableImageClockwiseBearingLimit - 360;
            }
            if ($disableImageAnticlockwiseBearingLimit < 0) {
                $disableImageAnticlockwiseBearingLimit = $disableImageAnticlockwiseBearingLimit + 360;
            }
            foreach ($relationalImageArray as $nearbyImage) {
// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone and closer than $nextImage.
                if ($disableImageClockwiseBearingLimit < $disableImageAnticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
                    if ($nearbyImage['distance'] < $nextImage['distance'] &&
                        ($nearbyImage['bearing'] >= $disableImageAnticlockwiseBearingLimit ||
                            $nearbyImage['bearing'] <= $disableImageClockwiseBearingLimit)
                    ) {
                        DEBUG_OUTPUT ? print "Intermediate image to disable within bearing limit "
                            . "($disableImageAnticlockwiseBearingLimit - $disableImageClockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                            . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;

// Disable the image.
                        $imageArray[$nearbyImage['sortOrder']]['disabled'] = true;
                    }
                } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries and closer than $nextImage.
                    if ($nearbyImage['distance'] < $nextImage['distance'] &&
                        $nearbyImage['bearing'] >= $disableImageAnticlockwiseBearingLimit &&
                        $nearbyImage['bearing'] <= $disableImageClockwiseBearingLimit
                    ) {
                        DEBUG_OUTPUT ? print "Intermediate image to disable within bearing limit "
                            . "($disableImageAnticlockwiseBearingLimit - $disableImageClockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                            . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Disable the image.
                        $imageArray[$nearbyImage['sortOrder']]['disabled'] = true;
                    }
                }
            }

            if (DEBUG_OUTPUT) {
                print "<h4>Image found through distance based expanding estimated bearing search. "
                    . "ID: {$nextImage['sortOrder']}, DISTANCE: {$nextImage['distance']}, BEARING: {$nextImage['bearing']}</h4>";
                print 'Current Image<pre>';
                print_r($imageArray[$targetImage]);
                print "</pre>Next Image<pre>";
                print_r($imageArray[$nextImage['sortOrder']]);
                print '</pre>';
            }
// Set the $targetImage value to that of the chosen nextImage to continue the search and then
// skip the rest of this iteration.
            $targetImage = $nextImage['sortOrder'];
            continue;
        } // END if (count($nextImageShortlist) > 0) Shortlist from expected bearing search.
//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => To reach this point $targetImage has previous and a bearing on which a next image was expected
// => to be found was calculated. A cone of uncertainty was built around this bearing which was skewed
// => in a direction that the plane may have historically been determined to be turning in... but no
// => images were found. This next code block will expend the search cone to a flat 45 degrees to both
// => sides of the unadjusted expected bearing.
        DEBUG_OUTPUT ? print "<h4>No next images found in estimated course cone. Widening search using "
            . "the unadjusted estimated course ($unadjustedBearing degrees +/- 45 degrees).</h4>" : false;

// Set the bearing boundaries to +/- 45 degrees
        $clockwiseBearingLimit = ceil($unadjustedBearing + 45);
        $anticlockwiseBearingLimit = floor($unadjustedBearing - 45);
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
        if ($clockwiseBearingLimit >= 360) {
            $clockwiseBearingLimit = $clockwiseBearingLimit - 360;
        }
        if ($anticlockwiseBearingLimit < 0) {
            $anticlockwiseBearingLimit = $anticlockwiseBearingLimit + 360;
        }

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin search of relationalImageArray for a shortlist of all images in the widened estimated
// direction (+/- 45 degress Add results to $nextImageShortlist array.
        foreach ($relationalImageArray as $nearbyImage) {
// Skip the image if it is too far away, too close, has been marked as a duplicate of an image
// (less than MINIMUM_NEXT_IMAGE_DISTANCE from an image) or if it has already been sequenced.
            if ($nearbyImage['distance'] > FINAL_MAXIMUM_COURSE_BASED_NEXT_IMAGE_DISTANCE ||
                isset($imageArray[$nearbyImage['sortOrder']]['sequenced']) ||
                isset($imageArray[$nearbyImage['sortOrder']]['disabled']) ||
                isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
            ) {
                continue; // Skip this nearby image.
            }

// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone.
            if ($clockwiseBearingLimit < $anticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
                if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit ||
                    $nearbyImage['bearing'] <= $clockwiseBearingLimit
                ) {
                    DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                        . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                        . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// To allow a difference between the expected bearing and the nearby image bearing to be
// calculated both must be in the same degree range (if either are beyond 0 degrees
// they must have 360 degrees added.
                    if ($nearbyImage['bearing'] <= $clockwiseBearingLimit) {
                        $tempNearbyImageBearing = $nearbyImage['bearing'] + 360;
                    } else {
                        $tempNearbyImageBearing = $nearbyImage['bearing'];
                    }
                    if ($unadjustedBearing <= $clockwiseBearingLimit) {
                        $tempAdjustedBearing = $unadjustedBearing + 360;
                    } else {
                        $tempAdjustedBearing = $unadjustedBearing;
                    }
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                    $nearbyImage['bearingDifference'] = round(abs($tempNearbyImageBearing - $tempAdjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                    $nextImageShortlist[] = $nearbyImage;
                }
            } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries.
                if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit &&
                    $nearbyImage['bearing'] <= $clockwiseBearingLimit
                ) {
                    DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                        . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                        . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                    $nearbyImage['bearingDifference'] = round(abs($nearbyImage['bearing'] - $unadjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                    $nextImageShortlist[] = $nearbyImage;
                }
            }
        }  // END ($relationalImageArray as $nearbyImage)
//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => Look through the next image shortlist to find the best next image available using distance,
// => difference in actual bearing from expected bearing and time difference as decision factors.
        if (count($nextImageShortlist) > 0) {
// Clear the nextImage variable;
            $nextImage = null;
            $bearingDifferenceShortlist = array();
            $smallestBearingDifference = null;

// Create a distance sorted version of the nextImageShortlist
            $distanceShortlist = $nextImageShortlist;
            usort($distanceShortlist, distance_sort);
            if (DEBUG_OUTPUT) {
                print "<h4>Distance Shortlist</h4>";
                foreach ($distanceShortlist as $key => $item) {
                    print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
                }
            }
// Determine the maximum distance a bearing difference based image can be from $targetImage
// (1.5 times the distance of the closes image by distance alone)
            $distanceBasedMaxDistance = $distanceShortlist[0]['distance'] * 1.5;

// Create a bearing difference shortlist based on nextImageShortlist where images are grouped with
// others with the same bearing difference and these are then sorted by distance. Array is
// multi-dimensional. First dimension is the bearing difference, second dimension holds image data
// arrays. Limit images to those less than $distanceBasedMaxDistance away from $targetImage
// Create the multidimensional array grouping images with the same bearing difference
            foreach ($nextImageShortlist as $image) {
                if ($image['distance'] < $distanceBasedMaxDistance) {
                    $bearingDifferenceShortlist[$image['bearingDifference']][] = $image;
                }
            }
// Sort the array by key value (bearing difference) ascending (lowest first)
            ksort($bearingDifferenceShortlist, SORT_NUMERIC);
// Sort each set of grouped images by distance. Smallest first. Record the key value of the first
// group returned as this will be the smallest amount ofbearing difference.
            foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $imagesGroupedByCourseChange) {
                if (is_null($smallestBearingDifference)) {
                    $smallestBearingDifference = $maximumBearingDifference;
                }
// Remove any bearing difference groups that are greater than the bearing difference of the
// closest image - 5 degrees.
                if ($maximumBearingDifference <= $distanceShortlist[0]['bearingDifference'] - 5) {
                    usort($imagesGroupedByCourseChange, distance_sort);
                    $bearingDifferenceShortlist[$maximumBearingDifference] = $imagesGroupedByCourseChange;
                } else {
                    unset($bearingDifferenceShortlist[$maximumBearingDifference]);
                }
            }
            if (DEBUG_OUTPUT) {
                print "<h4>Bearing Difference Shortlist</h4>";
                foreach ($bearingDifferenceShortlist as $deviation => $data) {
                    print "$deviation degrees expected course deviation<br>";
                    foreach ($data as $key => $item) {
                        print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
                    }
                }
            }

// If the closest image by distance is also the closest one with the smallest bearing difference
// then set it as the next image. Otherwise if the closest image with the smallest bearing
// difference is less that 1.5 times the distance away of the closest distance based image and
// it has a smaller time difference from $targetImage then choose it over the closest image.
// Default to the closest image if all other conditions fail to find a next image.
            if ($distanceShortlist[0]['sortOrder'] == $bearingDifferenceShortlist[$smallestBearingDifference][0]['sortOrder']) {
                $nextImage = $distanceShortlist[0];
                DEBUG_OUTPUT ? print "<br><b>Distance and Bearing Difference Agree.</b><br>" : false;
            } else {
// If the smallest bearing difference is less than the bearing difference of the closest
// image by distance alone then check those images out for possible better next image option.
//Loop through all of the smaller bearing differences
                foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $groupedImages) {
                    if ($maximumBearingDifference == $distanceShortlist[0]['bearingDifference']) {
                        break;
                    }
// Loop through each image in the bearing difference group. See if time difference is
// less or no more than 60 seconds ahead. If so use it as the next image.
                    for ($i = 0; $i < count($groupedImages); $i++) {
                        if (abs($groupedImages[$i]['timeDifference']) <= (abs($distanceShortlist[0]['timeDifference']) + 60) &&
                            $groupedImages[$i]['sortOrder'] != $distanceShortlist[0]['sortOrder']
                        ) {
                            $nextImage = $groupedImages[$i];
                            DEBUG_OUTPUT ? print "<br><b>Distance is bettered by course difference.</b> "
                                . "Using Course Difference<br>" : false;
                            break 2;
                        }
                    }
                }
            }
// Default to closes image by distance alone if no other image has been selected.
            if (is_null($nextImage)) {
                DEBUG_OUTPUT ? print "<br><b>No agreement between distance and bearing difference arrays and no "
                    . "bearing based image could better the distance based image. Defaulting to closest image."
                    . "</b><br>" : false;
                $nextImage = $distanceShortlist[0];
            }

//////////////////////////////////////////////////////////////////////////////////////////////////
// => Set the next and previous image details in $targetImage and $nextImage respectivley.
            $imageArray[$targetImage]['nextImage'] = $nextImage['sortOrder'];
            $imageArray[$targetImage]['nextImageDistance'] = $nextImage['distance'];
            $imageArray[$targetImage]['nextImageBearing'] = $nextImage['bearing'];
            $imageArray[$targetImage]['sequenced'] = true;
            $imageArray[$nextImage['sortOrder']]['previousImage'] = $targetImage;
            $imageArray[$nextImage['sortOrder']]['previousImageDistance'] = $nextImage['distance'];
            $imageArray[$nextImage['sortOrder']]['previousImageBearing'] = ($nextImage['bearing'] + 180) % 360;

            $positionInCollection = update_database(
                $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);

// Determine the boundaries for the 180 degree search cone that will disable all images between
// $targetImage and $nextImage
            $disableImageClockwiseBearingLimit = $nextImage['bearing'] + 90;
            $disableImageAnticlockwiseBearingLimit = $nextImage['bearing'] - 90;
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
            if ($disableImageClockwiseBearingLimit >= 360) {
                $disableImageClockwiseBearingLimit = $disableImageClockwiseBearingLimit - 360;
            }
            if ($disableImageAnticlockwiseBearingLimit < 0) {
                $disableImageAnticlockwiseBearingLimit = $disableImageAnticlockwiseBearingLimit + 360;
            }
            foreach ($relationalImageArray as $nearbyImage) {
// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone and closer than $nextImage.
                if ($disableImageClockwiseBearingLimit < $disableImageAnticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
                    if ($nearbyImage['distance'] < $nextImage['distance'] &&
                        ($nearbyImage['bearing'] >= $disableImageAnticlockwiseBearingLimit ||
                            $nearbyImage['bearing'] <= $disableImageClockwiseBearingLimit)
                    ) {
                        DEBUG_OUTPUT ? print "Intermediate image to disable within bearing limit "
                            . "($disableImageAnticlockwiseBearingLimit - $disableImageClockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                            . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;

// Disable the image.
                        $imageArray[$nearbyImage['sortOrder']]['disabled'] = true;
                    }
                } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries and closer than $nextImage.
                    if ($nearbyImage['distance'] < $nextImage['distance'] &&
                        $nearbyImage['bearing'] >= $disableImageAnticlockwiseBearingLimit &&
                        $nearbyImage['bearing'] <= $disableImageClockwiseBearingLimit
                    ) {
                        DEBUG_OUTPUT ? print "Intermediate image to disable within bearing limit "
                            . "($disableImageAnticlockwiseBearingLimit - $disableImageClockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                            . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Disable the image.
                        $imageArray[$nearbyImage['sortOrder']]['disabled'] = true;
                    }
                }
            }

            if (DEBUG_OUTPUT) {
                print "<h4>Image found through widened estimated bearing search. ID: {$nextImage['sortOrder']}, "
                    . "DISTANCE: {$nextImage['distance']}, BEARING: {$nextImage['bearing']}</h4>";
                print 'Current Image<pre>';
                print_r($imageArray[$targetImage]);
                print "</pre>Next Image<pre>";
                print_r($imageArray[$nextImage['sortOrder']]);
                print '</pre>';
            }
// Set the $targetImage value to that of the chosen nextImage to continue the search and then
// skip the rest of this iteration.
            $targetImage = $nextImage['sortOrder'];
            continue;
        } // END if (count($nextImageShortlist) > 0) Shortlist from expected bearing search.
    } // END if (count($previousImageArray) > 0)
//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => To reach this point $targetImage had no previous image from which to build an estimated bearing in
// => which the next image could be found OR an estimated bearing was generated but nothing was found in
// => that direction either through by an expanding distance based cone or a flat +/- 45 degree cone both
// => of which were centered on the calculated unadjusted estimated bearing. Now the code will look at
// => the region the image was determined to reside in and will look for a next image in the general
// => direction the coast heads in in that region. Search cone will have +/- 60 degree boundaries based
// => around that regional direction bearing and will first search up to a distance limit defined by the
// => INTIAL_REGION_BASED_NEXT_IMAGE_DISTANCE constant. If that fails then the search will expand up to
// => the range specified in MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE requiring a new relationalImageArray
// => to be generated.

    $nextImageShortlist = array();

// Find the next image search bearing for the region in which the image resides.
    switch ($imageArray[$targetImage]['region']) {
        case 1:  // Canada to Portland ME
            $unadjustedBearing = 243;
            break;
        case 2: // Portland ME to Boston MA
            If ((($imageArray[$targetImage]['latitude'] > 43.5241 && $imageArray[$targetImage]['latitude'] < 43.5665) &&
                    ($imageArray[$targetImage]['longitude'] > -70.341 && $imageArray[$targetImage]['longitude'] < -70.185)) ||
// north coast of Saco Bay, ME.
                (($imageArray[$targetImage]['latitude'] > 43.335 && $imageArray[$targetImage]['latitude'] < 43.349) &&
                    ($imageArray[$targetImage]['longitude'] > -70.542 && $imageArray[$targetImage]['longitude'] < -70.466))
// coast south of Kennebunkport, ME.
            ) {
                $unadjustedBearing = 270;
            } else if ((($imageArray[$targetImage]['latitude'] > 42.6583 && $imageArray[$targetImage]['latitude'] < 42.6652) &&
                    ($imageArray[$targetImage]['longitude'] > -70.6199 && $imageArray[$targetImage]['longitude'] < -70.5858)) ||
// Rockport, MA.
                (($imageArray[$targetImage]['latitude'] > 43.447 && $imageArray[$targetImage]['latitude'] < 43.462) &&
                    ($imageArray[$targetImage]['longitude'] > -70.379 && $imageArray[$targetImage]['longitude'] < -70.318)) ||
// south coast of Saco Bay, ME.
                (($imageArray[$targetImage]['latitude'] > 42.653 && $imageArray[$targetImage]['latitude'] < 42.697) &&
                    ($imageArray[$targetImage]['longitude'] > -70.784 && $imageArray[$targetImage]['longitude'] < -70.691)) ||
// south coast of Ipswich Bay, MA.
                (($imageArray[$targetImage]['latitude'] > 42.517 && $imageArray[$targetImage]['latitude'] < 42.697) &&
                    ($imageArray[$targetImage]['longitude'] > -70.784 && $imageArray[$targetImage]['longitude'] < -70.842))
// Marblehead, MA.
            ) {
                $unadjustedBearing = 90;
            } else if (($imageArray[$targetImage]['latitude'] > 42.6508 && $imageArray[$targetImage]['latitude'] < 42.7008) &&
                ($imageArray[$targetImage]['longitude'] > -70.6912 && $imageArray[$targetImage]['longitude'] < 70.6304)
            ) {
// Adjust general coast bearing to account for east coast of Ipswich Bay, MA
                $unadjustedBearing = 45;
            } else {
                $unadjustedBearing = 210;
            }
            break;
        case 3: // Boston MA to Sandwich MA
            $unadjustedBearing = 145;
            break;
        case 4: // Sandwich MA to Orleans, MA
            $unadjustedBearing = 90;
            break;
        case 5: // Orleans, MA to Wellfleet, MA
        case 7: // Jeremy Point MA to North Truro MA
            $unadjustedBearing = 0;
            break;
        case 6: // Wellfleet, MA to Jeremy Point, MA
        case 12: // Pilgrim Lake MA to Southeast Nantucket Island MA
        case 15: // East Chappaquiddick Island MA
        case 19: // East Block Island RI
            $unadjustedBearing = 180;
            break;
        case 8: //North Truro MA to Provincetown MA
        case 29: // South Sanibel Island, FL
            $unadjustedBearing = 270;
            break;
        case 9: // Provincetown Harbor (Long Point) MA to South Herring Cove MA
            if ($imageArray[$targetImage]['longitude'] >= -70.188) {
// Long Point, MA
                $unadjustedBearing = 225;
            } else {
// South Herring Cove, MA
                $unadjustedBearing = 325;
            }
            break;
        case 10: // North Herring Cove, Race Point and ProvinceTown Municipal Airport
            if ($imageArray[$targetImage]['latitude'] <= 42.0616) {
// North Herring Cove, Race Point
                $unadjustedBearing = 315;
            } else {
// Race Point, MA to ProvinceTown Municipal Airport, MA
                $unadjustedBearing = 50;
            }
            break;
        case 11: //ProvinceTown Municipal Airport MA to to Pilgrim Lake MA
            $unadjustedBearing = 102;
            break;
        case 14: // Southeast Nantucket Island MA to Muskeget Island MA
            $unadjustedBearing = 285;
            break;
        case 16: // South Chapaquiddick Island to West Martha's Vineyard
            if ($imageArray[$targetImage]['longitude'] < -70.776) {
// West Martha's Vineyard
                $unadjustedBearing = 315;
            } else if ($imageArray[$targetImage]['longitude'] < -70.724) {
// Squibnocket Bight
                $unadjustedBearing = 215;
            } else {
                $unadjustedBearing = 270;
            }
            break;
        case 17: //Southern Cape Cod Peninsular from Chatham MA to Cuttyhunk Island MA
            if (($imageArray[$targetImage]['latitude'] >= 41.61 && $imageArray[$targetImage]['latitude'] < 41.628) &&
                ($imageArray[$targetImage]['longitude'] >= -70.274 && $imageArray[$targetImage]['longitude'] < -70.266)
            ) {
// Northerly coastline south of Lewis Bay, MA
                $unadjustedBearing = 350;
            } else if ($imageArray[$targetImage]['longitude'] >= -70.342 && $imageArray[$targetImage]['longitude'] < -70.316) {
// East Centervill Harbor, MA
                $unadjustedBearing = 295;
            } else {
                $unadjustedBearing = 235;
            }
        case 18: // Southern MA and RI from Westport MA, to Napatree Point, RI including Fishers Island, NY
            if (($imageArray[$targetImage]['longitude'] >= -71.066 && $imageArray[$targetImage]['longitude'] < -71.0375) ||
// Coast of Horseneck Beach State Reservation
                ($imageArray[$targetImage]['longitude'] >= -71.866 && $imageArray[$targetImage]['longitude'] < -71.8586)
// Watch Tree Point RI
            ) {
                $unadjustedBearing = 340;
            } else if ($imageArray[$targetImage]['longitude'] >= -71.43 && $imageArray[$targetImage]['longitude'] < -71.2) {
// Little Compton RI to Narragansett, RI
                $unadjustedBearing = 270;
            } else if (($imageArray[$targetImage]['longitude'] >= -71.525 && $imageArray[$targetImage]['longitude'] < -71.485) ||
// Point Judith RI to East Matunuck State Beach
                ($imageArray[$targetImage]['longitude'] >= -71.7657 && $imageArray[$targetImage]['longitude'] < -71.7515)
// Weekapaug Point
            ) {
                $unadjustedBearing = 315;
            } else if ($imageArray[$targetImage]['longitude'] >= -72.043 && $imageArray[$targetImage]['longitude'] < -71.866) {
// Fisher's Island
                $unadjustedBearing = 240;
            } else {
                $unadjustedBearing = 250;
            }
            break;
        case 20: // Southern Block Island and Montauk, NY to Staten Island, NY
            if (($imageArray[$targetImage]['longitude'] >= -73.325 && $imageArray[$targetImage]['longitude'] < -73.315) ||
// Democrat Point NY
                ($imageArray[$targetImage]['longitude'] >= -73.585 && $imageArray[$targetImage]['longitude'] < -73.575) ||
// Point Lookout NY
                ($imageArray[$targetImage]['longitude'] >= -73.764 && $imageArray[$targetImage]['longitude'] < -73.754)
// Silver Point NY
            ) {
                $unadjustedBearing = 340;
            } else {
                $unadjustedBearing = 250;
            }
            break;
        case 21: // Sandy Hook NJ to Virginia Beach VA
            if ($imageArray[$targetImage]['longitude'] > -75.436 && $imageArray[$targetImage]['longitude'] < -75.3829) {
// Wallops Island, VA
                $unadjustedBearing = 300;
            } else if (($imageArray[$targetImage]['latitude'] > 37.568 && $imageArray[$targetImage]['latitude'] < 37.589) ||
// Wachapreague Inlet VA
                ($imageArray[$targetImage]['latitude'] > 39.105 && $imageArray[$targetImage]['latitude'] < 39.12) ||
// Townsends Inlet NJ
                ($imageArray[$targetImage]['latitude'] > 37.459 && $imageArray[$targetImage]['latitude'] < 37.477)
// Quinby Inlet
            ) {
                $unadjustedBearing = 135;
            } else {
                $unadjustedBearing = 200;
            }
            break;
        case 22: // Virginia Beach VA and Hatteras Island, NC
            if ($imageArray[$targetImage]['latitude'] > 36.924) {
                $unadjustedBearing = 100;
            } else {
                $unadjustedBearing = 167;
            }

            break;
        case 23: // Hatteras Island, NC to Hilton Head Island, SC
            if ($imageArray[$targetImage]['longitude'] > -75.6041 ||
                ($imageArray[$targetImage]['longitude'] > -76.7676 && $imageArray[$targetImage]['longitude'] < -76.534) ||
                ($imageArray[$targetImage]['longitude'] > -78.1867 && $imageArray[$targetImage]['longitude'] < -77.959)
            ) {
// Hatteras Island
// Onslow Bay
// Bald Head Island, NC
                $unadjustedBearing = 290;
            } else {
                $unadjustedBearing = 234;
            }
            break;
        case 24: // Hilton Head Island, SC and Key Largo, FL
            if ($imageArray[$targetImage]['latitude'] >= 30.601866) {
// Hilton Head Island SC to Fernandina Beach FL
                $unadjustedBearing = 202;
            } else {
// Fernandina Beach FL to Key Largo, FL
                $unadjustedBearing = 170;
            }
            break;
        case 28: // Micmac Lagoon, FL to Big Hickory Island, FL
            $unadjustedBearing = 330;
            break;
        case 30: // Sanibel Island, FL to Apalachee Bay, FL
            if (($imageArray[$targetImage]['latitude'] > 27.8782 && $imageArray[$targetImage]['latitude'] < 28.6137) &&
                ($imageArray[$targetImage]['longitude'] > -82.8934 && $imageArray[$targetImage]['longitude'] < -82.6132)
            ) {
// Clearwater, FL to Spring Hill, FL
                $unadjustedBearing = 20;
            } else if (($imageArray[$targetImage]['latitude'] > 29.1467 && $imageArray[$targetImage]['latitude'] < 29.19) &&
                ($imageArray[$targetImage]['longitude'] > -83.0730 && $imageArray[$targetImage]['longitude'] < -82.8117)
            ) {
// coast east of Cedar Key, FL
                $unadjustedBearing = 270;
            } else {
                $unadjustedBearing = 335;
            }
            break;
        case 31: // Apalachee Bay, FL and Cat Island, MI
            if ($imageArray[$targetImage]['longitude'] >= -84.343 && $imageArray[$targetImage]['longitude'] < -84.3292) {
// Piney Island and Bald Point, FL
                $unadjustedBearing = 180;
            } else if ($imageArray[$targetImage]['longitude'] >= -85.05 && $imageArray[$targetImage]['longitude'] < -84.343) {
// Bald Point, FL to Cape St George Island State Reserve, FL
                $unadjustedBearing = 243;
            } else if (($imageArray[$targetImage]['longitude'] > -85.22 && $imageArray[$targetImage]['longitude'] < -85.05) ||
// Cape St George Island State Reserve, FL to Indian Pass, FL
                ($imageArray[$targetImage]['longitude'] > -86.36 && $imageArray[$targetImage]['longitude'] < -85.8552)
// Panama City Beach, FL to Miramar Beach, FL
            ) {
                $unadjustedBearing = 301;
            } else if (($imageArray[$targetImage]['latitude'] > 29.6516 && $imageArray[$targetImage]['latitude'] < 30.21) &&
                ($imageArray[$targetImage]['longitude'] > -85.8552 && $imageArray[$targetImage]['longitude'] < -85.3463)
            ) {
// St. Joseph Bay to Panama City Beach, FL
                $unadjustedBearing = 321;
            } else {
                $unadjustedBearing = 270;
            }
            break;
        case 32: // Breton Island, LA to Port Eads, LA
            $unadjustedBearing = 190;
            break;
        case 33: // Port Eads, LA to Port Arthur TX (TX/LA Border)
            if ($imageArray[$targetImage]['longitude'] >= -89.82 && $imageArray[$targetImage]['longitude'] < -89.45) {
                $unadjustedBearing = 297;
            } else if ($imageArray[$targetImage]['longitude'] >= -90.22 && $imageArray[$targetImage]['longitude'] < -89.82) {
                $unadjustedBearing = 231;
            } else if ($imageArray[$targetImage]['longitude'] > -90.83 && $imageArray[$targetImage]['longitude'] < -90.22) {
                $unadjustedBearing = 270;
            } else {
                $unadjustedBearing = 288;
            }
            break;
        case 34: // Port Arthur TX (TX/LA Border) to Corpus Christi, TX
            $unadjustedBearing = 235;
            break;
        case 35:
// Corpus Christi, TX and US/Mexico Border
            $unadjustedBearing = 180;
            break;
        case 36: // US/Mexico Border and Eureka CA

            $unadjustedBearing = 323;
            break;
        case 37: // Eureka CA and US/Canada Border
            $unadjustedBearing = 0;
            break;
    }
    DEBUG_OUTPUT ? print "<h4>Either no previous images were found to generate an expected bearing or an "
        . "expected bearing was generated but nothing was found in the distance based or widened search cones. "
        . "Search will now continue based on a regional coastal bearing of $unadjustedBearing degrees "
        . "(+/- 60 degrees) and short range.</h4>" : false;

// Set the bearing boundaries to +/- 60 degrees
    $clockwiseBearingLimit = ceil($unadjustedBearing + 60);
    $anticlockwiseBearingLimit = floor($unadjustedBearing - 60);
// Correct bearings that exceed allowed limits (outside 0-360 degrees);
    if ($clockwiseBearingLimit >= 360) {
        $clockwiseBearingLimit = $clockwiseBearingLimit - 360;
    }
    if ($anticlockwiseBearingLimit < 0) {
        $anticlockwiseBearingLimit = $anticlockwiseBearingLimit + 360;
    }
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin search of relationalImageArray for a shortlist of all images in the regional direction
// (+/- 60 degrees). Add results to $nextImageShortlist array.
    foreach ($relationalImageArray as $nearbyImage) {
// Skip the image if it is too far away, has been marked as a duplicate of another image
// (less than MINIMUM_NEXT_IMAGE_DISTANCE from another image) or if it has already been sequenced.
        if ($nearbyImage['distance'] > INITIAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE ||
            isset($imageArray[$nearbyImage['sortOrder']]['sequenced']) ||
            isset($imageArray[$nearbyImage['sortOrder']]['disabled']) ||
            isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
        ) {
            continue; // Skip this nearby image.
        }
// If the image is less than MINIMUM_NEXT_IMAGE_DISTANCE and is not already marked as a duplicate
// of another image them mark it as a duplicate of $targetImage and then skip the image.
        if ($nearbyImage['distance'] < MINIMUM_NEXT_IMAGE_DISTANCE &&
            !isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
        ) {
            $imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'] = $targetImage;
            $imageArray[$targetImage]['duplicates'][] = $nearbyImage['sortOrder'];
            continue;
        }

// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone.
        if ($clockwiseBearingLimit < $anticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
            if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit ||
                $nearbyImage['bearing'] <= $clockwiseBearingLimit
            ) {
                DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                    . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                    . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// To allow a difference between the expected bearing and the nearby image bearing to be
// calculated both must be in the same degree range (if either are beyond 0 degrees
// they must have 360 degrees added.
                if ($nearbyImage['bearing'] <= $clockwiseBearingLimit) {
                    $tempNearbyImageBearing = $nearbyImage['bearing'] + 360;
                } else {
                    $tempNearbyImageBearing = $nearbyImage['bearing'];
                }
                if ($unadjustedBearing <= $clockwiseBearingLimit) {
                    $tempAdjustedBearing = $unadjustedBearing + 360;
                } else {
                    $tempAdjustedBearing = $unadjustedBearing;
                }
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                $nearbyImage['bearingDifference'] = round(abs($tempNearbyImageBearing - $tempAdjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                $nextImageShortlist[] = $nearbyImage;
            }
        } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries.
            if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit &&
                $nearbyImage['bearing'] <= $clockwiseBearingLimit
            ) {
                DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                    . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                    . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Calculate difference between expected bearing and nearbyImage bearing. Add to the array.
                $nearbyImage['bearingDifference'] = round(abs($nearbyImage['bearing'] - $unadjustedBearing));
// Add the nearbyImage to the nextImage shortlist.
                $nextImageShortlist[] = $nearbyImage;
            }
        }
    } // END ($relationalImageArray as $nearbyImage)
//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => Look through the next image shortlist to find the best next image available using distance,
// => difference in actual bearing from expected bearing and time difference as decision factors.
    if (count($nextImageShortlist) > 0) {
// Clear the nextImage variable;
        $nextImage = null;
        $bearingDifferenceShortlist = array();
        $smallestBearingDifference = null;

// Create a distance sorted version of the nextImageShortlist
        $distanceShortlist = $nextImageShortlist;
        usort($distanceShortlist, distance_sort);
        if (DEBUG_OUTPUT) {
            print "<h4>Distance Shortlist</h4>";
            foreach ($distanceShortlist as $key => $item) {
                print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
            }
        }
// Determine the maximum distance a bearing difference based image can be from $targetImage
// (1.5 times the distance of the closes image by distance alone)
        $distanceBasedMaxDistance = $distanceShortlist[0]['distance'] * 1.5;

// Create a bearing difference shortlist based on nextImageShortlist where images are grouped with
// others with the same bearing difference and these are then sorted by distance. Array is
// multi-dimensional. First dimension is the bearing difference, second dimension holds image data
// arrays. Limit images to those less than $distanceBasedMaxDistance away from $targetImage
// Create the multidimensional array grouping images with the same bearing difference
        foreach ($nextImageShortlist as $image) {
            if ($image['distance'] < $distanceBasedMaxDistance) {
                $bearingDifferenceShortlist[$image['bearingDifference']][] = $image;
            }
        }
// Sort the array by key value (bearing difference) ascending (lowest first)
        ksort($bearingDifferenceShortlist, SORT_NUMERIC);
// Sort each set of grouped images by distance. Smallest first. Record the key value of the first
// group returned as this will be the smallest amount ofbearing difference.
        foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $imagesGroupedByCourseChange) {
            if (is_null($smallestBearingDifference)) {
                $smallestBearingDifference = $maximumBearingDifference;
            }
// Remove any bearing difference groups that are greater than the bearing difference of the
// closest image - 5 degrees.
            if ($maximumBearingDifference <= $distanceShortlist[0]['bearingDifference'] - 5) {
                usort($imagesGroupedByCourseChange, distance_sort);
                $bearingDifferenceShortlist[$maximumBearingDifference] = $imagesGroupedByCourseChange;
            } else {
                unset($bearingDifferenceShortlist[$maximumBearingDifference]);
            }
        }
        if (DEBUG_OUTPUT) {
            print "<h4>Bearing Difference Shortlist</h4>";
            foreach ($bearingDifferenceShortlist as $deviation => $data) {
                print "$deviation degrees expected course deviation<br>";
                foreach ($data as $key => $item) {
                    print "$key - ID: {$item['sortOrder']}, DISTANCE: {$item['distance']}, BEARING: {$item['bearing']}, DIFFERENCE FROM EST. BEARING: {$item['bearingDifference']}, TIME DIFF: {$item['timeDifference']}<br>";
                }
            }
        }

// If the closest image by distance is also the closest one with the smallest bearing difference
// then set it as the next image. Otherwise if the closest image with the smallest bearing
// difference is less that 1.5 times the distance away of the closest distance based image and
// it has a smaller time difference from $targetImage then choose it over the closest image.
// Default to the closest image if all other conditions fail to find a next image.
        if ($distanceShortlist[0]['sortOrder'] == $bearingDifferenceShortlist[$smallestBearingDifference][0]['sortOrder']) {
            $nextImage = $distanceShortlist[0];
            DEBUG_OUTPUT ? print "<br><b>Distance and Bearing Difference Agree.</b><br>" : false;
        } else {
// If the smallest bearing difference is less than the bearing difference of the closest
// image by distance alone then check those images out for possible better next image option.
//Loop through all of the smaller bearing differences
            foreach ($bearingDifferenceShortlist as $maximumBearingDifference => $groupedImages) {
                if ($maximumBearingDifference == $distanceShortlist[0]['bearingDifference']) {
                    break;
                }
// Loop through each image in the bearing difference group. See if time difference is
// less. If so use it as the next image.
                for ($i = 0; $i < count($groupedImages); $i++) {
                    if (abs($groupedImages[$i]['timeDifference']) <= (abs($distanceShortlist[0]['timeDifference']) + 60) &&
                        $groupedImages[$i]['sortOrder'] != $distanceShortlist[0]['sortOrder']
                    ) {
                        $nextImage = $groupedImages[$i];
                        DEBUG_OUTPUT ? print "<br><b>Distance is bettered by course difference.</b> "
                            . "Using Course Difference<br>" : false;
                        break 2;
                    }
                }
            }
        }
// Default to closes image by distance alone if no other image has been selected.
        if (is_null($nextImage)) {
            DEBUG_OUTPUT ? print "<br><b>No agreement between distance and bearing difference arrays and no "
                . "bearing based image could better the distance based image. Defaulting to closest image."
                . "</b><br>" : false;
            $nextImage = $distanceShortlist[0];
        }

//////////////////////////////////////////////////////////////////////////////////////////////////
// => Set the next and previous image details in $targetImage and $nextImage respectivley.
        $imageArray[$targetImage]['nextImage'] = $nextImage['sortOrder'];
        $imageArray[$targetImage]['nextImageDistance'] = $nextImage['distance'];
        $imageArray[$targetImage]['nextImageBearing'] = $nextImage['bearing'];
        $imageArray[$targetImage]['sequenced'] = true;
        $imageArray[$nextImage['sortOrder']]['previousImage'] = $targetImage;
        $imageArray[$nextImage['sortOrder']]['previousImageDistance'] = $nextImage['distance'];
        $imageArray[$nextImage['sortOrder']]['previousImageBearing'] = ($nextImage['bearing'] + 180) % 360;

        $positionInCollection = update_database(
            $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);

        if (DEBUG_OUTPUT) {
            print "<h4>Image found through short range region search. ID: {$nextImage['sortOrder']}, "
                . "DISTANCE: {$nextImage['distance']}, BEARING: {$nextImage['bearing']}</h4>";
            print 'Current Image<pre>';
            print_r($imageArray[$targetImage]);
            print "</pre>Next Image<pre>";
            print_r($imageArray[$nextImage['sortOrder']]);
            print '</pre>';
        }
// Set the $targetImage value to that of the chosen nextImage to continue the search and then
// skip the rest of this iteration.
        $targetImage = $nextImage['sortOrder'];
        continue;
    } // END if (count($nextImageShortlist) > 0) Shortlist from short range regional bearing search.
//////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////
// => No images could be found in the short range regional direction search. Expand the range to the value
// => defined in the constant FINAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE.
    DEBUG_OUTPUT ? print "<h4>No images found in short range regional bearing search. "
        . "Attempting long range search with the same search cone.</h4>" : false;

//////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build an array of data about the relationship to $targetImage of other images within a specified radius of
// $targetImage's location. Includes distance and bearing FROM $targetImage as well as the time difference
// in seconds between them.)
// Loop through all images
    for ($i = 0; $i <= $lastImageArrayIndex; $i++) {
        $relationshipResult = distanceBearingTimeCalculator(
            $imageArray[$targetImage], $imageArray[$i], FINAL_MAXIMUM_REGION_BASED_NEXT_IMAGE_DISTANCE);
        if ($relationshipResult) {
            $relationalImageArray[$i] = $relationshipResult;
        }
    } // END for ($i = 0; $i <= $lastImageArrayIndex; $i++) Loop to build $relationalImageArray
    if (DEBUG_OUTPUT) {

//    print '<h4>Expanded $relationalImageArray</h4><pre>';
//    print_r($relationalImageArray);
//    print '</pre>';
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin search of relationalImageArray for a shortlist of all images in the regional direction
// (+/- 45degrees). Add results to $nextImageShortlist array.
    foreach ($relationalImageArray as $nearbyImage) {
// Skip the image if it is too far away, too close, has been marked as a duplicate of an image
// (less than MINIMUM_NEXT_IMAGE_DISTANCE from an image) or if it has already been sequenced.
        if (isset($imageArray[$nearbyImage['sortOrder']]['sequenced']) ||
            isset($imageArray[$nearbyImage['sortOrder']]['disabled']) ||
            isset($imageArray[$nearbyImage['sortOrder']]['duplicateImageOf'])
        ) {
            continue; // Skip this nearby image.
        }
// If bearing cone crosses 0 degrees (clockwise boundary becomes less then anticlockwise boundary)
// adjust the IF statement that determines if the nearbyImage is within the cone.
        if ($clockwiseBearingLimit < $anticlockwiseBearingLimit) { // 0 degrees is crossed.
// True result means the nearbyImage is within the search cone
            if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit ||
                $nearbyImage['bearing'] <= $clockwiseBearingLimit
            ) {
                DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                    . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                    . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Add the nearbyImage to the nextImage shortlist.
                $nextImageShortlist[] = $nearbyImage;
            }
        } else { // 0 degrees is not crossed. Simply check if bearing is between cone boundaries.
            if ($nearbyImage['bearing'] >= $anticlockwiseBearingLimit &&
                $nearbyImage['bearing'] <= $clockwiseBearingLimit
            ) {
                DEBUG_OUTPUT ? print "Potential neighbour within bearing limit "
                    . "($anticlockwiseBearingLimit - $clockwiseBearingLimit) found. {$nearbyImage['sortOrder']}:"
                    . " {$nearbyImage['bearing']} deg, {$nearbyImage['distance']} m</br>" : false;
// Add the nearbyImage to the nextImage shortlist.
                $nextImageShortlist[] = $nearbyImage;
            }
        }
    }
    if (count($nextImageShortlist) > 0) {
// Sort the nextImageShortlist by distance.
        usort($nextImageShortlist, distance_sort);
        $nextImage = $nextImageShortlist[0];

//////////////////////////////////////////////////////////////////////////////////////////////////
// => Set the next and previous image details in $targetImage and $nextImage respectivley.
        $imageArray[$targetImage]['nextImage'] = $nextImage['sortOrder'];
        $imageArray[$targetImage]['nextImageDistance'] = $nextImage['distance'];
        $imageArray[$targetImage]['nextImageBearing'] = $nextImage['bearing'];
        $imageArray[$targetImage]['sequenced'] = true;
        $imageArray[$nextImage['sortOrder']]['previousImage'] = $targetImage;
        $imageArray[$nextImage['sortOrder']]['previousImageDistance'] = $nextImage['distance'];
        $imageArray[$nextImage['sortOrder']]['previousImageBearing'] = ($nextImage['bearing'] + 180) % 360;

        $positionInCollection = update_database(
            $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);

        if (DEBUG_OUTPUT) {
            print "<h4>Image found through long range region search. ID: {$nextImage['sortOrder']}, "
                . "DISTANCE: {$nextImage['distance']}, BEARING: {$nextImage['bearing']}</h4>";
            print 'Current Image<pre>';
            print_r($imageArray[$targetImage]);
            print "</pre>Next Image<pre>";
            print_r($imageArray[$nextImage['sortOrder']]);
            print '</pre>';
        }
// Set the $targetImage value to that of the chosen nextImage to continue the search and then
// skip the rest of this iteration.
        $targetImage = $nextImage['sortOrder'];
        continue;
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
// => All search options are now exhausted and the only way to find the next image and try to jump
// => start the search again is to pick the next image in the $imageArray as the next image. This is
// => potentially unreliable as it is a simple regional direction sort but may allow the process to
// => resume.

    if ($targetImage < $lastImageArrayIndex) {
        DEBUG_OUTPUT ? print "<h4>No images found in long range regional bearing search. Now defaulting "
            . "to the next non-sequenced/non duplicate/non disabled image in the imageArray as the "
            . "next image.</h4>" : false;
// Get the inital loop variables for next image search
        $nextImageId = $targetImage + 1;
        $nextImage = $imageArray[$nextImageId];
// Loop through the next images until one is found that isn't already sequenced, disabled, or marked as a
// duplicate.
        while (isset($imageArray[$nextImageId]['sequenced']) ||
            isset($imageArray[$nextImageId]['disabled']) ||
            isset($imageArray[$nextImageId]['duplicateImageOf'])) {
            if ($nextImageId < $lastImageArrayIndex) {
                DEBUG_OUTPUT ? print "$nextImageId is sequenced, disabled, or is a duplicate. Incrementing again.<br>" : false;
                $nextImageId = $nextImageId + 1;
            } else {
                if (isset($imageArray[$nextImageId]['previousImageBearing'])) {
                    $imageArray[$nextImageId]['sequenced'] = true;
                }
                DEBUG_OUTPUT ? print "The last image has been reached. No next image can be set. "
                    . "Aborting next image search<br>" : false;
                break 2;
            }
        }
        $nextImage = distanceBearingTimeCalculator($imageArray[$targetImage], $imageArray[$nextImageId]);
        $imageArray[$targetImage]['nextImage'] = $nextImage['sortOrder'];
        $imageArray[$targetImage]['nextImageDistance'] = $nextImage['distance'];
        $imageArray[$targetImage]['nextImageBearing'] = $nextImage['bearing'];
        $imageArray[$targetImage]['sequenced'] = true;
        $imageArray[$nextImage['sortOrder']]['previousImage'] = $targetImage;
        $imageArray[$nextImage['sortOrder']]['previousImageDistance'] = $nextImage['distance'];
        $imageArray[$nextImage['sortOrder']]['previousImageBearing'] = ($nextImage['bearing'] + 180) % 360;

        $positionInCollection = update_database(
            $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);

        if (DEBUG_OUTPUT) {
            print "<h4>Image determined by choosing next image in imageArray. ID: {$nextImage['sortOrder']}, "
                . "DISTANCE: {$nextImage['distance']}, BEARING: {$nextImage['bearing']}</h4>";
            print 'Current Image<pre>';
            print_r($imageArray[$targetImage]);
            print "</pre>Next Image<br/><pre>";
            print_r($imageArray[$nextImage['sortOrder']]);
            print '</pre></p>';
        }
        $targetImage = $nextImage['sortOrder'];
        continue;
    }
    if (isset($imageArray[$targetImage]['previousImageBearing'])) {
        $imageArray[$targetImage]['sequenced'] = true;
    }
    DEBUG_OUTPUT ? print "<h4>No images found in long range regional bearing search and this is the last"
        . "image in the imageArray. No next image can be set. Aborting next image search</h4>" : false;
    $positionInCollection = update_database(
        $imageArray[$targetImage]['import_image_id'], $collectionMetadata['import_collection_id'], $positionInCollection, $imageArray[$targetImage]['sortOrder'], $totalImages);

    break;
} // END while (true). $targetImage loop to find neighbours.

$updateSequencingProgressQuery = '
    UPDATE import_collections
    SET sequencing_progress = 100, sequencing_stage = 3
    WHERE import_collection_id = :collectionId
    LIMIT 1';
$updateSequencingProgressParams['collectionId'] = $collectionMetadata['import_collection_id'];
$updateSequencingProgressResult = run_prepared_query($DBH, $updateSequencingProgressQuery, $updateSequencingProgressParams);
