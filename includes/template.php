<?php
require("feedback.php");
require("includes/templateCode.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
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
<?php
print $feedbackJavascript . "\n\r";
print $javaScript . "\n\r";
print $feedbackjQueryDocumentDotReadyCode . "\n\r";
print $jQueryDocumentDotReadyCode;
?>
        </script>
    </head>
    <body>
        <!--Header-->
        <?php require('includes/header.txt') ?>

        <!--Page Body-->
        <div id='navigationBar'>
            <?php print $mainNav ?>
        </div>

        <?php
        print $pageBody;
        print $feedbackPageHTML;
        require('includes/footer.txt');
        ?>

    </body>
</html>


