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
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];

$searchParams = array();

if (isset($_GET['project'])) {
    $searchParams['project'] = $_GET['project'];
}

if (isset($_GET['eventType'])) {
    $searchParams['eventType'] = $_GET['eventType'];
}

if (isset($_GET['user'])) {
    $searchParams['user'] = $_GET['user'];
}

if (isset($_GET['sourceURL'])) {
    $searchParams['sourceURL'] = $_GET['sourceURL'];
}

if (isset($_GET['sourceScript'])) {
    $searchParams['sourceScript'] = $_GET['sourceScript'];
}

if (isset($_GET['sourceFunction'])) {
    $searchParams['sourceFunction'] = $_GET['sourceFunction'];
}

if (isset($_POST['read'])) {
    $searchParams['read'] = TRUE;
}

if (isset($_POST['closed']) && $_POST['closed'] == 1) {
    $searchParams['closed'] = TRUE;
}

if (isset($_POST['orderBy'])) {
    $searchParams['orderBy'] = $_POST['orderBy'];
}


if (isset($_POST['sortDirection'])) {
    $searchParams['sortOrder'] = $_POST['sortDirection'];
}

if (isset($_POST['startRow'])) {
    $searchParams['startResultRow'] = $_POST['startRow'];
}

if (isset($_POST['resultSize'])) {
    $searchParams['resultSize'] = 10;
}

$ajaxDataObject = 'ajaxData = {';
foreach ($searchParams as $key => $value) {
    $javascriptVariables .= "$key: $value";
}

$ajaxDataObject .= '}';



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
        <?php
        print $cssLinks;
        ?>
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
<?php print $feedbackJavascript . "\n\r"; ?>

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// JAVASCRIPT


    function runAjaxEventQuery() {
        <?php print $ajaxDataObject ?>
        $.post('ajax/eventLogViewer.php', ajaxData, displayEvents, 'json');
    }

    function displayEvents(ajaxResult) {

    }






// END JAVASCRIPT
//////////////////////////////////////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// JAVASCRIPT DOCUMENT.READY




// END JAVASCRIPT DOCUMENT.READY
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


<?php print $feedbackjQueryDocumentDotReadyCode . "\n\r"; ?>
            (function(i, s, o, g, r, a, m) {
                i['GoogleAnalyticsObject'] = r;
                i[r] = i[r] || function() {
                    (i[r].q = i[r].q || []).push(arguments)
                }, i[r].l = 1 * new Date();
                a = s.createElement(o),
                        m = s.getElementsByTagName(o)[0];
                a.async = 1;
                a.src = g;
                m.parentNode.insertBefore(a, m)
            })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

            ga('create', 'UA-49706884-1', 'icoast.us');
            ga('require', 'displayfeatures');
            ga('send', 'pageview');

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
                <h1>iCoast Event Log Viewer</h1>
                <div id="eventLogTableWrapper">
                    <h2>Events Summary</h2>
                </div>
                <div id="eventDetailsWrapper">
                    <h2>Event Details</h2>
                </div>
                <div id="eventLogErrorWrapper">
                    <p>Error Text</p>
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


