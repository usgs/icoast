<?php

ob_start();
require('includes/pageCode/registrationCode.php');

$pageBody = <<<EOL
    <div id = "contentWrapper">
        <h1>iCoast Registration</h1>
        <p>No account for <span class="userData">$userEmail</span> has been found within iCoast.<br>
            Please complete the following information to build your iCoast profile.</p>
        <form method="post" action="registration.php" id="registerForm">
            <input type="hidden" name="submission" value="register" />
            <input type="hidden" name="registerEmail" value="$userEmail" />
            <div class="formFieldRow">
                <label for="registerTimeZone">Time Zone: *</label>
                <select id="registerTimeZone" name="registerTimeZone" >
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
                <select id="registerCrowdType" name="registerCrowdType" >
                    <option value="1" $crowdType1HTML>Coastal Science Researcher</option>
                    <option value="2" $crowdType2HTML>Coastal Manager or Planner</option>
                    <option value="3" $crowdType3HTML>Coastal Resident</option>
                    <option value="4" $crowdType4HTML>Watersport Enthusiast</option>
                    <option value="5" $crowdType5HTML>Marine Science Student</option>
                    <option value="6" $crowdType6HTML>Emergency Manager</option>
                    <option value="7" $crowdType7HTML>Policy Maker</option>
                    <option value="8" $crowdType8HTML>Digital Crisis Volunteer (VTC)</option>
                    <option value="9" $crowdType9HTML>Interested Public</option>
                    <option value="10" $crowdType10HTML>Other (Please specify below)</option>
                </select>
                $crowdTypeError
            </div>
            <div class="formFieldRow" id="registerOtherRow">
                <label for="registerOther">Other Crowd Type *: </label>
                <input type="text" id="registerOther" name="registerOther" value="$registerOtherContent" />
                $otherCrowdTypeError
            </div>
            <div class="formFieldRow">
                <label class="multiline" for="registerAffiliation">Coastal Expertise or Affiliation<br>(optional): </label>
                <input type="text" id="registerAffiliation" name="registerAffiliation" value="$registerAffiliationContent" />
                $registerAffiliationError
            </div>
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton" value="Complete Registration" />
            </div>
        </form>
        <p class="footer">* indicates the field is required.</p>
    </div>
EOL;

require("includes/template.php");
