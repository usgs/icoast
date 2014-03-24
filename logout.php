<?php

ob_start();
require('includes/pageCode/logoutCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Logged Out of iCoast</h1>
        <p>You have successfully logged out of iCoast</p>
        <form method="post" action="login.php">
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class = "clickableButton formButton" value="Login to iCoast"
                    title="This button takes you to the iCoast login page." />
            </div>
        </form>
    </div>
EOL;

require("includes/template.php");
