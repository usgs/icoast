<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$projectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);
$updateResult = filter_input(INPUT_GET, 'updateResult');

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
        $projectMetadata ['is_complete'] == 1) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$displaySafeProjectName = htmlspecialchars($projectMetadata['name']);
$displaySafeProjectDescription = restoreSafeHTMLTags(htmlspecialchars($projectMetadata['description']));
$displaySafePostImageHeader = htmlspecialchars($projectMetadata['post_image_header']);
$displaySafePreImageHeader = htmlspecialchars($projectMetadata['pre_image_header']);

$projectIdParam['projectId'] = $projectMetadata['project_id'];

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 50) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}


switch ($updateResult) {
    case 'success':
        $updateStatus = 'The requested update was completed sucessfully.';
        break;
    case 'failed':
        $updateStatus = 'The requested update failed. No changes have been made.';
        break;
    case 'noChange':
        $updateStatus = 'No changes in data were detected. The database has not been updated.';
        break;
    default:
        $updateStatus = '';
        break;
}

// Find basic details of the project collection
$projectCollectionDetailsQuery = '
    SELECT *, COUNT(*) AS available_images
    FROM import_matches
    WHERE project_id = :projectId
        AND pre_image_id != 0
    LIMIT 1
    ';
$projectCollectionDetailsResult = run_prepared_query($DBH, $projectCollectionDetailsQuery, $projectIdParam);
$projectCollectionDetails = $projectCollectionDetailsResult->fetch(PDO::FETCH_ASSOC);

if ($projectCollectionDetails['available_images'] == 0) {
    if ($projectMetadata['pre_collection_id']) {
        $projectCollectionDetails['pre_collection_id'] = $projectMetadata['pre_collection_id'];
        $projectCollectionDetails['is_pre_collection_imported'] = 0;
    } else {
        $preCollectionQuery = "
            SELECT import_collection_id
            FROM import_collections
            WHERE parent_project_id = :projectId
                AND collection_type = 'pre'
            LIMIT 1";
        $preCollectionResult = run_prepared_query($DBH, $preCollectionQuery, $projectIdParam);
        $projectCollectionDetails['pre_collection_id'] = $preCollectionResult->fetchColumn();
        $projectCollectionDetails['is_pre_collection_imported'] = 1;
    }
    if ($projectMetadata['post_collection_id']) {
        $projectCollectionDetails['post_collection_id'] = $projectMetadata['post_collection_id'];
        $projectCollectionDetails['is_post_collection_imported'] = 0;
    } else {
        $postCollectionQuery = "
            SELECT import_collection_id
            FROM import_collections
            WHERE parent_project_id = :projectId
                AND collection_type = 'post'
            LIMIT 1";
        $postCollectionResult = run_prepared_query($DBH, $postCollectionQuery, $projectIdParam);
        $projectCollectionDetails['post_collection_id'] = $postCollectionResult->fetchColumn();
        $projectCollectionDetails['is_post_collection_imported'] = 1;
    }
}

$preCollectionIdParam['collectionId'] = $projectCollectionDetails['pre_collection_id'];
$numberOfImagesInProject = number_format($projectCollectionDetails['available_images']);

// Determine the type of each collection
if ($projectCollectionDetails['is_post_collection_imported'] == 0) {
    $postCollectionImported = false;
    $postCollectionMetadata = retrieve_entity_metadata($DBH, $projectCollectionDetails['post_collection_id'], 'collection');
    $postCollectionMetadata['type'] = 'Existing';
} else {
    $postCollectionImported = true;
    $postCollectionMetadata = retrieve_entity_metadata($DBH, $projectCollectionDetails['post_collection_id'], 'importCollection');
    $postCollectionMetadata['type'] = 'Imported';
}
if ($projectCollectionDetails['is_pre_collection_imported'] == 0) {
    $preCollectionImported = false;
    $preCollectionMetadata = retrieve_entity_metadata($DBH, $projectCollectionDetails['pre_collection_id'], 'collection');
    $preCollectionMetadata['type'] = 'Existing';
} else {
    $preCollectionImported = true;
    $preCollectionMetadata = retrieve_entity_metadata($DBH, $projectCollectionDetails['pre_collection_id'], 'importCollection');
    $preCollectionMetadata['type'] = 'Imported';
}

$displaySafePostCollectionName = htmlspecialchars($postCollectionMetadata['name']);
$displaySafePostCollectionDescription = restoreSafeHTMLTags(htmlspecialchars($postCollectionMetadata['description']));
$displaySafePreCollectionName = htmlspecialchars($preCollectionMetadata['name']);
$displaySafePreCollectionDescription = restoreSafeHTMLTags(htmlspecialchars($preCollectionMetadata['description']));


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////// PROJECT LEVEL QUERIES ///////////////////////////////////////////////////
// Find the geographical range of the project
if ($postCollectionImported) {
    $projectGeoRangeQuery = "
        SELECT *
        FROM import_images ii
        INNER JOIN import_matches im
            ON im.post_image_id = ii.import_image_id
                AND im.pre_image_id != 0 AND
                    im.project_id = :projectId

        WHERE (
                ii.position_in_collection =
                (SELECT MIN(ii.position_in_collection)
                FROM import_images ii
                INNER JOIN import_matches im
                    ON im.post_image_id = ii.import_image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            OR
                ii.position_in_collection =
                (SELECT MAX(ii.position_in_collection)
                FROM import_images ii
                INNER JOIN import_matches im
                    ON im.post_image_id = ii.import_image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            )
        ORDER BY ii.position_in_collection ASC
    ";
} else {
    $projectGeoRangeQuery = "
        SELECT *
        FROM images i
        INNER JOIN import_matches im
            ON im.post_image_id = i.image_id
                AND im.pre_image_id != 0 AND
                    im.project_id = :projectId

        WHERE (
                i.position_in_collection =
                (SELECT MIN(i.position_in_collection)
                FROM images i
                INNER JOIN import_matches im
                    ON im.post_image_id = i.image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            OR
                i.position_in_collection =
                (SELECT MAX(i.position_in_collection)
                FROM images i
                INNER JOIN import_matches im
                    ON im.post_image_id = i.image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            )
        ORDER BY i.position_in_collection ASC
    ";
}
$projectGeoRangeResult = run_prepared_query($DBH, $projectGeoRangeQuery, $projectIdParam);
$projectGeoRange = $projectGeoRangeResult->fetchAll(PDO::FETCH_ASSOC);
$startLocation = build_image_location_string($projectGeoRange[0], TRUE);
$endLocation = build_image_location_string($projectGeoRange[1], TRUE);
$projectGeoRange = $startLocation . ' to ' . $endLocation;



// Find the individual image positions for the project
if ($postCollectionImported) {
    $projectImagePositionQuery = "
            SELECT latitude, longitude
            FROM import_images ii
            INNER JOIN import_matches im
                ON im.post_image_id = ii.import_image_id
                    AND im.pre_image_id != 0 AND
                        im.project_id = :projectId
            ORDER BY ii.position_in_collection
        ";
} else {
    $projectImagePositionQuery = "
            SELECT latitude, longitude
            FROM images i
            INNER JOIN import_matches im
                ON im.post_image_id = i.image_id
                    AND im.pre_image_id != 0 AND
                        im.project_id = :projectId
            ORDER BY i.position_in_collection
        ";
}
$projectImagePositionResults = run_prepared_query($DBH, $projectImagePositionQuery, $projectIdParam);
$projectImagePositions = $projectImagePositionResults->fetchAll(PDO::FETCH_ASSOC);




// Find the date range convered by the project's post storm images
if ($postCollectionImported) {
    $projectDateRangeQuery = "
        SELECT *
        FROM import_images ii
        INNER JOIN import_matches im
            ON im.post_image_id = ii.import_image_id
                AND im.pre_image_id != 0 AND
                    im.project_id = :projectId

        WHERE (
                ii.image_time =
                (SELECT MIN(ii.image_time)
                FROM import_images ii
                INNER JOIN import_matches im
                    ON im.post_image_id = ii.import_image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            OR
                ii.image_time =
                (SELECT MAX(ii.image_time)
                FROM import_images ii
                INNER JOIN import_matches im
                    ON im.post_image_id = ii.import_image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            )
        ORDER BY ii.image_time ASC
    ";
} else {
    $projectDateRangeQuery = "
        SELECT *
        FROM images i
        INNER JOIN import_matches im
            ON im.post_image_id = i.image_id
                AND im.pre_image_id != 0 AND
                    im.project_id = :projectId

        WHERE (
                i.image_time =
                (SELECT MIN(i.image_time)
                FROM images i
                INNER JOIN import_matches im
                    ON im.post_image_id = i.image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            OR
                i.image_time =
                (SELECT MAX(i.image_time)
                FROM images i
                INNER JOIN import_matches im
                    ON im.post_image_id = i.image_id
                        AND im.pre_image_id != 0 AND
                            im.project_id = :projectId)
            )
        ORDER BY i.image_time ASC
    ";
}
$projectDateRangeResult = run_prepared_query($DBH, $projectDateRangeQuery, $projectIdParam);
$projectDateRange = $projectDateRangeResult->fetchAll(PDO::FETCH_ASSOC);
$startDate = utc_to_timezone($projectDateRange[0]['image_time'], 'd M Y', $projectDateRange[0]['longitude']);
$endDate = utc_to_timezone($projectDateRange[1]['image_time'], 'd M Y', $projectDateRange[1]['longitude']);
if ($startDate == $endDate) {
    $projectDateRange = $startDate;
} else {
    $projectDateRange = $startDate . ' to ' . $endDate;
}



// Find the distinct dates the project's post event images were taken
if ($postCollectionImported) {
    $projectDatesQuery = "
            SELECT DISTINCT(cast(image_time as date)) as image_date
            FROM import_images ii
            INNER JOIN import_matches im
                ON im.post_image_id = ii.import_image_id
                    AND im.pre_image_id != 0
                    AND im.project_id = :projectId
            ORDER BY image_date ASC
            ";
} else {
    $projectDatesQuery = "
            SELECT DISTINCT(cast(image_time as date)) as image_date
            FROM images i
            INNER JOIN import_matches im
                ON im.post_image_id = i.image_id
                    AND im.pre_image_id != 0
                    AND im.project_id = :projectId
            ORDER BY image_date ASC
            ";
}
$projectDatesResult = run_prepared_query($DBH, $projectDatesQuery, $projectIdParam);
$projectDates = $projectDatesResult->fetchAll(PDO::FETCH_ASSOC);

// Find the geographical region covered by each distinct date
$projectGeoRangeByDate = '';
foreach ($projectDates as $projectDate) {
    $projectDate = $projectDate['image_date'];
    if ($postCollectionImported) {
        $projectGeoRangeByDateQuery = "
            SELECT *
            FROM import_images ii
            INNER JOIN import_matches im
                ON im.post_image_id = ii.import_image_id
                    AND im.pre_image_id != 0 AND
                        im.project_id = :projectId

            WHERE (
                    ii.position_in_collection =
                    (SELECT MIN(ii.position_in_collection)
                    FROM import_images ii
                    INNER JOIN import_matches im
                        ON im.post_image_id = ii.import_image_id
                            AND im.pre_image_id != 0 AND
                                im.project_id = :projectId
                    WHERE cast(ii.image_time as date) = '$projectDate')
                OR
                    ii.position_in_collection =
                    (SELECT MAX(ii.position_in_collection)
                    FROM import_images ii
                    INNER JOIN import_matches im
                        ON im.post_image_id = ii.import_image_id
                            AND im.pre_image_id != 0 AND
                                im.project_id = :projectId
                    WHERE cast(ii.image_time as date) = '$projectDate')
                )
            ORDER BY ii.position_in_collection ASC
        ";
    } else {
        $projectGeoRangeByDateQuery = "
            SELECT *
            FROM images i
            INNER JOIN import_matches im
                ON im.post_image_id = i.image_id
                    AND im.pre_image_id != 0 AND
                        im.project_id = :projectId

            WHERE (
                    i.position_in_collection =
                    (SELECT MIN(i.position_in_collection)
                    FROM images i
                    INNER JOIN import_matches im
                        ON im.post_image_id = i.image_id
                            AND im.pre_image_id != 0 AND
                                im.project_id = :projectId
                    WHERE cast(i.image_time as date) = '$projectDate')
                OR
                    i.position_in_collection =
                    (SELECT MAX(i.position_in_collection)
                    FROM images i
                    INNER JOIN import_matches im
                        ON im.post_image_id = i.image_id
                            AND im.pre_image_id != 0 AND
                                im.project_id = :projectId
                    WHERE cast(i.image_time as date) = '$projectDate')
                )
            ORDER BY i.position_in_collection ASC
        ";
    }

    $projectGeoRangeByDateResult = run_prepared_query($DBH, $projectGeoRangeByDateQuery, $projectIdParam);
    $projectGeoRangeByDateArray = $projectGeoRangeByDateResult->fetchAll(PDO::FETCH_ASSOC);
    $formattedDate = utc_to_timezone($projectGeoRangeByDateArray[0]['image_time'], 'd M Y', $projectGeoRangeByDateArray[0]['longitude']);
    $startLocation = build_image_location_string($projectGeoRangeByDateArray[0], TRUE);
    $endLocation = build_image_location_string($projectGeoRangeByDateArray[1], TRUE);
    $projectGeoRangeByDate .= $formattedDate . ' - ' . $startLocation . ' - ' . $endLocation . '<br>';
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////// POST COLLECTION QUERIES ///////////////////////////////////////////
$targetArray = array('post', 'pre');
foreach ($targetArray as $target) {
    $collectionImageCountVAR = $target . 'CollectionImageCount';
    $collectionImportedVar = $target . 'CollectionImported';
    $collectionGeoRangeVar = $target . 'CollectionGeoRange';
    $collectionImagePositionsVar = $target . 'CollectionImagePositions';
    $collectionDateRangeVar = $target . 'CollectionDateRange';
    $collectionGeoRangeByDateVar = $target . 'CollectionGeoRangeByDate';
    $collectionImagePositionsByDateVar = $target . 'collectionImagePositionsByDate';


    $collectionIdParam['collectionId'] = $projectCollectionDetails[$target . '_collection_id'];

// Find the number of images in the post-event collection
    if ($$collectionImportedVar) {
        $collectionImageCountQuery = "
        SELECT COUNT(*)
        FROM import_images
        WHERE import_collection_id = :collectionId
            AND position_in_collection IS NOT NULL
        ";
    } else {
        $collectionImageCountQuery = "
        SELECT COUNT(*)
        FROM images
        WHERE collection_id = :collectionId
            AND is_globally_disabled = 0
        ";
    }
    $collectionImageCountResult = run_prepared_query($DBH, $collectionImageCountQuery, $collectionIdParam);
    $$collectionImageCountVAR = number_format($collectionImageCountResult->fetchColumn());

// Find the geographical range of the post collection
    if ($$collectionImportedVar) {
        $collectionGeoRangeQuery = "
        SELECT *
        FROM import_images
        WHERE (
                position_in_collection =
                (SELECT MIN(position_in_collection)
                FROM import_images
                WHERE import_collection_id = :collectionId
                    AND position_in_collection IS NOT NULL)
            OR
                position_in_collection =
                (SELECT MAX(position_in_collection)
                FROM import_images
                WHERE import_collection_id = :collectionId
                    AND position_in_collection IS NOT NULL)
            )
            AND import_collection_id = :collectionId
        ORDER BY position_in_collection ASC
    ";
    } else {
        $collectionGeoRangeQuery = "
        SELECT *
        FROM images
        WHERE (
                position_in_collection =
                (SELECT MIN(position_in_collection)
                FROM images
                WHERE collection_id = :collectionId
                    AND is_globally_disabled = 0)
            OR
                position_in_collection =
                (SELECT MAX(position_in_collection)
                FROM images
                WHERE collection_id = :collectionId
                    AND is_globally_disabled = 0)
            )
            AND collection_id = :collectionId
            AND is_globally_disabled = 0
        ORDER BY position_in_collection ASC
    ";
    }
    $collectionGeoRangeResult = run_prepared_query($DBH, $collectionGeoRangeQuery, $collectionIdParam);
    $collectionGeoRange = $collectionGeoRangeResult->fetchAll(PDO::FETCH_ASSOC);
    $startLocation = build_image_location_string($collectionGeoRange[0], TRUE);
    $endLocation = build_image_location_string($collectionGeoRange[1], TRUE);
    $$collectionGeoRangeVar = $startLocation . ' to ' . $endLocation;

    // Find the individual image positions for each distinct date
    if ($$collectionImportedVar) {
        $collectionImagePositionsQuery = "
            SELECT latitude, longitude
            FROM import_images
            WHERE import_collection_id = :collectionId
                AND position_in_collection IS NOT NULL
            ORDER BY position_in_collection
        ";
    } else {
        $collectionImagePositionsQuery = "
            SELECT latitude, longitude
            FROM images
            WHERE collection_id = :collectionId
                AND is_globally_disabled = 0
            ORDER BY position_in_collection
        ";
    }
    $collectionImagePositionsResults = run_prepared_query($DBH, $collectionImagePositionsQuery, $collectionIdParam);
    $$collectionImagePositionsVar = $collectionImagePositionsResults->fetchAll(PDO::FETCH_ASSOC);


// Find the date range convered by the post-event images
    if ($$collectionImportedVar) {
        $collectionDateRangeQuery = "
        SELECT *
        FROM import_images
        WHERE (
                image_time =
                (SELECT MIN(image_time)
                FROM import_images
                WHERE import_collection_id = :collectionId
                    AND position_in_collection IS NOT NULL)
            OR
                image_time =
                (SELECT MAX(image_time)
                FROM import_images
                WHERE import_collection_id = :collectionId
                    AND position_in_collection IS NOT NULL)
            )
            AND import_collection_id = :collectionId
            AND position_in_collection IS NOT NULL
        ORDER BY image_time ASC
    ";
    } else {
        $collectionDateRangeQuery = "
        SELECT *
        FROM images
        WHERE (
                image_time =
                (SELECT MIN(image_time)
                FROM images
                WHERE collection_id = :collectionId
                    AND is_globally_disabled = 0)
            OR
                image_time =
                (SELECT MAX(image_time)
                FROM images
                WHERE collection_id = :collectionId
                    AND is_globally_disabled = 0)
            )
            AND collection_id = :collectionId
            AND is_globally_disabled = 0
        ORDER BY image_time ASC
    ";
    }
    $collectionDateRangeResult = run_prepared_query($DBH, $collectionDateRangeQuery, $collectionIdParam);
    $collectionDateRange = $collectionDateRangeResult->fetchAll(PDO::FETCH_ASSOC);
    $startDate = utc_to_timezone($collectionDateRange[0]['image_time'], 'd M Y', $collectionDateRange[0]['longitude']);
    $endDate = utc_to_timezone($collectionDateRange[1]['image_time'], 'd M Y', $collectionDateRange[1]['longitude']);
    if ($startDate == $endDate) {
        $$collectionDateRangeVar = $startDate;
    } else {
        $$collectionDateRangeVar = $startDate . ' to ' . $endDate;
    }



// Find the distinct dates the post event's images were taken
    if ($$collectionImportedVar) {
        $collectionDatesQuery = "
            SELECT DISTINCT(cast(image_time as date)) as image_date
            FROM import_images
            WHERE import_collection_id = :collectionId
                AND position_in_collection IS NOT NULL
            ORDER BY image_date ASC
            ";
    } else {
        $collectionDatesQuery = "
            SELECT DISTINCT(cast(image_time as date)) as image_date
            FROM images
            WHERE collection_id = :collectionId
                AND is_globally_disabled = 0
            ORDER BY image_date ASC
            ";
    }
    $collectionDatesResult = run_prepared_query($DBH, $collectionDatesQuery, $collectionIdParam);
    $collectionDates = $collectionDatesResult->fetchAll(PDO::FETCH_ASSOC);

// Find the geographical region covered by each distinct date
    $$collectionGeoRangeByDateVar = '';
    foreach ($collectionDates as $collectionDate) {
        $date = $collectionDate['image_date'];
        if ($$collectionImportedVar) {
            $collectionGeoRangeByDateQuery = "
            SELECT *
            FROM import_images
            WHERE (
                    position_in_collection =
                    (SELECT MIN(position_in_collection)
                    FROM import_images
                    WHERE import_collection_id = :collectionId
                        AND position_in_collection IS NOT NULL
                        AND cast(image_time as date) = '$date')
                OR
                    position_in_collection =
                    (SELECT MAX(position_in_collection)
                    FROM import_images
                    WHERE import_collection_id = :collectionId
                        AND position_in_collection IS NOT NULL
                        AND cast(image_time as date) = '$date')
                )
                AND import_collection_id = :collectionId
            ORDER BY position_in_collection ASC
        ";
        } else {
            $collectionGeoRangeByDateQuery = "
            SELECT *
            FROM images
            WHERE (
                    position_in_collection =
                    (SELECT MIN(position_in_collection)
                    FROM images
                    WHERE collection_id = :collectionId
                        AND is_globally_disabled = 0
                        AND cast(image_time as date) = '$date')
                OR
                    position_in_collection =
                    (SELECT MAX(position_in_collection)
                    FROM images
                    WHERE collection_id = :collectionId
                        AND is_globally_disabled = 0
                        AND cast(image_time as date) = '$date')
                )
                AND collection_id = :collectionId
                AND is_globally_disabled = 0
            ORDER BY position_in_collection ASC
        ";
        }

        $collectionGeoRangeByDateResult = run_prepared_query($DBH, $collectionGeoRangeByDateQuery, $collectionIdParam);
        $collectionGeoRangeByDateArray = $collectionGeoRangeByDateResult->fetchAll(PDO::FETCH_ASSOC);
        $formattedDate = utc_to_timezone($collectionGeoRangeByDateArray[0]['image_time'], 'd M Y', $collectionGeoRangeByDateArray[0]['longitude']);
        $startLocation = build_image_location_string($collectionGeoRangeByDateArray[0], TRUE);
        $endLocation = build_image_location_string($collectionGeoRangeByDateArray[1], TRUE);
        $$collectionGeoRangeByDateVar .= $formattedDate . ' - ' . $startLocation . ' - ' . $endLocation . '<br>';
    }
}

if ($postCollectionImported) {
    $postCollectionButtonHTML = '
        <button type="button" id="editPostCollection" class="clickableButton enlargedClickableButton collectionButton">
            Edit Collection Text
        </button>
        <button type="button" id="deletePostCollection" class="clickableButton enlargedClickableButton collectionButton">
            Delete Collection
        </button>
        <button type="button" id="resequencePostCollection" class="clickableButton enlargedClickableButton">
            Refine And Resequence Collection Images
        </button>
    ';
} else {
    $postCollectionButtonHTML = '
        <button type="button" id="removePostCollection" class="clickableButton enlargedClickableButton collectionButton">
            Remove Collection
        </button>
    ';
}

if ($preCollectionImported) {
    $preCollectionButtonHTML = '
        <button type="button" id="editPreCollection" class="clickableButton enlargedClickableButton collectionButton">
            Edit Collection Text
        </button>
        <button type="button" id="deletePreCollection" class="clickableButton enlargedClickableButton collectionButton">
            Delete Collection
        </button>
        <button type="button" id="resequencePreCollection" class="clickableButton enlargedClickableButton">
            Refine And Resequence Collection Images
        </button>
    ';
} else {
    $preCollectionButtonHTML = '
        <button type="button" id="removePreCollection" class="clickableButton enlargedClickableButton collectionButton">
            Remove Collection
        </button>
    ';
}

$matchRadius =$projectMetadata['matching_progress'];
$matchRadiusOptions = '';
for ($i = 200; $i <= 1500; $i += 100) {
    if ($i != $matchRadius) {
        $matchRadiusOptions .= '<option value="' . $i . '">' . $i . 'm</option>';
    } else {
        $matchRadiusOptions .= '<option value="' . $i . '" selected>' . $i . 'm</option>';
    }
}

if ($matchRadius == 400) {
    $matchRadiusOptionText = 'default';
} else {
    $matchRadiusOptionText = 'chosen';
}

$numberOfImagesQuery = '
        SELECT COUNT(*)
        FROM import_matches
        WHERE project_id = :projectId';
$numberOfImagesResult = run_prepared_query($DBH, $numberOfImagesQuery, $projectIdParam);
$numberOfPotentialMatches = $numberOfImagesResult->fetchColumn();
if ($numberOfPotentialMatches > 0) {
    $numberOfImagesWithMatchesQuery = '
        SELECT COUNT(*)
        FROM import_matches
        WHERE project_id = :projectId
            AND pre_image_id != 0';
    $numberOfImagesWithMatchesResult = run_prepared_query($DBH, $numberOfImagesWithMatchesQuery, $projectIdParam);
    $numberOfMatches = $numberOfImagesWithMatchesResult->fetchColumn();
    $percentageOfMatches = floor(($numberOfMatches / $numberOfPotentialMatches) * 100);
} else {
    $numberOfMatches = 0;
    $percentageOfMatches = 0;
}



$javaScriptLinkArray[] = 'scripts/leaflet.js';
$cssLinkArray[] = 'css/leaflet.css';

$embeddedCSS .= <<<EOL
            #projectReviewWrapper,
            #collectionsReviewWrapper,
            #collectionsReviewControlsWrapper,
            #mapReviewWrapper {
                overflow: hidden;
                clear: both;
            }

            #projectDetailsWrapper,
            #projectOptionsWrapper,
            .collectionDetailsWrapper {
                overflow: hidden;
                margin: 10px 0px;
                float: left;
            }

            #projectDetailsWrapper {
                width:70%;
            }

            #projectOptionsWrapper {
                width:30%;
            }

            .collectionDetailsWrapper {
                width: 50%;
            }


            .adminStatisticsTable td {
                line-height: 1.2em !important
            }

            .projectDetailsWrapper td:first-of-type {
                width: 250px;
            }

            .collectionDetailsWrapper td:first-of-type {
                width: 185px;
            }

            .projectReviewButton {
                width: 200px;
            }

            .mapButton,
            .acceptDenyButton {
                width: 200px;
            }

            .collectionButton {
                width: 180px;
            }

            #resequencePostCollection,
            #resequencePreCollection {
                width: 405px;
            }

            /*MAP CSS*/

            #reviewMapWrapper {
                position: relative;
            }

            #reviewMap {
                clear: both;
                height: 500px;
                position: relative;
            }

            .adminMapLegend {
                width: 150px;
                bottom: 20px;
                padding: 5px;
            }


            #reviewMapWrapper .adminMapLegendRowIcon {
                width: 14px;
            }

            #reviewMapWrapper .adminMapLegendRowIcon div {
                width: 14px;
                height: 14px;
            }

            .adminMapLegendSingleRowText {
                width: auto;
            }

            .confirmOptionButton {
                width: 375px;
            }
EOL;

$jsProjectImagePositions = json_encode($projectImagePositions);
$jsPostCollectionImagePositions = json_encode($postCollectionImagePositions);
$jsPreCollectionImagePositions = json_encode($preCollectionImagePositions);
$javaScript .= <<<EOL
    var projectId = {$projectMetadata['project_id']};
    var preCollectionId = {$projectCollectionDetails['pre_collection_id']};
    var postCollectionId = {$projectCollectionDetails['post_collection_id']};
    var availableImages = {$projectCollectionDetails['available_images']};
    var reviewMap;
    var polyLineGroup = L.featureGroup();
    var projectMarkers = L.featureGroup();
    var greenMarker = L.icon({
        iconUrl: 'images/system/greenMarker.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [0, -35]
    });

    if (availableImages > 0) {
        var projectImagePositions = $jsProjectImagePositions;
        var projectPolyLinePoints = [];
        $.each(projectImagePositions, function(key, coordinateArray) {
            var imageLatLng = L.latLng(coordinateArray.latitude, coordinateArray.longitude);
            var marker = L.marker(imageLatLng, {icon: greenMarker, clickable: false});
            projectMarkers.addLayer(marker);
            projectPolyLinePoints.push(imageLatLng);
        });

        var projectPolyLine = L.polyline(projectPolyLinePoints, {
            color: '#00ff00',
            weight: 5,
            opacity: 0.5,
            smoothFactor: 3
        });
        polyLineGroup.addLayer(projectPolyLine);
    }

    var postCollectionImagePositions = $jsPostCollectionImagePositions;
    var postCollectionPolyLinePoints = [];
    $.each(postCollectionImagePositions, function(key, coordinateArray) {
        postCollectionPolyLinePoints.push(L.latLng(coordinateArray.latitude, coordinateArray.longitude));
    });
    var postCollectionPolyLine = L.polyline(postCollectionPolyLinePoints, {
        color: '#ff0000',
        weight: 5,
        opacity: 0.5,
        smoothFactor: 3
    });
    polyLineGroup.addLayer(postCollectionPolyLine);

    var preCollectionImagePositions = $jsPreCollectionImagePositions;
    var preCollectionPolyLinePoints = [];
    $.each(preCollectionImagePositions, function(key, coordinateArray) {
        preCollectionPolyLinePoints.push(L.latLng(coordinateArray.latitude, coordinateArray.longitude));
    });
    var preCollectionPolyLine = L.polyline(preCollectionPolyLinePoints, {
        color: '#5555ff',
        weight: 5,
        opacity: 0.5,
        smoothFactor: 3
    });
    polyLineGroup.addLayer(preCollectionPolyLine);
EOL;

$jQueryDocumentDotReadyCode .= <<<EOL
    reviewMap = L.map('reviewMap', {maxZoom: 16}).setView([37, -94], 4);
    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
    }).addTo(reviewMap);
    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(reviewMap);
    L.control.scale({
        position: 'topright',
        metric: false
    }).addTo(reviewMap);
    if (availableImages > 0) {
        reviewMap.fitBounds(projectPolyLine.getBounds());
        projectPolyLine.addTo(reviewMap);
        projectMarkers.addTo(reviewMap);
        projectMarkers.bringToFront();
    } else {
        reviewMap.fitBounds(polyLineGroup.getBounds());
        polyLineGroup.addTo(reviewMap);
        $('#toggleProjectLine, #toggleMarkers, #liveButton, #acceptButton, #liveButtonlabel')
                .addClass('disabledClickableButton')
                .attr('disabled', 'disabled');
        $('.projectRangeDetails').hide();
        $('#noProjectImagesError').show();
    }


    $('#liveButton, #focusedButton').prop('checked', false);

    $('#toggleProjectLine').click(function() {
        if (reviewMap.hasLayer(projectPolyLine)) {
            reviewMap.removeLayer(projectPolyLine);
            $('#toggleProjectLine').text('Show Project Line');
        } else {
            reviewMap.addLayer(projectPolyLine);
            $('#toggleProjectLine').text('Hide Project Line');
        }
    });

    $('#toggleMarkers').click(function() {
        if (reviewMap.hasLayer(projectMarkers)) {
            reviewMap.removeLayer(projectMarkers);
            $('#toggleMarkers').text('Show Project Images');
        } else {
            reviewMap.addLayer(projectMarkers);
            $('#toggleMarkers').text('Hide Project Images');
        }
    });

    $('#togglePostLine').click(function() {
        if (reviewMap.hasLayer(postCollectionPolyLine)) {
            reviewMap.removeLayer(postCollectionPolyLine);
            $('#togglePostLine').text('Show Post-Event Line');
        } else {
            reviewMap.addLayer(postCollectionPolyLine);
            $('#togglePostLine').text('Hide Post-Event Line');
        }
    });

    $('#togglePreLine').click(function() {
        if (reviewMap.hasLayer(preCollectionPolyLine)) {
            reviewMap.removeLayer(preCollectionPolyLine);
            $('#togglePreLine').text('Show Pre-Event Line');
        } else {
            reviewMap.addLayer(preCollectionPolyLine);
            $('#togglePreLine').text('Hide Pre-Event Line');
        }
    });



    $('#editProjectDetailsButton').click(function() {
        window.location.href = 'modifyProject.php?projectId=' + projectId;
    });

    $('#previewQuestionsButton').click(function() {
        window.open('taskPreview.php?projectId=' + projectId);
    });

    $('#editQuestionsButton').click(function() {
        window.location.href = 'questionBuilder.php?projectId=' + projectId;
    });




    $('#editPostCollection').click(function() {
        window.location.href = 'modifyProjectCollection.php?projectId=' + projectId + '&edit=post';
    });

    $('#deletePostCollection, #removePostCollection').click(function() {
        window.location.href = 'modifyProjectCollection.php?projectId=' + projectId + '&delete=post';
    });


    $('#editPreCollection').click(function() {
        window.location.href = 'modifyProjectCollection.php?projectId=' + projectId + '&edit=pre';
    });

    $('#deletePreCollection, #removePreCollection').click(function() {
        window.location.href = 'modifyProjectCollection.php?projectId=' + projectId + '&delete=pre';
    });

    $('#resequencePostCollection').click(function() {
        window.location.href = 'refineProjectImport.php?projectId=' + projectId + '&collectionType=post';
    });

    $('#resequencePreCollection').click(function() {
        window.location.href = 'refineProjectImport.php?projectId=' + projectId + '&collectionType=pre';
    });

    $('#liveButton').click(function() {
        console.log('click');
        if ($(this).prop('checked') === true) {
            $('#focusedButton').removeAttr('disabled');
            $('#focusedButtonLabel').removeClass('disabledClickableButton');
        } else {
            $('#focusedButton').prop('checked', false);
            $('#focusedButton').attr('disabled', 'disabled');
            $('#focusedButtonLabel').addClass('disabledClickableButton');
        }
    });

    $('#acceptButton').click(function() {
        var makeLive = 0;
        var makeFocus = 0;
        if ($('#liveButton').prop('checked') === true) {
            makeLive = 1;
            if ($('#focusedButton').prop('checked') === true) {
                makeFocus = 1;
            }
        }
        window.location.href = 'finalizeProject.php?projectId=' + projectId + '&makeLive=' + makeLive.toString() + '&makeFocus=' + makeFocus.toString();
    });
EOL;
