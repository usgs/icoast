<?php

require_once('../includes/globalFunctions.php');
require $dbmsConnectionPathDeep;

$eventDataError = array();
$eventData = array();

print '<pre>';
print_r($_POST);
print '</pre>';

if (isset($_POST['eventType'])) {
    $setTypeResult = setType($_POST['eventType'], "integer");
    if ($setTypeResult &&
            ($_POST['eventType'] === 1 || $_POST['eventType'] === 2 || $_POST['eventType'] === 3)) {
        $eventData['event_type'] = $_POST['eventType'];
    } else {
        $eventDataError[] = "Invalid Event Type in supplied Event Logger data: {$_POST['eventType']}";
    }
} else {
    $eventDataError[] = "Missing Event Type field in supplied Event Logger data.";
}


if (isset($_POST['eventText'])) {
    if (strlen($_POST['eventText']) > 0) {
        $eventData['event_text'] = $_POST['eventText'];
    } else {
        $eventDataError[] = "Empty Event Text field in supplied Event Logger data.";
    }
} else {
    $eventDataError[] = "Missing Event Text field in supplied Event Logger data.";
}

if (isset($_POST['eventSummary'])) {
    if (strlen($_POST['eventSummary']) > 0) {
        $eventData['event_summary'] = $_POST['eventSummary'];
    } else {
        $eventDataError[] = "Empty Event Summary field in supplied Event Logger data.";
    }
} else {
    $eventDataError[] = "Missing Event Summary field in supplied Event Logger data.";
}


if (isset($_POST['userId'])) {
    $setTypeResult = setType($_POST['eventType'], "integer");
    if ($setTypeResult && $_POST['userId'] > 0) {
        $eventData['user_id'] = $_POST['userId'];
    } else {
        $eventDataError[] = "Invalid User Id field in supplied Event Logger data: {$_POST['userId']}";
    }
}

if (isset($_POST['url'])) {
    $eventData['source_url'] = $_POST['url'];
}

if (isset($_POST['queryString'])) {
    $eventData['query_string'] = $_POST['queryString'];
}

if (isset($_POST['postData'])) {
    $eventData['post_data'] = $_POST['postData'];
}

if (isset($_POST['sourceScript'])) {
    $eventData['source_script'] = $_POST['sourceScript'];
}

if (isset($_POST['sourceFunction'])) {
    $eventData['source_function'] = $_POST['sourceFunction'];
}

if (isset($_POST['clientAgent'])) {
    $eventData['client_agent'] = $_POST['clientAgent'];
}

if (isset($_POST['eventCode'])) {
    $setTypeResult = setType($_POST['eventCode'], "integer");
    if ($setTypeResult && $_POST['eventCode'] > 0) {
        $eventData['event_code'] = $_POST['eventCode'];
    } else {
        $eventDataError[] = "Invalid Event Code field in supplied Event Logger data: {$_POST['eventCode']}";
    }
}

print '<pre>';
print_r($eventData);
print '</pre>';

if (count($eventDataError = 0)) {
    $columnString = '';
    $valueString = '';
    $eventLogParams = array();
    foreach ($eventData as $column => $value) {
        $columnString .= ', ' . $column;
        $valueString .= ', :' . $column;
    }
    $eventLogQuery = "INSERT into event_log (event_time$columnString) VALUES (NOW()$valueString)";
    print $eventLogQuery;
    run_prepared_query($DBH, $eventLogQuery, $eventData);
} else {
    //Call to self required to log error with error logging!
}