<?php

$pageName = "welcome";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_GET['userType'])) {
    header('Location: login.php');
    exit;
}

$userType = $_GET['userType'];
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$userEmail = $userData['masked_email'];

$selectProjectButtonHTML = '';
$allProjects = array();
$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
foreach ($DBH->query($allProjectsQuery) as $row) {
    $allProjects[] = $row;
}
$numberOfProjects = count($allProjects);
if ($numberOfProjects > 1) {
    $projectSelectOptionHTML = "";
    foreach ($allProjects as $project) {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>/r/n";
    }
    $selectProjectButtonHTML = <<<EOL
      <form method="post" action="start.php">
          <input type="hidden" name="userId" value="$userId" />
          <label for="projectIdSelect">
              <p>Select a project to annotate:</p>
          </label>
          <div class="formFieldRow standAloneFormElement">
            <select id="projectIdSelect" name="projectId">
              $projectSelectOptionHTML
            </select>
          </div>
          <div class="formFieldRow standAloneFormElement">
            <input type="submit" id="" class="clickableButton formButton" value="Tag Chosen Project" />
          </div>
      </form>
EOL;
} else if ($numberOfProjects == 1) {
    $onlyProjectId = $allProjects[0]['project_id'];
    $onlyProjectName = $allProjects[0]['name'];
    $selectProjectButtonHTML = <<<EOL
      <form method="post" action="start.php" class="buttonForm">
        <input type="hidden" name="projectId" value="$onlyProjectId" />
        <input type="submit" id="continueClassifyingButton" class="clickableButton formButton" value="Start Tagging $onlyProjectName Project" />
      </form>
EOL;
} else {
    $selectProjectButtonHTML = <<<EOL
      <h2>No Projects Available</h2>
      <p>There are no projects available for annotation at this time.<br>
        Please check back at a later date.</p>
EOL;
}




switch ($userType) {
    case 'new':
        $welcomeBackHTML = '';
        $variableContent = <<<EOL
        <p>Thank you for your interest in signing up to iCoast. We are developing tutorials
          and other instructional materials to make it easier to get started. In the meantime,
            check out the first iCoast project showing aerial photographs taken after
              Hurricane Sandy.</p>

            $selectProjectButtonHTML
EOL;
        break;
    case 'existing':
        $welcomeBackHTML = 'Back';
        $lastProjectId = null;
        $projectName = null;
        $variableContent = '<h2>Your iCoast Statistics</h2>';

        $annotationQuery = "SELECT annotation_id, initial_session_end_time, project_id "
                . "FROM annotations WHERE user_id = :userId AND annotation_completed = 1 "
                . "ORDER BY initial_session_end_time DESC";
        $annotationParams['userId'] = $userId;
        $STH = run_prepared_query($DBH, $annotationQuery, $annotationParams);
        $annotations = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($annotations) > 0) {
            if ($numberOfProjects <= 1) {
                $selectProjectButtonHTML = '';
            }

            $numberOfAnnotations = count($annotations);
            $lastAnnotation = $annotations[0];
            $lastProjectId = $lastAnnotation['project_id'];
            $lastAnnotationTime = new DateTime($lastAnnotation['initial_session_end_time'], new DateTimeZone('UTC'));
            $lastAnnotationTime->setTimezone(new DateTimeZone('America/New_York'));
            $formattedLastAnnotationTime = $lastAnnotationTime->format('F j\, Y \a\t g:i A');

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
                $advancementHTML = "<tr><td># of Photos to Reach 1st Place:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
            }

            if ($positionInICoast > 2) {
                $advancementHTML = "<tr><td># of Photos to Move up a Position:</td><td class=\"userData\">$annotationsToNext</td></tr>\n" .
                        "<tr><td># of Photos to Reach 1st Place:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
            }



            $projectQuery = "SELECT name FROM projects WHERE project_id = :lastProjectId";
            $projectParams['lastProjectId'] = $lastProjectId;
            $STH = run_prepared_query($DBH, $projectQuery, $projectParams);
            $projectData = $STH->fetch(PDO::FETCH_ASSOC);
            $projectName = $projectData['name'];


            $variableContent.= <<<EOL
                <table>
                    <tr><td>Scoreboard Position:</td><td class="userData">$positionHTML</td></tr>
                    <tr><td># of Photos Tagged:</td><td class="userData">$numberOfAnnotations</td></tr>
                    <tr><td># of Tags in Total:</td><td class="userData">$numberOfTotalTags</td></tr>
                    $advancementHTML
                    <tr><td>Most Recent Annotation:</td><td class="userData">$formattedLastAnnotationTime</td></tr>
                </table>
                <form method="post" action="start.php" class="buttonForm">
                    <input type="hidden" name="projectId" value="$lastProjectId" />
                    <div class="formFieldRow standAloneFormElement">
                        <input type="submit" id="continueClassifyingButton" class="clickableButton formButton" value="Continue Tagging $projectName Project" />
                    </div>
                </form>
              $selectProjectButtonHTML
EOL;
        } else {
            $variableContent .= <<<EOL
                <p>You have not yet annotated any POST-storm photographs. Click the button below to
                  Start Tagging aerial photographs taken after Hurricane Sandy. See if you can tag
                  more photos than other iCoast users.</p>
                $selectProjectButtonHTML
EOL;
        }
        break;
    default:
        header('Location: login.php');
        break;
}