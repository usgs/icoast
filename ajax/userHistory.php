<?php

require('../includes/globalFunctions.php');
require('../includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

// SET DEFAULT VARIABLE VALUES
$queryParams = array();
$queryProjectAndClause = '';
$startRow = 0;
$rowsPerPage = 0;

// CHECK SUPPLIED PARAMETERS. SET REQUIRED VARIABLES
if (isset($_POST['userId'])) {
    settype($_POST['userId'], 'integer');
    if (!empty($_POST['userId'])) {
        $userMetadata = retrieve_entity_metadata($DBH, $_POST['userId'], 'users');
    }
}
if (!$userMetadata) {
    // EXIT IF NO USER ID WAS SUPPLIED (REQUIRED)
    exit();
}
if (isset($_POST['projectId'])) {
    settype($_POST['projectId'], 'integer');
    if (!empty($_POST['projectId'])) {
        $projectMetadata = retrieve_entity_metadata($DBH, $_POST['projectId'], 'project');
    }
}
if ($projectMetadata) {
    $queryProjectAndClause .= "AND a.project_id = {$projectMetadata['project_id']}";
    $queryParams['projectId'] = $projectMetadata['project_id'];
}

if (isset($_POST['startingRow'])) {
    settype($_POST['startingRow'], 'integer');
    if (!empty($_POST['startingRow'])) {
        $startRow = $_POST['startingRow'];
    }
}

if (isset($_POST['rowsPerPage'])) {
    settype($_POST['rowsPerPage'], 'integer');
    if (!empty($_POST['rowsPerPage'])) {
        $rowsPerPage = $_POST['rowsPerPage'];
    }
}

// CREATE DB QUERIES BASED ON IF A PROJECT WAS SPECIFIED
if ($projectMetadata) {
    $userStartedClassificationQuery = "SELECT annotation_id, project_id, a.image_id, initial_session_start_time, "
            . "initial_session_end_time, annotation_completed, thumb_url "
            . "FROM annotations a "
            . "LEFT JOIN images i ON a.image_id = i.image_id "
            . "WHERE user_id = :userId AND project_id = :projectId AND NOT user_match_id = '' "
            . "ORDER BY initial_session_start_time DESC "
            . "LIMIT $startRow, $rowsPerPage";
    $userStartedClassificationCountQuery = "SELECT COUNT(*) "
            . "FROM annotations "
            . "WHERE user_id = :userId AND project_id = :projectId AND NOT user_match_id = '' ";
    $userStartedClassificationParams = array(
        'projectId' => $projectMetadata['project_id'],
        'userId' => $userMetadata['user_id']
    );
} else {
    $userStartedClassificationQuery = "SELECT annotation_id, project_id, a.image_id, initial_session_start_time, "
            . "initial_session_end_time, annotation_completed, thumb_url "
            . "FROM annotations a "
            . "LEFT JOIN images i ON a.image_id = i.image_id "
            . "WHERE user_id = :userId AND NOT user_match_id = '' "
            . "ORDER BY initial_session_start_time DESC "
            . "LIMIT $startRow, $rowsPerPage";
//    print $userAnnotationQuery;
    $userStartedClassificationCountQuery = "SELECT COUNT(*) "
            . "FROM annotations "
            . "WHERE user_id = :userId AND NOT user_match_id = '' ";
    $userStartedClassificationParams['userId'] = $userMetadata['user_id'];
}

// RUN DB QUERIES TO RETURN ANNOTATIONS AND ANNOTATION COUNT
$STH = run_prepared_query($DBH, $userStartedClassificationQuery, $userStartedClassificationParams);
$userStartedClassifications = $STH->fetchAll(PDO::FETCH_ASSOC);
$STH = run_prepared_query($DBH, $userStartedClassificationCountQuery, $userStartedClassificationParams);
$userStartedClassificationCount = $STH->fetchColumn();

$projectDirectory = array();
for ($i = 0; $i < count($userStartedClassifications); $i++) {
    $annotationProjectId = $userStartedClassifications[$i]['project_id'];
    $annotationId = $userStartedClassifications[$i]['annotation_id'];
    $annotationImageId = $userStartedClassifications[$i]['image_id'];
    $annotationStartTime = $userStartedClassifications[$i]['initial_session_start_time'];
    $annotationEndTime = $userStartedClassifications[$i]['initial_session_end_time'];

    if (!array_key_exists($annotationProjectId, $projectDirectory)) {
        $projectMetadata = retrieve_entity_metadata($DBH, $annotationProjectId, 'project');
        $projectDirectory[$annotationProjectId] = $projectMetadata['name'];
    }
    $userStartedClassifications[$i]['project_name'] = $projectDirectory[$annotationProjectId];

    $userStartedClassifications[$i]['time_spent'] = timeDifference($annotationStartTime, $annotationEndTime, FALSE);
    $userStartedClassifications[$i]['annotation_time'] = formattedTime($annotationEndTime, $userMetadata['time_zone'], FALSE);
    $userStartedClassifications[$i]['number_of_tags'] = tagsInAnnotation($DBH, $annotationId);

    $annotationImageMetadata = retrieve_entity_metadata($DBH, $annotationImageId, 'image');
    $userStartedClassifications[$i]['location'] = build_image_location_string($annotationImageMetadata, TRUE);
    $userStartedClassifications[$i]['latitude'] = $annotationImageMetadata['latitude'];
    $userStartedClassifications[$i]['longitude'] = $annotationImageMetadata['longitude'];

    unset($userStartedClassifications[$i]['initial_session_start_time']);
    unset($userStartedClassifications[$i]['initial_session_end_time']);
}

// ADD CONTROL DATA TO THE ARRAY (ANNOTATION COUNT)
$userStartedClassifications['controlData'] = array(
    'resultCount' => $userStartedClassificationCount,
);

// ENCODE RESULTS INTO JSON FORMAT AND RETURN
echo json_encode($userStartedClassifications);

