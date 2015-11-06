<?php

require('includes/pageCode/photoEditorCode.php');
$pageBody = <<<EOL

    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <div>
                <h1 id="photoSelectHeader">Photo Editor</h1>
                $targetSelectionHTML
                $targetHTML
            </div>
        </div>
    </div>

EOL;

require('includes/template.php');