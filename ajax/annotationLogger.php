<?php

//print 'Raw Data<br><pre>';
//print_r($_POST);
//print '</pre><br>';

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




$annotationSessionId = $_POST['annotationSessionId'];
$userId = $_POST['userId'];
$authCheckCode = $_POST['authCheckCode'];
$projectId = $_POST['projectId'];
$postImageId = $_POST['postImageId'];

unset($_POST['annotationSessionId']);
unset($_POST['userId']);
unset($_POST['authCheckCode']);
unset($_POST['projectId']);
unset($_POST['postImageId']);

$validUser = validateUser($DBH, $userId, $authCheckCode);
if (!$validUser['validationResult']) {
  print "User authentication error: {$validUser['errorMsg']}";
  exit;
}


$annotationExistsQuery = "SELECT * FROM annotations WHERE user_id = $userId AND "
        . "project_id = $projectId AND image_id = $postImageId";
$annotationExistsParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$STH = run_prepared_query($DBH, $annotationExistsQuery, $annotationExistsParams);
$existingAnnotation = $STH->fetchAll(PDO::FETCH_ASSOC);
$existingAnnotationStatus = FALSE;
if (count($existingAnnotation) > 0) {
  $existingAnnotationStatus = TRUE;
  $existingAnnotation = $existingAnnotation[0];
}
//$annotationExistsResult = run_database_query($annotationExistsQuery);
//$existingAnnotation = $annotationExistsResult->fetch_assoc();

if (isset($_POST['loadEvent'])) {
  if (!$existingAnnotationStatus) {
    $annotationLoadEventQuery = "INSERT INTO annotations (initial_session_id, user_id, "
            . "project_id, image_id, initial_session_start_time) VALUES (:annotationSessionId, :userId, "
            . ":projectId, :postImageId, NOW())";
    $annotationLoadEventParams = array(
        'annotationSessionId' => $annotationSessionId,
        'userId' => $userId,
        'projectId' => $projectId,
        'postImageId' => $postImageId
    );
    $STH = run_prepared_query($DBH, $annotationLoadEventQuery, $annotationLoadEventParams);
//    $annotationLoadEventResult = run_database_query($annotationLoadEventQuery);
    if ($STH->rowCount() != 1) {
      //  Placeholder for error management
      print "Load event database update failed: $annotationLoadEventQuery";
      exit;
    }
  } else {
    if (is_null($existingAnnotation['user_match_id']) && $existingAnnotation['initial_session_id'] != $annotationSessionId) {
      $annotationLoadEventQuery = "UPDATE annotations SET initial_session_id = :annotationSessionId, "
              . "initial_session_start_time = NOW() "
              . "WHERE annotation_id = :annotationId";
      $annotationLoadEventParams = array(
          'annotationSessionId' => $annotationSessionId,
          'annotationId' => $existingAnnotation['annotation_id']
      );
      $STH = run_prepared_query($DBH, $annotationLoadEventQuery, $annotationLoadEventParams);
//      $annotationLoadEventResult = run_database_query($annotationLoadEventQuery);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Load event database update failed: $annotationLoadEventQuery");
      }
    }
  }
} else { // End loadEvent If
  $userDataChange = FALSE;

  if (!$existingAnnotationStatus) {
    print "Error: No annotation to update.";
    exit;
  }

  $preImageId = $_POST['preImageId'];
  unset($_POST['preImageId']);
  $annotationComplete = 0;
  if (isset($_POST['annotationComplete'])) {
    $annotationComplete = 1;
    unset($_POST['annotationComplete']);
  }

//  print 'Raw Selections<br><pre>';
//  print_r($_POST);
//  print '</pre>';


  if ($existingAnnotation['user_match_id'] != $preImageId ||
          ($existingAnnotation['annotation_completed'] == 0 && $existingAnnotation['annotation_completed'] != $annotationComplete)) {
//    print "Changinh userDataChange Flag<br>";
    $userDataChange = TRUE;
  }

  $selections = $_POST;
  $comments = array();
  unset($_POST);

  foreach ($selections as $tagId => $tagValue) {
    if (!is_numeric($tagId)) {
      $selections[$tagValue] = $tagValue;
      unset($selections[$tagId]);
    }
    if (!is_numeric($tagValue) || empty($tagValue)) {
      if (!empty($tagValue)) {
        $comments[$tagId] = $tagValue;
      }
      unset($selections[$tagId]);
    }
  }

//  print 'Unprocessed Selections<br><pre>';
//  print_r($selections);
//  print '</pre>';

  $tagSelectionQuery = "SELECT * FROM annotation_selections "
          . "WHERE annotation_id = :annotationId";
  $tagSelectionParams['annotationId'] = $existingAnnotation['annotation_id'];
  $STH = run_prepared_query($DBH, $tagSelectionQuery, $tagSelectionParams);
  $existingSelections = $STH->fetchAll(PDO::FETCH_ASSOC);
//  print 'Existing Selections<br><pre>';
//  print_r($existingSelections);
//  print '</pre>';

//  $tagSelectionResult = run_database_query($tagSelectionQuery);

  foreach ($existingSelections as $existingSelection) {
    $databaseTagId = $existingSelection['tag_id'];
    $databaseEntryId = $existingSelection['table_id'];
    if (in_array($databaseTagId, $selections)) {
      unset($selections[$databaseTagId]);
    } else {
      $tagSelectionDeleteQuery = "DELETE FROM annotation_selections "
              . "WHERE table_id = :databaseEntryId LIMIT 1";
      $tagSelectionDeleteParams['databaseEntryId'] = $databaseEntryId;
      $STH = run_prepared_query($DBH, $tagSelectionDeleteQuery, $tagSelectionDeleteParams);

//      $tagSelectionDeleteResult = run_database_query($tagSelectionDeleteQuery);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Deletion of unselcted tag from database failed: $annotationLoadEventQuery");
      }
      $userDataChange = TRUE;
    }
  }

//    print 'Selections to insert<br><pre>';
//  print_r($selections);
//  print '</pre>';
  if (count($selections > 0)) {
    foreach ($selections as $selection) {
      $tagSelectionInsertQuery = "INSERT INTO annotation_selections (annotation_id, tag_id) VALUES "
              . "(:annotationId, :tagId)";
      $tagSelectionInsertParams = array(
          'annotationId' => $existingAnnotation['annotation_id'],
          'tagId' => $selection
      );
      $STH = run_prepared_query($DBH, $tagSelectionInsertQuery, $tagSelectionInsertParams);
//      $tagSelectionInsertResult = run_database_query($tagSelectionInsertQuery);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Insertion of  newly selcted tag from database failed: $annotationLoadEventQuery");
      }
      $userDataChange = TRUE;
    }
  }

//  print 'Unprocessed Comments<br><pre>';
//  print_r($comments);
//  print '</pre>';


  $tagCommentQuery = "SELECT * FROM annotation_comments"
          . " WHERE annotation_id = :annotationId";
  $tagCommentParams['annotationId'] = $existingAnnotation['annotation_id'];
  $STH = run_prepared_query($DBH, $tagCommentQuery, $tagCommentParams);
  $existingComments = $STH->fetchAll(PDO::FETCH_ASSOC);
//  $tagCommentResult = run_database_query($tagCommentQuery);
//  print 'Existing Comments<br><pre>';
//  print_r($existingComments);
//  print '</pre>';

  foreach ($existingComments as $existingComment) {
    $databaseTagId = $existingComment['tag_id'];
    $databaseEntryId = $existingComment['table_id'];
    $databaseComment = $existingComment['comment'];
    if (array_key_exists($databaseTagId, $comments)) {
      if (strcmp($databaseComment, $comments[$databaseTagId]) == 0) {
        unset($comments[$databaseTagId]);
      } else {
        $tagCommentUpdateQuery = "UPDATE annotation_comments "
                . "SET comment = :comment "
                . "WHERE table_id = :databaseEntryId LIMIT 1";
        $tagCommentUpdateParams = array(
            'comment' => $comments[$databaseTagId],
            'databaseEntryId' => $databaseEntryId
        );
        $STH = run_prepared_query($DBH, $tagCommentUpdateQuery, $tagCommentUpdateParams);
//        $tagCommentDeleteResult = run_database_query($tagCommentDeleteQuery);
        if ($STH->rowCount() != 1) {
          //  Placeholder for error management
          exit("Update of existing comment in database failed: $annotationLoadEventQuery");
        }
        unset($comments[$databaseTagId]);
        $userDataChange = TRUE;
      }
    }
  }
//    print 'Comments to insert<br><pre>';
//  print_r($comments);
//  print '</pre>';
  if (count($comments > 0)) {
    foreach ($comments as $tagId => $comment) {
      $tagSelectionInsertQuery = "INSERT INTO annotation_comments (annotation_id, tag_id, comment) "
              . "VALUES (:annotationId, :tagId, :comment)";
      $tagSelectionInsertParams = array(
          'annotationId' => $existingAnnotation['annotation_id'],
          'tagId' => $tagId,
          'comment' => $comment
      );
      $STH = run_prepared_query($DBH, $tagSelectionInsertQuery, $tagSelectionInsertParams);
//      $tagSelectionInsertResult = run_database_query($tagSelectionInsertQuery);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Insertion of new comment into database failed: $annotationLoadEventQuery");
      }
      $userDataChange = TRUE;
    }
  }




  if ($userDataChange) {
    if ($annotationSessionId == $existingAnnotation['initial_session_id']) {


      if ($annotationComplete == 0) {
        $annotationUpdateQuery = "UPDATE annotations SET user_match_id = :preImageId,"
                . "initial_session_end_time = NOW() "
                . "WHERE annotation_id = :annotationId";
      } else {
        $annotationUpdateQuery = "UPDATE annotations SET user_match_id = :preImageId,"
                . "initial_session_end_time = NOW(), annotation_completed = 1 "
                . "WHERE annotation_id = :annotationId";
      }
      $annotationUpdateParams = array(
          'preImageId' => $preImageId,
          'annotationId' => $existingAnnotation['annotation_id']
      );
      $STH = run_prepared_query($DBH, $annotationUpdateQuery, $annotationUpdateParams);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Update of annotations table in database failed: $annotationLoadEventQuery");
      }

//      $annotationUpdateResult = run_database_query($annotationUpdateQuery);
//      if (!$annotationUpdateResult) {
//        print "Query Failure: $annotationUpdateResult";
//        exit;
//      }
    } else {
      if ($annotationSessionId != $existingAnnotation['revision_session_id']) {
        $revisionCount = $existingAnnotation['revision_count'] + 1;
      } else {
        $revisionCount = $existingAnnotation['revision_count'];
      }
      $annotationUpdateQuery = "UPDATE annotations "
              . "SET user_match_id = :preImageId, "
              . "revision_session_id = :annotationSessionId, "
              . "revision_count = :revisionCount, last_revision_time = NOW() "
              . "WHERE annotation_id = :annotationId";
      if ($annotationComplete == 1 && $existingAnnotation['annotation_completed'] == 0) {
        $annotationUpdateQuery = "UPDATE annotations "
                . "SET user_match_id = :preImageId, "
                . "revision_session_id = :annotationSessionId, "
                . "revision_count = :revisionCount, last_revision_time = NOW(), "
                . "annotation_completed = 1, annotation_completed_under_revision = 1 "
                . "WHERE annotation_id = :annotationId";
      }
      $annotationUpdateParams = array(
          'preImageId' => $preImageId,
          'annotationSessionId' => $annotationSessionId,
          'revisionCount' => $revisionCount,
          'annotationId' => $existingAnnotation['annotation_id'],
      );
      $STH = run_prepared_query($DBH, $annotationUpdateQuery, $annotationUpdateParams);
      if ($STH->rowCount() != 1) {
        //  Placeholder for error management
        exit("Update of annotations table in database failed: $annotationLoadEventQuery");
      }

//      $annotationUpdateResult = run_database_query($annotationUpdateQuery);
//      if (!$annotationUpdateResult) {
//        print "Query Failure: $annotationUpdateResult";
//        exit;
//      }
    }
  }
} // End loadEvent Else


