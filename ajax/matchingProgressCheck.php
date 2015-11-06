<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

if (isset($_GET['projectId'])) {
    $projectId = $_GET['projectId'];
} else {
    exit;
}
settype($projectId, 'integer');
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (empty($projectMetadata) || $projectMetadata['creator'] != $userData['user_id']) {
    exit;
}
unset($projectId);

$result['progress'] = (int)$projectMetadata['matching_progress'];

if ($projectMetadata['matching_progress'] > 100) {
    $imagesParam['projectId'] = $projectMetadata['project_id'];
    $numberOfImagesQuery = '
        SELECT COUNT(*)
        FROM import_matches
        WHERE project_id = :projectId';
    $numberOfImagesResult = run_prepared_query($DBH, $numberOfImagesQuery, $imagesParam);
    $numberOfImages = $numberOfImagesResult->fetchColumn();
    if ($numberOfImages > 0) {
        $numberOfImagesWithMatchesQuery = '
        SELECT COUNT(*)
        FROM import_matches
        WHERE project_id = :projectId
            AND pre_image_id != 0';
        $numberOfImagesWithMatchesResult = run_prepared_query($DBH, $numberOfImagesWithMatchesQuery, $imagesParam);
        $numberOfImagesWithMatches = $numberOfImagesWithMatchesResult->fetchColumn();
        $percentageOfImagesWithMatches = floor(($numberOfImagesWithMatches / $numberOfImages) * 100);
    } else {
        $numberOfImagesWithMatches = 0;
        $percentageOfImagesWithMatches = 0;
    }
    $result['percentageWithMatches'] = $percentageOfImagesWithMatches;
    $result['numberWithMatches'] = $numberOfImagesWithMatches;
}

print json_encode($result);

