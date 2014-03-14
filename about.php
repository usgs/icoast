<?php

ob_start();
require("includes/pageCode/aboutCode.php");

$pageBody = <<<EOL
        <div id="contentWrapper">
            <div id="aboutWrapper">

                <h1>About “iCoast - Did the Coast Change?”</h1>
                <h2> Purpose and Goal of iCoast</h2>


                <p>For the past 18 years, the United States Geological Survey (USGS) coastal scientists
                    have taken tens of thousands of aerial photographs of the U.S. coast before and after 23
                    extreme storms like hurricanes. However, USGS lacks the information processing capacity
                    and personnel to manually analyze the thousands of images they collect after each storm.
                    Computers cannot yet automatically identify coastal changes adequately. Human perception
                    is still needed to detect these coastal changes.</p>


                <p>“iCoast - Did the Coast Change?” is a USGS research project that integrates crowdsourcing
                    and citizen science techniques to create a web application that asks volunteers to
                    compare USGS oblique aerial photographs before and after extreme storms and then identify
                    coastal changes using predefined tags that contain geomorphologic keywords. The purpose
                    of iCoast is to use crowdsourcing to improve USGS predictive models of coastal change
                    and to communicate to the public about coastal vulnerability after extreme storms.</p>


                <p>There are scientific, technological, and societal benefits to the iCoast project. It
                    serves the cause of open government and open data, by publicly sharing USGS aerial
                    imagery but through engagement. It also enhances predictive modelling within hazard
                    science by providing more accurate predictions of coastal erosion that can benefit
                    emergency managers and coastal planners. Lastly, iCoast is intended to educate the
                    public and particularly coastal residents about their vulnerabilities to the coast after
                    extreme storms. iCoast can also be used by marine science educators to further
                    STEM education.</p>


                <h2>USGS Oblique Aerial Photographs</h2>


                <p>The USGS acquires high-resolution oblique aerial photography before and after a
                    hurricane's landfall to help understand the impacts of extreme storms on coastal
                    environments. These aerial photographs are taken at a low angle or altitude to capture a
                    small area of the coast where the horizon might not be visible. Aerial imagery helps USGS
                    scientists qualitatively classify the geomorphic and human development changes to the
                    coast from extreme storms that may not be easily recognizable in the quantitative
                    topographic data. The photography can be uploaded quickly to the web to help provide
                    damage assessments in the immediate aftermath of a storm. These digital photographs are
                    also geocoded to easily plot them on a map. However, the location of oblique aerial
                    photographs do not necessarily indicate the location of what was visually captured in the
                    photograph. Therefore, the location of the coastal aerial photographs shown in the
                    iCoast Photo Headings provide the nearest city or populated place, since these photos
                    were taken above the water along the coast.</p>


                <h2>USGS Predictive Models of Storm-Induced Coastal Changes</h2>


                <p>Sandy beaches provide a natural barrier between the ocean and inland communities,
                    ecosystems, and resources. However, these dynamic environments move and change in
                    response to winds, waves, and currents. During a hurricane, these changes can be large
                    and sometimes catastrophic. High waves and storm surge act together to erode beaches and
                    inundate low-lying lands, putting inland communities at risk. A decade of USGS research
                    on storm-induced coastal change hazards has provided the data and modeling capabilities
                    to identify areas of the US coastline that are likely to experience extreme and
                    potentially hazardous erosion during hurricanes or other extreme storms.</p>


                <p>The USGS predictive models of coastal change processes is based on estimating the
                    likelihood that the beach system will experience erosion and accumulation of sediment
                    resulting in beach erosion, dune erosion, overwash, and inundation
                    (see Storm-Impact Scale). This storm-impact scaling model uses observations of beach
                    morphology combined with sophisticated hydrodynamic models to predict how the coast will
                    respond to the direct landfall of Category 1-5 hurricanes. Hurricane-induced water
                    levels, due to both storm surge and waves, are compared to beach and dune elevations to
                    determine the probabilities of three types of coastal change (1) Dune Erosion – dune toe
                    is eroded by waves and surge, (2) Overwash - sand is transported landward over the beach
                    and dune by waves and surge, and (3) Inundation - beach and dune are completely and
                    continuously submerged by surge and wave setup.</p>


                <h2>The iCoast Team</h2>
                <div class="teamColumn">
                    <div>
                        <img src="images/system/sbliu.jpg" height="100" width="100" alt="Image of Sophia Liu" />
                        <h3>Sophia B. Liu</h3>
                        <p><span class="teamPosition">Principal Investigator</span><br>
                            USGS Research Geographer and<br>Mendenhall Postdoc Fellow</p>
                    </div>

                    <div>
                        <img src="images/system/bpoore.jpg" height="100" width="100" alt="Image of Barbara Poore" />
                        <h3>Barbara Poore</h3>
                        <p><span class="teamPosition">Principal Investigator</span><br>
                            USGS Research Geographer</p>
                    </div>

                    <div>
                        <img src="images/system/rsnell.jpg" height="100" width="100" alt="Image of Richard Snell" />
                        <h3>Richard Snell</h3>
                        <p><span class="teamPosition">Application Developer</span><br>
                            USGS Web Application Developer</p>
                    </div>

                    <div>
                        <img src="images/system/agoodman.jpg" height="100" width="100" alt="Image of Aubrey Goodman" />
                        <h3>Aubrey Goodman</h3>
                        <p><span class="teamPosition">Prototype Developer</span><br>
                            <a href="http://migrantstudios.com/">Migrant Studios Information Architect</a></p>
                    </div>
                </div>
                <div class="teamColumn">

                    <div>
                        <img src="images/system/nplant.jpg" height="100" width="100" alt="Image of Nathaniel Plant" />
                        <h3>Nathaniel Plant</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Research Oceanographer</p>
                    </div>

                    <div>
                        <img src="images/system/hstockdon.jpg" height="100" width="100" alt="Image of Hilary Stockdon" />
                        <h3>Hilary Stockdon</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Research Oceanographer</p>
                    </div>

                    <div>
                        <img src="images/system/kmorgan.jpg" height="100" width="100" alt="Image of Karen Morgan" />
                        <h3>Karen Morgan</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Geologist and Aerial Photographer</p>
                    </div>

                    <div>
                        <img src="images/system/dkrohn.jpg" height="100" width="100" alt="Image of Dennis Krohn" />
                        <h3>Dennis Krohn</h3>
                        <p><span class="teamPosition">Coastal Scientist</span><br>
                            USGS Geologist and Aerial Videographer</p>
                    </div>
                </div>
            </div>
        </div>
EOL;

require("includes/template.php");
