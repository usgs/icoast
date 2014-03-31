<?php

ob_start();
require("includes/pageCode/helpCode.php");

$pageBody = <<<EOL
        <div id="contentWrapper">
            <div id="helpWrapper">

                <h1>iCoast Help and FAQs</h1>
                <div id="faqs">
                    <h2>FAQs</h2>
                    <p>Click any question to reveal the answer</p>
                    <input type="button" class="clickableButton" id="revealAllFaqs" value="Reveal All FAQs"
                        title="Use this button to show all the FAQ answers with one click" />
                    <input type="button" class="clickableButton disabledClickableButton" id="hideAllFaqs"
                        value="Hide All FAQs" title="Use this button to hide all the FAQ answers with one click"/>
                    <h3>Logging Into iCoast</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why do I have to sign in with a Google account? Can I sign in with other accounts?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>USGS is required to use <a href="http://openid.net/get-an-openid/what-is-openid/">
                                    OpenID</a> and Google-based accounts are currently the only ones supported
                                    by the Federal government. If you or your organization use Google accounts for email, you can
                                    login using that email and password. You can also change the email you want us to use in
                                    iCoast by going to the profile page, but it must be changed to a Google-based account. If you
                                    have problems logging in, please contact <a href="mailto:icoast@usgs.gov">
                                    icoast@usgs.gov</a>.</p>
                            </div>
                        </div>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can I make changes to the information I provided when I first created an iCoast account?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, go to the profile page and you can update your account information there. We may request
                                other optional information in the future here.</p>
                            </div>
                        </div>


                    <h3>iCoast Tools</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can the magnifier tool also zoom in and out?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, you can zoom in and out with the magnifier tool by using the scroll wheel on a mouse or
                                touchpad.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What are the pop-up boxes that appear next to the tags?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>The pop-up boxes provide textual and pictorial information about each tag. The boxes appear
                                when the cursor is inside of the tag button for a few seconds, and will disappear when the
                                cursor is outside of the tag button.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What is the purpose of the Map in the middle of the classification page?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>This interactive map allows you to spatially see the location of the POST-storm photo. You can
                                zoom in and out to better determine whether the coastline is a barrier island. The map also
                                points out geographic features and familiar place names that may not be apparent in the aerial
                                photographs.</p>
                            </div>
                        </div>


                    <h3>Coastal Aerial Photos</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What is the difference between selecting a photo with the Random button
                                    versus the Map button?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>If you select the Random button, iCoast will find a random POST-storm photo that you have not
                                yet annotated. The Map button allows you to select a POST-storm photo that you have not yet
                                annotated through a map interface. You can also use the search box at the top of the map to
                                zoom into a specific location to search for POST-storm photos in that area. To find the photos
                                you have already annotated, go to your Profile page.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can I change and update POST-storm photos that I already annotated?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, you can find all the POST-storm photos you annotated in the Profile page. It provides a
                                Table and Map view of your annotated photos as well as a Tag button to allow you to edit the
                                tags you selected for that photo.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why do some PRE-storm photos show more change and damage than the POST-storm photos?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Some PRE-storm photos are actually POST-storm photos from a previous storm in that area. This
                                may be why the PRE-storm photo shows more damage than the POST-storm photo.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why is the location information above each photograph not always accurate?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>The aerial photographs are geocoded with the location of the aircraft to easily plot them on a
                                map. Each aerial photograph shown in iCoast includes an estimated location of the nearest
                                populated place or city. However, since these images were taken from the water, they may not
                                accurately reflect the location visible in the photograph.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can you put a scale bar on each aerial photograph?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>No, it is difficult to provide an accurate scale bar for every aerial photograph because of the
                                variability in how the oblique aerial photographs were taken.</p>
                            </div>
                        </div>
                </div>
            </div>
        </div>
EOL;

require("includes/template.php");
