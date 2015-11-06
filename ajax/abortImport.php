<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);
ini_set('max_execution_time', 180);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_GET['projectId'])) {
    $projectId = $_GET['projectId'];
} else {
    $projectId = null;
}
settype($projectId, 'integer');
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (!empty($projectMetadata) && $projectMetadata['creator'] == $userData['user_id']) {

    if (isset($_GET['collectionType']) &&
            ($_GET['collectionType'] == 'pre' || $_GET['collectionType'] == 'post')) {
        $collectionType = $_GET['collectionType'];
        $collectionQuery = '
        SELECT import_status_message
        FROM import_collections
        WHERE parent_project_id = :projectId
            AND collection_type = :collectionType';
        $collectionQueryParams = array(
            'projectId' => $projectMetadata['project_id'],
            'collectionType' => $collectionType
        );
        $collectionResults = run_prepared_query($DBH, $collectionQuery, $collectionQueryParams);
        $collectionState = $collectionResults->fetchColumn();
        if ($collectionState != 'Complete') {
            $abortImportQuery = '
                UPDATE import_collections
                SET user_abort_import_flag = 1
                WHERE parent_project_id = :projectId
                    AND collection_type = :collectionType';
            $abortImportResult = run_prepared_query($DBH, $abortImportQuery, $collectionQueryParams);
            if ($abortImportResult->rowCount() == 1) {
//                $sleepCount = 0;
//                do {
//                    $sleepCount++;
//                    sleep(5);
//                    $collectionQuery = '
//                        SELECT import_status_message
//                        FROM import_collections
//                        WHERE parent_project_id = :projectId
//                            AND collection_type = :collectionType';
//                    $collectionResults = run_prepared_query($DBH, $collectionQuery, $collectionQueryParams);
//                    $collectionState = $collectionResults->fetchColumn();
//                } while ($collectionState != 'User Abort Request' && $sleepCount < 12);
//                if ($collectionState == 'User Abort Request') {
                    print 1;
                    exit;
//                }
            }
        }
    }
}
print 0;


