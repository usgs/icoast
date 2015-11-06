<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/photoStatsCode.php');
$pageBody = <<<EOL
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <div>
                <h1>Photo Statistics</h1>
                $photoSelectHTML
                <form method="get" autocomplete="off" id="photoSelectionForm" action="#photoSelectHeader">
                    <label for="analyticsPhotoIdTextbox">Photo ID: </label>
                    <input type="textbox" id="analyticsPhotoIdTextbox" class="formInputStyle" name="targetPhotoId" $photoSelectTextValue>
                    <input type="submit" id="userIdSubmit" class="clickableButton" value="Select Photo">
                </form>
                $photoSelectClearFormHTML
                $photoDetailsHTML
                $photoMatchDetailsHTML
                $photoMatchesMapWrapperHTML
                $allProjectMatchesMapWrapperHTML
            </div>
        </div>
    </div>

EOL;

require('includes/template.php');
