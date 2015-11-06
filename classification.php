<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once('includes/pageCode/classificationCode.php');

$pageBody = <<<EOL

        <div id="popupWrapperParent">
            <div id="popupWrapperChild">
                <div class="popupContent" id="unsuitable" style="display: none">
                    <h2>Flag an Unsuitable Photo</h2>
                    <img src="$postDisplayImageURL" height="260" width="400">
                    <p>You have indicated that this photo of <span class="userData">$postImageLocation</span> is unsuitable for use
                     in iCoast.</p>
                    <p>A suitable photo should clearly show a small section of the coast taken
                         perpendicular to the shoreline. Examples of an unsuitable photo include those:</p>
                         <ul>
                            <li>in portrait orientation</li>
                            <li>with a non-coastal subject</li>
                            <li>showing a view along the coast (parallel)</li>
                            <li>too distant from the coast to make out details via the zoom tool</li>
                            <li>that are blurry</li>
                            <li>where the coast is badly obscured by weather or some other physical item</li>
                         </ul>
                    <h3>Unsuitable Photo Examples</h3>
                    <div class="photoPopupThumbnailCenteringWrapper">
                        <div class="photoPopupThumbnailWrapper">
                            <div class="examplePhotoWrapper">
                                <img src="http://coastal.er.usgs.gov/hurricanes/oblique/thumbnails/2012/1104/2012_1104_185318d.jpg" height="88" width="135">
                            </div>
                            <div class="examplePhotoWrapper">
                                <img src="http://coastal.er.usgs.gov/hurricanes/oblique/thumbnails/2012/1106/2012_1106_145031d.jpg" height="88" width="135">
                            </div>
                            <div class="examplePhotoWrapper">
                                <img src="http://coastal.er.usgs.gov/hurricanes/oblique/thumbnails/2012/1105/2012_1105_180033d.jpg" height="8" width="135">
                            </div>
                            <div class="examplePhotoWrapper">
                                <img src="http://coastal.er.usgs.gov/hurricanes/oblique/thumbnails/2012/1105/2012_1105_174025d.jpg" height="88" width="135">
                            </div>
                            <div class="examplePhotoWrapper">
                                <img src="http://coastal.er.usgs.gov/hurricanes/oblique/thumbnails/2012/1105/2012_1105_142652d.jpg" height="88" width="135">
                            </div>

                        </div>
                    </div>
                    <p>If you still feel this image is unsuitable for use in iCoast then please finish flagging it using the
                        button below.</p>
                    <p>iCoast administrators will be notified and once checked the photo will be removed from the system.</p>
                    <p>Thanks for your help to improve the quality of iCoast's data!</p>
                    <input type="button" id="confirmUnsuitable" class="clickableButton" value="Confirm This Is An Unsuitable Image">
                    <input type="button" class="clickableButton cancelPopup" value="Cancel">
                </div>
                <div class="popupContent" id="noMatch"  style="display: none">
                    <h2>Flag Photo Without a Match</h2>
                    <img src="$postDisplayImageURL" height="260" width="400">
                    <p>You have indicated that you have been unable to find a suitable match to this photo
                        taken near <span class="userData">$postImageLocation</span>.</p>
                    <p>A photo is considered to be without a suitable pre-storm match if:</p>
                         <ul>
                            <li>using all available natural and man-made fetures you can find no similarities
                                between the post-storm photo and any available pre-storm thumbnail</li>
                            <li>the best match is in portrait orientation</li>
                            <li>the best match shows a view along the coast (parallel)</li>
                            <li>the best match is too distant from the coast to make out details via the zoom tool</li>
                            <li>the best match is blurry</li>
                            <li>the best match is obscured by weather or some other physical item</li>
                         </ul>
                    <h3>Possible Matches for this Photo</h3>
                    <div class="photoPopupThumbnailCenteringWrapper">
                        <div class="photoPopupThumbnailWrapper">
                            $noMatchThumbnailHTML
                        </div>
                    </div>
                    <p>If you have looked through all available potential matches and feel this image has no suitable pre-storm match then please finish flagging it using the
                        button below.</p>
                    <p>iCoast administrators will be notified and once checked the photo will be removed from the system.</p>
                    <p>Thanks for your help to improve the quality of iCoast's data!</p>
                    <input type="button" id="confirmNoMatch" class="clickableButton" value="Confirm This Photo Has No Match">
                    <input type="button" class="clickableButton cancelPopup" value="Cancel">
                </div>
            </div>
        </div>

    <div id="classificationWrapper">
        <div id="images">
            <div class="imageColumn">
                <div class="imageColumnTitle">
                    $preImageTitle
                </div>
                <div id="preImageColumnContent" class="imageColumnContent">
                    <img id="preImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                         src="images/system/loading.gif"
                         title="Loading high resolution zoom tool..." alt="An animated spinner to indicate a
                             higher resolution image is loading."/>
                    <div id="preImageWrapper" class="imageWrapper">
                        <img id="preImage" src="$preDisplayImageURL" title="" alt="$preImageAltTagHTML"
                             data-zoom-image="$preDetailedImageURL" />
                    </div>
                </div>
            </div>

            <div class="imageColumn">
				<div class="imageColumnTitle" id="mapColumnTitle">
                </div>
                <div id="classificationMapWrapper">
                    <div id="mapInsert"></div>
                </div>
            </div>

            <div class="imageColumn">
						    <div class="imageColumnTitle">
                    $postImageTitle
                </div>
                <div id="postImageColumnContent" class="imageColumnContent">
                    <img id="postImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                         src="images/system/loading.gif"
                         title="Loading high resolution zoom tool..." alt="An animated spinner to indicate a
                             higher resolution image is loading." />
                            <div id="postImageWrapper" class="imageWrapper">
                                <img id="postImage" src="$postDisplayImageURL" title="" alt="$postImageAltTagHTML"
                                     data-zoom-image="$postDetailedImageURL" />
                            </div>
                </div>
            </div>
        </div>

        <div id="annotationWrapper">
            <div id="photoMatchingWrapper">
                <h1>FIND THE BEST MATCHING PRE-STORM PHOTO</h1>
                <p>Before any image comparison can be made you first need to ensure you have a pre-storm photo that accurately matches the post-storm photo.</p>
                <p>Our proximity based computer match is shown above-left. If this match is incorrect then select any of the thumbnails,<br>arrow buttons or map pins to browse other adjacent pre-storm photos.</p>
                <p>The coloring of the thumbnail and image borders match that of the map pins to help you visualize where the images are in relation to each other.</p>
                <div id="preNavigationCenteringWrapper">
                    <div id="preNavigationWrapper">
                        <button id="leftButton" type="button" class="clickableButton leftCoastalNavButton">
                            <img width="34" height="52" alt="Image of a left facing arrow. Used to navigate left along the coast" src="images/system/leftArrowCenter.png">
                        </button>
                        $thumbnailHtml
                        <button id="rightButton" type="button" class="clickableButton">
                            <img width="34" height="52" alt="Image of a right facing arrow. Used to navigate right along the coast" src="images/system/rightArrowCenter.png">
                        </button>
                    </div>
                </div>
                <div class="annotationControls">
                    <input id="flagButton" class="clickableButton" type="button" value="FLAG AS UNSUITABLE" title="">
                    <input id="noMatchButton" class="clickableButton" type="button" value="NO MATCH FOUND" title="">
                    <input id="startTaggingButton" class="clickableButton" type="button" value="START TAGGING" title="">
                </div>
            </div>
            <div id="taskWrapper">
                $taskHtmlString
                <div id="progressTrackerCenteringWrapper">
                    <div id="progressTrackerItemWrapper" title="The TASK TRACKER lets you know which TASK you are
                     currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS
                     Task buttons at the bottom corners of the page.">
                        $progressTrackerItems
                    </div>
                </div>
            </div>
        </div>
    </div>
EOL;

require_once('includes/template.php');




