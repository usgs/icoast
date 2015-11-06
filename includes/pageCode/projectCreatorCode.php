<?php

//A template file to use for page code files
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$message = '';
$errorHTML = '';

$postProjectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT);
$getProjectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);
$projectName = filter_input(INPUT_POST, 'projectName');
$projectDescription = filter_input(INPUT_POST, 'projectDescription');
$postStormHeader = filter_input(INPUT_POST, 'postStormHeader');
$preStormHeader = filter_input(INPUT_POST, 'preStormHeader');
$deleteFlag = filter_input(INPUT_POST, 'delete');
$cancelDeletionFlag = filter_input(INPUT_POST, 'cancelDeletion');
$reviewFlag = filter_input(INPUT_POST, 'review');
$confirmDeletionFlag = filter_input(INPUT_POST, 'confirmDeletion');
$createFlag = filter_input(INPUT_POST, 'create');
$formSubmittedFlag = filter_input(INPUT_POST, 'formSubmitted');
$postCompleteFlag = filter_input(INPUT_POST, 'complete');
$getCompleteFlag = filter_input(INPUT_GET, 'complete');
$errorToken = filter_input(INPUT_GET, 'error');

//var_dump(
//        $postProjectId,
//        $getProjectId,
//        $projectName,
//        $projectDescription,
//        $postStormHeader,
//        $preStormHeader,
//        $deleteFlag,
//        $cancelDeletionFlag,
//        $reviewFlag,
//        $confirmDeletionFlag,
//        $createFlag,
//        $formSubmittedFlag,
//        $postCompleteFlag,
//        $getCompleteFlag,
//        $errorToken);

$projectId = null;
if ($getProjectId) {
    $projectId = $getProjectId;
} else if ($postProjectId) {
    $projectId = $postProjectId;
}

$completeFlag = null;
if (isset($getCompleteFlag) || isset($postCompleteFlag)) {
    $completeFlag = 1;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => DELETE PROJECT CODE
if ($deleteFlag && $projectId) {
    if ($cancelDeletionFlag) {
        if ($reviewFlag) {
            header('Location: reviewProject.php?projectId=' . $projectId);
        } else {
            header('Location: projectCreator.php');
        }
        exit;
    }
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    if (!empty($projectMetadata) &&
            $projectMetadata['creator'] == $userId &&
            $projectMetadata['is_complete'] == 0) {


        if ($confirmDeletionFlag) {
            $deleteError = array();
            $projectIdParam['projectId'] = $projectMetadata['project_id'];


            // DELETE PROJECT MATCHES
            $checkMatchesToDeleteQuery = '
                    SELECT COUNT(*)
                    FROM import_matches
                    WHERE project_id = :projectId
                    ';
            $checkMatchesToDeleteResult = run_prepared_query($DBH, $checkMatchesToDeleteQuery, $projectIdParam);
            $matchesToDelete = $checkMatchesToDeleteResult->fetchColumn();
            if ($matchesToDelete > 0) {
                $deleteMatchesQuery = "
                        DELETE
                        FROM import_matches
                        WHERE project_id = :projectId
                        LIMIT $matchesToDelete
                    ";
                $deleteMatchesResult = run_prepared_query($DBH, $deleteMatchesQuery, $projectIdParam);
                if ($deleteMatchesResult->rowCount() != $matchesToDelete) {
                    $deleteError[] = "Error deleting project matches (project: {$projectMetadata['project_id']}).";
                }
            }


            if (file_exists("images/projects/{$projectMetadata['project_id']}/tooltips")) {
                $files = glob("images/projects/{$projectMetadata['project_id']}/tooltips/*");
                foreach ($files as $file) { // iterate files
                    if (is_file($file)) {
                        $unlinkResult = unlink($file); // delete file
                        if (!$unlinkResult) {
                            $deleteError[] = "Error deleting tooltip image (project: {$projectMetadata['project_id']}, file: $file).";
                        }
                    }
                }
                $rmTooltipsResult = rmdir("images/projects/{$projectMetadata['project_id']}/tooltips");
                if (!$rmTooltipsResult) {
                    $deleteError[] = "Error deleting tooltips folder (project: {$projectMetadata['project_id']}).";
                }
                $rmProjectResult = rmdir("images/projects/{$projectMetadata['project_id']}");
                if (!$rmProjectResult) {
                    $deleteError[] = "Error deleting project folder (project: {$projectMetadata['project_id']}).";
                }
            }

            $importedCollectionsQuery = '
                    SELECT *
                    FROM import_collections
                    WHERE parent_project_id = :projectId
                ';
            $importedCollectionsParams['projectId'] = $projectMetadata['project_id'];
            $importedCollectionsResults = run_prepared_query($DBH, $importedCollectionsQuery, $importedCollectionsParams);
            while ($collectionMetadata = $importedCollectionsResults->fetch()) {
                $importedCollection = $collectionMetadata['import_collection_id'];
                if ($collectionMetadata['import_status_message'] == 'Processing' ||
                        $collectionMetadata['import_status_message'] == 'Sleeping') {
                    $abortImportQuery = '
                            UPDATE import_collections
                            SET user_abort_import_flag = 1
                            WHERE import_collection_id = :collectionId';
                    $abortImportParams['collectionId'] = $importedCollection;
                    $abortImportResult = run_prepared_query($DBH, $abortImportQuery, $abortImportParams);
                    if ($abortImportResult->rowCount() == 1) {
                        $sleepCount = 0;
                        do {
                            $sleepCount++;
                            sleep(5);
                            $collectionMetadata = retrieve_entity_metadata($DBH, $importedCollection, 'importCollection');
                        } while ($collectionMetadata['import_status_message'] != 'User Abort Request' || $sleepCount < 6);
                    }
                }
                $collectionParam['importCollectionId'] = $importedCollection;
                if (file_exists("images/temporaryImportFolder/$importedCollection")) {
                    if (file_exists("images/temporaryImportFolder/$importedCollection/main")) {
                        $files = glob("images/temporaryImportFolder/$importedCollection/main/*");
                        foreach ($files as $file) { // iterate files
                            if (is_file($file)) {
                                $unlinkResult = unlink($file); // delete file
                                if (!$unlinkResult) {
                                    $deleteError[] = "Error deleting image file (collection: $importedCollection, file: $file).";
                                }
                            }
                        }
                        $rmMainResult = rmdir("images/temporaryImportFolder/$importedCollection/main");
                        if (!$rmMainResult) {
                            $deleteError[] = "Error deleting main folder (collection: $importedCollection).";
                        }
                    }
                    if (file_exists("images/temporaryImportFolder/$importedCollection/thumbnails")) {
                        $files = glob("images/temporaryImportFolder/$importedCollection/thumbnails/*");
                        foreach ($files as $file) { // iterate files
                            if (is_file($file)) {
                                $unlinkResult = unlink($file); // delete file
                                if (!$unlinkResult) {
                                    $deleteError[] = "Error deleting thumbnail file (collection: $importedCollection, file: $file).";
                                }
                            }
                        }
                        $rmThumbsResult = rmdir("images/temporaryImportFolder/$importedCollection/thumbnails");
                        if (!$rmThumbsResult) {
                            $deleteError[] = "Error deleting thumbnail folder (collection: $importedCollection).";
                        }
                    }
                    $rmCollectionResult = rmdir("images/temporaryImportFolder/$importedCollection");
                    if (!$rmCollectionResult) {
                        $deleteError[] = "Error deleting collection folder (collection: $importedCollection).";
                    }
                }

                $imagesToDeleteQuery = '
                        SELECT COUNT(*)
                        FROM import_images
                        WHERE import_collection_id = :importCollectionId';
                $imagesToDeleteResult = run_prepared_query($DBH, $imagesToDeleteQuery, $collectionParam);
                $imagesToDeleteCount = $imagesToDeleteResult->fetchCOlumn();

                if ($imagesToDeleteCount > 0) {
                    $deleteImportedImagesQuery = '
                        DELETE FROM import_images
                        WHERE import_collection_id = :importCollectionId
                        ';
                    $deleteImportedImagesResult = run_prepared_query($DBH, $deleteImportedImagesQuery, $collectionParam);
                    if ($deleteImportedImagesResult->rowCount() == 0) {
                        $deleteError[] = "Error deleting import_images rows (collection: $importedCollection).";
                    }
                }

                $deleteImportCollectionsQuery = '
                        DELETE FROM import_collections
                        WHERE import_collection_id = :importCollectionId
                        ';
                $deleteImportCollectionssResult = run_prepared_query($DBH, $deleteImportCollectionsQuery, $collectionParam);
                if ($deleteImportCollectionssResult->rowCount() == 0) {
                    $deleteError[] = "Error deleting import_collections rows (collection: $importedCollection).";
                }
            }

            $determineProjectTasksQuery = '
                    SELECT task_id
                    FROM task_metadata
                    WHERE project_id = :projectId
                    ';
            $determineProjectTasksResult = run_prepared_query($DBH, $determineProjectTasksQuery, $projectIdParam);
            $taskIds = array();
            while ($taskId = $determineProjectTasksResult->fetchColumn()) {
                $taskIds[] = $taskId;
            }
            if (count($taskIds) > 0) {
                $whereInTaskIds = where_in_string_builder($taskIds);

                $deleteTaskMetadataQuery = '
                    DELETE FROM task_metadata
                    WHERE project_id = :projectId';
                $deleteTaskMetadataResult = run_prepared_query($DBH, $deleteTaskMetadataQuery, $projectIdParam);
                if ($deleteTaskMetadataResult->rowCount() != count($taskIds)) {
                    $deleteError[] = "Error deleting task_metadata (project: {$projectMetadata['project_id']}).";
                }

                $taskContentRowCountQuery = "
                        SELECT COUNT(*)
                        FROM task_contents
                        WHERE task_id IN ($whereInTaskIds)
                        ";
                $taskContentRowCountResult = run_prepared_query($DBH, $taskContentRowCountQuery);
                $taskContentRowsToDelete = $taskContentRowCountResult->fetchColumn();
                if ($taskContentRowsToDelete > 0) {
                    $deleteTaskContentsQuery = "
                            DELETE FROM task_contents
                            WHERE task_id IN ($whereInTaskIds)
                            ";
                    $deleteTaskContentsResult = run_prepared_query($DBH, $deleteTaskContentsQuery);
                    $affectedRows = $deleteTaskContentsResult->rowCount();
                    if ($affectedRows != $taskContentRowsToDelete) {
                        $deleteError[] = "Error deleting task_contents (project: {$projectMetadata['project_id']}).";
                    }
                }

                $determineTagGroupsQuery = '
                    SELECT tag_group_id
                    FROM tag_group_metadata
                    WHERE project_id = :projectId
                    ';
                $determineTagGroupsResult = run_prepared_query($DBH, $determineTagGroupsQuery, $projectIdParam);
                $tagGroupIds = array();
                while ($tagGroupId = $determineTagGroupsResult->fetchColumn()) {
                    $tagGroupIds[] = $tagGroupId;
                }
                if (count($tagGroupIds) > 0) {
                    $whereInTagGroupIds = where_in_string_builder($tagGroupIds);

                    $deleteTagGroupMetadataQuery = '
                            DELETE FROM tag_group_metadata
                            WHERE project_id = :projectId';
                    $deleteTagGroupMetadataResult = run_prepared_query($DBH, $deleteTagGroupMetadataQuery, $projectIdParam);
                    if ($deleteTagGroupMetadataResult->rowCount() != count($tagGroupIds)) {
                        $deleteError[] = "Error deleting tag_group_metadata (project: {$projectMetadata['project_id']}).";
                    }

                    $tagGroupContentsRowCountQuery = "
                            SELECT COUNT(*)
                            FROM tag_group_contents
                            WHERE tag_group_id IN ($whereInTagGroupIds)
                            ";
                    $tagGroupContentsRowCountResult = run_prepared_query($DBH, $tagGroupContentsRowCountQuery);
                    $tagGroupContentsRowsToDelete = $tagGroupContentsRowCountResult->fetchColumn();
                    if ($tagGroupContentsRowsToDelete > 0) {
                        $deleteTagGroupContentsQuery = "
                                DELETE FROM tag_group_contents
                                WHERE tag_group_id IN ($whereInTagGroupIds)
                                ";
                        $deleteTagGroupContentsResult = run_prepared_query($DBH, $deleteTagGroupContentsQuery);
                        $affectedRows = $deleteTagGroupContentsResult->rowCount();
                        if ($affectedRows != $tagGroupContentsRowsToDelete) {
                            $deleteError[] = "Error deleting tag_group_contents (project: {$projectMetadata['project_id']}).";
                        }
                    }

                    $tagRowCountQuery = '
                            SELECT COUNT(*)
                            FROM tags
                            WHERE project_id = :projectId';
                    $tagRowCountResult = run_prepared_query($DBH, $tagRowCountQuery, $projectIdParam);
                    $tagRowsToDelete = $tagRowCountResult->fetchColumn();
                    if ($tagRowsToDelete > 0) {
                        $deleteTagsQuery = '
                                DELETE FROM tags
                                WHERE project_id = :projectId';
                        $deleteTagsResult = run_prepared_query($DBH, $deleteTagsQuery, $projectIdParam);
                        $affectedRows = $deleteTagsResult->rowCount();
                        if ($affectedRows != $tagRowsToDelete) {
                            $deleteError[] = "Error deleting tags (project: {$projectMetadata['project_id']}).";
                        }
                    }
                }
            } // if (count($taskIds) > 0)

            $projectDeletionQuery = '
                    DELETE FROM projects
                    WHERE project_id = :projectId
                    LIMIT 1
                ';

            $projectDeletionResult = run_prepared_query($DBH, $projectDeletionQuery, $projectIdParam);
            if ($projectDeletionResult->rowCount() == 0) {
                $deleteError[] = "Error deleting project (project: {$projectMetadata['project_id']}).";
            }

            if (count($deleteError) > 0) {
                $message = '<p class="error">There were problems deleting some or all components of the project.<br>
                        Detected Errors:';
                foreach ($deleteError as $error) {
                    $message .= "<br>$error";
                }
                $message .= '</p>';
            } else {
                $message .= '<p class="error">The requested project has been sucessfully deleted from iCoast.</p>';
            }
            $deleteFlag = null;
        } else {
            if ($reviewFlag) {
                $hiddenParam = '<input type="hidden" name="review" value="1">';
            } else {
                $hiddenParam = '';
            }
            $pageContentHTML = '
                <h2>Delete the "' . $projectMetadata['name'] . '" Project</h2>
                <p>
                    Deleting a project that is partially built will remove all related data from the database.
                    This action cannot be undone. All project data will be irreparably lost.
                </p>
                <p class="error">Are you sure you want to delete the ' . $projectMetadata['name'] . ' project?</p>
                <form autocomplete="off" method="post">
                    <input type="hidden" name="delete" value="1"/>
                    ' . $hiddenParam . '
                    <input type="hidden" name="projectId" value="' . $projectMetadata['project_id'] . '" />
                    <button type="submit" class="clickableButton enlargedClickableButton" name="confirmDeletion" value="1">
                        Delete
                    </button>
                    <button type="submit" class="clickableButton enlargedClickableButton" name="cancelDeletion" value="1">
                        Cancel
                    </button>
                </form>
            ';
        }
    } else {
        header('Location: projectCreator.php?error=InvalidProject');
        exit;
    }
} else if ($deleteFlag && !$projectId) {
    $deleteFlag = null;
}
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => CREATE PROJECT CODE
if ($createFlag) {
    if ($formSubmittedFlag) {

        $errorLine = '';


            $invalidRequiredField = FALSE;
            $databaseUpdateFailure = FALSE;
            $fileSystemUpdateFailure = FALSE;

            if (empty($projectName)) {
                $invalidRequiredField['projectName'] = 'Project Name';
                $projectName = '';
            }

            if (empty($postStormHeader)) {
                $invalidRequiredField['postStormHeader'] = 'Post-Event Image Header Text';
                $postStormHeader = '';
            }

            if (empty($preStormHeader)) {
                $invalidRequiredField['preStormHeader'] = 'Pre-Event Image Header Text';
                $preStormHeader = '';
            }

            if (is_null($projectDescription)) {
                $projectDescription = '';
            }
            
            if (!$invalidRequiredField) {
                $insertProjectQuery = '
                    INSERT INTO projects
                    (name, description, creator, post_image_header, pre_image_header)
                    VALUES
                    (:projectName, :projectDescription, :userId, :postStormHeader, :preStormHeader);
                    ';
                $insertProjectParams = array(
                    'projectName' => $projectName,
                    'projectDescription' => $projectDescription,
                    'userId' => $userId,
                    'postStormHeader' => $postStormHeader,
                    'preStormHeader' => $preStormHeader
                );
                $newProjectId = run_prepared_query($DBH, $insertProjectQuery, $insertProjectParams, true);

                if (!empty($newProjectId)) {
                    if (!file_exists("images/projects/$newProjectId/tooltips")) {
                        if (!mkdir("images/projects/$newProjectId/tooltips", 0775, true)) {
                            $fileSystemUpdateFailure['createTooltipFolderFailure'] = 'The system could not create
                                a project tooltip folder.';
                        }
                        chmod("images/projects/$newProjectId", 0775);
						chmod("images/projects/$newProjectId/tooltips", 0775);
                    } else {
                        $files = glob("images/projects/$newProjectId/tooltips/*");
                        foreach ($files as $file) { // iterate files
                            if (is_file($file)) {
                                $unlinkResult = unlink($file); // delete file
                                if (!$unlinkResult) {
                                    $fileSystemUpdateFailure['clearExistingTooltipFolderFailure'] = 'The system could not clear the contents from an existing tooltip
                                            folder for a project of the same ID';
                                }
                            }
                        }
                    }
                    if (!$fileSystemUpdateFailure) {
                        header("Location: projectCreator.php?complete=1&projectId=$newProjectId");
                        exit;
                    }
                } else {
                    $databaseUpdateFailure['insertProjectQuery'] = '$tagUpdateQuery';
                }
            }
        
        if ($fileSystemUpdateFailure) {
            $errorLine .= '<span class = "error">File System Update Error:</span> The filesystem could not be updated for the new project. Please try again.<br>';
        }
        if ($invalidRequiredField) {
            foreach ($invalidRequiredField as $fieldId => $fieldTitle) {
                $errorLine .= '<span class = "error">Field Error:</span> "' . $fieldTitle . '" must have a value.<br>';
            }
        }
        if ($databaseUpdateFailure) {
            $errorLine .= '<span class = "error">Database Update Error:</span> The database could not be updated. Please try again.<br>';
        }
        $errorLine = rtrim($errorLine, '<br>');
        if (!empty($errorLine)) {
            $errorLine = rtrim($errorLine, '<br>');
            $errorHTML = '
            <h3 class = "error">Errors Detected</h3>
            <p>' . $errorLine . '</p>';
        }
    }

    $pageContentHTML = '
            <h2>Project Details</h2>
            <p>The first step in building a new iCoast project is to define the basic details of the project. This is
            done using the text boxes below. The information supplied here is not final. Changes and corrections
            can be made later using the "Project Editor" link found in the Admin navigation panel to the left.
            </p>' .
            $errorHTML . '
            <form method="post" id="newProjectDetailsForm" autocomplete="off">
                <div class = "formFieldRow">
                    <label for = "projectName" title = "This is the text used throughout iCoast to inform the user and admins of what project they are working on. It is also used in all selection boxes where a user picks a project to work on. Keep the text here simple and short. Using an event name such as Hurricane Sandy is best. There is 50 character limit.">Project Name * :</label>
                    <input type = "text" id = "projectName" class = "formInputStyle" name = "projectName" maxlength = "50" value = "' . htmlspecialchars($projectName) . '" />
                </div>

                <div class = "formFieldRow">
                    <label for = "projectDescription" title = "This text is for admin reference only to help explain the details of the project. The content of this field is not shared with standard users. 500 character limit.">Project Description:</label>
                    <textarea id = "projectDescription" class = "formInputStyle" name = "projectDescription" maxlength = "500">' . htmlspecialchars($projectDescription) . '</textarea>
                </div>
                <div class="formFieldRow">
                    <label for="postStormHeader" title="This text is used as part of the header text for the post storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like \'POST-STORM: After Hurricane Sandy\'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">Post-Event Image Header Text * :</label>
                    <input type="text" id="postStormHeader" class="formInputStyle" name="postStormHeader" maxlength="50" value="' . htmlspecialchars($postStormHeader) . '" />
                </div>

                <div class="formFieldRow">
                    <label for="preStormHeader" title="This text is used as part of the header text for the pre storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like \'PRE-STORM: Before Hurricane Sandy\'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">Pre-Event Image Header Text * :</label>
                    <input type="text" id="preStormHeader" class="formInputStyle" name="preStormHeader" maxlength="50" value="' . htmlspecialchars($preStormHeader) . '" />
                </div>
                <p>* denotes a required field</p>
                <input type = "hidden" name = "create" value="1" />
                <div class = "createFormSubmissionControls">
                    <button type = "submit" class = "clickableButton enlargedClickableButton" name = "formSubmitted" value="1" title = "This will send the inputs to the database. Ensure all fields are correct before clicking this button.">Create Project</button>
                </div>
            </form>';
}

if ($completeFlag) {
    $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
    if ($projectMetadata) {
        if ($projectMetadata['is_complete'] == 1) {
            header('Location: projectCreator.php?error=invalidProject');
            exit;
        }
    } else {
        header('Location: projectCreator.php?error=MissingProjectId');
        exit;
    }
//    print 'Check Stage';
    project_creation_stage($projectMetadata['project_id'], true);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => DEFAULT CODE
if (is_null($completeFlag) && is_null($createFlag) && is_null($deleteFlag)) {

    $completeProjectSelectionHTML = '';

    if ($errorToken) {
        switch ($errorToken) {
            case 'InvalidProject':
                $errorHTML = '
                    <p class="error">You do not have permission to manipulate the requested project or the project is
                        not in a state that permits the requested type of manipulation.</p>';
                break;
            case 'DeleteFailed':
                $errorHTML = '
                    <p class="error">The attempt to delete the project failed due to a database error.</p>';
                break;
            case 'MissingProjectId':
                $errorHTML = '
                    <p class="error">The attempted action failed due to a missing or invalid iCoast project id.</p>';
                break;
            case 'MissingCollectionId':
                $errorHTML = '
                    <p class="error">The attempted action failed due to a missing or invalid iCoast collection id.</p>';
                break;
            case 'DirCreateError':
                $errorHTML = '
                    <p class="error">Import failed. The temporary image directory could not be created.</p>';
                break;
            case 'NoSession':
                $errorHTML = '
                    <p class="error">Import failed. The data session could not be found. Please try again.</p>';
                break;
            case 'MissingCollectionType':
                $errorHTML = '
                    <p class="error">Import failed. The collection type information was missing from the request.</p>';
                break;
            case 'InvalidCollectionType':
                $errorHTML = '
                    <p class="error">Import failed. The collection type specified in the request is invalid.</p>';
                break;
            case 'MissingImportKey':
                $errorHTML = '
                    <p class="error">Import failed. The import key was missing from the request.</p>';
                break;
            case 'InvalidMissingUser':
                $errorHTML = '
                    <p class="error">Sequencing failed due to bad or missing user credentials.</p>';
                break;
            case 'UpdateFailed':
                $errorHTML = '
                    <p class="error">The last database operation failed. Please try again.</p>';
                break;
            case 'InvalidOperation':
                $errorHTML = '
                    <p class="error">The action you requested is invalid.</p>';
            default:
                $errorHTML = '';
        }
    }

    $projectsInProgressQuery = "
        SELECT project_id, name, description
        FROM projects
        WHERE creator = $userId
            AND is_complete = 0
        ORDER BY name ASC";
    $projectsInProgressResult = run_prepared_query($DBH, $projectsInProgressQuery);
    $projectsInProgress = $projectsInProgressResult->fetchAll(PDO::FETCH_ASSOC);

    if (count($projectsInProgress) > 0) {

        $projectsInProgressSelectOptionsHTML = '';
        foreach ($projectsInProgress as $projectInProgress) {
            $projectsInProgressSelectOptionsHTML .= '
            <option value = "' . $projectInProgress['project_id'] . '" title = "' . $projectInProgress['description'] . '">' .
                    $projectInProgress['name'] .
                    '</option>';
        }
        $projectInProgressButtonHTML = '
            <button class = "clickableButton enlargedClickableButton projectCreatorButton" type = "submit" name = "complete" value="1" title = "Select this to complete the project chosen in the dropdown menu above.">
                Complete the Selected Project
            </button>
            <button  class = "clickableButton enlargedClickableButton projectCreatorButton" type = "submit" name = "delete" value="1" title = "Select this to delete the project chosen in the dropdown menu above.">
                Delete the Selected Project
            </button>';
        $projectInProgressProjectSelectionHTML = '
            <label for = "projectSelect">Project to Complete: </label>
            <select id = "projectSelect" class = "formInputStyle" name = "projectId">' .
                $projectsInProgressSelectOptionsHTML .
                '</select>
            <br />';
        $completeProjectSelectionHTML = '
            <h2>Complete or Delete a Partially Built Project</h2>
            <form method = "post" action = "projectCreator.php" autocomplete = "off">' .
                $projectInProgressProjectSelectionHTML .
                $projectInProgressButtonHTML .
                '</form>';
    }
    $pageContentHTML = $errorHTML . $message . '
            <h2>Create a New Project</h2>
            <form method = "post" action = "projectCreator.php" autocomplete = "off">
                <button type = "submit" class = "clickableButton enlargedClickableButton projectCreatorButton" name = "create" value = "1">Create a New Project</button>
            </form>' .
            $completeProjectSelectionHTML;
}



$embeddedCSS .= <<<EOL
    .clickableButton {
        width: 200px;
    }

    .projectCreatorButton,
    #projectSelect {
        width: 300px;
    }
EOL;

$jQueryDocumentDotReadyCode .= "$('#projectSelect').prop('selectedIndex', -1);\n\r";
