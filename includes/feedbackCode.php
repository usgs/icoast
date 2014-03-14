<?php

require_once('includes/globalFunctions.php');
require_once($dbmsConnectionPath);

if (!isset($userId)) {
    $userId = 0;
}
$url = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
$queryString = $_SERVER['QUERY_STRING'];

$postData = '';
$firstValue = TRUE;
foreach ($_POST as $postField => $postValue) {
    urlencode($postField);
    urldecode($postValue);
    if (!$firstValue) {
        $postData .= '&';
    }
    $postData .= $postField . '=' . $postValue;
    $firstValue = FALSE;
}

$clientAgent = $_SERVER['HTTP_USER_AGENT'];

$hiddenFormData = <<<EOL
    <input type="hidden" name="userId" value="$userId">
    <input type="hidden" name="url" value="$url">
    <input type="hidden" name="queryString" value="$queryString">
    <input type="hidden" name="postData" value="$postData">
    <input type="hidden" name="clientAgent" value="$clientAgent">
    <input type="hidden" name="eventSummary" value="User Feedback">
EOL;

$feedbackPanel1 = '';
$feedbackPanel2 = '';
$feedbackPanel3 = '';

$allProjects = array();
$allProjectsQuery = "SELECT project_id, name FROM projects WHERE is_public = 1 ORDER BY project_id ASC";
foreach ($DBH->query($allProjectsQuery) as $row) {
    $allProjects[] = $row;
}
$numberOfProjects = count($allProjects);

if ($numberOfProjects > 0) {
    $feedbackContentHeight = "height: 200px;\n\r";
    $feedbackWrapperHeight = "height: 206px;\n\r";
    $feedbackToggleHeight = "top: 173px\n\r;";
    $javascriptFeedbackWrapperHeight = 206;
    $feedbackPanel1 = <<<EOL
            <p>Click Application if you have feedback on errors, feature requests, etc.</p>
            <p>Click Project if you have feedback about the photos, tasks, tags, etc.</p>
            <div class="feedbackButtonWrapper">
                <input type="radio" id="systemFeedback" name="eventType" value="2">
                <label for="systemFeedback" class="clickableButton">Application</label>
                <input type="radio" id="projectFeedback" name="eventType" value="3">
                <label for="projectFeedback" class="clickableButton">Project</label>
            </div>

EOL;
    $projectSelectOptionHTML = "";
    foreach ($allProjects as $project) {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>/r/n";
    }
    $feedbackPanel2 = <<<EOL
            <p>Select the project that you wish to provide some feedback for...</p>
            <select id="feedbackFormProjectList" name="eventCode" class="clickableButton">
                $projectSelectOptionHTML
            </select>
            <div class="feedbackButtonWrapper">
                <input type="button" id="backToPanel1" class="clickableButton" value="Back">
            </div>
EOL;

    $feedbackPanel3 = <<<EOL
                <p><label for="feedbackText">What would you like to tell us?</label></p>
                <textarea rows="4" cols="55" id="feedbackText" name="eventText" maxlength="500"></textarea>
                <p id="feedbackCharacterCount">0 of 500 characters used</p>
                 <div class="feedbackButtonWrapper">
                    <input type="button" id="backToPreviousPanel" class="clickableButton" value="Back">
                    <input type="button" id="cancelFeedback" class="clickableButton" value="Cancel Feedback">
                    <input type="button" id="sendFeedback" class="clickableButton" value="Send Feedback">
                </div>
EOL;
} else {
    $feedbackContentHeight = "height: 230px\n\r;";
    $feedbackWrapperHeight = "height: 236px\n\r;";
    $feedbackToggleHeight = "top: 203px\n\r;";
    $javascriptFeedbackWrapperHeight = 236;
    $feedbackPanel1 = <<<EOL
            <input type="hidden" name="feedbackType" value="2">
                <textarea rows="4" cols="55" id="feedbackText" name="eventText" maxlength="500"></textarea>
            <p id="feedbackCharacterCount">0 of 500 characters used</p>
             <div class="feedbackButtonWrapper">
                <input type="button" id="cancelFeedback" class="clickableButton" value="Cancel Feedback">
                <input type="button" id="sendFeedback" class="clickableButton" value="Send Feedback">
            </div>

EOL;
}

$feedbackEmbeddedCSS = <<<EOL
    #feedbackWrapper {
        position: fixed;
        overflow: hidden;
        width: 33px;
        $feedbackWrapperHeight
    }

    #feedbackToggle {

        position: absolute;
        $feedbackToggleHeight
        left: 33px;
        background: #040535;
        color: #FFFFFF;
        width: 100px;
        height: 30px;
        border-radius: 10px 10px 0 0;
        border: 3px solid black;
        border-bottom: none;


        transform-origin: left bottom;
        -ms-transform-origin: left bottom;
        -webkit-transform-origin: left bottom;

        transform: rotate(270deg);
        -ms-transform: rotate(270deg);
        -webkit-transform: rotate(270deg);
    }

    #feedbackToggle p {
        margin: 0px;
        padding: 5px 10px;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;

    }

    #feedbackContent {
        position: absolute;
        left: 33px;
        display: inline-block;
        overflow: hidden;
        width: 500px;
        $feedbackContentHeight
        border-radius: 10px 0 0 0;
        border: 3px solid black;
        border-right: none;
    }

    .feedbackPanel {
        border-radius: 10px 0 0 0;
        background: #FFFFFF;
        overflow: hidden;
        position: absolute;
        top: 0px;
        left: 500px;
        display: block;
        float: left;
        width: 500px;
        $feedbackContentHeight
    }

    #feedbackPanel1 {
        z-index: 1;
        left: 0px;
    }
    #feedbackPanel2 {
        z-index: 2;
    }

    #feedbackPanel3 {
        z-index: 3;
    }

    .feedbackPanel p {
        margin: 10px;
    }

    .feedbackPanel h1 {
        margin: 10px;
    }

    .feedbackButtonWrapper {
        width: 100%;
        position: absolute;
        bottom: 5px;
    }

    .feedbackButtonWrapper .clickableButton {
        display: inline-block;
        min-width: 120px;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    #feedbackCharacterCount {
        text-align: left;
        font-size: 0.7em;
    }

    #feedbackContent textarea {
        font: inherit;
    }
EOL;

$feedbackJavascript = <<<EOL

    var feedbackHeight = $javascriptFeedbackWrapperHeight;

    function feedbackConfirmation() {
        $('#feedbackPanel1').animate({
            left: -500
        });
        $('#feedbackPanel3').animate({
            left: -500
        });
        $('#confirmationPanel').animate({
            left: 0
        });
    }

    function positionFeedbackDiv() {
        if ($('#contentWrapper').length) {
            var contentOffset = $('#contentWrapper').offset().left;
            var contentWidth = $('#contentWrapper').outerWidth();
            var contentHeight = $('#contentWrapper').outerHeight() + 72;
        } else {
            var contentOffset = $('#classificationWrapper').offset().left;
            var contentWidth = $('#classificationWrapper').outerWidth();
            var contentHeight = $('#classificationWrapper').outerHeight() + 25;

        }

        var feedbackWidth = $('#feedbackWrapper').outerWidth();
        var feedbackXPosition = contentOffset;
        feedbackXPosition += 'px';


        var windowHeight = $(window).height();
        if (contentHeight < windowHeight) {
            var feedbackYPosition = contentHeight
        } else {
            var feedbackYPosition = windowHeight
        }
        feedbackYPosition -= ((feedbackYPosition * 0.15) + feedbackHeight);
        if (feedbackYPosition < 0) {
            feedbackYPosition = 0
        }
        feedbackYPosition += 'px';

        $('#feedbackWrapper').css({
            right: feedbackXPosition,
            top: feedbackYPosition
        });


        if ($('#feedbackWrapper').css('display') === 'none') {
            $('#feedbackWrapper').show();
        }
    }
EOL;

$feedbackjQueryDocumentDotReadyCode = <<<EOL
    var panel2InUse;
    var feedbackHidden = true;

    positionFeedbackDiv();

    $(window).resize(positionFeedbackDiv);

    $('#feedbackToggle').click(function() {
        console.log("In feedbackToggle Click");
        if (feedbackHidden) {
            feedbackHidden = false;
            $('#feedbackPanel1').css('left', '0px');
            $('#feedbackPanel2').css('left', '500px');
            $('#feedbackPanel3').css('left', '500px');
            $('#confirmationPanel').css('left', '500px');
            if ($('#feedbackFormProjectList').length) {
                $('#feedbackFormProjectList').prop('selectedIndex', -1);
            }
            $('#feedbackText').val('Please type your feedback here.');
            $('#feedbackWrapper').animate({
                width: 536
            });
        } else {
            feedbackHidden = true;
            $('#feedbackWrapper').animate({
                width: 33
            });
        }
    });

    $('#feedbackText').keyup(function() {
        (console.log("in count chars"));
        var chars = $('#feedbackText').val().length;
        $('#feedbackCharacterCount').text(chars + ' of 500 characters used');
    });

    $('#feedbackText').focus(function() {
        if ($('#feedbackText').val() === 'You must provide some feedback.' ||
                $('#feedbackText').val() === 'Please type your feedback here.') {
            $('#feedbackText').val('');
        }
    });

    $('#cancelFeedback').click(function() {
        $('#feedbackToggle').click();
    });

    $('#sendFeedback').click(function() {
        if ($('#feedbackText').val().length > 0 &&
                $('#feedbackText').val() !== 'You must provide some feedback.' &&
                $('#feedbackText').val() !== 'Please type your feedback here.') {
            var formData = $('#userFeedbackForm').serialize();
            console.log(formData);
            $.post('ajax/eventLogger.php', formData, feedbackConfirmation());
        } else {
            $('#feedbackText').val('You must provide some feedback.');
        }

    });

    $('#closeConfirmation').click(function() {
        $('#feedbackToggle').click();
    });


    if ($('#systemFeedback').length) {
        $('#systemFeedback').click(function() {
            console.log("In Application Click");
            panel2InUse = false;
            console.log("panel2InUse" + panel2InUse);
            $('#feedbackPanel1').animate({
                left: -500
            });
            $('#feedbackPanel3').animate({
                left: 0
            });
        });
    }

    if ($('#projectFeedback').length) {
        $('#projectFeedback').click(function() {
            console.log("In Project Click");
            panel2InUse = true;
            console.log("panel2InUse" + panel2InUse);
            $('#feedbackPanel1').animate({
                left: -500
            });
            $('#feedbackFormProjectList').prop('selectedIndex', -1);
            $('#feedbackPanel2').animate({
                left: 0
            });
        });
    }

    if ($('#backToPanel1').length) {
        $('#backToPanel1').click(function() {
            console.log("In backToPanel1 Click");
            $('#feedbackPanel2').animate({
                left: 500
            });
            $('#feedbackPanel1').animate({
                left: 0
            });
        });
    }

    if ($('#feedbackFormProjectList').length) {
        $('#feedbackFormProjectList option').click(function() {
            console.log("In forwardToPanel3 Click");
            $('#feedbackPanel2').animate({
                left: -500
            });
            $('#feedbackPanel3').animate({
                left: 0
            });
        });
        $('#feedbackFormProjectList').change(function() {
            console.log("In forwardToPanel3 Option Change");
            $('#feedbackFormProjectList option').click();
        });
    }

    if ($('#backToPreviousPanel').length) {
        $('#backToPreviousPanel').click(function() {
            $('#feedbackPanel3').animate({
                left: 500
            });
            if (panel2InUse) {
                $('#feedbackFormProjectList').prop('selectedIndex', -1);
                $('#feedbackPanel2').animate({
                    left: 0
                });
            } else {
                $('#feedbackPanel1').animate({
                    left: 0
                });
            }
            console.log("In backToPreviousPanel Click");
            $('#feedbackPanel3').animate({
                left: 500
            });
        });
    }
EOL;
