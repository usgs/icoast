<?php

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);
$userId = $userData['user_id'];

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
$newAccountError = '';
$confirmLoginError = '';
$crowdTypeError = '';
$otherCrowdTypeError = '';
$affiliationError = '';
$newAccount = '';
$confirmNewLogin = '';
$otherCrowdType = '';
$affiliationContent = '';
$updateAck = '';
$stickyCrowdType = FALSE;
$crowdTypeReset = '';
$stickyTimeZone = FALSE;
$timeZoneReset = '';
$optInSelected = '';
$optOutSelected = '';

$crowdTypeSelectHTML = '';

$crowdTypeTableQuery = "SELECT * from crowd_types";
$crowdTypeTableResult = $DBH->query($crowdTypeTableQuery);
$crowdTypeArray = $crowdTypeTableResult->fetchAll(PDO::FETCH_ASSOC);
$crowdTypeIdArray = array();
foreach ($crowdTypeArray as $individualCrowdType) {
    $crowdTypeIdArray[] = $individualCrowdType['crowd_type_id'];
    $crowdHTMLVariableName = "crowdType{$individualCrowdType['crowd_type_id']}HTML";
    $$crowdHTMLVariableName = '';
}




if (isset($_POST['formSubmission'])) {
    switch ($_POST['formSubmission']) {






        case 'account':
            $newAccount = (isset($_POST['newAccount'])) ? strtolower(trim($_POST['newAccount'])) : null;
            $confirmNewLogin = (isset($_POST['confirmNewLogin'])) ? strtolower(trim($_POST['confirmNewLogin'])) : null;
            $filteredNewEmail = filter_var($newAccount, FILTER_VALIDATE_EMAIL);
            if (empty($filteredNewEmail)) {
                $errorMessage['newAccount'] = 'You must specify a valid new eMail Address.';
            } else if (empty($confirmNewLogin) || strcasecmp($filteredNewEmail, $confirmNewLogin) != 0) {
                $errorMessage['confirmEmail'] = 'Your confirmation eMail address must match your new eMail address.';
            }


            if (!isset($errorMessage)) {
                $maskedEmail = mask_email($newAccount);
                $emailExistsQuery = "SELECT encrypted_email, encryption_data FROM users "
                        . "WHERE masked_email = :maskedEmail";
                $emailExistsParams['maskedEmail'] = $maskedEmail;
                $STH = run_prepared_query($DBH, $emailExistsQuery, $emailExistsParams);
                $possibleEmailMatches = $STH->fetchAll(PDO::FETCH_ASSOC);
                $emailExists = FALSE;
                if (count($possibleEmailMatches) > 0) {

                    foreach ($possibleEmailMatches as $emailMatch) {
                        $clearTextEmail = mysql_aes_decrypt
                                ($emailMatch['encrypted_email'], $emailMatch['encryption_data']);
                        if (strcasecmp($filteredNewEmail, $clearTextEmail) === 0) {
                            $emailExists = TRUE;
                        }
                    }
                }
                if (!$emailExists) {
                    $encryptedEmailData = mysql_aes_encrypt($filteredNewEmail);
                    $profileUpdateQuery = "UPDATE users SET masked_email = :maskedEmail, "
                            . "encrypted_email = :encryptedEmail, encryption_data = :encryptionData "
                            . "WHERE user_id = :userId LIMIT 1";
                    $profileUpdateParams = array(
                        'maskedEmail' => mask_email($newAccount),
                        'encryptedEmail' => $encryptedEmailData[0],
                        'encryptionData' => $encryptedEmailData[1],
                        'userId' => $userId
                    );
                    $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                    if ($STH->rowCount() == 1) {
                        header('Location: profile.php?update=email');
                        exit;
                    } else {
                        print $STH->rowCount();
                        //  Placeholder for error management
                        print 'Error. Account update failed. No details have been changed.<br>';
                        exit;
                    }
                } else {
                    $newAccountError = '<label class="error" for="newAccount">The specified account already '
                            . 'exists within iCoast.</label>';
                    $confirmNewLogin = '';
                }
            } else if (isset($errorMessage['newAccount'])) {
                $newAccountError = '<label class="error" for="newAccount">' . $errorMessage['newAccount'] . '</label>';
            } else if (isset($errorMessage['confirmEmail'])) {
                $confirmLoginError = '<label class="error" for="confirmEmail">' . $errorMessage['confirmEmail'] . '</label>';
            }


            $newAccount = htmlentities($newAccount);
            $confirmNewLogin = htmlentities($confirmNewLogin);
            break;






        case 'crowd':
            $crowdType = (isset($_POST['crowdType'])) ? trim($_POST['crowdType']) : null;
            $otherCrowdType = (!empty($_POST['otherCrowdType'])) ? trim($_POST['otherCrowdType']) : '';

            if (empty($crowdType) && $crowdType !== '0') {
                $errorMessage['crowdType'] = 'You must select your crowd type to complete registration.';
            } else if ($crowdType !== '0' && !in_array($crowdType, $crowdTypeIdArray)) {
                $errorMessage['crowdType'] = 'The specified crowd type is invalid.';
            }


            if ($crowdType === '0' && empty($otherCrowdType)) {
                $errorMessage['otherCrowdType'] = 'You must specify your other crowd type if "Other" is selected in the crowd type list.';
            } elseif (!empty($otherCrowdType) && strlen($otherCrowdType) > 255) {
                $errorMessage['otherCrowdType'] = 'Your specified other crowd type is too long (max 255 characters).';
            }

            if (!isset($errorMessage)) {
                $existingCrowdType = $userData['crowd_type'];
                $existingOtherCrowdType = $userData['other_crowd_type'];
                if (($crowdType !== '0' && $crowdType == $existingCrowdType) ||
                        ($crowdType === '0' && strcmp($otherCrowdType, $existingOtherCrowdType) == 0)) {
                    header('Location: profile.php?update=crowd');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET crowd_type = :crowdType, other_crowd_type = :otherCrowdType "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'crowdType' => $crowdType,
                    'otherCrowdType' => $otherCrowdType,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=crowd');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['crowdType'])) {
                $crowdTypeError = '<label class="error" for="crowdType">' . $errorMessage['crowdType'] . '</label>';
            } else if (isset($errorMessage['otherCrowdType'])) {
                $otherCrowdTypeError = '<label class="error" for="otherCrowdType">' . $errorMessage['otherCrowdType'] . '</label>';
            }

            foreach ($crowdTypeIdArray as $crowdTypeId) {
                $crowdHTMLVariableName = "crowdType{$crowdTypeId}HTML";
                if (isset($crowdType) && $crowdType == $crowdTypeId) {
                    $$crowdHTMLVariableName = 'selected="selected"';
                    $stickyCrowdType = TRUE;
                }
            }

            if (isset($crowdType) && $crowdType === '0') {
                $crowdType0HTML = 'selected="selected"';
                $stickyCrowdType = TRUE;
            }



            $otherCrowdType = htmlEntities($otherCrowdType);
            break;







        case 'affiliation':
            $affiliationContent = (!empty($_POST['affiliation'])) ? trim($_POST['affiliation']) : '';
            if (!empty($affiliationContent)) {
                if (strlen($affiliationContent) > 255) {
                    $errorMessage['affiliation'] = 'Your specified affiliation is too long (max 255 characters).';
                }
            }
            if (!isset($errorMessage)) {
                $existingAffiliation = $userData['affiliation'];
                if (strcmp($affiliationContent, $existingAffiliation) == 0) {
                    header('Location: profile.php?update=affiliation');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET affiliation = :affiliation "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'affiliation' => $affiliationContent,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=affiliation');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['affiliation'])) {
                $affiliationError = '<label class="error" for="registerCrowdType">' . $errorMessage['affiliation'] . '</label>';
            }

            $affiliationContent = htmlentities($affiliationContent);
            break;








        case 'timeZone':
            $timeZone = (isset($_POST['timeZone'])) ? trim($_POST['timeZone']) : null;
            if (empty($timeZone)) {
                $errorMessage['timeZone'] = 'You must select your time zone to complete registration.';
            } else {
                if ($timeZone < 1 || $timeZone > 8) {
                    $errorMessage['timeZone'] = 'The specified time zone is invalid.';
                }
            }
            if (!isset($errorMessage)) {
                $existingTimeZone = $userData['time_zone'];
                if ($existingTimeZone == $timeZone) {
                    header('Location: profile.php?update=timeZone');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET time_zone = :timeZone "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'timeZone' => $timeZone,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=timeZone');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else if (isset($errorMessage['timeZone'])) {
                $timeZoneError = '<label class="error" for="registerTimeZone">' . $errorMessage['timeZone'] . '</label>';
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
            break;

        case 'emailPreference':
            $emailPreference = (isset($_POST['emailPreference'])) ? trim($_POST['emailPreference']) : null;
            if (!empty($emailPreference)) {
                if ($emailPreference !== 'in' && $emailPreference !== 'out') {
                    $errorMessage['emailPreference'] = 'The email preference specified is invalid. Please select an option above.';
                }
            }
            if (!isset($errorMessage)) {
                if ($emailPreference == 'in') {
                    $emailPreference = 1;
                } else {
                    $emailPreference = 0;
                }
                $existingEmailPreference = $userData['allow_email'];
                if ($emailPreference === $existingEmailPreference) {
                    header('Location: profile.php?update=emailPreference');
                    exit;
                }
                $profileUpdateQuery = "UPDATE users SET allow_email = :emailPreference "
                        . "WHERE user_id = :userId LIMIT 1";
                $profileUpdateParams = array(
                    'emailPreference' => $emailPreference,
                    'userId' => $userId
                );
                $STH = run_prepared_query($DBH, $profileUpdateQuery, $profileUpdateParams);
                if ($STH->rowCount() == 1) {
                    header('Location: profile.php?update=emailPreference');
                    exit;
                } else {
                    //  Placeholder for error management
                    print 'Error. Account update failed. No details have been changed.<br>';
                    exit;
                }
            } else {
                $emailPreferenceError = '<p><label class="error" for="optIn">' . $errorMessage['emailPreference'] . '</label></p>';
            }

            break;
    }

    if (!empty($newAccountError) || !empty($confirmNewLoginError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeAccountFormWrapper').css('display', 'block');";
    } else if (!empty($crowdTypeError) || !empty($otherCrowdTypeError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeCrowdFormWrapper').css('display', 'block');";
    } else if (!empty($affiliationError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeAffiliationFormWrapper').css('display', 'block');";
    } else if (!empty($timeZoneError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeTimeZoneFormWrapper').css('display', 'block');";
    } else if (!empty($emailPreferenceError)) {
        $profileFormErrorControl = "$('.profileUpdateField').css('display', 'none');";
        $profileFormErrorControl .= "$('#changeEmailPreferenceFormWrapper').css('display', 'block');";
    }
}

foreach ($crowdTypeArray as $individualCrowdType) {
    $crowdTypeId = $individualCrowdType['crowd_type_id'];
    $varibleCrowdTypeVariableName = "crowdType{$crowdTypeId}HTML";
    $crowdTypeName = $individualCrowdType['crowd_type_name'];

    $crowdTypeSelectHTML .= "<option value=\"$crowdTypeId\" {$$varibleCrowdTypeVariableName}>$crowdTypeName</option>";
}

if (!$stickyCrowdType) {
    $crowdTypeReset = "$('#crowdType').prop('selectedIndex', -1);";
}
if (!$stickyTimeZone) {
    $timeZoneReset = "$('#timeZone').prop('selectedIndex', -1);";
}


if (isset($_GET['update'])) {
    $updateAck = '<p class="highlightedText">Your ';
    switch ($_GET['update']) {
        case 'email':
            $updateAck .= 'eMail Address was sucessfully updated.';
            break;
        case 'crowd':
            $updateAck .= 'Crowd Type was sucessfully updated.';
            break;
        case 'affiliation':
            $updateAck .= 'Affiliation was sucessfully updated.';
            break;
        case 'timeZone':
            $updateAck .= 'Time Zone was sucessfully updated.';
            break;
        case 'emailPreference':
            $updateAck .= 'Email Preference was sucessfully updated.';
            break;
        default:
            $updateAck = '';
            break;
    }
}

$maskedEmail = $userData['masked_email'];
$crowdType = htmlentities(crowdTypeConverter($DBH, $userData['crowd_type'], $userData['other_crowd_type']));
if (!empty($userData['affiliation'])) {
    $affiliation = htmlentities($userData['affiliation']);
} else {
    $affiliation = "None Given";
}
$timeZone = timeZoneIdToTextConverter($userData['time_zone']);
if ($userData['allow_email'] == 1) {
    $emailPreference = 'Opt In';
    $emailPreferenceDetail = '<span class="userData">Opt In</span> to';
    $emailOptInSelected = 'checked';
} else {
    $emailPreference = 'Opt Out';
    $emailPreferenceDetail = '<span class="userData">Opt Out</span> of';
    $emailOptOutSelected = 'checked';
}














$javaScriptLinkArray[] = "scripts/jquery.validate.min.js";

$jQueryDocumentDotReadyCode = <<<EOT

        $crowdTypeReset
        $timeZoneReset

        $('#accountChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeAccountFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#crowdTypeChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeCrowdFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#affiliationChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeAffiliationFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#timeZoneChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeTimeZoneFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('#emailPreferenceChangeButton').click(function() {
            $('.profileUpdateField').slideUp();
            $('#changeEmailPreferenceFormWrapper').slideDown(positionFeedbackDiv);
        });

        $('.cancelUpdateButton').click(function() {
            $('.profileUpdateForm').slideUp();
            $('.profileUpdateField').slideDown(positionFeedbackDiv);
        });

        $profileFormErrorControl

        $('#accountForm').validate({
            rules: {
                newAccount: {
                    required: true
                },
                confirmNewLogin: {
                    equalTo: '#newAccount'
                }
            },
            messages: {
                newAccount: {
                    required: 'You must specify a new login account to continue.'
                },
                confirmNewLogin: {
                    equalTo: 'Your confirmation login entry must match your first entry.'
                }
            }
        });

        $('#crowdForm').validate({
            rules: {
                crowdType: {
                    required: true
                },
                otherCrowdType: {
                    maxlength: 255
                }
            },
            messages: {
                crowdType: {
                    required: 'You must select a crowd type.'
                },
                otherCrowdType: {
                    maxlength: 'Your specified other crowd type is too long (max 255 characters).'
                }
            }
        });

        $('#affiliationForm').validate({
            rules: {
                affiliation: {
                    maxlength: 255
                }
            },
            messages: {
                affiliation: {
                    maxlength: 'Your specified affiliation is too long (max 255 characters).'
                }
            }
        });

        $('#timeZoneForm').validate({
            rules: {
                timeZone: {
                    required: true
                }
            },
            messages: {
                timeZone: {
                    required: 'You must select your new time zone.'
                }
            }
        });

        if ($('#crowdType option:selected').text() !== 'Other (Please specify below)') {
            $('#profileOtherRow').css('display', 'none');
        }
        $('#crowdType').change(function() {
            if (($('#crowdType option:selected').text() === 'Other (Please specify below)') &&
                    ($('#profileOtherRow').css('display') === 'none')) {
                $('#feedbackWrapper').hide();
                $('#profileOtherRow').slideDown(positionFeedbackDiv);
            } else if ($('#profileOtherRow').css('display') === 'block') {
                $('#feedbackWrapper').hide();
                $('#profileOtherRow').slideUp(positionFeedbackDiv);
                $('#otherCrowdType').val('');
            }
        });

        $('#crowdType option').tipTip();
EOT;

