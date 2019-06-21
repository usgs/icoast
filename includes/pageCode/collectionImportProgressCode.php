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
$userData = authenticate_user($DBH, true, true, true);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

$collectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);

$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
  header("Location: collectionCreator.php?error=NoCollection");
  exit;
}


$importStatus = collection_creation_stage($collectionMetadata['import_collection_id']);
if ($importStatus != 2 && $importStatus != 3) {
  header('Location: collectionCreator.php?error=InvalidCollection');
  exit;
}


$embeddedCSS .= <<<EOL
    .bold {
        font-weight: bold;
    }    
EOL;

$javaScript .= <<< JS

    var collectionId = {$collectionMetadata['import_collection_id']};
    var progressCheckTimer;
    var countdownTimer;
    var countDownTimeRemaining = 9;

    function countdown() {
        $('#countdown').html(countDownTimeRemaining);
        if (countdown !== 0) {
            countDownTimeRemaining--;
        }
    }

    function updateProgress() {
        clearInterval(countdownTimer);
        clearInterval(progressCheckTimer);
        $.getJSON('ajax/collectionImportProgressCheck.php', {collectionId: collectionId}, function(importProgress) {
            var importText;
            if (importProgress.status === 'complete' && importProgress.successfulImages > 0) {
                importText = ' \
                    <p>Your collection has been imported.</p> \
                    <p>Check the statistics below to see if any images failed to import or \
                        were excluded from the collection due to their orientation.</p> \
                    <p>If you are unhappy with the results then click the <span class="italic">Reimport</span> \
                        button to delete the imported data and try the import again with a revised input file(s). \
                        Otherwise, click the <span class="italic">Continue Collection Creation</span> button.</p>';
                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
            } else if (importProgress.status === 'processing' ||
                    importProgress.status === 'sleeping' ||
                    importProgress.status === 'abortRequested' ||
                    importProgress.status === 'abortRequested') {
                importText = ' \
                    <p>The collection is currently being processed by the server.</p> \
                    <p>Check the progress bar and time estimate below to get an idea of how much longer \
                        the import process will take. Progress indicators will update in <span id="countdown">10</span> seconds.</p> \
                    <p>You may leave this window open to monitor the progress, or, you can close your browser/tab \
                        and check back later by clicking the "Collection Creator" link in the Administration panel \
                        and selecting the button to complete this collection.</p>';
            } else {
                importText = ' \
                    <p>Collection import processing has finished but with errors.</p> \
                    <p>Check the information below to determine why the import did not complete.</p>';
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

            if (importProgress.status === 'processing' ||
                    importProgress.status === 'sleeping') {
                var status;
                var sleepStatus;
                var progressBarText;
                if (importProgress.status === 'processing') {
                    status = 'Processing';
                } else {
                    status = 'Sleeping (10 Minute Pause Before Retrying Failed Images)';
                }
                if (importProgress.sleepStatus === 0) {
                    sleepStatus = 'Time remaining may increase by 10 minutes if one or more images fail \
                    to import.';
                } else {
                    sleepStatus = 'Time remaining includes a 10 minute sleep period that will be initiated before \
                    failed images are re-attempted.';
                }
                if (importProgress.remainingTime === 'Calculating') {
                    progressBarText = '<span class="progressBarText" style="left: 40%">Calculating</span>';
                } else {
                    progressBarText = '<span class="progressBarText">' +
                            importProgress.remainingTimeInMinutes + ' of ' +
                            importProgress.totalTimeInMinutes + ' Minutes Remaining</span>';
                }
                var htmlToInsert = ' \
                    <h3>Import Progress</h3> \
                    <div id="collectionImportDetails">\
                        <div class="progressBar"> \
                           <div class="progressBarFill" style="width: ' +
                        importProgress.timeProgressPercentage + '%"></div>' +
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
                                   <td class="userData">' + importProgress.processedImages +
                        ' of ' + importProgress.totalImages + ' images processed (' +
                        importProgress.imageProgressPercentage + '%)</td> \
                               </tr> \
                               <tr> \
                                   <td>Import Started On:</td> \
                                   <td class="userData">' + importProgress.startTime + '</td> \
                               </tr> \
                               <tr> \
                                   <td>Elapsed Time:</td> \
                                   <td class="userData">' + importProgress.elapsedTime + '</td> \
                               </tr> \
                               <tr> \
                                   <td>Estimated Time Remaining:</td> \
                                   <td class="userData">' + importProgress.remainingTime + '</td> \
                               </tr> \
                               <tr> \
                                   <td>Estimated Completion Time:</td> \
                                   <td class="userData">' + importProgress.endTime + '</td> \
                               </tr> \
                           </tbody> \
                       </table> \
                       <button id="collectionAbortButton" class="clickableButton">Abort Import</button> \
                    </div>';

                $('#collectionProgressWrapper').html(htmlToInsert);
                $('#collectionAbortButton').click(function() {
                    $.getJSON('ajax/abortImport.php', {targetId: collectionId}, function(abortResult) {
                        $('#collectionImportDetails').empty().html('<p class="error">Abort Requested</p> \
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
            } else if (importProgress.status === 'abortRequested') {
                var htmlToInsert = ' \
                    <h3>Import Progress</h3> \
                        <p class="error">Abort Requested</p> \
                        <p>A request to abort the import of this<br> \
                        collection has been submitted and is being processed.</p> \
                        <p>Please wait...</p>';
                $('#collectionProgressWrapper').html(htmlToInsert);
                //
                //
                //
                //
                //
                //
            } else if (importProgress.status === 'aborted') {
                        clearInterval(countdownTimer);
        clearInterval(progressCheckTimer);
                var htmlToInsert = ' \
                    <h3>Collection Import Progress</h3> \
                    <p class="error">Import Aborted</p> \
                    <p>The import of this collection has been aborted either by yourself or the system.<br> \
                        If this was a system driven event then you should check your CSV file for errors. When ready use the button below to try again.';
                $('#collectionProgressWrapper').html(htmlToInsert);
                $('#continueButton').html('Reimport This Collection');
                $('#continueButton').attr('title', 'The collection import either failed or was aborted/deleted. \
                    Use this button to attempt to reimport the collection.');
                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                $('#continueForm').attr('action', 'collectionImportController.php?collectionId=' + collectionId);
                //
                //
                //
                //
                //
                //
            } else if (importProgress.status === 'complete') {
        clearInterval(countdownTimer);
        clearInterval(progressCheckTimer);
                var htmlToInsert = '<h3>Collection Import Progress</h3>';



                if (importProgress.successfulImages > 0) {
                    htmlToInsert += ' \
                        <div id="collectionImportDetails">\
                            <div class="progressBar"> \
                                <div class="completeProgressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                <span class="progressBarText" style="left: 30%">Collection Processed</span> \
                            </div>';
                    var failedPercentage = Math.floor(100 - ((importProgress.successfulImages / importProgress.totalImages) * 100));
                    if (failedPercentage > 10) {
                        htmlToInsert += '<p class="error">' + failedPercentage + '% of your images failed to import.<br> \
                            Is there a problem with your CSV file?</p>';
                    }
                    deleteButtonHTML = '<button id="collectionDeleteButton" class="clickableButton">Reimport This Collection</button>';


                } else { // ELSE IF (importProgress.successfulImages == 0)
                    htmlToInsert += ' \
                        <div id="collectionImportDetails">\
                            <div class="progressBar"> \
                                <div class="progressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                            </div> \
                            <p class="error">No images were successfully imported into this collection.</p> \
                            <p class="error">You must correct this problem before you can continue<br> \
                                Assuming no other problems have been found use the button below to try again.</p>';
                    deleteButtonHTML = '';

                    $('#continueButton').html('Reimport This Collection');
                    $('#continueButton').attr('title', 'The collection import failed to successfully import any. \
                            images. Use this button to attempt to reimport the collection.');
                    $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                    $('#continueForm').attr('action', 'collectionImportController.php?collectionId=' + collectionId);
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
                                <td class="userData">' + importProgress.startTime + '</td> \
                            </tr> \
                            <tr> \
                                <td>Import Finished At:</td> \
                                <td class="userData">' + importProgress.endTime + '</td> \
                            </tr> \
                            <tr> \
                                <td>Total Time To Process:</td> \
                                <td class="userData">' + importProgress.elapsedTime + '</td> \
                            </tr> \
                            <tr> \
                                <td>No. of Images Submitted for Import:</td> \
                                <td class="userData">' + importProgress.totalImages + '</td> \
                            </tr> \
                            <tr> \
                                <td>No. of successfully Imported Images:</td> \
                                <td class="userData">' + importProgress.successfulImages + '</td> \
                            </tr> \
                            <tr> \
                                <td>No. of Portrait Images Excluded:</td> \
                                <td class="userData">' + importProgress.portraitImages + '</td> \
                            </tr> \
                            <tr> \
                                <td>No. of Failed Images:</td> \
                                <td class="userData">' + importProgress.failedImages + '</td> \
                            </tr> \
                        </tbody> \
                    </table>' +
                        deleteButtonHTML + ' \
                </div>';
                $('#collectionProgressWrapper').html(htmlToInsert);


                if (importProgress.successfulImages > 0) {
                    $('#collectionDeleteButton').click(function() {
                        $.getJSON('ajax/deleteImportCollection.php', {collectionId: collectionId},
                        function(deletionResult) {


                            if (deletionResult === 1) {
                                $('#collectionImportDetails').empty().html(' \
                                    <p class="error">Collection Deleted</p> \
                                    <p>The collection has been successfully deleted from the database.</p>\
                                    <p>Assuming no other problems have been found use the button below to try again.</p> \
                                ');
                                $('#continueButton').html('Reimport This Collection');
                                $('#continueButton').attr('title', 'Use this button to reimport the collection.');
                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                $('#continueForm').attr('action', 'collectionImportController.php?collectionId=' + collectionId);
                            } else {
                                $('#collectionImportDetails').empty().html(' \
                                    <p class="error">Delete Failed</p> \
                                    <p>The attempt to delete this collection failed.<br> \
                                        You may either delete the entire project from the main menu and start again, \
                                        or contact the iCoast developer for help.</p>\
                                ');
                                $('#continueButton').html('Collection Creator Menu');
                                $('#continueButton').attr('title', 'Returns you to the Collection Creator Menu');
                                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                                $('#continueForm').attr('action', 'collectionCreator.php?collectionId=' + collectionId);
                            }


                        });
                    });


                } else { // IF ELSE (importProgress.successfulImages > 0)
                    $.getJSON('ajax/deleteImportCollection.php', {collectionId: collectionId},
                    function(deletionResult) {


                        if (deletionResult === 0) {
                            $('#collectionImportDetails').empty().html('\
                                <div class="progressBar"> \
                                    <div class="progressBarFill" id="preCollectionProgressBar" style="width: 100%"></div> \
                                    <span class="progressBarText" style="left: 35%">Collection Failed</span> \
                                </div> \
                                <p class="error">No images were successfully imported into this collection.</p> \
                                <p>An attempt to recover from this error has failed.<br> \
                                    You must either delete the entire project and start again or contact the iCoast developer for help.<br> \
                                    Use the <span class="italic">Collection Creator Menu</span> button below to return to the main menu.</p> \
                                ');
                            $('#continueButton').html('Collection Creator Menu');
                            $('#continueButton').attr('title', 'Returns you to the Collection Creator Menu');
                            $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');
                            $('#continueForm').attr('action', 'collectionCreator.php?collectionId=' + collectionId);
                            if (importProgress.status !== 'complete') {
                                $.getJSON('ajax/abortImport.php', {targetId: collectionId});
                            }
                        }


                    });
                }
                //
                //
                //
                //
                //
                //
            } else {
                        clearInterval(countdownTimer);
        clearInterval(progressCheckTimer);
                var htmlToInsert = ' \
                    <h3>Collection Import Progress</h3> \
                    <div id="collectionImportDetails">\
                        <p class="error">Processing of the collection has failed.</p> \
                        <p class="error">Error Code: ' + importProgress.status + '</p> \
                        <p>Check the error for obvious reasons for failure, resolve the issue, and try the import again.</p> \
                        <p>If the reason for failure is unclear please contact the iCoast developer for assistance.</p> \
                    </div>';
                $('#collectionProgressWrapper').html(htmlToInsert);
                $('#continueForm').attr('action', 'collectionCreator.php?collectionId=' + collectionId + '&complete');
                $('#continueButton').attr("title", "You have deleted/removed one or more of the previously \
                            imported collections. Select this button to reimport.");
                $('#continueButton').text('Reimport Collection(s)');
                $('#continueButton').removeClass('disabledClickableButton').removeAttr('disabled');

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
            moveFooter();
            if (importProgress.status !== 'complete') {
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
