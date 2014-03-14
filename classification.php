<?php
ob_start();
require_once('includes/pageCode/classificationCode.php');

$pageBody = <<<EOL
    <div id="classificationWrapper">
        <div id="images">
            <div class="imageColumn">
                <div class="imageColumnTitle">
                    $preImageTitle
                </div>
                <div class="imageColumnContent">
                    <img id="preImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                         src="images/system/loading.gif"
                         title="Loading high resolution zoom tool..." />
                    <img id="preImage" src="$preDisplayImageURL"
                         data-zoom-image="$preDetailedImageURL" />
                </div>
            </div>

            <div class="imageColumn">
						    <div class="imageColumnTitle" id="mapColumnTitle">
                </div>
                <div class="imageColumnContent">
                    <div id="mapInsert"></div>
                </div>
            </div>

            <div class="imageColumn">
						    <div class="imageColumnTitle">
                    $postImageTitle
                </div>
                <div class="imageColumnContent">
                    <img id="postImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                         src="images/system/loading.gif"
                         title="Loading high resolution zoom tool..." />
                    <img id="postImage" src="$postDisplayImageURL"
                         data-zoom-image="$postDetailedImageURL" />
                </div>
            </div>
        </div>

        <div id="annotationWrapper">
            <div id="preNavigationCenteringWrapper">
                <div id="preNavigationWrapper">

                    $previousThumbnailHtml
                    $currentThumbnailHtml
                    $nextThumbnailHtml
                </div>
            </div>
            $taskHtmlString
            <div id="progressTrackerCenteringWrapper" title="The TASK TRACKER lets you know which TASK you are
                 currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS
                 Task buttons at the bottom corners of the page.">
                <div id="progressTrackerItemWrapper">
                    $progressTrackerItems
                </div>
            </div>
        </div>
    </div>
EOL;

require('includes/template.php');
