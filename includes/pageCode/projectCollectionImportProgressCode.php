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
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$projectId = filter_input(INPUT_GET, 'projectId', FILTER_VALIDATE_INT);
$importComplete = filter_input(INPUT_POST, 'importComplete');

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
    $projectMetadata['is_complete'] == 1
) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 20) {
    header('Location: projectCreator.php?error=InvalidProject');
}

$sleepPeriodInMins = 10;

if (isset($importComplete)) {
    $preIsComplete = FALSE;
    $postIsComplete = FALSE;
    $projectBasedQueryParam['projectId'] = $projectMetadata['project_id'];
    $collectionStatusQuery = '
        SELECT collection_type, import_status_message
        FROM import_collections
        WHERE parent_project_id = :projectId';
    $collectionStatusResult = run_prepared_query($DBH, $collectionStatusQuery, $projectBasedQueryParam);
    $collections = $collectionStatusResult->fetchAll(PDO::FETCH_ASSOC);
    if (count($collections) == 2) {
        foreach ($collections as $collection) {
            if ($collection['import_status_message'] == 'Complete') {
                if ($collection['collection_type'] == 'pre') {
                    $preIsComplete = TRUE;
                } else if ($collection['collection_type'] == 'post') {
                    $postIsComplete = TRUE;
                }
            }
        }
        if ($preIsComplete && $postIsComplete) {
            $setImportCompleteQuery = '
                UPDATE projects
                SET import_complete = 1
                WHERE project_id = :projectId
                LIMIT 1';
            $setImportCompleteResult = run_prepared_query($DBH, $setImportCompleteQuery, $projectBasedQueryParam);
            if ($setImportCompleteResult->rowCount() == 1) {
                header("Location: projectCreator.php?projectId={$projectMetadata['project_id']}&complete");
            }
        }
    } else if (count($collections) == 1) {
        if ($collections[0]['import_status_message'] == 'Complete' &&
            ($collections[0]['collection_type'] == 'pre' ||
                $collections[0]['collection_type'] == 'post')
        ) {
            if ($collections[0]['collection_type'] == 'pre') {
                $existingCollectionTypeToCheck = 'post';
            } else if ($collections[0]['collection_type'] == 'post') {
                $existingCollectionTypeToCheck = 'pre';
            }
            $existingCollectionQuery = "
                SELECT {$existingCollectionTypeToCheck}_collection_id
                FROM projects
                WHERE project_id = :projectId";
            $existingCollectionResult = run_prepared_query($DBH, $existingCollectionQuery, $projectBasedQueryParam);
            $existingCollectionId = $existingCollectionResult->fetchColumn();
            if (!empty($existingCollectionId)) {
                $setImportCompleteQuery = '
                    UPDATE projects
                    SET import_complete = 1
                    WHERE project_id = :projectId
                    LIMIT 1';
                $setImportCompleteResult = run_prepared_query($DBH, $setImportCompleteQuery, $projectBasedQueryParam);
                if ($setImportCompleteResult->rowCount() == 1) {
                    header("Location: projectCreator.php?projectId={$projectMetadata['project_id']}&complete");
                }
            }
        }
    }
}

$embeddedCSS .= <<<CSS
    .bold {
        font-weight: bold;
    }    
CSS;

$javaScript .= <<<JS
        
            var projectId = {$projectMetadata['project_id']};
            var progressCheckTimer;
            var countdownTimer;
            var countDownTimeRemaining = 9;
            var sleepPeriod = $sleepPeriodInMins;
            var updatePre = true;
            var updatePost = true;
            function countdown() {
                $('#countdown').html(countDownTimeRemaining);
                if (countdown !== 0) {
                    countDownTimeRemaining--;
                }
            }

            function updateProgress() {
                clearInterval(countdownTimer);
                clearInterval(progressCheckTimer);
                $.getJSON('ajax/projectImportProgressCheck.php', {projectId: projectId}, function(importProgress) {
                    var importText;
                    if (((importProgress.preCollection.status === 'complete' && importProgress.preCollection.sucessfulImages > 0) &&
                            (importProgress.postCollection.status === 'complete' && importProgress.postCollection.sucessfulImages > 0)) ||
                            (importProgress.preCollection.status === 'existing' &&
                                    (importProgress.postCollection.status === 'complete' && importProgress.postCollection.sucessfulImages > 0)) ||
                            ((importProgress.preCollection.status === 'complete' && importProgress.preCollection.sucessfulImages > 0) &&
                                    importProgress.postCollection.status === 'existing')) {
                        importText = ' \
                            <p>Your collections have now been imported.</p> \
                            <p>Check the statistics below to see if any images failed to import or \
                                were excluded from the collection due to their orientation.</p> \
                            <p>If you are unhappy with the results then click the <span class="italic">Delete</span> \
                                button under the relevant collection to delete it and try the import again.</p> \
                            <p>When you have finished reviewing the details / deleting collections click the \
                                <span class="italic">Continue Project Creation</span> / <span class="italic">Reimport Collection(s)</span> \
                                button immediatley below.</p>';
                        $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                    } else if (importProgress.preCollection.status === 'processing' ||
                            importProgress.postCollection.status === 'processing' ||
                            importProgress.preCollection.status === 'sleeping' ||
                            importProgress.postCollection.status === 'sleeping' ||
                            importProgress.preCollection.status === 'abortRequested' ||
                            importProgress.postCollection.status === 'abortRequested') {
                        importText = ' \
                            <p>The collections you began importing earlier are still being processed by the server.</p> \
                            <p>Check the progress bars and time estimates below to get an idea of how much longer \
                                the import process will take. They will update in <span id="countdown">10</span> seconds.</p> \
                            <p>You may leave this window open to monitor the progress, or, you can close your browser/tab \
                                and check back later by clicking the "Project Creator" link in the Administration panel \
                                and selecting the button to finish creating this project.</p>';
                    } else {
                        importText = ' \
                            <p>Collection processing has finished but with errors.</p> \
                            <p>Check the information below to determine why the import(s) did not complete.</p>';
                    }
                    $('#importTextWrapper').html(importText);
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    if (updatePre) {
                        if (importProgress.preCollection.status === 'processing' ||
                                importProgress.preCollection.status === 'sleeping') {
                            var status;
                            var sleepStatus;
                            var progressBarText;
                            if (importProgress.preCollection.status === 'processing') {
                                status = 'Processing';
                            } else {
                                status = 'Sleeping (' + sleepPeriod + ' Minute Pause Before Retrying Failed Images)';
                            }
                            if (importProgress.preCollection.sleepStatus === 0) {
                                sleepStatus = 'Time remaining may increase by ' + sleepPeriod + ' minutes if one or more images fail \
                                to import.';
                            } else {
                                sleepStatus = 'Time remaining includes a ' + sleepPeriod + ' minute sleep period that will be initiated before \
                                failed images are re-attempted.';
                            }
                            if (importProgress.preCollection.remainingTime === 'Calculating') {
                                progressBarText = '<span class="progressBarText" style="left: 40%">Calculating</span>';
                            } else {
                                progressBarText = '<span class="progressBarText">' +
                                        importProgress.preCollection.remainingTimeInMinutes + ' of ' +
                                        importProgress.preCollection.totalTimeInMinutes + ' Minutes Remaining</span>';
                            }
                            var htmlToInsert = ' \
                                <h3>' + importProgress.preCollection.name + ' (Pre-Event) Collection Import Progress</h3> \
                                <div id="preCollectionImportDetails">\
                                    <div class="progressBar"> \
                                       <div class="progressBarFill" style="width: ' +
                                    importProgress.preCollection.timeProgressPercentage + '%"></div>' +
                                    progressBarText + ' \
                                   </div> \
                                   <p>' + sleepStatus + '<p> \
                                   <table class="adminStatisticsTable"> \
                                       <tbody> \
                                           <tr> \
                                               <td>Status:</td> \
                                               <td class="userData">' + status + '</td> \
                                           </tr> \
                                           <tr> \
                                               <td>Progress:</td> \
                                               <td class="userData">' + importProgress.preCollection.processedImages +
                                    ' of ' + importProgress.preCollection.totalImages + ' images processed (' +
                                    importProgress.preCollection.imageProgressPercentage + '%)</td> \
                                           </tr> \
                                           <tr> \
                                               <td>Import Started On:</td> \
                                               <td class="userData">' + importProgress.preCollection.startTime + '</td> \
                                           </tr> \
                                           <tr> \
                                               <td>Elapsed Time:</td> \
                                               <td class="userData">' + importProgress.preCollection.elapsedTime + '</td> \
                                           </tr> \
                                           <tr> \
                                               <td>Estimated Time Remaining:</td> \
                                               <td class="userData">' + importProgress.preCollection.remainingTime + '</td> \
                                           </tr> \
                                           <tr> \
                                               <td>Estimated Completion Time:</td> \
                                               <td class="userData">' + importProgress.preCollection.endTime + '</td> \
                                           </tr> \
                                       </tbody> \
                                   </table> \
                                   <button id="preCollectionAbortButton" class="clickableButton">Abort This Pre-Event Collection Import</button> \
                                </div>';

                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);
                            $('#preCollectionAbortButton').click(function() {
                                $.getJSON('ajax/abortImport.php', {targetId: projectId, collectionType: 'pre'},
                                function(abortResult) {
                                    $('#preCollectionImportDetails').empty().html('<p class="error">Abort Requested</p> \
                                    <p>A request to abort the import of this<br> \
                                        collection has been submitted and is being processed.</p> \
                                    <p>Please wait...</p>');
                                });
                            });
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.preCollection.status === 'abortRequested') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.preCollection.name + ' (Pre-Event) Collection Import Progress</h3> \
                                    <p class="error">Abort Requested</p> \
                                    <p>A request to abort the import of this<br> \
                                    collection has been submitted and is being processed.</p> \
                                    <p>Please wait...</p>';
                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.preCollection.status === 'aborted') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.preCollection.name + ' (Pre-Event) Collection Import Progress</h3> \
                                <p class="error">Import Aborted</p> \
                                <p>The import if this collection has been aborted either by yourself or the system.<br> \
                                    Assuming no other problems have been found then use the button above to try again.';
                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);
                            if ($('#continueButton').html() !== 'Project Creator Menu') {
                                $('#continueButton').html('Reimport Collection(s)');
                                $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                    Use this button to attempt to reimport the affected collections.');
                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                            }
                            updatepre = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.preCollection.status === 'complete') {
                            var htmlToInsert = '<h3>' + importProgress.preCollection.name + ' (Post-Event) Collection Import Progress</h3>';
                            if (importProgress.preCollection.sucessfulImages > 0) {
                                htmlToInsert += ' \
                                    <div id="preCollectionImportDetails">\
                                        <div class="progressBar"> \
                                            <div class="completeProgressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 30%">Collection Processed</span> \
                                        </div>';
                                var failedPercentage = Math.floor(100 - ((importProgress.preCollection.sucessfulImages / importProgress.preCollection.totalImages) * 100));
                                if (failedPercentage > 10) {
                                    htmlToInsert += '<p class="error">' + failedPercentage + '% of your images failed to import.<br> \
                                        Is there a problem with your CSV file?</p>';
                                }
                                deleteButtonHTML = '<button id="preCollectionDeleteButton" class="clickableButton">Delete This Post-Event Collection</button>';
                            } else { // (importProgress.preCollection.sucessfulImages == 0)
                                htmlToInsert += ' \
                                    <div id="preCollectionImportDetails">\
                                        <div class="progressBar"> \
                                            <div class="progressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                                        </div> \
                                        <p class="error">No images were sucessfully imported into this collection.</p> \
                                        <p class="error">You must correct this problem before you can continue<br> \
                                            Assuming no other problems have been found use the button above to try again.</p>';
                                deleteButtonHTML = '';
                                if ($('#continueButton').html() !== 'Project Creator Menu') {
                                    $('#continueButton').html('Reimport Collection(s)');
                                    $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                            Use this button to attempt to reimport the affected collections.');
                                    $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                    $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                }
                            }

                            htmlToInsert += ' \
                                <table class="adminStatisticsTable"> \
                                    <tbody> \
                                        <tr> \
                                            <td>Status:</td> \
                                            <td class="userData">Complete</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Import Started On:</td> \
                                            <td class="userData">' + importProgress.preCollection.startTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Import Finished At:</td> \
                                            <td class="userData">' + importProgress.preCollection.endTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Total Time To Process:</td> \
                                            <td class="userData">' + importProgress.preCollection.elapsedTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Images Submitted for Import:</td> \
                                            <td class="userData">' + importProgress.preCollection.totalImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Sucessfully Imported Images:</td> \
                                            <td class="userData">' + importProgress.preCollection.sucessfulImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Portrait Images Excluded:</td> \
                                            <td class="userData">' + importProgress.preCollection.portraitImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Failed Images:</td> \
                                            <td class="userData">' + importProgress.preCollection.failedImages + '</td> \
                                        </tr> \
                                    </tbody> \
                                </table>' +
                                    deleteButtonHTML + ' \
                            </div>';
                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);
                            if (importProgress.preCollection.sucessfulImages > 0) {
                                $('#preCollectionDeleteButton').click(function() {
                                    $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'pre', newCollection: 1},
                                    function(deletionResult) {
                                        if (deletionResult === 1) {
                                            $('#preCollectionImportDetails').empty().html(' \
                                                <p class="error">Collection Deleted</p> \
                                                <p>The collection has been sucessfully deleted from the database.</p>\
                                                <p>Assuming no other problems have been found use the button above to try again.</p> \
                                            ');
                                            if ($('#continueButton').html() !== 'Project Creator Menu') {
                                                $('#continueButton').html('Reimport Collection(s)');
                                                $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                                    Use this button to attempt to reimport the affected collections.');
                                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                                $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                            }
                                        } else {
                                            $('#preCollectionImportDetails').empty().html(' \
                                                <p class="error">Delete Failed</p> \
                                                <p>The attempt to delete this collection failed.<br> \
                                                    You may either delete the entire project from the main menu and start again, \
                                                    or contact the iCoast developer for help.</p>\
                                            ');
                                            $('#continueButton').html('Project Creator Menu');
                                            $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                        }
                                    });
                                });
                            } else { // (importProgress.preCollection.sucessfulImages == 0)
                                $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'pre', newCollection: 1},
                                function(deletionResult) {
                                    if (deletionResult === 0) {
                                        $('#preCollectionImportDetails').empty().html('\
                                            <div class="progressBar"> \
                                                <div class="progressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                                <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                                            </div> \
                                            <p class="error">No images were sucessfully imported into this collection.</p> \
                                            <p>An attempt to recover from this error has failed.<br> \
                                                You must either delete the entire project and start again or contact the iCoast developer for help.<br> \
                                                Use the <span class="italic">Project Creator Menu</span> button above to return to the main menu.</p> \
                                            ');
                                        $('#continueButton').html('Project Creator Menu');
                                        $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                        $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                        $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                        if (importProgress.postCollection.status !== 'complete' &&
                                                importProgress.postCollection.status !== 'existing') {
                                            $.getJSON('ajax/abortImport.php', {targetId: projectId, collectionType: 'pre'});
                                        }
                                    }

                                });
                            }
                            updatePre = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.preCollection.status === 'existing') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.preCollection.name + ' (Pre-Event) Collection</h3> \
                                    <div id="preCollectionImportDetails"> \
                                        <div class="progressBar"> \
                                            <div class="completeProgressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 30%">Collection Processed</span> \
                                        </div> \
                                        <p>The existing "' + importProgress.preCollection.existingCollectionName + '" collection has been \
                                            sucessfully<br>  set as the pre-event collection to be used for this project.</p> \
                                        <button id="preCollectionDeleteButton" class="clickableButton">Remove this Pre-Event Collection</button> \
                                    </div>';
                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);


                            $('#preCollectionDeleteButton').click(function() {
                                $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'pre', newCollection: 0},
                                function(deletionResult) {
                                    if (deletionResult === 1) {
                                        $('#preCollectionImportDetails').empty().html(' \
                                                <p class="error">Collection Removed</p> \
                                                <p>The existing collection "' + importProgress.preCollection.existingCollectionName + ' \
                                                    " has been sucessfully removed from this project.</p>\
                                                <p>Assuming no other problems have been found use the button above to try again.</p> \
                                            ');
                                        if ($('#continueButton').html() !== 'Project Creator Menu') {
                                            $('#continueButton').html('Reimport Collection(s)');
                                            $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                            Use this button to attempt to reimport the affected collections.');
                                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                        }
                                    } else {
                                        $('#preCollectionImportDetails').empty().html(' \
                                                <p class="error">Removal Failed</p> \
                                                <p>The attempt to remove this collection from the new project failed.<br> \
                                                    You may either delete the entire project from the main menu and start again, \
                                                    or contact the iCoast developer for help.</p>\
                                            ');
                                        $('#continueButton').html('Project Creator Menu');
                                        $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                        $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                        $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                    }
                                });
                            });

                            updatePre = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else {
                            var htmlToInsert = ' \
                                <h3>Pre-Event Collection Import Progress</h3> \
                                <div id="preCollectionImportDetails">\
                                    <p class="error">Processing of the collection has failed.</p> \
                                    <p class="error">Error Code: ' + importProgress.preCollection.status + '</p> \
                                    <p>Check the error for obvious reasons for failure, resolve the issue, and try the import again.</p> \
                                    <p>If the reason for failure is unclear please contact the iCoast developer for assistance.</p> \
                                </div>';
                            $('#preEventCollectionProgressWrapper').html(htmlToInsert);
                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                            $('#continueButton').attr("title", "You have deleted/removed one or more of the previously \
                                        imported collections. Select this button to reimport.");
                            $('#continueButton').text('Reimport Collection(s)');
                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                            updatePre = false;
                        }
                    }
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    if (updatePost) {
                        if (importProgress.postCollection.status === 'processing' ||
                                importProgress.postCollection.status === 'sleeping') {
                            var status;
                            var sleepStatus;
                            var progressBarText;
                            if (importProgress.postCollection.status === 'processing') {
                                status = 'Processing';
                            } else {
                                status = 'Sleeping (' + sleepPeriod + ' Minute Pause Before Retrying Failed Images)';
                            }
                            if (importProgress.postCollection.sleepStatus === 0) {
                                sleepStatus = 'Time remaining may increase by ' + sleepPeriod + ' minutes if one or more images fail \
                                to import.';
                            } else {
                                sleepStatus = 'Time remaining includes a ' + sleepPeriod + ' minute sleep period that will be initiated before \
                                failed images are re-attempted.';
                            }
                            if (importProgress.postCollection.remainingTime === 'Calculating') {
                                progressBarText = '<span class="progressBarText" style="left: 40%">Calculating</span>';
                            } else {
                                progressBarText = '<span class="progressBarText">' +
                                        importProgress.postCollection.remainingTimeInMinutes + ' of ' +
                                        importProgress.postCollection.totalTimeInMinutes + ' Minutes Remaining</span>';
                            }
                            var htmlToInsert = ' \
                            <h3>' + importProgress.postCollection.name + ' (Post-Event) Collection Import Progress</h3> \
                            <div id="postCollectionImportDetails">\
                                <div class="progressBar"> \
                                   <div class="progressBarFill" style="width: ' +
                                    importProgress.postCollection.timeProgressPercentage + '%"></div>' +
                                    progressBarText + ' \
                               </div> \
                               <p>' + sleepStatus + '<p> \
                               <table class="adminStatisticsTable"> \
                                   <tbody> \
                                       <tr> \
                                           <td>Status:</td> \
                                           <td class="userData">' + status + '</td> \
                                       </tr> \
                                       <tr> \
                                           <td>Progress:</td> \
                                           <td class="userData">' + importProgress.postCollection.processedImages +
                                    ' of ' + importProgress.postCollection.totalImages + ' images processed (' +
                                    importProgress.postCollection.imageProgressPercentage + '%)</td> \
                                       </tr> \
                                       <tr> \
                                           <td>Import Started On:</td> \
                                           <td class="userData">' + importProgress.postCollection.startTime + '</td> \
                                       </tr> \
                                       <tr> \
                                           <td>Elapsed Time:</td> \
                                           <td class="userData">' + importProgress.postCollection.elapsedTime + '</td> \
                                       </tr> \
                                       <tr> \
                                           <td>Estimated Time Remaining:</td> \
                                           <td class="userData">' + importProgress.postCollection.remainingTime + '</td> \
                                       </tr> \
                                       <tr> \
                                           <td>Estimated Completion Time:</td> \
                                           <td class="userData">' + importProgress.postCollection.endTime + '</td> \
                                       </tr> \
                                   </tbody> \
                               </table> \
                               <button id="postCollectionAbortButton" class="clickableButton">Abort This Post-Event Collection Import</button> \
                            </div>';

                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);
                            $('#postCollectionAbortButton').click(function() {
                                $.getJSON('ajax/abortImport.php', {targetId: projectId, collectionType: 'post'},
                                function(abortResult) {
                                    $('#postCollectionImportDetails').empty().html('<p class="error">Abort Requested</p> \
                                    <p>A request to abort the import of this<br> \
                                        collection has been submitted and is being processed.</p> \
                                    <p>Please wait...</p>');
                                });
                            });
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.postCollection.status === 'abortRequested') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.postCollection.name + ' (Post-Event) Collection Import Progress</h3> \
                                    <p class="error">Abort Requested</p> \
                                    <p>A request to abort the import of this<br> \
                                    collection has been submitted and is being processed.</p> \
                                    <p>Please wait...</p>';
                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.postCollection.status === 'aborted') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.postCollection.name + ' (Post-Event) Collection Import Progress</h3> \
                                <p class="error">Import Aborted</p> \
                                <p>The import if this collection has been aborted either by yourself or the system.<br> \
                                    Assuming no other problems have been found use the button above to try again.';
                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);
                            if ($('#continueButton').html() !== 'Project Creator Menu') {
                                $('#continueButton').html('Reimport Collection(s)');
                                $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                            Use this button to attempt to reimport the affected collections.');
                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                            }
                            updatePost = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.postCollection.status === 'complete') {
                            var htmlToInsert = '<h3>' + importProgress.postCollection.name + ' (Post-Event) Collection Import Progress</h3>';
                            if (importProgress.postCollection.sucessfulImages > 0) {
                                htmlToInsert += ' \
                                    <div id="postCollectionImportDetails">\
                                        <div class="progressBar"> \
                                            <div class="completeProgressBarFill" id="postCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 30%">Collection Processed</span> \
                                        </div>';
                                var failedPercentage = Math.floor(100 - ((importProgress.postCollection.sucessfulImages / importProgress.postCollection.totalImages) * 100));
                                if (failedPercentage > 10) {
                                    htmlToInsert += '<p class="error">' + failedPercentage + '% of your images failed to import.<br> \
                                        Is there a problem with your CSV file?</p>';
                                }
                                deleteButtonHTML = '<button id="postCollectionDeleteButton" class="clickableButton">Delete This Post-Event Collection</button>';
                            } else { // (importProgress.postCollection.sucessfulImages == 0)
                                htmlToInsert += ' \
                                    <div id="postCollectionImportDetails">\
                                        <div class="progressBar"> \
                                            <div class="progressBarFill" id="postCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                                        </div> \
                                        <p class="error">No images were sucessfully imported into this collection.</p> \
                                        <p class="error">You must correct this problem before you can continue<br> \
                                            Assuming no other problems have been found use the button above to try again.</p>';
                                deleteButtonHTML = '';
                                if ($('#continueButton').html() !== 'Project Creator Menu') {
                                    $('#continueButton').html('Reimport Collection(s)');
                                    $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                            Use this button to attempt to reimport the affected collections.');
                                    $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                    $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                }
                            }

                            htmlToInsert += ' \
                                <table class="adminStatisticsTable"> \
                                    <tbody> \
                                        <tr> \
                                            <td>Status:</td> \
                                            <td class="userData">Complete</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Import Started On:</td> \
                                            <td class="userData">' + importProgress.postCollection.startTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Import Finished At:</td> \
                                            <td class="userData">' + importProgress.postCollection.endTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>Total Time To Process:</td> \
                                            <td class="userData">' + importProgress.postCollection.elapsedTime + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Images Submitted for Import:</td> \
                                            <td class="userData">' + importProgress.postCollection.totalImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Sucessfully Imported Images:</td> \
                                            <td class="userData">' + importProgress.postCollection.sucessfulImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Portrait Images Excluded:</td> \
                                            <td class="userData">' + importProgress.postCollection.portraitImages + '</td> \
                                        </tr> \
                                        <tr> \
                                            <td>No. of Failed Images:</td> \
                                            <td class="userData">' + importProgress.postCollection.failedImages + '</td> \
                                        </tr> \
                                    </tbody> \
                                </table>' +
                                    deleteButtonHTML + ' \
                            </div>';
                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);
                            if (importProgress.postCollection.sucessfulImages > 0) {
                                $('#postCollectionDeleteButton').click(function() {
                                    $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'post', newCollection: 1},
                                    function(deletionResult) {
                                        if (deletionResult === 1) {
                                            $('#postCollectionImportDetails').empty().html(' \
                                                <p class="error">Collection Deleted</p> \
                                                <p>The collection has been sucessfully deleted from the database.</p>\
                                                <p>Assuming no other problems have been found use the button above to try again.</p> \
                                            ');
                                            if ($('#continueButton').html() !== 'Project Creator Menu') {
                                                $('#continueButton').html('Reimport Collection(s)');
                                                $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                            Use this button to attempt to reimport the affected collections.');
                                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                                $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                            }
                                        } else {
                                            $('#postCollectionImportDetails').empty().html(' \
                                                <p class="error">Delete Failed</p> \
                                                <p>The attempt to delete this collection failed.<br> \
                                                    You may either delete the entire project from the main menu and start again, \
                                                    or contact the iCoast developer for help.</p>\
                                            ');
                                            $('#continueButton').html('Project Creator Menu');
                                            $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                        }
                                    });
                                });
                            } else { // (importProgress.postCollection.sucessfulImages == 0)
                                $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'post', newCollection: 1},
                                function(deletionResult) {
                                    if (deletionResult === 0) {
                                        $('#postCollectionImportDetails').empty().html('\
                                            <div class="progressBar"> \
                                                <div class="progressBarFill" id="postCollectionProgressBar" style="width: 100%"></div> \
                                                <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                                            </div> \
                                            <p class="error">No images were sucessfully imported into this collection.</p> \
                                            <p>An attempt to recover from this error has failed.<br> \
                                                You must either delete the entire project and start again or contact the iCoast developer for help.<br> \
                                                Use the <span class="italic">Project Creator Menu</span> button above to return to the main menu.</p> \
                                            ');
                                        $('#continueButton').html('Project Creator Menu');
                                        $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                        $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                        $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                        if (importProgress.postCollection.status !== 'complete' &&
                                                importProgress.postCollection.status !== 'existing') {
                                            $.getJSON('ajax/abortImport.php', {targetId: projectId, collectionType: 'post'});
                                        }
                                    }

                                });
                            }
                            updatePost = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else if (importProgress.postCollection.status === 'existing') {
                            var htmlToInsert = ' \
                                <h3>' + importProgress.postCollection.name + ' (Post-Event) Collection</h3> \
                                    <div id="postCollectionImportDetails"> \
                                        <div class="progressBar"> \
                                            <div class="completeProgressBarFill" id="postCollectionProgressBar" style="width: 100%"></div> \
                                            <span class="progressBarText" style="left: 30%">Collection Processed</span> \
                                        </div> \
                                        <p>The existing "' + importProgress.postCollection.existingCollectionName + '" collection has been \
                                            sucessfully<br>  set as the post-event collection to be used for this project.</p> \
                                        <button id="postCollectionDeleteButton" class="clickableButton">Remove this Post-Event Collection</button> \
                                    </div>';
                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);


                            $('#postCollectionDeleteButton').click(function() {
                                $.getJSON('ajax/deleteImportCollectionFromProject.php', {projectId: projectId, collectionType: 'post', newCollection: 0},
                                function(deletionResult) {
                                    if (deletionResult === 1) {
                                        $('#postCollectionImportDetails').empty().html(' \
                                                <p class="error">Collection Removed</p> \
                                                <p>The existing collection "' + importProgress.postCollection.existingCollectionName + ' \
                                                    " has been sucessfully removed from this project.</p>\
                                                <p>Assuming no other problems have been found use the button above to try again.</p> \
                                            ');
                                        if ($('#continueButton').html() !== 'Project Creator Menu') {
                                            $('#continueButton').html('Reimport Collection(s)');
                                            $('#continueButton').attr('title', 'One or both of the collections for this project either failed or were aborted/deleted. \
                                            Use this button to attempt to reimport the affected collections.');
                                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                                        }
                                    } else {
                                        $('#postCollectionImportDetails').empty().html(' \
                                                <p class="error">Removal Failed</p> \
                                                <p>The attempt to remove this collection from the new project failed.<br> \
                                                    You may either delete the entire project from the main menu and start again, \
                                                    or contact the iCoast developer for help.</p>\
                                            ');
                                        $('#continueButton').html('Project Creator Menu');
                                        $('#continueButton').attr('title', 'Returns you to the Project Creator Menu');
                                        $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                        $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId);
                                    }
                                });
                            });

                            updatePost = false;
                            //
                            //
                            //
                            //
                            //
                            //
                        } else {
                            var htmlToInsert = ' \
                                <h3>Post-Event Collection Import Progress</h3> \
                                <div id="postCollectionImportDetails">\
                                    <p class="error">Processing of the collection has failed.</p> \
                                    <p class="error">Error Code: ' + importProgress.postCollection.status + '</p> \
                                    <p>Check the error for obvious reasons for failure, resolve the issue, and try the import again.</p> \
                                    <p>If the reason for failure is unclear please contact the iCoast developer for assistance.</p> \
                                </div>';
                            $('#postEventCollectionProgressWrapper').html(htmlToInsert);
                            $('#continueForm').attr('action', 'projectCreator.php?projectId=' + projectId + '&complete');
                            $('#continueButton').attr("title", "You have deleted/removed one or more of the previously \
                                        imported collections. Select this button to reimport.");
                            $('#continueButton').text('Reimport Collection(s)');
                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                            updatePost = false;
                        }
                    }
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    //
                    moveFooter();
                    if ((importProgress.preCollection.status !== 'complete' &&
                            importProgress.preCollection.status !== 'existing' &&
                            importProgress.preCollection.status !== 'aborted' &&
                            importProgress.preCollection.status !== 'failed' &&
                            importProgress.preCollection.status !== 'missing') ||
                            (importProgress.postCollection.status !== 'complete' &&
                                    importProgress.postCollection.status !== 'existing' &&
                                    importProgress.postCollection.status !== 'aborted' &&
                                    importProgress.postCollection.status !== 'failed' &&
                                    importProgress.postCollection.status !== 'missing')) {
                        progressCheckTimer = setInterval(function() {
                            updateProgress()
                        }, 10000);
                        countDownTimeRemaining = 10;
                        $('#countdown').html(countDownTimeRemaining);
                        countDownTimeRemaining--;
                        countdownTimer = setInterval(function() {
                            countdown()
                        }, 1000);
                    }
                });
            }
   
JS;

$jQueryDocumentDotReadyCode .= <<<JS
        
        updateProgress();
        
JS;
