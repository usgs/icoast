<?php

$cssLinkArray[] = 'css/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';
$cssLinkArray[] = 'css/leafletGeoSearch.css';
$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearch.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearchProvider.js';


require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);
$userId = $userData['user_id'];

if (!isset($_GET['projectId']) || !isset($_GET['imageId'])) {
    header('Location: index.php');
    exit;
}

$filtered = TRUE;
$projectId = $_GET['projectId'];
$postImageId = $_GET['imageId'];

if (!$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project')) {
    //  Placeholder for error management
    exit("Project $projectId not found in Database");
}
if (!$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image')) {
    //  Placeholder for error management
    exit("Image $postImageId not found in Database");
}
$projectName = $projectMetadata['name'];
$postDisplayImageURL = "images/datasets/{$postImageMetadata['dataset_id']}/main/{$postImageMetadata['filename']}";
$postImageLocation = build_image_location_string($postImageMetadata);


//--------------------------------------------------------------------------------------------------
// Determine total number of user annotations in iCoast and update user metadata is needed.
$annotationCountQuery = "SELECT COUNT(*) FROM annotations WHERE user_id = :userId AND"
        . " annotation_completed = 1";
$annotationCountParams['userId'] = $userId;
$STH = run_prepared_query($DBH, $annotationCountQuery, $annotationCountParams);
$numberOfAnnotations = $STH->fetchColumn();
if ($numberOfAnnotations == 0) {
    header('Location: welcome.php');
    exit;
}
if ($numberOfAnnotations != $userData['completed_annotation_count']) {
    $setAnnotationCountQuery = "UPDATE users SET completed_annotation_count = :numberOfAnnotations WHERE user_id = :userId";
    $setAnnotationCountParams = array(
        'userId' => $userId,
        'numberOfAnnotations' => $numberOfAnnotations,
    );
    $STH = run_prepared_query($DBH, $setAnnotationCountQuery, $setAnnotationCountParams);
    if ($STH->rowCount() == 0) {
        //  Placeholder for error management
        print 'User Annotation Count Update Error: Update did not complete sucessfully.';
        exit;
    }
}
$ordinalNumberOfAnnotations = ordinal_suffix($numberOfAnnotations);


$positionQuery = "SELECT completed_annotation_count FROM users WHERE completed_annotation_count > :numberOfAnnotations "
        . "ORDER BY completed_annotation_count DESC";
$positionParams['numberOfAnnotations'] = $numberOfAnnotations;
$STH = run_prepared_query($DBH, $positionQuery, $positionParams);
$annotaionPositions = $STH->fetchAll(PDO::FETCH_ASSOC);
$positionInICoast = count($annotaionPositions) + 1;
$ordinalPositionInICoast = ordinal_suffix($positionInICoast) . ' Place';

$jointPosition = FALSE;
$jointQuery = "SELECT COUNT(*) FROM users WHERE completed_annotation_count = $numberOfAnnotations";
$jointParams['numberOfAnnotations'] = $numberOfAnnotations;
$STH = run_prepared_query($DBH, $jointQuery, $jointParams);
if ($STH->fetchColumn() > 1) {
    $jointPosition = TRUE;
}

if ($jointPosition) {
    $ordinalPositionInICoast = "Joint " . $ordinalPositionInICoast;
} elseif ($positionInICoast == 1) {
    $ordinalPositionInICoast .= " - Top iCoast Tagger!";
}

$annotationsToNextHTML = "";
if ($positionInICoast > 1) {
    $annotationsToFirst = $annotaionPositions[0]['completed_annotation_count'] - $numberOfAnnotations + 1;
    $nextPosition = ordinal_suffix($positionInICoast - 1);
    $annotationsToNextHTML = "<tr><td class=\"rowTitle\"># of Photos to be 1st:</td><td class=\"userData\">$annotationsToFirst</td></tr>";
}





//--------------------------------------------------------------------------------------------------
// Retreive annotation data for the last annotated image.
$lastAnnotationQuery = "SELECT * FROM annotations WHERE user_id = :userId AND "
        . "project_id = :projectId AND image_id = :postImageId";
$lastAnnotationParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$STH = run_prepared_query($DBH, $lastAnnotationQuery, $lastAnnotationParams);
$lastAnnotation = $STH->fetch(PDO::FETCH_ASSOC);
$annotationId = $lastAnnotation['annotation_id'];

$lastAnnotationTime = timeDifference($lastAnnotation['initial_session_start_time'], $lastAnnotation['initial_session_end_time']);
$tagCount = tagsInAnnotation($DBH, $annotationId);


//--------------------------------------------------------------------------------------------------
// Find image id's of next and previous post images
$postImageArray = find_adjacent_images($DBH, $postImageId, $projectId, $userId);
$previousImageId = $postImageArray[0]['image_id'];
if ($previousImageId != 0 && !$previousImageMetadata = retrieve_entity_metadata($DBH, $previousImageId, 'image')) {
    //  Placeholder for error management
    exit("Previous Image $previousImageId not found in Database");
}
$previousImageLatitude = $previousImageMetadata['latitude'];
$previousImageLongitude = $previousImageMetadata['longitude'];
$previousImageDisplayURL = "images/datasets/{$previousImageMetadata['dataset_id']}/main/{$previousImageMetadata['filename']}";
$previousImageLocation = build_image_location_string($previousImageMetadata);

$nextImageId = $postImageArray[2]['image_id'];
if ($nextImageId != 0 && !$nextImageMetadata = retrieve_entity_metadata($DBH, $nextImageId, 'image')) {
    //  Placeholder for error management
    exit("Next Image $nextImageId not found in Database");
}
$nextImageLatitude = $nextImageMetadata['latitude'];
$nextImageLongitude = $nextImageMetadata['longitude'];
$nextImageDisplayURL = "images/datasets/{$nextImageMetadata['dataset_id']}/main/{$nextImageMetadata['filename']}";
$nextImageLocation = build_image_location_string($nextImageMetadata);

//--------------------------------------------------------------------------------------------------
// Build next/previous post image buttons HTML


if ($previousImageId != 0) {
    $leftCoastalNavigationButtonHTML = '<button class="clickableButton leftCoastalNavButton" type="button" title="Click to show the next available POST-storm Photo to the LEFT of your last annotated photo." id="leftButton"><img src="images/system/leftArrow.png" alt="Image of a left facing arrow. Used to navigate left along the coast" height="64" width="41"></button>';
} else {
    $leftCoastalNavigationButtonHTML = '<button class="clickableButton disabledClickableButton leftCoastalNavButton" type="button" title="No more images are within range in this direction. Use the Map to move along the coat to the next region." id="leftButton" disabled><img src="images/system/leftArrow.png" alt="Image of a faded left facing arrow. Used to indicate there are no more images to the left of the last annotated image." height="64" width="41"></button>';
}
if ($nextImageId != 0) {
    $rightCoastalNavigationButtonHTML = '<button class="clickableButton" type="button" title="Click to show the next available POST-storm Photo to the RIGHT of your last annotated photo." id="rightButton"><img src="images/system/rightArrow.png" alt="Image of a right facing arrow. Used to navigate right along the coast" height="64" width="41"></button>';
} else {
    $rightCoastalNavigationButtonHTML = '<button class="clickableButton disabledClickableButton" type="button" title="No more images within range in this direction. Use the Map to move along the coat to the next region." id="rightButton" disabled><img src="images/system/rightArrow.png" alt="Image of a faded right facing arrow. Used to indicate there are no more images to the right of the last annotated image." height="64" width="41"></button>';
}


//--------------------------------------------------------------------------------------------------
// Build project selection information
$allProjects = array();
$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
foreach ($DBH->query($allProjectsQuery) as $row) {
    $allProjects[] = $row;
}
$numberOfProjects = count($allProjects);
if ($numberOfProjects > 1) {
    $projectSelectOptionHTML = "";
    for ($i = 0; $i < $numberOfProjects; $i++) {
        if ($allProjects[$i]['project_id'] == $projectId) {
            $id = $allProjects[$i]['project_id'];
            $name = $allProjects[$i]['name'];
            $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>\r\n";
            unset($allProjects[$i]);
        }
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



//--------------------------------------------------------------------------------------------------
// Determine the new Random Image for next annotation and prepare random image selection HTML
if ($numberOfProjects >= 1) {
    $newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty') {
        $newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id']);
    }
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty' || $newRandomImageId === FALSE) {
        exit("An error was detected while generating a new image");
    }
    if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
        //  Placeholder for error management
        exit("New random Image $newRandomImageId not found in Database");
    }
    $newRandomImageLatitude = $newRandomImageMetadata['latitude'];
    $newRandomImageLongitude = $newRandomImageMetadata['longitude'];
    $newRandomImageDisplayURL = "images/datasets/{$newRandomImageMetadata['dataset_id']}/main/{$newRandomImageMetadata['filename']}";
    $newRandomImageLocation = build_image_location_string($newRandomImageMetadata);
    $newRandomImageAltTag = "An oblique image of the United States coastline taken near $newRandomImageLocation.";

    $variableContent = <<<EOL
        <h2>Select Another Photo</h2>
        <div id="randomPostImagePreviewWrapper">
            <p>Here is a random photo near<br><span id="projectName" class="captionTitle">$newRandomImageLocation</span></p>
            <div>
                <img src="$newRandomImageDisplayURL" alt="$newRandomImageAltTag" title="This random image has been
                     chosen for you to tag next. Either accept the image, request a new random image, choose your
                     own image form the map, traverse the the coast from the location of your last annotated
                     image, or switch projects (if available) using the buttons on the right." height="250" width="384">
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
                    <label for="randomButton">Find a New Random Photo</label>
                    <button class="clickableButton" type="button" id="randomButton"
                            title="Using this button will cause iCoast to pick a new random image from your chosen
                            project for you to tag.">
                        <img src="images/system/dice.png" height="64" width="64" alt="Image of a dice indicating
                             that this button causes iCoast to randomly select an image to display">
                    </button>
                </div>
                <div class="stackedNavButtonWrapper">
                    <label for="mapButton">Find a Photo from the Map</label>
                    <button class="clickableButton" type="button" id="mapButton"
                            title="Using this button will cause iCoast to display a map of a section of the US coast from
                            which you can choose an image to tag.">
                        <img src="images/system/map.png" height="64" width="64" alt="Image of a map and push pin
                             indicating that this button causes iCoast to display a map from which you can choose an image
                             from your selected project to tag.">
                    </button>
                </div>
                <div class="stackedNavButtonWrapper" id="traverseCoastControls">
                    <p>Traverse the Coast</p>
                    $rightCoastalNavigationButtonHTML
                    $leftCoastalNavigationButtonHTML
                    <img id="coastalNavigationImage" src="$postDisplayImageURL"
                         title="This is photo you just tagged." alt="An oblique coastal image of the $postImageLocation
                         area. This is the photo you tagged." height="50" width="77" />
                </div>
                <div class="stackedNavSelectWrapper">
                    $projectSelectionHTML
                </div>
            </div>

        </div>
EOL;

    require("includes/mapNavigator.php");

    $variableContent .= $mapHTML;

    $javaScript = <<<EOL
        $mapScript
            function iCoastTitle() {
                if ($(window).width() > ($('#navigationBar ul').outerWidth() + $('#navigationBar p').outerWidth() + 20)) {
                    if ($(window).scrollTop() > $('#usgstitle').position().top && $('#navigationBar p').length == 0) {
                        $('#navigationBar').append('<p>USGS iCoast - Did the Coast Change?</p>');
                    } else if ($(window).scrollTop() < $('#usgstitle').position().top && $('#navigationBar p').length) {
                        $('#navigationBar p').remove();
                    }
                } else {
                    if ($('#navigationBar p').length) {
                        $('#navigationBar p').remove();
                    }
                }
            }
EOL;

    $jQueryDocumentDotReadyCode = <<<EOL
    $mapDocumentReadyScript
            var leftImageData = {
                newProjectName: '$projectName',
                newRandomImageId:  '$previousImageId',
                newRandomImageLatitude: '$previousImageLatitude',
                newRandomImageLongitude: '$previousImageLongitude',
                newRandomImageLocation: '$previousImageLocation',
                newRandomImageDisplayURL: '$previousImageDisplayURL'
            }
             var rightImageData = {
                newProjectName: '$projectName',
                newRandomImageId:  '$nextImageId',
                newRandomImageLatitude: '$nextImageLatitude',
                newRandomImageLongitude: '$nextImageLongitude',
                newRandomImageLocation: '$nextImageLocation',
                newRandomImageDisplayURL: '$nextImageDisplayURL'
            }

        $('#leftButton').click(function() {

            processRandomImageChange(leftImageData);
//            console.log('Showing ' + leftImageData.newRandomImageId);
            var currentImageDetails = {
                imageId: leftImageData.newRandomImageId,
                userId: $userId,
                projectId: $projectId
            };

            $.getJSON('ajax/traverseTheCoast.php', currentImageDetails, function(adjacentImageData) {
                leftImageData.newRandomImageId = adjacentImageData.left.newRandomImageId;
                leftImageData.newRandomImageLatitude = adjacentImageData.left.newRandomImageLatitude;
                leftImageData.newRandomImageLongitude = adjacentImageData.left.newRandomImageLongitude;
                leftImageData.newRandomImageLocation = adjacentImageData.left.newRandomImageLocation;
                leftImageData.newRandomImageDisplayURL = adjacentImageData.left.newRandomImageDisplayURL;

                rightImageData.newRandomImageId = adjacentImageData.right.newRandomImageId;
                rightImageData.newRandomImageLatitude = adjacentImageData.right.newRandomImageLatitude;
                rightImageData.newRandomImageLongitude = adjacentImageData.right.newRandomImageLongitude;
                rightImageData.newRandomImageLocation = adjacentImageData.right.newRandomImageLocation;
                rightImageData.newRandomImageDisplayURL = adjacentImageData.right.newRandomImageDisplayURL;

                if (leftImageData.newRandomImageId == 0) {
//                    console.log('Disabling left button.');
                    $("#leftButton").addClass('disabledClickableButton');
                    $("#leftButton").attr("disabled", "disabled");
                    $("#leftButton").attr("title", "No more images are within range in this direction. Use the Map to move along the coat to the next region.");

                } else {
//                    console.log('Enabling left button.');
                    $("#leftButton").removeClass('disabledClickableButton');
                    $("#leftButton").removeAttr("disabled", "disabled");
                    $("#leftButton").attr("title", "Click to show the next available POST-storm Photo to the LEFT of your last annotated photo.");
                }

                if (rightImageData.newRandomImageId == 0) {
//                    console.log('Disabling right button.');
                    $("#rightButton").addClass('disabledClickableButton');
                    $("#rightButton").attr("disabled", "disabled");
                    $("#rightButton").attr("title", "No more images are within range in this direction. Use the Map to move along the coat to the next region.");

                } else {
//                                console.log('Enabling right button.');

                    $("#rightButton").removeClass('disabledClickableButton');
                    $("#rightButton").removeAttr("disabled", "disabled");
                    $("#rightButton").attr("title", "Click to show the next available POST-storm Photo to the RIGHT of your last annotated photo.");
                }
//            console.log('Next Up: ' + leftImageData.newRandomImageId);
             });

        });
        $('#rightButton').click(function() {

            processRandomImageChange(rightImageData);
//            console.log('Showing ' + rightImageData.newRandomImageId);
            var currentImageDetails = {
                imageId: rightImageData.newRandomImageId,
                userId: $userId,
                projectId: $projectId
            };

            $.getJSON('ajax/traverseTheCoast.php', currentImageDetails, function(adjacentImageData) {
                leftImageData.newRandomImageId = adjacentImageData.left.newRandomImageId;
                leftImageData.newRandomImageLatitude = adjacentImageData.left.newRandomImageLatitude;
                leftImageData.newRandomImageLongitude = adjacentImageData.left.newRandomImageLongitude;
                leftImageData.newRandomImageLocation = adjacentImageData.left.newRandomImageLocation;
                leftImageData.newRandomImageDisplayURL = adjacentImageData.left.newRandomImageDisplayURL;

                rightImageData.newRandomImageId = adjacentImageData.right.newRandomImageId;
                rightImageData.newRandomImageLatitude = adjacentImageData.right.newRandomImageLatitude;
                rightImageData.newRandomImageLongitude = adjacentImageData.right.newRandomImageLongitude;
                rightImageData.newRandomImageLocation = adjacentImageData.right.newRandomImageLocation;
                rightImageData.newRandomImageDisplayURL = adjacentImageData.right.newRandomImageDisplayURL;

                if (leftImageData.newRandomImageId == 0) {
//                    console.log('Disabling left button.');
                    $("#leftButton").addClass('disabledClickableButton');
                    $("#leftButton").attr("disabled", "disabled");
                    $("#leftButton").attr("title", "No more images are within range in this direction. Use the Map to move along the coat to the next region.");

                } else {
//                    console.log('Enabling left button.');
                    $("#leftButton").removeClass('disabledClickableButton');
                    $("#leftButton").removeAttr("disabled", "disabled");
                    $("#leftButton").attr("title", "Click to show the next available POST-storm Photo to the LEFT of your last annotated photo.");
                }

                if (rightImageData.newRandomImageId == 0) {
//                    console.log('Disabling right button.');
                    $("#rightButton").addClass('disabledClickableButton');
                    $("#rightButton").attr("disabled", "disabled");
                    $("#rightButton").attr("title", "No more images are within range in this direction. Use the Map to move along the coat to the next region.");

                } else {
//                                console.log('Enabling right button.');

                    $("#rightButton").removeClass('disabledClickableButton');
                    $("#rightButton").removeAttr("disabled", "disabled");
                    $("#rightButton").attr("title", "Click to show the next available POST-storm Photo to the RIGHT of your last annotated photo.");
                }

//            console.log('Next Up: ' + rightImageData.newRandomImageId);
             });

        });

        if ($('#projectSelect').length) {
            $('#projectSelect').prop('selectedIndex', 0);
        }

        $(window).resize(function() {
            iCoastTitle();
        });

        $(window).scroll(function() {
            iCoastTitle();
        });

        $(window).scrollTop($('#navigationBar').position().top);
EOL;
} else {
    $variableContent = $projectSelectionHTML;
}




