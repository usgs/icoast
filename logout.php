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
            <p>You have successfully logged out of iCoast but remain logged into Google.
                To also log out of Google in this browser use the button below.</p>
            <form method="post" action="https://accounts.google.com/logout">
                <div class="formFieldRow standAloneFormElement">
                    <input type="submit" class = "clickableButton formButton" value="Logout of Google"
                        title="This button will log you out of Google on this browser." />
                </div>
            </form>
        </div>
    </div>
EOL;

require_once("includes/template.php");
