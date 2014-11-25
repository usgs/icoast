<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/myicoastCode.php');
$pageBody = <<<EOL
        <div id="contentWrapper">
            <h1>Your USGS iCoast Statistics and Tagging History</h1>
            <h2>USGS iCoast Account Statistics</h2>
            <p>On <span class="userData">$formattedAccoutCreatedOnTime</span> you created your iCoast account.</p>
            <p>You last logged in to iCoast on <span class="userData">$formattedLastLoggedInTime</span>.</p>
            $lastProjectNameHTML
            <h2 id="statsAndHistory">Your $statisticTargetTitle Tagging Statistics</h2>
            <div id="projectSelectControl">
                $projectSelectControlHTML
            </div>
            <div id="userAnnotationStatistics">
                $userAnnotationStatisticsHTML
            </div>
            <h2 id="statsAndHistory">Your $statisticTargetTitle Tagging History</h2>
            <div id="userAnnotationHistory">
                <div id="profileMapWrapper">
                    <h3>Map of Photos You Tagged</h3>
                    <p>Hover over a marker for more details.<br>
                        Click a marker to highlight the entry in the table.</p>
                    <div class="userMapWrapper">
                        <div id="userClassificationLocationMap" class="userMap"></div>
                        <div class="userMapLegend">
                            <div class="userMapLegendRow">
                              <p>ZOOM IN TO SEE<br>INDIVIDUAL PHOTOS</p>
                            </div>
                            <div class="userMapLegendRow">
                              <div class="userMapLegendRowIcon">
                                <img src="images/system/clusterLegendIcon.png" alt="Image of the map cluster symbol"
                                    width="24" height="24" title="">
                              </div>
                              <div class="userMapLegendRowText">
                                <p>Clustering of Photos</p>
                              </div>
                            </div>
                            <div class="userMapLegendRow">
                              <div class="userMapLegendRowIcon">
                                <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                                    width="13" height="24" title="">
                              </div>
                              <div class="userMapLegendRowText">
                                <p>Complete Classification</p>
                              </div>
                            </div>
                            <div class="userMapLegendRow">
                              <div class="userMapLegendRowIcon">
                                <img src="images/system/redMarker.png" alt="Image of a red map marker pin"
                                    width="13" height="24" title="">
                              </div>
                              <div class="userMapLegendRowText">
                                <p>Incomplete Classification</p>
                              </div>
                            </div>
                        </div>
                        </div>
                    </div>


                <div id="profileTableWrapper">
                    <h3>Photos You Tagged</h3>
                    <p>Select the Tag button to be taken to that image.<br>
                        Red button text indicates that tagging of the photo is incomplete.<br></p>
                    <div id="historyTableWrapper">
                        <div>
                            <table>
                                <thead>
                                    <tr>
                                        <th title="The buttons in this column will take you to the classification
                                            page for that photo. There you can edit your selections and complete
                                            unfinished tasks. Red buttons indicate one or more tasks were not
                                            finished for that photo.">Photo Link</th>
                                        <th title="The date and time you finished classifying a particular photo.">
                                            Date Classified</th>
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
                            <select id="resultSizeSelect" class="formInputStyle disabledClickableButton"
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
        </div>
EOL;

require_once('includes/template.php');
