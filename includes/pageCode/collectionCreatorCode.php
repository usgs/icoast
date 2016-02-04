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

$postCollectionId = filter_input(INPUT_POST, 'collectionId', FILTER_VALIDATE_INT);
$getCollectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);

$deleteFlag = filter_input(INPUT_POST, 'delete', FILTER_VALIDATE_BOOLEAN);
$cancelDeletionFlag = filter_input(INPUT_POST, 'cancelDeletion', FILTER_VALIDATE_BOOLEAN);
$confirmDeletionFlag = filter_input(INPUT_POST, 'confirmDeletion', FILTER_VALIDATE_BOOLEAN);
$reviewFlag = filter_input(INPUT_POST, 'review');
$postCompleteFlag = filter_input(INPUT_POST, 'complete', FILTER_VALIDATE_BOOLEAN);
$getCompleteFlag = filter_input(INPUT_GET, 'complete', FILTER_VALIDATE_BOOLEAN);
$errorToken = filter_input(INPUT_GET, 'error');

$collectionId = null;
if ($getCollectionId) {
    $collectionId = $getCollectionId;
} else if ($postCollectionId) {
    $collectionId = $postCollectionId;
}

$completeFlag = null;
if ($getCompleteFlag || $postCompleteFlag) {
    $completeFlag = 1;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => DELETE COLLECTION CODE
if ($deleteFlag && $collectionId) {
    if ($cancelDeletionFlag) {
        if ($reviewFlag) {
            header('Location: reviewCollection.php?collectionId=' . $collectionId);
        } else {
            header('Location: collectionCreator.php');
        }
        exit;
    }
    $collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
    if (empty($collectionMetadata)) {
        header('Location: collectionCreator.php?error=invalidCollection');
    }
    if ($collectionMetadata &&
        $collectionMetadata['creator'] == $userId
    ) {


        if ($confirmDeletionFlag) {
            $deleteError = array();
            $importCollectionIdParam['collectionId'] = $collectionId;

            if ($collectionMetadata['import_status_message'] == 'Processing' ||
                $collectionMetadata['import_status_message'] == 'Sleeping'
            ) {
                $abortImportQuery = '
                            UPDATE import_collections
                            SET user_abort_import_flag = 1
                            WHERE import_collection_id = :collectionId';
                $abortImportResult = run_prepared_query($DBH, $abortImportQuery, $importCollectionIdParam);
                if ($abortImportResult->rowCount() == 1) {
                    $sleepCount = 0;
                    do {
                        $sleepCount++;
                        sleep(5);
                        $collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
                    } while ($collectionMetadata['import_status_message'] != 'User Abort Request' || $sleepCount < 6);
                }
            }

            if (file_exists("images/temporaryImportFolder/$collectionId")) {
                if (file_exists("images/temporaryImportFolder/$collectionId/main")) {
                    $files = glob("images/temporaryImportFolder/$collectionId/main/*");
                    foreach ($files as $file) { // iterate files
                        if (is_file($file)) {
                            $unlinkResult = unlink($file); // delete file
                            if (!$unlinkResult) {
                                $deleteError[] = "Error deleting image file (collection: $collectionId, file: $file).";
                            }
                        }
                    }
                    $rmMainResult = rmdir("images/temporaryImportFolder/$collectionId/main");
                    if (!$rmMainResult) {
                        $deleteError[] = "Error deleting main folder (collection: $collectionId).";
                    }
                }
                if (file_exists("images/temporaryImportFolder/$collectionId/thumbnails")) {
                    $files = glob("images/temporaryImportFolder/$collectionId/thumbnails/*");
                    foreach ($files as $file) { // iterate files
                        if (is_file($file)) {
                            $unlinkResult = unlink($file); // delete file
                            if (!$unlinkResult) {
                                $deleteError[] = "Error deleting thumbnail file (collection: $collectionId, file: $file).";
                            }
                        }
                    }
                    $rmThumbsResult = rmdir("images/temporaryImportFolder/$collectionId/thumbnails");
                    if (!$rmThumbsResult) {
                        $deleteError[] = "Error deleting thumbnail folder (collection: $collectionId).";
                    }
                }
                $rmCollectionResult = rmdir("images/temporaryImportFolder/$collectionId");
                if (!$rmCollectionResult) {
                    $deleteError[] = "Error deleting collection folder (collection: $collectionId).";
                }
            }

            $imagesToDeleteQuery = '
                        SELECT COUNT(*)
                        FROM import_images
                        WHERE import_collection_id = :collectionId';
            $imagesToDeleteResult = run_prepared_query($DBH, $imagesToDeleteQuery, $importCollectionIdParam);
            $imagesToDeleteCount = $imagesToDeleteResult->fetchColumn();

            if ($imagesToDeleteCount > 0) {
                $deleteImportedImagesQuery = '
                        DELETE FROM import_images
                        WHERE import_collection_id = :collectionId
                        ';
                $deleteImportedImagesResult = run_prepared_query($DBH, $deleteImportedImagesQuery, $importCollectionIdParam);
                if ($deleteImportedImagesResult->rowCount() == 0) {
                    $deleteError[] = "Error deleting import_images rows (collection: $collectionId).";
                }
            }

            $deleteImportCollectionsQuery = '
                        DELETE FROM import_collections
                        WHERE import_collection_id = :collectionId
                        ';
            $deleteImportCollectionsResult = run_prepared_query($DBH, $deleteImportCollectionsQuery, $importCollectionIdParam);
            if ($deleteImportCollectionsResult->rowCount() == 0) {
                $deleteError[] = "Error deleting import_collection row (collection: $collectionId).";
            }


            if (count($deleteError) > 0) {
                $message = '<p class="error">There were problems deleting some or all components of the collection.<br>
                        Detected Errors:';
                foreach ($deleteError as $error) {
                    $message .= "<br>$error";
                }
                $message .= '</p>';
            } else {
                $message .= '<p class="error">The requested collection has been successfully deleted from iCoast.</p>';
            }
            $deleteFlag = null;


        } else {
            if ($reviewFlag) {
                $hiddenParam = '<input type="hidden" name="review" value="1">';
            } else {
                $hiddenParam = '';
            }
            $pageContentHTML = <<<HTML
                <h2>Delete the "{$collectionMetadata['name']}" Collection</h2>
                <p>
                    All data related to this partially completed collection will be irreparably lost.
                </p>
                <p class="error">Are you sure you want to delete the {$collectionMetadata['name']} collection?</p>
                <form autocomplete="off" method="post">
                    <input type="hidden" name="delete" value="1"/>
                    $hiddenParam
                    <input type="hidden" name="collectionId" value="$collectionId" />
                    <button type="submit" class="clickableButton enlargedClickableButton" name="confirmDeletion" value="1">
                        Delete
                    </button>
                    <button type="submit" class="clickableButton enlargedClickableButton" name="cancelDeletion" value="1">
                        Cancel
                    </button>
                </form>
HTML;
        }
    } else {
        header('Location: collectionCreator.php?error=InvalidCollection');
        exit;
    }
} else if ($deleteFlag && empty($collectionId)) {
    $deleteFlag = null;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => COMPLETE EXISTING COLLECTION CODE
if ($completeFlag) {
    $collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
    if (!$collectionMetadata) {
        header('Location: collectionCreator.php?error=MissingCollectionId');
        exit;
    }
//    print 'Check Stage';
    collection_creation_stage($collectionMetadata['import_collection_id'], true);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////// => DEFAULT CODE
if (empty($completeFlag) && empty($deleteFlag)) {

//    if (isset($collectionId)) {
//        collection_creation_stage($collectionId, true);
//    }

    if ($errorToken) {
        switch ($errorToken) {
            case 'InvalidCollection':
                $errorHTML = '
                    <p class="error">You do not have permission to manipulate the requested collection or it is not in a state that permits manipulation.</p>';
                break;
            case 'NoCollection':
                $errorHTML = '
                    <p class="error">The specified collection does not exist.</p>';
                break;
//            case 'DeleteFailed':
//                $errorHTML = '
//                    <p class="error">The attempt to delete the collection failed due to a database error.</p>';
//                break;
            case 'MissingCollectionId':
                $errorHTML = '
                    <p class="error">The attempted action failed due to a missing or invalid iCoast collection id.</p>';
                break;
//            case 'DirCreateError':
//                $errorHTML = '
//                    <p class="error">Import failed. The temporary image directory could not be created.</p>';
//                break;
//            case 'NoSession':
//                $errorHTML = '
//                    <p class="error">Import failed. The data session could not be found. Please try again.</p>';
//                break;
//            case 'MissingImportKey':
//                $errorHTML = '
//                    <p class="error">Import failed. The import key was missing from the request.</p>';
//                break;
//            case 'InvalidMissingUser':
//                $errorHTML = '
//                    <p class="error">Sequencing failed due to bad or missing user credentials.</p>';
//                break;
//            case 'UpdateFailed':
//                $errorHTML = '
//                    <p class="error">The last database operation failed. Please try again.</p>';
//                break;
//            case 'InvalidOperation':
//                $errorHTML = '
//                    <p class="error">The action you requested is invalid.</p>';
//                break;
//            case 'ImportFailed':
//                $errorHTML = '
//                    <p class="error">The import process failed to start.</p>';
//                break;
//            default:
//                $errorHTML = '';
        }
    }


    $collectionsInProgressQuery = <<<MYSQL
        SELECT import_collection_id, name, description
        FROM import_collections
        WHERE creator = $userId
        ORDER BY name ASC
MYSQL;
    $collectionsInProgressResult = run_prepared_query($DBH, $collectionsInProgressQuery);
    $collectionsInProgress = $collectionsInProgressResult->fetchAll(PDO::FETCH_ASSOC);

    $collectionSelectionHTML = '';

    if (count($collectionsInProgress) > 0) {

        $collectionsInProgressSelectOptionsHTML = '';
        foreach ($collectionsInProgress as $collectionInProgress) {
            $collectionsInProgressSelectOptionsHTML .= '
            <option value = "' . $collectionInProgress['import_collection_id'] . '" title = "' . $collectionInProgress['description'] . '">' .
                $collectionInProgress['name'] .
                '</option>';
        }

        $collectionSelectionHTML = <<<HTML

            <h2>Complete or Delete a Partially Built Collection</h2>
            <form method = "post" action = "collectionCreator.php" autocomplete = "off">
                <label for = "collectionSelect">Collection: </label>
                <select id = "collectionSelect" class = "formInputStyle" name = "collectionId">
                    $collectionsInProgressSelectOptionsHTML
                    </select>
                <br />
                <button class = "clickableButton enlargedClickableButton existingCollectionButton collectionCreatorButton disabledClickableButton" type = "submit" name = "complete" value="1" title = "Select this to complete the collection chosen in the dropdown menu above." disabled>
                    Complete the Selected Collection
                </button>
                <button class = "clickableButton enlargedClickableButton existingCollectionButton collectionCreatorButton disabledClickableButton" type = "submit" name = "delete" value="1" title = "Select this to delete the collection chosen in the dropdown menu above." disabled>
                    Delete the Selected Collection
                </button>
            </form>
HTML;
    } // END if (count($collectionsInProgress) > 0)

    $pageContentHTML = <<<HTML

        $errorHTML
        $message
        <h2>Create a New Collection</h2>
        <form method = "get" action = "collectionImportController.php" autocomplete = "off">
            <button type = "submit" class = "clickableButton enlargedClickableButton collectionCreatorButton">Create a New Collection</button>
        </form>
        $collectionSelectionHTML
HTML;

}


$embeddedCSS .= <<<EOL
    .clickableButton {
        width: 200px;
    }

    .collectionCreatorButton,
    #collectionSelect {
        width: 350px;
    }
EOL;

$jQueryDocumentDotReadyCode .= <<<JS

    $('#collectionSelect').prop('selectedIndex', -1);
    $('#collectionSelect').change(function() {
    console.log($('#collectionSelect').prop('selectedIndex'));
        if ($('#collectionSelect').prop('selectedIndex') >= 0) {
            $('.existingCollectionButton').removeClass('disabledClickableButton').removeAttr('disabled');
        } else {
            $('.existingCollectionButton').addClass('disabledClickableButton').attr('disabled', 'disabled');
        }
    });
JS;

