<?php
$pageName = detect_pageName();
$adminNavigationActive = TRUE;

$adminNavHTML = <<<EOL
<div id="adminNavigationBar">
    <ul>
EOL;

if ($pageName == 'adminHome') {
    $pageTitle = "USGS iCoast - Administration Home";
}

if ($pageName == 'projectEditor') {
    $adminNavHTML .= ' <li class="activePageLink">Project Editor</li>';
    $pageTitle = "USGS iCoast - Project Editor";
} else {
    $adminNavHTML .= '<li><a href="projectEditor.php">Project Editor</a></li>';
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

