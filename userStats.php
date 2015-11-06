<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/userStatsCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1>User Statistics</h1>
                    <h2 id="userSelectHeader">Select a User (Optional)</h2>
                    <p>For user specific statistics select a user using the following list.<br>Select a starting letter to narrow your user account selections.</p>

                    <form method="get" autocomplete="off" id="userSelectionForm" action="#userSelectHeader">
                        <label for="analyticsUserAccountStartingLetter">User Account Starting Character: </label>
                        <select id="analyticsUserAccountStartingLetter" class="formInputStyle" name="userAccountStartingLetter">
                            <option value="0">All Users</option>
                            <option value="1">A - D</option>
                            <option value="2">E - J</option>
                            <option value="3">K - O</option>
                            <option value="4">P - T</option>
                            <option value="5">U - Z</option>
                            <option value="6">Numeric</option>
                        </select>
                        <label for="analyticsUserSelection">User: </label>
                        <select id="analyticsUserSelection" class="formInputStyle" name="targetUserId">
                        </select>
                        <input type="submit" id="userIdSubmit" class="clickableButton" value="Select User">
                    </form>
                    <form method="get" autocomplete="off" id="userClearForm" action="#userSelectHeader">
                        <input type="submit" id="userIdClear" class="clickableButton" value="Clear Selected User">
                    </form>
                    <h2>$statsTitle</h2>
                    $allUserStatsHTML
                    $selectedUserStatsHTML
                    $mapHTML
                    $classificationTimeGraphHTML
                    $over60MinuteClassificationHTML
                    $under30SecondClassificationHTML
                </div>
            </div>
        </div>

EOL;

require('includes/template.php');
