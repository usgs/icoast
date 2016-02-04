<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/finalizeCollectionCode.php');
$pageBody = <<<HTML
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "{$collectionMetadata['name']}" Collection Creator</h1>
                <h2>Finalizing Collection</h2>
                <div id="finalizingDetailsWrapper">
                    <p>Your collection is being committed to the database. This may take a few moments</p>
                </div>

                <div id="finishedWrapper" style="display:none;">
                    <h3>Collection Complete</h3>
                    <p>Congratulations. You collection has been successfully inserted into iCoast.</p>
                    <p>You can use the <a href="projectCreator.php">Project Creator</a> link in the administrator links
                        (left) to use this collection in a new Project.  You may also disable unwanted images using the
                        <a href="photoEditor.php">Photos</a> link.</p>
                </div>
                <div class="progressBar">
                    <div class="progressBarFill" style="width: 0%"></div>
                    <span class="progressBarText" style="left: 40%">Initializing</span>
                </div>

                <form id="finishButtonForm" method="get" autocomplete="off" action="eventViewer.php" style="display:none">
                    <button type="submit" id="finishButton" class="clickableButton enlargedClickableButton">
                        Finish
                    </button>
                </form>

            </div>
        </div>
HTML;

require('includes/template.php');
