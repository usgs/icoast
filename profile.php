<?php

require('includes/pageCode/profileCode.php');
$pageBody = <<<EOL
        <div id="contentWrapper">
            <h1>Your iCoast Profile and Tagging History</h1>
            <div id="profileSettingsWrapper">
                <button id="profileHideButton" class="clickableButton hideProfilePanelButton">Hide Profile Details</button>
                <h2>Profile Details</h2>
                <div id="profileDetailsWrapper">
                    $updateAck





                    <div class="formFieldRow profileUpdateField">
                        <label for="emailChangeButton">Email: <span class="userData">$maskedEmail</span></label>
                        <input type="button" id="emailChangeButton" value="Change Email Address">
                    </div>
                    <div id="changeEmailFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Email Address</h3>
                        <p>Your current Email address is <span class="userData">$maskedEmail</span></p>
                        <p>IMPORTANT: This option provides the ability for you to associate your iCoast account with a
                            different Google account (including USGS accounts). You should not change your address here
                            unless you already have already created and tested your new Google account. Type your new
                            address carefully or your iCoast account could become permanently disassociated with a
                            working login account. At this time iCoast only accepts Google based accounts
                            (aperson@gmail.com, aperson@usgs.gov). Do not specify an address supported by any other entity.</p>
                        <form method="post" action="" id="eMailForm">
                            <input type="hidden" name="formSubmission" value="email" />
                            <div class="formFieldRow">
                                <label for="newEmail">New Email Address:</label>
                                <input type="text" id="newEmail" name="newEmail" value="$newEmail">
                                $newEmailError
                            </div>
                            <div class="formFieldRow">
                                <label for="confirmNewEmail">Confirm New Email Address:</label>
                                <input type="text" id="confirmNewEmail" name="confirmNewEmail"
                                       value="$confirmNewEmail">
                                       $confirmEmailError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                            <input type="submit" class="clickableButton" value="Change Email">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="crowdTypeChangeButton">Crowd Type: <span class="userData">$crowdType</span></label>
                        <input type="button" id="crowdTypeChangeButton" value="Change Crowd Type">
                    </div>
                    <div id="changeCrowdFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Crowd Type</h3>
                        <p>Your current crowd type is <span class="userData">$crowdType</span></p>
                        <form method="post" id="crowdForm">
                            <input type="hidden" name="formSubmission" value="crowd" />
                            <div class="formFieldRow">
                                <label for="crowdType">Choose a new crowd type:</label>
                                <select id="crowdType" name="crowdType" >
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
                            <div class="formFieldRow" id="profileOtherRow">
                                <label for="otherCrowdType">Other Crowd Type: </label>
                                <input type="text" id="otherCrowdType" name="otherCrowdType" value="$otherCrowdType"/>
                                $otherCrowdTypeError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                            <input type="submit" class="clickableButton" value="Change Crowd Type">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="affiliationChangeButton">Expertise or Affiliation: <span class="userData">$affiliation</span></label>
                        <input type="button" id="affiliationChangeButton" value="Change Expertise/Affiliation">
                    </div>
                    <div id="changeAffiliationFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Expertise or Affiliation</h3>
                        <p>Your current Expertise or Affiliation is <span class="userData">$affiliation</span></p>
                        <form method="post" id="affiliationForm">
                            <input type="hidden" name="formSubmission" value="affiliation" />
                            <div class="formFieldRow">
                                <label class="multiline" for="affiliation">New coastal expertise or affiliation<br>(optional): </label>
                                <input type="text" id="affiliation" name="affiliation" value="$affiliationContent" />
                                $affiliationError
                            </div>
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                            <input type="submit" class="clickableButton" value="Change Affiliation">
                        </form>
                    </div>






                    <div class="formFieldRow profileUpdateField">
                        <label for="timeZoneChangeButton">Time Zone: <span class="userData">$timeZone</span></label>
                        <input type="button" id="timeZoneChangeButton" value="Change Time Zone">
                    </div>
                    <div id="changeTimeZoneFormWrapper" class="profileUpdateForm">
                        <h3>Change Your Time Zone</h3>
                        <p>Your current TimeZone is set to  <span class="userData">$timeZone</span></p>
                        <form method="post" id="timeZoneForm">
                            <input type="hidden" name="formSubmission" value="timeZone" />
                            <div class="formFieldRow">
                                <label for="timeZone">Choose a new time zone:</label>
                                <select id="timeZone" name="timeZone" >
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
                            <input type="button" class="clickableButton cancelUpdateButton" value="Cancel">
                            <input type="submit" class="clickableButton" value="Change Time Zone">
                        </form>
                    </div>






                </div>


            </div>
            <div id="profileAnnotationListControls">
                <h2>View Your iCoast Tagging History</h2>
                <div id="historyControlWrapper">
                    <p>Choose from the options below to view either your complete tagging history across all of iCoast's projects...</p>
                    <input type="button" id="allPhotoButton" value="Complete History"><br>
                    <p>...or your specific history for a particular project</p>
                    <select id="projectSelection">
                        $projectSelectionOptions
                    </select>
                    <input type="button" id="projectPhotoButton" value="Specific Project History">
                </div>
            </div>
            <div id="userAnnotationHistory">

                <div id="profileMapWrapper">
                    <h2>Map of Photos You Tagged</h2>
                    <input id="pac-input" class="controls" type="text" placeholder="Search Box">
                    <p>Select a marker to highlight the annotation in the adjacent table.</p>
                    <div id="mapCanvas"></div>
                </div>

                <div id="profileTableWrapper">
                    <h2>Photos You Tagged</h2>
                    <p>Select any row to be taken to that image.<br>
                        Red text indicates that tagging of the photo is incomplete.</p>
                    <div id="historyTableWrapper">
                        <div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date Annotated</th>
                                        <th>Time Spent</th>
                                        <th># of Tags</th>
                                        <th>Photo Location</th>
                                        <th>Image ID</th>
                                        <th>Project Name</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                        <div id="annotationTableControls">
                            <input type="button" id="firstPageButton" class="clickableButton disabledClickableButton" value="<<">
                            <input type="button" id="previousPageButton" class="clickableButton disabledClickableButton" value="<">
                            <select id="resultSizeSelect" class="disabledClickableButton" disabled>
                                <option value="10">10 Results Per Page</option>
                                <option value="20">20 Results Per Page</option>
                                <option value="30">30 Results Per Page</option>
                                <option value="50">50 Results Per Page</option>
                                <option value="100">100 Results Per Page</option>
                            </select>
                            <input type="button" id="lastPageButton" class="clickableButton disabledClickableButton" value=">>">
                            <input type="button" id="nextPageButton" class="clickableButton disabledClickableButton" value=">">
                        </div>
                    </div>
                </div>


            </div>
        </div>

EOL;

require('includes/template.php');
