<?php

require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

function database_where_query_builder($query) {
    if (stripos($query, ' WHERE ') !== FALSE) {
        return " AND";
    } else {
        return " WHERE";
    }
}

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);
$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];



$eventLogQuery = "SELECT e.*, u.masked_email "
        . "FROM event_log e "
        . "LEFT JOIN users u "
        . "ON e.user_id = u.user_id";
$eventLogParams = array();

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// FILTERS

$project = null;
$eventType = null;
$user = null;
$userAdministeredProjects = find_administered_projects($DBH, $userId);
$errors = array();

if (isset($_POST['project']) && settype($_POST['project'], 'integer')) {
    $project = $_POST['project'];
    if ($adminLevel == 4 || (($adminLevel == 3 || $adminLevel == 2) && in_array($project, $userAdministeredProjects))) {
        $eventLogQuery .= database_where_query_builder($eventLogQuery);
        $eventLogQuery .= " event_type = 3 AND event_code = :project";
        $eventLogParams['project'] = $project;
    } else {
        $errors[] = "Query Failed. You requested to see data for a project that either you do not have "
                . "permission to access or does not exist.";
        $project = null;
    }
}

if (isset($_POST['eventType']) && settype($_POST['eventType'], 'integer') && is_null($project)) {
    $eventType = $_POST['eventType'];
    if ($adminLevel == 4 && ($eventType >= 1 && $eventType <= 3)) {
        $eventLogQuery .= database_where_query_builder($eventLogQuery);
        $eventLogQuery .= " event_type = :eventType";
        $eventLogParams['eventType'] = $eventType;
    } else {
        $errors[] = "Query Failed. You requested to see data for a type of event that either you do not have "
                . "permission to access or does not exist.";
        $eventType = null;
    }
}

if (isset($_POST['user']) && settype($_POST['user'], 'integer')) {
    $user = $_POST['user'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " e.user_id = :user";
    $eventLogParams['user'] = $user;
}

if (isset($_POST['sourceURL'])) {
    $sourceURL = $_POST['sourceURL'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_url LIKE :sourceURL";
    $eventLogParams['sourceURL'] = "%$sourceURL";
}

if (isset($_POST['sourceScript'])) {
    $sourceScript = $_POST['sourceScript'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_url LIKE :sourceScript";
    $eventLogParams['sourceScript'] = "%$sourceScript";
}

if (isset($_POST['sourceFunction'])) {
    $sourceFunction = $_POST['sourceFunction'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_url LIKE :sourceFunction";
    $eventLogParams['sourceFunction'] = "%$sourceFunction";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// RESTRICT NON-SYSTEM ADMINS TO PROJECT RESULTS ONLY
//
if (is_null($project) && is_null($eventType) && ($adminLevel == 3 || $adminLevel == 2)) {
    $projectString = where_in_string_builder($userAdministeredProjects);
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " event_type = 3 AND event_code IN ($projectString)";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// READ OR CLOSED EVENTS

if (isset($_POST['read']) && $_POST['read'] == 1) {
    $read = TRUE;
} else if (isset($_POST['read']) && $_POST['read'] == 0) {
    $read = FALSE;
}

if (isset($_POST['closed']) && $_POST['closed'] == 1) {
    $closed = TRUE;
    if (isset($read)) {
        unset($read);
    }
} else if (isset($_POST['closed']) && $_POST['closed'] == 0) {
    $closed = FALSE;
}

if (isset($read) && $read == TRUE) {
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " event_ack = 1";
} else if (isset($read) && $read == FALSE) {
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " event_ack = 0";
}

if (isset($closed) && $closed == TRUE) {
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " event_closed = 1";
} else if (isset($closed) && $closed == FALSE) {
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " event_closed = 0";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ORDER CONTROLS
$orderBy = FALSE;
if (isset($_POST['orderBy'])) {
    $orderBy = $_POST['orderBy'];
    switch ($orderBy) {
        case 'user':
            $eventLogQuery .= " ORDER BY user_id";
            break;
        case 'type':
            $eventLogQuery .= " ORDER BY event_type";
            break;
        case 'url':
            $eventLogQuery .= " ORDER BY source_url";
            break;
        case 'script':
            $eventLogQuery .= " ORDER BY source_script";
            break;
        case 'function':
            $eventLogQuery .= " ORDER BY source_function";
            break;
        case 'project':
            $eventLogQuery .= " ORDER BY event_code";
            break;
        case 'read':
            $eventLogQuery .= " ORDER BY event_ack";
            break;
        case 'closed':
            $eventLogQuery .= " ORDER BY event_closed";
            break;
        case 'time':
        default:
            $eventLogQuery .= " ORDER BY event_time";
            break;
    }
} else {
    $eventLogQuery .= " ORDER BY event_time";
}


if (isset($_POST['sortDirection']) && $_POST['sortDirection'] == 'ASC') {
    $sortOrder = 'ASC';
    $eventLogQuery .= " ASC";
} else {
    $sortOrder = 'DESC';
    $eventLogQuery .= " DESC";
}





/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LIMIT CONTROLS
if (isset($_POST['startRow']) && settype($_POST['startRow'], 'integer')) {
    $startResultRow = $_POST['startRow'];
} else {
    $startResultRow = 0;
}

if (isset($_POST['resultSize'])) {
    switch ($_POST['resultSize']) {
        case 20:
        case 30:
        case 50:
        case 100:
            $resultSize = $_POST['resultSize'];
            break;
    }
} else {
    $resultSize = 10;
}

$eventLogQuery .= " LIMIT $startResultRow, $resultSize";



//print $eventLogQuery;
if (count($errors) === 0) {
    $eventLogResult = run_prepared_query($DBH, $eventLogQuery, $eventLogParams);
    $events = $eventLogResult->fetchAll(PDO::FETCH_ASSOC);
} else {
    foreach ($errors as $error) {
        $events['controlData']['errors'][] = $error;
    }
}

print '<pre>';
print_r($events);
print '</pre>';

echo json_encode($events);
