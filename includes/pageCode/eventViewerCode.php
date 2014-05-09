<?php

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
require_once('includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];





$eventLogQuery = "SELECT e.*, u.masked_email "
        . "FROM event_log e "
        . "LEFT JOIN users u "
        . "ON e.user_id = u.user_id";

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// FILTERS

$project = null;
$userAdministeredProjects = find_administered_projects($DBH, $userId);
if (isset($_GET['project']) && settype($_GET['project'], 'integer')) {
    $project = $_GET['project'];


}





if ($adminLevel == 3 || $adminLevel == 2) {
    $projectString = where_in_string_builder($userAdministeredProjects);
    if (stripos($eventLogQuery, ' WHERE ') !== FALSE) {
        $eventLogQuery = " AND event_type = 3  AND event_code IN ($projectString)";
    } else {
        $eventLogQuery = " WHERE event_type = 3  AND event_code IN ($projectString)";
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// READ OR CLOSED EVENTS
$unreadOnly = FALSE;
if (isset($_GET['unreadOnly']) && $_GET['unreadOnly'] == TRUE) {
    $unreadOnly = TRUE;
}

$closedOnly = FALSE;
if (isset($_GET['closedOnly']) && $_GET['closedOnly'] == TRUE) {
    $closedOnly = TRUE;
    $unreadOnly = FALSE;
}

if ($unreadOnly) {
    if (stripos($eventLogQuery, ' WHERE ') !== FALSE) {
        $eventLogQuery .= " AND event_ack = 0";
    } else {
        $eventLogQuery .= " WHERE event_ack = 0";
    }
}

if ($closedOnly) {
    if (stripos($eventLogQuery, ' WHERE ') !== FALSE) {
        $eventLogQuery .= " AND event_closed = 1";
    } else {
        $eventLogQuery .= " WHERE event_closed = 1";
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ORDER CONTROLS
$orderBy = FALSE;
if (isset($_GET['orderBy'])) {
    $orderBy = $_GET['orderBy'];
    switch ($orderBy) {
        case 'user':
            $eventLogQuery .= " ORDER BY user_id";
            break;
    }
} else {
    $eventLogQuery .= " ORDER BY id";
}


if (isset($_GET['sortDirection']) && $_GET['sortDirection'] == 'DESC') {
    $sortOrder = 'DESC';
    $eventLogQuery .= " DESC";
} else {
    $sortOrder = 'DESC';
    $eventLogQuery .= " ASC";
}





/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LIMIT CONTROLS
if (isset($_GET['$startRow']) && settype($_GET['$startRow'], 'integer')) {
    $startRow = $_GET['$startRow'];
} else {
    $startResultRow = 0;
}

if (isset($_GET['resultSize'])) {
    switch ($_GET['resultSize']) {
        case 20:
        case 30:
        case 50:
        case 100:
            $resultSize = $_GET['resultSize'];
            break;
    }
} else {
    $resultSize = 10;
}

$eventLogQuery .= " LIMIT $startResultRow, $resultSize";
print $eventLogQuery;

$eventLogResult = $DBH->query($eventLogQuery);
$events = $eventLogResult->fetchAll(PDO::FETCH_ASSOC);
print '<pre>';
print_r($events);
print '</pre>';










if ($adminLevel == 2) {

    $errorEventQuery = "SELECT * FROM event_log WHERE event_type = 1 AND event_ack = 0";
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



if (count($userAdministeredProjects) > 0) {
    $projectString = where_in_string_builder($userAdministeredProjects);
    $projectFeedbackQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 3  AND event_ack = 0 AND "
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






