<?php

$cssLinkArray[] = 'css/tipTip.css';
$cssLinkArray[] = 'css/leaflet.css';

$javaScriptLinkArray[] = '//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js';
$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/elevateZoom.js';
$javaScriptLinkArray[] = 'scripts/tipTip.js';

$jQueryDocumentDotReadyCode = '';
$javaScript = '';



// => Define required files and initial includes
require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);
$userId = $userData['user_id'];
$authCheckCode = $userData['auth_check_code'];

$url = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$queryString = htmlentities($_SERVER['QUERY_STRING']);
$postData = '';
$firstValue = TRUE;
foreach ($_POST as $postField => $postValue) {
    urlencode($postField);
    urldecode($postValue);
    if (!$firstValue) {
        $postData .= '&';
    }
    $postData .= $postField . '=' . $postValue;
    $firstValue = FALSE;
}
$clientAgent = $_SERVER['HTTP_USER_AGENT'];

$filtered = TRUE;

$projectId = "";
if (empty($_POST['projectId']) && empty($_GET['projectId'])) {
    header("location: start.php");
    exit;
} else {
    if (!empty($_POST['projectId'])) {
        $projectId = $_POST['projectId'];
    } else {
        $projectId = $_GET['projectId'];
    }

    settype($projectId, 'integer');
    if (!empty($projectId)) {
        $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    }

    if (!$projectMetadata) {
        header("location: index.php?userType=existing");
        exit;
    }
}

//////////
// => No Image ID Page Redirect
// => If the page has been called without a random image id in the query string then generate
// => an image id and redirect back to the page with a string attached.
if (empty($_GET['imageId'])) {
    $postImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
    header("location: classification.php?projectId=$projectId&imageId=$postImageId");
    exit();
}

//--------------------------------------------------------------------------------------------------
// Functions to be removed to include
// Build Image Header
function build_image_header($imageMetadata, $header) {
    $imageLocalTime = utc_to_timezone($imageMetadata['image_time'], 'H:i:s T', $imageMetadata['longitude']);
    $imageDate = utc_to_timezone($imageMetadata['image_time'], 'd M Y', $imageMetadata['longitude']);
    $imageLocation = build_image_location_string($imageMetadata, TRUE);
    $imageHeader = <<<EOT
            <p>$header</p>
            <p>$imageDate at $imageLocalTime near $imageLocation</p>

EOT;
    return $imageHeader;
}

// Build Annotation Tags
function build_group_contents($projectId, $tags) {
    $totalTags = count($tags);
    if ($totalTags == 4) {
        $columnMax = 3;
    } else {
        $columnMax = 4;
    }
    $tagCounter = 0;
    $tagString[0] = <<<EOT
        <div class="tagColumn">

EOT;
    $tagString[1] = "";
    foreach ($tags as $tag) {
        $tagCounter++;
        if ($tagCounter == $columnMax) {
            $tagCounter = 1;
            $tagString[0] .= <<<EOT
            </div>
            <div class="tagColumn">

EOT;
        }
        $tagId = $tag['id'];
        $isTagAComment = $tag['comment'];
        $isTagARadioButton = $tag['radio'];
        $radioButtonName = $tag['radioGroup'];
        $tagText = $tag['text'];
        $tagTooltip = $tag['tooltipText'];
        $tagTooltipImage = $tag['tooltipImage'];
        $tagTooltipImageWidth = $tag['tooltipImageWidth'];
        $tagTooltipImageHeight = $tag['tooltipImageHeight'];
        $tagSelected = $tag['userSelected'];
        if (isset($tag['userComment'])) {
            $userComment = htmlspecialchars($tag['userComment']);
        }
        if ($isTagAComment) {
            $tagString[0] .= <<<EOT
              <label id="tag$tagId">$tagText<br>
                <textarea name="$tagId" rows="5" cols="50">

EOT;
            if ($tagSelected) {
                $tagString[0] .= $userComment;
            }
            $tagString[0] .= "</textarea></label>";
        } elseif ($isTagARadioButton) {
            $tagString[0] .= '<input type="radio" ';
            if ($tagSelected) {
                $tagString[0] .= 'checked="checked" ';
            }
            $tagString[0] .= <<<EOT
              id="$tagId" name="$radioButtonName" value="$tagId">
              <label for="$tagId" id="tag$tagId" class="tag">
                <span class="tagText">$tagText</span>
              </label>

EOT;
        } else {
            $tagString[0] .= '<input type="checkbox" ';
            if ($tagSelected) {
                $tagString[0] .= 'checked="checked" ';
            }
            $tagString[0] .= <<<EOT
              id="$tagId" name="$tagId" value="$tagId">
              <label for="$tagId" id="tag$tagId" class="tag">
                <span class="tagText">$tagText</span>
              </label>

EOT;
        }
        if (!empty($tagTooltip) && !empty($tagTooltipImage)) {
            if ($tagTooltipImageWidth < 400) {
                $maxTagTooltipImageWidth = ($tagTooltipImageWidth + 18) . 'px';
            } else {
                $maxTagTooltipImageWidth = '418px';
            }
            $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({
                content: '<div class="tagToolTip"><img src="images/projects/$projectId/tooltips/$tagTooltipImage" height="$tagTooltipImageHeight" width="$tagTooltipImageWidth" /><p>$tagTooltip</p></div>',
                maxWidth: '$maxTagTooltipImageWidth'
            });

EOT;
        } elseif (!empty($tagTooltip)) {
            $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({
                content: '<div class="tagToolTip"><p>$tagTooltip</p></div>'
            });

EOT;
        } elseif (!empty($tagTooltipImage)) {
            $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({
                content: '<div class="tagToolTip"><img src="images/projects/$projectId/tooltips/$tagTooltipImage"' +
                    'height="$tagTooltipImageHeight" width="$tagTooltipImageWidth" /></div>',
                maxWidth: '$maxTagTooltipImageWidth'
            });

EOT;
        }
    }
    $tagString[0] .= <<<EOT
          </div>

EOT;
    return $tagString;
}

//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
// Define required files and initial includes
// Define variables and PHP settings
$revision = 0;
$postImageId = $_GET['imageId'];
If (isset($_GET['preImageId'])) {
    $preImageId = $_GET['preImageId'];
    $specifiedPreImage = 1;
}

// Find match data $imageMatchData
$imageMatchData = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $postImageId);
$computerMatchImageId = $imageMatchData['pre_image_id'];
if (!isset($preImageId)) {
    $preImageId = $imageMatchData['pre_image_id'];
}

//--------------------------------------------------------------------------------------------------
// Determine if the user has already annotated the displayed image
$annotationExistsQuery = "SELECT * FROM annotations WHERE user_id = :userId AND "
        . "project_id = :projectId AND image_id = :postImageId";
$annotationExistsParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$STH = run_prepared_query($DBH, $annotationExistsQuery, $annotationExistsParams);
$existingAnnotation = $STH->fetch(PDO::FETCH_ASSOC);
if ($existingAnnotation) {
    if (!is_null($existingAnnotation['user_match_id'])) {
        if ($preImageId != $existingAnnotation['user_match_id'] && !isset($specifiedPreImage)) {
            header("location: classification.php?&projectId=$projectId&imageId=$postImageId&preImageId={$existingAnnotation['user_match_id']}");
        }
    }
}

// Find post image metadata $postImageMetadata
if (!$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image')) {
//  Placeholder for error management
    exit("Image $postImageId not found in Database");
}
$postImageLatitude = $postImageMetadata['latitude'];
$postImageLongitude = $postImageMetadata['longitude'];
$postImageLocation = build_image_location_string($postImageMetadata, TRUE);

// Find pre image metadata $preImageMetadata
if (!$preImageMetadata = retrieve_entity_metadata($DBH, $preImageId, 'image')) {
//  Placeholder for error management
    exit("Image $preImageId not found in Database");
}

If (isset($_GET['sessId'])) {
    $annotationSessionId = $_GET['sessId'];
} else {
    $annotationSessionId = (md5(time()));
}

$annotationMetaDataQueryString = "&annotationSessionId=$annotationSessionId&userId=$userId"
        . "&authCheckCode=$authCheckCode&projectId=$projectId&postImageId=$postImageId";





//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
// Build headers for images
$postDetailedImageURL = $postImageMetadata['full_url'];
$postDisplayImageURL = "images/collections/{$postImageMetadata['collection_id']}/main/{$postImageMetadata['filename']}";
$postImageTitle = build_image_header($postImageMetadata, $projectMetadata['post_image_header']);
$postImageAltTagHTML = "An oblique image of the $postImageLocation coastline.";

$preDetailedImageURL = $preImageMetadata['full_url'];
$preDisplayImageURL = "images/collections/{$preImageMetadata['collection_id']}/main/{$preImageMetadata['filename']}";
$preImageTitle = build_image_header($preImageMetadata, $projectMetadata['pre_image_header']);
$preImageAltTagHTML = "An oblique image of the " . build_image_location_string($preImageMetadata, TRUE) . " coastline.";



//--------------------------------------------------------------------------------------------------
// Build an array of selected tags in any existing annotation
if ($existingAnnotation) {
    $existingTags = array();
    $existingComments = array();

    $tagSelectionQuery = "SELECT * FROM annotation_selections "
            . "WHERE annotation_id = :annotationId";
    $tagSelectionParams['annotationId'] = $existingAnnotation['annotation_id'];
    $STH = run_prepared_query($DBH, $tagSelectionQuery, $tagSelectionParams);

    while ($existingSelection = $STH->fetch(PDO::FETCH_ASSOC)) {
        $existingTags[] = $existingSelection['tag_id'];
    }

    $tagCommentQuery = "SELECT * FROM annotation_comments"
            . " WHERE annotation_id = {$existingAnnotation['annotation_id']}";
    $tagCommentParams['annotationId'] = $existingAnnotation['annotation_id'];
    $STH = run_prepared_query($DBH, $tagCommentQuery, $tagCommentParams);

    while ($existingComment = $STH->fetch(PDO::FETCH_ASSOC)) {
        $existingComments[$existingComment['tag_id']] = $existingComment['comment'];
    }
}


//--------------------------------------------------------------------------------------------------
// Build an array of the tasks.
$taskMetadataQuery = "SELECT * from task_metadata WHERE project_id = :projectId
  ORDER BY order_in_project";
$taskMetadataParams['projectId'] = $projectId;
$STH = run_prepared_query($DBH, $taskMetadataQuery, $taskMetadataParams);
$taskMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
if (count($taskMetadata) > 0) {
    foreach ($taskMetadata as $task) {
        if ($task['is_enabled'] == 0) {
            continue;
        }
        $taskId = $task['task_id'];
        $annotations[$taskId] = array(
            'title' => $task['display_title'],
            'groups' => array()
        );

        $taskContentsQuery = "SELECT tag_group_id FROM task_contents WHERE task_id = :taskId
      ORDER BY order_in_task";
        $taskContentsParams['taskId'] = $taskId;
        $STH = run_prepared_query($DBH, $taskContentsQuery, $taskContentsParams);
        $taskContents = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($taskContents) > 0) {
            foreach ($taskContents as $tagGroupIdArray) {
                $tagGroupId = $tagGroupIdArray['tag_group_id'];
                $tagGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE tag_group_id = :tagGroupId
            AND project_id = :projectId";
                $tagGroupMetadataParams = array(
                    'tagGroupId' => $tagGroupId,
                    'projectId' => $projectId
                );
                $STH = run_prepared_query($DBH, $tagGroupMetadataQuery, $tagGroupMetadataParams);
                $tagGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                if (count($tagGroupMetadata) == 1) {
                    if ($tagGroupMetadata[0]['is_enabled'] == 0) {
                        continue;
                    }
                    $annotations[$taskId]['groups'][$tagGroupId] = array(
                        'text' => $tagGroupMetadata[0]['display_text'],
                        'border' => $tagGroupMetadata[0]['has_border'],
                        'color' => $tagGroupMetadata[0]['has_color'],
                        'forceWidth' => $tagGroupMetadata[0]['force_width'],
                        'groups' => array(),
                        'tags' => array()
                    );



                    if ($tagGroupMetadata[0]['contains_groups'] == 1) {
                        $groupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :tagGroupId ORDER BY order_in_group";
                        $groupContentsParams['tagGroupId'] = $tagGroupId;
                        $STH = run_prepared_query($DBH, $groupContentsQuery, $groupContentsParams);
                        $groupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                        if (count($groupContents) > 0) {
                            foreach ($groupContents as $groupContentsArray) {
                                $groupGroupId = $groupContentsArray['tag_id'];
                                $groupGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE
                  tag_group_id = :groupGroupId AND project_id = :projectId";
                                $groupGroupMetadataParams = array(
                                    'groupGroupId' => $groupGroupId,
                                    'projectId' => $projectId
                                );
                                $STH = run_prepared_query($DBH, $groupGroupMetadataQuery, $groupGroupMetadataParams);
                                $groupGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                if (count($groupGroupMetadata) == 1) {
                                    if ($groupGroupMetadata[0]['is_enabled'] == 0) {
                                        continue;
                                    }
                                    $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = array(
                                        'text' => $groupGroupMetadata[0]['display_text'],
                                        'border' => $groupGroupMetadata[0]['has_border'],
                                        'color' => $groupGroupMetadata[0]['has_color'],
                                        'forceWidth' => $groupGroupMetadata[0]['force_width'],
                                        'tags' => array()
                                    );
                                    $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :groupGroupId ORDER BY order_in_group";
                                    $tagGroupContentsParams = array();
                                    $tagGroupContentsParams['groupGroupId'] = $groupGroupId;
                                    $STH = run_prepared_query($DBH, $tagGroupContentsQuery, $tagGroupContentsParams);
                                    $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                                    if (count($tagGroupContents) > 0) {
                                        foreach ($tagGroupContents as $tagIdArray) {
                                            $tagId = $tagIdArray['tag_id'];
                                            $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                        project_id = :projectId";
                                            $tagMetadataParams = array();
                                            $tagMetadataParams = array(
                                                'tagId' => $tagId,
                                                'projectId' => $projectId
                                            );
                                            $STH = run_prepared_query($DBH, $tagMetadataQuery, $tagMetadataParams);
                                            $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                            if (count($tagMetadata) == 1) {
                                                if ($tagMetadata[0]['is_enabled'] == 0) {
                                                    continue;
                                                }
                                                $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                        ['tags'][$tagId] = array(
                                                    'id' => $tagMetadata[0]['tag_id'],
                                                    'comment' => $tagMetadata[0]['is_comment_box'],
                                                    'radio' => $tagMetadata[0]['is_radio_button'],
                                                    'radioGroup' => $tagMetadata[0]['radio_button_group'],
                                                    'text' => $tagMetadata[0]['display_text'],
                                                    'tooltipText' => $tagMetadata[0]['tooltip_text'],
                                                    'tooltipImage' => $tagMetadata[0]['tooltip_image'],
                                                    'tooltipImageWidth' => $tagMetadata[0]['tooltip_image_width'],
                                                    'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                                    'userSelected' => FALSE
                                                );
                                                if ($existingAnnotation) {
                                                    if ($tagMetadata[0]['is_comment_box'] == TRUE) {
                                                        if (array_key_exists($tagId, $existingComments)) {
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                                    ['tags'][$tagId]['userSelected'] = TRUE;
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                                    ['tags'][$tagId]['userComment'] = $existingComments[$tagId];
                                                        }
                                                    } else {
                                                        $key = array_search($tagId, $existingTags);
                                                        if ($key !== FALSE) {
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                                    ['tags'][$tagId]['userSelected'] = TRUE;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
          tag_group_id = :tagGroupId ORDER BY order_in_group";
                        $tagGroupContentsParams = array();
                        $tagGroupContentsParams['tagGroupId'] = $tagGroupId;
                        $STH = run_prepared_query($DBH, $tagGroupContentsQuery, $tagGroupContentsParams);
                        $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                        if (count($tagGroupContents) > 0) {
                            foreach ($tagGroupContents as $tagIdArray) {
                                $tagId = $tagIdArray['tag_id'];
                                $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                project_id = :projectId";
                                $tagMetadataParams = array();
                                $tagMetadataParams = array(
                                    'tagId' => $tagId,
                                    'projectId' => $projectId
                                );
                                $STH = run_prepared_query($DBH, $tagMetadataQuery, $tagMetadataParams);
                                $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                if (count($tagMetadata) == 1) {
                                    if ($tagMetadata[0]['is_enabled'] == 0) {
                                        continue;
                                    }
                                    $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = array(
                                        'id' => $tagMetadata[0]['tag_id'],
                                        'comment' => $tagMetadata[0]['is_comment_box'],
                                        'radio' => $tagMetadata[0]['is_radio_button'],
                                        'radioGroup' => $tagMetadata[0]['radio_button_group'],
                                        'text' => $tagMetadata[0]['display_text'],
                                        'tooltipText' => $tagMetadata[0]['tooltip_text'],
                                        'tooltipImage' => $tagMetadata[0]['tooltip_image'],
                                        'tooltipImageWidth' => $tagMetadata[0]['tooltip_image_width'],
                                        'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                        'userSelected' => FALSE
                                    );
                                    if ($existingAnnotation) {
                                        if ($tagMetadata[0]['is_comment_box'] == TRUE) {
                                            if (array_key_exists($tagId, $existingComments)) {
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                        ['userSelected'] = TRUE;
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                        ['userComment'] = $existingComments[$tagId];
                                            }
                                        } else {
                                            $key = array_search($tagId, $existingTags);
                                            if ($key !== FALSE) {
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                        ['userSelected'] = TRUE;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}



//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
// Build Task Navigation Buttons
$taskCount = count($annotations);
$progressTrackerItems = '';
for ($i = 1; $i <= $taskCount; $i++) {
    $progressTrackerItems .= <<<EOT
                <div id="progressTrackerItem{$i}" class="progressTrackerItem">
                  <p id="progressTrackerItem{$i}Content">{$i}</p>
                </div>

EOT;
}



//--------------------------------------------------------------------------------------------------
// Build thumbnail data.
$thumbnailArray = find_adjacent_images($DBH, $computerMatchImageId, NULL, NULL, 3, 20);

//--------------------------------------------------------------------------------------------------
// Build thumbnail HTML
$thumbnailHtml = '';
$noMatchThumbnailHTML = '';
$jsThumbnailMapScript = '';
$navThumbnailTitle = "Click this thumbnail to see if this pre-storm photo along the coast better matches the post-storm photo on the right. Is this a better match than what the computer found?";
$currentImageTitle = "This thumbnail is the pre-storm photo currently displayed above. If this pre-storm photo best matches the post-storm photo on the left, then click the Confirm Match button.";
$computerMatchTitle = "This thumbnail is the closest pre-storm photo the computer found. Can you find a better match?";
$noMoreImagesTitle = "You have reached the end of this collection. There are no more images to display in this direction.";



for ($i = 0; $i < count($thumbnailArray); $i++) {
    if ($thumbnailArray[$i]['image_id'] != 0) {

        $noMatchThumbnailHTML .= <<<EOL
            <div class="matchPhotoWrapper">
                <img src="{$thumbnailArray[$i]['thumb_url']}" height="72" width="108">
            </div>
EOL;

        $locationString = build_image_location_string($thumbnailArray[$i], TRUE);
        $thumbnailClass = '';
        $thumbnailText = '';
        if ($thumbnailArray[$i]['image_id'] == $computerMatchImageId && $thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id']) {
            $thumbnailClass = 'selectedMatch';
            $thumbnailText = 'Computer Match &amp; Currently Displayed Photo';
            $jsThumbnailMapScript .= <<<EOL
                thumbnail{$i}Marker = L.marker(L.latLng({$thumbnailArray[$i]['latitude']}, {$thumbnailArray[$i]['longitude']}),
                {
                    clickable: false,
                    icon: blueMarker
                });
                thumbnailLayer.addLayer(thumbnail{$i}Marker);

EOL;
        } else if ($thumbnailArray[$i]['image_id'] == $computerMatchImageId) {
            $thumbnailClass = 'computerMatch';
            $thumbnailText = 'Computer Match';
            $jsThumbnailMapScript .= <<<EOL
                thumbnail{$i}Marker = L.marker(L.latLng({$thumbnailArray[$i]['latitude']}, {$thumbnailArray[$i]['longitude']}),
                {
                    icon: greenMarker
                });
                thumbnail{$i}Marker.on('click', function() {
                    window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i]['image_id']}&sessId=$annotationSessionId");
                });
                thumbnailLayer.addLayer(thumbnail{$i}Marker);

EOL;
        } else if ($thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id']) {
            $thumbnailClass = 'selectedMatch';
            $thumbnailText = 'Currently Displayed Photo';
            $jsThumbnailMapScript .= <<<EOL
                thumbnail{$i}Marker = L.marker(L.latLng({$thumbnailArray[$i]['latitude']}, {$thumbnailArray[$i]['longitude']}),
                {
                    clickable: false,
                    icon: blueMarker
                });
                thumbnailLayer.addLayer(thumbnail{$i}Marker);

EOL;
        } else {
            $jsThumbnailMapScript .= <<<EOL
                thumbnail{$i}Marker = L.marker(L.latLng({$thumbnailArray[$i]['latitude']}, {$thumbnailArray[$i]['longitude']}),
                {
                    icon: yellowMarker
                });
                thumbnail{$i}Marker.on('click', function() {
                    window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i]['image_id']}&sessId=$annotationSessionId");
                });
                thumbnailLayer.addLayer(thumbnail{$i}Marker);

EOL;
        }

        $thumbnailHtml .= <<<EOL
            <div class="navThumbnailWrapper">
                <a href="classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i]['image_id']}&sessId=$annotationSessionId">
                    <img id="thumbnail$i" class="$thumbnailClass" src="{$thumbnailArray[$i]['thumb_url']}" alt="An oblique image of the $locationString coastline.">
                </a>
                <p>$thumbnailText</p>
            </div>
EOL;

        if ($thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id']) {
            if ($i != 0 && $thumbnailArray[$i - 1]['image_id'] != 0) {
                $jQueryDocumentDotReadyCode .= <<<EOL
            $('#leftButton').click(function() {
                window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i - 1]['image_id']}&sessId=$annotationSessionId");
           });

EOL;
            } else {
                $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#leftButton').addClass('disabledClickableButton');
                    $('#leftButton').attr('disabled', 'disabled');

EOL;
            }
            if ($i != (count($thumbnailArray) - 1) && $thumbnailArray[$i + 1]['image_id'] != 0) {
                $jQueryDocumentDotReadyCode .= <<<EOL
            $('#rightButton').click(function() {
                window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i + 1]['image_id']}&sessId=$annotationSessionId");
           });

EOL;
            } else {
                $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#rightButton').addClass('disabledClickableButton');
                    $('#rightButton').attr('disabled', 'disabled');

EOL;
            }
        }
    } else {

    }
}

//--------------------------------------------------------------------------------------------------
// Build the tasks html string from the tasks array.
$taskHtmlString = "";
$tagJavaScriptString = "";
$taskCounter = 0;
foreach ($annotations as $task) {
    $taskCounter++;
    $taskTitle = $task['title'];
    $groups = $task['groups'];
    $hiddenDataHTML = '';
    if ($taskCounter == 1) {
        $hiddenDataHTML = '<input type="hidden" name="preImageId" value="' . $preImageId . '">';
    }
    $taskHtmlString .= <<<EOT
        <div id = "task$taskCounter" class="task">
          <h1 id = "task{$taskCounter}Header">$taskTitle</h1>
          <form class="annotationForm" method="post" action="classification.php">
            $hiddenDataHTML
            <div class="groupWrapper">

EOT;

    foreach ($groups as $group) {
        $groupText = $group['text'];
        $groupBorder = $group['border'];
        $groupColor = $group['color'];
        $groupGroups = $group['groups'];
        $tags = $group['tags'];
        $forceWidth = $group['forceWidth'];
        $borderHtml = '';
        $colorHtml = '';
        $widthHtml = '';
        $widthClass = '';
        if (!empty($groupBorder)) {
            $borderHtml = "border-style:solid; border-width: 2px;";
        }
        if (!empty($groupColor)) {
            $colorHtml = "background-color: #$groupColor; ";
        }
        if (!empty($forceWidth)) {
            $widthHtml = "width: {$forceWidth}px; ";
            $widthClass = "forceWidth";
        }

        if (count($groupGroups) > 0) {
            $taskHtmlString .= <<<EOT
              <div style="$borderHtml $colorHtml" class="annotationGroup">
                <h2 class="groupText">$groupText</h2>

EOT;
            foreach ($groupGroups as $subGroup) {
                $subGroupText = $subGroup['text'];
                $subGroupBorder = $subGroup['border'];
                $subGroupColor = $subGroup['color'];
                $tags = $subGroup['tags'];
                $forceWidth = $subGroup['forceWidth'];
                $borderHtml = '';
                $colorHtml = '';
                $widthHtml = '';
                $widthClass = '';
                if (!empty($subGroupBorder)) {
                    $borderHtml = "border-style:solid; border-width: 3px;";
                }
                if (!empty($subGroupColor)) {
                    $colorHtml = "background-color: #$subGroupColor; ";
                }
                if (!empty($forceWidth)) {
                    $widthHtml = "width: {$forceWidth}px; ";
                    $widthClass = "forceWidth";
                }
                if (count($tags) > 1) {
                    reset($tags);
                    $first_key = key($tags);
                    if ($tags[$first_key]['radio'] == true) {
                        $subGroupText .= '<br><span class="tagInstruction">(choose one)</span>';
                    } elseif ($tags[$first_key]['comment'] == false && $tags[$first_key]['radio'] == false) {
                        $subGroupText .= '<br><span class="tagInstruction">(choose any)</span>';
                    }
                }

                $taskHtmlString .= <<<EOT
                <div style="$borderHtml $colorHtml $widthHtml" class="annotationSubgroup $widthClass">
                  <h3 class="subGroupText">$subGroupText</h3>

EOT;
                $tagCode = build_group_contents($projectId, $tags);
                $tagJavaScriptString .= $tagCode[1];
                $taskHtmlString .= $tagCode[0];
                $taskHtmlString .= <<<EOT
               </div>

EOT;
            }
        } else {
            if (count($tags) > 1) {
                reset($tags);
                $first_key = key($tags);
                if ($tags[$first_key]['radio'] == true) {
                    $groupText .= '<br><span class="tagInstruction">(choose one)</span>';
                } elseif ($tags[$first_key]['comment'] == false && $tags[$first_key]['radio'] == false) {
                    $groupText .= '<br><span class="tagInstruction">(choose any)</span>';
                }
            }
            $taskHtmlString .= <<<EOT
              <div style="$borderHtml $colorHtml $widthHtml" class="annotationGroup $widthClass">
                <h2 class="groupText">$groupText</h2>

EOT;
            $tagCode = build_group_contents($projectId, $tags);
            $tagJavaScriptString .= $tagCode[1];
            $taskHtmlString .= $tagCode[0];
        }
        $taskHtmlString .= <<<EOT
             </div>

EOT;
    }
    $taskHtmlString .= <<<EOT
            </div>
            <div class="annotationControls">

EOT;
    If ($taskCounter > 1) {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}PreviousButton" class="clickableButton" type="button" value="PREVIOUS TASK" title="Click to go to the PREVIOUS task.">

EOT;
    } else {
        $taskHtmlString .= <<<EOT
              <input id="returnToMatchingButton" class="clickableButton" type="button" value="RETURN TO PHOTO MATCHING" title="">

EOT;
    }

    if ($taskCounter < count($annotations)) {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}NextButton" class="clickableButton" type="button" value="NEXT TASK" title="Click to go to the NEXT task.">

EOT;
    } else {
        $taskHtmlString .= <<<EOT
              <input id="submitButton" class="clickableButton" type="button" value="DONE" title="Click once you have completed annotating this image set and are happy with your selections.">

EOT;
    }
    $taskHtmlString .= <<<EOT
            </div>
          </form>
        </div>

EOT;
}


$javaScript = <<<EOL
    icDisplayedTask = 0;
    var map = null;
    var postImageAspectRatio = {$postImageMetadata['display_image_height']} / {$postImageMetadata['display_image_width']};
    var preImageAspectRatio = {$preImageMetadata['display_image_height']} / {$preImageMetadata['display_image_width']};
    var postImageLatLon = null;
    var projectId = $projectId;
    var thumbnail0Marker;
    var thumbnail1Marker;
    var thumbnail2Marker;
    var thumbnail3Marker;
    var thumbnail4Marker;
    var thumbnail5Marker;
    var thumbnail6Marker;
    var thumbnailLayer = L.featureGroup();

    function initializeMaps() {
        postImageLatLon = L.latLng($postImageLatitude, $postImageLongitude);

        map = L.map("mapInsert").setView(postImageLatLon, 17);
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
        var yellowMarker = L.icon({
            iconUrl: 'images/system/yellowMarker.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [0, -35]
        });
        var blueMarker = L.icon({
            iconUrl: 'images/system/blueMarker.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [0, -35]
        });

        postImageMarker = L.marker(postImageLatLon,
        {
            clickable: false,
            icon: redMarker,
            zIndexOffset: 10000
        }).addTo(map);
        $jsThumbnailMapScript
        map.addLayer(thumbnailLayer);
    }

    function hideLoader(isPost) {
        if (isPost) {
            $('#postImageZoomLoadingIndicator').hide();
        } else {
            $('#preImageZoomLoadingIndicator').hide();
        }
    }

    function dynamicSizing(icDisplayedTask) {


        // Resize annotation groups to window width
        if (icDisplayedTask > 0) {
            var taskWidth = $('#task' + icDisplayedTask).width();
            var numberOfGroups = icTaskMap[icDisplayedTask];
            if (numberOfGroups > 0) {
                var groupWidth = (taskWidth / (numberOfGroups)) - 51;
            }
            var subGroups = document
                    .getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
            for (var i = 0; i < subGroups.length; i++) {
                var childNumber = i + 2;
                if (!$('#task' + icDisplayedTask +
                        ' .annotationSubgroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
                    var groupMinWidth = $('#task' + icDisplayedTask +
                            ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
                    if (groupWidth > groupMinWidth && subGroups[i].borderWidth === 0) {
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .width(groupWidth);
                    } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .width(parseInt(groupMinWidth) + 60);
                    } else {
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .width(parseInt(groupMinWidth) + 1);
                    }
                }
            }
            for (var i = 0; i < 5; i++) {
                var childNumber = i + 1;
                if ($('#task' + icDisplayedTask +
                        ' .annotationGroup:nth-child(' + childNumber + ')').length !== 0) {
                    if (!$('#task' + icDisplayedTask +
                            ' .annotationGroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
                        var groupMinWidth = $('#task' + icDisplayedTask +
                                ' .annotationGroup:nth-child(' + childNumber + ')')
                                .css('min-width').replace('px', '');
                        var borderWidth =
                                $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')')
                                .css('border-left-width').replace('px', '');
                        if (groupWidth > groupMinWidth && parseInt(borderWidth) === 0) {
                            $('#task' + icDisplayedTask +
                                    ' .annotationGroup:nth-child(' + childNumber + ')').width(groupWidth);
                        } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
                            $('#task' + icDisplayedTask +
                                    ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .width(parseInt(groupMinWidth) + 60);
                        } else {
                            $('#task' + icDisplayedTask +
                                    ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .width(parseInt(groupMinWidth) + 1);
                        }
                    }
                }
            }


            // Set group header heights to the same across the board
            $('.groupText, .subGroupText').show();
            $('.groupWrapper h2, .groupWrapper h3').height("");
            var subGroups = document.getElementById('task' + icDisplayedTask)
                    .getElementsByClassName('annotationSubgroup');
            var maxHeaderHeight = 0;
            for (var i = 0; i < subGroups.length; i++) {
                var childNumber = i + 2;
                var headerHeight = $('#task' + icDisplayedTask +
                        ' .annotationSubgroup:nth-child(' + childNumber + ') h3').height();
                if (headerHeight > maxHeaderHeight) {
                    maxHeaderHeight = headerHeight;
                }
            }
            $('#task' + icDisplayedTask + ' h3').height(maxHeaderHeight);
            var maxHeaderHeight = 0;
            for (var i = 0; i < 5; i++) {
                var childNumber = i + 1;
                var headerHeight = $('#task' + icDisplayedTask +
                        ' .annotationGroup:nth-child(' + childNumber + ') h2').height();
                if (headerHeight > maxHeaderHeight) {
                    maxHeaderHeight = headerHeight;
                }
            }
            $('#task' + icDisplayedTask + ' h2').height(maxHeaderHeight);
        }

        // Size Div over map to match push map down level with the images.
        var maxHeaderInnerHeight = 0
        var maxHeaderOuterHeight = 0
        $('.imageColumnTitle').height('auto');
        $('.imageColumnTitle').each(function() {

            if ($(this).height() > maxHeaderInnerHeight) {
                maxHeaderInnerHeight = $(this).height()
            }
            if ($(this).outerHeight() > maxHeaderOuterHeight) {
                maxHeaderOuterHeight = $(this).outerHeight()
            }
        });
        $('.imageColumnTitle').css('height', maxHeaderInnerHeight);






        // Determine size the page and its elements
        $('html').css('overflow', 'hidden');
        var bodyHeight = $('body').height();
        var bodyWidth = $('#classificationWrapper').width();
        $('html').css('overflow', 'auto');

        var annotationHeight = $('#annotationWrapper').outerHeight();

        // 30px from header, 1 to account for browser pixel rounding, 15 for image div padding top and bottom
        var maxImageHeightByY = bodyHeight - 30 - 1 - maxHeaderOuterHeight - 15 - annotationHeight;
        var maxImageWidthByX = (bodyWidth * 0.43) - 10;


        // Calculate max post image size limits
        var maxPostImageWidthByY = maxImageHeightByY / postImageAspectRatio;
        var maxPostImageHeightByX = maxImageWidthByX * postImageAspectRatio;

        if (maxPostImageWidthByY < maxImageWidthByX) {
            maxPostImageWidth = Math.floor(maxPostImageWidthByY);
            maxPostImageHeight = Math.floor(maxImageHeightByY);
        } else {
            maxPostImageWidth = Math.floor(maxImageWidthByX);
            maxPostImageHeight = Math.floor(maxPostImageHeightByX);
        }
        if (maxPostImageWidth > 800) {
            maxPostImageWidth = 800;
        }

        // Calculate max pre image size limits
        var maxPreImageWidthByY = maxImageHeightByY / preImageAspectRatio;
        var maxPreImageHeightByX = maxImageWidthByX * preImageAspectRatio;

        if (maxPreImageWidthByY < maxImageWidthByX) {
            maxPreImageWidth = Math.floor(maxPreImageWidthByY);
            maxPreImageHeight = Math.floor(maxImageHeightByY);
        } else {
            maxPreImageWidth = Math.floor(maxImageWidthByX);
            maxPreImageHeight = Math.floor(maxPreImageHeightByX);
        }
        if (maxPreImageWidth > 800) {
            maxPreImageWidth = 800;
        }

        var maxImageHeight;
        var preImageCenteringMargin = 0;
        var postImageCenteringMargin = 0;
        if (maxPostImageHeight > maxPreImageHeight) {
            maxImageHeight = maxPostImageHeight;
            preImageCenteringMargin = Math.floor((maxPostImageHeight - maxPreImageHeight) / 2);
        } else {
            maxImageHeight = maxPreImageHeight;
            postImageCenteringMargin = Math.floor((maxPreImageHeight - maxPostImageHeight) / 2);
        }

        $('#postImageColumnContent').css({
            'max-width': maxPostImageWidth + 'px',
            'max-height': maxPostImageHeight + 'px',
            'margin-top': postImageCenteringMargin + 'px'
            });
        $('#preImageColumnContent').css({
            'max-width': maxPreImageWidth + 'px',
            'max-height': maxPreImageHeight + 'px',
            'margin-top': preImageCenteringMargin + 'px'
            });
        $('#mapInsert').css('height', maxImageHeight + 'px');
        map.invalidateSize();

        $('.zoomContainer').remove();
        $('#postImage').elevateZoom({
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
        $('#preImage').elevateZoom({
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
                hideLoader(false);
            }
        });

        $(window).scrollTop($('#navigationBar').position().top);

    } // End dynamicSizing

    function iCoastTitle() {
        if ($(window).width() > ($('#navigationBar ul').outerWidth() + $('#navigationBar>p').outerWidth())) {
            if ($(window).scrollTop() > $('#usgstitle').position().top && $('#navigationBar>p').length == 0) {
                $('#navigationBar').append('<p>USGS iCoast - Did the Coast Change?</p>');
            } else if ($(window).scrollTop() < $('#usgstitle').position().top && $('#navigationBar>p').length) {
                $('#navigationBar>p').remove();
            }
        } else {
            if ($('#navigationBar>p').length) {
                $('#navigationBar>p').remove();
            }
        }
    }

    function photoFeedbackConfirmation() {
        window.location.href = 'classification.php?projectId=' + projectId;
    }

    $(window).bind("load", function() {
        dynamicSizing(icDisplayedTask);
    });
EOL;

$jQueryDocumentDotReadyCode .= <<<EOT

    $('#flagButton').click(function() {
        $('#popupWrapperParent').css('display', 'table');
        $('#unsuitable').show();
    });

    $('#noMatchButton').click(function() {
        $('#popupWrapperParent').css('display', 'table');
        $('#noMatch').show();
    });


    $('.cancelPopup').click(function() {
        $('#popupWrapperParent').css('display', 'none');
        $('#unsuitable, #noMatch').hide();
    });

    $('#confirmUnsuitable').click(function() {
        photoFeedbackData = {
            'eventType': 4,
            'eventText': 'Image {$postImageMetadata['image_id']} taken near $postImageLocation has been flagged as UNSUITABE for use in iCoast',
            'eventSummary': 'User Feedback',
            'userId': $userId,
            'url': '$url',
            'queryString': '$queryString',
            'postData': '$postData',
            'clientAgent': '$clientAgent'
        };
        $.post('ajax/eventLogger.php', photoFeedbackData, photoFeedbackConfirmation());
    });

    $('#confirmNoMatch').click(function() {
        photoFeedbackData = {
            'eventType': 4,
            'eventText': 'Image {$postImageMetadata['image_id']} taken near $postImageLocation has been flagged as NOT HAVING A VALID MATCH for use in iCoast',
            'eventSummary': 'User Feedback',
            'userId': $userId,
            'url': '$url',
            'queryString': '$queryString',
            'postData': '$postData',
            'clientAgent': '$clientAgent'
        };
        $.post('ajax/eventLogger.php', photoFeedbackData, photoFeedbackConfirmation());
    });

    $('#startTaggingButton').click(function() {
        var formData = $('.annotationForm').serialize();
        
        formData += '$annotationMetaDataQueryString';
        console.log(formData);
        $.post('ajax/annotationLogger.php', formData);

        $('#photoMatchingWrapper').css('display', 'none');
        $('.imageWrapper').css({
            'border': 'none',
            'box-shadow': 'none',
            'border-radius': '0',
            'padding': '3px'
             });
        $('#preImage, #postImage').css('box-shadow', '#666666 5px 5px 5px');
        map.removeLayer(thumbnailLayer);
        map.setView(postImageLatLon, 12);


        $('#taskWrapper').css('display', 'block');
        $('#progressTrackerItem1').addClass('currentProgressTrackerItem');
        $('#progressTrackerItem1Content').css('display', 'inline');
        $('#task1Header').css('display', 'block');
        $('#task1').css('display', 'block');
        icDisplayedTask = 1;
        setMinGroupHeaderWidth(icDisplayedTask);
    });


    $('#returnToMatchingButton').click(function() {
        var formData = $('.annotationForm').serialize();
        formData += '$annotationMetaDataQueryString';
        console.log(formData);
        $.post('ajax/annotationLogger.php', formData);

        $('#taskWrapper').css('display', 'none');
        $('#task1').css('display', 'none');
        $('#progressTrackerItem1').removeClass('currentProgressTrackerItem');
        $('#progressTrackerItem1Content').css('display', 'none');
        $('#task1Header').css('display', 'none');

        $('#preImage, #postImage').css('box-shadow', 'none');
        $('#photoMatchingWrapper').css('display', 'block');
        $('.imageWrapper').css({
            'box-shadow': '#666666 5px 5px 5px',
            'border-radius': '8px',
            'padding': '0'
             });

        $('#preImageWrapper').css('border', '3px solid #5384ed');
        $('#postImageWrapper').css('border', '3px solid #e56969');
        map.addLayer(thumbnailLayer);
        map.fitBounds(thumbnailLayer.getBounds(), {'padding': [25 ,10]});


        icDisplayedTask = 0;

        dynamicSizing(icDisplayedTask);

    });

EOT;



// Annotation Navigation Buttons
for ($i = 1; $i <= $taskCount; $i++) {
    $h = $i == 1 ? $h = 1 : $i - 1;
    $j = $i + 1;

// Annotation Next Navigation Button
    if ($i < $taskCount) {

        if ($i == 1) {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}NextButton').click(function() {
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  console.log(formData);
                  $.post('ajax/annotationLogger.php', formData);
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
                  $('#task{$j}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'none');
                  icDisplayedTask++;
                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        } else {

            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}NextButton').click(function() {
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  console.log(formData);
                  $.post('ajax/annotationLogger.php', formData);
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
                  $('#task{$j}Header').css('display', 'block');
                  icDisplayedTask++;
                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        }
    }

// Annotation Previous Navigation Button
    if ($i > 1) {

        if ($i == 2) {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}PreviousButton').click(function() {
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  console.log(formData);
                  $.post('ajax/annotationLogger.php', formData);
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
                  $('#task{$h}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'block');
                  icDisplayedTask--;
                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        } else {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}PreviousButton').click(function() {
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  console.log(formData);
                  $.post('ajax/annotationLogger.php', formData);
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
                  $('#task{$h}Header').css('display', 'block');
                  icDisplayedTask--;
                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        }
    }
}
$jQueryDocumentDotReadyCode .= <<<EOT
    $('#submitButton').click(function() {
      var formData = $('.annotationForm').serialize();
      formData += '$annotationMetaDataQueryString';
      formData += '&annotationComplete=1';
      console.log(formData);
      $.post('ajax/annotationLogger.php', formData, function() {
        window.location.href = 'complete.php?projectId=$projectId&imageId=$postImageId';
      });
    });

EOT;

$jQueryDocumentDotReadyCode .= "icTaskMap = new Array();\n";
$jsTaskCount = 1;
foreach ($annotations as $task) {
    $jsGroupCount = 0;
    if (count($task['groups']) > 0) {
        foreach ($task['groups'] as $group) {
            if (count($group['groups']) > 0) {
                $jsGroupCount += count($group['groups']);
            } else {
                $jsGroupCount++;
            }
        }
        $jQueryDocumentDotReadyCode .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
        $jsGroupCount = 0;
    } else {
        $jQueryDocumentDotReadyCode .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
    }
    $jsTaskCount++;
}

$jQueryDocumentDotReadyCode .= <<<EOL
    function setMinGroupHeaderWidth(icDisplayedTask) {
         $('.groupText, .subGroupText').hide();
         var subGroups = document.getElementById('task' + icDisplayedTask)
                 .getElementsByClassName('annotationSubgroup');
         for (var i = 0; i < subGroups.length; i++) {
             var forceWidthSetting = null;
             var childNumber = i + 2;
             if ($('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                     .hasClass('forceWidth')) {
                 forceWidthSetting = $('#task' + icDisplayedTask +
                         ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
             }
             $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                     .css('width', 'auto');
             $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                     .css('min-width', '');
             var subGroupWidth = $('#task' + icDisplayedTask +
                     ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
             $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                     .css('min-width', subGroupWidth);
             if (forceWidthSetting !== null) {
                 $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                         .width(forceWidthSetting);
             }
         }
         for (var i = 0; i < 5; i++) {
             var forceWidthSetting = null;
             var childNumber = i + 1;
             if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                     .length !== 0) {
                 if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                         .hasClass('forceWidth')) {
                     forceWidthSetting = $('#task' + icDisplayedTask +
                             ' .annotationGroup:nth-child(' + childNumber + ')').width();
                 }
                 $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                         .css('width', 'auto');
                 $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                         .css('min-width', '');
                 var groupWidth = $('#task' + icDisplayedTask +
                         ' .annotationGroup:nth-child(' + childNumber + ')').width();
                 $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                         .css('min-width', groupWidth);
                 if (forceWidthSetting !== null) {
                     $('#task' + icDisplayedTask +
                             ' .annotationGroup:nth-child(' + childNumber + ')').width(forceWidthSetting);
                 }
             }
         }
         dynamicSizing(icDisplayedTask);
     }


    $(window).scroll(function(){
        iCoastTitle();
    });


     initializeMaps();
     $('#progressTrackerItemWrapper, .thumbnail, .zoomLoadingIndicator')
             .tipTip({defaultPosition: "right"});
     $tagJavaScriptString

     var databaseAnnotationInitialization = 'startClassification=1$annotationMetaDataQueryString';
     console.log(databaseAnnotationInitialization);
     $.post('ajax/annotationLogger.php', databaseAnnotationInitialization);
     $(window).resize(function() {
         dynamicSizing(icDisplayedTask);
         map.setView(postImageLatLon);
         iCoastTitle();
     });
    dynamicSizing(icDisplayedTask);
    $(window).scrollTop($('#navigationBar').position().top);
    map.fitBounds(thumbnailLayer.getBounds(), {'padding': [25 ,10]});


EOL;
