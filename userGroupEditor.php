<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/userGroupEditorCode.php');
$pageBody = <<<EOL
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <div>
                <h1>User Group Management</h1>
                <h2>Add Users To User Groups</h2>
                $successMessage
                <form method="post">
                    <div class="formFieldRow">
                        <label for="userGroupSelect">Select a User Group: </label>
                        <select id="userGroupSelect" name="group" class="formInputStyle">
                            $groupsHTML
                        </select>
                    </div>
                    <div class="formFieldRow">
                        <label for="userSelect">Select a User: </label>
                        <select id="userSelect" name="user" class="formInputStyle">
                             $usersHTML
                        </select>
                    </div>
                    <input class="clickableButton" type="submit" name="submitted" value="Add User To Group">
                </form>
            </div>

        </div>
    </div>
EOL;

require('includes/template.php');
