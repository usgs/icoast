<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once("includes/pageCode/aboutCode.php");

$pageBody = <<<EOL
        <div id="contentWrapper">
            <div id="aboutWrapper">

                <h1>About “USGS iCoast - Did the Coast Change?”</h1>
                <h2>Purpose of USGS iCoast</h2>

                 <img src="images/system/indexSlideshowImages/classify.jpg"
                     alt="A screenshot of the iCoast application\'s classification page where users can
                     compare images and log what they see." width="350" height="227" style="border: 1px solid #666666" />

                <p class="aboutShortParagraph">Since 1995, the U.S. Geological Survey (USGS) has collected over 140,000 aerial photographs
                    of the Atlantic and Gulf coasts before and after 24 extreme storms in order to assess
                    coastal damages. The USGS has not been able to use these images to the fullest extent
                    due to a lack of the information processing capacity and personnel needed to analyze the
                    thousands of images they collect after each storm. Computers cannot yet automatically
                    identify coastal changes adequately. Human perception is still needed. “USGS iCoast - Did the
                    Coast Change?” is a USGS research project to construct and deploy a citizen science web
                    application that asks volunteers to compare pre- and post-storm aerial photographs and
                    identify coastal changes using predefined tags. This crowdsourced data will help USGS
                    improve predictive models of coastal change and educate the public about coastal
                    vulnerability to extreme storms.</p>

                <h2>USGS Oblique Aerial Photographs</h2>

                <img src="images/system/indexSlideshowImages/karen.jpg"
                     alt="An image showing a USGS field worker photographing the coastline from the cabin of
                     a light aircraft." width="350" height="227" />

                <p class="aboutShortParagraph">The USGS acquires high-resolution oblique aerial photography after extreme storms and
                    compares them to baseline imagery collected before the storms. These aerial photographs
                    are taken at a low altitude to capture a small area of the coast. Aerial imagery helps
                    USGS scientists qualitatively classify the geomorphic change and infrastructure damage
                    from extreme storms that may not be easily recognizable in the quantitative topographic
                    data. The photography can be uploaded quickly to the web to help provide damage
                    assessments in the immediate aftermath of a storm. These digital photographs are also
                    geocoded with the location of the aircraft to easily plot them on a map. Explore other oblique
                    aerial photography collections at the USGS <a href="https://coastal.er.usgs.gov/hurricanes/data/photos.php">
                    Aerial Photography</a> web page. Learn more about other storms the USGS has studied at the
                    <a href="https://coastal.er.usgs.gov/hurricanes/assessments/storms.php">Storm Response</a> page.</p>

                <h2>USGS Predictive Models of Storm-Induced Coastal Changes</h2>

                <img src="images/system/indexSlideshowImages/predictions.jpg"
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
                    <a href="https://coastal.er.usgs.gov/hurricanes/impact-scale/">
                    Storm-Impact Scale</a> to produce <a href="http://marine.usgs.gov/coastalchangehazardsportal/">
                    Coastal Change Probability</a> estimates. Hurricane-induced water levels, due
                    to both storm surge and waves, are compared to beach and dune elevations to determine the
                    probabilities of three types of coastal change processes: (1) Dune Erosion, where the base
                    or toe of the dune is eroded by waves and surge, (2) Overwash, where sand is transported
                    and deposited landward over the beach and dune by waves and surge, and (3) Inundation,
                    where the beach and dune are completely and continuously submerged by surge and wave
                    runup.</p>



                <h2>Benefits and Broader Impact of USGS iCoast</h2>

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

                <h2>iCoast In The News</h2>

                <p class="aboutAttributions">iCoast was featured on the USGS home page as a top story. <a href="http://www.usgs.gov/blogs/features/usgs_top_story/usgs-icoast-did-the-coast-change/">USGS Science Features: Top Story</a>

                <h2>The USGS iCoast Team</h2>
                <div class="teamColumn">
                    <div>
                        <img src="images/system/aboutImages/kmorgan.jpg" height="100" width="100" alt="Image of Karen Morgan"
                            title="Karen Morgan" />
                        <h3>Karen Morgan</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Geologist and Aerial Photographer</p>
                    </div>
        
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

                </div>
                <div class="teamColumn">
        
                    <div>
                        <img src="images/system/aboutImages/rsnell.jpg" height="100" width="100" alt="Image of Richard Snell"
                            title="Richard Snell" />
                        <h3>Richard Snell</h3>
                        <p><span class="teamPosition">Application Developer</span><br>
                            USGS Web Application Developer</p>
                    </div>

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

                </div>
                <p class="aboutAttributions">The USGS iCoast project is funded by the Coastal and Marine
                    Geology Program (<a href="http://marine.usgs.gov/">CMGP</a>) and the National Geospatial
                    Program (NGP) through the Center of Excellence for Geospatial Information Science
                    (<a href="http://cegis.usgs.gov/">CEGIS</a>).</p>

                <h2>Open Source and iCoast</h2>
                <p class="aboutAttributions"><a href="https://github.com/usgs/icoast">Fork iCoast on GitHub</a>
                <p class="aboutAttributions">Unless otherwise noted below, this software is in the public domain because it contains materials that originally came from the United States Geological Survey, an agency of the United States Department of Interior. For more information, see the official <a href="http://www.usgs.gov/visual-id/credit_usgs.html#copyright">USGS copyright policy</a></p>
                <h2>Attributions</h2>
                <p class="aboutAttributions">References to or use of non-U.S. Department of the Interior (DOI)
                    products does not constitute an endorsement by the DOI.</p>
                <p class="aboutAttributions">
                    <span class="captionTitle">Geocoding:</span> Location names derived using the <a href="http://www.geonames.org/">GeoNames</a> Gazetteer. <a href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0 License</a><br>
                    <span class="captionTitle">ToolTips:</span> <a href="https://github.com/drewwilson/TipTip">TipTip</a>. <a href="http://opensource.org/licenses/mit-license.php">MIT</a> and <a href="http://www.gnu.org/licenses/gpl.html">GPL</a> Licenses<br>
                    <span class="captionTitle">Zoom Tool:</span> <a href="http://www.elevateweb.co.uk/image-zoom">Elevate Zoom</a>. Dual licensed: <a href="http://opensource.org/licenses/mit-license.php">MIT</a> and <a href="http://www.gnu.org/licenses/gpl.html">GPL</a> Licenses<br>
                    <span class="captionTitle">Map Marker Clustering:</span> <a href="https://github.com/Leaflet/Leaflet.markercluster">Leaflet.markercluster</a>. <a href="http://opensource.org/licenses/mit-license.php">MIT License</a><br>
                    <span class="captionTitle">Map Search:</span> <a href="https://github.com/smeijer/L.GeoSearch">Leaflet.GeoSearch</a>. <a href="https://github.com/smeijer/L.GeoSearch/blob/master/LICENSE">License</a><br>
                    <span class="captionTitle">Form Validation:</span> <a href="http://jqueryvalidation.org">jQuery Validation Plugin</a>. <a href="http://opensource.org/licenses/mit-license.php">MIT License</a><br>
                    <span class="captionTitle">Map Markers:</span> <a href="http://mapicons.nicolasmollet.com">Map Icons Collection</a>. <a href="http://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-Share Alike 3.0 Unported License</a>
                </p>
            </div>
        </div>
EOL;

require_once("includes/template.php");
