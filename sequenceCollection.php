<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/sequenceCollectionCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "{$collectionMetadata['name']}" Collection Creator</h1>
                <h2>Collection Sequencing </h2>
                $pageContentHTML
            </div>
        </div>

EOL;

require('includes/template.php');
