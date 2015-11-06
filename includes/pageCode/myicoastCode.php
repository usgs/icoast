<?php

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);

// SET DEFAULT VARIABLES
$queryParams = array();
$queryProjectAndClause = '';
$classificationTimeGraphHTML = '';
$statisticTargetText = 'in iCoast';
$statisticTargetTitle = 'iCoast';
$lastProjectNameHTML = '';
$projectSelectControlHTML = '';
$userAnnotationStatisticsHTML = '';
$userPositionHTML = '';
$advancementHTML = '';
$projectMetadata = false;
$celebrationMessage = '';
$formattedAverageClassificationTime = '';
$formattedLongestClassificationTime  = '';
$formattedShortestClassificationTime  = '';
$projectId = 0;
$jsHistoryPanelAction = '';




//CHECK SUPPLIED PARAMETERS. SET REQUIRED VARIABLES
if (isset($_GET['projectId'])) {
    settype($_GET['projectId'], 'integer');
    if (!empty($_GET['projectId'])) {
        $projectMetadata = retrieve_entity_metadata($DBH, $_GET['projectId'], 'project');
    }
}
if ($projectMetadata) {
    $queryProjectAndClause .= "AND a.project_id = {$projectMetadata['project_id']}";
    $queryParams['projectId'] = $projectMetadata['project_id'];
    $statisticTargetText = "in the {$projectMetadata['name']} project";
    $statisticTargetTitle = $projectMetadata['name'];
    $projectId = $projectMetadata['project_id'];
}

// CREATE AND PREPARE OUTPUT FOR USER ACCOUNT STATISTICS
$formattedLastLoggedInTime = formattedTime($userData['last_logged_in_on'], $userData['time_zone']);
$formattedAccoutCreatedOnTime = formattedTime($userData['account_created_on'], $userData['time_zone']);
// FIND THE LAST PROJECT THE USER WORKED ON
$lastAnnotationQuery = "SELECT a.project_id, p.name "
        . "FROM annotations a "
        . "LEFT JOIN projects p ON a.project_id = p.project_id "
        . "WHERE a.user_id = {$userData['user_id']} AND a.user_match_id IS NOT NULL "
        . "ORDER BY a.initial_session_end_time DESC "
        . "LIMIT 1";
$lastAnnotation = $DBH->query($lastAnnotationQuery)->fetch(PDO::FETCH_ASSOC);
// IF THE USER HAS STARTED AT LEAST 1 CLASSIFICATION THEN PROCEED TO BUILD AND DISPLAY RELEVANT STATISTICS AND OPTIONS
if ($lastAnnotation) {

    $lastProjectNameHTML = <<<EOL
    <p>The last project in which you worked on a classification was the <span class="userData">{$lastAnnotation['name']}</span> project.</p>
EOL;

    // BUILD PROJECT SELECTION SELECT BOX OPTIONS
    $classifiedProjectsQuery = "SELECT DISTINCT(a.project_id) AS project_id, p.name "
            . "FROM annotations a "
            . "LEFT JOIN projects p ON a.project_id = p.project_id "
            . "WHERE a.user_id = {$userData['user_id']} AND a.user_match_id IS NOT NULL "
            . "ORDER BY a.initial_session_start_time DESC";
    $classifiedProjects = $DBH->query($classifiedProjectsQuery)->fetchAll(PDO::FETCH_ASSOC);

    $projectSelectControlHTML = <<<EOL
        <p>Choose from the options below to view either your tagging statistics and history across<br>
            all iCoast projects or for just a particular project.</p>
        <select id="projectSelection" class="formInputStyle" autocomplete="off"
            title="This select box lists all of the projects in which you have tagged photos. Use it
                in conjunction with the Specific Project History button found to the right to
                display photos you have tagged for a specific project.">
EOL;
    $projectSelectControlHTML .= "<option value=\"0\">All iCoast Projects</option>\n\r";
    foreach ($classifiedProjects as $singleProject) {
        $projectSelectControlHTML .= "<option value=\"{$singleProject['project_id']}\"";
        if ($projectMetadata && ($singleProject['project_id'] == $projectMetadata['project_id'])) {
            $projectSelectControlHTML .= ' selected';
        }
        $projectSelectControlHTML .= ">{$singleProject['name']}</option>\n\r";
    }

    $projectSelectControlHTML .= "</select>\n\r";

    // FIND COUNT OF ALL COMPLETED CLASSIFICATIONS
    $completeClassificationCountQuery = "SELECT COUNT(*)"
            . "FROM annotations a "
            . "WHERE user_id = {$userData['user_id']} AND annotation_completed = 1 $queryProjectAndClause";
    $completeClassificationCount = $DBH->query($completeClassificationCountQuery)->fetchColumn();
    $formattedCompleteClassificationCount = number_format($completeClassificationCount);
    if ($completeClassificationCount == 1) {
        $completeClassificationCountText = 'is';
        $tagCompleteClassifcationText = "in your single complete classification";
    } else {
        $completeClassificationCountText = 'are';
        $tagCompleteClassifcationText = "in total across all $formattedCompleteClassificationCount complete classifications";
    }

    // FIND COUNT OF ALL INCOMPLETE (BUT STARTED) CLASSIFICATIONS
    $incompleteClassificationCountQuery = "SELECT COUNT(*)"
            . "FROM annotations a "
            . "WHERE user_id = {$userData['user_id']} AND annotation_completed = 0 AND user_match_id IS NOT NULL $queryProjectAndClause";
    $incompleteClassificationCount = $DBH->query($incompleteClassificationCountQuery)->fetchColumn();
    $formattedIncompleteClassificationCount = number_format($incompleteClassificationCount);
    if ($incompleteClassificationCount == 1) {
        $incompleteClassificationCountText = 'was';
    } else {
        $incompleteClassificationCountText = 'were';
    }

    // CALCULATE TOTAL STARTED CLASSIFICATIONS
    $allStartedClassificationCount = $completeClassificationCount + $incompleteClassificationCount;
    $formattedAllStartedClassificationCount = number_format($allStartedClassificationCount);
    if ($allStartedClassificationCount == 1) {
        $allStartedClassificationCountText = 'classification has';
    } else {
        $allStartedClassificationCountText = 'classifications have';
    }

    if ($allStartedClassificationCount > 0) {
        $completeClassificationPercentage = number_format(($completeClassificationCount / $allStartedClassificationCount) * 100, 1);
        $incompleteClassificationPercentage = number_format(($incompleteClassificationCount / $allStartedClassificationCount) * 100, 1);
    } else {
        $completeClassificationPercentage = '0.0';
        $incompleteClassificationPercentage = '0.0';
    }

    // DETERMINE THE TOTAL NUMBER OF TAGS THE USER HAS SELECTED
    $numberOfTagsSelectedQuery = "SELECT COUNT(*) "
            . "FROM annotation_selections "
            . "WHERE annotation_id IN ("
            . "SELECT annotation_id "
            . "FROM annotations a "
            . "WHERE user_id = {$userData['user_id']} AND annotation_completed = 1 $queryProjectAndClause"
            . ")";
    $numberOfTags = $DBH->query($numberOfTagsSelectedQuery)->fetchColumn();
    $formattedNumberOfTags = number_format($numberOfTags);
    if ($numberOfTags == 1) {
        $numberOfTagsText = 'Tag has';
    } else {
        $numberOfTagsText = 'Tags have';
    }

    // DETERMINE THE COMPLETED CLASSIFICATION COUNT OF ALL OTHER USERS WITH HIGHER COUNTS
    $leaderboardPositionsQuery = "SELECT COUNT(annotation_id) as completed_annotation_count "
            . "FROM annotations a "
            . "WHERE annotation_completed = 1 $queryProjectAndClause "
            . "GROUP BY user_id "
            . "HAVING completed_annotation_count > $completeClassificationCount "
            . "ORDER BY completed_annotation_count DESC";
    $leaderboardPositions = $DBH->query($leaderboardPositionsQuery)->fetchAll(PDO::FETCH_ASSOC);
    $positionInICoast = count($leaderboardPositions) + 1;
    $ordinalPositionInICoast = ordinal_suffix($positionInICoast);

    $jointQuery = "SELECT COUNT(annotation_id) as completed_annotation_count "
            . "FROM annotations a "
            . "WHERE annotation_completed = 1 $queryProjectAndClause "
            . "GROUP BY user_id "
            . "HAVING completed_annotation_count = $completeClassificationCount";
    $jointQueryResult = $DBH->query($jointQuery)->fetchAll(PDO::FETCH_ASSOC);
    if (count($jointQueryResult) > 1) {
        $jointPosition = TRUE;
    } else {
        $jointPosition = FALSE;
    }

    if ($positionInICoast == 1) {
        if ($jointPosition) {
            $userPositionHTML = "joint 1st place";
            $celebrationMessage = '';
        } else {
            $userPositionHTML = "1st place";
            $celebrationMessage = "<p id=\"celebrationMessage\"><span class=\"userData\">Top $statisticTargetTitle Tagger!</span></p>";
        }
    } else if ($positionInICoast > 1) {
        if ($jointPosition) {
            $userPositionHTML = "Joint ";
        }
        $userPositionHTML .= "$ordinalPositionInICoast Place";
    }

    $annotationsToFirst = $leaderboardPositions[0]['completed_annotation_count'] - $completeClassificationCount + 1;
    if ($annotationsToFirst == 1) {
        $annotationsToFirstText = 'Classification is';
    } else {
        $annotationsToFirstText = 'Classifications are';
    }
    $annotationsToNext = $leaderboardPositions[$positionInICoast - 2]['completed_annotation_count'] - $completeClassificationCount;
    if ($annotationsToNext == 1) {
        $annotationsToNextText = 'Classification is';
    } else {
        $annotationsToNextText = 'Classifications are';
    }

    if ($positionInICoast > 1) {
        $advancementHTML .= '<p title = "The number of photos you need to tag to become the Top Tagger.">'
                . '<span class="statisticNumber">' . $annotationsToFirst . '</span> '
                . $annotationsToFirstText . ' are needed to become 1st ' . $statisticTargetText . '.</p>';
    }

    if ($positionInICoast > 2) {
        $advancementHTML .= '<p title ="The number of photos you need to tag to climb up the leaderboard by '
                . 'one position"><span class="statisticNumber">' . $annotationsToNext
                . '</span> ' . $annotationsToNextText . ' are needed to move up a leaderboard position ' . $statisticTargetText . '.</p>';
    }

    // OUTPUT THE USER STATISTICS
    $userAnnotationStatisticsHTML .= <<<EOL
    <h3>Leaderboard Position</h3>
    <div>
        <div class="cssStatistics">
            <p title="This is your position on the leaderboard out of all registered users in iCoast/the project. The more photos you tag the higher you will climb. Try to become the Top Tagger!">
                You are in <span class="userData">$userPositionHTML</span> out of all users who have completed classifications
                $statisticTargetText.</p>
            $advancementHTML
        </div>
        $celebrationMessage
    </div>
    <h3>Classification Status</h3>
    <div>
        <div class="cssStatistics">
            <p title="This is the number of classifications in either iCoast or the chosen project that you have worked on.">
                <span class="statisticNumber">$formattedAllStartedClassificationCount</span> Classifications have been worked on $statisticTargetText.</p>
            <p title="This is the number of classifications that you worked on and finished by completing all tasks and clicking the 'Done' button on the final task.">
                <span class="statisticNumber">$formattedCompleteClassificationCount</span> of those classifications $completeClassificationCountText complete.</p>
            <p title="This is the number of classifications that you started work on but never finished. The map and chart below can help you find these incomplete classifications so you can finish them off.">
                <span class="statisticNumber">$formattedIncompleteClassificationCount</span> $incompleteClassificationCountText started but not finished.</p>
            <p title=""><span class="statisticNumber">$formattedNumberOfTags</span> $numberOfTagsText been selected $tagCompleteClassifcationText.</p>
        </div>
    </div>
    <h3>Time Statistics</h3>
EOL;




    $durationFrequencyCount = array();
    $upperTimeLimitMins = 10;
    $nonDisplayableClassificationCount = 0;
    $calcTimeTotal = 0;
    $avgCount = 0;
    $longestClassification = 0;
    $shortestClassification = 0;
    $nonDisplayableCountHTML = '';
    $xScaleHTML = '';
    $classificationTimeGraphContentHTML = '';
    $yRangeX1000 = FALSE;
    $yScaleHTML = '';
    $yScaleTitleSuffix = '';
    $yScaleTitleStyling = '';

    for ($i = 1; $i <= $upperTimeLimitMins; $i++) {
        $durationFrequencyCount[$i] = 0;
    }

    $avgTimeQuery = "SELECT TIMESTAMPDIFF(SECOND, initial_session_start_time, initial_session_end_time) as time "
            . "FROM annotations a "
            . "WHERE annotation_completed = 1 AND annotation_completed_under_revision = 0 AND user_id = {$userData['user_id']} $queryProjectAndClause";

    foreach ($DBH->query($avgTimeQuery) as $avgTimeRow) {
        if ($avgTimeRow['time'] < 600) {
            $calcTimeTotal += $avgTimeRow['time'];
            $avgCount++;
            if (ceil($avgTimeRow['time'] / 60) <= $upperTimeLimitMins) {
                $durationFrequencyCount[ceil($avgTimeRow['time'] / 60)] ++;
            }
            if ($avgTimeRow['time'] > $longestClassification) {
                $longestClassification = $avgTimeRow['time'];
            }
            if ($avgTimeRow['time'] < $shortestClassification || $shortestClassification == 0) {
                $shortestClassification = $avgTimeRow['time'];
            }
        } else {
            $nonDisplayableClassificationCount++;
        }
    }

    if ($avgCount > 0) {
        $averageTime = $calcTimeTotal / $avgCount;

        $formattedTimeTotal = convertSeconds($calcTimeTotal);
        $formattedAverageClassificationTime .= convertSeconds($averageTime);
        $formattedLongestClassificationTime .= convertSeconds($longestClassification);
        $formattedShortestClassificationTime .= convertSeconds($shortestClassification);
        $formattedNonDisplayableClassificationCount = number_format($nonDisplayableClassificationCount, FALSE);
        if ($nonDisplayableClassificationCount == 1) {
            $nonDisplayableClassificationCountText = 'classification';
        } else {
            $nonDisplayableClassificationCountText = 'classifications';
        }

        $pixelsPerBarWrapperWidth = floor(880 / $upperTimeLimitMins);
        $pixelsPerBarWidth = $pixelsPerBarWrapperWidth - 2;

        $maxAnnotationTimeFrequency = max($durationFrequencyCount);
        if ($maxAnnotationTimeFrequency <= 5) {
            $maxY = 5;
        } else if ($maxAnnotationTimeFrequency <= 10) {
            $maxY = 10;
        } else if ($maxAnnotationTimeFrequency <= 15) {
            $maxY = 15;
        } else if ($maxAnnotationTimeFrequency <= 25) {
            $maxY = 25;
        } else if ($maxAnnotationTimeFrequency <= 50) {
            $maxY = 50;
        } else if ($maxAnnotationTimeFrequency <= 100) {
            $maxY = 100;
        } else if ($maxAnnotationTimeFrequency <= 250) {
            $maxY = 250;
        } else if ($maxAnnotationTimeFrequency <= 500) {
            $maxY = 500;
        } else if ($maxAnnotationTimeFrequency <= 1000) {
            $maxY = 1000;
        } else if ($maxAnnotationTimeFrequency <= 5000) {
            $maxY = 5000;
        } else if ($maxAnnotationTimeFrequency <= 10000) {
            $maxY = 10000;
        } else if ($maxAnnotationTimeFrequency <= 25000) {
            $maxY = 25000;
        } else if ($maxAnnotationTimeFrequency <= 50000) {
            $maxY = 50000;
        } else if ($maxAnnotationTimeFrequency <= 250000) {
            $maxY = 250000;
        } else {
            $maxY = 1000000;
        }
        $rangeOfYScaleMarks = $maxY / 5;
        $pixelsPerFrequencyCount = 250 / $maxY;


        for ($i = 1; $i <= $upperTimeLimitMins; $i++) {
            $iMinus1 = $i - 1;
            if ($durationFrequencyCount[$i] > 0) {
                $barHeightInPx = floor($durationFrequencyCount[$i] * $pixelsPerFrequencyCount);

                $classificationTimeGraphContentHTML .= ''
                        . '<div class="timeGraphBarWrapper" '
                        . 'style="width: ' . $pixelsPerBarWrapperWidth . 'px" '
                        . 'title="' . $durationFrequencyCount[$i] . ' classification(s) between ' . $iMinus1 . ' and ' . $i . ' minute(s)">'
                        . ' <div class="timeGraphBar" '
                        . '     style="width: ' . $pixelsPerBarWidth . 'px; height: ' . $barHeightInPx . 'px">'
                        . ' </div>'
                        . '</div>';
            } else {
                $classificationTimeGraphContentHTML .= ''
                        . '<div class="timeGraphBarWrapper" '
                        . 'style="width: ' . $pixelsPerBarWrapperWidth . 'px"'
                        . 'title="' . $durationFrequencyCount[$i] . ' classification(s) between ' . $iMinus1 . ' and ' . $i . ' minute(s)">'
                        . '</div>';
            }

            $xScaleHTML .= ''
                    . '<div class="xScaleDivision" '
                    . 'style="width: ' . $pixelsPerBarWrapperWidth . 'px">'
                    . ' <div class="xScaleMark"></div>'
                    . ' <div class="xScaleNumberWrapper">'
                    . $i
                    . ' </div>'
                    . '</div>';
        }

        for ($i = 1; $i <= 5; $i++) {
            $yScaleValue = $i * $rangeOfYScaleMarks;
            if ($i == 1 && $yScaleValue > 200) {
                $yRangeX1000 = TRUE;
                $yScaleTitleSuffix = '(x 1000)';
                $yScaleTitleStyling = 'top: 270px';
            }
            if ($yRangeX1000) {
                $yScaleValue = $yScaleValue / 1000;
            }
            $yScaleDivisionPosition = (($i - 1) * 50) + 1;
            $yScaleHTML .= ''
                    . '<div class="yScaleDivision" '
                    . ' style="bottom:' . $yScaleDivisionPosition . 'px">'
                    . '     <div class="yScaleNumberWrapper">'
                    . "         $yScaleValue"
                    . '     </div>'
                    . '     <div class="yScaleMark"></div>'
                    . '</div>';
        }

        $userAnnotationStatisticsHTML .= <<<EOL
                    <div>
                        <div class="cssStatistics">
                            <p class="timeStatistic" title=""><span class="statisticNumber">$formattedTimeTotal</span> is how long you spent classifying photos $statisticTargetText.</p>
                            <p class="timeStatistic" title=""><span class="statisticNumber">$formattedLongestClassificationTime</span> is the longest you spent on a classification $statisticTargetText.</p>
                            <p class="timeStatistic" title=""><span class="statisticNumber">$formattedShortestClassificationTime</span> is the shortest you spent on a classification $statisticTargetText.</p>
                            <p class="timeStatistic" title=""><span class="statisticNumber">$formattedAverageClassificationTime</span> is the average time it took you to complete a classification.</p>
                        </div>
                    </div>
                    <div id = "classificationTimeGraphWrapper">
                        <div id = "ctgUpperWrapper">
                            <div id = "ctgYBorder">
                                <div id = "ctgYTitleWrapper">
                                    <div id = "ctgYTitle" style = "$yScaleTitleStyling">
                                        Number of Classifications $yScaleTitleSuffix
                                    </div>
                                </div>
                                <div id = "ctgYScaleWrapper">
                                    $yScaleHTML
                                </div>
                            </div>
                            <div id = "ctgContent">
                                $classificationTimeGraphContentHTML
                            </div>
                        </div>
                        <div id = "ctgLowerWrapper">
                            <div id = "ctgDeadSpace">
                                <div>0</div>
                                <div>0</div>
                            </div>
                            <div id = "ctgXBorder">
                                <div id = "ctgXScaleWrapper">
                                    $xScaleHTML
                                </div>
                                <div id = "ctgXTitleWrapper">
                                    Time In Minutes
                                </div>
                            </div>
                        </div>
                    </div>
                    <p>Only complete classifications that were started and finished in less than $upperTimeLimitMins
                        minutes and during a single session are shown in the Time chart and used in the calculation
                        of the total and average classification time.<br>
                        <span class="userData">$formattedNonDisplayableClassificationCount</span> $nonDisplayableClassificationCountText exceeded $upperTimeLimitMins minutes.</p>
EOL;
    } else if ($avgCount == 0 && $nonDisplayableClassificationCount > 0) {
        $userAnnotationStatisticsHTML .= '<p>All your recorded photo classifications exceeded the average time calculation cut-off of 10 minutes. No time statistics have been generated.';
    }


    $jsHistoryPanelAction = <<<EOL
        var startingPosition = L.latLng(27.764463, -82.638284);

        map = L.map("userClassificationLocationMap", {maxZoom: 16}).setView(startingPosition, 16);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
        }).addTo(map);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
        L.control.scale({
            position: 'topright',
            metric: false
        }).addTo(map);
        markers = L.markerClusterGroup({
            disableClusteringAtZoom: 9,
            maxClusterRadius: 60
        });
        completeMarkers = L.layerGroup();
        incompleteMarkers = L.layerGroup();
        incomplete = L.icon({
            iconUrl: 'images/system/redMarker.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [0, -35]
        });
        complete = L.icon({
            iconUrl: 'images/system/greenMarker.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [0, -35]
        });

        runAjaxAnnotationQuery();
EOL;
} else {
    $userAnnotationStatisticsHTML = <<<EOL
        <p>You have not yet started tagging any photographs in iCoast.<br>
            Click the <span class="italic">Start Tagging Photos</span> button below to begin,<br>
            <br>See if you can tag more photos than other iCoast users.</p>
        <form method="post" action="start.php" class="buttonForm">
          <input type="submit" id="continueClassifyingButton" class="clickableButton formButton"
              value="Start Tagging Photos"
              title="Click to begin the classification process and start tagging."/>
        </form>
EOL;
    $jsHistoryPanelAction = "$('#userAnnotationHistory').css('display', 'none');";
}

$javaScript = <<<EOT
    var userId = {$userData['user_id']};
    var timeZone = {$userData['time_zone']};
    var searchedProject = $projectId;
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
    var map;
    var markers;
    var completeMarkers;
    var incompleteMarkers;
    var complete;
    var incomplete;



    function runAjaxAnnotationQuery() {
        if (startingRow < 0) {
            startingRow = 0;
        }
        ajaxData = {
            userId: userId,
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

        markers.clearLayers();
        completeMarkers.clearLayers();
        incompleteMarkers.clearLayers();

        var firstMarker = true;

        $.each(history, function(index, annotation) {
            var annotationPoint = L.latLng(annotation.latitude, annotation.longitude);

            if (firstMarker) {
                resultSetMapBounds = L.latLngBounds(annotationPoint, annotationPoint);
            } else {
                resultSetMapBounds.extend(annotationPoint);
            }
            firstMarker = false;

            if (annotation.annotation_completed == 1) {
                var marker = L.marker([annotation.latitude, annotation.longitude], {icon: complete});
                completeMarkers.addLayer(marker);
                var classificationStatusHTML = 'Complete';
            } else {
                var marker = L.marker([annotation.latitude, annotation.longitude], {icon: incomplete});
                incompleteMarkers.addLayer(marker);
                var classificationStatusHTML = '<span class="redHighlight">Incomplete</span>';
            }

            var infoString = 'Image ID: <a href="classification.php?projectId='
                + annotation.project_id + '&imageId=' + annotation.image_id + '" title="Click the link to load the classification">' + annotation.image_id + '</a><br>'
                + 'Location: ' + annotation.location + '<br>'
                + 'Classification Time: ' + annotation.annotation_time + '<br>'
                + 'Classification Status: ' + classificationStatusHTML + '<br>'
                + 'Classified through the ' + annotation.project_name + ' project<br>'
                + '<a href="classification.php?projectId=' + annotation.project_id + '&imageId=' + annotation.image_id
                + '"><img class="mapMarkerImage" width="167" height="109" src="' + annotation.thumb_url + '" title="Click the image to load the classification" /></a>';

            var markerPopup = L.popup({offset: L.point(0,-40), autoPan :false})
                .setContent(infoString).setLatLng([annotation.latitude, annotation.longitude]);


            marker.on('mouseover', function() {
                map.openPopup(markerPopup);
            });

            marker.on('click', function() {
                $('#historyTableWrapper tr').css('background-color', '#FFFFFF');
                $('#historyTableWrapper tr').css('font-weight', 'normal');
                $('#historyTableWrapper td').each(function() {
                    if ($(this).text() == annotation.image_id) {
                        $(this).parent().css('background-color', '#D3E2F0');
                        $(this).parent().css('font-weight', 'bold');
                    }
                });

                completeMarkers.eachLayer(function(layer) {
                    layer.setIcon(complete);
                });
                incompleteMarkers.eachLayer(function(layer) {
                    layer.setIcon(incomplete);
                });
                thisMarker.setIcon();
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
        $('#historyTableWrapper tbody tr').remove();
        $('#historyTableWrapper tbody').append(tableContents);

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
            $('#userAnnotationHistory').slideDown(positionFeedbackDiv);
        }
        markers.addLayer(completeMarkers);
        markers.addLayer(incompleteMarkers);
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



EOT;

$jQueryDocumentDotReadyCode = <<<EOT

    $('td:first-of-type').tipTip();

    $jsHistoryPanelAction

    $('#resultSizeSelect').prop('selectedIndex', 0);

    $('#resultSizeSelect').on('change', function() {
        rowsPerPage = $(this).val();
        runAjaxAnnotationQuery();
    });

    $('#projectSelection').on('change', function() {
        searchedProject = parseInt($('#projectSelection').val(), 10);
        if (searchedProject >= 0) {
            window.location.href = "myicoast.php?projectId=" + searchedProject + "#statsAndHistory";
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

EOT;





























$cssLinkArray[] = 'css/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';
$embeddedCSS = '#historyControlWrapper p:first-of-type {margin-top: 0px; padding-top: 10px;}';
$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
