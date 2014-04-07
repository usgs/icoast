<?php

$pageUrl = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$lastModifiedTimestamp = filemtime(__FILE__);
$fileModifiedDateTime = new DateTime();
$fileModifiedDateTime->setTimestamp($lastModifiedTimestamp);
$fileModifiedDateTime->setTimezone(new DateTimeZone('America/New_York'));
$fileModifiedDateTime = $fileModifiedDateTime->format('F jS, Y H:i T');

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


$adminLevel = FALSE;
$alertBox = "";


if ($userData && $userData['account_type'] >= 2 && $userData['account_type'] <= 4) {
    $userId = $userData['user_id'];
    $adminLevel = $userData['account_type'];
}

$mainNav = '<ul>';

if ($adminLevel) {
    $mainNav .= '<li id="adminPanelLink"><a href="adminPanel.php">Admin Panel</a></li>';
}

switch ($pageName) {
    case "home":
        $pageTitle = "iCoast - Home";
        if ($adminLevel) {

            $systemEventCount = 0;
            $systemAdminEventQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 1 OR event_type = 2 AND "
                    . "event_ack = 0";
            $systemAdminEventResult = $DBH->query($systemAdminEventQuery);
            if ($systemAdminEventResult) {
                $systemEventCount = $systemAdminEventResult->fetchColumn;
            }

            $projectEventCount = 0;
            $administeredProjectsQuery = "SELECT project_id FROM project_administrators WHERE user_id = :userId";
            $administeredProjectsParams['userId'] = $userId;
            $administeredProjectsResult = run_prepared_query($DBH, $projectAdminEventQuery, $projectAdminEventParams);
            $administeredProjects = $administeredProjectsResult->fetchAll(PDO::FETCH_ASSOC);
            if (count($administeredProjects) > 0) {
                $projectString = where_in_string_builder($administeredProjects);
                $projectAdminEventQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 3 AND "
                    . "event_code IN ($projectString)";
                $projectAdminEventResult = $DBH->query($projectAdminEventQuery);
                if ($projectAdminEventResult) {
                    $projectEventCount = $projectAdminEventResult->fetchColumn;
                }
            }
        }

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
        $mainNav .= <<<EOL
              <li><a href="index.php">Home</a></li>
              <li><a href="help.php">Help</a></li>
              <li><a href="about.php">About</a></li>
              <li><a href="index.php?login">Login</a></li>
            </ul>
EOL;
        break;

    case "welcome":
        $pageTitle = "iCoast: Welcome to iCoast";
        $mainNav .= <<<EOL
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
        $mainNav .= <<<EOL
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
        $mainNav .= <<<EOL
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
        $mainNav .= <<<EOL
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
        $mainNav .= <<<EOL
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
        $mainNav .= <<<EOL
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
