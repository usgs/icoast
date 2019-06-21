<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/userGroupEditorCode.php');
$pageBody = <<<HTML
    <div id="adminPageWrapper">
        $adminNavHTML
        <div id="adminContentWrapper">
            <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
            <h1>User Group Management</h1>
            
            <div id="loadingWrapper">
                <h2>Loading...</h2>
                <p>If this loading screen persists please check Javascript is enabled in your browser.</p>
            </div>
            
            <div id="actionButtonWrapper">
                <h2> Choose An Action</h2>
                <input type="button" id="newGroupActionButton" class="clickableButton" value="Create A New Group" />
                <input type="button" id="editGroupActionButton" class="clickableButton" 
                    value="Edit An Existing Group" />
                <input type="button" id="deleteGroupActionButton" class="clickableButton" 
                    value="Delete An Existing Group" />
            </div>
            
            <div id="newGroupWrapper" class="actionWrappers">
                <h2>Create A New User Group</h2>
                <div class="formFieldRow" id="newGroupProjectFormRow">
                    <label for="newGroupProjectSelect">Project: *</label>
                    <select id="newGroupProjectSelect" class="formInputStyle" autocomplete="off">
                        <option value="0">Select a project...</option>
                    </select>
                </div>
                <div class="formFieldRow" id="newGroupNameFormRow">
                    <label for="newGroupNameTextInput">Group Name: *</label>
                    <input type="text" id="newGroupNameTextInput" class="formInputStyle disabledClickableButton" 
                        maxlength="50" autocomplete="off" disabled>
                    <span id="newGroupNameValidationMessage" class="error"></span>
                </div>
                <div class="formFieldRow" id="newGroupDescriptionFormRow">
                    <label for="newGroupDescriptionTextArea">Group Description:</label>
                    <textarea id="newGroupDescriptionTextArea" class="formInputStyle disabledClickableButton" rows="4" 
                        cols="30" maxlength="500" autocomplete="off" disabled></textarea>
                    <span id="newGroupDescriptionValidationMessage" class="error"></span>
                </div>
                <p id="newGroupSubmissionErrorMessage" class="error"></p>
                <input type="button" id="newGroupSubmitButton" class="clickableButton disabledClickableButton submitButton" 
                    value="Create New Group" disabled/>
            </div>
            
            
            
            
            
            
            <div id="editGroupWrapper" class="actionWrappers">
                <h2>Edit An Existing Group</h2>
                <div>
                    <h3>Select A Group To Edit</h3>
                    <div class="formFieldRow" id="editGroupProjectFormRow">
                        <label for="editGroupProjectSelect">Project: *</label>
                        <select id="editGroupProjectSelect" class="formInputStyle" autocomplete="off">
                            <option value="0">Select a project...</option>
                        </select>
                        <span id="editGroupProjectValidationMessage" class="error"></span>
                    </div>
                    <div class="formFieldRow" id="editGroupSelectFormRow">
                        <label for="editGroupGroupSelect">Group: *</label>
                        <select id="editGroupGroupSelect" class="formInputStyle disabledClickableButton" autocomplete="off" disabled>
                        </select>
                    </div>
                </div>
                <div>
                    <h3>Group Properties</h3>
                    <p>Make any desired changes and then click the 'Submit Group Edits' button to commit them to the database.</p>
                    <p>To undo all changes click the 'Reset Group' button.</p>
                    <div class="formFieldRow" id="editGroupNameFormRow">
                        <label for="editGroupNameTextInput">Group Name: *</label>
                        <input type="text" id="editGroupNameTextInput" class="formInputStyle disabledClickableButton editGroupProperty" 
                            maxlength="50" autocomplete="off" disabled>
                        <span id="editGroupNameValidationMessage" class="error"></span>
                    </div>
                    <div class="formFieldRow" id="editGroupDescriptionFormRow">
                        <label for="editGroupDescriptionTextArea">Group Description:</label>
                        <textarea id="editGroupDescriptionTextArea" class="formInputStyle disabledClickableButton editGroupProperty" rows="4" 
                            cols="30" maxlength="500" autocomplete="off" disabled></textarea>
                        <span id="editGroupDescriptionValidationMessage" class="error"></span>
                    </div>
                    <h4>Review and Remove Users</h4>
                    <p>Select one or more users to remove from the group, then select the 'Remove Selected Users' button.</p>
                    <div class="formFieldRow" id="editGroupExistingUsersFormRow">
                        <label for="editGroupExistingUsersSelect">Existing Users:</label>
                        <select id="editGroupExistingUsersSelect" class="formInputStyle disabledClickableButton editGroupProperty" autocomplete="off" size="5" multiple disabled>
                        </select>
                    </div>
                    <input type="button" id="editRemoveUserButton" class="clickableButton disabledClickableButton" 
                        value="Remove Selected Users" autocomplete="off" disabled />
                </div>
                <div>
                    <h4>Add Users</h4>
                    <p>Select a alphabetical range, then a user from that range. Finally select the 'Add User" button to add the user to the group.</p>
                    <input type="button" id="a-dUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="A - D" autocomplete="off" disabled/>
                    <input type="button" id="e-jUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="E - J" autocomplete="off" disabled/>
                    <input type="button" id="k-oUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="K - O" autocomplete="off" disabled/>
                    <input type="button" id="p-tUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="P - T" autocomplete="off" disabled/>
                    <input type="button" id="u-zUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="U - Z" autocomplete="off" disabled/>
                    <input type="button" id="numericUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="0 - 9" autocomplete="off" disabled/>
                    <input type="button" id="otherUsers" class="clickableButton disabledClickableButton editGroupProperty" 
                        value="Others" autocomplete="off" disabled/>
                    <div class="formFieldRow" id="editGroupSelectFormRow">
                        <label for="editGroupAddUserSelect">User: *</label>
                        <select id="editGroupAddUserSelect" class="formInputStyle disabledClickableButton" autocomplete="off" disabled>
                        </select>
                    </div>
                    <input type="button" id="editAddUserButton" class="clickableButton disabledClickableButton" 
                        value="Add User" autocomplete="off" disabled />
                </div>
                <div>
                    <p id="editGroupSubmissionMessage" class="error"></p>
                    <input type="button" id="editGroupResetButton" class="clickableButton disabledClickableButton resetButton" 
                        value="Reset Group" autocomplete="off" disabled/>
                    <input type="button" id="editGroupSubmitButton" class="clickableButton disabledClickableButton submitButton" 
                        value="Submit Group Edits" autocomplete="off" disabled/>
                </div>
            </div>
            
            
            
            
            
            <div id="deleteGroupWrapper" class="actionWrappers">
                <h2>Delete An Existing Group</h2>
                    <div>
                        <h3>Select A Group To Delete</h3>
                        <div class="formFieldRow" id="deleteGroupProjectFormRow">
                            <label for="deleteGroupProjectSelect">Project: *</label>
                            <select id="deleteGroupProjectSelect" class="formInputStyle" autocomplete="off">
                                <option value="0">Select a project...</option>
                            </select>
                            <span id="deleteGroupProjectValidationMessage" class="error"></span>
                        </div>
                        <div class="formFieldRow" id="deleteGroupSelectFormRow">
                            <label for="deleteGroupGroupSelect">Group: *</label>
                            <select id="deleteGroupGroupSelect" class="formInputStyle disabledClickableButton" autocomplete="off" disabled>
                            </select>
                        </div>
                    </div>
                    <div id="deleteGroupSummaryWrapper">
                        <h3>Group Summary</h3>
                        <div class="summaryRow">
                            <span class="summaryTitle">Name:</span>
                            <span class="summaryContent userData" id="deleteGroupSummaryName"></span>
                        </div>
                        <div class="summaryRow">
                            <span class="summaryTitle">Description:</span>
                            <span class="summaryContent userData" id="deleteGroupSummaryDescription"></span>
                        </div>
                        <div class="summaryRow">
                            <span class="summaryTitle">Members:</span>
                            <span class="summaryContent userData" id="deleteGroupSummaryMembers"></span>
                        </div>
                        <p id="deleteGroupSubmissionMessage" class="error"></p>
                        <input type="button" id="deleteGroupSubmitButton" class="clickableButton submitButton" 
                            value="Delete Group" autocomplete="off"/>
                    </div>
                    
            </div>
            
        </div>
    </div>
    <script>
        $javaScript
    </script>
    <script src="scripts/userGroupEditor.js" type="text/javascript"></script>
HTML;

require('includes/template.php');
