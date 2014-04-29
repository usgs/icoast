<?php

ob_start();
require("includes/pageCode/indexCode.php");

$pageBody = <<<EOL
    <div id="contentWrapper">
        <div id="indexImageColumn">
            <div id="indexImageWrapper">
                <img src="images/system/indexImages/seasideHeights.jpg"
                    alt="An image of the pier at Seaside Heights, New Jersey following Hurricane Sandy.
                        The end has been washed away by the storm." height="435" width="670" title="" />
                <img src="" alt="" height="435" width="670" title="" />
            </div>
            <div id="imageCaptionWrapper">
                <p><span class="captionTitle" id="captionTitle"></span> <span id="captionText"></span></p>
            </div>
        </div>
        <div id="indexTextColumn">
        <h1>Welcome to USGS iCoast!</h1>
        <p>Help scientists at the U.S. Geological Survey (<a href="http://www.usgs.gov/">USGS</a>) annotate aerial photographs with keyword tags to
            identify changes to the coast after extreme storms like Hurricane Sandy. We need your eyes to
            help us understand how our coastlines are changing from extreme storms.</p>
        $variableContent
        </div>
    </div>
EOL;

require("includes/template.php");
