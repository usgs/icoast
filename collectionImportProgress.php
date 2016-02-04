<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/collectionImportProgressCode.php');
$pageBody = <<<HTML
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1> iCoast "{$collectionMetadata['name']}" Collection Creator</h1>
                    <h2>Import Progress</h2>
                    <div id="importTextWrapper"></div>
                    <div id="collectionProgressWrapper"></div>
                    <form id="continueForm" method="post" autocomplete="off" action="refineCollectionImport.php?collectionId=$collectionId">
                        <input type="hidden" name="importComplete">
                        <button type="submit" id="continueButton" class="clickableButton enlargedClickableButton disabledClickableButton"
                                title="If you are happy with the import result then select this button to continue to the next creation stage" disabled>
                            Continue Collection Creation
                        </button>
                    </form>
                </div>
            </div>
HTML;

require('includes/template.php');
