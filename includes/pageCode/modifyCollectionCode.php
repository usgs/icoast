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

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);


$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    header('Location: collectionCreator.php?error=MissingCollectionId');
    exit;
} else if ($collectionMetadata['creator'] != $userId) {
    header('Location: collectionCreator.php?error=InvalidCollection');
    exit;
}
$collectionIdParam['collectionId'] = $collectionId;

//$importStatus = project_creation_stage($projectMetadata['project_id']);
//if ($importStatus != 50) {
//    header('Location: projectCreator.php?error=InvalidProject');
//    exit;
//}


$commitChanges = filter_input(INPUT_POST, 'commitChanges', FILTER_VALIDATE_BOOLEAN);
$cancelChanges = filter_input(INPUT_POST, 'cancelChanges', FILTER_VALIDATE_BOOLEAN);
    $newCollectionName = filter_input(INPUT_POST, 'collectionName');
    $newCollectionDescription = filter_input(INPUT_POST, 'collectionDescription');


$oldCollectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
$oldCollectionName = ($newCollectionName) ? htmlspecialchars($newCollectionName) : htmlspecialchars($oldCollectionMetadata['name']);
    $oldCollectionDescription = (isset($newCollectionDescription)) ? restoreSafeHTMLTags(htmlspecialchars($newCollectionDescription)) : restoreSafeHTMLTags(htmlspecialchars($oldCollectionMetadata['description']));

    $errorHTML = '';
    $errorLine = '';

if ($commitChanges &&
            isset($newCollectionName) &&
            isset($newCollectionDescription)) {


        $invalidRequiredField = false;
        if (empty($newCollectionName)) {
            $invalidRequiredField[] = 'collectionName';
        }

        if (!$invalidRequiredField) {
            if ($newCollectionName == $oldCollectionMetadata['name'] &&
                    $newCollectionDescription == $oldCollectionMetadata['description']) {
                header('Location: reviewCollection.php?collectionId=' . $collectionId . '&updateResult=noChange');
                exit;
            }
            $updateCollectionQuery = "
                UPDATE import_collections
                SET name = :name,
                    description = :description
                WHERE import_collection_id = :collectionId
                LIMIT 1";
            $updateCollectionParams = array(
                'name' => $newCollectionName,
                'description' => $newCollectionDescription,
                'collectionId' => $collectionId
            );
            $updateCollectionResult = run_prepared_query($DBH, $updateCollectionQuery, $updateCollectionParams);
            if ($updateCollectionResult->rowCount() == 1) {
                header('Location: reviewCollection.php?collectionId=' . $collectionId . '&updateResult=success');
            } else {
                header('Location: reviewCollection.php?collectionId=' . $collectionId . '&updateResult=failed');
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
} else if ($cancelChanges) {
    header('Location: reviewCollection.php?collectionId=' . $collectionId . '&updateResult=noChange');
        exit;
    }

$modifyPageHTML = <<<HTML
            <h2>Edit Collection Details</h2>
            <h3>"$oldCollectionName"</h3>
            <p>Use this screen to modify the name or description of your new collection. Only iCoast
                administrators can see such collection details but descriptive names and descriptions can help
                understand a collection's content when it is being considered for use in a project.</p>
            $errorHTML
            <form method="post" id="modifyCollectionForm" autocomplete="off" >
                <div class="formFieldRow">
                    <label for="collectionName" title="This is the text used to summarize  this collection within the iCoast Admin interface. It is used in all selection boxes where an admin can select a collection. Keep the text here simple and short. Using a storm name and date such as 'Post Hurricane Sandy Images 2012' is best. There is 50 character limit. This text is not publicly displayed.">Collection Name * :</label>
                    <input type="textbox" id="collectionName" class="clickableButton" name="collectionName" maxlength="50" value="$oldCollectionName" />
                </div>
                <div class="formFieldRow">
                    <label for="collectionDescription" title="This text explains the details of the collection and may be more verbose than the title. 500 character limit. This text is not publicly displayed.">Collection Description:</label>
                    <textarea id="collectionDescription" class="clickableButton" name="collectionDescription" maxlength="500">$oldCollectionDescription</textarea>
                </div>
                <button type="submit" class="clickableButton enlargedClickableButton" name="commitChanges" value="1">
                    Submit Changes
                </button>
                <button type="submit" class="clickableButton enlargedClickableButton" name="cancelChanges" value="1">
                    Cancel Changes
                </button>
            </form>
HTML;
