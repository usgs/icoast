<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/matchCollectionsCode.php');
$pageBody = <<<EOL
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <h1>iCoast "{$projectMetadata['name']}" Project Creator</h1>
            <h2>Generating Image Matches </h2>
            <div id="matchingProgressDetailsWrapper">
                <p>The images from your selected collections are being compared to find those within close
                    proximity to each other. Post-event collection images that are found to have a nearby
                    pre-event collection image will be the ones available for classification in your project.</p>
                <p>This matching process may take a few minutes. Progress can be monitored below.</p>
            </div>

            <div class="progressBar">
                <div class="progressBarFill" style="width: 0%"></div>
                <span class="progressBarText" style="left: 40%">Initializing</span>
            </div>

            <form id="reviewButton" method="get" autocomplete="off" action="reviewProject.php" style="display:none">
                <input type="hidden" name="projectId" value="{$projectMetadata['project_id']}">
                <button type="submit" class="clickableButton enlargedClickableButton">
                    Review Project
                </button>
            </form>

        </div>
    </div>
EOL;

require('includes/template.php');
