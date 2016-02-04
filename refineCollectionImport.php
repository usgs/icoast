<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/refineCollectionImportCode.php');
$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1> iCoast "{$collectionMetadata['name']}" Collection Creator</h1>
                <h2>Collection Sequencing Preparation</h2>
                <p><span class="userData">$formattedPhotoCountResults</span> $photoCountText in this collection.</p>
                <p>This tool is a preliminary means of refining the images in this new collection. Use both the
                    Thumbnail and Map views to remove images that are obviously inappropriate for use in iCoast.
                    Such images may include images over land, images of objects other than the coast, poor quality/out of focus
                    images, those that too close/far away or of the wrong angle of the coast, or duplicates.</p>
                <p>You do not need to be thorough about duplicate and stray images at this stage. The next and final step
                    of the import for this collection will try to connect a sequence of images together along the coast to form a
                    simulated flight path. This process will attempt to remove any unnecessary images for you and can be tweaked
                    if necessary, however, it cannot determine image quality or the validity of content so bad images in this respect
                    should are best removed at this point.</p>
                <p>When you are happy with your selections click the <span class="italic">Sequence Collection</span> button at the bottom
                    of this page.</p>
                <form method="get" autocomplete="off" id="displayType" action="#displayType">
                    <input type="submit" class="clickableButton" name="displayType" value="Show Thumbnails">
                    <input type="submit" class="clickableButton" name="displayType" value="Show Map"></p>
                    <input type="hidden" name="collectionId" value="$collectionId" />
                </form>
                $contentHTML
                <form method="get" autocomplete="off" action="refineCollectionImport.php">
                    <input type="hidden" name="collectionId" value="$collectionId" />
                    <button type="submit" class="clickableButton enlargedClickableButton" name="sequenceCollection" value="1">
                        Sequence Collection
                    </button>
                </form>
            </div>
        </div>
EOL;

require('includes/template.php');
