<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/profileCode.php');
$pageBody = <<<EOL

EOL;

require('includes/template.php');
