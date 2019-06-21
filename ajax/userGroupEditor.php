<?php
/**
 * Summary Processes ajax requests related to User Group Management
 *
 * Project: iCoast
 * File: userGroupEditor.php
 * Created: 11/15/2016 3:41 PM
 *
 * @author Richard Snell <rsnell@usgs.gov>
 */

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$userData = authenticate_user($DBH, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE);
if (!$userData) {
    exit;
}

$error = false;

switch ($_POST['requestType']) {


    case 'newGroup':
        if (!isset($_POST['projectId']) ||
            !isset($_POST['name']) ||
            !isset($_POST['description'])
        ) {
            $error = 'Missing Post Data';
            break;
        }

        $newGroupData = array();

        $newGroupData['projectId'] = filter_input(INPUT_POST, "projectId", FILTER_VALIDATE_INT);
        $projectData = retrieve_entity_metadata($DBH, $newGroupData['projectId'], 'project');
        if (!$projectData) {
            $error = 'Bad Project Id: ' . $newGroupData['projectId'];
            break;
        }
        if ($projectData['creator'] != $userData['user_id']) {
            $error = 'No Permission To Add A Group To This Project';
            break;
        }

        $newGroupData['name'] = trim($_POST['name']);
        $nameLength = strlen($newGroupData['name']);
        if ($nameLength == 0 || $nameLength > 50) {
            $error = 'Bad Name Length: ' . $nameLength . 'chars (max 50 allowed)';
            break;
        }

        $exitingGroupCheckQuery = <<<MySQL
          SELECT 
            COUNT(*)
          FROM user_group_metadata
          WHERE
            project_id = :projectId AND 
            name = :name
MySQL;
        $existingGroupCheckResult = run_prepared_query($DBH, $exitingGroupCheckQuery, $newGroupData, false);
        $existingGroupCheck = $existingGroupCheckResult->fetchColumn();
        if ($existingGroupCheck) {
            $error = 'Group Already Exists';
            break;
        }

        $newGroupData['description'] = trim($_POST['description']);
        $descriptionLength = strlen($newGroupData['description']);
        if ($descriptionLength > 500) {
            $error = 'Bad Description Length: ' . $descriptionLength;
            break;
        }

        $newGroupQuery = <<<MySQL
          INSERT INTO user_group_metadata
            (project_id, is_enabled, name, description)
          VALUES
            (:projectId, 1, :name, :description)
MySQL;
        $groupId = run_prepared_query($DBH, $newGroupQuery, $newGroupData, true);
        if (!$groupId) {
            $error = 'Database Insert Error';
            break;
        }

        $returnData = array(
            'success' => true,
            'projectId' => $newGroupData['projectId'],
            'groupId' => $groupId,
            'name' => $newGroupData['name'],
            'description' => $newGroupData['description']
        );
        break; // END SWITCH CASE 'newGroup'

    case 'loadUsers':
        if (!isset($_POST['groupId'])) {
            $error = 'Missing Post Data';
            break;
        }

        $userListData['groupId'] = filter_input(INPUT_POST, "groupId", FILTER_VALIDATE_INT);
        $groupData = retrieve_entity_metadata($DBH, $userListData['groupId'], 'userGroup');
        if (!$groupData) {
            $error = 'Bad Group Id: ' . $userListData['groupId'];
            break;
        }
        $projectData = retrieve_entity_metadata($DBH, $groupData['project_id'], 'project');
        if ($projectData['creator'] != $userData['user_id']) {
            $error = 'No Permission To Edit This Project';
            break;
        }

        $userListQuery = <<<MySQL
            SELECT 
                ug.user_id AS userId, 
                u.encrypted_email AS encryptedEmail, 
                u.encryption_data AS encryptionData
            FROM user_groups ug
            LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
            LEFT JOIN users u ON ug.user_id = u.user_id
            WHERE ug.user_group_id = :groupId
MySQL;
        $userListResult = run_prepared_query($DBH, $userListQuery, $userListData);
        $userList = array();
        while ($user = $userListResult->fetch(PDO::FETCH_ASSOC)) {
            $userList[(integer)$user['userId']] = mysql_aes_decrypt($user['encryptedEmail'], $user['encryptionData']);
        }
        $returnData = array(
            'success' => true,
            'users' => $userList
        );

        break;

    case 'editGroup':
        if (!isset($_POST['groupId']) ||
            !isset($_POST['name']) ||
            !isset($_POST['description']) ||
            !isset($_POST['newUsers']) ||
            !isset($_POST['removedUsers'])
        ) {
            $error = 'Missing Post Data';
            break;
        }

        $updateMetadataData =
            array();

        $groupId = $updateMetadataData['groupId'] = filter_input(INPUT_POST, "groupId", FILTER_VALIDATE_INT);
        $userGroupData = retrieve_entity_metadata($DBH, $updateMetadataData['groupId'], 'userGroup');
        if (!$userGroupData) {
            $error = 'Bad Group Id: ' . $updateMetadataData['groupId'];
            break;
        }
        $projectData = retrieve_entity_metadata($DBH, $userGroupData['project_id'], 'project');
        if ($projectData['creator'] != $userData['user_id']) {
            $error = 'No Permission To Edit Groups In This Project';
            break;
        }
        $updateMetadataData['projectId'] = $projectData['project_id'];

        $updateMetadataData['name'] = trim($_POST['name']);
        $nameLength = strlen($updateMetadataData['name']);
        if ($nameLength == 0 || $nameLength > 50) {
            $error = 'Bad Name Length: ' . $nameLength . 'chars (max 50 allowed)';
            break;
        }

        $exitingGroupCheckQuery = <<<MySQL
          SELECT 
            COUNT(*)
          FROM user_group_metadata
          WHERE
            project_id = :projectId AND 
            name = :name AND 
            NOT user_group_id = :groupId
MySQL;
        $existingGroupCheckResult = run_prepared_query($DBH, $exitingGroupCheckQuery, $updateMetadataData, false);
        $existingGroupCheck = $existingGroupCheckResult->fetchColumn();
        if ($existingGroupCheck) {
            $error = 'Group Already Exists';
            break;
        }
        unset($updateMetadataData['projectId']);

        $updateMetadataData['description'] = trim($_POST['description']);
        $descriptionLength = strlen($updateMetadataData['description']);
        if ($descriptionLength > 500) {
            $error = 'Bad Description Length: ' . $descriptionLength;
            break;
        }

        $DBH->exec('Start Transaction');

        if ($updateMetadataData['name'] != $userGroupData['name'] ||
            $updateMetadataData['description'] != $userGroupData['description']) {
            $updateMetadataQuery = <<<MySQL
              UPDATE user_group_metadata
              SET
                name = :name,
                description = :description
              WHERE
                user_group_id = :groupId
MySQL;
            $updateMetadataResult = run_prepared_query($DBH, $updateMetadataQuery, $updateMetadataData);
            if ($updateMetadataResult->rowCount() == 0) {
                $DBH->exec('Rollback');
                $error = 'Group Metadata Update failed.';
                break;
            }
        }

        $newUsers = json_decode($_POST['newUsers']);
        $removedUsers = json_decode($_POST['removedUsers']);
        if (!is_array($newUsers) || !is_array($removedUsers)) {
            $DBH->exec('Rollback');
            $error = 'Invalid User Array(s)';
            break;
        }

        $sanitizedNewUserArray = array();
        if (count($newUsers) > 0) {
            foreach ($newUsers as $newUserId) {
                setType($newUserId, 'integer');
                if (empty($newUserId)) {
                    $DBH->exec('Rollback');
                    $error = 'Non-Numeric Entry In New User Array';
                    break;
                }
                $sanitizedNewUserArray[] = $newUserId;
            }
            $numberOfUsersToAdd = count($sanitizedNewUserArray);
            $userCheckWhereInString = implode(',', $sanitizedNewUserArray);
            $userCheckQuery = <<<MySQL
              SELECT COUNT(*)
              FROM users
              WHERE user_id IN ($userCheckWhereInString)
MySQL;
            $userCheckResult = $DBH->query($userCheckQuery);
            $userCheckResultCount = $userCheckResult->fetchColumn();
            if ($userCheckResultCount != $numberOfUsersToAdd) {
                $DBH->exec('Rollback');
                $error = "One Or More Specified User ID's Were Invalid";
                break;
            }

            $newUserValuesString = '';
            foreach($sanitizedNewUserArray as $sanitizedNewUserId) {
                $newUserValuesString .= "($groupId, $sanitizedNewUserId),";
            }

            $newUserValuesString = rtrim($newUserValuesString, ',');
            $newUsersQuery = <<<MySQL
              INSERT INTO user_groups
                (user_group_id, user_id)
              VALUES
                $newUserValuesString
MySQL;
            $newUsersResult = $DBH->query($newUsersQuery);
            $newUsersNumberOfAffectedRows = $newUsersResult->rowCount();
            if ($newUsersNumberOfAffectedRows != $numberOfUsersToAdd) {
                $DBH->exec('Rollback');
                $error = "Error Adding New Users To Database";
                break;
            }
        }

        $sanitizedRemovedUserArray = array();
        if (count($removedUsers) > 0) {
            foreach ($removedUsers as $removedUserId) {
                setType($removedUserId, 'integer');
                if (empty($removedUserId)) {
                    $DBH->exec('Rollback');
                    $error = 'Non-Numeric Entry In Removed User Array';
                    break;
                }
                $sanitizedRemovedUserArray[] = $removedUserId;
            }
            $numberOfUsersToRemove = count($sanitizedRemovedUserArray);

            $removedUserWhereInString = implode(',', $sanitizedRemovedUserArray);
            $removedUsersQuery = <<<MySQL
              DELETE FROM user_groups
              WHERE
                user_group_id = $groupId AND 
                user_id IN ($removedUserWhereInString)
MySQL;
            $removedUsersResult = $DBH->query($removedUsersQuery);
            $removedUsersNumberOfAffectedRows = $removedUsersResult->rowCount();
            if ($removedUsersNumberOfAffectedRows != $numberOfUsersToRemove) {
                $DBH->exec('Rollback');
                $error = "Error Removing Users From Database";
                break;
            }
        }

        $DBH->exec('Commit');

        $returnData = array(
            'success' => true,
            'groupId' => $groupId,
            'name' => $updateMetadataData['name'],
            'description' => $updateMetadataData['description'],
            'addedUsers' => json_encode($sanitizedNewUserArray),
            'removedUsers' => json_encode($sanitizedRemovedUserArray)
        );


        break;

    case 'deleteGroup':
        if (!isset($_POST['groupId']))
        {
            $error = 'Missing Post Data';
            break;
        }

        $deleteGroupData =
            array();

        $deleteGroupData['groupId'] = filter_input(INPUT_POST, "groupId", FILTER_VALIDATE_INT);
        $userGroupData = retrieve_entity_metadata($DBH, $deleteGroupData['groupId'], 'userGroup');
        if (!$userGroupData) {
            $error = 'Bad Group Id: ' . $deleteGroupData['groupId'];
            break;
        }
        $projectData = retrieve_entity_metadata($DBH, $userGroupData['project_id'], 'project');
        if ($projectData['creator'] != $userData['user_id']) {
            $error = 'No Permission To Edit Groups In This Project';
            break;
        }

        // Delete Users
        $deleteUsersQuery = <<<MySQL
          DELETE FROM user_groups
          WHERE user_group_id = :groupId
MySQL;
        $deleteUsersResult = run_prepared_query($DBH, $deleteUsersQuery, $deleteGroupData);

        $deleteGroupQuery = <<<MySQL
          DELETE FROM user_group_metadata
          WHERE user_group_id = :groupId
MySQL;
        $deleteGroupResult = run_prepared_query($DBH, $deleteGroupQuery, $deleteGroupData);


        $returnData = array(
            'success' => true,
            'groupId' => $deleteGroupData['groupId']
        );
        break;

    default:
        $error = 'Bad Request Type';
        break; // END SWITCH CASE default
} // END SWITCH ($_POST['requestType'])


if ($error) {
    $returnData = array(
        'success' => false,
        'error' => $error,
        'data' => $_POST
    );
}

print json_encode($returnData);