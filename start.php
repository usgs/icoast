<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/startCode.php');

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Choose a Project and Photo to Tag</h1>
        $variableContent
    </div>
EOL;

require_once('includes/template.php');