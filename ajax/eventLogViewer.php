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
$interactiveUserId = $userData['user_id'];
$adminLevel = $userData['account_type'];
$userTimeZone = $userData['time_zone'];
$userAdministeredProjects = find_administered_projects($DBH, $interactiveUserId, FALSE);




if (isset($_POST['action']) && isset($_POST['event_id'])) {
    $updatePermission = FALSE;
    if ($adminLevel == 2 || $adminLevel == 3) {
        $updatePermissionCheckQuery = 'SELECT event_type, event_code FROM event_log WHERE eventId = :eventId';
        $updatePermissionCheckParams['eventId'] = $_POST['event_id'];
        $updatePermissionCheckResult = run_prepared_query($DBH, $updatePermissionCheckQuery, $updatePermissionCheckParams);
        $eventPermissionData = $updatePermissionCheckResult->fetch(PDO::FETCH_ASSOC);
        if ($eventPermissionData['event_code'] == 3 && in_array($eventPermissionData['event_code'], $userAdministeredProjects)) {
            $updatePermission = TRUE;
        }
    } else {
        $updatePermission = TRUE;
    }

    if ($updatePermission) {
        switch ($_POST['action']) {
            case 'markRead':
                $updateQuery = "UPDATE event_log SET event_ack=1 WHERE id=:eventId LIMIT 1";
                break;
            case 'markUnread':
                $updateQuery = "UPDATE event_log SET event_ack=0 WHERE id=:eventId LIMIT 1";
                break;
            case 'markClosed':
                $updateQuery = "UPDATE event_log SET event_ack=1, event_closed=1 WHERE id=:eventId LIMIT 1";
                break;
            case 'markOpen':
                $updateQuery = "UPDATE event_log SET event_ack=1, event_closed=0 WHERE id=:eventId LIMIT 1";
                break;
        }
        $updateParams['eventId'] = $_POST['event_id'];
        $result = run_prepared_query($DBH, $updateQuery, $updateParams);
        if ($result) {
            print 1;
            exit;
        } else {
            // Error
            print 0;
            exit;
        }
    } else {
        // Error
        print 0;
        exit;
    }
}


$eventCountQueryStart = "SELECT COUNT(*) FROM event_log e";
$eventLogQueryStart = $eventLogQuery = "SELECT e.*, u.masked_email "
        . "FROM event_log e "
        . "LEFT JOIN users u "
        . "ON e.user_id = u.user_id";
$eventLogParams = array();

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// FILTERS

$project = null;
$eventType = null;
$searchUserId = null;
$errors = array();
if (isset($_POST['project_id']) && settype($_POST['project_id'], 'integer')) {
//    print "In Project";
    $project = $_POST ['project_id'];
    if ($adminLevel == 4 || (( $adminLevel == 3 || $adminLevel == 2) && in_array($project, $userAdministeredProjects))) {
        $eventLogQuery .= database_where_query_builder($eventLogQuery);
        $eventLogQuery .= " event_type = 3 AND event_code = :project";
        $eventLogParams['project'] = $project;
    } else {
        $errors[] = "Query Failed. You requested to see data for a project that either you do not have "
                . "permission to access or does not exist.";
        $project = null;
    }
}

if (isset($_POST ['event_type']) && settype($_POST['event_type'], 'integer') && is_null($project)) {
//    print "In Event";
    $eventType = $_POST ['event_type'];
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


if (isset($_POST['user_id']) && settype($_POST['user_id'], 'integer')) {
    $searchUserId = $_POST['user_id'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " e.user_id = :userId";
    $eventLogParams['userId'] = $searchUserId;
}

if (isset($_POST['source_url'])) {
    $sourceURL = $_POST['source_url'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_url LIKE :sourceURL";
    $eventLogParams['sourceURL'] = "%$sourceURL";
}

if (isset($_POST['source_script'])) {
    $sourceScript = $_POST['source_script'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_script LIKE :sourceScript";
    $eventLogParams['sourceScript'] = "%$sourceScript";
}

if (isset($_POST['source_function'])) {
    $sourceFunction = $_POST['source_function'];
    $eventLogQuery .= database_where_query_builder($eventLogQuery);
    $eventLogQuery .= " source_function = :sourceFunction";
    $eventLogParams['sourceFunction'] = "$sourceFunction";
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

if (isset($_POST['event_ack']) && $_POST['event_ack'] == 1) {
    $read = TRUE;
} else if (isset($_POST['event_ack']) && $_POST['event_ack'] == 0) {
    $read = FALSE;
}

if (isset($_POST['event_closed']) && $_POST['event_closed'] == 1) {
    $closed = TRUE;
    if (isset($read)) {
        unset($read);
    }
} else if (isset($_POST['event_closed']) && $_POST['event_closed'] == 0) {
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

// CREATE THE QUERY FOR THE TOTAL RESULT COUNT
$eventCountQuery = str_replace($eventLogQueryStart, $eventCountQueryStart, $eventLogQuery);

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ORDER CONTROLS
$orderBy = FALSE;
if (isset($_POST['sort_by_column'])) {
    $sortByColumn = $_POST['sort_by_column'];
    switch ($sortByColumn) {
        case 'event_type':
        case 'source_url':
        case 'source_script':
        case 'source_function':
        case 'event_code':
        case 'event_ack':
        case 'event_closed':
        case 'id':
            $eventLogQuery .= " ORDER BY $sortByColumn";
            break;
        case 'masked_email':
            $eventLogQuery .= " ORDER BY $sortByColumn";
            break;
        case 'event_time':
        default:
            $eventLogQuery .= " ORDER BY event_time";
            break;
    }
} else {
    $eventLogQuery .= " ORDER BY event_time";
}


if (isset($_POST['sort_direction']) && $_POST['sort_direction'] == 'asc') {
    $eventLogQuery .= " ASC";
} else {
    $eventLogQuery .= " DESC";
}





/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LIMIT CONTROLS
if (isset($_POST['start_row']) && settype($_POST['start_row'], 'integer')) {
    $startResultRow = $_POST['start_row'];
} else {
    $startResultRow = 0;
}

if (isset($_POST['rows_to_display'])) {
    switch ($_POST['rows_to_display']) {
        case 20:
        case 30:
        case 50:
        case 100:
            $rowsToDisplay = $_POST['rows_to_display'];
            break;
        case 10:
        default:
            $rowsToDisplay = 10;
    }
} else {
    $rowsToDisplay = 10;
}

$eventLogQuery .= " LIMIT  $startResultRow, $rowsToDisplay";

//print $eventLogQuery . '<br>';
;
if (count($errors) === 0) {
    $eventLogResult = run_prepared_query($DBH, $eventLogQuery, $eventLogParams);
    $events = $eventLogResult->fetchAll(PDO::FETCH_ASSOC);

    $projectQuery = "SELECT project_id, name FROM projects";
    $projectsResult = $DBH->query($projectQuery);
    while ($project = $projectsResult->fetch(PDO::FETCH_ASSOC)) {
        $allProjects[$project['project_id']] = $project['name'];
    }

    for ($i = 0; $i < count($events); $i++) {
        // Convert project id to project name if applicable.
        if ($events[$i]['event_type'] == 3) {
            if (array_key_exists($events[$i]['event_code'], $allProjects)) {
                $events[$i]['event_code'] = $allProjects[$events[$i]['event_code']];
            }
        }

        // Format time and convert to user timezone.
        $events[$i]['short_time'] = formattedTime($events[$i]['event_time'], $userTimeZone, FALSE);
        $events[$i]['long_time'] = formattedTime($events[$i]['event_time'], $userTimeZone, TRUE);
        $events[$i]['event_time'] = strtotime($events[$i]['event_time']);

// Format Event Type
        switch ($events[$i]['event_type']) {
            case 1:
                $events[$i]['event_type'] = "System Error";
                break;
            case 2:
                $events[$i]['event_type'] = "System Feedback";
                break;
            case 3:
                $events[$i]['event_type'] = "Project Feedback";
                break;
        }

        // Create short page name
        //        print $events[$i]['source_url'];
        $sourcePath = rtrim($events[$i]['source_url'], '/');
        $substrStart = strrpos($sourcePath, '/') + 1;
        $events[$i]['page_name'] = substr($sourcePath, $substrStart);

        // Replace null fields with empty string.
        foreach ($events[$i] as $key => $value) {
            if (is_null($value)) {
                $events[$i][$key] = "";
            }
        }
    }
    $eventCountResult = run_prepared_query($DBH, $eventCountQuery, $eventLogParams);
    $events['controlData']['resultCount'] = $eventCountResult->fetchColumn();
} else {
    foreach ($errors as $error) {
        $events['errors'][] = $error;
    }
}

//print '<pre>';
//print_r($events);
//print '</pre>';

echo json_encode($events);
