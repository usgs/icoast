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
$matchRadius = filter_input(INPUT_GET, 'matchRadius', FILTER_VALIDATE_INT);
$hostURL = filter_input(INPUT_SERVER, 'HTTP_HOST');

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata)) {
    header('Location: projectCreator.php?error=MissingProjectId');
    exit;
} else if ($projectMetadata['creator'] != $userId ||
        $projectMetadata ['is_complete'] == 1) {
//    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}

$importStatus = project_creation_stage($projectMetadata['project_id']);
if ($importStatus != 40 && $importStatus != 50) {
//    print $importStatus;
    header('Location: projectCreator.php?error=InvalidProject');
    exit;
}
unset($projectId);

if ($matchRadius && $matchRadius >= 200 && $matchRadius <= 1500) {
    $matchRadius = floor($matchRadius / 100) * 100;
} else {
    $matchRadius = 400;
}


if (is_null($projectMetadata['matching_progress']) || $importStatus == 50) {
        if (strcasecmp($hostURL, 'localhost') === 0 || strcasecmp($hostURL, 'igsafpesvm142') === 0) {
        $curlUrlHost = "http://localhost";
    } else if (strcasecmp($hostURL, 'coastal.er.usgs.gov') === 0) {
        $curlUrlHost = "http://coastal.er.usgs.gov/icoast";
    } else {
        header('Location: projectCreator.php');
        exit;
    }
    $curlUrl = $curlUrlHost . "/scripts/collectionMatcher.php";
    $curlPostParams = "projectId={$projectMetadata['project_id']}&matchRadius=$matchRadius&user={$userData['user_id']}&checkCode={$userData['auth_check_code']}";
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $curlUrl);
    curl_setopt($c, CURLOPT_POSTFIELDS, $curlPostParams);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
    curl_setopt($c, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
    curl_setopt($c, CURLOPT_TIMEOUT, 1);
    curl_exec($c);
    curl_close($c);
}




$javaScript = <<<EOL
    var progressCheckTimer;

    function updateProgress() {
        $.getJSON('ajax/matchingProgressCheck.php', {projectId: {$projectMetadata['project_id']}}, function(matchProgress) {
            if (matchProgress.progress !== 0 &&
                    matchProgress.progress <= 100) {
                $('.progressBarFill').css('width', matchProgress.progress + '%');
                $('.progressBarText').css('left', '30%');
                $('.progressBarText').text(matchProgress.progress + '% of Images Analyzed');
            } else if (matchProgress.progress >= 200) {
                clearInterval(progressCheckTimer);
                $('.progressBarFill').css('width', '100%');
                $('.progressBarFill').addClass('completeProgressBarFill').removeClass('progressBarFill');
                $('.progressBarText').css('left', '33%');
                $('.progressBarText').text('Matching Complete');
                $('#reviewButton').show();

                if (matchProgress.percentageWithMatches === 0) {
                    $('.progressBar').remove();
                    $('#matchingProgressDetailsWrapper').empty().html(' \
                        <p class="error">Matching has failed.</p> \
                        <p class="error">No images in your post-event collection images were found to have a nearby match in the pre-event \
                            collection</p> \
                        <p>The most likely cause of this problem is that you uploaded/chose a pair of collections \
                            that did not cover the same region.</p> \
                        <p>You may correct this by deleting one or both collections \
                            on the Review screen (next)</p> \
                        <p>Click the button below to review the details of your new project in full.</p>'
                        );


                } else if (matchProgress.percentageWithMatches < 50) {
                    $('#matchingProgressDetailsWrapper').empty().html(' \
                        <p class="error">Matching has been completed but with low success.</p> \
                        <p>Only <span class="error">' + matchProgress.percentageWithMatches + '% (' + matchProgress.numberWithMatches +
                            ')</span> of your post-event collection images had a nearby match in the pre-event collection.</p> \
                        <p>If a large percentage of the areas covered by both your collections overlap then a possible cause of this low match \
                            percentage is that the images in the two collection are not within the default match radius (400m) of each \
                            other. Look at the review map on the next screen to determine the problem. From there you can replace your \
                            collections to increase the amount of overlap or increase the match search radius and try to match the existing \
                                collections again.</p> \
                        <p>Only images with matches will be presented to users who view your project.<br>Did you \
                            upload/choose the correct pair of collections? Changes can be made in the Review screen (next).</p> \
                        <p>Click the button below to review the details of your new project in full.</p>'
                        );


                } else {
                    $('#matchingProgressDetailsWrapper').empty().html(' \
                        <p>Matching has been completed.</p> \
                        <p><span class="userData">' + matchProgress.percentageWithMatches + '% (' + matchProgress.numberWithMatches +
                            ')</span> of your post-event collection images had a nearby match in the pre-event collection.</p> \
                        <p>If the match percentage is not as high as expected then it is possible that the images in the two collection are \
                            not within the default match radius (400m) of each  other. Look at the review map on the next screen to \
                            determine if this is the case. From there you can replace your collections if necessary or increase the \
                           match search radius and try to match the existing collections again.</p>'
                        );
                }



            }
        });
    }

EOL;

$jQueryDocumentDotReadyCode .= <<<EOL
    progressCheckTimer = setInterval(function() {
        updateProgress()
    }, 2000);

EOL;
