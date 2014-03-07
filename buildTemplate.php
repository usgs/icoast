<?php
ob_start();
?>
<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
PHP Code file contents (PHP Generated Output)-->
<?php
?>



<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
Javascript Code File Contents-->
<?php
if (count($javaScriptLinkArray) > 0) {
    foreach ($javaScriptLinkArray as $link) {
        $javaScriptLinks .= "<script src='$link'></script>\n\r";
    }
}
print $javaScriptLinks;
?>
<script>
//  Content Start         ##############################


//  Content End           ##############################
</script>



<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
Javascript Document.Ready Code File Contents-->
<script>
    $(document).ready(function() {
//  Content Start     ##############################


//  Content End       ##############################
    });
</script>



<!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
Template - DO NOT ALTER-->
<?php
require("includes/templateCode.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
        <link rel="stylesheet" href="css/header.css">
        <link rel="stylesheet" href="css/icoast.css">
        <?php
        print $cssLinks;
        ?>
        <style>
<?php print $embeddedCSS; ?>
        </style>

        <?php // print $javaScriptLinks; ?>
        <script>
<?php
//        print $javaScript . "\n\r";
//        print $jQueryDocumentDotReadyCode;
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
                <img src="images/system/hurricaneBanner.jpg" alt="An image from Space of a hurricane approaching the Florida coastline." />
            </div>
            <?php print $mainNav ?>
        </div>

        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        Load file contents (HTML Page Body)-->



        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        END Load file contents (HTML Page Body)-->

    </body>
</html>


