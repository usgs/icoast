<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once("includes/pageCode/indexCode.php");

$pageBody = <<<EOL
    <div id="contentWrapper">
        $variablePageContent
    </div>
EOL;

require_once("includes/template.php");
