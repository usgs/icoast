<?php
ob_start();
?>
<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
PHP Code file contents (PHP Generated Output)-->
<?php
$pageName = "profile";
$cssLinkArray = array();
$embeddedCSS = '#historyControlWrapper p:first-of-type {margin-top: 0px; padding-top: 10px;}';
$javaScriptLinkArray[] = "//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js";
$javaScriptLinkArray[] = 'scripts/markerClusterPlus.js';
$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";

require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    header('Location: login.php');
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
$newEmailError = '';
$confirmEmailError = '';
$crowdTypeError = '';
$otherCrowdTypeError = '';
$affiliationError = '';
$newEmail = '';
$confirmNewEmail = '';
$otherCrowdType = '';
$affiliationContent = '';
$updateAck = '';


if (isset($_POST['formSubmission'])) {
    switch ($_POST['formSubmission']) {






        case 'email':
            $newEmail = (isset($_POST['newEmail'])) ? strtolower(trim($_POST['newEmail'])) : null;
            $confirmNewEmail = (isset($_POST['confirmNewEmail'])) ? strtolower(trim($_POST['confirmNewEmail'])) : null;
            $filteredNewEmail = filter_var($newEmail, FILTER_VALIDATE_EMAIL);
            if (empty($filteredNewEmail)) {
                $errorMessage['newEmail'] = 'You must specify a valid new eMail Address.';
            } else if (empty($confirmNewEmail) || strcasecmp($filteredNewEmail, $confirmNewEmail) != 0) {
                $errorMessage['confirmEmail'] = 'Your confirmation eMail address must match your new eMail address.';
            }


            if (!isset($errorMessage)) {
                $maskedEmail = mask_email($newEmail);
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
                        'maskedEmail' => mask_email($newEmail),
                        'encryptedEmail' => $encryptedEmailData[0],
                        'encryptionData' => $encryptedEmailData[1],
                        'userId' => $userId
                    );
                    $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                    if ($STH->rowCount() == 1) {
                        header('Location: profileDev.php?update=email');
                        exit;
                    } else {
                        print $STH->rowCount();
                        //  Placeholder for error management
                        print 'Error. Account update failed. No details have been changed.<br>';
                        exit;
                    }
                } else {
                    $newEmailError = '<label class="error" for="newEmail">The specified eMail address already '
                            . 'exists within iCoast.</label>';
                    $confirmNewEmail = '';
                }
            } else if (isset($errorMessage['newEmail'])) {
                $newEmailError = '<label class="error" for="newEmail">' . $errorMessage['newEmail'] . '</label>';
            } else if (isset($errorMessage['confirmEmail'])) {
                $confirmEmailError = '<label class="error" for="confirmEmail">' . $errorMessage['confirmEmail'] . '</label>';
            }


            $newEmail = htmlentities($newEmail);
            $confirmNewEmail = htmlentities($confirmNewEmail);
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
                $profileUpdateQuery = "UPDATE users SET crowd_type = :crowdType, other_crowd_type = :otherCrowdType "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'crowdType' => $crowdType,
                    'otherCrowdType' => $otherCrowdType,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profileDev.php?update=crowd');
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
            }
            if (isset($crowdType) && $crowdType == 2) {
                $crowdType2HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 3) {
                $crowdType3HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 4) {
                $crowdType4HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 5) {
                $crowdType5HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 6) {
                $crowdType6HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 7) {
                $crowdType7HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 8) {
                $crowdType8HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 9) {
                $crowdType9HTML = 'selected="selected"';
            }
            if (isset($crowdType) && $crowdType == 10) {
                $crowdType10HTML = 'selected="selected"';
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
                $profileUpdateQuery = "UPDATE users SET affiliation = :affiliation "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'affiliation' => $affiliationContent,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profileDev.php?update=affiliation');
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
                if ($timeZone < 1 || $timeZone > 7) {
                    $errorMessage['timeZone'] = 'The specified time zone is invalid.';
                }
            }
            if (!isset($errorMessage)) {
                $profileUpdateQuery = "UPDATE users SET time_zone = :timeZone "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'timeZone' => $timeZone,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profileDev.php?update=timeZone');
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['timeZone'])) {
                $timeZoneError = '<label class="error" for="registerTimeZone">' . $errorMessage['timeZone'] . '</label>';
            }

            if (isset($timeZone) && $timeZone == 1) {
                $timeZone1HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 2) {
                $timeZone2HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 3) {
                $timeZone3HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 4) {
                $timeZone4HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 5) {
                $timeZone5HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 6) {
                $timeZone6HTML = 'selected="selected"';
            }
            if (isset($timeZone) && $timeZone == 7) {
                $timeZone7HTML = 'selected="selected"';
            }
            break;
    }
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
    $projectSelectionOptions = '';
    foreach ($projectMetadata as $singleProject) {
        $projectId = $singleProject['project_id'];
        $projectName = $singleProject['name'];
        $projectSelectionOptions .= "<option value=\"$projectId\">$projectName</option>\n\r";
    }
}
?>



<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
Javascript Code File Contents-->
<?php
$javaScriptLinks = '';
if (count($javaScriptLinkArray) > 0) {
    foreach ($javaScriptLinkArray as $link) {
        $javaScriptLinks .= "<script src='$link'></script>\n\r";
    }
}
print $javaScriptLinks;
?>
<script>
//  Content Start         ##############################
    var userId = <?php print $userId ?>;
    var timeZone = <?php print $userData['time_zone'] ?>;
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

    icMap = null;
    icMarkers = null;
    icMarkerClusterer = null;



    function runAjaxAnnotationQuery() {
        if (startingRow < 0) {
            startingRow = 0;
        }
        $ajaxData = {
            userId: userId,
            userTimeZone: timeZone,
            projectId: searchedProject,
            startingRow: startingRow,
            rowsPerPage: rowsPerPage
        };
        $.post('ajax/userHistory.php', $ajaxData, displayUserHistory, 'json');
    }

    function displayUserHistory(history) {
        resultCount = parseFloat(history.controlData.resultCount);
        delete history.controlData;
        var tableContents;
        displayedRows = 0;
        resultSetMapBounds = new google.maps.LatLngBounds;
        icMarkers = new Array();
        $.each(history, function(index, annotation) {
            var annotationPoint = new google.maps.LatLng(annotation.latitude, annotation.longitude);
            resultSetMapBounds.extend(annotationPoint);

            var thisMarker = new google.maps.Marker({
                position: annotationPoint,
                icon: 'images/system/photo.png',
                title: 'Image Id: ' + annotation.image_id + '. Location ' + annotation.location +
                        '. Tagged on: ' + annotation.annotation_time + '. Part of the ' +
                        annotation.project_name + ' project.'

            });
            icMarkers.push(thisMarker);
            google.maps.event.addListener(thisMarker, 'click', (function(marker) {
                return function() {
                    $('tr').css('background-color', '#FFFFFF');
                    $('td').each(function() {
                        if ($(this).text() == annotation.image_id) {
                            $(this).parent().css('background-color', '#C7DBCC');
                        }
                    });
                };
            })(thisMarker));

            if (icMarkerClusterer === null) {
                var mcOptions = {
                    'gridSize': 60,
                    'minimumClusterSize': 5,
                    'maxZoom': 14,
                    'imagePath': 'images/system/m'
                };
                icMarkerClusterer = new MarkerClusterer(icMap, icMarkers, mcOptions);
            } else {
                icMarkerClusterer.clearMarkers();
                icMarkerClusterer.addMarkers(icMarkers);
            }

            displayedRows++;
            tableContents += '<tr>';

            if (annotation.annotation_completed == 1) {
                var classificationLink = '<td><a href="classification.php?projectId=' + annotation.project_id +
                        '&imageId=' + annotation.image_id + '">';
            } else {
                var classificationLink = '<td class="incompleteAnnotation"><a href="classification.php?projectId=' + annotation.project_id +
                        '&imageId=' + annotation.image_id + '">';
            }
            tableContents += classificationLink + annotation.image_id + '</a></td>';
            tableContents += classificationLink + annotation.location + '</a></td>';
            tableContents += classificationLink + annotation.annotation_time + '</a></td>';
            tableContents += classificationLink + annotation.time_spent + '</a></td>';
            tableContents += classificationLink + annotation.number_of_tags + '</a></td>';
            tableContents += classificationLink + annotation.project_name + '</a></td>';
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

        $('#profileTableWrapper p').remove();
        $('#profileTableWrapper').append('<p class="footer">Page ' + currentPage + ' of ' + totalPages +
                '. Displaying rows ' + topRow + ' - ' + bottomRow +
                ' of ' + resultCount + ' total results (' + totalRows + ' rows shown)</p>');

        if ($('#userAnnotationHistory').css('display') === 'none') {
            $('#userAnnotationHistory').slideDown();
        }
        google.maps.event.trigger(icMap, "resize");
        icMap.setZoom(20);
        icMap.fitBounds(resultSetMapBounds);

        if ($('#profileDetailsWrapper').css('display') !== 'none') {
            $('#profileDetailsWrapper').slideUp();
            $('#profileHideButton').text('Show Profile Details');
        }
    }


    function initializeMaps() {
        var startingPosition = new google.maps.LatLng(27.764463, -82.638284);
        var mapOptions = {
            center: startingPosition,
            mapTypeId: google.maps.MapTypeId.HYBRID,
            zoom: 16
        };
        icMap = new google.maps.Map(document.getElementById("mapCanvas"),
                mapOptions);
    } // End function initializeMaps


//  Content End           ##############################
</script>




<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
Javascript Document.Ready Code File Contents-->
<script>
    $(document).ready(function() {
//  Content Start     ##############################
        $('#emailChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeEmailFormWrapper').slideDown();
        });

        $('#crowdTypeChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeCrowdFormWrapper').slideDown();
        });

        $('#affiliationChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeAffiliationFormWrapper').slideDown();
        });

        $('#timeZoneChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeTimeZoneFormWrapper').slideDown();
        });

        $('.cancelUpdateButton').click(function() {
            $('.profileUpdateForm').slideUp();
            $('.profileUpdateField').slideDown();
        });


        $('#historyHideButton').click(function() {
            $('#userAnnotationHistory').slideUp(function() {
                rowsPerPage = 10;
                $('#resultSizeSelect').prop('selectedIndex', 0);
            });
        });

        <?php
        if (!empty($newEmailError) || !empty($confirmNewEmailError)) {
            print "$('#emailChangeButton').trigger('click');";
        } else if (!empty($crowdTypeError) || !empty($otherCrowdTypeError)) {
            print "$('#crowdTypeChangeButton').trigger('click');";
        } else if (!empty($affiliationError)) {
            print "$('#affiliationChangeButton').trigger('click');";
        } else if (!empty($timeZoneError)) {
            print "$('#timeZoneChangeButton').trigger('click');";
        }
        ?>

        $('#profileHideButton').click(function() {
            if ($('#profileDetailsWrapper').css('display') === 'none') {
                $('#profileHideButton').text('Hide Profile Details');
            } else {
                $('#profileHideButton').text('Show Profile Details');
            }
            $('#profileDetailsWrapper').slideToggle();
        });

        $('#controlHideButton').click(function() {
            if ($('#historyControlWrapper').css('display') === 'none') {
                $('#controlHideButton').text('Hide History Controls');
            } else {
                $('#controlHideButton').text('Show History Controls');
            }
            $('#historyControlWrapper').slideToggle();
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
                $.post('ajax/userHistory.php', $ajaxData, displayUserHistory, 'json');
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

        $('#eMailForm').validate({
            rules: {
                newEmail: {
                    required: true
                },
                confirmNewEmail: {
                    equalTo: '#newEmail'
                }
            },
            messages: {
                newEmail: {
                    required: 'You must specify your new eMail address to continue.'
                },
                confirmNewEmail: {
                    equalTo: 'Your confirmation eMail entry must match your first entry.'
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
                $('#profileOtherRow').slideDown();
            } else if ($('#profileOtherRow').css('display') === 'block') {
                $('#profileOtherRow').slideUp();
                $('#otherCrowdType').val('');
            }
        });
//  Content End       ##############################
    }
    );</script>



<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
Template - DO NOT ALTER OUTSIDE OF PAGE BODY-->
<?php
require("includes/templateCode.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
        <link rel="stylesheet" href="css/header.css">
        <link rel="stylesheet" href="css/icoast.css">
        <?php
        print $cssLinks;
        ?>
        <style>
<?php print $embeddedCSS; ?>
        </style>

        <?php // print $javaScriptLinks;     ?>
        <script>
<?php
//          print $javaScript . "\n\r";
//          print $jQueryDocumentDotReadyCode;
?>
        </script>
    </head>
    <body>
        <!--Header-->
        <div id="usgsColorBand">
            <div id="usgsIdentifier">
                <a href="http://www.usgs.gov">
                    <img src="images/system/usgsIdentifier.jpg" alt="USGS - science for a changing world"
                         title="U.S. Geological Survey Home Page" width="178" height="72" />
                </a>
                <p id="appTitle">iCoast</p>
                <p id="appSubtitle">did the coast change?</p>
            </div>
            <div id="headerImageWrapper">
                <img src="images/system/hurricaneBanner.jpg" alt="An image from Space of a hurricane approaching the Florida coastline." />
            </div>
            <?php print $mainNav ?>
        </div>

        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        Load file contents (HTML Page Body)-->
        <div id="contentWrapper">
            <h1>Your iCoast Profile and Tagging History</h1>
            <div id="profileSettingsWrapper">
                <button id="profileHideButton" class="clickableButton hideProfilePanelButton">Hide Profile Details</button>
                <h2>Profile Details</h2>
                <div id="profileDetailsWrapper">
                    <?php print $updateAck ?>





                    <div class="formFieldRow profileUpdateField">
                        <label for="emailChangeButton">E-Mail: <span class="userData"><?php print $maskedEmail ?></span></label>
                        <input type="button" id="emailChangeButton" value="Change Email Address">
                    </div>
                    <div id="changeEmailFormWrapper" class="profileUpdateForm">
                        <h3>Change Your eMail Address</h3>
                        <p>Your current eMail address is <span class="userData"><?php print $maskedEmail ?></span></p>
                        <p>IMPORTANT: This option provides the ability for you to associate your iCoast account with a
                            different Google account. You should not change your address here unless you already have
                            already created and tested your new Google account. Upon clicking the button below you
                            will be logged out of iCoast but will still need to also log out of Google before re-accessing
                            iCoast. Type your new address carefully or your iCoast account could become permanently disassociated
                            with a working Google account. At this time iCoast only accepts Google Accounts. Do not specify
                            an address from any other entity.</p>
                        <form method="post" action="" id="eMailForm">
                            <input type="hidden" name="formSubmission" value="email" />
                            <div class="formFieldRow">
                                <label for="newEmail">New eMail Address:</label>
                                <input type="text" id="newEmail" name="newEmail" value="<?php print $newEmail ?>">
                                <?php print $newEmailError ?>
                            </div>
                            <div class="formFieldRow">
                                <label for="confirmNewEmail">Confirm New eMail Address:</label>
                                <input type="text" id="confirmNewEmail" name="confirmNewEmail"
                                       value="<?php print $confirmNewEmail ?>">
                                       <?php print $confirmEmailError ?>
                            </div>
                            <input type="submit" class="clickableButton" value="Change E-Mail">
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="crowdTypeChangeButton">Crowd Type: <span class="userData"><?php print $crowdType ?></span></label>
                        <input type="button" id="crowdTypeChangeButton" value="Change Crowd Type">
                    </div>
                    <div id="changeCrowdFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Crowd Type</h3>
                        <p>Your current crowd type is <span class="userData"><?php print $crowdType ?></span></p>
                        <form method="post" id="crowdForm">
                            <input type="hidden" name="formSubmission" value="crowd" />
                            <div class="formFieldRow">
                                <label for="crowdType">Crowd Type:</label>
                                <select id="crowdType" name="crowdType" >
                                    <option value="1" <?php print $crowdType1HTML ?>>Coastal Science Researcher</option>
                                    <option value="2" <?php print $crowdType2HTML ?>>Coastal Manager or Planner</option>
                                    <option value="3" <?php print $crowdType3HTML ?>>Coastal Resident</option>
                                    <option value="4" <?php print $crowdType4HTML ?>>Coastal Recreational User</option>
                                    <option value="5" <?php print $crowdType5HTML ?>>Marine Science Student</option>
                                    <option value="6" <?php print $crowdType6HTML ?>>Emergency Manager</option>
                                    <option value="7" <?php print $crowdType7HTML ?>>Policy Maker</option>
                                    <option value="8" <?php print $crowdType8HTML ?>>Digital Crisis Volunteer (VTC)</option>
                                    <option value="9" <?php print $crowdType9HTML ?>>Interested Public</option>
                                    <option value="10" <?php print $crowdType10HTML ?>>Other (Please specify below)</option>
                                </select>
                                <?php print $crowdTypeError ?>
                            </div>
                            <div class="formFieldRow" id="profileOtherRow">
                                <label for="otherCrowdType">Other Crowd Type: </label>
                                <input type="text" id="otherCrowdType" name="otherCrowdType" value="<?php print $otherCrowdType ?>"/>
                                <?php print $otherCrowdTypeError ?>
                            </div>
                            <input type="submit" class="clickableButton" value="Change Crowd Type">
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="affiliationChangeButton">Expertise or Affiliation: <span class="userData"><?php print $affiliation ?></span></label>
                        <input type="button" id="affiliationChangeButton" value="Change Expertise/Affiliation">
                    </div>
                    <div id="changeAffiliationFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Expertise or Affiliation</h3>
                        <p>Your current Expertise or Affiliation is <span class="userData"><?php print $affiliation ?></span></p>
                        <form method="post" id="affiliationForm">
                            <input type="hidden" name="formSubmission" value="affiliation" />
                            <div class="formFieldRow">
                                <label for="affiliation">Coastal Expertise or Affiliation (optional): </label>
                                <input type="text" id="affiliation" name="affiliation" value="<?php print $affiliationContent ?>" />
                                <?php print $affiliationError ?>
                            </div>
                            <input type="submit" class="clickableButton" value="Change Affiliation">
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="timeZoneChangeButton">Time Zone: <span class="userData"><?php print $timeZone ?></span></label>
                        <input type="button" id="timeZoneChangeButton" value="Change Time Zone">
                    </div>
                    <div id="changeTimeZoneFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Expertise or Affiliation</h3>
                        <p>Your current Expertise or Affiliation is <span class="userData"><?php print $affiliation ?></span></p>
                        <form method="post" id="timeZoneForm">
                            <input type="hidden" name="formSubmission" value="timeZone" />
                            <div class="formFieldRow">
                                <label for="timeZone">Time Zone:</label>
                                <select id="timeZone" name="timeZone" >
                                    <option value="1" $timeZone1HTML>Eastern</option>
                                    <option value="2" $timeZone2HTML>Central</option>
                                    <option value="3" $timeZone3HTML>Mountain</option>
                                    <option value="4" $timeZone3HTML>Mountain (Arizona)</option>
                                    <option value="5" $timeZone4HTML>Pacific</option>
                                    <option value="6" $timeZone5HTML>Alaskan</option>
                                    <option value="7" $timeZone6HTML>Hawaiian</option>
                                </select>
                                <?php print $timeZoneError ?>
                            </div>
                            <input type="submit" class="clickableButton" value="Change Time Zone">
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                        </form>
                    </div>






                </div>


            </div>
            <div id="profileAnnotationListControls">
                <button id="controlHideButton" class="clickableButton hideProfilePanelButton">Hide History Controls</button>
                <h2>View Your iCoast Tagging History</h2>
                <div id="historyControlWrapper">
                    <p>Choose from the options below to view either your complete tagging history across all of iCoast's projects...</p>
                    <input type="button" id="allPhotoButton" value="Complete History"><br>
                    <p>...or your specific history for a particular project</p>
                    <select id="projectSelection">
                        <?php print $projectSelectionOptions ?>
                    </select>
                    <input type="button" id="projectPhotoButton" value="Specific Project History">
                </div>
            </div>
            <div id="userAnnotationHistory">
                <button id="historyHideButton" class="clickableButton hideProfilePanelButton">Hide History Panel</button>
                <div id="profileTableWrapper">
                    <h2>Photos You Have Tagged</h2>
                    <div id="historyTableWrapper">
                        <div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Image ID</th>
                                        <th>Location</th>
                                        <th>Annotation Time</th>
                                        <th>Time Spent</th>
                                        <th># of Tags</th>
                                        <th>Project</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                        <div id="annotationTableControls">
                            <input type="button" id="firstPageButton" class="clickableButton disabledClickableButton" value="<<">
                            <input type="button" id="previousPageButton" class="clickableButton disabledClickableButton" value="<">
                            <select id="resultSizeSelect" class="disabledClickableButton" disabled>
                                <option value="10">10 Results Per Page</option>
                                <option value="20">20 Results Per Page</option>
                                <option value="30">30 Results Per Page</option>
                                <option value="50">50 Results Per Page</option>
                                <option value="100">100 Results Per Page</option>
                            </select>
                            <input type="button" id="lastPageButton" class="clickableButton disabledClickableButton" value=">>">
                            <input type="button" id="nextPageButton" class="clickableButton disabledClickableButton" value=">">
                        </div>
                    </div>
                </div>

                <div id="profileMapWrapper">
                    <h2>Map of Photos You Have Tagged</h2>
                    <div id="mapCanvas"></div>
                </div>
            </div>
        </div>



        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        END Load file contents (HTML Page Body)-->

    </body>
</html>


