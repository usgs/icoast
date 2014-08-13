<?php

require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);

if (isset($_COOKIE['userId'])) {
  generate_cookie_credentials($DBH, $_COOKIE['userId'], TRUE);
}

