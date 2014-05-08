<?php

require_once('includes/globalFunctions.php');
$pageName = detect_pageName();
$adminNavigationActive = TRUE;

$adminNavHTML = <<<EOL
<div id="adminNavigationBar">
    <ul>
EOL;

if ($pageName == 'adminHome') {
    $pageTitle = "USGS iCoast - Administration Home";
}

if ($pageName == 'tagEditor') {
    $adminNavHTML .= ' <li class="activePageLink">Tag Editor</li>';
    $pageTitle = "USGS iCoast - Tag Editor";
} else {
    $adminNavHTML .= '<li><a href="tagEditor.php">Tag Editor</a></li>';
}

if ($pageName =='eventViewer') {
    $adminNavHTML .= '<li class="activePageLink">Event Viewer</li>';
    $pageTitle = "USGS iCoast - ";
} else {
    $adminNavHTML .= '<li><a href="eventViewer.php">Event Viewer</a></li>';
}

$adminNavHTML .= <<<EOL
    </ul>
</div>
EOL;

