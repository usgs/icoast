<?php

$pageName = "home";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$userData = FALSE;


require 'includes/openid.php';
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (isset($_COOKIE['userId']) && isset($_COOKIE['authCheckCode'])) {

    $userId = $_COOKIE['userId'];
    $authCheckCode = $_COOKIE['authCheckCode'];

    $userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
}

if ($userData) {
    $authCheckCode = generate_cookie_credentials($DBH, $userId);
    $buttonHTML = <<<EOL
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="enterICoastButton"
                    value="Enter iCoast and Start Tagging" title="You are already logged in. Click to enter the iCoast Application" />
            </div>
EOL;
} else {
    $buttonHTML = <<<EOL
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton"
                    value="Login or Register with Google" title="Click to begin iCoast login using an account
                    authenticated by Google (examples of accounts that can be used: aperson@gmail.com, aperson@usgs.gov)" />
            </div>
EOL;
}

$loginAccountInfoText = <<<EOL
    <p>NOTE: Any Google based account can be used for iCoast registration. This could be a standard gmail
        account or one managed by you or your organization (examples: aperson@gmail.com, aperson@usgs.gov).</p>
    <p>USGS has no access to your Google account other than receiving your email address upon registration.
        USGS does not share any information you provide to iCoast with Google.</p>
EOL;

$openid = new LightOpenID('http://' . $_SERVER['HTTP_HOST']);

if (!$openid->mode) {
    if ($userData) {
        $variableContent = <<<EOL
          <p>You are already logged in as <span class="userData">{$userData['masked_email']}</span>.</p>
          <p>Click the button below to enter iCoast.</p>
          <form action="welcome.php" method="get">
            <input type="hidden" name="userType" value="existing" />
            $buttonHTML
          </form>
EOL;
    } else {
        $variableContent = <<<EOL
          <p>Click the button below to <span class="italic">Login</span> or <span class="italic">Register</span>
              using a Google Account</p>
          <form action="?login" method="post">
            $buttonHTML
          </form>
          $loginAccountInfoText
EOL;
    }

    if (isset($_GET['login'])) {
        $openid->identity = 'https://www.google.com/accounts/o8/id';
        $openid->required = array('contact/email');
        header('Location: ' . $openid->authUrl());
    }
} elseif ($openid->mode == 'cancel') {
    $variableContent = <<<EOL
          <p class="loginError">Authentication process was cancelled. Click the button below to start the login process again</p>
          <form action="?login" method="post">
                $buttonHTML
          </form>
          $loginAccountInfoText
EOL;
} else {
    if (!$openid->validate()) {
        $variableContent = <<<EOL
          <p class="loginError">Authentication failed. Click the button below to try again.</p>
          <form action="?login" method="post">
                $buttonHTML
          </form>
          $loginAccountInfoText
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
                        $variableContent = <<<EOL
          <p class="loginError">Appliaction Failure. Unable to contact database. Please try again in a few minutes or advise an administrator of this problem.</p>
          <form action="?login" method="post">
                $buttonHTML
          </form>
          $loginAccountInfoText
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


$javaScript = <<<EOL


    function loadIndexImageContent (targetImage) {

        if (targetImage == 'next') {
            loadImage ++;
        }

        if (loadImage >= numberOfImages) {
            loadImage = 0;
        }

        var image = 'images/system/indexImages/' + indexImages[loadImage][0];
        var altTag = indexImages[loadImage][3];
        var captionTitle = indexImages[loadImage][1];
        var caption = indexImages[loadImage][2];

        switch (targetImage) {
        case 'displayed':
            $('#indexImageWrapper img:first-of-type').attr('src', image);
            $('#indexImageWrapper img:first-of-type').attr('alt', altTag)
            $('#captionTitle').text(captionTitle);
            $('#captionText').text(caption);
            break;
        case 'next':
            $('#indexImageWrapper img:last-of-type').attr('src', image);
            $('#indexImageWrapper img:last-of-type').attr('atr', altTag);
            nextCaptionTitle = indexImages[loadImage][1];
            nextCaption = indexImages[loadImage][2];
            break;
        }
    }

    function slideNewImage() {
        $('#imageCaptionWrapper').slideUp(500,
            function() {
                $('#captionTitle').text(nextCaptionTitle);
                $('#captionText').text(nextCaption);
                $('#imageCaptionWrapper').slideDown(500);
            });
        $('#indexImageWrapper img:first-of-type').animate({
            left: -670
        }, 1000);
        $('#indexImageWrapper img:last-of-type').animate({
            left: 0
        }, 1000, function() {
            $('#indexImageWrapper img:first-of-type').remove();
            $('#indexImageWrapper').append('<img src="" alt="" height="435" width="670" title="" />');
            loadIndexImageContent('next');
        });
    }

    var indexImages = [
        [
            'seasideHeights.jpg',
            'Welcome!',
            'iCoast is a USGS crowdsourcing application created for you to help us better understand how ' +
                'coastlines change after extreme storms.',
            'An image of the pier at Seaside Heights, New Jersey following Hurricane Sandy. The end has been ' +
                'washed away by the storm.'
        ],
        [
            'karen.jpg',
            'Help!',
            'Become a volunteer to help USGS scientists look for coastal changes in their oblique aerial ' +
                'photographs they have taken since 1995.',
            'An image showing a member of USGS photographing the coastline from the cabin of a light aircraft.'
        ],
        [
            'mantoloking.jpg',
            'Witness!',
            'Fly along the coast with us and witness the real world impacts of extreme storms affecting our ' +
                'coastlines.',
            'An image of severe coastal change at Mantoloking, New Jersey following Hurricane Sandy. A new ' +
                'inlet has been cut by the storm resulting in loss of housing and road infrastructure.'
        ],
        [
            'map.jpg',
            'Explore!',
            'Look at aerial photographs of the coast you never knew existed, or use the map to find aerial ' +
                'imagery of places familiar to you.',
            'An image of the iCoast map interface that is used to select specific images to tag.'
        ],
        [
            'classify.jpg',
            'Volunteer!',
            'Tag coastal aerial imagery with pre-defined keywords to help us identify and map coastal change ' +
                'processes after extreme storms.',
            'A screenshot of the iCoast application\'s classification page where users can compare images and ' +
                'log what they see.'
        ],
        [
            'predictions.jpg',
            'Improve!',
            'Help ground truth and improve USGS predictive models of coastal changes to inform evacuation, ' +
                'preparedness, and mitigation efforts.',
            'An image showing a graphical representation of the USGS coastal erosion prediction models for the ' +
                'north east United States coastline.'
        ],
        [
            'breach.jpg',
            'Respect!',
            'See before and after images of the coast to understand and build a respect for the real dangers ' +
                'of extreme storms.',
            'An image showing the effect of a coastal process called Inundation. An entire barrier island has ' +
                'been destroyed with almost complete loss of all housing and transportation infrastructure.'
        ],
        [
            'learn.jpg',
            'Learn!',
            'iCoast is designed to also educate the public about how extreme storms can threaten homes, ' +
                'businesses, and infrastructure on our Nation’s coast.',
            'An image showing a popup from the iCoast interface describing a coastal change process.'
        ]
    ];

    var loadImage = 0;
    var numberOfImages = indexImages.length;
    var nextCaptionTitle;
    var nextCaption;

EOL;

$jQueryDocumentDotReadyCode = <<<EOL
        loadIndexImageContent('displayed');
        loadIndexImageContent('next');
        setInterval(slideNewImage, 15000);
EOL;
