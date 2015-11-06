<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

ini_set('max_execution_time', 300);

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

function sortByOrderInProject($a, $b) {
    return $a['order_in_project'] - $b['order_in_project'];
}

function buildTaskSelectOptions($DBH, $projectId, $currentParent = false) {
// FIND DATA FOR ALL TASKS IN THE PROJECT
    $projectTaskQuery = "
        SELECT task_id, name, description, is_enabled
        FROM task_metadata
        WHERE project_id = :projectId
        ORDER BY name";
    $projectTaskParams['projectId'] = $projectId;
    $projectTaskResults = run_prepared_query($DBH, $projectTaskQuery, $projectTaskParams);
    $projectTasks = $projectTaskResults->fetchAll(PDO::FETCH_ASSOC);

// BUILD THE TASK SELECTION HTML FORM
    $taskSelectOptionsHTML = '';
    $taskList = array();
    foreach ($projectTasks as $individualTask) {
        $individualTaskId = htmlspecialchars($individualTask['task_id']);
        $individualTaskName = htmlspecialchars($individualTask['name']);
        $individualTaskDescription = restoreSafeHTMLTags(htmlspecialchars($individualTask['description']));
        $isEnabled = $individualTask['is_enabled'];

        $taskSelectOptionsHTML .= "<option title=\"$individualTaskDescription\" value=\"$individualTaskId\"";
        if ($currentParent) {
            if ($individualTask['task_id'] == $currentParent) {
                $taskSelectOptionsHTML .= ' selected="selected"';
            }
        }
        $taskSelectOptionsHTML .= ">$individualTaskName";
        if ($isEnabled == 0) {
            $taskSelectOptionsHTML .= " (Disabled)";
        }

        $taskSelectOptionsHTML .= "</option>";
        $taskList[] = $individualTaskId;
    }
    return array(
        $taskSelectOptionsHTML,
        $taskList);
}

function buildGroupSelectOptions($DBH, $projectId, $currentParent = false, $onlyGroupGroups = false, $excludeGroupGroups = false) {
//    print $currentParent;
// FIND DATA FOR ALL TASKS IN THE PROJECT
    if ($excludeGroupGroups) {
        $projectGroupQuery = "SELECT tag_group_id, name, description, is_enabled FROM tag_group_metadata WHERE project_id = :projectId AND contains_groups = 0 ORDER BY name";
    } else if (!$onlyGroupGroups) {
        $projectGroupQuery = "SELECT tag_group_id, name, description, is_enabled FROM tag_group_metadata WHERE project_id = :projectId ORDER BY name";
    } else {
        $projectGroupQuery = "SELECT tag_group_id, name, description, is_enabled FROM tag_group_metadata WHERE project_id = :projectId AND contains_groups = 1 ORDER BY name";
    }
    $projectGroupParams['projectId'] = $projectId;
    $projectGroupResults = run_prepared_query($DBH, $projectGroupQuery, $projectGroupParams);
    $projectGroups = $projectGroupResults->fetchAll(PDO::FETCH_ASSOC);

// BUILD THE TASK SELECTION HTML FORM
    $groupSelectOptionsHTML = '';
    $groupIdList = array();
    $groupNameList = array();
    foreach ($projectGroups as $individualGroup) {
        $individualGroupId = $individualGroup['tag_group_id'];
        $individualGroupName = $individualGroup['name'];
        $individualGroupDescription = $individualGroup['description'];
        $isEnabled = $individualGroup['is_enabled'];

        $groupSelectOptionsHTML .= "<option title=\"$individualGroupDescription\"value=\"$individualGroupId\"";
        if (!empty($currentParent)) {
            if ($individualGroupId == $currentParent) {
                $groupSelectOptionsHTML .= ' selected="selected"';
            }
        }
        $groupSelectOptionsHTML .= ">$individualGroupName";
        if ($isEnabled == 0) {
            $groupSelectOptionsHTML .= " (Disabled)";
        }
        $groupSelectOptionsHTML .= "</option>";
        $groupIdList[] = $individualGroupId;
        $groupNameList[] = $individualGroupName;
    }
    return array(
        $groupSelectOptionsHTML,
        $groupIdList,
        $groupNameList
    );
}

function groupContentsCheck($DBH, $groupId) {
    $groupHasContentsQuery = 'SELECT COUNT(*) FROM tag_group_contents WHERE tag_group_id = :groupId';
    $groupHasContentsParams['groupId'] = $groupId;
    $groupHasContentsResult = run_prepared_query($DBH, $groupHasContentsQuery, $groupHasContentsParams);
    $numberOfGroupChildren = $groupHasContentsResult->fetchColumn();
    if ($numberOfGroupChildren > 0) {
        return true;
    } else {
        return false;
    }
}

$actionSummaryHTML = '';
$projectUpdateErrorHTML = '';
$instructionHTML = '';
$actionSelctionHTML = '';
$actionControlsHTML = '';
$failedSubmissionHTML = '';
$failedSubmission = FALSE;


$projectToClone = filter_input(INPUT_POST, 'cloneProjectId', FILTER_VALIDATE_INT);
$taskId = filter_input(INPUT_POST, 'taskId', FILTER_VALIDATE_INT);
$tasksCompleteFlag = filter_input(INPUT_POST, 'tasksComplete', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$groupId = filter_input(INPUT_POST, 'groupId', FILTER_VALIDATE_INT);
$tagId = filter_input(INPUT_POST, 'tagId', FILTER_VALIDATE_INT);
$editSubmittedFlag = filter_input(INPUT_POST, 'editSubmitted', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

$projectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
        $projectMetadata['is_complete'] == 1) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$projectPropertyToUpdate = filter_input(INPUT_POST, 'projectPropertyToUpdate');
$projectEditSubAction = filter_input(INPUT_POST, 'projectEditSubAction');
if (isset($projectPropertyToUpdate)) {
    switch ($projectPropertyToUpdate) {
        case 'tasks':
            if (isset($projectEditSubAction) &&
                    $projectEditSubAction !== 'updateExistingTask' &&
                    $projectEditSubAction !== 'createNewTask') {
                $projectEditSubAction = false;
            }
            break;
        case 'groups':
            if (isset($projectEditSubAction) &&
                    $projectEditSubAction !== 'updateExistingGroup' &&
                    $projectEditSubAction !== 'createNewGroup') {
                $projectEditSubAction = false;
            }
            break;
        case 'tags':
            if (isset($projectEditSubAction) &&
                    $projectEditSubAction !== 'updateExistingTag' &&
                    $projectEditSubAction !== 'createNewTag') {
                $projectEditSubAction = false;
            }
            break;
        default:
            $projectPropertyToUpdate = false;
    }
}
$referingUrl = filter_input(INPUT_SERVER, 'HTTP_REFERER', FILTER_VALIDATE_URL);
$referingPage = detect_pageName($referingUrl);
$importStatus = project_creation_stage($projectMetadata['project_id']);

if (($importStatus <= 9 || $importStatus >= 15) &&
        !($referingPage == 'reviewProject' && $importStatus == 50)) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

if ($referingPage == 'reviewProject' && $importStatus == 50) {
    $updateTasksCompleteQuery = '
        UPDATE projects
        SET tasks_complete = 0
        WHERE project_id = :projectId
            AND is_complete = 0
        LIMIT 1
    ';
    $updateTasksCompleteParams['projectId'] = $projectMetadata['project_id'];
    $updateTasksCompleteResult = run_prepared_query($DBH, $updateTasksCompleteQuery, $updateTasksCompleteParams);
    if ($updateTasksCompleteResult->rowCount() != 1) {
        header("Location: projectCreator.php?projectId={$projectMetadata['project_id']
                }&complete");
        exit;
    }
    $importStatus = 10;
}



//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// TASKS COMPLETE CODE
//
//
if ($tasksCompleteFlag && ($importStatus == 10 || $importStatus == 50)) {
    $updateTasksCompleteQuery = '
        UPDATE projects
        SET tasks_complete = 1
        WHERE project_id = :projectId
            AND is_complete = 0
        LIMIT 1
    ';
    $updateTasksCompleteParams['projectId'] = $projectMetadata['project_id'];
    $updateTasksCompleteResult = run_prepared_query($DBH, $updateTasksCompleteQuery, $updateTasksCompleteParams);
    if ($updateTasksCompleteResult->rowCount() == 1) {

        header("Location: projectCreator.php?projectId={$projectMetadata['project_id']}&complete");
        exit;
    } else {
        header("Location: projectCreator.php?error=UpdateFailed");
        exit;
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CLONE CODE
//
//
if ($projectToClone) {
    $projectToCloneMetadata = retrieve_entity_metadata($DBH, $projectToClone, 'project');
    if ($projectToCloneMetadata &&
            $projectToCloneMetadata['is_complete'] == 1) {

        $continueProcessing = TRUE;

        function remove_rows($idArray, $type) {
            if (isset($GLOBALS['DBH'])) {
                global $DBH;
            } else {
                exit;
            }

            switch ($type) {
                case 'task_metadata':
                    $tableName = 'task_metadata';
                    $columnName = 'task_id';
                    break;
                case 'task_contents':
                    $tableName = 'task_contents';
                    $columnName = 'id';
                    break;
                case 'tag_group_metadata':
                    $tableName = 'tag_group_metadata';
                    $columnName = 'tag_group_id';
                    break;
                case 'tags':
                    $tableName = 'tags';
                    $columnName = 'tag_id';
                    break;
                case 'tag_group_contents':
                    $tableName = 'tag_group_contents';
                    $columnName = 'id';
                    break;
            }
            $whereInType = where_in_string_builder($idArray);
            $removeRowsQuery = "
                    DELETE FROM $tableName
                    WHERE $columnName IN ($whereInType)
                    ";
            $removeRowsResult = run_prepared_query($DBH, $removeRowsQuery);
            return $removeRowsResult->rowCount();
        }

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone task_metadata rows
        $taskMap = array();
        $tasksToCloneQuery = '
            SELECT *
            FROM task_metadata
            WHERE project_id = :projectIdToClone';
        $tasksToCloneParams['projectIdToClone'] = $projectToClone;
        $tasksToCloneResult = run_prepared_query($DBH, $tasksToCloneQuery, $tasksToCloneParams);
        while ($taskToClone = $tasksToCloneResult->fetch(PDO::FETCH_ASSOC)) {
            $insertNewTaskQuery = '
                INSERT INTO task_metadata
                (project_id, is_enabled, name, description, order_in_project, display_title)
                VALUES (:projectId, :isEnabled, :name, :description, :orderInProject, :displayTitle)
                ';
            $insertNewTaskParams = array(
                'projectId' => $projectMetadata['project_id'],
                'isEnabled' => $taskToClone['is_enabled'],
                'name' => $taskToClone ['name'],
                'description' => $taskToClone['description'],
                'orderInProject' => $taskToClone['order_in_project'],
                'displayTitle' => $taskToClone['display_title']
            );
            $taskMap[$taskToClone['task_id']] = run_prepared_query($DBH, $insertNewTaskQuery, $insertNewTaskParams, TRUE);
            if (empty($taskMap[$taskToClone['task_id']])) {
                remove_rows($taskMap, 'task_metadata');
                $continueProcessing = FALSE;
            }
        }

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone group_metadata rows
        if ($continueProcessing) {
            $groupMap = array();
            $groupsToCloneQuery = '
            SELECT *
            FROM tag_group_metadata
            WHERE project_id = :projectIdToClone';
            $groupsToCloneParams['projectIdToClone'] = $projectToClone;
            $groupsToCloneResult = run_prepared_query($DBH, $groupsToCloneQuery, $groupsToCloneParams);
            while ($groupToClone = $groupsToCloneResult->fetch(PDO::FETCH_ASSOC)) {
                $insertNewGroupQuery = '
                INSERT INTO tag_group_metadata
                (project_id, is_enabled, contains_groups, name, description, display_text, display_image,
                force_width, has_border, has_color)
                VALUES (:projectId, :isEnabled, :containsGroups, :name, :description, :displayText,
                :displayImage, :forceWidth, :hasBorder, :hasColor)
                ';
                $insertNewGroupParams = array(
                    'projectId' => $projectMetadata['project_id'],
                    'isEnabled' => $groupToClone['is_enabled'],
                    'containsGroups' => $groupToClone['contains_groups'],
                    'name' => $groupToClone['name'],
                    'description' => $groupToClone['description'],
                    'displayText' => $groupToClone['display_text'],
                    'displayImage' => $groupToClone['display_image'],
                    'forceWidth' => $groupToClone['force_width'],
                    'hasBorder' => $groupToClone['has_border'],
                    'hasColor' => $groupToClone['has_color']
                );
                $groupMap[$groupToClone['tag_group_id']] = run_prepared_query($DBH, $insertNewGroupQuery, $insertNewGroupParams, TRUE);
                if (empty($groupMap[$groupToClone['tag_group_id']])) {
                    remove_rows($groupMap, 'tag_group_metadata');
                    remove_rows($taskMap, 'task_metadata');
                    $continueProcessing = FALSE;
                }
            }
        }

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone/generate task_contents rows
        if ($continueProcessing) {
            $whereInOldTasks = where_in_string_builder(array_keys($taskMap));
            $taskContentsToReplicateQuery = "
            SELECT *
            FROM task_contents
            WHERE task_id IN ($whereInOldTasks)";
            $taskContentsToReplicateResult = run_prepared_query($DBH, $taskContentsToReplicateQuery);
            $taskContentsArray = $taskContentsToReplicateResult->fetchAll(PDO::FETCH_ASSOC);
            $newTaskContentsIdArray = array();
            foreach ($taskContentsArray as $taskContentsRow) {
                $taskContentsRow['task_id'] = $taskMap[$taskContentsRow['task_id']];
                $taskContentsRow['tag_group_id'] = $groupMap[$taskContentsRow['tag_group_id']];
                $insertTaskContentRowQuery = '
                INSERT INTO task_contents
                (task_id, tag_group_id, order_in_task)
                VALUES (:taskId, :tagGroupId, :orderInTask)
                ';
                $insertTaskContentRowParams = array(
                    'taskId' => $taskContentsRow['task_id'],
                    'tagGroupId' => $taskContentsRow['tag_group_id'],
                    'orderInTask' => $taskContentsRow['order_in_task']
                );
                $newTaskContentsId = run_prepared_query($DBH, $insertTaskContentRowQuery, $insertTaskContentRowParams, TRUE);
                if (empty($newTaskContentsId)) {
                    remove_rows($newTaskContentsIdArray, 'task_contents');
                    remove_rows($groupMap, 'tag_group_metadata');
                    remove_rows($taskMap, 'task_metadata');
                    $continueProcessing = FALSE;
                } else {
                    $newTaskContentsIdArray[] = $newTaskContentsId;
                }
            }
        }
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone tags rows
        if ($continueProcessing) {
            $tagMap = array();
            $tagsToCloneQuery = '
            SELECT *
            FROM tags
            WHERE project_id = :projectIdToClone';
            $tagsToCloneParams['projectIdToClone'] = $projectToClone;
            $tagsToCloneResult = run_prepared_query($DBH, $tagsToCloneQuery, $tagsToCloneParams);
            while ($tagToClone = $tagsToCloneResult->fetch(PDO::FETCH_ASSOC)) {
                $insertNewTagQuery = '
                INSERT INTO tags
                (project_id, is_enabled, is_comment_box, is_radio_button, radio_button_group, name, description,
                display_text, tooltip_text, tooltip_image, tooltip_image_width, tooltip_image_height)
                VALUES (:projectId, :isEnabled, :isCommentBox, :isRadioButton, :radioButtonGroup, :name,
                :description, :displayText, :tooltipText, :tooltipImage, :tooltipImageWwidth, :tooltipImageHeight)';
                $insertNewTagParams = array(
                    'projectId' => $projectMetadata['project_id'],
                    'isEnabled' => $tagToClone['is_enabled'],
                    'isCommentBox' => $tagToClone['is_comment_box'],
                    'isRadioButton' => $tagToClone['is_radio_button'],
                    'radioButtonGroup' => $tagToClone['radio_button_group'],
                    'name' => $tagToClone['name'],
                    'description' => $tagToClone['description'],
                    'displayText' => $tagToClone['display_text'],
                    'tooltipText' => $tagToClone['tooltip_text'],
                    'tooltipImage' => $tagToClone['tooltip_image'],
                    'tooltipImageWwidth' => $tagToClone['tooltip_image_width'],
                    'tooltipImageHeight' => $tagToClone['tooltip_image_height']
                );
                $tagMap[$tagToClone['tag_id']] = run_prepared_query($DBH, $insertNewTagQuery, $insertNewTagParams, TRUE);
                if (empty($tagMap[$tagToClone['tag_id']])) {
                    remove_rows($tagMap, 'tags');
                    remove_rows($newTaskContentsIdArray, 'task_contents');
                    remove_rows($groupMap, 'tag_group_metadata');
                    remove_rows($taskMap, 'task_metadata');
                    $continueProcessing = FALSE;
                }
            }
        }
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone/generate tag_group_contents rows
        if ($continueProcessing) {
            $groupGroupIds = array();
            $findGroupGroupsQuery = '
            SELECT tag_group_id
            FROM tag_group_metadata
            WHERE project_id = :projectIdToClone
                AND contains_groups = 1
            ';
            $findGroupGroupsParams['projectIdToClone'] = $projectToClone;
            $findGroupGroupsResults = run_prepared_query($DBH, $findGroupGroupsQuery, $findGroupGroupsParams);
            while ($groupGroup = $findGroupGroupsResults->fetchColumn()) {
                $groupGroupIds[] = $groupGroup;
            }

            $whereInOldGroups = where_in_string_builder(array_keys($groupMap));
            $tagGroupContentsToReplicateQuery = "
            SELECT *
            FROM tag_group_contents
            WHERE tag_group_id IN ($whereInOldGroups)";
            $tagGroupContentsToReplicateResult = run_prepared_query($DBH, $tagGroupContentsToReplicateQuery);
            $tagGroupContentsArray = $tagGroupContentsToReplicateResult->fetchAll(PDO::FETCH_ASSOC);
            $newTagGroupContentsIdArray = array();
            foreach ($tagGroupContentsArray as $tagGroupContentsRow) {
                if (in_array($tagGroupContentsRow['tag_group_id'], $groupGroupIds)) {
                    $tagGroupContentsRow['tag_id'] = $groupMap[$tagGroupContentsRow['tag_id']];
                } else {
                    $tagGroupContentsRow['tag_id'] = $tagMap[$tagGroupContentsRow['tag_id']];
                }
                $tagGroupContentsRow['tag_group_id'] = $groupMap[$tagGroupContentsRow['tag_group_id']];

                $insertTaskContentRowQuery = '
                INSERT INTO tag_group_contents
                (tag_group_id, tag_id, order_in_group)
                VALUES (:tagGroupId, :tagId, :orderInGroup)
                ';
                $insertTaskContentRowParams = array(
                    'tagGroupId' => $tagGroupContentsRow['tag_group_id'],
                    'tagId' => $tagGroupContentsRow['tag_id'],
                    'orderInGroup' => $tagGroupContentsRow['order_in_group']
                );
                $newTagGroupContentsId = run_prepared_query($DBH, $insertTaskContentRowQuery, $insertTaskContentRowParams, TRUE);
                if (empty($newTagGroupContentsId)) {
                    remove_rows($newTagGroupContentsIdArray, 'tag_group_contents');
                    remove_rows($tagMap, 'tags');
                    remove_rows($newTaskContentsIdArray, 'task_contents');
                    remove_rows($groupMap, 'tag_group_metadata');
                    remove_rows($taskMap, 'task_metadata');
                    $continueProcessing = FALSE;
                } else {
                    $newTagGroupContentsIdArray[] = $newTagGroupContentsId;
                }
            }
        }
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Clone tool tip images
        if ($continueProcessing) {
            $abort = FALSE;
            if (!file_exists("images/projects/{$projectMetadata['project_id']}/tooltips")) {
                if (!mkdir("images/projects/{$projectMetadata['project_id']}/tooltips", 0775, true)) {
                    $abort = TRUE;
                }
                chmod("images/projects/{$projectMetadata['project_id']}", 775);
                chmod("images/projects/{$projectMetadata['project_id']}/tooltips", 775);
            } else {
                $files = glob("images/projects/{$projectMetadata['project_id']}/tooltips/*");
                foreach ($files as $file) { // iterate files
                    if (is_file($file)) {
                        unlink($file); // delete file
                    }
                }
            }
            $sourceFileList = glob("images/projects/$projectToClone/tooltips/*");
            foreach ($sourceFileList as $file) { // iterate files
                if (is_file($file)) {
                    $filename = basename($file);
                    $copyResult = copy($file, "images/projects/{$projectMetadata['project_id']}/tooltips/{$filename}"); // copy file
                    if (!$copyResult) {
                        $abort = TRUE;
                        break;
                    }
                }
            }

            if ($abort) {
                if (file_exists("images/projects/{$projectMetadata['project_id']}/tooltips")) {
                    $files = glob("images/projects/{$projectMetadata['project_id']}/tooltips/*");
                    foreach ($files as $file) { // iterate files
                        if (is_file($file)) {
                            unlink($file); // delete file
                        }
                    }
                    rmdir("images/projects/{$projectMetadata['project_id']}{/tooltips");
                    rmdir("images/projects/{$projectMetadata['project_id']}");
                }
                remove_rows($newTagGroupContentsIdArray, 'tag_group_contents');
                remove_rows($tagMap, 'tags');
                remove_rows($newTaskContentsIdArray, 'task_contents');
                remove_rows($groupMap, 'tag_group_metadata');
                remove_rows($taskMap, 'task_metadata');
                $continueProcessing = FALSE;
            }
        }
        if ($continueProcessing) {
            $actionSelctionHTML = '
                <h3>Clone Sucessful</h3>
                <p>The existing project has now been cloned. You may either adapt the clone to the needs of this
                    project or accept it as is and move to the next stage of project creation.</p>
                ';
            $importStatus = 10;
        } else {
            $projectUpdateErrorHTML = <<<EOL
                <h3>Clone Failed</h3>
                <p class="error">An error occured during the clone. No changes have been made.</p>
                <p class="error">If this problem persists please contact an iCoast System Administrator. </p>
EOL;
        }
    }
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// UPDATE CODE
//
//
if ($editSubmittedFlag && $projectPropertyToUpdate) {



// CUSTOMIZE THE UPDATE PROCESS BASED ON THE PROPERTY BEING UPDATED
    switch ($projectPropertyToUpdate) {

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// UPDATE TASKS
//
//
        case 'tasks':
// CHECK THAT ALL REQUIRED FIELDS ARE PRESENT & CORRECT
            $invalidRequiredField = FALSE;
            $dataChanges = FALSE;
            $databaseUpdateFailure = FALSE;

            $newTaskName = filter_input(INPUT_POST, 'newTaskName');
            $newTaskDescription = filter_input(INPUT_POST, 'newTaskDescription');
            $newDisplayTitle = filter_input(INPUT_POST, 'newDisplayTitle');
            $newOrderInProject = filter_input(INPUT_POST, 'newOrderInProject', FILTER_VALIDATE_INT);
            $newTaskStatus = filter_input(INPUT_POST, 'newTaskStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);


            if (empty($projectEditSubAction)) {
                $invalidRequiredField[] = 'projectEditSubAction';
            }
            if (empty($newTaskName)) {
                $invalidRequiredField[] = 'newTaskName';
            }
            if (is_null($newTaskDescription)) {
                $newTaskDescription = '';
            }
            if (empty($newDisplayTitle)) {
                $invalidRequiredField[] = 'newDisplayTitle';
            }
            if (empty($newOrderInProject)) {
                $invalidRequiredField[] = 'newOrderInProject';
            }
            if (is_null($newTaskStatus)) {
                $invalidRequiredField[] = 'newTaskStatus';
            }

            if (!$invalidRequiredField) {
                $projectTaskQuery = "SELECT * FROM task_metadata WHERE project_id = :projectId ORDER BY order_in_project ASC";
                $projectTaskParams['projectId'] = $projectId;
                $projectTaskResults = run_prepared_query($DBH, $projectTaskQuery, $projectTaskParams);
                $projectTasks = $projectTaskResults->fetchAll(PDO::FETCH_ASSOC);
                $numberOfTasks = count($projectTasks);


// IF A TASK_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingTask
                if ($projectEditSubAction == 'updateExistingTask') {
                    $dbQueryTaskId = $updateTaskId = $taskId;
// REWRITE THE 'ORDER_IN_PROJECT' FIELDS TO BE SEQUENTIAL IF NOT ALREADY. ORDER IS UNCHANGED
                    $orderResequenced = FALSE;
                    $selectedProjectOrderToBeChanged = FALSE;
                    $taskFieldsToUpdate = array();
// LOOP THROUGH THE TASKS
                    for ($i = 0; $i < $numberOfTasks; $i++) {
// IF THE DATABASE ORDER NUMBER ISN'T SEQUENTIAL THEN CHANGE IT AND FLAG THAT A CHANGE WAS MADE
                        if ($projectTasks [$i]['order_in_project'] != ($i + 1)) {
                            $projectTasks[$i] ['order_in_project'] = ($i + 1);
                            $projectTasks[$i]['is_altered'] = true;
                            $orderResequenced = TRUE;
                        } else {
                            $projectTasks[$i]['is_altered'] = false;
                        }
// IF THE CURRENT TASK IS THE ONE THE USER IS EDITING AND A CHANGE IN ORDER IS DETECTED THEN SET THE FLAG
// AND DETECT IF THE TASK WILL MOVE UP OR DOWN THE ORDER. ALSO RECORD THE TASKS OLD POSITION
                        if ($projectTasks [$i]['task_id'] == $updateTaskId) {
                            foreach ($projectTasks[$i] as $column => $oldColumnValue) {
                                switch ($column) {
                                    case 'is_enabled':
                                        if ($oldColumnValue != $newTaskStatus) {
                                            $taskFieldsToUpdate['is_enabled'] = (int) $newTaskStatus;
                                        }
                                        break;
                                    case 'name':
                                        if ($oldColumnValue != $newTaskName) {
                                            $taskFieldsToUpdate['name'] = $newTaskName;
                                        }
                                        break;
                                    case 'description':
                                        if ($oldColumnValue != $newTaskDescription) {
                                            $taskFieldsToUpdate['description'] = $newTaskDescription;
                                        }
                                        break;
                                    case 'order_in_project':
                                        if ($oldColumnValue != $newOrderInProject) {
                                            $taskFieldsToUpdate['order_in_project'] = $newOrderInProject;
                                            $selectedProjectOrderToBeChanged = TRUE;
                                            $selectedTaskOldOrderInProject = $projectTasks[$i]['order_in_project'];
                                            if ($newOrderInProject < $oldColumnValue) {
                                                $directionOfProjectMovement = 'down';
                                            } else {
                                                $directionOfProjectMovement = 'up';
                                            }
                                        }
                                        break;
                                    case 'display_title':
                                        if ($oldColumnValue != $newDisplayTitle) {
                                            $taskFieldsToUpdate['display_title'] = $newDisplayTitle;
                                        }
                                        break;
                                }
                            }
                        }
                    }
// CHANGE THE ORDER OF THE TASKS IF NECESSARY (FLAG WAS SET)
                    if ($selectedProjectOrderToBeChanged) {
// LOOP THROUGH THE TASKS
                        for ($i = 0; $i < $numberOfTasks; $i++) {
// IF THE CURRENT TASK IN THE LOOP IS NOT THE TASK BEING EDITED AND IT WILL BE AFFECTED BY
// THE CHANGE OF ORDER OF THE EDITED TASK THEN MOVE IT UP OR DOWN THE ORDER IN THE PROJECT BY
// 1 PLACE TO MAKE ROOM FOR THE NEW LOCATION OF THE EDITED TASK.
                            if ($projectTasks [$i]['task_id'] != $updateTaskId) {
                                if ($directionOfProjectMovement == 'up' &&
                                        ($projectTasks [$i]['order_in_project'] > $selectedTaskOldOrderInProject ) &&
                                        ($projectTasks [$i]['order_in_project'] <= $newOrderInProject)) {
                                    $projectTasks[$i]['order_in_project'] --;
                                    $projectTasks[$i]['is_altered'] = true;
                                } else if ($directionOfProjectMovement == 'down' &&
                                        ($projectTasks [$i]['order_in_project'] < $selectedTaskOldOrderInProject ) &&
                                        ($projectTasks [$i]['order_in_project'] >= $newOrderInProject)) {
                                    $projectTasks[$i]['order_in_project'] ++;
                                    $projectTasks[$i]['is_altered'] = true;
                                }
                            } else {
// IF THE TASK IS THE ONE BEING EDITED THE REPLACE ITS ORDER IN PROJECT WITH THE NEW POSITION
                                $projectTasks[$i]['order_in_project'] = $newOrderInProject;
                                $projectTasks[$i]['is_altered'] = true;
                            }
                        }
                        $orderResequenced = TRUE;
                    }

// PREPARE UPDATE BASED ON CHANGES ONLY TO THE SELECTED TASK
// IF NO RESEQUENCING WAS NEEDED AND THE ORDER OF THE EDITED TASK IN THE PROJECT IS UNCHANGED
// THEN JUST UPDATE THE EDITED TASK INFORMATION.
                    if (count($taskFieldsToUpdate) > 0) {
                        $dataChanges = true;

                        $updateTaskQuery = "UPDATE task_metadata "
                                . "SET ";
                        $updateTaskParams = array();
                        $columnUpdateCount = 0;
                        foreach ($taskFieldsToUpdate as $column => $value) {
                            $updateTaskQuery .= "$column=:$column";
                            $columnUpdateCount++;
                            if ($columnUpdateCount != count($taskFieldsToUpdate)) {
                                $updateTaskQuery .= ", ";
                            }
                            $updateTaskParams[$column] = $value;
                        }
                        $updateTaskQuery .= " WHERE task_id = :taskToUpdate LIMIT 1";
                        $updateTaskParams['taskToUpdate'] = $updateTaskId;

                        $updateTaskResult = run_prepared_query($DBH, $updateTaskQuery, $updateTaskParams);
                        $affectedRows = $updateTaskResult->rowCount();

                        if (isset($affectedRows) && $affectedRows == 1) {
                            
                        } else {
                            $databaseUpdateFailure['TaskMetadataUpdateQuery'] = '$updateTaskQuery';
                        }
                    } // END FIELD CHANGES - if ($fieldChanges)

                    if ($orderResequenced) {
// EITHER THE ORDER WAS RESEQUENCED OR CHANGED AND ALL TASKS MUST BE UPDATED
                        foreach ($projectTasks as $individualTask) { // LOOP THROUGH THE TASKS
// IF THE CURRENT TASK IN THE LOOP IS NOT THE EDITED TASK AND ANY PREVIOUS TASK UPDATES HAVE BEEN SUCESSFUL
// THEN JUST UPDATE THE ORDER_IN_PROJECT COLUMN FOR THE CURRENT TASK
                            if (!$databaseUpdateFailure &&
                                    $individualTask ['is_altered'] == true &&
                                    ($individualTask ['task_id'] != $updateTaskId ||
                                    ($individualTask ['task_id'] == $updateTaskId && !$dataChanges))) {
                                $updateTaskOrderQuery = "UPDATE task_metadata "
                                        . "SET order_in_project = :orderInProject "
                                        . "WHERE task_id = :taskId LIMIT 1";
                                $updateTaskOrderParams = array(
                                    'orderInProject' => $individualTask['order_in_project'],
                                    'taskId' => $individualTask['task_id']
                                );
                                $updateTaskOrderResult = run_prepared_query($DBH, $updateTaskOrderQuery, $updateTaskOrderParams);
                                $affectedRows = $updateTaskOrderResult->rowCount();

                                if (isset($affectedRows) && $affectedRows == 1) {
                                    
                                } else {
                                    $databaseUpdateFailure['TaskOrderUpdate'] = '$updateTaskOrderQuery';
                                }
                            }
                        }
                    }
                } // END TASK UPDATE
                else if ($projectEditSubAction == 'createNewTask') {
                    $taskAlreadyPresentQuery = '
                        SELECT task_id
                        FROM task_metadata
                        WHERE project_id = :projectId
                            AND name = :name
                            AND description = :description
                            AND display_title = :displayTitle
                        ';
                    $taskAlreadyPresentParams = array(
                        'projectId' => $projectId,
                        'name' => $newTaskName,
                        'description' => $newTaskDescription,
                        'displayTitle' => $newDisplayTitle
                    );
                    $taskAlreadyPresentResult = run_prepared_query($DBH, $taskAlreadyPresentQuery, $taskAlreadyPresentParams);
                    $existingTaskId = $taskAlreadyPresentResult->fetchColumn();
                    if (!$existingTaskId) {
// IF THE UPDATE REQUEST IS TO CREATE NEW TASK THEN CREATE A NEW TASK
// REWRITE THE 'ORDER_IN_PROJECT' FIELDS TO BE SEQUENTIAL IF NOT ALREADY. ORDER IS UNCHANGED
                        $dataChanges = TRUE;
                        $sequentialOrderInProjectNumber = 1;
// LOOP THROUGH THE TASKS
                        for ($i = 0; $i < $numberOfTasks; $i++) {
// IF THE DATABASE ORDER NUMBER ISN'T SEQUENTIAL THEN CHANGE IT AND FLAG THAT A CHANGE WAS MADE
                            if ($projectTasks[$i]['order_in_project'] != $sequentialOrderInProjectNumber) {
                                $projectTasks[$i]['order_in_project'] = $sequentialOrderInProjectNumber;
                            }
                            $sequentialOrderInProjectNumber++;
                        }

// CHANGE THE ORDER OF THE TASKS TO MAKE SPACE FOR NEW TASK
// LOOP THROUGH THE TASKS
                        for ($i = 0; $i < $numberOfTasks; $i++) {
// IF THE CURRENT TASK IN THE LOOP IS NOT THE TASK BEING EDITED AND IT WILL BE AFFECTED BY
// THE CHANGE OF ORDER OF THE EDITED TASK THEN MOVE IT UP OR DOWN THE ORDER IN THE PROJECT BY
// 1 PLACE TO MAKE ROOM FOR THE NEW LOCATION OF THE EDITED TASK.

                            if ($projectTasks[$i]['order_in_project'] >= $newOrderInProject) {
                                $projectTasks[$i]['order_in_project'] ++;
                            }
                        }
                        foreach ($projectTasks as $individualTask) { // LOOP THROUGH THE TASKS
// IF THE CURRENT TASK IN THE LOOP IS NOT THE EDITED TASK AND PREVIOUS TASK UPDATES HAVE BEEN SUCESSFUL
// THEN JUST UPDATE THE ORDER_IN_PROJECT COLUMN FOR THE CURRENT TASK
                                $updateTaskQuery = "UPDATE task_metadata "
                                        . "SET order_in_project = :orderInProject "
                                        . "WHERE task_id = :taskId LIMIT 1";
                                $updateTaskParams = array(
                                    'orderInProject' => $individualTask['order_in_project'],
                                    'taskId' => $individualTask['task_id']
                                );
                                $updateTaskResult = run_prepared_query($DBH, $updateTaskQuery, $updateTaskParams);
                        }
// IF THE CURRENT TASK IN THE LOOP IS THE ONE BEING EDITED AND UPDATES TO
// PREVIOUS TASKS HAVE BEEN SUCESSFUL THEN PERFORM AN UPDATE OF ALL FIELDS ON THE CURRENT TASK
                        if (!$databaseUpdateFailure) {
                            $updateTaskQuery = "INSERT INTO task_metadata " .
                                    "(project_id, name, description, is_enabled, order_in_project, display_title) " .
                                    "VALUES (:projectId, :newTaskName, :newTaskDescription, :newTaskStatus, :newOrderInProject, :newDisplayTitle )";
                            $updateTaskParams = array(
                                'projectId' => $projectId,
                                'newTaskName' => $newTaskName,
                                'newTaskDescription' => $newTaskDescription,
                                'newTaskStatus' => (int) $newTaskStatus,
                                'newOrderInProject' => $newOrderInProject,
                                'newDisplayTitle' => $newDisplayTitle
                            );
                            $updateTaskResult = run_prepared_query($DBH, $updateTaskQuery, $updateTaskParams);
                            if (!isset($updateTaskResult) || $updateTaskResult->rowCount() === 0) {
                                $databaseUpdateFailure['InsertNewTask'] = $updateTaskQuery;
                            } else {
                                $dbQueryTaskId = $DBH->lastInsertID();
                            }
                        }
                    } else {
                        $dbQueryTaskId = $existingTaskId;
                    }
                }
            }

            if ($importStatus != 11) {
                $taskEditingButtonHTML = '
                            <input type="button" class="clickableButton" id="returnToTaskSelection"
                                title="This will return you to the task selection screen to choose
                                another task based action." value="Choose Another Task Action">';
            } else {
                $taskEditingButtonHTML = '';
            }
// IF THERE WERE NO UPDATE ERRORS THEN UPDATE THE USER AND SHOW THE NEW TASK METADATA.
            if ($invalidRequiredField) {
                $editSubmittedFlag = false;
                $failedSubmissionHTML = '<p class="error">';
                $failedFields = '';
                foreach ($invalidRequiredField as $invalidFieldName) {
                    switch ($invalidFieldName) {
                        case 'newTaskName':
                            $failedFields .= '<br>Task Admin Name';
                            break;
                        case 'newDisplayTitle':
                            $failedFields .= '<br>Task Display Text';
                            break;
                    }
                }
                if (!empty($failedFields)) {
                    $failedSubmissionHTML .= 'The following required fields were either missing user input or contained invalid data:' . $failedFields;
                } else {
                    $failedSubmissionHTML .= 'Data was missing from the submission. Please try again.';
                }
                $failedSubmissionHTML .= '</p>';
                if ($newTaskName) {
                    $currentTaskName = $newTaskName;
                }
                if ($newTaskDescription) {
                    $currentTaskDescription = $newTaskDescription;
                }
                if ($newTaskStatus) {
                    $currentTaskStatus = (int) $newTaskStatus;
                }
                if ($newOrderInProject) {
                    $currentOrderInProject = $newOrderInProject;
                }
                if ($newDisplayTitle) {
                    $currentDisplayTitle = $newDisplayTitle;
                }
            } else if (!$dataChanges) {
                $actionSummaryHTML = <<<EOL
                                <h3>No Changes Detected</h3>
                                <p>No change in the task data has been detected. The database has not been altered.</p>

EOL;
            } else if ($databaseUpdateFailure) {
                printArray($databaseUpdateFailure);
                $projectUpdateErrorHTML = <<<EOL
                                <h3>Update Failed</h3>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will return you to the Question Builder menu
                                        for you to choose another item to create or edit."
                                            value="Return To Question Builder Menu">
                                </div>

EOL;
            } else {
                $actionSummaryHTML = '
                                <h3>Update Successful</h3>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>
                        ';
            }



            if (!$databaseUpdateFailure && !$invalidRequiredField) {

                $summaryQuery = "SELECT * FROM task_metadata WHERE task_id = :taskId";
                $summaryParams['taskId'] = $dbQueryTaskId;
                $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                $dbTaskMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                if ($dbTaskMetadata ['is_enabled'] == 1) {
                    $dbTaskStatusText = 'Enabled';
                } else {
                    $dbTaskStatusText = '<span class="redHighlight">Disabled</span>';
                }

                $dbTaskMetadata['name'] = htmlspecialchars($dbTaskMetadata['name']);
                $dbTaskMetadata['description'] = restoreSafeHTMLTags(htmlspecialchars($dbTaskMetadata['description']));
                $dbTaskMetadata['display_title'] = htmlspecialchars($dbTaskMetadata['display_title']);

                $ordinalPositionInParent = ordinal_suffix($dbTaskMetadata['order_in_project']);

                $actionSummaryHTML .= <<<EOL
                                <h3>Task Details Summary</h3>
                                <table id="updateSummaryTable">
                                    <tbody>
                                        <tr>
                                            <td>Task Name:</td>
                                            <td class="userData">{$dbTaskMetadata['name']}</td>
                                        </tr>
                                        <tr>
                                            <td>Task Description</td>
                                            <td class="userData">{$dbTaskMetadata['description']}</td>
                                        </tr>
                                        <tr>
                                            <td>Task Display Text:</td>
                                            <td class="userData">{$dbTaskMetadata['display_title']}</td>
                                        </tr>
                                        <tr>
                                            <td>Order in Project:</td>
                                            <td class="userData">$ordinalPositionInParent</td>
                                        </tr>
                                        <tr>
                                            <td>Task Status:</td>
                                            <td class = "userData">$dbTaskStatusText</td>
                                        </tr>
                                    </tbody>
                                </table>
EOL;
                if ($importStatus == 10) {
                    $actionSummaryHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionSummaryHTML .= <<<EOL
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will return you to the task editing screen for you to update this task's details again." value="Return to the Task Menu Screen">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Task Builder screen for you to choose another task creator activity." value="Return to the Question Builder Menu">
                                </div>

EOL;
            }



            break;
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// UPDATE GROUPS
//
//
        case 'groups':
// Check that all required fields are present.

            $invalidRequiredField = FALSE;
            $dataChanges = FALSE;
            $databaseUpdateFailure = FALSE;

            $newGroupStatus = filter_input(INPUT_POST, 'newGroupStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $newGroupContainsGroupsStatus = filter_input(INPUT_POST, 'newGroupContainsGroupsStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $newGroupName = filter_input(INPUT_POST, 'newGroupName');
            $newGroupDescription = filter_input(INPUT_POST, 'newGroupDescription');
            $newGroupDisplayText = filter_input(INPUT_POST, 'newDisplayText');
            $newGroupWidth = filter_input(INPUT_POST, 'newGroupWidth', FILTER_VALIDATE_INT);
            $newGroupBorderStatus = filter_input(INPUT_POST, 'newGroupBorderStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $newGroupColorStatus = filter_input(INPUT_POST, 'newGroupColorStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $newGroupColor = filter_input(INPUT_POST, 'newGroupColor');
            $newParentTaskId = filter_input(INPUT_POST, 'newParentTaskId', FILTER_VALIDATE_INT);
            $newParentGroupId = filter_input(INPUT_POST, 'newParentGroupId', FILTER_VALIDATE_INT);
            $newGroupOrderInParent = filter_input(INPUT_POST, 'newGroupOrder', FILTER_VALIDATE_INT);
            $oldGroupParentType = filter_input(INPUT_POST, 'oldGroupParentType');
            $oldGroupParentId = filter_input(INPUT_POST, 'oldGroupParentId', FILTER_VALIDATE_INT);
            $oldGroupOrderInParent = filter_input(INPUT_POST, 'oldGroupOrderInParent', FILTER_VALIDATE_INT);

            if (empty($projectEditSubAction)) {
                $invalidRequiredField[] = 'projectEditSubAction';
            } else if ($projectEditSubAction == 'updateExistingGroup' && empty($groupId)) {
                $invalidRequiredField[] = 'groupId';
            } else if ($projectEditSubAction == 'updateExistingGroup') {
                $groupMetadata = retrieve_entity_metadata($DBH, $groupId, 'group');
                if (!$groupMetadata) {
                    $invalidRequiredField[] = 'groupId';
                }
            }

            if (is_null($newGroupStatus)) {
                $invalidRequiredField[] = 'newGroupStatus';
            }
            if (is_null($newGroupContainsGroupsStatus)) {
                $invalidRequiredField[] = 'newGroupContainsGroupsStatus';
            }
            if (empty($newGroupName)) {
                $invalidRequiredField[] = 'newGroupName';
            }
            if (is_null($newGroupDescription)) {
                $newGroupDescription = '';
            }
            if (empty($newGroupDisplayText)) {
                $invalidRequiredField[] = 'newDisplayText';
            }
            if (is_null($newGroupWidth) || $newGroupWidth === false) {
                $tempWidth = filter_input(INPUT_POST, 'newGroupWidth');
                if (empty($tempWidth)) {
                    $newGroupWidth = 0;
                } else {
                    $invalidRequiredField[] = 'newGroupWidth';
                }
            }
            if (is_null($newGroupBorderStatus)) {
                $invalidRequiredField[] = 'newGroupBorderStatus';
            }
            if (is_null($newGroupColorStatus)) {
                $invalidRequiredField[] = 'newGroupColorStatus';
            } else if ($newGroupColorStatus) {
                if (isset($newGroupColor)) {
                    $newGroupColor = trim($newGroupColor, "#");
                    $hexPattern = '/^([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?$/';
                    if (!preg_match($hexPattern, $newGroupColor)) {
                        $invalidRequiredField[] = 'newGroupColor';
                    }
                } else {
                    $invalidRequiredField[] = 'newGroupColor';
                }
            } else {
                $newGroupColor = '';
            }

            if (isset($newParentTaskId)) {
                $newGroupParentType = 'task';
                $newGroupParentId = $newParentTaskId;
                if (empty($newParentTaskId)) {
                    $invalidRequiredField[] = 'newParentTaskId';
                }
            } else if (isset($newParentGroupId)) {
                $newGroupParentType = 'group';
                $newGroupParentId = $newParentGroupId;
                if (empty($newParentGroupId)) {
                    $invalidRequiredField[] = 'newParentGroupId';
                }
            } else {
                $invalidRequiredField[] = 'parentId';
            }

            if (empty($newGroupOrderInParent)) {
                $invalidRequiredField[] = 'newGroupOrder';
            }


// IF A GROUP_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingGroup
            if (isset($groupId) &&
                    isset($oldGroupParentType) &&
                    isset($oldGroupParentId) &&
                    isset($oldGroupOrderInParent) &&
                    $projectEditSubAction == 'updateExistingGroup') {

                $updateGroupId = $groupId;

                if (empty($updateGroupId)) {
                    $invalidRequiredField[] = 'groupId';
                }

                if ($oldGroupParentType != 'group' && $oldGroupParentType != 'task') {
                    $invalidRequiredField[] = 'oldGroupParentType';
                }

                if (empty($oldGroupParentId)) {
                    $invalidRequiredField[] = 'oldGroupParentId';
                }

                if (empty($oldGroupOrderInParent)) {
                    $invalidRequiredField[] = 'oldGroupOrderInParent';
                }

                if (!$invalidRequiredField) {

                    $oldGroupStatus = $groupMetadata['is_enabled'];
                    $oldGroupContainsGroupsStatus = $groupMetadata['contains_groups'];
                    $oldGroupName = $groupMetadata['name'];
                    $oldGroupDescription = $groupMetadata['description'];
                    $oldGroupDisplayText = $groupMetadata['display_text'];
                    $oldGroupWidth = $groupMetadata['force_width'];
                    $oldGroupBorderStatus = $groupMetadata['has_border'];
                    $oldGroupColor = $groupMetadata['has_color'];
                    $groupFieldsToUpdate = array();
                    if ($oldGroupStatus != $newGroupStatus) {
                        $groupFieldsToUpdate['is_enabled'] = (int) $newGroupStatus;
                    }

                    if ($oldGroupContainsGroupsStatus != $newGroupContainsGroupsStatus) {
                        $groupHasContents = groupContentsCheck($DBH, $updateGroupId);
                        if (!$groupHasContents) {
                            $groupFieldsToUpdate['contains_groups'] = (int) $newGroupContainsGroupsStatus;
                        } else {
                            $invalidRequiredField[] = 'groupContents';
                        }
                    }

                    if ($oldGroupName != $newGroupName) {
                        $groupFieldsToUpdate['name'] = $newGroupName;
                    }

                    if ($oldGroupDescription != $newGroupDescription) {
                        $groupFieldsToUpdate['description'] = $newGroupDescription;
                    }

                    if ($oldGroupDisplayText != $newGroupDisplayText) {
                        $groupFieldsToUpdate['display_text'] = $newGroupDisplayText;
                    }

                    if ($oldGroupWidth != $newGroupWidth) {
                        $groupFieldsToUpdate['force_width'] = $newGroupWidth;
                    }

                    if ($oldGroupBorderStatus != $newGroupBorderStatus) {
                        $groupFieldsToUpdate['has_border'] = (int) $newGroupBorderStatus;
                    }

                    if ((empty($oldGroupColor) && !empty($newGroupColor)) ||
                            (!empty($oldGroupColor) && empty($newGroupColor)) ||
                            (!empty($oldGroupColor) && $oldGroupColor != $newGroupColor)) {
                        $groupFieldsToUpdate['has_color'] = $newGroupColor;
                    }

                    if (count($groupFieldsToUpdate) > 0) {
                        $dataChanges = TRUE;
                        $groupUpdateFieldsQuery = "UPDATE tag_group_metadata "
                                . "SET ";
                        $groupUpdateFieldsParams = array();
                        $columnUpdateCount = 0;
                        foreach ($groupFieldsToUpdate as $column => $value) {
                            $groupUpdateFieldsQuery .= "$column=:$column";
                            $columnUpdateCount++;
                            if ($columnUpdateCount != count($groupFieldsToUpdate)) {
                                $groupUpdateFieldsQuery .= ", ";
                            }
                            $groupUpdateFieldsParams[$column] = $value;
                        }
                        $groupUpdateFieldsQuery .= " WHERE tag_group_id = $updateGroupId LIMIT 1";
                        $groupUpdateResult = run_prepared_query($DBH, $groupUpdateFieldsQuery, $groupUpdateFieldsParams);
                        if ($groupUpdateResult->rowCount() != 1) {
                            $databaseUpdateFailure['Column Updates'] = 'Failed';
                        }
                    }





////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Moving to a new container
                    if (($newGroupParentType != $oldGroupParentType ) || ($newGroupParentId != $oldGroupParentId)) {
                        $dataChanges = TRUE;
//////////////////////////////////////////////////////////////////////////////
// New Container Reordering
                        $newContainer = true;
                        if ($newGroupParentType == 'task') {
                            $newParentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    task_id AS parent_id, 
                                    tag_group_id AS child_id, 
                                    order_in_task AS order_number 
                                FROM 
                                    task_contents 
                                WHERE 
                                    task_id = :parentId 
                                ORDER BY 
                                    order_number";
                        } else {
                            $newParentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    tag_group_id AS parent_id, 
                                    tag_id AS child_id, 
                                    order_in_group AS order_number 
                                FROM 
                                    tag_group_contents 
                                WHERE 
                                    tag_group_id = :parentId 
                                ORDER BY 
                                    order_number";
                        }

                        $newParentContainerOrderParams['parentId'] = $newGroupParentId;
                        $newParentContainerOrderResult = run_prepared_query($DBH, $newParentContainerOrderQuery, $newParentContainerOrderParams);
                        $newParentContainerOrder = $newParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($newParentContainerOrder);

                        $resequencingNumber = 1;
                        foreach ($newParentContainerOrder as &$individualGroup) {
                            if ($individualGroup ['order_number'] != $resequencingNumber) {
                                $individualGroup['order_number'] = $resequencingNumber;
                            }
                            $resequencingNumber++;
                        }

                        foreach ($newParentContainerOrder as &$individualGroup) {
                            if ($individualGroup ['order_number'] >= $newGroupOrderInParent) {
                                $individualGroup ['order_number'] ++;
                            }
                        }

/////////////////////////////////////////////////////////////////////////////
// Old Container Reordering
                        if ($oldGroupParentType == 'task') {
                            $oldParentContainerOrderQuery = "SELECT id AS db_id, task_id AS parent_id, tag_group_id AS child_id, order_in_task AS order_number FROM task_contents WHERE task_id = :parentId ORDER BY order_number";
                        } else {
                            $oldParentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number FROM tag_group_contents WHERE tag_group_id = :parentId ORDER BY order_number";
                        }

                        $oldParentContainerOrderParams['parentId'] = $oldGroupParentId;
                        $oldParentContainerOrderResult = run_prepared_query($DBH, $oldParentContainerOrderQuery, $oldParentContainerOrderParams);
                        $oldParentContainerOrder = $oldParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($oldParentContainerOrder);

                        for ($i = 0; $i < count($oldParentContainerOrder); $i++) {
                            if ($oldParentContainerOrder [$i]['child_id'] == $updateGroupId) {
                                unset($oldParentContainerOrder[$i]);
                            }
                        }

                        $resequencingNumber = 1;
                        foreach ($oldParentContainerOrder as &$individualGroup) {
                            if ($individualGroup ['order_number'] != $resequencingNumber) {
                                $individualGroup['order_number'] = $resequencingNumber;
                            }
                            $resequencingNumber++;
                        }

//////////////////////////////////////////////////////////////////////////////
// Update the database - New container
                        if ($newGroupParentType == 'task') {
                            $newParentContainerUpdateQuery = "UPDATE task_contents SET order_in_task = CASE id ";
                        } else {
                            $newParentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";
                        }
                        foreach ($newParentContainerOrder as $group) {
                            $newParentContainerUpdateQuery .= "WHEN {$group['db_id']} THEN {$group['order_number']} ";
                            $newIdsToUpdate[] = $group['db_id'];
                        }
                        $newWhereInIdsToUpdate = where_in_string_builder($newIdsToUpdate);
                        $newLimitAmount = count($newIdsToUpdate);
                        $newParentContainerUpdateQuery .= "END WHERE id IN ($newWhereInIdsToUpdate) LIMIT $newLimitAmount";
                        $newParentContainerOrderResult = $DBH->query($newParentContainerUpdateQuery);
                        if ($newParentContainerOrderResult) {
//////////////////////////////////////////////////////////////////////////////
// Update the database. Old Container
                            if ($oldGroupParentType == 'task') {
                                $oldParentContainerUpdateQuery = "UPDATE task_contents SET order_in_task = CASE id ";
                            } else {
                                $oldParentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";
                            }
                            foreach ($oldParentContainerOrder as $group) {
                                $oldParentContainerUpdateQuery .= "WHEN {$group['db_id']} THEN {$group['order_number']} ";
                                $oldIdsToUpdate[] = $group['db_id'];
                            }
                            $oldWhereInIdsToUpdate = where_in_string_builder($oldIdsToUpdate);
                            $oldLimitAmount = count($oldIdsToUpdate);
                            $oldParentContainerUpdateQuery .= "END WHERE id IN ($oldWhereInIdsToUpdate) LIMIT $oldLimitAmount";
                            $oldparentContainerOrderResult = $DBH->query($oldParentContainerUpdateQuery);
                            if ($oldparentContainerOrderResult) {
                                if ($oldGroupParentType == 'task' && $newGroupParentType == 'task') {
                                    $groupUpdateQuery = "UPDATE task_contents SET "
                                            . "task_id = :newParentId, "
                                            . "order_in_task = :newGroupOrderInParent "
                                            . "WHERE tag_group_id = $updateGroupId "
                                            . "LIMIT 1";
                                    $groupUpdateParams = array(
                                        'newParentId' => $newGroupParentId,
                                        'newGroupOrderInParent' => $newGroupOrderInParent
                                    );
                                    $groupUpdateResults = run_prepared_query($DBH, $groupUpdateQuery, $groupUpdateParams);
                                    if (!$groupUpdateResults || ($groupUpdateResults && $groupUpdateResults->rowCount() != 1)) {
                                        $databaseUpdateFailure['UpdateGroupsParentTaskIdAndPosition'] = '$groupUpdateQuery';
                                    }
                                } else if ($oldGroupParentType == 'group' && $newGroupParentType == 'group') {
                                    $groupUpdateQuery = "UPDATE tag_group_contents SET "
                                            . "tag_group_id = :newParentId, "
                                            . "order_in_group = :newGroupOrderInParent "
                                            . "WHERE tag_id = :groupId AND tag_group_id = :oldGroupParentId "
                                            . "LIMIT 1";
                                    $groupUpdateParams = array(
                                        'newParentId' => $newGroupParentId,
                                        'newGroupOrderInParent' => $newGroupOrderInParent,
                                        'groupId' => $updateGroupId,
                                        'oldGroupParentId' => $oldGroupParentId
                                    );
                                    $groupUpdateResults = run_prepared_query($DBH, $groupUpdateQuery, $groupUpdateParams);
                                    if (!$groupUpdateResults || ($groupUpdateResults && $groupUpdateResults->rowCount() != 1)) {
                                        $databaseUpdateFailure['UpdateGroupsParentGroupIdAndPosition'] = '$groupUpdateQuery';
                                    }
                                } else if ($oldGroupParentType == 'task' && $newGroupParentType == 'group') {
                                    $groupInsertQuery = "INSERT INTO tag_group_contents "
                                            . "(tag_group_id, tag_id, order_in_group) "
                                            . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                    $groupInsertParams = array(
                                        'newGroupParentId' => $newGroupParentId,
                                        'groupId' => $updateGroupId,
                                        'newGroupOrderInParent' => $newGroupOrderInParent
                                    );
                                    $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                    if ($groupInsertResult->rowCount() == 1) {
                                        $groupDeleteQuery = "DELETE FROM task_contents "
                                                . "WHERE tag_group_id = :groupId "
                                                . "LIMIT 1";
                                        $groupDeleteParams['groupId'] = $updateGroupId;
                                        $groupDeleteResult = run_prepared_query($DBH, $groupDeleteQuery, $groupDeleteParams);
                                        if (!$groupDeleteResult || ($groupDeleteResult && $groupDeleteResult->rowCount() != 1)) {
                                            $databaseUpdateFailure['DeleteGroupFromTaskBeforeInsertInNewParentGroup'] = '$groupDeleteQuery';
                                        }
                                    } else {
                                        $databaseUpdateFailure['InsertGroupIntoGroupContentsTableAfterRemovalFromOldParentTask'] = '$groupInsertQuery';
                                    }
                                } else if ($oldGroupParentType == 'group' && $newGroupParentType == 'task') {
                                    $groupInsertQuery = "INSERT INTO task_contents "
                                            . "(task_id, tag_group_id, order_in_task) "
                                            . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                    $groupInsertParams = array(
                                        'newGroupParentId' => $newGroupParentId,
                                        'groupId' => $updateGroupId,
                                        'newGroupOrderInParent' => $newGroupOrderInParent
                                    );
                                    $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                    if ($groupInsertResult->rowCount() == 1) {
                                        $groupDeleteQuery = "DELETE FROM tag_group_contents "
                                                . "WHERE tag_id = :groupId AND tag_group_id = :oldGroupParentId "
                                                . "LIMIT 1";
                                        $groupDeleteParams = array(
                                            'groupId' => $updateGroupId,
                                            'oldGroupParentId' => $oldGroupParentId
                                        );
                                        $groupDeleteResult = run_prepared_query($DBH, $groupDeleteQuery, $groupDeleteParams);
                                        if (!$groupDeleteResult || ($groupDeleteResult && $groupDeleteResult->rowCount() != 1)) {
                                            $databaseUpdateFailure['DeleteGroupFromGroupBeforeInsertInNewParentTask'] = '$groupDeleteQuery';
                                        }
                                    } else {
                                        $databaseUpdateFailure['InsertGroupIntoTaskContentsTableAfterRemovalFromOldParentGroup'] = '$groupInsertQuery';
                                    }
                                }
                            } else {
                                $databaseUpdateFailure['OldParentContainerOrderUpdate'] = '$oldParentContainerUpdateQuery';
                            }
                        } else {
                            $databaseUpdateFailure['NewParentContainerOrderUpdate'] = '$newParentContainerUpdateQuery';
                        }


////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Staying in the old container
                    } else {
                        $newContainer = false;
                        if ($oldGroupParentType == 'task') {
                            $parentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    task_id AS parent_id, 
                                    tag_group_id AS child_id, 
                                    order_in_task AS order_number 
                                FROM 
                                    task_contents 
                                WHERE 
                                    task_id = :parentId 
                                ORDER BY 
                                    order_number";
                        } else {
                            $parentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    tag_group_id AS parent_id, 
                                    tag_id AS child_id, 
                                    order_in_group AS order_number 
                                FROM 
                                    tag_group_contents 
                                WHERE 
                                    tag_group_id = :parentId 
                                ORDER BY 
                                    order_number";
                        }
                        $parentContainerOrderParams['parentId'] = $oldGroupParentId;
                        $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                        $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($parentContainerOrder);

                        $resequencingNumber = 1;
                        $resequenced = false;
                        $groupOrderChanged = false;
                        foreach ($parentContainerOrder as &$individualGroup) {
                            if ($individualGroup ['order_number'] != $resequencingNumber) {
                                $dataChanges = TRUE;
                                $individualGroup['order_number'] = $resequencingNumber;
                                $resequenced = true;
                            }
                            if ($individualGroup ['child_id'] == $updateGroupId && $individualGroup ['order_number'] != $newGroupOrderInParent) {
                                $dataChanges = TRUE;
                                if ($newGroupOrderInParent > $oldGroupOrderInParent) {
                                    $groupOrderChanged = 'up';
                                } else {
                                    $groupOrderChanged = 'down';
                                }
                            }
                            $resequencingNumber++;
                        }

                        if ($groupOrderChanged) {
                            foreach ($parentContainerOrder as &$individualGroup) {
                                if ($groupOrderChanged == 'up' && $individualGroup ['child_id'] != $updateGroupId) {
                                    if ($individualGroup ['order_number'] > $oldGroupOrderInParent && $individualGroup ['order_number'] <= $newGroupOrderInParent) {
                                        $individualGroup['order_number'] --;
                                    }
                                } elseif ($groupOrderChanged == 'down' && $individualGroup ['child_id'] != $updateGroupId) {
                                    if ($individualGroup ['order_number'] < $oldGroupOrderInParent && $individualGroup ['order_number'] >= $newGroupOrderInParent) {
                                        $individualGroup['order_number'] ++;
                                    }
                                }
                                if ($individualGroup ['child_id'] == $updateGroupId) {
                                    $individualGroup['order_number'] = $newGroupOrderInParent;
                                    $resequenced = true;
                                }
                            }
                        }

                        if ($resequenced) {
                            if ($newGroupParentType == 'task') {
                                $parentContainerUpdateQuery = "UPDATE task_contents SET order_in_task = CASE id ";
                            } else {
                                $parentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";
                            }
                            foreach ($parentContainerOrder as $group) {
                                $parentContainerUpdateQuery .= "WHEN {$group['db_id']} THEN {$group['order_number']} ";
                                $idsToUpdate[] = $group['db_id'];
                            }
                            $whereInIdsToUpdate = where_in_string_builder($idsToUpdate);
                            $limitAmount = count($idsToUpdate);
                            $parentContainerUpdateQuery .= "END WHERE id IN ($whereInIdsToUpdate) LIMIT $limitAmount";
                            $parentContainerOrderResult = $DBH->query($parentContainerUpdateQuery);
                            if ($parentContainerOrderResult) {
                                
                            } else {
                                $databaseUpdateFailure['ExistingParentContainerOrderUpdate'] = '$parentContainerUpdateQuery';
                            }
                        }
                    }
                } // End !InvalidRequiredField
                $dbQueryGroupId = $updateGroupId;
            } else if ($projectEditSubAction == 'createNewGroup') {

                $groupAlreadyPresentQuery = '
                                SELECT tag_group_id
                                FROM tag_group_metadata
                                WHERE project_id = :projectId
                                    AND name = :name
                                    AND description = :description
                                    AND display_text = :displayText
                                    AND contains_groups = :containsGroups
                            ';
                $groupAlreadyPresentParams = array(
                    'projectId' => $projectMetadata['project_id'],
                    'name' => $newGroupName,
                    'description' => $newGroupDescription,
                    'displayText' => $newGroupDisplayText,
                    'containsGroups' => (int) $newGroupContainsGroupsStatus
                );
                $groupAlreadyPresentResult = run_prepared_query($DBH, $groupAlreadyPresentQuery, $groupAlreadyPresentParams);
                $existingGroupId = $groupAlreadyPresentResult->fetchColumn();
                if (!$existingGroupId) {

                    $dataChanges = TRUE;

//////////////////////////////////////////////////////////////////////////////
// Insert new group into tag_group_metadata
                    $newGroupInsertQuery = "INSERT INTO tag_group_metadata "
                            . "(project_id, is_enabled, contains_groups, name, description, display_text, force_width, has_border, has_color) "
                            . "VALUES (:projectId, :isEnabled, :containsGroups, :name, :description, :displayText, :forceWidth, :hasBorder, :hasColor)";
                    $newGroupInsertParams = array(
                        'projectId' => $projectId,
                        'isEnabled' => (int) $newGroupStatus,
                        'containsGroups' => (int) $newGroupContainsGroupsStatus,
                        'name' => $newGroupName,
                        'description' => $newGroupDescription,
                        'displayText' => $newGroupDisplayText,
                        'forceWidth' => $newGroupWidth,
                        'hasBorder' => (int) $newGroupBorderStatus,
                        'hasColor' => $newGroupColor,
                    );
                    $newGroupInsertResult = run_prepared_query($DBH, $newGroupInsertQuery, $newGroupInsertParams);

//////////////////////////////////////////////////////////////////////////////
// If the insert was successful then reorder the parent container content list
// to make room for the new group and insert it in the parent container content list.
                    if ($newGroupInsertResult->rowCount() == 1) {
                        $newGroupId = $DBH->lastInsertID();



// Find the current contents of the parent container.
                        if ($newGroupParentType == 'task') {
                            $parentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    task_id AS parent_id, 
                                    tag_group_id AS child_id, 
                                    order_in_task AS order_number 
                                FROM 
                                    task_contents 
                                WHERE 
                                    task_id = :parentId 
                                ORDER BY 
                                    order_number";
                        } else {
                            $parentContainerOrderQuery = "
                                SELECT 
                                    id AS db_id, 
                                    tag_group_id AS parent_id, 
                                    tag_id AS child_id, 
                                    order_in_group AS order_number 
                                FROM 
                                    tag_group_contents 
                                WHERE 
                                    tag_group_id = :parentId 
                                ORDER BY 
                                    order_number";
                        }

                        $parentContainerOrderParams['parentId'] = $newGroupParentId;
                        $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                        $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($parentContainerOrder);
                        if ($numberOfSiblings >= 1) {
// Check for sequential numbering of the existing contents. Resequence sequentially if necessary.
                            $resequencingNumber = 1;
                            foreach ($parentContainerOrder as &$individualGroup) {
                                if ($individualGroup ['order_number'] != $resequencingNumber) {
                                    $individualGroup['order_number'] = $resequencingNumber;
                                }
                                $resequencingNumber++;
                            }

// Increment parent contents of equal or higher order than the new group by one number to make room.
                            foreach ($parentContainerOrder as &$individualGroup) {
                                if ($individualGroup ['order_number'] >= $newGroupOrderInParent) {
                                    $individualGroup['order_number'] ++;
                                }
                            }

// Update the database with the new order sequence.
                            if ($newGroupParentType == 'task') {
                                $parentContainerUpdateQuery = "UPDATE task_contents SET order_in_task = CASE id ";
                            } else {
                                $parentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";
                            }
                            foreach ($parentContainerOrder as $group) {
                                $parentContainerUpdateQuery .= "WHEN {$group['db_id']} THEN {$group['order_number']} ";
                                $idsToUpdate[] = $group['db_id'];
                            }
                            $whereInIdsToUpdate = where_in_string_builder($idsToUpdate);
                            $limitAmount = count($idsToUpdate);
                            $parentContainerUpdateQuery .= "END WHERE id IN ($whereInIdsToUpdate) LIMIT $limitAmount";
                            $parentContainerOrderResult = $DBH->query($parentContainerUpdateQuery);
                        }

                        if (($numberOfSiblings >= 1 && $parentContainerOrderResult ) ||
                                $numberOfSiblings == 0) {
// If the update of the parent contents order was successfull then insert the new
// group into the parent content list
                            if ($newGroupParentType == 'task') {
                                $groupInsertQuery = "INSERT INTO task_contents "
                                        . "(task_id, tag_group_id, order_in_task) "
                                        . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                $groupInsertParams = array(
                                    'newGroupParentId' => $newGroupParentId,
                                    'groupId' => $newGroupId,
                                    'newGroupOrderInParent' => $newGroupOrderInParent
                                );
                                $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                            } else {
                                $groupInsertQuery = "INSERT INTO tag_group_contents "
                                        . "(tag_group_id, tag_id, order_in_group) "
                                        . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                $groupInsertParams = array(
                                    'newGroupParentId' => $newGroupParentId,
                                    'groupId' => $newGroupId,
                                    'newGroupOrderInParent' => $newGroupOrderInParent
                                );
                                $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                            }
                            if ($groupInsertResult->rowCount() === 1) {
                                $dbQueryGroupId = $newGroupId;
                            } else {
                                $databaseUpdateFailure['NewGroupParentContainerOrderGroupInsertion'] = $groupInsertQuery;
                            }
                        } else {
                            $databaseUpdateFailure['NewGroupParentReordering'] = $parentContainerUpdateQuery;
                        }
                    } else {
                        $databaseUpdateFailure['InsertNewGroupMetaData'] = $newGroupInsertQuery;
                    }
                } else {
                    $dbQueryGroupId = $existingGroupId;
                }
            }


            if ($importStatus != 12) {
                $taskEditingButtonHTML = '
                            <input type="button" class="clickableButton" id="returnToTaskSelection"
                                title="This will return you to the group selection screen to choose
                                another group based action." value="Choose Another Group Action">';
            } else {
                $taskEditingButtonHTML = '';
            }

            if ($invalidRequiredField) {

                $editSubmittedFlag = false;
                $failedSubmissionHTML = '<p class="error">';
                $failedFields = '';
                foreach ($invalidRequiredField as $invalidFieldName) {
                    switch ($invalidFieldName) {
                        case 'newGroupName':
                            $failedFields .= '<br>Group Admin Name';
                            break;
                        case 'newDisplayText':
                            $failedFields .= '<br>Group Display Text';
                            break;
                        case 'newGroupColor':
                            $failedFields .= '<br>Group Background Color';
                            break;
                        case 'parentId':
                            $failedFields .= '<br>Task or Group Parent Container';
                            break;
                        case 'groupContents':
                            $failedFields .= "<br>Group Contains (A group must be empty before it's content type can be changed)";
                            break;
                    }
                }
                if (!empty($failedFields)) {
                    $failedSubmissionHTML .= 'The following required fields were either missing user input or contained invalid data:' . $failedFields;
                } else {
                    $failedSubmissionHTML .= 'Data was missing from the submission. Please try again.';
                }
                $failedSubmissionHTML .= '</p>';
                if (isset($newGroupName)) {
                    $currentGroupName = $newGroupName;
                }
                if (isset($newGroupDescription)) {
                    $currentGroupDescription = $newGroupDescription;
                }
                if (isset($newGroupDisplayText)) {
                    $currentGroupDisplayText = $newGroupDisplayText;
                }
                if (isset($newGroupWidth)) {
                    $currentGroupWidth = $newGroupWidth;
                }
                if (isset($newGroupBorderStatus)) {
                    $currentGroupBorder = $newGroupBorderStatus;
                }
                if (isset($newGroupColor)) {
                    $currentGroupColor = $newGroupColor;
                }
                if (isset($newGroupStatus)) {
                    $currentGroupStatus = $newGroupStatus;
                }
                if (isset($newGroupContainsGroupsStatus)) {
                    $currentGroupContainsGroups = $newGroupContainsGroupsStatus;
                }
            } else if (!$dataChanges) {
                $actionSummaryHTML = <<<EOL
                                <h3>No Changes Detected</h3>
                                <p>No change in the tag detail data has been detected. The database has not been altered.</p>

EOL;
            } else if ($databaseUpdateFailure) {
                $projectUpdateErrorHTML = <<<EOL
                                <h3>Update Failed</h3>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will return you to the Question Builder menu
                                        for you to choose another item to create or edit."
                                            value="Return To Question Builder Menu">
                                </div>

EOL;
            } else {
                $actionSummaryHTML = <<<EOL
                                <h3>Update Successful</h3>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
            }

            if (!$databaseUpdateFailure && !$invalidRequiredField) {
// CREATE HTML TO SHOW THE NEW TASK STATUS
                $parentIsTask = TRUE;
                $summaryQuery = "SELECT * FROM tag_group_metadata WHERE tag_group_id = :groupId";
                $summaryParams['groupId'] = $dbQueryGroupId;
                $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                $dbGroupMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                $summaryParentQuery = "SELECT tm.name, tc.order_in_task as position_in_parent "
                        . "FROM task_contents tc "
                        . "LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id "
                        . "WHERE tc.tag_group_id = :groupId";
                $summaryParentResults = run_prepared_query($DBH, $summaryParentQuery, $summaryParams);
                $dbParentMetadata = $summaryParentResults->fetch(PDO::FETCH_ASSOC);
                if (!$dbParentMetadata) {
                    $summaryParentQuery = "SELECT tgm.name, tgc.order_in_group as position_in_parent "
                            . "FROM tag_group_contents tgc "
                            . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                            . "WHERE tgc.tag_id = :groupId AND tgm.contains_groups = 1";
                    $summaryParentResults = run_prepared_query($DBH, $summaryParentQuery, $summaryParams);
                    $dbParentMetadata = $summaryParentResults->fetch(PDO::FETCH_ASSOC);
                    if ($dbParentMetadata) {
                        $parentIsTask = FALSE;
                    }
                }

                $dbGroupName = htmlspecialchars($dbGroupMetadata['name']);

                $dbGroupDescription = restoreSafeHTMLTags(htmlspecialchars($dbGroupMetadata['description']));

                $dbGroupDisplayText = htmlspecialchars($dbGroupMetadata['display_text']);

                if ($dbGroupMetadata ['contains_groups'] == 0) {
                    $dbGroupContents = "Tags";
                } else {
                    $dbGroupContents = "Groups";
                }

                if ($dbGroupMetadata ['force_width'] == 0) {
                    $dbGroupWidth = "Set Automatically";
                } else {
                    $dbGroupWidth = $dbGroupMetadata ['force_width'] . ' px';
                }

                if ($dbGroupMetadata ['has_border'] == 0) {
                    $dbBorderStatus = "No";
                } else {
                    $dbBorderStatus = "Yes";
                }

                if (empty($dbGroupMetadata['has_color'])) {
                    $dbBackgroundColor = "No";
                } else {
                    $dbBackgroundColor = "Yes <span style=\"width: 50px; border: 1px solid black; background-color: #{$dbGroupMetadata['has_color']}; border-radius: 5px;\">&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;</span>";
                }

                if ($parentIsTask) {
                    $dbParentType = "Task";
                } else {
                    $dbParentType = "Group";
                }

                $ordinalPositionInParent = ordinal_suffix($dbParentMetadata['position_in_parent']);

                if ($dbGroupMetadata ['is_enabled'] == 1) {
                    $dbGroupStatusText = 'Enabled';
                } else {
                    $dbGroupStatusText = '<span class="redHighlight">Disabled</span>';
                }

                $dbParentName = htmlspecialchars($dbParentMetadata['name']);

                $actionSummaryHTML .= <<<EOL
                                <h3>Group Summary</h3>
                                <table id="updateSummaryTable">
                                    <tbody>
                                        <tr>
                                            <td>Group Admin Name:</td>
                                            <td class="userData">$dbGroupName</td>
                                        </tr>
                                        <tr>
                                            <td>Group Admin Description:</td>
                                            <td class="userData">$dbGroupDescription</td>
                                        </tr>
                                        <tr>
                                            <td>Group Display Text:</td>
                                            <td class="userData">$dbGroupDisplayText</td>
                                        </tr>
                                        <tr>
                                            <td>Group Contains:</td>
                                            <td class="userData">$dbGroupContents</td>
                                        </tr>
                                        <tr>
                                            <td>Group Width:</td>
                                            <td class="userData">$dbGroupWidth</td>
                                        </tr>
                                        <tr>
                                            <td>Group Has A Border:</td>
                                            <td class="userData">$dbBorderStatus</td>
                                        </tr>
                                        <tr>
                                            <td>Group Has A Background Color:</td>
                                            <td class="userData">$dbBackgroundColor</td>
                                        </tr>
                                        <tr>
                                            <td>Group Is Contained In:</td>
                                            <td class="userData">$dbParentType</td>
                                        </tr>
                                        <tr>
                                            <td>Name of Parent Container:</td>
                                            <td class="userData">$dbParentName</td>
                                        </tr>
                                        <tr>
                                            <td>Position In Parent Container:</td>
                                            <td class="userData">$ordinalPositionInParent</td>
                                        </tr>
                                        <tr>
                                            <td>Group Status:</td>
                                            <td class = "userData">$dbGroupStatusText</td>
                                        </tr>
                                    </tbody>
                                </table>
EOL;
                if ($importStatus == 10) {
                    $actionSummaryHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionSummaryHTML .= <<<EOL
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will return you to the Question Builder menu
                                        for you to choose another item to create or edit."
                                            value="Return To Question Builder Menu">
                                </div>

EOL;
            }

            break;

//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// UPDATE TAGS
//
//
        case 'tags':
// Check that all required fields are present.

            $invalidRequiredField = FALSE;
            $processingFailure = FALSE;
            $dataChanges = FALSE;
            $databaseUpdateFailure = FALSE;

            $newTagName = filter_input(INPUT_POST, 'newTagName');
            $newTagDescription = filter_input(INPUT_POST, 'newTagDescription');
            $newTagDisplayText = filter_input(INPUT_POST, 'newTagDisplayText');
            $newTagToolTipText = filter_input(INPUT_POST, 'newTagToolTipText');
            $newTagType = filter_input(INPUT_POST, 'newTagType', FILTER_VALIDATE_INT);
            $newTagRadioGroupName = filter_input(INPUT_POST, 'newTagRadioGroupName');
            $newParentId = filter_input(INPUT_POST, 'newParentId', FILTER_VALIDATE_INT);
            $newTagOrder = filter_input(INPUT_POST, 'newTagOrder', FILTER_VALIDATE_INT);
            $newTagStatus = filter_input(INPUT_POST, 'newTagStatus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $newTagToolTipImage = filter_var($_FILES['newTagToolTipImage']['name']);
            $newNoTooltipImageStatus = filter_input(INPUT_POST, 'noTooltipImage', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $oldParentId = filter_input(INPUT_POST, 'oldParentId', FILTER_VALIDATE_INT);
            $oldTagOrder = filter_input(INPUT_POST, 'oldTagOrder', FILTER_VALIDATE_INT);


            if (empty($projectEditSubAction)) {
                $invalidRequiredField[] = 'projectEditSubAction';
            } else if ($projectEditSubAction == 'updateExistingTag' && empty($tagId)) {
                $invalidRequiredField[] = 'tagId';
            } else if ($projectEditSubAction == 'updateExistingTag') {
                $tagMetadata = retrieve_entity_metadata($DBH, $tagId, 'tag');
                if (!$tagMetadata) {
                    $invalidRequiredField[] = 'tagId';
                }
            }
            if (empty($newTagName)) {
                $invalidRequiredField[] = 'newTagName';
            }
            if (is_null($newTagDescription)) {
                $newTagDescription = '';
            }
            if (empty($newTagDisplayText)) {
                $invalidRequiredField[] = 'newTagDisplayText';
            }
            if (is_null($newTagToolTipText)) {
                $newTagToolTipText = '';
            }

            switch ($newTagType) {
                case 0:
                    $newCommentBoxFlag = 0;
                    $newRadioButtonFlag = 0;
                    $newTagRadioGroupName = "";
                    break;
                case 1:
                    if ($newTagRadioGroupName) {
                        $newCommentBoxFlag = 0;
                        $newRadioButtonFlag = 1;
                    } else {
                        $invalidRequiredField[] = 'newTagRadioGroupName';
                    }
                    break;
                case 2:
                    $newCommentBoxFlag = 1;
                    $newRadioButtonFlag = 0;
                    $newTagRadioGroupName = "";
                    break;
                default:
                    $invalidRequiredField[] = 'newTagType';
                    break;
            }

            if (empty($newParentId)) {
                $invalidRequiredField[] = 'newParentId';
            }

            if (empty($newTagOrder)) {
                $invalidRequiredField[] = 'newTagOrder';
            }

            if (is_null($newTagStatus)) {
                $invalidRequiredField[] = 'newTagStatus';
            }

            if (empty($newNoTooltipImageStatus) && $newTagToolTipImage) {
                $tooltipNameIsValid = true;
                $newTagToolTipImage = trim($newTagToolTipImage);
                if (empty($newTagToolTipImage)) {
                    $tooltipNameIsValid = false;
                }
                if ($tooltipNameIsValid) {
                    $dotLocation = stripos($newTagToolTipImage, '.');
                    if ($dotLocation) {
                        $fileExtension = substr($newTagToolTipImage, $dotLocation + 1);
                        if ($fileExtension != 'jpg' && $fileExtension != 'jpeg') {
                            $tooltipNameIsValid = false;
                        }
                    } else {
                        $tooltipNameIsValid = false;
                    }
                }
                if ($tooltipNameIsValid) {
                    $tooltipName = substr($newTagToolTipImage, 0, $dotLocation);
                    $forbiddenChars = array('*', '"', '/', '\\', ':', '|', '?', ',', '<', '>', ';', '[', ']');
                    foreach ($forbiddenChars as $char) {
                        if (strpos($tooltipName, $char) !== false) {
                            $tooltipNameIsValid = false;
                            break;
                        }
                    }
                }
                if (!$tooltipNameIsValid) {
                    $invalidRequiredField[] = 'newTooltipName';
                }
            } else {
                $newTagToolTipImage = '';
                $newTagToolTipImageWidth = 0;
                $newTagToolTipImageHeight = 0;
            }

// IF A TAG_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingTag
            if (isset($tagId) &&
                    isset($oldParentId) &&
                    isset($oldTagOrder) &&
                    $projectEditSubAction == 'updateExistingTag') {

                $updateTagId = $tagId;
                if (empty($updateTagId)) {
                    $invalidRequiredField[] = 'tagId';
                }
                if (empty($oldParentId)) {
                    $invalidRequiredField[] = 'oldParentId';
                }
                if (empty($oldTagOrder)) {
                    $invalidRequiredField[] = 'oldTagOrder';
                }

                if (!$invalidRequiredField) {

                    $oldTagStatus = $tagMetadata['is_enabled'];
                    $oldCommentBoxFlag = $tagMetadata['is_comment_box'];
                    $oldRadioButtonFlag = $tagMetadata['is_radio_button'];
                    $oldRadioButtonGroupName = $tagMetadata['radio_button_group'];
                    $oldTagName = $tagMetadata['name'];
                    $oldTagDescription = $tagMetadata['description'];
                    $oldTagDisplayText = $tagMetadata['display_text'];
                    $oldTagTooltipText = $tagMetadata['tooltip_text'];
                    $oldTagTooltipImage = $tagMetadata['tooltip_image'];

                    $tagFieldsToUpdate = array();

                    if ($oldTagStatus != $newTagStatus) {
                        $tagFieldsToUpdate['is_enabled'] = (int) $newTagStatus;
                    }

                    if ($oldTagName != $newTagName) {
                        $tagFieldsToUpdate['name'] = $newTagName;
                    }

                    if ($oldTagDescription != $newTagDescription) {
                        $tagFieldsToUpdate['description'] = $newTagDescription;
                    }

                    if ($oldTagDisplayText != $newTagDisplayText) {
                        $tagFieldsToUpdate['display_text'] = $newTagDisplayText;
                    }

                    if ($oldTagTooltipText != $newTagToolTipText) {
                        $tagFieldsToUpdate['tooltip_text'] = $newTagToolTipText;
                    }

                    if ($newNoTooltipImageStatus && !empty($oldTagTooltipImage)) {
                        $tagFieldsToUpdate['tooltip_image'] = '';
                        $tagFieldsToUpdate['tooltip_image_width'] = 0;
                        $tagFieldsToUpdate['tooltip_image_height'] = 0;
                        unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$oldTagTooltipImage");
                    }



                    if (!$newNoTooltipImageStatus && !empty($newTagToolTipImage) && $oldTagTooltipImage != $newTagToolTipImage) {

                        while (true) {
// Import the image into PHP GD
                            // file_put_contents('questionBuilderLog.txt', "In New Tooltip if.\r\n");
                            if (file_exists("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage")) {
                                // file_put_contents('questionBuilderLog.txt', "File already exists.\r\n");
                                unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage");
                            }
                            $originalImage = imagecreatefromjpeg($_FILES['newTagToolTipImage']['tmp_name']);
                            if (!$originalImage) {
                                // file_put_contents('questionBuilderLog.txt', "Reading new image failed.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                break;
                            }

// Determine original image dimensions.
                            $imageWidth = imagesx($originalImage);
                            $imageHeight = imagesy($originalImage);
//                            print $imageWidth . ' x ' . $imageHeight . '<br>';
// Check the image is Landscape. Skip if not.
                            if ($imageWidth > 400 || $imageHeight > 400) {
                                $imageAspectRatio = $imageWidth / $imageHeight;
//                                print $imageAspectRatio . '<br>';
//Calculate the maximum dimesions

                                if ($imageHeight > $imageWidth) {
                                    $newTagToolTipImageHeight = 400;
                                    $newTagToolTipImageWidth = 400 * $imageAspectRatio;
                                } else {
                                    $newTagToolTipImageWidth = 400;
                                    $newTagToolTipImageHeight = 400 / $imageAspectRatio;
                                }
                            } else {
                                $newTagToolTipImageWidth = $imageWidth;
                                $newTagToolTipImageHeight = $imageHeight;
                            }
//                            print $newTagToolTipImageWidth . ' x ' . $newTagToolTipImageHeight . '<br>';
// Create a blank canvas of the correct size.
                            // file_put_contents('questionBuilderLog.txt', "Creating new image.\r\n");
                            $newImage = imagecreatetruecolor($newTagToolTipImageWidth, $newTagToolTipImageHeight);
// Copy the original to the new display image canvas resizing as it copies.
                            if (!imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newTagToolTipImageWidth, $newTagToolTipImageHeight, $imageWidth, $imageHeight)) {
                                // file_put_contents('questionBuilderLog.txt', "Creating image failed.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                imagedestroy($originalImage);
                                imagedestroy($newImage);
                                break;
                            }

// Save the new display image to the disk.
                            // file_put_contents('questionBuilderLog.txt', "Saving image.\r\n");
                            if (!imagejpeg($newImage, "images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage", 75)) {
                                // file_put_contents('questionBuilderLog.txt', "Failed to save image.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                imagedestroy($originalImage);
                                imagedestroy($newImage);
                                break;
                            }
                            // Release the memory used in the resizing process
                            // file_put_contents('questionBuilderLog.txt', "Destroying temp files.\r\n");
                            imagedestroy($originalImage);
                            imagedestroy($newImage);
                            if (!chmod("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage", 0775)) {
                                $processingFailure[] = 'newTagToolTipImage';
                                unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage");
                            }
                            break;
                        } // END while(true)



                        if (!empty($oldTagTooltipImage) &&
                                file_exists("images/projects/{$projectMetadata['project_id']}/tooltips/$oldTagTooltipImage")) {
                            unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$oldTagTooltipImage");
                        }
                        $tagFieldsToUpdate['tooltip_image'] = $newTagToolTipImage;
                        $tagFieldsToUpdate['tooltip_image_width'] = $newTagToolTipImageWidth;
                        $tagFieldsToUpdate['tooltip_image_height'] = $newTagToolTipImageHeight;
                    }

                    if ($oldCommentBoxFlag != $newCommentBoxFlag) {
                        $tagFieldsToUpdate['is_comment_box'] = $newCommentBoxFlag;
                    }

                    if ($oldRadioButtonFlag != $newRadioButtonFlag) {
                        $tagFieldsToUpdate['is_radio_button'] = $newRadioButtonFlag;
                    }

                    if ($oldRadioButtonGroupName != $newTagRadioGroupName) {
                        $tagFieldsToUpdate['radio_button_group'] = $newTagRadioGroupName;
                    }

                    if (count($tagFieldsToUpdate) > 0 && !$processingFailure) {
                        $dataChanges = TRUE;
                        $tagUpdateFieldsQuery = "UPDATE tags "
                                . "SET ";
                        $tagUpdateFieldsParams = array();
                        $columnUpdateCount = 0;
                        foreach ($tagFieldsToUpdate as $column => $value) {
                            $tagUpdateFieldsQuery .= "$column=:$column";
                            $columnUpdateCount++;
                            if ($columnUpdateCount != count($tagFieldsToUpdate)) {
                                $tagUpdateFieldsQuery .= ", ";
                            }
                            $tagUpdateFieldsParams[$column] = $value;
                        }
                        $tagUpdateFieldsQuery .= " WHERE tag_id = $updateTagId LIMIT 1";

                        $tagUpdateResult = run_prepared_query($DBH, $tagUpdateFieldsQuery, $tagUpdateFieldsParams);
                        if ($tagUpdateResult->rowCount() == 1) {
                            
                        } else {
                            $databaseUpdateFailure['Column Updates'] = 'Failed';
                        }
                    }





////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Moving to a new container
                    if ($newParentId != $oldParentId && !$processingFailure) {
                        $dataChanges = TRUE;
//////////////////////////////////////////////////////////////////////////////
// New Container Reordering
                        $newContainer = true;
                        $resequencing = FALSE;
                        $reordering = FALSE;
                        $newParentContainerOrderQuery = "
                            SELECT 
                                id AS db_id, 
                                tag_group_id AS parent_id, 
                                tag_id AS child_id, 
                                order_in_group AS order_number
                            FROM 
                                tag_group_contents 
                            WHERE 
                                tag_group_id = :parentId
                            ORDER BY 
                                order_number";
                        $newParentContainerOrderParams['parentId'] = $newParentId;
                        $newParentContainerOrderResult = run_prepared_query($DBH, $newParentContainerOrderQuery, $newParentContainerOrderParams);
                        $newParentContainerOrder = $newParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($newParentContainerOrder);

                        $resequencingNumber = 1;
                        foreach ($newParentContainerOrder as &$individualTag) {
                            if ($individualTag ['order_number'] != $resequencingNumber) {
                                $individualTag['order_number'] = $resequencingNumber;
                                $resequencing = TRUE;
                            }
                            $resequencingNumber++;
                        }

                        foreach ($newParentContainerOrder as &$individualTag) {
                            if ($individualTag ['order_number'] >= $newTagOrder) {
                                $individualTag['order_number'] ++;
                                $reordering = TRUE;
                            }
                        }

/////////////////////////////////////////////////////////////////////////////
// Old Container Reordering

                        $oldParentContainerOrderQuery = "
                            SELECT 
                                id AS db_id, 
                                tag_group_id AS parent_id, 
                                tag_id AS child_id, 
                                order_in_group AS order_number 
                            FROM 
                                tag_group_contents 
                            WHERE 
                                tag_group_id = :parentId 
                            ORDER BY 
                                order_number";
                        $oldParentContainerOrderParams['parentId'] = $oldParentId;
                        $oldParentContainerOrderResult = run_prepared_query($DBH, $oldParentContainerOrderQuery, $oldParentContainerOrderParams);
                        $oldParentContainerOrder = $oldParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($oldParentContainerOrder);

                        for ($i = 0; $i < $numberOfSiblings; $i++) {
                            if ($oldParentContainerOrder [$i]['child_id'] == $updateTagId) {
                                unset($oldParentContainerOrder[$i]);
                            }
                        }

                        $resequencingNumber = 1;
                        foreach ($oldParentContainerOrder as &$individualTag) {
                            if ($individualTag ['order_number'] != $resequencingNumber) {
                                $individualTag['order_number'] = $resequencingNumber;
                            }
                            $resequencingNumber++;
                        }

//////////////////////////////////////////////////////////////////////////////
// Update the database - New container

                        $newParentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";

                        foreach ($newParentContainerOrder as $tag) {
                            $newParentContainerUpdateQuery .= "WHEN {$tag['db_id']} THEN {$tag['order_number']} ";
                            $newIdsToUpdate[] = $tag['db_id'];
                        }
                        $newWhereInIdsToUpdate = where_in_string_builder($newIdsToUpdate);
                        $newLimitAmount = count($newIdsToUpdate);
                        $newParentContainerUpdateQuery .= "END WHERE id IN ($newWhereInIdsToUpdate) LIMIT $newLimitAmount";
                        $newParentContainerOrderResult = $DBH->query($newParentContainerUpdateQuery);
                        if ($newParentContainerOrderResult) {
//////////////////////////////////////////////////////////////////////////////
// Update the database. Old Container

                            $oldParentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";
                            foreach ($oldParentContainerOrder as $tag) {
                                $oldParentContainerUpdateQuery .= "WHEN {$tag['db_id']} THEN {$tag['order_number']} ";
                                $oldIdsToUpdate[] = $tag['db_id'];
                            }
                            $oldWhereInIdsToUpdate = where_in_string_builder($oldIdsToUpdate);
                            $oldLimitAmount = count($oldIdsToUpdate);
                            $oldParentContainerUpdateQuery .= "END WHERE id IN ($oldWhereInIdsToUpdate) LIMIT $oldLimitAmount";
                            $oldparentContainerOrderResult = $DBH->query($oldParentContainerUpdateQuery);
                            if ($oldparentContainerOrderResult) {

                                $tagUpdateQuery = "UPDATE tag_group_contents SET "
                                        . "tag_group_id = :newParentId, "
                                        . "order_in_group = :newTagPosition "
                                        . "WHERE tag_id = :tagId AND tag_group_id = :oldParentId "
                                        . "LIMIT 1";
                                $tagUpdateParams = array(
                                    'newParentId' => $newParentId,
                                    'newTagPosition' => $newTagOrder,
                                    'tagId' => $updateTagId,
                                    'oldParentId' => $oldParentId
                                );
                                $tagUpdateResults = run_prepared_query($DBH, $tagUpdateQuery, $tagUpdateParams);
                                $affectedRows = $tagUpdateResults->rowCount();

                                if (isset($affectedRows) && $affectedRows == 1) {
                                    
                                } else {
                                    $databaseUpdateFailure['TagGroupMembershipAndOrder'] = '$tagUpdateQuery';
                                }
                            } else {
                                $databaseUpdateFailure['OldParentContainerOrderUpdate'] = '$oldParentContainerUpdateQuery';
                            }
                        } else {
                            $databaseUpdateFailure['NewParentContainerOrderUpdate'] = '$newParentContainerUpdateQuery';
                        }


////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Staying in the old container
                    } else if (!$processingFailure) {
                        $newContainer = false;
                        $resequencing = FALSE;
                        $reordering = FALSE;

                        $parentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number "
                                . "FROM tag_group_contents "
                                . "WHERE tag_group_id = :parentId "
                                . "ORDER BY order_number";

                        $parentContainerOrderParams['parentId'] = $oldParentId;
                        $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                        $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                        $numberOfSiblings = count($parentContainerOrder);

                        $resequencingNumber = 1;
                        $newParentResequenced = false;
                        $groupOrderChanged = false;
                        foreach ($parentContainerOrder as &$individualTag) {
                            if ($individualTag ['order_number'] != $resequencingNumber) {
                                $dataChanges = TRUE;
//                                                print $individualTag['order_number'] . ' becomes ' . $resequencingNumber;
                                $individualTag['order_number'] = $resequencingNumber;
                                $resequencing = true;
                            }
                            if ($individualTag ['child_id'] == $updateTagId && $individualTag ['order_number'] != $newTagOrder) {
                                $dataChanges = TRUE;
                                if ($newTagOrder > $oldTagOrder) {
                                    $groupOrderChanged = 'up';
                                } else {
                                    $groupOrderChanged = 'down';
                                }
                            }
                            $resequencingNumber++;
                        }
                        if ($groupOrderChanged) {
                            foreach ($parentContainerOrder as &$individualTag) {
                                if ($groupOrderChanged == 'up' && $individualTag ['child_id'] != $updateTagId) {
                                    if ($individualTag ['order_number'] > $oldTagOrder && $individualTag ['order_number'] <= $newTagOrder) {
                                        $individualTag['order_number'] --;
                                    }
                                } elseif ($groupOrderChanged == 'down' && $individualTag ['child_id'] != $updateTagId) {
                                    if ($individualTag ['order_number'] < $oldTagOrder && $individualTag ['order_number'] >= $newTagOrder) {
                                        $individualTag['order_number'] ++;
                                    }
                                }
                                if ($individualTag ['child_id'] == $updateTagId) {
                                    $individualTag['order_number'] = $newTagOrder;
                                }
                            }
                            $reordering = true;
                        }

                        if ($resequencing || $reordering) {

                            $parentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";

                            foreach ($parentContainerOrder as $tag) {
                                $parentContainerUpdateQuery .= "WHEN {$tag['db_id']} THEN {$tag['order_number']} ";
                                $idsToUpdate[] = $tag['db_id'];
                            }
                            $whereInIdsToUpdate = where_in_string_builder($idsToUpdate);
                            $limitAmount = count($idsToUpdate);
                            $parentContainerUpdateQuery .= "END WHERE id IN ($whereInIdsToUpdate) LIMIT $limitAmount";
                            $parentContainerOrderResult = $DBH->query($parentContainerUpdateQuery);
                            if ($parentContainerOrderResult) {
//                                                print "Success updating same group order";
                            } else {
                                $databaseUpdateFailure['ExistingParentContainerOrderUpdate'] = '$parentContainerUpdateQuery';
                            }
                        } else {
                            
                        }
                    }
                } // End !InvalidRequiredField
                $dbQueryTagId = $updateTagId;
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
            } else if ($projectEditSubAction == 'createNewTag' && !$invalidRequiredField) {
                $tagAlreadyPresentQuery = '
                                SELECT tag_id
                                FROM tags
                                WHERE project_id = :projectId
                                    AND name = :name
                                    AND description = :description
                                    AND display_text = :displayText
                                    AND is_comment_box = :commentBoxFlag
                                    AND is_radio_button = :radioButtonFlag
                            ';
                $tagAlreadyPresentParams = array(
                    'projectId' => $projectId,
                    'name' => $newTagName,
                    'description' => $newTagDescription,
                    'displayText' => $newTagDisplayText,
                    'commentBoxFlag' => $newCommentBoxFlag,
                    'radioButtonFlag' => $newRadioButtonFlag
                );
                $tagAlreadyPresentResult = run_prepared_query($DBH, $tagAlreadyPresentQuery, $tagAlreadyPresentParams);
                $existingTagId = $tagAlreadyPresentResult->fetchColumn();
                if (!$existingTagId) {
                    $dataChanges = TRUE;

                    if (!$newNoTooltipImageStatus && !empty($newTagToolTipImage)) {

                        while (true) {
// Import the image into PHP GD
                            // file_put_contents('questionBuilderLog.txt', "In New Tooltip if.\r\n");
                            if (file_exists("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage")) {
                                // file_put_contents('questionBuilderLog.txt', "File already exists.\r\n");
                                unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage");
                            }
                            $originalImage = imagecreatefromjpeg($_FILES['newTagToolTipImage']['tmp_name']);
                            if (!$originalImage) {
                                // file_put_contents('questionBuilderLog.txt', "Reading new image failed.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                break;
                            }

// Determine original image dimensions.
                            $imageWidth = imagesx($originalImage);
                            $imageHeight = imagesy($originalImage);
//                            print $imageWidth . ' x ' . $imageHeight . '<br>';
// Check the image is Landscape. Skip if not.
                            if ($imageWidth > 400 || $imageHeight > 400) {
                                $imageAspectRatio = $imageWidth / $imageHeight;
//                                print $imageAspectRatio . '<br>';
//Calculate the maximum dimesions

                                if ($imageHeight > $imageWidth) {
                                    $newTagToolTipImageHeight = 400;
                                    $newTagToolTipImageWidth = 400 * $imageAspectRatio;
                                } else {
                                    $newTagToolTipImageWidth = 400;
                                    $newTagToolTipImageHeight = 400 / $imageAspectRatio;
                                }
                            } else {
                                $newTagToolTipImageWidth = $imageWidth;
                                $newTagToolTipImageHeight = $imageHeight;
                            }
//                            print $newTagToolTipImageWidth . ' x ' . $newTagToolTipImageHeight . '<br>';
// Create a blank canvas of the correct size.
                            // file_put_contents('questionBuilderLog.txt', "Creating new image.\r\n");
                            $newImage = imagecreatetruecolor($newTagToolTipImageWidth, $newTagToolTipImageHeight);
// Copy the original to the new display image canvas resizing as it copies.
                            if (!imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newTagToolTipImageWidth, $newTagToolTipImageHeight, $imageWidth, $imageHeight)) {
                                // file_put_contents('questionBuilderLog.txt', "Creating image failed.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                imagedestroy($originalImage);
                                imagedestroy($newImage);
                                break;
                            }

// Save the new display image to the disk.
                            // file_put_contents('questionBuilderLog.txt', "Saving image.\r\n");
                            if (!imagejpeg($newImage, "images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage", 75)) {
                                // file_put_contents('questionBuilderLog.txt', "Failed to save image.\r\n");
                                $processingFailure[] = 'newTagToolTipImage';
                                imagedestroy($originalImage);
                                imagedestroy($newImage);
                                break;
                            }
                            // Release the memory used in the resizing process
                            // file_put_contents('questionBuilderLog.txt', "Destroying temp files.\r\n");
                            imagedestroy($originalImage);
                            imagedestroy($newImage);
                            if (!chmod("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage", 0775)) {
                                $processingFailure[] = 'newTagToolTipImage';
                                unlink("images/projects/{$projectMetadata['project_id']}/tooltips/$newTagToolTipImage");
                            }
                            break;
                        } // END while(true)
                    }

                    if (!$processingFailure) {

//////////////////////////////////////////////////////////////////////////////
// Insert new group into tag_group_metadata
                        $newTagInsertQuery = "INSERT INTO tags "
                                . "(project_id, is_enabled, is_comment_box, is_radio_button, radio_button_group, name, description, display_text, display_image, tooltip_text, tooltip_image, tooltip_image_width, tooltip_image_height) "
                                . "VALUES (:projectId, :isEnabled, :commentBoxFlag, :radioButtonFlag, :radioButtonGroup, :name, :description, :displayText, :displayImage, :tooltipText, :tooltipImage, :tooltipImageWidth, :tooltipImageHeight)";
                        $newTagInsertParams = array(
                            'projectId' => $projectId,
                            'isEnabled' => $newTagStatus,
                            'commentBoxFlag' => $newCommentBoxFlag,
                            'radioButtonFlag' => $newRadioButtonFlag,
                            'radioButtonGroup' => $newTagRadioGroupName,
                            'name' => $newTagName,
                            'description' => $newTagDescription,
                            'displayText' => $newTagDisplayText,
                            'displayImage' => '',
                            'tooltipText' => $newTagToolTipText,
                            'tooltipImage' => $newTagToolTipImage,
                            'tooltipImageWidth' => $newTagToolTipImageWidth,
                            'tooltipImageHeight' => $newTagToolTipImageHeight
                        );
                        $newTagInsertResult = run_prepared_query($DBH, $newTagInsertQuery, $newTagInsertParams);

//////////////////////////////////////////////////////////////////////////////
// If the insert was successful then reorder the parent container content list
// to make room for the new group and insert it in the parent container content list.
                        if ($newTagInsertResult->rowCount() == 1) {
                            $newTagId = $DBH->lastInsertID();

                            $resequenced = false;

// Find the current contents of the parent container.

                            $parentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number "
                                    . "FROM tag_group_contents "
                                    . "WHERE tag_group_id = :parentId "
                                    . "ORDER BY order_number";
                            $parentContainerOrderParams['parentId'] = $newParentId;
                            $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                            $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                            $numberOfSiblings = count($parentContainerOrder);
                            if ($numberOfSiblings >= 1) {
// Check for sequential numbering of the existing contents. Resequence sequentially if necessary.
                                $resequencingNumber = 1;
                                foreach ($parentContainerOrder as &$individualTag) {
                                    if ($individualTag ['order_number'] != $resequencingNumber) {
                                        $individualTag['order_number'] = $resequencingNumber;
                                        $resequenced = true;
                                    }
                                    $resequencingNumber++;
                                }

// Increment parent contents of equal or higher order than the new group by one number to make room.
                                foreach ($parentContainerOrder as &$individualTag) {
                                    if ($individualTag ['order_number'] >= $newTagOrder) {
                                        $individualTag['order_number'] ++;
                                    }
                                }

// Update the database with the new order sequence.

                                $parentContainerUpdateQuery = "UPDATE tag_group_contents SET order_in_group = CASE id ";

                                foreach ($parentContainerOrder as $tag) {
                                    $parentContainerUpdateQuery .= "WHEN {$tag['db_id']} THEN {$tag['order_number']} ";
                                    $idsToUpdate[] = $tag['db_id'];
                                }
                                $whereInIdsToUpdate = where_in_string_builder($idsToUpdate);
                                $limitAmount = count($idsToUpdate);
                                $parentContainerUpdateQuery .= "END WHERE id IN ($whereInIdsToUpdate) LIMIT $limitAmount";
                                $parentContainerOrderResult = $DBH->query($parentContainerUpdateQuery);
                            }
                            if (($numberOfSiblings >= 1 && $parentContainerOrderResult ) ||
                                    $numberOfSiblings == 0) {
// If the update of the parent contents order was successfull then insert the new
// group into the parent content list
                                $tagInsertQuery = "INSERT INTO tag_group_contents "
                                        . "(tag_group_id, tag_id, order_in_group) "
                                        . "VALUES (:newParentId, :tagId, :newTagPosition)";
                                $tagInsertParams = array(
                                    'newParentId' => $newParentId,
                                    'tagId' => $newTagId,
                                    'newTagPosition' => $newTagOrder
                                );
                                $tagInsertResult = run_prepared_query($DBH, $tagInsertQuery, $tagInsertParams);

                                if ($tagInsertResult->rowCount() == 1) {
                                    $dbQueryTagId = $newTagId;
                                } else {
                                    $databaseUpdateFailure['NewTagParentGroupOrderTagInsertion'] = $tagInsertQuery;
                                }
                            } else {
                                $databaseUpdateFailure['NewTagParentGroupReordering'] = $parentContainerUpdateQuery;
                            }
                        } else {
                            $databaseUpdateFailure['InsertNewTagMetaData'] = $newTagInsertQuery;
                        }
                    }
                } else {
                    $dbQueryTagId = $existingId;
                }
            } // END createNewTag


            if ($importStatus != 14) {
                $taskEditingButtonHTML = '
                            <input type="button" class="clickableButton" id="returnToTaskSelection"
                                title="This will return you to the tag selection screen to choose
                                another tag based action." value="Choose Another Tag Action">';
            } else {
                $taskEditingButtonHTML = '';
            }


            if ($invalidRequiredField) {
                $editSubmittedFlag = false;

                $failedSubmissionHTML = '<p class="error">';
                $failedFields = '';
                foreach ($invalidRequiredField as $invalidFieldName) {
                    switch ($invalidFieldName) {
                        case 'newTagName':
                            $failedFields .= '<br>Tag Admin Name';
                            break;
                        case 'newTagDisplayText':
                            $failedFields .= '<br>Tag Display Text';
                            break;
                        case 'newTagRadioGroupName':
                            $failedFields .= '<br>Exclusivity Group Name';
                            break;
                        case 'newParentId':
                            $failedFields .= "<br>Parent Group";
                            break;
                    }
                }
                if (!empty($failedFields)) {
                    $failedSubmissionHTML .= 'The following required fields were either missing user input or contained invalid data:' . $failedFields;
                } else {
                    $failedSubmissionHTML .= 'Data was missing from the submission. Please try again.';
                }
                $failedSubmissionHTML .= '</p>';


                if (isset($newTagName)) {
                    $currentTagName = $newTagName;
                }
                if (isset($newTagDescription)) {
                    $currentTagDescription = $newTagDescription;
                }
                if (isset($newTagDisplayText)) {
                    $currentTagDisplayText = $newTagDisplayText;
                }
                if (isset($newTagToolTipText)) {
                    $currentTagToolTipText = $newTagToolTipText;
                }
                if (!isset($newNoTooltipImageStatus) && isset($newTagToolTipImage)) {
                    $currentTagToolTipImage = $newTagToolTipImage;
                }
                if (isset($newTagStatus)) {
                    $currentTagStatus = $newTagStatus;
                }
                if (isset($newCommentBoxFlag)) {
                    $currentTagIsComment = $newCommentBoxFlag;
                }
                if (isset($newRadioButtonFlag)) {
                    $currentTagIsRadio = $newRadioButtonFlag;
                }
                if (isset($newTagRadioGroupName)) {
                    $currentTagRadioGroupName = $newTagRadioGroupName;
                }
            } else if (!$dataChanges) {
                $actionSummaryHTML = <<<EOL
                                <h3>No Changes Detected</h3>
                                <p>No change in the tag detail data has been detected. The database has not been altered.</p>

EOL;
            } else if ($processingFailure) {
                $projectUpdateErrorHTML = <<<EOL
                                <h3>Update Failed</h3>
                                <p>A problem was encountered while processing an image file. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                              title="This will return you to the Question Builder menu
                                              for you to choose another item to create or edit."
                                                  value="Return To Question Builder Menu">
                                </div>

EOL;
            } else if ($databaseUpdateFailure) {
                $projectUpdateErrorHTML = <<<EOL
                                <h3>Update Failed</h3>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                              title="This will return you to the Question Builder menu
                                              for you to choose another item to create or edit."
                                                  value="Return To Question Builder Menu">
                                </div>

EOL;
            } else {
                $actionSummaryHTML = <<<EOL
                                <h3>Update Successful</h3>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
            }

            if (!$databaseUpdateFailure && !$invalidRequiredField) {
// CREATE HTML TO SHOW THE NEW TASK STATUS

                $summaryQuery = "SELECT * FROM tags WHERE tag_id = :tagId";
                $summaryParams['tagId'] = $dbQueryTagId;
                $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                $dbTagMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                $summaryParentQuery = "SELECT tgm.name, tgc.order_in_group "
                        . "FROM tag_group_contents tgc "
                        . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                        . "WHERE tgc.tag_id = :tagId AND tgm.contains_groups = 0";
                $summaryParentResults = run_prepared_query($DBH, $summaryParentQuery, $summaryParams);
                $dbParentMetadata = $summaryParentResults->fetch(PDO::FETCH_ASSOC);

                $dbTagName = htmlspecialchars($dbTagMetadata['name']);
                $dbTagDescription = restoreSafeHTMLTags(htmlspecialchars($dbTagMetadata['description']));
                $dbDisplayText = htmlspecialchars($dbTagMetadata['display_text']);
                $dbTagTooltipText = restoreSafeHTMLTags(htmlspecialchars($dbTagMetadata['tooltip_text']));
                $dbRadioButtonGroup = $dbTagMetadata['radio_button_group'];
                $dbOrdinalPositionInGroup = ordinal_suffix($dbParentMetadata['order_in_group']);
                $dbParentGroupName = $dbParentMetadata['name'];

                if (empty($dbTagMetadata['tooltip_image'])) {
                    $dbTagTooltipImage = 'None';
                } else {
                    $dbTagTooltipImage = $dbTagMetadata['tooltip_image'];
                }

                if ($dbTagMetadata ['is_enabled'] == 1) {
                    $dbTagStatusText = 'Enabled';
                } else {
                    $dbTagStatusText = '<span class="redHighlight">Disabled</span>';
                }

                $showRadioGroupName = FALSE;
                if ($dbTagMetadata ['is_comment_box'] == 0 && $dbTagMetadata ['is_radio_button'] == 0) {
                    $dbTagType = "Multi-Select";
                } else if ($dbTagMetadata ['is_radio_button'] == 1) {
                    $dbTagType = "Mutually Exclusive";
                    $showRadioGroupName = TRUE;
                } else {
                    $dbTagType = "Comment Box";
                }

                $actionSummaryHTML .= <<<EOL
                                <h3>Tag Summary</h3>
                                <table id="updateSummaryTable">
                                    <tbody>
                                        <tr>
                                            <td>Tag Admin Name:</td>
                                            <td class="userData">$dbTagName</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Admin Description:</td>
                                            <td class="userData">$dbTagDescription</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Display Text:</td>
                                            <td class="userData">$dbDisplayText</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Tooltip Text:</td>
                                            <td class="userData">$dbTagTooltipText</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Tooltip Image:</td>
                                            <td class="userData">$dbTagTooltipImage</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Type:</td>
                                            <td class="userData">$dbTagType</td>
                                        </tr>

EOL;
                if ($showRadioGroupName) {
                    $actionSummaryHTML .= <<<EOL
                                        <tr>
                                            <td>Exclusivity Group Name:</td>
                                            <td class = "userData">$dbRadioButtonGroup</td>
                                        </tr>

EOL;
                }
                $actionSummaryHTML .= <<<EOL
                                        <tr>
                                            <td>Parent Group Name:</td>
                                            <td class = "userData">$dbParentGroupName</td>
                                        </tr>
                                           <tr>
                                            <td>Order Position In Parent Group:</td>
                                            <td class = "userData">$dbOrdinalPositionInGroup</td>
                                        </tr>
                                        <tr>
                                            <td>Task Status:</td>
                                            <td class = "userData">$dbTagStatusText</td>
                                        </tr>
                                    </tbody>
                                </table>
EOL;
                if ($importStatus == 10) {
                    $actionSummaryHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionSummaryHTML .= <<<EOL
                                <div class="updateFormSubmissionControls"><hr>
                                    $taskEditingButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                              title="This will return you to the Question Builder menu
                                              for you to choose another item to create or edit."
                                                  value="Return To Question Builder Menu">
                                </div>

EOL;
            }

            break; // END tags CASE
    }
} // END DATABASE UPDATE CODE - if (isset($_POST['editSubmitted']))
//
//
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// DISPLAY CODE
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD UPDATE ACTION HTML
//
//
// FROM THIS POINT ON THE PROJECT ID AND NAME HAS BEEN COPIED FROM $_POST TO VARIABLES
// BEGIN SELECTION OF THE PROJECT PROPERTY TO UPDATE IF IT HAS NOT BEEN SPECIFIED.
if (!$projectPropertyToUpdate) {
    $completeTasksButtonHTML = '';
    $previewAndCompleteButtonHTML = '';
    $instructionHTML = <<<EOL
                <p>This tool allows you to build a series of questions that will be presented to the user for each
                    image they view.</p>
EOL;
    if ($importStatus == 10) {
        $previewAndCompleteButtonHTML = '
            <div class="updateFormSubmissionControls">
                <p>You have sufficient content to preview your question set.</p>
                <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                    title="This button allows you to preview your work so far using a simulated classification page.">
                    Preview The Question Set
                </button>
            </div>
            <div class="updateFormSubmissionControls">
                <h3>OR...Finish Question Building</h3>
                <p>Click the button below if you have finished building questions for this project and are ready
                    to move to the next step in the project creation process</p>
            <form id="tasksCompleteForm" method="post" autocomplete="off">
                <input type="hidden" name="tasksComplete" value="1"/>
                <button type="submit" id="continueButton"class="clickableButton enlargedClickableButton"
                    title="This button exits the Question Builder stage and moves to the next step in creating
                    your project.">
                    Continue Project Creation
                </button>
            </form>

            </div>
        ';
        $jQueryDocumentDotReadyCode .= <<<EOL
            $('#previewQuestionSetButton').click(function () {
                window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
            });
EOL;
    }
    $buildFormHTML = '';
    if ($importStatus == 11) {
        $instructionHTML .= <<<EOL
                <p>You can choose to build the questions from scratch or use the questions from another
                    existing project as a starting template.</p>
                <p>Before you begin building your questions in iCoast it is highly recommended that you prototype
                    your the task, group, tag structure on paper to avoid confusion in the iCoast interface.
                    If you have not built iCoast questions before and do not understand how the task, group, and
                    tags nest under each other to form a question structure then read the <a href="taskGuide.php"
                        target="_blank">Question Building Guide</a> first.</p>
EOL;
        $existingProjectQuery = '
            SELECT project_id, name, description
            FROM projects
            WHERE is_complete = 1
        ';
        $existingProjectResults = run_prepared_query($DBH, $existingProjectQuery);
        $cloneExistingProjectOptions = '<option value="0"></option>';
        $projectsToClone = false;
        while ($project = $existingProjectResults->fetch(PDO::FETCH_ASSOC)) {
            $projectsToClone = true;
            $cloneExistingProjectOptions .= "<option title=\"{$project['description']}\" value=\"{$project['project_id']}\">{$project['name']}</option>";
        }
        if ($projectsToClone) {
            $actionSelctionHTML .= <<<EOL
                <h3>Use An Existing Project As A Starting Template</h3>
                <p>If you would like to start by cloning the questions from an existing project then choose the
                    project from the list below.</p>
                <form id="cloneProjectForm" autocomplete="off" method="post">
                    <select id="cloneProjectSelect" class="clickableButton" name="cloneProjectId">
                        $cloneExistingProjectOptions
                    </select><br>
                    <button type="submit" id="cloneButton" class="clickableButton enlargedClickableButton">Clone Project Questions</button>
                </form>
                <h3>OR...
EOL;
        } else {
            $actionSelctionHTML .= <<<EOL
                <h3>Use An Existing Project As A Starting Template</h3>
                <p>There are no projects currently in iCoast that are capable of being cloned. Please create a
                    new set of tasks, groups and tags using the tools below.</p>
                 <h3>OR...
EOL;
        }
        $buildFormHTML .= '
            <input type="radio" name="projectPropertyToUpdate" value="tasks" id="editProjectTasks" />
            <label for="editProjectTasks" class="clickableButton editProjectAction">Tasks</label>
        ';
    } else if ($importStatus == 12 || $importStatus == 13) { // END if ($importStatus == 11)
        $buildFormHTML .= '
            <input type="radio" name="projectPropertyToUpdate" value="tasks" id="editProjectTasks" />
            <label for="editProjectTasks" class="clickableButton editProjectAction">Tasks</label>

            <input type="radio" name="projectPropertyToUpdate" value="groups" id="editProjectTagGroups" />
            <label for="editProjectTagGroups" class="clickableButton editProjectAction">Tag Groups</label>
        ';
        $actionSelctionHTML .= '<h3>';
    } else {
        $buildFormHTML .= '
                <input type="radio" name="projectPropertyToUpdate" value="tasks" id="editProjectTasks" />
                <label for="editProjectTasks" class="clickableButton editProjectAction">Tasks</label>

                <input type="radio" name="projectPropertyToUpdate" value="groups" id="editProjectTagGroups" />
                <label for="editProjectTagGroups" class="clickableButton editProjectAction">Tag Groups</label>

                <input type="radio" name="projectPropertyToUpdate" value="tags" id="editProjectTags" />
                <label for="editProjectTags" class="clickableButton editProjectAction">Tags</label>
        ';
        $actionSelctionHTML .= '<h3>';
    }


    $actionSelctionHTML .= <<<EOL
            Build Your Questions</h3>
            <p>Select the item you would like to create, edit or delete.</p>
            <form id="editActionForm" method="post" autocomplete="off">
                $buildFormHTML
                <br>
                <input type="hidden" name="projectId" value="$projectId" />
                <div class="updateFormSubmissionControls">
                    <input type="submit" id="buildItemButton" class="clickableButton" value="Select Item" />
                </div>
            </form>
                $previewAndCompleteButtonHTML

EOL;
} // END PROJECT PROPERTY SELECTION FORM CREATION - if (isset($projectId) && !isset($_POST['projectPropertyToUpdate']))
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD ACTION SPECIFIC UPDATE FORMS
//
//
// IF THE PROJECT AND PROPERTY TO UPDATE HAS BEEN SPECIFIED AND THERE IS NO SUPPLIED DATA TO UPDATE CREATE
// THE FORMS NECESSARY TO PROVIDE UPDATED DETAILS
if ($projectPropertyToUpdate &&
        !$editSubmittedFlag) {
// CUSTOMIZE THE UPDATE FORM TO THE PROPERTY THAT IS TO BE UPDATED
    switch ($projectPropertyToUpdate) {

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD THE TASKS UPDATE FORM HTML
//
//
// UPDATE A TASK BELONGING TO THE PROJECT
        case 'tasks':
// IF A TASK TO UPDATE HAS NOT BEEN SPECIFIED OR THE OPTION TO CREATE A NEW TASK WAS NOT SELECTED THEN
// BUILD A TASK SELECTION FORM AND GIVE THE OPTION TO CREATE A NEW TASK
            if (!$projectEditSubAction && $importStatus != 11) {
                $tasks = buildTaskSelectOptions($DBH, $projectId);
                $taskSelectOptionsHTML = $tasks[0];
                $actionControlsHTML = <<<EOL
                            <h3>Create or Edit Project Tasks</h3>
                            <div class="threeColumnOrSplit">


                                <div>
                                    <p>Click to create a new task</p>
                                    <form method="post" class="taskSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="tasks" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewTask" />
                                        <input type="submit" id ="createNewTaskButton" class="clickableButton" value="Create A New Task" />
                                    </form>
                                </div>
                                <div>
                                    <p>OR</p>
                                </div>
                                <div>
                                    <p>Please select a task to edit</p>
                                    <form method="post" class="taskSelectForm">
                                        <div id="formFieldRow">
                                            <label for="taskSelectBox">Task:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="taskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>

                                        <input type="hidden" name="projectPropertyToUpdate" value="tasks" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="updateExistingTask" />
                                        <input type="submit" id="editSelectedItemButton" class="clickableButton disabledClickableButton" value="Edit Selected Task" disabled />
                                    </form>
                                </div>

                            </div>
EOL;
                if ($importStatus == 10) {
                    $actionControlsHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionControlsHTML .= <<<EOL
                            <div class="updateFormSubmissionControls"><hr>
                                <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the task selection screen and return you to the Question Builder menu for you to choose another question item to create or edit. No changes will be made to the database." value="Return to Question Builder Menu">
                            </div>

EOL;
            } // END THE CREATION OF THE TASK SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
// CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
// FIND DETAILS OF ALL OTHER TASKS
                $invalidRequiredField = FALSE;
                if ($projectEditSubAction) {
                    
                } else if ($importStatus == 11) {
                    $projectEditSubAction = 'createNewTask';
                } else {
                    $invalidRequiredField[] = 'projectEditSubAction';
                }
                if (!$invalidRequiredField) {
                    $projectTaskQuery = "
                        SELECT *
                        FROM task_metadata
                        WHERE project_id = :projectId
                        ORDER BY order_in_project ASC";
                    $projectTaskParams['projectId'] = $projectId;
                    $projectTaskResults = run_prepared_query($DBH, $projectTaskQuery, $projectTaskParams);
                    $projectTasks = $projectTaskResults->fetchAll(PDO::FETCH_ASSOC);
                    $numberOfTasks = count($projectTasks);

                    $taskOrderTableContentHTML = '';
                    $newOrderInProjectSelectHTML = '';
                    $sequentialOrderInProjectNumber = 1; // USED TO CREATE A SEQUENTIAL TASK NUMBER IN CASE DATABSE HAS HOLES IN ORDER LIST
// IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                    if ($taskId &&
                            $projectEditSubAction == 'updateExistingTask' &&
                            $importStatus != 11) {

// COPY CURRENT TASK DETAILS TO VARIABLES AND BUILD TASK SPECIFIC HTML
// LOOP THROUGH THE TASKS
                        for ($i = 0; $i < $numberOfTasks; $i++) {
                            $ordinalOrderInProject = ordinal_suffix($sequentialOrderInProjectNumber);
// REPLACE THE TASK ORDER NUMBER WITH AN EQUIVELENT SEQUENTIAL NUMBER
                            $projectTasks[$i]['order_in_project'] = $sequentialOrderInProjectNumber;
// IF TASK IS THE TASK TO BE UPDATED COPY OFF THE DETAILS
                            if ($projectTasks [$i]['task_id'] == $taskId) {
                                $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\" selected>$ordinalOrderInProject</option>";
                                $taskOrderTableContentHTML .= '<tr id="currentProperty"';
                                if (!isset($currentTaskStatus)) {
                                    $currentTaskStatus = $projectTasks[$i]["is_enabled"];
                                }
                                if (!isset($currentTaskName)) {
                                    $currentTaskName = $projectTasks[$i]["name"];
                                }
                                $currentTaskName = htmlspecialchars($currentTaskName);
                                if (!isset($currentTaskDescription)) {
                                    $currentTaskDescription = $projectTasks[$i]["description"];
                                }
                                $currentTaskDescription = restoreSafeHTMLTags(htmlspecialchars($currentTaskDescription));
                                if (!isset($currentOrderInProject)) {
                                    $currentOrderInProject = $projectTasks[$i]["order_in_project"];
                                }
                                if (!isset($currentDisplayTitle)) {
                                    $currentDisplayTitle = $projectTasks[$i]["display_title"];
                                }
                                $currentDisplayTitle = htmlspecialchars($currentDisplayTitle);
                            } else if ($projectTasks [$i]["is_enabled"] == 0) {
                                $taskOrderTableContentHTML .= '<tr class="disabledProperty"';
                                $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                            } else {
                                $taskOrderTableContentHTML .= '<tr';
                                $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                            } // END if ($projectTasks[$i]['task_id'] == $taskId)
                            $taskOrderTableContentHTML .= " title=\"{$projectTasks[$i]["display_title"]}\"><td>$ordinalOrderInProject</td><td>{$projectTasks[$i]["name"]}</td></tr>";
                            $sequentialOrderInProjectNumber ++;
                        } //END for ($i = 0; $i < $numberOfTasks; $i++)

                        $advancedTaskOptionHTML = '';

                        if ($numberOfTasks >= 2) {

                            $advancedTaskOptionHTML = <<<EOL
                                    <div class="twoColumnSplit">
                                        <div>
                                        <h3>Current Display Order</h3>
                                        <p>Disabled (hidden) tasks are shown in red.<br>
                                            The current task being edited is shown in green.<br>
                                            All other tasks are uncolored.<br>
                                            Hovering over a row displays more details of the task.</p>
                                        <table id="propertyOrderTable">
                                            <thead>
                                                <tr>
                                                    <td>Position Number</td>
                                                    <td>Task Name</td>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                $taskOrderTableContentHTML
                                            </tbody>
                                        </table>
                                        </div>
                                        <div>
                                        </div>
                                        <div>
                                            <h3>New Display Order</h3>
                                            <p>Select the new position you would like the task to be shown in. All other tasks will be
                                                re-numbered to sequentially precede or follow this task in their current order.</p>
                                            <label for="editTaskOrderInProject">Select the new desired task position:</label>
                                            <select id="editTaskOrderInProject" class="clickableButton" name="newOrderInProject">
                                                $newOrderInProjectSelectHTML;
                                            </select>
                                        </div>
                                    </div>
EOL;
                        }





// CREATE TASK STATUS HTML AND ADDED THE NECESSARY CHECK HTML TO THE CORRECT OPTION
                        $taskStatusRadioButtonHTML = <<<EOL
                            <input type="radio" id="editTaskStatusEnabled" name="newTaskStatus" value="1">
                            <label for="editTaskStatusEnabled" class="clickableButton" title="The task and its contents will be available for public viewing.">Enabled</label>
                            <input type="radio" id="editTaskStatusDisabled" name="newTaskStatus" value="0">
                            <label for="editTaskStatusDisabled" class="clickableButton" title="The task and its contents will NOT be available for public viewing.">Disabled</label>
EOL;
                        if ($currentTaskStatus == 1) {
                            $taskStatusRadioButtonHTML = str_replace('1">', '1" checked>', $taskStatusRadioButtonHTML);
                        } else {
                            $taskStatusRadioButtonHTML = str_replace('0">', '0" checked>', $taskStatusRadioButtonHTML);
                        }

// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                        $actionControlsHTML = <<<EOL
                            <h3>Edit Task Details</h3>
                            $failedSubmissionHTML
                            <form method="post" id="editTaskDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTaskName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Task Admin Name * :</label>
                                    <input type="textbox" id="editTaskName" class="clickableButton" name="newTaskName" maxlength="50" value="$currentTaskName" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDescription" title="This text is for admin reference only to help explain details of the task. The content of this field is not shared with standard users. 500 character limit.">Task Admin Description:</label>
                                    <textarea id="editTaskDescription" class="clickableButton" name="newTaskDescription" maxlength="500">$currentTaskDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDisplayTitle" title="This text is used as task title on the classification page. Text identifying the task position and a short description of the task questions is best here. There is a 100 character limit. Always check the text displays correctly on the classification page after editing this field.">Task Display Text * :</label>
                                    <textarea id="editTaskDisplayTitle" class="clickableButton" name="newDisplayTitle" maxlength="100">$currentDisplayTitle</textarea>
                                </div>
                                $advancedTaskOptionHTML
                                 <div class="formFieldRow">
                                    <label title="Disabling a task removes it, and all of the groups and tags it contains, from public view. By doing this it is removed from the task flow in the classification page but can easily be re-added again in the future br re-enabling the task here.">Task Status:</label>
                                    $taskStatusRadioButtonHTML
                                </div>

                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="updateExistingTask" />
                                <input type="hidden" name="taskId" value="$taskId" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection"
                                        title="This will exit the task editing screen without submitting changes
                                        to the database and return you to the task selection screen to choose
                                        another task based action." value="Cancel and Choose Another Task Action">
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will exit the task editing screen without submitting changes
                                        to the database and return you to the Question Builder menu screen
                                        for you to choose another item to create or edit."
                                        value="Cancel Changes and Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                    } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))
// IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                    else if ($projectEditSubAction == 'createNewTask' || $importStatus != 11) {

                        $advancedTaskOptionHTML = '';
                        $editDifferentTaskButtonHTML = '';

                        if (!isset($currentTaskName)) {
                            $currentTaskName = '';
                        }
                        if (!isset($currentTaskDescription)) {
                            $currentTaskDescription = '';
                        }
                        if (!isset($currentDisplayTitle)) {
                            $currentDisplayTitle = '';
                        }

                        if ($numberOfTasks >= 1) {
                            $editDifferentTaskButtonHTML = '
                                <input type="button" class="clickableButton" id="returnToTaskSelection"
                                    title="This will exit the task creation screen without submitting changes
                                    to the database and return you to the task selection screen to choose
                                    another task based action." value="Cancel and Choose Another Task Action">
                            ';

// LOOP THROUGH THE EXISTING TASKS RESEQUENCING THE ORDER NUMBERS AND BUILDING TASK SPECIFIC HTML
                            for ($i = 0; $i < $numberOfTasks; $i++) {

                                $ordinalOrderInProject = ordinal_suffix($sequentialOrderInProjectNumber);
// REPLACE THE TASK ORDER NUMBER WITH AN EQUIVELENT SEQUENTIAL NUMBER
                                $projectTasks[$i]['order_in_project'] = $sequentialOrderInProjectNumber;
// IF TASK IS THE TASK TO BE UPDATED COPY OFF THE DETAILS
                                if ($projectTasks [$i]["is_enabled"] == 0) {
                                    $taskOrderTableContentHTML .= '<tr class="disabledProperty"';
                                    $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                                } else {
                                    $taskOrderTableContentHTML .= '<tr';
                                    $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                                } // END if ($projectTasks[$i]['task_id'] == $taskId)
                                $taskOrderTableContentHTML .= " title=\"{$projectTasks[$i]["display_title"]}\"><td>$ordinalOrderInProject</td><td>{$projectTasks[$i]["name"]}</td></tr>";
                                $sequentialOrderInProjectNumber ++;
                            } //END for ($i = 0; $i < $numberOfTasks; $i++)

                            $ordinalOrderInProject = ordinal_suffix($sequentialOrderInProjectNumber);
                            $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\" selected>$ordinalOrderInProject</option>";


                            $advancedTaskOptionHTML = <<<EOL
                                <div class="twoColumnSplit">
                                    <div>
                                    <h3>Current Display Order</h3>
                                    <p>Disabled (hidden) tasks are shown in red.<br>
                                        Hovering over a row displays more details of the task.</p>
                                    <table id="propertyOrderTable">
                                        <thead>
                                            <tr>
                                                <td>Display Order</td>
                                                <td>Task Name</td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            $taskOrderTableContentHTML
                                        </tbody>
                                    </table>
                                    </div>
                                    <div>
                                    </div>
                                    <div>
                                        <h3>New Display Order</h3>
                                        <p>Select the position you would like the new task to be shown in. All other tasks will be
                                            re-numbered to sequentially precede or follow this task in their current order.</p>
                                        <label for="editTaskOrderInProject">Select the new desired task position:</label>
                                        <select id="editTaskOrderInProject" class="clickableButton" name="newOrderInProject">
                                            $newOrderInProjectSelectHTML;
                                        </select>
                                    </div>
                                </div>
EOL;
                        } else {
                            $advancedTaskOptionHTML = '
                                <input type="hidden" name="newOrderInProject" value="1" />

                            ';
                        }


// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                        $actionControlsHTML = <<<EOL
                            <h3>Create New Task</h3>
                                $failedSubmissionHTML
                            <form method="post" id="editTaskDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTaskName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Task Admin Name * :</label>
                                    <input type="textbox" id="editTaskName" class="clickableButton" name="newTaskName" value="$currentTaskName" maxlength="50" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDescription" title="This text is for admin reference only to help explain details of the task. The content of this field is not shared with standard users. 500 character limit.">Task Admin Description:</label>
                                    <textarea id="editTaskDescription" class="clickableButton" name="newTaskDescription" maxlength="500">$currentTaskDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDisplayTitle" title="This text is used as task title on the classification page. Text identifying the task position and a short description of the task questions is best here. There is a 100 character limit. Always check the text displays correctly on the classification page after editing this field.">Task Display Text * :</label>
                                    <textarea id="editTaskDisplayTitle" class="clickableButton" name="newDisplayTitle" maxlength="100">$currentDisplayTitle</textarea>
                                </div>
                                $advancedTaskOptionHTML
                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewTask" />
                                <input type="hidden" name="newTaskStatus" value="1" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Create Task">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    $editDifferentTaskButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will exit the task creation screen without submitting
                                        changes to the database and return you to the Question Builder menu
                                        for you to choose another item to create or edit."
                                        value="Cancel Changes and Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                    }
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h3>Errors Detected in Input Data</h3>
                          <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;
                }
            }


            break;

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD THE GROUPS UPDATE FORM HTML
//
//
        case 'groups':
// IF A GROUP TO UPDATE HAS NOT BEEN SPECIFIED OR THE OPTION TO CREATE A NEW GROUP WAS NOT SELECTED THEN
// BUILD A GROUP SELECTION FORM AND GIVE THE OPTION TO CREATE A NEW TASK
            if (!$projectEditSubAction && $importStatus != 12) {
// FIND DATA FOR ALL GROUPS IN THE PROJECT
                $projectGroupQuery = "SELECT tag_group_id, name, description, is_enabled FROM tag_group_metadata WHERE project_id = :projectId ORDER BY name";
                $projectGroupParams['projectId'] = $projectId;
                $projectGroupResults = run_prepared_query($DBH, $projectGroupQuery, $projectGroupParams);
                $projectGroups = $projectGroupResults->fetchAll(PDO::FETCH_ASSOC);

// BUILD THE GROUP SELECTION HTML FORM
                $groupSelectOptionsHTML = '';
                foreach ($projectGroups as $individualGroup) {
                    $individualGroupId = $individualGroup['tag_group_id'];
                    $individualGroupName = htmlspecialchars($individualGroup['name']);
                    $individualGroupDescription = restoreSafeHTMLTags(htmlspecialchars($individualGroup['description']));
                    $isEnabled = $individualGroup['is_enabled'];
                    if ($isEnabled == 1) {
                        $groupSelectOptionsHTML .= "<option title=\"$individualGroupDescription\" value=\"$individualGroupId\">$individualGroupName</option>";
                    } else {
                        $groupSelectOptionsHTML .= "<option title=\"$individualGroupDescription\" value=\"$individualGroupId\">$individualGroupName (Disabled)</option>";
                    }
                }
                $actionControlsHTML = <<<EOL
                            <h3>Create or Edit Project Groups</h3>
                            <div class="threeColumnOrSplit">
                                <div>
                                    <p>Click to create a new group</p>
                                    <form method="post" class="groupSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="groups" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewGroup" />
                                        <input type="submit" id ="createNewGroupButton" class="clickableButton" value="Create A New Group" />
                                    </form>
                                </div>

                                <div>
                                    <p>OR</p>
                                </div>

                                <div>
                                    <p>Please select a group to edit</p>
                                    <form method="post" class="groupSelectForm">
                                        <div id="formFieldRow">
                                            <label for="groupSelectBox">Group:</label>
                                            <select id="groupSelectBox" class="clickableButton" name="groupId">
                                                $groupSelectOptionsHTML
                                            </select>
                                        </div>

                                        <input type="hidden" name="projectPropertyToUpdate" value="groups" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="updateExistingGroup" />
                                        <input type="submit" id="editSelectedItemButton" class="clickableButton disabledClickableButton" value="Edit Selected Group" disabled />
                                    </form>
                                </div>

                            </div>
EOL;
                if ($importStatus == 10) {
                    $actionControlsHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionControlsHTML .= <<<EOL
                            <div class="updateFormSubmissionControls"><hr>
                                <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the tag group selection screen and return you to the Question Builder menu for you to choose another question item to create or edit. No changes will be made to the database." value="Return to Question Builder Menu">
                            </div>

EOL;
            } // END THE CREATION OF THE GROUP SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
// CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
                $invalidRequiredField = FALSE;
                if ($projectEditSubAction) {
                    
                } else if ($importStatus == 12) {
                    $projectEditSubAction = 'createNewGroup';
                } else {
                    $invalidRequiredField[] = 'projectEditSubAction';
                }
// IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                if ($groupId && isset($projectEditSubAction) &&
                        $projectEditSubAction == 'updateExistingGroup') {

                    $groupMetadata = retrieve_entity_metadata($DBH, $groupId, 'group');
                    if (!isset($currentGroupName)) {
                        $currentGroupName = $groupMetadata['name'];
                    }
                    $currentGroupName = htmlspecialchars($currentGroupName);
                    if (!isset($currentGroupDescription)) {
                        $currentGroupDescription = $groupMetadata['description'];
                    }
                    $currentGroupDescription = restoreSafeHTMLTags(htmlspecialchars($currentGroupDescription));
                    if (!isset($currentGroupDisplayText)) {
                        $currentGroupDisplayText = $groupMetadata['display_text'];
                    }
                    $currentGroupDisplayText = htmlspecialchars($currentGroupDisplayText);
                    if (!isset($currentGroupWidth)) {
                        $currentGroupWidth = $groupMetadata['force_width'];
                    }
                    if (!isset($currentGroupBorder)) {
                        $currentGroupBorder = $groupMetadata['has_border'];
                    }
                    if (!isset($currentGroupColor)) {
                        $currentGroupColor = $groupMetadata['has_color'];
                    }
                    if (!isset($currentGroupStatus)) {
                        $currentGroupStatus = $groupMetadata['is_enabled'];
                    }
                    if (!isset($currentGroupContainsGroups)) {
                        $currentGroupContainsGroups = $groupMetadata['contains_groups'];
                    }
                    if ($currentGroupWidth == 0) {
                        $currentGroupWidth = '';
                    }


                    $groupContentsRadioHTML = <<<EOL
                        <input type="radio" id="editGroupContainsTags" name="newGroupContainsGroupsStatus" value="0">
                        <label for="editGroupContainsTags" class="clickableButton" title="Sets this group to contain tags. If this option is unavailable then the group is currently set to contain groups and it has groups assigned to it. You must remove these child groups from this group first before you can set it to contain tags.">Tags</label>
                        <input type="radio" id="editGroupContainsGroups" name="newGroupContainsGroupsStatus" value="1">
                        <label for="editGroupContainsGroups" class="clickableButton" title="Sets this group to contain other groups. If this option is unavailable then the group is currently set to contain tags and it has tags assigned to it. You must remove these child tags from this group first before you can set it to contain groups.">Groups</label>

EOL;
                    $groupHasContents = groupContentsCheck($DBH, $groupId);
                    if ($currentGroupContainsGroups == 1) {
                        $groupContentsRadioHTML = str_replace('1">', '1" checked>', $groupContentsRadioHTML);
                        if ($groupHasContents) {
                            $groupContentsRadioHTML = str_replace('for="editGroupContainsTags" class="clickableButton"', 'for="editGroupContainsTags" class="clickableButton disabledClickableButton"', $groupContentsRadioHTML);
                            $groupContentsRadioHTML = str_replace('0">', '0" disabled>', $groupContentsRadioHTML);
                        }
                    } else {
                        $groupContentsRadioHTML = str_replace('0">', '0" checked>', $groupContentsRadioHTML);
                        if ($groupHasContents) {
                            $groupContentsRadioHTML = str_replace('for="editGroupContainsGroups" class="clickableButton"', 'for="editGroupContainsGroups" class="clickableButton disabledClickableButton"', $groupContentsRadioHTML);
                            $groupContentsRadioHTML = str_replace('1">', '1" disabled>', $groupContentsRadioHTML);
                        }
                    }


                    $groupDisplayBorderRadioHTML = <<<EOL
                        <input type="radio" id="editGroupHasBorderNo" name="newGroupBorderStatus" value="0">
                        <label for="editGroupHasBorderNo" class="clickableButton" title="Turns the group border off.">Off</label>
                        <input type="radio" id="editGroupHasBorderYes" name="newGroupBorderStatus" value="1">
                        <label for="editGroupHasBorderYes" class="clickableButton" title="Turns the group border on.">On</label>

EOL;
                    if ($currentGroupBorder == 1) {
                        $groupDisplayBorderRadioHTML = str_replace('1">', '1" checked>', $groupDisplayBorderRadioHTML);
                    } else {
                        $groupDisplayBorderRadioHTML = str_replace('0">', '0" checked>', $groupDisplayBorderRadioHTML);
                    }




                    $groupBackgroundColorRadioHTML = <<<EOL
                        <input type="radio" id="editGroupHasColorNo" name="newGroupColorStatus" value="0">
                        <label for="editGroupHasColorNo" class="clickableButton" title="Turns the group border off.">Off</label>
                        <input type="radio" id="editGroupHasColorYes" name="newGroupColorStatus" value="1">
                        <label for="editGroupHasColorYes" class="clickableButton" title="Turns the group border on.">On</label>
EOL;
                    $groupBackgroudColorPickerValue = '';
                    if (!empty($currentGroupColor)) {
                        $groupBackgroundColorRadioHTML = str_replace('1">', '1" checked>', $groupBackgroundColorRadioHTML);
                        $groupBackgroudColorPickerValue = $currentGroupColor;
                    } else {
                        $groupBackgroundColorRadioHTML = str_replace('0">', '0" checked>', $groupBackgroundColorRadioHTML);
                    }




                    $groupStatusRadioButtonHTML = <<<EOL
                            <input type="radio" id="editGroupStatusEnabled" name="newGroupStatus" value="1">
                            <label for="editGroupStatusEnabled" class="clickableButton" title="The group and its contents will be available for public viewing.">Enabled</label>
                            <input type="radio" id="editGroupStatusDisabled" name="newGroupStatus" value="0">
                            <label for="editGroupStatusDisabled" class="clickableButton" title="The group and its contents will NOT be available for public viewing.">Disabled</label>

EOL;
                    if ($currentGroupStatus == 1) {
                        $groupStatusRadioButtonHTML = str_replace('1">', '1" checked>', $groupStatusRadioButtonHTML);
                    } else {
                        $groupStatusRadioButtonHTML = str_replace('0">', '0" checked>', $groupStatusRadioButtonHTML);
                    }


                    $findParentTaskQuery = "SELECT task_id FROM task_contents WHERE tag_group_id = :groupId";
                    $findParentTaskParams['groupId'] = $groupId;
                    $findParentTaskResult = run_prepared_query($DBH, $findParentTaskQuery, $findParentTaskParams);
                    $parentOfSelectedGroup = $findParentTaskResult->fetchColumn();
                    if (!$parentOfSelectedGroup) {
                        $findParentGroupQuery = "SELECT tgc.tag_group_id "
                                . "FROM tag_group_contents tgc "
                                . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                                . "WHERE tgc.tag_id = :groupId AND tgm.contains_groups = 1";
                        $findParentGroupParams['groupId'] = $groupId;
                        $findParentGroupResult = run_prepared_query($DBH, $findParentGroupQuery, $findParentGroupParams);
                        $parentOfSelectedGroup = $findParentGroupResult->fetchColumn();
                        $parentOfSelectedGroupIsTask = FALSE;
                        $parentType = 'group';
                    } else {
                        $parentOfSelectedGroupIsTask = TRUE;
                        $parentType = 'task';
                    }

                    if ($parentOfSelectedGroupIsTask) {
                        $tasks = buildTaskSelectOptions($DBH, $projectId, $parentOfSelectedGroup);
                    } else {
                        $tasks = buildTaskSelectOptions($DBH, $projectId);
                    }
                    $taskSelectOptionsHTML = $tasks[0];
                    $taskIdList = $tasks[1];
                    $whereInTasks = where_in_string_builder($taskIdList);

                    if (!$parentOfSelectedGroupIsTask) {
                        $groups = buildGroupSelectOptions($DBH, $projectId, $parentOfSelectedGroup, true);
                    } else {
                        $groups = buildGroupSelectOptions($DBH, $projectId, false, true);
                    }
                    $groupSelectOptionsHTML = $groups[0];
                    $groupIdList = $groups[1];
                    $groupNameList = $groups[2];
                    $whereInGroups = where_in_string_builder($groupIdList);

                    if (empty($groupSelectOptionsHTML)) {
                        $groupContainedInHTML = <<<EOL
                                        <div id="formFieldRow">
                                            <label for="taskSelectBox" title="Use this select box to choose the task that should contain this group.">Parent Task:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>

EOL;
                        $groupContainer = 'Task';
                    } else {
                        $groupContainedInHTML = <<<EOL
                                <div class="threeColumnOrSplit">
                                    <div>
                                        <p>Select a task to contain this group * :</p>
                                        <div id="formFieldRow">
                                            <label for="taskSelectBox">Task Name:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <p>OR</p>
                                    </div>
                                    <div>
                                        <p>Select another group to contain this group * :</p>
                                        <div id="formFieldRow">
                                            <label for="groupSelectBox">Group Name:</label>
                                            <select id="groupSelectBox" class="clickableButton" name="newParentGroupId">
                                                $groupSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                </div>
EOL;
                    }





                    $taskGroupOrderQuery = "SELECT tgm.name, tgm.is_enabled, tgm.display_text, tc.task_id AS parent_id, tc.tag_group_id AS child_id, tc.order_in_task "
                            . "FROM task_contents tc "
                            . "LEFT JOIN tag_group_metadata tgm ON tc.tag_group_id = tgm.tag_group_id "
                            . "WHERE tc.task_id IN ($whereInTasks) "
                            . "ORDER BY parent_id, tc.order_in_task";
                    $taskGroupOrderParams['projectId'] = $projectId;
                    $taskGroupOrderResults = run_prepared_query($DBH, $taskGroupOrderQuery, $taskGroupOrderParams);
                    $taskGroups = $taskGroupOrderResults->fetchAll(PDO::FETCH_ASSOC);
                    $groupOrderInParentTask = array();
                    foreach ($taskGroups as $taskGroup) {
                        $groupOrderInParentTask[$taskGroup['parent_id']][] = $taskGroup;
                    }
                    $javascriptGroupOrderInParentTask = array();
                    foreach ($groupOrderInParentTask as &$parentContainer) {
                        $containerContainsGroup = FALSE;
                        $parentId = '';
                        $sequentialOrderInTask = '';
                        $javascriptGroupOrderInParentTask[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                        $javascriptGroupOrderInParentTask[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';
                        for ($i = 0; $i < count($parentContainer); $i++) {
                            $sequentialOrderInTask = $i + 1;
                            $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                            $parentId = $parentContainer[$i]['parent_id'];
                            $childId = $parentContainer[$i]['child_id'];
                            $isEnabled = $parentContainer[$i]['is_enabled'];


                            $parentContainer[$i]['order_in_task'] = $sequentialOrderInTask;

                            $orderInTask = $parentContainer[$i]['order_in_task'];

                            if ($childId == $groupId) {
                                $oldGroupParentId = $parentId;
                                $oldGroupOrderNumber = $orderInTask;
                                $containerContainsGroup = TRUE;
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . '<tr id="currentProperty"';
                                $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                            } else if ($isEnabled == 0) {
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                                $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                            } else {
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . '<tr';
                                $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                            }
                            $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInTask</td><td>{$parentContainer[$i]["name"]
                                    }</td></tr>\n\r";
                        }
                        if (!$containerContainsGroup) {
                            $sequentialOrderInTask++;
                            $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                            $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                        }
                    }
                    $javascriptGroupOrderInParentTask = json_encode($javascriptGroupOrderInParentTask);





                    if (count($groupIdList) > 0) {
                        $nestedGroupOrderQuery = "SELECT tgm.name, tgm.is_enabled, tgm.display_text, tgc.tag_group_id AS parent_id, tgc.tag_id AS child_id, tgc.order_in_group "
                                . "FROM tag_group_contents tgc "
                                . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_id = tgm.tag_group_id "
                                . "WHERE tgc.tag_group_id IN ($whereInGroups)"
                                . "ORDER BY parent_id, tgc.order_in_group";
                        $nestedGroupOrderParams['projectId'] = $projectId;
                        $nestedGroupOrderResults = run_prepared_query($DBH, $nestedGroupOrderQuery, $nestedGroupOrderParams);
                        $nestedGroups = $nestedGroupOrderResults->fetchAll(PDO::FETCH_ASSOC);
                        $groupOrderInParentGroup = array();
                        foreach ($nestedGroups as $nestedGroup) {
                            $groupOrderInParentGroup[$nestedGroup['parent_id']][] = $nestedGroup;
                        }
                        $javascriptGroupOrderInParentGroup = array();
                        foreach ($groupOrderInParentGroup as &$parentContainer) {
                            $containerContainsGroup = FALSE;
                            $parentId = '';
                            $sequentialOrderInGroup = '';
                            $javascriptGroupOrderInParentGroup[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                            $javascriptGroupOrderInParentGroup[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';
                            for ($i = 0; $i < count($parentContainer); $i++) {
                                $sequentialOrderInGroup = $i + 1;
                                $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                                $parentId = $parentContainer[$i]['parent_id'];
                                $childId = $parentContainer[$i]['child_id'];
                                $isEnabled = $parentContainer[$i]['is_enabled'];

                                $parentContainer[$i]['order_in_group'] = $sequentialOrderInGroup;

                                $orderInParent = $parentContainer[$i]['order_in_group'];

                                if ($childId == $groupId) {
                                    $oldGroupParentId = $parentId;
                                    $oldGroupOrderNumber = $orderInParent;
                                    $containerContainsGroup = TRUE;
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . '<tr id="currentProperty" ';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                                } else if ($isEnabled == 0) {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . '<tr class="disabledProperty" ';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                } else {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . '<tr';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                }
                                $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]
                                        }</td></tr>\n\r";
                            }
                            if (!$containerContainsGroup) {
                                $sequentialOrderInGroup++;
                                $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                                $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                            }
                        }

                        foreach ($groupIdList as $singleGroupId) {
                            if (!array_key_exists($singleGroupId, $javascriptGroupOrderInParentGroup)) {
                                $javascriptGroupOrderInParentGroup[$singleGroupId]['tableHTML'] = '';
                                $javascriptGroupOrderInParentGroup[$singleGroupId]['newOrderSelectHTML'] = "<option value=\"1\" selected>1st</option>\n\r";
                            }
                        }
                        $javascriptGroupOrderInParentGroup = json_encode($javascriptGroupOrderInParentGroup);
                    }






// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h3>Edit Tag Group Details</h3>
                            $failedSubmissionHTML
                            <form method="post" id="editGroupDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editGroupName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Group Admin Name * :</label>
                                    <input type="textbox" id="editGroupName" class="clickableButton" name="newGroupName" maxlength="50" value="$currentGroupName" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDescription" title="This text is for admin reference only to help explain details of the group. The content of this field is not shared with standard users. 500 character limit.">Group Admin Description:</label>
                                    <textarea id="editGroupDescription" class="clickableButton" name="newGroupDescription" maxlength="500">$currentGroupDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDisplayText" title="This text is used as the group title on the classification page. Text identifying whow the tags or groups this group contains are related is best here. There is a 500 character limit. Always check the text displays correctly on the classification page after editing this field.">Group Display Text * :</label>
                                    <textarea id="editGroupDisplayText" class="clickableButton" name="newDisplayText" maxlength="100">$currentGroupDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the type of objects this group can contain. The options are either Tags or other Groups. The current croup must not have any existing contents if you wish to change this option.">Group Contains:</label>
                                    $groupContentsRadioHTML
                                </div>
                                <div class="formFieldRow">
                                    <label for="editGroupWidth" title="If specified the number in this field is the width in pixels that the group will be forced to occupy on the page. Display issues may occur if this setting to too large or small. Always check the group displays correctly on the classification page after editing this field. A setting of 0 menas the group width will be calculated automatically.">Group Width (in pixels):</label>
                                    <input type="textbox" id="editGroupWidth" class="clickableButton" name="newGroupWidth" maxlength="4" value="$currentGroupWidth" />
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons turn the display of a border around the group on and off. Using a border can be beneficial if you have groups within groups and seperation would make them clearer.">Display Group Border:</label>
                                    $groupDisplayBorderRadioHTML
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the background color of a group. Using a background color can help distinguish a group or provide a way of showing increased importance.">Group Background Color:</label>
                                    $groupBackgroundColorRadioHTML
                                    <input id="groupColorPicker" class="color clickableButton disabledClickableButton" title="Manually enter the desired background color in six digit hexadecimal or use the color picker" name="newGroupColor" value="$groupBackgroudColorPickerValue" disabled/>
                                </div>
                                $groupContainedInHTML
                                <div class="twoColumnSplit">
                                    <div>
                                    <h3>Current Display Order In Task/Group </h3>
                                    <p>Disabled (hidden) groups are shown in red.<br>
                                        The current group being edited is shown in green.<br>
                                        All other groups are uncolored.<br>
                                        Hovering over a row displays more details of the group.</p>
                                    <table id="propertyOrderTable">
                                        <thead>
                                            <tr>
                                                <td>Position Number</td>
                                                <td>Group Name</td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div>
                                    </div>
                                    <div>
                                        <h3>New Display Order</h3>
                                        <p>Select the new position you would like the group to be shown in. All other groups will be
                                            re-numbered to sequentially precede or follow this group in their current order.</p>
                                        <label for="editGroupOrder">Select the new desired group position:</label>
                                        <select id="editGroupOrder" class="clickableButton" name="newGroupOrder">
                                        </select>
                                    </div>
                                </div>
                                 <div class="formFieldRow">
                                    <label title="Disabling a group removes it, and all of the tags it contains from public view. It can easily be re-added in the future br re-enabling the group here.">Group Status:</label>
                                    $groupStatusRadioButtonHTML
                                </div>

                                <input type="hidden" name="oldGroupParentType" value="$parentType" />
                                <input type="hidden" name="oldGroupParentId" value="$oldGroupParentId" />
                                <input type="hidden" name="oldGroupOrderInParent" value="$oldGroupOrderNumber" />
                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="updateExistingGroup" />
                                <input type="hidden" name="groupId" value="$groupId" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection"
                                        title="This will exit the group editing screen without submitting
                                        changes to the database and return you to the group selection screen
                                        for you to choose another group based action."
                                        value="Cancel Changes and Choose Another Group Action">
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will exit the group editing screen without submitting
                                        changes to the database and return you to the Question Builder menu
                                        for you to choose another item to create or edit."
                                        value="Cancel Changes and Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))
// IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                else if (isset($projectEditSubAction) && $projectEditSubAction == 'createNewGroup') {

                    $tasks = buildTaskSelectOptions($DBH, $projectId);
                    $taskSelectOptionsHTML = $tasks[0];
                    $taskIdList = $tasks[1];
                    $whereInTasks = where_in_string_builder($taskIdList);

                    if ($importStatus != 12) {
                        $editDifferentGroupButtonHTML = '
                             <input type="button" class="clickableButton" id="returnToTaskSelection"
                             title="This will exit the group creation screen without submitting changes to the
                             database and return you to the group selection screen for you to choose another
                             group option." value="Cancel and Choose Another Group Action">
                        ';
                    }


                    if (!isset($currentGroupName)) {
                        $currentGroupName = '';
                    }
                    if (!isset($currentGroupDescription)) {
                        $currentGroupDescription = '';
                    }
                    if (!isset($currentGroupDisplayText)) {
                        $currentGroupDisplayText = '';
                    }
                    if (!isset($currentGroupWidth)) {
                        $currentGroupWidth = '';
                    }
                    if (!isset($currentGroupBorder)) {
                        $currentGroupBorder = '';
                    }
                    if (!isset($currentGroupColor)) {
                        $currentGroupColor = '';
                    }
                    if (!isset($currentGroupStatus)) {
                        $currentGroupStatus = '';
                    }
                    if (!isset($currentGroupContainsGroups)) {
                        $currentGroupContainsGroups = '';
                    }
                    if ($currentGroupWidth == 0) {
                        $currentGroupWidth = '';
                    }


                    $groupContentsRadioHTML = <<<EOL
                        <input type="radio" id="editGroupContainsTags" name="newGroupContainsGroupsStatus" value="0">
                        <label for="editGroupContainsTags" class="clickableButton" title="Sets this group to contain tags. If this option is unavailable then the group is currently set to contain groups and it has groups assigned to it. You must remove these child groups from this group first before you can set it to contain tags.">Tags</label>
                        <input type="radio" id="editGroupContainsGroups" name="newGroupContainsGroupsStatus" value="1">
                        <label for="editGroupContainsGroups" class="clickableButton" title="Sets this group to contain other groups. If this option is unavailable then the group is currently set to contain tags and it has tags assigned to it. You must remove these child tags from this group first before you can set it to contain groups.">Groups</label>

EOL;

                    if ($currentGroupContainsGroups == 1) {
                        $groupContentsRadioHTML = str_replace('1">', '1" checked>', $groupContentsRadioHTML);
                    } else {
                        $groupContentsRadioHTML = str_replace('0">', '0" checked>', $groupContentsRadioHTML);
                    }


                    $groupDisplayBorderRadioHTML = <<<EOL
                        <input type="radio" id="editGroupHasBorderNo" name="newGroupBorderStatus" value="0">
                        <label for="editGroupHasBorderNo" class="clickableButton" title="Turns the group border off.">Off</label>
                        <input type="radio" id="editGroupHasBorderYes" name="newGroupBorderStatus" value="1">
                        <label for="editGroupHasBorderYes" class="clickableButton" title="Turns the group border on.">On</label>

EOL;
                    if ($currentGroupBorder == 1) {
                        $groupDisplayBorderRadioHTML = str_replace('1">', '1" checked>', $groupDisplayBorderRadioHTML);
                    } else {
                        $groupDisplayBorderRadioHTML = str_replace('0">', '0" checked>', $groupDisplayBorderRadioHTML);
                    }

                    $groupBackgroundColorRadioHTML = <<<EOL
                        <input type="radio" id="editGroupHasColorNo" name="newGroupColorStatus" value="0">
                        <label for="editGroupHasColorNo" class="clickableButton" title="Turns the group border off.">Off</label>
                        <input type="radio" id="editGroupHasColorYes" name="newGroupColorStatus" value="1">
                        <label for="editGroupHasColorYes" class="clickableButton" title="Turns the group border on.">On</label>
EOL;
                    $groupBackgroudColorPickerValue = 'FFFFF';
                    if (!empty($currentGroupColor)) {
                        $groupBackgroundColorRadioHTML = str_replace('1">', '1" checked>', $groupBackgroundColorRadioHTML);
                        $groupBackgroudColorPickerValue = $currentGroupColor;
                    } else {
                        $groupBackgroundColorRadioHTML = str_replace('0">', '0" checked>', $groupBackgroundColorRadioHTML);
                    }


                    $groups = buildGroupSelectOptions($DBH, $projectId, false, true);
                    $groupSelectOptionsHTML = $groups[0];
                    $groupIdList = $groups[1];
                    $groupNameList = $groups[2];
                    $whereInGroups = where_in_string_builder($groupIdList);

                    if (empty($groupSelectOptionsHTML)) {
                        $groupContainedInHTML = <<<EOL
                                        <div id="formFieldRow">
                                            <label for="taskSelectBox" title="Use this select box to choose the task that should contain this group.">Parent Task:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>

EOL;
                        $groupContainer = 'Task';
                    } else {
                        $groupContainedInHTML = <<<EOL
                                <div class="threeColumnOrSplit">
                                    <div>
                                        <p>Select a task to contain this group * :</p>
                                        <div id="formFieldRow">
                                            <label for="taskSelectBox">Task Name:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <p>OR</p>
                                    </div>
                                    <div>
                                        <p>Select another group to contain this group * :</p>
                                        <div id="formFieldRow">
                                            <label for="groupSelectBox">Group Name:</label>
                                            <select id="groupSelectBox" class="clickableButton" name="newParentGroupId">
                                                $groupSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                </div>
EOL;
                    }





                    $taskGroupOrderQuery = "
                        SELECT tgm.name, tgm.is_enabled, tgm.display_text, tc.task_id AS parent_id, tc.order_in_task
                        FROM task_contents tc
                        LEFT JOIN tag_group_metadata tgm ON tc.tag_group_id = tgm.tag_group_id
                        WHERE tc.task_id IN ($whereInTasks)
                        ORDER BY parent_id, tc.order_in_task";
                    $taskGroupOrderResults = run_prepared_query($DBH, $taskGroupOrderQuery);
                    $taskGroups = $taskGroupOrderResults->fetchAll(PDO::FETCH_ASSOC);
                    $groupOrderInParentTask = array();
                    foreach ($taskGroups as $taskGroup) {
                        $groupOrderInParentTask[$taskGroup['parent_id']][] = $taskGroup;
                    }
                    $javascriptGroupOrderInParentTask = array();
                    foreach ($groupOrderInParentTask as &$parentContainer) {
                        $parentId = '';
                        $sequentialOrderInTask = '';
                        $javascriptGroupOrderInParentTask[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                        $javascriptGroupOrderInParentTask[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';

                        for ($i = 0; $i < count($parentContainer); $i++) {
                            $sequentialOrderInTask = $i + 1;
                            $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                            $parentId = $parentContainer[$i]['parent_id'];
                            $isEnabled = $parentContainer[$i]['is_enabled'];
                            $parentContainer[$i]['order_in_task'] = $sequentialOrderInTask;

                            if ($isEnabled == 0) {
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                            } else {
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . '<tr';
                            }
                            $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInTask</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                        }
                        $sequentialOrderInTask++;
                        $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                        $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                    }

                    foreach ($taskIdList as $taskId) {
                        if (!array_key_exists($taskId, $javascriptGroupOrderInParentTask)) {
                            $javascriptGroupOrderInParentTask[$taskId]['tableHTML'] = '';
                            $javascriptGroupOrderInParentTask[$taskId]['newOrderSelectHTML'] = "<option value=\"1\" selected>1st</option>\n\r";
                        }
                    }
                    $javascriptGroupOrderInParentTask = json_encode($javascriptGroupOrderInParentTask);





                    if (count($groupIdList) > 0) {
                        $nestedGroupOrderQuery = "SELECT tgm.name, tgm.is_enabled, tgm.display_text, tgc.tag_group_id AS parent_id, tgc.order_in_group "
                                . "FROM tag_group_contents tgc "
                                . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_id = tgm.tag_group_id "
                                . "WHERE tgc.tag_group_id IN ($whereInGroups)"
                                . "ORDER BY parent_id, tgc.order_in_group";
                        $nestedGroupOrderParams['projectId'] = $projectId;
                        $nestedGroupOrderResults = run_prepared_query($DBH, $nestedGroupOrderQuery, $nestedGroupOrderParams);
                        $nestedGroups = $nestedGroupOrderResults->fetchAll(PDO::FETCH_ASSOC);
                        $groupOrderInParentGroup = array();
                        foreach ($nestedGroups as $nestedGroup) {
                            $groupOrderInParentGroup[$nestedGroup['parent_id']][] = $nestedGroup;
                        }
                        $javascriptGroupOrderInParentGroup = array();
                        foreach ($groupOrderInParentGroup as &$parentContainer) {
                            $parentId = '';
                            $sequentialOrderInGroup = '';
                            $javascriptGroupOrderInParentGroup[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                            $javascriptGroupOrderInParentGroup[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';
                            for ($i = 0; $i < count($parentContainer); $i++) {
                                $sequentialOrderInGroup = $i + 1;
                                $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                                $parentId = $parentContainer[$i]['parent_id'];
                                $isEnabled = $parentContainer[$i]['is_enabled'];
                                $parentContainer[$i]['order_in_group'] = $sequentialOrderInGroup;

                                if ($isEnabled == 0) {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                                } else {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . '<tr';
                                }
                                $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                                $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                            }
                            $sequentialOrderInGroup++;
                            $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                            $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                        }

                        foreach ($groupIdList as $groupId) {
                            if (!array_key_exists($groupId, $javascriptGroupOrderInParentGroup)) {
                                $javascriptGroupOrderInParentGroup[$groupId]['tableHTML'] = '';
                                $javascriptGroupOrderInParentGroup[$groupId]['newOrderSelectHTML'] = "<option value=\"1\" selected>1st</option>\n\r";
                            }
                        }
                        $javascriptGroupOrderInParentGroup = json_encode($javascriptGroupOrderInParentGroup);
                    }


// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h3>Create New Tag Group</h3>
                            $failedSubmissionHTML
                            <form method="post" id="editGroupDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editGroupName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Group Admin Name * :</label>
                                    <input type="textbox" id="editGroupName" class="clickableButton" name="newGroupName" value="$currentGroupName" maxlength="50" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDescription" title="This text is for admin reference only to help explain details of the group. The content of this field is not shared with standard users. 500 character limit.">Group Admin Description:</label>
                                    <textarea id="editGroupDescription" class="clickableButton" name="newGroupDescription" maxlength="500">$currentGroupDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDisplayText" title="This text is used as the group title on the classification page. Text identifying whow the tags or groups this group contains are related is best here. There is a 500 character limit. Always check the text displays correctly on the classification page after editing this field.">Group Display Text * :</label>
                                    <textarea id="editGroupDisplayText" class="clickableButton" name="newDisplayText" maxlength="500">$currentGroupDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the type of objects this group can contain. The options are either Tags or other Groups. The current croup must not have any existing contents if you wish to change this option.">Group Contains:</label>
                                    $groupContentsRadioHTML
                                </div>
                                <div class="formFieldRow">
                                    <label for="editGroupWidth" title="If specified the number in this field is the width in pixels that the group will be forced to occupy on the page. Display issues may occur if this setting to too large or small. Always check the group displays correctly on the classification page after editing this field. A setting of 0 menas the group width will be calculated automatically.">Group Width (in pixels):</label>
                                    <input type="textbox" id="editGroupWidth" class="clickableButton" name="newGroupWidth" value="$currentGroupWidth" maxlength="4" />
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons turn the display of a border around the group on and off. Using a border can be beneficial if you have groups within groups and seperation would make them clearer.">Display Group Border:</label>
                                    $groupDisplayBorderRadioHTML
                                    </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the background color of a group. Using a background color can help distinguish a group or provide a way of showing increased importance.">Group Background Color:</label>
                                    $groupBackgroundColorRadioHTML
                                    <input id="groupColorPicker" class="color clickableButton disabledClickableButton" title="Manually enter the desired background color in six digit hexadecimal or use the color picker" name="newGroupColor" value="$groupBackgroudColorPickerValue" disabled/>
                                </div>
                                $groupContainedInHTML
                                <div class="twoColumnSplit">
                                    <div>
                                    <h3>Current Display Order In Task/Group</h3>
                                    <p>Disabled (hidden) groups are shown in red.<br>
                                        All other groups are uncolored.<br>
                                        Hovering over a row displays more details of the group.</p>
                                    <table id="propertyOrderTable">
                                        <thead>
                                            <tr>
                                                <td>Position Number</td>
                                                <td>Group Name</td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div>
                                    </div>
                                    <div>
                                        <h3>New Display Order</h3>
                                        <p>Select the new position you would like the group to be shown in. All other groups will be
                                            re-numbered to sequentially precede or follow this group in their current order.</p>
                                        <label for="editGroupOrder">Select the new desired group position:</label>
                                        <select id="editGroupOrder" class="clickableButton disabledClickableButton" name="newGroupOrder" disabled>

                                        </select>
                                    </div>
                                </div>

                                <input type="hidden" name="newGroupStatus" value="1" />
                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewGroup" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Create New Group">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    $editDifferentGroupButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will exit the group creation screen without submitting
                                        changes to the database and return you to the Question Builder Menu
                                        for you to choose another item to create or edit."
                                        value="Cancel Creation and Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h3>Errors Detected in Input Data</h3>
                          <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;
                }
            }
            break;

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD THE PROJECT DETAILS UPDATE FORM HTML
//
//
        case 'tags':
// IF A GROUP TO UPDATE HAS NOT BEEN SPECIFIED OR THE OPTION TO CREATE A NEW GROUP WAS NOT SELECTED THEN
// BUILD A GROUP SELECTION FORM AND GIVE THE OPTION TO CREATE A NEW TASK
            if (!$projectEditSubAction && $importStatus != 14) {
// FIND DATA FOR ALL GROUPS IN THE PROJECT
                $projectTagsQuery = "SELECT tag_id, name, description, is_enabled FROM tags WHERE project_id = :projectId ORDER BY name";
                $projectTagsParams['projectId'] = $projectId;
                $projectTagsResults = run_prepared_query($DBH, $projectTagsQuery, $projectTagsParams);
                $projectTags = $projectTagsResults->fetchAll(PDO::FETCH_ASSOC);

// BUILD THE GROUP SELECTION HTML FORM
                $tagSelectOptionsHTML = '';
                foreach ($projectTags as $individualTag) {
                    $individualTagId = $individualTag['tag_id'];
                    $individualTagName = $individualTag['name'];
                    $individualTagDescription = $individualTag['description'];
                    $isEnabled = $individualTag['is_enabled'];
                    if ($isEnabled == 1) {
                        $tagSelectOptionsHTML .= "<option title=\"$individualTagDescription\" value=\"$individualTagId\">$individualTagName</option>";
                    } else {
                        $tagSelectOptionsHTML .= "<option title=\"$individualTagDescription\" value=\"$individualTagId\">$individualTagName (Disabled)</option>";
                    }
                }
                $actionControlsHTML = <<<EOL
                            <h3>Create Or Edit Project Tags</h3>
                            <div class="threeColumnOrSplit">

                                <div>
                                    <p>Click to create a new tag</p>
                                    <form method="post" class="tagSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="tags" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewTag" />
                                        <input type="submit" id ="createNewTagButton" class="clickableButton" value="Create New Tag" />
                                    </form>
                                </div>

                                <div>
                                    <p>OR</p>
                                </div>

                                <div>
                                    <p>Please select a tag to edit</p>
                                    <form method="post" class="tagSelectForm">
                                        <div id="formFieldRow">
                                            <label for="tagSelectBox">Tag:</label>
                                            <select id="tagSelectBox" class="clickableButton" name="tagId">
                                                $tagSelectOptionsHTML
                                            </select>
                                        </div>

                                        <input type="hidden" name="projectPropertyToUpdate" value="tags" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="updateExistingTag" />
                                        <input type="submit" id="editSelectedItemButton" class="clickableButton disabledClickableButton" value="Edit Selected Tag" disabled />
                                    </form>
                                </div>

                            </div>
EOL;
                if ($importStatus == 10) {
                    $actionControlsHTML .= <<<EOL
                        <div class="updateFormSubmissionControls">
                            <p>You have sufficient content to preview your question set.</p>
                            <button type="button" class="clickableButton" id="previewQuestionSetButton" name="previewQuestionSet"
                                title="This button allows you to preview your work so far using a simulated classification page.">
                                Preview The Question Set
                            </button>
                        </div>
EOL;
                    $jQueryDocumentDotReadyCode .= <<<EOL
                        $('#previewQuestionSetButton').click(function () {
                            window.open("taskPreview.php?projectId={$projectMetadata['project_id']}", "", "menubar=1, resizable=1, scrollbars=1, status=1, titlebar=1, toolbar=1, width=1250, height=828");
                        });

EOL;
                }
                $actionControlsHTML .= <<<EOL
                    <div class="updateFormSubmissionControls"><hr>
                        <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the tag selection screen and return you to the Question Builder menu for you to choose another question item to edit. No changes will be made to the database." value="Return to Question Builder Menu">
                    </div>

EOL;
            } // END THE CREATION OF THE GROUP SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
// CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
                $invalidRequiredField = FALSE;
                if ($projectEditSubAction) {
                    
                } else if ($importStatus === 14) {
                    $projectEditSubAction = 'createNewTag';
                } else {
                    $invalidRequiredField[] = 'projectEditSubAction';
                }
// IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                if ($tagId &&
                        isset($projectEditSubAction) &&
                        $projectEditSubAction == 'updateExistingTag') {

                    $tagMetadata = retrieve_entity_metadata($DBH, $tagId, 'tag');
                    if (!isset($currentTagName)) {
                        $currentTagName = $tagMetadata['name'];
                    }
                    $currentTagName = htmlspecialchars($currentTagName);
                    if (!isset($currentTagDescription)) {
                        $currentTagDescription = $tagMetadata['description'];
                    }
                    $currentTagDescription = restoreSafeHTMLTags(htmlspecialchars($currentTagDescription));
                    if (!isset($currentTagDisplayText)) {
                        $currentTagDisplayText = $tagMetadata['display_text'];
                    }
                    $currentTagDisplayText = htmlspecialchars($currentTagDisplayText);
                    if (!isset($currentTagToolTipText)) {
                        $currentTagToolTipText = $tagMetadata['tooltip_text'];
                    }
                    $currentTagToolTipText = restoreSafeHTMLTags(htmlspecialchars($currentTagToolTipText));
                    if (!isset($currentTagToolTipImage)) {
                        $currentTagToolTipImage = $tagMetadata['tooltip_image'];
                    }
                    $currentTagToolTipImage = htmlspecialchars($currentTagToolTipImage);
                    if (!isset($currentTagStatus)) {
                        $currentTagStatus = $tagMetadata['is_enabled'];
                    }
                    if (!isset($currentTagIsComment)) {
                        $currentTagIsComment = $tagMetadata['is_comment_box'];
                    }
                    if (!isset($currentTagIsRadio)) {
                        $currentTagIsRadio = $tagMetadata['is_radio_button'];
                    }
                    if (!isset($currentTagRadioGroupName)) {
                        $currentTagRadioGroupName = $tagMetadata['radio_button_group'];
                    }
                    $currentTagRadioGroupName = htmlspecialchars($currentTagRadioGroupName);

                    $tagTypeRadioHTML = <<<EOL
                        <input type="radio" id="editTagTypeSelect" name="newTagType" value="0" />
                        <label for="editTagTypeSelect" class="clickableButton" title="The default tag type. It has no restrictions on how it can be selected in relation to other selected tags.">Multi-Select</label>
                        <input type="radio" id="editTagTypeRadio" name="newTagType" value="1" />
                        <label for="editTagTypeRadio" class="clickableButton" title="Works like a radio button on a form where only one tag of several can be selected. All tags that are to be mutually exclusive must be assigned the same radio button group name.">Mutually Exclusive</label>
                        <input type="radio" id="editTagTypeComment" name="newTagType" value="2" />
                        <label for="editTagTypeComment" class="clickableButton" title="The tag displays as a comment box allowing the user to enter text providing a more vebose means of feedback. This provides an alternative to the standard on/off result supplied by tag buttons but interpretation of the contents usually requires human involvement.">Comment Box</label>


EOL;

                    if ($currentTagIsComment) {
                        $tagTypeRadioHTML = str_replace('2" /', '2" checked /', $tagTypeRadioHTML);
                    } else if ($currentTagIsRadio) {
                        $tagTypeRadioHTML = str_replace('1" /', '1" checked /', $tagTypeRadioHTML);
                    } else {
                        $tagTypeRadioHTML = str_replace('0" /', '0" checked /', $tagTypeRadioHTML);
                    }




                    $tagStatusRadioButtonHTML = <<<EOL
                            <input type="radio" id="editTagStatusEnabled" name="newTagStatus" value="1">
                            <label for="editTagStatusEnabled" class="clickableButton" title="The tag will be available for selection.">Enabled</label>
                            <input type="radio" id="editTagStatusDisabled" name="newTagStatus" value="0">
                            <label for="editTagStatusDisabled" class="clickableButton" title="The tag will NOT be available for selection and will be hidden from view.">Disabled</label>

EOL;
                    if ($currentTagStatus == 1) {
                        $tagStatusRadioButtonHTML = str_replace('1">', '1" checked>', $tagStatusRadioButtonHTML);
                    } else {
                        $tagStatusRadioButtonHTML = str_replace('0">', '0" checked>', $tagStatusRadioButtonHTML);
                    }
                    $existingTooltipImageHTML = '';
                    if (!empty($currentTagToolTipImage)) {
                        $existingTooltipImageHTML = <<<EOL
                                <div class="formFieldRow">
                                    <label for="currentTagToolTipImage" title="If set, this is the name of the current image file that is used if in the tooltip.">Current Tag Tooltip Image File:</label>
                                    <input type="textbox" class="clickableButton" style="width: 240px;" id="currentTagToolTipImage" value="$currentTagToolTipImage" readonly>
                                    <input type="checkbox" id="noTooltipImage" name="noTooltipImage" />
                                    <label class="clickableButton" for="noTooltipImage" style="width: 120px;">No Image</label>

                                </div>
EOL;
                        $jQueryDocumentDotReadyCode .= <<<EOL
                                $('#noTooltipImage').change(function () {
                                    if (this.checked) {
                                        $('#currentTagToolTipImage').addClass('disabledClickableButtton');
                                        $('#currentTagToolTipImage').attr('disabled', 'disabled');
                                        $('#editTagToolTipImage').addClass('disabledClickableButtton');
                                        $('#editTagToolTipImage').attr('disabled', 'disabled');
                                    } else {
                                        $('#currentTagToolTipImage').removeClass('disabledClickableButtton');
                                        $('#currentTagToolTipImage').removeAttr('disabled');
                                        $('#editTagToolTipImage').removeClass('disabledClickableButtton');
                                        $('#editTagToolTipImage').removeAttr('disabled');
                                    }
                                });
EOL;
                        $setOrChangeText = "Change";
                    } else {
                        $setOrChangeText = "Set";
                    }




                    $findParentGroupQuery = "SELECT tag_group_id FROM tag_group_contents WHERE tag_id = :tagId";
                    $findParentGroupParams['tagId'] = $tagId;
                    $findParentGroupResult = run_prepared_query($DBH, $findParentGroupQuery, $findParentGroupParams);
                    $allTagParents = $findParentGroupResult->fetchAll(PDO::FETCH_ASSOC);
                    $parentOfSelectedTag = FALSE;
                    if (count($allTagParents) > 1) {
                        foreach ($allTagParents as $parentGroup) {
                            $groupTypeQuery = "SELECT COUNT(*) FROM tag_group_metadata WHERE tag_group_id = :groupId AND contains_groups = 0";
                            $groupTypeParams['groupId'] = $parentGroup['tag_group_id'];
                            $groupTypeResults = run_prepared_query($DBH, $groupTypeQuery, $groupTypeParams);
                            if ($groupTypeResults->fetchColumn() == 1) {
                                $parentOfSelectedTag = $parentGroup['tag_group_id'];
                                break;
                            }
                        }
                    } else {
                        $parentOfSelectedTag = $allTagParents[0]['tag_group_id'];
                    }

                    if ($parentOfSelectedTag) {
                        $groups = buildGroupSelectOptions($DBH, $projectId, $parentOfSelectedTag, false, true);
                    } else {
                        $groups = buildGroupSelectOptions($DBH, $projectId, false, false, true);
                    }
                    $groupSelectOptionsHTML = $groups[0];
                    $groupIdList = $groups[1];
                    $groupNameList = $groups[2];
                    $whereInGroups = where_in_string_builder($groupIdList);





                    $tagOrderQuery = "SELECT t.name, t.is_enabled, t.display_text, tgc.tag_group_id AS parent_id, t.tag_id AS child_id, tgc.order_in_group "
                            . "FROM tag_group_contents tgc "
                            . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                            . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                            . "WHERE tgc.tag_group_id IN ($whereInGroups) AND tgm.contains_groups = 0 "
                            . "ORDER BY parent_id, tgc.order_in_group";
                    $tagOrderParams = array();
                    $tagOrderResults = run_prepared_query($DBH, $tagOrderQuery, $tagOrderParams);
                    $tags = $tagOrderResults->fetchAll(PDO::FETCH_ASSOC);
                    $tagOrderInParentGroup = array();
                    foreach ($tags as $individualTag) {
                        $tagOrderInParentGroup[$individualTag['parent_id']][] = $individualTag;
                    }

                    $javascriptTagOrderInParentGroup = array();
                    foreach ($tagOrderInParentGroup as &$parentContainer) {
                        $parentId = '';
                        $sequentialOrderInGroup = '';
                        $containerContainsTag = FALSE;
                        $javascriptTagOrderInParentGroup[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                        $javascriptTagOrderInParentGroup[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';
                        for ($i = 0; $i < count($parentContainer); $i++) {
                            $sequentialOrderInGroup = $i + 1;
                            $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                            $parentId = $parentContainer[$i]['parent_id'];
                            $childId = $parentContainer[$i]['child_id'];
                            $isEnabled = $parentContainer[$i]['is_enabled'];


                            $parentContainer[$i]['order_in_group'] = $sequentialOrderInGroup;
                            $orderInGroup = $sequentialOrderInGroup;

                            if ($childId == $tagId) {
                                $oldTagParentId = $parentId;
                                $containerContainsTag = TRUE;
                                $oldTagOrderNumber = $orderInGroup;
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . '<tr id="currentProperty"';
                                $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                            } else if ($isEnabled == 0) {
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                                $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                            } else {
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . '<tr';
                                $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                            }
                            $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]
                                    }</td></tr>\n\r";
                        }
                        if (!$containerContainsTag) {
                            $sequentialOrderInGroup++;
                            $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                            $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                        }
                    }
                    $javascriptTagOrderInParentGroup = json_encode($javascriptTagOrderInParentGroup);

// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h3>Edit Tag Details</h3>
                            $failedSubmissionHTML
                            <form enctype="multipart/form-data" method="post" id="editTagDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTagName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Tag Admin Name * :</label>
                                    <input type="textbox" id="editTagName" class="clickableButton" name="newTagName" maxlength="50" value="$currentTagName" />
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDescription" title="This text is for admin reference only to help explain details of the tag. The content of this field is not shared with standard users. 500 character limit.">Tag Admin Description:</label>
                                    <textarea id="editTagDescription" class="clickableButton" name="newTagDescription" maxlength="500">$currentTagDescription</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDisplayText" title="This text is displayed within the tag shown to the user. It should be short and descripive, describing the option the user is selecting. There is a 50 character limit however this length of text is likely to exceed the tags available space. Always check the text displays correctly on the classification page after editing this field.">Tag Display Text * :</label>
                                    <textarea id="editTagDisplayText" class="clickableButton" name="newTagDisplayText" maxlength="50">$currentTagDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipText" title="This text is shown in a tooltip popup if the user hovers over the tag. The text should provide more information or educational content about the meaning of the tag. There is a 1000 character limit. Always check the text displays correctly on the classification page after editing this field.">Tag Tooltip Text:</label>
                                    <textarea id="editTagToolTipText" class="clickableButton" name="newTagToolTipText" maxlength="1000">$currentTagToolTipText</textarea>
                                </div>
                                $existingTooltipImageHTML
                                <div class="formFieldRow">
                                    <label for="editTagToolTipImage" title="This is the name of the uploaded image file that is to be used if an image is desired in the tooltip. The image will be displayed below the tooltip text in the tooltiup popup. There is a 255 character limit on the file name. Always check the image displays correctly on the classification page after editing this field.">$setOrChangeText Tag Tooltip Image File:</label>
                                    <input type="file" accept=".jpg,.jpeg" class="clickableButton"  id="editTagToolTipImage" name="newTagToolTipImage">
                                </div>
                                <div class="formFieldRow">
                                    <label title="These options change the behavior or functionality of the tag. Hover over each option for a detailed description of what it does.">Tag Type:</label>
                                    $tagTypeRadioHTML
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagRadioGroupName" title="If the tag is to be mutually exclusive then all tags that share this exclusivity must be given the same Exclusivity Group name to tie them together. Enter any name here that you will use for all tags that should be mutally exclusive from one another. There is a 20 character limit. Always test tag behavior after editing this field to ensure all tags that shoudl be mutually exclusive behave in the correct manner (only one of them can be selected at any one time).">Exclusivity Group Name:</label>
                                    <input type="textbox" id="editTagRadioGroupName" class="clickableButton disabledClickableButton" name="newTagRadioGroupName" maxlength="20" value="$currentTagRadioGroupName" disabled />
                                </div>
                                <div class="formFieldRow">
                                    <label for="tagParentGroupSelectBox" title="Use the selection box to choose which group should contain this tag. Associated tags should be grouped together in the same parent group.">Parent Group * :</label>
                                    <select id="tagParentGroupSelectBox" class="clickableButton" name="newParentId">
                                        $groupSelectOptionsHTML
                                    </select>
                                </div>
                                <div class="twoColumnSplit">
                                    <div>
                                    <h3>Current Display Order Of Selected Parent Group</h3>
                                    <p>Disabled (hidden) tags are shown in red.<br>
                                        The current tag being edited is shown in green.<br>
                                        All other tags are uncolored.<br>
                                        Hovering over a row displays more details of the tag.</p>
                                    <table id="propertyOrderTable">
                                        <thead>
                                            <tr>
                                                <td>Position Number</td>
                                                <td>Tag Name</td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div>
                                    </div>
                                    <div>
                                        <h3>New Display Order</h3>
                                        <p>Select the new position you would like the tag to be shown in. All other tags will be
                                            re-numbered to sequentially precede or follow this tag in their current order.</p>
                                        <label for="editTagOrder">Select the new desired tag position:</label>
                                        <select id="editTagOrder" class="clickableButton" name="newTagOrder">
                                        </select>
                                    </div>
                                </div>
                                 <div class="formFieldRow">
                                    <label title="Disabling a tag removes it from public view and therefore cannot be selected. A disabled tag can easily be re-added in the future br re-enabling it here.">Tag Status:</label>
                                    $tagStatusRadioButtonHTML
                                </div>

                                <input type="hidden" name="oldParentId" value="$oldTagParentId" />
                                <input type="hidden" name="oldTagOrder" value="$oldTagOrderNumber" />
                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="updateExistingTag" />
                                <input type="hidden" name="tagId" value="$tagId" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection"
                                        title="This will return you to the tag selection screen to choose
                                        another tag based action." value="Choose Another Tag Action">
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                              title="This will return you to the Question Builder menu
                                              for you to choose another item to create or edit."
                                                  value="Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))
// IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                else if ($projectEditSubAction == 'createNewTag') {

                    if (!isset($currentTagName)) {
                        $currentTagName = '';
                    }
                    if (!isset($currentTagDescription)) {
                        $currentTagDescription = '';
                    }
                    if (!isset($currentTagDisplayText)) {
                        $currentTagDisplayText = '';
                    }
                    if (!isset($currentTagToolTipText)) {
                        $currentTagToolTipText = '';
                    }
                    if (!isset($currentTagToolTipImage)) {
                        $currentTagToolTipImage = '';
                    }
                    if (!isset($currentTagIsComment)) {
                        $currentTagIsComment = '';
                    }
                    if (!isset($currentTagIsRadio)) {
                        $currentTagIsRadio = '';
                    }
                    if (!isset($currentTagRadioGroupName)) {
                        $currentTagRadioGroupName = '';
                    }

                    $tagTypeRadioHTML = <<<EOL
                        <input type="radio" id="editTagTypeSelect" name="newTagType" value="0" />
                        <label for="editTagTypeSelect" class="clickableButton" title="The default tag type. It has no restrictions on how it can be selected in relation to other selected tags.">Multi-Select</label>
                        <input type="radio" id="editTagTypeRadio" name="newTagType" value="1" />
                        <label for="editTagTypeRadio" class="clickableButton" title="Works like a radio button on a form where only one tag of several can be selected. All tags that are to be mutually exclusive must be assigned the same radio button group name.">Mutually Exclusive</label>
                        <input type="radio" id="editTagTypeComment" name="newTagType" value="2" />
                        <label for="editTagTypeComment" class="clickableButton" title="The tag displays as a comment box allowing the user to enter text providing a more vebose means of feedback. This provides an alternative to the standard on/off result supplied by tag buttons but interpretation of the contents usually requires human involvement.">Comment Box</label>


EOL;
                    if ($currentTagIsComment) {
                        $tagTypeRadioHTML = str_replace('2" /', '2" checked /', $tagTypeRadioHTML);
                    } else if ($currentTagIsRadio) {
                        $tagTypeRadioHTML = str_replace('1" /', '1" checked /', $tagTypeRadioHTML);
                    } else {
                        $tagTypeRadioHTML = str_replace('0" /', '0" checked /', $tagTypeRadioHTML);
                    }




                    if ($importStatus != 14) {
                        $editDifferentTagButtonHTML = '
                            <input type="button" class="clickableButton" id="returnToTaskSelection"
                            title="This will exit the tag creation screen without submitting changes to the
                            database and return you to the tag selection screen for you to choose another
                            tag option." value="Cancel Changes and Choose Another Tag Action">
                        ';
                        $tagContinedInHTML = <<<EOL
                            <div class="twoColumnSplit">
                                <div>
                                <h3>Current Display Order Of Selected Parent Group</h3>
                                <p>Disabled (hidden) tags are shown in red.<br>
                                    The current tag being edited is shown in green.<br>
                                    All other tags are uncolored.<br>
                                    Hovering over a row displays more details of the tag.</p>
                                <table id="propertyOrderTable">
                                    <thead>
                                        <tr>
                                            <td>Position Number</td>
                                            <td>Tag Name</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                                </div>
                                <div>
                                </div>
                                <div>
                                    <h3>New Display Order</h3>
                                    <p>Select the new position you would like the tag to be shown in. All other tags will be
                                        re-numbered to sequentially precede or follow this tag in their current order.</p>
                                    <label for="editTagOrder">Select the new desired tag position:</label>
                                    <select id="editTagOrder" class="clickableButton disabledClickableButton" name="newTagOrder" disabled>
                                    </select>
                                </div>
                            </div>
EOL;
                    } else {
                        $tagContinedInHTML = '<input type="hidden" name="newTagOrder" value="1" />';
                        $editDifferentTagButtonHTML = '';
                    }

                    $groups = buildGroupSelectOptions($DBH, $projectId, false, false, true);
                    $groupSelectOptionsHTML = $groups[0];
                    $groupIdList = $groups[1];
                    $groupNameList = $groups[2];
                    $whereInGroups = where_in_string_builder($groupIdList);

                    $tagOrderQuery = "SELECT t.name, t.is_enabled, t.display_text, tgc.tag_group_id AS parent_id, t.tag_id AS child_id, tgc.order_in_group "
                            . "FROM tag_group_contents tgc "
                            . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                            . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                            . "WHERE tgc.tag_group_id IN ($whereInGroups) AND tgm.contains_groups = 0 "
                            . "ORDER BY parent_id, tgc.order_in_group";
                    $tagOrderParams = array();
                    $tagOrderResults = run_prepared_query($DBH, $tagOrderQuery, $tagOrderParams);
                    $tags = $tagOrderResults->fetchAll(PDO::FETCH_ASSOC);
                    $tagOrderInParentGroup = array();
                    foreach ($tags as $individualTag) {
                        $tagOrderInParentGroup[$individualTag['parent_id']][] = $individualTag;
                    }

                    $javascriptTagOrderInParentGroup = array();
                    foreach ($tagOrderInParentGroup as &$parentContainer) {
                        $parentId = '';
                        $sequentialOrderInGroup = '';
                        $javascriptTagOrderInParentGroup[$parentContainer[0]['parent_id']]['tableHTML'] = '';
                        $javascriptTagOrderInParentGroup[$parentContainer[0]['parent_id']]['newOrderSelectHTML'] = '';

                        for ($i = 0; $i < count($parentContainer); $i++) {
                            $sequentialOrderInGroup = $i + 1;
                            $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                            $parentId = $parentContainer[$i]['parent_id'];
                            $childId = $parentContainer[$i]['child_id'];
                            $isEnabled = $parentContainer[$i]['is_enabled'];
                            $parentContainer[$i]['order_in_group'] = $sequentialOrderInGroup;

                            if ($isEnabled == 0) {
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                            } else {
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . '<tr';
                            }
                            $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup [$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                        }
                        $sequentialOrderInGroup++;
                        $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                        $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId] ['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                    }

                    foreach ($groupIdList as $groupId) {
                        if (!array_key_exists($groupId, $javascriptTagOrderInParentGroup)) {
                            $javascriptTagOrderInParentGroup[$groupId]['tableHTML'] = '';
                            $javascriptTagOrderInParentGroup[$groupId]['newOrderSelectHTML'] = "<option value=\"1\" selected>1st</option>\n\r";
                        }
                    }
                    $javascriptTagOrderInParentGroup = json_encode($javascriptTagOrderInParentGroup);



// BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h3>Create New Tag</h3>
                            $failedSubmissionHTML
                            <form enctype="multipart/form-data" method="post" id="editTagDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTagName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Tag Admin Name * :</label>
                                    <input type="textbox" id="editTagName" class="clickableButton" name="newTagName" maxlength="50" value="$currentTagName" />
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDescription" title="This text is for admin reference only to help explain details of the tag. The content of this field is not shared with standard users. 500 character limit.">Tag Admin Description:</label>
                                    <textarea id="editTagDescription" class="clickableButton" name="newTagDescription" maxlength="500">$currentTagDescription</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDisplayText" title="This text is displayed within the tag shown to the user. It should be short and descripive, describing the option the user is selecting. There is a 50 character limit however this length of text is likely to exceed the tags available space. Always check the text displays correctly on the classification page after editing this field.">Tag Display Text * :</label>
                                    <textarea id="editTagDisplayText" class="clickableButton" name="newTagDisplayText" maxlength="50">$currentTagDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipText" title="This text is shown in a tooltip popup if the user hovers over the tag. The text should provide more information or educational content about the meaning of the tag. There is a 1000 character limit. Always check the text displays correctly on the classification page after editing this field.">Tag Tooltip Text:</label>
                                    <textarea id="editTagToolTipText" class="clickableButton" name="newTagToolTipText" maxlength="1000">$currentTagToolTipText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipImage" title="This is the name of the uploaded image file that is to be used if an image is desired in the tooltip. The image will be displayed below the tooltip text in the tooltiup popup. There is a 255 character limit on the file name. Always check the image displays correctly on the classification page after editing this field.">Tag Tooltip Image File:</label>
                                    <input type="file" accept=".jpg,.jpeg" class="clickableButton"  id="editTagToolTipImage" name="newTagToolTipImage">
                                </div>
                                <div class="formFieldRow">
                                    <label title="These options change the behavior or functionality of the tag. Hover over each option for a detailed description of what it does.">Tag Type:</label>
                                    $tagTypeRadioHTML
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagRadioGroupName" title="If the tag is to be mutually exclusive then all tags that share this exclusivity must be given the same Exclusivity Group name to tie them together. Enter any name here that you will use for all tags that should be mutally exclusive from one another. There is a 20 character limit. Always test tag behavior after editing this field to ensure all tags that should be mutually exclusive behave in the correct manner (only one of them can be selected at any one time).">Exclusivity Group Name:</label>
                                    <input type="textbox" id="editTagRadioGroupName" class="clickableButton disabledClickableButton" name="newTagRadioGroupName" maxlength="20" value="$currentTagRadioGroupName" disabled />
                                </div>
                                <div class="formFieldRow">
                                    <label for="tagParentGroupSelectBox" title="Use the selection box to choose which group should contain this tag. Associated tags should be grouped together in the same parent group.">Parent Group * :</label>
                                    <select id="tagParentGroupSelectBox" class="clickableButton" name="newParentId">
                                        $groupSelectOptionsHTML
                                    </select>
                                </div>
                                $tagContinedInHTML
                                <input type="hidden" name="newTagStatus" value="1" />
                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewTag" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <p class="clearBoth">* indicates a required field</p>
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    $editDifferentTagButtonHTML
                                    <input type="button" class="clickableButton" id="returnToActionSelection"
                                        title="This will exit the tag creation screen without submitting changes
                                        to the database and return you to the Question Builder menu for you
                                        to choose another item to create or edit."
                                        value="Cancel Creation and Return To Question Builder Menu">
                                </div>

                            </form>
EOL;
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h3>Errors Detected in Input Data</h3>
                          <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;
                }
            }
            break;
    } // END switch ($projectPropertyToUpdate)
} // END if (isset($projectId) && ... !isset($_POST['editSubmitted']))
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build variable javascript code.

$javaScript .= "var projectId = $projectId;\n\r";

if ($projectPropertyToUpdate) {
    $javaScript .= "var propertyToUpdate = '$projectPropertyToUpdate';\n\r";
}
if (isset($javascriptGroupOrderInParentTask)) {
    $javaScript .= 'var groupOrderInTaskData = ' . $javascriptGroupOrderInParentTask . ";\n\r";
} else {
    $javaScript .= "var groupOrderInTaskData;\n\r";
}
if (isset($javascriptGroupOrderInParentGroup)) {
    $javaScript .= 'var groupOrderInGroupData = ' . $javascriptGroupOrderInParentGroup . ";\n\r";
} else {
    $javaScript .= "var groupOrderInGroupData;\n\r";
}
if (isset($javascriptTagOrderInParentGroup)) {
    $javaScript .= 'var tagOrderInGroupData = ' . $javascriptTagOrderInParentGroup . ";\n\r";
} else {
    $javaScript .= "var tagOrderInGroupData;\n\r";
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build jQuery Document.Ready code.

$jQueryDocumentDotReadyCode .= <<<EOL
        $('#taskSelectBox, #groupSelectBox, #tagSelectBox,#tagParentGroupSelectBox').prop("selectedIndex", -1);

        $('#taskSelectBox, #groupSelectBox, #tagSelectBox').change(function() {
            $('#editSelectedItemButton').removeClass('disabledClickableButton').removeAttr('disabled');
        });

        var hasBackgroundColor = $('#editGroupHasColorYes[checked]').val();
        if (typeof hasBackgroundColor !== 'undefined') {
            $('#groupColorPicker').removeClass('disabledClickableButton').removeAttr('disabled');
        }


        $('#editGroupHasColorYes').click(function() {
            $('#groupColorPicker').removeClass('disabledClickableButton').removeAttr('disabled');

        });

        $('#editGroupHasColorNo').click(function() {
            $('#groupColorPicker').addClass('disabledClickableButton').attr('disabled', '');
        });

        if ($('#editTagTypeRadio').is(':checked')) {
            $('#editTagRadioGroupName').removeClass('disabledClickableButton').removeAttr('disabled');
        }

        $('#editTagTypeRadio').click(function() {
            $('#editTagRadioGroupName').removeClass('disabledClickableButton').removeAttr('disabled');
        });

        $('#editTagTypeSelect, #editTagTypeComment').click(function() {
            $('#editTagRadioGroupName').addClass('disabledClickableButton').attr('disabled', '');
        });

        $('#returnToActionSelection').click(function() {
            var form = $('<form method="post">' +
                    '<input type="hidden" name="projectId" value="' + projectId + '" />' +
                    '</form>');
            $('body').append(form);
            $(form).submit();
        });

        $('#returnToTaskSelection, #returnToProjectDetails, #returnToTagSelection').click(function() {
            var form = $('<form method="post">' +
                    '<input type="hidden" name="projectId" value="' + projectId + '" />' +
                    '<input type="hidden" name="projectPropertyToUpdate" value="' + propertyToUpdate + '" />' +
                    '</form>');
            $('body').append(form);
            $(form).submit();
        });

        $('#taskSelectBox').change(function() {
            $('#editGroupOrder').removeClass('disabledClickableButton').removeAttr('disabled');
            var selectedTask = $(this).val();
            $('#groupSelectBox').prop("selectedIndex", -1);
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(groupOrderInTaskData[selectedTask]['tableHTML']);
            $('#editGroupOrder').empty();
            $('#editGroupOrder').append(groupOrderInTaskData[selectedTask]['newOrderSelectHTML']);
        });

        $('#groupSelectBox').change(function() {
            $('#editGroupOrder').removeClass('disabledClickableButton').removeAttr('disabled');
            var selectedGroup = $(this).val();
            $('#taskSelectBox').prop("selectedIndex", -1);
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(groupOrderInGroupData[selectedGroup]['tableHTML']);
            $('#editGroupOrder').empty();
            $('#editGroupOrder').append(groupOrderInGroupData[selectedGroup]['newOrderSelectHTML']);

        });

        $('#tagParentGroupSelectBox').change(function() {
            $('#editTagOrder').removeClass('disabledClickableButton').removeAttr('disabled');
            var selectedGroup = $(this).val();
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(tagOrderInGroupData[selectedGroup]['tableHTML']);
            $('#editTagOrder').empty();
            $('#editTagOrder').append(tagOrderInGroupData[selectedGroup]['newOrderSelectHTML']);
        });

        $('#cloneProjectForm, #editActionForm, #tasksCompleteForm').submit(function(){
            $('#cloneButton, #buildItemButton, #continueButton').addClass('disabledClickableButton').attr('disabled', 'disabled');
        });

EOL;

if (isset($javascriptGroupOrderInParentTask)) {
    $jQueryDocumentDotReadyCode .= <<<EOL
                    var selectedTaskElement = $('#taskSelectBox option[selected]');
                    var selectedTaskValue = selectedTaskElement.val();
                    var selectedTaskIndex = selectedTaskElement.index();
                    if (typeof selectedTaskValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(groupOrderInTaskData[selectedTaskValue]['tableHTML']);
                        $('#editGroupOrder').append(groupOrderInTaskData[selectedTaskValue]['newOrderSelectHTML']);
                        $('#taskSelectBox').prop('selectedIndex', selectedTaskIndex);
                    }

EOL;
}

if (isset($javascriptGroupOrderInParentGroup)) {
    $jQueryDocumentDotReadyCode .= <<<EOL
                    var selectedGroupElement = $('#groupSelectBox option[selected]');
                    var selectedGroupValue = selectedGroupElement.val();
                    var selectedGroupIndex = selectedGroupElement.index();
                    if (typeof selectedGroupValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(groupOrderInGroupData[selectedGroupValue]['tableHTML']);
                        $('#editGroupOrder').append(groupOrderInGroupData[selectedGroupValue]['newOrderSelectHTML']);
                        $('#groupSelectBox').prop('selectedIndex', selectedGroupIndex);
                    }

EOL;
}

if (isset($javascriptTagOrderInParentGroup)) {
    $jQueryDocumentDotReadyCode .= <<<EOL
                    var selectedGroupElement = $('#tagParentGroupSelectBox option[selected]');
                    var selectedGroupValue = selectedGroupElement.val();
                    var selectedGroupIndex = selectedGroupElement.index();
                    if (typeof selectedGroupValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(tagOrderInGroupData[selectedGroupValue]['tableHTML']);
                        $('#editTagOrder').append(tagOrderInGroupData[selectedGroupValue]['newOrderSelectHTML']);
                        $('#tagParentGroupSelectBox').prop('selectedIndex', selectedGroupIndex);
                    }

EOL;
}

$javaScriptLinkArray[] = "scripts/jscolor/jscolor.js";


$embeddedCSS .= <<<EOL
    
    #cloneProjectSelect {
        width: 300px;
    }
        
EOL;
