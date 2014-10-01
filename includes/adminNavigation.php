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

if ($pageName =='eventViewer') {
    $adminNavHTML .= '<li class="activePageLink">Event Viewer</li>';
    $pageTitle = "USGS iCoast - Event Log Viewer";
} else {
    $adminNavHTML .= '<li><a href="eventViewer.php">Event Viewer</a></li>';
}

$adminNavHTML .= '<li class="adminNavHeader">Statistics</li>';

if ($pageName =='classificationStats') {
    $adminNavHTML .= '<li class="activePageLink">Classification</li>';
    $pageTitle = "USGS iCoast - Classification Statistics";
} else {
    $adminNavHTML .= '<li><a href="classificationStats.php">Classification</a></li>';
}

if ($pageName =='userStats') {

    $adminNavHTML .= '<li class="activePageLink">User</li>';
    $pageTitle = "USGS iCoast - User Statistics";
} else {
    $adminNavHTML .= '<li><a href="userStats.php">User</a></li>';
}

if ($pageName =='photoStats') {
    $adminNavHTML .= '<li class="activePageLink">Photo</li>';
    $pageTitle = "USGS iCoast - Photo Statistics";
} else {
    $adminNavHTML .= '<li><a href="photoStats.php">Photo</a></li>';
}

$adminNavHTML .= '<li class="adminNavHeader">System Editors</li>';

if ($pageName == 'systemEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="systemEditor.php">System</a></li>';
    $pageTitle = "USGS iCoast - System Editor";
} else {
    $adminNavHTML .= '<li><a href="systemEditor.php">System</a></li>';
}

if ($pageName == 'projectEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="projectEditor.php">Project</a></li>';
    $pageTitle = "USGS iCoast - Project Editor";
} else {
    $adminNavHTML .= '<li><a href="projectEditor.php">Project</a></li>';
}

if ($pageName == 'userEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="userEditor.php">User</a></li>';
    $pageTitle = "USGS iCoast - User Editor";
} else {
    $adminNavHTML .= '<li><a href="userEditor.php">User</a></li>';
}

if ($pageName == 'userGroupEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="userGroupEditor.php">User Groups</a></li>';
    $pageTitle = "USGS iCoast - User Group Editor";
} else {
    $adminNavHTML .= '<li><a href="userGroupEditor.php">User Groups</a></li>';
}

if ($pageName == 'imageGroupEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="imageGroupEditor.php">Image Groups</a></li>';
    $pageTitle = "USGS iCoast - Image Group Editor";
} else {
    $adminNavHTML .= '<li><a href="imageGroupEditor.php">Image Groups</a></li>';
}


$adminNavHTML .= <<<EOL
    </ul>
</div>
EOL;

