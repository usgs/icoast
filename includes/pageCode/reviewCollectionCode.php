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

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$updateResult = filter_input(INPUT_GET, 'updateResult');

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    header('Location: collectionCreator.php?error=MissingCollectionId');
    exit;
} else if ($collectionMetadata['creator'] != $userId) {
    header('Location: collectionCreator.php?error=InvalidCollection');
    exit;
}

$displaySafeCollectionName = htmlspecialchars($collectionMetadata['name']);
$displaySafeCollectionDescription = restoreSafeHTMLTags(htmlspecialchars($collectionMetadata['description']));

$collectionIdParam['collectionId'] = $collectionId;

$importStatus = collection_creation_stage($collectionMetadata['import_collection_id']);
if ($importStatus != 5) {
    header('Location: collectionCreator.php?error=InvalidCollection');
    exit;
}


switch ($updateResult) {
    case 'success':
        $updateStatus = 'The requested update was completed successfully.';
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


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////// COLLECTION QUERIES ///////////////////////////////////////////


// Find the number of images in the collection

$collectionImageCountQuery = "
        SELECT COUNT(*)
        FROM import_images
        WHERE import_collection_id = :collectionId
            AND position_in_collection IS NOT NULL
        ";

$collectionImageCountResult = run_prepared_query($DBH, $collectionImageCountQuery, $collectionIdParam);
$collectionImageCount = number_format($collectionImageCountResult->fetchColumn());

// Find the geographical range of the collection

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

$collectionGeoRangeResult = run_prepared_query($DBH, $collectionGeoRangeQuery, $collectionIdParam);
$collectionGeoRange = $collectionGeoRangeResult->fetchAll(PDO::FETCH_ASSOC);
$startLocation = build_image_location_string($collectionGeoRange[0], TRUE);
$endLocation = build_image_location_string($collectionGeoRange[1], TRUE);
$collectionGeoRange = $startLocation . ' to ' . $endLocation;

// Find the individual image positions for each distinct date

$collectionImagePositionsQuery = "
            SELECT latitude, longitude
            FROM import_images
            WHERE import_collection_id = :collectionId
                AND position_in_collection IS NOT NULL
            ORDER BY position_in_collection
        ";

$collectionImagePositionsResults = run_prepared_query($DBH, $collectionImagePositionsQuery, $collectionIdParam);
$collectionImagePositions = $collectionImagePositionsResults->fetchAll(PDO::FETCH_ASSOC);


// Find the date range covered by the collection images

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

$collectionDateRangeResult = run_prepared_query($DBH, $collectionDateRangeQuery, $collectionIdParam);
$collectionDateRange = $collectionDateRangeResult->fetchAll(PDO::FETCH_ASSOC);
$startDate = utc_to_timezone($collectionDateRange[0]['image_time'], 'd M Y', $collectionDateRange[0]['longitude']);
$endDate = utc_to_timezone($collectionDateRange[1]['image_time'], 'd M Y', $collectionDateRange[1]['longitude']);
if ($startDate == $endDate) {
    $collectionDateRange = $startDate;
} else {
    $collectionDateRange = $startDate . ' to ' . $endDate;
}


// Find the distinct dates the collection images were taken

$collectionDatesQuery = "
            SELECT DISTINCT(cast(image_time AS DATE)) AS image_date
            FROM import_images
            WHERE import_collection_id = :collectionId
                AND position_in_collection IS NOT NULL
            ORDER BY image_date ASC
            ";

$collectionDatesResult = run_prepared_query($DBH, $collectionDatesQuery, $collectionIdParam);
$collectionDates = $collectionDatesResult->fetchAll(PDO::FETCH_ASSOC);

// Find the geographical region covered by each distinct date
$collectionGeoRangeByDate = '';
foreach ($collectionDates as $collectionDate) {
    $date = $collectionDate['image_date'];

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


    $collectionGeoRangeByDateResult = run_prepared_query($DBH, $collectionGeoRangeByDateQuery, $collectionIdParam);
    $collectionGeoRangeByDateArray = $collectionGeoRangeByDateResult->fetchAll(PDO::FETCH_ASSOC);
    $formattedDate = utc_to_timezone($collectionGeoRangeByDateArray[0]['image_time'], 'd M Y', $collectionGeoRangeByDateArray[0]['longitude']);
    $startLocation = build_image_location_string($collectionGeoRangeByDateArray[0], TRUE);
    $endLocation = build_image_location_string($collectionGeoRangeByDateArray[1], TRUE);
    $collectionGeoRangeByDate .= $formattedDate . ' - ' . $startLocation . ' - ' . $endLocation . '<br>';
}


$javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';
$cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';

$embeddedCSS .= <<<EOL
            #mapReviewWrapper {
                overflow: hidden;
                clear: both;
            }

            .adminStatisticsTable td {
                line-height: 1.2em !important
            }

            .collectionDetailsWrapper td:first-of-type {
                width: 185px;
            }

            .mapButton,
            .acceptDenyButton {
                width: 230px;
            }

            .collectionButton {
                width: 180px;
            }

            #resequenceCollection {
                width: 430px;
            }

            form {
                display: inline;
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

EOL;

$jsCollectionImagePositions = json_encode($collectionImagePositions);
$javaScript .= <<<JS
    var collectionId = $collectionId;
    var reviewMap;
    var polyLineGroup = L.featureGroup();
    var collectionMarkers = L.featureGroup();
    var greenMarker = L.icon({
        iconUrl: 'images/system/greenMarker.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [0, -35]
    });

        var collectionImagePositions = $jsCollectionImagePositions;
        var collectionPolyLinePoints = [];
        $.each(collectionImagePositions, function(key, coordinateArray) {
            var imageLatLng = L.latLng(coordinateArray.latitude, coordinateArray.longitude);
            var marker = L.marker(imageLatLng, {icon: greenMarker, clickable: false});
            collectionMarkers.addLayer(marker);
            collectionPolyLinePoints.push(imageLatLng);
        });

        var collectionPolyLine = L.polyline(collectionPolyLinePoints, {
            color: '#00ff00',
            weight: 5,
            opacity: 0.5,
            smoothFactor: 3
        });
        polyLineGroup.addLayer(collectionPolyLine);

JS;

$jQueryDocumentDotReadyCode .= <<<JS
    reviewMap = L.map('reviewMap', {maxZoom: 16}).setView([37, -94], 4);
    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
    }).addTo(reviewMap);
    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(reviewMap);
    L.control.scale({
        position: 'topright',
        metric: false
    }).addTo(reviewMap);
        reviewMap.fitBounds(collectionPolyLine.getBounds());
        collectionPolyLine.addTo(reviewMap);
        collectionMarkers.addTo(reviewMap);
        collectionMarkers.bringToFront();



    $('#toggleCollectionLine').click(function() {
        if (reviewMap.hasLayer(collectionPolyLine)) {
            reviewMap.removeLayer(collectionPolyLine);
            $('#toggleCollectionLine').text('Show Collection Line');
        } else {
            reviewMap.addLayer(collectionPolyLine);
            $('#toggleCollectionLine').text('Hide Collection Line');
        }
    });

    $('#toggleMarkers').click(function() {
        if (reviewMap.hasLayer(collectionMarkers)) {
            reviewMap.removeLayer(collectionMarkers);
            $('#toggleMarkers').text('Show Collection Images');
        } else {
            reviewMap.addLayer(collectionMarkers);
            $('#toggleMarkers').text('Hide Collection Images');
        }
    });


    $('#editCollection').click(function() {
        window.location.href = 'modifyCollection.php?collectionId=' + collectionId;
    });

   $('#resequenceCollection').click(function() {
        window.location.href = 'refineCollectionImport.php?collectionId=' + collectionId;
    });

    $('#acceptButton').click(function() {
        window.location.href = 'finalizeCollection.php?collectionId=' + collectionId;
    });
JS;
