<?php

$cssLinkArray[] = 'css/tipTip.css';

$javaScriptLinkArray[] = '//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js';
$javaScriptLinkArray[] = 'scripts/tipTip.js';

$jQueryDocumentDotReadyCode = '';
$javaScript = '';



// => Define required files and initial includes
require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
//require_once('includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE);
$userId = $userData['user_id'];
$projectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);

if (empty($projectId)) {
    exit;
} else {
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    if (!$projectMetadata) {
        exit;
    }
}
$referingUrl = filter_input(INPUT_SERVER, 'HTTP_REFERER', FILTER_VALIDATE_URL);
if (!empty($referingUrl)) {
    $referingPage = detect_pageName($referingUrl);
    if ($referingPage != 'projectEditor' && $referingPage != 'reviewProject') {
        $creationStatus = project_creation_stage($projectMetadata['project_id']);
        if ($creationStatus != 10) {
            exit;
        }
    }
} else {
    exit;
}

$annotations = array();

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
// Build the tasks html string from the tasks array.
$taskHtmlString = "";
$tagJavaScriptString = "";
$taskCounter = 0;
foreach ($annotations as $task) {
    $taskCounter++;
    $taskTitle = $task['title'];
    $groups = $task['groups'];
    $hiddenDataHTML = '';
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
              <input id="returnToMatchingButton" class="clickableButton disabledClickableButton" type="button" value="RETURN TO PHOTO MATCHING" title="" disabled>

EOT;
    }

    if ($taskCounter < count($annotations)) {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}NextButton" class="clickableButton" type="button" value="NEXT TASK" title="Click to go to the NEXT task.">

EOT;
    } else {
        $taskHtmlString .= <<<EOT
              <input id="submitButton" class="clickableButton disabledClickableButton" type="button" value="DONE" title="Click once you have completed annotating this image set and are happy with your selections." disabled>

EOT;
    }
    $taskHtmlString .= <<<EOT
            </div>
          </form>
        </div>

EOT;
}

$javaScript .= <<<EOL
    icDisplayedTask = 1;
    var projectId = $projectId;

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

    } // End dynamicSizing

EOL;

$jQueryDocumentDotReadyCode .= <<<EOT


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


     $('#progressTrackerItemWrapper')
             .tipTip({defaultPosition: "right"});
     $tagJavaScriptString

        $('#taskWrapper').css('display', 'block');
        $('#progressTrackerItem1').addClass('currentProgressTrackerItem');
        $('#progressTrackerItem1Content').css('display', 'inline');
        $('#task1Header').css('display', 'block');
        $('#task1').css('display', 'block');
        icDisplayedTask = 1;
        setMinGroupHeaderWidth(icDisplayedTask);

     $(window).resize(function() {
         dynamicSizing(icDisplayedTask);
     });
    dynamicSizing(icDisplayedTask);

   $('a').each(function () {
        $(this).click(function (e) {
        e.preventDefault();
    });
   });

   $('#refreshButton').click(function() {
       location.reload(true);
   });

EOL;
