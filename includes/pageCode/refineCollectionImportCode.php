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
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$sequenceCollectionFlag = filter_input(INPUT_GET, 'sequenceCollection', FILTER_VALIDATE_BOOLEAN);
$getDisplayType = filter_input(INPUT_GET, 'displayType');
$photosPerPage = filter_input(INPUT_GET, 'photosPerPage', FILTER_VALIDATE_INT);
$getStartPhotoPosition = filter_input(INPUT_GET, 'startPhotoPosition', FILTER_VALIDATE_INT);


$referringPage = detect_pageName($_SERVER['HTTP_REFERER']);

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    exit;
}

$importStatus = collection_creation_stage($collectionMetadata['import_collection_id']);
if ($importStatus != 3 && $importStatus != 5) {
    header('Location: collectionCreator.php?error=InvalidCollection');
    exit;
}


if ($sequenceCollectionFlag) {
    $setSequencingQuery = '
        UPDATE import_collections
        SET sequencing_stage = 1
        WHERE import_collection_id = :collectionId
        LIMIT 1';
    $setSequencingParams['collectionId'] = $collectionId;
    $setSequencingResult = run_prepared_query($DBH, $setSequencingQuery, $setSequencingParams);
    if ($setSequencingResult->rowCount() == 1) {
        header("Location: sequenceCollection.php?collectionId=$collectionId");
        exit;
    }
}




if (isset($getDisplayType) &&
        ($getDisplayType == 'Show Thumbnails' || $getDisplayType == 'Show Map')) {
    $displayType = $getDisplayType;
    $javaScript .= "var displayType = \"$displayType\";\n\r";
} else {
    $displayType = 'Show Map';
    $javaScript .= "var displayType = \"Show Map\";\n\r";
}

$photoCountQuery = "
    SELECT COUNT(*) AS result_count
    FROM import_images
    WHERE import_collection_id = $collectionId
    ";
$photoCountResults = run_prepared_query($DBH, $photoCountQuery);
$photoCount = $photoCountResults->fetchColumn();
$formattedPhotoCountResults = number_format($photoCount);
if ($photoCount == 1) {
    $photoCountText = 'photo';
} else {
    $photoCountText = 'photos';
}




if ($displayType == 'Show Thumbnails') {


    $photosPerPageSelectHTML = <<<EOL
            <option value="25">25 Photos Per Page</option>
            <option value="50">50 Photos Per Page</option>
            <option value="100">100 Photos Per Page</option>
            <option value="250">250 Photos Per Page</option>
            <option value="500">500 Photos Per Page</option>
EOL;

    switch ($photosPerPage) {
        case '25':
        case '50':
        case '100':
        case '250':
        case '500':
            $photosPerPageSelectHTML = str_replace('"' . $photosPerPage . '">', '"' . $photosPerPage . '" selected>', $photosPerPageSelectHTML);
            break;
        default:
            $photosPerPage = 25;
            $photosPerPageSelectHTML = str_replace('"25">', '"25" selected>', $photosPerPageSelectHTML);
    }


    $startPhotoPosition = 0;

    if (!empty($getStartPhotoPosition)) {
        $startPhotoPosition = floor($getStartPhotoPosition / $photosPerPage) * $photosPerPage;
    }


    $photoQuery = "
        SELECT *
        FROM import_images
        WHERE import_collection_id = $collectionId
        ORDER BY import_image_id ASC
        LIMIT $startPhotoPosition, $photosPerPage
        ";
    $photoResults = $DBH->query($photoQuery)->fetchAll(PDO::FETCH_ASSOC);

    $columnCount = 0;
    $photoGridHTML = '<div class="adminPhotoThumbnailRow">';
    foreach ($photoResults as $photo) {
        $photoLocation = build_image_location_string($photo, TRUE);
        if ($photo['is_disabled'] == 0) {
            $photoStatus = 'Enabled';
            $photoStatusHighlight = 'green';
        } else {
            $photoStatus = 'Disabled';
            $photoStatusHighlight = 'red';
        }

        if ($columnCount == 5) {
            $photoGridHTML .= '</div><div class="adminPhotoThumbnailRow">';
            $columnCount = 0;
        }
        $photoGridHTML .= <<<EOL
                    <div id="photo{$photo['import_image_id']}Cell" class="adminPhotoThumbnailCell">
                        <div class="adminPhotoThumbnailWrapper">
                            <img src="{$photo['thumb_url']}" title="Click the image to toggle its status between Enabled and Disabled" style="border-color: $photoStatusHighlight" />
                        </div>
                        <span class="adminPhotoThumbnailMetadata"><span>Status:</span> <span id="StatusText">$photoStatus</span></span>
                        <span class="adminPhotoThumbnailMetadata"><span>Location:</span> $photoLocation</span>
                    </div>

EOL;

        $jQueryDocumentDotReadyCode .= <<<EOL
                 $('#photo{$photo['import_image_id']}Cell').data({
                    'photoId': {$photo['import_image_id']},
                    'currentStatus': {$photo['is_disabled']}
                });

EOL;

        $columnCount++;
    } // End foreach photo loop

    $numberOfPhotoPages = floor($photoCount / $photosPerPage + 1);
    $currentPageNumber = floor(($startPhotoPosition / $photosPerPage) + 1);
    $pageJumpSelectHTML = '';
    for ($i = 1; $i <= $numberOfPhotoPages; $i ++) {
        if ($i != $currentPageNumber) {
            $pageJumpSelectHTML .= "<option value=\"$i\">Jump To Page $i</option>";
        }
    }





    $photoGridHTML .= '</div>';
    $thumbnailGridControlHTML = <<<EOL
                <div class="thumbnailControlWrapper">
                    <div>
                        <input type="button" class="firstPageButton clickableButton disabledClickableButton" value="<<" disabled />
                        <input type="button" class="previousPageButton clickableButton disabledClickableButton" value="<" disabled />
                        <select class="photosPerPageSelect formInputStyle">
                            $photosPerPageSelectHTML
                        </select>
                    <p class="pageNumberInfo">Page $currentPageNumber of $numberOfPhotoPages</p>
                        <input type="button" class="lastPageButton clickableButton disabledClickableButton" value=">>" disabled />
                        <input type="button" class="nextPageButton clickableButton disabledClickableButton" value=">" disabled />
                        <select class="pageJumpSelect formInputStyle">
                            $pageJumpSelectHTML
                        </select>
                    </div>
                </div>
EOL;

    $contentHTML = <<<EOL
                <p>Click on a photo to toggle its status between Enabled and Disabled.<br>
                    A <span style="color: green">green</span> border indicates a photo is enabled. A <span style="color: red">red</span> border indicates a photo is disabled.<br>
                    </p>
                <div id="adminPhotoThumbnailGrid">
                    $thumbnailGridControlHTML
                    $photoGridHTML
                    $thumbnailGridControlHTML
                </div>
EOL;

    $javaScript .= <<<JS
                var collectionId = $collectionId;
                var photosPerPage = $photosPerPage;
                var startPhotoPosition = $startPhotoPosition;
                var numberOfPhotos = $photoCount;
                var currentPageNumber = $currentPageNumber;
                var numberOfPhotoPages = $numberOfPhotoPages;


                $(window).load(function() {
                    $('.adminPhotoThumbnailRow').each(function() {
                        var row = $(this);
                        var maxImageHeight = 0;
                        row.find('.adminPhotoThumbnailWrapper').each(function() {
                            if ($(this).find('img').height() > maxImageHeight) {
                                 maxImageHeight = $(this).find('img').height();
                             };
                        });
                        row.find('.adminPhotoThumbnailWrapper').each(function() {
                            if ($(this).find('img').height() < maxImageHeight) {
                                var padding = (maxImageHeight - $(this).find('img').height()) / 2;
                                $(this).css("padding-top", padding + "px");
                                $(this).css("padding-bottom", padding + "px");
                            }
                        });
                    });
                });
JS;

    $jQueryDocumentDotReadyCode .= <<<JS
                if (numberOfPhotoPages == 1) {
                    $('.pageJumpSelect').hide();
                }

                if ((numberOfPhotos / photosPerPage > 1) && (startPhotoPosition < numberOfPhotos - (numberOfPhotos % photosPerPage))) {
                    $('.lastPageButton, .nextPageButton').removeClass('disabledClickableButton');
                    $('.lastPageButton, .nextPageButton').attr('disabled',false);

                    $('.lastPageButton').click(function() {
                        var lastPageStartPhotoPosition = numberOfPhotos - (numberOfPhotos % photosPerPage);
                        window.location.href='refineCollectionImport.php?'
                            + 'collectionId=' + collectionId
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + lastPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage;
                    });
                    $('.nextPageButton').click(function() {
                        var nextPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) + photosPerPage;
                        window.location.href='refineCollectionImport.php?'
                            + 'collectionId=' + collectionId
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + nextPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage;
                    });
                }

                if (startPhotoPosition > 0) {
                    $('.firstPageButton, .previousPageButton').removeClass('disabledClickableButton');
                    $('.firstPageButton, .previousPageButton').attr('disabled',false);

                    $('.firstPageButton').click(function() {
                        window.location.href='refineCollectionImport.php?'
                            + 'collectionId=' + collectionId
                            + '&displayType=' + displayType
                            + '&photosPerPage=' + photosPerPage;
                    });
                    $('.previousPageButton').click(function() {
                        var previousPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) - photosPerPage;
                        if (previousPageStartPhotoPosition < 0) {
                            previousPageStartPhotoPosition = 0;
                        }
                        window.location.href='refineCollectionImport.php?'
                            + 'collectionId=' + collectionId
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + previousPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage;
                    });
                }

                $('.photosPerPageSelect').change(function() {
                    console.log('Select Changed');
                    var requestedPhotosPerPage = $(this).val();
                    console.log(requestedPhotosPerPage);
                    startPhotoPosition = Math.floor(startPhotoPosition/requestedPhotosPerPage)*requestedPhotosPerPage;
                    window.location.href='refineCollectionImport.php?'
                        + 'collectionId=' + collectionId
                        + '&displayType=' + displayType
                        + '&startPhotoPosition=' + startPhotoPosition
                        + '&photosPerPage=' + requestedPhotosPerPage;
                    console.log('Select Changed End');
                });

                $('.pageJumpSelect').click(function() {
                    $('.pageJumpSelect').prop('selectedIndex', -1);
                });


                $('.pageJumpSelect').change(function() {
                    var requestedPage = $(this).val();
                    jumpPhotoPosition = (requestedPage - 1) * photosPerPage;
                    window.location.href='refineCollectionImport.php?'
                        + 'collectionId=' + collectionId
                        + '&displayType=' + displayType
                        + '&startPhotoPosition=' + jumpPhotoPosition
                        + '&photosPerPage=' + photosPerPage;
                });

                $('.adminPhotoThumbnailCell img').click(function() {
                    var parentCell = $(this).parents('.adminPhotoThumbnailCell');

                    $.getJSON('ajax/importStatusChanger.php', parentCell.data(), function(statusChangeReturnData) {
                        if (statusChangeReturnData.success == 2) {
                            if (statusChangeReturnData.newImageStatus == 1) {
                                parentCell.data('currentStatus', 1);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Disabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "red");

                            } else {
                                parentCell.data('currentStatus', 0);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Enabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "green");
                            }
                        } else {
                            alert('The database update failed. Please try again later or report the problem to an Admin.');
                        }

                    });
                });
JS;

    //
//
//
//
//
//
//
//
//
    //
    //
} else { // $displayType = 'Show Map'
    $photoQuery = <<<MYSQL
        SELECT import_image_id, latitude, longitude, is_disabled
        FROM import_images
        WHERE import_collection_id = $collectionId
MYSQL;

    $photoQueryResults = run_prepared_query($DBH, $photoQuery);
    $mapResults = $photoQueryResults->fetchAll(PDO::FETCH_ASSOC);
    $JSONmapResults = json_encode($mapResults);
    $contentHTML = <<<HTML
            <p>Zoom in to show individual image markers.<br>
                <span style="color: green">Green</span> markers indicate a photo is enabled.
                <span style="color: red">Red</span> markers indicate a photo is disabled.<br>
                Single click a marker to display a popup contaning the image location, a thumbnail,
                and a button to cycle the image between <span style="color: green">Enabled</span>
                and <span style="color: red">Disabled</span> states.<br>
                Double click a marker to quickly cycle an image between <span style="color: green">Enabled</span>
                and <span style="color: red">Disabled</span> state.<br>
            <div id="refineImportMap">
            </div>

HTML;

    $cssLinkArray[] = 'css/leaflet.css';
    $cssLinkArray[] = 'css/markerCluster.css';

    $javaScriptLinkArray[] = 'scripts/leaflet.js';
    $javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';

    $jQueryDocumentDotReadyCode .= <<<JS
            var photos = $JSONmapResults;
            var popup = L.popup({closeOnClick: true, offset: L.point(0,-35)});
            var map = L.map('refineImportMap', {maxZoom: 16});
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
            }).addTo(map);
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
            L.control.scale({
                position: 'topright',
                metric: false
            }).addTo(map);
            var redMarker = L.icon({
                iconUrl: 'images/system/redMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });
            var greenMarker = L.icon({
                iconUrl: 'images/system/greenMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });

            var enabledMarkers = L.featureGroup();
            var disabledMarkers = L.featureGroup();
            var allMarkers = L.markerClusterGroup({
                disableClusteringAtZoom: 10,
                maxClusterRadius: 60
            });

            var isDoubleClick = false;

            $.each(photos, function(key, photo) {
                if (photo.is_disabled == 0) {
                    var marker = L.marker([photo.latitude, photo.longitude], {icon: greenMarker});
                    enabledMarkers.addLayer(marker);
                } else {
                    var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                    disabledMarkers.addLayer(marker);
                }


                marker.on('click', function() {
                    isDoubleClick = false;
                    var that = this;
                    setTimeout(function() {
                        if (!isDoubleClick) {
                            enabledMarkers.eachLayer(function (layer) {
                                layer.setIcon(greenMarker);
                                layer.setZIndexOffset(0);
                            });
                            disabledMarkers.eachLayer(function (layer) {
                                layer.setIcon(redMarker);
                                layer.setZIndexOffset(0);
                            });
                            that.setZIndexOffset(100000);

                            if (enabledMarkers.hasLayer(that)) {
                                var popupStatusHTML = '<p id="statusIndicatorText" class="userData">This photo is ENABLED</p>';
                                var popupButtonHTML = '<div style="text-align: center"><input type="button" id="photoStatusChangeButton" class="clickableButton" value="Disable This Photo"></div>';
                            } else {
                                var popupStatusHTML = '<p id="statusIndicatorText" class="redHighlight">This photo is DISABLED</p>';
                                var popupButtonHTML = '<div style="text-align: center"><input type="button" id="photoStatusChangeButton" class="clickableButton" value="Enable This Photo"></div>';
                            }

                            var imageData = {
                                photoId: photo.import_image_id,
                                currentStatus: photo.is_disabled
                            }

                            $.getJSON('ajax/importPopupGenerator.php', imageData, function(popupData) {
                                popup.setLatLng(marker.getLatLng());
                                popup.setContent('Location: ' + popupData.location + '<br>'
                                    + '<img class="mapMarkerImage" width="167" height="109" src="' + popupData.thumbnailURL + '" /></a>'
                                    + '<p id="updateResult" class="redHighlight"></p>'
                                    + popupStatusHTML
                                    + popupButtonHTML);
                                popup.openOn(map);
                                $('#photoStatusChangeButton').click(function() {
                                    $.getJSON('ajax/importStatusChanger.php', imageData, function(statusChangeReturnData) {
                                        if (statusChangeReturnData.success == 2) {
                                            $('#updateResult').replaceWith('<p id="updateResult" class="userData">Update successful.</p>');
                                            if (statusChangeReturnData.newImageStatus == 1) {
                                                $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="redHighlight">This photo is DISABLED.</p>');
                                                $('#photoStatusChangeButton').prop('value', 'Enable This Photo');
                                                imageData['currentStatus'] = 1;
                                                photo.is_disabled = 1;
                                                enabledMarkers.removeLayer(marker);
                                                disabledMarkers.addLayer(marker);
                                                marker.setIcon(redMarker);
                                            } else {
                                                $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="userData">This photo is ENABLED.</p>');
                                                $('#photoStatusChangeButton').prop('value', 'Disable This Photo');
                                                imageData['currentStatus'] = 0;
                                                photo.is_disabled = 0;
                                                disabledMarkers.removeLayer(marker);
                                                enabledMarkers.addLayer(marker);
                                                marker.setIcon(greenMarker);
                                            }
                                        } else if (statusChangeReturnData.success == 1) {
                                            $('#updateResult').replaceWith('<p id="updateResult" class="redHighlight">Portrait images cannot be used in iCoast.</p>').delay(500).slideUp();
                                        } else {
                                            $('#updateResult').replaceWith('<p id="updateResult" class="redHighlight">Update failed.</p>').delay(500).slideUp();
                                        }

                                    });
                                });
                            });
                        }
                    },250);

                });

                marker.on('dblclick', function() {
                    isDoubleClick = true;
                    var imageData = {
                        photoId: photo.import_image_id,
                        currentStatus: photo.is_disabled
                    }

                    $.getJSON('ajax/importStatusChanger.php', imageData, function(statusChangeReturnData) {
                        if (statusChangeReturnData.success == 2) {
                            if (statusChangeReturnData.newImageStatus == 1) {
                                photo.is_disabled = 1;
                                enabledMarkers.removeLayer(marker);
                                disabledMarkers.addLayer(marker);
                                marker.setIcon(redMarker);
                                map.closePopup(popup);
                            } else {
                                photo.is_disabled = 0;
                                disabledMarkers.removeLayer(marker);
                                enabledMarkers.addLayer(marker);
                                marker.setIcon(greenMarker);
                                map.closePopup(popup);
                            }
                        }
                    });
                });

            });


            allMarkers.addLayer(enabledMarkers);
            allMarkers.addLayer(disabledMarkers);
            map.fitBounds(allMarkers.getBounds());
            allMarkers.addTo(map);

JS;
}