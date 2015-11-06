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
ini_set('auto_detect_line_endings', true);
session_start();

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

////////////////////////////////////////////////////////////////////////////////
// Focus Code
$setFocusedProjectFlag = filter_input(INPUT_GET, 'setFocusedProject');
$newFocusProjectId = filter_input(INPUT_GET, 'newFocusProjectId', FILTER_VALIDATE_INT);
$changeFocusHTML = '';
$changeFocusResultHTML = '';


if (isset($setFocusedProjectFlag) && $newFocusProjectId) {
    $newFocusProjectMetadata = retrieve_entity_metadata($DBH, $newFocusProjectId, 'project');
    if ($newFocusProjectMetadata &&
            $newFocusProjectMetadata['is_complete'] == 1 &&
            $newFocusProjectMetadata['is_public']) {
        $updateFocusProjectQuery = "
            UPDATE system
            SET home_page_project = :newFocusProjectId
            WHERE id = 0;
            ";
        $updateFocusProjectParams['newFocusProjectId'] = $newFocusProjectId;
        $updateFocusProjectResult = run_prepared_query($DBH, $updateFocusProjectQuery, $updateFocusProjectParams);
        if ($updateFocusProjectResult->rowCount() == 1) {
            $changeFocusResultHTML = '<p class="redHighlight">The home page focus has been changed to the ' .
                    $newFocusProjectMetadata['name'] . ' project.</p>';
        } else {
            $changeFocusResultHTML = '<p class="redHighlight">The home page focus could not be changed due to a database update error. 
                Please see the iCoast developer for assistance.</p>';
        }
    } else {
        $changeFocusResultHTML = '<p class="redHighlight">The home page focus could not be changed as the specified
                project could not be found or was not in a valid state to be set as the focus.</p>';
    }
}

$currentProjectIdInFocusQuery = "
    SELECT project_id, name, description
    FROM projects p
    INNER JOIN system s ON p.project_id = s.home_page_project
    WHERE s.id = 0
    ";

$currentProjectIdInFocusResult = run_prepared_query($DBH, $currentProjectIdInFocusQuery);
$currentProjectIdInFocus = $currentProjectIdInFocusResult->fetch();

$enabledProjectsQuery = "
    SELECT project_id, name, description
    FROM projects
    WHERE
        is_complete = 1 &&
        is_public = 1
    ";
$enabledProjectsResult = run_prepared_query($DBH, $enabledProjectsQuery);
$enabledProjects = $enabledProjectsResult->fetchAll();
$projectsAvailableForFocusOptions = '';
if (count($enabledProjects) > 1) {
    foreach ($enabledProjects as $enabledProject) {
        if ($enabledProject['project_id'] == $currentProjectIdInFocus['project_id']) {
            continue;
        }
        $projectsAvailableForFocusOptions .= "
            <option value=\"{$enabledProject['project_id']}\" title=\"{$enabledProject['description']}\"> {$enabledProject['name']}</option>";
    }
    $changeFocusHTML = <<<EOL
        <p>The project you choose here will become the source for the map and statistics that
            are displayed on the iCoast Home page.</p>
        <p>No data is lost if the focus is changed and further alerations to the focused project can be made at any time.</p>
        <p>The current project in focus is the <span class="userData">{$currentProjectIdInFocus['name']}</span> project.<p>
        <form autocomplete="off" method="get" action="systemEditor.php">
            <label for="projectSelect">Change the iCoast focused project to: </label>
            <select id="projectSelect" class="formInputStyle" name="newFocusProjectId">
                $projectsAvailableForFocusOptions
            </select>
            <br>
            <button class="clickableButton enlargedClickableButton" name="setFocusedProject" type="submit"> Set The Focused Project </button>
        </form>            
EOL;
} else {
    $changeFocusHTML = <<<EOL
        <p>The current project in focus is the <span class="userData">{$currentProjectIdInFocus['name']}</span> project.</p>
        <p>As the {$currentProjectIdInFocus['name']} project is the only project publicly available in iCoast at this time the home page 
            focus cannot be changed. To change the iCoast focussed project you must first create a new project or enable a currently 
            disabled project.</p>
EOL;
}

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
// What's New Code

$updateWhatsNewFlag = filter_input(INPUT_POST, 'updateWhatsNew', FILTER_VALIDATE_BOOLEAN);

$whatsNewResultHTML = '';

if ($updateWhatsNewFlag) {
    $whatsNewContent = filter_input(INPUT_POST, trim('whatsNewContent'));
    $whatsNewTitle = filter_input(INPUT_POST, trim('whatsNewTitle'));
    if ($whatsNewTitle && empty($whatsNewContent)) {
        $whatsNewResultHTML = '<p class="error">If a title is supplied then it must be accompanied by content.</p>';
    } else {
        $updateWhatsNewQuery = "
            UPDATE 
                system
            SET
                whats_new_title = :whatsNewTitle,
                whats_new_content = :whatsNewContent
            WHERE
                id = 0
        ";
        $updateWhatsNewParams = array(
            'whatsNewTitle' => $whatsNewTitle,
            'whatsNewContent' => $whatsNewContent
        );
        $updateWhatsNewResult = run_prepared_query($DBH, $updateWhatsNewQuery, $updateWhatsNewParams);
        
        if ($updateWhatsNewResult->rowCount() == 1) {
            $whatsNewResultHTML = "<p class=\"error\">The What's New details have been updated.</p>";
        } else {
            $whatsNewResultHTML = "<p class=\"error\">A problem occured while updating the Whats's New details. No changes have been made.</p>";
        }
    }
} else {
    $existingWhatsNewQuery = "
        SELECT
            whats_new_title,
            whats_new_content
        FROM
            system
        WHERE
            id = 0
    ";
    $existingWhatsNewResult = run_prepared_query($DBH, $existingWhatsNewQuery);
    $whatsNew = $existingWhatsNewResult->fetch();
    $whatsNewContent = $whatsNew['whats_new_content'];
    $whatsNewTitle = $whatsNew['whats_new_title'];
}
$safeWhatsNewContent = restoreSafeHTMLTags(htmlspecialchars($whatsNewContent), false);
$safeWhatsNewTitle = htmlspecialchars($whatsNewTitle);








$embeddedCSS = <<<EOL
    
   #projectSelect {
        width: 300px;
    }
        
    #whatsNewTitle {
        width: 600px;
    }
        
    .whatsNewEditor label {
        width: 25% !important;
    }
        
EOL;

$jQueryDocumentDotReadyCode = <<<EOL
        $('#clearWhatsNewFields').click(function() {
            $('#whatsNewContent').val('');
            $('#whatsNewTitle').val('');
        });
EOL;
        