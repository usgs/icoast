<?php

$pageName = "start";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray[] = 'scripts/markerClusterPlus.js';
$javascript = '';
$jQueryDocumentDotReadyCode = '';


require 'includes/userFunctions.php';
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;




if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    header('Location: index.php');
    exit;
}

$filtered = TRUE;
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];
$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$variableContent = '';
$allProjects = array();
$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
foreach ($DBH->query($allProjectsQuery) as $row) {
    $allProjects[] = $row;
}
$numberOfProjects = count($allProjects);
if ($numberOfProjects > 1) {

    $lastAnnotatedProjectQuery = "SELECT project_id FROM annotations WHERE user_id = :userId AND "
            . "annotation_completed = 1 ORDER BY initial_session_end_time DESC LIMIT 1";
    $lastAnnotatedProjectParams['userId'] = $userId;
    $STH = run_prepared_query($DBH, $lastAnnotatedProjectQuery, $lastAnnotatedProjectParams);
    $lastAnnotatedProject = $STH->fetchColumn();
    $projectSelectOptionHTML = "";
    if ($lastAnnotatedProject) {
        for ($i = 0; $i < $numberOfProjects; $i++) {
            if ($allProjects[$i]['project_id'] == $lastAnnotatedProject) {
                $projectId = $allProjects[$i]['project_id'];
                $projectName = $allProjects[$i]['name'];
                $projectSelectOptionHTML .= "<option value=\"$projectId\">$projectName</option>\r\n";
                unset($allProjects[$i]);
            }
        }
    } else {
        $projectId = $allProjects[0]['project_id'];
        $projectName = $allProjects[0]['name'];
    }

    foreach ($allProjects as $project) {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>\r\n";
    }
    $projectSelectionHTML = <<<EOL
          <label for="projectSelect">
              Choose a Different Project
          </label>
          <div class="formFieldRow standAloneFormElement">
            <select class="clickableButton" id="projectSelect" name="projectId" title="Selecting a new project
                from this list will cause iCoast to pick a new random image from the new project for you to tag.">
              $projectSelectOptionHTML
            </select>
          </div>

EOL;
} else if ($numberOfProjects == 1) {
    $projectId = $allProjects[0]['project_id'];
    $projectName = $allProjects[0]['name'];
    $projectSelectionHTML = <<<EOL
            <p>You are annotating the only project currently available in iCoast. Other selections are not available at this time.</p>

EOL;
} else {
    $projectSelectionHTML = <<<EOL
      <h2>No Projects Available</h2>
      <p>At this time there are no projects available for annotation in iCoast.</p>
      <p>Please check back at a later date for exciting new coastal imagery.</p>
EOL;
}

if ($numberOfProjects >=1) {
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    $newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
// Find post image metadata $postImageMetadata
    if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
        //  Placeholder for error management
        exit("Image $newRandomImageId not found in Database");
    }
    $newRandomImageLatitude = $newRandomImageMetadata['latitude'];
    $newRandomImageLongitude = $newRandomImageMetadata['longitude'];
    $newRandomImageLocation = build_image_location_string($newRandomImageMetadata);
    $newRandomImageDisplayURL = "images/datasets/{$newRandomImageMetadata['dataset_id']}/main/{$newRandomImageMetadata['filename']}";
    $newRandomImageAltTag = "An oblique image of the United States coastline taken near $newRandomImageLocation.";




        $variableContent = <<<EOL
<h2 id="currentProjectText">Current Project: $projectName</h2>
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
            <div class="stackedNavSelectWrapper">
                $projectSelectionHTML
            </div>
        </div>
</div>

EOL;

    require("includes/mapNavigator.php");

    $variableContent .= $mapHTML;

    $javaScript = "$mapScript";

    $jQueryDocumentDotReadyCode = <<<EOL
    $mapDocumentReadyScript

    if ($('#projectSelect').length) {
        $('#projectSelect').prop('selectedIndex', 0);
    }

EOL;
} else {
    $variableContent = $projectSelectionHTML;
}
