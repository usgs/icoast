<?php

ob_start();
require("includes/pageCode/loginCode.php");

$pageBody = <<<EOL
    <div id = "contentWrapper">
        <h1>Welcome to iCoast!</h1>
        $variableContent
    </div>
EOL;

require("includes/template.php");