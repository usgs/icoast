<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/finalizeProjectCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "{$projectMetadata['name']}" Project Creator</h1>
                <h2>Finalizing Project</h2>
                <div id="finalizingDetailsWrapper">
                    <p>Your project is being committed to the database. This may take a few moments</p>
                </div>

                <div id="finishedWrapper" style="display:none;">
                    <h3>Project Complete</h3>
                    <p>Congratulations. You project has been successfully inserted into iCoast.</p>
                    $liveOptionHTML
                    <p>You can use the <a href="projectEditor.php">Project Editor</a> link in the administrator links
                        (left) to change the project status (live or disabled) make changes to the project details,
                        and alter tasks, groups (questions), or tags. You may also disable images using the
                        <a href="photoEditor.php">Photos</a> link.</p>
                </div>
                <div id="failedWrapper" style="display:none;">
                    <p class='error'>Project Failed Finalization</p>
                    <p>Sorry, unfortunately an error occurred during the finalization process.</p>
                    <p>Either seek the assistance of the iCoast developer or delete the project in the
                        <span class="italic">Project Creator</span> screen and try again.</p>
                </div>

                <table id="finalizingProgressTable" class="adminStatisticsTable">
                    <tbody>
                        <tr>
                            <td>Copying Pre-Event Collection Data:</td>
                            <td id="preCollectionStatus" class="queued">Queued</td>
                        </tr>
                        <tr>
                            <td>Copying Post-Event Collection Data:</td>
                            <td id="postCollectionStatus" class="queued">Queued</td>
                        </tr>
                        <tr>
                            <td>Copying Collection Image Match Data:</td>
                            <td id="matchStatus"class="queued">Queued</td>
                        </tr>
                        <tr>
                            <td>Setting Project Preferences:</td>
                            <td id="preferencesStatus"class="queued">Queued</td>
                        </tr>
                    </tbody>
                </table>

                <div class="progressBar">
                    <div class="progressBarFill" style="width: 0%"></div>
                    <span class="progressBarText" style="left: 40%">Initializing</span>
                </div>

                <form id="finishButtonForm" method="get" autocomplete="off" action="index.php" style="display:none">
                    <button type="submit" id="finishButton" class="clickableButton enlargedClickableButton">
                        Finish
                    </button>
                </form>

            </div>
        </div>
EOL;

require('includes/template.php');
