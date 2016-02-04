<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

$embeddedCSS .= <<<CSS
    .sequencingActionButton,
    .masterControl {
        width: 270px;
    }        
CSS;


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
$hostURL = $_SERVER['HTTP_HOST'];

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    header('Location: collectionCreator.php?error=MissingCollectionId');
    exit;
} else if ($collectionMetadata['creator'] != $userId) {
    header('Location: collectionCreator.php?error=InvalidProject');
    exit;
}

$importStatus = collection_creation_stage($collectionMetadata['import_collection_id']);
if ($importStatus != 4) {
    header('Location: collectionCreator.php?error=InvalidCollection');
    exit;
}


$pageContentHTML = '';

if ($collectionMetadata['sequencing_stage'] == 1) {
    if (strcasecmp($hostURL, 'localhost') === 0 ||
        strcasecmp($hostURL, 'igsafpesvm142') === 0
    ) {
        $curlUrlHost = "http://localhost";
    } else if (strcasecmp($hostURL, 'coastal.er.usgs.gov') === 0) {
        $curlUrlHost = "http://coastal.er.usgs.gov/icoast";
    } else {
        header('Location: collectionCreator.php');
        exit;
    }
    $curlUrl = $curlUrlHost . "/scripts/collectionSequencer.php";
//    $curlPostParams = urlencode("collectionId=$collectionId&user={$userData['user_id']}&checkCode={$userData['auth_check_code']}");
    $curlPostParams = "collectionId=$collectionId&user={$userData['user_id']}&checkCode={$userData['auth_check_code']}";

    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $curlUrl);
    curl_setopt($c, CURLOPT_POSTFIELDS, $curlPostParams);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
    curl_setopt($c, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
    curl_setopt($c, CURLOPT_TIMEOUT, 1);
    curl_exec($c);
    curl_close($c);
}

if ($collectionMetadata['sequencing_stage'] == 1 ||
    $collectionMetadata['sequencing_stage'] == 2
) {
    $pageContentHTML = <<<HTML
        <div id="sequenceContentWrapper">
            <div id="sequencingProgressDetailsWrapper">
                <p>The collection is now being sequenced.<br>
                    Once complete you will have the opportunity to review and edit the calculated "flight path".</p>
                <p>This process may take several minutes. Progress can be monitored below.</p>
            </div>

            <div class="progressBar">
                <div class="progressBarFill" style="width: 0%"></div>
                    <span class="progressBarText" style="left: 40%">Initializing</span>
            </div>

            <form id="reviewFlightButton" method="get" autocomplete="off" style="display:none">
                <input type="hidden" name="collectionId" value="$collectionId">
                <button type="submit" class="clickableButton enlargedClickableButton">
                    Review Flight Path
                </button>
            </form>
HTML;


    $javaScript = <<<JS
    var progressCheckTimer;

    function updateProgress() {
        $.getJSON('ajax/collectionSequencingProgressCheck.php', {collectionId: $collectionId}, function(importProgress) {
            if (importProgress.response !== 'initalizing' && importProgress.response !== 'complete') {
                $('.progressBarFill').css('width', importProgress.response + '%');
                $('.progressBarText').css('left', '35%');
                $('.progressBarText').text(importProgress.response + '% Sequenced');
            } else if (importProgress.response === 'complete') {
                clearInterval(progressCheckTimer);
                $('#sequencingProgressDetailsWrapper').empty().html('<p>Sequencing has been completed.</p>');
                $('.progressBarFill').css('width', '100%');
                $('.progressBarFill').addClass('completeProgressBarFill').removeClass('progressBarFill');
                $('.progressBarText').css('left', '30%');
                $('.progressBarText').text('Sequencing Complete');
                $('#reviewFlightButton').show();
            }
        });
    }
JS;

    $jQueryDocumentDotReadyCode = <<<JS
    progressCheckTimer = setInterval(function() {
        updateProgress()
    }, 2000);

JS;
} else if ($collectionMetadata['sequencing_stage'] == 3) {


    $cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';

    $javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';

    $sequencedPhotoQuery = <<<MYSQL
        SELECT import_image_id, latitude, longitude
        FROM import_images
        WHERE import_collection_id = $collectionId
            AND is_disabled = 0
            AND position_in_collection IS NOT NULL
        ORDER BY position_in_collection
MYSQL;
    $sequencedPhotoResults = run_prepared_query($DBH, $sequencedPhotoQuery);
    $sequencedPhotosJS = '[';
    while ($photo = $sequencedPhotoResults->fetch(PDO::FETCH_ASSOC)) {
        $sequencedPhotosJS .= json_encode($photo) . ',';
    }
    $sequencedPhotosJS = rtrim($sequencedPhotosJS, ',');
    $sequencedPhotosJS .= ']';


    $nonSequencedPhotoQuery = <<<MYSQL
        SELECT import_image_id, latitude, longitude
        FROM import_images
        WHERE import_collection_id = $collectionId
            AND is_disabled = 0
            AND position_in_collection IS NULL
MYSQL;
    $nonSequencedPhotoResults = run_prepared_query($DBH, $nonSequencedPhotoQuery);
    $nonSequencedPhotosJS = '[';
    while ($photo = $nonSequencedPhotoResults->fetch(PDO::FETCH_ASSOC)) {
        $nonSequencedPhotosJS .= json_encode($photo) . ',';
    }
    $nonSequencedPhotosJS = rtrim($nonSequencedPhotosJS, ',');
    $nonSequencedPhotosJS .= ']';


    $javaScript .= <<<JS
            //////////////////////////////////////////////////////////////////////////////////////////////////
            ////// POPUP FUNCTIONS
            function mouseOverFunction (marker) {
                marker.iCoastData.isClicked = false;
                if (mouseoverTimer != null) {
                    clearTimeout(mouseoverTimer);
                }
                if (!marker.iCoastData.restrictPopup) {
                    mouseoverTimer = setTimeout(function() {
                        if (!marker.iCoastData.isClicked) {;
                            var imageData = {
                                photoId: marker.iCoastData.imageId
                            }

                            $.getJSON('ajax/importPopupGenerator.php', imageData, function(popupData) {
                                popup.setLatLng(marker.getLatLng());
                                popup.setContent('<p>Location: ' + popupData.location + '</p>'
                                    + '<img class="mapMarkerImage" width="167" height="109" src="' + popupData.thumbnailURL + '" /></a>');
                                popup.openOn(map);
                            });
                        }
                    },1000);
                } else {
                    marker.iCoastData.restrictPopup = false;
                }
            }

            function mouseOutFunction () {
                map.closePopup(popup);
                clearTimeout(mouseoverTimer);
                mouseoverTimer = null;
            }

            //////////////////////////////////////////////////////////////////////////////////////////////////
            /////// SET ACTION INSTRUCTIONS TEXT
            function setActionInstructions () {
                if (map.getZoom() < 11) {
                    $('#actionInstructions').empty().html(' \
                        <p class="error"> Individual image markers have been removed from the map due to the \
                            small scale of the current zoom level.</p> \
                        <p class="error">Markers will re-appear when you zoom back in.</p>'
                    );
                } else {
                    switch (action) {
                        case 'insert':
                            $('#actionInstructions').empty().html(' \
                                <p id="actionOverview">Use this tool to insert any currently excluded image into the flight path at a \
                                    specific point.</p> \
                                <p id="actionResult" class="error"></p> \
                                <p id="actionStep"><span style="font-weight: bold">Step 1:</span> \
                                    Select the currently excluded image (<span style="color: red">red</span> marker) \
                                    you would like to insert.</p>');
                            break;
                        case 'remove':
                            $('#actionInstructions').empty().html(' \
                                <p id="actionOverview">Use this tool to remove images from the flight path.</p> \
                                <p id="actionResult" class="error"></p> \
                                <p id="actionStep">Simply click any currently included marker (green) on the map and it will \
                                    remove itself from the flight path.</p>');
                            break;
                        default:
                            $('#actionInstructions').empty().html(' \
                                <p id="actionOverview">Click an action button above to make a change to the flight path.</br></br> \
                                    You may remove images currently in the flight path or insert images that are \
                                    currently excluded into the flight path at a specific point.</p> \
                                <p id="actionResult" class="error"></p>'
                            );
                            break;
                    }
                }
            }


            //////////////////////////////////////////////////////////////////////////////////////////////////
            ////// INSERT IMAGE FUNCTIONS

            function imageToInsert (markerToInsert) {
                markerToInsert.iCoastData.isClicked = true;
                map.closePopup(popup);
                imageToInsertSelected = true;
                sequencedMarkers.eachLayer(function (markerToInsertAfter) {
                    markerToInsertAfter.setOpacity(1);
                    markerToInsertAfter.on('click', function() {
                        imageToInsertAfter(markerToInsertAfter, markerToInsert);
                    })
                });
                nonSequencedMarkers.eachLayer(function (marker) {
                    marker.setOpacity(0.3);
                    marker.removeEventListener('click');
                });
                markerToInsert.setIcon(orangeMarker)
                markerToInsert.setOpacity(1);
                markerToInsert.on('click', function() {
                    resetInsertAction(markerToInsert);
                });
                $('#actionOverview').empty();
                $('#actionResult').empty();
                $('#actionStep').empty().html('<span style="font-weight: bold">Step 2:</span> Select an image \
                    currently included in the flight path (<span style="color: green">green</span> marker) that you would \
                    like to insert the new image after (general direction of travel is anti-clockwise).<br> \
                    Click the currently excluded image again (<span style="color: orange">orange</span> marker) \
                    to reset the insert action and choose a different image to insert.');
            }

            function imageToInsertAfter (markerToInsertAfter, markerToInsert) {
                markerToInsertAfter.iCoastData.isClicked = true;
                map.closePopup(popup);
                if (imageToInsertSelected) {
                    positionToInsertAfter = markerToInsertAfter.iCoastData.positionInCollection;
                    sequencedMarkers.eachLayer(function (sequencedMarker) {
                        if (sequencedMarker.iCoastData.positionInCollection > positionToInsertAfter) {
                            sequencedMarker.iCoastData.positionInCollection += 1;
                        }
                        sequencedMarker.setOpacity(0.3);
                        sequencedMarker.removeEventListener('click');
                    });
                    markerToInsert.iCoastData.positionInCollection = positionToInsertAfter + 1;
                    flightPath.spliceLatLngs(positionToInsertAfter + 1, 0, markerToInsert.getLatLng());

                    nonSequencedMarkers.removeLayer(markerToInsert);
                    sequencedMarkers.addLayer(markerToInsert);
                    markerToInsert.setIcon(greenMarker);
                    markerToInsert.setOpacity(0.3);
                    markerToInsert.removeEventListener('click');

                    nonSequencedMarkers.eachLayer(function(nonSequencedMarker) {
                        nonSequencedMarker.setOpacity(1);
                        nonSequencedMarker.on('click', function() {
                            imageToInsert(nonSequencedMarker);
                        });
                    });

                    $('#actionResult').html('IMAGE SUCESSFULLY INSERTED');
                    $('#actionStep').empty().html('<span style="font-weight: bold">Step 1:</span> \
                        Select the currently excluded image (<span style="color: red">red</span> marker) \
                        you would like to insert.</p>');

                    imageToInsertSelected = false;
                } else {
                    $('#insertImageButton').trigger('click');

                }
            }

            function resetInsertAction(marker) {
                marker.iCoastData.isClicked = true;
                map.closePopup(popup);
                marker.setIcon(redMarker);
                $('#actionStep').empty().html('<span style="font-weight: bold">Step 1:</span> \
                    Select the currently excluded image (<span style="color: red">red</span> marker) \
                    you would like to insert.</p>');
                marker.removeEventListener('click');
                sequencedMarkers.eachLayer(function (marker) {
                    marker.setOpacity(0.3);
                    marker.removeEventListener('click');
                });
                nonSequencedMarkers.eachLayer(function (marker) {
                    marker.setOpacity(1);
                    marker.on('click', function() {
                        imageToInsert(marker);
                    });
                });
            }

            //////////////////////////////////////////////////////////////////////////////////////////////////
            ////// REMOVE IMAGE FUNCTION
            function removeImage (marker) {
                marker.iCoastData.isClicked = true;
                marker.iCoastData.restrictPopup = true;
                map.closePopup(popup);
                if (sequencedPhotos[marker.iCoastData.positionInCollection].import_imageId == marker.imageId) {
                    var markerPosition = marker.iCoastData.positionInCollection;
                    marker.iCoastData.positionInCollection = null;
                    flightPath.spliceLatLngs(markerPosition, 1);
                    sequencedMarkers.removeLayer(marker);
                    nonSequencedMarkers.addLayer(marker);
                    marker.iCoastData.isClicked = true;
                    marker.removeEventListener('click');
                    marker.setIcon(redMarker);
                    marker.setOpacity(0.3);
                    $('#actionResult').empty().html('IMAGE SUCESSFULLY REMOVED');

                    sequencedMarkers.eachLayer(function (sequencedMarker) {
                        if (sequencedMarker.iCoastData.positionInCollection > markerPosition) {
                            sequencedMarker.iCoastData.positionInCollection -= 1;
                        }
                    });

                }
            }



            //////////////////////////////////////////////////////////////////////////////////////////////////
            ////// RESET MAP FUNCTION
            function resetMap() {
                setActionInstructions();
                map.closePopup(popup);
                allMarkers.clearLayers();
                allMarkers.addLayer(sequencedMarkers);
                allMarkers.addLayer(nonSequencedMarkers);
                sequencedMarkers.eachLayer(function (marker) {
                    marker.setIcon(greenMarker);
                    marker.setOpacity(1);
                    marker.removeEventListener('click');
                });
                nonSequencedMarkers.eachLayer(function (marker) {
                    marker.setIcon(redMarker);
                    marker.setOpacity(1);
                    marker.removeEventListener('click');
                });

                if (!allMarkers.hasLayer(sequencedMarkers)) {
                    allLayers.addLayer(sequencedMarkers);
                }
                if (!allMarkers.hasLayer(nonSequencedMarkers)) {
                    allLayers.addLayer(nonSequencedMarkers);
                }
                imageToInsertSelected = false;

                $('#insertImageButton').removeClass('selectedClickableButton');
                $('#removeImageButton').removeClass('selectedClickableButton');

            }


            function zoomMarkerControl() {
                if (map.getZoom() < 11) {
                    if (map.hasLayer(allMarkers)) {
                        map.removeLayer(allMarkers);
                        setActionInstructions();
                    }
                } else {
                    if (!map.hasLayer(allMarkers)) {
                        map.addLayer(allMarkers);
                        setActionInstructions();
                    }
                }
            }




            var map;
            var popup = L.popup({autoPan: false, offset: L.point(0,-35)});
            var sequencedMarkers = L.featureGroup();
            var nonSequencedMarkers = L.featureGroup();
            var allMarkers = L.featureGroup();
            var isClicked;
            var mouseoverTimer;
            var flightPathPoints = [];
            var action = null;

            var imageToInsertSelected;
            var imageToSwapSelected;

            var sequencedPhotos = $sequencedPhotosJS;
            var nonSequencedPhotos = $nonSequencedPhotosJS;
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
            var orangeMarker = L.icon({
                iconUrl: 'images/system/orangeMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });


            $.each(sequencedPhotos, function(positionInCollection, photo) {
                var marker = L.marker([photo.latitude, photo.longitude], {icon: greenMarker});
                marker.iCoastData = {
                        'imageId': photo.import_image_id,
                        'positionInCollection': positionInCollection
                     };
                sequencedMarkers.addLayer(marker);
                flightPathPoints.push(L.latLng([photo.latitude, photo.longitude]));

                marker.on('mouseover', function() {
                    mouseOverFunction(marker)
                });

                marker.on('mouseout', function() {
                    mouseOutFunction()
                });
                marker.iCoastData.restrictPopup = false;
            });

           $.each(nonSequencedPhotos, function(key, photo) {
                var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                marker.iCoastData = {
                    'imageId': photo.import_image_id,
                };
                nonSequencedMarkers.addLayer(marker);

                marker.on('mouseover', function() {
                    mouseOverFunction(marker)
                });

                marker.on('mouseout', function() {
                    mouseOutFunction()
                });
                marker.iCoastData.restrictPopup = false;
            });


            var flightPath = L.polyline(flightPathPoints, {
                color: '#4BCB00',
                clickable: false,
                opacity: 1,
                smoothFactor: 3
            });


            allMarkers.addLayer(sequencedMarkers);
            allMarkers.addLayer(nonSequencedMarkers);




JS;


    $jQueryDocumentDotReadyCode .= <<<JS

        map = L.map('sequencingMap', {maxZoom: 16});
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
        }).addTo(map);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
        L.control.scale({
            position: 'topright',
            metric: false
        }).addTo(map);


        flightPath.addTo(map);
        allMarkers.addTo(map);
        map.fitBounds(allMarkers.getBounds());
        zoomMarkerControl();

        map.on('zoomend', function () {
            zoomMarkerControl();
        });

        $('.sequencingActionButton').click(function() {
            location.hash = 'sequencingActionButtonWrapper';
        });

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        ////// INSERT ACTION BUTTON CLICK EVENT
        $('#insertImageButton').click(function() {
            resetMap();

            if (action != 'insert') {
                action = 'insert';
                $('#insertImageButton').addClass('selectedClickableButton');
                sequencedMarkers.eachLayer(function (marker) {
                    marker.setOpacity(0.3);
                });
                nonSequencedMarkers.eachLayer(function (marker) {
                    marker.on('click', function() {
                        imageToInsert(marker);
                    });
                });
            } else {
                action = null;
            }
            setActionInstructions();
        });



        //////////////////////////////////////////////////////////////////////////////////////////////////////
        ////// REMOVE ACTION BUTTON CLICK EVENT
        $('#removeImageButton').click(function() {
            resetMap();

            if (action != 'remove') {
                action = 'remove';
                $('#removeImageButton').addClass('selectedClickableButton');
                sequencedMarkers.eachLayer(function (marker) {
                    marker.on('click', function() {
                        removeImage(marker);
                    });
                });
                nonSequencedMarkers.eachLayer(function (marker) {
                    marker.setOpacity(0.3);
                });
            } else {
                action = null;
            }
            setActionInstructions();
        });

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        ////// REVERT BUTTON CLICK EVENT
        $('#revertCollectionButton').click(function() {
            location.reload();
        });

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        ////// COMMIT BUTTON CLICK EVENT
        $('#commitCollectionButton').click(function() {
            $('.masterControl, .sequencingActionButton')
                .addClass('disabledClickableButton')
                .attr('disabled', 'disabled');
            $('#commitCollectionButton').html('<span style="color: red">Working...</span>');
            var positionArray = [];
            sequencedMarkers.eachLayer(function (sequencedMarker) {
                var imageId = sequencedMarker.iCoastData.imageId;
                var positionInCollection = sequencedMarker.iCoastData.positionInCollection;
                positionArray[positionInCollection] = imageId;
            });
            var ajaxData = {};
            ajaxData['collectionId'] = $collectionId;
            ajaxData['positionData'] = positionArray;
            $.post('ajax/commitCollection.php', ajaxData, function (ajaxResponse) {
                if (ajaxResponse == 1) {
                    window.location.href = 'reviewCollection.php?collectionId=' + $collectionId;
                } else {
                    $('.sequencingActionButton').removeClass('selectedClickableButton');
                    $('#actionOverview').empty();
                    $('#actionResult').empty().html('DATABASE COMMIT FAILED<br><br>Please try again or contact the iCoast developer.');
                    $('.masterControl, .sequencingActionButton')
                        .removeClass('disabledClickableButton')
                        .removeAttr('disabled')
                    $('#commitCollectionButton').text('Commit & Review');
                }
            });
        });




JS;


    $pageContentHTML = <<<HTML
        <div id="sequenceContentWrapper">
            <div id="sequencingProgressDetailsWrapper">
                <h3>Sequencing Complete!</h3>
                <p>iCoast has attempted to sequence the collection's images in a manner that creates a
                    simulated flight path along the coast. This path should exclude any unnecessary adjacent
                    images to create a single smooth line.</p>
                <p>Before finally committing this collection to the database you may refine this flight path by
                    adding, removing, or swapping images. Use the buttons below to select the action you wish to
                    perform and then follow the displayed instructions to interact with the map markers.</p>
                <p>Once you have finished making alterations click the <span class='italic'>
                    Review Collection</span> button at the bottom of the page. Images in the flight path will
                        be included in the collection. All other images will be discarded.</p>
            </div>
            <div id="sequencingActionButtonWrapper">
                <button type="button" class="clickableButton sequencingActionButton" id="insertImageButton">
                    Insert Image(s)
                </button>
                <button type="button" class="clickableButton sequencingActionButton" id="removeImageButton">
                    Remove Image(s)
                </button>

            </div>
            <div id="actionInstructions" style="height: 100px">
                <p id="actionOverview">Click an action button above to make a change to the flight path.</br></br>
                    You may remove images currently in the flight path or insert images that are
                    currently excluded into the flight path at a specific point.</p>
                <p id="actionResult" class="error"></p>
            </div>
            <div id="sequencingMap"></div>
            <button type="button" id="commitCollectionButton" class="clickableButton enlargedClickableButton masterControl"
                    name="commitCollection" title="Clicking this button will commit the changes made here to the database.">
                Commit & Review Collection
            </button>
            <button type="button" id="revertCollectionButton" class="clickableButton enlargedClickableButton masterControl"
                    title="Clicking this button will revert all changes made to the collection through the Insert
                    and Remove tools on this page that allowing you to start again.">
                Revert Changes
            </button>

        </div>
HTML;
}