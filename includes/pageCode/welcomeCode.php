<?php

require_once('includes/userFunctions.php');
require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);

if (!isset($_GET['userType'])) {
    header('Location: index.php');
    exit;
}
$userType = $_GET['userType'];
$userEmail = $userData['masked_email'];

$startTaggingButtonHTML = <<<EOL
      <form method="post" action="start.php" class="buttonForm">
        <input type="submit" id="continueClassifyingButton" class="clickableButton formButton"
            value="Start Tagging Photos"
            title="Click to begin the classification process and start tagging."/>
      </form>
EOL;


switch ($userType) {
    case 'new':
        $welcomeBackHTML = '';
        $variableContent = <<<EOL
        <h2>Thanks for joining USGS iCoast</h2>
        <p>Check out the first iCoast project showing aerial photographs taken after Hurricane Sandy.</p>

            $startTaggingButtonHTML
EOL;
        break;
    case 'existing':
        $welcomeBackHTML = 'Back';
        $lastProjectId = null;
        $projectName = null;
        $variableContent = '<h2>Your USGS iCoast Statistics</h2>';

        $annotationQuery = "SELECT annotation_id, initial_session_end_time, project_id "
                . "FROM annotations WHERE user_id = :userId AND annotation_completed = 1 "
                . "ORDER BY initial_session_end_time DESC";
        $annotationParams['userId'] = $userId;
        $STH = run_prepared_query($DBH, $annotationQuery, $annotationParams);
        $annotations = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($annotations) > 0) {

            $numberOfAnnotations = count($annotations);
            $lastAnnotation = $annotations[0];
            $lastProjectId = $lastAnnotation['project_id'];
            $formattedLastAnnotationTime =
                    formattedTime($lastAnnotation['initial_session_end_time'], $userData['time_zone']);

            if ($numberOfAnnotations != $userData['completed_annotation_count']) {
                $setAnnotationCountQuery = "UPDATE users SET completed_annotation_count = :numberOfAnnotations WHERE user_id = :userId";
                $setAnnotationCountParams = array(
                    'userId' => $userId,
                    'numberOfAnnotations' => $numberOfAnnotations,
                );

                $STH = run_prepared_query($DBH, $setAnnotationCountQuery, $setAnnotationCountParams);
                if ($STH->rowCount() == 0) {
                    //  Placeholder for error management
                    print 'User Annotation Count Update Error: Update did not complete sucessfully.';
                    exit;
                }
            }

            foreach ($annotations as $annotation) {
                $annotationIdArray[] = $annotation['annotation_id'];
            }
            $whereInString = where_in_string_builder($annotationIdArray);
            $numberOfTotalTagsQuery = "SELECT COUNT(*) FROM annotation_selections WHERE annotation_id"
                    . " IN ($whereInString)";
            $numberOfTotalTagsParams = array();
            $STH = run_prepared_query($DBH, $numberOfTotalTagsQuery, $numberOfTotalTagsParams);
            $numberOfTotalTags = $STH->fetchColumn();


            $positionQuery = "SELECT completed_annotation_count FROM users WHERE completed_annotation_count > :numberOfAnnotations "
                    . "ORDER BY completed_annotation_count DESC";
            $positionParams['numberOfAnnotations'] = $numberOfAnnotations;
            $STH = run_prepared_query($DBH, $positionQuery, $positionParams);
            $annotaionPositions = $STH->fetchAll(PDO::FETCH_ASSOC);
            $positionInICoast = count($annotaionPositions) + 1;
            $ordinalPositionInICoast = ordinal_suffix($positionInICoast);

            $jointQuery = "SELECT COUNT(*) FROM users WHERE completed_annotation_count = $numberOfAnnotations";
            $jointParams['numberOfAnnotations'] = $numberOfAnnotations;
            $STH = run_prepared_query($DBH, $jointQuery, $jointParams);
            if ($STH->fetchColumn() > 1) {
                $jointPosition = TRUE;
            } else {
                $jointPosition = FALSE;
            }

            if ($positionInICoast > 1) {
                $annotationsToFirst = $annotaionPositions[0]['completed_annotation_count'] - $numberOfAnnotations + 1;
                $annotationsToNext = $annotaionPositions[$positionInICoast - 2]['completed_annotation_count'] - $numberOfAnnotations;
                $nextPosition = ordinal_suffix($positionInICoast - 1);
            }

            $positionHTML = '';
            $advancementHTML = '';
            if ($positionInICoast == 1) {
                if ($jointPosition) {
                    $positionHTML = "Joint 1st Place";
                } else {
                    $positionHTML = "1st Place - Top iCoast Tagger!";
                }
            } else if ($positionInICoast > 1) {
                if ($jointPosition) {
                    $positionHTML = "Joint ";
                }
                $positionHTML .= "$ordinalPositionInICoast Place";
            }
            if ($positionInICoast == 2) {
                $advancementHTML = "<tr><td title=\"The number of photos you need to tag to become the iCoast
                        Top Tagger.\"># of Photos to be 1st:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
            }

            if ($positionInICoast > 2) {
                $advancementHTML = "<tr><td title =\"The number of photos you need to tag to climb up the"
                        . " leaderboard by one position\"># of Photos to Move up a Position:</td><td class=\"userData\">$annotationsToNext</td></tr>\n" .
                        "<tr><td># of Photos to Reach 1st Place:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
            }



            $projectQuery = "SELECT name FROM projects WHERE project_id = :lastProjectId";
            $projectParams['lastProjectId'] = $lastProjectId;
            $STH = run_prepared_query($DBH, $projectQuery, $projectParams);
            $projectData = $STH->fetch(PDO::FETCH_ASSOC);
            $projectName = $projectData['name'];


            $variableContent.= <<<EOL
                <table class="statisticsTable">
                    <tr><td title="This is your position on the leaderboard out of all registered users in
                        iCoast. The more photos you tag the higher you will climb. Try to become the iCoast
                            Top Tagger!">Leaderboard Position:</td><td class="userData">$positionHTML</td></tr>
                    <tr><td title="This is the total number of photos you have tagged in iCoast across all
                        projects. Only complete annotations count meaning you had to view all tasks and
                        clicked the final Done button. Use the profile page to see any incomplete annotations
                        you might have.">
                        # of Photos Tagged:</td><td class="userData">$numberOfAnnotations</td></tr>

                    $advancementHTML
                    <tr><td title="This is the total number of tags you have selected in iCoast photos across all
                        projects."># of Tags in Total:</td><td class="userData">$numberOfTotalTags</td></tr>
                    <tr><td title="The last iCoast project that you tagged was the $projectName project.">
                        Last Project Annotated:</td><td class="userData">$projectName</td></tr>
                    <tr><td title="The local time you submitted your last complete annotation">
                        Last Annotation:</td><td class="userData">$formattedLastAnnotationTime</td></tr>
                </table>
              $startTaggingButtonHTML
EOL;
        } else {
            $variableContent .= <<<EOL
                <p>You have not yet tagged any post-storm photographs.<br>Click the <span class="italic">
                    Start Tagging</span> button below to tag aerial photographs taken after Hurricane Sandy.
                    <br>See if you can tag more photos than other iCoast users.</p>
                $startTaggingButtonHTML
EOL;
        }
        break;
    default:
        header('Location: index.php');
        break;
}

$jQueryDocumentDotReadyCode = <<<EOL
        $('td:first-of-type').tipTip();
EOL;
