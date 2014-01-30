<?php

print '<pre>';
print_r($_POST);
print '</pre>';

require_once('../../iCoastSecure/DBMSConnection.php');
require_once('../includes/globalFunctions.php');

function validateUser($userId, $authCheckCode) {
  $validUser = TRUE;
  $errorMsg = '';
  $userValidationQuery = "SELECT auth_check_code, is_enabled FROM users WHERE user_id = $userId LIMIT 1";
  $userValidationResult = run_database_query($userValidationQuery);
  if ($userValidationResult == FALSE) {
    $validUser = FALSE;
    $errorMsg = 'Query Failure';
  }
  $user = $userValidationResult->fetch_assoc();

  if ($user['auth_check_code'] != $authCheckCode) {
    $validUser = FALSE;
    $errorMsg = 'Authentication code mismatch.';
  }

  if ($user['is_enabled'] == 0) {
    $validUser = FALSE;
    $errorMsg = 'User account is disabled';
  }

  return array('validationResult' => $validUser,
      'errorMsg' => $errorMsg);
}

foreach ($_POST as $key => $value) {
  $_POST[$key] = escape_string($value);
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

$validUser = validateUser($userId, $authCheckCode);
if (!$validUser['validationResult']) {
  print "User authentication error: {$validUser['errorMsg']}";
  exit;
}










$annotationExistsQuery = "SELECT * FROM annotations WHERE user_id = $userId AND "
        . "project_id = $projectId AND image_id = $postImageId";
$annotationExistsResult = run_database_query($annotationExistsQuery);
if (!$annotationExistsResult) {
  print "Query Failure: $annotationExistsQuery";
  exit;
}
$existingAnnotation = $annotationExistsResult->fetch_assoc();


if (isset($_POST['loadEvent'])) {
  if ($annotationExistsResult->num_rows == 0) {
    $annotationLoadEventQuery = "INSERT INTO annotations (initial_session_id, user_id, "
            . "project_id, image_id) VALUES ('$annotationSessionId', $userId, "
            . "$projectId, $postImageId)";
    $annotationLoadEventResult = run_database_query($annotationLoadEventQuery);
    if (!$annotationLoadEventResult) {
      print "Query Failure: $annotationLoadEventQuery";
      exit;
    }
  } else {
    if (is_null($existingAnnotation['user_match_id']) && $existingAnnotation['user_match_id'] != $annotationSessionId) {
      $annotationLoadEventQuery = "UPDATE annotations SET initial_session_id = '$annotationSessionId', "
              . "initial_session_start_time = NOW() "
              . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
      $annotationLoadEventResult = run_database_query($annotationLoadEventQuery);
    }
  }
} else { // End loadEvent If
  $userDataChange = FALSE;

  print '<pre>';
  print_r($_POST);
  print '</pre>';

  if ($annotationExistsResult->num_rows == 0) {
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


  if ($existingAnnotation['user_match_id'] != $preImageId ||
          $existingAnnotation['annotation_completed'] != $annotationComplete) {
    $userDataChange = TRUE;
  }

  $selections = $_POST;
  $comments = array();
  unset($_POST);

  print '<pre>';
  print_r($selections);
  print '</pre>';

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

  print '<pre>';
  print_r($selections);
  print '</pre>';

  print '<pre>';
  print_r($comments);
  print '</pre>';



  $tagSelectionQuery = "SELECT * FROM annotation_selections "
          . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
  $tagSelectionResult = run_database_query($tagSelectionQuery);

  if (!$tagSelectionResult) {
    print "Query Failure: $tagSelectionQuery";
    exit;
  }


  while ($existingSelection = $tagSelectionResult->fetch_assoc()) {
    $databaseTagId = $existingSelection['tag_id'];
    $databaseEntryId = $existingSelection['table_id'];
    if (in_array($databaseTagId, $selections)) {
      unset($selections[$databaseTagId]);
    } else {
      $tagSelectionDeleteQuery = "DELETE FROM annotation_selections "
              . "WHERE table_id = $databaseEntryId LIMIT 1";
      $tagSelectionDeleteResult = run_database_query($tagSelectionDeleteQuery);
      if (!$tagSelectionDeleteResult) {
        print "Query Failure: $tagSelectionDeleteQuery";
        exit;
      }
      $userDataChange = TRUE;
    }
  }

  if (count($selections > 0)) {
    foreach ($selections as $selection) {
      $tagSelectionInsertQuery = "INSERT INTO annotation_selections (annotation_id, tag_id) VALUES "
              . "({$existingAnnotation['annotation_id']}, $selection)";
      $tagSelectionInsertResult = run_database_query($tagSelectionInsertQuery);
      if (!$tagSelectionInsertResult) {
        print "Query Failure: $tagSelectionInsertResult";
        exit;
      }
      $userDataChange = TRUE;
    }
  }





  $tagCommentQuery = "SELECT * FROM annotation_comments"
          . " WHERE annotation_id = {$existingAnnotation['annotation_id']}";
  $tagCommentResult = run_database_query($tagCommentQuery);

  if (!$tagCommentResult) {
    print "Query Failure: $tagCommentQuery";
    exit;
  }

  while ($existingComment = $tagCommentResult->fetch_assoc()) {
    $databaseTagId = $existingComment['tag_id'];
    $databaseEntryId = $existingComment['table_id'];
    $databaseComment = $existingComment['comment'];
    if (array_key_exists($databaseTagId, $comments)) {
      if (strcmp($databaseComment, $comments[$databaseTagId]) == 0) {
        unset($comments[$databaseTagId]);
      } else {
        $tagCommentDeleteQuery = "UPDATE annotation_comments "
                . "SET comment ='$comments[$databaseTagId]'"
                . "WHERE table_id = $databaseEntryId LIMIT 1";
        $tagCommentDeleteResult = run_database_query($tagCommentDeleteQuery);
        if (!$tagCommentDeleteResult) {
          print "Query Failure: $tagCommentDeleteQuery";
          exit;
        }
        unset($comments[$databaseTagId]);
        $userDataChange = TRUE;
      }
    }
  }

  if (count($comments > 0)) {
    foreach ($comments as $tagId => $comment) {
      $tagSelectionInsertQuery = "INSERT INTO annotation_comments (annotation_id, tag_id, comment) "
              . "VALUES ({$existingAnnotation['annotation_id']}, $tagId, '$comment')";
      $tagSelectionInsertResult = run_database_query($tagSelectionInsertQuery);
      if (!$tagSelectionInsertResult) {
        print "Query Failure: $tagSelectionInsertResult";
        exit;
      }
      $userDataChange = TRUE;
    }
  }




  if ($userDataChange) {
    if ($annotationSessionId == $existingAnnotation['initial_session_id']) {

      $annotationUpdateQuery = "UPDATE annotations SET user_match_id = $preImageId,"
              . "initial_session_end_time = NOW() "
              . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";

      if ($annotationComplete == 1) {
        $annotationUpdateQuery = "UPDATE annotations SET user_match_id = $preImageId,"
                . "initial_session_end_time = NOW(), annotation_completed = 1 "
                . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
      }
      $annotationUpdateResult = run_database_query($annotationUpdateQuery);
      if (!$annotationUpdateResult) {
        print "Query Failure: $annotationUpdateResult";
        exit;
      }
    } else {
      if ($annotationSessionId != $existingAnnotation['revision_session_id']) {
        $revisionCount = $existingAnnotation['revision_count'] + 1;
      } else {
        $revisionCount = $existingAnnotation['revision_count'];
      }
      $annotationUpdateQuery = "UPDATE annotations "
              . "SET user_match_id = $preImageId, "
              . "revision_session_id = '$annotationSessionId', "
              . "revision_count = $revisionCount, last_revision_time = NOW() "
              . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
      if ($annotationComplete == 1 && $existingAnnotation['annotation_completed'] == 0) {
        $annotationUpdateQuery = "UPDATE annotations "
                . "SET user_match_id = $preImageId, "
                . "revision_session_id = '$annotationSessionId', "
                . "revision_count = $revisionCount, last_revision_time = NOW(), "
                . "annotation_completed = 1, annotation_completed_under_revision = 1 "
                . "WHERE annotation_id = {$existingAnnotation['annotation_id']}";
      }
      $annotationUpdateResult = run_database_query($annotationUpdateQuery);
      if (!$annotationUpdateResult) {
        print "Query Failure: $annotationUpdateResult";
        exit;
      }
    }
  }
} // End loadEvent Else

