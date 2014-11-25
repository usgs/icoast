<?php

require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE);
$userId = $userData['user_id'];
$userEmail = mysql_aes_decrypt($userData['encrypted_email'], $userData['encryption_data']);
$emailAtLocation = strpos($userEmail, '@');
$userEmail = substr($userEmail, 0, $emailAtLocation);
$userTimeZone = $userData['time_zone'];
$formattedTime = formattedTime('@' . time() . '', $userTimeZone, FALSE, TRUE);

if (!$userData || !isset($_GET['dataSource'])) {
    exit();
}

function outputClassificationDataToCSV($DBH, $annotationsArray, $targetProjectId, $clientCSVFileName) {

    foreach ($DBH->query("SELECT * FROM crowd_types") as $crowdType) {
        $crowdTypeArray[$crowdType['crowd_type_id']] = $crowdType['crowd_type_name'];
    }
    $crowdTypeArray[0] = "Other Crowd Type";

    $tasksQuery = "SELECT task_id "
            . "FROM task_metadata "
            . "WHERE project_id = $targetProjectId "
            . "ORDER BY order_in_project";
    $tasksResult = $DBH->query($tasksQuery);
    $tasks = $tasksResult->fetchAll(PDO::FETCH_ASSOC);

    $groupsQuery = "SELECT tgm.tag_group_id, tgm.contains_groups, tgm.is_enabled, tc.task_id, tc.order_in_task "
            . "FROM tag_group_metadata tgm "
            . "RIGHT JOIN task_contents tc ON tc.tag_group_id = tgm.tag_group_id "
            . "WHERE tgm.project_id = $targetProjectId AND tgm.is_enabled = 1 "
            . "ORDER BY tc.task_id, tc.order_in_task";
    $groupsResult = $DBH->query($groupsQuery);
    $groups = $groupsResult->fetchAll(PDO::FETCH_ASSOC);

    $csvRowArray = array(
        'Image ID',
        'Latitude',
        'Longitude',
        'File Name',
        'User ID',
        'User Name',
        'Crowd Type',
        'Other Crowd Type',
        'Completion Time'
    );
    $tagIdMap = array();
    foreach ($tasks as $task) {
        $taskId = $task['task_id'];
        foreach ($groups as $group) {
            if ($group['task_id'] == $taskId) {
                $groupId = $group['tag_group_id'];
                if ($group['contains_groups'] == 0) {
                    $tagQuery = "SELECT t.tag_id, t.name, t.is_comment_box "
                            . "FROM tag_group_contents tgc "
                            . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                            . "WHERE tgc.tag_group_id = $groupId AND t.is_enabled = 1 "
                            . "ORDER BY tgc.order_in_group";
                    foreach ($DBH->query($tagQuery) as $tag) {
                        ;
                        $csvRowArray[] = $tag['name'];
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
                    foreach ($DBH->query($nestedGroupQuery) as $nestedGroup) {
                        $nestedGroupId = $nestedGroup['tag_id'];
                        $nestedTagQuery = "SELECT t.tag_id, t.name, t.is_comment_box FROM tag_group_contents tgc "
                                . "LEFT JOIN tags t ON tgc.tag_id = t.tag_id "
                                . "WHERE tgc.tag_group_id = $nestedGroupId AND t.is_enabled = 1 "
                                . "ORDER BY tgc.order_in_group";
                        foreach ($DBH->query($nestedTagQuery) as $tag) {
                            $csvRowArray[] = $tag['name'];
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
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $clientCSVFileName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $csvRowArray);
    foreach ($annotationsArray as $annotation) {
        if (count($annotation['tags']) == 0) {
            continue;
        }

        $unencryptedEmail = mysql_aes_decrypt($annotation['encrypted_email'], $annotation['encryption_data']);
        $crowdType = $crowdTypeArray[$annotation['crowd_type']];

        $csvRowArray = array(
            $annotation['image_id'],
            $annotation['latitude'],
            $annotation['longitude'],
            $annotation['full_url'],
            $annotation['user_id'],
            $unencryptedEmail,
            $crowdType,
            $annotation['other_crowd_type'],
            $annotation['completion_time']);

        foreach ($tagIdMap as $targetTag) {
            $cellContent = FALSE;

            if (isset($annotation['tags'][$targetTag['id']])) {
                if ($targetTag['isComment'] == 0) {
                    $cellContent = 1;
                } else {
                    $cellContent = $annotation['tags'][$targetTag['id']];
                }
            } else {
                if ($targetTag['isComment'] == 1) {
                    $cellContent = '';
                }
            }

            if ($cellContent !== FALSE) {
                $csvRowArray[] = $cellContent;
            } else {
                $csvRowArray[] = '0';
            }
        }
        fputcsv($fp, $csvRowArray);
    }
    fclose($fp);
    exit;
}

$csvFile = '';
$targetProjectMetadata = FALSE;
$targetUserMetadata = FALSE;
$targetProjectMetadata = FALSE;

if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
        $targetProjectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
        if ($targetProjectMetadata) {
            $queryProjectParams['targetProjectId'] = $targetProjectId;
            $queryBothParams['targetProjectId'] = $targetProjectId;
            $targetProjectName = $targetProjectMetadata['name'];
        }
    }
}
if (!$targetProjectMetadata) {
    unset($targetProjectId);
    $queryProjectParams = array();
}

if (isset($_GET['targetUserId'])) {
    settype($_GET['targetUserId'], 'integer');
    if (!empty($_GET['targetUserId'])) {
        $targetUserId = $_GET['targetUserId'];
        $targetUserMetadata = retrieve_entity_metadata($DBH, $targetUserId, 'users');
        if ($targetUserMetadata) {
            $targetUserEMail = mysql_aes_decrypt($targetUserMetadata['encrypted_email'], $targetUserMetadata['encryption_data']);
            $emailAtLocation = strpos($targetUserEMail, '@');
            $targetUserEMail = substr($targetUserEMail, 0, $emailAtLocation);
            $queryUserParams['targetUserId'] = $targetUserId;
            $queryBothParams['targetUserId'] = $targetUserId;
        }
    }
}
if (!$targetUserMetadata) {
    $queryUserParams = array();
    unset($targetUserId);
}

if (!isset($queryBothParams)) {
    $queryBothParams = array();
}

if (isset($_GET['targetPhotoId'])) {
    settype($_GET['targetPhotoId'], 'integer');
    if (!empty($_GET['targetPhotoId'])) {
        $targetPhotoId = $_GET['targetPhotoId'];
        $targetPhotoMetadata = retrieve_entity_metadata($DBH, $targetPhotoId, 'image');
        if ($targetPhotoMetadata) {
            $queryPhotoParams['targetPhotoId'] = $targetPhotoId;
        }
    }
}
if (!$targetPhotoMetadata) {
    unset($targetPhotoId);
    $queryPhotoParams = array();
}

switch ($_GET['dataSource']) {
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

    case 'gisUsers':
        break;
        //If no project supplied then exit.
        if (!$targetProjectMetadata) {
            break;
        }
        ini_set('memory_limit', '128M');

        $annotationsArray = array();


        $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' All Classifications For GIS Users In ' . $targetProjectName . '.csv';

        $gisUserEmailArray = array(

        );
        for ($i = 0; $i < count($gisUserEmailArray); $i++) {
            $gisUserEmailArray[$i] = strtolower($gisUserEmailArray[$i]);
        }

        $gisUserIdArray = array();

        $userArray = $DBH->query("SELECT user_id, encrypted_email, encryption_data FROM users")->fetchAll(PDO::FETCH_ASSOC);

        for ($i = 0; $i < count($userArray); $i++) {
            $userArray[$i]['email'] = mysql_aes_decrypt($userArray[$i]['encrypted_email'], $userArray[$i]['encryption_data']);
            if ($key = array_search($userArray[$i]['email'], $gisUserEmailArray)) {
                $gisUserIdArray[] = $userArray[$i]['user_id'];
                unset($gisUserEmailArray[$key]);
            }
        }

        $gisUserIdWhereInString = where_in_string_builder($gisUserIdArray);

        foreach ($DBH->query("SELECT tag_id FROM tags WHERE project_id = {$targetProjectMetadata['project_id']}") as $tag) {
            $projectTagArray[] = $tag['tag_id'];
        }
        $tagIdWhereInString = where_in_string_builder($projectTagArray);




        $dataExportQuery = "SELECT a.image_id, a.annotation_id, a.user_id, i.latitude, i.longitude, i.full_url, u.encrypted_email, u.encryption_data, u.crowd_type, u.other_crowd_type, if (annotation_completed_under_revision = 0, initial_session_end_time, last_revision_time) as completion_time "
                . "FROM annotations a "
                . "LEFT JOIN users u ON a.user_id = u.user_id "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "WHERE a.annotation_completed = 1 AND a.project_id = $targetProjectId AND a.user_id IN ($gisUserIdWhereInString)";

        foreach ($DBH->query($dataExportQuery, PDO::FETCH_ASSOC) as $row) {
            $annotationsArray[$row['annotation_id']] = $row;
            $annotationsArray[$row['annotation_id']]['tags'] = array();
        }

        $tagSelectionQuery = "SELECT annotation_id, tag_id "
                . "FROM annotation_selections "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagSelectionQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = 1;
            }
        }

        $tagCommentQuery = "SELECT annotation_id, tag_id, comment "
                . "FROM annotation_comments "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagCommentQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = $row['comment'];
            }
        }

        outputClassificationDataToCSV($DBH, $annotationsArray, $targetProjectId, $clientCSVFileName);
        break;

    case 'allUsers':
        $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' All Users in iCoast.csv';
        $allUserQuery = "SELECT u.user_id, u.encrypted_email, u.encryption_data, ct.crowd_type_name, u.other_crowd_type, u.affiliation, u.account_created_on, u.last_logged_in_on, COUNT(a.annotation_id) AS annotation_count "
                . "FROM users u "
                . "LEFT JOIN crowd_types ct ON u.crowd_type = ct.crowd_type_id "
                . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 1 "
                . "GROUP BY u.user_id "
                . "ORDER BY u.masked_email";
        $queryUserParams = array();
        run_prepared_query($DBH, $allUserQuery, $queryUserParams);
        $csvArray[0] = array(
            'User Name',
            'User Id',
            'Crowd Type',
            'Other Crowd Type',
            'Affiliation',
            'Account Created On',
            'Last Activity On',
            'Complete Annotations'
        );
        $csvArrayKey = 0;
        foreach ($DBH->query($allUserQuery) as $user) {
            $csvArrayKey ++;
            $csvArray[$csvArrayKey] = array(
                mysql_aes_decrypt($user['encrypted_email'], $user['encryption_data']),
                $user['user_id'],
                $user['crowd_type_name'],
                $user['other_crowd_type'],
                $user['affiliation'],
                $user['account_created_on'],
                $user['last_logged_in_on'],
                $user['annotation_count']
            );
        }
        break;
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
    case 'allClassifications':
        //If no project supplied then exit.
        if (!$targetProjectMetadata) {
            break;
        }
        ini_set('memory_limit', '128M');

        $annotationsArray = array();

        if ($targetUserMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' All Classifications For User ' . $targetUserEMail . ' In ' . $targetProjectName . '.csv';
        } else {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' All Classifications In ' . $targetProjectName . '.csv';
        }

        foreach ($DBH->query("SELECT tag_id FROM tags WHERE project_id = {$targetProjectMetadata['project_id']}") as $tag) {
            $projectTagArray[] = $tag['tag_id'];
        }
        $tagIdWhereInString = where_in_string_builder($projectTagArray);


        $dataExportQuery = "SELECT a.image_id, a.annotation_id, a.user_id, i.latitude, i.longitude, i.full_url, u.encrypted_email, u.encryption_data, u.crowd_type, u.other_crowd_type, if (annotation_completed_under_revision = 0, initial_session_end_time, last_revision_time) as completion_time "
                . "FROM annotations a "
                . "LEFT JOIN users u ON a.user_id = u.user_id "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "WHERE a.annotation_completed = 1 AND a.project_id = $targetProjectId";
        if ($targetUserMetadata) {
            $dataExportQuery .= " AND u.user_id = $targetUserId";
        }

        foreach ($DBH->query($dataExportQuery, PDO::FETCH_ASSOC) as $row) {
            $annotationsArray[$row['annotation_id']] = $row;
            $annotationsArray[$row['annotation_id']]['tags'] = array();
        }

        $tagSelectionQuery = "SELECT annotation_id, tag_id "
                . "FROM annotation_selections "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagSelectionQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = 1;
            }
        }

        $tagCommentQuery = "SELECT annotation_id, tag_id, comment "
                . "FROM annotation_comments "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagCommentQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = $row['comment'];
            }
        }

        outputClassificationDataToCSV($DBH, $annotationsArray, $targetProjectId, $clientCSVFileName);
        break;
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
    case 'imageGroupClassifications':

        if (!$targetProjectMetadata) {
            break;
        }

        ini_set('memory_limit', '128M');
        $annotationsArray = array();

        if (isset($_GET['dataSubset'])) {
            switch ($_GET['dataSubset']) {
                case 'allProjectClassificationsLessImageGroupsByLinkedUserGroups':
                case 'imageGroupClassificationsByTargetedUserGroups':
                case 'imageGroupClassificationsByNonTargetedUserGroups':
                case 'imageGroupClassificationsByAllUsers':
                    $dataSubset = $_GET['dataSubset'];
                    break;
                default:
                    $dataSubset = null;
            }
        }

        $imageGroupIdArray = array();

        if (isset($_GET['targetImageGroupId'])) {
            settype($_GET['targetImageGroupId'], 'integer');
            if (!empty($_GET['targetImageGroupId'])) {
                $targetImageGroupId = $_GET['targetImageGroupId'];
                $targetImageGroupMetadata = retrieve_entity_metadata($DBH, $targetImageGroupId, 'imageGroups');
                if ($targetImageGroupMetadata) {
                    $imageGroupIdArray[] = $targetImageGroupId;
                    $targetImageGroupName = $targetImageGroupMetadata['name'];
                }
            }
        }
        if (!$targetImageGroupMetadata) {
            unset($targetImageGroupId);
            $imageGroupsInProjectQuery = "SELECT image_group_id FROM image_group_metadata WHERE project_id = $targetProjectId";
            foreach ($DBH->query($imageGroupsInProjectQuery) as $imageGroup) {
                $imageGroupIdArray[] = $imageGroup['image_group_id'];
            }
        }

        if ($targetImageGroupMetadata) {
            if ($dataSubset == 'allProjectClassificationsLessImageGroupsByLinkedUserGroups') {
                $clientCSVFileName .= 'All Classifications In The ' . $targetProjectName . ' Project Except Those In Image Group ' . $targetImageGroupName . ' Completed By Targeted Users.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByTargetedUserGroups') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' All Classifications By Targeted Users For Image Group ' . $targetImageGroupName . ' In ' . $targetProjectName . '.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByNonTargetedUserGroups') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' All Classifications By Non-Targeted Users For Image Group ' . $targetImageGroupName . ' In ' . $targetProjectName . '.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByAllUsers') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' Classifications By All iCoast Users For Image Group ' . $targetImageGroupName . ' In ' . $targetProjectName . '.csv';
            }
        } else {
            if ($dataSubset == 'allProjectClassificationsLessImageGroupsByLinkedUserGroups') {
                $clientCSVFileName .= 'All Classifications In The ' . $targetProjectName . ' Project Except Those In Image Group ' . $targetImageGroupName . ' Completed By Targeted Users.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByTargetedUserGroups') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' Classifications By Targeted Users For All Image Groups In ' . $targetProjectName . '.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByNonTargetedUserGroups') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' Classifications By Non-Targeted Users For All Image Groups In ' . $targetProjectName . '.csv';
            } else if ($dataSubset == 'imageGroupClassificationsByAllUsers') {
                $clientCSVFileName .= $userEmail . ' ' . $formattedTime . ' All Classifications For All Image Groups In ' . $targetProjectName . '.csv';
            }
        }

        foreach ($DBH->query("SELECT * FROM crowd_types") as $crowdType) {
            $crowdTypeArray[$crowdType['crowd_type_id']] = $crowdType['crowd_type_name'];
        }
        $crowdTypeArray[0] = "Other Crowd Type";

        foreach ($DBH->query("SELECT tag_id FROM tags WHERE project_id = {$targetProjectMetadata['project_id']}") as $tag) {
            $projectTagArray[] = $tag['tag_id'];
        }
        $tagIdWhereInString = where_in_string_builder($projectTagArray);

        $imageGroupImagesUsersArray = array();
        foreach ($imageGroupIdArray as $imageGroupId) {
            $imageGroupImagesUsersArray[$imageGroupId] = array();
            $imageGroupMetadataQuery = "SELECT * FROM image_groups WHERE image_group_id = $imageGroupId";
            $imageGroupMetadataResult = $DBH->query($imageGroupMetadataQuery);
            $imageGroupMetadata = $imageGroupMetadataResult->fetch(PDO::FETCH_ASSOC);
            $startImageId = $imageGroupMetadata['image_id'];
            $imageCount = $imageGroupMetadata['group_range'];
            $incrementor = $imageGroupMetadata['show_every_nth_image'];
            $imageIdWhereInArray = array();
            for ($i = 0; $i < $imageCount; $i++) {
                $imageGroupImagesUsersArray[$imageGroupId]['imageIds'][] = $startImageId + ($incrementor * $i);
            }

            $userGroupIdsArray = array();
            $userGroupIdsQuery = "SELECT user_group_id FROM user_group_assignments WHERE image_group_id = $imageGroupId";
            foreach ($DBH->query($userGroupIdsQuery) as $userGroupId) {
                $userGroupIdsArray[] = $userGroupId['user_group_id'];
            }
            $whereInUsersGroupString = where_in_string_builder($userGroupIdsArray);

            $usersInUserGroupQuery = "SELECT user_id FROM user_groups WHERE user_group_id IN ($whereInUsersGroupString)";
            foreach ($DBH->query($usersInUserGroupQuery) as $user) {
                $imageGroupImagesUsersArray[$imageGroupId]['userIds'][] = $user['user_id'];
            }
        }

        $dataExportQuery = "SELECT a.image_id, a.annotation_id, a.user_id, i.latitude, i.longitude, i.full_url, u.encrypted_email, u.encryption_data, u.crowd_type, u.other_crowd_type, if (a.annotation_completed_under_revision = 0, a.initial_session_end_time, a.last_revision_time) as completion_time "
                . "FROM annotations a "
                . "LEFT JOIN users u ON a.user_id = u.user_id "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "WHERE ";
        if ($dataSubset == 'allProjectClassificationsLessImageGroupsByLinkedUserGroups') { // All Classifications Less Classifications Within Image Groups done by Image Group Users
            $dataExportQuery .= "NOT ";
        }
        $dataExportQuery .= "(";
        foreach ($imageGroupImagesUsersArray as $imageGroupData) {
            $whereInUsersString = where_in_string_builder($imageGroupData['userIds']);
            $imageIdWhereInString = where_in_string_builder($imageGroupData['imageIds']);
            $dataExportQuery .= " (a.image_id IN ($imageIdWhereInString) ";
            if ($dataSubset == 'imageGroupClassificationsByTargetedUserGroups' || $dataSubset == 'allProjectClassificationsLessImageGroupsByLinkedUserGroups') {
                $dataExportQuery .= "AND a.user_id IN ($whereInUsersString)";
            }
            if ($dataSubset == 'imageGroupClassificationsByNonTargetedUserGroups') { // Only classifications within Image Groups by users who aren't members of a linked User Group
                $dataExportQuery .= "AND NOT a.user_id IN ($whereInUsersString)";
            }
//          If neither of the above IF statements are executed then the data returned is all classifications in the specified image groups by all users.
            $dataExportQuery .= ") OR";
        }
        $dataExportQuery = substr($dataExportQuery, 0, -3);
        $dataExportQuery .= ") AND a.annotation_completed = 1 AND a.project_id = $targetProjectId";

        foreach ($DBH->query($dataExportQuery) as $row) {
            $annotationsArray[$row['annotation_id']] = $row;
            $annotationsArray[$row['annotation_id']]['tags'] = array();
        }


        $tagSelectionQuery = "SELECT annotation_id, tag_id "
                . "FROM annotation_selections "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagSelectionQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = 1;
            }
        }



        $tagCommentQuery = "SELECT annotation_id, tag_id, comment "
                . "FROM annotation_comments "
                . "WHERE tag_id IN ($tagIdWhereInString)";
        foreach ($DBH->query($tagCommentQuery) as $row) {
            if (array_key_exists($row['annotation_id'], $annotationsArray)) {
                $annotationsArray[$row['annotation_id']]['tags'][$row['tag_id']] = $row['comment'];
            }
        }

        outputClassificationDataToCSV($DBH, $annotationsArray, $targetProjectId, $clientCSVFileName);
        break;
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
    case 'classificationOverviewByUser':



        $usersInTable = array();
        $userClassificationData = array();
        if (!$targetProjectMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' User Classification Summary For iCoast.csv';
        } else {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' User Classification Summary For ' . $targetProjectName . '.csv';
        }


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// All Classifications Query
        $classificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS classification_count, '
                . 'u.user_id, '
                . 'u.encrypted_email, '
                . 'u.encryption_data '
                . 'FROM users u '
                . "LEFT JOIN annotations a ON u.user_id = a.user_id ";
        if ($targetProjectMetadata) {
            $classificationsPerUserQuery .= 'AND a.project_id = :targetProjectId ';
        }
        $classificationsPerUserQuery .= 'GROUP BY u.user_id '
                . 'ORDER BY classification_count DESC, u.masked_email ';
        $classificationsPerUserResult = run_prepared_query($DBH, $classificationsPerUserQuery, $queryProjectParams);
        $classificationsPerUser = $classificationsPerUserResult->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classificationsPerUser as $userClassifications) {
            if ($userClassifications['classification_count'] > 0) {
                $usersInTable[] = $userClassifications['user_id'];
                $userClassificationData[$userClassifications['user_id']] = array(
                    'user_id' => $userClassifications['user_id'],
                    'account' => mysql_aes_decrypt($userClassifications['encrypted_email'], $userClassifications['encryption_data']),
                    'classification_count' => $userClassifications['classification_count']
                );
            }
        }

        $whereInUsersInTable = where_in_string_builder($usersInTable);






//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Complete Classifications Query
        $completeClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS complete_count, '
                . 'u.user_id '
                . 'FROM users u '
                . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 1 ";
        if ($targetProjectMetadata) {
            $completeClassificationsPerUserQuery .= 'AND a.project_id = :targetProjectId ';
        }
        $completeClassificationsPerUserQuery .= "WHERE u.user_id IN ($whereInUsersInTable) "
                . 'GROUP BY u.user_id ';
        $completeClassificationsPerUserResult = run_prepared_query($DBH, $completeClassificationsPerUserQuery, $queryProjectParams);
        $completeClassificationsPerUser = $completeClassificationsPerUserResult->fetchAll(PDO::FETCH_ASSOC);

        foreach ($completeClassificationsPerUser as $userCompleteClassifications) {
            $userClassificationData[$userCompleteClassifications['user_id']]['complete_count'] = $userCompleteClassifications['complete_count'];
        }









//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Incomplete Classifications Query
        $incompleteClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS incomplete_count, '
                . 'u.user_id '
                . 'FROM users u '
                . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 0 AND a.user_match_id IS NOT NULL ";
        if ($targetProjectMetadata) {
            $incompleteClassificationsPerUserQuery .= 'AND a.project_id = :targetProjectId ';
        }
        $incompleteClassificationsPerUserQuery .= "WHERE u.user_id IN ($whereInUsersInTable) "
                . 'GROUP BY u.user_id ';
        $incompleteClassificationsPerUserResult = run_prepared_query($DBH, $incompleteClassificationsPerUserQuery, $queryProjectParams);
        $incompleteClassificationsPerUser = $incompleteClassificationsPerUserResult->fetchAll(PDO::FETCH_ASSOC);

        foreach ($incompleteClassificationsPerUser as $userIncompleteClassifications) {
            $userClassificationData[$userIncompleteClassifications['user_id']]['incomplete_count'] = $userIncompleteClassifications['incomplete_count'];
        }






//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Unstarted Classifications Query
        $unstartedClassificationsPerUserQuery = 'SELECT COUNT(a.annotation_id) AS unstarted_count, '
                . 'u.user_id '
                . 'FROM users u '
                . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.user_match_id IS NULL ";
        if ($targetProjectMetadata) {
            $unstartedClassificationsPerUserQuery .= 'AND a.project_id = :targetProjectId ';
        }
        $unstartedClassificationsPerUserQuery .= "WHERE u.user_id IN ($whereInUsersInTable) "
                . 'GROUP BY u.user_id ';
        $unstartedClassificationsPerUserResult = run_prepared_query($DBH, $unstartedClassificationsPerUserQuery, $queryProjectParams);
        $unstartedClassificationsPerUser = $unstartedClassificationsPerUserResult->fetchAll(PDO::FETCH_ASSOC);

        foreach ($unstartedClassificationsPerUser as $userUnstartedClassifications) {
            $userClassificationData[$userUnstartedClassifications['user_id']]['unstarted_count'] = $userUnstartedClassifications['unstarted_count'];
        }





//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Unstarted Classifications Query
        $photosPerUserQuery = 'SELECT COUNT(DISTINCT a.image_id) AS photo_count, '
                . 'u.user_id '
                . 'FROM users u '
                . "LEFT JOIN annotations a ON u.user_id = a.user_id AND a.annotation_completed = 1 ";
        if ($targetProjectMetadata) {
            $photosPerUserQuery .= 'AND a.project_id = :targetProjectId ';
        }
        $photosPerUserQuery .= "WHERE u.user_id IN ($whereInUsersInTable) "
                . 'GROUP BY u.user_id ';
        $photosPerUserResult = run_prepared_query($DBH, $photosPerUserQuery, $queryProjectParams);
        $photosPerUser = $photosPerUserResult->fetchAll(PDO::FETCH_ASSOC);

        foreach ($photosPerUser as $userPhotos) {
            $userClassificationData[$userPhotos['user_id']]['photo_count'] = $userPhotos['photo_count'];
        }

        $csvArray[0] = array('User Id',
            'User Name',
            'Total Classifications',
            'Complete Classifications',
            'Incomplete Classifications',
            'Unstarted Classifications',
            'Distinct Photos With Complete Classifications'
        );

        $csvArrayKey = 0;
        foreach ($userClassificationData as $userClassificationStats) {
            $csvArrayKey ++;
            foreach ($userClassificationStats as $value) {
                $csvArray[$csvArrayKey][] = $value;
            }
        }
        break;
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
    case 'tagSelectionFrequencies':

        if ($targetProjectMetadata) {

            if ($targetPhotoMetadata) {

                $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Tag Selection Frequencies For Photo ' . $targetPhotoId . ' in ' . $targetProjectName . '.csv';


                $annotationCountQuery = "SELECT annotation_id "
                        . "FROM annotations "
                        . "WHERE image_id = $targetPhotoId AND annotation_completed = 1 AND project_id = $targetProjectId";
                $params = array();
                $photoAnnotationsResult = run_prepared_query($DBH, $annotationCountQuery, $params);
                while ($annotation = $photoAnnotationsResult->fetch(PDO::FETCH_ASSOC)) {
                    $annotationWhereInArray[] = $annotation['annotation_id'];
                }
                $annotationWhereInString = where_in_string_builder($annotationWhereInArray);

                $photoTagFrequency = array();

                $tagSelectionFrequencyQuery = "SELECT tag_id, COUNT(annotation_id) as frequency "
                        . "FROM annotation_selections "
                        . "WHERE annotation_id IN ($annotationWhereInString) "
                        . "GROUP BY tag_id";
                $tagSelectionFrequencyParams = array();
                $tagSelectionFrequencyResults = run_prepared_query($DBH, $tagSelectionFrequencyQuery, $tagSelectionFrequencyParams);
                while ($individualTagSelectionFrequency = $tagSelectionFrequencyResults->fetch(PDO::FETCH_ASSOC)) {
                    $photoTagFrequency[$individualTagSelectionFrequency['tag_id']] = $individualTagSelectionFrequency['frequency'];
                }


                $tagFrequency = array();
                $tagBreakdown = '';

                $tagOrderInProjectQuery = '(SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
                    t.is_enabled AS tag_enabled,
                    null AS lower_parent_id,
                    null AS lower_parent_name,
                    null AS order_in_lower_parent,
                    null AS lower_parent_enabled,
                    tgcUpper.order_in_group AS order_in_upper_parent,
                    tgcUpper.tag_group_id AS upper_parent_id,
                    tgmUpper.name AS upper_parent_name,
                    tgmUpper.is_enabled AS upper_parent_enabled,
                    tc.order_in_task AS upper_group_order_in_task,
                    tc.task_id,
                    tm.order_in_project AS task_order_in_project,
                    tm.is_enabled AS task_enabled,
                    tm.name AS task_name,
                    tm.project_id
                    FROM tags t
                    LEFT JOIN tag_group_contents tgcUpper ON t.tag_id = tgcUpper.tag_id
                    LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
                    LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
                    LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
                    LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
                    LEFT JOIN annotations a ON anns.annotation_id = a.annotation_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 0
                    GROUP BY t.tag_id)

                    UNION

                    (SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
                    t.is_enabled AS tag_enabled,
                    tgcLower.tag_group_id AS lower_parent_id,
                    tgmLower.name AS lower_parent_name,
                    tgcLower.order_in_group AS order_in_lower_parent,
                    tgmLower.is_enabled AS lower_parent_enabled,
                    tgcUpper.order_in_group AS order_in_upper_parent,
                    tgcUpper.tag_group_id AS upper_parent_id,
                    tgmUpper.name AS upper_parent_name,
                    tgmUpper.is_enabled AS upper_parent_enabled,
                    tc.order_in_task AS upper_group_order_in_task,
                    tc.task_id,
                    tm.order_in_project AS task_order_in_project,
                    tm.is_enabled AS task_enabled,
                    tm.name AS task_name,
                    tm.project_id

                    FROM tags t
                    LEFT JOIN tag_group_contents tgcLower ON t.tag_id = tgcLower.tag_id
                    LEFT JOIN tag_group_contents tgcUpper ON tgcLower.tag_group_id = tgcUpper.tag_id
                    LEFT JOIN tag_group_metadata tgmLower ON tgcLower.tag_group_id = tgmLower.tag_group_id
                    LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
                    LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
                    LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
                    LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
                    LEFT JOIN annotations a ON anns.annotation_id = a.annotation_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 1
                    GROUP BY t.tag_id)

                    ORDER BY task_order_in_project, upper_group_order_in_task, order_in_upper_parent, order_in_lower_parent';
                $tagOrderInProjectResult = run_prepared_query($DBH, $tagOrderInProjectQuery, $queryProjectParams);
                while ($individualTag = $tagOrderInProjectResult->fetch(PDO::FETCH_ASSOC)) {
                    if (array_key_exists($individualTag['tag_id'], $photoTagFrequency)) {
                        $individualTag['frequency'] = $photoTagFrequency[$individualTag['tag_id']];
                    } else {
                        $individualTag['frequency'] = 0;
                    }
                    $tagFrequency[] = $individualTag;
                }
            } else {

                $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Tag Selection Frequencies For ' . $targetProjectName . '.csv';
                $tagFrequencyInProjectQuery = '(SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
                    COUNT(anns.tag_id) AS frequency,
                    t.is_enabled AS tag_enabled,
                    null AS lower_parent_id,
                    null AS lower_parent_name,
                    null AS order_in_lower_parent,
                    null AS lower_parent_enabled,
                    tgcUpper.order_in_group AS order_in_upper_parent,
                    tgcUpper.tag_group_id AS upper_parent_id,
                    tgmUpper.name AS upper_parent_name,
                    tgmUpper.is_enabled AS upper_parent_enabled,
                    tc.order_in_task AS upper_group_order_in_task,
                    tc.task_id,
                    tm.order_in_project AS task_order_in_project,
                    tm.is_enabled AS task_enabled,
                    tm.name AS task_name,
                    tm.project_id
                    FROM tags t
                    LEFT JOIN tag_group_contents tgcUpper ON t.tag_id = tgcUpper.tag_id
                    LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
                    LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
                    LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
                    LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 0
                    GROUP BY t.tag_id)

                    UNION

                    (SELECT
                    t.tag_id AS tag_id,
                    t.name AS tag_name,
                    t.description AS tag_description,
                    COUNT(anns.tag_id) AS frequency,
                    t.is_enabled AS tag_enabled,
                    tgcLower.tag_group_id AS lower_parent_id,
                    tgmLower.name AS lower_parent_name,
                    tgcLower.order_in_group AS order_in_lower_parent,
                    tgmLower.is_enabled AS lower_parent_enabled,
                    tgcUpper.order_in_group AS order_in_upper_parent,
                    tgcUpper.tag_group_id AS upper_parent_id,
                    tgmUpper.name AS upper_parent_name,
                    tgmUpper.is_enabled AS upper_parent_enabled,
                    tc.order_in_task AS upper_group_order_in_task,
                    tc.task_id,
                    tm.order_in_project AS task_order_in_project,
                    tm.is_enabled AS task_enabled,
                    tm.name AS task_name,
                    tm.project_id

                    FROM tags t
                    LEFT JOIN tag_group_contents tgcLower ON t.tag_id = tgcLower.tag_id
                    LEFT JOIN tag_group_contents tgcUpper ON tgcLower.tag_group_id = tgcUpper.tag_id
                    LEFT JOIN tag_group_metadata tgmLower ON tgcLower.tag_group_id = tgmLower.tag_group_id
                    LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
                    LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
                    LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
                    LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
                    WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 1
                    GROUP BY t.tag_id)

                    ORDER BY task_order_in_project, upper_group_order_in_task, order_in_upper_parent, order_in_lower_parent';
                $tagFrequencyInProjectResult = run_prepared_query($DBH, $tagFrequencyInProjectQuery, $queryProjectParams);
                $tagFrequency = $tagFrequencyInProjectResult->fetchAll(PDO::FETCH_ASSOC);
            }


            $csvArray[0] = array(
                'Task Name',
                'Task Enabled',
                'Group Name',
                'Group Enabled',
                'Nested Group Name',
                'Nested Group Enabled',
                'Tag Name',
                'Tag Enabled',
                'Selection Frequency'
            );
            $csvArrayKey = 0;
            foreach ($tagFrequency as $tagStatistics) {
                $csvArrayKey ++;
                $csvArray[$csvArrayKey] = array(
                    $tagStatistics['task_name'],
                    $tagStatistics['task_enabled'],
                    $tagStatistics['upper_parent_name'],
                    $tagStatistics['upper_parent_enabled'],
                    $tagStatistics['lower_parent_name'],
                    $tagStatistics['lower_parent_enabled'],
                    $tagStatistics['tag_name'],
                    $tagStatistics['tag_enabled'],
                    $tagStatistics['frequency']
                );
            }
        }
        break;



//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
    case 'timeChart':

        if (!$targetProjectMetadata && !$targetUserMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Classification Time Data For All iCoast Users And Projects.csv';
        } else if ($targetProjectMetadata && $targetUserMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Classification Time Data For User ' . $targetUserEMail . ' In ' . $targetProjectName . ' Project.csv';
        } else if ($targetProjectMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Classification Time Data For All iCoast Users In The' . $targetProjectName . ' Project.csv';
        } else if ($targetUserMetadata) {
            $clientCSVFileName = $userEmail . ' ' . $formattedTime . ' Classification Time Data For User ' . $targetUserEMail . ' In All iCoast Projects.csv';
        }

        if (isset($_GET['upperTimeLimit'])) {
            settype($_GET['upperTimeLimit'], 'integer');
            if (!empty($_GET['upperTimeLimit'])) {
                $upperTimeLimitMins = $_GET['upperTimeLimit'];
            }
        }
        if (!isset($upperTimeLimitMins)) {
            $upperTimeLimitMins = 0;
        }
        if ($upperTimeLimitMins > 0 && $upperTimeLimitMins <= 100) {
            $maxX = $upperTimeLimitMins;
        } else {
            $maxX = 100;
        }
//        print '<pre>';
//        print_r($_GET);
//        print '</pre>';
//        print $upperTimeLimitMins;
//        print $maxX;

        $classificationCount = 0;
        $timeTotal = 0;
        $excessiveTimeCount = 0;
        $nonDisplayableClassificationCount = 0;
        $maxUnrestrictedClassificationTime = 0;
        $longestClassification = 0;
        $shortestClassification = 0;
        $durationFrequencyCount = array();
        $annotationTimeGraphHTML = '';
        $avgTimeParams = array();

        for ($i = 1; $i <= $maxX; $i++) {
            $durationFrequencyCount[$i] = 0;
        }



        $avgTimeQuery = "SELECT initial_session_start_time, initial_session_end_time FROM annotations WHERE annotation_completed = 1 AND annotation_completed_under_revision = 0";
        if ($targetUserMetadata) {
            $avgTimeQuery .= " AND user_id = :userId";
            $avgTimeParams['userId'] = $targetUserId;
        }

        if ($targetProjectMetadata) {
            $avgTimeQuery .= " AND project_id = :projectId";
            $avgTimeParams['projectId'] = $targetProjectId;
        }

        $avgTimeResults = run_prepared_query($DBH, $avgTimeQuery, $avgTimeParams);
        while ($classification = $avgTimeResults->fetch(PDO::FETCH_ASSOC)) {

            $startTime = strtotime($classification['initial_session_start_time']);
            $endTime = strtotime($classification['initial_session_end_time']);
            $timeDelta = $endTime - $startTime;
            if ($timeDelta <= ($maxX * 60)) {
                $durationFrequencyCount[ceil($timeDelta / 60)] ++;
            }
        }

        $csvArray[0] = array(
            'Time In Minutes',
            'Number Of Classifications Completed Within Time');
        for ($i = 1; $i <= $maxX; $i++) {
            $csvArray[$i] = array(
                $i,
                $durationFrequencyCount[$i]
            );
        }


        break;
}

if (isset($csvArray) && count($csvArray) > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $clientCSVFileName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    $fp = fopen('php://output', 'w');
    foreach ($csvArray as $csvRow) {
        fputcsv($fp, $csvRow);
    }
    fclose($fp);
}


