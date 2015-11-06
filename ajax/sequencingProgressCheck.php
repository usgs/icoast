<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_GET['collectionId'])) {
    $collectionId = $_GET['collectionId'];
} else {
    exit;
}
settype($collectionId, 'integer');
$collectionMetadata = retrieve_entity_metadata($DBH, $collectionId, 'importCollection');
if (empty($collectionMetadata)) {
    exit;
}
unset($collectionId);

$projectCreatorQuery = '
    SELECT creator
    FROM projects
    WHERE project_id = :projectId';
$projectCreatorParams['projectId'] = $collectionMetadata['parent_project_id'];
$projectCreatorResult = run_prepared_query($DBH, $projectCreatorQuery, $projectCreatorParams);
$creator = $projectCreatorResult->fetchColumn();
if ($creator != $userData['user_id']) {
    exit;
}

$results = array();

if ($collectionMetadata['sequencing_stage'] == 1) {
    $results['response'] = 'initializing';
} else if ($collectionMetadata['sequencing_stage'] == 2) {
    $results['response'] =  $collectionMetadata['sequencing_progress'];
} else {
    $results['response'] = 'complete';
}
print json_encode($results);

