<?php

$pageUrl = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$fileModifiedDateTime =  date ('F jS, Y H:i', filemtime(__FILE__)) . " EDT";

if (!isset($pageName)) {
    header('Location: index.php');
}
if (!isset($cssLinkArray)) {
    $cssLinkArray = array();
}
if (!isset($embeddedCSS)) {
    $embeddedCSS = '';
}
if (!isset($javaScriptLinkArray)) {
    $javaScriptLinkArray = array();
}
if (!isset($javaScript)) {
    $javaScript = '';
}
if (!isset($jQueryDocumentDotReadyCode)) {
    $jQueryDocumentDotReadyCode = '';
}
if (!isset($pageBody)) {
    $pageBody = '';
}
$cssLinks = '';
$javaScriptLinks = '';

//define('STATIC_HEADER', 'css/staticHeader.css');
//define('DYNAMIC_HEADER', 'css/dynamicHeader.css');

switch ($pageName) {
    case "home":
        $pageTitle = "iCoast - Home";
        $mainNav = '<ul>';
        $mainNav .= '<li class="activePageLink">Home</li>';
        if ($userData) {
            $mainNav .= '<li><a href="start.php">Classify</a></li>';
            $mainNav .= '<li><a href="profile.php">Profile</a></li>';
        }
        $mainNav .= '<li><a href="help.php">Help</a></li>';
        $mainNav .= '<li><a href="about.php">About</a></li>';
        if ($userData) {
            $mainNav .= '<li class="accountControlLink"><a href="logout.php">Logout</a></li>';
        } else {
            $mainNav .= '<li><a href="index.php?login">Login</a></li>';
        }
        $mainNav .= '</ul>';
        break;

    case "registration":
        $pageTitle = "iCoast - User Registration";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="index.php?login">Login</a></li>
            </ul>
EOL;
        break;

    case "welcome":
        $pageTitle = "iCoast: Welcome to iCoast";
        $mainNav = <<<EOL
      <ul>
        <li><a href="index.php">Home</a></li>
        <li class="activePageLink">Welcome</li>
        <li><a href="start.php">Classify</a></li>
        <li><a href="profile.php">Profile</a></li>
        <li><a href="help.php">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="accountControlLink"><a href="logout.php">Logout</a></li>
      </ul>
EOL;
        break;

    case "classify":
        $pageTitle = "iCoast: Classification";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li class="activePageLink"><a href="start.php">Classify</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="accountControlLink"><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;

    case "start":
        $pageTitle = "iCoast: Choose Your Photo";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li class="activePageLink">Classify</li>
              <li><a href="profile.php">Profile</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="accountControlLink"><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;

    case "complete":
        $pageTitle = "iCoast: Annotation Summary";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li class="activePageLink"><a href="start.php">Classify</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="accountControlLink"><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;

    case "profile":
        $pageTitle = "iCoast: User Profile";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li><a href="start.php">Classify</a></li>
              <li class="activePageLink">Profile</li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="accountControlLink"><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;

    case "help":
        $pageTitle = "iCoast: Help";
        $mainNav = '<ul>';
        $mainNav .= '<li><a href="index.php">Home</a></li>';
        if ($userData) {
            $mainNav .= '<li><a href="profile.php">Profile</a></li>';
            $mainNav .= '<li><a href="start.php">Classify</a></li>';
        }
        $mainNav .= '<li class="activePageLink">Help</li>';
        $mainNav .= '<li><a href="about.php">About</a></li>';
        if ($userData) {
             $mainNav .= '<li class="accountControlLink"><a href="logout.php">Logout</a></li>';
        } else {
            $mainNav .= '<li><a href="index.php?login">Login</a></li>';
        }
        $mainNav .= '</ul>';
        break;

    case "about":
        $pageTitle = 'iCoast: About "iCoast - Did the Coast Change"';
        $mainNav = '<ul>';
        $mainNav .= '<li><a href="index.php">Home</a></li>';
        if ($userData) {
            $mainNav .= '<li><a href="profile.php">Profile</a></li>';
            $mainNav .= '<li><a href="start.php">Classify</a></li>';
        }
        $mainNav .= '<li><a href="help.php">Help</a></li>';
        $mainNav .= '<li class="activePageLink">About</li>';
        if ($userData) {
             $mainNav .= '<li class="accountControlLink"><a href="logout.php">Logout</a></li>';
        } else {
            $mainNav .= '<li><a href="index.php?login">Login</a></li>';
        }
        $mainNav .= '</ul>';
        break;

    case "logout":
        $pageTitle = "iCoast - User Logout";
        $mainNav = <<<EOL
            <ul>
              <li><a href="index.php">Home</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="index.php?login">Login</a></li>
            </ul>
EOL;
        break;

    default:
        header('Location: index.php');
        break;
}

$javaScript .= <<<EOL
    function moveFooter() {
        //console.log('IN MOVE FOOTER');
        $('#usgsfooter').css({
            position: 'relative',
            top: 0
        });
        //console.log($('#usgsfooter').css('position'));
        var footerTopOffset = $('#usgsfooter').offset().top;
        //console.log('footerTopOffset: ' + footerTopOffset);
        var footerHeight = $('#usgsfooter').outerHeight();
        //console.log('footerHeight: ' + footerHeight);
        var windowHeight = $(window).height();
        //console.log('windowHeight: ' + windowHeight);

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

$jQueryDocumentDotReadyCode .= <<<EOL
        $('img, .clickableButton').tipTip();

        $(window).resize(function () {
            moveFooter();
        });

        moveFooter();
EOL;


if (count($cssLinkArray) > 0) {
    foreach ($cssLinkArray as $link) {
        $cssLinks .= "<link rel='stylesheet' href='$link'>\n\r";
    }
}

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
