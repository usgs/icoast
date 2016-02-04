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
$httpHost = $_SERVER['HTTP_HOST'];

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


if (strcasecmp($httpHost, 'localhost') === 0 || strcasecmp($httpHost, 'igsafpesvm142') === 0) {
    $curlUrlHost = "http://localhost";
} else if (strcasecmp($httpHost, 'coastal.er.usgs.gov') === 0) {
    $curlUrlHost = "http://coastal.er.usgs.gov/icoast";
} else {
    header('Location: collectionCreator.php');
    exit;
}
$curlUrl = $curlUrlHost . "/scripts/collectionFinalizer.php";
$curlPostParams = "collectionId=$collectionId&user={$userData['user_id']}&checkCode={$userData['auth_check_code']}";
$c = curl_init();
curl_setopt($c, CURLOPT_URL, $curlUrl);
curl_setopt($c, CURLOPT_POSTFIELDS, $curlPostParams);
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
curl_setopt($c, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
curl_setopt($c, CURLOPT_TIMEOUT, 1);
curl_exec($c);
curl_close($c);


$embeddedCSS .= <<<CSS
    #finishButton {
        width: 200px;
    }

CSS;

$javaScript .= <<<JS
    var collectionId = $collectionId;
    var progressCheckTimer;

    function updateProgress() {
        $.getJSON('ajax/collectionFinalizerProgressChecker.php', {collectionId: collectionId}, function(finalizerProgress) {

            switch (finalizerProgress.stage) {
                case 1:
                    $('.progressBarFill').css('width', finalizerProgress.progressPercentage + '%');
                    $('.progressBarText').text('Finalizing Collection').css('left', '28%');
                    break;
                case 2:
                    clearInterval(progressCheckTimer);
                    $('.progressBar').hide();
                    $('#finalizingProgressTable').hide();
                    $('#finalizingDetailsWrapper').hide();
                    $('#finishedWrapper').show();
                    $('#finishButtonForm').show();
                    break;
                default:
                    break;
            }
        });
    } 
JS;


$jQueryDocumentDotReadyCode .= <<<JS
    progressCheckTimer = setInterval(function() {
        updateProgress();
    }, 2000);
        
JS;
