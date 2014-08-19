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
                    <h2>Select a Project (Optional)</h2>
                    <p>For project specific statistics select a project using the following list.</p>
                    <form method="get" autocomplete="off">
                        <div class="formFieldRow">
                            <label for="analyticsProjectSelection">Project: </label>
                            <select id="analyticsProjectSelection" class="clickableButton" name="targetProjectId">
                                $projectSelectHTML
                            </select>
                            <input type="submit" class="clickableButton" value="Select Project">
                        </div>
                    </form>
                    <h2>$statsTitle</h2>
                    <table class="statisticsTable">
                        $statsTableContent
                    </table>
                    $tagBreakdown
                </div>

            </div>
        </div>

EOL;

require('includes/template.php');
