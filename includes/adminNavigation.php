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
    $adminNavHTML .= '<li class="activePageLink">Feedback Viewer</li>';
    $pageTitle = "USGS iCoast - Feedback and Error Log Viewer";
} else {
    $adminNavHTML .= '<li><a href="eventViewer.php">Feedback Viewer</a></li>';
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

if ($pageName == 'collectionCreator' || $pageName == 'collectionImportController' ||
    $pageName == 'collectionImportProgress' || $pageName == 'refineCollectionImport' ||
    $pageName == 'sequenceCollection' || $pageName == 'reviewCollection' ||
    $pageName == 'modifyCollection' || $pageName == 'finalizeCollection'
) {
    $adminNavHTML .= ' <li class="activePageLink"><a href="collectionCreator.php">Collection Creator</a></li>';
    $pageTitle = "USGS iCoast - Collection Creator";
} else {
    $adminNavHTML .= '<li><a href="collectionCreator.php">Collection Creator</a></li>';
}

if ($pageName == 'projectCreator' || $pageName == 'projectCollectionImportController' ||
    $pageName == 'questionBuilder' || $pageName == 'projectCollectionImportProgress' ||
    $pageName == 'taskPreview' || $pageName == 'refineProjectImport' ||
        $pageName == 'sequenceImport' || $pageName == 'matchCollections' ||
        $pageName == 'finalizeProject' || $pageName == 'reviewProject' ||
    $pageName == 'modifyProjectCollection' || $pageName == 'modifyProject'
) {
    $adminNavHTML .= ' <li class="activePageLink"><a href="projectCreator.php">Project Creator</a></li>';
    $pageTitle = "USGS iCoast - Project Creator";
} else {
    $adminNavHTML .= '<li><a href="projectCreator.php">Project Creator</a></li>';
}

if ($pageName == 'projectEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="projectEditor.php">Project Editor</a></li>';
    $pageTitle = "USGS iCoast - Project Editor";
} else {
    $adminNavHTML .= '<li><a href="projectEditor.php">Project Editor</a></li>';
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

if ($pageName == 'photoEditor') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="photoEditor.php">Photos</a></li>';
    $pageTitle = "USGS iCoast - Photo Editor";
} else {
    $adminNavHTML .= '<li><a href="photoEditor.php">Photos</a></li>';
}

$adminNavHTML .= '<li class="adminNavHeader">Admin Tasks</li>';

if ($pageName == 'emailTool') {
    $adminNavHTML .= ' <li class="activePageLink"><a href="emailTool.php">Bulk Email</a></li>';
    $pageTitle = "USGS iCoast - Bulk Email Tool";
} else {
    $adminNavHTML .= '<li><a href="emailTool.php">Bulk Email</a></li>';
}

$adminNavHTML .= <<<EOL
    </ul>
</div>
EOL;

