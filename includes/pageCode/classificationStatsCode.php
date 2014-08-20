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

// Look for and setup user specified query paramters
if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
        $queryProjectClause = "project_id = :targetProjectId";
        $queryParams['targetProjectId'] = $targetProjectId;
        $projectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
        $projectTitle = $projectMetadata['name'];
        $statsTitle = "$projectTitle Project Statistics";
        $statsTarget = "project";
    }
}
if (!isset($queryParams)) {
    $numberofPhotosParams = array();
    $statsTitle = 'All iCoast Project Statistics';
    $statsTarget = 'iCoast';
}

$statsTableContent = '';

// Determine the number of available photos in the system/project
$numberOfPhotosQuery = "SELECT COUNT(*) FROM images "
        . "WHERE has_display_file = 1 AND is_globally_disabled = 0 AND dataset_id IN ("
        . "SELECT dataset_id "
        . "FROM datasets "
        . "WHERE collection_id "
        . "IN ("
        . "SELECT DISTINCT post_collection_id "
        . "FROM projects";
if (isset($queryProjectClause)) {
    $numberOfPhotosQuery .= ' WHERE ' . $queryProjectClause;
}
$numberOfPhotosQuery .= ")"
        . ")";
$numberOfPhotosResult = run_prepared_query($DBH, $numberOfPhotosQuery, $queryParams);
$numberOfPhotos = $numberOfPhotosResult->fetchColumn();
$statsTableContent .= '<tr><td title="This is the total number of post-event photos available to the user in either the iCoast system or the selected project">Number of photos in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfPhotos) . '</td></tr>';


// Determine number of distinct classified photos in system/project
$numberOfClassifiedPhotosQuery = "SELECT COUNT(DISTINCT image_id) "
        . "FROM annotations "
        . "WHERE annotation_completed = 1";
if (isset($queryProjectClause)) {
    $numberOfClassifiedPhotosQuery .= ' AND ' . $queryProjectClause;
}
$numberOfClassifiedPhotosResult = run_prepared_query($DBH, $numberOfClassifiedPhotosQuery, $queryParams);
$numberOfClassifiedPhotos = $numberOfClassifiedPhotosResult->fetchColumn();
$statsTableContent .= '<tr><td title="This is the number of photos in either iCoast or the selected project that have at least 1 classification.">Number of classified photos in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfClassifiedPhotos) . '</td></tr>';

// Determine the percentage of photo with 1 or more classifications
$classifiedPhotoPercentage = round(($numberOfClassifiedPhotos / $numberOfPhotos) * 100, 1);
$statsTableContent .= '<tr title="This is the percentage of the total number of photos available to the user in either iCoast or the selected project that have 1 or more classification." class="tableDivider"><td>Percentage of classified photos in ' . $statsTarget . '</td><td class="userData">' . $classifiedPhotoPercentage . '%</td></tr>';

$totalClassifications = 0;
// Determine the total number of classifications for the system/project.
$numberOfCompleteClassificationsQuery = "SELECT COUNT(*) FROM annotations WHERE annotation_completed = 1";
if (isset($queryProjectClause)) {
    $numberOfCompleteClassificationsQuery .= ' AND ' . $queryProjectClause;
}
$numberOfCompleteClassificationsResult = run_prepared_query($DBH, $numberOfCompleteClassificationsQuery, $queryParams);
$numberOfCompleteClassifications = $numberOfCompleteClassificationsResult->fetchColumn();
$totalClassifications += $numberOfCompleteClassifications;
$statsTableContent .= '<tr><td title="This is the total number of complete classifications users have completed in either iCoast as a whole or the selected project.">Number of complete classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfCompleteClassifications) . '</td></tr>';


// Determine the total number of incomplete classifications which have a match for the system/project.
$numberOfIncompleteClassificationsQuery = "SELECT COUNT(*) FROM annotations WHERE annotation_completed = 0 AND user_match_id IS NOT NULL";
if (isset($queryProjectClause)) {
    $numberOfIncompleteClassificationsQuery .= ' AND ' . $queryProjectClause;
}
$numberOfIncompleteClassificationsResult = run_prepared_query($DBH, $numberOfIncompleteClassificationsQuery, $queryParams);
$numberOfIncompleteClassifications = $numberOfIncompleteClassificationsResult->fetchColumn();
$totalClassifications += $numberOfIncompleteClassifications;
$statsTableContent .= '<tr><td title="This is the number of incomplete classifications within iCoast as a whole or the selected project. An incomplete classification is one in which the user was displayed and image and they selected a pre-event matching image. They may have completed some tasks but did not click the \'Done\' button on the final task to indicate completion of the classification. This number excludes unstarted classifications (see below).">Number of incomplete classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfIncompleteClassifications) . '</td></tr>';


// Determine the total number of unstarted classifications for the system/project.
$numberOfUnstartedClassificationsQuery = "SELECT COUNT(*) FROM annotations WHERE annotation_completed = 0 AND user_match_id IS NULL";
if (isset($queryProjectClause)) {
    $numberOfUnstartedClassificationsQuery .= ' AND ' . $queryProjectClause;
}
$numberOfUnstartedClassificationsResult = run_prepared_query($DBH, $numberOfUnstartedClassificationsQuery, $queryParams);
$numberOfUnstartedClassifications = $numberOfUnstartedClassificationsResult->fetchColumn();
$totalClassifications += $numberOfUnstartedClassifications;
$statsTableContent .= '<tr><td title="This is the number of unstarted classifications within iCoast as a whole or the selected project. An unstarted classification is one in which the user was displayed a post-event image but did not image match it to a pre-event image or complete any of the tasks.">Number of unstarted classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfUnstartedClassifications) . '</td></tr>';

// Determine the percentage of complete classifications from the total classifications for the system/project.
$completeClassificationPercentage = round(($numberOfCompleteClassifications / $totalClassifications) * 100, 1);
// Determine the percentage of incomplete classifications from the total classifications for the system/project.
$incompleteClassificationPercentage = round(($numberOfIncompleteClassifications / $totalClassifications) * 100, 1);
// Determine the percentage of unstarted classifications from the total classifications for the system/project.
$unstartedClassificationPercentage = round(($numberOfUnstartedClassifications / $totalClassifications) * 100, 1);
$statsTableContent .= '<tr><td title="Percentage of completed classifications from all classifications within iCoast as a whole or the selected project.">Percentage of complete classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($completeClassificationPercentage) . '%</td></tr>';
$statsTableContent .= '<tr><td title="Percentage of incomplete classifications from all classifications within iCoast as a whole or the selected project.">Percentage of incomplete classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($incompleteClassificationPercentage) . '%</td></tr>';
$statsTableContent .= '<tr class="tableDivider"><td title="Percentage of unstarted classifications from all classifications within iCoast as a whole or the selected project.">Percentage of unstarted classifications in ' . $statsTarget . '</td><td class="userData">' . number_format($unstartedClassificationPercentage) . '%</td></tr>';


// Determine the total number of tags that have been selected in the syetm/project
if (isset($queryProjectClause)) {
    $numberOfTagsSelectedQuery = "SELECT COUNT(*) "
            . "FROM annotation_selections anns "
            . "LEFT JOIN tags t ON anns.tag_id = t.tag_id "
            . "WHERE t.project_id = :targetProjectId";
} else {
    $numberOfTagsSelectedQuery = "SELECT COUNT(*) FROM annotation_selections";
}
$numberOfTagsSelectedResult = run_prepared_query($DBH, $numberOfTagsSelectedQuery, $queryParams);
$numberOfTags = $numberOfTagsSelectedResult->fetchColumn();
$statsTableContent .= '<tr><td title="The total number of tags that have been selected either in iCoast as a whole or within the selected project. ">Number of selected tags in ' . $statsTarget . '</td><td class="userData">' . number_format($numberOfTags) . '</td></tr>';

$tagBreakdown = '';

if (isset($queryProjectClause)) {
    $tagFrequencyInProjectQuery = '(SELECT
t.tag_id AS tag_id,
t.name AS tag_name,
t.description AS tag_description,
COUNT(anns.tag_id) AS frequency,
t.is_enabled AS tag_enabled,
null AS lower_parent_id,
null AS lower_parent_name,
null AS order_in_lower_parent,
null AS lower_parent_enabled,
tgcUpper.order_in_group AS order_in_upper_parent,
tgcUpper.tag_group_id AS upper_parent_id,
tgmUpper.name AS upper_parent_name,
tgmUpper.is_enabled AS upper_parent_enabled,
tc.order_in_task AS upper_group_order_in_task,
tc.task_id,
tm.order_in_project AS task_order_in_project,
tm.is_enabled AS task_enabled,
tm.name AS task_name,
tm.project_id
FROM tags t
LEFT JOIN tag_group_contents tgcUpper ON t.tag_id = tgcUpper.tag_id
LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 0
GROUP BY t.tag_id)

UNION

(SELECT
t.tag_id AS tag_id,
t.name AS tag_name,
t.description AS tag_description,
COUNT(anns.tag_id) AS frequency,
t.is_enabled AS tag_enabled,
tgcLower.tag_group_id AS lower_parent_id,
tgmLower.name AS lower_parent_name,
tgcLower.order_in_group AS order_in_lower_parent,
tgmLower.is_enabled AS lower_parent_enabled,
tgcUpper.order_in_group AS order_in_upper_parent,
tgcUpper.tag_group_id AS upper_parent_id,
tgmUpper.name AS upper_parent_name,
tgmUpper.is_enabled AS upper_parent_enabled,
tc.order_in_task AS upper_group_order_in_task,
tc.task_id,
tm.order_in_project AS task_order_in_project,
tm.is_enabled AS task_enabled,
tm.name AS task_name,
tm.project_id

FROM tags t
LEFT JOIN tag_group_contents tgcLower ON t.tag_id = tgcLower.tag_id
LEFT JOIN tag_group_contents tgcUpper ON tgcLower.tag_group_id = tgcUpper.tag_id
LEFT JOIN tag_group_metadata tgmLower ON tgcLower.tag_group_id = tgmLower.tag_group_id
LEFT JOIN tag_group_metadata tgmUpper ON tgcUpper.tag_group_id = tgmUpper.tag_group_id
LEFT JOIN task_contents tc ON tgcUpper.tag_group_id = tc.tag_group_id
LEFT JOIN task_metadata tm ON tc.task_id = tm.task_id
LEFT JOIN annotation_selections anns ON t.tag_id = anns.tag_id
WHERE tm.project_id = :targetProjectId AND tgmUpper.contains_groups = 1
GROUP BY t.tag_id)

ORDER BY task_order_in_project, upper_group_order_in_task, order_in_upper_parent, order_in_lower_parent';
    $tagFrequencyInProjectResult = run_prepared_query($DBH, $tagFrequencyInProjectQuery, $queryParams);
    $tagFrequency = $tagFrequencyInProjectResult->fetchAll(PDO::FETCH_ASSOC);

    $currentTaskId = '';
    $currentUpperParentId = '';
    $currentLowerParentId = '';
    $tagBreakdown .= "<h2>Individual Tag Selection Frequencies</h2>"
            . "<p>The table below shows the frequency of selection of individual tags with the chosen project.<br>"
            . "Tags are listed in the order in which they are displayed and grouped by their parent containers using horizontal dividing lines.</p>"
            . "<p>A red highlight over a task, group, or tag name indicates that the container is disabled.<br>"
            . "A red highlight in a frequency cell summarises if the tag or a parent container is disabled thus hiding the tag from users.<br>"
            . "An empty grey cell indicates that a nested group is not used in the organisation of the tag.</p>"
            . '<table id="tagFrequencyTable" class="borderedTable dividedColumns">'
            . '<thead><tr><td>Task Name</td><td>Group Name</td><td>Nested Group Name</td><td>Tag Name</td><td>Frequency</td></tr></thead><tbody>';
    foreach ($tagFrequency as $tag) {
        $showTopBorder = FALSE;
        if ($currentTaskId != $tag['task_id']) {
            if ($tag['task_enabled'] == 0) {
                $tagBreakdown .= '<tr class="topTaskTableRowBorder"><td class="disabledProperty">';
            } else {
                $tagBreakdown .= '<tr class="topTaskTableRowBorder"><td>';
            }
            $tagBreakdown .= "{$tag['task_name']}</td>";
        } else {
            $tagBreakdown .= '<tr><td></td>';
        }
        $currentTaskId = $tag['task_id'];



        if ($currentUpperParentId != $tag['upper_parent_id']) {
            $showTopBorder = TRUE;
            if ($tag['upper_parent_enabled'] == 0) {
                $tagBreakdown .= '<td class="disabledProperty topBorder">';
            } else {
                $tagBreakdown .= '<td class="topBorder">';
            }
            $tagBreakdown .= "{$tag['upper_parent_name']}</td>";
        } else {
            if ($showTopBorder) {
                $tagBreakdown .= '<td class="topBorder"></td>';
            } else {
                $tagBreakdown .= '<td></td>';
            }
        }
        $currentUpperParentId = $tag['upper_parent_id'];



        if (empty($tag['lower_parent_id'])) {
            if ($showTopBorder) {
                $tagBreakdown .= '<td class="unusedTableCell topBorder"></td>';
            } else {
                $tagBreakdown .= '<td class="unusedTableCell"></td>';
            }
        } else {
            if ($currentLowerParentId != $tag['lower_parent_id']) {
                $showTopBorder = TRUE;
                if ($tag['lower_parent_enabled'] == 0) {
                    $tagBreakdown .= '<td class="disabledProperty topBorder">';
                } else {
                    $tagBreakdown .= '<td class="topBorder">';
                }
                $tagBreakdown .= "{$tag['lower_parent_name']}</td>";
            } else {
                if ($showTopBorder) {
                    $tagBreakdown .= '<td class="topBorder"></td>';
                } else {
                    $tagBreakdown .= '<td></td>';
                }
            }
        }
        $currentLowerParentId = $tag['lower_parent_id'];



        if ($tag['tag_enabled'] == 0) {
            $tagBreakdown .= '<td class="disabledProperty topBorder" title="' . $tag['tag_description'] . '">';
        } else {
            $tagBreakdown .= '<td class="topBorder" title="' . $tag['tag_description'] . '">';
        }
        $tagBreakdown .= "{$tag['tag_name']}</td>";

        if ($tag['task_enabled'] == 0 ||
                $tag['upper_parent_enabled'] == 0 ||
                (!empty($tag['lower_parent_enabled']) && $tag['lower_parent_enabled'] == 0) ||
                $tag['tag_enabled'] == 0) {
            $tagBreakdown .= '<td class="topBorder disabledProperty">';
        } else {
            $tagBreakdown .= '<td class="topBorder">';
        }
        $tagBreakdown .= number_format($tag['frequency']) . '</td>';

        $tagBreakdown .= '</tr>';
    }
    $tagBreakdown .= '</table>';
}





// DETERMINE THE LIST OF AVAILABLE/PERMISSIONED PROJECTS
$userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
$projectCount = count($userAdministeredProjects);
// BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
// PROJECT SELECT
$projectSelectHTML = "<option title=\"All Projects in the iCoast system.\" value=\"0\">All iCoast Projects</option>";
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $optionProjectId = $singeUserAdministeredProject['project_id'];
    $optionProjectName = $singeUserAdministeredProject['name'];
    $optionProjectDescription = $singeUserAdministeredProject['description'];
    $projectSelectHTML .= "<option title=\"$optionProjectDescription\" value=\"$optionProjectId\"";
    if ($optionProjectId == $targetProjectId) {
        $projectSelectHTML .= ' selected ';
    }
    $projectSelectHTML .= ">$optionProjectName</option>";
}