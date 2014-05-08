<?php

require('../includes/globalFunctions.php');
require('../includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$projectId = $_POST['projectId'];
$userId = $_POST['userId'];
$userTimeZone = $_POST['userTimeZone'];
$startRow = $_POST['startingRow'];
$rowsPerPage = $_POST['rowsPerPage'];
//$endRow = $startRow + $rowsPerPage;

if (!is_numeric($projectId)) {
    //  Placeholder for error management
    print 'Invalid Project ID: Must be numeric.';
    exit;
}
if (!is_numeric($startRow)) {
    $startRow = 0;
}

if ($projectId > 0) {
    $userAnnotationQuery = "SELECT annotation_id, project_id, image_id, initial_session_start_time, "
            . "initial_session_end_time, annotation_completed FROM annotations "
            . "WHERE user_id = :userId AND project_id = :projectId AND NOT user_match_id = '' "
            . "ORDER BY initial_session_start_time DESC LIMIT $startRow, $rowsPerPage";
    $userAnnotationCountQuery = "SELECT COUNT(*) FROM annotations "
            . "WHERE user_id = :userId AND project_id = :projectId AND NOT user_match_id = '' ";
    $userAnnotationParams = array(
        'projectId' => $projectId,
        'userId' => $userId
    );
} else {
    $userAnnotationQuery = "SELECT annotation_id, project_id, image_id, initial_session_start_time, "
            . "initial_session_end_time, annotation_completed FROM annotations "
            . "WHERE user_id = :userId AND NOT user_match_id = '' "
            . "ORDER BY initial_session_start_time DESC LIMIT $startRow, $rowsPerPage";
//    print $userAnnotationQuery;
    $userAnnotationCountQuery = "SELECT COUNT(*) FROM annotations "
            . "WHERE user_id = :userId AND NOT user_match_id = '' ";
    $userAnnotationParams['userId'] = $userId;
}

$STH = run_prepared_query($DBH, $userAnnotationQuery, $userAnnotationParams);
$userAnnotations = $STH->fetchAll(PDO::FETCH_ASSOC);
$STH = run_prepared_query($DBH, $userAnnotationCountQuery, $userAnnotationParams);
$userAnnotationCount = $STH->fetchColumn();

$projectDirectory = array();
for ($i = 0; $i < count($userAnnotations); $i++) {
    $annotationProjectId = $userAnnotations[$i]['project_id'];
    $annotationId = $userAnnotations[$i]['annotation_id'];
    $annotationImageId = $userAnnotations[$i]['image_id'];
    $annotationStartTime = $userAnnotations[$i]['initial_session_start_time'];
    $annotationEndTime = $userAnnotations[$i]['initial_session_end_time'];

    if (!array_key_exists($annotationProjectId, $projectDirectory)) {
        $projectMetadata = retrieve_entity_metadata($DBH, $annotationProjectId, 'project');
        $projectDirectory[$annotationProjectId] = $projectMetadata['name'];
    }
    $userAnnotations[$i]['project_name'] = $projectDirectory[$annotationProjectId];

    $userAnnotations[$i]['time_spent'] = timeDifference($annotationStartTime, $annotationEndTime, FALSE);
    $userAnnotations[$i]['annotation_time'] = formattedAnnotationTime($annotationEndTime, $userTimeZone, FALSE);
    $userAnnotations[$i]['number_of_tags'] = tagsInAnnotation($DBH, $annotationId);

    $annotationImageMetadata = retrieve_entity_metadata($DBH, $annotationImageId, 'image');
    $userAnnotations[$i]['location'] = build_image_location_string($annotationImageMetadata, TRUE);
    $userAnnotations[$i]['latitude'] = $annotationImageMetadata['latitude'];
    $userAnnotations[$i]['longitude'] = $annotationImageMetadata['longitude'];

    unset($userAnnotations[$i]['initial_session_start_time']);
    unset($userAnnotations[$i]['initial_session_end_time']);
}

$userAnnotations['controlData'] = array(
    'resultCount' => $userAnnotationCount,
);

echo json_encode($userAnnotations);

