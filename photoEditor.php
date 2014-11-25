<?php

require('includes/pageCode/photoEditorCode.php');
$pageBody = <<<EOL

    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>. Your admin level is
                    <span class="userData">$adminLevelText</span></p>
            </div>
            <div>
                <h1 id="photoSelectHeader">Photo Editor</h1>
                $photoSelectHTML
                $displaySelectHTML
                $photoDetailsHTML
            </div>
        </div>
    </div>

EOL;

require('includes/template.php');