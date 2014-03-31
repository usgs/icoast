<?php

ob_start();
require('includes/pageCode/logoutCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <div id="welcomeImageColumn">
            <div id="welcomeImageWrapper">
                <img src="images/system/indexImages/rodanthe.jpg"
                    alt="An image of the North Carolina coast at Rodanthe where inundation and infrastructure
                        damage following Hurricane Sandy is clearly visible." height="357" width="550" title="" />
            </div>
            <div id="welcomeImageCaptionWrapper">
                <p><span class="captionTitle" id="captionTitle">Rodanthe, NC.</span> <span id="captionText">
                    Hurricane Sandy caused this section of coastline to experience inundation with resulting
                    damage to housing and infrastructure.</span></p>
            </div>
        </div>
        <div id="welcomeTextColumn">
            <h1>Logged Out of iCoast</h1>
            <p>You have successfully logged out of iCoast</p>
            <form method="post" action="index.php">
                <div class="formFieldRow standAloneFormElement">
                    <input type="submit" class = "clickableButton formButton" value="Login to iCoast"
                        title="This button takes you to the iCoast login page." />
                </div>
            </form>
        </div>
    </div>
EOL;

require("includes/template.php");
