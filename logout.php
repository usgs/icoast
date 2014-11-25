<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/logoutCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <div id="logoffImageColumn">
            <div id="logoffImageWrapper">
                <img src="images/system/rodanthe.jpg"
                    alt="An image of the North Carolina coast at Rodanthe where inundation and infrastructure
                        damage following Hurricane Sandy is clearly visible." height="357" width="550" title="" />
            </div>
            <div id="logoffImageCaptionWrapper">
                <p><span class="captionTitle" id="captionTitle">Rodanthe, NC.</span> <span id="captionText">
                    Hurricane Sandy caused this section of coastline to experience inundation with resulting
                    damage to housing and infrastructure.</span></p>
            </div>
        </div>
        <div id="logoffTextColumn">
            <h1>Logged Out of USGS iCoast</h1>
            <p>You have successfully logged out of iCoast</p>
            <form method="post" action="index.php?login">
                <div class="formFieldRow standAloneFormElement">
                    <input type="submit" class = "clickableButton formButton" value="Login to iCoast"
                        title="This button takes you to the iCoast login page." />
                </div>
            </form>
        </div>
    </div>
EOL;

require_once("includes/template.php");
