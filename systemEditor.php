<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/systemEditorCode.php');
$pageBody = <<<EOL
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <div id="adminBanner">
                <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            </div>
            <h1>iCoast System Editor</h1>
            <h2>Change the Home Page Focused Project</h2>
            $changeFocusResultHTML
            $changeFocusHTML
            <h2>Edit "What's New in iCoast"</h2>
            $whatsNewResultHTML
            <p>The "What's New in iCoast" section can be found on the iCoast Home page in both the 
                logged in and logged out states. It provides a quick way to share updates and news related
                to iCoast.</p>
            <p>The section is formatted as single paragraph with a lead-in title (summary) shown in bold.
                You may not apply any HTML formatting to the text beyond the use of line breaks, bold, and italic which can
                be included using the standard HTML break tags (&lt;br&gt;, &lt;b&gt; & &lt;/b&gt;, &lt;i&gt; & &lt;/i&gt;). Using two break tags succession can simulate
                a new paragraph. Always close any formatting tags you open.</p>
            <p>The Title is not required but if specified is limited to 128 characters.<br>
                The content is limited to 1000 characters (including break tags).<br>
                Both fields may be left empty. If this is done the "What's New" section is hidden on the Home page.</p>
            <form method="post" autocomplete="off" action="systemEditor.php">
                <div class="formFieldRow whatsNewEditor">
                    <label for="whatsNewTitle" title="This text appears in bold at the 
                        start of the 'What's New' text. Use it as a summary and lead-in to
                        the main text. <br><br>128 character limit. This field is optional.">
                        Title (Optional):
                    </label>
                    <input type="text" class="formInputStyle" id="whatsNewTitle" name="whatsNewTitle" maxlength="128" value="$safeWhatsNewTitle">
                </div>
                <div class="formFieldRow whatsNewEditor">
                    <label for="whatsNewContent" title="This text is used to tell users of new features
                        or projects in iCoast.<br><br>You may use HTML break tags to force a new line in 
                        the text (2 to simulate a new paragraph).<br><br>1000 character limit. This is a 
                        required field.">
                        Content *:
                    </label>
                    <textarea class="formInputStyle" id="whatsNewContent" name="whatsNewContent" rows="5" cols="60" maxlength="1000">$safeWhatsNewContent</textarea>
                </div>
                <div>
                    <button type="button" class="clickableButton enlargedClickableButton" id="clearWhatsNewFields"
                        title="This will clear the text from the fields above. You must the click the Update button to commit the empty fields to the database.">
                        Clear All Text
                    </button>
                    <button type="submit" class="clickableButton enlargedClickableButton" name="updateWhatsNew" value="1" 
                        title="This will commit your text changes to the database and alter the iCoast home pages">
                     Update What's New
                    </button>
                </div>
            </form>
            
        </div>
    </div>
EOL;

require('includes/template.php');
