<?php
ob_start();
require('includes/pageCode/completeCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Annotation Complete</h1>
        <h2>Congratulations!</h2>
        <p> This is the <span class="userData">$ordinalNumberOfAnnotations</span> photo you have tagged in iCoast for the <span class="userData">$projectName Project</span>.<br>
            Statistics of the last photo you tagged are below.</p>
        <div id="annotationDetails">
            <img src="$postDisplayImageURL" width="200px" height="130px" />
            <table>
            <tr><td class="rowTitle">Scoreboard Position:</td><td class="userData">$ordinalPositionInICoast</td></tr>
            <tr><td class="rowTitle">Location of Photo:</td><td class="userData">$postImageLocation</td></tr>
            <tr><td class="rowTitle">Time Spent Tagging Photo:</td><td class="userData">$lastAnnotationTime</td></tr>
            <tr><td class="rowTitle"># Of Tags Selected:</td><td class="userData">$tagCount</td></tr>
            $annotationsToNextHTML
            </table>
        </div>
        <div id="chooseNextImageWrapper">
            <h2>Select Another Photo</h2>
            <p>Choose a Random photo, select a photo in a specific location on the Map, or Move Along The Coast from the last photo you tagged.     </p>
            <div class="postNavButtonWrapper">
            <p>Random</p>
                <button class="clickableButton" type="button" id="randomButton"><img src="images/system/dice.png" alt="Image of dice. Used to select a random photo" height="128" width="128"></button>
        </div>
        <div class="postNavButtonWrapper">
            <p>Map</p>
            <button class="clickableButton" type="button" id="mapButton"><img src="images/system/map.png" alt="Image of a map. Used to select a photo form a map" height="128" width="128"></button>
        </div>
        <div class="postNavButtonWrapper">
            <p>Move Along The Coast</p>
            $leftCoastalNavigationButtonHTML
            $rightCoastalNavigationButtonHTML
            <img id="coastalNavigationImage" src="$postDisplayImageURL" id="annotatedImage" />
        </div>
    </div>
$mapHTML
EOL;

require('includes/template.php');
