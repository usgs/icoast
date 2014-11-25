<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/profileCode.php');
$pageBody = <<<EOL
        <div id="contentWrapper">
            <h1>Your USGS iCoast Profile</h1>
            <div id="profileSettingsWrapper">
                <div id="profileDetailsWrapper">
                    $updateAck

                    <div class="formFieldRow profileUpdateField">
                        <label for="accountChangeButton">Login Account: <span class="userData">$maskedEmail</span></label>
                        <input type="button" id="accountChangeButton" value="Change Login Account" class="clickableButton"
                            title="Click this button to display the form that allows you to update your details">
                    </div>
                    <div id="changeAccountFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Login Account</h3>
                        <p>Your current login is <span class="userData">$maskedEmail</span></p>
                        <p>IMPORTANT: This option provides the ability for you to associate your iCoast login with a
                            different Google based account (includes USGS accounts). You should not change your address here
                            unless you already have already created and tested a new Google account. Type your new
                            account carefully or your iCoast login could become permanently disassociated with a
                            working account. At this time iCoast only accepts Google based accounts for authentication
                            (aperson@gmail.com, aperson@usgs.gov). Do not specify an account authenticated by any other entity.</p>
                        <form method="post" action="" id="accountForm">
                            <input type="hidden" name="formSubmission" value="account" />
                            <div class="formFieldRow">
                                <label for="newAccount">New login account:</label>
                                <input type="text" id="newAccount" class="formInputStyle" name="newAccount"
                                    value="$newAccount" title="Type your new login account details here.">
                                $newAccountError
                            </div>
                            <div class="formFieldRow">
                                <label for="confirmNewLogin">Confirm new login account:</label>
                                <input type="text" id="confirmNewLogin" class="formInputStyle" name="confirmNewLogin"
                                       value="$confirmNewLogin" title="Confirm your new login account details here.
                                           It must match the field above.">
                                       $confirmLoginError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel"
                                title="Use this button to leave the update screen without making any changes
                                to your account.">
                            <input type="submit" class="clickableButton" value="Change Login"
                                title="This button will submit your valid changes to the database.
                                Errors or problems with your change will be reported in red text.">
                        </form>
                    </div>

                    <div class="formFieldRow profileUpdateField">
                        <label for="crowdTypeChangeButton">Crowd Type: <span class="userData">$crowdType</span></label>
                        <input type="button" id="crowdTypeChangeButton" value="Change Crowd Type" class="clickableButton"
                            title="Click this button to display the form that allows you to update your details">
                    </div>
                    <div id="changeCrowdFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Crowd Type</h3>
                        <p>Your current crowd type is <span class="userData">$crowdType</span></p>
                        <form method="post" id="crowdForm">
                            <input type="hidden" name="formSubmission" value="crowd" />
                            <div class="formFieldRow">
                                <label for="crowdType">Choose a new crowd type:</label>
                                <select id="crowdType" name="crowdType" class="formInputStyle"
                                    title="Select a crowd type from this list that best defines your interest or experience
                                    in the coastal environment. (Select one)">
                                    $crowdTypeSelectHTML
                                    <option value="0" $crowdType0HTML>Other (Please specify below)</option>
                                </select>
                                $crowdTypeError
                            </div>
                            <div class="formFieldRow" id="profileOtherRow">
                                <label for="otherCrowdType">Other Crowd Type: </label>
                                <input type="text" id="otherCrowdType" class="formInputStyle" name="otherCrowdType"
                                    value="$otherCrowdType" title="Use this text field to specify a crowd type not
                                    included in the listbox above. (Max 255 characters)"/>
                                $otherCrowdTypeError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel"
                                title="Use this button to leave the update screen without making any changes
                                to your account.">
                            <input type="submit" class="clickableButton" value="Change Crowd Type"
                                title="This button will submit your valid changes to the database.
                                Errors or problems with your change will be reported in red text.">
                        </form>
                    </div>

                    <div class="formFieldRow profileUpdateField">
                        <label for="affiliationChangeButton">Coastal Expertise / Affiliation: <span class="userData">$affiliation</span></label>
                        <input type="button" id="affiliationChangeButton" value="Change Expertise/Affiliation" class="clickableButton"
                            title="Click this button to display the form that allows you to update your details">
                    </div>
                    <div id="changeAffiliationFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Coastal Expertise / Affiliation</h3>
                        <p>Your current Expertise / Affiliation is <span class="userData">$affiliation</span></p>
                        <form method="post" id="affiliationForm">
                            <input type="hidden" name="formSubmission" value="affiliation" />
                            <div class="formFieldRow">
                                <label class="multiline" for="affiliation">New coastal expertise / affiliation<br>(optional): </label>
                                <input type="text" id="affiliation" class="formInputStyle" name="affiliation"
                                    value="$affiliationContent" title="This optional text field allows you to specify
                                    and relevant experience or professional affiiation you may have.
                                    (Max 255 Characters)"/>
                                $affiliationError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel"
                                title="Use this button to leave the update screen without making any changes
                                to your account.">
                            <input type="submit" class="clickableButton" value="Change Affiliation"
                                title="This button will submit your valid changes to the database.
                                Errors or problems with your change will be reported in red text.">
                        </form>
                    </div>

                    <div class="formFieldRow profileUpdateField">
                        <label for="timeZoneChangeButton">Time Zone: <span class="userData">$timeZone</span></label>
                        <input type="button" id="timeZoneChangeButton" value="Change Time Zone" class="clickableButton"
                            title="Click this button to display the form that allows you to update your details">
                    </div>
                    <div id="changeTimeZoneFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Time Zone</h3>
                        <p>Your current TimeZone is set to  <span class="userData">$timeZone</span></p>
                        <form method="post" id="timeZoneForm">
                            <input type="hidden" name="formSubmission" value="timeZone" />
                            <div class="formFieldRow">
                                <label for="timeZone">Choose a new time zone:</label>
                                <select id="timeZone" name="timeZone" class="formInputStyle" title="Select the
                                    appropriate time zone that you reside in. If your time zone is not listed
                                    please use UTC. (Select one).">
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
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel"
                                title="Use this button to leave the update screen without making any changes
                                to your account.">
                            <input type="submit" class="clickableButton" value="Change Time Zone"
                                title="This button will submit your valid changes to the database.
                                Errors or problems with your change will be reported in red text.">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="emailPreferenceChangeButton">Email Preference: <span class="userData">$emailPreference</span></label>
                        <input type="button" id="emailPreferenceChangeButton" value="Change Email Preference" class="clickableButton"
                            title="Click this button to display the form that allows you to change your email preference.">
                    </div>
                    <div id="changeEmailPreferenceFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Email Preference</h3>
                        <p>Your have currently chosen to $emailPreferenceDetail receiving iCoast related emails.</p>
                        <p>Opting to receive iCoast emails means we will send you occasional messages informing you of new features,
                            photographs, and projects as well as updates regarding existing projects.</p><p>Your address is never passed
                            on to anyone else and will not be used for any other purposes beyond the scope of this system.</p>
                        <form method="post" action="" id="emailPreferenceForm">
                            <input type="hidden" name="formSubmission" value="emailPreference" />
                            <div>
                                <input type="radio" id="optIn" name="emailPreference"
                                    value="in" title="Select this option if you wish to receive iCoast related emails from USGS." $emailOptInSelected>
                                <label for="optIn" class="clickableButton">Opt In</label>
                                <input type="radio" id="optOut" name="emailPreference"
                                       value="out" title="Select this option if you DO NOT wish to receive iCoast related emails from USGS." $emailOptOutSelected>
                                <label for="optOut" class="clickableButton">Opt Out</label>
                            </div>
                            $emailPreferenceError
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel"
                                title="Use this button to leave the update screen without making any changes
                                to your account.">
                            <input type="submit" class="clickableButton" value="Change Email Preference"
                                title="This button will submit your valid changes to the database.
                                Errors or problems with your change will be reported in red text.">
                        </form>
                    </div>






                </div>
            </div>
        </div>

EOL;

require_once('includes/template.php');
