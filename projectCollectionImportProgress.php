<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/projectCollectionImportProgressCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1> iCoast "{$projectMetadata['name']}" Project Creator</h1>
                    <h2>Collection Import Progress</h2>
                    <div id="importTextWrapper"></div>
                    <hr>
                    <div id="preEventCollectionProgressWrapper"></div>
                    <hr>
                    <div id="postEventCollectionProgressWrapper"></div>
                    <div id="continueProjectCreationButton"></div>
                    <hr>
                    <form id="continueForm" method="post" autocomplete="off">
                        <input type="hidden" name="importComplete">
                        <button type="submit" id="continueButton" class="clickableButton enlargedClickableButton disabledClickableButton"
                                title="If you are happy with the collection
                                imports select this button to continue to the next project creation stage" disabled>
                            Continue Project Creation
                        </button>
                    </form>
                </div>
            </div>
        </div>
EOL;

require('includes/template.php');
