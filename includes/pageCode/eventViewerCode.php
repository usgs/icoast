<?php



































//if ($adminLevel == 2) {
//
//    $errorEventQuery = "SELECT * FROM event_log WHERE event_type = 1 AND event_ack = 0";
//    $errorEventResult = $DBH->query($errorEventQuery);
//    if ($errorEventResult) {
//        $errorEventCount = $errorEventResult->fetchColumn();
//    }
//
//    $systemFeedbackQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 2 AND event_ack = 0";
//    $systemFeedbackResult = $DBH->query($systemFeedbackQuery);
//    if ($systemFeedbackResult) {
//        $systemFeedbackCount = $systemFeedbackResult->fetchColumn();
//    }
//}
//
//
//
//if (count($userAdministeredProjects) > 0) {
//    $projectString = where_in_string_builder($userAdministeredProjects);
//    $projectFeedbackQuery = "SELECT COUNT(*) FROM event_log WHERE event_type = 3  AND event_ack = 0 AND "
//            . "event_code IN ($projectString)";
//    $projectFeedbackResult = $DBH->query($projectFeedbackQuery);
//    if ($projectFeedbackResult) {
//        $projectFeedbackCount = $projectFeedbackResult->fetchColumn();
//    }
//}
//
//$totalFeedbackCount = $systemFeedbackCount + $projectFeedbackCount;
//
//if ($errorEventCount > 0 || $totalFeedbackCount > 0) {
//    $alertBoxContent .= "<h1 id=\"alertBoxTitle\">iCoast: You have unread events in the log</h1>";
//    if ($errorEventCount > 0) {
//        $alertBoxContent .= "<p>You have <span class=\"userData captionTitle\">$errorEventCount "
//                . "unread system error events</span> that require investigation.</p>";
//    }
//    if ($errorEventCount > 0 && $totalFeedbackCount > 0) {
//        $alertBoxContent .= "<p>AND</p>";
//    }
//    if ($totalFeedbackCount > 0) {
//        $alertBoxContent .= "<p>You have <span class=\"userData captionTitle\">$totalFeedbackCount "
//                . "unread feedback submissions</span> to be reviewed.</p>";
//    }
//    $alertBoxDynamicControls .= '<input type="button" id="goToEventViewerButton" class="clickableButton" '
//            . 'value="Go To Event Log">';
//}
