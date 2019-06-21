<?php

// SHutdown text in line 40.

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, false);

$redirectError = filter_input(INPUT_GET, 'error');

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin What's New
$whatsNewHTML = '';
$whatsNewQuery = "
        SELECT 
            whats_new_title,
            whats_new_content
        FROM
            system
        WHERE
            id = 0
    ";
$whatsNewResult = run_prepared_query($DBH, $whatsNewQuery);
$whatsNew = $whatsNewResult->fetch();

if ($whatsNew['whats_new_content'])
{
    $whatsNewTitle = htmlspecialchars($whatsNew['whats_new_title']);
    $whatsNewContent = restoreSafeHTMLTags(htmlspecialchars($whatsNew['whats_new_content']));

    $whatsNewHTML .= "<div class=\"whatsNew\"><h2>What's New in iCoast</h2><p>"
//	. "<p style=\"color: red\">Due to a lapse in appropriations, the majority of USGS websites may not be up to date and may not reflect current conditions. Websites displaying real-time data, such as Earthquake and Water and information needed for public health and safety will be updated with limited support. Additionally, USGS will not be able to respond to inquiries until appropriations are enacted.  For more information, please see <a href=\"https://www.doi.gov/shutdown\">www.doi.gov/shutdown</a>.</p>" .
	;

    if ($whatsNewTitle)
    {
        $whatsNewHTML .= "<span class=\"whatsNewTitle\">$whatsNewTitle -</span> ";
    }
    $whatsNewHTML .= "$whatsNewContent<br></p></div>";
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin all User Statistics


$numberOfUsersQuery = "SELECT COUNT(*) FROM users";
$numberOfUsers = $DBH->query($numberOfUsersQuery)->fetchColumn();
$formattedNumberOfUsers = number_format($numberOfUsers);
if ($numberOfUsers == 1)
{
    $numberOfUsersWelcomeText = 'User';
    $numberOfUsersHomeText = 'user';
} else
{
    $numberOfUsersWelcomeText = 'Users';
    $numberOfUsersHomeText = 'users';
}


$numberOfUsersWithAClassificationQuery = "SELECT COUNT(DISTINCT user_id)  "
                                         . "FROM annotations "
                                         . "WHERE annotation_completed = 1";
$numberOfUsersWithAClassification = $DBH->query($numberOfUsersWithAClassificationQuery)->fetchColumn();
if ($numberOfUsersWithAClassification == 1)
{
    $numberOfUsersWithAClassificationText = 'User has';
} else
{
    $numberOfUsersWithAClassificationText = 'Users have';
}
$formattedNumberOfUsersWithAClassification = number_format($numberOfUsersWithAClassification);
$formattedPercentageOfUsersWithAClassification = round(($numberOfUsersWithAClassification / $numberOfUsers) * 100, 1);


$allICoastAvgClassificationTimeQuery = "
    SELECT AVG(timestampdiff(SECOND, initial_session_start_time, initial_session_end_time)) AS average_time
    FROM annotations
    WHERE annotation_completed = 1
        AND annotation_completed_under_revision = 0
        AND timestampdiff(SECOND, initial_session_start_time, initial_session_end_time) <= 600";
$iCoastAverageTime = $DBH->query($allICoastAvgClassificationTimeQuery)->fetchColumn();
if ($iCoastAverageTime > 0)
{
    $formattedAllICoastClassificationTime = convertSeconds($iCoastAverageTime, false);
    $allICoastAverageTimeText = '
        <p title="Average time is calculated from all classifications completed in 10 minutes or less"><span class="statisticNumber">' .
                                $formattedAllICoastClassificationTime . '
                </span>Is the average time it takes to classify one photo</p>
';
} else
{
    $allICoastAverageTimeText = '';
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Begin project Statistics

$projectInFocusQuery = <<<EOL
        SELECT p.project_id, p.name, p.post_collection_id
        FROM system s
        LEFT JOIN projects p ON s.home_page_project = p.project_id
        WHERE p.is_public = 1
EOL;
$projectInFocus = $DBH->query($projectInFocusQuery)->fetch(PDO::FETCH_ASSOC);
$tagFocusedProjectButtonHTML =
    '<input type="button" class="clickableButton" id="tagFocusedProject" value="Tag Photos From The ' .
    $projectInFocus['name'] .
    ' Project"><br>';
$jQueryDocumentDotReadyCode .= <<<EOL
    $('#tagFocusedProject').click(function() {
        window.location = 'start.php?requestedProjectId={$projectInFocus["project_id"]}';
    });
                    
EOL;

$avgProjectClassificationTimeQuery = "
    SELECT AVG(timestampdiff(second, initial_session_start_time, initial_session_end_time)) as average_time
    FROM annotations
    WHERE annotation_completed = 1
        AND annotation_completed_under_revision = 0
        AND timestampdiff(second, initial_session_start_time, initial_session_end_time) <= 600
        AND project_id = {$projectInFocus['project_id']}";
$averageProjectTime = $DBH->query($avgProjectClassificationTimeQuery)->fetchColumn();
if ($averageProjectTime > 0)
{

    $formattedProjectAverageClassificationTime = convertSeconds($averageProjectTime);
    $projectAverageText = '
        <p>It takes an average of <span class="userData">' .
                          $formattedProjectAverageClassificationTime .
                          '</span> to classify one photo!</p>';
} else
{
    $projectAverageText = '';
}


$numberOfCompleteProjectClassificationsQuery = "SELECT COUNT(*) "
                                               .
                                               "FROM annotations "
                                               .
                                               "WHERE annotation_completed = 1 AND project_id = {$projectInFocus['project_id']}";
$numberOfCompleteProjectClassifications = $DBH->query($numberOfCompleteProjectClassificationsQuery)->fetchColumn();
$formattedNumberOfCompleteProjectClassifications = number_format($numberOfCompleteProjectClassifications);
if ($numberOfCompleteProjectClassifications == 1)
{
    $numberOfCompleteProjectClassificationsText = 'Classification';
} else
{
    $numberOfCompleteProjectClassificationsText = 'Classifications';
}


$numberOfProjectTagsSelectedQuery = "SELECT COUNT(*) "
                                    . "FROM annotation_selections anns "
                                    . "LEFT JOIN tags t ON anns.tag_id = t.tag_id "
                                    . "WHERE t.project_id = {$projectInFocus['project_id']}";
$numberOfProjectTagsSelected = $DBH->query($numberOfProjectTagsSelectedQuery)->fetchColumn();
$formattedNumberOfProjectTagsSelected = number_format($numberOfProjectTagsSelected);
if ($numberOfProjectTagsSelected == 1)
{
    $numberOfProjectTagsSelectedText = 'Tag';
} else
{
    $numberOfProjectTagsSelectedText = 'Tags';
}


$numberOfProjectPhotosQuery = <<<EOL
    SELECT COUNT(*)
    FROM images i
    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
    (
         SELECT DISTINCT post_collection_id
         FROM projects
         WHERE project_id = {$projectInFocus['project_id']}
    )
    AND m.pre_collection_id IN
    (
         SELECT DISTINCT pre_collection_id
         FROM projects
         WHERE project_id = {$projectInFocus['project_id']}
    )
    WHERE i.is_globally_disabled = 0 AND i.collection_id = {$projectInFocus['post_collection_id']}
EOL;
$numberOfProjectPhotos = $DBH->query($numberOfProjectPhotosQuery)->fetchColumn();
$formattedNumberOfProjectPhotos = number_format($numberOfProjectPhotos);


$numberOfClassifiedProjectPhotosQuery = "SELECT COUNT(DISTINCT a.image_id) "
                                        .
                                        "FROM annotations a "
                                        .
                                        "INNER JOIN images i ON a.image_id = i.image_id "
                                        .
                                        "INNER JOIN matches m ON m.post_image_id = a.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN "
                                        .
                                        "("
                                        .
                                        "     SELECT DISTINCT post_collection_id "
                                        .
                                        "     FROM projects "
                                        .
                                        "     WHERE project_id = {$projectInFocus['project_id']}"
                                        .
                                        ") "
                                        .
                                        "AND m.pre_collection_id IN "
                                        .
                                        "("
                                        .
                                        "     SELECT DISTINCT pre_collection_id "
                                        .
                                        "     FROM projects "
                                        .
                                        "     WHERE project_id = {$projectInFocus['project_id']}"
                                        .
                                        ") "
                                        .
                                        "WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$projectInFocus['project_id']}";
$numberOfClassifiedProjectPhotos = $DBH->query($numberOfClassifiedProjectPhotosQuery)->fetchColumn();
$formattedNumberOfClassifiedProjectPhotos = number_format($numberOfClassifiedProjectPhotos);
if ($numberOfClassifiedProjectPhotos == 1)
{
    $numberOfClassifiedProjectPhotosText = 'Photo has';
} else
{
    $numberOfClassifiedProjectPhotosText = 'Photos have';
}


$numberOfUnclassifiedProjectPhotos = $numberOfProjectPhotos - $numberOfClassifiedProjectPhotos;

$formattedNumberOfUnclassifiedProjectPhotos = number_format($numberOfUnclassifiedProjectPhotos);
if ($numberOfUnclassifiedProjectPhotos == 1)
{
    $numberOfUnclassifiedProjectPhotosText = 'Photo has';
} else
{
    $numberOfUnclassifiedProjectPhotosText = 'Photos have';
}
if ($numberOfProjectPhotos > 0)
{
    $formattedPercentageOfProjectPhotosWithAClassification =
        round(($numberOfClassifiedProjectPhotos / $numberOfProjectPhotos) * 100, 1);
} else
{
    $formattedPercentageOfProjectPhotosWithAClassification = '0';
}

if ($numberOfUnclassifiedProjectPhotos > 0)
{
    $loggedOutStatsHTML = <<<EOL
        <p>Help us classify the remaining <span class="userData">$formattedNumberOfUnclassifiedProjectPhotos photos</span> in the {$projectInFocus['name']} Project!</p>
        $projectAverageText
        <p><span class="userData">$formattedNumberOfClassifiedProjectPhotos of $formattedNumberOfProjectPhotos photos</span> have been classified!</p>
        <div class="progressBar">
            <div class="progressBarFill" id="alliCoastProgressBar" style="width: $formattedPercentageOfProjectPhotosWithAClassification%"></div>
            <span class="progressBarText">$formattedPercentageOfProjectPhotosWithAClassification% Of Photos Complete</span>
        </div>
EOL;
} else
{
    $loggedOutStatsHTML = <<<EOL
        <p>Help improve the accuracy of the {$projectInFocus['name']} Project<br>
            by classifying additional photos!</p>
        $projectAverageText
EOL;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Logged in user content

if ($userData)
{
    $userEmail = $userData['masked_email'];
    $userId = $userData['user_id'];
    $userType = filter_input(INPUT_GET, 'userType', FILTER_SANITIZE_STRING);
    $errorHTML = '';
    $focusedProjectMessageHTML = '';

    switch ($userType)
    {
        case 'new':
            $welcomeHTML = '<h1>Welcome to USGS iCoast</h1>';
            $taggingButtonContent = "Start Tagging Photos";
            $tagFocusedProjectButtonHTML = '';
            $personalStatisticsButtonHTML = '';
            $focusedProjectMessageHTML = '<h2>Thanks for joining USGS iCoast</h2>
        <p>Check out the current iCoast project showing aerial photographs taken before and after <a href="start.php?requestedProjectId=' .
                                         $projectInFocus['project_id'] .
                                         '">' .
                                         $projectInFocus['name'] .
                                         '</a>.</p>';
            break;
        case 'existing':
            $annotatedFocusedProjectCheckQuery = "
                SELECT COUNT(*)
                FROM annotations 
                WHERE 
                    project_id = :projectId AND
                    user_id = :userId AND 
                    annotation_completed = 1 
                ";
            $annotatedFocusedProjectCheckParams = array(
                'projectId' => $projectInFocus['project_id'],
                'userId'    => $userId
            );
            $annotatedFocusedProjectCheckResult =
                run_prepared_query($DBH, $annotatedFocusedProjectCheckQuery, $annotatedFocusedProjectCheckParams);

            $lastAnnotatedProjectQuery = "
                SELECT p.project_id, p.name, p.is_public
                FROM annotations a
                JOIN projects p ON a.project_id = p.project_id
                WHERE 
                    user_id = :userId AND
                    annotation_completed = 1
                ORDER BY initial_session_end_time 
                DESC LIMIT 1";
            $lastAnnotatedProjectParams['userId'] = $userId;
            $STH = run_prepared_query($DBH, $lastAnnotatedProjectQuery, $lastAnnotatedProjectParams);
            $lastAnnotatedProject = $STH->fetch();
            if ($lastAnnotatedProject && !$lastAnnotatedProject['is_public'])
            {
                    $groupProjectAccessQuery = <<<MySQL
                		SELECT DISTINCT 
                            ugm.project_id
                        FROM 
                            user_groups ug
                        LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
                        LEFT JOIN projects p ON ugm.project_id = p.project_id
                        WHERE 
                            ug.user_id = $userId AND
                            ugm.is_enabled = 1 AND
                            p.is_complete = 1 
MySQL;
                    $STH =
                        run_prepared_query($DBH,
                                           $groupProjectAccessQuery,
                                           array('userId' => $userId));
                    $groupAccess = false;
                    while ($groupProjectAccessId = $STH->fetchColumn())
                    {
                        if ($groupProjectAccessId == $lastAnnotatedProject['project_id'])
                        {
                            $groupAccess = true;
                        }
                    }
                    if (!$groupAccess)
                    {
                        $lastAnnotatedProject = false;
                    }
            }
            if ($lastAnnotatedProject)
            {
                $taggingButtonContent = "Continue Tagging Photos From The {$lastAnnotatedProject['name']} Project";
            } else
            {
                $taggingButtonContent = "Start Tagging Photos";
                $tagFocusedProjectButtonHTML = '';
            }


            if ($lastAnnotatedProject['project_id'] == $projectInFocus['project_id'])
            {
                $tagFocusedProjectButtonHTML = '';
            }

            $welcomeHTML = '<h1>Welcome Back to USGS iCoast</h1>';
            if ($annotatedFocusedProjectCheckResult->fetchColumn() == 0)
            {
                $focusedProjectMessageHTML =
                    '<p>Check out the current iCoast project showing aerial photographs taken before and after <a href="start.php?requestedProjectId=' .
                    $projectInFocus['project_id'] .
                    '">' .
                    $projectInFocus['name'] .
                    '</a>.</p>';
            }

            $personalStatisticsButtonHTML =
                '<input type="button" id="myiCoastLinkButton" class="clickableButton" value="See Your Personal Statistics In My iCoast">';
            $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#myiCoastLinkButton').click(function() {
                        window.location = 'myicoast.php';
                    });
                    
EOL;
            break;
        default:
            if ($redirectError == "admin")
            {
                $errorHTML = <<<EOL
                    <h2>Permission Error</h2>
                    <p class="error">Your user account has insufficient privileges to access the
                        requested page. If you believe you should have elevated permissions within iCoast then
                        please contact the iCoast System Administrator at
                        <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
                $taggingButtonContent = "Start Tagging Photos";
            } else
            {
                header('Location: index.php?userType=existing');
            }
            break;
    }
    if ($numberOfUnclassifiedProjectPhotos != 0)
    {
        $welcomeMapHTML = <<<EOL
        <h2>Locations of Unclassified Photos in the {$projectInFocus['name']} Project</h2>
        <div id="welcomePageMapWrapper">
        </div>
EOL;

        $unclassifiedPhotoQuery = "
            SELECT i.image_id, i.latitude, i.longitude, m.post_collection_id, m.pre_collection_id, m.pre_image_id
            FROM images i
            INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                 (
                     SELECT DISTINCT post_collection_id
                     FROM projects
                     WHERE project_id = {$projectInFocus['project_id']}
                 )
                 AND m.pre_collection_id IN
                 (
                     SELECT DISTINCT pre_collection_id
                     FROM projects
                     WHERE project_id = {$projectInFocus['project_id']}
                 )
            LEFT JOIN annotations a ON a.image_id = i.image_id  AND project_id = {$projectInFocus['project_id']}
            WHERE i.is_globally_disabled = 0 AND i.collection_id = {$projectInFocus['post_collection_id']}
            GROUP BY i.image_id
            HAVING COUNT(DISTINCT a.annotation_id) = 0 OR (COUNT(DISTINCT a.annotation_id) != 0 AND SUM(a.annotation_completed) = 0)";
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
                        photoId: photo.image_id
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
    } else
    {
        $usersClassifications = array();
        $userProjectClassificationsQuery = <<<EOL
                SELECT image_id
                FROM annotations
                WHERE user_id = $userId AND project_id = {$projectInFocus['project_id']} AND annotation_completed = 1
EOL;
        foreach ($DBH->query($userProjectClassificationsQuery, PDO::FETCH_ASSOC) as $usersClassification)
        {
            $usersClassifications[] = $usersClassification['image_id'];
        }
        $numberOfImagesClassifiedByUser = count($usersClassifications);

        if ($numberOfImagesClassifiedByUser < $numberOfProjectPhotos)
        {

            $classificationCount = 0;
            while (true)
            {
                $classificationCount++;
                $classifiedPhotoQuery = <<<EOL
                SELECT COUNT(DISTINCT a.annotation_id) as annotation_count, i.image_id, i.latitude, i.longitude, m.post_collection_id, m.pre_collection_id, m.pre_image_id
                FROM images i
                INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                     (
                         SELECT DISTINCT post_collection_id
                         FROM projects
                         WHERE project_id = {$projectInFocus['project_id']}
                     )
                     AND m.pre_collection_id IN
                     (
                         SELECT DISTINCT pre_collection_id
                         FROM projects
                         WHERE project_id = {$projectInFocus['project_id']}
                     )
                INNER JOIN annotations a ON a.image_id = i.image_id AND a.annotation_completed = 1 AND a.user_id != $userId AND project_id = {$projectInFocus['project_id']}
                WHERE i.is_globally_disabled = 0 AND i.collection_id = {$projectInFocus['post_collection_id']}
                GROUP BY i.image_id
                HAVING annotation_count = $classificationCount
EOL;
                $classifiedPhotos = array();
                foreach ($DBH->query($classifiedPhotoQuery, PDO::FETCH_ASSOC) as $classifiedImage)
                {
                    if ($numberOfImagesClassifiedByUser == 0 ||
                        !in_array($classifiedImage['image_id'], $usersClassifications)
                    )
                    {
                        $classifiedPhotos[] = $classifiedImage;
                    }
                }

                if (count($classifiedPhotos) > 0)
                {
                    break;
                }
            }

            if ($classificationCount == 1)
            {
                $classificationCountText = 'Classification';
            } else
            {
                $classificationCountText = 'Classifications';
            }

            $welcomeMapHTML = <<<EOL
        <p>Great job iCoast volunteers! You have now classified all the photos in the {$projectInFocus['name']} project!<br>
        But wait, we still need help. Use the map below to find {$projectInFocus['name']} photos you have not yet classified<br>
        and have the fewest number of classifications by other iCoast users.<br>
        Each additional classification you can provide helps to improve the quality of the data for that image.<br>
        Alternatively, use the <span class="italic">Continue Tagging Photos</span> button above to classify a random {$projectInFocus['name']} photo or switch projects.<br>
        <h2>Locations of Photos with $classificationCount $classificationCountText in the {$projectInFocus['name']} Project</h2>
        <div id="welcomePageMapWrapper">
        </div>
EOL;

            $classifiedPhotoJSON = json_encode($classifiedPhotos);

            $jQueryDocumentDotReadyCode .= <<<EOL
            var projectId = {$projectInFocus['project_id']};
            var classifiedPhotos = $classifiedPhotoJSON;
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
            $.each(classifiedPhotos, function(key, photo) {
                var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                marker.on('click', function() {
                    markers.eachLayer(function (layer) {
                        layer.setIcon(redMarker);
                        layer.setZIndexOffset(0);
                    });
                    this.setIcon(greenMarker);
                    this.setZIndexOffset(100000);
                    var imageData = {
                        photoId: photo.image_id
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
        } else
        {
            $welcomeMapHTML = <<<EOL
                    <p>Wow! Excellent work! You have classified all the photos in the {$projectInFocus['name']} project!<br>
                    To keep on going use the <span class="italic">Continue Tagging Photos</span> button above to switch to a different iCoast project.<br>
                    Thanks for all your hard work!</p>
EOL;
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Logged in page template
    $variablePageContent = <<<EOL
        $welcomeHTML
        <p>You are logged in as <span class="userData">$userEmail</span><br>
            If this is not you, <a href="logout.php">logout</a> then login with your Google Account.</p>
        $errorHTML
        $whatsNewHTML
        <h2>Start Tagging</h2>
        $focusedProjectMessageHTML
        $tagFocusedProjectButtonHTML
        <input type="button" class="clickableButton" id="startTaggingButton" value="$taggingButtonContent">
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
                        $allICoastAverageTimeText
                    </div>
                </div>
        $personalStatisticsButtonHTML
            </div>
        </div>
        $welcomeMapHTML
EOL;

    $cssLinkArray[] = 'css/leaflet.css';
    $cssLinkArray[] = 'css/markerCluster.css';

    $javaScriptLinkArray[] = 'scripts/leaflet.js';
    $javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';


    $jQueryDocumentDotReadyCode .= <<<EOL
        $('#startTaggingButton').click(function() {
            window.location = 'start.php';
        });

EOL;
} else
{


    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // User not logged in

    if ($redirectError == 'auth')
    {
        $variableHomeContent = <<<EOL
                <p class="error">Your local authentication credentials have lost synchronization with the
                    server and your login has been reset. Please click the button below to log back in to
                    iCoast using Google. If this problem persists then please contact the iCoast System
                    Administrator at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
    } else
    {
        if ($redirectError == "canceled")
        {
            $variableHomeContent = <<<EOL
                    <p class="error">Login canceled.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
        } else
        {
            if ($redirectError == "invalidEmail")
            {
                $variableHomeContent = <<<EOL
                    <p class="error">A problem was detected with the email address returned by Google.
                        If this problem persists then please contact the iCoast System
                        Administrator at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
            } else
            {
                if ($redirectError == "databaseConnection")
                {
                    $variableHomeContent = <<<EOL
                    <p class="error">Appliaction Failure. Unable to contact database.
                        Please try again in a few minutes and if this problem persists
                        contact the iCoast System Administrator at
                        <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
                } else
                {
                    if ($redirectError == 'disabled')
                    {
                        $variableHomeContent = <<<EOL
                    <p class="error">Sorry, your iCoast account is currently disabled. If you believe this has
                        been done in error then please contact the iCoast System Administrator at
                        <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
EOL;
                    } else
                    {
                        if ($redirectError == "cookies")
                        {
                            $variableHomeContent = <<<EOL
                    <p class="error">You have attempted to access a page that requires you to be logged in.
                        Please login and try the page again. If this error persists then first ensure that your
                        browser is set to accept cookies before contacting the iCoast System Administrator
                        for assistance at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
                        } else
                        {
                            if ($redirectError == "CSRFToken")
                            {
                                $variableHomeContent = <<<EOL
                    <p class="error">Your login attempt failed due to a mismatch of security tokens.
                        Please try to login again. If this error persists then first ensure that your
                        browser is set to accept cookies before contacting the iCoast System Administrator
                        for assistance at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
                            } else
                            {
                                if ($redirectError == "IDTokenExchange")
                                {
                                    $variableHomeContent = <<<EOL
                    <p class="error">Login failed. The system was unable to recover your ID Token from Google.
                        Please attempt to login again. If this problem persists then please contact the iCoast System
                    Administrator at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
                                } else
                                {
                                    if ($redirectError == true)
                                    {
                                        $variableHomeContent = <<<EOL
                    <p class="error">Login failed. An unspecified error occurred ($redirectError).
                        Please attempt to login again. If this problem persists then please contact the iCoast System
                        Administrator at <a href="mailto:icoast.usgs.gov">icoast@usgs.gov</a>.</p>
                    <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                        using Google.</p>
EOL;
                                    } else
                                    {
                                        $variableHomeContent = <<<EOL
                <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
                    using Google.</p>
EOL;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Begin SlideShow Generation //
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
    for ($i = 0; $i < count($slideShowContent); $i++)
    {
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

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Login Button
    $variableHomeContent .= <<<EOL

          <form action="login.php">
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


    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Logged out page template
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
                identify changes to the coast after extreme storms like Hurricanes Ike and Sandy. We need your eyes to
                help us understand how our coastlines are changing from extreme storms.</p>
            $variableHomeContent
            </div>
            $whatsNewHTML
            <div id="homePageStatisticsWrapper">
                $loggedOutStatsHTML
            </div>
        </div>

EOL;

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Javascript for Slideshow functionality
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

$embeddedCSS .= <<<EOL
        
    .whatsNew {
        margin: 20px 50px;
        clear: both;
    }
        
    .whatsNewTitle {
        font-weight: bold;
    }
   
EOL;
