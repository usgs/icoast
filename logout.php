<?php
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (isset($_COOKIE['userId'])) {
  setcookie('userId', '', time() - 360 * 24, '/', '', 0, 1);
}
if (isset($_COOKIE['authCheckCode'])) {
  setcookie('authCheckCode', '', time() - 360 * 24, '/', '', 0, 1);
}
if (isset($_POST['userId']) && is_numeric($_POST['userId'])) {
  $userId = $_POST['userId'];
  $authCheckCode = md5(rand());
  $query = "UPDATE users SET auth_check_code = '$authCheckCode', last_logged_in_on = now() "
          . "WHERE user_id = '$userId'";
  $mysqlResult = run_database_query($query);
}
?>

<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Logout</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
    <link rel="stylesheet" href="css/icoast.css">
    <link rel="stylesheet" href="css/login.css">
  </head>
  <body id="wrapper">
    <div id = "loginWrapper">
      <h1>iCoast: Did it Change?</h1>
      <h2>Logout</h2>
      <p>You have successfully logged out of iCoast.</p>
      <form method="post" action="login.php">
        <input type="submit" class = "formButton" value="Log Back In To iCoast" />
      </form>
  </body>
</html>