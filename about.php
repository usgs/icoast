<?php

ob_start();
require("includes/pageCode/aboutCode.php");

$pageBody = <<<EOL
        <div id="contentWrapper">
            <div id="aboutWrapper">

                <h1>About “iCoast - Did the Coast Change?”</h1>
                <h2>Purpose of iCoast</h2>

                 <img src="images/system/indexImages/classify.jpg"
                     alt="A screenshot of the iCoast application\'s classification page where users can
                     compare images and log what they see." width="350" height="227" style="border: 1px solid #666666" />

                <p class="aboutShortParagraph">Since 1995, the U.S. Geological Survey (USGS) has collected over 140,000 aerial photographs
                    of the Atlantic and Gulf coasts before and after 24 extreme storms in order to assess
                    coastal damages. The USGS has not been able to use these images to the fullest extent
                    due to a lack of the information processing capacity and personnel needed to analyze the
                    thousands of images they collect after each storm. Computers cannot yet automatically
                    identify coastal changes adequately. Human perception is still needed. “iCoast - Did the
                    Coast Change?” is a USGS research project to construct and deploy a citizen science web
                    application that asks volunteers to compare pre- and post-storm aerial photographs and
                    identify coastal changes using predefined tags. This crowdsourced data will help USGS
                    improve predictive models of coastal change and educate the public about coastal
                    vulnerability to extreme storms.</p>

                <h2>USGS Oblique Aerial Photographs</h2>

                <img src="images/system/indexImages/karen.jpg"
                     alt="An image showing a USGS field worker photographing the coastline from the cabin of
                     a light aircraft." width="350" height="227" />

                <p class="aboutShortParagraph">The USGS acquires high-resolution oblique aerial photography after extreme storms and
                    compares them to baseline imagery collected before the storms. These aerial photographs
                    are taken at a low altitude to capture a small area of the coast. Aerial imagery helps
                    USGS scientists qualitatively classify the geomorphic change and infrastructure damage
                    from extreme storms that may not be easily recognizable in the quantitative topographic
                    data. The photography can be uploaded quickly to the web to help provide damage
                    assessments in the immediate aftermath of a storm. These digital photographs are also
                    geocoded with the location of the aircraft to easily plot them on a map. Learn more at the
                    USGS <a href="http://coastal.er.usgs.gov/hurricanes/oblique.php">Aerial Photography</a> web page.</p>

                <h2>USGS Predictive Models of Storm-Induced Coastal Changes</h2>

                <img src="images/system/indexImages/predictions.jpg"
                     alt="An image showing a graphical representation of the USGS coastal erosion prediction
                     models for the north east United States coastline." width="350" height="227" />

                <p>Sandy beaches provide a natural barrier between the ocean and inland communities,
                    ecosystems, and resources. These dynamic environments move and change in response to
                    winds, waves, and currents. During a hurricane, these changes can be large and sometimes
                    catastrophic. High waves and storm surge act together to erode beaches and inundate
                    low-lying lands, putting inland communities at risk. Research on storm-induced coastal
                    change hazards has provided the data and modeling capabilities to allow the USGS to
                    identify areas of the US coastline that are likely to experience extreme and potentially
                    hazardous erosion during hurricanes or other extreme storms.</p>

                <p>To assess coastal vulnerability to extreme storms, the USGS has developed a
                    <a href="http://coastal.er.usgs.gov/hurricanes/impact-scale/">
                    Storm-Impact Scale</a> to produce <a href="http://coastal.er.usgs.gov/hurricanes/erosionhazards/">
                    Coastal Change Probability</a> estimates. Hurricane-induced water levels, due
                    to both storm surge and waves, are compared to beach and dune elevations to determine the
                    probabilities of three types of coastal change processes: (1) Dune Erosion, where the base
                    or toe of the dune is eroded by waves and surge, (2) Overwash, where sand is transported
                    and deposited landward over the beach and dune by waves and surge, and (3) Inundation,
                    where the beach and dune are completely and continuously submerged by surge and wave
                    runup.</p>



                <h2>Benefits and Broader Impact of iCoast</h2>

                <img src="images/system/aboutImages/coastalDamage.jpg"
                     alt="An taken from ground level image looking along a beach showing considerable damage
                         to buildings following an extreme weather event." width="350" height="227" />

                <p class="aboutShortParagraph">There are scientific, technological, and societal benefits to the iCoast project. The
                    crowdsourced data produced from users like you in iCoast will enhance predictive modeling
                    of coastal erosion to better inform emergency managers, coastal planners, and coastal
                    residents of coastal vulnerabilities in their region. iCoast also serves the cause of open
                    government and open data, by sharing USGS aerial imagery with the public. Lastly, iCoast
                    educates the public and particularly coastal residents about the vulnerabilities to the
                    coastline resulting from extreme erosion during storms. iCoast can also be used by marine
                    science educators to further science, technology, engineering, and math (STEM) education.
                    </p>

                <h2>The iCoast Team</h2>
                <div class="teamColumn">
                    <div>
                        <img src="images/system/aboutImages/sbliu.jpg" height="100" width="100" alt="Image of Sophia Liu"
                            title="Sophia B. Liu" />
                        <h3>Sophia B. Liu</h3>
                        <p><span class="teamPosition">Principal Investigator</span><br>
                            USGS Research Geographer and Mendenhall Postdoc Fellow</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/bpoore.jpg" height="100" width="100" alt="Image of Barbara Poore"
                            title="Barbara Poore" />
                        <h3>Barbara Poore</h3>
                        <p><span class="teamPosition">Principal Investigator</span><br>
                            USGS Research Geographer</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/rsnell.jpg" height="100" width="100" alt="Image of Richard Snell"
                            title="Richard Snell" />
                        <h3>Richard Snell</h3>
                        <p><span class="teamPosition">Application Developer</span><br>
                            USGS Web Application Developer</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/agoodman.jpg" height="100" width="100" alt="Image of Aubrey Goodman"
                            title="Aubrey Goodman" />
                        <h3>Aubrey Goodman</h3>
                        <p><span class="teamPosition">Prototype Developer</span><br>
                            <a href="http://migrantstudios.com/">Migrant Studios Information Architect</a></p>
                    </div>
                </div>
                <div class="teamColumn">

                    <div>
                        <img src="images/system/aboutImages/nplant.jpg" height="100" width="100" alt="Image of Nathaniel Plant"
                            title="Nathaniel Plant" />
                        <h3>Nathaniel Plant</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Research Oceanographer</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/hstockdon.jpg" height="100" width="100" alt="Image of Hilary Stockdon"
                            title="Hilary Stockdon"/>
                        <h3>Hilary Stockdon</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Research Oceanographer</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/kmorgan.jpg" height="100" width="100" alt="Image of Karen Morgan"
                            title="Karen Morgan" />
                        <h3>Karen Morgan</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Geologist and Aerial Photographer</p>
                    </div>

                    <div>
                        <img src="images/system/aboutImages/dkrohn.jpg" height="100" width="100" alt="Image of Dennis Krohn"
                            title="Dennis Krohn" />
                        <h3>Dennis Krohn</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Geologist and Aerial Videographer</p>
                    </div>
                </div>
                <h2>Attributions and Acknowledgements</h2>
                <p id="aboutAttributions">
                    <span class="captionTitle">Geocoding:</span> Location names derived using the <a href="http://www.geonames.org/">GeoNames</a> Gazetteer. License: <a href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0 License</a><br>
                    <span class="captionTitle">ToolTips:</span> <a href="http://code.drewwilson.com/entry/tiptip-jquery-plugin">TipTip</a>. Dual licensed: <a href="http://opensource.org/licenses/mit-license.php">MIT</a> and <a href="http://www.gnu.org/licenses/gpl.html">GPL</a><br>
                    <span class="captionTitle">Zoom Tool:</span> <a href="http://www.elevateweb.co.uk/image-zoom">Elevate Zoom</a>. Dual licensed: <a href="http://opensource.org/licenses/mit-license.php">MIT</a> and <a href="http://www.gnu.org/licenses/gpl.html">GPL</a><br>
                    <span class="captionTitle">Map Marker Clustering:</span> <a href="http://google-maps-utility-library-v3.googlecode.com/svn/trunk/markerclustererplus/docs/reference.html">MarkerClusterPlus</a>. License: <a hre="http://www.apache.org/licenses/LICENSE-2.0">Apache License, V 2.0</a><br>
                    <span class="captionTitle">Form Validation:</span> <a href="http://jqueryvalidation.org">jQuery Validation Plugin</a>. License: <a href="http://opensource.org/licenses/mit-license.php">MIT</a><br>
                    <span class="captionTitle">OpenId Validation:</span> <a href="https://code.google.com/p/lightopenid">lightopenid</a>. License: <a href="http://opensource.org/licenses/mit-license.php">MIT</a><br>
                    <span class="captionTitle">Map Icon:</span> Created by <a href="http://simpleicon.com">Simple Icon</a>. License: <a href="http://simpleicon.com/license-agreement/">Simple Icon</a><br>
                    <span class="captionTitle">Dice Icon:</span> Created by <a href="http://www.visualpharm.com">VisualPharm</a>. License: <a href="http://www.visualpharm.com/free_icons.html">VisualPharm</a><br>
                    <span class="captionTitle">Map Markers:</span> <a href="http://mapicons.nicolasmollet.com">Map Icons Collection</a>. License: <a href="http://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-Share Alike 3.0 Unported</a>
                </p>
            </div>
        </div>
EOL;

require("includes/template.php");
