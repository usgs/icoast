<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/modifyCollectionCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1> iCoast "{$projectMetadata['name']}" Project Creator</h1>
                    $modifyPageHTML


                </div>
            </div>
        </div>
EOL;

require('includes/template.php');
