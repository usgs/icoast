<?php

require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    print "No Cookie Data<br>Please login to iCoast first.";
//    header('Location: index.php');
    exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
if (!$userData) {
    print "Failed iCoast Authentication<br>Please logout and then back in to iCoast.";
    exit;
}
$authCheckCode = generate_cookie_credentials($DBH, $userId);

if ($userData['account_type'] != 4) {
    print "Insufficient Permissions<br>Access Denied.";
//    header('Location: index.php');
    exit;
}

$finalDataArray = array();
$dataExportQuery = "SELECT a.image_id, a.annotation_id, a.user_id, i.latitude, i.longitude, i.full_url, u.encrypted_email, u.encryption_data "
        . "FROM annotations a "
        . "LEFT JOIN users u ON a.user_id = u.user_id "
        . "LEFT JOIN images i ON a.image_id = i.image_id "
        . "WHERE a.annotation_completed = 1";
$dataExportResult = $DBH->query($dataExportQuery);

$finalDataArray = $dataExportResult->fetchAll(PDO::FETCH_ASSOC);
foreach ($finalDataArray as &$dataRow) {
    $annotationId = $dataRow['annotation_id'];
    $tagSelectionQuery = "SELECT ans.tag_id, t.name "
            . "FROM annotation_selections ans "
            . "LEFT JOIN tags t ON ans.tag_id = t.tag_id "
            . "WHERE ans.annotation_id = $annotationId";
    $tagSelectionResult = $DBH->query($tagSelectionQuery);
    $annotationSelections = $tagSelectionResult->fetchAll(PDO::FETCH_ASSOC);
    $dataRow['tags'] = $annotationSelections;

//    $tagCommentQuery = "SELECT tag_id "
//            . "FROM annotation_comments "
//            . "WHERE annotation_id = $annotationId";
//    $tagCommentResult = $DBH->query($tagCommentQuery);
//    $annotationComments = $tagCommentResult->fetchAll(PDO::FETCH_ASSOC);
//    foreach ($annotationComments as $annotationComment) {
////print '<pre>';
////print_r($annotationComment);
////print '</pre>';
//        $dataRow['tags'][] = $annotationComment;
//    }
}




$tasksQuery = "SELECT task_id FROM task_metadata WHERE project_id = 1 ORDER BY order_in_project";
$tasksResult = $DBH->query($tasksQuery);
//print_r($DBH->errorInfo());
$tasks = $tasksResult->fetchAll(PDO::FETCH_ASSOC);




$groupsQuery = "SELECT tgm.tag_group_id, tgm.contains_groups, tgm.is_enabled, tc.task_id, tc.order_in_task "
        . "FROM tag_group_metadata tgm "
        . "RIGHT JOIN task_contents tc ON tc.tag_group_id = tgm.tag_group_id "
        . "WHERE tgm.project_id = 1 AND tgm.is_enabled = 1 ORDER BY tc.task_id, tc.order_in_task";
$groupsResult = $DBH->query($groupsQuery);
//print_r($DBH->errorInfo());
$groups = $groupsResult->fetchAll(PDO::FETCH_ASSOC);

//print '<pre>';
//print_r($tasks);
//print '</pre>';
//print '<pre>';
//print_r($groups);
//print '</pre>';

$csvFile = 'Image ID,Latitude,Longitude,File Name,User ID,User E-mail';
$tagIdMap = array();
foreach ($tasks as $task) {
    $taskId = $task['task_id'];
    foreach ($groups as $group) {

        if ($group['task_id'] == $taskId) {

            $groupId = $group['tag_group_id'];
//            print '<pre>';
//            print_r($task);
//            print '</pre>';
//            print '<pre>';
//            print_r($group);
//            print '</pre>';
            if ($group['contains_groups'] == 0) {
                $tagQuery = "SELECT t.tag_id, t.name, t.is_comment_box FROM tag_group_contents tgc "
                        . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                        . "WHERE tgc.tag_group_id = $groupId AND t.is_enabled = 1 AND t.is_comment_box = 0 "
                        . "ORDER BY tgc.order_in_group";
                $tagResult = $DBH->query($tagQuery);
//                print_r($DBH->errorInfo());
                $groupTags = $tagResult->fetchAll(PDO::FETCH_ASSOC);
//                print '<pre>';
//                print_r($groupTags);
//                print '</pre>';
                foreach ($groupTags as $tag) {
                    $csvFile .= ",{$tag['name']}";
                    $tagIdMap[] = array(
                        'id' => $tag['tag_id'],
                        'isComment' => $tag['is_comment_box']
                    );
                }
            } else {
                $nestedGroupQuery = "SELECT tag_id "
                        . "FROM tag_group_contents "
                        . "WHERE tag_group_id = $groupId "
                        . "ORDER BY order_in_group";
                $nestedGroupResult = $DBH->query($nestedGroupQuery);
//                print_r($DBH->errorInfo());

                $nestedGroups = $nestedGroupResult->fetchAll(PDO::FETCH_ASSOC);
                foreach ($nestedGroups as $nestedGroup) {
//                    print '<pre>';
//                    print_r($nestedGroup);
//                    print '</pre>';
                    $nestedGroupId = $nestedGroup['tag_id'];
                    $nestedTagQuery = "SELECT t.tag_id, t.name, t.is_comment_box FROM tag_group_contents tgc "
                            . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                            . "WHERE tgc.tag_group_id = $nestedGroupId AND t.is_enabled = 1 AND t.is_comment_box = 0 "
                            . "ORDER BY tgc.order_in_group";
                    $nestedTagResult = $DBH->query($nestedTagQuery);
//                    print_r($DBH->errorInfo());
                    $nestedGroupTags = $nestedTagResult->fetchAll(PDO::FETCH_ASSOC);
//                    print '<pre>';
//                    print_r($nestedGroupTags);
//                    print '</pre>';
                    foreach ($nestedGroupTags as $tag) {
                        $csvFile .= ",{$tag['name']}";
                        $tagIdMap[] = array(
                            'id' => $tag['tag_id'],
                            'isComment' => $tag['is_comment_box']
                        );
                    }
                }
            }
        }
    }
}
$csvFile .= PHP_EOL;
//print $csvFile;
//print '<pre>';
//print_r($tagIdMap);
//print '</pre>';

foreach ($finalDataArray as $annotation) {
    $annotationId = $annotation['annotation_id'];
    $unencryptedEmail = mysql_aes_decrypt($annotation['encrypted_email'], $annotation['encryption_data']);
    $csvFile .= $annotation['image_id'] . ',' . $annotation['latitude'] . ',' . $annotation['longitude'] . ',' . $annotation['full_url'] . ',' . $annotation['user_id'] . ',' . $unencryptedEmail;

    foreach ($tagIdMap as $targetTag) {
        $tagFound = FALSE;
        for ($i = 0; $i < count($annotation['tags']); $i++) {
            if ($annotation['tags'][$i]['tag_id'] == $targetTag['id']) {
                $tagFound = True;
            }
        }
        if ($tagFound) {
//            if ($targetTag['isComment'] == 0) {
                $csvFile .= ",1";
//            } else {
//                $commentQuery = "SELECT comment FROM annotation_comments WHERE annotation_id = $annotationId AND tag_id = {$targetTag['id']}";
////                print $commentQuery;
//                $commentResult = $DBH->query($commentQuery);
//                $comment = $commentResult->fetchColumn();
//                print '<h1>$comment</h1>';
//                $csvFile .= ",$comment";
//            }
        } else {
//            if ($targetTag['isComment'] == 0) {
                $csvFile .= ",0";
//            } else {
//                $commentQuery = "SELECT comment FROM annotation_comments WHERE annotation_id = $annotationId AND tag_id = {$targetTag['id']}";
////                print $commentQuery;
//                $commentResult = $DBH->query($commentQuery);
//                $comment = $commentResult->fetchColumn();
//                print "<h1>$comment</h1>";
//                $csvFile .= ",";
//            }
        }
    }
    $csvFile .= PHP_EOL;
}
print "<h1>View the source of this page and copy the content below into a text file. Save it as a csv and import into your chosen application</h1>\n\r";

print $csvFile;








//print '<pre>';
//print_r($finalDataArray);
//print '</pre>';

