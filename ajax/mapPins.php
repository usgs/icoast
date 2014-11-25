<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$queryProjectWhereCondition = '';

if (isset($_GET['projectId'])) {
    settype($_GET['projectId'], 'integer');
    if (!empty($_GET['projectId'])) {
        $ProjectMetadata = retrieve_entity_metadata($DBH, $_GET['projectId'], 'project');
    }
}

if ($ProjectMetadata) {
    $queryProjectWhereCondition = "WHERE project_id = {$ProjectMetadata['project_id']}";
}

switch ($_GET['requestType']) {
    case 'unclassified':
        $unclassifiedPhotoQuery = "SELECT i.image_id, i.latitude, i.longitude, m.post_collection_id, m.pre_collection_id, m.pre_image_id "
            . "FROM images i "
            . "INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN "
            . "     ("
            . "         SELECT DISTINCT post_collection_id "
            . "         FROM projects "
            . "         $queryProjectWhereCondition"
            . "     ) "
            . "     AND m.pre_collection_id IN "
            . "     ("
            . "         SELECT DISTINCT pre_collection_id "
            . "         FROM projects "
            . "         $queryProjectWhereCondition"
            . "     ) "
            . "LEFT JOIN annotations a ON a.image_id = i.image_id "
            . "WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0 AND i.dataset_id IN "
            . " ("
            . "     SELECT dataset_id "
            . "     FROM datasets "
            . "     WHERE collection_id "
            . "     IN ("
            . "         SELECT DISTINCT post_collection_id "
            . "         FROM projects "
            . "         $queryProjectWhereCondition"
            . "         ) "
            . " ) "
            . "GROUP BY i.image_id "
            . "HAVING COUNT(DISTINCT a.annotation_id) = 0 OR (COUNT(DISTINCT a.annotation_id) != 0 AND SUM(a.annotation_completed) = 0)";
        $result = $DBH->query($unclassifiedPhotoQuery)->fetchAll(PDO::FETCH_ASSOC);
        $unclassifiedPhotoJSON = json_encode($DBH->query($unclassifiedPhotoQuery)->fetchAll(PDO::FETCH_ASSOC));
        break;
    default:
        $unclassifiedPhotoJSON = json_encode(array());
        break;
}

print $unclassifiedPhotoJSON;

