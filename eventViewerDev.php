<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE PHP
ob_start();
$pageModifiedTime = filemtime(__FILE__);




// END PAGE PHP
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CODE PAGE PHP

$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';
$jQueryDocumentDotReadyCode = '';

require_once('includes/globalFunctions.php');
require_once('includes/adminFunctions.php');
require_once('includes/adminNavigation.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

$userId = $userData['user_id'];
$adminLevel = $userData['account_type'];
$adminLevelText = admin_level_to_text($adminLevel);
$maskedEmail = $userData['masked_email'];

// BUILD JAVASCRIPT OBJECT LITERAL CODE FOR POST INCLUSION IN SCRIPT
$jsPostSettings = '';
foreach ($_GET as $key => $value) {
    if (is_numeric($value)) {
        $jsPostSettings .= "$key: $value,";
    } else {
        $jsPostSettings .= "$key: '$value',";
    }
}
rtrim($jsPostSettings, ",");

// DETERMINE THE LIST OF AVAILABLE/PERMISSIONED PROJECTS
if ($adminLevel == 4) {
    $userAdministeredProjects = array();
    $allProjectsQuery = "SELECT project_id, name FROM projects ORDER BY project_id ASC";
    foreach ($DBH->query($allProjectsQuery) as $row) {
        $userAdministeredProjects[] = $row;
    }
} else {
    $userAdministeredProjects = find_administered_projects($DBH, $userId, TRUE);
}

// BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
// PROJECT SELECT
$projectSelectHTML = "";
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $projectId = $singeUserAdministeredProject['project_id'];
    $projectName = $singeUserAdministeredProject['name'];
    if (isset($_GET['project_id']) && $_GET['project_id'] == $projectId) {
        $projectSelectHTML .= "<option value=\"$projectId\" selected>$projectName</option>";
    } else {
        $projectSelectHTML .= "<option value=\"$projectId\">$projectName</option>";
    }
}
$projectCount = count($userAdministeredProjects);

// EVENT TYPE SELECT
$eventTypeSelectHTML = <<<EOL
        <option value="1">System Error</option>
        <option value="2">System Feedback</option>
        <option value="3">Project Feedback</option>
EOL;
if (isset($_GET['event_type'])) {
    switch ($_GET['event_type']) {
        case 1:
            $eventTypeSelectHTML = str_replace('1">', '1" selected>', $eventTypeSelectHTML);
            break;
        case 2:
            $eventTypeSelectHTML = str_replace('2">', '2" selected>', $eventTypeSelectHTML);
            break;
        case 3:
            $eventTypeSelectHTML = str_replace('3">', '3" selected>', $eventTypeSelectHTML);
            break;
    }
}

// USER SELECT
$userSelectHTML = '';
$allUsersQuery = "SELECT user_id, masked_email FROM users ORDER BY masked_email ASC";
foreach ($DBH->query($allUsersQuery) as $individualICoastUser) {
    $iCoastUserId = $individualICoastUser['user_id'];
    $iCoastUserMaskedEmail = $individualICoastUser['masked_email'];
    if (isset($_GET['user_id']) && $_GET['user_id'] == $iCoastUserId) {
        $userSelectHTML .= "<option value = \"$iCoastUserId\" selected>$iCoastUserMaskedEmail</option>";
    } else {
        $userSelectHTML .= "<option value = \"$iCoastUserId\">$iCoastUserMaskedEmail</option>";
    }
}

// SOURCE URL SELECT
$sourcePageSelectHTML = '';
$allSourcePagesQuery = "SELECT DISTINCT source_url FROM event_log ORDER BY source_url ASC";
foreach ($DBH->query($allSourcePagesQuery) as $eachPage) {
    $sourcePath = rtrim($eachPage['source_url'], '/');
    $substrStart = strrpos($sourcePath, '/');
    if ($substrStart !== FALSE) {
        $substrStart++;
    }
    $pageName = substr($sourcePath, $substrStart);
    if (isset($_GET['source_url']) && $_GET['source_url'] == $pageName) {
        $sourcePageSelectHTML .= "<option value = \"$pageName\" selected>$pageName</option>";
    } else {
        $sourcePageSelectHTML .= "<option value = \"$pageName\">$pageName</option>";
    }
}

// SOURCE SCRIPT SELECT
$sourceScriptSelectHTML = '';
$scriptCount = 0;
$allSourceScriptsQuery = "SELECT DISTINCT source_script FROM event_log ORDER BY source_script ASC";
foreach ($DBH->query($allSourceScriptsQuery) as $eachScript) {
    $scriptCount++;
    $sourcePath = rtrim($eachScript['source_script'], '/');
    $substrStart = strrpos($sourcePath, '/');
    if ($substrStart !== FALSE) {
        $substrStart++;
    }
    $scriptName = substr($sourcePath, $substrStart);
    if (isset($_GET['source_script']) && $_GET['source_script'] == $scriptName) {
        $sourceScriptSelectHTML .= "<option value = \"$scriptName\" selected>$scriptName</option>";
    } else {
        $sourceScriptSelectHTML .= "<option value = \"$scriptName\">$scriptName</option>";
    }
}

//SOURCE FUNCTION SELECT
$sourceFunctionSelectHTML = '';
$functionCount = 0;
$allSourceFunctionsQuery = "SELECT DISTINCT source_function FROM event_log ORDER BY source_function ASC";
foreach ($DBH->query($allSourceFunctionsQuery) as $eachFunction) {
    $functionCount ++;
    $function = $eachFunction['source_function'];
    if (isset($_GET['source_function']) && $_GET['source_function'] == $function) {
        $sourceFunctionSelectHTML .= "<option value = \"$function\" selected>$function</option>";
    } else {
        $sourceFunctionSelectHTML .= "<option value = \"$function\">$function</option>";
    }
}

// EVENT READ RADIO BUTTONS
$readFilterRadioHTML = <<<EOL
    <input id="unreadRadioButton" type="radio" name="event_ack" value="0">
    <label class="clickableButton" for="unreadRadioButton">Unread</label>
    <input id="readRadioButton"  type="radio" name="event_ack" value="1">
    <label class="clickableButton" for="readRadioButton">Read</label>
EOL;
if (isset($_GET['event_ack'])) {
    switch ($_GET['event_ack']) {
        case 0:
            $readFilterRadioHTML = str_replace('0">', '0" checked>', $readFilterRadioHTML);
            break;
        case 1:
            $readFilterRadioHTML = str_replace('1">', '1" checked>', $readFilterRadioHTML);
            break;
    }
}

// EVENT CLOSED RADIO BUTTONS
$closedFilterRadioHTML = <<<EOL
    <input id="openRadioButton" type="radio" name="event_closed" value="0">
    <label class="clickableButton" for="openRadioButton">Open</label>
    <input id="closedRadioButton" type="radio" name="event_closed" value="1">
    <label class="clickableButton" for="closedRadioButton">Closed</label>
EOL;
if (isset($_GET['event_closed'])) {
    switch ($_GET['event_closed']) {
        case 0:
            $closedFilterRadioHTML = str_replace('0">', '0" checked>', $closedFilterRadioHTML);
            break;
        case 1:
            $closedFilterRadioHTML = str_replace('1">', '1" checked>', $closedFilterRadioHTML);
            break;
    }
}

// SORT BY COLUMN SELECT
$sortByColumnSelectHTML = <<<EOL
    <option value="event_time" selected>Event Time</option>
    <option value="id">Event ID</option>
    <option value="event_type">Event Type</option>
    <option value="masked_email">User</option>
    <option value="source_url">Source Page</option>
    <option value="source_script">Source Script</option>
    <option value="source_function">Source Function</option>
    <option value="event_code">Project or Error</option>
    <option value="event_ack">Read / Unread</option>
    <option value="event_closed">Open / Closed</option>
EOL;
if (isset($_GET['sort_by_column'])) {
    switch ($_GET['sort_by_column']) {
        case 'user_id':
            $sortByColumnSelectHTML = str_replace('user_id">', 'user_id" selected>', $sortByColumnSelectHTML);
            break;
        case 'event_type':
            $sortByColumnSelectHTML = str_replace('event_type">', 'event_type" selected>', $sortByColumnSelectHTML);
            break;
        case 'source_url':
            $sortByColumnSelectHTML = str_replace('source_url">', 'source_url" selected>', $sortByColumnSelectHTML);
            break;
        case 'source_script':
            $sortByColumnSelectHTML = str_replace('source_script">', 'source_script" selected>', $sortByColumnSelectHTML);
            break;
        case 'source_function':
            $sortByColumnSelectHTML = str_replace('source_function">', 'source_function" selected>', $sortByColumnSelectHTML);
            break;
        case 'event_code':
            $sortByColumnSelectHTML = str_replace('event_code">', 'event_code" selected>', $sortByColumnSelectHTML);
            break;
        case 'event_ack':
            $sortByColumnSelectHTML = str_replace('read">', 'read" selected>', $sortByColumnSelectHTML);
            break;
        case 'event_closed':
            $sortByColumnSelectHTML = str_replace('closed">', 'closed" selected>', $sortByColumnSelectHTML);
            break;
        case 'id':
            $sortByColumnSelectHTML = str_replace('id">', 'id" selected>', $sortByColumnSelectHTML);
            break;
        case 'event_time':
            $sortByColumnSelectHTML = str_replace('event_time">', 'event_time" selected>', $sortByColumnSelectHTML);
            break;
    }
}

// SORT DIRECTION RADIO BUTTON
$sortDirectionFilterRadioHTML = <<<EOL
    <input id="ascSortRadioButton" type="radio" name="sort_direction" value="asc">
    <label class="clickableButton" for="ascSortRadioButton">Asc</label>
    <input id="descSortRadioButton" type="radio" name="sort_direction" value="desc" checked>
    <label class="clickableButton" for="descSortRadioButton">Desc</label>
EOL;
if (isset($_GET['sort_direction'])) {
    switch ($_GET['sort_direction']) {
        case 'asc':
            $sortDirectionFilterRadioHTML = str_replace('value="desc" checked>', 'value="desc">', $sortDirectionFilterRadioHTML);
            $sortDirectionFilterRadioHTML = str_replace('value="asc">', 'value="asc" checked>', $sortDirectionFilterRadioHTML);
            break;
    }
}

$rowsPerPageSelectHTML = <<<EOL
    <option value="10">10 Results Per Page</option>
    <option value="20">20 Results Per Page</option>
    <option value="30">30 Results Per Page</option>
    <option value="50">50 Results Per Page</option>
    <option value="100">100 Results Per Page</option>
EOL;
if (isset($_GET['rows_to_display'])) {
    switch ($_GET['rows_to_display']) {
        case 20:
            $rowsPerPageSelectHTML = str_replace('20">', '20" selected>', $rowsPerPageSelectHTML);
            break;
        case 30:
            $rowsPerPageSelectHTML = str_replace('30">', '30" selected>', $rowsPerPageSelectHTML);
            break;
        case 50:
            $rowsPerPageSelectHTML = str_replace('50">', '50" selected>', $rowsPerPageSelectHTML);
            break;
        case 100:
            $rowsPerPageSelectHTML = str_replace('100">', '100" selected>', $rowsPerPageSelectHTML);
            break;
        case 10:
        default:
            $rowsPerPageSelectHTML = str_replace('10">', '10" selected>', $rowsPerPageSelectHTML);
            break;
    }
}

// END CODE PAGE PHP
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


require("includes/feedback.php");
require("includes/templateCode.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <meta name="description" content=" “USGS iCoast - Did the Coast Change?” is a USGS research project to
              construct and deploy a citizen science web application that asks volunteers to compare pre- and
              post-storm aerial photographs and identify coastal changes using predefined tags. This
              crowdsourced data will help USGS improve predictive models of coastal change and educate the
              public about coastal vulnerability to extreme storms.">
        <meta name="author" content="Snell, Poore, Liu">
        <meta name="keywords" content="USGS iCoast, iCoast, Department of the Interior, USGS, hurricane, , hurricanes,
              extreme weather, coastal flooding, coast, beach, flood, floods, erosion, inundation, overwash,
              marine science, dune, photographs, aerial photographs, prediction, predictions, coastal change,
              coastal change hazards, hurricane sandy, beaches">
        <meta name="publisher" content="U.S. Geological Survey">
        <meta name="created" content="20140328">
        <meta name="review" content="20140328">
        <meta name="expires" content="Never">
        <meta name="language" content="EN">
        <link rel="stylesheet" href="css/icoast.css">
        <link rel="stylesheet" href="http://www.usgs.gov/styles/common.css" />
        <link rel="stylesheet" href="css/custom.css">
        <link rel="stylesheet" href="css/tipTip.css">
        <?php print $cssLinks; ?>
        <style>
<?php
print $feedbackEmbeddedCSS . "\n\r";
print $embeddedCSS;
?>
        </style>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <script src="scripts/tipTip.js"></script>
        <?php print $javaScriptLinks; ?>
        <script>
<?php
print $feedbackJavascript . "\n\r";
print $javaScript . "\n\r";
?>

            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            // JAVASCRIPT
            // Query Criteria

            var postVariables = {
<?php print $jsPostSettings; ?>
            }
            var displayedRows = 0;
            var resultCount;
            var displayedEventId;
            var displayedEventIndex;
            var cachedAjaxResult = [];
            var jsTableSortDirection;
            var ajaxData = {};
            var filtersApplied = false;

            function runAjaxEventQuery() {
                if (typeof postVariables.project_id !== "undefined") {
                    ajaxData.project_id = postVariables.project_id;
                    filtersApplied = true;
                }

                if (typeof postVariables.event_type !== "undefined") {
                    ajaxData.event_type = postVariables.event_type;
                    filtersApplied = true;
                }

                if (typeof postVariables.user_id !== "undefined") {
                    ajaxData.user_id = postVariables.user_id;
                    filtersApplied = true;
                }

                if (typeof postVariables.source_url !== "undefined") {
                    ajaxData.source_url = postVariables.source_url;
                    filtersApplied = true;
                }
                if (typeof postVariables.source_script !== "undefined") {
                    ajaxData.source_script = postVariables.source_script;
                    filtersApplied = true;
                }
                if (typeof postVariables.source_function !== "undefined") {
                    ajaxData.source_function = postVariables.source_function;
                    filtersApplied = true;
                }

                if (typeof postVariables.event_ack !== "undefined") {
                    ajaxData.event_ack = postVariables.event_ack;
                    filtersApplied = true;
                }

                if (typeof postVariables.event_closed !== "undefined") {
                    ajaxData.event_closed = postVariables.event_closed;
                    filtersApplied = true;
                }

                if (typeof postVariables.sort_by_column !== "undefined") {
                    ajaxData.sort_by_column = postVariables.sort_by_column;
                    jsTableSortBy = postVariables.sort_by_column;
                } else {
                    jsTableSortBy = 'event_time';
                }

                if (typeof postVariables.sort_direction !== "undefined") {
                    ajaxData.sort_direction = postVariables.sort_direction;
                    jsTableSortDirection = postVariables.sort_direction;
                } else {
                    jsTableSortDirection = 'desc';
                }

                if ('start_row' in ajaxData) {
                    if (ajaxData.start_row < 0) {
                        ajaxData.start_row = 0;
                    }
                    console.log(ajaxData.start_row);
                } else if (typeof postVariables.start_row !== "undefined") {
                    ajaxData.start_row = postVariables.start_row;
                } else {
                    ajaxData.start_row = 0;
                }

                if ('rows_to_display' in ajaxData) {
                    console.log(ajaxData.rows_to_display);
                } else if (typeof postVariables.rows_to_display !== "undefined") {
                    ajaxData.rows_to_display = postVariables.rows_to_display;
                } else {
                    ajaxData.rows_to_display = 10;
                }

                console.log(ajaxData);
                $.post('ajax/eventLogViewer.php', ajaxData, processEvents, 'json');
            }

            function processEvents(ajaxResult) {
                if ('errors' in ajaxResult) {
                    var errorHtml = '';
                    $.each(ajaxResult.errors, function(key, error) {
                        errorHtml += '<p class="error">' + error + '</p>';
                    });
                    $('#eventLogErrorWrapper').html(errorHtml);
                    $('#eventLogWrapper').hide();
                    $('#eventLogErrorWrapper').show();
                } else {
                    resultCount = parseFloat(ajaxResult.controlData.resultCount);
                    delete ajaxResult.controlData;
                    cachedAjaxResult = [];
                    displayedRows = 0;
                    $.each(ajaxResult, function(index, event) {
                        cachedAjaxResult.push(event);
                        displayedRows++;
                    });
                }
                displayEvents();
            }

            function displayEvents() {
                $('.headContent img').remove();
                var arrowHTML = '<img class="sortArrow" \n\
        src="images/system/' + jsTableSortDirection + '.png" height="8" width="14" \n\
        alt="image of an arrow indicating the direction of sort on a column" />';
                switch (jsTableSortBy) {
                    case 'id':
                        $('#eventIdHeader .headContent').append(arrowHTML);
                        break;
                    case 'event_time':
                        $('#eventTimeHeader .headContent').append(arrowHTML);
                        break;
                    case 'event_type':
                        $('#eventTypeHeader .headContent').append(arrowHTML);
                        break;
                    case 'masked_email':
                        $('#eventUserHeader .headContent').append(arrowHTML);
                        break;
                    case 'source_url':
                        $('#eventUrlHeader .headContent').append(arrowHTML);
                        break;
                    case 'event_summary':
                        $('#eventSummaryHeader .headContent').append(arrowHTML);
                        break;
                    case 'event_code':
                        $('#eventCodeHeader .headContent').append(arrowHTML);
                        break;
                }
                $('tbody tr').remove();
                $.each(cachedAjaxResult, function(cachedIndex, cachedEvent) {
                    tableContents = '<tr id="eventRow' + cachedEvent.id + '"';
                    if (cachedEvent.event_ack === "0") {
                        tableContents += ' class="unreadEvent"';
                    }
                    if (cachedEvent.event_closed === "1") {
                        tableContents += ' class="closedEvent"';
                    }
                    tableContents += '>';
                    tableContents += '<td class="eventIdColumn">' + cachedEvent.id + '</td>';
                    tableContents += "<td>" + cachedEvent.short_time + "</td>";
                    tableContents += "<td>" + cachedEvent.event_type + "</td>";
                    tableContents += '<td title="' + cachedEvent.masked_email + '">' + cachedEvent.masked_email + '</td>';
                    tableContents += '<td title="' + cachedEvent.page_name + '">' + cachedEvent.page_name + '</td>';
                    tableContents += "<td>" + cachedEvent.event_summary + "</td>";
                    tableContents += "<td>" + cachedEvent.event_code + "</td>";
                    tableContents += "</tr>";
                    $('tbody').append(tableContents);
                    $('#eventRow' + cachedEvent.id).css('cursor', 'pointer');
                    $('#eventRow' + cachedEvent.id).click(function() {
                        displayedEventId = cachedEvent.id;
                        displayedEventIndex = cachedIndex;
                        $('tr').css('background-color', '#FFFFFF');
                        $(this).css('background-color', '#D3E2F0');
                        $('#eventId').html(displayedEventId);
                        $('#eventTimeValue').html('<p>' + cachedEvent.long_time + '</p>');
                        $('#eventTypeValue').html('<p>' + cachedEvent.event_type + '</p>');
                        $('#eventUserValue').html('<p>' + cachedEvent.masked_email + '</p>');
                        $('#eventUrlValue').html('<p>' + cachedEvent.source_url + '</p>');
                        $('#eventQueryStringValue').html('<p>' + cachedEvent.query_string + '</p>');
                        $('#eventPostValue').html('<p>' + cachedEvent.post_data + '</p>');
                        $('#eventScriptValue').html('<p>' + cachedEvent.source_script + '</p>');
                        $('#eventFunctionValue').html('<p>' + cachedEvent.source_function + '</p>');
                        $('#eventClientValue').html('<p>' + cachedEvent.client_agent + '</p>');
                        $('#eventSummaryValue').html('<p>' + cachedEvent.event_summary + '</p>');
                        $('#eventCodeValue').html('<p>' + cachedEvent.event_code + '</p>');
                        $('#eventDetailsValue').html('<p>' + cachedEvent.event_text + '</p>');
                        $('#noEventSelectedText').hide();
                        $('#eventDetailsContent').show();
                        moveFooter();
                        if (cachedAjaxResult[displayedEventIndex]['event_ack'] == "0") {
                            markRead();
                        } else {
                            $('#toggleReadButton').attr('value', 'Mark as Unread');
                        }// END event unread

                        if (cachedAjaxResult[displayedEventIndex]['event_closed'] == "1") {
                            $('#toggleClosedButton').attr('value', 'Re-open Event');
                            $('#toggleReadButton').addClass('disabledClickableButton');
                            $('#eventStatus').html('<span id="closedEventText">Closed</span>');
                        } else {
                            $('#toggleClosedButton').attr('value', 'Close Event');
                            $('#toggleReadButton').removeClass('disabledClickableButton');
                            $('#eventStatus').html('<span id="openEventText">Open</span>');
                        }// END cachedEvent.event_ack - "0"
                    }); // END Event Row Click
                }); // END each ajaxResult

                $('#resultSizeSelect').removeClass('disabledClickableButton').removeAttr('disabled');
                if (displayedRows + ajaxData.start_row < resultCount) {
                    $('#nextPageButton, #lastPageButton').removeClass('disabledClickableButton');
                } else {
                    $('#nextPageButton, #lastPageButton').addClass('disabledClickableButton');
                }

                if (ajaxData.start_row >= 10) {
                    $('#previousPageButton, #firstPageButton').removeClass('disabledClickableButton');
                } else {
                    $('#previousPageButton, #firstPageButton').addClass('disabledClickableButton');
                }

                var topRow = ajaxData.start_row + 1;
                if ((parseInt(ajaxData.start_row) + parseInt(ajaxData.rows_to_display)) < resultCount) {
                    var bottomRow = parseInt(ajaxData.start_row) + parseInt(ajaxData.rows_to_display);
                } else {
                    var bottomRow = resultCount;
                }
                var totalRows = bottomRow - ajaxData.start_row;
                var totalPages = Math.ceil(resultCount / ajaxData.rows_to_display);
                var currentPage = Math.ceil(topRow / ajaxData.rows_to_display);
                $('#eventSummaryWrapper p').remove();
                $('#eventSummaryWrapper').append('<p>Page ' + currentPage + ' of ' + totalPages +
                        '. Displaying rows ' + topRow + ' - ' + bottomRow +
                        ' of ' + resultCount + ' total results (' + totalRows + ' rows shown)</p>');
                moveFooter();
            } //END function displayEvents

            function markRead() {
                var ajaxData = {
                    action: 'markRead',
                    event_id: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkReadResult) {
                    if (ajaxMarkReadResult == 1) {
                        $('#eventRow' + displayedEventId).removeClass('unreadEvent');
                        $('#toggleReadButton').attr('value', 'Mark as Unread');
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 1;
                    }
                }, 'json');
            }

            function markUnread() {
                var ajaxData = {
                    action: 'markUnread',
                    event_id: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkUnreadResult) {
                    if (ajaxMarkUnreadResult == 1) {
                        $('#eventRow' + displayedEventId).addClass('unreadEvent');
                        $('#toggleReadButton').attr('value', 'Mark as Read');
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 0;
                    }
                }, 'json');
            }

            function markClosed() {
                var ajaxData = {
                    action: 'markClosed',
                    event_id: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkReadResult) {
                    if (ajaxMarkReadResult == 1) {
                        $('#eventRow' + displayedEventId).addClass('closedEvent');
                        $('#eventRow' + displayedEventId).removeClass('unreadEvent')
                        $('#eventStatus').html('<span id="closedEventText">Closed</span>');
                        $('#toggleClosedButton').attr('value', 'Re-open Event');
                        $('#toggleReadButton').attr('value', 'Mark as Unread');
                        $('#toggleReadButton').addClass('disabledClickableButton');
                        cachedAjaxResult[displayedEventIndex]['event_closed'] = 1;
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 1;
                    } else {
                        console.log('Error!');
                    }
                }, 'json');
            }

            function markOpen() {
                var ajaxData = {
                    action: 'markOpen',
                    event_id: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkUnreadResult) {
                    if (ajaxMarkUnreadResult == 1) {
                        $('#eventRow' + displayedEventId).removeClass('closedEvent');
                        $('#eventRow' + displayedEventId).removeClass('unreadEvent')
                        $('#eventStatus').html('<span id="openEventText">Open</span>');
                        $('#toggleClosedButton').attr('value', 'Close Event');
                        $('#toggleReadButton').attr('value', 'Mark as Unread');
                        $('#toggleReadButton').removeClass('disabledClickableButton');
                        cachedAjaxResult[displayedEventIndex]['event_closed'] = 0;
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 1;
                    } else {
                        console.log('Error!');
                    }
                }, 'json');
            }

            function numericSort(sortField) {
                if (jsTableSortBy != sortField) {
                    jsTableSortDirection = "Desc";
                }
                if (jsTableSortDirection == "Asc") {
                    cachedAjaxResult.sort(function(a, b) {
                        return b[sortField] - a[sortField];
                    });
                    jsTableSortDirection = "Desc";
                } else {
                    cachedAjaxResult.sort(function(a, b) {
                        return a[sortField] - b[sortField];
                    });
                    jsTableSortDirection = "Asc";
                }

                jsTableSortBy = sortField;
                displayEvents(cachedAjaxResult);
            }

            function alphanumericSort(sortField) {
                if (jsTableSortBy != sortField) {
                    jsTableSortDirection = "Desc";
                }
                if (jsTableSortDirection == "Asc") {
                    cachedAjaxResult.sort(function(a, b) {
                        if (a[sortField] < b[sortField]) {
                            return 1;
                        }
                        if (a[sortField] > b[sortField]) {
                            return -1;
                        }
                        return 0;
                    });
                    jsTableSortDirection = "Desc";
                } else {
                    cachedAjaxResult.sort(function(a, b) {
                        if (a[sortField] < b[sortField]) {
                            return -1;
                        }
                        if (a[sortField] > b[sortField]) {
                            return 1;
                        }
                        return 0;
                    });
                    jsTableSortDirection = "Asc";
                }
                jsTableSortBy = sortField;
                displayEvents(cachedAjaxResult);
            }

            function sizeSelectBoxes() {
                $individualFilterWrapperWidth = $('.individualFilterWrapper').width();
                $filterSelectBoxSize = $individualFilterWrapperWidth - 128;
                $('.filterSelectBox').width($filterSelectBoxSize);
            }






            // END JAVASCRIPT
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////


            $(document).ready(function() {
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                // JAVASCRIPT DOCUMENT.READY

                // Initially size the select boxes to fit the colum width.
                // Then set to size select boxes on window resize
                // Finally reset all unused select boxes to blank selection.
                sizeSelectBoxes();
                $(window).resize(function() {
                    sizeSelectBoxes();
                });
                $('.filterSelectBox').each(function() {
                    var selectedOption = false;
                    $(this).children('option').each(function() {
                        var attr = $(this).attr('selected');
                        if (typeof attr !== 'undefined' && attr !== false) {
                            selectedOption = true;
                        }
                    });
                    if (!selectedOption) {
                        $(this).prop('selectedIndex', -1);
                    } else {
                        $(this).parents('.individualFilterWrapper').find('p:first-of-type').html('-');
                        $(this).parents('.individualFilterWrapper').css({'padding-bottom': '2px'});
                        $(this).parent().find('.clearIndividualFilterButton').removeClass('disabledClickableButton');
                        $(this).parent().show();
                    }
                });
                // SET ALL FILTERED OPTIONS TO BE REVEALED
                $('.individualFilterWrapper').each(function() {
                    var checkedButton = false;
                    $(this).find('input').each(function() {
                        var attr = $(this).attr('checked');
                        if (typeof attr !== 'undefined' && attr !== false) {
                            checkedButton = true;
                        }
                    });
                    if (checkedButton) {
                        $(this).children('p:first-of-type').html('-');
                        $(this).css({'padding-bottom': '2px'});
                        $(this).find('.clearIndividualFilterButton').removeClass('disabledClickableButton');
                        $(this).children('.individualFilterControlWrapper').show();
                    }
                });
                // Expand and shrink individual filter divs using titles.
                $('.individualFilterWrapper p').each(function() {
                    $(this).click(function() {
                        if ($(this).parent().find('p:first-of-type').html() == '+') {
                            $(this).parent().find('p:first-of-type').html('-');
                            $(this).parent().animate({'padding-bottom': '2px'});
                        } else {
                            $(this).parent().find('p:first-of-type').html('+');
                            $(this).parent().animate({'padding-bottom': '10px'});
                        }
                        $(this).parent().find('.individualFilterControlWrapper').slideToggle();
                    });
                });
                // Enable the individual filter clear button if a filter button or select box is selected for that filter.
                $('.individualFilterWrapper label').each(function() {
                    $(this).click(function() {
                        $(this).parent().find('.clearIndividualFilterButton').removeClass('disabledClickableButton');
                    });
                });
                $('.individualFilterWrapper select').on('change', function() {
                    $(this).siblings('.clearIndividualFilterButton').removeClass('disabledClickableButton');
                });
                // Clear individual filter options when clear button is clicked, then disable the clear button.
                $('.clearIndividualFilterButton').each(function() {
                    $(this).click(function() {
                        $(this).parent().find('input:checked').each(function() {
                            $(this).removeAttr("checked");
                        });
                        $(this).parent().find('option:selected').each(function() {
                            $(this).removeAttr("selected");
                            $(this).parent().prop('selectedIndex', -1);
                        });
                        $(this).addClass("disabledClickableButton");
                    });
                });
                // Populate the event summary table
                runAjaxEventQuery();
                // Event Summary table display controls
                // Sort functionality on table header click
                $('#eventIdHeader').click(function() {
                    numericSort('id');
                });
                $('#eventTimeHeader').click(function() {
                    numericSort('event_time');
                });
                $('#eventTypeHeader').click(function() {
                    alphanumericSort('event_type');
                });
                $('#eventUserHeader').click(function() {
                    alphanumericSort('masked_email');
                });
                $('#eventUrlHeader').click(function() {
                    alphanumericSort('source_url');
                });
                $('#eventSummaryHeader').click(function() {
                    alphanumericSort('event_summary');
                });
                $('#eventCodeHeader').click(function() {
                    alphanumericSort('event_code');
                });
                // Reset the number of results select box
                if (!'rows_to_display' in ajaxData) {
                    console.log("dfsafda");
                    $('#resultSizeSelect').prop('selectedIndex', 0);
                }
                // Trigger redisplay of table if result set size is altered.
                $('#resultSizeSelect').on('change', function() {
                    ajaxData.rows_to_display = $(this).val();
                    $('#rowsToDisplayFormData').remove();
                    var rowFormData = '<input type="hidden" id="rowsToDisplayFormData" name="rows_to_display" value="' + ajaxData.rows_to_display + '">';
                    $('#filterForm').append(rowFormData);
                    runAjaxEventQuery();
                });
                // Navigate the result set int he Event Summary Table
                $('#firstPageButton').click(function() {
                    if (!$('#firstPageButton').hasClass('disabledClickableButton')) {
                        ajaxData.start_row = 0;
                        runAjaxEventQuery();
                    }
                });
                $('#previousPageButton').click(function() {
                    if (!$('#previousPageButton').hasClass('disabledClickableButton')) {
                        ajaxData.start_row -= parseInt(ajaxData.rows_to_display);
                        runAjaxEventQuery();
                    }

                });
                $('#nextPageButton').click(function() {
                    if (!$('#nextPageButton').hasClass('disabledClickableButton')) {
                        ajaxData.start_row += parseInt(ajaxData.rows_to_display);
                        runAjaxEventQuery();
                    }
                });
                $('#lastPageButton').click(function() {
                    if (!$('#lastPageButton').hasClass('disabledClickableButton')) {
                        ajaxData.start_row = (Math.floor((resultCount - 1) / ajaxData.rows_to_display) * ajaxData.rows_to_display);
                        runAjaxEventQuery();
                    }
                });
                // Event Details table controls
                // Provide click functinality to the Event Details Buttons
                $('#toggleReadButton').click(function() {
                    if (cachedAjaxResult[displayedEventIndex]['event_closed'] == 0) {
                        if (cachedAjaxResult[displayedEventIndex]['event_ack'] == 0) {
                            markRead();
                        } else {
                            markUnread();
                        }
                    }
                });
                $('#toggleClosedButton').click(function() {
                    if (cachedAjaxResult[displayedEventIndex]['event_closed'] == 0) {
                        markClosed();
                    } else {
                        markOpen();
                    }
                });

                if (filtersApplied) {
                    $('#filterStatusMessage p').html('Filters <span class="redHighlight">are</span> applied the Event Log results show below.');
                }

                $('#filterDisplayToggle').click(function() {
                    if ($(this).text() == 'Show Filter Control Panel') {
                        $(this).text('Hide Filter Control Panel')
                    } else {
                        $(this).text('Show Filter Control Panel')
                    }
                    $('#eventLogFilterWrapper').slideToggle(function() {
                        moveFooter();
                        sizeSelectBoxes();
                    });

                });

                $('#clearAllFilters').click(function() {
                    $('.eventLogFilterColumn').find('.clearIndividualFilterButton').each(function() {
                        $(this).trigger("click");
                    });
                });
                // END JAVASCRIPT DOCUMENT.READY
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            });
<?php
print $feedbackjQueryDocumentDotReadyCode . "\n\r";
print $jQueryDocumentDotReadyCode . "\n\r";
?>
            (function(i, s, o, g, r, a, m) {
                i['GoogleAnalyticsObject'] = r;
                i[r] = i[r] || function() {
                    (i[r].q = i[r].q || []).push(arguments)
                }, i[r].l = 1 * new Date();
                a = s.createElement(o),
                        m = s.getElementsByTagName(o)[0];
                a.async = 1;
                a.src = g;
                m.parentNode.insertBefore(a, m)
            }
            )(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');
            ga('create', 'UA-49706884-1', 'icoast.us');
            ga('require', 'displayfeatures');
            ga('send', 'pageview');

        </script>
    </head>
    <body>
        <!--Header-->
        <?php require('includes/header.txt') ?>

        <!--Page Body-->
        <a href="#skipmenu" title="Skip this menu"></a>
        <div id='navigationBar'>
            <?php print $mainNav ?>
        </div>
        <a id="skipmenu"></a>

        <!--//////////////////////////////////////////////////////////////////////////////////////////////////////////
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // PAGE HTML CODE -->






        <!--END PAGE HTML CODE
        //////////////////////////////////////////////////////////////////////////////////////////////////////////-->


        <?php
        print $feedbackPageHTML;
        require('includes/footer.txt');
        ?>

        <div id="alertBoxWrapper">
            <div id="alertBoxCenteringWrapper">
                <div id="alertBox">
                    <?php print $alertBoxContent; ?>
                    <div id="alertBoxControls">
                        <?php print $alertBoxDynamicControls; ?>
                        <input type="button" id="closeAlertBox" class="clickableButton" value="Close">
                    </div>
                </div>
            </div>
        </div>

    </body>
</html>


