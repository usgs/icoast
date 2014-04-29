<?php

require('includes/pageCode/profileCode.php');
$pageBody = <<<EOL
        <div id="contentWrapper">
            <h1>Your USGS iCoast Profile and Tagging History</h1>
            <div id="profileSettingsWrapper">
                <button id="profileHideButton" class="clickableButton hideProfilePanelButton" title="Use this
                    button to show or hide your profile information such as registration account, timezone,
                        etc.">Hide Profile Details</button>
                <h2>Profile Details</h2>
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
                                <input type="text" id="newAccount" class="clickableButton" name="newAccount"
                                    value="$newAccount" title="Type your new login account details here.">
                                $newAccountError
                            </div>
                            <div class="formFieldRow">
                                <label for="confirmNewLogin">Confirm new login account:</label>
                                <input type="text" id="confirmNewLogin" class="clickableButton" name="confirmNewLogin"
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
                                <select id="crowdType" name="crowdType" class="clickableButton"
                                    title="Select a crowd type from this list that best defines your interest or experience
                                    in the coastal environment. (Select one)">
                                    <option value="1" $crowdType1HTML>Coastal & Marine Scientist</option>
                                    <option value="2" $crowdType2HTML>Coastal Manager or Planner</option>
                                    <option value="3" $crowdType3HTML>Coastal Resident</option>
                                    <option value="4" $crowdType4HTML title="Someone who enjoys the beach">
                                        Watersport Enthusiast
                                    </option>
                                    <option value="5" $crowdType5HTML>Marine Science Student</option>
                                    <option value="6" $crowdType6HTML>Emergency Responder</option>
                                    <option value="8" $crowdType8HTML>Digital Crisis Volunteer (VTC)</option>
                                    <option value="9" $crowdType9HTML>Interested Public</option>
                                    <option value="10" $crowdType10HTML>Other (Please specify below)</option>
                                </select>
                                $crowdTypeError
                            </div>
                            <div class="formFieldRow" id="profileOtherRow">
                                <label for="otherCrowdType">Other Crowd Type: </label>
                                <input type="text" id="otherCrowdType" class="clickableButton" name="otherCrowdType"
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
                                <input type="text" id="affiliation" class="clickableButton" name="affiliation"
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
                                <select id="timeZone" name="timeZone" class="clickableButton" title="Select the
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






                </div>


            </div>
            <div id="profileAnnotationListControls">
                <h2>View Your USGS iCoast Tagging History</h2>
                <div id="historyControlWrapper">
                    <p>Choose from the options below to view either your complete tagging history across all of iCoast's projects...</p>
                    <input type="button" id="allPhotoButton" value="Complete History" class="clickableButton"
                        title="Clicking this button will display details of all of the photos you have tagged in iCoast.">
                        <br>
                    <p>...or your specific history for a particular project</p>
                    <select id="projectSelection" class="clickableButton"
                        title="This select box lists all of the projects in which you have tagged photos. Use it
                            in conjunction with the Specific Project History button found to the right to
                            display photos you have tagged for a specific project.">
                        $projectSelectionOptions
                    </select>
                    <input type="button" id="projectPhotoButton" value="Specific Project History" class="clickableButton"
                        title="Use this button in conjunction with the select box found to the left to display photos
                        you have tagged for a specific project.">
                </div>
            </div>
            <div id="userAnnotationHistory">

                <div id="profileMapWrapper">
                    <h2>Map of Photos You Tagged</h2>
                    <p>Select a marker to highlight the annotation in the adjacent table.</p>
                    <div id="mapCanvas"></div>
                </div>

                <div id="profileTableWrapper">
                    <h2>Photos You Tagged</h2>
                    <p>Select the Tag button to be taken to that image.<br>
                        Red button text indicates that tagging of the photo is incomplete.</p>
                    <div id="historyTableWrapper">
                        <div>
                            <table>
                                <thead>
                                    <tr>
                                        <th title="The buttons in this column will take you to the classification
                                            page for that photo. There you can edit your selections and complete
                                            unfinished tasks. Red buttons indicate one or more tasks were not
                                            finished for that photo.">Photo Link</th>
                                        <th title="The date and time you finished annotating a particular photo.">
                                            Date Annotated</th>
                                        <th title="The time you spent tagging a particular photo.">Time Spent</th>
                                        <th title="The number of tags you selected as you annotated a particular photo">
                                            # of Tags</th>
                                        <th title="The name of the state and closest city to the photo.">
                                            Photo<br>Location</th>
                                        <th title="The ID number of the image you tagged. Use this number to
                                            direct others to a particular image of interest.">Image ID</th>
                                        <th title="The name of the project a particular photo was a member of.">
                                            Project Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        <div id="annotationTableControls">
                            <input type="button" id="firstPageButton" class="clickableButton disabledClickableButton"
                                value="<<" title="Use this button to jump to the first page of results.">
                            <input type="button" id="previousPageButton" class="clickableButton disabledClickableButton"
                                value="<" title="use this button to display the previous page of results.">
                            <select id="resultSizeSelect" class="clickableButton disabledClickableButton"
                                title="Changing the value in this select box will increase or decrease the number
                                    of rows shown on each page of the table." disabled>
                                <option value="10">10 Results Per Page</option>
                                <option value="20">20 Results Per Page</option>
                                <option value="30">30 Results Per Page</option>
                                <option value="50">50 Results Per Page</option>
                                <option value="100">100 Results Per Page</option>
                            </select>
                            <input type="button" id="lastPageButton" class="clickableButton disabledClickableButton"
                                value=">>" title="Use this button to jump to the last page of results.">
                            <input type="button" id="nextPageButton" class="clickableButton disabledClickableButton"
                                value=">" title="Use this button to display the next page of results.">
                        </div>
                    </div>
                </div>


            </div>
        </div>

EOL;

require('includes/template.php');
