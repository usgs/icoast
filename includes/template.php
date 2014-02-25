<?php
require("includes/templateCode.php");
?>

<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?>"</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
        <link rel="stylesheet" href="css/icoast.css">
        <?php
        print $headerCSSLink;
        print $pageSpecificExternalCSSLinks;
        ?>
        <style>
        <?php print $pageSpecificEmbeddedCSS; ?>
        </style>
        <?php print $pageSpecificJavaScriptLinks; ?>
        <script>
        <?php
        print $pageSpecificJavaScript;
        if (!empty($pageSpecificJQueryDocumentReadyCode)) {
            print <<<EOL
                $(document).ready(function() {
                    $pageSpecificJQueryDocumentReadyCode
                });
EOL;
        }
        ?>
        </script>
    </head>
    <body>
        <!--Header-->
        <div id="usgsColorBand">
            <div id="usgsIdentifier">
                <a href="http://www.usgs.gov">
                    <img src="images/system/usgsIdentifier.jpg" alt="USGS - science for a changing world"
                         title="U.S. Geological Survey Home Page" width="178" height="72" />
                </a>
                <p id="appTitle">iCoast</p>
                <p id="appSubtitle">did the coast change?</p>
            </div>
            <div id="headerImageWrapper">
                <img src="images/system/hurricaneBanner.jpg" />
            </div>
            <?php print $mainNavHTML ?>
        </div>

        <!--Page Body-->
        <?php
        print $pageBodyHTML;
        ?>

    </body>
</html>


