<?php

//--------------------------------------------------------------------------------------------------
// Functions to be removed to include
// Build Image Header
function build_image_header($imageMetadata, $header) {
  $imageLocalTime = utc_to_timezone($imageMetadata['image_time'], 'H:i:s T', $imageMetadata['longitude']);
  $imageDate = utc_to_timezone($imageMetadata['image_time'], 'd M Y', $imageMetadata['longitude']);
  $imageLocation = build_image_location_string($imageMetadata);
  $imageHeader = <<<EOT
            <h1 class="sectionHeader">$header</h1>
            <p class="imageDetails">$imageDate at $imageLocalTime near $imageLocation</p>

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
      <div class="tagWrapper">
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
      $userComment = $tag['userComment'];
    }
    if ($isTagAComment) {
      $tagString[0] .= <<<EOT
              <label id="tag$tagId">$tagText<br>
                <textarea name="$tagId" rows="5" cols="50">
EOT;
      if ($tagSelected) {
        $tagString[0] .= htmlspecialchars($userComment);
      }
      $tagString[0] .= "</textarea></label>";
    } elseif ($isTagARadioButton) {
      $tagString[0] .= '<input type="radio" ';
      if ($tagSelected) {
        $tagString[0] .= 'checked="checked" ';
      }
      $tagString[0] .= <<<EOT
              id="$tagId" class="annotationInput" name="$radioButtonName" value="$tagId">
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
              id="$tagId" class="annotationInput" name="$tagId" value="$tagId">
              <label for="$tagId" id="tag$tagId" class="tag">
                <span class="tagText">$tagText</span>
              </label>

EOT;
    }
    if (!empty($tagTooltip) && !empty($tagTooltipImage)) {
      $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({content: '<div class="tagToolTip"><img src="images/projects/$projectId/tooltips/$tagTooltipImage" height="$tagTooltipImageHeight" width="$tagTooltipImageWidth" /><p>$tagTooltip</p></div>'});

EOT;
    } elseif (!empty($tagTooltip)) {
      $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({content: '<div class="tagToolTip"><p>$tagTooltip</p></div>'});

EOT;
    } elseif (!empty($tagTooltipImage)) {
      $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({content: '<div class="tagToolTip"><img src="images/projects/$projectId/tooltips/$tagTooltipImage" height="$tagTooltipImageHeight" width="$tagTooltipImageWidth" /></div>'});

EOT;
    }
  }
  $tagString[0] .= <<<EOT
          </div>
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
// Find project image metadata $projectMetadata
if (!$projectMetadata = retrieve_entity_metadata($projectId, 'project')) {
  exit("Project $projectId not found in Database");
}
// Find match data $imageMatchData
if (!isset($preImageId)) {
  $imageMatchData = retrieve_image_match_data($projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $postImageId);
  $preImageId = $imageMatchData['pre_image_id'];
}
$imageMatchData = retrieve_image_match_data($projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $postImageId);
$ComputerMatchImageId = $imageMatchData['pre_image_id'];

//--------------------------------------------------------------------------------------------------
// Determine if the user has already annotated the displayed image
$annotationExistsQuery = "SELECT * FROM annotations WHERE user_id = $userId AND "
        . "project_id = $projectId AND image_id = $postImageId";
$annotationExistsResult = run_database_query($annotationExistsQuery);
if (!$annotationExistsResult) {
  print "Query Failure: $annotationExistsQuery";
  exit;
}
$existingAnnotation = FALSE;
if ($annotationExistsResult->num_rows > 0) {
  $existingAnnotation = $annotationExistsResult->fetch_assoc();
  if (!is_null($existingAnnotation['user_match_id'])) {
    if ($preImageId != $existingAnnotation['user_match_id'] && !isset($specifiedPreImage)) {
      header("location: classification.php?&projectId=$projectId&imageId=$postImageId&preImageId={$existingAnnotation['user_match_id']}");
    }
  }
}

// Find post image metadata $postImageMetadata
if (!$postImageMetadata = retrieve_entity_metadata($postImageId, 'image')) {
  exit("Image $postImageId not found in Database");
}
$postImageLatitude = $postImageMetadata['latitude'];
$postImageLongitude = $postImageMetadata['longitude'];

// Find pre image metadata $preImageMetadata
if (!$preImageMetadata = retrieve_entity_metadata($preImageId, 'image')) {
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
$postImageHeader = build_image_header($postImageMetadata, $projectMetadata['post_image_header']);

$preDetailedImageURL = $preImageMetadata['full_url'];
$preDisplayImageURL = "images/datasets/{$preImageMetadata['dataset_id']}/main/{$preImageMetadata['filename']}";
$preImageHeader = build_image_header($preImageMetadata, $projectMetadata['pre_image_header']);


//--------------------------------------------------------------------------------------------------
// Build map marker tooltip
$markerToolTip = build_image_location_string($postImageMetadata);



//--------------------------------------------------------------------------------------------------
// Find image id's of next and previous post images
$postImageArray = find_adjacent_images($postImageId, $projectId);
$previousImageId = $postImageArray[0]['image_id'];
$nextImageId = $postImageArray[2]['image_id'];





//--------------------------------------------------------------------------------------------------
// Build thumbnail data.
$thumbnailArray2 = find_adjacent_images($preImageMetadata['image_id']);
if ($thumbnailArray2[0]['image_id'] != 0) {
  $thumbnailArray1 = find_adjacent_images($thumbnailArray2[0]['image_id']);
} else {
  $thumbnailArray1[0]['image_id'] = 0;
}
if ($thumbnailArray2[2]['image_id'] != 0) {
  $thumbnailArray3 = find_adjacent_images($thumbnailArray2[2]['image_id']);
} else {
  $thumbnailArray3[2]['image_id'] = 0;
}
$thumbnailCounter = 1;
if ($thumbnailArray1[0]['image_id'] != 0) {
  $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
  $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
  $$thumbnailUrlVarName = $thumbnailArray1[0]['thumb_url'];
  $$thumbnailLinkVarName = "classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray1[0]['image_id']}&sessId=$annotationSessionId";
}

$thumbnailCounter++;
foreach ($thumbnailArray2 as $thumbnail) {
  if ($thumbnail['image_id'] != 0) {
    $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
    $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
    $$thumbnailUrlVarName = $thumbnail['thumb_url'];
    $$thumbnailLinkVarName = "classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnail['image_id']}&sessId=$annotationSessionId";
  }
  $thumbnailCounter++;
}
if ($thumbnailArray3[2]['image_id'] != 0) {
  $thumbnailUrlVarName = "thumbnail" . $thumbnailCounter . "Url";
  $thumbnailLinkVarName = "thumbnail" . $thumbnailCounter . "Link";
  $$thumbnailUrlVarName = $thumbnailArray3[2]['thumb_url'];
  $$thumbnailLinkVarName = "classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray3[2]['image_id']}&sessId=$annotationSessionId";
}


//--------------------------------------------------------------------------------------------------
// Build an array of selected tags in any existing annotation
if ($existingAnnotation) {
  $existingTags = array();
  $existingComments = array();

  $tagSelectionQuery = "SELECT * FROM annotation_selections "
          . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
  $tagSelectionResult = run_database_query($tagSelectionQuery);

  if (!$tagSelectionResult) {
    print "Query Failure: $tagSelectionQuery";
    exit;
  }
  while ($existingSelection = $tagSelectionResult->fetch_assoc()) {
    $existingTags[] = $existingSelection['tag_id'];
  }

  $tagCommentQuery = "SELECT * FROM annotation_comments"
          . " WHERE annotation_id = {$existingAnnotation['annotation_id']}";
  $tagCommentResult = run_database_query($tagCommentQuery);

  if (!$tagCommentResult) {
    print "Query Failure: $tagCommentQuery";
    exit;
  }

  while ($existingComment = $tagCommentResult->fetch_assoc()) {
    $existingComments[$existingComment['tag_id']] = $existingComment['comment'];
  }
}


//--------------------------------------------------------------------------------------------------
// Build an array of the tasks.
$taskMetadataQuery = "SELECT * from task_metadata WHERE project_id = $projectId
  ORDER BY order_in_project";
$taskMetadataResults = run_database_query($taskMetadataQuery);
if ($taskMetadataResults && $taskMetadataResults->num_rows > 0) {
  $taskMetadata = $taskMetadataResults->fetch_all(MYSQLI_ASSOC);
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

    $taskContentsQuery = "SELECT tag_group_id FROM task_contents WHERE task_id = $taskId
      ORDER BY order_in_task";
    $taskContentsResult = run_database_query($taskContentsQuery);
    if ($taskContentsResult && $taskContentsResult->num_rows > 0) {
      $taskContents = $taskContentsResult->fetch_all(MYSQLI_ASSOC);
      foreach ($taskContents as $tagGroupIdArray) {
        $tagGroupId = $tagGroupIdArray['tag_group_id'];
        $tagGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE tag_group_id = $tagGroupId
            AND project_id = $projectId";
        $tagGroupMetadataResults = run_database_query($tagGroupMetadataQuery);
        if ($tagGroupMetadataResults && $tagGroupMetadataResults->num_rows == 1) {
          $tagGroupMetadata = $tagGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
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
                    tag_group_id = $tagGroupId ORDER BY order_in_group";
            $groupContentsResult = run_database_query($groupContentsQuery);
            if ($groupContentsResult && $groupContentsResult->num_rows > 0) {
              $groupContents = $groupContentsResult->fetch_all(MYSQLI_ASSOC);
              foreach ($groupContents as $groupContentsArray) {
                $groupGroupId = $groupContentsArray['tag_id'];
                $groupGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE
                  tag_group_id = $groupGroupId AND project_id = $projectId";
                $groupGroupMetadataResults = run_database_query($groupGroupMetadataQuery);
                if ($groupGroupMetadataResults && $groupGroupMetadataResults->num_rows == 1) {
                  $groupGroupMetadata = $groupGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
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
                    tag_group_id = $groupGroupId ORDER BY order_in_group";
                  $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
                  if ($tagGroupContentsResult && $tagGroupContentsResult->num_rows > 0) {
                    $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
                    foreach ($tagGroupContents as $tagIdArray) {
                      $tagId = $tagIdArray['tag_id'];
                      $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = $tagId AND
                        project_id = $projectId";
                      $tagMetadataResult = run_database_query($tagMetadataQuery);
                      if ($tagMetadataResult && $tagMetadataResult->num_rows == 1) {
                        $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
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
          tag_group_id = $tagGroupId ORDER BY order_in_group";
            $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
            if ($tagGroupContentsResult && $tagGroupContentsResult->num_rows > 0) {
              $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
              foreach ($tagGroupContents as $tagIdArray) {
                $tagId = $tagIdArray['tag_id'];
                $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = $tagId AND
                project_id = $projectId";
                $tagMetadataResult = run_database_query($tagMetadataQuery);
                if ($tagMetadataResult && $tagMetadataResult->num_rows == 1) {
                  $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
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
$taskBreadcrumbs = '';
for ($i = 1; $i <= $taskCount; $i++) {
  $taskBreadcrumbs .= <<<EOT
                <div id="task{$i}Breadcrumb" class="taskBreadcrumb">
                  <p id="task{$i}BreadcrumbContent" class="taskBreadcrumbContent">{$i}</p>
                </div>

EOT;
}

//--------------------------------------------------------------------------------------------------
// Build next/previous post image buttons HTML
$newRandomPostImageId = 0;
while ($newRandomPostImageId == 0 || $newRandomPostImageId == $postImageId) {
  $newRandomPostImageId = random_post_image_id_generator($projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
}

$postImageNavigationHtml = '';

if ($previousImageId != 0) {
  $postImageNavigationHtml .= <<<EOT
              <form class="postImageNavForm" method="get" action="classification.php">
                 <input type="hidden" name="projectId" value="$projectId">
                 <input type="hidden" name="imageId" value="$previousImageId">
                <button title="Click to show the next POST-storm Photo along the LEFT of the coast." class="clickableButton postImageNavButton" type="submit">
                  <span class="icon-left"></span>
                </button>
              </form>

EOT;
}

$postImageNavigationHtml .= <<<EOT
              <form class="postImageNavForm" method="get" action="classification.php">
                 <input type="hidden" name="projectId" value="$projectId">
                  <input type="hidden" name="imageId" value="$newRandomPostImageId">
                <button title="Click to show a RANDOM POST-storm Photo and matching PRE-Storm Photo." class="clickableButton postImageNavButton" type="submit">
                  <span class="icon-camera"></span>
                </button>
              </form>
              <button title="Click to view the MAP NAVIGATOR to see the location of the current POST-STORM photo or to select another POST-STORM photo to Tag." id="mapLoad" class="clickableButton postImageNavButton">
                <span class="icon-globe"></span>
              </button>

EOT;

if ($nextImageId != 0) {
  $postImageNavigationHtml .= <<<EOT
              <form class="postImageNavForm" method="get" action="classification.php">
                <input type="hidden" name="projectId" value="$projectId">
                <input type="hidden" name="imageId" value="$nextImageId">
                <button title="Click to show the next POST-storm Photo along the RIGHT of the coast." class="clickableButton postImageNavButton" type="submit">
                  <span class="icon-right"></span>
                </button>
              </form>

EOT;
}

//--------------------------------------------------------------------------------------------------
// Build thumbnail HTML
$previousThumbnailHtml = "";
$nextThumbnailHtml = "";
$currentThumbnailHtml = "";
$navThumbnailTitle = "Click this THUMBNAIL to see if this PRE-STORM photo along the coast better matches the POST-STORM photo on the left. Is this a better match than what the computer found?";
$currentImageTitle = "This THUMBNAIL is the PRE-STORM photo currently displayed above. If this PRE-STORM photo best matches the POST-STORM photo on the left, then click the Confirm Match button.";
$computerMatchTitle = "This HIGHLIGHTED THUMBNAIL is the closest PRE-STORM photo the computer found. Can you find a better match?";
$noMoreImagesTitle = "You have reached the end of this dataset. There are no more images to display in this section of coast.";
if ($thumbnailArray1[0]['image_id'] == $ComputerMatchImageId) {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail1Link">
                <img id="computerMatch" class="thumbnail" src="$thumbnail1Url" height="93" width="140" title="$computerMatchTitle">
              </a>
            </div>

EOT;
} elseif ($thumbnailArray1[0]['image_id'] != 0) {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail1Link">
                <img class="thumbnail" src="$thumbnail1Url" height="93" width="140" title="$navThumbnailTitle">
              </a>
            </div>

EOT;
} else {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img class="thumbnail" src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle">
            </div>

EOT;
}

if ($thumbnailArray2[0]['image_id'] == $ComputerMatchImageId) {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail2Link">
                <img id="computerMatch" class="thumbnail" src="$thumbnail2Url" height="93" width="140" title="$computerMatchTitle">
              </a>
            </div>

EOT;
} elseif ($thumbnailArray2[0]['image_id'] != 0) {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail2Link">
                <img class="thumbnail" src="$thumbnail2Url" height="93" width="140" title="$navThumbnailTitle">
              </a>
            </div>

EOT;
} else {
  $previousThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img class="thumbnail" src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle">
            </div>

EOT;
}

if ($thumbnailArray2[1]['image_id'] == $ComputerMatchImageId) {
  $currentThumbnailHtml .= <<<EOT
            <div class="currentThumbnailWrapper">
              <img id="computerMatch" class="thumbnail" src="$thumbnail3Url" height="103" width="156" title="$computerMatchTitle">
            </div>

EOT;
} else {
  $currentThumbnailHtml .= <<<EOT
            <div class="currentThumbnailWrapper">
              <img class="thumbnail" src="$thumbnail3Url" height="103" width="156" title="$currentImageTitle">
            </div>

EOT;
}

if ($thumbnailArray2[2]['image_id'] == $ComputerMatchImageId) {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail4Link">
                <img id="computerMatch" class="thumbnail" src="$thumbnail4Url" height="93" width="140" title="$computerMatchTitle">
              </a>
            </div>

EOT;
} elseif ($thumbnailArray2[2]['image_id'] != 0) {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail4Link">
                <img class="thumbnail" src="$thumbnail4Url" height="93" width="140" title="$navThumbnailTitle">
              </a>
            </div>

EOT;
} else {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img class="thumbnail" src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle">
            </div>

EOT;
}

if ($thumbnailArray3[2]['image_id'] == $ComputerMatchImageId) {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail5Link">
                <img id="computerMatch" class="thumbnail" src="$thumbnail5Url" height="93" width="140" title="$computerMatchTitle">
              </a>
            </div>

EOT;
} elseif ($thumbnailArray3[2]['image_id'] != 0) {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <a href="$thumbnail5Link">
                <img class="thumbnail" src="$thumbnail5Url" height="93" width="140" title="$navThumbnailTitle">
              </a>
            </div>

EOT;
} else {
  $nextThumbnailHtml .= <<<EOT
            <div class="navThumbnailWrapper">
              <img class="thumbnail" src="images/system/noMoreImages.png" width="150" height="96" title="$noMoreImagesTitle">
            </div>

EOT;
}

//--------------------------------------------------------------------------------------------------
// Build the tasks html string from the tasks array.
$taskHtmlString = "";
//$taskHeaderHTMLString = "";
$tagJavaScriptString = "";
$taskCounter = 0;
foreach ($annotations as $task) {
  $taskCounter++;
  $taskTitle = $task['title'];
  $taskText = $task['text'];
  $groups = $task['groups'];
  $hiddenDataHTML = '';
  if ($taskCounter == 1) {
    $hiddenDataHTML = ''
//            . '<input type="hidden" name="projectId" value="' . $projectId . '">'
//            . '<input type="hidden" name="postImageId" value="' . $postImageId . '">'
            . '<input type="hidden" name="preImageId" value="' . $preImageId . '">'
//            . '<input type="hidden" name="userId" value="' . $userId . '">'
//            . '<input type="hidden" name="authCheckCode" value="' . $authCheckCode . '">'
    ;
  }
//  $taskHeaderHTMLString .= <<<EOT
//
//
//EOT;
//  $taskHtmlString .= <<<EOT
//        <div id = "task$taskCounter" class="task">
//          <p class="taskText">$taskText</p>
//          <form class="annotationForm" method="post" action="classification.php">
//            <div class="groupWrapper">
//
//EOT;
  $taskHtmlString .= <<<EOT
        <div id = "task$taskCounter" class="task">
          <h1 id = "task{$taskCounter}Header"class="taskTitle">$taskTitle</h1>
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
              <div style="$borderHtml $colorHtml" class="annotationGroupGroup">
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
//        print '<pre>';
//        print_r($tags);
//        print '</pre>';
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
            <div id="annotationControls">


EOT;
  If ($taskCounter > 1) {
    $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}PreviousButton" class="clickableButton annotationButton leftAnnotationButton" type="button" value="Previous Task" title="Click to go to the PREVIOUS task.">

EOT;
  }

  if ($taskCounter < count($annotations)) {
    $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}NextButton" class="clickableButton annotationButton rightAnnotationButton" type="button" value="Next Task" title="Click to go to the NEXT task.">

EOT;
  } else {
    $taskHtmlString .= <<<EOT
              <input id="submitButton" class="clickableButton annotationButton rightAnnotationButton" type="button" value="Done" title="Click once you have completed annotating this image set and are happy with your selections.">

EOT;
  }
  $taskHtmlString .= <<<EOT
            </div>
          </form>
        </div>

EOT;
}





//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
// Build javascript;
// Annotation Group Spacing
// Annotation Navigation Buttons
$jsAnnotationNavButtons = "";
for ($i = 1; $i <= $taskCount; $i++) {
  $h = $i == 1 ? $h = 1 : $i - 1;
  $j = $i + 1;

// Annotation Next Navigation Button
  if ($i < $taskCount) {
    $jsAnnotationNavButtons .= <<<EOT
        $('#task{$i}NextButton').click(function() {
          var formData = $('.annotationForm').serialize();
          formData += '$annotationMetaDataQueryString';
          console.log(formData);
          $.post('ajax/annotationLogger.php', formData);
          $('#task{$i}').css('display', 'none');
          $('#task{$i}Breadcrumb').removeClass('currentTaskBreadcrumb');
          $('#task{$i}BreadcrumbContent').css('display', 'none');
          $('#task{$i}Header').css('display', 'none');
          $('#task{$j}').css('display', 'block');
          $('#task{$j}Breadcrumb').addClass('currentTaskBreadcrumb');
          $('#task{$j}BreadcrumbContent').css('display', 'inline');
          $('#task{$j}Header').css('display', 'block');
          icDisplayedTask++;
          setMinGroupHeaderWidth(icDisplayedTask);
        });


EOT;
  }

  // Annotation Previous Navigation Button
  if ($i > 1) {
    $jsAnnotationNavButtons .= <<<EOT
        $('#task{$i}PreviousButton').click(function() {
          var formData = $('.annotationForm').serialize();
          formData += '$annotationMetaDataQueryString';
          console.log(formData);
          $.post('ajax/annotationLogger.php', formData);
          $('#task{$i}').css('display', 'none');
          $('#task{$i}Breadcrumb').removeClass('currentTaskBreadcrumb');
          $('#task{$i}BreadcrumbContent').css('display', 'none');
          $('#task{$i}Header').css('display', 'none');
          $('#task{$h}').css('display', 'block');
          $('#task{$h}Breadcrumb').addClass('currentTaskBreadcrumb');
          $('#task{$h}BreadcrumbContent').css('display', 'inline');
          $('#task{$h}Header').css('display', 'block');
          icDisplayedTask--;
          setMinGroupHeaderWidth(icDisplayedTask);
        });


EOT;
  }
}

$jsAnnotationNavButtons .= <<<EOT
        $('#submitButton').click(function() {
          var formData = $('.annotationForm').serialize();
          formData += '$annotationMetaDataQueryString';
          formData += '&annotationComplete=1';
          console.log(formData);
          $.post('ajax/annotationLogger.php', formData);
        });

EOT;

$jsProjectId = "icProjectId = $projectId;";



//$jsLoadScript = "";
//for ($i = 1; $i <= $taskCount; $i++) {
//  $jsLoadScript .= <<<EOT
//        var task{$i}Div = document.getElementById('task$i');
//        var task{$i}Breadcrumb = document.getElementById('task{$i}Breadcrumb');
//        var task{$i}BreadcrumbContent = document.getElementById('task{$i}BreadcrumbContent');
//        var task{$i}Header = document.getElementById('task{$i}Header');
//
//EOT;
//}
//
//$jsLoadScript .= "\n";



$jsTaskMap = "icTaskMap = new Array();\n";
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
    $jsTaskMap .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
    $jsGroupCount = 0;
  } else {
    $jsTaskMap .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
  }
  $jsTaskCount++;
}


