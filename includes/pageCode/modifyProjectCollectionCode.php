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
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$projectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);
$editCollection = filter_input(INPUT_GET, 'edit');
$deleteCollection = filter_input(INPUT_GET, 'delete');


$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
    $projectMetadata ['is_complete'] == 1
) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}
$projectIdParam['projectId'] = $projectMetadata['project_id'];

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 50) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}


if (isset($editCollection) && ($editCollection == 'pre' || $editCollection == 'post')) {
    $collectionType = $editCollection;
} else if (isset($deleteCollection) && ($deleteCollection == 'pre' || $deleteCollection == 'post')) {
    $collectionType = $deleteCollection;
} else {
    header('Location: projectCreator.php?error=InvalidOperation');
    exit;
}
$ucCollectionType = ucfirst($collectionType);


if (isset($editCollection)) {

    $commitChanges = filter_input(INPUT_POST, 'commitChanges');
    $newCollectionName = filter_input(INPUT_POST, 'collectionName');
    $newCollectionDescription = filter_input(INPUT_POST, 'collectionDescription');
    $canelChanges = filter_input(INPUT_POST, 'cancelChanges');

    if (!is_null($projectMetadata[$collectionType . '_collection_id'])) {
        header('Location: projectCreator.php?error=InvalidCollectionType');
        exit;
    }

    $oldCollectionMetadataQuery = "
                    SELECT *
                    FROM import_collections
                    WHERE parent_project_id = :projectId
                        AND collection_type = '$collectionType'";
    $oldCollectionMetadataResult = run_prepared_query($DBH, $oldCollectionMetadataQuery, $projectIdParam);
    $oldCollectionMetadata = $oldCollectionMetadataResult->fetch(PDO::FETCH_ASSOC);
    $oldCollectionName = (isset($newCollectionName)) ? htmlspecialchars($newCollectionName) : htmlspecialchars($oldCollectionMetadata['name']);
    $oldCollectionDescription = (isset($newCollectionDescription)) ? restoreSafeHTMLTags(htmlspecialchars($newCollectionDescription)) : restoreSafeHTMLTags(htmlspecialchars($oldCollectionMetadata['description']));

    $errorHTML = '';
    $errorLine = '';

    if (isset($commitChanges) &&
        isset($newCollectionName) &&
        isset($newCollectionDescription)
    ) {


        $invalidRequiredField = false;
        if (empty($newCollectionName)) {
            $invalidRequiredField[] = 'collectionName';
        }

        if (!$invalidRequiredField) {
            if ($newCollectionName == $oldCollectionMetadata['name'] &&
                $newCollectionDescription == $oldCollectionMetadata['description']
            ) {
                header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=noChange');
                exit;
            }
            $updateCollectionQuery = "
                UPDATE import_collections
                SET name = :name,
                    description = :description
                WHERE parent_project_id = :projectId
                    AND collection_type = :collectionType
                LIMIT 1";
            $updateCollectionParams = array(
                'name' => $newCollectionName,
                'description' => $newCollectionDescription,
                'projectId' => $projectMetadata['project_id'],
                'collectionType' => $collectionType
            );
            $updateCollectionResult = run_prepared_query($DBH, $updateCollectionQuery, $updateCollectionParams);
            if ($updateCollectionResult->rowCount() == 1) {
                header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=success');
            } else {
                header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=failed');
            }
            exit;
        }
        if ($invalidRequiredField) {
            foreach ($invalidRequiredField as $fieldTitle) {
                $errorLine .= '<span class = "error">Field Error:</span> "' . $fieldTitle . '" must have a value.<br>';
            }
        }
        $errorLine = rtrim($errorLine, '<br>');
        if (!empty($errorLine)) {
            $errorLine = rtrim($errorLine, '<br>');
            $errorHTML = '
            <h3 class = "error">Errors Detected</h3>
            <p>' . $errorLine . '</p>';
        }
    } else if (isset($canelChanges)) {
        header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=noChange');
        exit;
    }

    $modifyPageHTML = <<<EOL
            <h2>Edit $ucCollectionType-Event Collection Details</h2>
            <h3>"$oldCollectionName"</h3>
            <p>Use this screen to modify the name or description of your new collection. Only iCoast
                administrators can see such collection details but descriptive names and descriptions can help
                understand a collection's content when it is being considered for reuse later on.</p>
            $errorHTML
            <form method="post" id="modifyCollectionForm" autocomplete="off" >
                <div class="formFieldRow">
                    <label for="collectionName" title="This is the text used to summarize  this collection within the iCoast Admin interface. It is used in all selection boxes where an admin can select a collection. Keep the text here simple and short. Using a storm name and date such as 'Post Hurricane Sandy Images 2012' is best. There is 50 character limit. Thiis text is not publicly displayed.">$ucCollectionType-Event Collection Name * :</label>
                    <input type="textbox" id="collectionName" class="clickableButton" name="collectionName" maxlength="50" value="$oldCollectionName" />
                </div>
                <div class="formFieldRow">
                    <label for="collectionDescription" title="This text explains the details of the collection and may be more verbose than the title. 500 character limit. This text is not publicly displayed.">$ucCollectionType-Event Collection Description:</label>
                    <textarea id="collectionDescription" class="clickableButton" name="collectionDescription" maxlength="500">$oldCollectionDescription</textarea>
                </div>
                <button type="submit" class="clickableButton enlargedClickableButton" name="commitChanges">
                    Submit Changes
                </button>
                <button type="submit" class="clickableButton enlargedClickableButton" name="cancelChanges">
                    Cancel Changes
                </button>
            </form>
EOL;
} else if (isset($deleteCollection)) {

    $confirmDelete = filter_input(INPUT_POST, 'confirmDelete');
    $cancelDelete = filter_input(INPUT_POST, 'cancelDelete');

    if (isset($confirmDelete)) {

        $deleteError = array();

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

        if (is_null($projectMetadata[$collectionType . '_collection_id'])) { // Imported project actions
            $oldCollectionMetadataQuery = "
                    SELECT *
                    FROM import_collections
                    WHERE parent_project_id = :projectId
                        AND collection_type = '$collectionType'";
            $oldCollectionMetadataResult = run_prepared_query($DBH, $oldCollectionMetadataQuery, $projectIdParam);
            $oldCollectionMetadata = $oldCollectionMetadataResult->fetch(PDO::FETCH_ASSOC);
            $oldCollectionId = $oldCollectionMetadata['import_collection_id'];
            $oldCollectionIdParam['collectionId'] = $oldCollectionId;
            $oldCollectionName = htmlspecialchars($oldCollectionMetadata['name']);


// Delete collection images
            if (file_exists("images/temporaryImportFolder/$oldCollectionId")) {
                if (file_exists("images/temporaryImportFolder/$oldCollectionId/main")) {
                    $files = glob("images/temporaryImportFolder/$oldCollectionId/main/*");
                    foreach ($files as $file) { // iterate files
                        if (is_file($file)) {
                            $unlinkResult = unlink($file); // delete file
                            if (!$unlinkResult) {
                                $deleteError[] = "Error deleting image file (collection: $oldCollectionId, file: $file).";
                            }
                        }
                    }
                    $rmMainResult = rmdir("images/temporaryImportFolder/$oldCollectionId/main");
                    if (!$rmMainResult) {
                        $deleteError[] = "Error deleting main folder (collection: $oldCollectionId).";
                    }
                }
                if (file_exists("images/temporaryImportFolder/$oldCollectionId/thumbnails")) {
                    $files = glob("images/temporaryImportFolder/$oldCollectionId/thumbnails/*");
                    foreach ($files as $file) { // iterate files
                        if (is_file($file)) {
                            $unlinkResult = unlink($file); // delete file
                            if (!$unlinkResult) {
                                $deleteError[] = "Error deleting thumbnail file (collection: $oldCollectionId, file: $file).";
                            }
                        }
                    }
                    $rmThumbsResult = rmdir("images/temporaryImportFolder/$oldCollectionId/thumbnails");
                    if (!$rmThumbsResult) {
                        $deleteError[] = "Error deleting thumbnail folder (collection: $oldCollectionId).";
                    }
                }
                $rmCollectionResult = rmdir("images/temporaryImportFolder/$oldCollectionId");
                if (!$rmCollectionResult) {
                    $deleteError[] = "Error deleting collection folder (collection: $oldCollectionId).";
                }
            }

            $imagesToDeleteQuery = '
                        SELECT COUNT(*)
                        FROM import_images
                        WHERE import_collection_id = :collectionId';
            $imagesToDeleteResult = run_prepared_query($DBH, $imagesToDeleteQuery, $oldCollectionIdParam);
            $imagesToDeleteCount = $imagesToDeleteResult->fetchCOlumn();
            if ($imagesToDeleteCount > 0) {
                $deleteImportedImagesQuery = "
                        DELETE FROM import_images
                        WHERE import_collection_id = :collectionId
                        LIMIT $imagesToDeleteCount
                        ";
                $deleteImportedImagesResult = run_prepared_query($DBH, $deleteImportedImagesQuery, $oldCollectionIdParam);
                if ($deleteImportedImagesResult->rowCount() != $imagesToDeleteCount) {
                    $deleteError[] = "Error deleting import_images rows (collection: $oldCollectionId).";
                }
            }

            $collectionsToDeleteQuery = '
            SELECT COUNT(*)
            FROM import_collections
            WHERE import_collection_id = :collectionId';
            $collectionsToDeleteResult = run_prepared_query($DBH, $collectionsToDeleteQuery, $oldCollectionIdParam);
            $collectionsToDelete = $collectionsToDeleteResult->rowCount();
            if ($collectionsToDelete > 0) {
                $deleteImportCollectionsQuery = '
                        DELETE FROM import_collections
                        WHERE import_collection_id = :collectionId
                        LIMIT 1
                        ';
                $deleteImportCollectionsResult = run_prepared_query($DBH, $deleteImportCollectionsQuery, $oldCollectionIdParam);
                if ($deleteImportCollectionsResult->rowCount() != $collectionsToDelete) {
                    $deleteError[] = "Error deleting import_collections rows (collection: $oldCollectionId).";
                }
            }

            $updateProjectsQuery = '
            UPDATE projects
            SET import_complete = 0,
                matching_progress = NULL
            WHERE project_id = :projectId
            ';
            $updateProjectsResult = run_prepared_query($DBH, $updateProjectsQuery, $projectIdParam);
            if ($updateProjectsResult->rowCount() != 1) {
                $deleteError[] = "Error updating project with new status (project: {$projectMetadata['project_id']}).";
            }
        } else { // Existing Project Remove Actions
            $oldCollectionMetadata = retrieve_entity_metadata($DBH, $projectMetadata[$collectionType . '_collection_id'], 'collection');
            $oldCollectionName = htmlspecialchars($oldCollectionMetadata['name']);

            $updateProjectsQuery = "
            UPDATE projects
            SET {$collectionType}_collection_id = NULL,
                import_complete = 0,
                matching_progress = NULL
            WHERE project_id = :projectId
            ";
            $updateProjectsResult = run_prepared_query($DBH, $updateProjectsQuery, $projectIdParam);
            if ($updateProjectsResult->rowCount() != 1) {
                $deleteError[] = "Error updating project with new status (project: {$projectMetadata['project_id']}).";
            }
        }
        if (count($deleteError) > 0) {

            if (is_null($projectMetadata[$collectionType . '_collection_id'])) { // Imported Error Response
                $modifyPageHTML = <<<EOL
                    <h2>Delete New $ucCollectionType-Event Collection</h2>
                    <h3>"$oldCollectionName"</h3>
                    <p class="error">There were problems deleting some or all components of the collection
                        from the project.<br>
                            Detected Errors:';
EOL;
            } else { // Existing Error Response
                $modifyPageHTML = <<<EOL
                    <h2>Remove Existing $ucCollectionType-Event Collection</h2>
                    <h3>"$oldCollectionName"</h3>
                    <p class="error">There were problems removing some or all components of the collection
                        from the project.<br>
                            Detected Errors:';
EOL;
            }

            foreach ($deleteError as $error) {
                $modifyPageHTML .= "<br>$error";
            }
            $modifyPageHTML .= <<<EOL
                </p>
                <p>To recover the project please note the failure details and contact an iCoast developer for
                    assistance, or you may attempt to delete the entire project from the Project Creator menu
                    and try again from scratch.</p>
                <form id="modifyCollectionForm" autocomplete="off" action"projectCreator.php">
                    <button type="submit" class="clickableButton enlargedClickableButton" style="width: 200px;">
                        OK
                    </button>
                </form>
EOL;
        } else { // No errors
            if (is_null($projectMetadata[$collectionType . '_collection_id'])) { // Imported Error Response
                $modifyPageHTML = <<<EOL
                    <h2>Delete New $ucCollectionType-Event Collection</h2>
                    <h3>"$oldCollectionName"</h3>
                    <p>The collection was sucessfully deleted.</p>
EOL;
            } else { // Existing Error Response
                $modifyPageHTML = <<<EOL
                    <h2>Remove Existing $ucCollectionType-Event Collection</h2>
                    <h3>"$oldCollectionName"</h3>
                    <p>The collection was sucessfully removed.</p>
EOL;
            }
            $modifyPageHTML .= <<<EOL
                    <form id="modifyCollectionForm" autocomplete="off" action="projectCreator.php">
                        <input type="hidden" name="projectId" value="{$projectMetadata['project_id']}" />
                        <button type="submit" class="clickableButton enlargedClickableButton" name="complete" style="width: 200px;">
                            OK
                        </button>
                    </form>
EOL;
        }
    } else if (isset($cancelDelete)) {
        header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=noChange');
        exit;
    } else {
        if (is_null($projectMetadata[$collectionType . '_collection_id'])) { // Imported Collection Confimration
            $oldCollectionMetadataQuery = "
                    SELECT *
                    FROM import_collections
                    WHERE parent_project_id = :projectId
                        AND collection_type = '$collectionType'";
            $oldCollectionMetadataResult = run_prepared_query($DBH, $oldCollectionMetadataQuery, $projectIdParam);
            $oldCollectionMetadata = $oldCollectionMetadataResult->fetch(PDO::FETCH_ASSOC);
            $oldCollectionName = htmlspecialchars($oldCollectionMetadata['name']);

            $modifyPageHTML = <<<EOL
                <h2>Delete $ucCollectionType-Event Collection </h2>
                <h3>"$oldCollectionName"</h3>
                <p>You have requested to delete the "$oldCollectionName" collection from your project and iCoast.
                    Once complete, this action cannot be undone. By confirming this request the collection match data will also be
                    cleared and you will be required to either pick another existing collection, or upload, refine, and
                    sequence a new collection before your project can be made public.</p>
                <p class="error">Are you sure you want to delete the "$oldCollectionName" collection?</p>
                <form method="post" id="modifyCollectionForm" autocomplete="off" >
                    <button type="submit" class="clickableButton enlargedClickableButton" name="confirmDelete">
                        Confirm Delete
                    </button>
                    <button type="submit" class="clickableButton enlargedClickableButton" name="cancelDelete">
                        Cancel Delete
                    </button>
                </form>
EOL;
        } else { // Existing Collection Confimration
            $oldCollectionMetadata = retrieve_entity_metadata($DBH, $projectMetadata[$collectionType . '_collection_id'], 'collection');
            $oldCollectionName = htmlspecialchars($oldCollectionMetadata['name']);

            $modifyPageHTML = <<<EOL
                <h2>Remove $ucCollectionType-Event Collection </h2>
                <h3>"$oldCollectionName"</h3>
                <p>You have requested to remove the "$oldCollectionName" existing collection from your project.
                    By confirming this request the collection match data for your project will also be cleared and
                    you will be required to either pick another existing collection, or upload, refine, and
                    sequence a new collection before your project can be made public. As this is an existing collection
                    it will remain available in iCoast for other projects to use.</p>
                <p class="error">Are you sure you want to remove the "$oldCollectionName" collection?</p>
                <form method="post" id="modifyCollectionForm" autocomplete="off" >
                    <button type="submit" class="clickableButton enlargedClickableButton" name="confirmDelete">
                        Confirm Removal
                    </button>
                    <button type="submit" class="clickableButton enlargedClickableButton" name="cancelDelete">
                        Cancel Removal
                    </button>
                </form>
EOL;
        }
    }
} else {
    header('Location: projectCreator.php?error=InvalidOperation');
    exit;
}