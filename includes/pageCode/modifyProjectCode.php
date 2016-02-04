<?php

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
$commitChanges = filter_input(INPUT_POST, 'commitChanges');
$cancelChanges = filter_input(INPUT_POST, 'cancelChanges');
$newProjectName = filter_input(INPUT_POST, 'newProjectName');
$newProjectDescription = filter_input(INPUT_POST, 'newProjectDescription');
$newPostStormHeader = filter_input(INPUT_POST, 'newPostStormHeader');
$newPreStormHeader = filter_input(INPUT_POST, 'newPreStormHeader');


$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
        $projectMetadata ['is_complete'] == 1) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 50) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$errorHTML = '';
$errorLine = '';

if (isset($commitChanges) &&
        isset($newProjectName) &&
        isset($newProjectDescription) &&
        isset($newPostStormHeader) &&
        isset($newPreStormHeader)) {

    $invalidRequiredField = false;

    if (empty($newProjectName)) {
        $invalidRequiredField[] = 'newProjectName';
    }

    if (empty($newPostStormHeader)) {
        $invalidRequiredField[] = 'newPostStormHeader';
    }

    if (empty($newPreStormHeader)) {
        $invalidRequiredField[] = 'newPreStormHeader';
    }

    if (!$invalidRequiredField) {
        if ($newProjectName == $projectMetadata['name'] &&
                $newProjectDescription == $projectMetadata['description'] &&
                $newPostStormHeader == $projectMetadata['post_image_header'] &&
                $newPreStormHeader == $projectMetadata['pre_image_header']) {
            header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=noChange');
            exit;
        }
        $updateProjectQuery = "
                UPDATE projects
                SET name = :name,
                    description = :description,
                    post_image_header = :postImageHeader,
                    pre_image_header = :preImageHeader
                WHERE project_id = :projectId
                LIMIT 1";
        $updateProjectParams = array(
            'name' => $newProjectName,
            'description' => $newProjectDescription,
            'postImageHeader' => $newPostStormHeader,
            'preImageHeader' => $newPreStormHeader,
            'projectId' => $projectMetadata['project_id']
        );
        $updateProjectResult = run_prepared_query($DBH, $updateProjectQuery, $updateProjectParams);
        if ($updateProjectResult->rowCount() == 1) {
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
} else if (isset($cancelChanges)) {
    header('Location: reviewProject.php?projectId=' . $projectMetadata['project_id'] . '&updateResult=noChange');
    exit;
}

$displayName = (isset($newProjectName)) ? htmlspecialchars($newProjectName) : htmlspecialchars($projectMetadata['name']);
$displayDescription = (isset($newProjectDescription)) ? restoreSafeHTMLTags(htmlspecialchars($newProjectDescription)) : restoreSafeHTMLTags(htmlspecialchars($projectMetadata['description']));
$displayPreStormHeader = (isset($newPreStormHeader)) ? htmlspecialchars($newPreStormHeader) : htmlspecialchars($projectMetadata['pre_image_header']);
$displayPostStormHeader = (isset($newPostStormHeader)) ? htmlspecialchars($newPostStormHeader) : htmlspecialchars($projectMetadata['post_image_header']);
$modifyPageHTML = <<<EOL
            <h2>Edit Project Details</h2>
            <p>Use this screen to modify the details of your new project.</p>
            <p>Entries in these fields are publicly viewable. The Name field is used in drop-down boxes,
                description is used in tooltips, and the headers are presented above the images in the
                Classification page. Tooltips shown by hovering over the names of each field provide further details.
            $errorHTML
            <form method="post" id="newProjectDetailsForm" autocomplete="off">
                <div class = "formFieldRow">
                    <label for = "newProjectName"
                            title = "This is the text used throughout iCoast to inform the user and admins of what project they are working on. It is also used in all selection boxes where a user picks a project to work on. Keep the text here simple and short. Using an event name such as Hurricane Sandy is best. There is 50 character limit.">
                        Project Name * :
                    </label>
                    <input type = "textbox" id = "newProjectName" class = "formInputStyle" name = "newProjectName" maxlength = "50" value = "$displayName" />
                </div>

                <div class = "formFieldRow">
                    <label for = "newProjectDescription"
                            title = "This text is for admin reference only to help explain the details of the project. The content of this field is not shared with standard users. 500 character limit.">
                        Project Description:
                    </label>
                    <textarea id = "newProjectDescription" class = "formInputStyle" name = "newProjectDescription" maxlength = "500">$displayDescription</textarea>
                </div>
                <div class="formFieldRow">
                    <label for="newPostStormHeader"
                            title="This text is used as part of the header text for the post storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like \'POST-STORM: After Hurricane Sandy\'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">
                        Post Event Image Header Text * :
                    </label>
                    <input type="textbox" id="newPostStormHeader" class="formInputStyle" name="newPostStormHeader" maxlength="50" value="$displayPostStormHeader" />
                </div>

                <div class="formFieldRow">
                    <label for="newPreStormHeader"
                            title="This text is used as part of the header text for the pre storm image on the classification page. It is followed by the date and time of image capture. Short descriptive headers are best. Consider something like \'PRE-STORM: Before Hurricane Sandy\'. There is a 50 character limit. Always check the text displays correctly on the classification page after editing this field.">
                        Pre Event Image Header Text * :
                    </label>
                    <input type="textbox" id="newPreStormHeader" class="formInputStyle" name="newPreStormHeader" maxlength="50" value="$displayPreStormHeader" />
                </div>
                <p>* denotes a required field</p>
                <button type="submit" class="clickableButton enlargedClickableButton" name="commitChanges">
                    Submit Changes
                </button>
                <button type="submit" class="clickableButton enlargedClickableButton" name="cancelChanges">
                    Cancel Changes
                </button>
            </form>
EOL;
