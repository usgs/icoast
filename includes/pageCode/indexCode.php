<?php

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/openid.php');
require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, FALSE);

if (isset($_GET['error'])) {
    $redirectError = $_GET['error'];
} else {
    $redirectError = FALSE;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin all User Statistics


$numberOfUsersQuery = "SELECT COUNT(*) FROM users";
$numberOfUsers = $DBH->query($numberOfUsersQuery)->fetchColumn();
$formattedNumberOfUsers = number_format($numberOfUsers);
if ($numberOfUsers == 1) {
    $numberOfUsersWelcomeText = 'User';
    $numberOfUsersHomeText = 'user';
} else {
    $numberOfUsersWelcomeText = 'Users';
    $numberOfUsersHomeText = 'users';
}


$numberOfUsersWithAClassificationQuery = "SELECT COUNT(DISTINCT user_id)  "
        . "FROM annotations "
        . "WHERE annotation_completed = 1";
$numberOfUsersWithAClassification = $DBH->query($numberOfUsersWithAClassificationQuery)->fetchColumn();
if ($numberOfUsersWithAClassification == 1) {
    $numberOfUsersWithAClassificationText = 'User has';
} else {
    $numberOfUsersWithAClassificationText = 'Users have';
}
$formattedNumberOfUsersWithAClassification = number_format($numberOfUsersWithAClassification);
$formattedPercentageOfUsersWithAClassification = round(($numberOfUsersWithAClassification / $numberOfUsers ) * 100, 1);

$timeTotal = 0;
$classificationCount = 0;
$avgClassificationTimeQuery = "SELECT user_id, annotation_id, image_id, initial_session_start_time, initial_session_end_time "
        . "FROM annotations "
        . "WHERE annotation_completed = 1 AND annotation_completed_under_revision = 0";
foreach ($DBH->query($avgClassificationTimeQuery) as $classification) {
    $startTime = strtotime($classification['initial_session_start_time']);
    $endTime = strtotime($classification['initial_session_end_time']);
    $timeDelta = $endTime - $startTime;
    if ($timeDelta <= (600)) {
        $timeTotal += $timeDelta;
        $classificationCount++;
    }
}
if ($classificationCount > 0) {
    $averageTime = $timeTotal / $classificationCount;
//    $formattedTotalClassificationTime = convertSeconds($timeTotal);
    $formattedShortAverageClassificationTime = convertSeconds($averageTime, false);
    $formattedLongAverageClassificationTime = convertSeconds($averageTime);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin project Statistics

$projectInFocusQuery = "SELECT p.project_id, p.name "
        . "FROM system s "
        . "LEFT JOIN projects p ON s.home_page_project = p.project_id "
        . "WHERE p.is_public = 1";
$projectInFocus = $DBH->query($projectInFocusQuery)->fetch(PDO::FETCH_ASSOC);

$numberOfCompleteProjectClassificationsQuery = "SELECT COUNT(*) "
        . "FROM annotations "
        . "WHERE annotation_completed = 1 AND project_id = {$projectInFocus['project_id']}";
$numberOfCompleteProjectClassifications = $DBH->query($numberOfCompleteProjectClassificationsQuery)->fetchColumn();
$formattedNumberOfCompleteProjectClassifications = number_format($numberOfCompleteProjectClassifications);
if ($numberOfCompleteProjectClassifications == 1) {
    $numberOfCompleteProjectClassificationsText = 'Classification';
} else {
    $numberOfCompleteProjectClassificationsText = 'Classifications';
}


$numberOfProjectTagsSelectedQuery = "SELECT COUNT(*) "
        . "FROM annotation_selections anns "
        . "LEFT JOIN tags t ON anns.tag_id = t.tag_id "
        . "WHERE t.project_id = {$projectInFocus['project_id']}";
$numberOfProjectTagsSelected = $DBH->query($numberOfProjectTagsSelectedQuery)->fetchColumn();
$formattedNumberOfProjectTagsSelected = number_format($numberOfProjectTagsSelected);
if ($numberOfProjectTagsSelected == 1) {
    $numberOfProjectTagsSelectedText = 'Tag';
} else {
    $numberOfProjectTagsSelectedText = 'Tags';
}


$numberOfProjectPhotosQuery = "SELECT COUNT(*) "
        . "FROM images i "
        . "INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
        . "("
        . "     SELECT DISTINCT post_collection_id "
        . "     FROM projects "
        . "     WHERE project_id = {$projectInFocus['project_id']}"
        . ") "
        . "AND m.pre_collection_id IN "
        . "("
        . "     SELECT DISTINCT pre_collection_id "
        . "     FROM projects "
        . "     WHERE project_id = {$projectInFocus['project_id']}"
        . ") "
        . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN "
        . "("
        . "     SELECT dataset_id "
        . "     FROM datasets "
        . "     WHERE collection_id IN "
        . "     ("
        . "         SELECT DISTINCT post_collection_id "
        . "         FROM projects "
        . "         WHERE project_id = {$projectInFocus['project_id']}"
        . "     )"
        . ")";
$numberOfProjectPhotos = $DBH->query($numberOfProjectPhotosQuery)->fetchColumn();
$formattedNumberOfProjectPhotos = number_format($numberOfProjectPhotos);


$numberOfClassifiedProjectPhotosQuery = "SELECT COUNT(DISTINCT a.image_id) "
        . "FROM annotations a "
        . "INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1 "
        . "INNER JOIN matches m ON m.post_image_id = a.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
        . "("
        . "     SELECT DISTINCT post_collection_id "
        . "     FROM projects "
        . "     WHERE project_id = {$projectInFocus['project_id']}"
        . ") "
        . "AND m.pre_collection_id IN "
        . "("
        . "     SELECT DISTINCT pre_collection_id "
        . "     FROM projects "
        . "     WHERE project_id = {$projectInFocus['project_id']}"
        . ") "
        . "WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$projectInFocus['project_id']}";
$numberOfClassifiedProjectPhotos = $DBH->query($numberOfClassifiedProjectPhotosQuery)->fetchColumn();
$formattedNumberOfClassifiedProjectPhotos = number_format($numberOfClassifiedProjectPhotos);
if ($numberOfClassifiedProjectPhotos == 1) {
    $numberOfClassifiedProjectPhotosText = 'Photo has';
} else {
    $numberOfClassifiedProjectPhotosText = 'Photos have';
}



$numberOfUnclassifiedProjectPhotos = $numberOfProjectPhotos - $numberOfClassifiedProjectPhotos;

$formattedNumberOfUnclassifiedProjectPhotos = number_format($numberOfUnclassifiedProjectPhotos);
if ($numberOfUnclassifiedProjectPhotos == 1) {
    $numberOfUnclassifiedProjectPhotosText = 'Photo has';
} else {
    $numberOfUnclassifiedProjectPhotosText = 'Photos have';
}
if ($numberOfProjectPhotos > 0) {
    $formattedPercentageOfProjectPhotosWithAClassification = round(($numberOfClassifiedProjectPhotos / $numberOfProjectPhotos ) * 100, 1);
} else {
    $formattedPercentageOfProjectPhotosWithAClassification = '0';
}

$welcomeStatisticsHTML1 = <<<EOL
    <div id="welcomePageStatisticsWrapper">
         <div>
            <h2>{$projectInFocus['name']} Project Statistics</h2>
            <div class="cssStatistics">
                <p><span class="statisticNumber">$formattedNumberOfProjectTagsSelected</span>$numberOfProjectTagsSelectedText selected</p>
                <p><span class="statisticNumber">$formattedNumberOfCompleteProjectClassifications</span>$numberOfCompleteProjectClassificationsText in total</p>
                <p><span class="statisticNumber">$formattedNumberOfClassifiedProjectPhotos of $formattedNumberOfProjectPhotos</span>$numberOfClassifiedProjectPhotosText been classified</p>
                <p><span class="statisticNumber">$formattedNumberOfUnclassifiedProjectPhotos of $formattedNumberOfProjectPhotos</span>$numberOfUnclassifiedProjectPhotosText not been classified</p>
                <div class="progressBar">
                    <div class="progressBarFill" id="alliCoastProgressBar" style="width: $formattedPercentageOfProjectPhotosWithAClassification%"></div>
                    <span class="progressBarText">$formattedPercentageOfProjectPhotosWithAClassification% Of Photos Completed</span>
                </div>
            </div>
        </div>

        <div>
            <h2>All iCoast User Statistics</h2>
            <div>
                <div class="cssStatistics">
                    <p><span class="statisticNumber">$formattedNumberOfUsers</span>$numberOfUsersWelcomeText in iCoast</p>
                    <p><span class="statisticNumber">$formattedNumberOfUsersWithAClassification</span>$numberOfUsersWithAClassificationText classified one or more photo</p>
                    <p title="Average time is calculated form all classifications completed in 10 minutes or less"><span class="statisticNumber">$formattedShortAverageClassificationTime</span>Is the average time it takes to classify one photo</p>
                </div>
            </div>
EOL;
$welcomeStatisticsHTML2 = <<<EOL
        </div>
    </div>
EOL;
if ($numberOfUnclassifiedProjectPhotos > 0) {
    $welcomeMapHTML = <<<EOL
        <h2>Locations of Unclassified Photos in the {$projectInFocus['name']} Project</h2>
        <div id="welcomePageMapWrapper">
        </div>
EOL;
} else {
    $welcomeMapHTML = '';
}


if ($userData) {
    $userEmail = $userData['masked_email'];
    $userId = $userData['user_id'];
    $userType = $_GET['userType'];
    $errorHTML = '';

    switch ($userType) {
        case 'new':
            $welcomeHTML = '<h1>Welcome to USGS iCoast</h1>';
            $taggingButtonContent = "Start Tagging Photos";
            $personalStatisticsButtonHTML = '';
            $newUserHTML = '<h2>Thanks for joining USGS iCoast</h2>
        <p>Check out the first iCoast project showing aerial photographs taken after Hurricane Sandy.</p>';
            break;
        case 'existing':
            $welcomeHTML = '<h1>Welcome Back to USGS iCoast</h1>';
            $taggingButtonContent = "Continue Tagging Photos";
            $personalStatisticsButtonHTML = '<input type="button" id="myiCoastLinkButton" class="clickableButton" value="See Your Personal Statistics In My iCoast">';
            $newUserHTML = '';
            $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#myiCoastLinkButton').click(function() {
                        window.location = 'myicoast.php';
                    });

EOL;
            break;
        default:
            if ($redirectError == "admin") {
                $errorHTML = <<<EOL
                    <h2>Permission Error</h2>
                    <p class="error">Your user account has insufficient privileges to access the
                        requested page. If you believe you should have elevated permissions within iCoast then
                        please contact the iCoast System Administrator at
                        <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
            } else {
                header('Location: index.php?userType=existing');
            }
            break;
    }
    if ($numberOfUnclassifiedProjectPhotos != 0) {
        $unclassifiedPhotoQuery = "SELECT i.image_id, i.latitude, i.longitude, m.post_collection_id, m.pre_collection_id, m.pre_image_id "
                . "FROM images i "
                . "INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
                . "     ("
                . "         SELECT DISTINCT post_collection_id "
                . "         FROM projects "
                . "         WHERE project_id = {$projectInFocus['project_id']}"
                . "     ) "
                . "     AND m.pre_collection_id IN "
                . "     ("
                . "         SELECT DISTINCT pre_collection_id "
                . "         FROM projects "
                . "         WHERE project_id = {$projectInFocus['project_id']}"
                . "     ) "
                . "LEFT JOIN annotations a ON a.image_id = i.image_id "
                . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN "
                . " ("
                . "     SELECT dataset_id "
                . "     FROM datasets "
                . "     WHERE collection_id "
                . "     IN ("
                . "         SELECT DISTINCT post_collection_id "
                . "         FROM projects "
                . "         WHERE project_id = {$projectInFocus['project_id']}"
                . "         ) "
                . " ) "
                . "GROUP BY i.image_id "
                . "HAVING COUNT(DISTINCT a.annotation_id) = 0 OR (COUNT(DISTINCT a.annotation_id) != 0 AND SUM(a.annotation_completed) = 0)";
        $result = $DBH->query($unclassifiedPhotoQuery)->fetchAll(PDO::FETCH_ASSOC);
        $unclassifiedPhotoJSON = json_encode($DBH->query($unclassifiedPhotoQuery)->fetchAll(PDO::FETCH_ASSOC));

        $jQueryDocumentDotReadyCode .= <<<EOL
            var projectId = {$projectInFocus['project_id']};
            var unclassifiedPhotos = $unclassifiedPhotoJSON;
            var map = L.map('welcomePageMapWrapper', {maxZoom: 16});
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
            var markers = L.markerClusterGroup({
                disableClusteringAtZoom: 9,
                maxClusterRadius: 60
            });
            $.each(unclassifiedPhotos, function(key, photo) {
                var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                marker.on('click', function() {
                    markers.eachLayer(function (layer) {
                        layer.setIcon(redMarker);
                        layer.setZIndexOffset(0);
                    });
                    this.setIcon(greenMarker);
                    this.setZIndexOffset(100000);
                    var imageData = {
                        imageId: photo.image_id
                    }
                    $.getJSON('ajax/popupGenerator.php', imageData, function(popupData) {
                        marker.bindPopup('Image ID: <a href="classification.php?projectId=' + projectId + '&imageId=' + photo.image_id + '">' + photo.image_id + '</a><br>'
                            + 'Location: ' + popupData.location + '<br>'
                            + '<a href="classification.php?projectId=' + projectId + '&imageId=' + photo.image_id + '"><img class="mapMarkerImage" width="167" height="109" src="' + popupData.thumbnailURL + '" /></a>'
                            + '<div style="text-align: center"><input type="button" id="unclassifiedPhotoButton" class="clickableButton" value="Tag This Photo"></div>',
                            {closeOnClick: true}
                        ).openPopup();
                        $('#unclassifiedPhotoButton').click(function() {
                            window.location = 'classification.php?projectId=' + projectId + '&imageId=' + photo.image_id;
                        });
                    });
                });
                markers.addLayer(marker);
            });
            map.fitBounds(markers.getBounds());
            markers.addTo(map);

EOL;
    }


    $variablePageContent = <<<EOL
        $welcomeHTML
        <p>You are logged in as <span class="userData">$userEmail</span><br>
            If this is not you, <a href="logout.php">logout</a> then login with your Google Account.</p>
        $newUserHTML
        $errorHTML
        <input type="button" class="clickableButton" id="startTaggingButton" value="$taggingButtonContent">
        $welcomeStatisticsHTML1
        $personalStatisticsButtonHTML
        $welcomeStatisticsHTML2
        $welcomeMapHTML
EOL;

    $cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';
    $cssLinkArray[] = 'css/markerCluster.css';

    $javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';
    $javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';



    $jQueryDocumentDotReadyCode .= <<<EOL
        $('#startTaggingButton').click(function() {
            window.location = 'start.php';
        });

EOL;
} else {


























    $openid = new LightOpenID('http://' . $_SERVER['HTTP_HOST']);

    if (!$openid->mode) {
        if ($redirectError == 'auth') {
            $variableHomeContent = <<<EOL
                <p class="error">Your local authentication credentials have lost synchronization with the
                    server and your login has been reset. Please click the button below to log back in to
                    iCoast using Google. If this problem persists then please contact the iCoast System
                    Administrator at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
        } else if ($redirectError == 'disabled') {
            $variableHomeContent = <<<EOL
                    <p class="error">Sorry, your iCoast account is currently disabled. If you believe this has
                        been done in error then please contact the iCoast System Administrator at
                        <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
        } else if ($redirectError == "cookies") {
            $variableHomeContent = <<<EOL
                    <p class="error">You have attempted to access a page that requires you to be logged in.
                        Please login and try the page again. If this error persists then first ensure that your
                        browser is set to accept cookies before contacting the iCoast System Administrator
                        for assistance at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
        } else {
            $variableHomeContent = <<<EOL
                <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                    using Google.</p>
EOL;
        }

        if (isset($_GET['login'])) {
            $openid->identity = 'https://www.google.com/accounts/o8/id';
            $openid->required = array('contact/email');
            header('Location: ' . $openid->authUrl());
        }
    } elseif ($openid->mode == 'cancel') {
        $variableHomeContent = <<<EOL
          <p class="error">Authentication process was cancelled. Click the button below to start the login process again</p>

EOL;
    } else {
        if (!$openid->validate()) {
            $variableHomeContent = <<<EOL
          <p class="error">Authentication failed. Click the button below to try again.</p>

EOL;
        } else {
            $user = $openid->getAttributes();
            $googleUserEmail = filter_var($user['contact/email'], FILTER_VALIDATE_EMAIL);
            if (!$googleUserEmail) {
//            Placeholder for error management
                print 'Error. Invalid eMail Address.<br>';
                exit;
            }
            $maskedUserEmail = mask_email($googleUserEmail);

            $queryStatement = "SELECT * FROM users WHERE masked_email = :maskedEmail";
            $queryParams['maskedEmail'] = $maskedUserEmail;
            $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
            $queryResult = $STH->fetchAll(PDO::FETCH_ASSOC);
            if (count($queryResult) > 0) {
                $userFound = FALSE;
                foreach ($queryResult as $userCredentials) {
                    $decryptedEmail = mysql_aes_decrypt($userCredentials['encrypted_email'], $userCredentials['encryption_data']);
                    if (strcasecmp($decryptedEmail, $googleUserEmail) === 0) {
                        $userFound = TRUE;
                        $authCheckCode = md5(rand());

                        $queryStatement = "UPDATE users SET auth_check_code = :authCheckCode, last_logged_in_on = now() WHERE user_id = :userId";
                        $queryParams = array(
                            'authCheckCode' => $authCheckCode,
                            'userId' => $userCredentials['user_id']
                        );
                        $STH = run_prepared_query($DBH, $queryStatement, $queryParams);

                        if ($STH->rowCount() === 1) {
                            setcookie('userId', $userCredentials['user_id'], time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                            setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                            header('Location: index.php?userType=existing');
                            exit;
                        } else {
                            $variableHomeContent = <<<EOL
          <p class="error">Appliaction Failure. Unable to contact database. Please try again in a few minutes or advise an administrator of this problem.</p>
EOL;
                        }
                    }
                }
            }
            if (count($queryResult) === 0 || $userFound === FALSE) {
                setcookie('registrationEmail', $googleUserEmail, time() + 60 * 5, '/', '', 0, 1);
                header('Location: registration.php');
                exit;
            }
        }
    }

    $slideShowContentQuery = <<<EOL
        SELECT image_name, image_alt_text, caption_header, caption_text
        FROM slideshow s
        WHERE slideshow_group_id = (
            SELECT slideshow_group_id
            FROM system
        ) AND is_enabled = 1
        ORDER BY position_in_slideshow
EOL;
    $slideShowContent = $DBH->query($slideShowContentQuery)->fetchAll(PDO::FETCH_ASSOC);
    $jsSlideShowContent = json_encode($slideShowContent);

    $slideshowJumpButtonHTML = '';
    for ($i = 0; $i < count($slideShowContent); $i++) {
        $slideshowJumpButtonHTML .= <<<EOL
            <div class="slideshowJumpButton" id="jumpButton$i"></div>
EOL;
            $jQueryDocumentDotReadyCode .= <<<EOL
                $('#jumpButton$i').data('id', $i);
                $('#jumpButton$i').click(function() {
                    $('.slideshowJumpButton').each(function () {
                        $(this).css({
                            "border": "2px solid black",
                            "background-color": "white"
                        });
                    });
                    $(this).css({
                        "border": "2px solid white",
                        "background-color": "black"
                    });
                    loadImage = $(this).data('id');
                    loadIndexImageContent('displayed');
                    loadIndexImageContent('next');
                    clearInterval(slideTimer);
                    slideTimer = setInterval(slideNewImage, 15000);
                });

EOL;
    }


    $variableHomeContent .= <<<EOL
          <form action="?login" method="post">
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton"
                    value="Login or Register with Google" title="Click to begin iCoast login using an account
                    authenticated by Google (examples of accounts that can be used: aperson@gmail.com, aperson@usgs.gov)" />
            </div>
            </form>
            <p><span class="captionTitle">Note:</span> Any Google based account, including standard Gmail accounts or those managed by you or your
                organization, can be used to create an iCoast account.</p>
            <p>(Examples: aperson@gmail.com, aperson@usgs.gov, aperson@university.edu)</p>
            <p><a href="help.php#loginFAQ">Why Google?</a></p>
EOL;

    $variablePageContent = <<<EOL
        <div id="homePageLoggedOutWrapper">
            <div id="indexImageColumn">
                <div id="indexImageWrapper">
                    <img src="images/system/indexSlideShowImages/{$slideShowContent[0]['image_name']}"
                        alt="{$slideShowContent[0]['image_alt_text']}" height="435" width="670" title="" />
                    <img src="" alt="" height="435" width="670" title="" />
                    <div id="slideshowJumpButtonWrapper">
                        $slideshowJumpButtonHTML
                    </div>
                </div>
                <div id="imageCaptionWrapper">
                    <p><span class="captionTitle" id="captionTitle">{$slideShowContent[0]['caption_header']}</span> <span id="captionText">{$slideShowContent[0]['caption_text']}</span></p>
                </div>
            </div>
            <div id="indexTextColumn">
            <h1>Welcome to USGS iCoast!</h1>
            <p>Help scientists at the U.S. Geological Survey (<a href="http://www.usgs.gov/">USGS</a>) annotate aerial photographs with keyword tags to
                identify changes to the coast after extreme storms like Hurricane Sandy. We need your eyes to
                help us understand how our coastlines are changing from extreme storms.</p>
            $variableHomeContent
            </div>
            <div id="homePageStatisticsWrapper">
                <p>Help us classify the remaining <span class="userData">$formattedNumberOfUnclassifiedProjectPhotos photos</span> in the {$projectInFocus['name']} Project!</p>
                <p>It takes an average of <span class="userData">$formattedLongAverageClassificationTime</span> to classify one photo!</p>
                <p><span class="userData">$formattedNumberOfClassifiedProjectPhotos of $formattedNumberOfProjectPhotos photos</span> have been classified!</p>
                <div class="progressBar">
                    <div class="progressBarFill" id="alliCoastProgressBar" style="width: $formattedPercentageOfProjectPhotosWithAClassification%"></div>
                    <span class="progressBarText">$formattedPercentageOfProjectPhotosWithAClassification% Of Photos Complete</span>
                </div>

            </div>
        </div>

EOL;

    $javaScript .= <<<EOL


        function loadIndexImageContent (targetImage) {

            if (targetImage == 'next') {
                loadImage ++;
            }

            if (loadImage >= numberOfImages) {
                loadImage = 0;
            }

            var image = 'images/system/indexSlideshowImages/' + slideShowContent[loadImage]['image_name'];
            var altTag = slideShowContent[loadImage]['image_alt_text'];
            var captionHeader = slideShowContent[loadImage]['caption_header'];
            var caption = slideShowContent[loadImage]['caption_text'];

            switch (targetImage) {
            case 'displayed':
                $('#indexImageWrapper img:first-of-type').attr('src', image);
                $('#indexImageWrapper img:first-of-type').attr('alt', altTag)
                $('#captionTitle').text(captionHeader);
                $('#captionText').text(caption);

                break;
            case 'next':
                previousImage = loadImage - 1;
                if (previousImage < 0) {
                    previousImage = numberOfImages - 1;
                }
                $('#indexImageWrapper img:last-of-type').attr('src', image);
                $('#indexImageWrapper img:last-of-type').attr('atr', altTag);
                nextCaptionHeader = slideShowContent[loadImage]['caption_header'];
                nextCaption = slideShowContent[loadImage]['caption_text'];
                break;
            }
        }

        function slideNewImage() {
            $('#jumpButton' + loadImage).css({
                "border": "2px solid white",
                "background-color": "black"
            });
            $('#jumpButton' + previousImage).css({
                "border": "2px solid black",
                "background-color": "white"
            });
            $('#imageCaptionWrapper').slideUp(500,
                function() {
                    $('#captionTitle').text(nextCaptionHeader);
                    $('#captionText').text(nextCaption);
                    $('#imageCaptionWrapper').slideDown(500);
                });
            $('#indexImageWrapper img:first-of-type').animate({
                left: -670
            }, 1000);
            $('#indexImageWrapper img:last-of-type').animate({
                left: 0
            }, 1000, function() {
                $('#indexImageWrapper img:first-of-type').remove();
                $('#indexImageWrapper img:first-of-type').after('<img src="" alt="" height="435" width="670" title="" />');
                loadIndexImageContent('next');
            });
        }

        var slideShowContent = $jsSlideShowContent;

        var loadImage = 0;
        var previousImage;
        var numberOfImages = slideShowContent.length;
        var nextCaptionHeader;
        var nextCaption;

EOL;

    $jQueryDocumentDotReadyCode .= <<<EOL
        loadIndexImageContent('displayed');
        loadIndexImageContent('next');
        var slideTimer = setInterval(slideNewImage, 15000);
EOL;
}



