<?php
//////////
// => Define required files and initial includes
require_once('../iCoastSecure/DBMSConnection.php');
require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
//////////
$filtered = TRUE;
$projectId = 1;

//////////
// => No Image ID Page Redirect
// => If the page has been called without a random image id in the query string then generate
// => an image id and redirect back to the page with a string attached.
if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
  header('Location: login.php');
  exit;
}
$userId = escape_string($_COOKIE['userId']);
$authCheckCode = escape_string($_COOKIE['authCheckCode']);
$authQuery = "SELECT * FROM users WHERE user_id = '$userId' AND auth_check_code = '$authCheckCode' LIMIT 1";
$authMysqlResult = run_database_query($authQuery);
if ($authMysqlResult && $authMysqlResult->num_rows == 0) {
  header('Location: login.php');
  exit;
}
$userData = $authMysqlResult->fetch_assoc();
$userName = $userData['email'];
$permissionLevel = $userData['account_type'];
$authCheckCode = md5(rand());
$query = "UPDATE users SET auth_check_code = '$authCheckCode', last_logged_in_on = now() "
        . "WHERE user_id = '$userId'";
$mysqlResult = run_database_query($query);
if ($mysqlResult) {
  setcookie('userId', $userId, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
  setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
} else {
  $error = true;
  $bodyHTML .= <<<EOL
          <p>Appliaction Failure. Unable to contact database. Please try again in a few minutes or advise an administrator of this problem.</p>
          <form action="login.php" method="post">
            <input type="submit" value="Login / Register using Google" />
          </form>

EOL;
}

// Build up project data
$projectMetadataQuery = "SELECT * FROM projects WHERE project_id = $projectId";
$projectMetadataResult = run_database_query($projectMetadataQuery);
if ($projectMetadataResult && $projectMetadataResult->num_rows > 0) {
  $projectMetadata = $projectMetadataResult->fetch_assoc();
}
$projectMetadata['projectId'] = $projectId;

$ownerQuery = "SELECT email FROM users WHERE user_id = {$projectMetadata['owner']}";
$ownerResult = run_database_query($ownerQuery);
if ($ownerResult && $ownerResult->num_rows > 0) {
  $ownerArray = $ownerResult->fetch_assoc();
}
$projectMetadata['owner_name'] = $ownerArray['email'];

$postCollectionQuery = "SELECT name FROM collections WHERE collection_id = {$projectMetadata['post_collection_id']}";
$postCollectionResult = run_database_query($postCollectionQuery);
if ($postCollectionResult && $postCollectionResult->num_rows > 0) {
  $postCollectionArray = $postCollectionResult->fetch_assoc();
}
$projectMetadata['post_collection_name'] = $postCollectionArray['name'];

$preCollectionQuery = "SELECT name FROM collections WHERE collection_id = {$projectMetadata['pre_collection_id']}";
$preCollectionResult = run_database_query($preCollectionQuery);
if ($preCollectionResult && $preCollectionResult->num_rows > 0) {
  $preCollectionArray = $preCollectionResult->fetch_assoc();
}
$projectMetadata['pre_collection_name'] = $preCollectionArray['name'];

//---------------------------------------------------------------------------------


function build_project_form_html($projectId) {


  // Build up project data
  $projectMetadataQuery = "SELECT * FROM projects WHERE project_id = $projectId";
  $projectMetadataResult = run_database_query($projectMetadataQuery);
  if ($projectMetadataResult && $projectMetadataResult->num_rows > 0) {
    $projectMetadata = $projectMetadataResult->fetch_assoc();
  }
  $projectMetadata['projectId'] = $projectId;

  $ownerQuery = "SELECT email FROM users WHERE user_id = {$projectMetadata['owner']}";
  $ownerResult = run_database_query($ownerQuery);
  if ($ownerResult && $ownerResult->num_rows > 0) {
    $ownerArray = $ownerResult->fetch_assoc();
  }
  $projectMetadata['owner_name'] = $ownerArray['email'];

  $postCollectionQuery = "SELECT name FROM collections WHERE collection_id = {$projectMetadata['post_collection_id']}";
  $postCollectionResult = run_database_query($postCollectionQuery);
  if ($postCollectionResult && $postCollectionResult->num_rows > 0) {
    $postCollectionArray = $postCollectionResult->fetch_assoc();
  }
  $projectMetadata['post_collection_name'] = $postCollectionArray['name'];

  $preCollectionQuery = "SELECT name FROM collections WHERE collection_id = {$projectMetadata['pre_collection_id']}";
  $preCollectionResult = run_database_query($preCollectionQuery);
  if ($preCollectionResult && $preCollectionResult->num_rows > 0) {
    $preCollectionArray = $preCollectionResult->fetch_assoc();
  }
  $projectMetadata['pre_collection_name'] = $preCollectionArray['name'];



  $projectId = $projectMetadata['projectId'];
  $projectName = $projectMetadata['name'];
  $projectDescription = $projectMetadata['description'];
  $projectOwner = $projectMetadata['owner'];
  $projectOwnerName = $projectMetadata['owner_name'];
  $postCollectionId = $projectMetadata['post_collection_id'];
  $preCollectionId = $projectMetadata['pre_collection_id'];
  $postImageHeader = $projectMetadata['post_image_header'];
  $preImageHeader = $projectMetadata['pre_image_header'];
  $projectEnabled = $projectMetadata['is_public'];
  $projectFormHTML = <<<EOL
<div class="projectFormContainer">
  <h1>Project Metadata</h1>
  <form method="post" action="">
    <table>
      <input type="hidden" name="submissionType" value="project">
      <input type="hidden" name="projectId" value="$projectId">
      <input type="hidden" name="owner" value="$projectOwner">
      <input type="hidden" name="postCollectionId" value="$postCollectionId">
      <input type="hidden" name="preCollectionId" value="$preCollectionId">
      <input type="hidden" name="isPublic" value="$projectEnabled">
      <tr>
        <td><label for="projectName" title="The name that appears in the welcome screen selection drop down.">Project Name: </label></td>
        <td><input type="textbox" size="50" name="projectName" id="projectName" value="$projectName"><br></td>
      </tr>
      <tr>
        <td><label for="projectDescription" title="For admin reference only. Not displayed.">Project Description: </label></td>
        <td><textarea name="projectDescription" id="projectDescription" rows="4" cols="50">$projectDescription</textarea><br></td>
      </tr>
      <tr>
        <td><label for="postImageHeader" title="The title text that appears below the post storm image.">Post Image Title: </label></td>
        <td><input type="textbox" size="50" name="postImageHeader" id="postImageHeader" value="$postImageHeader"><br></td>
      </tr>
      <tr>
        <td><label for="preImageHeader" title="The title that appears below the pre storm image.">Pre Image Title: </label></td>
        <td><input type="textbox" size="50" name="preImageHeader" id="preImageHeader" value="$preImageHeader"><br></td>
      </tr>
      <tr>
       <td><input type="submit" value="Update Project"></td>
      </tr>
    </table>
  </form>
</div>
EOL;
  return $projectFormHTML;
}

function taskForm($projectId, $taskId, $taskEnabled, $taskName, $taskDescription, $taskOrderInProject, $taskDisplayTitle, $taskDisplayText) {
  $functionHTML = "<div class=\"taskFormContainer\">";
  if ($taskEnabled == 1) {
    $functionHTML .= "<h1 style=\"color:green\">Group: $taskName</h1>";
  } else {
    $functionHTML .= "<h1 style=\"color:red\">Group: $taskName</h1>";
  }
  $functionHTML .= <<<EOL
        <form method="post" action="">
          <input type="hidden" name="submissionType" value="task">
          <input type="hidden" name="taskId" value="$taskId">
          <input type="hidden" name="projectId" value="$projectId">
          <table>
            <tr>
              <td><label for="taskName" title="For admin reference only. Not displayed.">Task Name: </label></td>
              <td><input type="textbox" size="50" name="taskName" id="taskName" value="$taskName"><br></td>
            </tr>
            <tr>
              <td><label for="taskDescription" title="For admin reference only. Not displayed.">Task Description: </label></td>
              <td><textarea name="taskDescription" id="taskDescription" rows="4" cols="50">$taskDescription</textarea><br></td>
            </tr>
            <tr>
              <td><label for="taskOrderInProject" title="The order of the task in the project. Must be a whole number. If changing one task then all the others should be changed too to create a new order. Doesn't have to be contiguous numbers.">Order In Project: </label></td>
              <td><input type="textbox" size="5" name="taskOrderInProject" id="taskOrderInProject" value="$taskOrderInProject"><br></td>
            </tr>
            <tr>
              <td><label for="taskDisplayTitle" title="The task title text displayed at the top of each task.">UI Display Title: </label></td>
              <td><input type="textbox" size="80" name="taskDisplayTitle" id="taskDisplayTitle" value="$taskDisplayTitle"><br></td>
            </tr>

            <tr>
              <td><label for="taskEnabled" title="Select ENABLED if this task should be displayed in iCoast. DISABLED if not.">Task Enabled:</label></td>
              <td>
EOL;
//  <tr>
//  <td><label for = "taskDisplayText" title = "The general task text displayed in each task before any groups (task description). This might be disabled, therfore inoperative. Can't remember!">UI Display Text</label></td>
//  <td><textarea name = "taskDisplayText" id = "taskDisplayText" rows = "4" cols = "50">$taskDisplayText</textarea><br></td>
//  </tr>

  if ($taskEnabled == 0) {
    $functionHTML .= '<input type="radio" name="taskEnabled" id="taskEnabled" value="0" checked>Disabled<br>';
    $functionHTML .= '<input type="radio" name="taskEnabled" id="taskEnabled" value="1">Enabled<br>';
  } else {
    $functionHTML .= '<input type="radio" name="taskEnabled" id="taskEnabled" value="0">Disabled<br>';
    $functionHTML .= '<input type="radio" name="taskEnabled" id="taskEnabled" value="1" checked>Enabled<br>';
  }

  $functionHTML .= <<<EOL
              </td>
            </tr>
            <tr>
             <td><input type="submit" value="Update Task"></td>
            </tr>
          </table>
        </form>
EOL;

  return $functionHTML;
}

function groupForm($divClass, $tagGroupId, $projectId, $groupEnabled, $groupContainsGroups, $groupName, $groupDescription, $groupDisplayText, $groupImage, $groupWidth, $groupBorder, $groupColor, $groupOrder) {

  $functionHTML = "<div class=\"$divClass\">";
  if ($groupEnabled == 1) {
    $functionHTML .= "<h2 style=\"color:green\">Group: $groupName</h2>";
  } else {
    $functionHTML .= "<h2 style=\"color:red\">Group: $groupName</h2>";
  }
  $functionHTML .= <<<EOL
        <form method="post" action="">
          <input type="hidden" name="submissionType" value="group">
          <input type="hidden" name="tagGroupId" value="$tagGroupId">
          <input type="hidden" name="projectId" value="$projectId">
          <input type="hidden" name="groupImage" value="$groupImage">
          <table>
            <tr>
              <td><label for="groupName" title="For admin reference only. Not displayed.">Group Name: </label></td>
              <td><input type="textbox" size="50" name="groupName" id="groupName" value="$groupName"><br></td>
            </tr>
            <tr>
              <td><label for="groupDescription" title="For admin reference only. Not displayed.">Group Description: </label></td>
              <td><textarea name="groupDescription" id="groupDescription" rows="4" cols="50">$groupDescription</textarea><br></td>
            </tr>
            <tr>
              <td><label for="groupDisplayText" title="The text displayed in the head of each group.">UI Display Text</label></td>
              <td><textarea name="groupDisplayText" id="groupDisplayText" rows="4" cols="50">$groupDisplayText</textarea><br></td>
            </tr>
            <tr>
              <td><label for="groupOrder" title="The order of the group within it's parent (either a task or another group if there are nested groups). If one group is changed then all others within the same container (task or group) shoudl be re-numbered accordingly. Use whole numbers only. Numbers do not have to be contigous.">Order In Parent: </label></td>
              <td><input type="textbox" size="5" name="groupOrder" id="groupOrder" value="$groupOrder"><br></td>
            </tr>
            <tr>
              <td><label for="groupWidth" title="The application will automatically space groups within theiir parent container however their width can be overridden by this setting. Use whole numbers only to denote pixel width. If changing one group then you will probably want to set a fixed width for all other groups in the same container althouth it isn't requred.">Group Width: </label></td>
              <td><input type="textbox" size="10" name="groupWidth" id="groupWidth" value="$groupWidth"><br></td>
            </tr>
            <tr>
              <td><label for="groupColor" title="Use this to set a background color for the group. Requires color in HEX format (no #prefix). Example: A6C854">Group Color: </label></td>
              <td><input type="textbox" size="10" name="groupColor" id="groupColor" value="$groupColor"><br></td>
            </tr>
EOL;

  $functionHTML .= '<tr>';
  $functionHTML .= '<td><label for="groupBorder" title="Puts a border around the group in question.">Group Border:</label></td>';
  $functionHTML .= '<td>';
  if ($groupBorder == 0) {
    $functionHTML .= '<input type="radio" name="groupBorder" id="groupBorder" value="0" checked>Disabled<br>';
    $functionHTML .= '<input type="radio" name="groupBorder" id="groupBorder" value="1">Enabled<br>';
  } else {
    $functionHTML .= '<input type="radio" name="groupBorder" id="groupBorder" value="0">Disabled<br>';
    $functionHTML .= '<input type="radio" name="groupBorder" id="groupBorder" value="1" checked>Enabled<br>';
  }
  $functionHTML .= '</td>';
  $functionHTML .= '</tr>';


  $functionHTML .= '<tr>';
  $functionHTML .= '<td><label for="groupContainsGroups" title="This flag denotes of a group contains sub groups or tags. best left alone as you have no way of generating new tags or groups should you change this setting.">Group Contains: </label></td>';
  $functionHTML .= '<td>';
  if ($groupContainsGroups == 1) {
    $functionHTML .= '<input type="radio" name="groupContainsGroups" id="groupContainsGroups" value="0">Tags<br>';
    $functionHTML .= '<input type="radio" name="groupContainsGroups" id="groupContainsGroups" value="1" checked>Other Groups<br>';
  } else {
    $functionHTML .= '<input type="radio" name="groupContainsGroups" id="groupContainsGroups" value="0" checked>Tags<br>';
    $functionHTML .= '<input type="radio" name="groupContainsGroups" id="groupContainsGroups" value="1">Other Groups<br>';
  }
  $functionHTML .= '</td>';
  $functionHTML .= '</tr>';


  $functionHTML .= '<tr>';
  $functionHTML .= '<td><label for="groupEnabled" title="Set to ENABLED to display the group in the user interface; DISABLED to hide it.">Group Enabled:</label></td>';
  $functionHTML .= '<td>';
  if ($groupEnabled == 0) {
    $functionHTML .= '<input type="radio" name="groupEnabled" id="groupEnabled" value="0" checked>Disabled<br>';
    $functionHTML .= '<input type="radio" name="groupEnabled" id="groupEnabled" value="1">Enabled<br>';
  } else {
    $functionHTML .= '<input type="radio" name="groupEnabled" id="groupEnabled" value="0">Disabled<br>';
    $functionHTML .= '<input type="radio" name="groupEnabled" id="groupEnabled" value="1" checked>Enabled<br>';
  }
  $functionHTML .= '</td>';
  $functionHTML .= '</tr>';

  $functionHTML .= <<<EOL
            <tr>
             <td><input type="submit" value="Update Group"></td>
            </tr>
          </table>
        </form>
EOL;

  return $functionHTML;
}

function tagForm($divClass, $tagId, $projectId, $tagEnabled, $isCommentBox, $isRadioButton, $radioButtonGroup, $name, $description, $displayText, $displayImage, $tooltipText, $tooltipImage, $tooltipImageWidth, $tooltipImageHeight, $orderInGroup) {
  $functionHTML = "<div class=\"$divClass\">";
  if ($tagEnabled == 1) {
    $functionHTML .= "<h3 style=\"color:green\">Tag: $name</h3>";
  } else {
    $functionHTML .= "<h3 style=\"color:red\">Tag: $name</h3>";
  }
  $functionHTML .= <<<EOL
        <form method="post" action="">
          <input type="hidden" name="submissionType" value="tag">
          <input type="hidden" name="tagId" value="$tagId">
          <input type="hidden" name="projectId" value="$projectId">
          <input type="hidden" name="tagImage" value="$displayImage">
          <table>
            <tr>
              <td><label for="tagName" title="For admin reference only. Not displayed.">Tag Name: </label></td>
              <td><input type="textbox" size="50" name="tagName" id="tagName" value="$name"><br></td>
            </tr>
            <tr>
              <td><label for="tagDescription" title="For admin reference only. Not displayed.">Tag Description: </label></td>
              <td><textarea name="tagDescription" id="tagDescription" rows="4" cols="50">$description</textarea><br></td>
            </tr>
EOL;

  $functionHTML .= '<tr>';
  $functionHTML .= '<td><label for="tagType" title="Select the type of tag this tag should be. If set to RADIO be sure to assign the same Radio Button Group Name in the next field to all buttons that should be in the same button group.">TagType:</label></td>';
  $functionHTML .= '<td>';
  if ($isCommentBox == 1) {
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="comment" checked>Comment Box<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="radio">Radio Button (Select one)<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="check">Multi Choice (Select many)<br>';
  } elseif ($isRadioButton == 1) {
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="comment">Comment Box<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="radio" checked>Radio Button (Select one)<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="check">Multi Choice (Select many)<br>';
  } else {
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="comment">Comment Box<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="radio">Radio Button (Select one)<br>';
    $functionHTML .= '<input type="radio" name="tagType" id="tagType" value="check" checked>Multi Choice (Select many)<br>';
  }
  $functionHTML .= '</td>';
  $functionHTML .= '</tr>';

  $functionHTML .= <<<EOL
            <tr>
              <td><label for="radioButtonGroup" title="Required for Radio Button Tags. All tags that must be groups so as only one can be selected must have exactly the same Radio Button Name.">Radio Button Group Name: </label></td>
              <td><input type="textbox" size="50" name="radioButtonGroup" id="radioButtonGroup" value="$radioButtonGroup"><br></td>
            </tr>
            <tr>
              <td><label for="tagDisplayText" title="The tag text displayed in the UI.">UI Display Text: </label></td>
              <td><input type="textbox" size="50" name="tagDisplayText" id="tagDisplayText" value="$displayText"><br></td>
            </tr>
            <tr>
              <td><label for="tagTooltipText" title="The tooltip text displayed for this tag.">Tooltip Text: </label></td>
              <td><textarea name="tagTooltipText" id="tagTooltipText" rows="4" cols="50">$tooltipText</textarea><br></td>
            </tr>
            <tr>
              <td><label for="tagTooltipImage" title="The filename of the image to be used for the tooltip.">Tooltip Image Filename: </label></td>
              <td><input type="textbox" size="50" name="tagTooltipImage" id="tagTooltipImage" value="$tooltipImage"><br></td>
            </tr>
            <tr>
              <td><label for="tooltipImageWidth" title="The horizontal dimension in pixels of the tooltip image.">Tooltip Image Width: </label></td>
              <td><input type="textbox" size="10" name="tooltipImageWidth" id="tooltipImageWidth" value="$tooltipImageWidth"><br></td>
            </tr>
            <tr>
              <td><label for="tooltipImageHeight" title="The vertical dimension in pixels of the tooltip image.">Tooltip Image Height: </label></td>
              <td><input type="textbox" size="10" name="tooltipImageHeight" id="tooltipImageHeight" value="$tooltipImageHeight"><br></td>
            </tr>
            <tr>
              <td><label for="tagOrder" title="The order of the tag in its parent group. If changing the order number of one tag all others shoudl be changed accordingly. Whole numbers must be used. Numbers do not have to be contiguous.">Order In Group: </label></td>
              <td><input type="textbox" size="5" name="tagOrder" id="tagOrder" value="$orderInGroup"><br></td>
            </tr>
EOL;

  $functionHTML .= '<tr>';
  $functionHTML .= '<td><label for="tagEnabled" title="Controls the display of the tag in the UI. Set to ENABLED to display the tag. DISABLED to hide the tag.">Tag Enabled:</label></td>';
  $functionHTML .= '<td>';
  if ($tagEnabled == 0) {
    $functionHTML .= '<input type="radio" name="tagEnabled" id="tagEnabled" value="0" checked>Disabled<br>';
    $functionHTML .= '<input type="radio" name="tagEnabled" id="tagEnabled" value="1">Enabled<br>';
  } else {
    $functionHTML .= '<input type="radio" name="tagEnabled" id="tagEnabled" value="0">Disabled<br>';
    $functionHTML .= '<input type="radio" name="tagEnabled" id="tagEnabled" value="1" checked>Enabled<br>';
  }
  $functionHTML .= '</td>';
  $functionHTML .= '</tr>';

  $functionHTML .= <<<EOL
            <tr>
             <td><input type="submit" value="Update Tag"></td>
            </tr>
          </table>
        </form>
      </div>
EOL;

  return $functionHTML;
}

function build_annotation_list_html($projectId) {

  // Build an array of the tasks.
  $taskMetadataQuery = "SELECT * from task_metadata WHERE project_id = $projectId
  ORDER BY order_in_project";
  $taskMetadataResults = run_database_query($taskMetadataQuery);
  if ($taskMetadataResults && $taskMetadataResults->num_rows > 0) {
    $taskMetadata = $taskMetadataResults->fetch_all(MYSQLI_ASSOC);
    foreach ($taskMetadata as $task) {
      $taskId = $task['task_id'];
      $tagStructure[$taskId] = $task;

      $taskContentsQuery = "SELECT * FROM task_contents WHERE task_id = $taskId
      ORDER BY order_in_task";
      $taskContentsResult = run_database_query($taskContentsQuery);
      if ($taskContentsResult && $taskContentsResult->num_rows > 0) {
        $taskContents = $taskContentsResult->fetch_all(MYSQLI_ASSOC);
        foreach ($taskContents as $tagGroupIdArray) {
          $tagGroupId = $tagGroupIdArray['tag_group_id'];
          $orderInTask = $tagGroupIdArray['order_in_task'];
          $tagGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE tag_group_id = $tagGroupId
            AND project_id = $projectId";
          $tagGroupMetadataResults = run_database_query($tagGroupMetadataQuery);
          if ($tagGroupMetadataResults && $tagGroupMetadataResults->num_rows == 1) {
            $tagGroupMetadata = $tagGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
            foreach ($tagGroupMetadata as $value) {
              $tagStructure[$taskId]['groups'][$tagGroupId] = $value;
            }
            $tagStructure[$taskId]['groups'][$tagGroupId]['order_in_task'] = $orderInTask;
//            $tagStructure[$taskId]['groups'][$tagGroupId] = $tagGroupMetadata;



            if ($tagGroupMetadata[0]['contains_groups'] == 1) {
              $groupContentsQuery = "SELECT * FROM tag_group_contents WHERE
                    tag_group_id = $tagGroupId ORDER BY order_in_group";
              $groupContentsResult = run_database_query($groupContentsQuery);
              if ($groupContentsResult && $groupContentsResult->num_rows > 0) {
                $groupContents = $groupContentsResult->fetch_all(MYSQLI_ASSOC);
                foreach ($groupContents as $groupContentsArray) {
                  $groupGroupId = $groupContentsArray['tag_id'];
                  $groupGroupOrderInGroup = $groupContentsArray['order_in_group'];
                  $groupGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE
                  tag_group_id = $groupGroupId AND project_id = $projectId";
                  $groupGroupMetadataResults = run_database_query($groupGroupMetadataQuery);
                  if ($groupGroupMetadataResults && $groupGroupMetadataResults->num_rows == 1) {
                    $groupGroupMetadata = $groupGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
                    foreach ($groupGroupMetadata as $value) {
                      $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = $value;
                    }
                    $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]['order_in_group'] = $groupGroupOrderInGroup;
//                    $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = $groupGroupMetadata;
                    $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]['tags'] = array();
                    $tagGroupContentsQuery = "SELECT * FROM tag_group_contents WHERE
                    tag_group_id = $groupGroupId ORDER BY order_in_group";
                    $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
                    if ($tagGroupContentsResult && $tagGroupContentsResult->num_rows > 0) {
                      $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
                      foreach ($tagGroupContents as $tagIdArray) {
                        $tagId = $tagIdArray['tag_id'];
                        $orderInGroup = $tagIdArray['order_in_group'];
                        $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = $tagId AND
                        project_id = $projectId";
                        $tagMetadataResult = run_database_query($tagMetadataQuery);
                        if ($tagMetadataResult && $tagMetadataResult->num_rows == 1) {
                          $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
                          foreach ($tagMetadata as $value) {
                            $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                    ['tags'][$tagId] = $value;
                          }
//                          $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
//                                  ['tags'][$tagId] = $tagMetadata;
                          $tagStructure[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                  ['tags'][$tagId]['order_in_group'] = $orderInGroup;
                        }
                      }
                    }
                  }
                }
              }
            } else {
              $tagGroupContentsQuery = "SELECT * FROM tag_group_contents WHERE
          tag_group_id = $tagGroupId ORDER BY order_in_group";
              $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
              if ($tagGroupContentsResult && $tagGroupContentsResult->num_rows > 0) {
                $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
                foreach ($tagGroupContents as $tagIdArray) {
                  $tagId = $tagIdArray['tag_id'];
                  $orderInGroup = $tagIdArray['order_in_group'];
                  $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = $tagId AND
                project_id = $projectId";
                  $tagMetadataResult = run_database_query($tagMetadataQuery);
                  if ($tagMetadataResult && $tagMetadataResult->num_rows == 1) {
                    $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
                    foreach ($tagMetadata as $value) {
                      $tagStructure[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = $value;
                    }
//                    $tagStructure[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = $tagMetadata;
                    $tagStructure[$taskId]['groups'][$tagGroupId]['tags'][$tagId]['order_in_group'] = $orderInGroup;
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  $annotationListHTML = "";
  foreach ($tagStructure as $task) {
    $annotationListHTML .= taskForm($task['project_id'], $task['task_id'], $task['is_enabled'], $task['name'], $task['description'], $task['order_in_project'], $task['display_title'], $task['display_text']);
    foreach ($task['groups'] as $group) {
      $annotationListHTML .= groupForm('groupFormContainer', $group['tag_group_id'], $group['project_id'], $group['is_enabled'], $group['contains_groups'], $group['name'], $group['description'], $group['display_text'], $group['display_image'], $group['force_width'], $group['has_border'], $group['has_color'], $group['order_in_task']);

      if ($group['contains_groups'] == 1) {
        foreach ($group['groups'] as $subGroup) {
          $annotationListHTML .= groupForm('groupGroupFormContainer', $subGroup['tag_group_id'], $subGroup['project_id'], $subGroup['is_enabled'], $subGroup['contains_groups'], $subGroup['name'], $subGroup['description'], $subGroup['display_text'], $subGroup['display_image'], $subGroup['force_width'], $subGroup['has_border'], $subGroup['has_color'], $subGroup['order_in_group']);
          foreach ($subGroup['tags'] as $tag) {
            $annotationListHTML .= tagForm('tagFormContainer', $tag['tag_id'], $tag['project_id'], $tag['is_enabled'], $tag['is_comment_box'], $tag['is_radio_button'], $tag['radio_button_group'], $tag['name'], $tag['description'], $tag['display_text'], $tag['display_image'], $tag['tooltip_text'], $tag['tooltip_image'], $tag['tooltip_image_width'], $tag['tooltip_image_height'], $tag['order_in_group']);
          }
          $annotationListHTML .= '</div>';
        }
//      $annotationListHTML .= '</div>';
      } else {
        foreach ($group['tags'] as $tag) {
          $annotationListHTML .= tagForm('tagFormContainer', $tag['tag_id'], $tag['project_id'], $tag['is_enabled'], $tag['is_comment_box'], $tag['is_radio_button'], $tag['radio_button_group'], $tag['name'], $tag['description'], $tag['display_text'], $tag['display_image'], $tag['tooltip_text'], $tag['tooltip_image'], $tag['tooltip_image_width'], $tag['tooltip_image_height'], $tag['order_in_group']);
        }
      }
      $annotationListHTML .= '</div>';
    }
    $annotationListHTML .= '</div>';
  }
  return $annotationListHTML;
}
?>


<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Tag Editor</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--<link rel="stylesheet" href="css/icoast.css">-->
    <style>
      .projectFormContainer {
        border: 2px solid black;
        background-color: #CDADD6;
        padding-bottom: 1em;
        margin-bottom: 1em;
      }

      .taskFormContainer {
        border: 2px solid black;
        background-color: #BBBBE8;
        padding-bottom: 1em;
        margin-bottom: 1em;
      }

      .groupFormContainer {
        margin-left: 75px;
        border-top: 2px solid gray;
        background-color: #C2EFCF;
        padding-bottom: 1em;
      }

      .groupGroupFormContainer {
        margin-left: 75px;
        border-top: 2px solid gray;
        background-color: #E4EDC0;
        padding-bottom: 1em;
      }

      .tagFormContainer {
        margin-left: 75px;
        border-top: 2px solid gray;
        background-color: #EDC0C0;
        padding-bottom: 1em;
      }

      .updateSuccess {
        color: green;
      }

      .updateFailure {
        color: red;
      }


    </style>
  </head>
  <body>
    <?php
    /////////
// => Check user permissions to access the page.
    if ($permissionLevel <= 3) {
      if (isset($_POST['submissionType'])) {
//      $data = array();
//      foreach ($_POST as $key => $value) {
//        $value = htmlspecialchars($value);
//        $data[$key] = escape_string($value);
//      }
//      print '<pre>';
//      print_r($data);
//      print '</pre>';




        foreach ($_POST as $key => $value) {
          $value = htmlspecialchars($value);
          $$key = escape_string($value);
        }

        switch ($_POST['submissionType']) {
          case 'project':
            $updateQuery = "UPDATE projects SET name='$projectName', "
                    . "description='$projectDescription', post_image_header='$postImageHeader', "
                    . "pre_image_header='$preImageHeader' WHERE project_id='$projectId'";
//          print "<br>" . $updateQuery;
            $updateResult = FALSE;
            $updateResult = run_database_query($updateQuery);
            print "<h1>Update Status</h1>";
            if ($updateResult == TRUE) {
              print '<p class="updateSuccess">Update completed sucessfully</p>';
            } else {
              print '<p class="updateFailure">Update failed. No changes made.</p>';
            }
            break;



          case 'task':
            $updateQuery = "UPDATE task_metadata SET name='$taskName', description='$taskDescription', "
                    . "order_in_project='$taskOrderInProject', display_title='$taskDisplayTitle', "
                    . "is_enabled='$taskEnabled' "
                    . "WHERE task_id='$taskId' LIMIT 1";
//          print "<br>" . $updateQuery;
            $updateResult = FALSE;
            $updateResult = run_database_query($updateQuery);
            print "<h1>Update Status</h1>";
            if ($updateResult == TRUE) {
              print '<p class="updateSuccess">Update completed sucessfully</p>';
            } else {
              print '<p class="updateFailure">Update failed. No changes made.</p>';
            }
            break;



          case 'group':
            $updateQuery = "UPDATE tag_group_metadata SET name='$groupName', "
                    . "description='$groupDescription', display_text='$groupDisplayText', "
                    . "force_width='$groupWidth', has_color='$groupColor', has_border='$groupBorder', "
                    . "contains_groups='$groupContainsGroups', is_enabled='$groupEnabled' "
                    . "WHERE tag_group_id='$tagGroupId' LIMIT 1";
//          print "<br>" . $updateQuery;
            $updateResult = FALSE;
            $updateResult = run_database_query($updateQuery);
            print "<h1>Update Status</h1>";
            if ($updateResult == TRUE) {
              print '<p class="updateSuccess">Update to tag_group_metadata table completed '
                      . 'sucessfully.</p><p>Attempting update to task_contents table.</p>';
              $updateQuery = "UPDATE task_contents SET order_in_task='$groupOrder'"
                      . " WHERE tag_group_id='$tagGroupId' LIMIT 1";
//            print "<br>" . $updateQuery;
              $updateResult = FALSE;
              $updateResult = run_database_query($updateQuery);
              if ($updateResult == TRUE) {
                print '<p class="updateSuccess">Update to task_contents table completed sucessfully.'
                        . ' All changes completed sucessfully.</p>';
              } else {
                print '<p class="updateFailure">Update to task_contents table failed. All changes'
                        . 'EXCEPT the group order in the task have been saved.</p>';
              }
            } else {
              print '<p class="updateFailure">Update to tag_group_metadata table failed '
                      . 'Aborting update to task_contents table. No changes made.</p>';
            }
            break;



          case 'tag':

            switch ($tagType) {
              case 'check':
                $tagTypeSql = "is_comment_box='0', is_radio_button='0', ";
                break;
              case 'radio':
                $tagTypeSql = "is_comment_box='0', is_radio_button='1', ";
                break;
              case 'comment':
                $tagTypeSql = "is_comment_box='1', is_radio_button='0', ";
                break;
            }
            $updateQuery = "UPDATE tags SET name='$tagName', description='$tagDescription', "
                    . $tagTypeSql . "radio_button_group='$radioButtonGroup', "
                    . "display_text='$tagDisplayText', tooltip_text='$tagTooltipText', "
                    . "tooltip_image='$tagTooltipImage', tooltip_image_width='$tooltipImageWidth', "
                    . "tooltip_image_height='$tooltipImageHeight', is_enabled='$tagEnabled' "
                    . "WHERE tag_id='$tagId' LIMIT 1";
//          print "<br>" . $updateQuery;
            $updateResult = FALSE;
            $updateResult = run_database_query($updateQuery);
            print "<h1>Update Status</h1>";
            if ($updateResult == TRUE) {
              print '<p class="updateSuccess">Update to tags table completed '
                      . 'sucessfully.</p><p>Attempting update to tag_group_contents table.</p>';
              $updateQuery = "UPDATE tag_group_contents SET order_in_group='$tagOrder'"
                      . " WHERE tag_id='$tagId' LIMIT 1";
//            print "<br>" . $updateQuery;
              $updateResult = FALSE;
              $updateResult = run_database_query($updateQuery);
              if ($updateResult == TRUE) {
                print '<p class="updateSuccess">Update to tag_group_contents table completed '
                        . 'sucessfully. All changes completed sucessfully.</p>';
              } else {
                print '<p class="updateFailure">Update to tag_group_contents table failed. All changes'
                        . 'EXCEPT the tag order in the group have been saved.</p>';
              }
            } else {
              print '<p class="updateFailure">Update to tags table failed '
                      . 'Aborting update to tag_group_contents table. No changes made.</p>';
            }
            break;
        }
      }
      $projectFormHTML = build_project_form_html($projectId);
      print $projectFormHTML;
      $annotationListHTML = build_annotation_list_html($projectId);
      print $annotationListHTML;
    } else {
      print "<p>You do not have sufficient permissions to access this page.</p>";
    }
      ?>
  </body>
</html>


