<?php
$pageName = "logout";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';


require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (isset($_COOKIE['userId'])) {
  generate_cookie_credentials($DBH, $_COOKIE['userId'], TRUE);
}

