<?php
ob_start();
require('includes/pageCode/startCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Choose a Project and Photo to Tag</h1>
        $variableContent
    </div>
EOL;

require('includes/template.php');