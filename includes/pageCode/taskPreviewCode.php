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
$userData =
    authenticate_user($DBH,
                      true,
                      true,
                      true,
                      true,
                      false,
                      false);
$userId = $userData['user_id'];
$projectId =
    filter_input(INPUT_GET,
                 'projectId',
                 FILTER_VALIDATE_INT);

if (empty($projectId))
{
    exit;
}
else
{
    $projectMetadata =
        retrieve_entity_metadata($DBH,
                                 $projectId,
                                 'project');
    if (!$projectMetadata)
    {
        exit;
    }
}
if ($_SERVER['HTTP_REFERER'])
{
    $referingPage = detect_pageName($_SERVER['HTTP_REFERER']);
    if ($referingPage != 'projectEditor' && $referingPage != 'reviewProject')
    {
        $creationStatus = project_creation_stage($projectMetadata['project_id']);
        if ($creationStatus != 10)
        {
            exit;
        }
    }
}
else
{
    exit;
}

$questionMap = array();

// Build Annotation Tags
function build_group_contents($projectId,
                              $tags)
{
    $totalTags = count($tags);
    if ($totalTags == 4)
    {
        $columnMax = 3;
    }
    else
    {
        $columnMax = 4;
    }
    $tagCounter = 0;
    $tagString[0] = <<<EOT
        <div class="tagColumn">

EOT;
    $tagString[1] = "";
    foreach ($tags as $tag)
    {
        $tagCounter++;
        if ($tagCounter == $columnMax)
        {
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
        $tagText = restoreSafeHTMLTags(htmlspecialchars($tag['text']));
        $tagTooltip = $tag['tooltipText'];
        $tagTooltipImage = $tag['tooltipImage'];
        $tagTooltipImageWidth = $tag['tooltipImageWidth'];
        $tagTooltipImageHeight = $tag['tooltipImageHeight'];
        $tagSelected = $tag['userSelected'];
        if (isset($tag['userComment']))
        {
            $userComment = htmlspecialchars($tag['userComment']);
        }
        if ($isTagAComment)
        {
            $tagString[0] .= <<<EOT
              <label id="tag$tagId">$tagText<br>
                <textarea name="$tagId" rows="5" cols="50">

EOT;
            if ($tagSelected)
            {
                $tagString[0] .= $userComment;
            }
            $tagString[0] .= "</textarea></label>";
        }
        elseif ($isTagARadioButton)
        {
            $tagString[0] .= '<input type="radio" ';
            if ($tagSelected)
            {
                $tagString[0] .= 'checked="checked" ';
            }
            $tagString[0] .= <<<EOT
              id="$tagId" name="$radioButtonName" value="$tagId">
              <label for="$tagId" id="tag$tagId" class="tag">
                <span class="tagText">$tagText</span>
              </label>

EOT;
        }
        else
        {
            $tagString[0] .= '<input type="checkbox" ';
            if ($tagSelected)
            {
                $tagString[0] .= 'checked="checked" ';
            }
            $tagString[0] .= <<<EOT
              id="$tagId" name="$tagId" value="$tagId">
              <label for="$tagId" id="tag$tagId" class="tag">
                <span class="tagText">$tagText</span>
              </label>

EOT;
        }
        if (!empty($tagTooltip) && !empty($tagTooltipImage))
        {
            if ($tagTooltipImageWidth < 400)
            {
                $maxTagTooltipImageWidth = ($tagTooltipImageWidth + 18) . 'px';
            }
            else
            {
                $maxTagTooltipImageWidth = '418px';
            }
            $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({
                content: '<div class="tagToolTip"><img src="images/projects/$projectId/tooltips/$tagTooltipImage" height="$tagTooltipImageHeight" width="$tagTooltipImageWidth" /><p>$tagTooltip</p></div>',
                maxWidth: '$maxTagTooltipImageWidth'
            });

EOT;
        }
        elseif (!empty($tagTooltip))
        {
            $tagString[1] .= <<<EOT
        $('#tag$tagId').tipTip({
                content: '<div class="tagToolTip"><p>$tagTooltip</p></div>'
            });

EOT;
        }
        elseif (!empty($tagTooltipImage))
        {
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
$taskMetadataQuery = "SELECT * FROM task_metadata WHERE project_id = :projectId
  ORDER BY order_in_project";
$taskMetadataParams['projectId'] = $projectId;
$STH =
    run_prepared_query($DBH,
                       $taskMetadataQuery,
                       $taskMetadataParams);
$taskMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
if (count($taskMetadata) > 0)
{
    foreach ($taskMetadata as $task)
    {
        if ($task['is_enabled'] == 0)
        {
            continue;
        }
        $taskId = $task['task_id'];
        $questionMap[$taskId] = array(
            'title'  => restoreSafeHTMLTags(htmlspecialchars($task['display_title'])),
            'groups' => array()
        );

        $taskContentsQuery = "SELECT tag_group_id FROM task_contents WHERE task_id = :taskId
      ORDER BY order_in_task";
        $taskContentsParams['taskId'] = $taskId;
        $STH =
            run_prepared_query($DBH,
                               $taskContentsQuery,
                               $taskContentsParams);
        $taskContents = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($taskContents) > 0)
        {
            foreach ($taskContents as $tagGroupIdArray)
            {
                $tagGroupId = $tagGroupIdArray['tag_group_id'];
                $tagGroupMetadataQuery = "SELECT * FROM tag_group_metadata WHERE tag_group_id = :tagGroupId
            AND project_id = :projectId";
                $tagGroupMetadataParams = array(
                    'tagGroupId' => $tagGroupId,
                    'projectId'  => $projectId
                );
                $STH =
                    run_prepared_query($DBH,
                                       $tagGroupMetadataQuery,
                                       $tagGroupMetadataParams);
                $tagGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                if (count($tagGroupMetadata) == 1)
                {
                    if ($tagGroupMetadata[0]['is_enabled'] == 0)
                    {
                        continue;
                    }
                    $questionMap[$taskId]['groups'][$tagGroupId] = array(
                        'text'       => restoreSafeHTMLTags(htmlspecialchars($tagGroupMetadata[0]['display_text'])),
                        'border'     => $tagGroupMetadata[0]['has_border'],
                        'color'      => $tagGroupMetadata[0]['has_color'],
                        'forceWidth' => $tagGroupMetadata[0]['force_width'],
                        'groups'     => array(),
                        'tags'       => array()
                    );


                    if ($tagGroupMetadata[0]['contains_groups'] == 1)
                    {
                        $groupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :tagGroupId ORDER BY order_in_group";
                        $groupContentsParams['tagGroupId'] = $tagGroupId;
                        $STH =
                            run_prepared_query($DBH,
                                               $groupContentsQuery,
                                               $groupContentsParams);
                        $groupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                        if (count($groupContents) > 0)
                        {
                            foreach ($groupContents as $groupContentsArray)
                            {
                                $groupGroupId = $groupContentsArray['tag_id'];
                                $groupGroupMetadataQuery = "SELECT * FROM tag_group_metadata WHERE
                  tag_group_id = :groupGroupId AND project_id = :projectId";
                                $groupGroupMetadataParams = array(
                                    'groupGroupId' => $groupGroupId,
                                    'projectId'    => $projectId
                                );
                                $STH =
                                    run_prepared_query($DBH,
                                                       $groupGroupMetadataQuery,
                                                       $groupGroupMetadataParams);
                                $groupGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                if (count($groupGroupMetadata) == 1)
                                {
                                    if ($groupGroupMetadata[0]['is_enabled'] == 0)
                                    {
                                        continue;
                                    }
                                    $questionMap[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = array(
                                        'text'       => restoreSafeHTMLTags(htmlspecialchars
                                                                            ($groupGroupMetadata[0]['display_text'])),
                                        'border'     => $groupGroupMetadata[0]['has_border'],
                                        'color'      => $groupGroupMetadata[0]['has_color'],
                                        'forceWidth' => $groupGroupMetadata[0]['force_width'],
                                        'tags'       => array()
                                    );
                                    $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :groupGroupId ORDER BY order_in_group";
                                    $tagGroupContentsParams = array();
                                    $tagGroupContentsParams['groupGroupId'] = $groupGroupId;
                                    $STH =
                                        run_prepared_query($DBH,
                                                           $tagGroupContentsQuery,
                                                           $tagGroupContentsParams);
                                    $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                                    if (count($tagGroupContents) > 0)
                                    {
                                        foreach ($tagGroupContents as $tagIdArray)
                                        {
                                            $tagId = $tagIdArray['tag_id'];
                                            $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                        project_id = :projectId";
                                            $tagMetadataParams = array();
                                            $tagMetadataParams = array(
                                                'tagId'     => $tagId,
                                                'projectId' => $projectId
                                            );
                                            $STH =
                                                run_prepared_query($DBH,
                                                                   $tagMetadataQuery,
                                                                   $tagMetadataParams);
                                            $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                            if (count($tagMetadata) == 1)
                                            {
                                                if ($tagMetadata[0]['is_enabled'] == 0)
                                                {
                                                    continue;
                                                }
                                                $questionMap[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                ['tags'][$tagId] = array(
                                                    'id'                 => $tagMetadata[0]['tag_id'],
                                                    'comment'            => $tagMetadata[0]['is_comment_box'],
                                                    'radio'              => $tagMetadata[0]['is_radio_button'],
                                                    'radioGroup'         => $tagMetadata[0]['radio_button_group'],
                                                    'text'               => restoreSafeHTMLTags(htmlspecialchars
                                                                                                ($tagMetadata[0]['display_text'])),
                                                    'tooltipText'        => restoreSafeHTMLTags(htmlspecialchars
                                                                                                ($tagMetadata[0]['tooltip_text'])),
                                                    'tooltipImage'       => $tagMetadata[0]['tooltip_image'],
                                                    'tooltipImageWidth'  => $tagMetadata[0]['tooltip_image_width'],
                                                    'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                                    'userSelected'       => false
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
          tag_group_id = :tagGroupId ORDER BY order_in_group";
                        $tagGroupContentsParams = array();
                        $tagGroupContentsParams['tagGroupId'] = $tagGroupId;
                        $STH =
                            run_prepared_query($DBH,
                                               $tagGroupContentsQuery,
                                               $tagGroupContentsParams);
                        $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
                        if (count($tagGroupContents) > 0)
                        {
                            foreach ($tagGroupContents as $tagIdArray)
                            {
                                $tagId = $tagIdArray['tag_id'];
                                $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                project_id = :projectId";
                                $tagMetadataParams = array();
                                $tagMetadataParams = array(
                                    'tagId'     => $tagId,
                                    'projectId' => $projectId
                                );
                                $STH =
                                    run_prepared_query($DBH,
                                                       $tagMetadataQuery,
                                                       $tagMetadataParams);
                                $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
                                if (count($tagMetadata) == 1)
                                {
                                    if ($tagMetadata[0]['is_enabled'] == 0)
                                    {
                                        continue;
                                    }
                                    $questionMap[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = array(
                                        'id'                 => $tagMetadata[0]['tag_id'],
                                        'comment'            => $tagMetadata[0]['is_comment_box'],
                                        'radio'              => $tagMetadata[0]['is_radio_button'],
                                        'radioGroup'         => $tagMetadata[0]['radio_button_group'],
                                        'text'               => restoreSafeHTMLTags(htmlspecialchars
                                                                                    ($tagMetadata[0]['display_text'])),
                                        'tooltipText'        => restoreSafeHTMLTags(htmlspecialchars
                                                                                    ($tagMetadata[0]['tooltip_text'])),
                                        'tooltipImage'       => $tagMetadata[0]['tooltip_image'],
                                        'tooltipImageWidth'  => $tagMetadata[0]['tooltip_image_width'],
                                        'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                        'userSelected'       => false
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
$taskCount = count($questionMap);
$progressTrackerItems = '';
for ($i = 1; $i <= $taskCount; $i++)
{
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
foreach ($questionMap as $task)
{
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

    foreach ($groups as $group)
    {
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
        if (!empty($groupBorder))
        {
            $borderHtml = "border-style:solid; border-width: 2px;";
        }
        if (!empty($groupColor))
        {
            $colorHtml = "background-color: #$groupColor; ";
        }
        if (!empty($forceWidth))
        {
            $widthHtml = "width: {$forceWidth}px; ";
            $widthClass = "forceWidth";
        }

        if (count($groupGroups) > 0)
        {
            $taskHtmlString .= <<<EOT
              <div style="$borderHtml $colorHtml" class="annotationGroup">
                <h2 class="groupText">$groupText</h2>

EOT;
            foreach ($groupGroups as $subGroup)
            {
                $subGroupText = $subGroup['text'];
                $subGroupBorder = $subGroup['border'];
                $subGroupColor = $subGroup['color'];
                $tags = $subGroup['tags'];
                $forceWidth = $subGroup['forceWidth'];
                $borderHtml = '';
                $colorHtml = '';
                $widthHtml = '';
                $widthClass = '';
                if (!empty($subGroupBorder))
                {
                    $borderHtml = "border-style:solid; border-width: 3px;";
                }
                if (!empty($subGroupColor))
                {
                    $colorHtml = "background-color: #$subGroupColor; ";
                }
                if (!empty($forceWidth))
                {
                    $widthHtml = "width: {$forceWidth}px; ";
                    $widthClass = "forceWidth";
                }
                if (count($tags) > 1)
                {
                    reset($tags);
                    $first_key = key($tags);
                    if ($tags[$first_key]['radio'] == true)
                    {
                        $subGroupText .= '<br><span class="tagInstruction">(choose one)</span>';
                    }
                    elseif ($tags[$first_key]['comment'] == false && $tags[$first_key]['radio'] == false)
                    {
                        $subGroupText .= '<br><span class="tagInstruction">(choose any)</span>';
                    }
                }

                $taskHtmlString .= <<<EOT
                <div style="$borderHtml $colorHtml $widthHtml" class="annotationSubgroup $widthClass">
                  <h3 class="subGroupText">$subGroupText</h3>

EOT;
                $tagCode =
                    build_group_contents($projectId,
                                         $tags);
                $tagJavaScriptString .= $tagCode[1];
                $taskHtmlString .= $tagCode[0];
                $taskHtmlString .= <<<EOT
               </div>

EOT;
            }
        }
        else
        {
            if (count($tags) > 1)
            {
                reset($tags);
                $first_key = key($tags);
                if ($tags[$first_key]['radio'] == true)
                {
                    $groupText .= '<br><span class="tagInstruction">(choose one)</span>';
                }
                elseif ($tags[$first_key]['comment'] == false && $tags[$first_key]['radio'] == false)
                {
                    $groupText .= '<br><span class="tagInstruction">(choose any)</span>';
                }
            }
            $taskHtmlString .= <<<EOT
              <div style="$borderHtml $colorHtml $widthHtml" class="annotationGroup $widthClass">
                <h2 class="groupText">$groupText</h2>

EOT;
            $tagCode =
                build_group_contents($projectId,
                                     $tags);
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
    If ($taskCounter > 1)
    {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}PreviousButton" class="clickableButton" type="button" value="PREVIOUS TASK" title="Click to go to the PREVIOUS task.">

EOT;
    }
    else
    {
        $taskHtmlString .= <<<EOT
              <input id="returnToMatchingButton" class="clickableButton disabledClickableButton" type="button" value="RETURN TO PHOTO MATCHING" title="" disabled>

EOT;
    }

    if ($taskCounter < count($questionMap))
    {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}NextButton" class="clickableButton" type="button" value="NEXT TASK" title="Click to go to the NEXT task.">

EOT;
    }
    else
    {
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

$javaScript .= <<<JS
    icDisplayedTask = 1;
    var projectId = $projectId;

function dynamicSizing(icDisplayedTask) {
       
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
        
        var maxTaskHeight = 0;
        //console.log('ICDisplayedTask: ' + icDisplayedTask)
        if (icDisplayedTask == 0) {
            $('#photoMatchingWrapper').hide();
            $('#taskWrapper').show();
        } else {
            $('#task' + icDisplayedTask).hide();
        }
        
        $('.task').each(function(i) {
        //console.log($(this));
            var taskNumber = ++i;
            //console.log('Task ' + taskNumber);
            $(this).show();
        
            //console.log(icTaskMap);
            var taskWidth = $(this).width();
            //console.log('Task width ' + taskWidth);
            //console.log(icTaskMap);
            var numberOfGroups = icTaskMap[taskNumber];
            if (numberOfGroups != 0) {

                var groups = $('#task' + taskNumber + ' .annotationGroup');
                var subGroups = $('#task' + taskNumber + ' .annotationSubgroup');
                
                var equalGroupWidth = null;
                equalGroupWidth = Math.floor(taskWidth / (numberOfGroups)) - 51;
                //console.log('EqualGroupWidth: ' + equalGroupWidth);
                
                var oversizeGroupExcessAmount = 0;
                
                $(this).find('.groupText, .subGroupText').hide();
                
                subGroups.each(function(){
                    //console.log($(this));
                    var borderWidth = Math.ceil($(this).css('border-left-width').replace('px', ''));
                    var groupWidth = $(this).width();
                    var groupMinWidth = Math.ceil($(this).css('min-width').replace('px', ''));
                    //console.log('Group Width ' + groupWidth + ' groupMinWidth ' + groupMinWidth + ' BorderWidth ' + borderWidth);
                    
                    if ($(this).hasClass('forceWidth') && groupWidth > equalGroupWidth) {
                        //console.log('GroupForceWiNodth is oversize');
                        oversizeGroupExcessAmount += (groupWidth - equalGroupWidth);
                    } else if (groupMinWidth > equalGroupWidth) {
                        //console.log('GroupMinWidth is oversize');
                        oversizeGroupExcessAmount += (groupMinWidth - equalGroupWidth);
                    } else {
                        //console.log('Group is not oversize');
                    };
                });
                if (oversizeGroupExcessAmount > 0) {
                    equalGroupWidth = Math.floor((taskWidth - oversizeGroupExcessAmount) / (numberOfGroups)) - 51;
                    //console.log('New EqualGroupWidth: ' + equalGroupWidth);
                }
                
                
                
                //console.log('************************************');
                
                subGroups.each(function(){
                //console.log($(this));
                    if (!$(this).hasClass('forceWidth')) {
                        var groupMinWidth = Math.ceil($(this).css('min-width').replace('px', ''));
                        var borderWidth = Math.ceil($(this).css('border-left-width').replace('px', ''));
                               
                        if (equalGroupWidth > groupMinWidth
                         //&& borderWidth === 0
                         ) {
                            //console.log('Subgroup: if 1');
                            $(this).width(equalGroupWidth);
                        //} else if (equalGroupWidth >= (groupMinWidth + 60)) {
                        //console.log('if 2');
                        //    $(this).width(groupMinWidth + 60);
                        } else {
                            //console.log('Subgroup: if 2');
                            $(this).width(groupMinWidth);
                        }
                    }
                });
    
                groups.each(function(){
                //console.log($(this));
                    var subGroupElem = $(this).find('.annotationSubgroup');
                    //console.log('Group ForceWidth: ' + $(this).hasClass('forceWidth'));
                    //console.log('Subgroups: ' + subGroupElem.length);
                    if (!$(this).hasClass('forceWidth') && subGroupElem.length == 0) {
                        var groupMinWidth = Math.ceil($(this).css('min-width').replace('px', ''));
                        var borderWidth = Math.ceil($(this).css('border-left-width').replace('px', ''));
                        if (equalGroupWidth > groupMinWidth 
                        //&& borderWidth === 0
                        ) {
                            //console.log('Subgroup: if 1');
                            $(this).width(groupWidth);
                        //} else if (equalGroupWidth >= (groupMinWidth + 60)) {
                        //    $(this).width(groupMinWidth + 60);
                        } else {
                            //console.log('Subgroup: if 2');
                            $(this).width(groupMinWidth);
                        }
                    }
                });
    
                //console.log('************************************');
                // Set group header and div heights to the same across the board
                $(this).find('.groupText, .subGroupText').show();
                $(this).find('.groupWrapper h2, .groupWrapper h3').height("");
                
    
                var maxGroupHeaderHeight = 0;
                groups.each(function() {  
                //console.log($(this));
                    var groupHeaderHeight = $(this).find('h2').height();
                    //console.log('Group Header Height: ' + groupHeaderHeight);
                    if (groupHeaderHeight > maxGroupHeaderHeight) {
                        maxGroupHeaderHeight = groupHeaderHeight;
                        //console.log('New Max Group Header Height: ' + maxGroupHeaderHeight);
                    }
    
                    // Now go through and sub groups in this parent group.
                    var childGroups = $(this).find('.annotationSubgroup');
                    var maxChildGroupHeaderHeight = 0;
                    childGroups.each(function() {                  
                        var childGroupHeaderHeight = $(this).find('h3').height();
                        //console.log('Child Group Header Height: ' + childGroupHeaderHeight);
                        if (childGroupHeaderHeight > maxChildGroupHeaderHeight) {
                            maxChildGroupHeaderHeight = childGroupHeaderHeight;
                            //console.log('New Max Child Group Header Height: ' + maxChildGroupHeaderHeight);
                        }
                    });
                    childGroups.each(function() {
                        //console.log('Setting New Max Child Group Header Height: ' + maxChildGroupHeaderHeight);
                        $(this).find('h3').height(maxChildGroupHeaderHeight)
                    });
                });
                groups.each(function() {
                //console.log($(this));
                //    console.log('Setting New Max Group Header Height: ' + maxGroupHeaderHeight);
                //    console.log($(this));
                    $(this).find('h2').height(maxGroupHeaderHeight)
                });
                
                var maxGroupHeight = 0;
                groups.each(function() {
                    $(this).css('height', 'auto');
                    var groupHeight = $(this).height();
                    // console.log('Group Height: ' + groupHeight);
                    if (groupHeight > maxGroupHeight) {
                        maxGroupHeight = groupHeight;
                        //console.log('New Max Group Height: ' + maxGroupHeight);
                    }
        
                    // Now go through and sub groups in this parent group.
                    var childGroups = $(this).find('.annotationSubgroup');
                    var maxChildGroupHeight = 0;
                    childGroups.each(function() {
                    //console.log($(this));
                        $(this).css('height', 'auto');
                        var childGroupHeight = $(this).height();
                        //console.log('Child Group Height: ' + childGroupHeight);
                        if (childGroupHeight > maxChildGroupHeight) {
                            maxChildGroupHeight = childGroupHeight;
                            //console.log('New Max Child Group Height: ' + maxGroupHeight);
                        }
                    });
                    childGroups.each(function() {
                    //console.log($(this));
                        //console.log('Setting New Max Child Group Height');
                        $(this).height(maxChildGroupHeight);
                    });
                });
                groups.each(function() {
                    //var borderWidth = parseInt($(this).css('border-left-width'));
                    //var backGroundColor = $(this).css('background-color');
                    //console.log(backGroundColor);
                    //console.log('Setting New Max Group  Height');
                    $(this).height(maxGroupHeight);
    
                });
        
            }
       
            //console.log($(this));
            var taskHeight = $(this).height();
            taskHeight > maxTaskHeight ? maxTaskHeight = taskHeight : false;
            //console.log(i + ': ' + taskHeight + ': ' + maxTaskHeight);
            $(this).hide();
        });
               
         if (icDisplayedTask == 0) {
            $('#taskWrapper').hide();
            $('#photoMatchingWrapper').show();
        } else {
            $('#task' + icDisplayedTask).show();
        }
        
        if (maxTaskHeight > 343) {
            $('#annotationWrapper').css('min-height', maxTaskHeight + 'px');
        } else {
            maxTaskHeight = 343;
        }
        
        
        
        
        
        
        
        
        
        
        
        //console.log(maxTaskHeight);
        //console.log($('#annotationWrapper').css('border-top-width'));
        var annotationWrapperBoarderWidth = Math.ceil($('#annotationWrapper').css('border-top-width').replace('px', ''));
        //console.log(annotationWrapperBoarderWidth);
        maxTaskHeight = maxTaskHeight + Math.ceil(annotationWrapperBoarderWidth);
        //console.log('Max annotation height: ' + maxTaskHeight);

        // 30px from header, 1 to account for browser pixel rounding, 15 for image div padding top and bottom
        var maxImageHeightByY = bodyHeight - 30 - 1 - maxHeaderOuterHeight - 15 - maxTaskHeight;
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

JS;

$jQueryDocumentDotReadyCode .= <<<EOT


EOT;

// Annotation Navigation Buttons
for ($i = 1; $i <= $taskCount; $i++)
{
    $h = $i == 1 ? $h = 1 : $i - 1;
    $j = $i + 1;

    // Annotation Next Navigation Button
    if ($i < $taskCount)
    {

        if ($i == 1)
        {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}NextButton').click(function() {
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
//                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
//                  $('#task{$j}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'none');
                  icDisplayedTask++;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        }
        else
        {

            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}NextButton').click(function() {
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
//                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
//                  $('#task{$j}Header').css('display', 'block');
                  icDisplayedTask++;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        }
    }

    // Annotation Previous Navigation Button
    if ($i > 1)
    {

        if ($i == 2)
        {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}PreviousButton').click(function() {
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
//                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
//                  $('#task{$h}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'block');
                  icDisplayedTask--;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

EOT;
        }
        else
        {
            $jQueryDocumentDotReadyCode .= <<<EOT
                $('#task{$i}PreviousButton').click(function() {
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
//                  $('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
//                  $('#task{$h}Header').css('display', 'block');
                  icDisplayedTask--;
//                  setMinGroupHeaderWidth(icDisplayedTask);
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
foreach ($questionMap as $task)
{
    $jsGroupCount = 0;
    if (count($task['groups']) > 0)
    {
        foreach ($task['groups'] as $group)
        {
            if (count($group['groups']) > 0)
            {
                $jsGroupCount += count($group['groups']);
            }
            else
            {
                $jsGroupCount++;
            }
        }
        $jQueryDocumentDotReadyCode .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
        $jsGroupCount = 0;
    }
    else
    {
        $jQueryDocumentDotReadyCode .= "icTaskMap[$jsTaskCount] = $jsGroupCount;\n";
    }
    $jsTaskCount++;
}

$jQueryDocumentDotReadyCode .= <<<JS
     $('.groupText, .subGroupText').hide();
     $('.annotationSubgroup').each(function(){
         var forceWidthSetting = null;
         if ($(this).hasClass('forceWidth')) {
             forceWidthSetting = $(this).width();
         }
         $(this).css('width', 'auto');
         var subGroupWidth = $(this).width();
         //console.log('Setting subGroup min-Width to ' + subGroupWidth);
         $(this).css('min-width', subGroupWidth);
         if (forceWidthSetting !== null) {
         //console.log('setting width to forced width ' + forceWidthSetting);
             $(this).width(forceWidthSetting);
         }
     });
     
     $('.annotationGroup').each(function(){
         var forceWidthSetting = null;
         if ($(this).hasClass('forceWidth')) {
             forceWidthSetting = $(this).width();
         }
         $(this).css('width', 'auto');
         $(this).css('min-width', '');
         var groupWidth = $(this).width();
                  //console.log('Setting Group min-Width to: ' + groupWidth);
         $(this).css('min-width', groupWidth);
         if (forceWidthSetting !== null) {
            //console.log('setting group to forced width ' + forceWidthSetting);
             $(this).width(forceWidthSetting);
         }
     });
     $('.groupText, .subGroupText').show();
     $('#taskWrapper .task').hide();
         dynamicSizing(icDisplayedTask);



     $('#progressTrackerItemWrapper')
             .tipTip({defaultPosition: "right"});
     $tagJavaScriptString

        $('#taskWrapper').css('display', 'block');
        $('#progressTrackerItem1').addClass('currentProgressTrackerItem');
        $('#progressTrackerItem1Content').css('display', 'inline');
        $('#task1Header').css('display', 'block');
        $('#task1').css('display', 'block');
        icDisplayedTask = 1;
        //setMinGroupHeaderWidth(icDisplayedTask);

     $(window).resize(function() {
         dynamicSizing(icDisplayedTask);
     });

   $('a').each(function () {
        $(this).click(function (e) {
        e.preventDefault();
    });
   });

   $('#refreshButton').click(function() {
       location.reload(true);
   });

JS;
