<?php

$pageName = "profile";
$cssLinkArray[] = 'css/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';
$embeddedCSS = '#historyControlWrapper p:first-of-type {margin-top: 0px; padding-top: 10px;}';
$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";

require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    header('Location: index.php');
    exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$timeZone1HTML = '';
$timeZone2HTML = '';
$timeZone3HTML = '';
$timeZone4HTML = '';
$timeZone5HTML = '';
$timeZone6HTML = '';
$timeZone7HTML = '';
$timeZone8HTML = '';
$crowdType1HTML = '';
$crowdType2HTML = '';
$crowdType3HTML = '';
$crowdType4HTML = '';
$crowdType5HTML = '';
$crowdType6HTML = '';
$crowdType7HTML = '';
$crowdType8HTML = '';
$crowdType9HTML = '';
$crowdType10HTML = '';
$timeZoneError = '';
$newAccountError = '';
$confirmLoginError = '';
$crowdTypeError = '';
$otherCrowdTypeError = '';
$affiliationError = '';
$newAccount = '';
$confirmNewLogin = '';
$otherCrowdType = '';
$affiliationContent = '';
$updateAck = '';
$stickyCrowdType = FALSE;
$crowdTypeReset = '';
$stickyTimeZone = FALSE;
$timeZoneReset = '';
$profileFormErrorControl = '';
$hideHistoryPanelJavaScript = '';
$projectSelectionOptions = '';


if (isset($_POST['formSubmission'])) {
    switch ($_POST['formSubmission']) {






        case 'account':
            $newAccount = (isset($_POST['newAccount'])) ? strtolower(trim($_POST['newAccount'])) : null;
            $confirmNewLogin = (isset($_POST['confirmNewLogin'])) ? strtolower(trim($_POST['confirmNewLogin'])) : null;
            $filteredNewEmail = filter_var($newAccount, FILTER_VALIDATE_EMAIL);
            if (empty($filteredNewEmail)) {
                $errorMessage['newAccount'] = 'You must specify a valid new eMail Address.';
            } else if (empty($confirmNewLogin) || strcasecmp($filteredNewEmail, $confirmNewLogin) != 0) {
                $errorMessage['confirmEmail'] = 'Your confirmation eMail address must match your new eMail address.';
            }


            if (!isset($errorMessage)) {
                $maskedEmail = mask_email($newAccount);
                $emailExistsQuery = "SELECT encrypted_email, encryption_data FROM users "
                        . "WHERE masked_email = :maskedEmail";
                $emailExistsParams['maskedEmail'] = $maskedEmail;
                $STH = run_prepared_query($DBH, $emailExistsQuery, $emailExistsParams);
                $possibleEmailMatches = $STH->fetchAll(PDO::FETCH_ASSOC);
                $emailExists = FALSE;
                if (count($possibleEmailMatches) > 0) {

                    foreach ($possibleEmailMatches as $emailMatch) {
                        $clearTextEmail = mysql_aes_decrypt
                                ($emailMatch['encrypted_email'], $emailMatch['encryption_data']);
                        if (strcasecmp($filteredNewEmail, $clearTextEmail) === 0) {
                            $emailExists = TRUE;
                        }
                    }
                }
                if (!$emailExists) {
                    $encryptedEmailData = mysql_aes_encrypt($filteredNewEmail);
                    $profileUpdateQuery = "UPDATE users SET masked_email = :maskedEmail, "
                            . "encrypted_email = :encryptedEmail, encryption_data = :encryptionData "
                            . "WHERE user_id = :userId LIMIT 1";
                    $profileUpdateParams = array(
                        'maskedEmail' => mask_email($newAccount),
                        'encryptedEmail' => $encryptedEmailData[0],
                        'encryptionData' => $encryptedEmailData[1],
                        'userId' => $userId
                    );
                    $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                    if ($STH->rowCount() == 1) {
                        header('Location: profile.php?update=email');
                        exit;
                    } else {
                        print $STH->rowCount();
                        //  Placeholder for error management
                        print 'Error. Account update failed. No details have been changed.<br>';
                        exit;
                    }
                } else {
                    $newAccountError = '<label class="error" for="newAccount">The specified account already '
                            . 'exists within iCoast.</label>';
                    $confirmNewLogin = '';
                }
            } else if (isset($errorMessage['newAccount'])) {
                $newAccountError = '<label class="error" for="newAccount">' . $errorMessage['newAccount'] . '</label>';
            } else if (isset($errorMessage['confirmEmail'])) {
                $confirmLoginError = '<label class="error" for="confirmEmail">' . $errorMessage['confirmEmail'] . '</label>';
            }


            $newAccount = htmlentities($newAccount);
            $confirmNewLogin = htmlentities($confirmNewLogin);
            break;






        case 'crowd':
            $crowdType = (isset($_POST['crowdType'])) ? trim($_POST['crowdType']) : null;
            $otherCrowdType = (!empty($_POST['otherCrowdType'])) ? trim($_POST['otherCrowdType']) : '';

            if (empty($crowdType)) {
                $errorMessage['crowdType'] = 'You must select your crowd type to complete registration.';
            } else {
                if ($crowdType < 0 || $crowdType > 10) {
                    $errorMessage['crowdType'] = 'The specified crowd type is invalid.';
                }
            }

            if ($crowdType == 10 && empty($otherCrowdType)) {
                $errorMessage['otherCrowdType'] = 'You must specify your other crowd type if "Other" is selected in the crowd type list.';
            } elseif (!empty($otherCrowdType) && strlen($otherCrowdType) > 255) {
                $errorMessage['otherCrowdType'] = 'Your specified other crowd type is too long (max 255 characters).';
            }

            if (!isset($errorMessage)) {
                $existingCrowdType = $userData['crowd_type'];
                $existingOtherCrowdType = $userData['other_crowd_type'];
                if (($crowdType != 10 && $crowdType == $existingCrowdType) ||
                        ($crowdType == 10 && strcmp($otherCrowdType, $existingOtherCrowdType) == 0)) {
                    header('Location: profile.php?update=crowd');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET crowd_type = :crowdType, other_crowd_type = :otherCrowdType "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'crowdType' => $crowdType,
                    'otherCrowdType' => $otherCrowdType,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=crowd');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['crowdType'])) {
                $crowdTypeError = '<label class="error" for="crowdType">' . $errorMessage['crowdType'] . '</label>';
            } else if (isset($errorMessage['otherCrowdType'])) {
                $otherCrowdTypeError = '<label class="error" for="otherCrowdType">' . $errorMessage['otherCrowdType'] . '</label>';
            }


            if (isset($crowdType) && $crowdType == 1) {
                $crowdType1HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 2) {
                $crowdType2HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 3) {
                $crowdType3HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 4) {
                $crowdType4HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 5) {
                $crowdType5HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 6) {
                $crowdType6HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 7) {
                $crowdType7HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 8) {
                $crowdType8HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 9) {
                $crowdType9HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }
            if (isset($crowdType) && $crowdType == 10) {
                $crowdType10HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }



            $otherCrowdType = htmlEntities($otherCrowdType);
            break;







        case 'affiliation':
            $affiliationContent = (!empty($_POST['affiliation'])) ? trim($_POST['affiliation']) : '';
            if (!empty($affiliationContent)) {
                if (strlen($affiliationContent) > 255) {
                    $errorMessage['affiliation'] = 'Your specified affiliation is too long (max 255 characters).';
                }
            }
            if (!isset($errorMessage)) {
                $existingAffiliation = $userData['affiliation'];
                if (strcmp($affiliationContent, $existingAffiliation) == 0) {
                    header('Location: profile.php?update=affiliation');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET affiliation = :affiliation "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'affiliation' => $affiliationContent,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=affiliation');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['affiliation'])) {
                $affiliationError = '<label class="error" for="registerCrowdType">' . $errorMessage['affiliation'] . '</label>';
            }

            $affiliationContent = htmlentities($affiliationContent);
            break;








        case 'timeZone':
            $timeZone = (isset($_POST['timeZone'])) ? trim($_POST['timeZone']) : null;
            if (empty($timeZone)) {
                $errorMessage['timeZone'] = 'You must select your time zone to complete registration.';
            } else {
                if ($timeZone < 1 || $timeZone > 8) {
                    $errorMessage['timeZone'] = 'The specified time zone is invalid.';
                }
            }
            if (!isset($errorMessage)) {
                $existingTimeZone = $userData['time_zone'];
                if ($existingTimeZone == $timeZone) {
                    header('Location: profile.php?update=timeZone');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET time_zone = :timeZone "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'timeZone' => $timeZone,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=timeZone');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['timeZone'])) {
                $timeZoneError = '<label class="error" for="registerTimeZone">' . $errorMessage['timeZone'] . '</label>';
            }

            switch ($timeZone) {
                case 1;
                    $timeZone1HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 2;
                    $timeZone2HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 3;
                    $timeZone3HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 4;
                    $timeZone4HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 5;
                    $timeZone5HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 6;
                    $timeZone6HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 7;
                    $timeZone7HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
                case 8;
                    $timeZone8HTML = 'selected="selected"';
                    $stickyTimeZone = TRUE;
                    break;
            }
            break;
    }

    if (!empty($newAccountError) || !empty($confirmNewLoginError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeAccountFormWrapper').css('display', 'block');";
    } else if (!empty($crowdTypeError) || !empty($otherCrowdTypeError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeCrowdFormWrapper').css('display', 'block');";
    } else if (!empty($affiliationError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeAffiliationFormWrapper').css('display', 'block');";
    } else if (!empty($timeZoneError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeTimeZoneFormWrapper').css('display', 'block');";
    }
}

if (!$stickyCrowdType) {
    $crowdTypeReset = "$('#crowdType').prop('selectedIndex', -1);";
}
if (!$stickyTimeZone) {
    $timeZoneReset = "$('#timeZone').prop('selectedIndex', -1);";
}


if (isset($_GET['update'])) {
    $updateAck = '<p class="highlightedText">Your ';
    switch ($_GET['update']) {
        case 'email':
            $updateAck .= 'eMail Address was sucessfully updated.';
            break;
        case 'crowd':
            $updateAck .= 'Crowd Type was sucessfully updated.';
            break;
        case 'affiliation':
            $updateAck .= 'Affiliation was sucessfully updated.';
            break;
        case 'timeZone':
            $updateAck .= 'Time Zone was sucessfully updated.';
            break;
        default:
            $updateAck = '';
            break;
    }
}

$maskedEmail = $userData['masked_email'];
$crowdType = htmlentities(crowdTypeConverter($userData['crowd_type'], $userData['other_crowd_type']));
if (!empty($userData['affiliation'])) {
    $affiliation = htmlentities($userData['affiliation']);
} else {
    $affiliation = "None Given";
}
$timeZone = timeZoneIdToTextConverter($userData['time_zone']);


$annotatedProjectsQuery = "SELECT DISTINCT(project_id) AS project_id FROM annotations "
        . "WHERE user_id = :userId AND NOT user_match_id = '' ORDER BY initial_session_start_time DESC";
$annotatedProjectsParams['userId'] = $userId;
$STH = run_prepared_query($DBH, $annotatedProjectsQuery, $annotatedProjectsParams);
$projectMetadata = array();
while ($project = $STH->fetch(PDO::FETCH_ASSOC)) {
    $projectMetadata[] = retrieve_entity_metadata($DBH, $project['project_id'], 'project');
}

if (count($projectMetadata) > 0) {
    foreach ($projectMetadata as $singleProject) {
        $projectId = $singleProject['project_id'];
        $projectName = $singleProject['name'];
        $projectSelectionOptions .= "<option value=\"$projectId\">$projectName</option>\n\r";
    }
} else {
    $hideHistoryPanelJavaScript = "$('#profileAnnotationListControls').css('display', 'none');";
    $hideHistoryPanelJavaScript .= "$('#profileSettingsWrapper').css('border-bottom-width', '0px');";
    $hideHistoryPanelJavaScript .= "$('#profileHideButton').css('display', 'none');";
}









$javaScript = <<<EOT
    var userId = $userId;
    var timeZone = {$userData['time_zone']};
    var startingRow = 0;
    var rowsPerPage = 10;
    var displayedRows;
    var resultCount;
    var minLatitude;
    var maxLatitude;
    var minLongitude;
    var maxLongitude;
    var resultSetMapBounds;
    var resultSetCenterPoint;
    var searchedProject;

    var map = null;
    var markers = null;
    var resultSetMapBounds;

    var photoIcon = L.icon({
        iconSize: [32, 37],
        iconAnchor: [16, 37],
        iconUrl: 'images/system/photo.png',
        popupAnchor: [16, 18]
    });
    var selectedIcon = L.icon({
        iconSize: [32, 37],
        iconAnchor: [16, 37],
        iconUrl: 'images/system/photoSelected.png',
        popupAnchor: [16, 18]
    });

    function runAjaxAnnotationQuery() {
        if (startingRow < 0) {
            startingRow = 0;
        }
        ajaxData = {
            userId: userId,
            userTimeZone: timeZone,
            projectId: searchedProject,
            startingRow: startingRow,
            rowsPerPage: rowsPerPage
        };
        $.post('ajax/userHistory.php', ajaxData, displayUserHistory, 'json');
    }

    function displayUserHistory(history) {
        resultCount = parseFloat(history.controlData.resultCount);
        delete history.controlData;
        var tableContents;
        displayedRows = 0;

        if (markers === null) {
            markers = L.markerClusterGroup({
                disableClusteringAtZoom: 15,
                maxClusterRadius: 40
            });
        } else {
            markers.clearLayers();
        }

        var firstMarker = true;

        $.each(history, function(index, annotation) {
            var annotationPoint = L.latLng(annotation.latitude, annotation.longitude);
            var infoString = 'Image Id: ' + annotation.image_id + '. Location ' + annotation.location +
                '. Tagged on: ' + annotation.annotation_time + '. Part of the ' + annotation.project_name +
                ' project.'

            if (firstMarker) {
                resultSetMapBounds = L.latLngBounds(annotationPoint, annotationPoint);
            } else {
                resultSetMapBounds.extend(annotationPoint);
            }
            if (resultSetMapBounds.contains(annotationPoint)) {
                console.log('In Bounds');
            }
            firstMarker = false;
            var markerPopup = L.popup({offset: L.point(0,-40), closeButton: false, autoPan :false}).setContent(infoString).setLatLng(annotationPoint);

            var thisMarker = L.marker(annotationPoint, {icon: photoIcon});
            thisMarker.on('mouseover', function() {
                map.openPopup(markerPopup);
            });
            thisMarker.on('mouseout', function() {
                map.closePopup(markerPopup);
            });
    
            markers.addLayer(thisMarker);





            thisMarker.on('click', function() {
                $('tr').css('background-color', '#FFFFFF');
                $('tr').css('font-weight', 'normal');
                $('td').each(function() {
                    if ($(this).text() == annotation.image_id) {
                        $(this).parent().css('background-color', '#D3E2F0');
                        $(this).parent().css('font-weight', 'bold');
                    }
                });
                markers.eachLayer(function(layer) {
                    layer.setIcon(photoIcon);
                });
                thisMarker.setIcon(selectedIcon);
            });
            displayedRows++;
            var mapLink = '<td><a href="classification.php?projectId=' + annotation.project_id +
                    '&imageId=' + annotation.image_id + '">';

            tableContents += '<tr><td><a href="classification.php?projectId=' + annotation.project_id +
                    '&imageId=' + annotation.image_id + '">';

            if (annotation.annotation_completed == 1) {
                tableContents += '<div class="clickableButton" title="Click this button to see the photo and edit ' +
                        'your selections if you wish.">Tag</div></a></td>';
            } else {
                tableContents += '<div class="clickableButton incompleteAnnotation" title="Not all tasks for ' +
                        'this photo were completed. Click this button to return to the photo, edit your selections, ' +
                        'and complete all the tasks so it counts towards to leaderboard statistics.">Tag</div></a></td>';
            }
            tableContents += '<td>' + annotation.annotation_time + '</td>';
            tableContents += '<td>' + annotation.time_spent + '</td>';
            tableContents += '<td>' + annotation.number_of_tags + '</td>';
            tableContents += '<td>' + annotation.location + '</td>';
            tableContents += '<td>' + annotation.image_id + '</td>';
            tableContents += '<td>' + annotation.project_name + '</td>';
            tableContents += '</tr>';
        });
        $('tbody tr').remove();
        $('tbody').append(tableContents);

        $('#resultSizeSelect').removeClass('disabledClickableButton').removeAttr('disabled');

        if (displayedRows + startingRow < resultCount) {
            $('#nextPageButton, #lastPageButton').removeClass('disabledClickableButton');
        } else {
            $('#nextPageButton, #lastPageButton').addClass('disabledClickableButton');
        }

        if (startingRow >= 10) {
            $('#previousPageButton, #firstPageButton').removeClass('disabledClickableButton');
        } else {
            $('#previousPageButton, #firstPageButton').addClass('disabledClickableButton');
        }

        var topRow = startingRow + 1;
        if ((parseInt(startingRow) + parseInt(rowsPerPage)) < resultCount) {
            var bottomRow = parseInt(startingRow) + parseInt(rowsPerPage);
        } else {
            var bottomRow = resultCount;
        }
        var totalRows = bottomRow - startingRow;
        var totalPages = Math.ceil(resultCount / rowsPerPage);
        var currentPage = Math.ceil(topRow / rowsPerPage);

        $('#profileTableWrapper p:nth-of-type(2)').remove();
        $('#profileTableWrapper').append('<p>Page ' + currentPage + ' of ' + totalPages +
                '. Displaying rows ' + topRow + ' - ' + bottomRow +
                ' of ' + resultCount + ' total results (' + totalRows + ' rows shown)</p>');

        if ($('#userAnnotationHistory').css('display') === 'none') {
    //            $('#feedbackWrapper').hide();
            $('#userAnnotationHistory').slideDown(positionFeedbackDiv);
        }
        map.addLayer(markers);
        map.invalidateSize();
        map.fitBounds(resultSetMapBounds, {
            maxZoom: 15
        });


        if ($('#profileDetailsWrapper').css('display') !== 'none') {
    //            $('#feedbackWrapper').hide();
            $('#profileDetailsWrapper').slideUp(positionFeedbackDiv);
            $('#profileHideButton').text('Show Profile Details');
        }

        $('#profileTableWrapper .clickableButton').tipTip();

        moveFooter();

    }


    function initializeMaps() {
        var startingPosition = L.latLng(27.764463, -82.638284);

        map = L.map("mapCanvas", {maxZoom: 18}).setView(startingPosition, 16);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
        }).addTo(map);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
        L.control.scale({
            position: 'topright',
            metric: false
        }).addTo(map);

    } // End function initializeMaps

EOT;

$jQueryDocumentDotReadyCode = <<<EOT

        $crowdTypeReset
        $timeZoneReset

        $('#accountChangeButton').click(function() {
//            $('#feedbackWrapper').hide();
            $('.profileUpdateField').slideUp();
            $('#changeAccountFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#crowdTypeChangeButton').click(function() {
//            $('#feedbackWrapper').hide();
            $('.profileUpdateField').slideUp();
            $('#changeCrowdFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#affiliationChangeButton').click(function() {
//            $('#feedbackWrapper').hide();
            $('.profileUpdateField').slideUp();
            $('#changeAffiliationFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#timeZoneChangeButton').click(function() {
//            $('#feedbackWrapper').hide();
            $('.profileUpdateField').slideUp();
            $('#changeTimeZoneFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('.cancelUpdateButton').click(function() {
//            $('#feedbackWrapper').hide();
            $('.profileUpdateForm').slideUp();
            $('.profileUpdateField').slideDown(positionFeedbackDiv);
        });

        $profileFormErrorControl

        $hideHistoryPanelJavaScript

        $('#profileHideButton').click(function() {
            if ($('#profileDetailsWrapper').css('display') === 'none') {
                $('#profileHideButton').text('Hide Profile Details');
            } else {
                $('#profileHideButton').text('Show Profile Details');
            }
//            $('#feedbackWrapper').hide();
            $('#profileDetailsWrapper').slideToggle(positionFeedbackDiv);
            moveFooter();
        });

        var script = document.createElement("script");
        script.type = "text/javascript";
        script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
        document.body.appendChild(script);

        $('#resultSizeSelect').prop('selectedIndex', 0);

        $('#resultSizeSelect').on('change', function() {
            rowsPerPage = $(this).val();
            runAjaxAnnotationQuery();
        });

        $('#allPhotoButton').click(function() {
            searchedProject = 0;
            startingRow = 0;
            runAjaxAnnotationQuery();
        });

        $('#projectPhotoButton').click(function() {
            searchedProject = parseInt($('#projectSelection').val(), 10);
            startingRow = 0;
            if (searchedProject > 0) {
                runAjaxAnnotationQuery();
            }
        });

        $('#firstPageButton').click(function() {
            if (!$('#firstPageButton').hasClass('disabledClickableButton')) {
                startingRow = 0;
                runAjaxAnnotationQuery();
            }
        });

        $('#previousPageButton').click(function() {
            if (!$('#previousPageButton').hasClass('disabledClickableButton')) {
                startingRow -= parseInt(rowsPerPage);
                runAjaxAnnotationQuery();
                $.post('ajax/userHistory.php', ajaxData, displayUserHistory, 'json');
            }

        });

        $('#nextPageButton').click(function() {
            if (!$('#nextPageButton').hasClass('disabledClickableButton')) {
                startingRow += parseInt(rowsPerPage);
                runAjaxAnnotationQuery();
            }
        });

        $('#lastPageButton').click(function() {
            if (!$('#lastPageButton').hasClass('disabledClickableButton')) {
                startingRow = (Math.floor((resultCount - 1) / rowsPerPage) * rowsPerPage);
                runAjaxAnnotationQuery();
            }
        });

        $('#accountForm').validate({
            rules: {
                newAccount: {
                    required: true
                },
                confirmNewLogin: {
                    equalTo: '#newAccount'
                }
            },
            messages: {
                newAccount: {
                    required: 'You must specify a new login account to continue.'
                },
                confirmNewLogin: {
                    equalTo: 'Your confirmation login entry must match your first entry.'
                }
            }
        });

        $('#crowdForm').validate({
            rules: {
                crowdType: {
                    required: true
                },
                otherCrowdType: {
                    maxlength: 255
                }
            },
            messages: {
                crowdType: {
                    required: 'You must select a crowd type.'
                },
                otherCrowdType: {
                    maxlength: 'Your specified other crowd type is too long (max 255 characters).'
                }
            }
        });

        $('#affiliationForm').validate({
            rules: {
                affiliation: {
                    maxlength: 255
                }
            },
            messages: {
                affiliation: {
                    maxlength: 'Your specified affiliation is too long (max 255 characters).'
                }
            }
        });

        $('#timeZoneForm').validate({
            rules: {
                timeZone: {
                    required: true
                }
            },
            messages: {
                timeZone: {
                    required: 'You must select your new time zone.'
                }
            }
        });


        if ($('#crowdType option:selected').text() !== 'Other (Please specify below)') {
            $('#profileOtherRow').css('display', 'none');
        }
        $('#crowdType').change(function() {
            if (($('#crowdType option:selected').text() === 'Other (Please specify below)') &&
                    ($('#profileOtherRow').css('display') === 'none')) {
                $('#feedbackWrapper').hide();
                $('#profileOtherRow').slideDown(positionFeedbackDiv);
            } else if ($('#profileOtherRow').css('display') === 'block') {
                $('#feedbackWrapper').hide();
                $('#profileOtherRow').slideUp(positionFeedbackDiv);
                $('#otherCrowdType').val('');
            }
        });

        $('#crowdType option').tipTip();
EOT;

