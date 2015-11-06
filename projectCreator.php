<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/projectCreatorCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1> iCoast Project Creator</h1>
                    $pageContentHTML
                </div>
            </div>
        </div>
EOL;

require('includes/template.php');
