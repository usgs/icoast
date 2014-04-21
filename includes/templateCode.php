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


$adminLevel = FALSE;
$alertBoxContent = "";
$alertBoxDynamicControls = "";


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

            $userAdministeredProjects = array();
            $errorEventCount = 0;
            $systemFeedbackCount = 0;
            $projectFeedbackCount = 0;
            $projectString = '';

            if ($adminLevel == 2) {

                $errorEventQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 1 AND event_ack = 0";
                $errorEventResult = $DBH->query($errorEventQuery);
                if ($errorEventResult) {
                    $errorEventCount = $errorEventResult->fetchColumn();
                }

                $systemFeedbackQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 2 AND event_ack = 0";
                $systemFeedbackResult = $DBH->query($systemFeedbackQuery);
                if ($systemFeedbackResult) {
                    $systemFeedbackCount = $systemFeedbackResult->fetchColumn();
                }
            }

            $userAdministeredProjectsQuery = "SELECT project_id FROM project_administrators WHERE user_id = :userId";
            $userAdministeredProjectsParams['userId'] = $userId;
            $userAdministeredProjectsResult = run_prepared_query($DBH, $userAdministeredProjectsQuery, $userAdministeredProjectsParams);
            while ($row = $userAdministeredProjectsResult->fetch(PDO::FETCH_ASSOC)) {
                $userAdministeredProjects[] = $row['project_id'];
            }

            if (count($userAdministeredProjects) > 0) {
                $projectString = where_in_string_builder($userAdministeredProjects);
                $projectFeedbackQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 3 AND "
                        . "event_code IN ($projectString)";
                $projectFeedbackResult = $DBH->query($projectFeedbackQuery);
                if ($projectFeedbackResult) {
                    $projectFeedbackCount = $projectFeedbackResult->fetchColumn();
                }

            }

            $totalFeedbackCount = $systemFeedbackCount + $projectFeedbackCount;

            if ($errorEventCount > 0 || $totalFeedbackCount > 0) {
                $alertBoxContent .= "<h1 id=\"alertBoxTitle\">iCoast: You have unread events in the log</h1>";
                if ($errorEventCount > 0) {
                    $alertBoxContent .= "<p>You have <span class=\"userData captionTitle\">$errorEventCount "
                            . "unread system error events</span> that require investigation.</p>";
                }
                if ($errorEventCount > 0 && $totalFeedbackCount > 0) {
                    $alertBoxContent .= "<p>AND</p>";
                }
                if ($totalFeedbackCount > 0) {
                    $alertBoxContent .= "<p>You have <span class=\"userData captionTitle\">$totalFeedbackCount "
                            . "unread feedback submissions</span> to be reviewed.</p>";
                }
                $alertBoxDynamicControls .= '<input type="button" id="goToEventViewerButton" class="clickableButton" '
                        . 'value="Go To Event Log">';
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
        $('#closeAlertBox').click(function() {
            $('#alertBoxWrapper').hide();
        });

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
