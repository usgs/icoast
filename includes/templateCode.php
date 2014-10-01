<?php
if (!isset($pageName)) {
    $pageName = detect_pageName();
}
$pageUrl = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$fileModifiedDateTime = file_modified_date_time($pageModifiedTime, $pageCodeModifiedTime);

$mainNav = '<ul>';

if ($userData && ($userData['account_type'] >= 2 && $userData['account_type'] <= 4)) {
    if (isset($adminNavigationActive)) {
        $mainNav .= '<li id="adminHomeLink" class="activePageLink">Administration</li>';
    } else {
        $mainNav .= '<li id="adminHomeLink"><a href="adminHome.php">Administration</a></li>';
    }
}

if ($pageName == 'index') {
    $pageTitle = "USGS iCoast - Home";
    $mainNav .= '<li class="activePageLink">Home</li>';
} else {
    $mainNav .= '<li><a href="index.php">Home</a></li>';
}


if ($pageName == 'registration') {
    $pageTitle = "USGS iCoast - User Registration";
}


if ($pageName == 'welcome') {
    $pageTitle = "USGS iCoast: Welcome to USGS iCoast";
    $mainNav .= '<li class="activePageLink">Welcome</li>';
}


if ($pageName == 'start' || $pageName == 'classification' || $pageName == 'complete') {
    switch ($pageName) {
        case "start":
            $pageTitle = "USGS iCoast: Choose Your Photo";
            break;
        case "classification":
            $pageTitle = "USGS iCoast: Classification";
            break;
        case "complete":
            $pageTitle = "USGS iCoast: Annotation Summary";
            break;
    }
    $mainNav .= '<li class="activePageLink"><a href="start.php">Classify</a></li>';
} else if ($userData) {
    $mainNav .= '<li><a href="start.php">Classify</a></li>';
}


if ($pageName == 'profile') {
    $pageTitle = "USGS iCoast: User Profile";
    $mainNav .= '<li class="activePageLink">Profile</li>';
} else if ($userData) {
    $mainNav .= '<li><a href="profile.php">Profile</a></li>';
}


if ($pageName == 'help') {
    $pageTitle = "USGS iCoast: Help";
    $mainNav .= '<li class="activePageLink">Help</li>';
} else {
    $mainNav .= '<li><a href="help.php">Help</a></li>';
}


if ($pageName == 'about') {
    $pageTitle = 'USGS iCoast: About "USGS iCoast - Did the Coast Change"';
    $mainNav .= '<li class="activePageLink">About</li>';
} else {
    $mainNav .= '<li><a href="about.php">About</a></li>';
}


if ($pageName == 'logout') {
    $pageTitle = "USGS iCoast - User Logout";
    $mainNav .= '<li><a href="index.php?login">Login</a></li>';
} else if ($userData) {
    $mainNav .= '<li class="accountControlLink"><a href="logout.php">Logout</a></li>';
} else {
    $mainNav .= '<li><a href="index.php?login">Login</a></li>';
}

$mainNav .= '</ul>';

if (!isset($pageTitle)) {
    header('Location: index.php');
}




if (!isset($javaScript)) {
    $javaScript = '';
}
$javaScript .= <<<EOL
    function moveFooter() {
        $('#usgsfooter').css({
            position: 'relative',
            top: 0
        });
        var footerTopOffset = $('#usgsfooter').offset().top;
        var footerHeight = $('#usgsfooter').outerHeight();
        var windowHeight = $(window).height();

        if (footerTopOffset < (windowHeight - footerHeight)) {
            //console.log('<');
            $('#usgsfooter').css({
                width: '100%',
                position: 'absolute',
                top: windowHeight - footerHeight
            });
        }
    }

EOL;

if (!isset($jQueryDocumentDotReadyCode)) {
    $jQueryDocumentDotReadyCode = '';
}
$jQueryDocumentDotReadyCode .= <<<EOL
        $('#closeAlertBox').click(function() {
            $('#alertBoxWrapper').hide();
        });

        $('img, .clickableButton, .formInputStyle').tipTip();

        $(window).resize(function () {
            moveFooter();
        });

        moveFooter();

EOL;


if (!isset($cssLinkArray)) {
    $cssLinkArray = array();
}
$cssLinks = '';
if (isset($adminNavigationActive)) {
    $cssLinkArray[] = "css/icoastAdmin.css";
}
if (count($cssLinkArray) > 0) {
    foreach ($cssLinkArray as $link) {
        $cssLinks .= "<link rel='stylesheet' href='$link'>\n\r";
    }
}


if (!isset($javaScriptLinkArray)) {
    $javaScriptLinkArray = array();
}
$javaScriptLinks = '';
if (count($javaScriptLinkArray) > 0) {
    foreach ($javaScriptLinkArray as $link) {
        $javaScriptLinks .= "<script src='$link'></script>\n\r";
    }
}

if (!empty($jQueryDocumentDotReadyCode)) {
    $tempCode = $jQueryDocumentDotReadyCode;
    $jQueryDocumentDotReadyCode = <<<EOL
        $(document).ready(function() {
        $tempCode
        });

EOL;
}

if (!empty($feedbackjQueryDocumentDotReadyCode)) {
    $tempCode = $feedbackjQueryDocumentDotReadyCode;
    $feedbackjQueryDocumentDotReadyCode = <<<EOL
        $(document).ready(function() {
        $tempCode
        });

EOL;
}

if (!isset($embeddedCSS)) {
    $embeddedCSS = '';
}

if (!isset($pageBody)) {
    $pageBody = '';
}
