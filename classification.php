<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

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
                         title="Loading high resolution zoom tool..." alt="An animated spinner to indicate a
                             higher resolution image is loading."/>
                    <img id="preImage" src="$preDisplayImageURL" title="" alt="$preImageAltTagHTML"
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
                         title="Loading high resolution zoom tool..." alt="An animated spinner to indicate a
                             higher resolution image is loading." />
                    <img id="postImage" src="$postDisplayImageURL" title="" alt="$postImageAltTagHTML"
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
            <div id="progressTrackerCenteringWrapper">
                <div id="progressTrackerItemWrapper" title="The TASK TRACKER lets you know which TASK you are
                 currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS
                 Task buttons at the bottom corners of the page.">
                    $progressTrackerItems
                </div>
            </div>
        </div>
    </div>
EOL;

require_once('includes/template.php');
