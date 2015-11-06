<?php

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
$maskedEmail = $userData['masked_email'];

$targetType = FALSE;
$targetId = FALSE;
$filter = FALSE;
$displayType = FALSE;
$targetSelectionHTML = '';
$targetHTML = '';
$error = '';




if (isset($_GET['photoId'])) {
    $tempTargetId = $_GET['photoId'];
    $targetType = 'image';
} else if (isset($_GET['projectId']) && $_GET['projectId'] > 0) {
    $tempTargetId = $_GET['projectId'];
    $targetType = 'project';
} else if (isset($_GET['collectionId']) && $_GET['collectionId'] > 0) {
    $tempTargetId = $_GET['collectionId'];
    $targetType = 'collection';
}


if ($tempTargetId) {
    settype($tempTargetId, 'integer');
    if (!empty($tempTargetId)) {
        $targetMetadata = retrieve_entity_metadata($DBH, $tempTargetId, $targetType);
        if ($targetMetadata) {
            $targetId = $tempTargetId;
            $javaScript .= "var targetId = $tempTargetId;\n\r";
            $javaScript .= "var targetType = '$targetType';\n\r";
        } else {
            unset($targetType);
        }
    }
    unset($tempTargetId);
}

$filterOptionsHTML = <<<EOL
    <label for="filterSelect">Filter: </label>
    <select id="filterSelect" class="formInputStyle" name="filter">
        <option value="None" selected>None</option>
        <option value="Classified">Classified Photos Only</option>
        <option value="Unclassified">Unclassified Photos Only</option>
        <option value="Enabled">Enabled Photos Only</option>
        <option value="Disabled">Disabled Photos Only</option>
    </select>
EOL;

if (isset($_GET['filter']) &&
        ($_GET['filter'] == 'None' ||
        $_GET ['filter'] == 'Classified' ||
        $_GET['filter'] == 'Unclassified' ||
        $_GET['filter'] == 'Enabled' ||
        $_GET ['filter'] == 'Disabled')) {
    $filter = $_GET['filter'];
    $javaScript .= "var filter = \"$filter\";\n\r";
    $filterOptionsHTML = str_replace('"' . $filter . '"', '"' . $filter . '" selected', $filterOptionsHTML);
}

if (isset($_GET['displayType']) &&
        ($_GET['displayType'] == 'Show Thumbnails' || $_GET['displayType'] == 'Show Map')) {
    $displayType = $_GET['displayType'];
    $javaScript .= "var displayType = \"$displayType\";\n\r";
}



if (!$targetType) {
    $projectOptionHTML = '<option value="0"></option>';
    $collectionOptionHTML = '<option value="0"></option>';

    $projectList = find_projects($DBH, TRUE, TRUE);
    foreach ($projectList as $project) {
        $projectOptionHTML .= <<<EOL
                <option title="{$project['description']}" value="{$project['project_id']}">{$project['name']}</option>
EOL;
    }
    unset($project);

    $collectionListQuery = <<<EOL
        SELECT collection_id, name, description
        FROM collections
        ORDER BY name
EOL;
    $collectionListResult = run_prepared_query($DBH, $collectionListQuery);
    $collectionList = $collectionListResult->fetchAll(PDO::FETCH_ASSOC);
    foreach ($collectionList as $collection) {
        $collectionOptionHTML .= <<<EOL
                <option title="{$collection['description']}" value="{$collection['collection_id']}">{$collection['name']}</option>
EOL;
    }
    unset($collection);

    $targetSelectionHTML = <<<EOL
    <h2>Work On A Specific Photo</h2>
    <p>To edit a particular photo enter the ID in the textbox below.</p>
    <form method="get" autocomplete="off" id="photoSelectionForm" action="#targetHeader">
        <label for="photoTextBox">Photo ID: </label>
        <input type="textbox" id="photoTextBox" class="formInputStyle" name="photoId" >
        <input type="submit" id="userIdSubmit" class="clickableButton" value="Show Photo">
    </form>
    <h2>Work On Complete Project or Collection</h2>
    <p>To work on multiple photos from a single collection or project select the<br>source, filter, and display
        method from the options below.<p>
            <p class="error">$error</p>
    <form method="get" autocomplete="off">

        <label for="projectSelect">Project: </label>
        <select id="projectSelect" class="formInputStyle" name="projectId">
            $projectOptionHTML
        </select>
        <span id="photoEditorSeperator">OR</span>
        <label for="collectionSelect">Collection: </label>
        <select id="collectionSelect" class="formInputStyle" name="collectionId">
            $collectionOptionHTML
        </select>
        <br>
        $filterOptionsHTML
        <br>
        <p>Display Method:
        <input type="submit" class="clickableButton" name="displayType" value="Show Thumbnails">
        <input type="submit" class="clickableButton" name="displayType" value="Show Map"></p>
    </form>
EOL;
    $jQueryDocumentDotReadyCode = <<<EOL
        $('#projectSelect').prop('selectedIndex', -1);
        $('#projectSelect').change(function() {
            if ($(projectSelect).prop('selectedIndex') > 0) {
                $('#collectionSelect').prop('selectedIndex', 0);
            }
        });
        $( "#collectionSelect" ).change(function() {
            if ($(this).prop('selectedIndex') > 0) {
                $('#projectSelect').prop('selectedIndex', 0);
            }
        });

EOL;
} else {


    if ($targetType == 'image') {
        $targetSelectionHTML = <<<EOL
        <form method="get" autocomplete="off" id="photoMenuReturnForm" action="">
            <input type="submit" id="userIdSubmit" class="clickableButton" value="Return To Photo Selection Menu">
        </form>

EOL;

        // If the action field is supplied execute the enable or diable change to the image in the database.
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'enable' :
                    $statusChangeQuery = "UPDATE images "
                            . "SET is_globally_disabled = 0 "
                            . "WHERE image_id = $targetId "
                            . "LIMIT 1";
                    break;
                case 'disable' :
                    $statusChangeQuery = "UPDATE images "
                            . "SET is_globally_disabled = 1 "
                            . "WHERE image_id = $targetId "
                            . "LIMIT 1";
                    break;
            }
            if ($statusChangeQuery) {
                run_prepared_query($DBH, $statusChangeQuery);
                $targetMetadata = retrieve_entity_metadata($DBH, $targetId, 'image');
            }
        }

        // Format postion coordinates as N/S vs. +/-
        if ($targetMetadata ['latitude'] >= 0) {
            $latitude = $targetMetadata['latitude'] . ' N';
        } else {
            $latitude = abs($targetMetadata ['latitude']) . ' S';
        }
        if ($targetMetadata ['longitude'] >= 0) {
            $longitude = $targetMetadata['longitude'] . ' E';
        } else {
            $longitude = abs($targetMetadata['longitude']) . ' W';
        }

        $imageLocation = build_image_location_string($targetMetadata);
        $imageDate = utc_to_timezone($targetMetadata['image_time'], 'd M Y', $targetMetadata['longitude']);
        $imageTime = utc_to_timezone($targetMetadata['image_time'], 'H:i:s T', $targetMetadata['longitude']);
        $collectionMetadata = retrieve_entity_metadata($DBH, $targetMetadata['collection_id'], 'collection');
        $collectionName = $collectionMetadata['name'];
        $collectionDescription = $collectionMetadata['description'];


// Highlight in red if image is disabled. If not find out the projects it is used in.
        if ($targetMetadata['is_globally_disabled'] == 0) {
            $globalImageStatusHTML = "Enabled";

            $imageParentProjectsQuery = <<<EOL
            SELECT project_id, name, description, post_collection_id, pre_collection_id, is_public
            FROM projects
            WHERE (post_collection_id = {$targetMetadata['collection_id']}
                OR pre_collection_id = {$targetMetadata['collection_id']})
            ORDER BY name
EOL;
            $imageParentProjectsResult = run_prepared_query($DBH, $imageParentProjectsQuery);

// Format image and parent details for display
// Build list of projects using the specified image for incusion in table cell.
            $projectListHTML = '';


            while ($parentProject = $imageParentProjectsResult->fetch(PDO::FETCH_ASSOC)) {
                $optionProjectId = $parentProject['project_id'];
                $optionProjectName = $parentProject['name'];
                $optionProjectDescription = $parentProject ['description'];
                if ($parentProject['post_collection_id'] == $targetMetadata['collection_id']) {
                    $preOrPostText = ' Used as a Post-Event Photo';
                } else {
                    $preOrPostText = ' Used as a Pre-Event Photo';
                }

                if ($parentProject ['is_public'] == 1) {
                    $projectListHTML .= '<span title="' . $parentProject['description'] . '"><a href="classificationStats.php?targetProjectId=' . $parentProject ['project_id'] . '">' . $parentProject['project_id'] . ' - ' . $parentProject['name'] . "</a>. $preOrPostText.";
                } else {
                    $projectListHTML .= '<span title="' . $parentProject['description'] . ' (Project Disabled)"><a href="classificationStats.php?targetProjectId=' . $parentProject ['project_id'] . '">' . $parentProject['project_id'] . ' - ' . $parentProject['name'] . "</a>. $preOrPostText (<span class=\"redHighlight\">Project Disabled</span>).";
                }
                $projectListHTML .= '</span><br>';
            }
            $projectListHTML = rtrim($projectListHTML, '<br>');
        } else {
            $globalImageStatusHTML = '<span class="redHighlight">Disabled</span>';
            $projectListHTML = 'None';
        }

        $targetHTML = <<<EOL
            <h2 id="targetHeader">Image $targetId Status</h2>
            <div id="photoStatsImageWrapper">
                <img id="photoStatsImage" src="images/collections/{$targetMetadata['collection_id']}/main/{$targetMetadata['filename']}" width="800" height="521" data-zoom-image="$detailedImageURL">
            </div>
            <div id="photoStatsMapDetailsWrapper">
                <div id="photoStatsDetailsWrapper">
                    <h3>Photo Details</h3>
                    <table class="adminStatisticsTable">
                        <tr>
                            <td>Position:</td>
                            <td class="userData">$latitude, $longitude</td>
                        </tr>
                        <tr>
                            <td>Taken Near:</td>
                            <td class="userData">$imageLocation</td>
                        </tr>
                        <tr>
                            <td>Captured On:</td>
                            <td class="userData">$imageDate, $imageTime</td>
                        </tr>
                        <tr>
                            <td>Part Of Collection</td>
                            <td class="userData" title="$collectionDescription">$collectionName</td>
                        </tr>
                        <tr>
                            <td>Global Status In iCoast:</td>
                            <td class="userData">$globalImageStatusHTML</td>

                        </tr>
                        <tr>
                            <td>Image Used in Project(s):</td>
                            <td class="userData">$projectListHTML</td>
                        </tr>
                    </table>
EOL;
        if ($globalImageStatusHTML != 'Enabled') {
            $targetHTML .= <<<EOL
                    <form method="get" autocomplete="off" action="#targetHeader">
                        <input type="hidden" name="photoId" value="$targetId" />
                        <input type="hidden" name="action" value="enable" />
                        <input type="submit" value="Enable Image In iCoast" class="clickableButton"
                            title="Clicking this button will make this image available to all projects that use the image's
                                collection for either it's pre or post event photos.">
                    </form>

EOL;
        } else {
            $targetHTML .= <<<EOL
                    <form method="get" autocomplete="off" action="#targetHeader">
                        <input type="hidden" name="photoId" value="$targetId" />
                        <input type="hidden" name="action" value="disable" />
                        <input type="submit" value="Disable Image In iCoast" class="clickableButton"
                            title="Clicking this button will make this image unavailable for all projects that use the image's
                                collection for either it's pre or post event photos.">

                    </form>

EOL;
        }
        $targetHTML .= <<<EOL
                </div>
            </div>
EOL;
    } // END if ($targetType == 'image')

    if (($targetType == 'project' || $targetType = 'collection') && $filter && $displayType) {

        $targetSelectionHTML = <<<EOL
            <form method="get" autocomplete="off" id="photoMenuReturnForm" action="">
                <input type="submit" id="userIdSubmit" class="clickableButton" value="Return To Photo Selection Menu">
            </form>
            <h2>Refine Your Current Project/Collection Selection</h2>
            <form method="get" autocomplete="off" action="#targetHeader">
                <input type="hidden" name="{$targetType}Id" value="$targetId">
                $filterOptionsHTML
                <br>
                <p>Display Method:
                <input type="submit" class="clickableButton" name="displayType" value="Show Thumbnails">
                <input type="submit" class="clickableButton" name="displayType" value="Show Map"></p>
            </form>

EOL;
        $jQueryDocumentDotReadyCode .= <<<EOL
                    $('#filterSelect').change(function() {
                    filter = $(this).val();
                    window.location.href='photoEditor.php?'
                        + targetType + 'Id=' + targetId
                        + '&filter=' + filter
                        + '&displayType=' + displayType
                        + '#targetHeader';
                    });

EOL;

        if ($displayType == 'Show Thumbnails') {


            $photosPerPageSelectHTML = <<<EOL
            <option value="25">25 Photos Per Page</option>
            <option value="50">50 Photos Per Page</option>
            <option value="100">100 Photos Per Page</option>
            <option value="250">250 Photos Per Page</option>
            <option value="500">500 Photos Per Page</option>
EOL;

            if (isset($_GET['photosPerPage'])) {
                switch ($_GET['photosPerPage']) {
                    case '25':
                    case '50':
                    case '100':
                    case '250':
                    case '500':
                        $photosPerPage = $_GET['photosPerPage'];
                        $photosPerPageSelectHTML = str_replace('"' . $_GET['photosPerPage'] . '">', '"' . $_GET['photosPerPage'] . '" selected>', $photosPerPageSelectHTML);
                        break;
                }
            }

            if (!$photosPerPage) {
                $photosPerPage = 25;
                $photosPerPageSelectHTML = str_replace('"25">', '"25" selected>', $photosPerPageSelectHTML);
            }


            $startPhotoPosition = 0;
            if (isset($_GET['startPhotoPosition'])) {
                settype($_GET['startPhotoPosition'], 'integer');
                if (!empty($_GET['startPhotoPosition'])) {
                    $startPhotoPosition = floor($_GET['startPhotoPosition'] / $photosPerPage) * $photosPerPage;
                }
            }

            if ($targetType == 'project') {
                switch ($filter) {
                    case "None":
                        $photoCountQuery = <<<EOL
                        SELECT COUNT(*) AS result_count
                        FROM images i
                        INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                        WHERE i.collection_id = {$targetMetadata['post_collection_id']}
EOL;

                        $photoQuery = <<<EOL
                        SELECT i.*
                        FROM images i
                        INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                        WHERE i.collection_id = {$targetMetadata['post_collection_id']}
                        ORDER BY i.position_in_collection DESC
                        LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;

                    case "Classified":
                        $photoCountQuery = <<<EOL
                        SELECT COUNT(DISTINCT(a.image_id)) AS result_count
                        FROM annotations a
                        INNER JOIN images i ON a.image_id = i.image_id
                        INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                            )
                        WHERE a.annotation_completed = 1
                            AND i.is_globally_disabled = 0
                            AND a.project_id = {$targetMetadata['project_id']}
EOL;

                        $photoQuery = <<<EOL
                        SELECT DISTINCT(a.image_id), i.*
                        FROM annotations a
                        INNER JOIN images i ON a.image_id = i.image_id
                        INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                        WHERE a.annotation_completed = 1
                            AND i.is_globally_disabled = 0
                            AND a.project_id = {$targetMetadata['project_id']}
                        ORDER BY i.position_in_collection DESC
                        LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                    case "Unclassified":

                        $photoCountQuery = <<<EOL
                        SELECT COUNT(*)
                        FROM
                        (
                            SELECT i.*, a.annotation_id
                            FROM annotations a
                            RIGHT JOIN images i ON a.image_id = i.image_id
                            INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                                 (
                                     SELECT DISTINCT post_collection_id
                                     FROM projects
                                     WHERE project_id = 1
                                 )
                                 AND m.pre_collection_id IN
                                 (
                                     SELECT DISTINCT pre_collection_id
                                     FROM projects
                                     WHERE project_id = 1
                                 )
                            WHERE i.is_globally_disabled = 0
                            GROUP BY i.image_id
                            HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                        ) t1
EOL;

                        $photoQuery = <<<EOL
                        SELECT i.*, a.annotation_id
                        FROM annotations a
                        RIGHT JOIN images i ON a.image_id = i.image_id
                        INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = 1
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = 1
                             )
                        WHERE i.is_globally_disabled = 0
                        GROUP BY i.image_id
                        HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                        ORDER BY i.position_in_collection DESC
                        LIMIT $startPhotoPosition, $photosPerPage
EOL;

                        break;
                    case "Enabled":
                        $photoCountQuery = <<<EOL
                        SELECT COUNT(*) AS result_count
                        FROM images i
                        INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                        WHERE is_globally_disabled = 0
                            AND i.collection_id = {$targetMetadata['post_collection_id']}
EOL;

                        $photoQuery = <<<EOL
                        SELECT i.*
                        FROM images i
                        INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                             (
                                 SELECT DISTINCT post_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                             AND m.pre_collection_id IN
                             (
                                 SELECT DISTINCT pre_collection_id
                                 FROM projects
                                 WHERE project_id = {$targetMetadata['project_id']}
                             )
                        WHERE is_globally_disabled = 0
                            AND i.collection_id = {$targetMetadata['post_collection_id']}
                        ORDER BY i.position_in_collection DESC
                        LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                    case "Disabled":
                        $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE is_globally_disabled = 1
                        AND i.collection_id = {$targetMetadata['post_collection_id']}
EOL;
                        $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE is_globally_disabled = 1
                        AND i.collection_id = {$targetMetadata['post_collection_id']}
                    ORDER BY i.position_in_collection DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                }
            } else { // $targetType == 'collection'
                switch ($filter) {
                    case "None":
                        $photoCountQuery = <<<EOL
                        SELECT COUNT(*) AS result_count
                        FROM images i
                        WHERE i.collection_id = {$targetMetadata['collection_id']}
EOL;

                        $photoQuery = <<<EOL
                        SELECT i.*
                        FROM images i
                        WHERE i.collection_id = {$targetMetadata['collection_id']}
                        ORDER BY i.position_in_collection DESC
                        LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;

                    case "Classified":
                        $photoCountQuery = <<<EOL
                    SELECT COUNT(DISTINCT(a.image_id)) AS result_count
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
EOL;

                        $photoQuery = <<<EOL
                    SELECT DISTINCT(a.image_id), i.*
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
                    ORDER BY i.position_in_collection DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                    case "Unclassified":

                        $photoCountQuery = <<<EOL
                    SELECT COUNT(*)
                    FROM
                    (
                        SELECT i.*, a.annotation_id
                        FROM annotations a
                        RIGHT JOIN images i ON a.image_id = i.image_id
                        WHERE i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
                        GROUP BY i.image_id
                        HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                    ) t1
EOL;

                        $photoQuery = <<<EOL
                    SELECT i.*, a.annotation_id
                    FROM annotations a
                    RIGHT JOIN images i ON a.image_id = i.image_id
                    WHERE i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
                    GROUP BY i.image_id
                    HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                    ORDER BY i.position_in_collection DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;

                        break;
                    case "Enabled":
                        $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    WHERE is_globally_disabled = 0
                            AND i.collection_id = {$targetMetadata['collection_id']}
EOL;

                        $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    WHERE is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
                    ORDER BY i.position_in_collection DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                    case "Disabled":
                        $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    WHERE is_globally_disabled = 1
                        AND i.collection_id = {$targetMetadata['collection_id']}
EOL;
                        $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    WHERE is_globally_disabled = 1
                        AND i.collection_id = {$targetMetadata['collection_id']}
                    ORDER BY i.position_in_collection DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                        break;
                }
            }
            $photoCountReturn = run_prepared_query($DBH, $photoCountQuery);
            $photoCountResults = $photoCountReturn->fetchColumn();
            $formattedPhotoCountResults = number_format($photoCountResults);
            if ($photoCountResults == 1) {
                $photoCountText = 'photo matches';
            } else {
                $photoCountText = 'photos match';
            }
            $photoResults = $DBH->query($photoQuery)->fetchAll(PDO::FETCH_ASSOC);

            if ($photoCountResults > 0) {

                $columnCount = 0;
                $photoGridHTML = '<div class="adminPhotoThumbnailRow">';
                foreach ($photoResults as $photo) {
                    $photoLocation = build_image_location_string($photo, TRUE);
                    if ($photo['is_globally_disabled'] == 0) {
                        $photoStatus = 'Enabled';
                        $photoStatusHighlight = 'green';
                    } else {
                        $photoStatus = 'Disabled';
                        $photoStatusHighlight = 'red';
                    }

                    if ($columnCount == 5) {
                        $photoGridHTML .= '</div><div class="adminPhotoThumbnailRow">';
                        $columnCount = 0;
                    }
                    $photoGridHTML .= <<<EOL
                    <div id="photo{$photo['image_id']}Cell" class="adminPhotoThumbnailCell">
                        <div class="adminPhotoThumbnailWrapper">
                            <img src="{$photo['thumb_url']}" title="Click the image to toggle its status between Enabled and Disabled" style="border-color: $photoStatusHighlight" />
                        </div>
                        <input type="button" class="clickableButton" value="View Photo Stats" title="Opens this photo's statistics page in a new tab." />
                        <span class="adminPhotoThumbnailMetadata"><span>Photo ID:</span> {$photo['image_id']}</span>
                        <span class="adminPhotoThumbnailMetadata"><span>Status:</span> <span id="StatusText">$photoStatus</span></span>
                        <span class="adminPhotoThumbnailMetadata"><span>Location:</span> $photoLocation</span>
                    </div>

EOL;

                    $jQueryDocumentDotReadyCode .= <<<EOL
                 $('#photo{$photo['image_id']}Cell').data({
                    'photoId': {$photo['image_id']},
                    'currentStatus': {$photo['is_globally_disabled']}
                });

EOL;

                    $columnCount++;
                } // End foreach photo loop

                $numberOfPhotoPages = floor($photoCountResults / $photosPerPage + 1);
                $currentPageNumber = floor(($startPhotoPosition / $photosPerPage) + 1);
                $pageJumpSelectHTML = '';
                for ($i = 1; $i <= $numberOfPhotoPages; $i ++) {
                    if ($i != $currentPageNumber) {
                        $pageJumpSelectHTML .= "<option value=\"$i\">Jump To Page $i</option>";
                    }
                }





                $photoGridHTML .= '</div>';
                $thumbnailGridControlHTML = <<<EOL
                <div class="thumbnailControlWrapper">
                    <div>
                        <input type="button" class="firstPageButton clickableButton disabledClickableButton" value="<<" disabled />
                        <input type="button" class="previousPageButton clickableButton disabledClickableButton" value="<" disabled />
                        <select class="photosPerPageSelect formInputStyle">
                            $photosPerPageSelectHTML
                        </select>
                    <p class="pageNumberInfo">Page $currentPageNumber of $numberOfPhotoPages</p>
                        <input type="button" class="lastPageButton clickableButton disabledClickableButton" value=">>" disabled />
                        <input type="button" class="nextPageButton clickableButton disabledClickableButton" value=">" disabled />
                        <select class="pageJumpSelect formInputStyle" $jumpSelectStatus>
                            $pageJumpSelectHTML
                        </select>
                    </div>
                </div>
EOL;

                $targetHTML = <<<EOL
                <h2 id="targetHeader">SELECTED PROJECT: {$targetMetadata['name']} - FILTER: $filter</h2>
                <p><span class="userData">$formattedPhotoCountResults</span> $photoCountText your selected criteria.</p>
                <p>Click on a photo to toggle its status between Enabled and Disabled.<br>
                    A <span style="color: green">green</span> border indicates a photo is enabled. A <span style="color: red">red</span> border indicates a photo is disabled.<br>
                    Use the "View Photo Stats" buttons to display a separate page showing classification details for the chosen photo.</p>
                <div id="adminPhotoThumbnailGrid">
                    $thumbnailGridControlHTML
                    $photoGridHTML
                    $thumbnailGridControlHTML
                </div>
EOL;

                $javaScript .= <<<EOL
                var photosPerPage = $photosPerPage;
                var startPhotoPosition = $startPhotoPosition;
                var numberOfPhotos = $photoCountResults;
                var currentPageNumber = $currentPageNumber;
                var numberOfPhotoPages = $numberOfPhotoPages;


                $(window).load(function() {
                    $('.adminPhotoThumbnailRow').each(function() {
                        var row = $(this);
                        var maxImageHeight = 0;
                        row.find('.adminPhotoThumbnailWrapper').each(function() {
                            if ($(this).find('img').height() > maxImageHeight) {
                                 maxImageHeight = $(this).find('img').height();
                             };
                        });
                        row.find('.adminPhotoThumbnailWrapper').each(function() {
                            if ($(this).find('img').height() < maxImageHeight) {
                                var padding = (maxImageHeight - $(this).find('img').height()) / 2;
                                $(this).css("padding-top", padding + "px");
                                $(this).css("padding-bottom", padding + "px");
                            }
                        });
                    });
                });
EOL;

                $jQueryDocumentDotReadyCode .= <<<EOL
                if (numberOfPhotoPages == 1) {
                    $('.pageJumpSelect').hide();
                }

                if ((numberOfPhotos / photosPerPage > 1) && (startPhotoPosition < numberOfPhotos - (numberOfPhotos % photosPerPage))) {
                    $('.lastPageButton, .nextPageButton').removeClass('disabledClickableButton');
                    $('.lastPageButton, .nextPageButton').attr('disabled',false);

                    $('.lastPageButton').click(function() {
                        var lastPageStartPhotoPosition = numberOfPhotos - (numberOfPhotos % photosPerPage);
                        window.location.href='photoEditor.php?'
                            + targetType + 'Id=' + targetId
                            + '&filter=' + filter
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + lastPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#targetHeader';
                    });
                    $('.nextPageButton').click(function() {
                        var nextPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) + photosPerPage;
                        window.location.href='photoEditor.php?'
                            + targetType + 'Id=' + targetId
                            + '&filter=' + filter
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + nextPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#targetHeader';
                    });
                }

                if (startPhotoPosition > 0) {
                    $('.firstPageButton, .previousPageButton').removeClass('disabledClickableButton');
                    $('.firstPageButton, .previousPageButton').attr('disabled',false);

                    $('.firstPageButton').click(function() {
                        window.location.href='photoEditor.php?'
                            + targetType + 'Id=' + targetId
                            + '&filter=' + filter
                            + '&displayType=' + displayType
                            + '&photosPerPage=' + photosPerPage
                            + '#targetHeader';
                    });
                    $('.previousPageButton').click(function() {
                        var previousPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) - photosPerPage;
                        if (previousPageStartPhotoPosition < 0) {
                            previousPageStartPhotoPosition = 0;
                        }
                        window.location.href='photoEditor.php?'
                            + targetType + 'Id=' + targetId
                            + '&filter=' + filter
                            + '&displayType=' + displayType
                            + '&startPhotoPosition=' + previousPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#targetHeader';
                    });
                }

                $('.photosPerPageSelect').change(function() {
                    console.log('Select Changed');
                    var requestedPhotosPerPage = $(this).val();
                    console.log(requestedPhotosPerPage);
                    startPhotoPosition = Math.floor(startPhotoPosition/requestedPhotosPerPage)*requestedPhotosPerPage;
                    window.location.href='photoEditor.php?'
                        + targetType + 'Id=' + targetId
                        + '&filter=' + filter
                        + '&displayType=' + displayType
                        + '&startPhotoPosition=' + startPhotoPosition
                        + '&photosPerPage=' + requestedPhotosPerPage
                        + '#targetHeader';
                    console.log('Select Changed End');
                });

                $('.pageJumpSelect').click(function() {
                    $('.pageJumpSelect').prop('selectedIndex', -1);
                });


                $('.pageJumpSelect').change(function() {
                    var requestedPage = $('.pageJumpSelect').val();
                    jumpPhotoPosition = (requestedPage - 1) * photosPerPage;
                    window.location.href='photoEditor.php?'
                        + targetType + 'Id=' + targetId
                        + '&filter=' + filter
                        + '&displayType=' + displayType
                        + '&startPhotoPosition=' + jumpPhotoPosition
                        + '&photosPerPage=' + photosPerPage
                        + '#targetHeader';
                });

                $('.adminPhotoThumbnailCell img').click(function() {
                    var parentCell = $(this).parents('.adminPhotoThumbnailCell');

                    $.getJSON('ajax/statusChanger.php', parentCell.data(), function(statusChangeReturnData) {
                        if (statusChangeReturnData.success == 2) {
                            if (statusChangeReturnData.newImageStatus == 1) {
                                parentCell.data('currentStatus', 1);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Disabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "red");

                            } else {
                                parentCell.data('currentStatus', 0);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Enabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "green");
                            }
                        } else if (statusChangeReturnData.success == 1) {
                            alert('Portrait images cannot be used in iCoast. The image will remain disabled.');
                        } else {
                            alert('The database update failed. Please try again later or report the problem to an Admin.');
                        }

                    });
                });



                $('.adminPhotoThumbnailCell input').click(function() {
                    var parentCell = $(this).parents('.adminPhotoThumbnailCell');
                    var redirectTarget = 'photoStats.php?targetPhotoId=' + parentCell.data('photoId');
                    if (targetType == 'project') {
                        redirectTarget += '&targetProjectId=' + targetId;
                    }
                    window.open(redirectTarget, '_blank');
                });

EOL;
            } else {
                $targetHTML = <<<EOL
                    <p>There are no photos of the selected type in the {$targetMetadata['name']} project.<br>
                    Please select a different Photo Type or Project to try again.</p>
                    <form method="get" autocomplete="off" id="photoMenuReturnForm" action="">
                        <input type="submit" id="userIdSubmit" class="clickableButton" value="Return To Photo Selection Menu">
                    </form>

EOL;
            }
        } else { // $displayType = 'Show Map'
            if ($targetType == 'project') {
                switch ($filter) {
                    case "None":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE i.collection_id = {$targetMetadata['post_collection_id']}
EOL;
                        break;
                    case "Classified":
                        $photoQuery = <<<EOL
                    SELECT DISTINCT(a.image_id), i.latitude, i.longitude, i.is_globally_disabled
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$targetMetadata['project_id']}
EOL;
                        break;
                    case "Unclassified":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled, a.annotation_id
                    FROM annotations a
                    RIGHT JOIN images i ON a.image_id = i.image_id
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = 1
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = 1
                         )
                    WHERE i.is_globally_disabled = 0
                    GROUP BY i.image_id
                    HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
EOL;
                        break;
                    case "Enabled":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['post_collection_id']}
EOL;
                        break;
                    case "Disabled":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.is_enabled = 1 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$targetMetadata['project_id']}
                         )
                    WHERE is_globally_disabled = 1 AND i.collection_id = {$targetMetadata['post_collection_id']}
EOL;
                        break;
                }
            } else { // $targetType = 'collection'
                switch ($filter) {
                    case "None":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    WHERE i.collection_id = {$targetMetadata['collection_id']}
EOL;
                        break;
                    case "Classified":
                        $photoQuery = <<<EOL
                    SELECT DISTINCT(a.image_id), i.latitude, i.longitude, i.is_globally_disabled
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
EOL;
                        break;
                    case "Unclassified":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled, a.annotation_id
                    FROM annotations a
                    RIGHT JOIN images i ON a.image_id = i.image_id
                    WHERE i.is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
                    GROUP BY i.image_id
                    HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
EOL;
                        break;
                    case "Enabled":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    WHERE is_globally_disabled = 0 AND i.collection_id = {$targetMetadata['collection_id']}
EOL;
                        break;
                    case "Disabled":
                        $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    WHERE is_globally_disabled = 1 AND i.collection_id = {$targetMetadata['collection_id']}
EOL;
                        break;
                }
            }

            $photoQueryResults = run_prepared_query($DBH, $photoQuery);
            $mapResults = $photoQueryResults->fetchAll(PDO::FETCH_ASSOC);
            if (count($mapResults) > 0) {
                $JSONmapResults = json_encode($mapResults);
                $capitalizedTargetType = strtoupper($targetType);
                $targetHTML = <<<EOL
            <h2 id="targetHeader">SELECTED $capitalizedTargetType: {$targetMetadata['name']} - FILTER: $filter</h2>
            <div id="photoEditorMap">
            </div>

EOL;

                $cssLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';
                $cssLinkArray[] = 'css/markerCluster.css';

                $javaScriptLinkArray[] = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js';
                $javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';

                $jQueryDocumentDotReadyCode .= <<<EOL
            var photos = $JSONmapResults;
            var map = L.map('photoEditorMap', {maxZoom: 16});
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
            }).addTo(map);
            L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
            L.control.scale({
                position: 'topright',
                metric: false
            }).addTo(map);
            var redMarker = L.icon({
                iconUrl: 'images/system/redMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });
            var greenMarker = L.icon({
                iconUrl: 'images/system/greenMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });
            var blueMarker = L.icon({
                iconUrl: 'images/system/blueMarker.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [0, -35]
            });

            var enabledMarkers = L.featureGroup();
            var disabledMarkers = L.featureGroup();
            var allMarkers = L.markerClusterGroup({
                disableClusteringAtZoom: 9,
                maxClusterRadius: 60
            });


            $.each(photos, function(key, photo) {
                if (photo.is_globally_disabled == 0) {
                    var marker = L.marker([photo.latitude, photo.longitude], {icon: greenMarker});
                    enabledMarkers.addLayer(marker);
                } else {
                    var marker = L.marker([photo.latitude, photo.longitude], {icon: redMarker});
                    disabledMarkers.addLayer(marker);
                }
                marker.on('click', function() {

                    enabledMarkers.eachLayer(function (layer) {
                        layer.setIcon(greenMarker);
                        layer.setZIndexOffset(0);
                    });
                    disabledMarkers.eachLayer(function (layer) {
                        layer.setIcon(redMarker);
                        layer.setZIndexOffset(0);
                    });
                    this.setIcon(blueMarker);
                    this.setZIndexOffset(100000);

                    if (enabledMarkers.hasLayer(this)) {
                        var popupStatusHTML = '<p id="statusIndicatorText" class="userData">This photo is ENABLED</p>';
                        var popupButtonHTML = '<div style="text-align: center"><input type="button" id="photoStatusChangeButton" class="clickableButton" value="Disable This Photo"></div>';
                    } else {
                        var popupStatusHTML = '<p id="statusIndicatorText" class="redHighlight">This photo is DISABLED</p>';
                        var popupButtonHTML = '<div style="text-align: center"><input type="button" id="photoStatusChangeButton" class="clickableButton" value="Enable This Photo"></div>';
                    }

                    var imageData = {
                        photoId: photo.image_id,
                        currentStatus: photo.is_globally_disabled
                    }

                    $.getJSON('ajax/popupGenerator.php', imageData, function(popupData) {
                        var photoStatsLink = 'photoStats.php?targetPhotoId=' + photo.image_id;
                        if (targetType == 'project') {
                            photoStatsLink += '&targetProjectId=' + targetId;
                        }
                        marker.bindPopup('Image ID: <a href="' + photoStatsLink + '#targetHeader" target="_blank">' + photo.image_id + '</a><br>'
                            + 'Location: ' + popupData.location + '<br>'
                            + '<a href="' + photoStatsLink + '#targetHeader" target="_blank"><img class="mapMarkerImage" width="167" height="109" src="' + popupData.thumbnailURL + '" /></a>'
                            + '<p id="updateResult" class="redHighlight"></p>'
                            + popupStatusHTML
                            + popupButtonHTML,
                            {closeOnClick: true}
                        ).openPopup();
                        $('#photoStatusChangeButton').click(function() {
                            $.getJSON('ajax/statusChanger.php', imageData, function(statusChangeReturnData) {
                                if (statusChangeReturnData.success == 2) {
                                    $('#updateResult').replaceWith('<p id="updateResult" class="userData">Update successful.</p>');
                                    if (statusChangeReturnData.newImageStatus == 1) {
                                        $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="redHighlight">This photo is DISABLED.</p>');
                                        $('#photoStatusChangeButton').prop('value', 'Enable This Photo');
                                        imageData['currentStatus'] = 1;
                                        enabledMarkers.removeLayer(marker);
                                        disabledMarkers.addLayer(marker);
                                    } else {
                                        $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="userData">This photo is ENABLED.</p>');
                                        $('#photoStatusChangeButton').prop('value', 'Disable This Photo');
                                        imageData['currentStatus'] = 0;
                                        disabledMarkers.removeLayer(marker);
                                        enabledMarkers.addLayer(marker);
                                    }
                                } else if (statusChangeReturnData.success == 1) {
                                    $('#updateResult').replaceWith('<p id="updateResult" class="redHighlight">Portrait images cannot be used in iCoast.</p>').delay(500).slideUp();
                                } else {
                                    $('#updateResult').replaceWith('<p id="updateResult" class="redHighlight">Update failed.</p>').delay(500).slideUp();
                                }

                            });
                        });
                    });


                });

            });
            allMarkers.addLayer(enabledMarkers);
            allMarkers.addLayer(disabledMarkers);
            map.fitBounds(allMarkers.getBounds());
            allMarkers.addTo(map);

EOL;
            } else {
                $targetHTML = <<<EOL
                    <p>There are no photos of the selected type in the {$targetMetadata['name']} project.<br>
                    Please select a different Photo Type or Project to try again.</p>
                    <form method="get" autocomplete="off" id="photoMenuReturnForm" action="">
                        <input type="submit" id="userIdSubmit" class="clickableButton" value="Return To Photo Selection Menu">
                    </form>

EOL;
            }
        } // END // $displayType = 'Show Map'
    } // END if (($targetType == 'project' || $targetType = 'collection') && $filter && $displayType)
}