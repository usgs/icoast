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
//$userData = retrieve_entity_metadata($DBH, 181, 'user');
$userId = $userData['user_id'];
$authCheckCode = $userData['auth_check_code'];

$url = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$queryString = htmlentities($_SERVER['QUERY_STRING']);
$postData = '';
$firstValue = true;
foreach ($_POST as $postField => $postValue)
{
    urlencode($postField);
    urldecode($postValue);
    if (!$firstValue)
    {
        $postData .= '&';
    }
    $postData .= $postField . '=' . $postValue;
    $firstValue = false;
}
$clientAgent = $_SERVER['HTTP_USER_AGENT'];

$filtered = true;

$projectId = "";
if (empty($_POST['projectId']) && empty($_GET['projectId']))
{
    header("location: start.php");
    exit;
}
else
{
    if (!empty($_POST['projectId']))
    {
        $projectId = $_POST['projectId'];
    }
    else
    {
        $projectId = $_GET['projectId'];
    }

    settype($projectId,
            'integer');
    if (!empty($projectId))
    {
        $projectMetadata =
            retrieve_entity_metadata($DBH,
                                     $projectId,
                                     'project');
    }
    $access = false;
    if (!$projectMetadata)
    {
        header("location: start.php");
        exit;
    }
    elseif (!$projectMetadata['is_public'])
    {
        $groupProjectAccessQuery = <<<MySQL
		SELECT DISTINCT 
			ugm.project_id
		FROM 
			user_groups ug
		LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
		LEFT JOIN projects p ON ugm.project_id = p.project_id
		WHERE 
			ug.user_id = $userId AND
			ugm.is_enabled = 1 AND
			p.is_complete = 1 
MySQL;
        $STH =
            run_prepared_query($DBH,
                               $groupProjectAccessQuery,
                               array('userId' => $userId));

        while ($groupProjectAccess = $STH->fetchColumn())
        {
            if ($groupProjectAccess == $projectId)
            {
                $access = true;
            }
        }
    }
    else
    {
        $access = true;
    }
    if (!$access)
    {
        header("location: start.php");
        exit;
    }
}

//////////
// => No Image ID Page Redirect
// => If the page has been called without a random image id in the query string then generate
// => an image id and redirect back to the page with a string attached.
if (empty($_GET['imageId']))
{
    $postImageId =
        random_post_image_id_generator($DBH,
                                       $projectId,
                                       $filtered,
                                       $projectMetadata['post_collection_id'],
                                       $projectMetadata['pre_collection_id'],
                                       $userId,
                                       true);
    if ($postImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty')
    {
//        print 'All group images completed.';
        $postImageId =
            random_post_image_id_generator($DBH,
                                           $projectId,
                                           $filtered,
                                           $projectMetadata['post_collection_id'],
                                           $projectMetadata['pre_collection_id'],
                                           $userId);
    }
    if ($postImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty') {
//        print 'All regular images completed';
        exit("An error was detected while generating a new image. $postImageId");
    }
    header("location: classification.php?projectId=$projectId&imageId=$postImageId");
    exit();
}

//--------------------------------------------------------------------------------------------------
// Functions to be removed to include
// Build Image Header
function build_image_header($imageMetadata,
                            $header)
{
    $imageLocalTime =
        utc_to_timezone($imageMetadata['image_time'],
                        'H:i:s T',
                        $imageMetadata['longitude']);
    $imageDate =
        utc_to_timezone($imageMetadata['image_time'],
                        'd M Y',
                        $imageMetadata['longitude']);
    $imageLocation =
        build_image_location_string($imageMetadata,
                                    true);
    $imageHeader = <<<EOT
            <p>$header</p>
            <p>$imageDate at $imageLocalTime near $imageLocation</p>

EOT;
    return $imageHeader;
}

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
        $tagText = $tag['text'];
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
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------
// Define required files and initial includes
// Define variables and PHP settings
$revision = 0;
$postImageId = $_GET['imageId'];
If (isset($_GET['preImageId']))
{
    $preImageId = $_GET['preImageId'];
    $specifiedPreImage = 1;
}

// Find match data $imageMatchData
$imageMatchData =
    retrieve_image_match_data($DBH,
                              $projectMetadata['post_collection_id'],
                              $projectMetadata['pre_collection_id'],
                              $postImageId);
$computerMatchImageId = $imageMatchData['pre_image_id'];
if (!isset($preImageId))
{
    $preImageId = $imageMatchData['pre_image_id'];
}

//--------------------------------------------------------------------------------------------------
// Determine if the user has already annotated the displayed image
$annotationExistsQuery = "SELECT * FROM annotations WHERE user_id = :userId AND "
                         . "project_id = :projectId AND image_id = :postImageId";
$annotationExistsParams = array(
    'userId'      => $userId,
    'projectId'   => $projectId,
    'postImageId' => $postImageId
);
$STH =
    run_prepared_query($DBH,
                       $annotationExistsQuery,
                       $annotationExistsParams);
$existingAnnotation = $STH->fetch(PDO::FETCH_ASSOC);
if ($existingAnnotation)
{
    if (!is_null($existingAnnotation['user_match_id']))
    {
        if ($preImageId != $existingAnnotation['user_match_id'] && !isset($specifiedPreImage))
        {
            header("location: classification.php?&projectId=$projectId&imageId=$postImageId&preImageId={$existingAnnotation['user_match_id']}");
        }
    }
}

// Find post image metadata $postImageMetadata
if (!$postImageMetadata =
    retrieve_entity_metadata($DBH,
                             $postImageId,
                             'image')
)
{
    //  Placeholder for error management
    exit("Image $postImageId not found in Database");
}
$postImageLatitude = $postImageMetadata['latitude'];
$postImageLongitude = $postImageMetadata['longitude'];
$postImageLocation =
    build_image_location_string($postImageMetadata,
                                true);

// Find pre image metadata $preImageMetadata
if (!$preImageMetadata =
    retrieve_entity_metadata($DBH,
                             $preImageId,
                             'image')
)
{
    //  Placeholder for error management
    exit("Image $preImageId not found in Database");
}

If (isset($_GET['sessId']))
{
    $annotationSessionId = $_GET['sessId'];
}
else
{
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
$postImageTitle =
    build_image_header($postImageMetadata,
                       $projectMetadata['post_image_header']);
$postImageAltTagHTML = "An oblique image of the $postImageLocation coastline.";

$preDetailedImageURL = $preImageMetadata['full_url'];
$preDisplayImageURL = "images/collections/{$preImageMetadata['collection_id']}/main/{$preImageMetadata['filename']}";
$preImageTitle =
    build_image_header($preImageMetadata,
                       $projectMetadata['pre_image_header']);
$preImageAltTagHTML =
    "An oblique image of the " .
    build_image_location_string($preImageMetadata,
                                true) .
    " coastline.";


//--------------------------------------------------------------------------------------------------
// Build an array of selected tags in any existing annotation
if ($existingAnnotation)
{
    $existingTags = array();
    $existingComments = array();

    $tagSelectionQuery = "SELECT * FROM annotation_selections "
                         . "WHERE annotation_id = :annotationId";
    $tagSelectionParams['annotationId'] = $existingAnnotation['annotation_id'];
    $STH =
        run_prepared_query($DBH,
                           $tagSelectionQuery,
                           $tagSelectionParams);

    while ($existingSelection = $STH->fetch(PDO::FETCH_ASSOC))
    {
        $existingTags[] = $existingSelection['tag_id'];
    }

    $tagCommentQuery = "SELECT * FROM annotation_comments"
                       . " WHERE annotation_id = {$existingAnnotation['annotation_id']}";
    $tagCommentParams['annotationId'] = $existingAnnotation['annotation_id'];
    $STH =
        run_prepared_query($DBH,
                           $tagCommentQuery,
                           $tagCommentParams);

    while ($existingComment = $STH->fetch(PDO::FETCH_ASSOC))
    {
        $existingComments[$existingComment['tag_id']] = $existingComment['comment'];
    }
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
        $annotations[$taskId] = array(
            'title'  => $task['display_title'],
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
                    $annotations[$taskId]['groups'][$tagGroupId] = array(
                        'text'       => $tagGroupMetadata[0]['display_text'],
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
                                    $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = array(
                                        'text'       => $groupGroupMetadata[0]['display_text'],
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
                                                $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                ['tags'][$tagId] = array(
                                                    'id'                 => $tagMetadata[0]['tag_id'],
                                                    'comment'            => $tagMetadata[0]['is_comment_box'],
                                                    'radio'              => $tagMetadata[0]['is_radio_button'],
                                                    'radioGroup'         => $tagMetadata[0]['radio_button_group'],
                                                    'text'               => $tagMetadata[0]['display_text'],
                                                    'tooltipText'        => $tagMetadata[0]['tooltip_text'],
                                                    'tooltipImage'       => $tagMetadata[0]['tooltip_image'],
                                                    'tooltipImageWidth'  => $tagMetadata[0]['tooltip_image_width'],
                                                    'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                                    'userSelected'       => false
                                                );
                                                if ($existingAnnotation)
                                                {
                                                    if ($tagMetadata[0]['is_comment_box'] == true)
                                                    {
                                                        if (array_key_exists($tagId,
                                                                             $existingComments))
                                                        {
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                            ['tags'][$tagId]['userSelected'] = true;
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                            ['tags'][$tagId]['userComment'] = $existingComments[$tagId];
                                                        }
                                                    }
                                                    else
                                                    {
                                                        $key =
                                                            array_search($tagId,
                                                                         $existingTags);
                                                        if ($key !== false)
                                                        {
                                                            $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                                            ['tags'][$tagId]['userSelected'] = true;
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
                                    $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = array(
                                        'id'                 => $tagMetadata[0]['tag_id'],
                                        'comment'            => $tagMetadata[0]['is_comment_box'],
                                        'radio'              => $tagMetadata[0]['is_radio_button'],
                                        'radioGroup'         => $tagMetadata[0]['radio_button_group'],
                                        'text'               => $tagMetadata[0]['display_text'],
                                        'tooltipText'        => $tagMetadata[0]['tooltip_text'],
                                        'tooltipImage'       => $tagMetadata[0]['tooltip_image'],
                                        'tooltipImageWidth'  => $tagMetadata[0]['tooltip_image_width'],
                                        'tooltipImageHeight' => $tagMetadata[0]['tooltip_image_height'],
                                        'userSelected'       => false
                                    );
                                    if ($existingAnnotation)
                                    {
                                        if ($tagMetadata[0]['is_comment_box'] == true)
                                        {
                                            if (array_key_exists($tagId,
                                                                 $existingComments))
                                            {
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                ['userSelected'] = true;
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                ['userComment'] = $existingComments[$tagId];
                                            }
                                        }
                                        else
                                        {
                                            $key =
                                                array_search($tagId,
                                                             $existingTags);
                                            if ($key !== false)
                                            {
                                                $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                                ['userSelected'] = true;
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
for ($i = 1; $i <= $taskCount; $i++)
{
    $progressTrackerItems .= <<<EOT
                <div id="progressTrackerItem{$i}" class="progressTrackerItem">
                  <p id="progressTrackerItem{$i}Content">{$i}</p>
                </div>

EOT;
}


//--------------------------------------------------------------------------------------------------
// Build thumbnail data.
$thumbnailArray =
    find_adjacent_images($DBH,
                         $computerMatchImageId,
                         null,
                         null,
                         3,
                         20);

//--------------------------------------------------------------------------------------------------
// Build thumbnail HTML
$thumbnailHtml = '';
$noMatchThumbnailHTML = '';
$jsThumbnailMapScript = '';
$navThumbnailTitle =
    "Click this thumbnail to see if this pre-storm photo along the coast better matches the post-storm photo on the right. Is this a better match than what the computer found?";
$currentImageTitle =
    "This thumbnail is the pre-storm photo currently displayed above. If this pre-storm photo best matches the post-storm photo on the left, then click the Confirm Match button.";
$computerMatchTitle = "This thumbnail is the closest pre-storm photo the computer found. Can you find a better match?";
$noMoreImagesTitle =
    "You have reached the end of this collection. There are no more images to display in this direction.";


for ($i = 0; $i < count($thumbnailArray); $i++)
{
    if ($thumbnailArray[$i]['image_id'] != 0)
    {

        $noMatchThumbnailHTML .= <<<EOL
            <div class="matchPhotoWrapper">
                <img src="{$thumbnailArray[$i]['thumb_url']}" height="72" width="108">
            </div>
EOL;

        $locationString =
            build_image_location_string($thumbnailArray[$i],
                                        true);
        $thumbnailClass = '';
        $thumbnailText = '';
        if ($thumbnailArray[$i]['image_id'] == $computerMatchImageId &&
            $thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id']
        )
        {
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
        }
        else
        {
            if ($thumbnailArray[$i]['image_id'] == $computerMatchImageId)
            {
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
            }
            else
            {
                if ($thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id'])
                {
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
                }
                else
                {
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
            }
        }

        $thumbnailHtml .= <<<EOL
            <div class="navThumbnailWrapper">
                <a href="classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i]['image_id']}&sessId=$annotationSessionId">
                    <img id="thumbnail$i" class="$thumbnailClass" src="{$thumbnailArray[$i]['thumb_url']}" alt="An oblique image of the $locationString coastline.">
                </a>
                <p>$thumbnailText</p>
            </div>
EOL;

        if ($thumbnailArray[$i]['image_id'] == $preImageMetadata['image_id'])
        {
            if ($i != 0 && $thumbnailArray[$i - 1]['image_id'] != 0)
            {
                $jQueryDocumentDotReadyCode .= <<<EOL
            $('#leftButton').click(function() {
                window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i -
                                                                                                                                  1]['image_id']}&sessId=$annotationSessionId");
           });

EOL;
            }
            else
            {
                $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#leftButton').addClass('disabledClickableButton');
                    $('#leftButton').attr('disabled', 'disabled');

EOL;
            }
            if ($i != (count($thumbnailArray) - 1) && $thumbnailArray[$i + 1]['image_id'] != 0)
            {
                $jQueryDocumentDotReadyCode .= <<<EOL
            $('#rightButton').click(function() {
                window.location.replace("classification.php?projectId=$projectId&imageId=$postImageId&preImageId={$thumbnailArray[$i +
                                                                                                                                  1]['image_id']}&sessId=$annotationSessionId");
           });

EOL;
            }
            else
            {
                $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#rightButton').addClass('disabledClickableButton');
                    $('#rightButton').attr('disabled', 'disabled');

EOL;
            }
        }
    }
    else
    {

    }
}

//--------------------------------------------------------------------------------------------------
// Build the tasks html string from the tasks array.
$taskHtmlString = "";
$tagJavaScriptString = "";
$taskCounter = 0;
foreach ($annotations as $task)
{
    $taskCounter++;
    $taskTitle = $task['title'];
    $groups = $task['groups'];
    $hiddenDataHTML = '';
    if ($taskCounter == 1)
    {
        $hiddenDataHTML = '<input type="hidden" name="preImageId" value="' . $preImageId . '">';
    }
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
                        $subGroupText .= '<br><span class="tagInstruction">(choose any that apply)</span>';
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
                    $groupText .= '<br><span class="tagInstruction">(choose any that apply)</span>';
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
              <input id="returnToMatchingButton" class="clickableButton" type="button" value="RETURN TO PHOTO MATCHING" title="">

EOT;
    }

    if ($taskCounter < count($annotations))
    {
        $taskHtmlString .= <<<EOT
              <input id="task{$taskCounter}NextButton" class="clickableButton" type="button" value="NEXT TASK" title="Click to go to the NEXT task.">

EOT;
    }
    else
    {
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


$javaScript = <<<JS
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
        
        if (maxTaskHeight > 330) {
            $('#annotationWrapper').css('min-height', maxTaskHeight + 'px');
        } else {
            maxTaskHeight = 330;
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
    
    function loggingError(errorText) {
        $('#popupWrapperParent').css('display', 'table');
        $('#annotationError').empty().text(errorText);
        $('#loggingError').show();
        
    }

    $(window).bind("load", function() {
        dynamicSizing(icDisplayedTask);
    });
JS;

$jQueryDocumentDotReadyCode .= <<<JS

    $('#reloadPhotoButton').on('click', function()
        {
            window.location.href = 'classification.php?projectId=$projectId&imageId=$postImageId';
        }
    );
    
    $('#startButton').on('click', function()
        {
            window.location.href = 'start.php';
        }
    );

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
        //console.log(formData);
          $.post('ajax/annotationLogger.php', formData, function(returnData){
                if (!returnData.result) {
                    loggingError(returnData.details);
                }        
          }, 'json');

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
        //$('#task1Header').css('display', 'block');
        $('#task1').css('display', 'block');
        icDisplayedTask = 1;
        //setMinGroupHeaderWidth(icDisplayedTask);
    });


    $('#returnToMatchingButton').click(function() {
        var formData = $('.annotationForm').serialize();
        formData += '$annotationMetaDataQueryString';
        //console.log(formData);
          $.post('ajax/annotationLogger.php', formData, function(returnData){
                if (!returnData.result) {
                    loggingError(returnData.details);
                }       
          }, 'json');

        $('#taskWrapper').css('display', 'none');
        $('#task1').css('display', 'none');
        $('#progressTrackerItem1').removeClass('currentProgressTrackerItem');
        $('#progressTrackerItem1Content').css('display', 'none');
        //$('#task1Header').css('display', 'none');

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

JS;


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
            $jQueryDocumentDotReadyCode .= <<<JS
                $('#task{$i}NextButton').click(function() {
                console.log('Click next = 1');
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  //console.log(formData);
                  $.post('ajax/annotationLogger.php', formData, function(returnData){
                        if (!returnData.result) {
                            loggingError(returnData.details);
                        }       
                  }, 'json');
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  //$('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
                  //$('#task{$j}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'none');
                  icDisplayedTask++;
//                  setMinGroupHeaderWidth(icDisplayedTask);
console.log('END Click next = 1');
                });

JS;
        }
        else
        {

            $jQueryDocumentDotReadyCode .= <<<JS
                $('#task{$i}NextButton').click(function() {
                console.log('Click next != 1');
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  //console.log(formData);
                  $.post('ajax/annotationLogger.php', formData, function(returnData){
                        if (!returnData.result) {
                            loggingError(returnData.details);
                        }        
                  }, 'json');
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  //$('#task{$i}Header').css('display', 'none');
                  $('#task{$j}').css('display', 'block');
                  $('#progressTrackerItem{$j}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$j}Content').css('display', 'inline');
                  //$('#task{$j}Header').css('display', 'block');
                  icDisplayedTask++;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

JS;
        }
    }

    // Annotation Previous Navigation Button
    if ($i > 1)
    {

        if ($i == 2)
        {
            $jQueryDocumentDotReadyCode .= <<<JS
                $('#task{$i}PreviousButton').click(function() {
                console.log('Click previous == 2');
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  //console.log(formData);
                  $.post('ajax/annotationLogger.php', formData, function(returnData){
                        if (!returnData.result) {
                            loggingError(returnData.details);
                        }             
                  }, 'json');
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  //$('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
                  //$('#task{$h}Header').css('display', 'block');
                  $('#preNavigationCenteringWrapper').css('display', 'block');
                  icDisplayedTask--;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

JS;
        }
        else
        {
            $jQueryDocumentDotReadyCode .= <<<JS
                $('#task{$i}PreviousButton').click(function() {
                console.log('Click previous != 2');
                  var formData = $('.annotationForm').serialize();
                  formData += '$annotationMetaDataQueryString';
                  //console.log(formData);
                  $.post('ajax/annotationLogger.php', formData, function(returnData){
                        if (!returnData.result) {
                            loggingError(returnData.details);
                        }             
                  }, 'json');
                  $('#task{$i}').css('display', 'none');
                  $('#progressTrackerItem{$i}').removeClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$i}Content').css('display', 'none');
                  //$('#task{$i}Header').css('display', 'none');
                  $('#task{$h}').css('display', 'block');
                  $('#progressTrackerItem{$h}').addClass('currentProgressTrackerItem');
                  $('#progressTrackerItem{$h}Content').css('display', 'inline');
                  //$('#task{$h}Header').css('display', 'block');
                  icDisplayedTask--;
//                  setMinGroupHeaderWidth(icDisplayedTask);
                });

JS;
        }
    }
}
$jQueryDocumentDotReadyCode .= <<<JS
    $('#submitButton').click(function() {
      var formData = $('.annotationForm').serialize();
      formData += '$annotationMetaDataQueryString';
      formData += '&annotationComplete=1';
      //console.log(formData);
      $.post('ajax/annotationLogger.php', formData, function(returnData) {
        if (!returnData.result) {
            loggingError(returnData.details);
        } else {  
            window.location.href = 'complete.php?projectId=$projectId&imageId=$postImageId';
        }
      }, 'json');
    });

JS;

$jQueryDocumentDotReadyCode .= "icTaskMap = new Array();\n";
$jsTaskCount = 1;
foreach ($annotations as $task)
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

    $(window).scroll(function(){
        iCoastTitle();
    });
     initializeMaps();
     $('#progressTrackerItemWrapper, .thumbnail, .zoomLoadingIndicator')
             .tipTip({defaultPosition: "right"});
     $tagJavaScriptString

     var databaseAnnotationInitialization = 'startClassification=1$annotationMetaDataQueryString';
     //console.log(databaseAnnotationInitialization);
       $.post('ajax/annotationLogger.php', databaseAnnotationInitialization, function(returnData){
            if (!returnData.result) {
                window.location.href = 'classification.php?projectId=$projectId&imageId=$postImageId';
            }      
      }, 'json');
     
     
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
    $(window).scrollTop($('#navigationBar').position().top);
    map.fitBounds(thumbnailLayer.getBounds(), {'padding': [25 ,10]});

     $(window).resize(function() {
         dynamicSizing(icDisplayedTask);
         map.setView(postImageLatLon);
         iCoastTitle();
     });

JS;
