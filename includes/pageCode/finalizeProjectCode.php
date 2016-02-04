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
$makeLiveFlag = filter_input(INPUT_GET, 'makeLive', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$makeFocusFlag = filter_input(INPUT_GET, 'makeFocus', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$httpHost = $_SERVER['HTTP_HOST'];

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
        $projectMetadata ['is_complete'] == 1) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}
unset($projectId);
$projectIdParam['projectId'] = $projectMetadata['project_id'];

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 50) {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

if (!isset($makeLiveFlag) && !isset($makeFocusFlag)) {
    header('Location: projectCreator.php?error=InvalidOperation');
    exit;
}
$liveOptionHTML = '';
if ($makeLiveFlag) {
    $liveOptionHTML .= '<p>Your project is live and available for the public to classify.</p>';
    if ($makeFocusFlag) {
        $liveOptionHTML .= '<p>Your project has been made the focus for providing the iCoast home page statistics.</p>';
    }
} else {
    $liveOptionHTML .= '<p>Your project is not yet live and therfore not currently available for users to work on.</p>';
}

if ($projectMetadata['finalization_stage'] == 0 && $projectMetadata['is_complete'] == 0) {
    if (strcasecmp($httpHost, 'localhost') === 0 || strcasecmp($httpHost, 'igsafpesvm142') === 0) {
        $curlUrlHost = "http://localhost";
    } else if (strcasecmp($httpHost, 'coastal.er.usgs.gov') === 0) {
        $curlUrlHost = "http://coastal.er.usgs.gov/icoast";
    } else {
        header('Location: projectCreator.php');
        exit;
    }
    $curlUrl = $curlUrlHost . "/scripts/projectFinalizer.php";
    $dbMakeLiveFlag = (int)$makeLiveFlag;
    $dbMakeFocusFlag = (int)$makeFocusFlag;
    $curlPostParams = "projectId={$projectMetadata['project_id']}&user={$userData['user_id']}&checkCode={$userData['auth_check_code']}&makeLive=$dbMakeLiveFlag&makeFocus=$dbMakeFocusFlag";
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $curlUrl);
    curl_setopt($c, CURLOPT_POSTFIELDS, $curlPostParams);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
    curl_setopt($c, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
    curl_setopt($c, CURLOPT_TIMEOUT, 1);
    curl_exec($c);
    curl_close($c);
} else {
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}



$embeddedCSS .= <<<EOL
    .queued {
        color: red;
    }

    .working {
        color: orange;
    }

    .complete {
        color: green;
    }

    #finishButton {
        width: 200px;
    }
EOL;

$javaScript .= <<<EOL
    var projectId = {$projectMetadata['project_id']};
    var progressCheckTimer;

    function updateProgress() {
        $.getJSON('ajax/projectFinalizerProgressChecker.php', {projectId: projectId}, function(finalizerProgress) {

            switch (finalizerProgress.stage) {
                case 1:
                    $('.progressBarFill').css('width', finalizerProgress.progressPercentage + '%');
                    $('.progressBarText').text('Finalizing Pre Collection').css('left', '28%');
                    $('#preCollectionStatus').removeClass('queued').addClass('working').text('Working');
                    break;
                case 2:
                    $('.progressBarFill').css('width', finalizerProgress.progressPercentage + '%');
                    $('.progressBarText').text('Finalizing Post Collection').css('left', '28%');
                    $('#preCollectionStatus').removeClass('queued working').addClass('complete').text('Complete');
                    $('#postCollectionStatus').addClass('working').text('Working');
                    break;
                case 3:
                    $('.progressBarFill').css('width', finalizerProgress.progressPercentage + '%');
                    $('.progressBarText').text('Finalizing Collection Matches').css('left', '25%');
                    $('#preCollectionStatus, #postCollectionStatus').removeClass('queued working').addClass('complete').text('Complete');
                    $('#matchStatus').addClass('working').text('Working');
                    break;
                case 4:
                    $('.progressBarFill').css('width', finalizerProgress.progressPercentage + '%');
                    $('.progressBarText').text('Setting Project Preferences').css('left', '25%');
                    $('#preCollectionStatus, #postCollectionStatus, #matchStatus').removeClass('queued working').addClass('complete').text('Complete');
                    $('#preferencesStatus').addClass('working').text('Working');
                    break;
                case 5:
                    clearInterval(progressCheckTimer);
                    $('.progressBar').hide();
                    $('#finalizingProgressTable').hide();
                    $('#finalizingDetailsWrapper').hide();
                    $('#finishedWrapper').show();
                    $('#finishButtonForm').show();
                    break;
                case 10:
                    clearInterval(progressCheckTimer);
                    $('.progressBar').hide();
                    $('#finalizingProgressTable').hide();
                    $('#finalizingDetailsWrapper').hide();
                    $('#failedWrapper').show();
                    $('#finishButtonForm').attr('action', 'projectCreator.php').show();
                    break;
                default:
                    break;
            }
        });
    } 
EOL;


$jQueryDocumentDotReadyCode .= <<<EOL
    progressCheckTimer = setInterval(function() {
        updateProgress();
    }, 2000);
        
EOL;
