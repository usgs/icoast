<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();


function sortByOrderInProject($a, $b) {
    return $a['order_in_project'] - $b['order_in_project'];
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
                $groupSelectOptionsHTML .= ' selected';
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

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];
$actionSummaryHTML = '';
$projectUpdateErrorHTML = '';
//
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// UPDATE CODE
//
//
if (isset($_POST['editSubmitted'])) {
    // MUST HAVE PROJECT ID AND UPDATE PROPERTY TO PROCEED WITH THE UPDATE
    if (isset($_POST['projectId']) && isset($_POST['projectPropertyToUpdate'])) {
        $userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId);
        // CHECK USER HAS THE RIGHTS TO UPDATE THE SPECIFIED PROJECT
        if (in_array($_POST['projectId'], $userAdministeredProjects)) {


            // CUSTOMIZE THE UPDATE PROCESS BASED ON THE PROPERTY BEING UPDATED
            switch ($_POST['projectPropertyToUpdate']) {
                //
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                // UPDATE DETAILS
                //
            //
                case 'details':
                    // CHECK ALL REQUIRED FIELDS ARE PRESENT
                    if (isset($_POST['newProjectName']) &&
                            isset($_POST['newProjectDescription']) &&
                            isset($_POST['newPostStormHeader']) &&
                            isset($_POST['newPreStormHeader']) &&
                            isset($_POST['newProjectStatus'])) {

                        $invalidRequiredField = FALSE;
                        $dataChanges = FALSE;
                        $databaseUpdateFailure = FALSE;

                        settype($_POST['projectId'], 'integer');
                        if (!empty($_POST['projectId'])) {
                            $projectId = $_POST['projectId'];
                        } else {
                            $invalidRequiredField['projectId'] = $_POST['projectId'];
                        }

                        if (!empty($_POST['newProjectName'])) {
                            $newProjectName = htmlspecialchars($_POST['newProjectName']);
                        } else {
                            $invalidRequiredField['newProjectName'] = $_POST['newProjectName'];
                        }

                        $newProjectDescription = htmlspecialchars($_POST['newProjectDescription']);

                        if (!empty($_POST['newPostStormHeader'])) {
                            $newPostStormHeader = htmlspecialchars($_POST['newPostStormHeader']);
                        } else {
                            $invalidRequiredField['newPostStormHeader'] = $_POST['newPostStormHeader'];
                        }

                        if (!empty($_POST['newPreStormHeader'])) {
                            $newPreStormHeader = htmlspecialchars($_POST['newPreStormHeader']);
                        } else {
                            $invalidRequiredField['newPreStormHeader'] = $_POST['newPreStormHeader'];
                        }

                        if ($_POST['newProjectStatus'] == 0 || $_POST['newProjectStatus'] == 1) {
                            $newProjectStatus = $_POST['newProjectStatus'];
                        } else {
                            $invalidRequiredField['newProjectStatus'] = $_POST['newProjectStatus'];
                        }

                        if (!$invalidRequiredField) {

                            $checkForChangesQuery = "SELECT * FROM projects WHERE project_id = :projectId";
                            $checkForChangesParams['projectId'] = $projectId;
                            $checkForChangesResult = run_prepared_query($DBH, $checkForChangesQuery, $checkForChangesParams);
                            $oldProjectDetails = $checkForChangesResult->fetch(PDO::FETCH_ASSOC);
                            $taskFieldsToUpdate = array();
                            foreach ($oldProjectDetails as $column => $oldColumnValue) {
                                switch ($column) {
                                    case 'name':
                                        if ($oldColumnValue != $newProjectName) {
                                            $taskFieldsToUpdate['name'] = $newProjectName;
                                        }
                                        break;
                                    case 'description':
                                        if ($oldColumnValue != $newProjectDescription) {
                                            $taskFieldsToUpdate['description'] = $newProjectDescription;
                                        }
                                        break;
                                    case 'post_image_header':
                                        if ($oldColumnValue != $newPostStormHeader) {
                                            $taskFieldsToUpdate['post_image_header'] = $newPostStormHeader;
                                        }
                                        break;
                                    case 'pre_image_header':
                                        if ($oldColumnValue != $newPreStormHeader) {
                                            $taskFieldsToUpdate['pre_image_header'] = $newPreStormHeader;
                                        }
                                        break;
                                    case 'is_public':
                                        if ($oldColumnValue != $newProjectStatus) {
                                            $taskFieldsToUpdate['is_public'] = $newProjectStatus;
                                        }
                                        break;
                                }
                            }

                            if (count($taskFieldsToUpdate) > 0) {
                                $dataChanges = true;

                                $updateProjectDetailsQuery = "UPDATE projects "
                                        . "SET ";
                                $updateProjectDetailsParams = array();
                                $columnUpdateCount = 0;
                                foreach ($taskFieldsToUpdate as $column => $value) {
                                    $updateProjectDetailsQuery .= "$column=:$column";
                                    $columnUpdateCount++;
                                    if ($columnUpdateCount != count($taskFieldsToUpdate)) {
                                        $updateProjectDetailsQuery .= ", ";
                                    }
                                    $updateProjectDetailsParams[$column] = $value;
                                }
                                $updateProjectDetailsQuery .= " WHERE project_id = :projectToUpdate LIMIT 1";
                                $updateProjectDetailsParams['projectToUpdate'] = $projectId;

                                $updateProjectDetailsResult = run_prepared_query($DBH, $updateProjectDetailsQuery, $updateProjectDetailsParams);
                                $affectedRows = $updateProjectDetailsResult->rowCount();

                                if (isset($affectedRows) && $affectedRows == 1) {

                                } else {
                                    $databaseUpdateFailure['ProjectUpdateQuery'] = '$tagUpdateQuery';
                                }
                            }
                        }
                    } else {
                        $invalidRequiredField['Unknown'] = "Core fields are missing from supplied data.";
                        // ERROR LOGGING
                    }

                    if ($invalidRequiredField) {
                        $actionSummaryHTML = <<<EOL
                                <h2>Errors Detected in Input Data</h2>
                                <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToProjectDetails" title="This will return you to the project details editing screen screen for you to update this project's details again." value="Return to the Project Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;
                    } else if (!$dataChanges) {
                        $actionSummaryHTML = <<<EOL
                                <h2>No Changes Detected</h2>
                                <p>No change in the project data has been detected. The database has not been altered.</p>

EOL;
                    } else if ($databaseUpdateFailure) {
                        $projectUpdateErrorHTML = <<<EOL
                                <h2>Update Failed</h2>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToProjectDetails" title="This will return you to the project details editing screen screen for you to update this project's details again." value="Return to the Project Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;
                    } else {
                        $actionSummaryHTML = <<<EOL
                                <h2>Update Successful</h2>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
                    }



                    if (!$databaseUpdateFailure && !$invalidRequiredField) {

                        $summaryQuery = "SELECT * FROM projects WHERE project_id = :projectId";
                        $summaryParams['projectId'] = $projectId;
                        $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                        $dbProjectMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                        if ($dbProjectMetadata['is_public'] == 1) {
                            $dbProjectStatusText = 'Enabled';
                        } else {
                            $dbProjectStatusText = '<span class="redHighlight">Disabled</span>';
                        }

                        $actionSummaryHTML .= <<<EOL
                                <h3>Project Details Summary</h3>
                                <table id="updateSummaryTable">
                                    <tbody>
                                        <tr>
                                            <td>Project Name:</td>
                                            <td class="userData">{$dbProjectMetadata['name']}</td>
                                        </tr>
                                        <tr>
                                            <td>Project Description</td>
                                            <td class="userData">{$dbProjectMetadata['description']}</td>
                                        </tr>
                                        <tr>
                                            <td>Post Storm Image Header Text:</td>
                                            <td class="userData">{$dbProjectMetadata['post_image_header']}</td>
                                        </tr>
                                        <tr>
                                            <td>Pre Storm Image Header Text:</td>
                                            <td class="userData">{$dbProjectMetadata['pre_image_header']}</td>
                                        </tr>
                                        <tr>
                                            <td>Project Status:</td>
                                            <td class = "userData">$dbProjectStatusText</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToProjectDetails" title="This will return you to the project details editing screen screen for you to update this project's details again." value="Return to the Project Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;
                    }

                    break; // END PROJECT DETAILS UPDATE
                //
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                // UPDATE TASKS
                //
                //
                case 'tasks':
                    // CHECK THAT ALL REQUIRED FIELDS ARE PRESENT & CORRECT
                    if (isset($_POST['projectEditSubAction']) &&
                            isset($_POST['newTaskName']) &&
                            isset($_POST['newTaskDescription']) &&
                            isset($_POST['newDisplayTitle']) &&
                            isset($_POST['newOrderInProject']) &&
                            isset($_POST['newTaskStatus'])) {


                        $invalidRequiredField = FALSE;
                        $dataChanges = FALSE;
                        $databaseUpdateFailure = FALSE;

                        if ($_POST['projectEditSubAction'] == 'updateExistingTask' || $_POST['projectEditSubAction'] == 'createNewTask') {
                            $projectEditSubAction = $_POST['projectEditSubAction'];
                        } else {
                            $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                        }

                        settype($_POST['projectId'], 'integer');
                        if (!empty($_POST['projectId'])) {
                            $projectId = $_POST['projectId'];
                        } else {
                            $invalidRequiredField['projectId'] = $_POST['projectId'];
                        }

                        if (!empty($_POST['newTaskName'])) {
                            $newTaskName = htmlspecialchars($_POST['newTaskName']);
                        } else {
                            $invalidRequiredField['newTaskName'] = $_POST['newTaskName'];
                        }

                        $newTaskDescription = htmlspecialchars($_POST['newTaskDescription']);

                        if (!empty($_POST['newDisplayTitle'])) {
                            $newDisplayTitle = htmlspecialchars($_POST['newDisplayTitle']);
                        } else {
                            $invalidRequiredField['newDisplayTitle'] = $_POST['newDisplayTitle'];
                        }

                        settype($_POST['newOrderInProject'], 'integer');
                        if (!empty($_POST['newOrderInProject'])) {
                            $newOrderInProject = $_POST['newOrderInProject'];
                        } else {
                            $invalidRequiredField['newOrderInProject'] = $_POST['newOrderInProject'];
                        }

                        if ($_POST['newTaskStatus'] == 0 || $_POST['newTaskStatus'] == 1) {
                            $newTaskStatus = $_POST['newTaskStatus'];
                        } else {
                            $invalidRequiredField['newTaskStatus'] = $_POST['newTaskStatus'];
                        }

                        if (!$invalidRequiredField) {




                            $projectTaskQuery = "SELECT * FROM task_metadata WHERE project_id = :projectId ORDER BY order_in_project ASC";
                            $projectTaskParams['projectId'] = $projectId;
                            $projectTaskResults = run_prepared_query($DBH, $projectTaskQuery, $projectTaskParams);
                            $projectTasks = $projectTaskResults->fetchAll(PDO::FETCH_ASSOC);
                            $numberOfTasks = count($projectTasks);



                            // IF A TASK_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingTask
                            if (isset($_POST['taskId']) &&
                                    $projectEditSubAction == 'updateExistingTask') {

                                settype($_POST['taskId'], 'integer');
                                if (!empty($_POST['taskId'])) {
                                    $taskId = $_POST['taskId'];
                                } else {
                                    $invalidRequiredField['taskId'] = $_POST['taskId'];
                                }

                                if (!$invalidRequiredField) {
                                    // REWRITE THE 'ORDER_IN_PROJECT' FIELDS TO BE SEQUENTIAL IF NOT ALREADY. ORDER IS UNCHANGED
                                    $orderResequenced = FALSE;
                                    $selectedProjectOrderToBeChanged = FALSE;
                                    $taskFieldsToUpdate = array();
                                    // LOOP THROUGH THE TASKS
                                    for ($i = 0; $i < $numberOfTasks; $i++) {
                                        // IF THE DATABASE ORDER NUMBER ISN'T SEQUENTIAL THEN CHANGE IT AND FLAG THAT A CHANGE WAS MADE
                                        if ($projectTasks[$i]['order_in_project'] != ($i + 1)) {
                                            $projectTasks[$i]['order_in_project'] = ($i + 1);
                                            $projectTasks[$i]['is_altered'] = true;
                                            $orderResequenced = TRUE;
                                        } else {
                                            $projectTasks[$i]['is_altered'] = false;
                                        }
                                        // IF THE CURRENT TASK IS THE ONE THE USER IS EDITING AND A CHANGE IN ORDER IS DETECTED THEN SET THE FLAG
                                        // AND DETECT IF THE TASK WILL MOVE UP OR DOWN THE ORDER. ALSO RECORD THE TASKS OLD POSITION
                                        if ($projectTasks[$i]['task_id'] == $taskId) {
                                            foreach ($projectTasks[$i] as $column => $oldColumnValue) {
                                                switch ($column) {
                                                    case 'is_enabled':
                                                        if ($oldColumnValue != $newTaskStatus) {
                                                            $taskFieldsToUpdate['is_enabled'] = $newTaskStatus;
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
                                            if ($projectTasks[$i]['task_id'] != $taskId) {
                                                if ($directionOfProjectMovement == 'up' &&
                                                        ($projectTasks[$i]['order_in_project'] > $selectedTaskOldOrderInProject) &&
                                                        ($projectTasks[$i]['order_in_project'] <= $newOrderInProject)) {
                                                    $projectTasks[$i]['order_in_project'] --;
                                                    $projectTasks[$i]['is_altered'] = true;
                                                } else if ($directionOfProjectMovement == 'down' &&
                                                        ($projectTasks[$i]['order_in_project'] < $selectedTaskOldOrderInProject) &&
                                                        ($projectTasks[$i]['order_in_project'] >= $newOrderInProject)) {
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
                                        $updateTaskParams['taskToUpdate'] = $taskId;

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
                                                    $individualTask['is_altered'] == true &&
                                                    ($individualTask['task_id'] != $taskId ||
                                                    ($individualTask['task_id'] == $taskId && !$dataChanges))) {
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
                                } else {
                                    $invalidRequiredField['Unknown'] = "Core fields are missing from supplied data.";
                                }
                            } // END TASK UPDATE
                            else if ($projectEditSubAction == 'createNewTask') {
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
                                    if (!$databaseUpdateFailure) {
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
                                        if (!isset($updateTaskResult) || $updateTaskResult->rowCount === 0) {
                                            $databaseUpdateFailure['TaskReorderingForNewTask'] = $updateTaskQuery;
                                        }
                                    }
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
                                        'newTaskStatus' => $newTaskStatus,
                                        'newOrderInProject' => $newOrderInProject,
                                        'newDisplayTitle' => $newDisplayTitle
                                    );
                                    $updateTaskResult = run_prepared_query($DBH, $updateTaskQuery, $updateTaskParams);
                                    if (!isset($updateTaskResult) || $updateTaskResult->rowCount === 0) {
                                        $databaseUpdateFailure['InsertNewTask'] = $updateTaskQuery;
                                    } else {
                                        $taskId = $DBH->lastInsertID();
                                    }
                                }
                            }
                        }
                    } else {
                        // END ISSET REQUIRED FIELDS
                        $invalidRequiredField['Unknown'] = "Core fields are missing from supplied data.";
                    }

                    // IF THERE WERE NO UPDATE ERRORS THEN UPDATE THE USER AND SHOW THE NEW TASK METADATA.
                    if ($invalidRequiredField) {
                        $actionSummaryHTML = <<<EOL
                                <h2>Errors Detected in Input Data</h2>
                                <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will return you to the task details editing screen screen for you to update this task's details again." value="Return to the Task Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;

                    } else if (!$dataChanges) {
                        $actionSummaryHTML = <<<EOL
                                <h2>No Changes Detected</h2>
                                <p>No change in the task data has been detected. The database has not been altered.</p>

EOL;
                    } else if ($databaseUpdateFailure) {
                        $projectUpdateErrorHTML = <<<EOL
                                <h2>Update Failed</h2>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will return you to the task details editing screen screen for you to update this task's details again." value="Return to the Task Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;

                    } else {
                        $actionSummaryHTML = <<<EOL
                                <h2>Update Successful</h2>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
                    }



                    if (!$databaseUpdateFailure && !$invalidRequiredField) {

                        $summaryQuery = "SELECT * FROM task_metadata WHERE task_id = :taskId";
                        $summaryParams['taskId'] = $taskId;
                        $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                        $dbTaskMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                        if ($dbTaskMetadata['is_enabled'] == 1) {
                            $dbTaskStatusText = 'Enabled';
                        } else {
                            $dbTaskStatusText = '<span class="redHighlight">Disabled</span>';
                        }

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
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will return you to the task details editing screen screen for you to update this task's details again." value="Return to the Task Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
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
                    if (isset($_POST['projectEditSubAction']) &&
                            isset($_POST['newGroupName']) &&
                            isset($_POST['newGroupDescription']) &&
                            isset($_POST['newDisplayText']) &&
                            isset($_POST['newGroupContainsGroupsStatus']) &&
                            isset($_POST['newGroupWidth']) &&
                            isset($_POST['newGroupBorderStatus']) &&
                            isset($_POST['newGroupColorStatus']) &&
                            ($_POST['newGroupColorStatus'] == 0 || ($_POST['newGroupColorStatus'] == 1 && isset($_POST['newGroupColor']))) &&
                            isset($_POST['newGroupOrder']) &&
                            isset($_POST['newGroupStatus']) &&
                            (isset($_POST['newParentGroupId']) || isset($_POST['newParentTaskId']))) {


                        $invalidRequiredField = FALSE;
                        $dataChanges = FALSE;
                        $databaseUpdateFailure = FALSE;

                        if ($_POST['projectEditSubAction'] == 'updateExistingGroup' || $_POST['projectEditSubAction'] == 'createNewGroup') {
                            $projectEditSubAction = $_POST['projectEditSubAction'];
                        } else {
                            $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                        }

                        if ($_POST['newGroupStatus'] == 0 || $_POST['newGroupStatus'] == 1) {
                            $newGroupStatus = $_POST['newGroupStatus'];
                        } else {
                            $invalidRequiredField['newGroupStatus'] = $_POST['newGroupStatus'];
                        }

                        if ($_POST['newGroupContainsGroupsStatus'] == 0 || $_POST['newGroupContainsGroupsStatus'] == 1) {
                            $newGroupContainsGroupsStatus = $_POST['newGroupContainsGroupsStatus'];
                        } else {
                            $invalidRequiredField['newGroupContainsGroupsStatus'] = $_POST['newGroupContainsGroupsStatus'];
                        }

                        if (!empty($_POST['newGroupName'])) {
                            $newGroupName = htmlspecialchars($_POST['newGroupName']);
                        } else {
                            $invalidRequiredField['newGroupName'] = $_POST['newGroupName'];
                        }

                        $newGroupDescription = htmlspecialchars($_POST['newGroupDescription']);

                        if (!empty($_POST['newDisplayText'])) {
                            $newGroupDisplayText = htmlspecialchars($_POST['newDisplayText']);
                        } else {
                            $invalidRequiredField['newDisplayText'] = $_POST['newDisplayText'];
                        }

                        settype($_POST['newGroupWidth'], 'integer');
                        if (empty($_POST['newGroupWidth'])) {
                            $newGroupWidth = 0;
                        } else {
                            $newGroupWidth = $_POST['newGroupWidth'];
                        }

                        if ($_POST['newGroupBorderStatus'] == 0 || $_POST['newGroupBorderStatus'] == 1) {
                            $newGroupBorderStatus = $_POST['newGroupBorderStatus'];
                        } else {
                            $invalidRequiredField['newGroupBorderStatus'] = $_POST['newGroupBorderStatus'];
                        }

                        if ($_POST['newGroupColorStatus'] == 1) {
                            $newGroupColor = trim($_POST['newGroupColor'], "#");
                            $hexPattern = '/^([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?$/';
                            if (!preg_match($hexPattern, $newGroupColor)) {
                                $invalidRequiredField['newGroupColor'] = $newGroupColor;
                                $newGroupColor = '';
                            }
                        } else if ($_POST['newGroupColorStatus'] == 0) {
                            $newGroupColor = '';
                        } else {
                            $invalidRequiredField['newGroupColorStatus'] = $_POST['newGroupColorStatus'];
                        }

                        settype($_POST['newGroupOrder'], 'integer');
                        if (!empty($_POST['newGroupOrder'])) {
                            $newGroupOrderInParent = $_POST['newGroupOrder'];
                        } else {
                            $invalidRequiredField['newGroupOrder'] = $_POST['newGroupOrder'];
                        }

                        if (isset($_POST['newParentTaskId'])) {
                            $newGroupParentType = 'task';
                            settype($_POST['newParentTaskId'], 'integer');
                            if (!empty($_POST['newParentTaskId'])) {
                                $newGroupParentId = $_POST['newParentTaskId'];
                            } else {
                                $invalidRequiredField['newParentTaskId'] = $_POST['newParentTaskId'];
                            }
                        } else {
                            $newGroupParentType = 'group';
                            settype($_POST['newParentGroupId'], 'integer');
                            if (!empty($_POST['newParentGroupId'])) {
                                $newGroupParentId = $_POST['newParentGroupId'];
                            } else {
                                $invalidRequiredField['newParentGroupId'] = $_POST['newParentGroupId'];
                            }
                        }



// IF A GROUP_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingGroup
                        if (isset($_POST['groupId']) &&
                                isset($_POST['oldGroupParentType']) &&
                                isset($_POST['oldGroupParentId']) &&
                                isset($_POST['oldGroupOrderInParent']) &&
                                $projectEditSubAction == 'updateExistingGroup') {

                            settype($_POST['groupId'], 'integer');
                            if (!empty($_POST['groupId'])) {
                                $groupId = $_POST['groupId'];
                            } else {
                                $invalidRequiredField['groupId'] = $_POST['groupId'];
                            }

                            if ($_POST['oldGroupParentType'] == 'group' || $_POST['oldGroupParentType'] == 'task') {
                                $oldGroupParentType = $_POST['oldGroupParentType'];
                            } else {
                                $invalidRequiredField['oldGroupParentType'] = $_POST['oldGroupParentType'];
                            }

                            settype($_POST['oldGroupParentId'], 'integer');
                            if (!empty($_POST['oldGroupParentId'])) {
                                $oldGroupParentId = $_POST['oldGroupParentId'];
                            } else {
                                $invalidRequiredField['oldGroupParentId'] = $_POST['oldGroupParentId'];
                            }

                            settype($_POST['oldGroupOrderInParent'], 'integer');
                            if (!empty($_POST['oldGroupOrderInParent'])) {
                                $oldGroupOrderInParent = $_POST['oldGroupOrderInParent'];
                            } else {
                                $invalidRequiredField['oldGroupOrderInParent'] = $_POST['oldGroupOrderInParent'];
                            }

                            $groupMetadata = retrieve_entity_metadata($DBH, $groupId, 'groups');

                            $oldGroupStatus = $groupMetadata['is_enabled'];
                            $oldGroupContainsGroupsStatus = $groupMetadata['contains_groups'];
                            $oldGroupName = $groupMetadata['name'];
                            $oldGroupDescription = $groupMetadata['description'];
                            $oldGroupDisplayText = $groupMetadata['display_text'];
                            $oldGroupWidth = $groupMetadata['force_width'];
                            $oldGroupBorderStatus = $groupMetadata['has_border'];
                            $oldGroupColor = $groupMetadata['has_color'];


                            if (!$invalidRequiredField) {
                                $groupFieldsToUpdate = array();
                                if ($oldGroupStatus != $newGroupStatus) {
                                    $groupFieldsToUpdate['is_enabled'] = $newGroupStatus;
                                }

                                if ($oldGroupContainsGroupsStatus != $newGroupContainsGroupsStatus) {
                                    $groupHasContents = groupContentsCheck($DBH, $groupId);
                                    if (!$groupHasContents) {
                                        $groupFieldsToUpdate['contains_groups'] = $newGroupContainsGroupsStatus;
                                    } else {
                                        $updateError = "You cannot change the type of contents a groups can hold unless it is already empty. "
                                                . "Move all the contents of the group to another group or task and then try to change the content type again.";
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
                                    $groupFieldsToUpdate['has_border'] = $newGroupBorderStatus;
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
                                    $groupUpdateFieldsQuery .= " WHERE tag_group_id = $groupId LIMIT 1";
                                    $groupUpdateResult = run_prepared_query($DBH, $groupUpdateFieldsQuery, $groupUpdateFieldsParams);
                                    if ($groupUpdateResult->rowCount() == 1) {
                                    } else {
                                        $databaseUpdateFailure['Column Updates'] = 'Failed';
                                    }
                                }





////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Moving to a new container
                                if (($newGroupParentType != $oldGroupParentType) || ($newGroupParentId != $oldGroupParentId)) {
                                    $dataChanges = TRUE;
//////////////////////////////////////////////////////////////////////////////
// New Container Reordering
                                    $newContainer = true;
                                    if ($newGroupParentType == 'task') {
                                        $newParentContainerOrderQuery = "SELECT id AS db_id, task_id AS parent_id, tag_group_id AS child_id, order_in_task AS order_number FROM task_contents WHERE task_id = :parentId ORDER BY order_number";
                                    } else {
                                        $newParentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number FROM tag_group_contents WHERE tag_group_id = :parentId ORDER BY order_number";
                                    }

                                    $newParentContainerOrderParams['parentId'] = $newGroupParentId;
                                    $newParentContainerOrderResult = run_prepared_query($DBH, $newParentContainerOrderQuery, $newParentContainerOrderParams);
                                    $newParentContainerOrder = $newParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                                    $numberOfSiblings = count($newParentContainerOrder);

                                    $resequencingNumber = 1;
                                    foreach ($newParentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] != $resequencingNumber) {
                                            $individualGroup['order_number'] = $resequencingNumber;
                                        }
                                        $resequencingNumber++;
                                    }

                                    foreach ($newParentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] >= $newGroupOrderInParent) {
                                        }
                                    }

                                    /////////////////////////////////////////////////////////////////////////////
                                    // Old Container Reordering
                                    $newContainer = true;
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
                                        if ($oldParentContainerOrder[$i]['child_id'] == $groupId) {
                                            unset($oldParentContainerOrder[$i]);
                                        }
                                    }

                                    $resequencingNumber = 1;
                                    foreach ($oldParentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] != $resequencingNumber) {
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
                                                        . "WHERE tag_group_id = $groupId "
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
                                                    'groupId' => $groupId,
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
                                                    'groupId' => $groupId,
                                                    'newGroupOrderInParent' => $newGroupOrderInParent
                                                );
                                                $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                                if ($groupInsertResult->rowCount() == 1) {
                                                    $groupDeleteQuery = "DELETE FROM task_contents "
                                                            . "WHERE tag_group_id = :groupId "
                                                            . "LIMIT 1";
                                                    $groupDeleteParams['groupId'] = $groupId;
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
                                                    'groupId' => $groupId,
                                                    'newGroupOrderInParent' => $newGroupOrderInParent
                                                );
                                                $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                                if ($groupInsertResult->rowCount() == 1) {
                                                    $groupDeleteQuery = "DELETE FROM tag_group_contents "
                                                            . "WHERE tag_id = :groupId AND tag_group_id = :oldGroupParentId "
                                                            . "LIMIT 1";
                                                    $groupDeleteParams = array(
                                                        'groupId' => $groupId,
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
                                        $parentContainerOrderQuery = "SELECT id AS db_id, task_id AS parent_id, tag_group_id AS child_id, order_in_task AS order_number FROM task_contents WHERE task_id = :parentId ORDER BY order_number";
                                    } else {
                                        $parentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number FROM tag_group_contents WHERE tag_group_id = :parentId ORDER BY order_number";
                                    }
                                    $parentContainerOrderParams['parentId'] = $oldGroupParentId;
                                    $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                                    $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                                    $numberOfSiblings = count($parentContainerOrder);

                                    $resequencingNumber = 1;
                                    $newParentResequenced = false;
                                    $groupOrderChanged = false;
                                    foreach ($parentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] != $resequencingNumber) {
                                            $dataChanges = TRUE;
                                            $individualGroup['order_number'] = $resequencingNumber;
                                            $newParentResequenced = true;
                                        }
                                        if ($individualGroup['child_id'] == $groupId && $individualGroup['order_number'] != $newGroupOrderInParent) {
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
                                            if ($groupOrderChanged == 'up' && $individualGroup['child_id'] != $groupId) {
                                                if ($individualGroup['order_number'] > $oldGroupOrderInParent && $individualGroup['order_number'] <= $newGroupOrderInParent) {
                                                    $individualGroup['order_number'] --;
                                                }
                                            } elseif ($groupOrderChanged == 'down' && $individualGroup['child_id'] != $groupId) {
                                                if ($individualGroup['order_number'] < $oldGroupOrderInParent && $individualGroup['order_number'] >= $newGroupOrderInParent) {
                                                    $individualGroup['order_number'] ++;
                                                }
                                            }
                                            if ($individualGroup['child_id'] == $groupId) {
                                                $individualGroup['order_number'] = $newGroupOrderInParent;
                                                $newParentResequenced = true;
                                            }
                                        }
                                    }

                                    if ($newParentResequenced) {
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
                                    } else {
                                    }
                                }
                            } // End !InvalidRequiredField
                            else {
                                $updateError = "One or more of the required fields necessary to update the group contained invalid or non-existant data.";
                            } // End !InvalidRequiredField ELSE
                        } else if ($projectEditSubAction == 'createNewGroup') {
                            $dataChanges = TRUE;

                            settype($_POST['projectId'], 'integer');
                            if (!empty($_POST['projectId'])) {
                                $projectId = $_POST['projectId'];
                            } else {
                                $invalidRequiredField['projectId'] = $_POST['projectId'];
                            }

                            if (!$invalidRequiredField) {

                                //////////////////////////////////////////////////////////////////////////////
                                // Insert new group into tag_group_metadata
                                $newGroupInsertQuery = "INSERT INTO tag_group_metadata "
                                        . "(project_id, is_enabled, contains_groups, name, description, display_text, force_width, has_border, has_color) "
                                        . "VALUES (:projectId, :isEnabled, :containsGroups, :name, :description, :displayText, :forceWidth, :hasBorder, :hasColor)";
                                $newGroupInsertParams = array(
                                    'projectId' => $projectId,
                                    'isEnabled' => $newGroupStatus,
                                    'containsGroups' => $newGroupContainsGroupsStatus,
                                    'name' => $newGroupName,
                                    'description' => $newGroupDescription,
                                    'displayText' => $newGroupDisplayText,
                                    'forceWidth' => $newGroupWidth,
                                    'hasBorder' => $newGroupBorderStatus,
                                    'hasColor' => $newGroupColor,
                                );
                                $newGroupInsertResult = run_prepared_query($DBH, $newGroupInsertQuery, $newGroupInsertParams);

                                //////////////////////////////////////////////////////////////////////////////
                                // If the insert was successful then reorder the parent container content list
                                // to make room for the new group and insert it in the parent container content list.
                                if ($newGroupInsertResult->rowCount() == 1) {
                                    $groupId = $DBH->lastInsertID();



                                    // Find the current contents of the parent container.
                                    if ($newGroupParentType == 'task') {
                                        $parentContainerOrderQuery = "SELECT id AS db_id, task_id AS parent_id, tag_group_id AS child_id, order_in_task AS order_number FROM task_contents WHERE task_id = :parentId ORDER BY order_number";
                                    } else {
                                        $parentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number FROM tag_group_contents WHERE tag_group_id = :parentId ORDER BY order_number";
                                    }

                                    $parentContainerOrderParams['parentId'] = $newGroupParentId;
                                    $parentContainerOrderResult = run_prepared_query($DBH, $parentContainerOrderQuery, $parentContainerOrderParams);
                                    $parentContainerOrder = $parentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                                    $numberOfSiblings = count($parentContainerOrder);

                                    // Check for sequential numbering of the existing contents. Resequence sequentially if necessary.
                                    $resequencingNumber = 1;
                                    foreach ($parentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] != $resequencingNumber) {
                                            $individualGroup['order_number'] = $resequencingNumber;
                                        }
                                        $resequencingNumber++;
                                    }

                                    // Increment parent contents of equal or higher order than the new group by one number to make room.
                                    foreach ($parentContainerOrder as &$individualGroup) {
                                        if ($individualGroup['order_number'] >= $newGroupOrderInParent) {
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
                                    if ($parentContainerOrderResult) {
                                        // If the update of the parent contents order was successfull then insert the new
                                        // group into the parent content list
                                        if ($newGroupParentType == 'task') {
                                            $groupInsertQuery = "INSERT INTO task_contents "
                                                    . "(task_id, tag_group_id, order_in_task) "
                                                    . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                            $groupInsertParams = array(
                                                'newGroupParentId' => $newGroupParentId,
                                                'groupId' => $groupId,
                                                'newGroupOrderInParent' => $newGroupOrderInParent
                                            );
                                            $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                        } else {
                                            $groupInsertQuery = "INSERT INTO tag_group_contents "
                                                    . "(tag_group_id, tag_id, order_in_group) "
                                                    . "VALUES (:newGroupParentId, :groupId, :newGroupOrderInParent)";
                                            $groupInsertParams = array(
                                                'newGroupParentId' => $newGroupParentId,
                                                'groupId' => $groupId,
                                                'newGroupOrderInParent' => $newGroupOrderInParent
                                            );
                                            $groupInsertResult = run_prepared_query($DBH, $groupInsertQuery, $groupInsertParams);
                                        }
                                        if ($groupInsertResult->rowCount() != 1) {
                                            $databaseUpdateFailure['NewGroupParentContainerOrderGroupInsertion'] = $groupInsertQuery;
                                        }
                                    } else {
                                        $databaseUpdateFailure['NewGroupParentReordering'] = $parentContainerUpdateQuery;
                                    }
                                } else {
                                    $databaseUpdateFailure['InsertNewGroupMetaData'] = $newGroupInsertQuery;
                                }
                            }
                        }
                    } else {
                        $invalidRequiredField['Unknown'] = "Core fields are missing from supplied data.";
                    }

                    if ($invalidRequiredField) {
                        $actionSummaryHTML = <<<EOL
                                <h2>Errors Detected in Input Data</h2>
                                <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;

                    } else if (!$dataChanges) {
                        $actionSummaryHTML = <<<EOL
                                <h2>No Changes Detected</h2>
                                <p>No change in the tag detail data has been detected. The database has not been altered.</p>

EOL;
                    } else if ($databaseUpdateFailure) {
                        $projectUpdateErrorHTML = <<<EOL
                                <h2>Update Failed</h2>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToGroupDetails" title="This will return you to the group details editing screen screen for you to update this group's details again." value="Return to the Tag Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;

                    } else {
                        $actionSummaryHTML = <<<EOL
                                <h2>Update Successful</h2>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
                    }

                    if (!$databaseUpdateFailure && !$invalidRequiredField) {
                        // CREATE HTML TO SHOW THE NEW TASK STATUS

                        $parentIsTask = TRUE;
                        $summaryQuery = "SELECT * FROM tag_group_metadata WHERE tag_group_id = :groupId";
                        $summaryParams['groupId'] = $groupId;
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


                        if ($dbGroupMetadata['contains_groups'] == 0) {
                            $dbGroupContents = "Tags";
                        } else {
                            $dbGroupContents = "Groups";
                        }

                        if ($dbGroupMetadata['force_width'] == 0) {
                            $dbGroupWidth = "Set Automatically";
                        } else {
                            $dbGroupWidth = $dbGroupMetadata['force_width'] . ' px';
                        }

                        if ($dbGroupMetadata['has_border'] == 0) {
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

                        if ($dbGroupMetadata['is_enabled'] == 1) {
                            $dbGroupStatusText = 'Enabled';
                        } else {
                            $dbGroupStatusText = '<span class="redHighlight">Disabled</span>';
                        }

                        $actionSummaryHTML .= <<<EOL
                                <h3>Group Summary</h3>
                                <table id="updateSummaryTable">
                                    <tbody>
                                        <tr>
                                            <td>Group Admin Name:</td>
                                            <td class="userData">{$dbGroupMetadata['name']}</td>
                                        </tr>
                                        <tr>
                                            <td>Group Admin Description:</td>
                                            <td class="userData">{$dbGroupMetadata['description']}</td>
                                        </tr>
                                        <tr>
                                            <td>Group Display Text:</td>
                                            <td class="userData">{$dbGroupMetadata['display_text']}</td>
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
                                            <td class="userData">{$dbParentMetadata['name']}</td>
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
                                <div class = "updateFormSubmissionControls"><hr>
                                    <input type = "button" class = "clickableButton" id = "returnToTagSelection" title = "This will return you to the tag selection screen for you to choose another tag to edit or create." value = "Return to the Group Selection Screen">
                                    <input type = "button" class = "clickableButton" id = "returnToActionSelection" title = "This will return you to the Action Selection screen for you to choose another project editing activity." value = "Return To Action Selection Screen">
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
                    if (isset($_POST['projectEditSubAction']) &&
                            isset($_POST['newTagName']) &&
                            isset($_POST['newTagDescription']) &&
                            isset($_POST['newTagDisplayText']) &&
                            isset($_POST['newTagToolTipText']) &&
                            isset($_POST['newTagToolTipImage']) &&
                            isset($_POST['newTagType']) &&
                            ($_POST['newTagType'] == 0 || $_POST['newTagType'] == 2 || ($_POST['newTagType'] == 1 && isset($_POST['newTagRadioGroupName']))) &&
                            isset($_POST['newParentId']) &&
                            isset($_POST['newTagOrder']) &&
                            isset($_POST['newTagStatus'])) {

                        $invalidRequiredField = FALSE;
                        $dataChanges = FALSE;
                        $databaseUpdateFailure = FALSE;

                        if ($_POST['projectEditSubAction'] == 'updateExistingTag' || $_POST['projectEditSubAction'] == 'createNewTag') {
                            $projectEditSubAction = $_POST['projectEditSubAction'];
                        } else {
                            $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                        }

                        if (!empty($_POST['newTagName'])) {
                            $newTagName = htmlspecialchars($_POST['newTagName']);
                        } else {
                            $invalidRequiredField['newTagName'] = $_POST['newTagName'];
                        }

                        $newTagDescription = htmlspecialchars($_POST['newTagDescription']);

                        if (!empty($_POST['newTagDisplayText'])) {
                            $newTagDisplayText = htmlspecialchars($_POST['newTagDisplayText']);
                        } else {
                            $invalidRequiredField['newTagDisplayText'] = $_POST['newTagDisplayText'];
                        }

                        $newTagToolTipText = htmlspecialchars($_POST['newTagToolTipText']);
                        $newTagToolTipImage = htmlspecialchars($_POST['newTagToolTipImage']);

                        switch ($_POST['newTagType']) {
                            case 0:
                                $newCommentBoxFlag = 0;
                                $newRadioButtonFlag = 0;
                                $newRadioButtonGroupName = "";
                                break;
                            case 1:
                                if (isset($_POST['newTagRadioGroupName']) && !empty($_POST['newTagRadioGroupName'])) {
                                    $newCommentBoxFlag = 0;
                                    $newRadioButtonFlag = 1;
                                    $newRadioButtonGroupName = htmlspecialchars($_POST['newTagRadioGroupName']);
                                } else {
                                    if (!isset($_POST['newTagRadioGroupName'])) {
                                        $invalidRequiredField['newTagRadioGroupName'] = "Field doesn't exist!";
                                    } else {
                                        $invalidRequiredField['newTagRadioGroupName'] = "Field Empty!";
                                    }
                                }
                                break;
                            case 2:
                                $newCommentBoxFlag = 1;
                                $newRadioButtonFlag = 0;
                                $newRadioButtonGroupName = "";
                                break;
                            default:
                                $invalidRequiredField['newTagType'] = $_POST['newTagType'];
                                break;
                        }

                        settype($_POST['newParentId'], 'integer');
                        if (!empty($_POST['newParentId'])) {
                            $newParentId = $_POST['newParentId'];
                        } else {
                            $invalidRequiredField['newParentId'] = $_POST['newParentId'];
                        }

                        settype($_POST['newTagOrder'], 'integer');
                        if (!empty($_POST['newTagOrder'])) {
                            $newTagPosition = $_POST['newTagOrder'];
                        } else {
                            $invalidRequiredField['newTagOrder'] = $_POST['newTagOrder'];
                        }

                        if ($_POST['newTagStatus'] == 0 || $_POST['newTagStatus'] == 1) {
                            $newTagStatus = $_POST['newTagStatus'];
                        } else {
                            $invalidRequiredField['newTagStatus'] = $_POST['newTagStatus'];
                        }


                        // IF A TAG_ID HAS BEEN SUPPLIED AND projectEditSubAction IS updateExistingTag
                        if (isset($_POST['tagId']) &&
                                isset($_POST['oldParentId']) &&
                                isset($_POST['oldTagOrder']) &&
                                $projectEditSubAction == 'updateExistingTag') {

                            settype($_POST['tagId'], 'integer');
                            if (!empty($_POST['tagId'])) {
                                $tagId = $_POST['tagId'];
                            } else {
                                $updateError = "The supplied tag Id is invalid.";
                                $invalidRequiredField['tagId'] = $_POST['tagId'];
                            }


                            settype($_POST['oldParentId'], 'integer');
                            if (!empty($_POST['oldParentId'])) {
                                $oldParentId = $_POST['oldParentId'];
                            } else {
                                $invalidRequiredField['oldParentId'] = $_POST['oldParentId'];
                            }

                            settype($_POST['oldTagOrder'], 'integer');
                            if (!empty($_POST['oldTagOrder'])) {
                                $oldTagPosition = $_POST['oldTagOrder'];
                            } else {
                                $invalidRequiredField['oldTagOrder'] = $_POST['oldTagOrder'];
                            }

                            if (!$invalidRequiredField) {
                                $tagMetadata = retrieve_entity_metadata($DBH, $tagId, 'tags');

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
                                    $tagFieldsToUpdate['is_enabled'] = $newTagStatus;
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

                                if ($oldTagTooltipImage != $newTagToolTipImage) {
                                    if (!empty($newTagToolTipImage)) {
                                        $toolTipImagesPath = "images/projects/$projectId/tooltips/";
                                        if (file_exists($toolTipImagesPath . $newTagToolTipImage)) {
                                            $imageMetadata = getimagesize($toolTipImagesPath . $newTagToolTipImage);
                                            if ($imageMetadata) {
                                                $tagFieldsToUpdate['tooltip_image'] = $newTagToolTipImage;
                                                $tagFieldsToUpdate['tooltip_image_width'] = $imageMetadata[0];
                                                $tagFieldsToUpdate['tooltip_image_height'] = $imageMetadata[1];
                                            } else {
                                                $invalidRequiredField['newTagToolTipImage'] = 'The image dimensions could not be read.';
                                            }
                                        } else {
                                            $invalidRequiredField['newTagToolTipImage'] = "The image could not be found.";
                                        }
                                    } else {
                                        $tagFieldsToUpdate['tooltip_image'] = '';
                                        $tagFieldsToUpdate['tooltip_image_width'] = 0;
                                        $tagFieldsToUpdate['tooltip_image_height'] = 0;
                                    }
                                }

                                if ($oldCommentBoxFlag != $newCommentBoxFlag) {
                                    $tagFieldsToUpdate['is_comment_box'] = $newCommentBoxFlag;
                                }

                                if ($oldRadioButtonFlag != $newRadioButtonFlag) {
                                    $tagFieldsToUpdate['is_radio_button'] = $newRadioButtonFlag;
                                }

                                if ($oldRadioButtonGroupName != $newRadioButtonGroupName) {
                                    $tagFieldsToUpdate['radio_button_group'] = $newRadioButtonGroupName;
                                }


                                if (!$invalidRequiredField) {

                                    if (count($tagFieldsToUpdate) > 0) {
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
                                        $tagUpdateFieldsQuery .= " WHERE tag_id = $tagId LIMIT 1";

                                        $tagUpdateResult = run_prepared_query($DBH, $tagUpdateFieldsQuery, $tagUpdateFieldsParams);
                                        if ($tagUpdateResult->rowCount() == 1) {
                                        } else {
                                            $databaseUpdateFailure['Column Updates'] = 'Failed';
                                        }
                                    } else {
                                    }





////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// Moving to a new container
                                    if ($newParentId != $oldParentId) {
                                        $dataChanges = TRUE;
//////////////////////////////////////////////////////////////////////////////
// New Container Reordering
                                        $newContainer = true;
                                        $resequencing = FALSE;
                                        $reordering = FALSE;
                                        $newParentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number "
                                                . "FROM tag_group_contents "
                                                . "WHERE tag_group_id = :parentId "
                                                . "ORDER BY order_number";
                                        $newParentContainerOrderParams['parentId'] = $newParentId;
                                        $newParentContainerOrderResult = run_prepared_query($DBH, $newParentContainerOrderQuery, $newParentContainerOrderParams);
                                        $newParentContainerOrder = $newParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                                        $numberOfSiblings = count($newParentContainerOrder);

                                        $resequencingNumber = 1;
                                        foreach ($newParentContainerOrder as &$individualTag) {
                                            if ($individualTag['order_number'] != $resequencingNumber) {
                                                $individualTag['order_number'] = $resequencingNumber;
                                                $resequencing = TRUE;
                                            }
                                            $resequencingNumber++;
                                        }

                                        foreach ($newParentContainerOrder as &$individualTag) {
                                            if ($individualTag['order_number'] >= $newTagPosition) {
                                                $individualTag['order_number'] ++;
                                                $reordering = TRUE;
                                            }
                                        }

                                        /////////////////////////////////////////////////////////////////////////////
                                        // Old Container Reordering

                                        $oldParentContainerOrderQuery = "SELECT id AS db_id, tag_group_id AS parent_id, tag_id AS child_id, order_in_group AS order_number "
                                                . "FROM tag_group_contents "
                                                . "WHERE tag_group_id = :parentId "
                                                . "ORDER BY order_number";
                                        $oldParentContainerOrderParams['parentId'] = $oldParentId;
                                        $oldParentContainerOrderResult = run_prepared_query($DBH, $oldParentContainerOrderQuery, $oldParentContainerOrderParams);
                                        $oldParentContainerOrder = $oldParentContainerOrderResult->fetchAll(PDO::FETCH_ASSOC);
                                        $numberOfSiblings = count($oldParentContainerOrder);

                                        for ($i = 0; $i < $numberOfSiblings; $i++) {
                                            if ($oldParentContainerOrder[$i]['child_id'] == $tagId) {
                                                unset($oldParentContainerOrder[$i]);
                                            }
                                        }

                                        $resequencingNumber = 1;
                                        foreach ($oldParentContainerOrder as &$individualTag) {
                                            if ($individualTag['order_number'] != $resequencingNumber) {
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
                                                    'newTagPosition' => $newTagPosition,
                                                    'tagId' => $tagId,
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
                                    } else {
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
                                            if ($individualTag['order_number'] != $resequencingNumber) {
                                                $dataChanges = TRUE;
//                                                print $individualTag['order_number'] . ' becomes ' . $resequencingNumber;
                                                $individualTag['order_number'] = $resequencingNumber;
                                                $resequencing = true;
                                            }
                                            if ($individualTag['child_id'] == $tagId && $individualTag['order_number'] != $newTagPosition) {
                                                $dataChanges = TRUE;
                                                if ($newTagPosition > $oldTagPosition) {
                                                    $groupOrderChanged = 'up';
                                                } else {
                                                    $groupOrderChanged = 'down';
                                                }
                                            }
                                            $resequencingNumber++;
                                        }

                                        if ($groupOrderChanged) {
                                            foreach ($parentContainerOrder as &$individualTag) {
                                                if ($groupOrderChanged == 'up' && $individualTag['child_id'] != $tagId) {
                                                    if ($individualTag['order_number'] > $oldTagPosition && $individualTag['order_number'] <= $newTagPosition) {
                                                        $individualTag['order_number'] --;
                                                    }
                                                } elseif ($groupOrderChanged == 'down' && $individualTag['child_id'] != $tagId) {
                                                    if ($individualTag['order_number'] < $oldTagPosition && $individualTag['order_number'] >= $newTagPosition) {
                                                        $individualTag['order_number'] ++;
                                                    }
                                                }
                                                if ($individualTag['child_id'] == $tagId) {
                                                    $individualTag['order_number'] = $newTagPosition;
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
                            } // End !InvalidRequiredField
                            if ($invalidRequiredField) {
                                $updateError = "One or more of the required fields necessary to update the group contained invalid or non-existant data.";

                            } // End invalidRequiredField IF
                            //////////////////////////////////////////////////////////////////////////////////
                            //////////////////////////////////////////////////////////////////////////////////
                            //////////////////////////////////////////////////////////////////////////////////
                            //////////////////////////////////////////////////////////////////////////////////
                            //////////////////////////////////////////////////////////////////////////////////
                            //////////////////////////////////////////////////////////////////////////////////
                        } else if ($projectEditSubAction == 'createNewTag') {
                            $dataChanges = TRUE;

                            settype($_POST['projectId'], 'integer');
                            if (!empty($_POST['projectId'])) {
                                $projectId = $_POST['projectId'];
                            } else {
                                $invalidRequiredField['projectId'] = $_POST['projectId'];
                            }

                            if (!empty($newTagToolTipImage)) {
                                $toolTipImagesPath = "images/projects/$projectId/tooltips/";
                                if (file_exists($toolTipImagesPath . $newTagToolTipImage)) {
                                    $imageMetadata = getimagesize($toolTipImagesPath . $newTagToolTipImage);
                                    if ($imageMetadata) {
                                        $newTooltipImageWidth = $imageMetadata[0];
                                        $newTooltipImageHeight = $imageMetadata[1];
                                    } else {
                                        $invalidRequiredField['newTagToolTipImage'] = 'The image dimensions could not be read.';
                                    }
                                } else {
                                    $invalidRequiredField['newTagToolTipImage'] = "The image could not be found. {$toolTipImagesPath}{$newTagToolTipImage}";
                                }
                            } else {
                                $newTooltipImageWidth = 0;
                                $newTooltipImageHeight = 0;
                            }


                            if (!$invalidRequiredField) {

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
                                    'radioButtonGroup' => $newRadioButtonGroupName,
                                    'name' => $newTagName,
                                    'description' => $newTagDescription,
                                    'displayText' => $newTagDisplayText,
                                    'displayImage' => '',
                                    'tooltipText' => $newTagToolTipText,
                                    'tooltipImage' => $newTagToolTipImage,
                                    'tooltipImageWidth' => $newTooltipImageWidth,
                                    'tooltipImageHeight' => $newTooltipImageHeight
                                );
                                $newTagInsertResult = run_prepared_query($DBH, $newTagInsertQuery, $newTagInsertParams);

                                //////////////////////////////////////////////////////////////////////////////
                                // If the insert was successful then reorder the parent container content list
                                // to make room for the new group and insert it in the parent container content list.
                                if ($newTagInsertResult->rowCount() == 1) {
                                    $tagId = $DBH->lastInsertID();

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
                                    // Check for sequential numbering of the existing contents. Resequence sequentially if necessary.
                                    $resequencingNumber = 1;
                                    foreach ($parentContainerOrder as &$individualTag) {
                                        if ($individualTag['order_number'] != $resequencingNumber) {
                                            $individualTag['order_number'] = $resequencingNumber;
                                            $resequenced = true;
                                        }
                                        $resequencingNumber++;
                                    }

                                    // Increment parent contents of equal or higher order than the new group by one number to make room.
                                    foreach ($parentContainerOrder as &$individualTag) {
                                        if ($individualTag['order_number'] >= $newTagPosition) {
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
                                    if ($parentContainerOrderResult) {
                                        // If the update of the parent contents order was successfull then insert the new
                                        // group into the parent content list
                                        $tagInsertQuery = "INSERT INTO tag_group_contents "
                                                . "(tag_group_id, tag_id, order_in_group) "
                                                . "VALUES (:newParentId, :tagId, :newTagPosition)";
                                        $tagInsertParams = array(
                                            'newParentId' => $newParentId,
                                            'tagId' => $tagId,
                                            'newTagPosition' => $newTagPosition
                                        );
                                        $tagInsertResult = run_prepared_query($DBH, $tagInsertQuery, $tagInsertParams);

                                        if ($tagInsertResult->rowCount() == 1) {
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
                        }
                    } else {
                        $invalidRequiredField['Unknown'] = "Core fields are missing from supplied data.";
                    }
                    if ($invalidRequiredField) {
                        $actionSummaryHTML = <<<EOL
                                <h2>Errors Detected in Input Data</h2>
                                <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;
                    } else if (!$dataChanges) {
                        $actionSummaryHTML = <<<EOL
                                <h2>No Changes Detected</h2>
                                <p>No change in the tag detail data has been detected. The database has not been altered.</p>

EOL;
                    } else if ($databaseUpdateFailure) {
                        $projectUpdateErrorHTML = <<<EOL
                                <h2>Update Failed</h2>
                                <p>An unknown error occured during the database update. No changes have been made.
                                    If this problem persists please contact an iCoast System Administrator. </p>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTagDetails" title="This will return you to the tag details editing screen screen for you to update this tag's details again." value="Return to the Tag Details Editor">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will return you to the Action Selection screen for you to choose another project editing activity." value="Return To Action Selection Screen">
                                </div>

EOL;
                    } else {
                        $actionSummaryHTML = <<<EOL
                                <h2>Update Successful</h2>
                                <p>It is recommended that you now review the project in iCoast to ensure your changes are
                                    displayed correctly.</p>

EOL;
                    }

                    if (!$databaseUpdateFailure && !$invalidRequiredField) {
                        // CREATE HTML TO SHOW THE NEW TASK STATUS

                        $summaryQuery = "SELECT * FROM tags WHERE tag_id = :tagId";
                        $summaryParams['tagId'] = $tagId;
                        $summaryResult = run_prepared_query($DBH, $summaryQuery, $summaryParams);
                        $dbTagMetadata = $summaryResult->fetch(PDO::FETCH_ASSOC);

                        $summaryParentQuery = "SELECT tgm.name, tgc.order_in_group "
                                . "FROM tag_group_contents tgc "
                                . "LEFT JOIN tag_group_metadata tgm ON tgc.tag_group_id = tgm.tag_group_id "
                                . "WHERE tgc.tag_id = :tagId AND tgm.contains_groups = 0";
                        $summaryParentResults = run_prepared_query($DBH, $summaryParentQuery, $summaryParams);
                        $dbParentMetadata = $summaryParentResults->fetch(PDO::FETCH_ASSOC);
                        $ordinalPositionInGroup = ordinal_suffix($dbParentMetadata['order_in_group']);

                        if (empty($dbTagMetadata['tooltip_image'])) {
                            $dbTagTooltipImage = 'None';
                        } else {
                            $dbTagTooltipImage = $dbTagMetadata['tooltip_image'];
                        }

                        if ($dbTagMetadata['is_enabled'] == 1) {
                            $dbTagStatusText = 'Enabled';
                        } else {
                            $dbTagStatusText = '<span class="redHighlight">Disabled</span>';
                        }

                        $showRadioGroupName = FALSE;
                        if ($dbTagMetadata['is_comment_box'] == 0 && $dbTagMetadata['is_radio_button'] == 0) {
                            $dbTagType = "Multi-Select";
                        } else if ($dbTagMetadata['is_radio_button'] == 1) {
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
                                            <td class="userData">{$dbTagMetadata['name']}</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Admin Description:</td>
                                            <td class="userData">{$dbTagMetadata['description']}</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Display Text:</td>
                                            <td class="userData">{$dbTagMetadata['display_text']}</td>
                                        </tr>
                                        <tr>
                                            <td>Tag Tooltip Text:</td>
                                            <td class="userData">{$dbTagMetadata['tooltip_text']}</td>
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
                                            <td class = "userData">{$dbTagMetadata['radio_button_group']}</td>
                                        </tr>

EOL;
                        }
                        $actionSummaryHTML .= <<<EOL
                                        <tr>
                                            <td>Parent Group Name:</td>
                                            <td class = "userData">{$dbParentMetadata['name']}</td>
                                        </tr>
                                           <tr>
                                            <td>Order Position In Parent Group:</td>
                                            <td class = "userData">$ordinalPositionInGroup</td>
                                        </tr>
                                        <tr>
                                            <td>Task Status:</td>
                                            <td class = "userData">$dbTagStatusText</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class = "updateFormSubmissionControls"><hr>
                                    <input type = "button" class = "clickableButton" id = "returnToTagSelection" title = "This will return you to the tag selection screen for you to choose another tag to edit or create." value = "Return to the Tag Screen">
                                    <input type = "button" class = "clickableButton" id = "returnToActionSelection" title = "This will return you to the Action Selection screen for you to choose another project editing activity." value = "Return To Action Selection Screen">
                                </div>

EOL;
                    }

                    break; // END tags CASE
            }
        } else { // END SUCESSFUL USER UPDATE PERMISSION CHECK - if (in_array($_POST['projectId'], $userAdministeredProjects))
            $projectUpdateErrorHTML = <<<EOL
        <h2>Project Update Error</h2>
        <p>You have requested to update a project for which you do not have sufficient permission.
            To update this project please seek Project Editor access from the project owner.</p>

EOL;
        // ERROR LOGGING
        } // END FAILED USER PERMISSION CHECK - if (in_array($_POST['projectId'], $userAdministeredProjects)) ELSE
    } else { // END SUCCESSFUL CHECK OF PROJECT ID AND PROPERTY TO UPDATE CHECK - if (isset($_POST['projectId']) && isset($_POST['projectPropertyToUpdate']))
        $projectUpdateErrorHTML = <<<EOL
        <h2>Project Update Error</h2>
        <p>Required fields are either missing or contain invalid data. No changes have been made to the database. Please try again and if this problem persists contact an administrator.</p>
EOL;
        // Error logging.
    } // END FAILED CHECK OF PROJECT ID AND PROPERTY TO UPDATE CHECK -  if (isset($_POST['projectId']) && isset($_POST['projectPropertyToUpdate'])) ELSE
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
// BUILD PROJECT SELECTION HTML
//
//
// CHECK OF A PROJECT TO UPDATE HAS ALREADY BEEN SPECIFIED OR PROVIDE A CHOICE OF PROJECTS TO UPDATE
if (isset($_POST['projectId'])) {
// IF A PROJECT HAS ALREADY BEEN SELECTED CHECH THE USER PERMISSIONS AND IF SUCCESSFUL DETERMINE THE PROJECT
// METADATA AND COPY TO LOCAL VARIABLES
    $invalidRequiredField = FALSE;
    settype($_POST['projectId'], 'integer');
    if (!empty($_POST['projectId'])) {
        $projectId = $_POST['projectId'];
    } else {
        $invalidRequiredField['projectId'] = $_POST['projectId'];
    }
    // CHECK USER HAS THE PERMISSION TO EDIT THE SELECTED PROJECT
    $userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId);
    $hasProjectPermission = FALSE;
    if (!$invalidRequiredField && in_array($_POST['projectId'], $userAdministeredProjects)) {
        $hasProjectPermission = TRUE;
        $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
        $projectName = $projectMetadata['name'];
        $projectSelectHTML = <<<EOL
            <div id=chosenProjectNotificationWrapper>
                <p>Your are editing the <span class="userData">$projectName</span> project.</p>
                <form method="POST" id="projectSelectForm">
                    <input type="submit" class="clickableButton" value="Change Project">
                </form>
            </div>
EOL;
    } else { // END SUCCESSFUL CHECK OF USER PERMISSION TO EDIT PROJECT - if (in_array($_POST['projectId'], $userAdministeredProjects))
        $projectUpdateErrorHTML = <<<EOL
        <h2>Project Edit Error</h2>
        <p>The specified project was either invalid or you have requested to edit a project for which you do not have sufficient permission.
            Please check the specified project is correct and if so seek Project Editor access from the project owner to proceed.</p>
        <div class="updateFormSubmissionControls">
            <form method="POST" id="projectSelectForm">
                <input type="submit" class="clickableButton" value="Select a Different Project">
            </form>
        </div>

EOL;
    } // END FAILED CHECK OF USER PERMISSION TO EDIT PROJECT - if (in_array($_POST['projectId'], $userAdministeredProjects)) ELSE
} else {
    // DETERMINE THE LIST OF AVAILABLE/PERMISSIONED PROJECTS
    $userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
    $projectCount = count($userAdministeredProjects);
    // IF ONLY 1 PROJECT EXISTS AUTO SELECT IT FOR THE USER
    if ($projectCount == 1) {
        $projectId = $userAdministeredProjects[0]['project_id'];
        $projectName = $userAdministeredProjects[0]['name'];
        $projectSelectHTML = <<<EOL
            <div id=chosenProjectNotificationWrapper>
                <p>Your are editing the <span class="userData">$projectName</span> project.</p>
            </div>
EOL;
    } else { // END AUTO SELCTION OF A SINGLE PROJECT. BEGIN CREATION OF PROJECT SELECTION BOX. - if ($projectCount == 1)
        // BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
        // PROJECT SELECT
        $projectSelectHTML = "";
        foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
            $optionProjectId = $singeUserAdministeredProject['project_id'];
            $optionProjectName = $singeUserAdministeredProject['name'];
            $optionProjectDescription = $singeUserAdministeredProject['description'];
            $projectSelectHTML .= "<option title=\"$optionProjectDescription\" value=\"$optionProjectId\">$optionProjectName</option>";
        }
        $projectSelectHTML = <<<EOL
                <div id="projectSelectWrapper">
                    <h2>Select A Project To Edit</h2>
                    <p>Using the select box below choose the project that you wish to edit. Only projects
                        for which you have permission to edit are listed</p>
                    <form method="POST" id="projectSelectForm" autocomplete="off">
                        <div class="formFieldRow">
                            <label for="projectIdSelectBox">Project To Edit:</label>
                            <select name="projectId" id="projectIdSelectBox" class="clickableButton">
                                $projectSelectHTML;
                            </select>
                        </div>
                <div class="updateFormSubmissionControls">
                        <input type="submit" class="clickableButton" value="Select Project">
                </div>
                    </form>
                </div>

EOL;
    } // END CREATION OF PROJECT SELECTION FORM HTML - if ($projectCount == 1) ELSE
} // END if (!isset($_POST['projectId'])) ELSE
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// BUILD UPDATE ACTION HTML
//
//
// FROM THIS POINT ON THE PROJECT ID AND NAME HAS BEEN COPIED FROM $_POST TO VARIABLES
// BEGIN SELECTION OF THE PROJECT PROPERTY TO UPDATE IF IT HAS NOT BEEN SPECIFIED.
if (isset($projectId) && $hasProjectPermission && !isset($_POST['projectPropertyToUpdate'])) {
    $actionSelctionHTML = <<<EOL
            <h2>Select an Action to Perform on this Project</h2>
            <p>What would you like to edit in this project?</p>
            <form id="editActionForm" method="post" autocomplete="off">
                <input type="radio" name="projectPropertyToUpdate" value="details" id="editProjectDetails" />
                <label for="editProjectDetails" class="clickableButton editProjectAction">Project Details</label>

                <input type="radio" name="projectPropertyToUpdate" value="tasks" id="editProjectTasks" />
                <label for="editProjectTasks" class="clickableButton editProjectAction">Tasks</label>

                <input type="radio" name="projectPropertyToUpdate" value="groups" id="editProjectTagGroups" />
                <label for="editProjectTagGroups" class="clickableButton editProjectAction">Tag Groups</label>

                <input type="radio" name="projectPropertyToUpdate" value="tags" id="editProjectTags" />
                <label for="editProjectTags" class="clickableButton editProjectAction">Tags</label>

                <br>

                <input type="hidden" name="projectId" value="$projectId" />
                <div class="updateFormSubmissionControls">
                    <input type="submit" class="clickableButton" value="Select Action" />
                </div>
            </form>
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
if (isset($projectId) &&
        $hasProjectPermission &&
        isset($_POST['projectPropertyToUpdate']) &&
        !isset($_POST['editSubmitted'])) {
    $projectPropertyToUpdate = $_POST['projectPropertyToUpdate'];
    // CUSTOMIZE THE UPDATE FORM TO THE PROPERTY THAT IS TO BE UPDATED
    switch ($projectPropertyToUpdate) {
        // UPDATE THE PROJECT DETAILS
        //
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // BUILD THE PROJECT DETAILS UPDATE FORM HTML
        //
        //
        case 'details':
            // FIND THE CURRENT PROJECT DETAILS TO POPULATE THE UPDATE FIELDS WITH AND COPY TO VARIABLES
            if (!isset($projectMetadata)) {
                $projectDetailsQuery = "SELECT * FROM projects WHERE project_id = :projectId";
                $projectDetailsParams['projectId'] = $projectId;
                $projectDetailsResult = run_prepared_query($DBH, $projectDetailsQuery, $projectDetailsParams);
                $projectMetadata = $projectDetailsResult->fetch(PDO::FETCH_ASSOC);
            }
            $currentProjectName = $projectMetadata['name'];
            $currentProjectDescription = $projectMetadata['description'];
            $currentPostImageHeader = $projectMetadata['post_image_header'];
            $currentPreImageHeader = $projectMetadata['pre_image_header'];
            $currentProjectStatus = $projectMetadata['is_public'];

            $projectStatusRadioButtonHTML = <<<EOL
                            <input type="radio" id="editProjectStatusEnabled" name="newProjectStatus" value="1">
                            <label for="editProjectStatusEnabled" class="clickableButton" title="The project will be available for public viewing and photo classification.">Enabled</label>
                            <input type="radio" id="editProjectStatusDisabled" name="newProjectStatus" value="0">
                            <label for="editProjectStatusDisabled" class="clickableButton" title="The project will NOT be available for public viewing and phot classification.">Disabled</label>
EOL;
            if ($currentProjectStatus == 1) {
                $projectStatusRadioButtonHTML = str_replace('1">', '1" checked>', $projectStatusRadioButtonHTML);
            } else {
                $projectStatusRadioButtonHTML = str_replace('0">', '0" checked>', $projectStatusRadioButtonHTML);
            }

            // BUILD THE PROJECT DETAILS UPDATE FORM HTML
            $actionControlsHTML = <<<EOL
                    <h2>Edit Project Details</h2>
                    <form method="post" id="editProjectDetailsForm" autocomplete="off">
                        <div class="formFieldRow">
                            <label for="editProjectName" title="This is the text used throughout iCoast to inform the user and admins of what project they are working on. It is also used in all selection boxes where a user picks a project to work on. Keep the text here simple and short. Using a storm name such as 'Hurricane Sandy' is best. There is 50 character limit.">Project Name:</label>
                            <input type="textbox" id="editProjectName" class="clickableButton" name="newProjectName" maxlength="50" value="$currentProjectName" />
                        </div>

                        <div class="formFieldRow">
                            <label for="editDescription" title="This text is for admin reference only to help explain detasils of the project. The content of this field is not shared with standard users. 500 character limit.">Project Description:</label>
                            <textarea id="editDescription" class="clickableButton" name="newProjectDescription" maxlength="500">$currentProjectDescription</textarea>
                        </div>

                        <div class="formFieldRow">
                            <label for="editPostStormHeader" title="This text is used as part of the header text for the post storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like 'POST-STORM: After Hurricane Sandy'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">Post Storm Image Header Text:</label>
                            <input type="textbox" id="editPostStormHeader" class="clickableButton" name="newPostStormHeader" maxlength="50" value="$currentPostImageHeader" />
                        </div>

                        <div class="formFieldRow">
                            <label for="editPreStormHeader" title="This text is used as part of the header text for the pre storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like 'PRE-STORM: Before Hurricane Sandy'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">Pre Storm Image Header Text:</label>
                            <input type="textbox" id="editPreStormHeader" class="clickableButton" name="newPreStormHeader" maxlength="50" value="$currentPreImageHeader" />
                        </div>

                        <div class="formFieldRow">
                           <label title="Disabling a project removes it from from public view in iCoast. By doing this it will be removed from all publically accessible project selection lists and save links to the project will redirect back to the project selection screen. The project can be made publicly available again by re-enabling the it here.">Project Status:</label>
                           $projectStatusRadioButtonHTML
                       </div>

                        <input type="hidden" name="projectId" value="$projectId" />
                        <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                        <input type="hidden" name="editSubmitted" value="1" />
                        <div class="updateFormSubmissionControls">
                            <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                            </div>
                        <div class="updateFormSubmissionControls"><hr>
                            <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the project details update screen without submitting changes to the database. You will be returned to the Action Selection screen to choose another project editing activity." value="Cancel Changes and Return to Action Selection Screen">
                        </div>

                    </form>
EOL;
            break;
        //
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // BUILD THE TASKS UPDATE FORM HTML
        //
        //
        // UPDATE A TASK BELONGING TO THE PROJECT
        case 'tasks':
            // IF A TASK TO UPDATE HAS NOT BEEN SPECIFIED OR THE OPTION TO CREATE A NEW TASK WAS NOT SELECTED THEN
            // BUILD A TASK SELECTION FORM AND GIVE THE OPTION TO CREATE A NEW TASK
            if (!isset($_POST['projectEditSubAction'])) {
                $tasks = buildTaskSelectOptions($DBH, $projectId);
                $taskSelectOptionsHTML = $tasks[0];
                $actionControlsHTML = <<<EOL
                            <h2>Edit Project Tasks</h2>
                            <div class="threeColumnOrSplit">
                                <div>
                                        <p>Please select a task to edit</p>
                                    <form method="post" class="taskSelectForm">
                                        <div id="formFieldRow">
                                            <label for"taskSelectBox">Task Name:</label>
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
                                <div>
                                    <p>OR</p>
                                </div>
                                <div>
                                    <p>Click to create a new task</p>
                                    <form method="post" class="taskSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="tasks" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewTask" />
                                        <input type="submit" id ="createNewTaskButton" class="clickableButton" value="Create New Task in This Project" />
                                    </form>
                                </div>
                            </div>
                            <div class="updateFormSubmissionControls"><hr>
                                <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the task selection screen and return you to the Action Selection screen for you to choose another project editing activity. No changes will be made to the database." value="Return to Action Selection Screen">
                            </div>

EOL;
            } // END THE CREATION OF THE TASK SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
            // CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
                // FIND DETAILS OF ALL OTHER TASKS
                $invalidRequiredField = FALSE;
                if ($_POST['projectEditSubAction'] == 'updateExistingTask' || $_POST['projectEditSubAction'] == 'createNewTask') {
                    $projectEditSubAction = $_POST['projectEditSubAction'];
                } else {
                    $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                }
                if (!$invalidRequiredField) {
                    $projectTaskQuery = "SELECT * FROM task_metadata WHERE project_id = :projectId ORDER BY order_in_project ASC";
                    $projectTaskParams['projectId'] = $projectId;
                    $projectTaskResults = run_prepared_query($DBH, $projectTaskQuery, $projectTaskParams);
                    $projectTasks = $projectTaskResults->fetchAll(PDO::FETCH_ASSOC);
                    $numberOfTasks = count($projectTasks);

                    $taskOrderTableContentHTML = '';
                    $newOrderInProjectSelectHTML = '';
                    $sequentialOrderInProjectNumber = 1; // USED TO CREATE A SEQUENTIAL TASK NUMBER IN CASE DATABSE HAS HOLES IN ORDER LIST
                    // IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                    if (isset($_POST["taskId"]) && $projectEditSubAction == 'updateExistingTask') {
                        settype($_POST['taskId'], 'integer');
                        if (!empty($_POST['taskId'])) {
                            $taskId = $_POST['taskId'];
                        } else {
                            $invalidRequiredField['taskId'] = $_POST['taskId'];
                        }
                        if (!$invalidRequiredField) {
                            // COPY CURRENT TASK DETAILS TO VARIABLES AND BUILD TASK SPECIFIC HTML
                            // LOOP THROUGH THE TASKS
                            for ($i = 0; $i < $numberOfTasks; $i++) {
                                $ordinalOrderInProject = ordinal_suffix($sequentialOrderInProjectNumber);
                                // REPLACE THE TASK ORDER NUMBER WITH AN EQUIVELENT SEQUENTIAL NUMBER
                                $projectTasks[$i]['order_in_project'] = $sequentialOrderInProjectNumber;
                                // IF TASK IS THE TASK TO BE UPDATED COPY OFF THE DETAILS
                                if ($projectTasks[$i]['task_id'] == $taskId) {
                                    $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\" selected>$ordinalOrderInProject</option>";
                                    $taskOrderTableContentHTML .= '<tr id="currentProperty"';
                                    $currentTaskStatus = $projectTasks[$i]["is_enabled"];
                                    $currentTaskName = $projectTasks[$i]["name"];
                                    $currentTaskDescription = $projectTasks[$i]["description"];
                                    $currentOrderInProject = $projectTasks[$i]["order_in_project"];
                                    $currentDisplayTitle = $projectTasks[$i]["display_title"];
                                } else if ($projectTasks[$i]["is_enabled"] == 0) {
                                    $taskOrderTableContentHTML .= '<tr class="disabledProperty"';
                                    $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                                } else {
                                    $taskOrderTableContentHTML .= '<tr';
                                    $newOrderInProjectSelectHTML .= "<option value=\"$sequentialOrderInProjectNumber\">$ordinalOrderInProject</option>";
                                } // END if ($projectTasks[$i]['task_id'] == $taskId)
                                $taskOrderTableContentHTML .= " title=\"{$projectTasks[$i]["display_title"]}\"><td>$ordinalOrderInProject</td><td>{$projectTasks[$i]["name"]}</td></tr>";
                                $sequentialOrderInProjectNumber ++;
                            } //END for ($i = 0; $i < $numberOfTasks; $i++)
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
                            <h2>Edit Task Details</h2>
                            <form method="post" id="editTaskDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTaskName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Task Admin Name:</label>
                                    <input type="textbox" id="editTaskName" class="clickableButton" name="newTaskName" maxlength="50" value="$currentTaskName" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDescription" title="This text is for admin reference only to help explain details of the task. The content of this field is not shared with standard users. 500 character limit.">Task Admin Description:</label>
                                    <textarea id="editTaskDescription" class="clickableButton" name="newTaskDescription" maxlength="500">$currentTaskDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDisplayTitle" title="This text is used as task title on the classification page. Text identifying the task position and a short description of the task questions is best here. There is a 100 character limit. Always check the text displays correctly on the classification page after editing this field.">Task Display Text:</label>
                                    <textarea id="editTaskDisplayTitle" class="clickableButton" name="newDisplayTitle" maxlength="100">$currentDisplayTitle</textarea>
                                </div>
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
                                 <div class="formFieldRow">
                                    <label title="Disabling a task removes it, and all of the groups and tags it contains, from public view. By doing this it is removed from the task flow in the classification page but can easily be re-added again in the future br re-enabling the task here.">Task Status:</label>
                                    $taskStatusRadioButtonHTML
                                </div>

                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="updateExistingTask" />
                                <input type="hidden" name="taskId" value="$taskId" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the task editing screen without submitting changes to the database and return you to the task selection screen for you to choose another task to edit." value="Cancel Changes and Edit a Different Task">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the task editing screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Changes and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                        }
                    } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))
                    // IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                    else if ($projectEditSubAction == 'createNewTask') {
                        // LOOP THROUGH THE EXISTING TASKS RESEQUENCING THE ORDER NUMBERS AND BUILDING TASK SPECIFIC HTML
                        for ($i = 0; $i < $numberOfTasks; $i++) {

                            $ordinalOrderInProject = ordinal_suffix($sequentialOrderInProjectNumber);
                            // REPLACE THE TASK ORDER NUMBER WITH AN EQUIVELENT SEQUENTIAL NUMBER
                            $projectTasks[$i]['order_in_project'] = $sequentialOrderInProjectNumber;
                            // IF TASK IS THE TASK TO BE UPDATED COPY OFF THE DETAILS
                            if ($projectTasks[$i]["is_enabled"] == 0) {
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


                        // BUILD THE PROJECT DETAILS UPDATE FORM HTML
                        $actionControlsHTML = <<<EOL
                            <h2>Create New Task</h2>
                            <form method="post" id="editTaskDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTaskName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Task Admin Name:</label>
                                    <input type="textbox" id="editTaskName" class="clickableButton" name="newTaskName" maxlength="50" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDescription" title="This text is for admin reference only to help explain details of the task. The content of this field is not shared with standard users. 500 character limit.">Task Admin Description:</label>
                                    <textarea id="editTaskDescription" class="clickableButton" name="newTaskDescription" maxlength="500"></textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editTaskDisplayTitle" title="This text is used as task title on the classification page. Text identifying the task position and a short description of the task questions is best here. There is a 100 character limit. Always check the text displays correctly on the classification page after editing this field.">Task Display Text:</label>
                                    <textarea id="editTaskDisplayTitle" class="clickableButton" name="newDisplayTitle" maxlength="100"></textarea>
                                </div>
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
                                 <div class="formFieldRow">
                                    <label title="Disabling a task removes it, and all of the groups and tags it contains, from public view. By doing this it is removed from the task flow in the classification page but can easily be re-added again in the future br re-enabling the task here.">Task Status:</label>
                                    <input type="radio" id="editTaskStatusEnabled" name="newTaskStatus" value="1" checked>
                                    <label for="editTaskStatusEnabled" class="clickableButton"title="The task and its contents will be available for public viewing.">Enabled</label>
                                    <input type="radio" id="editTaskStatusDisabled" name="newTaskStatus" value="0">
                                    <label for="editTaskStatusDisabled" class="clickableButton" title="The task and its contents will NOT be available for public viewing.">Disabled</label>
                                </div>

                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewTask" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the task editing screen without submitting changes to the database and return you to the task selection screen for you to choose another task to edit." value="Cancel Changes and Edit a Different Task">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the task editing screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Changes and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                    }
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h2>Errors Detected in Input Data</h2>
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
            if (!isset($_POST['projectEditSubAction'])) {
                // FIND DATA FOR ALL GROUPS IN THE PROJECT
                $projectGroupQuery = "SELECT tag_group_id, name, description, is_enabled FROM tag_group_metadata WHERE project_id = :projectId ORDER BY name";
                $projectGroupParams['projectId'] = $projectId;
                $projectGroupResults = run_prepared_query($DBH, $projectGroupQuery, $projectGroupParams);
                $projectGroups = $projectGroupResults->fetchAll(PDO::FETCH_ASSOC);

                // BUILD THE GROUP SELECTION HTML FORM
                $groupSelectOptionsHTML = '';
                foreach ($projectGroups as $individualGroup) {
                    $individualGroupId = $individualGroup['tag_group_id'];
                    $individualGroupName = $individualGroup['name'];
                    $individualGroupDescription = $individualGroup['description'];
                    $isEnabled = $individualGroup['is_enabled'];
                    if ($isEnabled == 1) {
                        $groupSelectOptionsHTML .= "<option title=\"$individualGroupDescription\" value=\"$individualGroupId\">$individualGroupName</option>";
                    } else {
                        $groupSelectOptionsHTML .= "<option title=\"$individualGroupDescription\" value=\"$individualGroupId\">$individualGroupName (Disabled)</option>";
                    }
                }
                $actionControlsHTML = <<<EOL
                            <h2>Edit Project Groups</h2>
                            <div class="threeColumnOrSplit">
                                <div>
                                    <p>Please select a group to edit</p>
                                    <form method="post" class="groupSelectForm">
                                        <div id="formFieldRow">
                                            <label for"groupSelectBox">Group Name:</label>
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
                                <div>
                                    <p>OR</p>
                                </div>
                                <div>
                                    <p>Click to create a new group</p>
                                    <form method="post" class="groupSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="groups" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewGroup" />
                                        <input type="submit" id ="createNewGroupButton" class="clickableButton" value="Create New Group in This Project" />
                                    </form>
                                </div>
                            </div>
                            <div class="updateFormSubmissionControls"><hr>
                                <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the tag group selection screen and return you to the Action Selection screen for you to choose another project editing activity. No changes will be made to the database." value="Return to Action Selection Screen">
                            </div>

EOL;
            } // END THE CREATION OF THE GROUP SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
            // CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
                $invalidRequiredField = FALSE;
                if ($_POST['projectEditSubAction'] == 'updateExistingGroup' || $_POST['projectEditSubAction'] == 'createNewGroup') {
                    $projectEditSubAction = $_POST['projectEditSubAction'];
                } else {
                    $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                }
                // IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                if (isset($_POST["groupId"]) && isset($projectEditSubAction) && $projectEditSubAction == 'updateExistingGroup') {
                    settype($_POST['groupId'], 'integer');
                    if (!empty($_POST['groupId'])) {
                        $groupId = $_POST['groupId'];
                    } else {
                        $invalidRequiredField['groupId'] = $_POST['groupId'];
                    }

                    if (!$invalidRequiredField) {

                        $groupMetadata = retrieve_entity_metadata($DBH, $groupId, 'groups');
                        $currentGroupName = $groupMetadata['name'];
                        $currentGroupDescription = $groupMetadata['description'];
                        $currentGroupDisplayText = $groupMetadata['display_text'];
                        $currentGroupWidth = $groupMetadata['force_width'];
                        $currentGroupBorder = $groupMetadata['has_border'];
                        $currentGroupColor = $groupMetadata['has_color'];
                        $currentGroupStatus = $groupMetadata['is_enabled'];
                        $currentGroupContainsGroups = $groupMetadata['contains_groups'];

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
                        $taskNameList = $tasks[2];
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
                                            <label for"taskSelectBox" title="Use this select box to choose the task that should contain this group.">Parent Task:</label>
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
                                        <p>Select a task to contain this group</p>
                                        <div id="formFieldRow">
                                            <label for"taskSelectBox">Task Name:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <p>OR</p>
                                    </div>
                                    <div>
                                        <p>Select another group to contain this group</p>
                                        <div id="formFieldRow">
                                            <label for"groupSelectBox">Group Name:</label>
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
                                    $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . '<tr id="currentProperty"';
                                    $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                                } else if ($isEnabled == 0) {
                                    $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                                    $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                                } else {
                                    $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . '<tr';
                                    $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                                }
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInTask</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            }
                            if (!$containerContainsGroup) {
                                $sequentialOrderInTask++;
                                $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                                $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                            }
                        }
                        $javascriptGroupOrderInParentTask = json_encode($javascriptGroupOrderInParentTask);






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
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . '<tr id="currentProperty" ';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                                } else if ($isEnabled == 0) {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . '<tr class="disabledProperty" ';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                } else {
                                    $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . '<tr';
                                    $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                }
                                $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            }
                            if (!$containerContainsGroup) {
                                $sequentialOrderInGroup++;
                                $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                                $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                            }
                        }
                        $javascriptGroupOrderInParentGroup = json_encode($javascriptGroupOrderInParentGroup);






                        // BUILD THE PROJECT DETAILS UPDATE FORM HTML
                        $actionControlsHTML = <<<EOL
                            <h2>Edit Tag Group Details</h2>
                            <form method="post" id="editGroupDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editGroupName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Group Admin Name:</label>
                                    <input type="textbox" id="editGroupName" class="clickableButton" name="newGroupName" maxlength="50" value="$currentGroupName" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDescription" title="This text is for admin reference only to help explain details of the group. The content of this field is not shared with standard users. 500 character limit.">Group Admin Description:</label>
                                    <textarea id="editGroupDescription" class="clickableButton" name="newGroupDescription" maxlength="500">$currentGroupDescription</textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDisplayText" title="This text is used as the group title on the classification page. Text identifying whow the tags or groups this group contains are related is best here. There is a 500 character limit. Always check the text displays correctly on the classification page after editing this field.">Group Display Text:</label>
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
                                    <h3>Current Display Order In Task </h3>
                                    <p>Disabled (hidden) tasks are shown in red.<br>
                                        The current group being edited is shown in green.<br>
                                        All other **** are uncolored.<br>
                                        Hovering over a row displays more details of the task.</p>
                                    <table id="propertyOrderTable">
                                        <thead>
                                            <tr>
                                                <td>Position Number</td>
                                                <td>Task Name</td>
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
                                        <label for="editGroupOrder">Select the new desired task position:</label>
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
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the group editing screen without submitting changes to the database and return you to the group selection screen for you to choose another group to edit." value="Cancel Changes and Edit a Different Group">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the group editing screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Changes and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                    }
                } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))

                // IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                else if (isset($projectEditSubAction) && $projectEditSubAction == 'createNewGroup') {

                    $tasks = buildTaskSelectOptions($DBH, $projectId);
                    $taskSelectOptionsHTML = $tasks[0];
                    $taskIdList = $tasks[1];
                    $taskNameList = $tasks[2];
                    $whereInTasks = where_in_string_builder($taskIdList);


                    $groups = buildGroupSelectOptions($DBH, $projectId, false, true);
                    $groupSelectOptionsHTML = $groups[0];
                    $groupIdList = $groups[1];
                    $groupNameList = $groups[2];
                    $whereInGroups = where_in_string_builder($groupIdList);

                    if (empty($groupSelectOptionsHTML)) {
                        $groupContainedInHTML = <<<EOL
                                        <div id="formFieldRow">
                                            <label for"taskSelectBox" title="Use this select box to choose the task that should contain this group.">Parent Task:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="taskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>

EOL;
                        $groupContainer = 'Task';
                    } else {
                        $groupContainedInHTML = <<<EOL
                                <div class="threeColumnOrSplit">
                                    <div>
                                        <p>Select a task to contain this group</p>
                                        <div id="formFieldRow">
                                            <label for"taskSelectBox">Task Name:</label>
                                            <select id="taskSelectBox" class="clickableButton" name="newParentTaskId">
                                                $taskSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <p>OR</p>
                                    </div>
                                    <div>
                                        <p>Select another group to contain this group</p>
                                        <div id="formFieldRow">
                                            <label for"groupSelectBox">Group Name:</label>
                                            <select id="groupSelectBox" class="clickableButton" name="newParentGroupId">
                                                $groupSelectOptionsHTML
                                            </select>
                                        </div>
                                    </div>
                                </div>
EOL;
                    }





                    $taskGroupOrderQuery = "SELECT tgm.name, tgm.is_enabled, tgm.display_text, tc.task_id AS parent_id, tc.order_in_task "
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
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                            } else {
                                $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . '<tr';
                            }
                            $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] = $javascriptGroupOrderInParentTask[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInTask</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\">$ordinalOrderInTask</option>\n\r";
                        }
                        $sequentialOrderInTask++;
                        $ordinalOrderInTask = ordinal_suffix($sequentialOrderInTask);
                        $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentTask[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInTask\" selected>$ordinalOrderInTask</option>\n\r";
                    }
                    $javascriptGroupOrderInParentTask = json_encode($javascriptGroupOrderInParentTask);






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
                                $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                            } else {
                                $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . '<tr';
                            }
                            $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                        }
                        $sequentialOrderInGroup++;
                        $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                        $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptGroupOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                    }
                    $javascriptGroupOrderInParentGroup = json_encode($javascriptGroupOrderInParentGroup);



                    // BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h2>Create New Tag Group</h2>
                            <form method="post" id="editGroupDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editGroupName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Group Admin Name:</label>
                                    <input type="textbox" id="editGroupName" class="clickableButton" name="newGroupName" maxlength="50" />
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDescription" title="This text is for admin reference only to help explain details of the group. The content of this field is not shared with standard users. 500 character limit.">Group Admin Description:</label>
                                    <textarea id="editGroupDescription" class="clickableButton" name="newGroupDescription" maxlength="500"></textarea>
                                </div>

                                <div class="formFieldRow">
                                    <label for="editGroupDisplayText" title="This text is used as the group title on the classification page. Text identifying whow the tags or groups this group contains are related is best here. There is a 500 character limit. Always check the text displays correctly on the classification page after editing this field.">Group Display Text:</label>
                                    <textarea id="editGroupDisplayText" class="clickableButton" name="newDisplayText" maxlength="500"></textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the type of objects this group can contain. The options are either Tags or other Groups. The current croup must not have any existing contents if you wish to change this option.">Group Contains:</label>
                                    <input type="radio" id="editGroupContainsTags" name="newGroupContainsGroupsStatus" value="0" checked>
                                    <label for="editGroupContainsTags" class="clickableButton" title="Sets this group to contain tags.">Tags</label>
                                    <input type="radio" id="editGroupContainsGroups" name="newGroupContainsGroupsStatus" value="1">
                                    <label for="editGroupContainsGroups" class="clickableButton" title="Sets this group to contain other groups.">Groups</label>

                                </div>
                                <div class="formFieldRow">
                                    <label for="editGroupWidth" title="If specified the number in this field is the width in pixels that the group will be forced to occupy on the page. Display issues may occur if this setting to too large or small. Always check the group displays correctly on the classification page after editing this field. A setting of 0 menas the group width will be calculated automatically.">Group Width (in pixels):</label>
                                    <input type="textbox" id="editGroupWidth" class="clickableButton" name="newGroupWidth" maxlength="4" />
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons turn the display of a border around the group on and off. Using a border can be beneficial if you have groups within groups and seperation would make them clearer.">Display Group Border:</label>
                                    <input type="radio" id="editGroupHasBorderNo" name="newGroupBorderStatus" value="0" checked>
                                    <label for="editGroupHasBorderNo" class="clickableButton" title="Turns the group border off.">Off</label>
                                    <input type="radio" id="editGroupHasBorderYes" name="newGroupBorderStatus" value="1">
                                    <label for="editGroupHasBorderYes" class="clickableButton" title="Turns the group border on.">On</label>
                                </div>
                                <div class="formFieldRow">
                                    <label title="These buttons set the background color of a group. Using a background color can help distinguish a group or provide a way of showing increased importance.">Group Background Color:</label>
                                    <input type="radio" id="editGroupHasColorNo" name="newGroupColorStatus" value="0" checked>
                                    <label for="editGroupHasColorNo" class="clickableButton" title="Turns the group border off.">Off</label>
                                    <input type="radio" id="editGroupHasColorYes" name="newGroupColorStatus" value="1">
                                    <label for="editGroupHasColorYes" class="clickableButton" title="Turns the group border on.">On</label>
                                    <input id="groupColorPicker" class="color clickableButton disabledClickableButton" title="Manually enter the desired background color in six digit hexadecimal or use the color picker" name="newGroupColor" value="FFFFFF" disabled/>
                                </div>
                                $groupContainedInHTML
                                <div class="twoColumnSplit">
                                    <div>
                                    <h3>Current Display Order In Task </h3>
                                    <p>Disabled (hidden) tasks are shown in red.<br>
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
                                        </tbody>
                                    </table>
                                    </div>
                                    <div>
                                    </div>
                                    <div>
                                        <h3>New Display Order</h3>
                                        <p>Select the new position you would like the group to be shown in. All other groups will be
                                            re-numbered to sequentially precede or follow this group in their current order.</p>
                                        <label for="editGroupOrder">Select the new desired task position:</label>
                                        <select id="editGroupOrder" class="clickableButton" name="newGroupOrder">
                                        </select>
                                    </div>
                                </div>
                                 <div class="formFieldRow">
                                    <label title="Disabling a group removes it, and all of the tags it contains from public view. It can easily be re-added in the future br re-enabling the group here.">Group Status:</label>
                                    <input type="radio" id="editGroupStatusEnabled" name="newGroupStatus" value="1" checked>
                                    <label for="editGroupStatusEnabled" class="clickableButton" title="The group and its contents will be available for public viewing.">Enabled</label>
                                    <input type="radio" id="editGroupStatusDisabled" name="newGroupStatus" value="0">
                                    <label for="editGroupStatusDisabled" class="clickableButton" title="The group and its contents will NOT be available for public viewing.">Disabled</label>
                                </div>

                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewGroup" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Create New Group">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the group creation screen without submitting changes to the database and return you to the group selection screen for you to choose another group option." value="Cancel Changes and Work With a Different Group">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the group creation screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Creation and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h2>Errors Detected in Input Data</h2>
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
            if (!isset($_POST['projectEditSubAction'])) {
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
                            <h2>Edit Project Tags</h2>
                            <div class="threeColumnOrSplit">
                                <div>
                                    <p>Please select a tag to edit</p>
                                    <form method="post" class="tagSelectForm">
                                        <div id="formFieldRow">
                                            <label for"tagSelectBox">Tag Name:</label>
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
                                <div>
                                    <p>OR</p>
                                </div>
                                <div>
                                    <p>Click to create a new tag</p>
                                    <form method="post" class="tagSelectForm">
                                        <input type="hidden" name="projectPropertyToUpdate" value="tags" />
                                        <input type="hidden" name="projectId" value="$projectId" />
                                        <input type="hidden" name="projectEditSubAction" value="createNewTag" />
                                        <input type="submit" id ="createNewGroupButton" class="clickableButton" value="Create New Tag in This Project" />
                                    </form>
                                </div>
                            </div>
                            <div class="updateFormSubmissionControls"><hr>
                                <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the tag selection screen and return you to the Action Selection screen for you to choose another project editing activity. No changes will be made to the database." value="Return to Action Selection Screen">
                            </div>

EOL;
            } // END THE CREATION OF THE GROUP SELECTION FORM - if (!isset($_POST['taskId']) && !isset($_POST['projectEditSubAction']))
            // CREATE VARIABLES AND HTML THAT IS SHARED BETWEEN UPDATING A TASK AND CREATING A TASK
            else {
                $invalidRequiredField = FALSE;
                if ($_POST['projectEditSubAction'] == 'updateExistingTag' || $_POST['projectEditSubAction'] == 'createNewTag') {
                    $projectEditSubAction = $_POST['projectEditSubAction'];
                } else {
                    $invalidRequiredField['projectEditSubAction'] = $_POST['projectEditSubAction'];
                }
                // IF A TASK ID TO UPDATE HAS BEEN SUPPLIED BUILD THE FORM TO UPDATE THE TASK
                if (isset($_POST["tagId"]) && isset($projectEditSubAction) && $projectEditSubAction == 'updateExistingTag') {
                    settype($_POST['tagId'], 'integer');
                    if (!empty($_POST['tagId'])) {
                        $tagId = $_POST['tagId'];
                    } else {
                        $invalidRequiredField['tagId'] = $_POST['tagId'];
                    }
                    if (!$invalidRequiredField) {
                        $tagMetadata = retrieve_entity_metadata($DBH, $tagId, 'tags');
                        $currentTagName = $tagMetadata['name'];
                        $currentTagDescription = $tagMetadata['description'];
                        $currentTagDisplayText = $tagMetadata['display_text'];
                        $currentTagToolTipText = $tagMetadata['tooltip_text'];
                        $currentTagToolTipImage = $tagMetadata['tooltip_image'];
                        $currentTagToolTipImageWidth = $tagMetadata['tooltip_image_width'];
                        $currentTagToolTipImageHeight = $tagMetadata['tooltip_image_height'];
                        $currentTagStatus = $tagMetadata['is_enabled'];
                        $currentTagIsComment = $tagMetadata['is_comment_box'];
                        $currentTagIsRadio = $tagMetadata['is_radio_button'];
                        $currentTagRadioGroupName = $tagMetadata['radio_button_group'];


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
                                    $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . '<tr id="currentProperty"';
                                    $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                                } else if ($isEnabled == 0) {
                                    $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                                    $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                } else {
                                    $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . '<tr';
                                    $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                                }
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            }
                            if (!$containerContainsTag) {
                                $sequentialOrderInGroup++;
                                $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                                $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                            }
                        }
                        $javascriptTagOrderInParentGroup = json_encode($javascriptTagOrderInParentGroup);

                        // BUILD THE PROJECT DETAILS UPDATE FORM HTML
                        $actionControlsHTML = <<<EOL
                            <h2>Edit Tag Details</h2>
                            <form method="post" id="editTagDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTagName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Tag Admin Name:</label>
                                    <input type="textbox" id="editTagName" class="clickableButton" name="newTagName" maxlength="50" value="$currentTagName" />
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDescription" title="This text is for admin reference only to help explain details of the tag. The content of this field is not shared with standard users. 500 character limit.">Tag Admin Description:</label>
                                    <textarea id="editTagDescription" class="clickableButton" name="newTagDescription" maxlength="500">$currentTagDescription</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDisplayText" title="This text is displayed within the tag shown to the user. It should be short and descripive, describing the option the user is selecting. There is a 50 character limit however this length of text is likely to exceed the tags available space. Always check the text displays correctly on the classification page after editing this field.">Tag Display Text:</label>
                                    <textarea id="editTagDisplayText" class="clickableButton" name="newTagDisplayText" maxlength="50">$currentTagDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipText" title="This text is shown in a tooltip popup if the user hovers over the tag. The text should provide more information or educational content about the meaning of the tag. There is a 1000 character limit. Always check the text displays correctly on the classification page after editing this field.">Tag Tooltip Text:</label>
                                    <textarea id="editTagToolTipText" class="clickableButton" name="newTagToolTipText" maxlength="1000">$currentTagToolTipText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipImage" title="This is the name of the uploaded image file that is to be used if an image is desired in the tooltip. The image will be displayed below the tooltip text in the tooltiup popup. There is a 255 character limit on the file name. Always check the image displays correctly on the classification page after editing this field.">Tag Tooltip Image File:</label>
                                    <input type="textbox" id="editTagToolTipText" class="clickableButton" name="newTagToolTipImage" maxlength="255" value="$currentTagToolTipImage" />
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
                                    <label for"tagParentGroupSelectBox" title="Use the selection box to choose which group should contain this tag. Associated tags should be grouped together in the same parent group.">Parent Group:</label>
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
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the group editing screen without submitting changes to the database and return you to the group selection screen for you to choose another group to edit." value="Cancel Changes and Edit a Different Tag">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the group editing screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Changes and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                    }
                } // END FORM CREATION FOR UPDATING OF AN EXISTING TASK - if (isset($_POST["taskId"]))

                // IF REQUEST IS TO BUILD A NEW TASK THEN BUILD THE FORM TO CREATE THE TASK
                else if ($projectEditSubAction == 'createNewTag') {

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
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . '<tr class="disabledProperty"';
                            } else {
                                $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . '<tr';
                            }
                            $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] = $javascriptTagOrderInParentGroup[$parentId]['tableHTML'] . " title=\"{$parentContainer[$i]["display_text"]}\"><td>$ordinalOrderInGroup</td><td>{$parentContainer[$i]["name"]}</td></tr>\n\r";
                            $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\">$ordinalOrderInGroup</option>\n\r";
                        }
                        $sequentialOrderInGroup++;
                        $ordinalOrderInGroup = ordinal_suffix($sequentialOrderInGroup);
                        $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] = $javascriptTagOrderInParentGroup[$parentId]['newOrderSelectHTML'] . "<option value=\"$sequentialOrderInGroup\" selected>$ordinalOrderInGroup</option>\n\r";
                    }
                    $javascriptTagOrderInParentGroup = json_encode($javascriptTagOrderInParentGroup);



                    // BUILD THE PROJECT DETAILS UPDATE FORM HTML
                    $actionControlsHTML = <<<EOL
                            <h2>Create New Tag</h2>
                            <form method="post" id="editTagDetailsForm" autocomplete="off">
                                <div class="formFieldRow">
                                    <label for="editTagName" title="This text is for admin reference only to provided an abbreviated title for ease of selection. The content of this field is not shared with standard users. 50 character limit.">Tag Admin Name:</label>
                                    <input type="textbox" id="editTagName" class="clickableButton" name="newTagName" maxlength="50" value="$currentTagName" />
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDescription" title="This text is for admin reference only to help explain details of the tag. The content of this field is not shared with standard users. 500 character limit.">Tag Admin Description:</label>
                                    <textarea id="editTagDescription" class="clickableButton" name="newTagDescription" maxlength="500">$currentTagDescription</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagDisplayText" title="This text is displayed within the tag shown to the user. It should be short and descripive, describing the option the user is selecting. There is a 50 character limit however this length of text is likely to exceed the tags available space. Always check the text displays correctly on the classification page after editing this field.">Tag Display Text:</label>
                                    <textarea id="editTagDisplayText" class="clickableButton" name="newTagDisplayText" maxlength="50">$currentTagDisplayText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipText" title="This text is shown in a tooltip popup if the user hovers over the tag. The text should provide more information or educational content about the meaning of the tag. There is a 1000 character limit. Always check the text displays correctly on the classification page after editing this field.">Tag Tooltip Text:</label>
                                    <textarea id="editTagToolTipText" class="clickableButton" name="newTagToolTipText" maxlength="1000">$currentTagToolTipText</textarea>
                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagToolTipImage" title="This is the name of the uploaded image file that is to be used if an image is desired in the tooltip. The image will be displayed below the tooltip text in the tooltiup popup. There is a 255 character limit on the file name. Always check the image displays correctly on the classification page after editing this field.">Tag Tooltip Image File:</label>
                                    <input type="textbox" id="editTagToolTipText" class="clickableButton" name="newTagToolTipImage" maxlength="255" value="$currentTagToolTipImage" />
                                </div>
                                <div class="formFieldRow">
                                    <label title="These options change the behavior or functionality of the tag. Hover over each option for a detailed description of what it does.">Tag Type:</label>
                                    <input type="radio" id="editTagTypeSelect" name="newTagType" value="0" checked/>
                                    <label for="editTagTypeSelect" class="clickableButton" title="The default tag type. It has no restrictions on how it can be selected in relation to other selected tags.">Multi-Select</label>
                                    <input type="radio" id="editTagTypeRadio" name="newTagType" value="1" />
                                    <label for="editTagTypeRadio" class="clickableButton" title="Works like a radio button on a form where only one tag of several can be selected. All tags that are to be mutually exclusive must be assigned the same radio button group name.">Mutually Exclusive</label>
                                    <input type="radio" id="editTagTypeComment" name="newTagType" value="2" />
                                    <label for="editTagTypeComment" class="clickableButton" title="The tag displays as a comment box allowing the user to enter text providing a more vebose means of feedback. This provides an alternative to the standard on/off result supplied by tag buttons but interpretation of the contents usually requires human involvement.">Comment Box</label>

                                </div>
                                <div class="formFieldRow">
                                    <label for="editTagRadioGroupName" title="If the tag is to be mutually exclusive then all tags that share this exclusivity must be given the same Exclusivity Group name to tie them together. Enter any name here that you will use for all tags that should be mutally exclusive from one another. There is a 20 character limit. Always test tag behavior after editing this field to ensure all tags that shoudl be mutually exclusive behave in the correct manner (only one of them can be selected at any one time).">Exclusivity Group Name:</label>
                                    <input type="textbox" id="editTagRadioGroupName" class="clickableButton disabledClickableButton" name="newTagRadioGroupName" maxlength="20" value="$currentTagRadioGroupName" disabled />
                                </div>
                                <div class="formFieldRow">
                                    <label for"tagParentGroupSelectBox" title="Use the selection box to choose which group should contain this tag. Associated tags should be grouped together in the same parent group.">Parent Group:</label>
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
                                    <input type="radio" id="editTagStatusEnabled" name="newTagStatus" value="1" checked>
                                    <label for="editTagStatusEnabled" class="clickableButton" title="The tag will be available for selection.">Enabled</label>
                                    <input type="radio" id="editTagStatusDisabled" name="newTagStatus" value="0">
                                    <label for="editTagStatusDisabled" class="clickableButton" title="The tag will NOT be available for selection and will be hidden from view.">Disabled</label>
                                </div>

                                <input type="hidden" name="projectId" value="$projectId" />
                                <input type="hidden" name="projectPropertyToUpdate" value="$projectPropertyToUpdate" />
                                <input type="hidden" name="projectEditSubAction" value="createNewTag" />
                                <input type="hidden" name="editSubmitted" value="1" />
                                <div class="updateFormSubmissionControls">
                                    <input type="submit" class="clickableButton" title="This will send your changes to the database. Ensure all fields are correct before clicking this button." value="Submit Changes">
                                </div>
                                <div class="updateFormSubmissionControls"><hr>
                                    <input type="button" class="clickableButton" id="returnToTaskSelection" title="This will exit the tag editing screen without submitting changes to the database and return you to the tag selection screen for you to choose another tag to edit." value="Cancel Changes and Edit a Different Tag">
                                    <input type="button" class="clickableButton" id="returnToActionSelection" title="This will exit the tag editing screen without submitting changes to the database and return you to the Action Selection screen for you to choose another project editing activity." value="Cancel Changes and Return To Action Selection Screen">
                                </div>

                            </form>
EOL;
                }
                if ($invalidRequiredField) {
                    $actionControlsHTML = <<<EOL
                          <h2>Errors Detected in Input Data</h2>
                          <p>One or more of the required data fields are either missing from the submission or contain invalid data.</p>

EOL;
                }
            }
            break;
    } // END switch ($projectPropertyToUpdate)
} // END if (isset($projectId) && ... !isset($_POST['editSubmitted']))

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build variable javascript code.
$variableJavascript = '';
if (isset($_POST['projectId'])) {
    $variableJavascript .= "var projectId = {$_POST['projectId']};\n\r";
}
if (isset($_POST['projectPropertyToUpdate'])) {
    $variableJavascript .= "var propertyToUpdate = '{$_POST['projectPropertyToUpdate']}';\n\r";
}
if (isset($javascriptGroupOrderInParentTask)) {
    $variableJavascript .= 'var groupOrderInTaskData = ' . $javascriptGroupOrderInParentTask . ';\n\r';
}
if (isset($javascriptGroupOrderInParentGroup)) {
    $variableJavascript .= 'var groupOrderInGroupData = ' . $javascriptGroupOrderInParentGroup . ';\n\r';
}
if (isset($javascriptTagOrderInParentGroup)) {
    $variableJavascript .= 'var tagOrderInGroupData = ' . $javascriptTagOrderInParentGroup . ';\n\r';
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build variable jQuery Document.Ready code.
$variableDocumentDotReadyCode = '';
if (isset($javascriptGroupOrderInParentTask)) {
    $variableDocumentDotReadyCode .= <<<EOL
                    var selectedTaskElement = $('#taskSelectBox option[selected]');
                    var selectedTaskValue = selectedTaskElement.val();
                    var selectedTaskindex = selectedTaskElement.index();
                    if (typeof selectedTaskValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(groupOrderInTaskData[selectedTaskValue]['tableHTML']);
                        $('#editGroupOrder').append(groupOrderInTaskData[selectedTaskValue]['newOrderSelectHTML']);
                        $('#taskSelectBox').prop('selectedIndex', selectedTaskindex);
                    }
EOL;
}

if (isset($javascriptGroupOrderInParentGroup)) {
    $variableDocumentDotReadyCode .= <<<EOL
                    var selectedGroupElement = $('#groupSelectBox option[selected]');
                    var selectedGroupValue = selectedGroupElement.val();
                    var selectedGroupindex = selectedGroupElement.index();
                    if (typeof selectedGroupValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(groupOrderInGroupData[selectedGroupValue]['tableHTML']);
                        $('#editGroupOrder').append(groupOrderInGroupData[selectedGroupValue]['newOrderSelectHTML']);
                        $('#groupSelectBox').prop('selectedIndex', selectedGroupindex);
                    }
EOL;
}

if (isset($javascriptTagOrderInParentGroup)) {
    $variableDocumentDotReadyCode .= <<<EOL
                    var selectedGroupElement = $('#tagParentGroupSelectBox option[selected]');
                    var selectedGroupValue = selectedGroupElement.val();
                    var selectedGroupindex = selectedGroupElement.index();
                    if (typeof selectedGroupValue !== 'undefined') {
                        $('#propertyOrderTable tbody').append(tagOrderInGroupData[selectedGroupValue]['tableHTML']);
                        $('#editTagOrder').append(tagOrderInGroupData[selectedGroupValue]['newOrderSelectHTML']);
                        $('#tagParentGroupSelectBox').prop('selectedIndex', selectedGroupindex);
                    }
EOL;
}






$javaScript = $variableJavascript;

$jQueryDocumentDotReadyCode = <<<EOL
        $variableDocumentDotReadyCode
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
            var selectedTask = $(this).val();
            console.log(groupOrderInTaskData[selectedTask]['tableHTML']);
            $('#groupSelectBox').prop("selectedIndex", -1);
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(groupOrderInTaskData[selectedTask]['tableHTML']);
            $('#editGroupOrder').empty();
            $('#editGroupOrder').append(groupOrderInTaskData[selectedTask]['newOrderSelectHTML']);
        });

        $('#groupSelectBox').change(function() {
            var selectedGroup = $(this).val();
            console.log(groupOrderInGroupData[selectedGroup]['tableHTML']);
            $('#taskSelectBox').prop("selectedIndex", -1);
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(groupOrderInGroupData[selectedGroup]['tableHTML']);
            $('#editGroupOrder').empty();
            $('#editGroupOrder').append(groupOrderInGroupData[selectedGroup]['newOrderSelectHTML']);
        });

        $('#tagParentGroupSelectBox').change(function() {
            var selectedGroup = $(this).val();
            console.log(tagOrderInGroupData[selectedGroup]['tableHTML']);
            $('#propertyOrderTable tbody').empty();
            $('#propertyOrderTable tbody').append(tagOrderInGroupData[selectedGroup]['tableHTML']);
            $('#editTagOrder').empty();
            $('#editTagOrder').append(tagOrderInGroupData[selectedGroup]['newOrderSelectHTML']);
        });
EOL;
