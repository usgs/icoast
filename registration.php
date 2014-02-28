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
                <label for="registerCrowdType">Crowd Type: *</label>
                <select id="registerCrowdType" name="registerCrowdType" >
                    <option class="selectOption" value="1" $select1HTML>Coastal Science Researcher</option>
                    <option value="2" $select2HTML>Coastal Manager or Planner</option>
                    <option value="3" $select3HTML>Coastal Resident</option>
                    <option value="4" $select4HTML>Coastal Recreational User</option>
                    <option value="5" $select5HTML>Marine Science Student</option>
                    <option value="6" $select6HTML>Emergency Manager</option>
                    <option value="7" $select7HTML>Policy Maker</option>
                    <option value="8" $select8HTML>Digital Crisis Volunteer (VTC)</option>
                    <option value="9" $select9HTML>Interested Public</option>
                    <option value="10" $select10HTML>Other (Please specify below)</option>
                </select>
                $crowdTypeError
            </div>
            <div class="formFieldRow" id="registerOtherRow">
                <label for="registerOther">Other Crowd Type *: </label>
                <input type="text" id="registerOther" name="registerOther" value="$registerOtherContent" />
                $otherCrowdTypeError
            </div>
            <div class="formFieldRow">
                <label for="registerAffiliation">Affiliation: </label>
                <input type="text" id="registerAffiliation" name="registerAffiliation" value="$registerAffiliationContent" />
                $registerAffiliationError
            </div>
            <div class="formFieldRow standAloneFormElement">
                <input type="submit" class="clickableButton formButton" id="registerSubmitButton" value="Complete Registration" />
            </div>
        </form>
        <div id="contentWrapperFooter">
            <p>* indicates the field is required.</p>
        </div>
    </div>
EOL;

require("includes/template.php");