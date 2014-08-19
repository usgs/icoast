<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE PHP
ob_start();
$pageModifiedTime = filemtime(__FILE__);




// END PAGE PHP
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CODE PAGE PHP

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];

// Determine the number of users in iCoast
$numberUsersResult = $DBH->query("SELECT COUNT(*) FROM users");
$numberUsers = $numberUsersResult->fetchColumn();
print "Number of users: " . $numberUsers . '<br>';

// Determine the number of users per Crowd Type
$crowdTypeStats = array();

$usersPerCrowdTypeQuery = 'SELECT COUNT(*) AS count_users, u.crowd_type, ct.crowd_type_name '
        . 'FROM users u '
        . 'LEFT JOIN crowd_types ct ON crowd_type = crowd_type_id '
        . 'GROUP BY crowd_type';
$usersPerCrowdTypeResults = $DBH->query($usersPerCrowdTypeQuery);
while ($row = $usersPerCrowdTypeResults->fetch(PDO::FETCH_ASSOC)) {
    if ($row['crowd_type'] == 0) {
        $crowdTypeStats['Other'] = $row['count_users'];
    } else {
        $crowdTypeStats[$row['crowd_type_name']] = $row['count_users'];
    }
}
print "Users per Crowd type<br>";
print "<pre>";
print_r($crowdTypeStats);
print "</pre>";
//
//
//
//
// Determine the number of photos each user has annotated
$userClassificationCount = array();
$classificationsPerUserQuery = 'SELECT COUNT(*) AS classification_count, a.user_id, u.encrypted_email, u.encryption_data '
        . 'FROM annotations a '
        . 'LEFT JOIN users u ON a.user_id = u.user_id '
        . 'WHERE a.annotation_completed = 1 '
        . 'GROUP BY a.user_id '
        . 'ORDER BY classification_count DESC';
$classificationsPerUserResults = $DBH->query($classificationsPerUserQuery);
if ($classificationsPerUserResults === FALSE) {
    print $DBH->errorCode();
    print $DBH->errorInfo();
} else {
    while ($row = $classificationsPerUserResults->fetch(PDO::FETCH_ASSOC)) {
        $email = mysql_aes_decrypt($row['encrypted_email'], $row['encryption_data']);
        $userClassificationCount[$email] = $row['classification_count'];
    }
}
print "Number of Photos each user has classified:<br>";
print "<pre>";
print_r($userClassificationCount);
print "</pre>";

function convertSeconds($s) {
    $hrs = floor($s / 3600);
    $mins = floor(($s % 3600) / 60);
    $secs = ($s % 3600) % 60;
    if ($hrs > 0) {
        return "$hrs Hour(s) $mins Minute(s) $secs Second(s)";
    } elseif ($mins > 0) {
        return "$mins Minute(s) $secs Second(s)";
    } else {
        return "$secs Second(s)";
    }
}

if (isset($_GET['averageUpperLimit'])) {
    settype($_GET['averageUpperLimit'], 'integer');
    if (!empty($_GET['averageUpperLimit'])) {
        $upperTimeLimit = $_GET['averageUpperLimit'];
    }
}
if (!isset($upperTimeLimit)) {
    $upperTimeLimit = 3600;
}

if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
    }
}

if (isset($_GET['targetUserId'])) {
    settype($_GET['targetUserId'], 'integer');
    if (!empty($_GET['targetUserId'])) {
        $targetUserId = $_GET['targetUserId'];
    }
}

$classificationCount = 0;
$timeTotal = 0;
$excessiveTimeCount = 0;
$longestClassification = 0;
$shortestClassification = 0;
$avgTimeQuery = "SELECT initial_session_start_time, initial_session_end_time FROM annotations WHERE annotation_completed = 1 AND annotation_completed_under_revision = 0";
if (isset($targetUserId)) {
    $avgTimeQuery .= " AND user_id = :userId";
    $avgTimeParams['userId'] = $targetUserId;
}

if (isset($targetProjectId)) {
    $avgTimeQuery .= " AND project_id = :projectId";
    $avgTimeParams['projectId'] = $targetProjectId;
}

if (!isset($avgTimeParams)) {
    $avgTimeParams = array();
}

$avgTimeResults = run_prepared_query($DBH, $avgTimeQuery, $avgTimeParams);
while ($classification = $avgTimeResults->fetch(PDO::FETCH_ASSOC)) {

    $startTime = strtotime($classification['initial_session_start_time']);
    $endTime = strtotime($classification['initial_session_end_time']);
    $timeDelta = $endTime - $startTime;
    if ($timeDelta < $upperTimeLimit) {
        $timeTotal += $timeDelta;
        $classificationCount++;
        if ($timeDelta > $longestClassification) {
            $longestClassification = $timeDelta;
        } else if ($timeDelta < $shortestClassification || $shortestClassification == 0) {
            $shortestClassification = $timeDelta;
        }
    } else {
        $excessiveTimeCount++;
    }

//    print "{$classification['initial_session_start_time']} ($startTime) - {$classification['initial_session_end_time']} ($endTime) = $timeDelta. Total = $timeTotal. Count = $classificationCount<br>";
}
if ($classificationCount > 0) {
    $averageTime = $timeTotal / $classificationCount;
    print "Average Time: " . convertSeconds($averageTime) . "<br>";
    print "Longest Time: " . convertSeconds($longestClassification) . "<br>";
    print "Shortest Time: " . convertSeconds($shortestClassification) . "<br>";
} else {
    print "No Classsifications found. No average calculated<br>";
}

if ($excessiveTimeCount + $classificationCount > 0) {
    print $excessiveTimeCount . " out of " . ($excessiveTimeCount + $classificationCount) . " classification(s) exceeded the " . convertSeconds($upperTimeLimit) . " limit and are excluded from the average.<br>";
}








// END CODE PAGE PHP
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


require("includes/feedback.php");
require("includes/templateCode.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <meta name="description" content=" “USGS iCoast - Did the Coast Change?” is a USGS research project to
              construct and deploy a citizen science web application that asks volunteers to compare pre- and
              post-storm aerial photographs and identify coastal changes using predefined tags. This
              crowdsourced data will help USGS improve predictive models of coastal change and educate the
              public about coastal vulnerability to extreme storms.">
        <meta name="author" content="Snell, Poore, Liu">
        <meta name="keywords" content="USGS iCoast, iCoast, Department of the Interior, USGS, hurricane, , hurricanes,
              extreme weather, coastal flooding, coast, beach, flood, floods, erosion, inundation, overwash,
              marine science, dune, photographs, aerial photographs, prediction, predictions, coastal change,
              coastal change hazards, hurricane sandy, beaches">
        <meta name="publisher" content="U.S. Geological Survey">
        <meta name="created" content="20140328">
        <meta name="review" content="20140328">
        <meta name="expires" content="Never">
        <meta name="language" content="EN">
        <link rel="stylesheet" href="css/icoast.css">
        <link rel="stylesheet" href="http://www.usgs.gov/styles/common.css" />
        <link rel="stylesheet" href="css/custom.css">
        <link rel="stylesheet" href="css/tipTip.css">
        <?php print $cssLinks; ?>
        <style>
<?php
print $feedbackEmbeddedCSS . "\n\r";
print $embeddedCSS;
?>
        </style>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <script src="scripts/tipTip.js"></script>
        <?php print $javaScriptLinks; ?>
        <script>
<?php
print $feedbackJavascript . "\n\r";
print $javaScript . "\n\r";
?>

            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            // JAVASCRIPT





            // END JAVASCRIPT
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////


            $(document).ready(function() {
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                // JAVASCRIPT DOCUMENT.READY




                // END JAVASCRIPT DOCUMENT.READY
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            });

<?php
print $feedbackjQueryDocumentDotReadyCode . "\n\r";
print $jQueryDocumentDotReadyCode . "\n\r";
?>

        </script>
    </head>
    <body>
        <!--Header-->
        <?php require('includes/header.txt') ?>

        <!--Page Body-->
        <a href="#skipmenu" title="Skip this menu"></a>
        <div id='navigationBar'>
            <?php print $mainNav ?>
        </div>
        <a id="skipmenu"></a>

        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // PAGE HTML CODE -->



        <div id="adminPageWrapper">
            <?php print $adminNavHTML ?>
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData"><?php print $maskedEmail ?></span>. Your admin level is
                        <span class="userData"><?php print $adminLevelText ?></span></p>
                </div>

            </div>
        </div>



        <!--END PAGE HTML CODE
        //////////////////////////////////////////////////////////////////////////////////////////////////////////-->


        <?php
        print $feedbackPageHTML;
        require('includes/footer.txt');
        ?>

        <div id="alertBoxWrapper">
            <div id="alertBoxCenteringWrapper">
                <div id="alertBox">
                    <?php print $alertBoxContent; ?>
                    <div id="alertBoxControls">
                        <?php print $alertBoxDynamicControls; ?>
                        <input type="button" id="closeAlertBox" class="clickableButton" value="Close">
                    </div>
                </div>
            </div>
        </div>

    </body>
</html>


