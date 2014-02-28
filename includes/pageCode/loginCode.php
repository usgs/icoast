<?php

$pageName = "login";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require 'includes/openid.php';
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (isset($_COOKIE['userId']) && isset($_COOKIE['authCheckCode'])) {
    $authResult = authenticate_cookie_credentials($DBH, $_COOKIE['userId'], $_COOKIE['authCheckCode'], FALSE);
    if ($authResult) {
        header('Location: welcome.php?userType=existing');
        exit;
    }
}

$openid = new LightOpenID('http://' . $_SERVER['HTTP_HOST']);

if (!$openid->mode) {
    $variableContent = <<<EOL
          <p>Help scientists at the U.S. Geological Survey annotate aerial photographs with keyword
                tags to identify changes to the coast after extreme storms like Hurricane Sandy. We
                need your eyes to help us understand how United States coastlines are changing from
                extreme storms.</p>
          <p>Click button below to Login or Register using a Google Account</p>
          <form action="?login" method="post">
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton"
                    value="Login or Register with Google" />
            </div>
          </form>
EOL;

    if (isset($_GET['login'])) {
        $openid->identity = 'https://www.google.com/accounts/o8/id';
        $openid->required = array('contact/email');
        header('Location: ' . $openid->authUrl());
    }
} elseif ($openid->mode == 'cancel') {
    $variableContent = <<<EOL
          <p>Authentication process was cancelled. Click the button below to start the login process again</p>
          <form action="?login" method="post">
                <button>Login or Register with Google</button>
          </form>
EOL;
} else {
    if (!$openid->validate()) {
        $variableContent = <<<EOL
          <p>Authentication failed. Click the button below to try again.</p>
          <form action="?login" method="post">
                <button>Login or Register with Google</button>
          </form>
EOL;
    } else {
        $user = $openid->getAttributes();
        $googleUserEmail = filter_var($user['contact/email'], FILTER_VALIDATE_EMAIL);
        if (!$googleUserEmail) {
//            Placeholder for error management
            print 'Error. Invalid eMail Address.<br>';
            exit;
        }
        $maskedUserEmail = mask_email($googleUserEmail);

        $queryStatement = "SELECT * FROM users WHERE masked_email = :maskedEmail";
        $queryParams['maskedEmail'] = $maskedUserEmail;
        $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
        $queryResult = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($queryResult) > 0) {
            $userFound = FALSE;
            foreach ($queryResult as $userCredentials) {
                $decryptedEmail = mysql_aes_decrypt($userCredentials['encrypted_email'], $userCredentials['encryption_data']);
                if (strcasecmp($decryptedEmail, $googleUserEmail) === 0) {
                    $userFound = TRUE;
                    $authCheckCode = md5(rand());

                    $queryStatement = "UPDATE users SET auth_check_code = :authCheckCode, last_logged_in_on = now() WHERE user_id = :userId";
                    $queryParams = array(
                        'authCheckCode' => $authCheckCode,
                        'userId' => $userCredentials['user_id']
                    );
                    $STH = run_prepared_query($DBH, $queryStatement, $queryParams);

                    if ($STH->rowCount() === 1) {
                        setcookie('userId', $userCredentials['user_id'], time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                        setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                        header('Location: welcome.php?userType=existing');
                        exit;
                    } else {
                        $variableContent =  <<<EOL
          <p>Appliaction Failure. Unable to contact database. Please try again in a few minutes or advise an administrator of this problem.</p>
          <form action="?login" method="post">
                <button>Login or Register with Google</button>
          </form>
EOL;
                    }
                }
            }
        }
        if (count($queryResult) === 0 || $userFound === FALSE) {
            setcookie('registrationEmail', $googleUserEmail, time() + 60 * 5, '/', '', 0, 1);
            header('Location: registration.php');
            exit;
        }
    }
}