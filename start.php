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
          <button class="clickableButton" type="button" id="randomButton" title="Using this button will cause
              iCoast to pick a random image from your chosen project for you to tag.">
              <img src="images/system/dice.png" height="128" width="128" alt="Image of a dice indicating that
              this button causes iCoast to randomly select an image to display">
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
    </div>
    $mapHTML
EOL;

require('includes/template.php');