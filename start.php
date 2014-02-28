<?php
ob_start();
require('includes/pageCode/startCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Choose a Photo to Tag</h1>
        <p>Click the Random button to tag a random photo.<br>
          Click the Map button to select a photo in a particular location.</p>
        <div class="postNavButtonWrapper">
        <p>Random</p>
          <button class="clickableButton" type="button" id="randomButton"><img src="images/system/dice.png"></button>
        </div>
        <div class="postNavButtonWrapper">
          <p>Map</p>
          <button class="clickableButton" type="button" id="mapButton"><img src="images/system/map.png"></button>
        </div>
    </div>
    $mapHTML
EOL;

require('includes/template.php');