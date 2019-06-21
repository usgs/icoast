<?php
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, false);

if ($userData) {
  header("Location: index.php?userType=existing");
  exit;
}

if (isset($_GET['continue'])) {
  $CSRFToken = md5(rand());
  setcookie('CSRFToken', $CSRFToken);
  header("Location: https://accounts.google.com/o/oauth2/auth?"
         . "response_type=code&"
         . "client_id=$googleOAuthClientID&"
         . "redirect_uri=$googleOAuthRedirectURI&"
         . "scope=email&"
         . "access_type=online&"
         . "state=$CSRFToken");
  exit;
}

if (isset($_GET['error'])) {
  if ($_GET['error'] == 'access_denied') {
    header("Location: index.php?error=canceled");
  } else {
    header("Location: index.php?error={$_GET['error']}");
  }
  exit;
}

if (isset($_GET['code'])) {
//        print $googleOAuthClientID . "\r\n";
//    print $googleOAuthSecret . "\r\n";
//    print $googleOAuthRedirectURI . "\r\n";
//    exit;

  $userCSRFToken = $_COOKIE['CSRFToken'];
  $responseCSRFToken = $_GET['state'];
  setcookie('CSRFToken', '', time() - 360 * 24, '/', '', 0, 1);

  if ($userCSRFToken != $responseCSRFToken) {
    header("Location: index.php?error=CSRFToken");
    exit;
  }

  $responseCode = $_GET['code'];
  $postParamString = "code=$responseCode&";
  $postParamString .= "client_id=$googleOAuthClientID&";
  $postParamString .= "client_secret=$googleOAuthSecret&";
  $postParamString .= "redirect_uri=$googleOAuthRedirectURI&";
  $postParamString .= "grant_type=authorization_code";

  $cURLPostRequest = curl_init('https://www.googleapis.com/oauth2/v3/token');
  curl_setopt($cURLPostRequest, CURLOPT_POST, true);
  curl_setopt($cURLPostRequest, CURLOPT_POSTFIELDS, $postParamString);
  curl_setopt($cURLPostRequest, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($cURLPostRequest, CURLOPT_SSL_VERIFYPEER, false);
  $curlResponse = curl_exec($cURLPostRequest);
  curl_close($cURLPostRequest);

  if ($curlResponse == false) {
    header("Location: index.php?error=IDTokenExchange");
    exit;
  }

  $authResponse = json_decode($curlResponse, true);
  $authIDToken = explode('.', $authResponse['id_token']);
  $authIDTokenPayload = json_decode(base64_decode($authIDToken[1]), true);
  $userEmail = $authIDTokenPayload['email'];

  $googleUserEmail = filter_var($userEmail, FILTER_VALIDATE_EMAIL);
  if (!$googleUserEmail) {
    header("Location: index.php?error=invalidEmail");
    exit;
  }
  $maskedUserEmail = mask_email($googleUserEmail);

  $queryStatement = "SELECT * FROM users WHERE masked_email = :maskedEmail";
  $queryParams['maskedEmail'] = $maskedUserEmail;
  $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
  $queryResult = $STH->fetchAll(PDO::FETCH_ASSOC);

  if (count($queryResult) > 0) {
    foreach ($queryResult as $userCredentials) {
      $decryptedEmail = mysql_aes_decrypt($userCredentials['encrypted_email'], $userCredentials['encryption_data']);
      if (strcasecmp($decryptedEmail, $googleUserEmail) === 0) {

        $cookieGenerationSuccess = generate_cookie_credentials($DBH, $userCredentials['user_id']);

        if ($cookieGenerationSuccess) {
          header('Location: index.php?userType=existing');
          exit;
        } else {
          header("Location: index.php?error=databaseConnection");
          exit;
        }
      }
    }
  }
  setcookie('registrationEmail', $googleUserEmail, time() + 60 * 5, '/', '', 0, 1);
  header('Location: registration.php');
  exit;
}

$jQueryDocumentDotReadyCode = <<<EOL
  $('#cancelButton').click(function() {
      window.location.replace("index.php");
  });
  $('#continueButton').click(function() {
      window.location.replace("login.php?continue");
  });
EOL;


