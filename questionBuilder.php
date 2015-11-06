<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/questionBuilderCode.php');
$pageBody = <<<EOL

    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <h1> iCoast "{$projectMetadata['name']}" Project Creator</h1>
            <h2>Question Builder Tool</h2>
            $instructionHTML
            <div id="actionSelectWrapper">
                $projectUpdateErrorHTML
            </div>
            <div id="actionSelectWrapper">
                $actionSelctionHTML
            </div>
            <div id="actionControls">
                $actionControlsHTML
            </div>
            <div id="actionSummary">
                $actionSummaryHTML
            </div>
        </div>
    </div>
        
EOL;

require('includes/template.php');
