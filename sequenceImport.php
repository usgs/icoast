<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/sequenceImportCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "{$projectMetadata['name']}" Project Creator</h1>
                <h2>$prePostTitleText-Event Collection Sequencing </h2>
                <h3>{$collectionMetadata['name']}</h3>
                $pageContentHTML
            </div>
        </div>

EOL;

require('includes/template.php');
