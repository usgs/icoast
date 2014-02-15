<?php
require 'includes/globalFunctions.php';
require $dbmsConnectionPath;
ob_start();
$userEmail = null;
if (!isset($_COOKIE['registrationEmail'])) {
  header("Location: login.php");
} else {
  $userEmail = $_COOKIE['registrationEmail'];
}

if (isset($_POST['submission']) && $_POST['submission'] == 'register') {
  $validCredentials = true;
  $errorMessage = array();
  $registerEmail = (isset($_POST['registerEmail'])) ? strtolower(trim($_POST['registerEmail'])) : null;
  $registerEmail = filter_var($registerEmail, FILTER_VALIDATE_EMAIL);
  if (!$registerEmail) {
    //  Placeholder for error management
    print 'Error. Invalid eMail Address.<br>';
    exit;
  }
  $registerCrowdType = (isset($_POST['registerCrowdType'])) ? trim($_POST['registerCrowdType']) : null;
  $registerOther = (isset($_POST['registerOther'])) ? trim($_POST['registerOther']) : null;
  $registerAffiliation = (isset($_POST['registerAffiliation'])) ? trim($_POST['registerAffiliation']) : null;

  if (empty($registerCrowdType)) {
    $validCredentials = false;
    $errorMessage[] = 'You must select your "Crowd Type" to complete registration.';
  } else {
    if ($registerCrowdType < 0 || $registerCrowdType > 10) {
      $validCredentials = false;
      $errorMessage[] = 'The specified crowd type is invalid.';
    }
  }

  if ($registerCrowdType == 10 && empty($registerOther)) {
    $validCredentials = false;
    $errorMessage[] = 'You must type your "Other Crowd Type" if "Other" is selected in the crowd type list.';
  } elseif (!empty($registerOther) && strlen($registerOther) > 255) {
    $validCredentials = false;
    $errorMessage[] = 'Your specified "Other Crowd Type" is too long for registration (max 255 characters).';
  }

  if (!empty($registerAffiliation)) {
    if (strlen($registerAffiliation) > 255) {
      $validCredentials = false;
      $errorMessage[] = 'Your specified "Affiliation" is too long (max 255 characters).';
    }
  }

  if ($validCredentials) {
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
        'registerOther' => $registerOther,
        'registerAffiliation' => $registerAffiliation
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
?>
<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Registration</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
    <link rel="stylesheet" href="css/icoast.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/staticHeader.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="scripts/jquery.validate.min.js"></script>
    <script>
      $(document).ready(function() {
        $('#registerOtherRow').css('display', 'none');
<?php
if (!isset($registerCrowdType) || is_null($registerCrowdType)) {
  print "$('#registerCrowdType').prop('selectedIndex', -1);";
}
?>
        if (($('#registerCrowdType option:selected').text() === 'Other (Please specify below)') &&
                ($('#registerOtherRow').css('display') === 'none')) {
          $('#registerOtherRow').slideDown();
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
      });
    </script>
  </head>
  <body id="body">
    <div id="wrapper">
      <?php
      $pageName = "registration";
      require("includes/header.php");
      ?>
      <div id = "loginWrapper">
        <h1>iCoast: Did it Change?</h1>
        <h2>Welcome to iCoast.</h2>
        <div id="forms">
          <p>No account for <span class="userData"><?php print $userEmail ?></span> has
            been found within iCoast.<br>
            Please complete the following information to build your iCoast profile.</p>
          <form method="post" action="registration.php" id="registerForm">
            <input type="hidden" name="submission" value="register" />
            <input type="hidden" name="registerEmail" value="<?php print $userEmail ?>" />
            <div class="formFieldRow">
              <label for="registerCrowdType">Crowd Type: *</label>
              <select id="registerCrowdType" name="registerCrowdType" >
                <option value="1" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 1) {
                  print 'selected="selected"';
                }
                ?>>Coastal Science Researcher</option>
                <option value="2" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 2) {
                  print 'selected="selected"';
                }
                ?>>Coastal Manager or Planner</option>
                <option value="3" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 3) {
                  print 'selected="selected"';
                }
                ?>>Coastal Resident</option>
                <option value="4" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 4) {
                  print 'selected="selected"';
                }
                ?>>Coastal Recreational User</option>
                <option value="5" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 5) {
                  print 'selected="selected"';
                }
                ?>>Marine Science Student</option>
                <option value="6" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 6) {
                  print 'selected="selected"';
                }
                ?>>Emergency Manager</option>
                <option value="7" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 7) {
                  print 'selected="selected"';
                }
                ?>>Policy Maker</option>
                <option value="8" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 8) {
                  print 'selected="selected"';
                }
                ?>>Digital Crisis Volunteer (VTC)</option>
                <option value="9" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 9) {
                  print 'selected="selected"';
                }
                ?>>Interested Public</option>
                <option value="10" <?php
                if (isset($registerCrowdType) && $registerCrowdType == 10) {
                  print 'selected="selected"';
                }
                ?>>Other (Please specify below)</option>
              </select>
            </div>
            <div class="formFieldRow" id="registerOtherRow">
              <label for="registerOther">Other Crowd Type *: </label>
              <input type="text" id="registerOther" name="registerOther" value="<?php
              if (isset($registerOther)) {
                print $registerOther;
              }
              ?>"/>
            </div>
            <div class="formFieldRow">
              <label for="registerAffiliation">Affiliation: </label>
              <input type="text" id="registerAffiliation" name="registerAffiliation" value="<?php
              if (isset($registerAffiliation)) {
                print $registerAffiliation;
              }
              ?>"/>
            </div>


            <input type="submit" class="formButton" id="registerSubmitButton" value="Complete Registration" />
          </form>
        </div>
        <div id="loginWrapperFooter">
          <?php
          if (isset($errorMessage)) {
            print '<p class="phpFormError">';
            foreach ($errorMessage as $error) {
              print $error . "<br>";
            }
            print '</p>';
          }
          ?>
          <p>* indicates the field is required.</p>
        </div>
      </div>
    </div>
  </body>
</html>
<?php
ob_end_flush();
