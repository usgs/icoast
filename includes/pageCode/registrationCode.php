<?php

$pageName = "registration";
$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";

require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);

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
$crowdType0HTML = '';
$timeZoneError = '';
$crowdTypeError = '';
$otherCrowdTypeError = '';
$registerAffiliationError = '';
$registerOtherContent = '';
$registerAffiliationContent = '';
$crowdTypeSelectHTML = '';
$crowdTypeIdArray = array();

$crowdTypeTableQuery = "SELECT * from crowd_types";
$crowdTypeTableResult = $DBH->query($crowdTypeTableQuery);
$crowdTypeArray = $crowdTypeTableResult->fetchAll(PDO::FETCH_ASSOC);
foreach ($crowdTypeArray as $individualCrowdType) {
    $crowdTypeIdArray[] = $individualCrowdType['crowd_type_id'];
    $crowdHTMLVariableName = "crowdType{$individualCrowdType['crowd_type_id']}HTML";
    $$crowdHTMLVariableName = '';
}


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
    $registerEmailPreference = (isset($_POST['registerEmailPreference'])) ? trim($_POST['registerEmailPreference']) : null;

    if (empty($registerTimeZone)) {
        $errorMessage['timeZone'] = 'You must select your time zone to complete registration.';
    } else {
        if ($registerTimeZone < 1 || $registerTimeZone > 8) {
            $errorMessage['timeZone'] = 'The specified time zone is invalid.';
        }
    }

    if (empty($registerCrowdType) && $registerCrowdType !== '0') {
        $errorMessage['crowdType'] = 'You must select your crowd type to complete registration.';
    } else if ($registerCrowdType !== '0' && !in_array($registerCrowdType, $crowdTypeIdArray)) {
        $errorMessage['crowdType'] = 'The specified crowd type is invalid.';
    }


    if ($registerCrowdType === '0' && empty($registerOtherContent)) {
        $errorMessage['otherCrowdType'] = 'You must specify your other crowd type if "Other" is selected in the crowd type list.';
    } elseif (!empty($registerOtherContent) && strlen($registerOtherContent) > 255) {
        $errorMessage['otherCrowdType'] = 'Your specified other crowd type is too long for registration (max 255 characters).';
    }

    if (!empty($registerAffiliationContent)) {
        if (strlen($registerAffiliationContent) > 255) {
            $errorMessage['affiliation'] = 'Your specified affiliation is too long (max 255 characters).';
        }
    }

    if (empty($registerEmailPreference)) {
        $errorMessage['emailPreference'] = 'You must select your email preference to complete registration.';
    } else {
        if ($registerEmailPreference !== 'in' && $registerEmailPreference !== 'out') {
            $errorMessage['emailPreference'] = 'The specified email preference is invalid.';
        }
    }
    print $errorMessage['emailPreference'];

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
        $registerAffiliationError = '<label class="error" for="registerAffiliation">' . $errorMessage['affiliation'] . '</label>';
    }
    if (isset($errorMessage['emailPreference'])) {
        $emailPreferenceError = '<label class="error" for="registerOptIn">' . $errorMessage['emailPreference'] . '</label>';
    }
    switch ($registerTimeZone) {
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

    foreach ($crowdTypeIdArray as $crowdTypeId) {
        $crowdHTMLVariableName = "crowdType{$crowdTypeId}HTML";
        if (isset($registerCrowdType) && $registerCrowdType == $crowdTypeId) {
            $$crowdHTMLVariableName = 'selected="selected"';
        }
    }

    if (isset($registerCrowdType) && $registerCrowdType === '0') {
        $crowdType0HTML = 'selected="selected"';
    }

    if ($registerEmailPreference == 'in') {
        $registerEmailPreference = 1;
    } else {
        $registerEmailPreference = 0;
    }

    if (!isset($errorMessage)) {
        $maskedUserEmail = mask_email($registerEmail);
        $queryStatement = "SELECT * FROM users WHERE masked_email = :maskedEmail";
        $queryParams['maskedEmail'] = $maskedUserEmail;
        $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
        $queryResult = $STH->fetchAll(PDO::FETCH_ASSOC);
        if (count($queryResult) > 0) {
            foreach ($queryResult as $userCredentials) {
                $decryptedEmail = mysql_aes_decrypt($userCredentials['encrypted_email'], $userCredentials['encryption_data']);
                if (strcasecmp($decryptedEmail, $registerEmail) === 0) {
                    setcookie('registrationEmail', '', time() - 360 * 24, '/', '', 0, 1);
                    header('Location: index.php');
                }
            }
        }
        if (count($queryResult) === 0) {
            $encryptedEmailData = mysql_aes_encrypt($registerEmail);
            setType($registerCrowdType, "int");
            $authCheckCode = md5(rand());
            $queryStatement = "INSERT INTO users (masked_email, encrypted_email, encryption_data, auth_check_code, crowd_type, other_crowd_type, affiliation, "
                    . "time_zone, account_created_on, last_logged_in_on, allow_email) VALUES (:maskedEmail, :encryptedRegisterEmail, "
                    . ":encryptedRegisterEmailIV, :authCheckCode, :registerCrowdType, "
                    . ":registerOther, :registerAffiliation, :timeZone, NOW( ), NOW( ), :registerEmailPreference)";
            $queryParams = array(
                'maskedEmail' => $maskedUserEmail,
                'encryptedRegisterEmail' => $encryptedEmailData[0],
                'encryptedRegisterEmailIV' => $encryptedEmailData[1],
                'authCheckCode' => $authCheckCode,
                'registerCrowdType' => $registerCrowdType,
                'registerOther' => $registerOtherContent,
                'registerAffiliation' => $registerAffiliationContent,
                'timeZone' => $registerTimeZone,
                'registerEmailPreference' => $registerEmailPreference
            );
            $STH = run_prepared_query($DBH, $queryStatement, $queryParams);
            if ($STH->rowCount() > 0) {
                print $STH->rowCount();
                setcookie('userId', $DBH->lastInsertId(), time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                setcookie('authCheckCode', $authCheckCode, time() + 60 * 60 * 24 * 180, '/', '', 0, 1);
                setcookie('registrationEmail', '', time() - 360 * 24, '/', '', 0, 1);
                header('Location: index.php?userType=new');
                exit;
            }
        }
    }
}

foreach ($crowdTypeArray as $individualCrowdType) {
    $crowdTypeId = $individualCrowdType['crowd_type_id'];
    $varibleCrowdTypeVariableName = "crowdType{$crowdTypeId}HTML";
    $crowdTypeName = $individualCrowdType['crowd_type_name'];

    $crowdTypeSelectHTML .= "<option value=\"$crowdTypeId\" {$$varibleCrowdTypeVariableName}>$crowdTypeName</option>";
}

$jQueryDocumentDotReadyCode = '';
if (empty($registerTimeZone)) {
    $jQueryDocumentDotReadyCode .= "$('#registerTimeZone').prop('selectedIndex', -1);\r\n";
}
if (empty($registerCrowdType) && $registerCrowdType !== '0') {
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
