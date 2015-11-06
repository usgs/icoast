<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/taskPreviewCode.php');

$pageBody = <<<EOL
    <div id="classificationWrapper">
        <div id="refreshWrapper">
            <h1>"{$projectMetadata['name']}" Project Question Set Preview</h1>
            <p>This is a simulation of what your currently defined set of tasks, groups and tags will look like and
                how they will behave in iCoast.</p>
            <p>You may click back and forth between tasks and click the tags to see if they behave as expected.
                Tooltips will load as they would for a user.</p>
            <button type="button" id="refreshButton" class="clickableButton">Refresh Preview</button>
        </div>
        <div id="annotationWrapper">
            <div id="taskWrapper">
                $taskHtmlString
                <div id="progressTrackerCenteringWrapper">
                    <div id="progressTrackerItemWrapper" title="The TASK TRACKER lets you know which TASK you are
                     currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS
                     Task buttons at the bottom corners of the page.">
                        $progressTrackerItems
                    </div>
                </div>
            </div>
        </div>
    </div>
EOL;

require_once('includes/template.php');