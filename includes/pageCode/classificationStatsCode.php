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
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];

// Look for and setup user specified query paramters

$generalStatsTableContent = '';
$mapHTML = "";
$userStatsTableContent = '';
$tagStatsTableContent = '';
$tagBreakdown = '';
$jsTargetProjectId = '';
$jsPhotoArray = '';
$jsCompleteClassificationsPresent = 'var completeClassificationsPresent = false;';
$jsMapCode = '';
$totalClassifications = 0;

if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
        $projectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
        if ($projectMetadata) {
            $queryProjectWhereClause = "WHERE project_id = :targetProjectId";
            $queryProjectAndClause = 'AND project_id = :targetProjectId';
            $queryProjectWhereClausePrefixed = "WHERE a.project_id = :targetProjectId";
            $queryProjectAndClausePrefixed = 'AND a.project_id = :targetProjectId';
            $queryParams['targetProjectId'] = $targetProjectId;
            $projectTitle = $projectMetadata['name'];
            $generalStatsTitle = "$projectTitle Classification Statistics";
            $tagStatsTitle = "$projectTitle Tag Statistics";
            $statsTarget = "the project";
            $jsTargetProjectId = "var targetProjectId = $targetProjectId;";
        }
    }
}
if (!isset($queryParams)) {
    $queryParams = array();
    unset($targetProjectId);
    $generalStatsTitle = 'All iCoast Classification Statistics';
    $tagStatsTitle = "All iCoast Tag Statistics";
    $statsTarget = 'iCoast';
    $queryProjectWhereClause = '';
    $queryProjectAndClause = '';
    $queryProjectWhereClausePrefixed = '';
    $queryProjectAndClausePrefixed = '';
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the number of available photos in the system/project
$numberOfUsersQuery = "SELECT COUNT(*) FROM users";
$numberOfUsersResult = run_prepared_query($DBH, $numberOfUsersQuery, $queryParams);
$numberOfUsers = $numberOfUsersResult->fetchColumn();
$formattedNumberOfUsers = number_format($numberOfUsers);


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the number of available photos in the system/project
$numberOfUsersWithAClassificationQuery = "SELECT COUNT(DISTINCT user_id)  "
        . "FROM annotations "
        . "WHERE annotation_completed = 1 $queryProjectAndClause";
$numberOfUsersWithAClassificationResult = run_prepared_query($DBH, $numberOfUsersWithAClassificationQuery, $queryParams);
$numberOfUsersWithAClassification = $numberOfUsersWithAClassificationResult->fetchColumn();
$formattedNumberOfUsersWithAClassification = number_format($numberOfUsersWithAClassification);

$percentageOfUsersWithAClassification = round(($numberOfUsersWithAClassification / $numberOfUsers ) * 100, 1);

$numberOfUsersWithoutAClassification = $numberOfUsers - $numberOfUsersWithAClassification;
$formattedNumberOfUsersWithoutAClassification = number_format($numberOfUsersWithoutAClassification);
$percentageOfUsersWithoutAClassification = round(($numberOfUsersWithoutAClassification / $numberOfUsers ) * 100, 1);

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the number of available photos in the system/project
//$numberOfPhotosQuery = "SELECT COUNT(*) "
//        . "FROM images "
//        . "WHERE has_display_file = 1 AND is_globally_disabled = 0 AND dataset_id IN ("
//        . "SELECT dataset_id "
//        . "FROM datasets "
//        . "WHERE collection_id "
//        . "IN ("
//        . "SELECT DISTINCT post_collection_id "
//        . "FROM projects "
//        . "$queryProjectWhereClause";

$numberOfPhotosQuery = "SELECT COUNT(*) "
        . "FROM images i "
        . "INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
        . "("
        . "     SELECT DISTINCT post_collection_id "
        . "     FROM projects "
        . "     $queryProjectWhereClause"
        . ") "
        . "AND m.pre_collection_id IN "
        . "("
        . "     SELECT DISTINCT pre_collection_id "
        . "     FROM projects "
        . "     $queryProjectWhereClause"
        . ") "
        . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN "
        . "("
        . "     SELECT dataset_id "
        . "     FROM datasets "
        . "     WHERE collection_id IN "
        . "     ("
        . "         SELECT DISTINCT post_collection_id "
        . "         FROM projects "
        . "         $queryProjectWhereClause"
        . "     )"
        . ")";

//$numberOfPhotosQuery .= ")"
//        . ")";
$numberOfPhotosResult = run_prepared_query($DBH, $numberOfPhotosQuery, $queryParams);
$numberOfPhotos = $numberOfPhotosResult->fetchColumn();
$formattedNumberOfPhotos = number_format($numberOfPhotos);


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine number of distinct classified photos in system/project
$numberOfClassifiedPhotosQuery = <<<EOL
                    SELECT COUNT(DISTINCT(a.image_id)) AS result_count
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             $queryProjectWhereClause
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             $queryProjectWhereClause
                        )
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 $queryProjectAndClausePrefixed
EOL;

$numberOfClassifiedPhotosResult = run_prepared_query($DBH, $numberOfClassifiedPhotosQuery, $queryParams);
$numberOfClassifiedPhotos = $numberOfClassifiedPhotosResult->fetchColumn();
$formattedNumberOfClassifiedPhotos = number_format($numberOfClassifiedPhotos);

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the percentage of photo with 1 or more classifications
if ($numberOfPhotos > 0) {
    $classifiedPhotoPercentage = round(($numberOfClassifiedPhotos / $numberOfPhotos) * 100, 1);
} else {
    $classifiedPhotoPercentage = 0;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the total number of tags that have been selected in the system/project
if (!empty($queryProjectWhereClause)) {
    $numberOfTagsSelectedQuery = "SELECT COUNT(*) "
            . "FROM annotation_selections anns "
            . "LEFT JOIN tags t ON anns.tag_id = t.tag_id "
            . "WHERE t.project_id = :targetProjectId";
} else {
    $numberOfTagsSelectedQuery = "SELECT COUNT(*) FROM annotation_selections";
} $numberOfTagsSelectedResult = run_prepared_query($DBH, $numberOfTagsSelectedQuery, $queryParams);
$numberOfTags = $numberOfTagsSelectedResult->fetchColumn();
$formattedNumberOfTags = number_format($numberOfTags);

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the total number of complete classifications for the system/project.
$numberOfCompleteClassificationsQuery = <<<EOL
                    SELECT COUNT(*)
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1 AND i.is_globally_disabled = 0
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             $queryProjectWhereClause
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             $queryProjectWhereClause
                        )
                    WHERE a.annotation_completed = 1 $queryProjectAndClausePrefixed
EOL;

$numberOfCompleteClassificationsResult = run_prepared_query($DBH, $numberOfCompleteClassificationsQuery, $queryParams);
$numberOfCompleteClassifications = $numberOfCompleteClassificationsResult->fetchColumn();
$formattedNumberOfCompleteClassifications = number_format($numberOfCompleteClassifications);
$totalClassifications += $numberOfCompleteClassifications;


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the total number of incomplete classifications which have a match for the system/project.
$numberOfIncompleteClassificationsQuery = <<<EOL
                    SELECT COUNT(*)
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1 AND i.is_globally_disabled = 0
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             $queryProjectWhereClause
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             $queryProjectWhereClause
                        )
                    WHERE a.annotation_completed = 0 AND user_match_id IS NOT NULL $queryProjectAndClausePrefixed
EOL;

$numberOfIncompleteClassificationsResult = run_prepared_query($DBH, $numberOfIncompleteClassificationsQuery, $queryParams);
$numberOfIncompleteClassifications = $numberOfIncompleteClassificationsResult->fetchColumn();
$formattedNumberOfIncompleteClassifications = number_format($numberOfIncompleteClassifications);
$totalClassifications += $numberOfIncompleteClassifications;


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the total number of unstarted classifications for the system/project.
$numberOfUnstartedClassificationsQuery = <<<EOL
                    SELECT COUNT(*)
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1 AND i.is_globally_disabled = 0
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             $queryProjectWhereClause
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             $queryProjectWhereClause
                        )
                    WHERE a.annotation_completed = 0 AND user_match_id IS NULL $queryProjectAndClausePrefixed
EOL;
$numberOfUnstartedClassificationsResult = run_prepared_query($DBH, $numberOfUnstartedClassificationsQuery, $queryParams);
$numberOfUnstartedClassifications = $numberOfUnstartedClassificationsResult->fetchColumn();
$formattedNumberOfUnstartedClassifications = number_format($numberOfUnstartedClassifications);
$totalClassifications += $numberOfUnstartedClassifications;
$formattedTotalClassifications = number_format($totalClassifications);


if ($totalClassifications > 0) {
// Determine the percentage of complete classifications from the total classifications for the system/project.
    $completeClassificationPercentage = round(($numberOfCompleteClassifications / $totalClassifications ) * 100, 1);
// Determine the percentage of incomplete classifications from the total classifications for the system/project.
    $incompleteClassificationPercentage = round(($numberOfIncompleteClassifications / $totalClassifications ) * 100, 1);
// Determine the percentage of unstarted classifications from the total classifications for the system/project.
    $unstartedClassificationPercentage = round(($numberOfUnstartedClassifications / $totalClassifications ) * 100, 1);
} else {
// Determine the percentage of complete classifications from the total classifications for the system/project.
    $completeClassificationPercentage = 0;
// Determine the percentage of incomplete classifications from the total classifications for the system/project.
    $incompleteClassificationPercentage = 0;
// Determine the percentage of unstarted classifications from the total classifications for the system/project.
    $unstartedClassificationPercentage = 0;
}

$generalStatsTableContent .= <<<EOL
        <tr title="Number of users who have completely classified at least 1 photo within iCoast as a whole or within the selected project.">
            <td class="userData">$formattedNumberOfUsersWithAClassification</td>
            <td>of $formattedNumberOfUsers total users have completed at least 1 classification in $statsTarget ($percentageOfUsersWithAClassification%)</td>
        </tr>
        <tr title="Number of users who have not classified any photos within iCoast as a whole or within the selected project.">
            <td class="userData">$formattedNumberOfUsersWithoutAClassification</td>
            <td>of $formattedNumberOfUsers total users have not completed any classifications in $statsTarget ($percentageOfUsersWithoutAClassification%)</td>
        </tr>
        <tr title="The total number of tags that have been selected either within iCoast as a whole or within the selected project.">
            <td class="userData">$formattedNumberOfTags</td>
            <td>Tags have been selected in $statsTarget</td>
        </tr>
        <tr title="This is the total number of post-event photos available to the user in either the iCoast system or the selected project">
            <td class="userData">$formattedNumberOfPhotos</td>
            <td>Post-storm photos in  $statsTarget </td>
        </tr>
        <tr title="This is the number of photos either within iCoast or the selected project that have at least 1 complete classification. The percentage is of the total number of photos iCoast or the selected project.">
            <td class="userData">$formattedNumberOfClassifiedPhotos</td>
            <td>Photos have at least 1 complete classification in  $statsTarget ($classifiedPhotoPercentage%)</td>
        </tr>
         <tr title="This is the total number of classifications users were shown either within iCoast as a whole or the selected project. This includes complete, incomplete, and unstarted classifications (broken down below).">
            <td class="userData">$formattedTotalClassifications</td>
            <td>Total classifications in  $statsTarget</td>
        </tr>
        <tr title="This is the number of classifications users have completed either within iCoast as a whole or the selected project. The percentage is of the total classifications in iCoast as a whole or in the selected project.">
            <td class="userData">$formattedNumberOfCompleteClassifications</td>
            <td>Complete classifications in  $statsTarget ($completeClassificationPercentage%)</td>
        </tr>
        <tr title="This is the number of incomplete classifications either within iCoast as a whole or the selected project. An incomplete classification is one in which the user was displayed and image and they selected a pre-event matching image. They may have completed some tasks but did not click the 'Done' button on the final task to indicate completion of the classification. This number excludes unstarted classifications (see below).  The percentage is of the total classifications in iCoast as a whole or in the selected project.">
            <td class="userData">$formattedNumberOfIncompleteClassifications</td>
            <td>Incomplete classification in  $statsTarget ($incompleteClassificationPercentage%)</td>
        </tr>
        <tr title="This is the number of unstarted classifications either within iCoast as a whole or the selected project. An unstarted classification is one in which the user was displayed a post-event image but did not image match it to a pre-event image or complete any of the tasks. The percentage is of the total classifications in iCoast as a whole or in the selected project.">
            <td class="userData">$formattedNumberOfUnstartedClassifications</td>
            <td>Unstarted classifications in  $statsTarget ($unstartedClassificationPercentage%)</td>
        </tr>
EOL;

if ($numberOfCompleteClassifications > 0) {
    $jsCompleteClassificationsPresent = "var completeClassificationsPresent = true;";
    if (!isset($_GET['commentsOnly'])) {
        if ($projectMetadata) {
            $mapTitle = "Locations Of Photos in $projectTitle";
        } else {
            $mapTitle = "Locations Of Photos in iCoast";
        }
//        $classificationMappingQuery = "SELECT COUNT(DISTINCT a.annotation_id) as annotation_count, i.image_id, i.thumb_url, i.latitude, i.longitude, i.city, i.state "
//                . "FROM images i "
//                . "LEFT JOIN annotations a ON a.image_id = i.image_id "
//                . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN ("
//                . " SELECT dataset_id "
//                . " FROM datasets "
//                . " WHERE collection_id "
//                . " IN ("
//                . "     SELECT DISTINCT post_collection_id "
//                . "     FROM projects "
//                . "     $queryProjectWhereClause) "
//                . " ) "
//                . "GROUP BY i.image_id "
//                . "ORDER BY i.state, i.city";
        $classificationMappingQuery = "SELECT COUNT(DISTINCT a.annotation_id) as annotation_count, i.image_id, i.thumb_url, i.latitude, i.longitude, i.city, i.state "
                . "FROM images i "
                . "INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
                . "     ("
                . "         SELECT DISTINCT post_collection_id "
                . "         FROM projects "
                . "         $queryProjectWhereClause"
                . "     ) "
                . "     AND m.pre_collection_id IN "
                . "     ("
                . "         SELECT DISTINCT pre_collection_id "
                . "         FROM projects "
                . "         $queryProjectWhereClause"
                . "     ) "
                . "LEFT JOIN annotations a ON a.image_id = i.image_id "
                . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN "
                . "("
                . "     SELECT dataset_id "
                . "     FROM datasets "
                . "     WHERE collection_id IN "
                . "     ("
                . "         SELECT DISTINCT post_collection_id "
                . "         FROM projects "
                . "         $queryProjectWhereClause"
                . "     )"
                . ")"
                . "GROUP BY i.image_id ";
        $mapLegend = <<<EOL
            <div class="adminMapLegend">
                <div class="adminMapLegendRow">
                  <p>ZOOM IN TO SEE<br>INDIVIDUAL PHOTOS</p>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/clusterLegendIcon.png" alt="Image of the map cluster symbol"
                        width="24" height="24" title="">
                  </div>
                  <div class="adminMapLegendSingleRowText">
                    <p>Clustering of Photos</p>
                  </div>
                </div>
                <div class="adminMapLegendRow">
                    <div class="adminMapLegendRowIcon">
                      <img width="13" height="24" title="" alt="Image of a blue map marker pin" src="http://cdn.leafletjs.com/leaflet-0.7.3/images/marker-icon.png">
                    </div>
                    <div class="adminMapLegendRowText">
                      <p>Photo with 0<br>classifications</p>
                    </div>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/redMarker.png" alt="Image of a red map marker pin"
                        width="13" height="24" title="">
                  </div>
                  <div class="adminMapLegendRowText">
                    <p>Photo with 1 or 2 classifications</p>
                  </div>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                        width="13" height="24" title="">
                  </div>
                  <div class="adminMapLegendRowText">
                    <p>Photo with 3 or more classifications</p>
                  </div>
                </div>
            </div>
EOL;
        $mapCommentButton = '<form method="get" autocomplete="off" action="#classificationLocationMapTitle">'
                . '<input type="submit" class="clickableButton" title="This button will show all annotations for which the user entered a text based comment (filtered by a project if selected)" value="Show Only Classifications With Comments">'
                . '<input type="hidden" name="commentsOnly" value="1">';
        if ($projectMetadata) {
            $mapCommentButton .= '<input type="hidden" name="targetProjectId" value="' . $targetProjectId . '">';
        }
        $mapCommentButton .= '</form>';
    } else {
        if ($projectMetadata) {
            $mapTitle = "Locations Of Completed Classifications with Comments in $projectTitle";
        } else {
            $mapTitle = "Locations Of Completed Classifications with Comments in iCoast";
        }
        $classificationMappingQuery = "SELECT a.image_id, i.thumb_url, i.latitude, i.longitude, i.city, i.state, ac.comment "
                . "FROM annotations a "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "LEFT JOIN annotation_comments ac ON a.annotation_id = ac.annotation_id "
                . "WHERE a.annotation_completed = 1 $queryProjectAndClause AND a.annotation_id IN (SELECT ac.annotation_id FROM annotation_comments ac LEFT JOIN annotations a ON ac.annotation_id = a.annotation_id $queryProjectWhereClause) "
                . "ORDER BY i.state, i.city";

        $mapLegend = <<<EOL
            <div class="adminMapLegend">
                <div class="adminMapLegendRow">
                  <p>ZOOM IN TO SEE<br>INDIVIDUAL PHOTOS</p>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/clusterLegendIcon.png" alt="Image of the map cluster symbol"
                        width="24" height="24" title="">
                  </div>
                  <div class="adminMapLegendSingleRowText">
                    <p>Clustering of Photos</p>
                  </div>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/redMarker.png" alt="Image of a red map marker pin"
                        width="13" height="24" title="">
                  </div>
                  <div class="adminMapLegendRowText">
                    <p>Photo with 1 comment</p>
                  </div>
                </div>
                <div class="adminMapLegendRow">
                  <div class="adminMapLegendRowIcon">
                    <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                        width="13" height="24" title="">
                  </div>
                  <div class="adminMapLegendRowText">
                    <p>Photo with 1 or more comments</p>
                  </div>
                </div>
            </div>
EOL;
        $mapCommentButton = '<form method="get" autocomplete="off" action="#classificationLocationMapTitle">'
                . '<input type="submit" class="clickableButton" title="This button will show all complete annotations on the map (filtered by a project if selected.)." value="Show All Complete Classifications">';
        if ($projectMetadata) {
            $mapCommentButton .= '<input type="hidden" name="targetProjectId" value="' . $targetProjectId . '">';
        }
        $mapCommentButton .= '</form>';
    }
    $classificationMappingResult = run_prepared_query($DBH, $classificationMappingQuery, $queryParams);
    $classificationMappingData = $classificationMappingResult->fetchAll(PDO::FETCH_ASSOC);
//    print '<pre>';
//    print_r($classificationMappingData);
//    print '</pre>';
//    print '<pre>';
//    for ($i=0; $i < 200; $i++) {
//        print_r($classificationMappingData[$i]);
//    }
//    print '</pre>';
    if (isset($_GET['commentsOnly'])) {
        $trackingArray = array();
        for ($i = 0; $i < count($classificationMappingData); $i++) {
            $classificationMappingDataImageKey = array_search($classificationMappingData[$i]['image_id'], $trackingArray);
            if ($classificationMappingDataImageKey !== FALSE) {
                $classificationMappingData[$classificationMappingDataImageKey]['comment'] = '<a href="photoStats.php?targetPhotoId=' . $classificationMappingData[$i]['image_id'] . '">Multiple Comments</a>';
                $classificationMappingData[$classificationMappingDataImageKey]['commentCount'] ++;
                unset($classificationMappingData[$i]);
            } else {
                $trackingArray[$i] = $classificationMappingData[$i]['image_id'];
                $classificationMappingData[$i]['commentCount'] = 1;
            }
        }
    }


    $photoArray = json_encode($classificationMappingData);
    $jsPhotoArray = "var photoArray = $photoArray;";
    $mapHTML = <<<EOL
        <h3 id="classificationLocationMapTitle">$mapTitle</h3>
        <div class="adminMapWrapper">
            <div id="classificationLocationMap" class="adminMap">
            </div>
            $mapLegend
        </div>
        $mapCommentButton
EOL;
    $jsMapCode = <<<EOL
                var map = L.map('classificationLocationMap', {maxZoom: 16});
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
                markers = L.markerClusterGroup({
                    disableClusteringAtZoom: 9,
                    maxClusterRadius: 60
                });
                var hasResults = false;
                $.each(photoArray, function(key, photo) {
                    hasResults = true;
                    var manageButton = '<div style="text-align: center"><form method="get" action="photoEditor.php#imageDetailsHeader" target="_blank">'
                            + '<input type="hidden" name="targetPhotoId" value="' + photo.image_id + '" />'
                            + '<input type="submit" value="Manage Image" class="clickableButton" />'
                            + '</form></div>';
                    if (typeof (photo.commentCount) != "undefined" && photo.commentCount > 1) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: greenMarker});
                    } else if (typeof (photo.commentCount) != "undefined" && photo.commentCount == 1) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                    } else if (photo.annotation_count >= 3) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: greenMarker});
                    } else if (photo.annotation_count >= 1) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                    } else {
                        var marker = L.marker([photo.latitude, photo.longitude]);
                    }
                    if (typeof (photo.comment) != "undefined") {
                        var markerPopup = 'Image ID: <a href="photoStats.php?targetPhotoId=' + photo.image_id + '">' + photo.image_id + '</a><br>'
                        + 'Location: ' + photo.city + ', ' + photo.state + '<br>'
                        + '<span style="width: 250px">Comment: ' + photo.comment + '</span><br>'
                        + '<a href="photoStats.php?targetPhotoId=' + photo.image_id + '"><img class="mapMarkerImage" width="167" height="109" src="' + photo.thumb_url + '" /></a>'
                        + manageButton;
                    } else {
                        var markerPopup = 'Image ID: <a href="photoStats.php?targetPhotoId=' + photo.image_id + '">' + photo.image_id + '</a><br>'
                        + 'Location: ' + photo.city + ', ' + photo.state + '<br>'
                        + 'Number Of Classifications: <a href="photoStats.php?targetPhotoId=' + photo.image_id + '">' + photo.annotation_count + '</a><br>'
                        + '<a href="photoStats.php?targetPhotoId=' + photo.image_id + '"><img class="mapMarkerImage" width="167" height="109" src="' + photo.thumb_url + '" /></a>'
                        + manageButton;
                    }
                    marker.bindPopup(markerPopup, {closeOnClick: true});
                    markers.addLayer(marker);
                });
                if (hasResults) {
                    map.fitBounds(markers.getBounds());
                    markers.addTo(map);
                } else {
                    map.setView([36.9131888,-96.2073795], 4);
                }

EOL;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine the number of photos each user has annotated
if ($totalClassifications > 0) {
    $startingRow = 0;
    $usersInTable = array();
    $userClassificationData = array();
    $userStatsTableContent .= '<h2>Classification Statistics By User</h3>'
            . '<table class="borderedTable cellDividedTable">'
            . '<thead>'
            . '<tr>'
            . '<th>User</th>'
            . '<th>Total Classifications</th>'
            . '<th>Complete Classifications</th>'
            . '<th>Incomplete Classifications</th>'
            . '<th>Unstarted Classifications</th>'
            . '<th>Distinct Photos With Complete Classifications</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';


    $classificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS classification_count, '
            . 'u.user_id, '
            . 'u.encrypted_email, '
            . 'u.encryption_data '
            . 'FROM users u '
            . "LEFT JOIN annotations a ON u.user_id = a.user_id $queryProjectAndClause "
            . 'GROUP BY u.user_id '
            . 'ORDER BY classification_count DESC, u.masked_email ';
    $classificationsPerUserResult = run_prepared_query($DBH, $classificationsPerUserQuery, $queryParams);

    foreach ($classificationsPerUserResult as $userClassifications) {
        if ($userClassifications['classification_count'] > 0) {
            $usersInTable[] = $userClassifications['user_id'];
            $userClassificationData[$userClassifications['user_id']] = array(
                'user_id' => $userClassifications['user_id'],
                'account' => mysql_aes_decrypt($userClassifications['encrypted_email'], $userClassifications['encryption_data']),
                'classification_count' => $userClassifications['classification_count']
            );
        }
    }

    $whereInUsersInTable = where_in_string_builder($usersInTable);

    $incompleteClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS incomplete_count, '
            . 'u.user_id '
            . 'FROM users u '
            . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 0 AND a.user_match_id IS NOT NULL $queryProjectAndClause "
            . "WHERE u.user_id IN ($whereInUsersInTable) "
            . 'GROUP BY u.user_id ';
    $incompleteClassificationsPerUserResult = run_prepared_query($DBH, $incompleteClassificationsPerUserQuery, $queryParams);
    foreach ($incompleteClassificationsPerUserResult as $userIncompleteClassifications) {
        $userClassificationData[$userIncompleteClassifications['user_id']]['incomplete_count'] = $userIncompleteClassifications['incomplete_count'];
    }

    $unstartedClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS unstarted_count, '
            . 'u.user_id '
            . 'FROM users u '
            . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.user_match_id IS NULL $queryProjectAndClause "
            . "WHERE u.user_id IN ($whereInUsersInTable) "
            . 'GROUP BY u.user_id ';
    $unstartedClassificationsPerUserResult = run_prepared_query($DBH, $unstartedClassificationsPerUserQuery, $queryParams);
    foreach ($unstartedClassificationsPerUserResult as $userUnstartedClassifications) {
        $userClassificationData[$userUnstartedClassifications['user_id']]['unstarted_count'] = $userUnstartedClassifications['unstarted_count'];
    }

    $completeClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS complete_count, '
            . 'u.user_id '
            . 'FROM users u '
            . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 1 $queryProjectAndClause "
            . "WHERE u.user_id IN ($whereInUsersInTable) "
            . 'GROUP BY u.user_id ';
    $completeClassificationsPerUserResult = run_prepared_query($DBH, $completeClassificationsPerUserQuery, $queryParams);
    foreach ($completeClassificationsPerUserResult as $userCompleteClassifications) {
        $userClassificationData[$userCompleteClassifications['user_id']]['complete_count'] = $userCompleteClassifications['complete_count'];
    }

    $photosPerUserQuery = 'SELECT COUNT(DISTINCT a.image_id) AS photo_count, '
            . 'u.user_id '
            . 'FROM users u '
            . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 1 $queryProjectAndClause "
            . "WHERE u.user_id IN ($whereInUsersInTable) "
            . 'GROUP BY u.user_id ';
    $photosPerUserResult = run_prepared_query($DBH, $photosPerUserQuery, $queryParams);
    foreach ($photosPerUserResult as $userPhotos) {
        $userClassificationData[$userPhotos['user_id']]['photo_count'] = $userPhotos['photo_count'];
    }


    foreach ($userClassificationData AS $row) {
        $userStatsTableContent .= '<tr>'
                . '<td><a href="userStats.php?targetUserId=' . $row ['user_id'] . '">' . $row['account'] . '</a></td>'
                . '<td class="userData">' . $row['classification_count'] . '</td>'
                . '<td class="userData">' . $row['complete_count'] . '</td>'
                . '<td class="userData">' . $row['incomplete_count'] . '</td>'
                . '<td class="userData">' . $row['unstarted_count'] . '</td>'
                . '<td class="userData">' . $row['photo_count'] . '</td>'
                . '</tr>';
    }
    $userStatsTableContent .= '</tbody>'
            . '</table>'
            . '<input type="button" id="userClassificationStatsDownload" class="clickableButton" title="This button will give you the option to save the User Classification data to a CSV file on your hard drive for further analysis with other tools."value="Download All User Classification Statistics In CSV Format">';

    if ($numberOfTags > 0) {
        $tagBreakdown = '';

        if (!empty($queryProjectWhereClause)) {
            $tagFrequencyInProjectQuery = '(SELECT
t.tag_id AS tag_id,
t.name AS tag_name,
t.description AS tag_description,
COUNT(anns.tag_id) AS frequency,
t.is_enabled AS tag_enabled,
null AS lower_parent_id,
null AS lower_parent_name,
null AS order_in_lower_parent,
null AS lower_parent_enabled,
tgcUpper.order_in_group AS order_in_upper_parent,
tgcUpper.tag_group_id AS upper_parent_id,
tgmUpper.name AS upper_parent_name,
tgmUpper.is_enabled AS upper_parent_enabled,
tc.order_in_task AS upper_group_order_in_task,
tc.task_id,
tm.order_in_project AS task_order_in_project,
tm.is_enabled AS task_enabled,
tm.name AS task_name,
tm.project_id
FROM tags t
LEFT JOIN tag_group_contents tgcUpper ON t.tag_id = tgcUpper.tag_id
LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 0
GROUP BY t.tag_id)

UNION

(SELECT
t.tag_id AS tag_id,
t.name AS tag_name,
t.description AS tag_description,
COUNT(anns.tag_id) AS frequency,
t.is_enabled AS tag_enabled,
tgcLower.tag_group_id AS lower_parent_id,
tgmLower.name AS lower_parent_name,
tgcLower.order_in_group AS order_in_lower_parent,
tgmLower.is_enabled AS lower_parent_enabled,
tgcUpper.order_in_group AS order_in_upper_parent,
tgcUpper.tag_group_id AS upper_parent_id,
tgmUpper.name AS upper_parent_name,
tgmUpper.is_enabled AS upper_parent_enabled,
tc.order_in_task AS upper_group_order_in_task,
tc.task_id,
tm.order_in_project AS task_order_in_project,
tm.is_enabled AS task_enabled,
tm.name AS task_name,
tm.project_id

FROM tags t
LEFT JOIN tag_group_contents tgcLower ON t.tag_id = tgcLower.tag_id
LEFT JOIN tag_group_contents tgcUpper ON tgcLower.tag_group_id = tgcUpper.tag_id
LEFT JOIN tag_group_metadata tgmLower ON tgcLower.tag_group_id = tgmLower.tag_group_id
LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 1
GROUP BY t.tag_id)

ORDER BY task_order_in_project, upper_group_order_in_task, order_in_upper_parent, order_in_lower_parent';
            $tagFrequencyInProjectResult = run_prepared_query($DBH, $tagFrequencyInProjectQuery, $queryParams);
            $tagFrequency = $tagFrequencyInProjectResult->fetchAll(PDO::FETCH_ASSOC);

            $currentTaskId = '';
            $currentUpperParentId = '';
            $currentLowerParentId = '';
            $tagBreakdown .= "<h2>Individual Tag Selection Frequencies</h2>"
                    . "<p>The table below shows the frequency of selection of individual tags with the chosen project.<br>"
                    . "Tags are listed in the order in which they are displayed and grouped by their parent containers "
                    . "using horizontal dividing lines.</p>"
                    . "<p>A red highlight over a task, group, or tag name indicates that the container is disabled.<br>"
                    . "A red highlight in a frequency cell summarises if the tag or a parent container is disabled thus "
                    . "hiding the tag from users.<br>"
                    . "An empty grey cell indicates that a nested group is not used in the organisation of the tag.</p>"
                    . '<table class="borderedTable dividedColumns">'
                    . '<thead>'
                    . '<tr>'
                    . '<th>Task Name</th><th>Group Name</th><th>Nested Group Name</th><th>Tag Name</th><th>Frequency</th>'
                    . '</tr>'
                    . '</thead>'
                    . '<tbody>';
            foreach ($tagFrequency as $tag) {
                $showTopBorder = FALSE;
                if ($currentTaskId != $tag ['task_id']) {
                    if ($tag['task_enabled'] == 0) {
                        $tagBreakdown .= '<tr class="topTaskTableRowBorder"><td class="disabledProperty">';
                    } else {
                        $tagBreakdown .= '<tr class="topTaskTableRowBorder"><td>';
                    }
                    $tagBreakdown .= "{$tag['task_name']}</td>";
                } else {
                    $tagBreakdown .= '<tr><td></td>';
                }
                $currentTaskId = $tag['task_id'];



                if ($currentUpperParentId != $tag['upper_parent_id']) {
                    $showTopBorder = TRUE;
                    if ($tag['upper_parent_enabled'] == 0) {
                        $tagBreakdown .= '<td class="disabledProperty topBorder">';
                    } else {
                        $tagBreakdown .= '<td class="topBorder">';
                    }
                    $tagBreakdown .= "{$tag['upper_parent_name']}</td>";
                } else {
                    if ($showTopBorder) {
                        $tagBreakdown .= '<td class="topBorder"></td>';
                    } else {
                        $tagBreakdown .= '<td></td>';
                    }
                }
                $currentUpperParentId = $tag['upper_parent_id'];



                if (empty($tag['lower_parent_id'])) {
                    if ($showTopBorder) {
                        $tagBreakdown .= '<td class="unusedTableCell topBorder"></td>';
                    } else {
                        $tagBreakdown .= '<td class="unusedTableCell"></td>';
                    }
                } else {
                    if ($currentLowerParentId != $tag['lower_parent_id']) {
                        $showTopBorder = TRUE;
                        if ($tag['lower_parent_enabled'] == 0) {
                            $tagBreakdown .= '<td class="disabledProperty topBorder">';
                        } else {
                            $tagBreakdown .= '<td class="topBorder">';
                        }
                        $tagBreakdown .= "{$tag['lower_parent_name']}</td>";
                    } else {
                        if ($showTopBorder) {
                            $tagBreakdown .= '<td class="topBorder"></td>';
                        } else {
                            $tagBreakdown .= '<td></td>';
                        }
                    }
                }
                $currentLowerParentId = $tag ['lower_parent_id'];



                if ($tag['tag_enabled'] == 0) {
                    $tagBreakdown .= '<td class="disabledProperty topBorder" title="' . $tag['tag_description'] . '">';
                } else {
                    $tagBreakdown .= '<td class="topBorder" title="' . $tag['tag_description'] . '">';
                }
                $tagBreakdown .= "{$tag ['tag_name'] }</td>";

                if ($tag['task_enabled'] == 0 ||
                        $tag['upper_parent_enabled'] == 0 ||
                        (!empty($tag['lower_parent_enabled']) && $tag['lower_parent_enabled'] == 0) ||
                        $tag['tag_enabled'] == 0) {
                    $tagBreakdown .= '<td class="topBorder disabledProperty">';
                } else {
                    $tagBreakdown .= '<td class="topBorder">';
                }
                $tagBreakdown .= number_format($tag['frequency']) . '</td>';

                $tagBreakdown .= '</tr>';
            }
            $tagBreakdown .= '</table>'
                    . '<input type="button" id="tagSelectionFrequencyDownload" class="clickableButton" title="This button will give you the option to save the Tag Selection Frequency data to a CSV file on your hard drive for further analysis with other tools." value="Download All Tag Selection Frequency Statistics In CSV Format">';
        }
    }
}





// DETERMINE THE LIST OF AVAILABLE/PERMISSIONED PROJECTS
$userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
$projectCount = count($userAdministeredProjects);
// BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
// PROJECT SELECT
$projectSelectHTML = "<option title=\"All Projects in the iCoast system.\" value=\"0\">All iCoast Projects</option>";
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $optionProjectId = $singeUserAdministeredProject['project_id'];
    $optionProjectName = $singeUserAdministeredProject['name'];
    $optionProjectDescription = $singeUserAdministeredProject['description'];
    $projectSelectHTML .= "<option title=\"$optionProjectDescription\" value=\"$optionProjectId\"";
    if ($optionProjectId == $targetProjectId) {
        $projectSelectHTML .= ' selected ';
    }
    $projectSelectHTML .= ">$optionProjectName</option>";
}

$cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';

$javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';

$javaScript .= <<<EOL
    $jsTargetProjectId
    $jsPhotoArray
    $jsCompleteClassificationsPresent
EOL;

$jQueryDocumentDotReadyCode .= <<<EOL

                if (typeof (targetProjectId) !== 'undefined' && completeClassificationsPresent) {
                    $('#allClassificationDownload').removeClass('disabledClickableButton').removeAttr('disabled');
                }
                $('#allClassificationDownload').click(function() {
                    window.location = "ajax/csvGenerator.php?dataSource=allClassifications&targetProjectId=" + targetProjectId;
                });
                $('#userClassificationStatsDownload').click(function() {
                    if (typeof (targetProjectId) !== 'undefined') {
                        window.location = "ajax/csvGenerator.php?dataSource=classificationOverviewByUser&targetProjectId=" + targetProjectId;
                    } else {
                        window.location = "ajax/csvGenerator.php?dataSource=classificationOverviewByUser";
                    }

                });
                $('#tagSelectionFrequencyDownload').click(function() {
                    window.location = "ajax/csvGenerator.php?dataSource=tagSelectionFrequencies&targetProjectId=" + targetProjectId;
                });

                $jsMapCode;


EOL;
