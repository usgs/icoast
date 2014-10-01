<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/classificationStatsCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>. Your admin level is
                        <span class="userData">$adminLevelText</span></p>
                </div>
                <div>
                    <h1>Classification Statistics</h1>
                    <h2 id="projectSelectHeader">Select a Project (Optional)</h2>
                    <p>For project specific statistics select a project using the following list.</p>
                    <form method="get" autocomplete="off" action="#projectSelectHeader">
                        <div class="formFieldRow">
                            <label for="analyticsProjectSelection">Project: </label>
                            <select id="analyticsProjectSelection" class="formInputStyle" name="targetProjectId">
                                $projectSelectHTML
                            </select>
                            <input type="submit" class="clickableButton" value="Select Project">
                        </div>
                    </form>
                    <h2>$generalStatsTitle</h2>
                    <table class="adminStatisticsTable">
                        $generalStatsTableContent
                    </table>
                        <input type="button" id="allClassificationDownload" class="clickableButton disabledClickableButton" title="This button will give you the option to save all classifications data for the selected project to a CSV file on your hard drive for further analysis with other tools. If this button is unavailable then you wither have not selected a specific project or there are no complete classifications to download for the chosen project."value="Download All Project Classifications In CSV Format" disabled>
                    $mapHTML
                    $userStatsTableContent
                    $tagStatsTableContent
                    $tagBreakdown
                </div>

            </div>
        </div>

EOL;

require('includes/template.php');
