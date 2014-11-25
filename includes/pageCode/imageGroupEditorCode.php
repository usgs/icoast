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

$userGroupProgressHTML = '';
$projectSelectionDetailsHTML = '';
$jsButtonFunctions = '';


if (isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
        $projectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
        if ($projectMetadata) {
            $projectTitle = $projectMetadata['name'];
            $generalStatsTitle = "$projectTitle Classification Statistics";
            $jsTargetProjectId = "var targetProjectId = $targetProjectId;";
        }
    }
}
if (!$projectMetadata) {
    unset($targetProjectId);
}


// BUILD ALL FORM SELECT OPTIONS AND RADIO BUTTONS
// PROJECT SELECT
$userAdministeredProjects = find_administered_projects($DBH, $adminLevel, $userId, TRUE);
foreach ($userAdministeredProjects as $singeUserAdministeredProject) {
    $optionProjectId = $singeUserAdministeredProject['project_id'];
    $optionProjectName = $singeUserAdministeredProject['name'];
    $optionProjectDescription = $singeUserAdministeredProject['description'];
    $projectSelectHTML .= "<option title=\"$optionProjectDescription\" value=\"$optionProjectId\"";
    if ($projectMetadata && ($optionProjectId == $targetProjectId)) {
        $projectSelectHTML .= ' selected ';
    }
    $projectSelectHTML .= ">$optionProjectName</option>";
}

if ($projectMetadata) {
    $userGroupProgressHTML = "<h2>$projectTitle Image Group Progress</h2>";
    $imageGroupsInProjectQuery = "SELECT COUNT(*) FROM image_group_metadata WHERE project_id = $targetProjectId";
    $imageGroupsInProjectResult = $DBH->query($imageGroupsInProjectQuery);
    $imageGroupsInProject = $imageGroupsInProjectResult->fetchColumn();
    if ($imageGroupsInProject > 0) {
        $userGroupProgressHTML .= <<<EOL
            <p>The $projectTitle project contains <span class="userData">$imageGroupsInProject</span> image groups.</p>
            <input type="button" id="igButtonAllGroupsTargeted"class="formInputStyle" value="Download Classifications For All Image Groups By All Targeted Users"><br>
            <input type="button" id="igButtonAllGroupsNonTargeted"class="formInputStyle" value="Download Classifications For All Image Groups By Non-Targeted Users"><br>
            <input type="button" id="igButtonAllGroupsAllUsers"class="formInputStyle" value="Download Classifications For All Image Groups By All iCoast Users"><br>
            <input type="button" id="igButtonAllProjectClassificationsLessAllGroupsTargeted"class="formInputStyle" value="Download All $projectTitle Classifications Excluding Those From Image Groups By All Targeted Users"><br>
            <hr>
                
EOL;

        $jsButtonFunctions .= <<<EOL
            $('#igButtonAllGroupsTargeted').click(function() {
                window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&dataSubset=imageGroupClassificationsByTargetedUserGroups";
            });
            $('#igButtonAllGroupsNonTargeted').click(function() {
                window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&dataSubset=imageGroupClassificationsByNonTargetedUserGroups";
            });
            $('#igButtonAllGroupsAllUsers').click(function() {
                window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&dataSubset=imageGroupClassificationsByAllUsers";
            });
            $('#igButtonAllProjectClassificationsLessAllGroupsTargeted').click(function() {
                window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&dataSubset=allProjectClassificationsLessImageGroupsByLinkedUserGroups";
            });

EOL;

        $userGroupQuery = "
    SELECT
        u.encrypted_email,
        u.encryption_data,
        count(ann.annotation_id) AS classifications_completed,
        igm.image_group_id,
        igm.name,
        igm.description,
        (FLOOR(ig.group_range / show_every_nth_image)) AS image_group_count
    FROM user_groups ug
    INNER JOIN users u ON ug.user_id = u.user_id
    INNER JOIN user_group_assignments uga ON ug.user_group_id = uga.user_group_id
    INNER JOIN image_groups ig ON uga.image_group_id = ig.image_group_id
    INNER JOIN image_group_metadata igm ON ig.image_group_id = igm.image_group_id
    LEFT JOIN annotations ann ON ug.user_id = ann.user_id AND (ann.image_id BETWEEN ig.image_id AND (ig.image_id + (ig.group_range - 1)) AND ann.annotation_completed = 1)
    WHERE igm.project_id = $targetProjectId
    GROUP BY igm.image_group_id, ug.user_id
    ORDER BY igm.image_group_id, classifications_completed DESC";

        $userGroupResult = $DBH->query($userGroupQuery);
        $userGroupProgress = $userGroupResult->fetchAll(PDO::FETCH_ASSOC);

        $currentUserGroup = '';
        $firstTable = true;
        foreach ($userGroupProgress as $usersProgress) {
            $userEmail = mysql_aes_decrypt($usersProgress['encrypted_email'], $usersProgress['encryption_data']);
            $formattedClassificationsCompleted = number_format($usersProgress['classifications_completed']);
            if ($currentUserGroup != $usersProgress['image_group_id']) {

                if (!$firstTable) {
                    $userGroupProgressHTML .= <<<EOL
                                </tbody>
                            </table>
                        </div>
                        <input type="button" id="igButtonTargetUsers$currentUserGroup"class="formInputStyle" value="Download Classifications For This Group By The Targeted Users"><br>
                        <input type="button" id="igButtonNonTargetUsers$currentUserGroup" class="formInputStyle" value="Download Classifications For This Group By Non-Targeted Users"><br>
                        <input type="button" id="igButtonAllUsers$currentUserGroup" class="formInputStyle" value="Download Classifications For This Group By All Users">
                        <hr>

EOL;
                    $jsButtonFunctions .= <<<EOL
                    $('#igButtonTargetUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByTargetedUserGroups";
                    });
                    $('#igButtonNonTargetUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByNonTargetedUserGroups";
                    });
                    $('#igButtonAllUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByAllUsers";
                    });

EOL;
                }
                $firstTable = false;
                $userGroupProgressHTML .= '<h3>' . $usersProgress['name'] . ' - ' . $usersProgress['image_group_count'] . ' Photos</h3>';
                $userGroupProgressHTML .= '<div class="progressTableWrapper"><table>';
                $userGroupProgressHTML .= "<thead><tr><th>User</th><th>Photos Completed</th></tr></thead><tbody>";
                if ($usersProgress['classifications_completed'] == $usersProgress['image_group_count']) {
                    $userGroupProgressHTML .= "<tr class='completed'><td>$userEmail</td><td>$formattedClassificationsCompleted</td></tr>";
                } else {
                    $userGroupProgressHTML .= "<tr><td>$userEmail</td><td>$formattedClassificationsCompleted</td></tr>";
                }
                $currentUserGroup = $usersProgress['image_group_id'];
            } else {
                if ($usersProgress['classifications_completed'] == $usersProgress['image_group_count']) {
                    $userGroupProgressHTML .= "<tr class='completed'><td>$userEmail</td><td>$formattedClassificationsCompleted</td></tr>";
                } else {
                    $userGroupProgressHTML .= "<tr><td>$userEmail</td><td>$formattedClassificationsCompleted</td></tr>";
                }
            }
        }
        $userGroupProgressHTML .= <<<EOL
                                </tbody>
                            </table>
                        </div>
                        <input type="button" id="igButtonTargetUsers$currentUserGroup"class="formInputStyle" value="Download Classifications For This Group By The Targeted Users"><br>
                        <input type="button" id="igButtonNonTargetUsers$currentUserGroup" class="formInputStyle" value="Download Classifications For This Group By Non-Targeted Users"><br>
                        <input type="button" id="igButtonAllUsers$currentUserGroup" class="formInputStyle" value="Download Classifications For This Group By All Users">

EOL;
        $jsButtonFunctions .= <<<EOL
                    $('#igButtonTargetUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByTargetedUserGroups";
                    });
                    $('#igButtonNonTargetUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByNonTargetedUserGroups";
                    });
                    $('#igButtonAllUsers$currentUserGroup').click(function() {
                        window.location = "ajax/csvGenerator.php?dataSource=imageGroupClassifications&targetProjectId=$targetProjectId&targetImageGroupId=$currentUserGroup&dataSubset=imageGroupClassificationsByAllUsers";
                    });

EOL;
    } else { // END if ($imageGroupsInProject > 0)
        $userGroupProgressHTML .= "<p>This project has no associated Image Groups.</p>";
    } // END if ($imageGroupsInProject > 0) ELSE
} // END if $projectMetadata.





$jQueryDocumentDotReadyCode = $jsButtonFunctions;