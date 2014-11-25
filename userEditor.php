<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE PHP
ob_start();
$pageModifiedTime = filemtime(__FILE__);

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

$emailArray = array();
$emailCount = 0;

$userEmailQuery = "SELECT encrypted_email, encryption_data FROM users WHERE allow_email = 1";
foreach ($DBH->query($userEmailQuery) as $user) {
    $arrayContainerIndex = floor($emailCount / 500);
    $emailCount ++;
    $email = mysql_aes_decrypt($user['encrypted_email'], $user['encryption_data']);
    $emailArray[$arrayContainerIndex][] = $email;
}

$mailGroupCount = 0;
$recipientString = '';
foreach ($emailArray as $emailGroup) {
    $mailGroupCount ++;
    $recipientString .= '<h2>Email Group ' . $mailGroupCount . '</h2><p class="emailList">';
    foreach ($emailGroup as $email) {
        $recipientString .= "$email ";
    }
    $recipientString .= '</p>';
}
$jsNumberOfGroups = $mailGroupCount;




// END PAGE PHP
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CODE PAGE PHP
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

var mailGroupCount = <?php print $jsNumberOfGroups ?>;



// END JAVASCRIPT
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


            $(document).ready(function() {
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// JAVASCRIPT DOCUMENT.READY


                $('#showEmailListButton').on('click', function() {
                    $('#emailListWrapper').show();
                    moveFooter();
                    for (var i = 1; i <= mailGroupCount; i++) {
                        window.location.href = "mailto:?bcc=PASTE-USER-ADDRESSES-HERE.DO-NOT-USE-TO-OR-CC-FIELDS&subject=Copy%20mail%20subject%20here&body=Copy%20mail%20content%20here.";
                    }
                });



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
                <input type="button" value="Prepare Bulk Email" id="showEmailListButton" class="clickableButton">
                <div id="emailListWrapper">
                    <h1>User E-Mail List</h1>
                    <p>Gmail limitations restrict the maximum number of recipients per mail to 500 addresses.<br>
                        As a result the iCoast user list is broken down into groups (below) that do not exceed this length.<br>
                       Copy each list into the BCC field of separate emails and then duplicate (copy/paste) all desired content.<br>
                       Alternativley mail the 1st user group and then forward that mail (removing any subject and body prefix/suffix) to the other user groups, again remembering to use the BCC field for addresses.<br>
                       Be sure to add an unsubscribe footer to the email directing users to their "profile" page (<a href="http://coastal.er.usgs.gov/icoast/profile.php">http://coastal.er.usgs.gov/icoast/profile.php</a>) where they can unsubscribe.<br>
                       The required number of emails have automatically been opened in your email system (ensure you will be sending your emails from the correct account (usgsicoast@usgs.gov)).
                    </p>
                    <p class="redHighlight">Do not paste the lists into the TO or CC fields. This will expose user addresses to all other recipients and compromise user security.<br>
                        <span style="font_weight: bold">ONLY PASTE THESE ADDRESSES IN TO THE BCC FIELD OF THE EMAIL!</span></p>
                    <?php print $recipientString ?>
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


