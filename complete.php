<?php
ob_start();
require('includes/pageCode/completeCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Annotation Complete</h1>
        <h2>Congratulations!</h2>
        <p style="font-weight: normal"> This is the <span class="userData">$ordinalNumberOfAnnotations</span>
            photo you have tagged in iCoast for the <span class="userData">$projectName Project</span>.<br>
            Statistics of the last photo you tagged are below.</p>
        <div id="annotationDetails">
            <img src="$postDisplayImageURL" width="400" height="260" title="This is photo you just tagged."
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
            <h2>Select Another Photo</h2>
            <p style="font-weight: normal">Choose a Random photo, select a photo in a specific location on the Map,<br>or Move Along The Coast from the last photo you tagged.</p>
            <div class="postNavButtonWrapper">
                <p>Random</p>
                <button class="clickableButton" type="button" id="randomButton" title="Using this button will
                        cause iCoast to pick a random image from your chosen project for you to tag.">
                    <img src="images/system/dice.png" alt="Image of a dice indicating that this button
                         causes iCoast to randomly select an image to display" height="128" width="128">
                </button>
            </div>
            <div class="postNavButtonWrapper">
                <p>Map</p>
                <button class="clickableButton" type="button" id="mapButton" title="Using this button will cause
                        iCoast to display a map of a section of the US coast from which you can choose an image to tag.">
                    <img src="images/system/map.png" height="128" width="128" alt="Image of a map and push pin
                         indicating that this button causes iCoast to display a map from which you can choose an image
                         from your selected project to tag.">
                </button>
            </div>
            <div class="postNavButtonWrapper">
                <p>Move Along The Coast</p>
                $leftCoastalNavigationButtonHTML
                $rightCoastalNavigationButtonHTML
                <img id="coastalNavigationImage" src="$postDisplayImageURL" id="annotatedImage"
                    title="This is photo you just tagged." alt="An oblique coastal image of the $postImageLocation
                    area. This is the photo you tagged." height="50" width="77" />
            </div>
        </div>
    </div>
$mapHTML
EOL;

require('includes/template.php');
