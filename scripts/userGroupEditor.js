var actionWrappers,
    newGroupWrapper,
    newGroupActionButton,
    editGroupActionButton,
    deleteGroupActionButton,
    newGroupProjectSelect,
    newGroupNameFormRow,
    newGroupNameTextInput,
    newGroupNameValidationMessage,
    newGroupDescriptionTextArea,
    newGroupDescriptionValidationMessage,
    newGroupSubmissionErrorMessage,
    newGroupSubmitButton,
    editGroupWrapper,
    editGroupProjectSelect,
    editGroupProjectValidationMessage,
    editGroupGroupSelect,
    editGroupProperty,
    editGroupNameValidationMessage,
    editGroupNameTextInput,
    editGroupDescriptionTextArea,
    editGroupExistingUsersSelect,
    editRemoveUserButton,
    a2dUserAccountsButton,
    e2jUserAccountsButton,
    k2oUserAccountsButton,
    p2tUserAccountsButton,
    u2zUserAccountsButton,
    numericUserAccountsButton,
    otherUserAccountsButton,
    editGroupAddUserSelect,
    editAddUserButton,
    editGroupResetButton,
    editGroupSubmitButton,
    editGroupSubmissionMessage,
    deleteGroupWrapper,
    deleteGroupProjectSelect,
    deleteGroupProjectValidationMessage,
    deleteGroupGroupSelect,
    deleteGroupSummaryWrapper,
    deleteGroupSummaryName,
    deleteGroupSummaryDescription,
    deleteGroupSummaryMembers,
    deleteGroupSubmissionMessage,
    deleteGroupSubmitButton;

var dividedUserArray = null;
var newGroupSelectedProjectId = null;
var editGroupSelectedProjectId = null;
var editGroupSelectedGroupId = null;
var editGroupSelectedGroupUsers = null;
var editGroupNewGroupUsers = [];
var editGroupRemovedGroupUsers = [];
var editGroupCurrentAddUserGroupInSelect = null;
var editGroupGroupNameUpdated = false;
var editGroupGroupNameValid = true;
var deleteGroupSelectedProjectId = null;
var deleteGroupSelectedGroupId = null;
var deleteGroupSelectedGroupUsers = null;

function dividedUserArrayEmailSort(email1, email2) {
    if (email1['email'] == email2['email']) {
        return 0;
    }
    return (email1['email'] < email2['email']) ? -1 : 1;
} // END function dividedUserArrayEmailSort(email1, email2)

function divideUserArray() {
    dividedUserArray = {
        'a-dUsers': [],
        'e-jUsers': [],
        'k-oUsers': [],
        'p-tUsers': [],
        'u-zUsers': [],
        'numericUsers': [],
        'otherUsers': []
    };
    $.each(allUsers, function (userId, email) {
        var firstCharacter = email.substring(0, 1);
        if (firstCharacter >= 'a' && firstCharacter <= 'd') {
            dividedUserArray['a-dUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else if (firstCharacter >= 'e' && firstCharacter <= 'j') {
            dividedUserArray['e-jUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else if (firstCharacter >= 'k' && firstCharacter <= 'o') {
            dividedUserArray['k-oUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else if (firstCharacter >= 'p' && firstCharacter <= 't') {
            dividedUserArray['p-tUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else if (firstCharacter >= 'u' && firstCharacter <= 'z') {
            dividedUserArray['u-zUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else if (firstCharacter >= '0' && firstCharacter <= '9') {
            dividedUserArray['numericUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        } else {
            dividedUserArray['otherUsers'].push(
                {
                    'userId': userId,
                    'email': email
                }
            );
        }
    }); // END $.each(allUsers, function (userId, email)

    $.each(dividedUserArray, function (index, dividedArray) {
        dividedArray.sort(dividedUserArrayEmailSort);
    });
} // END function divideUserArray()

function assignElemsToVars() {
    actionWrappers = $('.actionWrappers');
    newGroupWrapper = $('#newGroupWrapper');
    newGroupActionButton = $('#newGroupActionButton');
    newGroupProjectSelect = $('#newGroupProjectSelect');
    editGroupActionButton = $('#editGroupActionButton');
    deleteGroupActionButton = $('#deleteGroupActionButton');
    newGroupNameFormRow = $('#newGroupNameFormRow');
    newGroupNameTextInput = $('#newGroupNameTextInput');
    newGroupNameValidationMessage = $('#newGroupNameValidationMessage');
    newGroupDescriptionTextArea = $('#newGroupDescriptionTextArea');
    newGroupDescriptionValidationMessage = $('#newGroupDescriptionValidationMessage');
    newGroupSubmissionErrorMessage = $('#newGroupSubmissionErrorMessage');
    newGroupSubmitButton = $('#newGroupSubmitButton');
    editGroupWrapper = $('#editGroupWrapper');
    editGroupProjectSelect = $('#editGroupProjectSelect');
    editGroupProjectValidationMessage = $('#editGroupProjectValidationMessage');
    editGroupGroupSelect = $('#editGroupGroupSelect');
    editGroupProperty = $('.editGroupProperty');
    editGroupNameTextInput = $('#editGroupNameTextInput');
    editGroupNameValidationMessage = $('#editGroupNameValidationMessage');
    editGroupDescriptionTextArea = $('#editGroupDescriptionTextArea');
    editGroupExistingUsersSelect = $('#editGroupExistingUsersSelect');
    editRemoveUserButton = $('#editRemoveUserButton');
    a2dUserAccountsButton = $('#a-dUsers');
    e2jUserAccountsButton = $('#e-jUsers');
    k2oUserAccountsButton = $('#k-oUsers');
    p2tUserAccountsButton = $('#p-tUsers');
    u2zUserAccountsButton = $('#u-zUsers');
    numericUserAccountsButton = $('#numericUsers');
    otherUserAccountsButton = $('#otherUsers');
    editGroupAddUserSelect = $('#editGroupAddUserSelect');
    editAddUserButton = $('#editAddUserButton');
    editGroupResetButton = $('#editGroupResetButton');
    editGroupSubmitButton = $('#editGroupSubmitButton');
    editGroupSubmissionMessage = $('#editGroupSubmissionMessage');
    deleteGroupWrapper = $('#deleteGroupWrapper');
    deleteGroupProjectSelect = $('#deleteGroupProjectSelect');
    deleteGroupProjectValidationMessage = $('#deleteGroupProjectValidationMessage');
    deleteGroupGroupSelect = $('#deleteGroupGroupSelect');
    deleteGroupSummaryWrapper = $('#deleteGroupSummaryWrapper');
    deleteGroupSummaryName = $('#deleteGroupSummaryName');
    deleteGroupSummaryDescription = $('#deleteGroupSummaryDescription');
    deleteGroupSummaryMembers = $('#deleteGroupSummaryMembers');
    deleteGroupSubmissionMessage = $('#deleteGroupSubmissionMessage');
    deleteGroupSubmitButton = $('#deleteGroupSubmitButton');

} // END assignElemsToVars()

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Action Selection Buttons
function resetActionButtons() {
    newGroupActionButton.add(editGroupActionButton)
                        .add(deleteGroupActionButton)
                        .removeClass('selectedClickableButton')
} // END resetActionButtons()

function newGroupActionButtonClick() {

    newGroupFormReset();
    newGroupSelectedProjectId = null;
    newGroupProjectSelect.prop('selectedIndex', 0);

    if (newGroupWrapper.not(':visible')) {
        resetActionButtons();
        newGroupActionButton.addClass('selectedClickableButton');
        actionWrappers.hide();
        newGroupWrapper.show();
        positionFeedbackDiv();
    }
} // END newGroupButtonClick()

function editGroupActionButtonClick($projectId, $groupId) {

    editGroupFormReset();
    editGroupProjectSelect.prop('selectedIndex', 0);
    editGroupProjectValidationMessage.add(editGroupGroupSelect)
                                     .empty();
    editGroupGroupSelect.addClass('disabledClickableButton')
                        .prop('disabled', true);
    editGroupSelectedProjectId = null;
    editGroupSelectedGroupId = null;

    if ($projectId != null) {
        $('#editGroupProjectSelect option[value=' + $projectId + ']').prop('selected', true);
        editGroupProjectSelected();
    }
    if ($projectId != null && $groupId != null) {
        $('#editGroupGroupSelect option[value=' + $groupId + ']').prop('selected', true);
        editGroupGroupSelected();
    }

    if (editGroupWrapper.not(':visible')) {
        resetActionButtons();
        editGroupActionButton.addClass('selectedClickableButton');
        actionWrappers.hide();
        editGroupWrapper.show();
        positionFeedbackDiv();
    }
} // END editGroupActionButtonClick();

function deleteGroupActionButtonClick() {
    deleteGroupProjectSelect.prop('selectedIndex', 0);
    deleteGroupSelectedProjectId = null;
    deleteGroupGroupSelect.empty()
                          .addClass('disabledClickableButton')
                          .prop('disabled', true);
    deleteGroupSelectedGroupId = null;
    deleteGroupFormReset();
    if (deleteGroupWrapper.not(':visible')) {
        resetActionButtons();
        deleteGroupActionButton.addClass('selectedClickableButton');
        actionWrappers.hide();
        deleteGroupWrapper.show();
        positionFeedbackDiv();
    }
} // END deleteGroupActionButtonClick();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// New Group Controls
function newGroupFormReset() {

    newGroupNameTextInput.add(newGroupDescriptionTextArea)
                         .val('');

    newGroupNameTextInput.add(editRemoveUserButton)
                         .add(newGroupDescriptionTextArea)
                         .add(newGroupSubmitButton)
                         .addClass('disabledClickableButton')
                         .prop('disabled', true);
}

function validateNewGroupName() {
    var validationMessage = '';
    var submitButtonEnabled = true;
    var name = newGroupNameTextInput.val().trim();
    if (name.length == 0) {
        validationMessage = 'Required field';
        submitButtonEnabled = false;
    } else {
        if (name.length == 50) {
            validationMessage = 'Max length reached';
        }
        if (groups[newGroupSelectedProjectId]) {
            $.each(groups[newGroupSelectedProjectId], function (index, group) {
                if (name.toLowerCase() === group.name.toLowerCase()) {
                    validationMessage = 'Name already used';
                    submitButtonEnabled = false;
                    return false;
                }
            }); // END EACH
        } // END IF (groups[newGroupSelectedProjectId])
    } // END IF ELSE (name.length == 50)

    newGroupNameValidationMessage.text(validationMessage);
    if (submitButtonEnabled === true) {
        newGroupSubmitButton.removeClass('disabledClickableButton')
                            .prop("disabled", false);
    } else {
        newGroupSubmitButton.addClass('disabledClickableButton')
                            .prop("disabled", true);
    }
} // END validateNewGroupName()

function newGroupProjectSelected() {
    var optionSelected = newGroupProjectSelect.find("option:selected");
    newGroupSelectedProjectId = parseInt(optionSelected.val());
    console.log(newGroupSelectedProjectId);
    if (newGroupSelectedProjectId === 0) {
        newGroupFormReset();
        newGroupSelectedProjectId = null;
    } else {
        newGroupFormReset();
        newGroupNameTextInput.add(newGroupDescriptionTextArea)
                             .removeClass('disabledClickableButton')
                             .prop("disabled", false);
    }

} // END newGroupProjectSelected()

function validateNewGroupDescription() {
    var validationMessage = '';
    var description = newGroupDescriptionTextArea.val().trim();
    if (description.length == 500) {
        validationMessage = 'Max length reached';
    }
    newGroupDescriptionValidationMessage.text(validationMessage);
} // END validateNewGroupDescription()

function submitNewGroup() {
    var data = {
        'requestType': 'newGroup',
        'projectId': newGroupSelectedProjectId,
        'name': newGroupNameTextInput.val().trim(),
        'description': newGroupDescriptionTextArea.val().trim()
    };

    $.post('ajax/userGroupEditor.php',
        data,
        function (result) {
            if (result.success && result.success === true) {
                // Add the new group to the group object
                if (!groups[result.projectId]) {
                    groups[result.projectId] = [];
                }
                groups[result.projectId].push({
                    'groupId': result.groupId,
                    'projectId': result.projectId,
                    'name': result.name,
                    'description': result.description
                });
                alert('Your new user group has been created.\n\n' +
                    'You will now be redirected to the Edit Group\n' +
                    'screen to make changes and add users.');
                editGroupActionButtonClick(result.projectId, result.groupId);
            } else {
                if (result.error) {
                    newGroupSubmissionErrorMessage.html(
                        'Creation of new user group failed with error:<br><b>' +
                        result.error +
                        '</b><br>Please try again or contact the developer if the problem persists.'
                    );
                    positionFeedbackDiv();
                }
            }
        }, // END POST RESULT PROCESSING FUNCTION
        'json'
    ); // END $.POST

} // END submitNewGroup()

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Edit Group Controls
function editGroupFormReset() {
    editGroupSelectedGroupUsers = null;
    editGroupNewGroupUsers = [];
    editGroupRemovedGroupUsers = [];
    editGroupGroupNameUpdated = false;
    editGroupGroupNameValid = true;
    editGroupCurrentAddUserGroupInSelect = null;

    editGroupNameTextInput.add(editGroupDescriptionTextArea)
                          .val('');
    editGroupNameValidationMessage.add(editGroupExistingUsersSelect)
                                  .add(editGroupAddUserSelect)
                                  .add(editGroupSubmissionMessage)
                                  .empty();
    editGroupProperty.add(editRemoveUserButton)
                     .add(editGroupAddUserSelect)
                     .add(editAddUserButton)
                     .add(editGroupResetButton)
                     .add(editGroupSubmitButton)
                     .removeClass('selectedClickableButton')
                     .addClass('disabledClickableButton')
                     .prop('disabled', true);
}

function editGroupProjectSelected() {

    editGroupFormReset();
    editGroupGroupSelect.empty();
    editGroupSelectedGroupId = null;
    var selectedOptionValue = editGroupProjectSelect.val();
    editGroupSelectedProjectId = parseInt(selectedOptionValue);
    if (editGroupSelectedProjectId === 0) {
        editGroupProjectValidationMessage.empty();
        editGroupGroupSelect.addClass('disabledClickableButton')
                            .prop('disabled', true);
        editGroupSelectedProjectId = null;
        return;
    }
    console.log(editGroupSelectedProjectId);
    console.log(groups);
    if (groups[editGroupSelectedProjectId]) {
        editGroupGroupSelect.append('<option value="0">Select a group...</option>');
        $.each(groups[editGroupSelectedProjectId], function (key, group) {
            var projectSelectOptionHTML = '<option';
            projectSelectOptionHTML += ' value="' + group['groupId'] + '"';
            projectSelectOptionHTML += ' title="' + group['description'] + '"';
            projectSelectOptionHTML += '>' + group['name'];
            projectSelectOptionHTML += '</option>\n\r';
            editGroupGroupSelect.append(projectSelectOptionHTML);
        });
        editGroupProjectValidationMessage.text('');
        editGroupGroupSelect.removeClass('disabledClickableButton')
                            .prop('disabled', false);
    } else {
        editGroupProjectValidationMessage.text('No groups in this project');
        editGroupGroupSelect.empty()
                            .addClass('disabledClickableButton')
                            .prop('disabled', true);
    }
} // END editGroupProjectSelected()

function buildExistingUserSelectOptions() {
    editGroupExistingUsersSelect.empty();
    if (editGroupSelectedGroupUsers == null) {
        return;
    }
    var firstLoop = true;
    $.each(editGroupSelectedGroupUsers, function (userId, email) {
        var newOptionElement = $('<option class="existingUserListOptions" value="' + userId + '">' + email + '</option>');
        if (firstLoop) {
            editGroupExistingUsersSelect.append(newOptionElement);
            firstLoop = false;
        } else {
            var optionWritten = false;
            $('.existingUserListOptions').each(function () {
                if (email.toLowerCase() < $(this).text().toLowerCase()) {
                    $(this).before(newOptionElement);
                    optionWritten = true;
                    return false;
                } else {
                }
            });
            if (!optionWritten) {
                editGroupExistingUsersSelect.append(newOptionElement);
            }
        }
    });
    var userCount = Object.keys(editGroupSelectedGroupUsers).length;
    userCount = userCount > 10 ? 10 : userCount;
    userCount = userCount < 5 ? 5 : userCount;
    editGroupExistingUsersSelect.attr('size', userCount);
}

function editGroupGroupSelected() {

    editGroupFormReset();

    var optionSelected = editGroupGroupSelect.find("option:selected");
    editGroupSelectedGroupId = parseInt(optionSelected.val());

    if (editGroupSelectedGroupId === 0) {
        editGroupSelectedGroupId = null;
        return;
    }

    editGroupNameTextInput.val(optionSelected.text());
    editGroupDescriptionTextArea.val(optionSelected.attr('title'));
    var data = {
        'requestType': 'loadUsers',
        'groupId': editGroupSelectedGroupId
    };
    $.post('ajax/userGroupEditor.php',
        data,
        function (result) {
            if (result.success && result.success === true) {
                editGroupSelectedGroupUsers = result.users;

                buildExistingUserSelectOptions();

                editGroupProperty.removeClass('disabledClickableButton')
                                 .prop('disabled', false);
                $.each(dividedUserArray, function (userAlphaGroupKey, dividedUsers) {
                    if (dividedUsers.length == 0) {
                        $('#' + userAlphaGroupKey).addClass('disabledClickableButton')
                                                  .prop('disabled', true)
                                                  .attr('title', 'No users in this grouping.');
                    }
                });
                positionFeedbackDiv();
            }
        }, // END POST RESULT PROCESSING FUNCTION
        'json'); // END $.POST


} // END editGroupGroupSelected

function validateEditGroupName() {
    editGroupGroupNameUpdated = true;
    editGroupGroupNameValid = true;
    var validationMessage = '';
    var name = editGroupNameTextInput.val().trim();
    if (name.length == 0) {
        validationMessage = 'Required field';
        editGroupGroupNameUpdated = true;
        editGroupGroupNameValid = false;
    } else {
        if (name.length == 50) {
            validationMessage = 'Max length reached';
        }
        if (groups[editGroupSelectedProjectId]) {
            $.each(groups[editGroupSelectedProjectId], function (index, group) {
                if (group.groupId == editGroupSelectedGroupId &&
                    name.toLowerCase() === group.name.toLowerCase()) {
                    editGroupGroupNameUpdated = false;
                    editGroupGroupNameValid = true;
                    return false;
                }

                if (name.toLowerCase() === group.name.toLowerCase()) {
                    validationMessage = 'Name already used';
                    editGroupGroupNameUpdated = true;
                    editGroupGroupNameValid = false;
                    return false;
                }
            }); // END EACH
        } // END IF (groups[newGroupSelectedProjectId])
    } // END IF ELSE (name.length == 50)
    editGroupNameValidationMessage.text(validationMessage);
    editGroupSubmissionMessage.empty();
    editGroupSubmitAndResetButtonStatus();
} // END validateNewGroupName()

function editGroupRemoveUserSelected() {
    editRemoveUserButton.removeClass('disabledClickableButton')
                        .prop('disabled', false);
    editGroupSubmissionMessage.empty();
}

function editGroupRemoveUserButtonClick() {
    var userIds = editGroupExistingUsersSelect.val();
    $.each(userIds, function (key, userId) {
        var editGroupNewGroupUsersIndex = $.inArray(userId, editGroupNewGroupUsers);
        if (editGroupNewGroupUsersIndex >= 0) {
            editGroupNewGroupUsers.splice(editGroupNewGroupUsersIndex, 1);
        } else {
            editGroupRemovedGroupUsers.push(parseInt(userId));
        }
        editGroupExistingUsersSelect.find('option').remove("option[value=" + userId + ']');
        if (editGroupCurrentAddUserGroupInSelect) {
            populateAddUserSelect(editGroupCurrentAddUserGroupInSelect);
        }
    });
    editGroupSubmitAndResetButtonStatus();
}

function populateAddUserSelect(userGroupButton) {
    if (userGroupButton.hasClass('selectedClickableButton')) {
        return;
    }
    a2dUserAccountsButton.add(e2jUserAccountsButton)
                         .add(k2oUserAccountsButton)
                         .add(p2tUserAccountsButton)
                         .add(u2zUserAccountsButton)
                         .add(numericUserAccountsButton)
                         .add(otherUserAccountsButton)
                         .removeClass('selectedClickableButton');
    userGroupButton.addClass('selectedClickableButton');
    editGroupAddUserSelect.empty();
    editGroupCurrentAddUserGroupInSelect = userGroupButton;
    editGroupAddUserSelect.append('<option value="0">Select a user to add...</option>');
    $.each(dividedUserArray[userGroupButton.attr('id')], function (key, dividedUsers) {
        if ((editGroupSelectedGroupUsers[dividedUsers.userId] && $.inArray(dividedUsers.userId, editGroupRemovedGroupUsers) == -1) ||
            $.inArray(dividedUsers.userId, editGroupNewGroupUsers) >= 0) {
            return true;
        }
        var newUsersSelectHTML = '<option';
        newUsersSelectHTML += ' value="' + dividedUsers.userId + '"';
        newUsersSelectHTML += '>' + dividedUsers.email;
        newUsersSelectHTML += '</option>\n\r';
        editGroupAddUserSelect.append(newUsersSelectHTML);
    });
    editGroupAddUserSelect.removeClass('disabledClickableButton')
                          .prop('disabled', false);
    editAddUserButton.addClass('disabledClickableButton')
                     .prop('disabled', true);
    editGroupSubmissionMessage.empty();
}

function editGroupAddUserSelected() {
    var selectedOptionValue = editGroupAddUserSelect.val();
    if (selectedOptionValue > 0) {
        editAddUserButton.removeClass('disabledClickableButton')
                         .prop('disabled', false);
    } else {
        editAddUserButton.addClass('disabledClickableButton')
                         .prop('disabled', true);
    }
    editGroupSubmissionMessage.empty();
}

function editGroupAddUserClick() {
    var userId = editGroupAddUserSelect.val();
    var editGroupRemovedGroupUsersIndex = $.inArray(userId, editGroupRemovedGroupUsers);
    if (editGroupRemovedGroupUsersIndex >= 0) {
        editGroupRemovedGroupUsers.splice(editGroupRemovedGroupUsersIndex, 1);
    } else {
        editGroupNewGroupUsers.push(parseInt(userId));
    }
    editGroupExistingUsersSelect.append(editGroupAddUserSelect.find("option[value=" + userId + ']'));
    editGroupAddUserSelect.find('option').remove("option[value=" + userId + ']');
    editGroupAddUserSelect.prop('selectedIndex', 0);
    editAddUserButton.addClass('disabledClickableButton')
                     .prop('disabled', true);
    editGroupSubmitAndResetButtonStatus();
}

function editGroupSubmitAndResetButtonStatus() {
    var groupSelectedOption = editGroupGroupSelect.find("option:selected");
    if ((
            editGroupGroupNameUpdated ||
            editGroupNewGroupUsers.length > 0 ||
            editGroupRemovedGroupUsers.length > 0 ||
            editGroupDescriptionTextArea.val().trim() != groupSelectedOption.attr('title')
        ) &&
        editGroupGroupNameValid
    ) {
        editGroupSubmitButton.add(editGroupResetButton)
                             .removeClass('disabledClickableButton')
                             .prop("disabled", false);
    } else {
        editGroupSubmitButton.add(editGroupResetButton)
                             .addClass('disabledClickableButton')
                             .prop("disabled", true);
    }
}

function editGroupResetButtonClick() {
    editGroupNewGroupUsers = [];
    editGroupRemovedGroupUsers = [];
    editGroupGroupNameUpdated = false;
    editGroupGroupNameValid = true;
    editGroupCurrentAddUserGroupInSelect = null;

    var optionSelected = editGroupGroupSelect.find("option:selected");
    editGroupNameTextInput.val(optionSelected.text());
    console.log(optionSelected.attr('title'));
    editGroupDescriptionTextArea.val(optionSelected.attr('title'));

    buildExistingUserSelectOptions();
    editGroupProperty.removeClass('selectedClickableButton');
    editGroupAddUserSelect.add(editGroupSubmissionMessage)
                          .empty();
    editGroupSubmitButton.add(editGroupResetButton)
                         .add(editRemoveUserButton)
                         .add(editAddUserButton)
                         .add(editGroupAddUserSelect)
                         .addClass('disabledClickableButton')
                         .prop('disabled', true);
} // END editGroupResetButtonClick()

function editGroupSubmitButtonClick() {
    var data = {
        'requestType': 'editGroup',
        'groupId': editGroupSelectedGroupId,
        'name': editGroupNameTextInput.val().trim(),
        'description': editGroupDescriptionTextArea.val().trim(),
        'newUsers': JSON.stringify(editGroupNewGroupUsers),
        'removedUsers': JSON.stringify(editGroupRemovedGroupUsers)
    };
    console.log(data);
    $.post(
        'ajax/userGroupEditor.php',
        data,
        function (result) {
            console.log('Running');
            if (result.success && result.success === true) {
                console.log('Success');
                // Add the new group to the group object
                $.each(groups[editGroupSelectedProjectId], function (projectId, group) {
                    if (group.groupId == result.groupId) {
                        group.name = result.name;
                        group.description = result.description
                        return true;
                    }
                });
                console.log('Start');
                console.log(editGroupSelectedGroupUsers);
                var addedUsers = $.parseJSON(result.addedUsers);
                $.each(addedUsers, function (index, userId) {
                    editGroupSelectedGroupUsers[userId] = allUsers[userId];
                });
                console.log('After adding');
                console.log(editGroupSelectedGroupUsers);
                var removedUsers = $.parseJSON(result.removedUsers);
                $.each(removedUsers, function (index, userId) {
                    delete editGroupSelectedGroupUsers[userId];
                });
                console.log('After removing');
                console.log(editGroupSelectedGroupUsers);
                var optionSelected = editGroupGroupSelect.find("option:selected");
                optionSelected.text(result.name).prop('title', result.description);
                editGroupNewGroupUsers = [];
                editGroupRemovedGroupUsers = [];
                editGroupSubmissionMessage.html(
                    'Edits successfully saved.'
                );
            } else {
                if (!result.error) {
                    result.error = "Unspecified";
                }
                editGroupResetButton.trigger('click');
                editGroupSubmissionMessage.html(
                    'Edit of existing user group failed with error:<br><b>' +
                    result.error +
                    '</b><br>Please try again or contact the developer if the problem persists.'
                );
                positionFeedbackDiv();
            }
        }, // END POST RESULT PROCESSING FUNCTION
        'json'
    ); // END $.POST
} //END editGroupSubmitButtonClick

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Delete Button Controls
function deleteGroupFormReset() {
    deleteGroupSelectedGroupUsers = null;

    deleteGroupProjectValidationMessage.text('');

    deleteGroupSummaryName.add(deleteGroupProjectValidationMessage)
                          .add(deleteGroupSummaryDescription)
                          .add(deleteGroupSummaryMembers)
                          .add(deleteGroupSubmissionMessage)
                          .empty();
    deleteGroupSummaryWrapper.hide();
}

function deleteGroupProjectSelected() {

    deleteGroupFormReset();
    deleteGroupGroupSelect.empty();
    deleteGroupSelectedGroupId = null;

    var selectedOptionValue = deleteGroupProjectSelect.val();
    deleteGroupSelectedProjectId = parseInt(selectedOptionValue);
    if (deleteGroupSelectedProjectId === 0) {
        deleteGroupGroupSelect.addClass('disabledClickableButton')
                              .prop('disabled', true);
        deleteGroupSelectedProjectId = null;
        return;
    }
    if (groups[deleteGroupSelectedProjectId]) {
        deleteGroupGroupSelect.append('<option value="0">Select a group...</option>');
        console.log(groups);
        $.each(groups[deleteGroupSelectedProjectId], function (key, group) {
            console.log(key);
            console.log(group);
            var projectSelectOptionHTML = '<option';
            projectSelectOptionHTML += ' value="' + group['groupId'] + '"';
            projectSelectOptionHTML += ' title="' + group['description'] + '"';
            projectSelectOptionHTML += '>' + group['name'];
            projectSelectOptionHTML += '</option>\n\r';
            deleteGroupGroupSelect.append(projectSelectOptionHTML);
        });
        deleteGroupProjectValidationMessage.text('');
        deleteGroupGroupSelect.removeClass('disabledClickableButton')
                              .prop('disabled', false);
    } else {
        deleteGroupProjectValidationMessage.text('No groups in this project');
        deleteGroupGroupSelect.empty()
                              .addClass('disabledClickableButton')
                              .prop('disabled', true);
    }
} // END editGroupProjectSelected()

function deleteGroupGroupSelected() {

    deleteGroupFormReset();

    var optionSelected = deleteGroupGroupSelect.find("option:selected");
    deleteGroupSelectedGroupId = parseInt(optionSelected.val());

    if (deleteGroupSelectedGroupId === 0) {
        deleteGroupSelectedGroupId = null;
        return;
    }

    deleteGroupSummaryName.text(optionSelected.text());
    deleteGroupSummaryDescription.text(optionSelected.attr('title'));
    var data = {
        'requestType': 'loadUsers',
        'groupId': deleteGroupSelectedGroupId
    };
    $.post('ajax/userGroupEditor.php',
        data,
        function (result) {
            if (result.success && result.success === true) {
                deleteGroupSelectedGroupUsers = result.users;
                if (deleteGroupSelectedGroupUsers == null) {
                    deleteGroupSummaryMembers.html('<span class="existingUserRow">No members in group</span>');
                    return;
                }
                var firstLoop = true;
                $.each(deleteGroupSelectedGroupUsers, function (userId, email) {
                    var newSpanElement = $('<span class="existingUserRow">' + email + '</span>');
                    if (firstLoop) {
                        deleteGroupSummaryMembers.append(newSpanElement);
                        firstLoop = false;
                    } else {
                        var optionWritten = false;
                        $('.existingUserRow').each(function () {
                            if (email.toLowerCase() < $(this).text().toLowerCase()) {
                                $(this).before(newSpanElement);
                                optionWritten = true;
                                return false;
                            }
                        });
                        if (!optionWritten) {
                            deleteGroupSummaryMembers.append(newSpanElement);
                        }
                    }
                });
                deleteGroupSummaryWrapper.show();
                positionFeedbackDiv();
            }
        }, // END POST RESULT PROCESSING FUNCTION
        'json'); // END $.POST


} // END editGroupGroupSelected

function deleteGroupSubmitButtonClicked() {
    var optionSelected = deleteGroupGroupSelect.find("option:selected");

    var userResponse = confirm('Are you sure you want to delete the\n"' +
        optionSelected.text() + '" group');
    if (!userResponse) {
        return;
    }
    var data = {
        'requestType': 'deleteGroup',
        'groupId': deleteGroupSelectedGroupId
    };

    $.post('ajax/userGroupEditor.php',
        data,
        function (result) {
            if (result.success && result.success === true) {
                // Group has been deleted.
                console.log(groups[deleteGroupSelectedProjectId]);
                $.each(groups[deleteGroupSelectedProjectId], function (index, group) {
                    console.log(index);
                    console.log(group);
                    if (group.groupId == result.groupId) {
                        groups[deleteGroupSelectedProjectId].splice(index, 1);
                        return false;
                    }
                });
                console.log(groups[deleteGroupSelectedProjectId]);
                alert('The group has been successfully deleted.');
                resetActionButtons();
                actionWrappers.hide();
                deleteGroupFormReset();
                deleteGroupGroupSelect.empty();
                deleteGroupSelectedProjectId = null;
                deleteGroupSelectedGroupId = null;
            } else {
                if (result.error) {
                    deleteGroupSubmissionMessage.html(
                        'Deletion of group failed with error:<br><b>' +
                        result.error +
                        '</b><br>Please try again or contact the developer if the problem persists.'
                    );
                    positionFeedbackDiv();
                }
            }
        }, // END POST RESULT PROCESSING FUNCTION
        'json'
    ); // END $.POST
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$(document).ready(function () {

    divideUserArray();

    // Assign commonly used elements to variables
    assignElemsToVars();

    // Build project select options, add to select elements and set initial selection to blank.
    $.each(projects, function (key, project) {
        var projectSelectOptionHTML = '<option';
        projectSelectOptionHTML += ' value="' + project['projectId'] + '"';
        projectSelectOptionHTML += ' title="' + project['description'] + '"';
        projectSelectOptionHTML += '>' + project['name'];
        projectSelectOptionHTML += '</option>\n\r';
        newGroupProjectSelect.add(editGroupProjectSelect)
                             .add(deleteGroupProjectSelect)
                             .append(projectSelectOptionHTML);
    });

    // Set event handlers.
    newGroupActionButton.on('click', newGroupActionButtonClick);
    editGroupActionButton.on('click', function () {
        editGroupActionButtonClick()
    });
    deleteGroupActionButton.on('click', deleteGroupActionButtonClick);

    newGroupProjectSelect.on('change', newGroupProjectSelected);
    newGroupNameTextInput.on('input', validateNewGroupName);
    newGroupDescriptionTextArea.on('input', validateNewGroupDescription);
    newGroupSubmitButton.on('click', submitNewGroup);
    editGroupProjectSelect.on('change', editGroupProjectSelected);
    editGroupGroupSelect.on('change', editGroupGroupSelected);
    editGroupNameTextInput.on('input', validateEditGroupName);
    editGroupDescriptionTextArea.on('input', editGroupSubmitAndResetButtonStatus);
    editGroupExistingUsersSelect.on('change', editGroupRemoveUserSelected);
    editRemoveUserButton.on('click', editGroupRemoveUserButtonClick);
    a2dUserAccountsButton.add(e2jUserAccountsButton)
                         .add(k2oUserAccountsButton)
                         .add(p2tUserAccountsButton)
                         .add(u2zUserAccountsButton)
                         .add(numericUserAccountsButton)
                         .add(otherUserAccountsButton)
                         .on('click', function () {
                             populateAddUserSelect($(this))
                         });
    editGroupAddUserSelect.on('change', editGroupAddUserSelected);
    editAddUserButton.on('click', editGroupAddUserClick);
    editGroupResetButton.on('click', editGroupResetButtonClick);
    editGroupSubmitButton.on('click', editGroupSubmitButtonClick);
    deleteGroupProjectSelect.on('change', deleteGroupProjectSelected);
    deleteGroupGroupSelect.on('change', deleteGroupGroupSelected);
    deleteGroupSubmitButton.on('click', deleteGroupSubmitButtonClicked);
    // Clear loading message and display content as javascript is obviously present.
    $('#loadingWrapper').hide();
    $('#actionButtonWrapper').show();


});
