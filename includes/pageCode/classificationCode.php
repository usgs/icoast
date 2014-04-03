<?php

$pageName = "classify";
$cssLinkArray[] = 'css/tipTip.css';
$embeddedCSS = '';
$javaScriptLinkArray[] = '//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js';
$javaScriptLinkArray[] = 'scripts/elevateZoom.js';
$javaScriptLinkArray[] = 'scripts/tipTip.js';



// => Define required files and initial includes
require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
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
    $validProjectQuery = "SELECT COUNT(*) FROM projects WHERE project_id = :projectId";
    $validProjectParams['projectId'] = $projectId;
    $STH = run_prepared_query($DBH, $validProjectQuery, $validProjectParams);
    $matchingProjectCount = $STH->fetchColumn();
    if ($matchingProjectCount == 0) {
        header("location: welcome.php?userType=existing");
        exit;
    }
}

//////////
// => No Image ID Page Redirect
// => If the page has been called without a random image id in the query string then generate
// => an image id and redirect back to the page with a string attached.
if (empty($_GET['imageId'])) {
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
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
    $imageLocation = build_image_location_string($imageMetadata);
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
// Find project metadata $projectMetadata
if (!$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project')) {
    //  Placeholder for error management
    exit("Project $projectId not found in Database");
}
// Find match data $imageMatchData
$imageMatchData = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $postImageId);
if (!isset($preImageId)) {
    $preImageId = $imageMatchData['pre_image_id'];
}
$ComputerMatchImageId = $imageMatchData['pre_image_id'];

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
$postDisplayImageURL = "images/datasets/{$postImageMetadata['dataset_id']}/main/{$postImageMetadata['filename']}";
$postImageTitle = build_image_header($postImageMetadata, $projectMetadata['post_image_header']);
$postImageAltTagHTML = "An oblique image of the " . build_image_location_string($postImageMetadata, TRUE) . " coastline.";

$preDetailedImageURL = $preImageMetadata['full_url'];
$preDisplayImageURL = "images/datasets/{$preImageMetadata['dataset_id']}/main/{$preImageMetadata['filename']}";
$preImageTitle = build_image_header($preImageMetadata, $projectMetadata['pre_image_header']);
$preImageAltTagHTML = "An oblique image of the " . build_image_location_string($preImageMetadata, TRUE) . " coastline.";


//--------------------------------------------------------------------------------------------------
// Build map marker tooltip
$markerToolTip = build_image_location_string($postImageMetadata);





//--------------------------------------------------------------------------------------------------
// Build thumbnail data.
$thumbnailArray2 = find_adjacent_images($DBH, $preImageMetadata['image_id']);
if ($thumbnailArray2[0]['image_id'] != 0) {
    $thumbnailArray1 = find_adjacent_images($DBH, $thumbnailArray2[0]['image_id']);
} else {
    $thumbnailArray1[0]['image_id'] = 0;
}
if ($thumbnailArray2[2]['image_id'] != 0) {
    $thumbnailArray3 = find_adjacent_images($DBH, $thumbnailArray2[2]['image_id']);
} else {
    $thumbnailArray3[2]['image_id'] = 0;
}
$thumbnailCounter = 1;
if ($thumbnailArray1[0]['image_id'] != 0) {
    $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
    $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
    $thumbnailAltVerName = "thumbnail" . $thumbnailCounter . "Alt";
    $$thumbnailUrlVarName = $thumbnailArray1[0]['thumb_url'];
    $$thumbnailLinkVarName = "classification.php?projectId=$projectId&amp;imageId=$postImageId&amp;preImageId={$thumbnailArray1[0]['image_id']}&amp;sessId=$annotationSessionId";
    $$thumbnailAltVerName = "An oblique image of the " . build_image_location_string($thumbnailArray1[0], TRUE) . " coastline.";
}

$thumbnailCounter++;
foreach ($thumbnailArray2 as $thumbnail) {
    if ($thumbnail['image_id'] != 0) {
        $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
        $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
        $thumbnailAltVerName = "thumbnail" . $thumbnailCounter . "Alt";
        $$thumbnailUrlVarName = $thumbnail['thumb_url'];
        $$thumbnailLinkVarName = "classification.php?projectId=$projectId&amp;imageId=$postImageId&amp;preImageId={$thumbnail['image_id']}&amp;sessId=$annotationSessionId";
        $$thumbnailAltVerName = "An oblique image of the " . build_image_location_string($thumbnail, TRUE) . " coastline.";
    }
    $thumbnailCounter++;
}
if ($thumbnailArray3[2]['image_id'] != 0) {
    $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
    $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
    $thumbnailAltVerName = "thumbnail" . $thumbnailCounter . "Alt";
    $$thumbnailUrlVarName = $thumbnailArray3[2]['thumb_url'];
    $$thumbnailLinkVarName = "classification.php?projectId=$projectId&amp;imageId=$postImageId&amp;preImageId={$thumbnailArray3[2]['image_id']}&amp;sessId=$annotationSessionId";
    $$thumbnailAltVerName = "An oblique image of the " . build_image_location_string($thumbnailArray3[2], TRUE) . " coastline.";
}


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
            'text' => $task['display_text'],
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
// Build thumbnail HTML
$previousThumbnailHtml = "";
$nextThumbnailHtml = "";
$currentThumbnailHtml = "";
$navThumbnailTitle = "Click this THUMBNAIL to see if this PRE-STORM photo along the coast better matches the POST-STORM photo on the right. Is this a better match than what the computer found?";
$currentImageTitle = "This THUMBNAIL is the PRE-STORM photo currently displayed above. If this PRE-STORM photo best matches the POST-STORM photo on the left, then click the Confirm Match button.";
$computerMatchTitle = "This HIGHLIGHTED THUMBNAIL is the closest PRE-STORM photo the computer found. Can you find a better match?";
$noMoreImagesTitle = "You have reached the end of this dataset. There are no more images to display in this section of coast.";
if ($thumbnailArray1[0]['image_id'] == $ComputerMatchImageId) {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail1Link">
                <img id="computerMatch" src="$thumbnail1Url" height="93" width="140" title="$computerMatchTitle" alt="$thumbnail1Alt">
              </a>
          <p>Computer Match</p>
            </div>

EOT;
} elseif ($thumbnailArray1[0]['image_id'] != 0) {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail1Link">
                <img src="$thumbnail1Url" height="93" width="140" title="$navThumbnailTitle" alt="$thumbnail1Alt">
              </a>
            </div>

EOT;
} else {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle"  alt="There are no more images in this dataset">
          <p>Currently Displayed Photo</p>
            </div>

EOT;
}

if ($thumbnailArray2[0]['image_id'] == $ComputerMatchImageId) {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail2Link">
                <img id="computerMatch" src="$thumbnail2Url" height="93" width="140" title="$computerMatchTitle" alt="$thumbnail2Alt">
              </a>
          <p>Computer Match</p>
            </div>

EOT;
} elseif ($thumbnailArray2[0]['image_id'] != 0) {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail2Link">
                <img src="$thumbnail2Url" height="93" width="140" title="$navThumbnailTitle" alt="$thumbnail2Alt">
              </a>
            </div>

EOT;
} else {
    $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle"  alt="There are no more images in this dataset">
          <p>Currently Displayed Photo</p>
            </div>

EOT;
}

if ($thumbnailArray2[1]['image_id'] == $ComputerMatchImageId) {
    $currentThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper currentThumbnailWrapper">
              <img id="computerMatch" src="$thumbnail3Url" height="103" width="156" title="$computerMatchTitle" alt="$thumbnail3Alt">
          <p>Computer Match &amp;</p>
          <p>Currently Displayed Photo</p>
            </div>

EOT;
} else {
    $currentThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper currentThumbnailWrapper">
              <img src="$thumbnail3Url" height="103" width="156" title="$currentImageTitle" alt="$thumbnail3Alt">
          <p>Currently Displayed Photo</p>
            </div>

EOT;
}

if ($thumbnailArray2[2]['image_id'] == $ComputerMatchImageId) {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail4Link">
                <img id="computerMatch" src="$thumbnail4Url" height="93" width="140" title="$computerMatchTitle" alt="$thumbnail4Alt">
              </a>
          <p>Computer Match</p>
            </div>

EOT;
} elseif ($thumbnailArray2[2]['image_id'] != 0) {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail4Link">
                <img src="$thumbnail4Url" height="93" width="140" title="$navThumbnailTitle" alt="$thumbnail4Alt">
              </a>
            </div>

EOT;
} else {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle" alt="There are no more images in this dataset">
          <p>Currently Displayed Photo</p>
            </div>

EOT;
}

if ($thumbnailArray3[2]['image_id'] == $ComputerMatchImageId) {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail5Link">
                <img id="computerMatch" src="$thumbnail5Url" height="93" width="140" title="$computerMatchTitle" alt="$thumbnail5Alt">
              </a>
          <p>Computer Match</p>
            </div>

EOT;
} elseif ($thumbnailArray3[2]['image_id'] != 0) {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail5Link">
                <img src="$thumbnail5Url" height="93" width="140" title="$navThumbnailTitle" alt="$thumbnail5Alt">
              </a>
            </div>

EOT;
} else {
    $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle" alt="There are no more images in this dataset">
          <p>Currently Displayed Photo</p>
            </div>

EOT;
}

//--------------------------------------------------------------------------------------------------
// Build the tasks html string from the tasks array.
$taskHtmlString = "";
$tagJavaScriptString = "";
$taskCounter = 0;
foreach ($annotations as $task) {
    $taskCounter++;
    $taskTitle = $task['title'];
    $taskText = $task['text'];
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
    icDisplayedTask = 1;
    icMap = null;
    icCurrentImageLatLon = null;
    icCurrentImageMarker = null;
    icProjectId = '';

    function initializeMaps() {
        icCurrentImageLatLon = new google.maps.LatLng
                ($postImageLatitude, $postImageLongitude);
        var mapOptions = {
            center: icCurrentImageLatLon,
            zoom: 12,
            mapTypeId: google.maps.MapTypeId.HYBRID
        };
        icMap = new google.maps.Map(document.getElementById("mapInsert"),
                mapOptions);
        var input = (document.getElementById('pac-input'));
        icMap.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

        var mapCurrentIcon = {
            size: new google.maps.Size(32, 37),
            url: 'images/system/photoCurrent.png'
        };
        icCurrentImageMarker = new google.maps.Marker({
            position: icCurrentImageLatLon,
            animation: google.maps.Animation.DROP,
            icon: mapCurrentIcon,
            clickable: false,
            map: icMap
        });
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

        // Size Div over map to match push map down level with the images.
        var headerInnerHeight = $('.imageColumnTitle').height();
        $('#mapColumnTitle').css('height', headerInnerHeight);


        // Determine size the page;
        $('html').css('overflow', 'hidden');
        var bodyHeight = $('body').height();
        var bodyWidth = $('#classificationWrapper').width();
        $('html').css('overflow', 'auto');


        // Calculate and set an image size that stops body exceeding viewport height.
        var headerHeight = $('.imageColumnTitle').outerHeight();
        var annotationHeight = $('#annotationWrapper').outerHeight();
        // 25px from header, 15px from imageColumnContent padding, 1 to account for browser pixel rounding
        var maxImageHeightByY = bodyHeight - 25 - 15 - 1 - headerHeight - annotationHeight
        console.log('Header:' + headerHeight);
        console.log('Annotation:' + annotationHeight);
        console.log('maxImageHeightByY:' + maxImageHeightByY);
        var maxImageWidth = maxImageHeightByY / 0.652;
        if (maxImageWidth >= (bodyWidth * 0.43) - 10) {
            maxImageWidth = (bodyWidth * 0.43) - 15;
            maxImageHeightByY = maxImageWidth * 0.652;
        }
        maxImageWidth = Math.floor(maxImageWidth);
        maxImageWidth += 'px';

        $('.imageColumnContent').css('max-width', maxImageWidth);

        var maxImageHeightByX = (((bodyWidth * 0.43) - 15) * 0.65) - 1;
        console.log('maxImageHeightByX:' + maxImageHeightByX);
        if (maxImageHeightByY < maxImageHeightByX) {
            var mapInsertHeight = maxImageHeightByY;
            console.log('maxImageHeightByY = mapInsertHeight');
        } else {
            var mapInsertHeight = maxImageHeightByX;
            console.log('maxImageHeightByX = mapInsertHeight');
        }

        if (mapInsertHeight > 521) {
            mapInsertHeight = 521;
        }
        mapInsertHeight = Math.floor(mapInsertHeight);
        console.log('mapInsertHeight: ' + mapInsertHeight);
        mapInsertHeight += "px";
        $('#mapInsert').css('height', mapInsertHeight);

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
        if ($(window).width() > ($('#navigationBar ul').outerWidth() + $('#navigationBar p').outerWidth() + 20)) {
            if ($(window).scrollTop() > $('#usgstitle').position().top && $('#navigationBar p').length == 0) {
                $('#navigationBar').append('<p>iCoast - Did the Coast Change?</p>');
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


$jQueryDocumentDotReadyCode = "icProjectId = $projectId;\r\n";

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


     var script = document.createElement("script");
     script.type = "text/javascript";
     script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
     document.body.appendChild(script);
     $('#progressTrackerItem1').addClass('currentProgressTrackerItem');
     $('#progressTrackerItem1Content').css('display', 'inline');
     $('#task1Header').css('display', 'block');
     $('#task1').css('display', 'block');
     $('#progressTrackerItemWrapper, .thumbnail, .zoomLoadingIndicator')
             .tipTip({defaultPosition: "right"});
     $tagJavaScriptString
     icDisplayedTask = 1;
     setMinGroupHeaderWidth(icDisplayedTask);

     $('#centerMapButton').click(function() {
         icMap.setCenter(icCurrentImageLatLon);
     });

     var databaseAnnotationInitialization = 'loadEvent=True$annotationMetaDataQueryString';
     console.log(databaseAnnotationInitialization);
     $.post('ajax/annotationLogger.php', databaseAnnotationInitialization);
     $(window).resize(function() {
         dynamicSizing(icDisplayedTask);
         icMap.setCenter(icCurrentImageLatLon);
         iCoastTitle();
     });
     dynamicSizing(icDisplayedTask);
    $(window).scrollTop($('#navigationBar').position().top);


EOL;
