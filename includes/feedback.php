<?php
require_once('feedbackCode.php');

$feedbackPageHTML = <<<EOL
    <div id="feedbackWrapper">
        <div id="feedbackToggle">
            <p>Feedback</p>
        </div>
        <div id="feedbackContent">
            <form id="userFeedbackForm">
                $hiddenFormData
                <div id="feedbackPanel1" class="feedbackPanel">
                    <h1>iCoast Feedback</h1>
                    $feedbackPanel1
                </div>
                <div id="feedbackPanel2" class="feedbackPanel">
                    $feedbackPanel2
                </div>
                <div id="feedbackPanel3" class="feedbackPanel">
                    $feedbackPanel3
                </div>
                <div id="confirmationPanel" class="feedbackPanel">
                    <h2>Thanks!</h2>
                    <p>Your feedback has been sucessfully received.</p>
                    <p>We appreciate your help to improve iCoast.</p>
                    <div class="feedbackButtonWrapper">
                        <input type="button" id="closeConfirmation" class="clickableButton" value="Close">
                    </div>
                </div>
            </form>
        </div>
    </div>
EOL;





