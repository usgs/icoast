<?php

if (!isset($pageName)) {
    header('Location: login.php');
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

define('STATIC_HEADER', 'css/staticHeader.css');
define('DYNAMIC_HEADER', 'css/dynamicHeader.css');

switch ($pageName) {
    case "welcome":
        $pageTitle = "iCoast: Welcome to iCoast";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
      <ul id="mainHeaderNavigation">
        <li id="activePageLink">Home</li>
        <li><a href="profile.php">Profile</a></li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>

        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
        break;
    case "classify":
        $pageTitle = "iCoast: Classification";
        $cssLinkArray[] = DYNAMIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#usgsColorBand').click(function() {

                        $('#usgsColorBand').animate({
                            top: "0px"
                        }, 500, "swing");

                        $('#headerImageWrapper').animate({
                            left: "350px"
                        }, 500, "swing");

                        $('#usgsIdentifier').animate({
                            width: "350px"
                        }, 500, "swing");

                        $('#usgsIdentifier a').show(0, function() {
                            $('#usgsIdentifier a').animate({
                                opacity: 1
                            }, 500, "swing");
                        });

                        $('#appTitle').animate({
                            left: "190px",
                            top: "7px",
                            margin: "0 0 0 0",
                            fontSize: "48px",
                            lineHeight: "48px"
                        }, 500, "swing");

                        $('#appSubtitle').animate({
                            left: "190px",
                            top: "52px"
                        }, 500, "swing");

                        $('#mainHeaderNavigation li').animate({
                            opacity: 1
                        }, 500, "swing");




                    }); // End header click (expand) function.


                    $('#usgsColorBand').mouseleave(function() {

                        $('#usgsColorBand').animate({
                            top: "-47px"
                        }, 500, "swing");

                        $('#headerImageWrapper').animate({
                            left: "252px"
                        }, 500, "swing");

                        $('#usgsIdentifier').animate({
                            width: "252px"
                        }, 500, "swing");

                        $('#usgsIdentifier a').animate({
                            opacity: 0
                        }, 500, "swing", function() {
                            $('#usgsIdentifier a').hide(0);
                        });

                        $('#appTitle').animate({
                            left: "0px",
                            top: "47px",
                            margin: "0 0 0 15",
                            fontSize: "25px",
                            lineHeight: "25px"
                        }, 500, "swing");

                        $('#appSubtitle').animate({
                            left: "97px",
                            top: "56px"
                        }, 500, "swing");

                        $('#mainHeaderNavigation li').animate({
                            opacity: 0
                        }, 500, "swing");

                    }); // End header mouseleave (collapse) function.
EOL;
        break;
    case "start":
        $pageTitle = "iCoast: Choose Your Photo";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "complete":
        $pageTitle = "iCoast: Annotation Summary";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "help":
        $pageTitle = "iCoast: Help";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li id="activePageLink">Help</li>
              <li><a href="about.php">About</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "about":
        $pageTitle = 'iCoast: About "iCoast - Did the Coast Change"';
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li id="activePageLink">About</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "profile":
        $pageTitle = "iCoast: User Profile";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li id="activePageLink">Profile</li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "logout":
        $pageTitle = "iCoast - User Logout";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="login.php">Login</a></li>
            </ul>
EOL;
        break;
    case "login":
        $pageTitle = "iCoast - User Login";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
               <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li id="activePageLink">Login</li>
            </ul>
EOL;
        break;
    case "registration":
        $pageTitle = "iCoast - User Registration";
        $cssLinkArray[] = STATIC_HEADER;
        $mainNav = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li><a href="profile.php">Profile</a></li>
              <li class="missingPageLink">Help</li>
              <li><a href="about.php">About</a></li>
              <li id="activePageLink">Login</li>
            </ul>
EOL;
        break;
    default:
        header('Location: login.php');
        break;
}





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



