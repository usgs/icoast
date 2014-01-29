<?php
require '../iCoastSecure/DBMSConnection.php';
require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
$error = false;
$numberOfProjects = 0;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_GET['userType'])) {
  header('Location: login.php');
  exit;
}
$userType = $_GET['userType'];
$userId = escape_string($_COOKIE['userId']);
$authCheckCode = escape_string($_COOKIE['authCheckCode']);
$authQuery = "SELECT * FROM users WHERE user_id = '$userId' AND auth_check_code = '$authCheckCode' LIMIT 1";
$authMysqlResult = run_database_query($authQuery);
if ($authMysqlResult && $authMysqlResult->num_rows == 0) {
  header('Location: login.php');
  exit;
} else {
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
  $userData = $authMysqlResult->fetch_assoc();
  $authCheckCode = md5(rand());
  $query = "UPDATE users SET auth_check_code = '$authCheckCode', last_logged_in_on = now() "
          . "WHERE user_id = '$userId'";
  $mysqlResult = run_database_query($query);
  if ($mysqlResult) {
    setcookie('userId', $userId, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
    setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
  } else {
    $error = true;
    $bodyHTML .= <<<EOL
          <p>Appliaction Failure. Unable to contact database. Please try again in a few minutes or advise an administrator of this problem.</p>
          <form action="login.php" method="post">
            <input type="submit" value="Login / Register using Google" />
          </form>

EOL;
  }
}
if (!$error) {
//  $logoutButtonHTML = <<<EOL
//      <form method="post" action="logout.php" class="buttonForm">
//        <input type="hidden" name="userId" value="$userId" />
//        <input type="submit" id="appLogoutButton" class="formButton" value="Logout" />
//      </form>
//EOL;

  $selectProjectButtonHTML = '';
  $allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
  $allProjectsMysqlResult = run_database_query($allProjectsQuery);
  if ($allProjectsMysqlResult) {
    while ($row = $allProjectsMysqlResult->fetch_assoc()) {
      $allProjects[] = $row;
    }
    $numberOfProjects = count($allProjects);
    if ($numberOfProjects > 1) {

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
    } else {
      $onlyProjectId = $allProjects[0]['project_id'];
      $onlyProjectName = $allProjects[0]['name'];
      $selectProjectButtonHTML = <<<EOL
      <form method="post" action="classification.php" class="buttonForm">
        <input type="hidden" name="projectId" value="$onlyProjectId" />
        <input type="hidden" name="userId" value="$userId" />
        <input type="submit" id="continueClassifyingButton" class="formButton" value="Start Classifying $onlyProjectName" />
      </form>
EOL;
    }
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
            <h2>What would you like to do next?</h2>
            $selectProjectButtonHTML
EOL;
      break;

    case 'existing':
      $lastProjectId = null;
      $projectName = null;
      $bodyHTML .= '<h2>Your iCoast Statistics</h2>';
      $lastAnnotationQuery = "SELECT initial_session_end_time, project_id "
              . "FROM annotations WHERE user_id = '$userId' AND annotation_completed = 1 "
              . "ORDER BY initial_session_end_time DESC";
      $lastAnnotationMysqlResult = run_database_query($lastAnnotationQuery);
      if ($lastAnnotationMysqlResult && $lastAnnotationMysqlResult->num_rows > 0) {
        if ($numberOfProjects <= 1) {
          $selectProjectButtonHTML = '';
        }
        $numberOfAnnotations = $lastAnnotationMysqlResult->num_rows;
        $lastAnnotation = $lastAnnotationMysqlResult->fetch_assoc();
        $lastAnnotationTime = $lastAnnotation['initial_session_end_time'];
        $lastProjectId = $lastAnnotation['project_id'];

        $setAnnotationCountQuery = "UPDATE users SET completed_annotation_count = $numberOfAnnotations WHERE user_id = $userId";
        $setAnnotationCountMysqlResult = run_database_query($setAnnotationCountQuery);
        if ($setAnnotationCountMysqlResult) {

          $positionQuery = "SELECT completed_annotation_count FROM users WHERE completed_annotation_count > $numberOfAnnotations "
                  . "ORDER BY completed_annotation_count DESC";
          $positionMysqlResult = run_database_query($positionQuery);
          if ($positionMysqlResult) {
            $positionInICoast = $positionMysqlResult->num_rows + 1;
            $ordinalPositionInICoast = ordinal_suffix($positionInICoast);
            if ($positionMysqlResult->num_rows > 0) {
              while ($row = $positionMysqlResult->fetch_assoc()) {
                $annotaionPositions[] = $row;
              }
              $annotationsToFirst = $annotaionPositions[0]['completed_annotation_count'] - $numberOfAnnotations;
              $annotationsToNext = $annotaionPositions[$positionInICoast - 2]['completed_annotation_count'] - $numberOfAnnotations;
              $nextPosition = ordinal_suffix($positionInICoast - 1);
            }
          }

          $projectQuery = "SELECT name FROM projects WHERE project_id = $lastProjectId";
          $projectMysqlResult = run_database_query($projectQuery);
          $projectName = "";
          if ($projectMysqlResult && $projectMysqlResult->num_rows > 0) {
            $projectData = $projectMysqlResult->fetch_assoc();
            $projectName = $projectData['name'];
          }
        }


        $bodyHTML.= <<<EOL

      <table>
      <tr><td>Total number of complete annotations:</td><td class="userData">$numberOfAnnotations</td></tr>

EOL;
        if ($positionInICoast == 1) {
          $bodyHTML .= "<tr><td>Annotation Scoreboard Position:</td><td class=\"userData\">1st! Great Job!</td></tr>";
        }
        if ($positionInICoast > 1) {
          $bodyHTML .= "<tr><td>Annotation Scoreboard Position:</td><td class=\"userData\">$ordinalPositionInICoast</td></tr>"
                  . "<tr><td>Complete annotations required to become $nextPosition:</td><td class=\"userData\">$annotationsToNext</td></tr>";
          if ($positionInICoast > 2) {
            $bodyHTML .= "<tr><td>Complete annotations required to become 1st:</td><td class=\"userData\">$annotationsToFirst</td><tr>";
          }
        }
//          if ($numberOfAnnotations > 0) {
            $bodyHTML .= "<tr><td>Date and time of last complete annotation:</td><td class=\"userData\">$lastAnnotationTime</td></tr>"
                    . "<tr><td>Last project annotated:</td><td class=\"userData\">$projectName</td></tr>"
                    . "</table>";
            $bodyHTML .= <<<EOL
            <h2>What would you like to do next?</h2>
 <form method="post" action="classification.php" class="buttonForm">
              <input type="hidden" name="projectId" value="$lastProjectId" />
              <input type="hidden" name="userId" value="$userId" />
              <input type="submit" id="continueClassifyingButton" class="formButton" value="Continue Classifying $projectName" />
            </form>
 $selectProjectButtonHTML

EOL;
//          }
      } else {
        $bodyHTML .= <<<EOL
                <p>You have not yet annotated any images</p>
EOL;
        $bodyHTML .= <<<EOL
        <h2>What would you like to do next?</h2>
      $selectProjectButtonHTML

EOL;
      }

      break;
  }
}







ob_start();
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

<?php
ob_end_flush();
