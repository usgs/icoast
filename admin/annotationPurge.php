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

if (isset($_POST['clearAnnotationsForUser'])) {
    switch ($_POST['clearAnnotationsForUser']) {
        case 'Sophia':
            $targetUserId = 16;
            break;
        case 'Barbara':
            $targetUserId = 14;
            break;
        case 'Richard':
            $targetUserId = 2;
            break;
    }
//    settype($_POST['clearAnnotationsForUser'], 'integer');
//    $targetUserId = $_POST['clearAnnotationsForUser'];
    $annotationTableIdArray = array();
    $annotationSelectionTableIdArray = array();
    $annotationCommentTableIdArray = array();
    if (!empty($targetUserId)) {
        $annotationQuery = "SELECT an.annotation_id AS an_id, ans.table_id AS ans_id, anc.table_id AS anc_id FROM annotations an "
                . "LEFT JOIN annotation_selections ans ON an.annotation_id = ans.annotation_id "
                . "LEFT JOIN annotation_comments anc ON an.annotation_id = anc.annotation_id "
                . "WHERE an.user_id = :userId";
        $annotationParams['userId'] = $targetUserId;
        $annotationResult = run_prepared_query($DBH, $annotationQuery, $annotationParams);
        print "Results:<br>";
        while ($annotation = $annotationResult->fetch(PDO::FETCH_ASSOC)) {
            print '<pre>';
            print_r($annotation);
            print '</pre>';
            if (!in_array($annotation['an_id'], $annotationTableIdArray)) {
                $annotationTableIdArray[] = $annotation['an_id'];
            }
            if (!empty($annotation['ans_id'])) {
                if (!in_array($annotation['ans_id'], $annotationSelectionTableIdArray)) {
                    $annotationSelectionTableIdArray[] = $annotation['ans_id'];
                }
            }
            if (!empty($annotation['anc_id'])) {
                if (!in_array($annotation['anc_id'], $annotationCommentTableIdArray)) {
                    $annotationCommentTableIdArray[] = $annotation['anc_id'];
                }
            }
        }
        print 'Annotation IDs';
        print '<pre>';
        print_r($annotationTableIdArray);
        print '</pre>';
        print 'Annotation Selection IDs';
        print '<pre>';
        print_r($annotationSelectionTableIdArray);
        print '</pre>';
        print 'Annotation CommentsIDs';
        print '<pre>';
        print_r($annotationCommentTableIdArray);
        print '</pre>';

        $numberOfComments = count($annotationCommentTableIdArray);
        $numberOfTagSelections = count($annotationSelectionTableIdArray);
        $numberofAnnotations = count($annotationTableIdArray);
        $deletionSuccess = array(
            'annotations' => 'Unchanged',
            'selections' => 'Unchanged',
            'comments' => 'Unchanged'
        );

        if ($numberOfComments > 0) {
            print $numberOfComments . '<br>Comments <br>';
            $commentWhereInString = where_in_string_builder($annotationCommentTableIdArray);
            print 'Where In String ' . $commentWhereInString . '<br>';
            $commentDeletionQuery = "DELETE FROM annotation_comments "
                    . "WHERE table_id IN ($commentWhereInString) "
                    . "LIMIT $numberOfComments";
            print $commentDeletionQuery . '<br>';
            $commentDeletionResult = $DBH->query($commentDeletionQuery);
            print "Affected rows " . $commentDeletionResult->rowCount() . '<br>';
            if ($commentDeletionResult->rowCount() == $numberOfComments) {
                print "Success <br>";
                $deletionSuccess['comments'] = 'Deleted';
            } else {
                print "Failure";
                $deletionSuccess['comments'] = 'Delete Failed';
            }
        }

        if ($numberOfTagSelections > 0) {
            $selectionWhereInString = where_in_string_builder($annotationSelectionTableIdArray);
            $selectionDeletionQuery = "DELETE FROM annotation_selections "
                    . "WHERE table_id IN ($selectionWhereInString) "
                    . "LIMIT $numberOfTagSelections";
            $selectionDeletionResult = $DBH->query($selectionDeletionQuery);
            if ($selectionDeletionResult->rowCount() == $numberOfTagSelections) {
                $deletionSuccess['selections'] = 'Deleted';
            } else {
                $deletionSuccess['selections'] = 'Delete Failed';
            }
        }

        if ($numberofAnnotations > 0) {
            print $numberOfComments . '<br>Annotations <br>';
            $annotationWhereInString = where_in_string_builder($annotationTableIdArray);
            print 'Where In String ' . $annotationWhereInString . '<br>';
            $annotationnDeletionQuery = "DELETE FROM annotations "
                    . "WHERE annotation_id IN ($annotationWhereInString) "
                    . "LIMIT $numberofAnnotations";
            print $annotationnDeletionQuery . '<br>';
            $annotationDeletionResult = $DBH->query($annotationnDeletionQuery);
            print "Affected rows " . $annotationDeletionResult->rowCount() . '<br>';
            if ($annotationDeletionResult->rowCount() == $numberofAnnotations) {
                print "Success <br>";
                $deletionSuccess['annotations'] = 'Deleted';
            } else {
                print "Failure";
                $deletionSuccess['annotations'] = 'Delete Failed';
            }
        }

        print '<pre>';
        print_r($deletionSuccess);
        print '</pre>';

        print "All annotations for {$_POST['clearAnnotationsForUser']} have been purged.";
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <style>

        </style>
        <script>

        </script>

    </head>
    <body>
        <h1>Delete all user annotations for the following user:</h1>
        <p>Be careful what you click! There is no confirmation and no going back!</p>
        <form method="POST">
            <input type="submit" name="clearAnnotationsForUser" value="Sophia"><br><br>
            <input type="submit" name="clearAnnotationsForUser" value="Barbara"><br><br>
            <input type="submit" name="clearAnnotationsForUser" value="Richard">
        </form>
    </body>
</html>

