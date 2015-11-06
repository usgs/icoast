<?php

$cssLinkArray[] = 'css/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';
$cssLinkArray[] = 'css/leafletGeoSearch.css';

$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearch.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearchProvider.js';

$jQueryDocumentDotReadyCode = '';


require_once('includes/userFunctions.php');
require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);
$userId = $userData['user_id'];

$filtered = TRUE;

$requestedProjectId = filter_input(INPUT_GET, 'requestedProjectId', FILTER_VALIDATE_INT);
$requestedProjectMetadata = retrieve_entity_metadata($DBH, $requestedProjectId, 'project');
if ($requestedProjectMetadata &&
        $requestedProjectMetadata['is_complete'] == 0 &&
        $requestedProjectMetadata['is_public'] == 0) {
    $requestedProjectMetadata = null;
    $requestedProjectId = null;
} else {
    $targetProject = $requestedProjectId;
}

$variableContent = '';
$focusedProjectReminder = '';
$allProjects = array();

$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 AND is_complete = 1 ORDER BY project_id DESC";
foreach ($DBH->query($allProjectsQuery) as $row) {
    $allProjects[] = $row;
}
$numberOfProjects = count($allProjects);
if ($numberOfProjects > 1) {

    $projectInFocusQuery = '
        SELECT home_page_project
        FROM system';
    $projectInFocusResult = run_prepared_query($DBH, $projectInFocusQuery);
    $projectInFocus = $projectInFocusResult->fetchColumn();

    if (!$requestedProjectMetadata) {
        $lastAnnotatedProjectQuery = "SELECT project_id FROM annotations WHERE user_id = :userId AND "
                . "annotation_completed = 1 ORDER BY initial_session_end_time DESC LIMIT 1";
        $lastAnnotatedProjectParams['userId'] = $userId;
        $STH = run_prepared_query($DBH, $lastAnnotatedProjectQuery, $lastAnnotatedProjectParams);
        $targetProject = $STH->fetchColumn();
    }
    if ($targetProject && ($targetProject != $projectInFocus)) {
        $projectInFocusMetadata = retrieve_entity_metadata($DBH, $projectInFocus, 'project');
        $focusedProjectReminder = "<p class=\"focusedProjectTextHighlight\">Don't forget to check out our current focused project, <a href=\"start.php?requestedProjectId=$projectInFocus\">{$projectInFocusMetadata['name']}</a>.</p>";
    }

    $projectSelectOptionHTML = "";
    if ($targetProject) {
        for ($i = 0; $i < $numberOfProjects; $i++) {
            if ($allProjects[$i]['project_id'] == $targetProject) {
                $projectId = $allProjects[$i]['project_id'];
                $projectName = $allProjects[$i]['name'];
                $projectSelectOptionHTML .= "<option value=\"$projectId\">$projectName</option>\r\n";
                unset($allProjects[$i]);
            }
        }
    } else {
        for ($i = 0; $i < $numberOfProjects; $i++) {
            if ($allProjects[$i]['project_id'] == $projectInFocus) {
                $projectId = $allProjects[$i]['project_id'];
                $projectName = $allProjects[$i]['name'];
                $projectSelectOptionHTML .= "<option value=\"$projectId\">$projectName</option>\r\n";
                unset($allProjects[$i]);
            }
        }
    }

    foreach ($allProjects as $project) {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>\r\n";
    }
    $projectSelectionHTML = <<<EOL

            <div>
                <span id="selectedProjectTitle">Current Project:</span>
                <select class="formInputStyle" id="projectSelect" name="projectId" title="Selecting a new project
                    from this list will cause iCoast to pick a new random image from the new project for you to tag.">
                  $projectSelectOptionHTML
                </select>
            </div>


EOL;
} else if ($numberOfProjects == 1) {
    $projectId = $allProjects[0]['project_id'];
    $projectName = $allProjects[0]['name'];
    $projectSelectionHTML = <<<EOL
            <p id="selectedProjectTitle">Current Project: $projectName</p>

EOL;
} else {
    $projectSelectionHTML = <<<EOL
      <h2>No Projects Available</h2>
      <p>At this time there are no projects available for annotation in iCoast.</p>
      <p>Please check back at a later date for exciting new coastal imagery.</p>
EOL;
}

if ($numberOfProjects >= 1) {
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    $newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
// Find post image metadata $postImageMetadata
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty') {
        $newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id']);
    }
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty' || $newRandomImageId === FALSE) {
        exit("An error was detected while generating a new image. $newRandomImageId");
    }
    if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
        //  Placeholder for error management
        exit("Image $newRandomImageId not found in Database");
    }
    $newRandomImageLatitude = $newRandomImageMetadata['latitude'];
    $newRandomImageLongitude = $newRandomImageMetadata['longitude'];
    $newRandomImageLocation = build_image_location_string($newRandomImageMetadata);
    $newRandomImageDisplayURL = "images/collections/{$newRandomImageMetadata['collection_id']}/main/{$newRandomImageMetadata['filename']}";
    $newRandomImageAltTag = "An oblique image of the United States coastline taken near $newRandomImageLocation.";




    $variableContent = <<<EOL
$projectSelectionHTML
$focusedProjectReminder
<div id="randomPostImagePreviewWrapper">
    <p>Here is a random photo near<br><span id="projectName" class="captionTitle">$newRandomImageLocation</span></p>
    <div>
        <img src="$newRandomImageDisplayURL" alt="$newRandomImageAltTag" title="This random image has been
             chosen for you to tag next. Either accept the image, request a new random image, choose your
             own image from the map or switch projects (if available) using the buttons on the right."
             height="250" width="384">
    </div>
</div>



<div id="randomPostImageControls">


    <div class="singleNavButtonWrapper">
        <p>Tag This<br>Random Photo</p>
        <button class="clickableButton" type="button" id="tagButton"
                title="Using this button will load the classification page using the random image shown on the left.">
            <img src="images/system/checkmark.png" height="232" width="232" alt="Image of a dice indicating
                 that this button causes iCoast to randomly select an image to display">
        </button>
    </div>
    <div id="navOptionDivider">
        <p>OR</p>
    </div>


        <div id="stackedNavButtons">
            <p>Select an Option Below</p>
            <div class="stackedNavButtonWrapper">
                <label for="randomButton">
                    Find a New Random Photo
                </label>
                <button class="clickableButton" type="button" id="randomButton"
                        title="Using this button will cause iCoast to pick a new random image from your chosen
                            project for you to tag.">
                    <img src="images/system/dice.png" height="64" width="64" alt="Image of a dice indicating
                         that this button causes iCoast to randomly select an image to display">
                </button>
            </div>
            <div class="stackedNavButtonWrapper">
                <label for="mapButton">
                    Find a Photo from the Map
                </label>
                <button class="clickableButton" type="button" id="mapButton"
                        title="Using this button will cause iCoast to display a map of a section of the US coast from
                        which you can choose an image to tag.">
                    <img src="images/system/map.png" height="64" width="64" alt="Image of a map and push pin
                         indicating that this button causes iCoast to display a map from which you can choose an image
                         from your selected project to tag.">
                </button>
            </div>
        </div>
</div>

EOL;

    require("includes/mapNavigator.php");

    $variableContent .= $mapHTML;

    $javaScript = "$mapScript";

    $jQueryDocumentDotReadyCode .= <<<EOL
    $mapDocumentReadyScript

    if ($('#projectSelect').length) {
        $('#projectSelect').prop('selectedIndex', 0);
    }

EOL;
} else {
    $variableContent = $projectSelectionHTML;
}

$embeddedCSS = <<<EOL
        .focusedProjectTextHighlight {
            font-weight: bold;
        }
        
        #projectSelect {
            min-width: 200px;
            max-width: 350px;
        }
        
        #selectedProjectTitle {
            position: relative;
            top: 3px;
            display: inline-block;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .stackedNavButtonWrapper:first-of-type {
            margin-top: 40px;
        }
EOL;
        