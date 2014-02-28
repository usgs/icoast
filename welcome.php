<?php
ob_start();
require('includes/pageCode/welcomeCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Welcome $welcomeBackHTML to iCoast</h1>
        <p>You are logged in as <span class="userData">$userEmail</span>.<br>
            If this is not you, Logout then Login with your Google Account.</p>
        $variableContent
    </div>
EOL;

require('includes/template.php');