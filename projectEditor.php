<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/projectEditorCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
$adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>. Your admin level is
                        <span class="userData">$adminLevelText</span></p>
                </div>
                <h1>iCoast Project Editor</h1>
                    $projectSelectHTML
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
