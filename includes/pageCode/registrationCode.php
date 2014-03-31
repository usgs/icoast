<?php

$pageName = "registration";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";
$javaScript = '';

require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['registrationEmail'])) {
    header("Location: index.php");
    exit;
} else {
    $userEmail = $_COOKIE['registrationEmail'];
}

$timeZone1HTML = '';
$timeZone2HTML = '';
$timeZone3HTML = '';
$timeZone4HTML = '';
$timeZone5HTML = '';
$timeZone6HTML = '';
$timeZone7HTML = '';
$timeZone8HTML = '';
$crowdType1HTML = '';
$crowdType2HTML = '';
$crowdType3HTML = '';
$crowdType4HTML = '';
$crowdType5HTML = '';
$crowdType6HTML = '';
$crowdType7HTML = '';
$crowdType8HTML = '';
$crowdType9HTML = '';
$crowdType10HTML = '';
$timeZoneError = '';
$crowdTypeError = '';
$otherCrowdTypeError = '';
$registerAffiliationError = '';
$registerOtherContent = '';
$registerAffiliationContent = '';



if (isset($_POST['submission']) && $_POST['submission'] == 'register') {
    $registerEmail = (isset($_POST['registerEmail'])) ? strtolower(trim($_POST['registerEmail'])) : null;
    $registerEmail = filter_var($registerEmail, FILTER_VALIDATE_EMAIL);
    if (!$registerEmail) {
        //  Placeholder for error management
        print 'Error. Invalid eMail Address.<br>';
        exit;
    }
    $registerTimeZone = (isset($_POST['registerTimeZone'])) ? trim($_POST['registerTimeZone']) : null;
    $registerCrowdType = (isset($_POST['registerCrowdType'])) ? trim($_POST['registerCrowdType']) : null;
    $registerOtherContent = (!empty($_POST['registerOther'])) ? trim($_POST['registerOther']) : '';
    $registerAffiliationContent = (!empty($_POST['registerAffiliation'])) ? trim($_POST['registerAffiliation']) : '';

    if (empty($registerTimeZone)) {
        $errorMessage['timeZone'] = 'You must select your time zone to complete registration.';
    } else {
        if ($registerTimeZone < 1 || $registerTimeZone > 8) {
            $errorMessage['timeZone'] = 'The specified time zone is invalid.';
        }
    }

    if (empty($registerCrowdType)) {
        $errorMessage['crowdType'] = 'You must select your crowd type to complete registration.';
    } else {
        if ($registerCrowdType < 0 || $registerCrowdType > 10) {
            $errorMessage['crowdType'] = 'The specified crowd type is invalid.';
        }
    }

    if ($registerCrowdType == 10 && empty($registerOtherContent)) {
        $errorMessage['otherCrowdType'] = 'You must specify your other crowd type if "Other" is selected in the crowd type list.';
    } elseif (!empty($registerOtherContent) && strlen($registerOtherContent) > 255) {
        $errorMessage['otherCrowdType'] = 'Your specified other crowd type is too long for registration (max 255 characters).';
    }

    if (!empty($registerAffiliationContent)) {
        if (strlen($registerAffiliationContent) > 255) {
            $errorMessage['affiliation'] = 'Your specified affiliation is too long (max 255 characters).';
        }
    }

    if (isset($errorMessage['timeZone'])) {
        $timeZoneError = '<label class="error" for="registerTimeZone">' . $errorMessage['timeZone'] . '</label>';
    }

    if (isset($errorMessage['crowdType'])) {
        $crowdTypeError = '<label class="error" for="registerCrowdType">' . $errorMessage['crowdType'] . '</label>';
    }

    if (isset($errorMessage['otherCrowdType'])) {
        $otherCrowdTypeError = '<label class="error" for="registerCrowdType">' . $errorMessage['otherCrowdType'] . '</label>';
    }

    if (isset($errorMessage['affiliation'])) {
        $registerAffiliationError = '<label class="error" for="registerCrowdType">' . $errorMessage['affiliation'] . '</label>';
    }


    switch ($timeZone) {
        case 1;
            $timeZone1HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 2;
            $timeZone2HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 3;
            $timeZone3HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 4;
            $timeZone4HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 5;
            $timeZone5HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 6;
            $timeZone6HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 7;
            $timeZone7HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
        case 8;
            $timeZone8HTML = 'selected="selected"';
            $stickyTimeZone = TRUE;
            break;
    }


    if (isset($registerCrowdType) && $registerCrowdType == 1) {
        $crowdType1HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 2) {
        $crowdType2HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 3) {
        $crowdType3HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 4) {
        $crowdType4HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 5) {
        $crowdType5HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 6) {
        $crowdType6HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 7) {
        $crowdType7HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 8) {
        $crowdType8HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 9) {
        $crowdType9HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 10) {
        $crowdType10HTML = 'selected="selected"';
    }

    if (!isset($errorMessage)) {
        $encryptedEmailData = mysql_aes_encrypt($registerEmail);
        setType($registerCrowdType, "int");
        $authCheckCode = md5(rand());
        $queryStatement = "INSERT INTO users (masked_email, encrypted_email, encryption_data, auth_check_code, crowd_type, other_crowd_type, affiliation, "
                . "time_zone, account_created_on, last_logged_in_on) VALUES (:maskedEmail, :encryptedRegisterEmail, "
                . ":encryptedRegisterEmailIV, :authCheckCode, :registerCrowdType, "
                . ":registerOther, :registerAffiliation, :timeZone, NOW( ), NOW( ))";
        $queryParams = array(
            'maskedEmail' => mask_email($registerEmail),
            'encryptedRegisterEmail' => $encryptedEmailData[0],
            'encryptedRegisterEmailIV' => $encryptedEmailData[1],
            'authCheckCode' => $authCheckCode,
            'registerCrowdType' => $registerCrowdType,
            'registerOther' => $registerOtherContent,
            'registerAffiliation' => $registerAffiliationContent,
            'timeZone' => $registerTimeZone
        );
        $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
        if ($STH->rowCount() > 0) {
            print $STH->rowCount();
            setcookie('userId', $DBH->lastInsertId(), time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
            setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
            setcookie('registrationEmail', '', time() - 360 * 24, '/', '', 0, 1);
            header('Location: welcome.php?userType=new');
            exit;
        }
    }
}

$jQueryDocumentDotReadyCode = '';
if (empty($registerTimeZone)) {
    $jQueryDocumentDotReadyCode .= "$('#registerTimeZone').prop('selectedIndex', -1);\r\n";
}
if (empty($registerCrowdType)) {
    $jQueryDocumentDotReadyCode .= "$('#registerCrowdType').prop('selectedIndex', -1);\r\n";
}
$jQueryDocumentDotReadyCode .= <<<EOL
if ($('#registerCrowdType option:selected').text() !== 'Other (Please specify below)') {
  $('#registerOtherRow').css('display', 'none');
}
$('#registerCrowdType').change(function() {
  if (($('#registerCrowdType option:selected').text() === 'Other (Please specify below)') &&
          ($('#registerOtherRow').css('display') === 'none')) {
    $('#registerOtherRow').slideDown();
  } else if ($('#registerOtherRow').css('display') === 'block') {
    $('#registerOtherRow').slideUp();
    $('#registerOther').val('');
  }
});
$('#registerForm').validate({
  rules: {
    registerTimeZone: {
        required: true
    },
    registerCrowdType: {
        required: true
    },
    registerOther: {
        maxlength: 255
    },
    registerAffiliation: {
        maxlength: 255
    }
  },
  messages: {
    registerTimeZone: {
        required: 'You must select your time zone to complete registration.'
    },
    registerCrowdType: {
        required: 'You must select your crowd type to complete registration.'
    },
    registerOther: {
        maxlength: 'Your specified other crowd type is too long for registration (max 255 characters).'
    },
    registerAffiliation: {
        maxlength: 'Your specified affiliation is too long for registration (max 255 characters).'
    }
  }
});
EOL;
