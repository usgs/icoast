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

if ($adminLevel == 4) {
    $userAdministeredProjects = array();
    $allProjectsQuery = "SELECT project_id, name FROM projects ORDER BY project_id ASC";
    foreach ($DBH->query($allProjectsQuery) as $row) {
        $userAdministeredProjects[] = $row;
    }
} else {
    $userAdministeredProjects = find_administered_projects($DBH, $userId, TRUE);
}
$projectSelectHTML = "";
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $projectId = $singeUserAdministeredProject['project_id'];
    $projectName = $singeUserAdministeredProject['name'];
    if (isset($_POST['project']) && $_POST['project'] == $projectId) {
        $projectSelectHTML .= "<option value=\"$projectId\" selected>$projectName</option>";
    } else {
        $projectSelectHTML .= "<option value=\"$projectId\">$projectName</option>";
    }
}
$projectCount = count($userAdministeredProjects);

$eventTypeSelectHTML = <<<EOL
        <option value="1">System Error</option>
        <option value="2">System Feedback</option>
        <option value="3">Project Feedback</option>
EOL;
if (isset($_POST['eventType'])) {
    switch ($_POST['eventType']) {
        case 1:
            str_replace('1">', '1" selected>', $eventTypeSelectHTML);
            break;
        case 2:
            str_replace('2">', '2" selected>', $eventTypeSelectHTML);
            break;
        case 3:
            str_replace('3">', '3" selected>', $eventTypeSelectHTML);
            break;
    }
}

$userSelectHTML = '';
$allUsersQuery = "SELECT user_id, masked_email FROM users ORDER BY masked_email ASC";
foreach ($DBH->query($allUsersQuery) as $row) {
    $userId = $row['user_id'];
    $userMaskedEmail = $row['masked_email'];
    if (isset($_POST['user']) && $_POST['user'] == $userId) {
        $userSelectHTML .= "<option value = \"$userId\" selected>$userMaskedEmail</option>";
    } else {
        $userSelectHTML .= "<option value = \"$userId\">$userMaskedEmail</option>";
    }
}

$sourcePageSelectHTML = '';
$allSourcePagesQuery = "SELECT DISTINCT source_url FROM event_log ORDER BY source_url ASC";
foreach ($DBH->query($allSourcePagesQuery) as $row) {
    $sourcePath = rtrim($row['source_url'], '/');
    $substrStart = strrpos($sourcePath, '/') + 1;
    $pageName = substr($sourcePath, $substrStart);
    if (isset($_POST['sourceURL']) && $_POST['sourceURL'] == $pageName) {
        $sourcePageSelectHTML .= "<option value = \"$pageName\" selected>$pageName</option>";
    } else {
        $sourcePageSelectHTML .= "<option value = \"$pageName\">$pageName</option>";
    }
}

$sourceScriptSelectHTML = '';
$scriptCount = 0;
$allSourceScriptsQuery = "SELECT DISTINCT source_script FROM event_log ORDER BY source_script ASC";
foreach ($DBH->query($allSourceScriptsQuery) as $row) {
    $scriptCount++;
    $sourcePath = rtrim($row['source_script'], '/');
    $substrStart = strrpos($sourcePath, '/') + 1;
    $scriptName = substr($sourcePath, $substrStart);
    if (isset($_POST['sourceScript']) && $_POST['sourceScript'] == $scriptName) {
        $sourceScriptSelectHTML .= "<option value = \"$scriptName\" selected>$scriptName</option>";
    } else {
        $sourceScriptSelectHTML .= "<option value = \"$scriptName\">$scriptName</option>";
    }
}

$sourceFunctionSelectHTML = '';
$functionCount = 0;
$allSourceFunctionsQuery = "SELECT DISTINCT source_function FROM event_log ORDER BY source_function ASC";
foreach ($DBH->query($allSourceFunctionsQuery) as $row) {
    $function = $row['source_function'];
    if (isset($_POST['sourceFunction']) && $_POST['sourceFunction'] == $function) {
        $sourceFunctionSelectHTML .= "<option value = \"$scriptName\" selected>$scriptName</option>";
    } else {
        $sourceFunctionSelectHTML .= "<option value = \"$scriptName\">$scriptName</option>";
    }
}

$readFilterRadioHTML = <<<EOL
    <input id="unreadRadioButton" type="radio" name="read" value="0">
    <label class="clickableButton" for="unreadRadioButton">Unread</label>
    <input id="readRadioButton"  type="radio" name="read" value="1">
    <label class="clickableButton" for="readRadioButton">Read</label>    
EOL;
if (isset($_POST['read'])) {
    switch ($_POST['read']) {
        case 0:
            str_replace('0">', '0" selected>', $readFilterRadioHTML);
            break;
        case 1:
            str_replace('1">', '1" selected>', $readFilterRadioHTML);
            break;
    }
}

$closedFilterRadioHTML = <<<EOL
    <input id="openRadioButton" type="radio" name="closed" value="0">
    <label class="clickableButton" for="openRadioButton">Open</label>
    <input id="closedRadioButton" type="radio" name="closed" value="1">
    <label class="clickableButton" for="closedRadioButton">Closed</label> 
EOL;
if (isset($_POST['closed'])) {
    switch ($_POST['closed']) {
        case 0:
            str_replace('0">', '0" selected>', $closedFilterRadioHTML);
            break;
        case 1:
            str_replace('1">', '1" selected>', $closedFilterRadioHTML);
            break;
    }
}

$orderBySelectHTML = <<<EOL
    <option value="time" selected>Event Time</option>
    <option value="id">Event ID</option>
    <option value="event_type">Event Type</option>
    <option value="user_id">User</option>
    <option value="source_url">Source Page</option>
    <option value="source_script">Source Script</option>
    <option value="source_function">Source Function</option>
    <option value="event_code">Project or Error</option>
    <option value="read">Read / Unread</option>
    <option value="closed">Open / Closed</option>        
EOL;
if (isset($_POST['orderBy'])) {
    switch ($_POST['orderBy']) {
        case 'user_id':
            str_replace('user_id">', 'user_id" selected>', $orderBySelectHTML);
            break;
        case 'event_type':
            str_replace('event_type">', 'event_type" selected>', $orderBySelectHTML);
            break;
        case 'source_url':
            str_replace('source_url">', 'source_url" selected>', $orderBySelectHTML);
            break;
        case 'source_script':
            str_replace('source_script">', 'source_script" selected>', $orderBySelectHTML);
            break;
        case 'source_function':
            str_replace('source_function">', 'source_function" selected>', $orderBySelectHTML);
            break;
        case 'event_code':
            str_replace('event_code">', 'event_code" selected>', $orderBySelectHTML);
            break;
        case 'read':
            str_replace('read">', 'read" selected>', $orderBySelectHTML);
            break;
        case 'closed':
            str_replace('closed">', 'closed" selected>', $orderBySelectHTML);
            break;
        case 'id':
            str_replace('id">', 'id" selected>', $orderBySelectHTML);
            break;
        case 'time':
            str_replace('time">', 'time" selected>', $orderBySelectHTML);
            break;
    }
}

$sortDirectionFilterRadioHTML = <<<EOL
    <input id="ascSortRadioButton" type="radio" name="sortDirection" value="asc" checked>
    <label class="clickableButton" for="ascSortRadioButton">Asc</label>
    <input id="descSortRadioButton" type="radio" name="sortDirection" value="desc">
    <label class="clickableButton" for="descSortRadioButton">Desc</label>
EOL;
if (isset($_POST['sortDirection'])) {
    switch ($_POST['sortDirection']) {
        case 'desc':
            str_replace('value="asc" checked>', 'value="asc">', $sortDirectionFilterRadioHTML);
            str_replace('value="desc">', 'value="desc" checked>', $sortDirectionFilterRadioHTML);
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
        <?php
        print $cssLinks;
        ?>
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
<?php if (isset($_POST['project'])) { ?>
                var jsProject = <?php print $_POST['project']; ?>;
<?php } else { ?>
                var jsProject;
<?php } ?>

<?php if (isset($_POST ['eventType'])) { ?>
                var jsEventType = <?php print $_POST ['eventType']; ?>;
<?php } else { ?>
                var jsEventType;
<?php } ?>


<?php if (isset($_POST['user'])) { ?>
                var jsUserId = <?php print $_POST['user']; ?>;
<?php } else { ?>
                var jsUserId;
<?php } ?>


<?php if (isset($_POST['sourceURL'])) { ?>
                var jsSourceURL = '<?php print $_POST['sourceURL']; ?>';
<?php } else { ?>
                var jsSourceURL;
<?php } ?>


<?php if (isset($_POST['sourceScript'])) { ?>
                var jsSourceScript = '<?php print $_POST['sourceScript']; ?>';
<?php } else { ?>
                var jsSourceScript;
<?php } ?>


<?php if (isset($_POST['sourceFunction'])) { ?>
                var jsSourceFunction = '<?php print $_POST['sourceFunction']; ?>';
<?php } else { ?>
                var jsSourceFunction;
<?php } ?>


<?php if (isset($_POST['read'])) { ?>
                var jsRead = <?php print $_POST['read']; ?>;
<?php } else { ?>
                var jsRead;
<?php } ?>


<?php if (isset($_POST['closed'])) { ?>
                var jsClosed = <?php print $_POST['closed']; ?>;
<?php } else { ?>
                var jsClosed = 0;
<?php } ?>


<?php if (isset($_POST['orderBy'])) { ?>
                var jsOrderBy = '<?php print $_POST['orderBy']; ?>';
                var jsTableOrderBy = '<?php print $_POST['orderBy']; ?>';
<?php } else { ?>
                var jsOrderBy = 'time';
                var jsTableOrderBy = 'time';
<?php } ?>


<?php if (isset($_POST['sortDirection'])) { ?>
                var jsSortDirection = '<?php print $_POST['sortDirection']; ?>';
<?php } else { ?>
                var jsSortDirection = 'Desc';
<?php } ?>


<?php if (isset($_POST['startRow'])) { ?>
                var jsStartRow = <?php print $_POST['startRow']; ?>;
<?php } else { ?>
                var jsStartRow = 0;
<?php } ?>


<?php if (isset($_POST['resultSize'])) { ?>
                var jsResultSize = <?php print $_POST['resultSize']; ?>;
<?php } else { ?>
                var jsResultSize = 10;
<?php } ?>

            var displayedRows = 0;
            var resultCount;
            var displayedEventId;
            var displayedEventIndex;
            var cachedAjaxResult = [];



            function runAjaxEventQuery() {

                var ajaxData = {};

                if (typeof jsProject !== "undefined") {
                    ajaxData.project = jsProject;
                }

                if (typeof jsEventType !== "undefined") {
                    ajaxData.eventType = jsEventType;
                }

                if (typeof jsUserId !== "undefined") {
                    ajaxData.userId = jsUserId;
                }

                if (typeof jsSourceURL !== "undefined") {
                    ajaxData.sourceURL = jsSourceURL;
                }
                if (typeof jsSourceScript !== "undefined") {
                    ajaxData.sourceScript = jsSourceScript;
                }
                if (typeof jsSourceFunction !== "undefined") {
                    ajaxData.sourceFunction = jsSourceFunction;
                }

                if (typeof jsRead !== "undefined") {
                    ajaxData.read = jsRead;
                }

                if (typeof jsClosed !== "undefined") {
                    ajaxData.closed = jsClosed;
                }

                if (typeof jsOrderBy !== "undefined") {
                    ajaxData.orderBy = jsOrderBy;
                }

                if (typeof jsSortDirection !== "undefined") {
                    ajaxData.sortDirection = jsSortDirection;
                }

                if (typeof jsStartRow !== "undefined") {
                    ajaxData.startRow = jsStartRow;
                }

                if (typeof jsResultSize !== "undefined") {
                    ajaxData.resultSize = jsResultSize;
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
                        console.log("Ahhh, push it!");
                        cachedAjaxResult.push(event);
                        //                        eventReadRecord[event.id] = event.event_ack;
                        //                        eventClosedRecord[event.id] = event.event_closed;
                        displayedRows++;
                    });
                }
                displayEvents();
            }

            function displayEvents() {
                $('.headContent img').remove();
                var arrowHTML = '<img class="sortArrow" \n\
        src="images/system/sort' + jsSortDirection + '.png" height="8" width="14" \n\
        alt="image of an upward pointing arrow indicating an ascending sort on the column" />';
                switch (jsTableOrderBy) {
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
                    case 'page_name':
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
                        console.log(displayedEventId);
                        console.log(displayedEventIndex);
                        //                        console.log(eventReadRecord[displayedEventId]);
                        //                        console.log(eventClosedRecord[displayedEventId]);

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

                        if (cachedAjaxResult[displayedEventIndex]['event_ack'] == "0") {
                            console.log("updating read status on open");
                            markRead();
                        }// END event unread

                        if (cachedAjaxResult[displayedEventIndex]['event_closed'] == "1") {
                            console.log('Event is closed.');
                            $('#toggleClosedButton').attr('value', 'Re-open Event');
                            $('#toggleReadButton').addClass('disabledClickableButton');
                            $('#eventStatus').html('<span id="closedEventText">Closed</span>');
                        } else {
                            console.log('Event is open.');
                            $('#toggleClosedButton').attr('value', 'Close Event');
                            $('#toggleReadButton').removeClass('disabledClickableButton');
                            $('#eventStatus').html('<span id="openEventText">Open</span>');
                        }// END cachedEvent.event_ack - "0"
                    }); // END Event Row Click
                }); // END each ajaxResult

                $('#resultSizeSelect').removeClass('disabledClickableButton').removeAttr('disabled');

                if (displayedRows + jsStartRow < resultCount) {
                    $('#nextPageButton, #lastPageButton').removeClass('disabledClickableButton');
                } else {
                    $('#nextPageButton, #lastPageButton').addClass('disabledClickableButton');
                }

                if (jsStartRow >= 10) {
                    $('#previousPageButton, #firstPageButton').removeClass('disabledClickableButton');
                } else {
                    $('#previousPageButton, #firstPageButton').addClass('disabledClickableButton');
                }

                var topRow = jsStartRow + 1;
                if ((parseInt(jsStartRow) + parseInt(jsResultSize)) < resultCount) {
                    var bottomRow = parseInt(jsStartRow) + parseInt(jsResultSize);
                } else {
                    var bottomRow = resultCount;
                }
                var totalRows = bottomRow - jsStartRow;
                var totalPages = Math.ceil(resultCount / jsResultSize);
                var currentPage = Math.ceil(topRow / jsResultSize);

                $('#eventSummaryWrapper p').remove();
                $('#eventSummaryWrapper').append('<p>Page ' + currentPage + ' of ' + totalPages +
                        '. Displaying rows ' + topRow + ' - ' + bottomRow +
                        ' of ' + resultCount + ' total results (' + totalRows + ' rows shown)</p>');


                moveFooter();
                console.log(cachedAjaxResult);
            } //END function displayEvents

            function markRead() {
                console.log('In Mark Read');
                var ajaxData = {
                    action: 'markRead',
                    eventId: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkReadResult) {
                    if (ajaxMarkReadResult == 1) {
                        $('#eventRow' + displayedEventId).removeClass('unreadEvent');
                        $('#toggleReadButton').attr('value', 'Mark as Unread');
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 1;
                    }
                }, 'json');
                console.log('Cached value:' + cachedAjaxResult[displayedEventIndex]['event_ack']);
            }

            function markUnread() {
                console.log('In Mark Unread');
                var ajaxData = {
                    action: 'markUnread',
                    eventId: displayedEventId
                }
                $.post('ajax/eventLogViewer.php', ajaxData, function(ajaxMarkUnreadResult) {
                    if (ajaxMarkUnreadResult == 1) {
                        $('#eventRow' + displayedEventId).addClass('unreadEvent');
                        $('#toggleReadButton').attr('value', 'Mark as Read');
                        cachedAjaxResult[displayedEventIndex]['event_ack'] = 0;
                    }
                }, 'json');
                console.log('Cached value:' + cachedAjaxResult[displayedEventIndex]['event_ack']);
            }

            function markClosed() {
                console.log('In Mark Closed');
                var ajaxData = {
                    action: 'markClosed',
                    eventId: displayedEventId
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
                console.log('Cached value:' + cachedAjaxResult[displayedEventIndex]['event_closed']);
            }

            function markOpen() {
                console.log('In Mark Open');
                var ajaxData = {
                    action: 'markOpen',
                    eventId: displayedEventId
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
                console.log('Cached value:' + cachedAjaxResult[displayedEventIndex]['event_closed']);
            }

            function numericSort(sortField) {
                if (jsTableOrderBy != sortField) {
                    jsSortDirection = "Desc";
                }
                if (jsSortDirection == "Asc") {
                    cachedAjaxResult.sort(function(a, b) {
                        return b[sortField] - a[sortField];
                    });
                    jsSortDirection = "Desc";
                } else {
                    cachedAjaxResult.sort(function(a, b) {
                        return a[sortField] - b[sortField];
                    });
                    jsSortDirection = "Asc";
                }

                jsTableOrderBy = sortField;
                displayEvents(cachedAjaxResult);
            }

            function alphanumericSort(sortField) {
                console.log('In alphaSort: ' + sortField);
                if (jsTableOrderBy != sortField) {
                    console.log('Table order is not equal. Setting current sort to desc');
                    jsSortDirection = "Desc";
                }
                console.log(jsSortDirection);
                if (jsSortDirection == "Asc") {
                    console.log('Is currently Asc sort');
                    cachedAjaxResult.sort(function(a, b) {
                        if (a[sortField] < b[sortField]) {
                            return 1;
                        }
                        if (a[sortField] > b[sortField]) {
                            return -1;
                        }
                        console.log('Returned 0');
                        return 0;
                    });
                    jsSortDirection = "Desc";
                } else {
                    console.log('Is currently Desc sort');
                    cachedAjaxResult.sort(function(a, b) {
                        if (a[sortField] < b[sortField]) {
                            return -1;
                        }
                        if (a[sortField] > b[sortField]) {
                            return 1;
                        }
                        return 0;
                    });
                    jsSortDirection = "Asc";
                }
                jsTableOrderBy = sortField;
                displayEvents(cachedAjaxResult);
            }

            function sizeSelectBoxes() {
                $individualFilterWrapperWidth = $('.individualFilterWrapper').width();
                console.log($individualFilterWrapperWidth);
                $filterSelectBoxSize = $individualFilterWrapperWidth - 128;
                console.log($filterSelectBoxSize);
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
                // Finally reset all select boxes to blank selection.
                sizeSelectBoxes();

                $(window).resize(function() {
                    sizeSelectBoxes();
                });

                $('.filterSelectBox').each(function() {
                    var selectedOption = false;
                    $('this').children('option').each(function() {
                        if ($(this).checked) {
                            selectedOption = true
                        }
                    });
                    if (!selectedOption) {
                        $('this').prop('selectedIndex', -1);
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
                    alphanumericSort('page_name')
                });

                $('#eventSummaryHeader').click(function() {
                    alphanumericSort('event_summary')
                });

                $('#eventCodeHeader').click(function() {
                    alphanumericSort('event_code')
                });

                // Reset the number of results select box
                $('#resultSizeSelect').prop('selectedIndex', 0);

                // Trigger redisplay of table if result set size is altered.
                $('#resultSizeSelect').on('change', function() {
                    jsResultSize = $(this).val();
                    console.log('Result Size: ' + jsResultSize);
                    runAjaxEventQuery();
                });

                // Navigate the result set int he Event Summary Table
                $('#firstPageButton').click(function() {
                    if (!$('#firstPageButton').hasClass('disabledClickableButton')) {
                        jsStartRow = 0;
                        runAjaxEventQuery();
                    }
                });

                $('#previousPageButton').click(function() {
                    if (!$('#previousPageButton').hasClass('disabledClickableButton')) {
                        jsStartRow -= parseInt(jsResultSize);
                        runAjaxEventQuery();
                    }

                });

                $('#nextPageButton').click(function() {
                    if (!$('#nextPageButton').hasClass('disabledClickableButton')) {
                        jsStartRow += parseInt(jsResultSize);
                        runAjaxEventQuery();
                    }
                });

                $('#lastPageButton').click(function() {
                    if (!$('#lastPageButton').hasClass('disabledClickableButton')) {
                        jsStartRow = (Math.floor((resultCount - 1) / jsResultSize) * jsResultSize);
                        runAjaxEventQuery();
                    }
                });

                // Event Details table controls
                // Provide click functinality to the Event Details Buttons
                $('#toggleReadButton').click(function() {
                    console.log('Click!');
                    if (cachedAjaxResult[displayedEventIndex]['event_closed'] == 0) {
                        if (cachedAjaxResult[displayedEventIndex]['event_ack'] == 0) {
                            markRead();
                        } else {
                            markUnread();
                        }
                    }
                });

                $('#toggleClosedButton').click(function() {
                    console.log('Click!');
                    if (cachedAjaxResult[displayedEventIndex]['event_closed'] == 0) {
                        markClosed();
                    } else {
                        markOpen();
                    }
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
        <div id="adminPageWrapper">
            <?php print $adminNavHTML ?>
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData"><?php print $maskedEmail ?></span>. Your admin level is
                        <span class="userData"><?php print $adminLevelText ?></span></p>
                </div>
                <h1>iCoast Event Log Viewer</h1>
                <form autocomplete="off" method="post">
                    <div id="eventLogFilterWrapper">
                        <h2>Event Log Filter Selections</h2>
                        <p>Use the following options to filter the results loaded into the event log below</p>
                        <div class="eventLogFilterColumn">
                            <h3>Event Data</h3>


                            <?php if ($projectCount > 1) { ?>
                                <div id="projectFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Project:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="projectFilterSelect" class="clickableButton filterSelectBox" name="project">
                                            <?php print $projectSelectHTML; ?>
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />
                                    </div>
                                </div>
                                <?php
                            }


                            if ($adminLevel == 4) {
                                ?>
                                <div id="typeFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Event Type:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="eventTypeFilterSelect" class="clickableButton filterSelectBox" name="eventType">
                                            <?php print $eventTypeSelectHTML; ?>
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearEventTypeFilter" type="button" value="Clear" />

                                    </div>
                                </div>
                            <?php } else { ?>
                                <input type="hidden" name="eventType" value="3" />
                            <?php } ?>


                            <div id="userFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>User:</p>
                                <div class="individualFilterControlWrapper">
                                    <select id="userFilterSelect" class="clickableButton filterSelectBox" name="user">
                                        <?php print $userSelectHTML; ?>
                                    </select>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                </div>
                            </div>


                            <div id="urlFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Event Source Page:</p>
                                <div class="individualFilterControlWrapper">
                                    <select id="sourcePageFilterSelect" class="clickableButton filterSelectBox" name="sourceURL">
                                        <?php print $sourcePageSelectHTML ?>
                                    </select>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />
                                </div>
                            </div>     


                            <?php if ($scriptCount > 1) { ?>
                                <div id="scriptFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Event Source Script:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="sourceScript">
                                            <?php print $sourceScriptSelectHTML; ?>
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($functionCount > 1) { ?>    
                                <div id="functionFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Event Source Function:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="sourceScript">
                                            <?php print $sourceFunctionSelectHTML; ?>
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                    </div>
                                </div>
                            <?php } ?>

                        </div>


                        <div class="eventLogFilterColumn">
                            <h3>Event Status</h3>


                            <div id="readFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Read Status:</p>
                                <div class="individualFilterControlWrapper">
                                    <?php print $readFilterRadioHTML; ?>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearReadFilter" type="button" value="Clear" />
                                </div>
                            </div>


                            <div id="closedFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Closed Status:</p>
                                <div class="individualFilterControlWrapper">
                                    <?php print $closedFilterRadioHTML ?>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearClosedFilter" type="button" value="Clear" />
                                </div>
                            </div>
                        </div>


                        <div class="eventLogFilterColumn">
                            <h3>Result Sort</h3>


                            <div id="sortFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Return Results Sorted By:<p>
                                <div class="individualFilterControlWrapper">
                                    <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="orderBy">
                                        <?php print $orderBySelectHTML ?>
                                    </select>
                                </div>
                            </div>


                            <div id="directionFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Sort Direction:</p>
                                <div class="individualFilterControlWrapper">
                                    <?php print $sortDirectionFilterRadioHTML; ?>
                                </div>

                            </div>
                        </div>
                        <div id="applyFilterWrapper">
                            <p>Activate or Clear Filters:</p>
                            <input class="clickableButton" id="clearAllFilters" type="button" value="Clear All Filters" />
                            <input class="clickableButton" id="applyFilter" type="submit" value="Apply Filter" />
                        </div>
                    </div>
                </form>

                <div id="eventLogWrapper">
                    <div id="eventSummaryWrapper">
                        <h2>Events Summary</h2>
                        <div id="eventTableWrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th class="eventIdColumn" id="eventIdHeader" title="The ID of this event in the iCoast system."><div class="headContent">ID</div></th>
                                <th id="eventTimeHeader" title="The time at which the event was logged."><div class="headContent">Event Time</div></th>
                                <th id="eventTypeHeader" title="The type of event that was logged. Error, System Feedback, or Project Feedback">
                                <div class="headContent">Event Type</div></th>
                                <th id="eventUserHeader" title="The user logged in when the event was recorded."><div class="headContent">User</div></th>
                                <th id="eventUrlHeader" title="The page displayed when the feedback was logged.">
                                <div class="headContent">Source Page</div></th>
                                <th id="eventSummaryHeader" title="A summary of the event contents.">
                                <div class="headContent">Summary</div></th>
                                <th id="eventCodeHeader" title="The error code or project name relating to the event."><div class="headContent">Project or Error</div></th>
                                </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                            <!--</div>-->
                            <div id="eventSummaryControls">
                                <input type="button" id="firstPageButton" class="clickableButton disabledClickableButton"
                                       value="<<" title="Use this button to jump to the first page of results.">
                                <input type="button" id="previousPageButton" class="clickableButton disabledClickableButton"
                                       value="<" title="use this button to display the previous page of results.">
                                <select id="resultSizeSelect" class="clickableButton disabledClickableButton"
                                        title="Changing the value in this select box will increase or decrease the number
                                        of rows shown on each page of the table." disabled>
                                    <option value="10">10 Results Per Page</option>
                                    <option value="20">20 Results Per Page</option>
                                    <option value="30">30 Results Per Page</option>
                                    <option value="50">50 Results Per Page</option>
                                    <option value="100">100 Results Per Page</option>
                                </select>
                                <input type="button" id="lastPageButton" class="clickableButton disabledClickableButton"
                                       value=">>" title="Use this button to jump to the last page of results.">
                                <input type="button" id="nextPageButton" class="clickableButton disabledClickableButton"
                                       value=">" title="Use this button to display the next page of results.">
                            </div>
                        </div>
                    </div>

                    <div id="eventDetailsWrapper">
                        <h2>Event Details</h2>
                        <p id="noEventSelectedText">You must select an event from the table on the left to see its details here.</p>
                        <div id="eventDetailsContent">
                            <div id="eventDetailsText">
                                <p id="eventStatusText"></p>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Status:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventStatus"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event ID:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventId"></div>
                                </div>                                

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Time:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventTimeValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Type:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventTypeValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>User:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventUserValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Source URL:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventUrlValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Query String:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventQueryStringValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>POST Data:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventPostValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Source Script:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventScriptValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Source Function:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventFunctionValue">

                                    </div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Client Agent:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventClientValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Summary:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventSummaryValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Code or Project:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventCodeValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Event Details:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventDetailsValue"></div>
                                </div>

                            </div>

                            <div id="eventDetailsControls">
                                <input type="button" id="toggleReadButton" class="clickableButton"
                                       value="Mark as Unread" title="Use this button to toggle the event between read and unread status.">
                                <input type="button" id="toggleClosedButton" class="clickableButton"
                                       value="Mark as Closed" title="Use this button to toggle the event bewtween closed or open status.">
                            </div>
                        </div>



                    </div>
                </div>
                <div id="eventLogErrorWrapper">
                    <p>Error Text</p>
                </div>
            </div>
        </div>





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


