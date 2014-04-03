<?php

function crowdTypeConverter($crowdTypeId, $otherCrowdType) {
    switch ($crowdTypeId) {
        case 1:
            return "Coastal & Marine Scientist";
            break;
        case 2:
            return "Coastal Manager or Planner";
            break;
        case 3:
            return "Coastal Resident";
            break;
        case 4:
            return "Watersport Enthusiast";
            break;
        case 5:
            return "Marine Science Student";
            break;
        case 6:
            return "Emergency Responder";
            break;
        case 7:
            return "Policy Maker";
            break;
        case 8:
            return "Digital Crisis Volunteer (VTC)";
            break;
        case 9:
            return "Interested Public";
            break;
        case 10:
            return "Other: " . $otherCrowdType;
            break;
    }
}

function timeZoneIdToTextConverter($timeZoneId) {
    switch ($timeZoneId) {
        case 1:
            return "Eastern";
            break;
        case 2:
            return "Central";
            break;
        case 3:
            return "Mountain";
            break;
        case 4:
            return "Mountain (Arizona)";
            break;
        case 5:
            return "Pacific";
            break;
        case 6:
            return "Alaskan";
            break;
        case 7:
            return "Hawaiian";
            break;
        case 8:
            return "UTC";
            break;
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Creates a formatted string showing a time converted from UTC to a users recorded time zone
 *
 * @param string $time A date/time string representing the start time in a valid Date and Time Format
 *        (http://www.php.net/manual/en/datetime.formats.php)
 * @param int $userTimeZone An integer from 1 to 7 representing the users time zone.
 * @return string A formatted string with the supplied time converted to correct time zone
 *         (example: March 3, 2014 at 7:50 AM)
 */
function formattedAnnotationTime($time, $userTimeZone, $verbose = TRUE) {
    switch ($userTimeZone) {
        case 1:
            $timeZoneString = ('America/New_York');
            break;
        case 2:
            $timeZoneString = ('America/Chicago');
            break;
        case 3:
            $timeZoneString = ('America/Denver');
            break;
        case 4:
            $timeZoneString = ('America/Phoenix');
            break;
        case 5:
            $timeZoneString = ('America/Los_Angeles');
            break;
        case 6:
            $timeZoneString = ('America/Anchorage');
            break;
        case 7:
            $timeZoneString = ('Pacific/Honolulu');
            break;
        case 8:
            $timeZoneString = ('UTC');
            break;
        default:
            // Placeholder for error reporting
            exit('Invalid user time zone specified. Should be 1 - 8 (Eastern to Hawaii or UTC');
            break;
    }
    $annotationTime = new DateTime($time, new DateTimeZone('UTC'));
    $annotationTime->setTimezone(new DateTimeZone($timeZoneString));
    if ($verbose) {
        return $annotationTime->format('F j\, Y \a\t g:i A');
    } else {
        return $annotationTime->format('d/m/y h:iA');
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds the number of tags a user selected in a single annotation
 *
 * Function to count the number of tag selections for a given annotation Id.
 *
 * @param int $annotationId An intenger number of the annotationId from the annotations table.
 * @return int A count of the number of entries in the annotation_selections table that have the annotation
 * ID sepecified. This is the number of tags the user selected in this annotation.
 */
function tagsInAnnotation($DBH, $annotationId) {
    $tagCountQuery = "SELECT COUNT(*) FROM annotation_selections WHERE annotation_id = :annotationId";
    $tagCountParams['annotationId'] = $annotationId;
    $STH = run_prepared_query($DBH, $tagCountQuery, $tagCountParams);
    return $STH->fetchColumn();
}

// -------------------------------------------------------------------------------------------------
/**
 * Determines the time difference between two date time strings
 *
 * Function to determine the time difference in minutes and seconds between two date time strings.
 *
 * @param string $startDateTime A date/time string representing the start time in a valid Date and Time Format
 *                              (http://www.php.net/manual/en/datetime.formats.php)
 * @param string $endDateTime A date/time string representing the later end time in a valid Date and Time Format
 *                              (http://www.php.net/manual/en/datetime.formats.php)
 * @return string The difference between the two times in minutes and seconds "x min(s) x sec(s)"
 */
function timeDifference($startDateTime, $endDateTime, $verbose = TRUE) {
    $startDateTime = new DateTime($startDateTime);
    $endDateTime = new DateTime($endDateTime);
    $annotationInterval = $startDateTime->diff($endDateTime);
    if ($verbose) {
        return $annotationInterval->format('%i min(s) %s sec(s)');
    } else {
        return $annotationInterval->format('%im %Ss');
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Determines and returns the ordinal suffix for a number
 *
 * Function to determine and return the ordinal suffix for a number
 *
 * @param int $num The number to analyse
 * @return string The supplied number including the ordinal suffix.
 */
function ordinal_suffix($num) {
    if ($num < 11 || $num > 13) {
        switch ($num % 10) {
            case 1: return "{$num}st";
            case 2: return "{$num}nd";
            case 3: return "{$num}rd";
        }
    }
    return "{$num}th";
}

//require_once("includes/globalFunctions.php");
// -------------------------------------------------------------------------------------------------
/**
 * Generates a random image number based on supplied arguments.
 *
 * Function to generate and return a random image id based on Project Id and User Id (if
 * specified). Result can be filtered to ensure the supplied ID has a valid pre image match (ergo,
 * both images have display images and are not globally disabled) and optionally if it has not
 * already been annotated by the user.
 *
 * @param int $projectId iCoast DB row id of the project in question.
 * @param bool $isFiltered If TRUE ensures the returned id is not for an image the user has already
 * annotated and that the image has a valid pre image match.
 * @param type $postCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the post image pool.
 * @param type $preCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the pre image pool.
 * @param type $userId Optional. Default = 0. iCoast DB row id of the user.
 * @return int|boolean If sucessful returns a random image id <b>OR</b><br>
 * On failure returns FALSE.
 */
function random_post_image_id_generator($DBH, $projectId, $isFiltered, $postCollectionId = 0, $preCollectionId = 0, $userId = 0) {
//   print "<p><b>In random_post_image_id_generator function.</b><br>Arguments:<br>ProjectId = $projectId<br>
//    Filtered = $isFiltered<br>Post Collection = $postCollectionId<br>Pre Collection = $preCollectionId<br>$userId</p>";
    if (!is_null($projectId) && !is_null($isFiltered) && is_Numeric($projectId) &&
            is_Numeric($postCollectionId) && is_Numeric($preCollectionId) && is_Numeric($userId) &&
            is_bool($isFiltered)) {
        $projectData = retrieve_entity_metadata($DBH, $projectId, 'project');
        if ($projectData) {
            $projectDatasets = find_datasets_in_collection($DBH, $projectData['post_collection_id']);
        }
        if ($userId !== 0) {
            $userGroups = find_user_group_membership($DBH, $userId, $projectId, TRUE);
            if ($userGroups) {
                $imageGroups = find_assigned_image_groups($DBH, $userGroups, TRUE);
                if ($imageGroups) {
                    $imageIdPool = retrieve_image_id_pool($DBH, $imageGroups, TRUE, FALSE);
                } else {
                    $imageIdPool = retrieve_image_id_pool($DBH, $projectDatasets, FALSE, FALSE);
                }
            } else {
                $imageIdPool = retrieve_image_id_pool($DBH, $projectDatasets, FALSE, FALSE);
            }
        } else {
            $imageIdPool = retrieve_image_id_pool($DBH, $projectDatasets, FALSE, FALSE);
        }
        if ($imageIdPool AND !$isFiltered) {
            $imagesCount = count($imageIdPool);
            $randomId = $imageIdPool[rand(1, $imagesCount)];
// print "RETURNING: $randomId Unifltered Random Image Id<br>";
            return $randomId;
        }
        if ($imageIdPool && $isFiltered) {
            while (!empty($imageIdPool)) {
                $imagesCount = count($imageIdPool);
                $randomIndex = rand(1, $imagesCount);
                $randomId = $imageIdPool[$randomIndex];
                array_splice($imageIdPool, $randomIndex, 1);
                $imageMatchData = retrieve_image_match_data($DBH, $postCollectionId, $preCollectionId, $randomId);
                if ($imageMatchData AND $imageMatchData['is_enabled'] == 1) {
                    if ($userId != 0) {
                        if (has_user_annotated_image($DBH, $randomId, $userId) === 0) {
                            /* print "RETURNING: $randomId Filtered (by Match Enabled and Not User Annotated)
                              Random Image Id<br>"; */
                            return $randomId;
                        } else {
// print "FILTERING: Failed on User Annotation";
                        }
                    } else {
// print "RETURNING: $randomId Filtered (by Match Enabled) Random Image Id<br>";
                        return $randomId;
                    }
                } else {
//print "FILTERING: Failed on is-Enabled";
                }
            }
        }
    }
//print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Generates a pool of post images available to the user.
 *
 * Function to generate and return a pool of images based on Project Id and User Id (if
 * specified). Result can be filtered to ensure the supplied pool only contains images that have a
 * valid pre image match (ergo, both images have display images and are not globally disabled) and
 * optionally if they have not already been annotated by the user.
 *
 * @param int $projectId iCoast DB row id of the project in question.
 * @param bool $isFiltered If TRUE ensures the returned id is not for an image the user has already
 * annotated and that the image has a valid pre image match.
 * @param type $postCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the post image pool.
 * @param type $preCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the pre image pool.
 * @param type $userId Optional. Default = 0. iCoast DB row id of the user.
 * @return int|boolean If sucessful returns a random image id <b>OR</b><br>
 * On failure returns FALSE.
 */
function post_image_pool_generator($DBH, $projectId, $isFiltered, $postCollectionId = 0, $preCollectionId = 0, $userId = 0) {
    /* print "<p><b>In random_post_image_id_generator function.</b><br>Arguments:<br>$projectId<br>
      $isFiltered<br>$postCollectionId<br>$preCollectionId<br>$userId</p>"; */
    if (!is_null($projectId) && !is_null($isFiltered) && is_Numeric($projectId) &&
            is_Numeric($postCollectionId) && is_Numeric($preCollectionId) && is_Numeric($userId) &&
            is_bool($isFiltered)) {
        $projectData = retrieve_entity_metadata($projectId, 'project');
        if ($projectData) {
            $projectDatasets = find_datasets_in_collection($DBH, $projectData['post_collection_id']);
        }
        if ($userId !== 0) {
            $userGroups = find_user_group_membership($userId, $projectId, TRUE);
            if ($userGroups) {
                $imageGroups = find_assigned_image_groups($userGroups, TRUE);
                if ($imageGroups) {
                    if ($isFiltered) {
                        $imageIdPool = retrieve_image_id_pool($imageGroups, TRUE, TRUE);
                    } else {
                        $imageIdPool = retrieve_image_id_pool($imageGroups, TRUE, FALSE);
                    }
                } else {
                    if ($isFiltered) {
                        $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, TRUE);
                    } else {
                        $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, FALSE);
                    }
                }
            } else {
                if ($isFiltered) {
                    $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, TRUE);
                } else {
                    $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, FALSE);
                }
            }
        } else {
            if ($isFiltered) {
                $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, TRUE);
            } else {
                $imageIdPool = retrieve_image_id_pool($projectDatasets, FALSE, FALSE);
            }
        }
        if ($imageIdPool AND $userId == 0) {
// print "RETURNING: $imageIdPool An Unfiltered Pool of images<br>";
            return $imageIdPool;
        }
        if ($imageIdPool && $userId != 0) {
            $userAnnotations = all_user_annotated_images($userId, $projectId);
            for ($i = 0; $i < count($imageIdPool); $i++) {
                if (in_array($imageIdPool[$i], $userAnnotations)) {
                    array_splice($imageIdPool, $i, 1);
                }
            }
            return $imageIdPool;
        }
    } else {
// print "RETURNING: $randomId Filtered (by Match Enabled) Random Image Id<br>";
        return FALSE;
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Retrieves and returns id's of all images annotated by the user either globall or specific to a
 * project.
 *
 * @param type $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0, iCoast DB row id of the project in question.
 * @return array|boolean On success returns a 1D indexed array where element values contain
 * image_ids.  <b>OR</b><br>On failure returns boolean FALSE.
 */
function all_user_annotated_images($userId, $projectId = 0) {
    if (empty($userId) || !is_numeric($userId) || !is_numeric($projectId)) {
        return false;
    }

    $query = "SELECT image_id FROM annotations WHERE user_id = $userId";
    if ($projectId > 0) {
        $query .= " AND project_id = $projectId";
    }
    $annotatedImagesResult = run_database_query($query);
    if ($annotatedImagesResult) {
        $imageIdsReturn = Array();
        while ($imageId = $annotatedImagesResult->fetch_assoc()) {
            $imageIdsReturn[] = $imageId['image_id'];
        }
// print "RETURNING: imageIdsReturn Array<br>";
        return $imageIdsReturn;
    } else {
        return false;
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Retrieves and returns metadata for an image match.
 *
 * @param type $postCollectionId iCoast DB row id of the collection used as the post image pool.
 * @param type $preCollectionId iCoast DB row id of the collection used as the pre image pool.
 * @param type $postImageId iCoast DB row id of the post image to be checked.
 * @return array|boolean On success returns a 1D associative array where keys and values represent
 * a row in the matches table of the iCoast DB <b>OR</b><br>
 * On failure or no match found returns FALSE.
 */
function retrieve_image_match_data($DBH, $postCollectionId, $preCollectionId, $postImageId) {
    /* print "<p><b>In retrieve_image_match_data function.</b><br>Arguments:<br>$postCollectionId<br>
      $preCollectionId<br>$postImageId</p>"; */

    if (!empty($postCollectionId) AND !empty($preCollectionId) AND !empty($postImageId) AND
            is_numeric($postCollectionId) AND is_numeric($preCollectionId) AND
            is_numeric($postImageId)) {
        $imageMatchDataQuery = "SELECT * FROM matches WHERE post_collection_id = :postCollectionId AND
      pre_collection_id = :preCollectionId AND post_image_id = :postImageId";
        $imageMatchDataParams = array(
            'postCollectionId' => $postCollectionId,
            'preCollectionId' => $preCollectionId,
            'postImageId' => $postImageId
        );
        $STH = run_prepared_query($DBH, $imageMatchDataQuery, $imageMatchDataParams);
        $imageMatchData = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($imageMatchData) > 0) {
            /* print "RETURNING: <pre>";
              print_r($imageMatchData);
              print '</pre>'; */
            return $imageMatchData[0];
        }
    }
// print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Checks if a user has already annotated a specified image either globally or within a specific
 * project.
 *
 * @param int $postImageId iCoast DB row id of the post image to be checked.
 * @param int $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0, iCoast DB row id of the project in question.
 * @return int|boolean On success returns a 0 (no annotation found) or 1 (annotation found)
 * <b>OR</b><br> On failure returns FALSE.
 */
function has_user_annotated_image($DBH, $postImageId, $userId, $projectId = 0) {
// print "<p><b>In has_user_annotated_image function.</b><br>Arguments:<br>$postImageId</p>";
    if (is_numeric($postImageId) && is_numeric($userId) && is_numeric($projectId)) {
        $annotationCheckQuery = "SELECT COUNT(*) FROM annotations WHERE user_id = :userId AND
        image_id = :postImageId";
        $annotationCheckParams = array(
            'userId' => $userId,
            'postImageId' => $postImageId
        );
        if ($projectId > 0) {
            $annotationCheckQuery .= " AND project_id = :projectId";
            $annotationCheckParams['projectId'] = $projectId;
        }
        $STH = run_prepared_query($DBH, $annotationCheckQuery, $annotationCheckParams);
        $annotationCheckResult = $STH->fetchColumn();
//    print $annotationCheckResult;
//    exit;
//    $annotationCheckResult = run_database_query($annotationCheckQuery);
        if ($annotationCheckResult == 0) {
// print "RETURNING: 0 (No existing image annotation found for the user)<br>";
            return 0;
        } else {
// print "RETURNING: 1 (Existing image annotation found for the user)<br>";
            return 1;
        }
    }
// print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds all images groups a user group is permissioned for.
 *
 * Function to find all image groups a/many user group(s) has been permissioned for based on a
 * supplied list of one or more user groups.
 *
 * @param array $userGroups Either 2D where level 1 values = individual group, level 2 values =
 * group data fields, or  1D where values = user group ids
 * @param bool $IdOnly Optional. Default = FALSE. Determines return type. If TRUE only returns the
 * id's of image groups. If FALSE returns full metadata for the image groups.
 * @return array|boolean If $idOnly = TRUE then returns 1D array where values = image group ids.
 * <b>OR</b><br>If $idOnly = FALSE then returns 2D array wher level 1 values = an array for each
 * image group, level 2 keys and values = individual image group fields from image_group_metadata
 * table in iCoast DB <b>OR</b><br> On failure or no image groups found retuns FALSE.
 */
function find_assigned_image_groups($DBH, $userGroups, $IdOnly = FALSE) {
    /* print "<p><b>In find_assigned_image_groups function</b>.<br>Arguments:<br><pre>";
      print_r($userGroups);
      print "</pre></p>"; */

    foreach ($userGroups as $userGroup) {
        if (isset($userGroup['user_group_id'])) {
            $userGroupIds[] = $userGroup['user_group_id'];
        } else {
            $userGroupIds[] = $userGroup;
        }
    }
    $whereString = where_in_string_builder($userGroupIds);

    $imageGroupIdQuery = "SELECT image_group_id FROM user_group_assignments WHERE user_group_id IN "
            . "($whereString)";
//  $imageGroupIdParams['whereString'] = $whereString;
    $imageGroupIdParams = array();
    $STH = run_prepared_query($DBH, $imageGroupIdQuery, $imageGroupIdParams);
    $imageGroups = $STH->fetachAll(PDO::FETCH_ASSOC);
//  $imageGroups = run_database_query($imageGroupIdQuery);
    if (count($imageGroups) > 0) {
        foreach ($imageGroups as $singleImageGroup) {
            $imageGroupIds[] = $singleImageGroup['image_group_id'];
        }
        if ($IdOnly) {
            /* print "RETURNING: <pre>";
              print_r($imageGroupIds);
              print '</pre>'; */
            return $imageGroupIds;
        } else {
// Potential Function
            $whereString = where_in_string_builder($imageGroupIds);
            $imageGroupMetadataQuery = "SELECT * FROM image_group_metadata WHERE image_group_id IN "
                    . "($whereString)";
//      $imageGroupMetadataParams['whereString'] = $whereString;
            $imageGroupMetadataParams = array();
//      $imageGroupMetadata = run_database_query($imageGroupIdQuery);
            $STH = run_prepared_query($DBH, $imageGroupMetadataQuery, $imageGroupMetadataParams);
            $imageGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
            foreach ($imageGroupMetadata as $singleImageGroupMetadata) {
                $imageGroupResults[] = $singleImageGroupMetadata;
            }
            /* print "RETURNING: <pre>";
              print_r($imageGroupResults);
              print '</pre>'; */
            return $imageGroupResults;
        }
    }
//print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds all user groups a user has been assigned to.
 *
 * Function to find all user groups a user has been placed in based on a supplied user Id and
 * optionally filtered by a given project.
 *
 * @param int $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0. iCoast DB row id of the project to be queried.
 * @param type $idOnly Optional. Default = FALSE. Determines return type. If TRUE only returns the
 * id's of user groups. If FALSE returns full metadata for the user groups.
 * @return array|boolean If $idOnly = TRUE then returns 1D array where values = user group ids.
 * <b>OR</b><br>If $idOnly = FALSE then returns 2D array wher level 1 values = an array for each
 * user group, level 2 keys and values = individual user group fields from user_group_metadata
 * table in iCoast DB <b>OR</b><br> On failure or no image groups found retuns FALSE.
 */
function find_user_group_membership($DBH, $userId, $projectId = 0, $idOnly = FALSE) {
//print "<p><b>In find_user_group_membership function.</b><br>Arguments:<br>$userId<br>$projectId</p>";
// Define variables and PHP settings
    $idArray = array();

    if (!is_null($userId) && is_numeric($userId) && is_numeric($projectId) && is_Bool($idOnly)) {
        $userGroupIdsQuery = "SELECT user_group_id FROM user_groups WHERE user_id = :userId";
        $userGroupIdsParams['userId'] = $userId;
//    $userGroupIds = run_database_query($userGroupIdsQuery);
        $STH = run_prepared_query($DBH, $userGroupIdsQuery, $userGroupIdsParams);
        $userGroupIds = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($userGroupIds) > 0) {
            foreach ($userGroupIds as $id) {
                $idArray[] = $id;
            }
// Potential Function
            $whereString = where_in_string_builder($idArray);
            $userGroupDetailsQuery = "SELECT * FROM user_group_metadata WHERE user_group_id IN "
                    . "($whereString)";
//      $userGroupDetailsParams['whereString'] = $whereString;
            $userGroupDetailsParams = array();
            if ($projectId > 0) {
                $userGroupDetailsQuery .= " AND project_id = :projectId";
                $userGroupDetailsParams['projectId'] = $projectId;
            }
            $STH = run_prepared_query($DBH, $userGroupDetailsQuery, $userGroupDetailsParams);
            $userGroupDetails = $STH->fetchAll(PDO::FETCH_ASSOC);
//      $userGroupDetails = run_database_query($userGroupDetailsQuery);

            if (count($userGroupDetails) > 0) {
                foreach ($userGroupDetails as $details) {
                    if ($idOnly) {
                        $userGroupResults[] = $details['user_group_id'];
                    } else {
                        $userGroupResults[] = $details;
                    }
                }
                /* print "RETURNING: <pre>";
                  print_r($userGroupResults);
                  print '</pre>'; */
                return $userGroupResults;
            }
        }
    }
// print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds the details of one image on each side of a supplied image id.
 *
 * Using a supplied image id this function queries the database to find the next and previous image
 * in the photo sequence (as defined in the dataset) and returns an array of metadata for all three
 * images.
 *
 * @param int $imageId iCoast DB row id of the source image.
 * @param int $projectId Optional. Default = NULL. If specified the returned adjacent images will
 * be checked to ensure they contain a valid match in the matches table of the iCoast DB (should
 * only be specified when a post image id is passed as the $imageId argument.
 * @return array|boolean On success returns a 2D array where 1st level values contain an array of
 * metadata for each image and the second level keys/values contain databse column names and row
 * data from the images table of the iCoast DB except where $projectId was passed and no adjacent
 * image was found in which case the 2nd level will contain one key "image_id" with value "0"
 * <b>OR</b><br> On failure returns FALSE.
 */
function find_adjacent_images($DBH, $imageId, $projectId = NULL) {
    // print "In find_adjacent_images. Image ID = $imageId Project ID = $projectId<br>";
    $adjacentSearchRange = 20;
    $adjacentImageArray = Array();
    if (!is_null($imageId) && is_numeric($imageId)) {
        // Retrieve image and dataset metadata
        $imageMetadata = retrieve_entity_metadata($DBH, $imageId, 'image');
        if (isset($imageMetadata) && $imageMetadata) {
            $positionInDataset = $imageMetadata['position_in_set'];
            $datasetId = $imageMetadata['dataset_id'];
            $datasetMetadata = retrieve_entity_metadata($DBH, $datasetId, 'dataset');
        }
        if (!is_null($projectId) && (is_numeric($projectId))) {
            // If project id is supplied retrieve project metadata
            $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
        }
        if (isset($datasetMetadata) && $datasetMetadata) {
            $imagesInDataset = $datasetMetadata['rows_in_set'];

            // Ensure the range in the query doesn't exceed the available rows in the dataset.
            $minPosition = $positionInDataset - $adjacentSearchRange;
            if ($minPosition <= 0) {
                $minPosition = 1;
            }
            $maxPosition = $positionInDataset + $adjacentSearchRange;
            if ($maxPosition > $imagesInDataset) {
                $maxPosition = $imagesInDataset;
            }

            // Query the iCoast DB for all images with position id's in the defined range.
            $adjacentImageQuery = "SELECT * FROM images WHERE dataset_id = :datasetId AND
        position_in_set BETWEEN :minPosition AND :maxPosition";
            $adjacentImageParams = array(
                'datasetId' => $datasetId,
                'minPosition' => $minPosition,
                'maxPosition' => $maxPosition
            );
            $STH = run_prepared_query($DBH, $adjacentImageQuery, $adjacentImageParams);
            $adjacentImageMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//      $adjacentImageQueryResult = run_database_query($adjacentImageQuery);
            if (count($adjacentImageMetadata) > 0) {
//        $adjacentImageMetadata = $adjacentImageQueryResult->fetch_all(MYSQLI_ASSOC);
                // Loop through the array of adjacent images in range in ascending order searching for
                // the image with the next ascending position_in_dataset number from the current image.
                if ($positionInDataset == $maxPosition) {
                    $adjacentImageArray[] = array('image_id' => 0);
                } else {
                    $tempPositionCounter = $positionInDataset + 1;
                    while (TRUE) {
                        // Start Loop
                        foreach ($adjacentImageMetadata as $adjacentImage) {
                            // Initiate a match check if required.
                            if (!is_null($projectId)) {
                                if ($adjacentImage['position_in_set'] == $tempPositionCounter) {
                                    $match = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $adjacentImage['image_id']);
                                    if (!$match || $match['is_enabled'] == 0) {
                                        // Position was found but it didn't pass match check. Break the foreach loop and
                                        // start again with the next position number.
                                        break;
                                    }
                                } else {
                                    // Image is not the one we are looking for. Skip the rest of the foreach loop.
                                    continue;
                                }
                            }
                            // Initiate an image validity check.
                            if ($adjacentImage['position_in_set'] == $tempPositionCounter) {
                                if ($adjacentImage['has_display_file'] == 1 &&
                                        $adjacentImage['is_globally_disabled'] == 0) {
                                    // Passed check. Add image details to $adjacentImageArray, break the while loop.
                                    $adjacentImageArray[] = $adjacentImage;
                                    break 2;
                                } else {
                                    // Position was found but it didn't pass validity check. Break the foreach loop
                                    // and start again with the next position number.
                                    break;
                                }
                            }
                        }
                        // Increment the tempPositionCounter to look for the next image in the set.
                        $tempPositionCounter++;
                        // If we have search all ascending images in the query results and nothing was found
                        // set the array manually with an identified to show no match found.
                        if ($tempPositionCounter == $maxPosition) {
                            $adjacentImageArray[] = array('image_id' => 0);
                            break;
                        }
                    }
                }

                // Add the current image to the middle of the $adjacentImageArray.
                $adjacentImageArray[] = $imageMetadata;

                // Loop through the array of adjacent images in range in ascending order searching for
                // the image with the next decending position_in_dataset number from the current image.
                if ($positionInDataset == $minPosition) {
                    $adjacentImageArray[] = array('image_id' => 0);
                } else {
                    $tempPositionCounter = $positionInDataset - 1;
                    while (TRUE) {
                        // Start Loop
                        foreach ($adjacentImageMetadata as $adjacentImage) {
                            // Initiate a match check if required.
                            if (!is_null($projectId)) {
                                if ($adjacentImage['position_in_set'] == $tempPositionCounter) {
                                    $match = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $adjacentImage['image_id']);
                                    if (!$match || $match['is_enabled'] == 0) {
                                        // Position was found but it didn't pass match check. Break the foreach loop and
                                        // start again with the next position number.
                                        break;
                                    }
                                } else {
                                    // Image is not the one we are looking for. Skip the rest of the foreach loop.
                                    continue;
                                }
                            }
                            // Initiate an image validity check.
                            if ($adjacentImage['position_in_set'] == $tempPositionCounter) {
                                if ($adjacentImage['has_display_file'] == 1 &&
                                        $adjacentImage['is_globally_disabled'] == 0) {
                                    $adjacentImageArray[] = $adjacentImage;
                                    break 2;
                                } else {
                                    // Position was found but it didn't pass validity check. Break the foreach loop
                                    // and start again with the next position number.
                                    break;
                                }
                            }
                        }
                        // Decrement the tempPositionCounter to look for the previous image in the set.
                        $tempPositionCounter--;
                        if ($tempPositionCounter == $minPosition) {
                            // If we have search all ascending images in the query results and nothing was found
                            // set the array manually with an identified to show no match found.
                            $adjacentImageArray[] = array('image_id' => 0);
                            break;
                        }
                    }
                }
                /* print "RETURNING: adjacentImageArray:<br>";
                  print '<pre>';
                  print_r($adjacentImageArray);
                  print '</pre>'; */
                return $adjacentImageArray;
            }
        }
    }
    //print "RETURNING: FALSE";
    return FALSE;
}
