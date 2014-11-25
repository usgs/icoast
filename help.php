<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once("includes/pageCode/helpCode.php");

$pageBody = <<<EOL
        <div id="contentWrapper">
            <div id="helpWrapper">

                <h1>USGS iCoast Help and FAQs</h1>
                <div id="faqs">
                    <h2>FAQs</h2>
                    <p>Click any question to reveal the answer</p>
                    <input type="button" class="clickableButton" id="revealAllFaqs" value="Reveal All FAQs"
                        title="Use this button to show all the FAQ answers with one click" />
                    <input type="button" class="clickableButton disabledClickableButton" id="hideAllFaqs"
                        value="Hide All FAQs" title="Use this button to hide all the FAQ answers with one click"/>
                    <h3 id="loginFAQ">Logging Into USGS iCoast</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why do I have to sign in with a Google account? Can I sign in with other accounts?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>USGS has chosen to use <a href="http://openid.net/get-an-openid/what-is-openid/">
                                    OpenID</a> authentication and Google based credentials to lighten the
                                    burden on the public of creating multiple user names and passwords. Using
                                    externally issued credentials in this manner also increases user security
                                    by reducing the number of places in which sensitive information such as
                                    IDs and Passwords is stored.</p>
                            </div>
                        </div>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>I don't have a Google Account. How do I get one? Can I register with Google
                                    using an existing non-Google email address?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>By clicking the <span class="italic">Login or Register with Google</span>
                                    button on the iCoast <span class="italic">Login</span>
                                    page you will be redirected to a Google server which handles the login
                                    process. Below the Email and Password boxes is a link to create a new
                                    account with Google. You can use any email address to create a Google
                                    account by selecting the option to use your own address when asked to choose
                                    your username. If you have problems logging into iCoast, please contact the
                                    iCoast administrators at <a href="mailto:icoast@usgs.gov">
                                    icoast@usgs.gov</a>.</p>
                            </div>
                        </div>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What information is shared between Google and the USGS iCoast system?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>After iCoast has successfully authenticated the user via a Google server,
                                    the iCoast system only stores the user's Google username, which is the
                                    email used to login to Google Accounts. USGS does not share any information
                                    about you or your actions within the iCoast system with Google. Google is
                                    primarily used as a secure authentication medium. If you have any security
                                    questions or other concerns, please contact <a href="mailto:icoast@usgs.gov">
                                    icoast@usgs.gov</a>.</p>
                            </div>
                        </div>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can I make changes to the information I provided when I first created an iCoast account?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, go to the <span class="italic">Profile</span> page and you can update
                                    your account information there. We may request other optional information
                                    in the future here.</p>
                            </div>
                        </div>


                    <h3>Using the USGS iCoast System</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What is the best screen resolution for using iCoast?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>iCoast is designed to dynamically adapt to the size of your screen but it
                                    requires a minimum resolution of 1280 pixels wide and 786 pixels high.
                                    Screens or windows smaller than this resolution may require the user to
                                    excessively scroll and may find it difficult to use iCoast.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can the magnifier tool zoom in and out?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, you can zoom in and out with the magnifier tool by using the scroll
                                    wheel on a mouse or touchpad. The magnifier tool is only visible when your
                                    cursor is over the aerial photographs.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What are the pop-up boxes that appear next to the tags?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>The pop-up boxes provide textual and pictorial information about each tag
                                    containing pre-defined keywords on the buttons. The boxes appear when the
                                    cursor is inside of the tag button for a few seconds and will disappear
                                    when the cursor is outside of the tag button.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What is the purpose of the map in the middle of the classification page?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>This interactive map allows you to see the location of the post-storm
                                    photo. The geographic features and place names may help you to orient
                                    yourself along the coast. You can also zoom in and out to better
                                    determine whether the coastline is a barrier island.</p>
                            </div>
                        </div>


                    <h3>Coastal Aerial Photos</h3>
                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>What is the difference between selecting a photo with the
                                    <span class="italic">Random</span> button versus the <span class="italic">
                                    Map</span> button?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>If you select the <span class="italic"></span> button, iCoast will find a
                                    random post-storm photo that you have not yet annotated. The
                                    <span class="italic">Map</span> button allows you to select a post-storm
                                    photo through a map interface. You can also use the search box at the top
                                    of the map to zoom into a specific location to search for post-storm
                                    photos in that area. To find the images you have already annotated, go to
                                    your <span class="italic">My iCoast</span> page.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can I change and update post-storm photos that I already annotated?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Yes, you can find all the post-storm photos you annotated in the
                                    <span class="italic">My iCoast</span> page. It provides a table and map view
                                    of your annotated photos as well as a <span class="italic">Tag</span>
                                    button to allow you to edit the tags you selected.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why do some pre-storm photos show more change and damage than the post-storm photos?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>Some pre-storm photos were taken after a previous storm in that area.
                                    This may be why the pre-storm photo shows more damage than the post-storm
                                    one.</p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Why is the location information above each photograph not always accurate?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>The aerial photographs are geocoded based on the location of the aircraft.
                                    Because these images were taken from the water, the geographic tag may
                                    not accurately reflect the location visible in the photograph. iCoast
                                    shows an estimated location of the nearest populated place or city. </p>
                            </div>
                        </div>

                        <div class="faq">
                            <div class="faqQuestion">
                                <p>+</p>
                                <p>Can you put a scale bar on each aerial photograph?</p>
                            </div>
                            <div class="faqAnswer">
                                <p>No, it is difficult to provide an accurate scale bar for every
                                    photograph because of the variability in altitude, distance offshore,
                                    and field of view of each flight.</p>
                            </div>
                        </div>
                </div>
            </div>
        </div>
EOL;

require_once("includes/template.php");
