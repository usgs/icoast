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

$feedbackPanel1 = '';
$cancelFeedbackButtonHTML = '<input type="button" class="clickableButton cancelFeedback" title="Closes the feedback form and resets it to default values." value="Cancel Feedback">';

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
                <label for="systemFeedback" class="clickableButton" title="Use the Application button if your
                    feedback relates to the iCoast application in general. This can include bugs or errors you
                    have found, improvement suggestions, or new feature requests.">Application</label>
                <input type="radio" id="projectFeedback" name="eventType" value="3">
                <label for="projectFeedback" class="clickableButton" title="Use the Project button if your
                feedback relates to a specific project within iCoast. Such feedback can relate to the coastal
                images used within a project, the wording of questions or tags, or the clarity of the
                tooltips.">Project</label>
            </div>

EOL;
    $projectSelectOptionHTML = "";
    foreach ($allProjects as $project) {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>/r/n";
    }
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
                $cancelFeedbackButtonHTML
                <input type="button" id="sendFeedback" class="clickableButton" value="Send Feedback">
            </div>

EOL;
}

$feedbackEmbeddedCSS = <<<EOL

    #feedbackBackground {
        display: none;
        position:fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
    }

    #feedbackWrapper {
        text-align: center;
        position: fixed;
        overflow: hidden;
        width: 33px;
        right: 0px;
        $feedbackWrapperHeight
        z-index: 3;
    }

    #feedbackToggle {

        position: absolute;
        $feedbackToggleHeight
        left: 33px;
        background: #284D68;
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
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    #projectSelectionError {
        color: red;
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
            var contentHeight = $('#contentWrapper').outerHeight() + 135;
            console.log('contentHeight: ' + contentHeight);
        } else {
            var contentHeight = $('#classificationWrapper').outerHeight() + 135;
            console.log('contentHeight: ' + contentHeight);
        }

        var windowHeight = $(window).height();
        if (contentHeight < windowHeight) {
            var feedbackYPosition = contentHeight
        } else {
            var feedbackYPosition = windowHeight
        }
        console.log('feedbackYPosition: ' + feedbackYPosition);
        feedbackYPosition -= (100 + feedbackHeight);
        if (feedbackYPosition < 135) {
            feedbackYPosition = 135
        }
        if (feedbackYPosition > (contentHeight - feedbackHeight)) {
            feedbackYPosition = (contentHeight - feedbackHeight)
        }
        console.log('feedbackYPosition: ' + feedbackYPosition);
        feedbackYPosition += 'px';

        $('#feedbackWrapper').css({
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

    $('#feedbackToggle').tipTip();

    positionFeedbackDiv();

    $(window).resize(positionFeedbackDiv);

    $('#feedbackToggle').click(function() {
        console.log("In feedbackToggle Click");
        if (feedbackHidden) {
            feedbackHidden = false;
            $('#feedbackBackground').show();
            $('#feedbackWrapper').animate({
                width: 539
            });
        } else {
            feedbackHidden = true;
            $('#feedbackBackground').hide();
            $('#feedbackWrapper').animate({
                width: 33
            });
        }
    });

    $('#feedbackBackground').click(function() {
        $('#feedbackToggle').click();
    });

    $('.cancelFeedback').click(function() {
        console.log("In cancelFeedback Click");
        if (feedbackHidden) {
            feedbackHidden = false;
            $('#feedbackWrapper').animate({
                width: 539
            });
        } else {
            feedbackHidden = true;
            $('#feedbackWrapper').animate(
                {
                    width: 33
                },
                function() {
                    $('#feedbackPanel1').css('left', '0px');
                    $('#feedbackPanel2').css('left', '500px');
                    $('#feedbackPanel3').css('left', '500px');
                    $('#confirmationPanel').css('left', '500px');
                    if ($('#feedbackFormProjectList').length) {
                        $('#feedbackFormProjectList').prop('selectedIndex', -1);
                    }
                    $('#feedbackText').val('Please type your feedback here.');
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

    if ($('#nextToFeedbackPanel3').length) {
        $('#nextToFeedbackPanel3').click(function() {
            console.log("In forwardToPanel3 Click");
            if ($('#feedbackFormProjectList').prop('selectedIndex') >= 0) {
                if ($('#projectSelectionError').length) {
                    $('#projectSelectionError').remove();
                }
                $('#feedbackPanel2').animate({
                    left: -500
                });
                $('#feedbackPanel3').animate({
                    left: 0
                });
            } else {
                if ($('#projectSelectionError').length == 0) {
                    $('#feedbackFormProjectList').after('<p id="projectSelectionError">' +
                        'You must select a project to continue, or return the the previous screen and select' +
                        ' the Application feedback button.</p>');
                }
            }
        });
    }

    if ($('#backToPreviousPanel').length) {
        $('#backToPreviousPanel').click(function() {
            $('#feedbackPanel3').animate({
                left: 500
            });
            if (panel2InUse) {
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
