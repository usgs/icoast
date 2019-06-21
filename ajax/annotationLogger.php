<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

function validateUser($DBH, $userId, $authCheckCode) {
    $validUser = TRUE;
    $errorMsg = '';

    $userValidationQuery = "SELECT auth_check_code, is_enabled FROM users WHERE user_id = :userId LIMIT 1";
    $userValidationParams['userId'] = $userId;
    $STH = run_prepared_query($DBH, $userValidationQuery, $userValidationParams);
    $user = $STH->fetch(PDO::FETCH_ASSOC);
//  $userValidationResult = run_database_query($userValidationQuery);
//  $user = $userValidationResult->fetch_assoc();

    if ($user['auth_check_code'] != $authCheckCode) {
        $validUser = FALSE;
        $errorMsg = 'Authentication code mismatch.';
    }

    if ($user['is_enabled'] == 0) {
        $validUser = FALSE;
        $errorMsg = 'User account is disabled' . $user;
    }

    return array('validationResult' => $validUser,
        'errorMsg' => $errorMsg);
}

// file_put_contents('loggerLog.txt', "START\n\r");
// file_put_contents('loggerLog.txt', "Post Variables:" . print_r($_POST, true) . "\n\r", FILE_APPEND);

$annotationSessionId = filter_input(INPUT_POST, 'annotationSessionId');
$authCheckCode = filter_input(INPUT_POST, 'authCheckCode');
$userId = filter_input(INPUT_POST, 'userId', FILTER_VALIDATE_INT);
$projectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT);
$postImageId = filter_input(INPUT_POST, 'postImageId', FILTER_VALIDATE_INT);
$preImageId = filter_input(INPUT_POST, 'preImageId', FILTER_VALIDATE_INT);
$startClassificationFlag = filter_input(INPUT_POST, 'startClassification', FILTER_VALIDATE_BOOLEAN);
$annotationCompleteFlag = filter_input(INPUT_POST, 'annotationComplete', FILTER_VALIDATE_BOOLEAN);

$validUser = validateUser($DBH, $userId, $authCheckCode);
if (!$validUser['validationResult']) {
    // file_put_contents('loggerLog.txt', "Not a valid user. Exit.", FILE_APPEND);
//    echo json_encode(array('result' => 0,
//                           'details' => "Invalid user credentials"
//                     )
//    );
    exit('{"result":false, "details":"Invalid user authentication credentials."}');
}

$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image');
if (!$projectMetadata ||
    !$postImageMetadata ||
    $postImageMetadata && $postImageMetadata['is_globally_disabled'] == 1) {
    exit('{"result":false, "details":"Invalid project, invalid post-event image, or post-event image is globally 
    disabled"}');
}

if ($projectMetadata['is_public'] == 0) {
    // file_put_contents('loggerLog.txt', "Not valid project or post image. Exit.", FILE_APPEND);
    $previewUserQuery = <<<MySQL
		SELECT DISTINCT 
			ugm.project_id
		FROM 
			user_groups ug
		LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
		LEFT JOIN projects p ON ugm.project_id = p.project_id
		WHERE 
			ug.user_id = $userId AND
			ugm.is_enabled = 1 AND
			p.is_complete = 1 
MySQL;
    $userHasPreviewPermission = false;
    $previewUserResult = $DBH->query($previewUserQuery);
    while ($previewProjectId = $previewUserResult->fetchColumn()) {
        if ($projectMetadata['project_id'] == $previewProjectId) {
            $userHasPreviewPermission = true;
        }
    }
    if (!$userHasPreviewPermission)
    {
        exit('{"result":false, "details":"The project is not public and user does not have preview permission."}');
    }
}
// file_put_contents('loggerLog.txt', "Project and Post Image Verified\n\r", FILE_APPEND);
if (isset($preImageId)) {
    $preImageMetadata = retrieve_entity_metadata($DBH, $preImageId, 'image');
    if (!$preImageMetadata ||
            ($preImageMetadata && $preImageMetadata['is_globally_disabled'] == 1)) {
        // file_put_contents('loggerLog.txt', "Not a valid pre image. Exit.", FILE_APPEND);
        exit('{"result":false, "details":"Invalid pre-event image."}');
    }
    // file_put_contents('loggerLog.txt', "Pre Image Verified\n\r", FILE_APPEND);
}

$existingAnnotationQuery = "
    SELECT 
        * 
    FROM 
        annotations 
    WHERE 
        user_id = :userId AND
        project_id = :projectId AND 
        image_id = :postImageId
    ";
$existingAnnotationParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$existingAnnotationResult = run_prepared_query($DBH, $existingAnnotationQuery, $existingAnnotationParams);
$existingAnnotation = $existingAnnotationResult->fetch(PDO::FETCH_ASSOC);
//if ($existingAnnotation) {
//    print "Existing Annotation<br>";
//     file_put_contents('loggerLog.txt', "Existing Annotation: " . print_r($existingAnnotation, true) . "\n\r", FILE_APPEND);
//} else {
//    print "New Annotation<br>";
//     file_put_contents('loggerLog.txt', "No Existing Annotation.\n\r", FILE_APPEND);
//}

if ($startClassificationFlag) {
    // file_put_contents('loggerLog.txt', "Just loaded classification.\n\r", FILE_APPEND);
    if (empty($existingAnnotation)) {
        // file_put_contents('loggerLog.txt', "New Classification.\n\r", FILE_APPEND);
        $insertAnnotationQuery = "
            INSERT INTO 
                annotations 
            (
                initial_session_id, 
                user_id, 
                project_id, 
                image_id, 
                initial_session_start_time
            ) 
            VALUES 
            (
                :annotationSessionId, 
                :userId, 
                :projectId, 
                :postImageId, 
                NOW())
            ";
        $insertAnnotationParams = array(
            'annotationSessionId' => $annotationSessionId,
            'userId' => $userId,
            'projectId' => $projectId,
            'postImageId' => $postImageId
        );
        $insertAnnotationResult = run_prepared_query($DBH, $insertAnnotationQuery, $insertAnnotationParams);
        if ($insertNewAnnotationResult->rowCount() != 1) {
            // file_put_contents('loggerLog.txt', "New annotation insert failed. Exit.", FILE_APPEND);
            exit('{"result":false, "details":"Server failed to insert new annotation into database"}');
        }
    } else { // END if (empty($existingAnnotation))

        // file_put_contents('loggerLog.txt', "Existing Classification\n\r", FILE_APPEND);
        if (
                is_null($existingAnnotation['user_match_id']) &&
                $existingAnnotation['initial_session_id'] != $annotationSessionId
        ) {
            // file_put_contents('loggerLog.txt', "Resetting existing classification.\n\r", FILE_APPEND);
            $updateAnnotationQuery = "
                UPDATE 
                    annotations 
                SET 
                    initial_session_id = :annotationSessionId,
                    initial_session_start_time = NOW()
                WHERE
                    annotation_id = :annotationId";
            $updateAnnotationParams = array(
                'annotationSessionId' => $annotationSessionId,
                'annotationId' => $existingAnnotation['annotation_id']
            );
            $updateAnnotationResult = run_prepared_query($DBH, $updateAnnotationQuery, $updateAnnotationParams);
            if ($updateAnnotationResult->rowCount() != 1) {
                // file_put_contents('loggerLog.txt', "Update of exissting annotaion failed. Exit.", FILE_APPEND);
                exit('{"result":false, "details":"Server failed to update the existing annotation with initial session 
                details"}');
            }
        }
    } // END if (empty($existingAnnotation)) ELSE
} else { // END if ($startClassificationFlag)
    // file_put_contents('loggerLog.txt', "Working through the tasks.\n\r", FILE_APPEND);
    $userDataChange = FALSE;

    if (empty($existingAnnotation)) {
        // file_put_contents('loggerLog.txt', "There should be an existing classification. But there isn't!\n\r", FILE_APPEND);
        exit('{"result":false, "details":"No start flag was detected and no exiting annotation found."}');
    }


    if (
            $existingAnnotation['user_match_id'] != $preImageId ||
            ($existingAnnotation['annotation_completed'] == 0 && $annotationCompleteFlag)
    ) {
        // file_put_contents('loggerLog.txt', "The Pre Image has changed or the annotation needs to be marked as completed.\n\r", FILE_APPEND);
        $userDataChange = TRUE;
    }



    $validTagIdsQueryParam['projectId'] = $projectId;
    $validTagIdsQuery = "
    SELECT 
        tag_id, 
        is_comment_box
    FROM 
        tags
    WHERE
        project_id = :projectId AND
        is_enabled = 1
    ";
    $validTagIdsResult = run_prepared_query($DBH, $validTagIdsQuery, $validTagIdsQueryParam);

    $validTagIds = array();
    $validCommentIds = array();
    while ($validTagId = $validTagIdsResult->fetch()) {
        if ($validTagId['is_comment_box'] == 0) {
            $validTagIds[] = $validTagId['tag_id'];
        } else {
            $validCommentIds[] = $validTagId['tag_id'];
        }
    }
    // file_put_contents('loggerLog.txt', "Project Tags are:" . print_r($validTagIds, true) . "\n\r", FILE_APPEND);
    // file_put_contents('loggerLog.txt', "Project Comment Tags are:" . print_r($validCommentIds, true) . "\n\r", FILE_APPEND);

    $newSelectedTagIds = array();
    $newUserComments = array();
    foreach ($_POST as $postName => $postValue) {
//        print $tagId . '<br>' . $postValue . '<br>';
        $filteredPostName = filter_var($postName, FILTER_VALIDATE_INT);
        $filteredPostValue = filter_var(trim($postValue));
//        print $filteredTagId . '<br>' . $filteredTagValue . '<br>------------------------<br>';
//         file_put_contents('loggerLog.txt', "Checking POST entry $postName ($filteredPostName) containing value '$postValue' ($filteredPostValue).\n\r", FILE_APPEND);


        switch ($postName)
        {
            case 'annotationSessionId':
            case 'authCheckCode':
            case 'userId':
            case 'projectId':
            case 'postImageId':
            case 'preImageId':
            case 'startClassification':
            case 'annotationComplete':
            case '__ncforminfo':
//                file_put_contents('loggerLog.txt',
//                                  "The entry is a valid metadata tag.\n\r",
//                                  FILE_APPEND);
                break;
            default:
                if (
                    $filteredPostValue &&
                    array_search($filteredPostValue,
                                 $validTagIds) !== false
                )
                {
                    $newSelectedTagIds[$filteredPostValue] = $filteredPostValue;
//                    file_put_contents('loggerLog.txt',
//                                      "The entry is a valid tag.\n\r",
//                                      FILE_APPEND);
                }
                else
                {
                    if (
                        $filteredPostName &&
                        array_search($filteredPostName,
                                     $validCommentIds) !== false
                    )
                    {
                        $newUserComments[$filteredPostName] = $filteredPostValue;
//                        file_put_contents('loggerLog.txt',
//                                          "The entry is a valid comment tag.\n\r",
//                                          FILE_APPEND);
                    }
                    else
                    {
                        exit('{"result":false, "details":"Unexpected information was detected in the data upload."}');
                    }
                }
        }
    }
//     file_put_contents('loggerLog.txt', "New Tags are:" . print_r($newSelectedTagIds, true) . "\n\r", FILE_APPEND);
//     file_put_contents('loggerLog.txt', "New Comment Tags are:" . print_r($newUserComments, true) . "\n\r", FILE_APPEND);


    $existingTagSelectionsQuery = "
        SELECT 
            * 
        FROM 
            annotation_selections
        WHERE 
            annotation_id = :annotationId";
    $existingTagSelectionsParams['annotationId'] = $existingAnnotation['annotation_id'];
    $existingTagSelectionsResult = run_prepared_query($DBH, $existingTagSelectionsQuery, $existingTagSelectionsParams);
    $existingTagSelections = $existingTagSelectionsResult->fetchAll(PDO::FETCH_ASSOC);
//     file_put_contents('loggerLog.txt', "Existing tags are:" . print_r($existingTagSelections, true) . "\n\r", FILE_APPEND);


    foreach ($existingTagSelections as $existingTagSelection) {
        $existingTagId = $existingTagSelection['tag_id'];
        $existingTableId = $existingTagSelection['table_id'];
//         file_put_contents('loggerLog.txt', "Checking existing tag $existingTagId in row $existingTableId.\n\r", FILE_APPEND);
        if (in_array($existingTagId, $newSelectedTagIds)) {
//             file_put_contents('loggerLog.txt', "The existing tag is unchanged in the new submission.\n\r", FILE_APPEND);
            unset($newSelectedTagIds[$existingTagId]);
        } else {
//             file_put_contents('loggerLog.txt', "The existing tag is no longer selected and is being rmoved form the DB.\n\r", FILE_APPEND);
            $unselectedTagDeleteQuery = "
                DELETE FROM 
                    annotation_selections
                WHERE 
                    table_id = :existingTableId 
                LIMIT 
                    1
            ";
            $unselectedTagDeleteParams['existingTableId'] = $existingTableId;
            $unselectedTagDeleteResult = run_prepared_query($DBH, $unselectedTagDeleteQuery, $unselectedTagDeleteParams);

            if ($unselectedTagDeleteResult->rowCount() != 1) {
                // file_put_contents('loggerLog.txt', "Deleting an unselcted tag failed. Exit.", FILE_APPEND);
                exit('{"result":false, "details":"Server failed to remove a previously chosen tag from the database
                ."}');
            }
            $userDataChange = TRUE;
        }
    }


    if (count($newSelectedTagIds) > 0) {
        // file_put_contents('loggerLog.txt', "New tags have been selected and must be added to the DB.\n\r", FILE_APPEND);
        foreach ($newSelectedTagIds as $newSelectedTagId) {
            // file_put_contents('loggerLog.txt', "Adding tag $newSelectedTagId to annotation {$existingAnnotation['annotation_id']}\n\r", FILE_APPEND);
            $newSelectedTagInsertQuery = "
                INSERT INTO 
                    annotation_selections 
                (
                    annotation_id, 
                    tag_id
                ) 
                VALUES 
                (
                    :annotationId, 
                    :tagId
                )
            ";
            $newSelectedTagInsertParams = array(
                'annotationId' => $existingAnnotation['annotation_id'],
                'tagId' => $newSelectedTagId
            );
            $newSelectedTagInsertResult = run_prepared_query($DBH, $newSelectedTagInsertQuery, $newSelectedTagInsertParams);
            if ($newSelectedTagInsertResult->rowCount() != 1) {
                // file_put_contents('loggerLog.txt', "Inserting a new tag failed. Exit.", FILE_APPEND);
                exit('{"result":false, "details":"Server failed to insert a newly selected tag into the database."}');
            }
            $userDataChange = TRUE;
        }
    }




    $existingCommentsQuery = "
        SELECT 
            * 
        FROM 
            annotation_comments
        WHERE 
            annotation_id = :annotationId
        ";
    $existingCommentsParams['annotationId'] = $existingAnnotation['annotation_id'];
    $existingCommentsResult = run_prepared_query($DBH, $existingCommentsQuery, $existingCommentsParams);
    $existingComments = $existingCommentsResult->fetchAll(PDO::FETCH_ASSOC);
    // file_put_contents('loggerLog.txt', "Existing comments are:" . print_r($existingComments, true) . "\n\r", FILE_APPEND);

    foreach ($existingComments as $existingComment) {
        $existingCommentTagId = $existingComment['tag_id'];
        $existingCommentTableId = $existingComment['table_id'];
        $existingCommentText = $existingComment['comment'];
        // file_put_contents('loggerLog.txt', "Checking existing comment id $existingCommentTagId with comment '$existingCommentText' against new submission. \n\r", FILE_APPEND);
        if (array_key_exists($existingCommentTagId, $newUserComments)) {
            if (strcmp($existingCommentText, $newUserComments[$existingCommentTagId]) == 0) {
                // file_put_contents('loggerLog.txt', "Existing comment is unchanged in new submission.\n\r", FILE_APPEND);
                unset($newUserComments[$existingCommentTagId]);
            } else {
                if ($newUserComments[$existingCommentTagId]) {
                    // file_put_contents('loggerLog.txt', "Existing comment has changed. Updating comment to new submission.\n\r", FILE_APPEND);
                    $updateExistingCommentQuery = "
                        UPDATE 
                            annotation_comments
                        SET 
                            comment = :comment
                        WHERE 
                            table_id = :existingCommentTableId 
                        LIMIT 
                            1
                    ";
                    $updateExistingCommentParams = array(
                        'comment' => $newUserComments[$existingCommentTagId],
                        'existingCommentTableId' => $existingCommentTableId
                    );
                    $updateExistingCommentResult = run_prepared_query($DBH, $updateExistingCommentQuery, $updateExistingCommentParams);
                    if ($updateExistingCommentResult->rowCount() != 1) {
                        // file_put_contents('loggerLog.txt', "Update of existing comment failed. Exit.", FILE_APPEND);
                        exit('{"result":false, "details":"Server failed to update an existing comment."}');
                    }
                } else {
                    // file_put_contents('loggerLog.txt', "Existing comment is no longer supplied. Removing it from the DB.\n\r", FILE_APPEND);
                    $deleteExistingCommentQuery = "
                        DELETE FROM 
                            annotation_comments
                        WHERE 
                            table_id = :existingCommentTableId 
                        LIMIT 
                            1
                    ";
                    $deleteExistingCommentParams = array(
                        'existingCommentTableId' => $existingCommentTableId
                    );
                    $deleteExistingCommentResult = run_prepared_query($DBH, $deleteExistingCommentQuery, $deleteExistingCommentParams);
                    if ($deleteExistingCommentResult->rowCount() != 1) {
                        // file_put_contents('loggerLog.txt', "Deletion of existing comment failed. Exit.", FILE_APPEND);
                        exit('{"result":false, "details":"Server failed to delete an existing comment."}');
                    }
                }
                unset($newUserComments[$existingCommentTagId]);
                $userDataChange = TRUE;
            }
        }
    }
    if (count($newUserComments) > 0) {
        // file_put_contents('loggerLog.txt', "New user comments exist that may need to be added to the DB.\n\r", FILE_APPEND);
        foreach ($newUserComments as $newCommentTagId => $newCommentText) {
            if ($newCommentText) {
                // file_put_contents('loggerLog.txt', "Adding '$newCommentText' under tag id $newCommentTagId. \n\r", FILE_APPEND);
                $newCommentInsertQuery = "
                INSERT INTO 
                    annotation_comments 
                (
                    annotation_id,
                    tag_id,
                    comment
                )
                VALUES 
                (
                    :existingAnnotationId, 
                    :newCommentTagId, 
                    :newCommentText
                )
            ";
                $newCommentInsertParams = array(
                    'existingAnnotationId' => $existingAnnotation['annotation_id'],
                    'newCommentTagId' => $newCommentTagId,
                    'newCommentText' => $newCommentText
                );
                $newCommentInsertResult = run_prepared_query($DBH, $newCommentInsertQuery, $newCommentInsertParams);
                if ($newCommentInsertResult->rowCount() != 1) {
                    // file_put_contents('loggerLog.txt', "Insertion of new user comment failed. Exit.", FILE_APPEND);
                    exit('{"result":false, "details":"Server failed to insert new comment."}');
                }
                $userDataChange = TRUE;
            } else {
                // file_put_contents('loggerLog.txt', "Comment is empty.\n\r", FILE_APPEND);
            }
        }
    }




    if ($userDataChange) {
        // file_put_contents('loggerLog.txt', "Data has changed. The main annotation row must be updated.\n\r", FILE_APPEND);

        if ($annotationSessionId == $existingAnnotation['initial_session_id']) {
            // file_put_contents('loggerLog.txt', "The update is part of an ongoing classifcation.\n\r", FILE_APPEND);

            if (!$annotationCompleteFlag) {
                // file_put_contents('loggerLog.txt', "The annotation isn't complete yet.\n\r", FILE_APPEND);
                $annotationUpdateQuery = "
                    UPDATE 
                        annotations 
                    SET 
                        user_match_id = :preImageId,
                        initial_session_end_time = NOW()
                    WHERE 
                        annotation_id = :existingAnnotationId
                ";
            } else {
                // file_put_contents('loggerLog.txt', "The annotation is complete.\n\r", FILE_APPEND);
                $annotationUpdateQuery = "
                    UPDATE 
                        annotations 
                    SET 
                        user_match_id = :preImageId,
                        initial_session_end_time = NOW(), 
                        annotation_completed = 1
                    WHERE 
                        annotation_id = :existingAnnotationId
                ";
            }
            $annotationUpdateParams = array(
                'preImageId' => $preImageId,
                'existingAnnotationId' => $existingAnnotation['annotation_id']
            );
            $annotationUpdateResult = run_prepared_query($DBH, $annotationUpdateQuery, $annotationUpdateParams);
            if ($annotationUpdateResult->rowCount() != 1) {
                // file_put_contents('loggerLog.txt', "Updating the annotation row failed. Exit.", FILE_APPEND);
                exit('{"result":false, "details":"Server failed to update the annotation."}');
            }
        } else {
            if ($annotationSessionId != $existingAnnotation['revision_session_id']) {
                $revisionCount = $existingAnnotation['revision_count'] + 1;
                // file_put_contents('loggerLog.txt', "This change is part of a new revision to an existign annotaion.\n\r", FILE_APPEND);
            } else {
                $revisionCount = $existingAnnotation['revision_count'];
                // file_put_contents('loggerLog.txt', "This change is part of a revision in progress.\n\r", FILE_APPEND);
            }

            if (
                    $annotationCompleteFlag &&
                    $existingAnnotation['annotation_completed'] == 0) {
                // file_put_contents('loggerLog.txt', "The annotaion has been completed under this revision.\n\r", FILE_APPEND);
                $annotationUpdateQuery = "
                    UPDATE 
                        annotations 
                    SET 
                        user_match_id = :preImageId, 
                        revision_session_id = :annotationSessionId, 
                        revision_count = :revisionCount, 
                        last_revision_time = NOW(), 
                        annotation_completed = 1, 
                        annotation_completed_under_revision = 1 
                    WHERE 
                    annotation_id = :existingAnnotationId
                ";
            } else {
                // file_put_contents('loggerLog.txt', "The annotaion completed status has not changed.\n\r", FILE_APPEND);
                $annotationUpdateQuery = "
                    UPDATE 
                        annotations 
                    SET 
                        user_match_id = :preImageId, 
                        revision_session_id = :annotationSessionId, 
                        revision_count = :revisionCount, 
                        last_revision_time = NOW() 
                    WHERE 
                        annotation_id = :existingAnnotationId
                ";
            }
            $annotationUpdateParams = array(
                'preImageId' => $preImageId,
                'annotationSessionId' => $annotationSessionId,
                'revisionCount' => $revisionCount,
                'existingAnnotationId' => $existingAnnotation['annotation_id'],
            );
            $annotationUpdateResult = run_prepared_query($DBH, $annotationUpdateQuery, $annotationUpdateParams);
            if ($annotationUpdateResult->rowCount() != 1) {
                // file_put_contents('loggerLog.txt', "Updating the annotaion row under revision failed. Exit.", FILE_APPEND);
                exit('{"result":false, "details":"Server failed to update the annotation currently under revision."}');
            }
        }
    } // END if ($userDataChange)
}  // END if ($startClassificationFlag) ELSE

// file_put_contents('loggerLog.txt', "END", FILE_APPEND);
//echo json_encode(array('result' => 1));
print '{"result":true}';


