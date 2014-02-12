<?php
require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;
$numberOfProjects = 0;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_GET['userType'])) {
  header('Location: login.php');
  exit;
}
$userType = $_GET['userType'];
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];
$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
switch ($userType) {
  case 'new':
    $bodyHTML = "<h2>Welcome to iCoast</h2>";
    break;
  case 'existing':
    $bodyHTML = "<h2>Welcome Back to iCoast</h2>";
    break;
  default:
    header('Location: login.php');
    break;
}
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$selectProjectButtonHTML = '';
$allProjects = array();
$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
foreach ($DBH->query($allProjectsQuery) as $row) {
  $allProjects[] = $row;
}
//$allProjectsMysqlResult = run_database_query($allProjectsQuery);
$numberOfProjects = count($allProjects);
if ($numberOfProjects >= 2) {

  $projectSelectOptionHTML = "";
  foreach ($allProjects as $project) {
    $id = $project['project_id'];
    $name = $project['name'];
    $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>";
  }
  $selectProjectButtonHTML = <<<EOL
      <form method="post" action="classification.php">
        <input type="hidden" name="userId" value="$userId" />
        <label for="projectIdSelect">
          <p>Select a project to annotate:</p>
        </label>
        <select id="projectIdSelect" name="projectId">
          $projectSelectOptionHTML
        </select><br>
        <input type="submit" id="" class="formButton" value="Classify Chosen Project" />
      </form>
EOL;
} else if ($numberOfProjects == 1) {
  $onlyProjectId = $allProjects[0]['project_id'];
  $onlyProjectName = $allProjects[0]['name'];
  $selectProjectButtonHTML = <<<EOL
      <form method="post" action="start.php" class="buttonForm">
        <input type="hidden" name="projectId" value="$onlyProjectId" />
        <input type="submit" id="continueClassifyingButton" class="formButton" value="Start Classifying $onlyProjectName" />
      </form>
EOL;
} else {
  $selectProjectButtonHTML = <<<EOL
      <h2>No Projects Available</h2>
      <p>There are no projects available for annotation at this time.<br>
        Please check back at a later date.</p>
EOL;
}
$bodyHTML .= <<<EOL
          <p>You are logged in as <span class="userData">{$userData['masked_email']}</span>.<br>
           If this is not your login then please log out using the button below and then sign back in
            with your own credentials.</p>
EOL;
switch ($userType) {
  case 'new':
    $bodyHTML .= <<<EOL
            <p>New user blurb</p>

            $selectProjectButtonHTML
EOL;
    break;

  case 'existing':
    $lastProjectId = null;
    $projectName = null;
    $bodyHTML .= '<h2>Your iCoast Statistics</h2>';



    $lastAnnotationQuery = "SELECT initial_session_end_time, project_id "
            . "FROM annotations WHERE user_id = :userId AND annotation_completed = 1 "
            . "ORDER BY initial_session_end_time DESC";
    $lastAnnotationParams['userId'] = $userId;
    $STH = run_prepared_query($DBH, $lastAnnotationQuery, $lastAnnotationParams);
    $annotations = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (count($annotations) > 0) {
      if ($numberOfProjects <= 1) {
        $selectProjectButtonHTML = '';
      }
      $numberOfAnnotations = count($annotations);
      $lastAnnotation = $annotations[0];
      $lastAnnotationTime = $lastAnnotation['initial_session_end_time'];
      $lastProjectId = $lastAnnotation['project_id'];

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


      $projectQuery = "SELECT name FROM projects WHERE project_id = :lastProjectId";
      $projectParams['lastProjectId'] = $lastProjectId;
      $STH = run_prepared_query($DBH, $projectQuery, $projectParams);
      $projectData = $STH->fetch(PDO::FETCH_ASSOC);
      $projectName = $projectData['name'];


      $bodyHTML.= <<<EOL

      <table>
        <tr><td>Total number of complete annotations:</td><td class="userData">$numberOfAnnotations</td></tr>
        <tr><td>Annotation Scoreboard Position:</td><td class="userData">
EOL;
      if ($positionInICoast == 1) {
        if ($jointPosition) {
          $bodyHTML .= "Joint 1st!</td></tr>";
        } else {
          $bodyHTML .= "1st! Great Job!</td></tr>";
        }
      } else if ($positionInICoast > 1) {
        if ($jointPosition) {
          $bodyHTML .= "Joint ";
        }
        $bodyHTML .= "$ordinalPositionInICoast</td></tr>";

        if ($positionInICoast == 2) {
          $bodyHTML .= "<tr><td>Complete annotations required to become 1st:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
        }

        if ($positionInICoast > 2) {
          $bodyHTML .= "<tr><td>Complete annotations required to move up a position:</td><td class=\"userData\">$annotationsToNext</td></tr>";
          $bodyHTML .= "<tr><td>Complete annotations required to become 1st:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
        }
      }
      $bodyHTML .= "<tr><td>Date and time of last complete annotation:</td><td class=\"userData\">$lastAnnotationTime</td></tr>"
              . "<tr><td>Last project annotated:</td><td class=\"userData\">$projectName</td></tr>"
              . "</table>";
      $bodyHTML .= <<<EOL
            <h2>What would you like to do next?</h2>
 <form method="post" action="start.php" class="buttonForm">
              <input type="hidden" name="projectId" value="$lastProjectId" />
              <input type="submit" id="continueClassifyingButton" class="formButton" value="Continue Classifying $projectName" />
            </form>

 $selectProjectButtonHTML
EOL;
    } else {
      $bodyHTML .= <<<EOL
                <p>You have not yet annotated any images</p>
EOL;
      $bodyHTML .= <<<EOL
      $selectProjectButtonHTML

EOL;
    }

    break;
}


?>

<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Welcome</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
    <link rel="stylesheet" href="css/icoast.css">
    <link rel="stylesheet" href="css/login.css">
  </head>
  <body id="wrapper">
    <div id = "loginWrapper">
      <h1>iCoast: Did the Coast Change?</h1>
      <?php print $bodyHTML; ?>
  </body>
</html>
