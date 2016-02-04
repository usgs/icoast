<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/reviewCollectionCode.php');
$pageBody = <<<HTML

        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "$displaySafeCollectionName" Collection Creator</h1>
                <h2>New Collection Details Review</h2>
                <p class="error">$updateStatus</p>

                <div id="collectionReviewWrapper">
                    <table class="adminStatisticsTable">
                        <tbody>
                            <tr>
                                <td>Name:</td>
                                <td class="collectionDetailsField userData">$displaySafeCollectionName</td>
                            </tr>
                            <tr>
                                <td>Description:</td>
                                <td class="collectionDetailsField userData">$displaySafeCollectionDescription</td>
                            </tr>
                            <tr>
                                <td>Number Of Images:</td>
                                <td class="userData">$collectionImageCount</td>
                            </tr>
                            <tr>
                                <td>Date Range:</td>
                                <td class="collectionDetailsField userData">$collectionDateRange</td>
                            </tr>
                            <tr>
                                <td>Geographical Range:</td>
                                <td class="collectionDetailsField userData">$collectionGeoRange</td>
                            </tr>
                            <tr>
                                <td>Geographical Range By Date:</td>
                                <td class="collectionDetailsField userData">$collectionGeoRangeByDate</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="collectionDetailsWrapper">
                <button type="button" id="editCollection" class="clickableButton enlargedClickableButton collectionButton">
                    Edit Collection Text
                </button>
                </div>



                <div id="mapReviewWrapper">
                    <h3>Collection Coverage Map</h3>
                    <p>Here you can see the area that your new collection will cover. </p>
                    <p>If your collection contains holes you may need to ammend you csv files and re-upload the collection.</p>

                    <div id="reviewMapWrapper">
                        <div id="reviewMap"></div>
                    </div>
                    <div>
                        <button type="button" id="toggleCollectionLine" class="clickableButton mapButton">
                            Hide Collection Line
                        </button>
                        <button type="button" id="toggleMarkers" class="clickableButton mapButton">
                            Hide Collection Images
                        </button>
                    </div>
                    <div>
                        <button type="button" id="resequenceCollection" class="clickableButton enlargedClickableButton">
                            Refine And Resequence Collection Images
                        </button>
                    </div>
                </div>
                <hr>
                <div>
                    <button type="button" id="acceptButton" class="clickableButton enlargedClickableButton acceptDenyButton"
                            title="Clicking this button will make final changes to the database to commit this collection to iCoast.">
                        Accept Collection
                    </button>
                    <form method="post" autocomplete="off" action="collectionCreator.php">
                        <input type="hidden" name="collectionId" value="$collectionId">
                        <input type="hidden" name="delete" value="1">
                        <input type="hidden" name="review" value="1">
                        <button type="submit" id="deleteCollection" class="clickableButton enlargedClickableButton collectionButton">
                            Delete Collection
                        </button>
                    </form>
                </div>
            </div>
        </div>
HTML;

require('includes/template.php');
