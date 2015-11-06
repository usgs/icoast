<?php

ob_start();
$pageModifiedTime = filemtime(__FILE__);

require('includes/pageCode/eventViewerCode.php');

$pageBody = <<<EOL
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <h1>iCoast Feedback and Error Log Viewer</h1>
                <div id=filterStatusMessage>
                    <h2>Filter Status</h2>
                    <p>Filters are not applied to the results shown below.</p>
                    <button type="button" id="filterDisplayToggle" class="clickableButton">Show Filter Control Panel</button>
                </div>

                <div id="eventLogFilterWrapper">
                    <h2>Filter Selections</h2>
                    <p>Use the following options to filter the results loaded into the summary below</p>
                    <form autocomplete="off" method="get" id="filterForm">

                        <div class="eventLogFilterColumn">
                            <h3>Entry Data</h3>

EOL;

if ($projectCount > 1) {
    $pageBody .= <<<EOL
                                <div id="projectFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Project:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="projectFilterSelect" class="clickableButton filterSelectBox" name="project_id">
                                            $projectSelectHTML
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />
                                    </div>
                                </div>
                                <div id="typeFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Entry Type:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="eventTypeFilterSelect" class="clickableButton filterSelectBox" name="event_type">
                                            $eventTypeSelectHTML
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearEventTypeFilter" type="button" value="Clear" />

                                    </div>
                                </div>

EOL;
}
$pageBody .= <<<EOL


                            <div id="userFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>User:</p>
                                <div class="individualFilterControlWrapper">
                                    <select id="userFilterSelect" class="clickableButton filterSelectBox" name="user_id">
                                        $userSelectHTML
                                    </select>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                </div>
                            </div>


                            <div id="urlFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Entry Source Page:</p>
                                <div class="individualFilterControlWrapper">
                                    <select id="sourcePageFilterSelect" class="clickableButton filterSelectBox" name="source_url">
                                        $sourcePageSelectHTML
                                    </select>
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />
                                </div>
                            </div>


EOL;
if ($scriptCount > 0) {
    $pageBody .= <<<EOL
                                <div id="scriptFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Entry Source Script:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="source_script">
                                            $sourceScriptSelectHTML
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                    </div>
                                </div>

EOL;
}

if ($functionCount > 0) {
    $pageBody .= <<<EOL
                                <div id="functionFilterWrapper" class="individualFilterWrapper">
                                    <p>+</p><p>Entry Source Function:</p>
                                    <div class="individualFilterControlWrapper">
                                        <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="source_function">
                                            $sourceFunctionSelectHTML
                                        </select>
                                        <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearProjectFilter" type="button" value="Clear" />

                                    </div>
                                </div>

EOL;
}
$pageBody .= <<<EOL
                        </div>


                        <div class="eventLogFilterColumn">
                            <h3>Entry Status</h3>


                            <div id="readFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Read Status:</p>
                                <div class="individualFilterControlWrapper">
                                    $readFilterRadioHTML
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearReadFilter" type="button" value="Clear" />
                                </div>
                            </div>


                            <div id="closedFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Closed Status:</p>
                                <div class="individualFilterControlWrapper">
                                    $closedFilterRadioHTML
                                    <input class="clickableButton disabledClickableButton clearIndividualFilterButton" id="clearClosedFilter" type="button" value="Clear" />
                                </div>
                            </div>
                        </div>


                        <div class="eventLogFilterColumn">
                            <h3>Result Sort</h3>


                            <div id="sortFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Return Results Sorted By:<p>
                                <div class="individualFilterControlWrapper">
                                    <select id="sourceScriptFilterSelect" class="clickableButton filterSelectBox" name="sort_by_column">
                                        $sortByColumnSelectHTML
                                    </select>
                                </div>
                            </div>


                            <div id="directionFilterWrapper" class="individualFilterWrapper">
                                <p>+</p><p>Sort Direction:</p>
                                <div class="individualFilterControlWrapper">
                                    $sortDirectionFilterRadioHTML
                                </div>

                            </div>
                        </div>
                        <div id="applyFilterWrapper">
                            <p>Activate or Clear Filters:</p>
                            <input class="clickableButton" id="clearAllFilters" type="button" value="Clear All Filters" />
                            <input class="clickableButton" id="applyFilter" type="submit" value="Apply Filter" />
                        </div>
                    </form>
                </div>


                <div id="eventLogWrapper">
                    <div id="eventSummaryWrapper">
                        <h2>Feedback and Error Summary</h2>
                        <div id="eventTableWrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th class="eventIdColumn" id="eventIdHeader" title="The ID of this entry in the iCoast system."><div class="headContent">ID</div></th>
                                <th id="eventTimeHeader" title="The time at which the entry was logged."><div class="headContent">Entry Time</div></th>
                                <th id="eventTypeHeader" title="The type of entry that was logged. Error, System Feedback, or Project Feedback">
                                <div class="headContent">Entry Type</div></th>
                                <th id="eventUserHeader" title="The user logged in when the entry was recorded."><div class="headContent">User</div></th>
                                <th id="eventUrlHeader" title="The page displayed when the feedback was logged.">
                                <div class="headContent">Source Page</div></th>
                                <th id="eventSummaryHeader" title="A summary of the entry contents.">
                                <div class="headContent">Summary</div></th>
                                <th id="eventCodeHeader" title="The error code or project name relating to the entry."><div class="headContent">Project or Error</div></th>
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
                                            $rowsPerPageSelectHTML
                                </select>
                                <input type="button" id="lastPageButton" class="clickableButton disabledClickableButton"
                                       value=">>" title="Use this button to jump to the last page of results.">
                                <input type="button" id="nextPageButton" class="clickableButton disabledClickableButton"
                                       value=">" title="Use this button to display the next page of results.">
                            </div>
                        </div>
                    </div>

                    <div id="eventDetailsWrapper">
                        <h2>Feedback and Error Details</h2>
                        <p id="noEventSelectedText">You must select an entry from the table on the left to see its details here.</p>
                        <div id="eventDetailsContent">
                            <div id="eventDetailsText">
                                <p id="eventStatusText"></p>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry Status:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventStatus"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry ID:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventId"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry Time:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventTimeValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry Type:</p>
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
                                        <p>Entry Summary:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventSummaryValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry Code or Project:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventCodeValue"></div>
                                </div>

                                <div class="eventDetailRow">
                                    <div class="eventRowName">
                                        <p>Entry Details:</p>
                                    </div>
                                    <div class="eventRowValue" id="eventDetailsValue"></div>
                                </div>

                            </div>

                            <div id="eventDetailsControls">
                                <input type="button" id="toggleReadButton" class="clickableButton"
                                       value="Mark as Unread" title="Use this button to toggle the entry between read and unread status.">
                                <input type="button" id="toggleClosedButton" class="clickableButton"
                                       value="Mark as Closed" title="Use this button to toggle the entry between closed or open status.">
                            </div>
                        </div>



                    </div>
                </div>
                <div id="eventLogErrorWrapper">
                    <p>Error Text</p>
                </div>
            </div>
        </div>
EOL;

require('includes/template.php');
