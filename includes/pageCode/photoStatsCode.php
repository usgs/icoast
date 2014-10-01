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

$photoSelectClearFormHTML = '';
$jsPhotoLocationLatitude = '';
$jsPhotoLocationLongitude = '';
$photoDetailsHTML = '';
$photoMatchDetailsHTML = '';
$jsphotoLocationMapCode = '';
$projectListHTML = '';
$projectQueryParams = array();
$photoQueryParams = array();
$allQueryParams = array();
$queryProjectWhereClause = '';
$queryProjectAndClause = '';
$photoMatchDetailsHTML = '';
$jsPhotoMatchesArray = '';
$jsPhotoMatchesMapCode = '';
$jsTargetPhotoId = '';
$jsTargetProjectId = '';
$matchesMapWrapperHTML = '';
$projectSelectHTML = '';





// DETERMINE THE LIST OF AVAILABLE/PERMISSIONED PROJECTS
$userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
$projectCount = count($userAdministeredProjects);
// BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
// PROJECT SELECT


if (isset($_GET['targetPhotoId'])) {
    settype($_GET['targetPhotoId'], 'integer');
    if (!empty($_GET['targetPhotoId'])) {
        $targetPhotoId = $_GET['targetPhotoId'];
        $photoMetadata = retrieve_entity_metadata($DBH, $targetPhotoId, 'image');
        if ($photoMetadata) {
            $jsTargetPhotoId = "var targetPhotoId = $targetPhotoId;";
            $jsPhotoMetadata = json_encode($photoMetadata);
            $photoQueryParams['targetPhotoId'] = $targetPhotoId;
            $allQueryParams['targetPhotoId'] = $targetPhotoId;
        } else {
            unset($targetPhotoId);
        }
    }
}

if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
        $projectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
        if ($projectMetadata) {
            $queryProjectWhereClause = "WHERE project_id = :targetProjectId";
            $queryProjectAndClause = "AND project_id = :targetProjectId";
            $projectQueryParams['targetProjectId'] = $targetProjectId;
            $allQueryParams['targetProjectId'] = $targetProjectId;
            $projectTitle = $projectMetadata['name'];
            $jsTargetProjectId = "var targetProjectId = $targetProjectId;";
        } else {
            unset($targetProjectId);
        }
    }
}


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


if ($photoMetadata) {

    // Set variable HTML content
    $photoSelectHTML = <<<EOL
            <h3 id="photoSelectHeader">Change the Specified Photo</h3>
            <p>To see details for a different photo enter the desired photo ID below.</p>
EOL;
    $photoSelectTextValue = ' value="' . $targetPhotoId . '"';
    $photoSelectClearFormHTML = <<<EOL
                <form method="get" autocomplete="off" id="userClearForm" action="#photoSelectHeader">
                    <input type="submit" id="userIdClear" class="clickableButton" value="Clear Selected Photo">
                </form>
EOL;

    // Determine all image and parent container details
    $detailedImageURL = $photoMetadata['full_url'];
    $datasetMetadata = retrieve_entity_metadata($DBH, $photoMetadata['dataset_id'], 'dataset');
    $collectionMetadata = retrieve_entity_metadata($DBH, $datasetMetadata['collection_id'], 'collection');
    $imageParentProjectQuery = "SELECT project_id, name, description, post_collection_id, pre_collection_id, is_public "
            . "FROM projects "
            . "WHERE post_collection_id = {$datasetMetadata['collection_id']} OR pre_collection_id = {$datasetMetadata['collection_id']} "
            . "ORDER BY project_id";

    // Format image and parent details for display
    // Build list of projects using the specified image for incusion in table cell.
    foreach ($DBH->query($imageParentProjectQuery) as $parentProject) {
        if ($parentProject['is_public'] == 1) {
            $projectListHTML .= '<span title="' . $parentProject['description'] . '"><a href="classificationStats.php?targetProjectId=' . $parentProject['project_id'] . '">' . $parentProject['project_id'] . ' - ' . $parentProject['name'] . '</a>';
        } else {
            $projectListHTML .= '<span title="' . $parentProject['description'] . ' (Disabled)"><a href="classificationStats.php?targetProjectId=' . $parentProject['project_id'] . '">' . $parentProject['project_id'] . ' - ' . $parentProject['name'] . '</a> <span class="redHighlight">Disabled</span>';
        }
        if ($parentProject['post_collection_id'] == $datasetMetadata['collection_id']) {
            $projectListHTML .= ' (Post-Event Photo)';
        } else {
            $projectListHTML .= ' (Pre-Event Photo)';
        }
        $projectListHTML .= '</span><br>';
    }
    $projectListHTML = rtrim($projectListHTML, '<br>');

    // Format postion coordinates as N/S vs. +/-
    if ($photoMetadata['latitude'] >= 0) {
        $latitude = $photoMetadata['latitude'] . ' N';
    } else {
        $latitude = abs($photoMetadata['latitude']) . ' S';
    }
    if ($photoMetadata['longitude'] >= 0) {
        $longitude = $photoMetadata['longitude'] . ' E';
    } else {
        $longitude = abs($photoMetadata['longitude']) . ' W';
    }

    $imageLocation = build_image_location_string($photoMetadata);
    $imageDate = utc_to_timezone($photoMetadata['image_time'], 'd M Y', $photoMetadata['longitude']);
    $imageTime = utc_to_timezone($photoMetadata['image_time'], 'H:i:s T', $photoMetadata['longitude']);

    // Highlight in red if image is disabled.
    if ($photoMetadata['is_globally_disabled'] == 0) {
        $imageStatus = "Enabled";
    } else {
        $imageStatus = '<span class="redHighlight">Disabled</span>';
    }
    // Highlight in red if dataset is disabled.
    if ($datasetMetadata['is_enabled'] == 1) {
        $parentDatasetHTML = '<td class="userData" title="' . $datasetMetadata['description'] . '">' . $photoMetadata['dataset_id'] . ' - ' . $datasetMetadata['name'] . '</td>';
    } else {
        $parentDatasetHTML = '<td class="userData redHighlight" title="' . $datasetMetadata['description'] . ' (Disabled)">' . $photoMetadata['dataset_id'] . ' - ' . $datasetMetadata['name'] . '</td>';
    }

    // Highlight in red if collection is disabled.
    if ($collectionMetadata['is_globally_enabled'] == 1) {
        $parentCollectionHTML = '<td class="userData" title="' . $collectionMetadata['description'] . '">' . $collectionMetadata['collection_id'] . ' - ' . $collectionMetadata['name'] . '</td>';
    } else {
        $parentCollectionHTML = '<td class="userData redHighlight" title="' . $collectionMetadata['description'] . ' (Disabled)">' . $collectionMetadata['collection_id'] . ' - ' . $collectionMetadata['name'] . '</td>';
    }

    //Build HTML Output for Image Details
    $photoDetailsHTML = <<<EOL
            <h2>Details and Statistics For Image $targetPhotoId</h2>
            <div id="photoStatsImageWrapper">
                <img id="imageZoomLoadingIndicator" class="zoomLoadingIndicator" src="images/system/loading.gif" title="Loading high resolution zoom tool..." alt="An animated spinner to indicate a higher resolution image is loading." />
                <img id="photoStatsImage" src="images/datasets/{$photoMetadata['dataset_id']}/main/{$photoMetadata['filename']}" width="800" height="521" data-zoom-image="$detailedImageURL">
            </div>
            <div id="photoStatsMapDetailsWrapper">
                <div id="photoStatsMapWrapper">
                </div>
                <div id="photoStatsDetailsWrapper">
                    <h3>Photo Details</h3>
                    <table class="adminStatisticsTable">
                    <tr>
                        <td>Position:</td>
                        <td class="userData">$latitude, $longitude</td>
                    </tr>
                    <tr>
                        <td>Taken Near:</td>
                        <td class="userData">$imageLocation</td>
                    </tr>
                    <tr>
                        <td>Captured On:</td>
                        <td class="userData">$imageDate, $imageTime</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td class="userData">$imageStatus</td>
                    </tr>
                    <tr>
                        <td>Parent Dataset:</td>
                        $parentDatasetHTML
                    </tr>
                    <tr>
                        <td>Parent Collection:</td>
                        $parentCollectionHTML
                    </tr>
                    <tr>
                        <td>Used in Project(s)</td>
                        <td class="userData">$projectListHTML</td>
                    </tr>
                     <tr>
                        <td></td>
                        <td class="userData"></td>
                    </tr>
                    </table>
                </div>
            </div>
            <h3>Photo Statistics</h3>
            <h4 id="projectSelectHeader">Select a Project (Optional)</h4>
            <p>For project specific statistics select a project using the following list.</p>
            <form method="get" autocomplete="off" action="#projectSelectHeader">
                <label for="analyticsProjectSelection">Project: </label>
                <select id="analyticsProjectSelection" class="formInputStyle" name="targetProjectId">
                    <option title=\"All Projects in the iCoast system.\" value=\"0\">All iCoast Projects</option>
                    $projectSelectHTML
                </select>
                <input type="hidden" name="targetPhotoId" value="$targetPhotoId">
                <input type="submit" class="clickableButton" value="Select Project">
            </form>
EOL;

    // Build necessary javascript to display small image location map.
    $jsPhotoLocationLatitude = "var latitude = {$photoMetadata['latitude']};";
    $jsPhotoLocationLongitude = "var longitude = {$photoMetadata['longitude']};";
    $jsphotoLocationMapCode = <<<EOL
        var photoLocationMap = L.map('photoStatsMapWrapper', {zoom: 14, maxZoom: 16, center: [latitude, longitude]});
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
        }).addTo(photoLocationMap);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(photoLocationMap);
        L.control.scale({
            position: 'topright',
            metric: false
        }).addTo(photoLocationMap);
        var photoLocation = L.marker([latitude, longitude]).addTo(photoLocationMap);

EOL;

    $annotationWhereInString = array();
    $timeTotal = 0;
    $annotationCount = 0;
    $longestAnnotation = 0;
    $shortestAnnotation = 0;
    $usersAndComments = '';
    $annotationCountQuery = "SELECT a.annotation_id, u.user_id, u.encrypted_email, u.encryption_data, a.initial_session_start_time, a.initial_session_end_time, annotation_completed_under_revision "
            . "FROM annotations a "
            . "LEFT JOIN users u ON a.user_id = u.user_id "
            . "WHERE a.image_id = $targetPhotoId AND a.annotation_completed = 1 $queryProjectAndClause";
    $photoAnnotationsResult = run_prepared_query($DBH, $annotationCountQuery, $projectQueryParams);
    $photoAnnotations = $photoAnnotationsResult->fetchAll(PDO::FETCH_ASSOC);
    $formattedAnnotationCount = number_format(count($photoAnnotations));
    foreach ($photoAnnotations as &$photoAnnotation) {
        $photoAnnotation['unencrypted_email'] = mysql_aes_decrypt($photoAnnotation['encrypted_email'], $photoAnnotation['encryption_data']);

        if ($photoAnnotation['annotation_completed_under_revision'] == 0) {
            $startTime = strtotime($photoAnnotation['initial_session_start_time']);
            $endTime = strtotime($photoAnnotation['initial_session_end_time']);
            $timeDelta = $endTime - $startTime;
            if ($timeDelta < (3600)) {
                $timeTotal += $timeDelta;
                $annotationCount++;
                if (ceil($timeDelta / 60) <= $maxX) {
                    $durationFrequencyCount[ceil($timeDelta / 60)] ++;
                }
                if ($timeDelta > $longestAnnotation) {
                    $longestAnnotation = $timeDelta;
                }
                if ($timeDelta < $shortestAnnotation || $shortestAnnotation == 0) {
                    $shortestAnnotation = $timeDelta;
                }
            } else {
                $excessiveTimeCount++;
            }
        }

        $annotationWhereInArray[] = $photoAnnotation['annotation_id'];
    }
    if (count($photoAnnotations) > 0) {
        $annotationWhereInString = where_in_string_builder($annotationWhereInArray);

        $annotationCommentsQuery = "SELECT annotation_id, comment "
                . "FROM annotation_comments "
                . "WHERE annotation_id IN ($annotationWhereInString)";
        $annotationCommentsParams = array();
        $annotationCommentsResults = run_prepared_query($DBH, $annotationCommentsQuery, $annotationCommentsParams);
        $annotationComments = $annotationCommentsResults->fetchAll(PDO::FETCH_ASSOC);
        foreach ($annotationComments as $annotationComment) {
            foreach ($photoAnnotations as &$photoAnnotation) {
                if ($photoAnnotation['annotation_id'] == $annotationComment['annotation_id']) {
                    $photoAnnotation['comment'] = $annotationComment['comment'];
                }
            }
        }

        if ($projectMetadata) {
            $numberOfTagsSelectedForPhotoQuery = "SELECT COUNT(*) "
                    . "FROM annotation_selections anns "
                    . "LEFT JOIN tags t ON anns.tag_id = t.tag_id "
                    . "LEFT JOIN annotations a ON anns.annotation_id = a.annotation_id "
                    . "WHERE t.project_id = :targetProjectId AND a.image_id = :targetPhotoId";
            $numberOfTagsSelectedForPhotoResults = run_prepared_query($DBH, $numberOfTagsSelectedForPhotoQuery, $allQueryParams);
            if ($numberOfTagsSelectedForPhotoResults->fetchColumn() > 0) {

                $photoTagFrequency = array();

                $tagSelectionFrequencyQuery = "SELECT tag_id, COUNT(annotation_id) as frequency "
                        . "FROM annotation_selections "
                        . "WHERE annotation_id IN ($annotationWhereInString) "
                        . "GROUP BY tag_id";
                $tagSelectionFrequencyParams = array();
                $tagSelectionFrequencyResults = run_prepared_query($DBH, $tagSelectionFrequencyQuery, $tagSelectionFrequencyParams);
                while ($individualTagSelectionFrequency = $tagSelectionFrequencyResults->fetch(PDO::FETCH_ASSOC)) {
                    $photoTagFrequency[$individualTagSelectionFrequency['tag_id']] = $individualTagSelectionFrequency['frequency'];
                }


                $tagFrequency = array();
                $tagBreakdown = '';

                $tagOrderInProjectQuery = '(SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
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
                    LEFT JOIN annotations a ON anns.annotation_id = a.annotation_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 0
                    GROUP BY t.tag_id)

                    UNION

                    (SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
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
                    LEFT JOIN annotations a ON anns.annotation_id = a.annotation_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 1
                    GROUP BY t.tag_id)

                    ORDER BY task_order_in_project, upper_group_order_in_task, order_in_upper_parent, order_in_lower_parent';
                $tagOrderInProjectResult = run_prepared_query($DBH, $tagOrderInProjectQuery, $projectQueryParams);
                while ($individualTag = $tagOrderInProjectResult->fetch(PDO::FETCH_ASSOC)) {
                    if (array_key_exists($individualTag['tag_id'], $photoTagFrequency)) {
                        $individualTag['frequency'] = $photoTagFrequency[$individualTag['tag_id']];
                    } else {
                        $individualTag['frequency'] = 0;
                    }
                    $tagFrequency[] = $individualTag;
                }

                $currentTaskId = '';
                $currentUpperParentId = '';
                $currentLowerParentId = '';
                $tagBreakdown .= "<p>The table below shows the frequency of selection of individual tags for the specfied photo and "
                        . "the chosen project.<br>"
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
            } else {
                $tagBreakdown .= "<p>No tags have been selected for this photo in the {$projectMetadata['name']} project.</p>";
            }
        } else {
            $tagBreakdown .= "<p>You must select a project to see tag selected frequency counts.</p>";
        }


        foreach ($photoAnnotations as $individualAnnotation) {
            if (!isset($individualAnnotation['comment'])) {
                $individualAnnotation['comment'] = 'No Comment';
            }
            $usersAndComments .= <<<EOL
            <tr>
                <td><a href="userStats.php?targetUserId={$individualAnnotation['user_id']}">{$individualAnnotation['unencrypted_email']}</a></td>
                <td class="userData">{$individualAnnotation['comment']}</td>
            </tr>
EOL;
        }

//    print '<pre>';
//    print_r($photoAnnotations);
//    print '</pre>';
        if ($annotationCount > 0) {
            $averageTime = $timeTotal / $annotationCount;

            $formattedTimeTotal = convertSeconds($timeTotal);
//        print $formattedTimeTotal;
            $formattedAverageAnnotationTime .= convertSeconds($averageTime);
            $formattedLongestAnnotationTime .= convertSeconds($longestAnnotation);
            $formattedShortestAnnotationTime .= convertSeconds($shortestAnnotation);
            $formattedExcessiveTimeCount = number_format($excessiveTimeCount);
        }

        $photoDetailsHTML.= <<<EOL
        <table class="adminStatisticsTable">
        <tr>
            <td>Number of Complete Annotations:</td>
            <td class="userData">$formattedAnnotationCount</td>
        </tr>
        <tr title="The total time spent by all users annotating this photo. The number excludes any annotation whose total annotation time exceeded 60 minutes and any annotations that were completed under revision.">
            <td>Total Annotation Time:</td>
            <td class="userData">$formattedTimeTotal</td>
        </tr>
        <tr title="The average annotation time of the photo across all users. The number excludes any annotation whose total annotation time exceeded 60 minutes and any annotations that were completed under revision.">
            <td>Average Annotation Time:</td>
            <td class="userData">$formattedAverageAnnotationTime</td>
        </tr>
        <tr title="The longest time it took a user to annotate this photo.  The number excludes any annotation whose total annotation time exceeded 60 minutes and any annotations that were completed under revision.">
            <td>Longest Annotation Time:</td>
            <td class="userData">$formattedLongestAnnotationTime</td>
        </tr>
        <tr title="The shortest time it took a user to annotate this photo.  The number excludes any annotation whose total annotation time exceeded 60 minutes and any annotations that were completed under revision.">
            <td>Shortest Annotation Time:</td>
            <td class="userData">$formattedShortestAnnotationTime</td>
        </tr>
        <tr title="The number of annotations of this photo that took longer than 60 minutes to complete in a single session. Times for annotations completed in two or more sessions are not included in this number.">
            <td>Excessively Long Annotation Count (> 60 mins):</td>
            <td class="userData">$formattedExcessiveTimeCount</td>
        </tr>
        </table>
        <h3>Individual Tag Selection Frequencies</h3>
        $tagBreakdown
        <h3>Annotating Users and Comments (if supplied)</h3>
        <table class="adminStatisticsTable" id="userAndCommentTable">
            <thead>
                <th>User</th>
                <th>Comment</th>
            </thead>
            <tbody>
                $usersAndComments
            </tbody>
        </table>


EOL;



        if ($projectMetadata) {
            $photoMatchDetailsHTML = '<h2>Details and Statistics For Photo Matches</h2>';
            $computerMatchMetadata = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $targetPhotoId);
            if ($computerMatchMetadata) {
                $computerMatchPhotoMetadata = retrieve_entity_metadata($DBH, $computerMatchMetadata['pre_image_id'], 'image');

                $photoMatchesArray[$computerMatchPhotoMetadata['image_id']] = array(
                    'computer_match' => '1',
                    'latitude' => $computerMatchPhotoMetadata['latitude'],
                    'longitude' => $computerMatchPhotoMetadata['longitude'],
                    'thumb_url' => $computerMatchPhotoMetadata['thumb_url'],
                    'match_count' => 0
                );
                $jsComputerMatchPhotoMetadata = json_encode($computerMatchPhotoMetadata);

                $photoMatchesQuery = "SELECT a.user_match_id, "
                        . "i.latitude, "
                        . "i.longitude, "
                        . "i.thumb_url "
                        . "FROM annotations a "
                        . "LEFT JOIN images i ON i.image_id = a.user_match_id "
                        . "WHERE a.image_id = $targetPhotoId AND a.project_id = $targetProjectId";

                $totalPhotoMatches = 0;
                $userMatchIdList = '';
                foreach ($DBH->query($photoMatchesQuery, PDO::FETCH_ASSOC) as $userMatch) {
                    $totalPhotoMatches ++;
                    if (!array_key_exists($userMatch['user_match_id'], $photoMatchesArray)) {
                        $photoMatchesArray[$userMatch['user_match_id']] = array(
                            'computer_match' => '0',
                            'latitude' => $userMatch['latitude'],
                            'longitude' => $userMatch['longitude'],
                            'thumb_url' => $userMatch['thumb_url'],
                            'match_count' => 1
                        );
                    } else {
                        $photoMatchesArray[$userMatch['user_match_id']]['match_count'] ++;
                    }
                }

                foreach ($photoMatchesArray as $photoKey => $photoMatch) {
                    if ($photoKey != $computerMatchPhotoMetadata['image_id']) {
                        $userMatchIdListHTML .= '<a href="photoStats?targetPhotoId=' . $photoKey . '">' . $photoKey . '</a> was selected ' . $photoMatch['match_count'] . ' time(s).<br>';
                    } else {
                        $computerMatchIdHTML .= '<a href="photoStats?targetPhotoId=' . $photoKey . '">' . $photoKey . '</a> was selected ' . $photoMatch['match_count'] . ' time(s).';
                    }
                }



                $photoMatchDetailsHTML .= <<<EOL
        <table class="adminStatisticsTable">
            <tr>
                <td>Computer Selected Photo Match ID:</td>
                <td class="userData">$computerMatchIdHTML</td>
            </tr>
            <tr>
                <td>User Selected Photo Match ID(s):</td>
                <td class="userData">$userMatchIdListHTML</td>
            </tr>
        </table>
EOL;

                $jsPhotoMatchesArray = json_encode($photoMatchesArray);
                $totalPhotoDistinctMatches = count($photoMatchesArray);

                $jsPhotoMatchesMapCode = <<<EOL
            var photoMetadata = $jsPhotoMetadata;
            var photoMatchesArray = $jsPhotoMatchesArray;
            var totalPhotoMatches = $totalPhotoMatches;
            var totalPhotoDistinctMatches = $totalPhotoDistinctMatches;

            var photoMatchesMap = L.map('photoMatchesMapWrapper');
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
            }).addTo(photoMatchesMap);
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(photoMatchesMap);
            L.control.scale({
            position: 'topright',
                    metric: false
            }).addTo(photoMatchesMap);

            var nonMatchingIcon = L.icon({
            iconUrl: 'images/system/redMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41]
            });
            var userPhoto = L.icon({
            iconUrl: 'images/system/greenMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41]
            });
            var computerPhoto = L.icon({
            iconUrl: 'images/system/yellowMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41]
            });

            var allMarkers = L.featureGroup();
            var allLines = L.featureGroup();
            var computerLatLng = null;

            var photoLatLng = L.latLng(photoMetadata.latitude, photoMetadata.longitude);
            var photoMarker = L.marker(photoLatLng);
            photoMarker.bindPopup('<h2>Image ' + photoMetadata.image_id + '</h2><p>This is the currently selected post-storm image.</p><p><span class="userData">' + totalPhotoMatches + '</span> matches to <span class="userData">' + totalPhotoDistinctMatches + '</span> distinct pre-storm photos have been made for this image.</p><img class="mapMarkerImage" width="167" height="109" src="' + photoMetadata.thumb_url + '">', {offset: [0, -35]});
            allMarkers.addLayer(photoMarker);

            $.each(photoMatchesArray, function(key, photo) {

                if (photo.computer_match == 1) {
                    computerLatLng = L.latLng(photo.latitude, photo.longitude);
                    var computerMarker = L.marker(computerLatLng, {icon: computerPhoto});
                    var distanceFromPostStormPhoto = Math.floor(computerLatLng.distanceTo(photoLatLng) * 3.3);
                    computerMarker.bindPopup('<h2>Computer Match</h2><p>Image <a href="photoStats.php?targetPhotoId=' + key + '">' + key + '</a></p><p>This is the photo chosen by the computer as the best possible pre-storm match based on proximity (<span class="userData">' + distanceFromPostStormPhoto + ' ft.</span>) to the selected post-storm photo.</p><p><span class="userData">' + photo.match_count + ' of ' + totalPhotoMatches + '</span> user match selections from complete annotations chose this as the best match for the post-storm photo.</p><div class="photoMatchMapComparisonWrapper"><div class="photoMatchMapComparisonPostPhotoWrapper"><p>Post-Storm Photo (' + photoMetadata.image_id + ')</p><img class="mapMarkerImage" width="167" height="109" src="' + photoMetadata.thumb_url + '"></div><div class="photoMatchMapComparisonPrePhotoWrapper"><p>Computer\'s Pre-Storm Match (<a href="photoStats.php?targetPhotoId=' + key + '">' + key + '</a>)</p><a href="photoStats.php?targetPhotoId=' + key + '"><img class="mapMarkerImage" width="167" height="109" src="' + photo.thumb_url + '"></a></div></div>', {offset: [0, -35], maxWidth: 344});
                    allMarkers.addLayer(computerMarker);
                    var computerLine = L.polyline([photoLatLng, computerLatLng], {color: 'yellow'}).addTo(allLines);
                } else {
                    var userLatLng = L.latLng(photo.latitude, photo.longitude);
                    var marker = L.marker(userLatLng, {icon: userPhoto});
                    var distanceFromComputerMatch = Math.floor(userLatLng.distanceTo(computerLatLng) * 3.3);
                    var distanceFromPostStormPhoto = Math.floor(userLatLng.distanceTo(photoLatLng) * 3.3);
                    marker.bindPopup('<h2>User Match</h2><p>Image <a href="photoStats.php?targetPhotoId=' + key + '">' + key + '</a></p><p>This photo was selected in <span class="userData">' + photo.match_count + ' out of ' + totalPhotoMatches + '</span> complete user annotations as the best match for the post-storm photo based on human visual matching of landmarks.</p><p>This image is <span class="userData">' + distanceFromComputerMatch + ' ft.</span> from the computers best match and <span class="userData">' + distanceFromPostStormPhoto + ' ft.</span> from the post-storm photo.</p><div class="photoMatchMapComparisonWrapper"><div class="photoMatchMapComparisonPostPhotoWrapper"><p>Post-Storm Photo (' + photoMetadata.image_id + ')</p><img class="mapMarkerImage" width="167" height="109" src="' + photoMetadata.thumb_url + '"></div><div class="photoMatchMapComparisonPrePhotoWrapper"><p>User\'s Pre-Storm Match (<a href="photoStats.php?targetPhotoId=' + key + '">' + key + '</a>)</p><a href="photoStats.php?targetPhotoId=' + key + '"><img class="mapMarkerImage" width="167" height="109" src="' + photo.thumb_url + '"></a></div></div>', {offset: [0, -35], maxWidth: 344});
                    allMarkers.addLayer(marker);
                    var userLine = L.polyline([photoLatLng, userLatLng], {color: 'green'}).addTo(allLines);
                }



            });

            photoMatchesMap.fitBounds(allMarkers.getBounds());
            allMarkers.addTo(photoMatchesMap);
            allLines.addTo(photoMatchesMap);

EOL;

                $photoMatchesMapWrapperHTML = <<<EOL
            <div class="adminMapWrapper">
                <div id="photoMatchesMapWrapper" class="adminMap">
                </div>
                <div class="adminMapLegend">
                    <div class="adminMapLegendRow">
                      <p>CLICK THE MAP PINS FOR<br>DETAILED MATCH INFO</p>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="http://cdn.leafletjs.com/leaflet-0.7.3/images/marker-icon.png" alt="Image of a blue map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Post Storm Photo</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/yellowMarker.png" alt="Image of a yellow map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Computer Selected Match</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>User Selected Match</p>
                      </div>
                    </div>
                </div>
            </div>
EOL;
            } else {
                $photoMatchDetailsHTML .= "<p>This photo has no matches in the specified project</p>";
            }
        } // END IF ($projectMetadata)
    } else { // IF (count($photoAnnotations) > 0) ELSE
        $photoDetailsHTML.= '<p>No annotations with details to display</p>';
    }  // END IF (count($photoAnnotations) > 0) ELSE
} else { // IF ($photoMetadata) ELSE
    $photoSelectHTML = <<<EOL
            <h2 id="photoSelectHeader">Choose a Photo to View</h2>
            <p>To see details for a specific photo enter  photo ID below.</p>
EOL;



    $photoMatchDetailsHTML = <<<EOL
            <h2>All Annotated Photos and Matches for the $projectTitle Project</h2>
            <h3 id="projectAllMatchesSelectHeader">Select A Different Project to View All Annotated Photos and Matches</h3>
            <p>To change the project in focus for the map of all annotated post-storm photos and their related matches select a different project from the following list.</p>
            <form method="get" autocomplete="off" action="#projectSelectHeader">
                <label for="analyticsAllMatchesProjectSelection">Project: </label>
                <select id="analyticsAllMatchesProjectSelection" class="formInputStyle" name="targetProjectId">
                    $projectSelectHTML
                </select>
                <input type="submit" class="clickableButton" value="Select Project">
            </form>
            <form method="get" autocomplete="off" id="userClearForm" action="#projectAllMatchesSelectHeader">
                <input type="submit" id="projectClear" class="clickableButton" value="Clear Selected Project">
            </form>
EOL;

    if ($projectMetadata) {
        $matchesQuery = "SELECT "
                . "a.image_id AS photo_image_id,  "
                . "i.latitude AS photo_latitude, "
                . "i.longitude AS photo_longitude, "
                . "i.thumb_url AS photo_thumb, "
                . "m.pre_image_id AS computer_image_id, "
                . "(SELECT latitude FROM images WHERE image_id = computer_image_id) AS computer_latitude, "
                . "(SELECT longitude FROM images WHERE image_id = computer_image_id) AS computer_longitude, "
                . "(SELECT thumb_url FROM images WHERE image_id = computer_image_id) AS computer_thumb, "
                . "a.user_match_id AS user_image_id, "
                . "IF (a.user_match_id != m.pre_image_id, (SELECT latitude FROM images WHERE image_id = user_image_id), NULL) AS user_latitude, "
                . "IF (a.user_match_id != m.pre_image_id, (SELECT longitude FROM images WHERE image_id = user_image_id), NULL) AS user_longitude, "
                . "IF (a.user_match_id != m.pre_image_id, (SELECT thumb_url FROM images WHERE image_id = user_image_id), NULL) AS user_thumb "
                . "FROM annotations a "
                . "LEFT JOIN matches m ON a.image_id = m.post_image_id "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "WHERE a.annotation_completed = 1 AND a.project_id = :targetProjectId";


        $matchesQueryResult = run_prepared_query($DBH, $matchesQuery, $projectQueryParams);
        $matches = $matchesQueryResult->fetchAll(PDO::FETCH_ASSOC);

        $matchesArray = array();

        foreach ($matches as $match) {
            $photoImageId = $match['photo_image_id'];
            $userImageId = $match['user_image_id'];

            if (!array_key_exists($match['photo_image_id'], $matchesArray)) {
                $matchesArray[$photoImageId]['photoLatitude'] = $match['photo_latitude'];
                $matchesArray[$photoImageId]['photoLongitude'] = $match['photo_longitude'];
                $matchesArray[$photoImageId]['photoThumb'] = $match['photo_thumb'];
                $matchesArray[$photoImageId]['computerImageId'] = $match['computer_image_id'];
                $matchesArray[$photoImageId]['computerLatitude'] = $match['computer_latitude'];
                $matchesArray[$photoImageId]['computerLongitude'] = $match['computer_longitude'];
                $matchesArray[$photoImageId]['computerThumb'] = $match['computer_thumb'];
                $matchesArray[$photoImageId]['computerMatchCount'] = 0;
                $matchesArray[$photoImageId]['userMatchCount'] = 0;
                $matchesArray[$photoImageId]['userMatches'] = array();
            }

            if ($userImageId == $match['computer_image_id']) {
                $matchesArray[$photoImageId]['computerMatchCount'] ++;
            } else {
                if (!array_key_exists($userImageId, $matchesArray[$photoImageId]['userMatches'])) {
                    $matchesArray[$photoImageId]['userMatchCount'] ++;
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['userLatitude'] = $match['user_latitude'];
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['userLongitude'] = $match['user_longitude'];
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['userThumb'] = $match['user_thumb'];
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['photoMatchCount'] = 1;
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['matchDelta'] = $userImageId - $match['computer_image_id'];
                    if ($matchesArray[$photoImageId]['userMatches'][$userImageId]['matchDelta'] > 0) {
                        $matchesArray[$photoImageId]['userMatches'][$userImageId]['direction'] = 'left';
                    } else {
                        $matchesArray[$photoImageId]['userMatches'][$userImageId]['direction'] = 'right';
                    }
                } else {
                    $matchesArray[$photoImageId]['userMatches'][$userImageId]['photoMatchCount'] ++;
                }
            }
        }

        $jsAllProjectMatchesArray = json_encode($matchesArray);

//        print '<pre>';
//        print_r($matchesArray);
//        print '</pre>';
//        print $jsAllProjectMatchesArray;
//        foreach ($matchesArray as $postPhotoId => $matchDetails) {
//            $matchDelta = abs($matchDelta);
//            if ($matchDelta < 10) {
//                $deltaText = "<br>The user selected an image <strong>$matchDelta image(s)</strong> to the $direction of the computer match.";
//            } else {
//                $deltaText = "<br>The user selected an image to the $direction of the computer match.";
//            }
//        }





        if (count($matchesArray) > 0) {
            $jsAllProjectMatchesMapCode = <<<EOL
                    var matchesArray = $jsAllProjectMatchesArray;

                    var matchesMap = L.map('matchesMapWrapper', {maxZoom: 16});
                    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
                    }).addTo(matchesMap);
                    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(matchesMap);
                    L.control.scale({
                    position: 'topright',
                            metric: false
                    }).addTo(matchesMap);


                    var postPhotoComputerMatchOnly = L.icon({
                    iconUrl: 'images/system/greenMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    var postPhotoMixedMatch = L.icon({
                    iconUrl: 'images/system/yellowMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    var postPhotoUserMatchOnly = L.icon({
                    iconUrl: 'images/system/redMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    var computerMatchIcon = L.icon({
                    iconUrl: 'images/system/greyMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });

                    var computerOnlyMatchMarkers = L.featureGroup();
                    var mixedMatchMarkers = L.featureGroup();
                    var userOnlyMatchMarkers = L.featureGroup();
                    var photoMatchMarkers = L.featureGroup();
                    var allMarkers = L.markerClusterGroup({
                        disableClusteringAtZoom: 12,
                        maxClusterRadius: 60
                    });

                    $.each(matchesArray, function(postPhotoId, postPhotoMatchData) {
                        var tempMatchMarkers = L.featureGroup();
                        var totalUserMatches = 0;
                        var distinctPhotos = 0;

                        $.each(postPhotoMatchData.userMatches, function (userMatchId, userMatchData) {
                            totalUserMatches += userMatchData.photoMatchCount;
                            distinctPhotos ++;
                        });
                        var totalMatches = totalUserMatches + postPhotoMatchData.computerMatchCount;
                        if (postPhotoMatchData.computerMatchCount > 0) {
                            distinctPhotos ++;
                        }

                        var photoLatLng = L.latLng(postPhotoMatchData.photoLatitude, postPhotoMatchData.photoLongitude);
                        if (postPhotoMatchData.computerMatchCount > 0 && postPhotoMatchData.userMatchCount == 0) {
                            var photoMarker = L.marker(photoLatLng, {icon: postPhotoComputerMatchOnly});
                            computerOnlyMatchMarkers.addLayer(photoMarker);
                        } else if (postPhotoMatchData.computerMatchCount > 0 && postPhotoMatchData.userMatchCount > 0) {
                            var photoMarker = L.marker(photoLatLng, {icon: postPhotoMixedMatch});
                            mixedMatchMarkers.addLayer(photoMarker);
                        } else {
                            var photoMarker = L.marker(photoLatLng, {icon: postPhotoUserMatchOnly});
                            userOnlyMatchMarkers.addLayer(photoMarker);
                        }
                        photoMarker.bindPopup('<h2>Post-Storm Photo</h2><p>Image <a href="photoStats.php?targetPhotoId=' + postPhotoId + '">' + postPhotoId + '</a></p><p>This is the currently selected post-storm image.</p><p><span class="userData">' + totalMatches + '</span> matches using <span class="userData">' + distinctPhotos + '</span> distinct pre-storm photos have been made for this image.</p><p><span class="userData">' + postPhotoMatchData.computerMatchCount + '</span> of <span class="userData">' + totalMatches + '</span> annotations used the <a href="photoStats.php?targetPhotoId=' + postPhotoMatchData.computerImageId + '">computer selected photo</a> as the best pre-storm match.</p><p><span class="userData">' + totalUserMatches + '</span> of <span class="userData">' + totalMatches + '</span> annotations used a <span class="userData">user selected photo</span> as the best pre-storm match.</p><a href="photoStats.php?targetPhotoId=' + postPhotoId + '"><img class="mapMarkerImage" width="167" height="109" src="' + postPhotoMatchData.photoThumb + '"></a>', {offset: [0, -35], autoPan: false});




                        photoMarker.on('dblclick', function(e) {
                            photoMatchMarkers.clearLayers();

                            var photoLatLng = L.latLng(matchesArray[postPhotoId]['photoLatitude'], matchesArray[postPhotoId]['photoLongitude']);

                            var computerLatLng = L.latLng(matchesArray[postPhotoId]['computerLatitude'], matchesArray[postPhotoId]['computerLongitude']);
                            var computerMarker = L.marker(computerLatLng, {icon: computerMatchIcon});
                            var distanceFromPostStormPhoto = Math.floor(computerLatLng.distanceTo(photoLatLng) * 3.3);
                            computerMarker.bindPopup('<h2>Computer Match</h2><p>Image <a href="photoStats.php?targetPhotoId=' + postPhotoMatchData.computerImageId + '">' + postPhotoMatchData.computerImageId + '</a></p><p>This is the photo chosen by the computer as the best possible pre-storm match based on proximity (<span class="userData">' + distanceFromPostStormPhoto + ' ft.</span>) to the selected post-storm photo.</p><p><span class="userData">' + postPhotoMatchData.computerMatchCount + '</span> of <span class="userData">' + totalMatches + '</span> annotations used this photo as the best pre-storm match.</p><div class="photoMatchMapComparisonWrapper"><div class="photoMatchMapComparisonPostPhotoWrapper"><p>Post-Storm Photo (<a href="photoStats.php?targetPhotoId=' + postPhotoId + '">' + postPhotoId + '</a>)</p><a href="photoStats.php?targetPhotoId=' + postPhotoId + '"><img class="mapMarkerImage" width="167" height="109" src="' + postPhotoMatchData.photoThumb + '"></a></div><div class="photoMatchMapComparisonPrePhotoWrapper"><p>Computer\'s Pre-Storm Match (<a href="photoStats.php?targetPhotoId=' + postPhotoMatchData.computerImageId + '">' + postPhotoMatchData.computerImageId + '</a>)</p><a href="photoStats.php?targetPhotoId=' + postPhotoMatchData.computerImageId + '"><img class="mapMarkerImage" width="167" height="109" src="' + postPhotoMatchData.computerThumb + '"></a></div></div>', {offset: [0, -35], maxWidth: 344, autoPan: false});
                            var computerLine = L.polyline([photoLatLng, computerLatLng], {color: 'grey'});
                            photoMatchMarkers.addLayer(computerMarker);
                            photoMatchMarkers.addLayer(computerLine);

                            $.each(matchesArray[postPhotoId]['userMatches'], function (userMatchId, userMatchData) {
                                var userLatLng = L.latLng(userMatchData.userLatitude, userMatchData.userLongitude);
                                var userMarker = L.marker(userLatLng);
                                var distanceFromComputerMatch = Math.floor(userLatLng.distanceTo(computerLatLng) * 3.3);
                                var distanceFromPostStormPhoto = Math.floor(userLatLng.distanceTo(photoLatLng) * 3.3);
                                userMarker.bindPopup('<h2>User Match</h2><p>Image <a href="photoStats.php?targetPhotoId=' + userMatchId + '">' + userMatchId + '</a></p><p>This photo was selected in <span class="userData">' + userMatchData.photoMatchCount + ' out of ' + totalMatches + '</span> complete user annotations as the best match for the post-storm photo based on human visual matching of landmarks.</p><p>This image is <span class="userData">' + distanceFromComputerMatch + ' ft.</span> from the computers best match and <span class="userData">' + distanceFromPostStormPhoto + ' ft.</span> from the post-storm photo.</p><div class="photoMatchMapComparisonWrapper"><div class="photoMatchMapComparisonPostPhotoWrapper"><p>Post-Storm Photo (<a href="photoStats.php?targetPhotoId=' + postPhotoId + '">' + postPhotoId + '</a>)</p><a href="photoStats.php?targetPhotoId=' + postPhotoId + '"><img class="mapMarkerImage" width="167" height="109" src="' + postPhotoMatchData.photoThumb + '"></a></div><div class="photoMatchMapComparisonPrePhotoWrapper"><p>User\'s Pre-Storm Match (<a href="photoStats.php?targetPhotoId=' + userMatchId + '">' + userMatchId + '</a>)</p><a href="photoStats.php?targetPhotoId=' + userMatchId + '"><img class="mapMarkerImage" width="167" height="109" src="' + userMatchData.userThumb + '"></a></div></div>', {offset: [0, -35], maxWidth: 344, autoPan: false});
                                var userLine = L.polyline([photoLatLng, userLatLng], {color: '#267FCA'});
                                photoMatchMarkers.addLayer(userMarker);
                                photoMatchMarkers.addLayer(userLine);
                            });
                            matchesMap.fitBounds(photoMatchMarkers.getBounds());
                        });

                    });


                    allMarkers.addLayer(computerOnlyMatchMarkers);
                    allMarkers.addLayer(mixedMatchMarkers);
                    allMarkers.addLayer(userOnlyMatchMarkers);

                    matchesMap.fitBounds(allMarkers.getBounds());
                    allMarkers.addTo(matchesMap);
                    photoMatchMarkers.addTo(matchesMap);

                $('#resetBoundaries').click(function() {
                    photoMatchMarkers.clearLayers();
                    matchesMap.fitBounds(allMarkers.getBounds());
                });

                $('#allMarkers').click(function() {
                    allMarkers.clearLayers();
                    photoMatchMarkers.clearLayers();
                    allMarkers.addLayer(computerOnlyMatchMarkers);
                    allMarkers.addLayer(mixedMatchMarkers);
                    allMarkers.addLayer(userOnlyMatchMarkers);
                    $('#computerMatchLegendRow').show();
                    $('#computerUserMatchLegendRow').show();
                    $('#userMatchLegendRow').show();
                });
                $('#computerMarkers').click(function() {
                    allMarkers.clearLayers();
                    photoMatchMarkers.clearLayers();
                    allMarkers.addLayer(computerOnlyMatchMarkers);
                    $('#computerMatchLegendRow').show();
                    $('#computerUserMatchLegendRow').hide();
                    $('#userMatchLegendRow').hide();
                });
                $('#userMarkers').click(function() {
                    allMarkers.clearLayers();
                    photoMatchMarkers.clearLayers();
                    allMarkers.addLayer(userOnlyMatchMarkers);
                    $('#computerMatchLegendRow').hide();
                    $('#computerUserMatchLegendRow').hide();
                    $('#userMatchLegendRow').show();
                });
                $('#bothMarkers').click(function() {
                    allMarkers.clearLayers();
                    photoMatchMarkers.clearLayers();
                    allMarkers.addLayer(mixedMatchMarkers);
                    $('#computerMatchLegendRow').hide();
                    $('#computerUserMatchLegendRow').show();
                    $('#userMatchLegendRow').hide();
                });



EOL;
            $allProjectMatchesMapWrapperHTML = <<<EOL
            <div class="adminMapWrapper"  id="allMatchesMap">
                <div id="matchesMapWrapper" class="adminMap">
                </div>
                <div class="adminMapLegend">
                    <div class="adminMapLegendRow">
                      <p>DOUBLE CLICK TO REVEAL MATCHES</p><p>SINGLE CLICK TO SHOW DETAILS</p>
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
                    <div class="adminMapLegendRow" id="computerMatchLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Post Storm Photo with only Computer Matches</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow" id="computerUserMatchLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/yellowMarker.png" alt="Image of a yellow map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Post Storm Photo with User and Computer Matches</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow" id="userMatchLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/redMarker.png" alt="Image of a red map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Post Storm Photo with only User Matches</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/greyMarker.png" alt="Image of a grey map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Computer Match Photo</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="http://cdn.leafletjs.com/leaflet-0.7.3/images/marker-icon.png" alt="Image of a blue map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>User Match Photo</p>
                      </div>
                    </div>
                </div>
            </div>
            <div>
                    <h3>Map Marker Controls</h3>
                <input type="button" class="clickableButton allMatchesMapControlButton" id="resetBoundaries" value="Reset Map Boundaries to Display All Markers"><br>
                <input type="button" class="clickableButton allMatchesMapControlButton" id="allMarkers" value="Show All Annotated Photos"><br>
                <input type="button" class="clickableButton allMatchesMapControlButton" id="computerMarkers" value="Show Annotated Photos With Just Computer Matches"><br>
                <input type="button" class="clickableButton allMatchesMapControlButton" id="userMarkers" value="Show Annotated Photos With Just User Matches"><br>
                <input type="button" class="clickableButton allMatchesMapControlButton" id="bothMarkers" value="Show Annotated Photos With Both Computer And User Matches">
            </div>
EOL;
        } else { // IF (count($matchesArray) > 0) ELSE
            $photoMatchDetailsHTML .= '<p>No photos have been annotated in the selected project. Nothing to display.</p>';
        }
    } else { // IF ($projectMetadata) ELSE
        $photoMatchDetailsHTML = <<<EOL
            <h2 id="projectAllMatchesSelectHeader">Select A Project to View All Annotated Photos and Matches</h2>
            <p>To view a map of all annotated post-storm photos and their related matches select a project from the following list.</p>
            <form method="get" autocomplete="off" action="#projectAllMatchesSelectHeader">
                <label for="analyticsAllMatchesProjectSelection">Project: </label>
                <select id="analyticsAllMatchesProjectSelection" class="formInputStyle" name="targetProjectId">
                    $projectSelectHTML
                </select>
                <input type="submit" class="clickableButton" value="Select Project">
            </form>
EOL;
    } // END IF ($projectMetadata) ELSE
}





$cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';

$javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
$javaScriptLinkArray[] = 'scripts/elevateZoom.js';

$javaScript .= <<<EOL
function hideLoader(isPost) {
    $('#imageZoomLoadingIndicator').hide();
}

$jsPhotoLocationLatitude
$jsPhotoLocationLongitude
$jsTargetPhotoId
$jsTargetProjectId

EOL;


$jQueryDocumentDotReadyCode .= <<<EOL

$jsphotoLocationMapCode
$jsPhotoMatchesMapCode
$jsAllProjectMatchesMapCode

$('#photoStatsImage').elevateZoom({
    scrollZoom: 'true',
    zoomType: 'lens',
    lensSize: 200,
    cursor: "crosshair",
    lensFadeIn: 400,
    lensFadeOut: 400,
    containLensZoom: 'true',
    scrollZoomIncrement: 0.2,
    borderColour: '#000000',
    onZoomedImageLoaded: function() {
        hideLoader(true);
    }
});

$('#tagSelectionFrequencyDownload').click(function() {
    window.location = "ajax/csvGenerator.php?dataSource=tagSelectionFrequencies&targetProjectId=" + targetProjectId + "&targetPhotoId=" + targetPhotoId;
});

var selectedProjectValue = $('#analyticsAllMatchesProjectSelection option[selected]').val();
if (typeof (selectedProjectValue) === 'undefined') {
    $('#analyticsAllMatchesProjectSelection').prop('selectedIndex', -1);
}

EOL;
