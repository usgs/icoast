<?php

$pageName = "registration";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray[] = "//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js";
$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";
$javaScript = '';

require 'includes/globalFunctions.php';
require $dbmsConnectionPath;

if (!isset($_COOKIE['registrationEmail'])) {
    header("Location: login.php");
} else {
    $userEmail = $_COOKIE['registrationEmail'];
}

if (isset($_POST['submission']) && $_POST['submission'] == 'register') {
    $registerEmail = (isset($_POST['registerEmail'])) ? strtolower(trim($_POST['registerEmail'])) : null;
    $registerEmail = filter_var($registerEmail, FILTER_VALIDATE_EMAIL);
    if (!$registerEmail) {
        //  Placeholder for error management
        print 'Error. Invalid eMail Address.<br>';
        exit;
    }
    $registerCrowdType = (isset($_POST['registerCrowdType'])) ? trim($_POST['registerCrowdType']) : null;
    $registerOtherContent = (isset($_POST['registerOther'])) ? trim($_POST['registerOther']) : '';
    $registerAffiliationContent = (isset($_POST['registerAffiliation'])) ? trim($_POST['registerAffiliation']) : '';

    if (empty($registerCrowdType)) {
        $errorMessage['crowdType'] = 'You must select your "Crowd Type" to complete registration.';
    } else {
        if ($registerCrowdType < 0 || $registerCrowdType > 10) {
            $errorMessage['crowdType'] = 'The specified crowd type is invalid.';
        }
    }

    if ($registerCrowdType == 10 && empty($registerOtherContent)) {
        $errorMessage['otherCrowdType'] = 'You must specify your "Other Crowd Type" if "Other" is selected in the crowd type list.';
    } elseif (!empty($registerOtherContent) && strlen($registerOtherContent) > 255) {
        $errorMessage['otherCrowdType'] = 'Your specified "Other Crowd Type" is too long for registration (max 255 characters).';
    }

    if (!empty($registerAffiliationContent)) {
        if (strlen($registerAffiliationContent) > 255) {
            $errorMessage[affiliation] = 'Your specified "Affiliation" is too long (max 255 characters).';
        }
    }

    $crowdTypeError = '';
    if (isset($errorMessage['crowdType'])) {
        $crowdTypeError = '<label class="error" for="registerCrowdType">' . $errorMessage['crowdType'] . '</label>';
    }

    $otherCrowdTypeError = '';
    if (isset($errorMessage['otherCrowdType'])) {
        $otherCrowdTypeError = '<label class="error" for="registerCrowdType">' . $errorMessage['otherCrowdType'] . '</label>';
    }

    $registerAffiliationError = '';
    if (isset($errorMessage['affiliation'])) {
        $registerAffiliationError = '<label class="error" for="registerCrowdType">' . $errorMessage['affiliation'] . '</label>';
    }


    $select1HTML = '';
    $select2HTML = '';
    $select3HTML = '';
    $select4HTML = '';
    $select5HTML = '';
    $select6HTML = '';
    $select7HTML = '';
    $select8HTML = '';
    $select9HTML = '';
    $select10HTML = '';
    if (isset($registerCrowdType) && $registerCrowdType == 1) {
        $select1HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 2) {
        $select2HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 3) {
        $select3HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 4) {
        $select4HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 5) {
        $select5HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 6) {
        $select6HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 7) {
        $select7HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 8) {
        $select8HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 9) {
        $select9HTML = 'selected="selected"';
    }
    if (isset($registerCrowdType) && $registerCrowdType == 10) {
        $select10HTML = 'selected="selected"';
    }

    if (!isset($errorMessage)) {
        $encryptedEmailData = mysql_aes_encrypt($registerEmail);
        setType($registerCrowdType, "int");
        $authCheckCode = md5(rand());
        $queryStatement = "INSERT INTO users (masked_email, encrypted_email, encryption_data, auth_check_code, crowd_type, other_crowd_type, affiliation, "
                . "account_created_on, last_logged_in_on) VALUES (:maskedEmail, :encryptedRegisterEmail, "
                . ":encryptedRegisterEmailIV, :authCheckCode, :registerCrowdType, "
                . ":registerOther, :registerAffiliation, NOW( ), NOW( ))";
        $queryParams = array(
            'maskedEmail' => mask_email($registerEmail),
            'encryptedRegisterEmail' => $encryptedEmailData[0],
            'encryptedRegisterEmailIV' => $encryptedEmailData[1],
            'authCheckCode' => $authCheckCode,
            'registerCrowdType' => $registerCrowdType,
            'registerOther' => $registerOtherContent,
            'registerAffiliation' => $registerAffiliationContent
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
if (!isset($registerCrowdType)) {
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
    registerCrowdType: {required: true
    },
    registerOther: {
      maxlength: 255
    },
    registerAffiliation: {
      maxlength: 255
    }
  },
  messages: {
    registerCrowdType: {
      required: 'You must select your "Crowd Type" to complete registration.'
    },
    registerOther: {
      maxlength: 'Your specified other "Crowd Type" is too long for registration (max 255 characters).'
    },
    registerAffiliation: {
      maxlength: 'Your specified "Affiliation" is too long for registration (max 255 characters).'
    }
  }
});
EOL;
