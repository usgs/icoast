<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/registrationCode.php');

$pageBody = <<<EOL
    <div id = "contentWrapper">
        <h1>USGS iCoast Registration</h1>
        <p>No account for <span class="userData">$userEmail</span> has been found within iCoast.<br>
            Please complete the following information to build your iCoast profile.</p>
        <form method="post" action="registration.php" id="registerForm">
            <input type="hidden" name="submission" value="register" />
            <input type="hidden" name="registerEmail" value="$userEmail" />
            <div class="formFieldRow">
                <label for="registerTimeZone">Time Zone: *</label>
                <select id="registerTimeZone" name="registerTimeZone" class="clickableButton"
                    title="Select the appropriate time zone that you reside in. If your time zone is not
                    listed please use UTC. (Select one).">
                    <option value="1" $timeZone1HTML>Eastern</option>
                    <option value="2" $timeZone2HTML>Central</option>
                    <option value="3" $timeZone3HTML>Mountain</option>
                    <option value="4" $timeZone4HTML>Mountain (Arizona)</option>
                    <option value="5" $timeZone5HTML>Pacific</option>
                    <option value="6" $timeZone6HTML>Alaskan</option>
                    <option value="7" $timeZone7HTML>Hawaiian</option>
                    <option value="8" $timeZone8HTML>UTC</option>
                </select>
                $timeZoneError
            </div>
            <div class="formFieldRow">
                <label for="registerCrowdType">Crowd Type: *</label>
                <select id="registerCrowdType" name="registerCrowdType" class="clickableButton"
                    title="Select a crowd type from this list that best defines your interest or experience
                    in the coastal environment. (Select one)">
                    <option value="1" $crowdType1HTML>Coastal & Marine Scientist</option>
                    <option value="2" $crowdType2HTML>Coastal Manager or Planner</option>
                    <option value="3" $crowdType3HTML>Coastal Resident</option>
                    <option value="4" $crowdType4HTML>Watersport Enthusiast</option>
                    <option value="5" $crowdType5HTML>Marine Science Student</option>
                    <option value="6" $crowdType6HTML>Emergency Responder</option>

                    <option value="8" $crowdType8HTML>Digital Crisis Volunteer (VTC)</option>
                    <option value="9" $crowdType9HTML>Interested Public</option>
                    <option value="10" $crowdType10HTML>Other (Please specify below)</option>
                </select>
                $crowdTypeError
            </div>
            <div class="formFieldRow" id="registerOtherRow">
                <label for="registerOther">Other Crowd Type *: </label>
                <input type="text" id="registerOther" class="clickableButton" name="registerOther"
                    value="$registerOtherContent" title="Use this text field to specify a crowd type not
                    included in the listbox above. (Max 255 characters)"/>
                $otherCrowdTypeError
            </div>
            <div class="formFieldRow">
                <label class="multiline" for="registerAffiliation">Coastal Expertise / Affiliation<br>(optional): </label>
                <input type="text" id="registerAffiliation" class="clickableButton" name="registerAffiliation"
                    value="$registerAffiliationContent" title="This optional text field allows you to specify
                    and relevant experience or professional affiiation you may have. (Max 255 Characters)"/>
                $registerAffiliationError
            </div>
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton"
                    value="Complete Registration" title="Click to submit your registration information."/>
            </div>
        </form>
        <p class="footer">* indicates the field is required.</p>
    </div>
EOL;

require_once("includes/template.php");
