<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/reviewProjectCode.php');
$pageBody = <<<EOL

        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "$displaySafeProjectName" Project Creator</h1>
                <h2>New Project Details Review</h2>
                <p class="error">$updateStatus</p>
                <h3 id="projectDetailsHeader">Project Details</h3>
                <p>This section contains details about the project as a whole, particularly information indicating
                    the project coverage area which is the result of how the post and pre event collections relate to
                    each other (overlap). Use the buttons on the right to make project level changes. Collection level changes
                    can be implemented in the sections below.</p>
                <p id="noProjectImagesError" class="error" style="display: none">
                    No image matches were found between your two collections. This suggests that the regions
                    covered by the post and pre-event collections did not overlap, did overlap but matching images
                    were removed during the sequencing process, or none of the available images were near each other.
                    Look at the collection details and map below to determine the problem. Then either resequence the current
                    collections ensuring overlapping images remain in the collections or replace one or both collections
                    with more appropriate selections.
                </p>
                <div id="projectReviewWrapper" style="display: block;">
                    <div id="projectDetailsWrapper">
                        <table class="adminStatisticsTable">
                            <tbody>
                                <tr>
                                    <td>Name:</td>
                                    <td class="userData">$displaySafeProjectName</td>
                                </tr>
                                <tr>
                                    <td>Description:</td>
                                    <td class="userData">$displaySafeProjectDescription</td>
                                </tr>
                                <tr>
                                    <td>Post-Event Image Header:</td>
                                    <td class="userData">$displaySafePostImageHeader</td>
                                </tr>
                                <tr>
                                    <td>Pre-Event Image Header:</td>
                                    <td class="userData">$displaySafePreImageHeader</td>
                                </tr>
                                <tr>
                                    <td>Number Of Images Available For Classification:</td>
                                    <td class="userData">$numberOfImagesInProject</td>
                                </tr>
                                <tr class="projectRangeDetails">
                                    <td>Project Geographical Range:</td>
                                    <td class="collectionDetailsField userData">$projectGeoRange</td>
                                </tr>
                                <tr class="projectRangeDetails">
                                    <td>Project Date Range:</td>
                                    <td class="collectionDetailsField userData">$projectDateRange</td>
                                </tr>
                                <tr class="projectRangeDetails">
                                    <td>Project Geographical Range By Date:</td>
                                    <td class="collectionDetailsField userData">$projectGeoRangeByDate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="projectOptionsWrapper">
                        <button type="button" id="editProjectDetailsButton" class="clickableButton enlargedClickableButton projectReviewButton">
                            Edit Project Details
                        </button>
                        <button type="button" id="previewQuestionsButton" class="clickableButton enlargedClickableButton projectReviewButton">
                            Preview Questions
                        </button>
                        <button type="button" id="editQuestionsButton" class="clickableButton enlargedClickableButton projectReviewButton">
                            Edit Questions
                        </button>

                    </div>

                </div>
                <div id="collectionsReviewWrapper">
                    <div class="collectionDetailsWrapper">
                        <h3>Post-Event Collection Details</h3>
                    </div>
                    <div class="collectionDetailsWrapper">
                        <h3>Pre-Event Collection Details</h3>
                    </div>
                    <div>
                        <p>These sections contain details about the collections you used
                            to build this project. If your project's coverage (see the map below) or content
                            was not as expected then you may remove an incorrect collection now.
                            New collections uploaded as part of making this project will be deleted from iCoast
                            entirely. Collections that already existed in iCoast will be removed from this project
                            but still remain in iCoast for other projects to continue to use. New collections
                            may also have their name name and description amended.
                            Use the buttons below to make collection changes.</p>
                    </div>
                    <div class="collectionDetailsWrapper">
                        <table class="adminStatisticsTable">
                            <tbody>
                                <tr>
                                    <td>Name:</td>
                                    <td class="collectionDetailsField userData">$displaySafePostCollectionName</td>
                                </tr>
                                <tr>
                                    <td>Description:</td>
                                    <td class="collectionDetailsField userData">$displaySafePostCollectionDescription</td>
                                </tr>
                                <tr>
                                    <td>New or Existing:</td>
                                    <td class="collectionDetailsField userData">{$postCollectionMetadata['type']}</td>
                                </tr>
                                <tr>
                                    <td>Number Of Images:</td>
                                    <td class="userData">$postCollectionImageCount</td>
                                </tr>
                                <tr>
                                    <td>Date Range:</td>
                                    <td class="collectionDetailsField userData">$postCollectionDateRange</td>
                                </tr>
                                <tr>
                                    <td>Geographical Range:</td>
                                    <td class="collectionDetailsField userData">$postCollectionGeoRange</td>
                                </tr>
                                <tr>
                                    <td>Geographical Range By Date:</td>
                                    <td class="collectionDetailsField userData">$postCollectionGeoRangeByDate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="collectionDetailsWrapper">
                        <table class="adminStatisticsTable">
                            <tbody>
                                <tr>
                                    <td>Name:</td>
                                    <td class="collectionDetailsField userData">$displaySafePreCollectionName</td>
                                </tr>
                                <tr>
                                    <td>Description:</td>
                                    <td class="collectionDetailsField userData">$displaySafePreCollectionDescription</td>
                                </tr>
                                <tr>
                                    <td>New or Existing:</td>
                                    <td class="collectionDetailsField userData">{$preCollectionMetadata['type']}</td>
                                </tr>
                                <tr>
                                    <td>Number Of Images:</td>
                                    <td class="userData">$preCollectionImageCount</td>
                                </tr>
                                <tr>
                                    <td>Date Range:</td>
                                    <td class="collectionDetailsField userData">$preCollectionDateRange</td>
                                </tr>
                                <tr>
                                    <td>Geographical Range:</td>
                                    <td class="collectionDetailsField userData">$preCollectionGeoRange</td>
                                </tr>
                                <tr>
                                    <td>Geographical Range By Date:</td>
                                    <td class="collectionDetailsField userData">$preCollectionGeoRangeByDate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>


                </div>
                <div id="collectionsReviewControlsWrapper">
                    <div class="collectionDetailsWrapper">
                        <h3>Post-Event Collection Controls</h3>
                        $postCollectionButtonHTML
                    </div>
                    <div class="collectionDetailsWrapper">
                        <h3>Pre-Event Collection Controls</h3>
                        $preCollectionButtonHTML
                    </div>

                </div>
                <div id="mapReviewWrapper">
                    <h3>Project Coverage Map</h3>
                    <p>Here you can see the area that your new project will cover as well as the areas covered by the
                        two collections you used. Only areas where the two collections closely overlap and in which images are within
                        a defined search radius (400m default) of one another will be available in your project. You can turn the project and collection
                        lines on and off as well as display markers for each image your project will present to iCoast users.</p>
                    <p>If your project contains holes in areas where both collections overlap you can try to increase
                        the match search radius from default and run the matching process again using the options presented below the map. Increasing the radius too much
                        may decrease the accuracy of some fringe image matches presented to the user but could be the only option if the two collection
                        flight paths run in parallel but are separated by too greater distance.</p>
                    <p>The $matchRadiusOptionText <span class="userData">match radius of {$matchRadius}m resulted in $numberOfMatches of $numberOfPotentialMatches 
                        ({$percentageOfMatches}%)</span> post-event images finding a pre-event match.</p>
                    <div id="reviewMapWrapper">
                        <div id="reviewMap"></div>
                        <div class="adminMapLegend">
                            <div class="adminMapLegendRow">
                                <p>Map Key</p>
                            </div>
                            <div class="adminMapLegendRow">
                                <div class="adminMapLegendRowIcon">
                                    <div style="background-color:#00ff00;"></div>
                                </div>
                                <div class="adminMapLegendSingleRowText">
                                    <p>Project</p>
                                </div>
                            </div>
                            <div class="adminMapLegendRow">
                                <div class="adminMapLegendRowIcon">
                                    <div style="background-color:#ff0000;"></div>
                                </div>
                                <div class="adminMapLegendSingleRowText">
                                    <p>Post-Event Collection</p>
                                </div>
                            </div>
                            <div class="adminMapLegendRow">
                                <div class="adminMapLegendRowIcon">
                                    <div style="background-color:#5555ff;"></div>
                                </div>
                                <div class="adminMapLegendSingleRowText">
                                    <p>Pre-Event Collection</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button type="button" id="toggleProjectLine" class="clickableButton mapButton">
                            Hide Project Line
                        </button>
                        <button type="button" id="toggleMarkers" class="clickableButton mapButton">
                            Hide Project Images
                        </button>
                        <button type="button" id="togglePostLine" class="clickableButton mapButton">
                            Show Post-Event Line
                        </button>
                        <button type="button" id="togglePreLine" class="clickableButton mapButton">
                            Show Pre-Event Line
                        </button>
                    </div>
                    <form method="get" autocomplete="off" action="matchCollections.php">
                        <p style="display:inline">Re-match collections using new search radius of:</p>
                        <input type="hidden" name="projectId" value="{$projectMetadata['project_id']}">
                        <select class="clickableButton" name="matchRadius">
                            $matchRadiusOptions
                        </select>
                        <button type="submit" class="clickableButton mapButton">Re-Match Collections</button>
                    </form>
                </div>
                <hr>
                <div>
                    <input type="checkbox" id="liveButton">
                    <label for="liveButton" id="liveButtonlabel" class="clickableButton confirmOptionButton"
                           title="Selecting this option will make the project immediatly available to the public when accepted.">
                        Make Public On Confirmation
                    </label>

                    <input type="checkbox" id="focusedButton" disabled>
                    <label for="focusedButton" id="focusedButtonLabel" class="clickableButton disabledClickableButton confirmOptionButton"
                           title="Selecting this option will make this project the focus of the home page statistics.
                           It is only currently available if you make the project live at this point. It can be changed at a later time.">
                        Make The Focus Of The Home Page
                    </label>

                </div>
                <div>
                    <button type="button" id="acceptButton" class="clickableButton enlargedClickableButton acceptDenyButton"
                            title="Clicking this button will make final changes to the database to commit this project to iCoast.">
                        Accept Project
                    </button>
                    <form method="post" autocomplete="off" action="projectCreator.php" style="display: inline">
                        <input type="hidden" name="projectId" value="{$projectMetadata['project_id']}">
                        <input type="hidden" name="delete" >
                        <input type="hidden" name="review" >
                        <button type="submit" id="deleteProjectButton" class="clickableButton enlargedClickableButton acceptDenyButton"
                                title="Clicking this button will provide the option to delete the project, any uploaded collections,
                                and related images (tooltips) from iCoast. Once deleted the project cannot be recovered and would
                                need to be recreated.">
                            Delete Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
EOL;

require('includes/template.php');
