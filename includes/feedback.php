<?php
require_once('feedbackCode.php');

$feedbackPageHTML = <<<EOL
    <div id="feedbackBackground">
    </div>
    <div id="feedbackWrapper">
        <div id="feedbackToggle" title="Click to open the Feedback Panel so you can provide us with details
            of any bugs, suggestions or feature requests for iCoast">
            <p>Feedback</p>
        </div>
        <div id="feedbackContent">
            <form id="userFeedbackForm">
                <input type="hidden" name="userId" value="$userId">
                <input type="hidden" name="url" value="$url">
                <input type="hidden" name="queryString" value="$queryString">
                <input type="hidden" name="postData" value="$postData">
                <input type="hidden" name="clientAgent" value="$clientAgent">
                <input type="hidden" name="eventSummary" value="User Feedback">
                <div id="feedbackPanel1" class="feedbackPanel">
                    <h1>iCoast Feedback</h1>
                    $feedbackPanel1
                </div>
                <div id="feedbackPanel2" class="feedbackPanel">
                    <p>Select the project that you wish to provide feedback for...</p>
                    <select id="feedbackFormProjectList" name="eventCode" class="clickableButton" title="Select
                        the iCoast project you would like to provide feedback for from the list here.">
                        $projectSelectOptionHTML
                    </select>
                    <div class="feedbackButtonWrapper">
                        <input type="button" id="backToPanel1" class="clickableButton" title="Takes
                        you back to the previous feedback step." value="Back">
                        $cancelFeedbackButtonHTML
                        <input type="button" id="nextToFeedbackPanel3" class="clickableButton" title="Takes
                        you to the next step in submitting your feedback." value="Next">
                    </div>
                </div>
                <div id="feedbackPanel3" class="feedbackPanel">
                    <p><label for="feedbackText">What would you like to tell us?</label></p>
                    <textarea rows="4" cols="55" id="feedbackText" name="eventText" maxlength="500"></textarea>
                    <p id="feedbackCharacterCount">0 of 500 characters used</p>
                     <div class="feedbackButtonWrapper">
                        <input type="button" id="backToPreviousPanel" class="clickableButton" title="Takes
                        you back to the previous feedback step." value="Back">
                        $cancelFeedbackButtonHTML
                        <input type="button" id="sendFeedback" class="clickableButton" title="Sends your
                            finished feedback to the iCoast administrators." value="Send Feedback">
                    </div>
                </div>
                <div id="confirmationPanel" class="feedbackPanel">
                    <h2>Thanks!</h2>
                    <p>Your feedback has been sucessfully received.</p>
                    <p>We appreciate your help to improve iCoast.</p>
                    <div class="feedbackButtonWrapper">
                        <input type="button" class="clickableButton cancelFeedback" value="Close">
                    </div>
                </div>
            </form>
        </div>
    </div>
EOL;





