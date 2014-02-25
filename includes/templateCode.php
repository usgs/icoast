<?php
define('STATIC_HEADER', '<link rel="stylesheet" href="css/staticHeader.css">');
define('DYNAMIC_HEADER', '<link rel="stylesheet" href="css/dynamicHeader.css">');
$headerCSSLink = STATIC_HEADER;

$pageName = (!isset($pageName)) ? '' : $pageName;
$pageSpecificExternalCSSLinkArray = (!isset($pageSpecificExternalCSSLinkArray)) ? array() : $pageSpecificExternalCSSLinkArray;
$pageSpecificEmbeddedCSS = (!isset($pageSpecificEmbeddedCSS)) ? '' : $pageSpecificEmbeddedCSS;
$pageSpecificJavaScriptLinkArray = (!isset($pageSpecificJavaScriptLinkArray)) ? array() : $pageSpecificJavaScriptLinkArray;
$pageSpecificJavaScript = (!isset($pageSpecificJavaScript)) ? '' : $pageSpecificJavaScript;
$pageSpecificJQueryDocumentReadyCode = (!isset($pageSpecificJQueryDocumentReadyCode)) ? '' : $pageSpecificJQueryDocumentReadyCode;
$pageBodyHTML = (!isset($pageBodyHTML)) ? '' : $pageBodyHTML;

switch ($pageName) {
    case "welcome":
        $pageTitle = "iCoast: Welcome to iCoast";
        $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li id="activePageLink">Home</li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
        break;
    case "classify":
        $pageTitle = "iCoast: Classification";
        $headerCSSLink = DYNAMIC_HEADER;
        $pageSpecificJavaScript .= <<<EOL
            var pageName = '<?php print $pageName ?>';
            $(document).ready(function() {
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
            }); // End Document Ready
EOL;
    case "start":
        $pageTitle = "iCoast: Choose Your Photo";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "complete":
        $pageTitle = "iCoast: Annotation Summary";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "help":
        $pageTitle = "iCoast: Help";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li id="activePageLink">Help</li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "about":
        $pageTitle = 'iCoast: About "iCoast - Did the Coast Change"';
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li id="activePageLink">About</li>
              <li class="missingPageLink">Profile</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "profile":
        $pageTitle = "iCoast: User Profile";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li id="activePageLink">Profile</li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
EOL;
        break;
    case "logout":
        $pageTitle = "iCoast - User Logout";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
              <li id="activePageLink">Logout</a></li>
            </ul>
EOL;
        break;
    case "login":
        $pageTitle = "iCoast - User Login";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
            </ul>
EOL;
        break;
    case "registration":
        $pageTitle = "iCoast - User Registration";
        $mainNavHTML = <<<EOL
            <ul id="mainHeaderNavigation">
              <li><a href="welcome.php">Home</a></li>
              <li class="missingPageLink">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li class="missingPageLink">Profile</li>
            </ul>
EOL;
        break;
    default:
        header('Location: login.php');
        break;
}


