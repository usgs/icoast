<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/completeCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Annotation Complete</h1>
        <h2>Congratulations!</h2>
        <p style="font-weight: normal"> This is the <span class="userData">$ordinalNumberOfAnnotations</span>
            photo you have tagged in iCoast for the <span class="userData">$projectName Project</span>.<br>
            Statistics of the last photo you tagged are below.</p>
        <div id="annotationDetails">
            <img src="$postDisplayImageURL" width="384" height="250" title="This is photo you just tagged."
                 alt="An oblique coastal image of the $postImageLocation area. This is the photo you tagged." />
            <table class="statisticsTable">
                <tr><td class="rowTitle">Leaderboard Position:</td><td class="userData">$ordinalPositionInICoast</td></tr>
                $annotationsToNextHTML
                <tr><td class="rowTitle"># of Tags Selected:</td><td class="userData">$tagCount</td></tr>
                <tr><td class="rowTitle">Time Spent Tagging Photo:</td><td class="userData">$lastAnnotationTime</td></tr>
                <tr><td class="rowTitle">Location of Photo:</td><td class="userData">$postImageLocation</td></tr>

            </table>
        </div>
        <div id="chooseNextImageWrapper">
            $variableContent
        </div>
    </div>

EOL;


require_once('includes/template.php');
