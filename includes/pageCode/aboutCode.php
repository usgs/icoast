<?php

$pageName = "about";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

$userData = FALSE;

if (isset($_COOKIE['userId']) && isset($_COOKIE['authCheckCode'])) {

    $userId = $_COOKIE['userId'];
    $authCheckCode = $_COOKIE['authCheckCode'];

    $userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
    if ($userData) {
        $authCheckCode = generate_cookie_credentials($DBH, $userId);
    }
}
