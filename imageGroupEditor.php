<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/imageGroupEditorCode.php');
$pageBody = <<<EOL
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <div>
                <h1>Image Group Management</h1>
                <h2 id="projectSelectHeader">Select a Project</h2>
                <p>To manage image groups please first select a project from the list below.</p>
                <form method="get" autocomplete="off" action="#projectSelectHeader">
                    <div class="formFieldRow">
                        <label for="analyticsProjectSelection">Project: </label>
                        <select id="analyticsProjectSelection" class="formInputStyle" name="targetProjectId">
                            $projectSelectHTML
                        </select>
                        <input type="submit" class="clickableButton" value="Select Project">
                    </div>
                </form>

                $projectSelectionDetailsHTML
                $userGroupProgressHTML

            </div>

        </div>
    </div>
EOL;

require('includes/template.php');
