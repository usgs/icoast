<?php

require('includes/pageCode/eventViewerCode.php');
$pageBody = <<<EOL
    <h1>iCoast Event Viewer</h1>
    <p>You have $totalEventCount unread event(s).</p>
    <table>
        <thead>
            <tr>
                <th>Event Time</th>
                <th>Event Type</th>
                <th>Logged By</th>
                <th>Project</th>
                <th>Event Preview</th>
            </tr>
        </thead>
        <tbody>
            $eventTableContents
        </tbody>
    </table>
EOL;

require('includes/template.php');
