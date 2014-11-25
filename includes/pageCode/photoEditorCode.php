<?php

//A template file to use for page code files
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

if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $projectMetadata = retrieve_entity_metadata($DBH, $_GET['targetProjectId'], 'project');
        if ($projectMetadata) {
            $queryProjectWhereClause = "WHERE project_id = :targetProjectId";
            $queryProjectAndClause = "AND project_id = :targetProjectId";
            $javaScript .= "var projectId = {$projectMetadata['project_id']};\n\r";
        }
    }
}

if (isset($_GET['requestedAction'])) {
    switch ($_GET['requestedAction']) {
        case "Show Thumbnails":
            $thumbs = true;
            break;
        case "Show Map":
            $map = true;
            break;
    }
}

$contentSelectHTML = <<<EOL
            <option value="all">Show All Photos</option>
            <option value="classified">Show Classified Photos</option>
            <option value="unclassified">Show Unclassified photos</option>
            <option value="enabled">Show Enabled photos</option>
            <option value="disabled">Show Disabled photos</option>
EOL;

if (isset($_GET['targetContent'])) {
    switch ($_GET['targetContent']) {
        case "all":
        case "classified":
        case "unclassified":
        case "enabled":
        case "disabled":
            $contentSelectHTML = str_replace('"' . $_GET['targetContent'] . '"', '"' . $_GET['targetContent'] . '" selected', $contentSelectHTML);
            $targetContent = $_GET['targetContent'];
            $javaScript .= "var targetContent = '$targetContent';\n\r";
            break;
    }
}

if (isset($_GET['targetPhotoId'])) {
    settype($_GET['targetPhotoId'], 'integer');
    if (!empty($_GET['targetPhotoId'])) {
        $photoMetadata = retrieve_entity_metadata($DBH, $_GET['targetPhotoId'], 'image');
    }
}

$userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $optionProjectId = $singeUserAdministeredProject['project_id'];
    $optionProjectName = $singeUserAdministeredProject['name'];
    $optionProjectDescription = $singeUserAdministeredProject['description'];
    $projectSelectHTML .= "<option title=\"$optionProjectDescription\" value=\"$optionProjectId\"";
    if ($projectMetadata && $optionProjectId == $projectMetadata['project_id']) {
        $projectSelectHTML .= ' selected ';
    }
    $projectSelectHTML .= ">$optionProjectName</option>";
}

$photoSelectHTML = <<<EOL
    <h2 id="photoSelectHeader">Work On A Specific Photo</h2>
    <p>To edit a particular photo enter the ID in the textbox below.</p>
    <form method="get" autocomplete="off" id="photoSelectionForm" action="#imageDetailsHeader">
        <label for="analyticsPhotoIdTextbox">Photo ID: </label>
        <input type="textbox" id="analyticsPhotoIdTextbox" class="formInputStyle" name="targetPhotoId" >
        <input type="submit" id="userIdSubmit" class="clickableButton" value="Select Photo">
    </form>
EOL;
$displaySelectHTML = <<<EOL
    <h2 id="displaySelectHeader">Select A Project to View All Related Image Thumbnails</h2>
    <p>To view a grid of thumbnails of all photos in iCoast or a particular project choose a focus form the select box below.</p>
    <form method="get" autocomplete="off" action="#displaySelectHeader">
        <label for="displayProjectSelection">Project: </label>
        <select id="displayProjectSelection" class="formInputStyle" name="targetProjectId">
            $projectSelectHTML
        </select>
        <label for="displayContentSelection">Photo Type: </label>
        <select id="displayContentSelection" class="formInputStyle" name="targetContent">
            $contentSelectHTML
        </select>
        <br>
        <input type="submit" class="clickableButton" name="requestedAction" value="Show Thumbnails">
        <input type="submit" class="clickableButton" name="requestedAction" value="Show Map">
    </form>
EOL;


if ($photoMetadata && !$projectMetadata) {
    if (isset($_GET['setStatus'])) {
        switch ($_GET['setStatus']) {
            case 'Enable Image in iCoast':
                $statusChangeQuery = "UPDATE images "
                        . "SET is_globally_disabled = 0 "
                        . "WHERE image_id = {$photoMetadata['image_id']} "
                        . "LIMIT 1";
                break;
            case 'Disable Image in iCoast':
                $statusChangeQuery = "UPDATE images "
                        . "SET is_globally_disabled = 1 "
                        . "WHERE image_id = {$photoMetadata['image_id']} "
                        . "LIMIT 1";
                break;
        }
        if ($statusChangeQuery) {
            $DBH->query($statusChangeQuery);
            $photoMetadata = retrieve_entity_metadata($DBH, $photoMetadata['image_id'], 'image');
        }
    }

    $photoSelectHTML = <<<EOL
        <h2 id="photoSelectHeader">Change the Specified Photo</h2>
        <p>To see details for a different photo replace the photo ID below.</p>
        <form method="get" autocomplete="off" id="photoSelectionForm" action="#imageDetailsHeader">
            <label for="analyticsPhotoIdTextbox">Photo ID: </label>
            <input type="textbox" id="analyticsPhotoIdTextbox" class="formInputStyle" name="targetPhotoId" value="{$photoMetadata['image_id']}">
            <input type="submit" id="userIdSubmit" class="clickableButton" value="Select Photo">
        </form>
        <form method="get" autocomplete="off" id="userClearForm" action="#photoSelectHeader">
            <input type="submit" id="userIdClear" class="clickableButton" value="Clear Selected Photo">
        </form>
EOL;
    $displaySelectHTML = '';

    // Format postion coordinates as N/S vs. +/-
    if ($photoMetadata['latitude'] >= 0) {
        $latitude = $photoMetadata['latitude'] . ' N';
    } else {
        $latitude = abs($photoMetadata['latitude']) . ' S';
    }
    if ($photoMetadata['longitude'] >= 0) {
        $longitude = $photoMetadata['longitude'] . ' E';
    } else {
        $longitude = abs($photoMetadata['longitude']) . ' W';
    }

    $imageLocation = build_image_location_string($photoMetadata);
    $imageDate = utc_to_timezone($photoMetadata['image_time'], 'd M Y', $photoMetadata['longitude']);
    $imageTime = utc_to_timezone($photoMetadata['image_time'], 'H:i:s T', $photoMetadata['longitude']);

    // Highlight in red if image is disabled.
    if ($photoMetadata['is_globally_disabled'] == 0) {
        $imageStatus = "Enabled";
        $statusButtonHTML = '<input type="submit" name="setStatus" value="Disable Image in iCoast" class="clickableButton">';
    } else {
        $imageStatus = '<span class="redHighlight">Disabled</span>';
        $statusButtonHTML = '<input type="submit" name="setStatus" value="Enable Image in iCoast" class="clickableButton">';
    }


    $photoDetailsHTML = <<<EOL
            <h2 id="imageDetailsHeader">Image {$photoMetadata['image_id']} Status</h2>
            <div id="photoStatsImageWrapper">
                <img id="photoStatsImage" src="images/datasets/{$photoMetadata['dataset_id']}/main/{$photoMetadata['filename']}" width="800" height="521" data-zoom-image="$detailedImageURL">
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
                            <td>Status:</td>
                            <td class="userData">$imageStatus</td>
                        </tr>
                    </table>
                    <form method="get" autocomplete="off" action="#imageDetailsHeader">
                        <input type="hidden" name="targetPhotoId" value="{$photoMetadata['image_id']}" />
                        $statusButtonHTML
                    </form>
                </div>
                <form
            </div>

EOL;
} else if ($projectMetadata && $targetContent && !$photoMetadata) {


    if ($thumbs) {

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
                $startPhotoPosition = floor($_GET['startPhotoPosition']/$photosPerPage) * $photosPerPage;
            }
        }


        switch ($targetContent) {
            case "all":
                $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;

                $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    LEFT JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE i.has_display_file = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
                    ORDER BY d.region_id DESC, d.position_in_region DESC, i.position_in_set DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                break;
            case "classified":
                $photoCountQuery = <<<EOL
                    SELECT COUNT(DISTINCT(a.image_id)) AS result_count
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                        )
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$projectMetadata['project_id']}
EOL;

                $photoQuery = <<<EOL
                    SELECT DISTINCT(a.image_id), i.*
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    LEFT JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$projectMetadata['project_id']}
                    ORDER BY d.region_id DESC, d.position_in_region DESC, i.position_in_set DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                break;
            case "unclassified":

                $photoCountQuery = <<<EOL
                    SELECT COUNT(*)
                    FROM
                    (
                        SELECT i.*, a.annotation_id
                        FROM annotations a
                        RIGHT JOIN images i ON a.image_id = i.image_id
                        INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
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
                        INNER JOIN datasets d ON d.dataset_id = i.dataset_id
                        WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0
                        GROUP BY i.image_id
                        HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                    ) t1
EOL;

                $photoQuery = <<<EOL
                    SELECT i.*, a.annotation_id
                    FROM annotations a
                    RIGHT JOIN images i ON a.image_id = i.image_id
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
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
                    INNER JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0
                    GROUP BY i.image_id
                    HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
                    ORDER BY d.region_id DESC, d.position_in_region DESC, i.position_in_set DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;

                break;
            case "enabled":
                $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 0 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;

                $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    LEFT JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 0 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
                    ORDER BY d.region_id DESC, d.position_in_region DESC, i.position_in_set DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                break;
            case "disabled":
                $photoCountQuery = <<<EOL
                    SELECT COUNT(*) AS result_count
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;
                $photoQuery = <<<EOL
                    SELECT i.*
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    LEFT JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
                    ORDER BY d.region_id DESC, d.position_in_region DESC, i.position_in_set DESC
                    LIMIT $startPhotoPosition, $photosPerPage
EOL;
                break;
        }
        $photoCountResults = $DBH->query($photoCountQuery)->fetchColumn();
        $formattedPhotoCountResults = number_format($photoCountResults);
        if ($photoCountResults == 1) {
            $photoCountText = 'image matches';
        } else {
            $photoCountText = 'images match';
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

            $photoDetailsHTML = <<<EOL
                <p><span class="userData">$formattedPhotoCountResults</span> $photoCountText your selected criteria.</p>
                <p>Click on an image to toggle its status between Enabled and Disabled.<br>
                    A <span style="color: green">green</span> border indicates a photo is enabled. A <span style="color: red">red</span> border indicates a photo is disabled.<br>
                    Use the "View Photo Stats" buttons to display a seperate page showing classification details for the chosen photo.</p>
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
                        window.location.href='photoEditor.php?targetProjectId=' + projectId
                            + '&targetContent=' + targetContent
                            + '&requestedAction=Show+Thumbnails'
                            + '&startPhotoPosition=' + lastPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#displaySelectHeader';
                    });
                    $('.nextPageButton').click(function() {
                        var nextPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) + photosPerPage;
                        window.location.href='photoEditor.php?targetProjectId=' + projectId
                            + '&targetContent=' + targetContent
                            + '&requestedAction=Show+Thumbnails'
                            + '&startPhotoPosition=' + nextPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#displaySelectHeader';
                    });
                }

                if (startPhotoPosition > 0) {
                    $('.firstPageButton, .previousPageButton').removeClass('disabledClickableButton');
                    $('.firstPageButton, .previousPageButton').attr('disabled',false);

                    $('.firstPageButton').click(function() {
                        window.location.href='photoEditor.php?targetProjectId=' + projectId
                            + '&targetContent=' + targetContent
                            + '&requestedAction=Show+Thumbnails'
                            + '&photosPerPage=' + photosPerPage
                            + '#displaySelectHeader';
                    });
                    $('.previousPageButton').click(function() {
                        var previousPageStartPhotoPosition = (Math.floor(startPhotoPosition/photosPerPage)*photosPerPage) - photosPerPage;
                        if (previousPageStartPhotoPosition < 0) {
                            previousPageStartPhotoPosition = 0;
                        }
                        window.location.href='photoEditor.php?targetProjectId=' + projectId
                            + '&targetContent=' + targetContent
                            + '&requestedAction=Show+Thumbnails'
                            + '&startPhotoPosition=' + previousPageStartPhotoPosition
                            + '&photosPerPage=' + photosPerPage
                            + '#displaySelectHeader';
                    });
                }

                $('.photosPerPageSelect').change(function() {
                    var requestedPhotosPerPage = $('.photosPerPageSelect').val();
                    startPhotoPosition = Math.floor(startPhotoPosition/requestedPhotosPerPage)*requestedPhotosPerPage;
                    window.location.href='photoEditor.php?targetProjectId=' + projectId
                        + '&targetContent=' + targetContent
                        + '&requestedAction=Show+Thumbnails'
                        + '&startPhotoPosition=' + startPhotoPosition
                        + '&photosPerPage=' + requestedPhotosPerPage
                        + '#displaySelectHeader';
                });

                $('.pageJumpSelect').click(function() {
                    $('.pageJumpSelect').prop('selectedIndex', -1);
                });


                $('.pageJumpSelect').change(function() {
                    var requestedPage = $('.pageJumpSelect').val();
                    jumpPhotoPosition = (requestedPage - 1) * photosPerPage;
                    window.location.href='photoEditor.php?targetProjectId=' + projectId
                        + '&targetContent=' + targetContent
                        + '&requestedAction=Show+Thumbnails'
                        + '&startPhotoPosition=' + jumpPhotoPosition
                        + '&photosPerPage=' + photosPerPage
                        + '#displaySelectHeader';
                });

                $('.adminPhotoThumbnailCell img').click(function() {
                    var parentCell = $(this).parents('.adminPhotoThumbnailCell');

                    $.getJSON('ajax/statusChanger.php', parentCell.data(), function(statusChangeReturnData) {
                        if (statusChangeReturnData.success == 1) {
                            if (statusChangeReturnData.newImageStatus == 1) {
                                parentCell.data('currentStatus', 1);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Disabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "red");

                            } else {
                                parentCell.data('currentStatus', 0);
                                $('#photo' + parentCell.data('photoId') + 'Cell #StatusText').text('Enabled');
                                $('#photo' + parentCell.data('photoId') + 'Cell img').css("border-color", "green");
                            }
                        } else {
                            alert('The database update failed. Please try again later or report the problem to an Admin.');
                        }

                    });
                });



                $('.adminPhotoThumbnailCell input').click(function() {
                    var parentCell = $(this).parents('.adminPhotoThumbnailCell');
                    window.open('photoStats.php?targetPhotoId=' + parentCell.data('photoId') + '&targetProjectId=' + projectId + '#imageDetailsHeader', '_blank');
                });

EOL;
        } else {
            $photoDetailsHTML = <<<EOL
                    <p>There are no photos of the selected type in the {$projectMetadata['name']} project.<br>
                    Please select a different Photo Type or Project to try again.</p>

EOL;
        }
    } else if ($map) {
        switch ($targetContent) {
            case "all":
                $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;
                break;
            case "classified":
                $photoQuery = <<<EOL
                    SELECT DISTINCT(a.image_id), i.latitude, i.longitude, i.is_globally_disabled
                    FROM annotations a
                    INNER JOIN images i ON a.image_id = i.image_id AND i.has_display_file = 1
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE a.annotation_completed = 1 AND i.is_globally_disabled = 0 AND a.project_id = {$projectMetadata['project_id']}
EOL;
                break;
            case "unclassified":
                $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled, a.annotation_id
                    FROM annotations a
                    RIGHT JOIN images i ON a.image_id = i.image_id
                    INNER JOIN matches m ON i.image_id = m.post_image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
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
                    INNER JOIN datasets d ON d.dataset_id = i.dataset_id
                    WHERE i.has_display_file = 1 AND i.is_globally_disabled = 0
                    GROUP BY i.image_id
                    HAVING a.annotation_id IS NULL OR SUM(a.annotation_completed) = 0
EOL;
                break;
            case "enabled":
                $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 0 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;
                break;
            case "disabled":
                $photoQuery = <<<EOL
                    SELECT i.image_id, i.latitude, i.longitude, i.is_globally_disabled
                    FROM images i
                    INNER JOIN matches m ON m.post_image_id = i.image_id AND m.pre_image_id != 0 AND m.post_collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                         AND m.pre_collection_id IN
                         (
                             SELECT DISTINCT pre_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    WHERE i.has_display_file = 1 AND is_globally_disabled = 1 AND i.dataset_id IN
                    (
                         SELECT dataset_id
                         FROM datasets
                         WHERE collection_id IN
                         (
                             SELECT DISTINCT post_collection_id
                             FROM projects
                             WHERE project_id = {$projectMetadata['project_id']}
                         )
                    )
EOL;
                break;
        }
        $JSONmapResults = json_encode($DBH->query($photoQuery)->fetchAll(PDO::FETCH_ASSOC));
        $photoDetailsHTML = <<<EOL
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
                        marker.bindPopup('Image ID: <a href="photoStats.php?targetPhotoId=' + photo.image_id + '&targetProjectId=' + projectId + '#imageDetailsHeader" target="_blank">' + photo.image_id + '</a><br>'
                            + 'Location: ' + popupData.location + '<br>'
                            + '<a href="photoStats.php?targetPhotoId=' + photo.image_id + '&targetProjectId=' + projectId + '#imageDetailsHeader" target="_blank"><img class="mapMarkerImage" width="167" height="109" src="' + popupData.thumbnailURL + '" /></a>'

                            + '<p id="updateResult" class="redHighlight"></p>'
                            + popupStatusHTML
                            + popupButtonHTML,
                            {closeOnClick: true}
                        ).openPopup();
                        $('#photoStatusChangeButton').click(function() {
                            $.getJSON('ajax/statusChanger.php', imageData, function(statusChangeReturnData) {
                                if (statusChangeReturnData.success == 1) {
                                    $('#updateResult').replaceWith('<p id="updateResult" class="userData">Update successful.</p>');
                                    if (statusChangeReturnData.newImageStatus == 1) {
                                        $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="redHighlight">This photo is DISABLED.</p>');
                                        $('#photoStatusChangeButton').prop('value', 'Enable This Photo');
                                        enabledMarkers.removeLayer(marker);
                                        disabledMarkers.addLayer(marker);
                                    } else {
                                        $('#statusIndicatorText').replaceWith('<p id="statusIndicatorText" class="userData">This photo is ENABLED.</p>');
                                        $('#photoStatusChangeButton').prop('value', 'Disable This Photo');
                                        disabledMarkers.removeLayer(marker);
                                        enabledMarkers.addLayer(marker);
                                    }
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
    }
}
