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

function userEmailSort($array1, $array2) {
    return strcasecmp($array1['email'], $array2['email']);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

$allUserStatsHTML = '';
$selectedUserStatsHTML = '';
$classificationTimeGraphContentHTML = '';
$mapHTML = '';
$jsPhotoArray = '';
$jsTargetUserId = '';
$jsTargetProjectId = '';
$jsCompleteClassificationsPresent = "var completeClassificationsPresent = false;";
$mapJavascript = '';

// Determine if a user Id has been specified.
if (isset($_GET['targetUserId'])) {
    settype($_GET['targetUserId'], 'integer');
    if (!empty($_GET['targetUserId'])) {
        $targetUserId = $_GET['targetUserId'];
        $jsTargetUserId = "var targetUserId = $targetUserId;";
    }
}

// Determine if a project Id has been specified.
if (isset($targetUserId) && isset($_GET['targetProjectId'])) {
    settype($_GET['targetProjectId'], 'integer');
    if (!empty($_GET['targetProjectId'])) {
        $targetProjectId = $_GET['targetProjectId'];
    }
}

//Determine project metadata and set HTML text variables for project or system.
if (isset($targetProjectId)) {
    $targetProjectMetadata = retrieve_entity_metadata($DBH, $targetProjectId, 'project');
    $targetProjectName = $targetProjectMetadata['name'];
}
if (!empty($targetProjectName)) {
    $jsTargetProjectId = "var targetProjectId = $targetProjectId;";
    $queryProjectAndClause .= 'AND a.project_id = :targetProjectId';
    $userStatsParams['targetProjectId'] = $targetProjectId;
    $queryTargetText = "in the $targetProjectName project";
    $queryTargetTitleText = "in the $targetProjectName Project";
    $mapTitle = "Locations Of All User Classifications in the $targetProjectName Project";
} else {
    $queryProjectAndClause .= '';
    $userStatsParams = array();
    $queryTargetText = "in iCoast";
    $queryTargetTitleText = "in iCoast";
    $mapTitle = "Locations Of All User Classifications in iCoast";
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



//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build a user selection list
$userArray = array();
$userAtoDArray = array();
$userEtoJArray = array();
$userKtoOArray = array();
$userPtoTArray = array();
$userUtoZArray = array();
$userNumericArray = array();

$patternAtoD = '/^[a-dA-D]/';
$patternEtoJ = '/^[e-jE-J]/';
$patternKtoO = '/^[k-oK-O]/';
$patternPtoT = '/^[p-tP-T]/';
$patternUtoZ = '/^[u-zU-Z]/';
$patternNumeric = '/^[0-9]/';

$masterUserList = array();

// Retrieve all users and store in $masterUserList array.
$userQuery = "SELECT encrypted_email, encryption_data, user_id FROM users ORDER BY encrypted_email";
foreach ($DBH->query($userQuery, PDO::FETCH_ASSOC) as $result) {
    $masterUserList[$result['user_id']] = $result;
}

//Loop through the $masterUserListArray, decrypt account name and store in the array, then use regular
// expressions to sort the names into letter grouped arrays.
foreach ($masterUserList as &$icoastUser) {
    $icoastUser['email'] = mysql_aes_decrypt($icoastUser['encrypted_email'], $icoastUser['encryption_data']);
    if (preg_match($patternAtoD, $icoastUser['email']) === 1) {
        $userAtoDArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    } else if (preg_match($patternEtoJ, $icoastUser['email']) === 1) {
        $userEtoJArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    } else if (preg_match($patternKtoO, $icoastUser['email']) === 1) {
        $userKtoOArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    } else if (preg_match($patternPtoT, $icoastUser['email']) === 1) {
        $userPtoTArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    } else if (preg_match($patternUtoZ, $icoastUser['email']) === 1) {
        $userUtoZArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    } else if (preg_match($patternNumeric, $icoastUser['email']) === 1) {
        $userNumericArray[] = array(
            'email' => $icoastUser['email'],
            'id' => $icoastUser['user_id']
        );
    }
}

// Sort the letter group arrays by user account.
usort($userAtoDArray, 'userEmailSort');
usort($userEtoJArray, 'userEmailSort');
usort($userKtoOArray, 'userEmailSort');
usort($userPtoTArray, 'userEmailSort');
usort($userUtoZArray, 'userEmailSort');
usort($userNumericArray, 'userEmailSort');

// Loop through each letter group array to build the option tags for the user selection form.
// Also checks to see if the acount in the group is the specified target user and sets the account name variable.
foreach ($userAtoDArray as $icoastUser) {
    $userArray[1] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[1] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[1] .= '>' . $icoastUser['email'] . "</option>\n\r";
}
foreach ($userEtoJArray as $icoastUser) {
    $userArray[2] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[2] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[2] .= '>' . $icoastUser['email'] . "</option>\n\r";
}
foreach ($userKtoOArray as $icoastUser) {
    $userArray[3] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[3] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[3] .= '>' . $icoastUser['email'] . "</option>\n\r";
}
foreach ($userPtoTArray as $icoastUser) {
    $userArray[4] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[4] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[4] .= '>' . $icoastUser['email'] . "</option>\n\r";
}
foreach ($userUtoZArray as $icoastUser) {
    $userArray[5] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[5] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[5] .= '>' . $icoastUser['email'] . "</option>\n\r";
}
foreach ($userNumericArray as $icoastUser) {
    $userArray[6] .= '<option value="' . $icoastUser['id'] . '"';
    if (isset($targetUserId) && $targetUserId == $icoastUser['id']) {
        $userArray[6] .= ' selected="selected"';
        $targetUserAccount = $icoastUser['email'];
    }
    $userArray[6] .= '>' . $icoastUser['email'] . "</option>\n\r";
}

$userArray = json_encode($userArray);

// IF A USER HAS NOT BEEN SPECIFIED SHOW SYSTEM STATS ONLY
if (!isset($targetUserAccount)) {
    $statsTitle = "Statistics for All iCoast Users";
    $allUserStatsHTML .= '<table class="adminStatisticsTable">';

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//  Determine the number of users in iCoast
    $numberUsersResult = $DBH->query("SELECT COUNT(*) FROM users");
    $numberUsers = $numberUsersResult->fetchColumn();
    $allUserStatsHTML .= '<tr>'
            . '<td class="userData">' . number_format($numberUsers) . '<td> users are registered in iCoast</td>'
            . '</td>'
            . '</tr>'
            . '</table>'
            . '<input type="button" id="allUsersDownload" class="clickableButton" title="This button will give you the option to save all user data to a CSV file on your hard drive for further analysis with other tools." value="Download All User Details in CSV Format">';

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//  Determine the number of users per Crowd Type
    $crowdTypeStats = array();

    $usersPerCrowdTypeQuery = 'SELECT COUNT(u.user_id) AS count_users, ct.crowd_type_id, ct.crowd_type_name '
            . 'FROM crowd_types ct '
            . 'LEFT JOIN users u ON ct.crowd_type_id = u.crowd_type '
            . 'GROUP BY ct.crowd_type_id '
            . 'UNION '
            . "SELECT COUNT(user_id) AS count_users, 0 AS crowd_type_id, 'Other' AS crowd_type_name "
            . 'FROM users '
            . 'WHERE crowd_type = 0';
    $usersPerCrowdTypeResults = $DBH->query($usersPerCrowdTypeQuery);
    while ($row = $usersPerCrowdTypeResults->fetch(PDO::FETCH_ASSOC)) {
        if ($row['crowd_type_id'] != 0) {
            $crowdTypeStats[$row['crowd_type_name']] = $row['count_users'];
        } else {
            $crowdTypeStats['Other'] = $row['count_users'];
        }
    }
    $allUserStatsHTML .= '<h3>Users By Crowd Type</h3>'
            . '<table class="adminStatisticsTable twoColumnDividedTable">'
            . '<thead>'
            . '<tr>'
            . '<th>User Count</td><th>Crowd Type</td>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';
    foreach ($crowdTypeStats as $crowd => $userCount) {
        $allUserStatsHTML .= '<tr>'
                . '<td class="userData">' . $userCount . '</td><td>' . $crowd . '</td>'
                . '</tr>';
    }
    $allUserStatsHTML .= '</tbody>'
            . '</table>';
} // END !isset($targetUserAccount)
// IF A USER ACCOUNT HAS BEEN SPECIFIED SHOW USER DETAILS AND STATS
else {
    $userStatsParams['targetUserId'] = $targetUserId;
    $statsTitle = "Details and Statistics for $targetUserAccount $queryTargetText";

    // SHOW DETAILS TABLE
    $userAccountMetadata = retrieve_entity_metadata($DBH, $targetUserId, 'users');
    $crowdType = crowdTypeConverter($DBH, $userAccountMetadata['crowd_type'], $userAccountMetadata['other_crowd_type']);
    $timeZone = timeZoneIdToTextConverter($userAccountMetadata['time_zone']);
    $formattedLastLoggedInTime = formattedTime($userAccountMetadata['last_logged_in_on'], $userAccountMetadata['time_zone'], TRUE);
    $formattedAccoutCreatedOnTime = formattedTime($userAccountMetadata['account_created_on'], $userAccountMetadata['time_zone'], TRUE);
    $selectedUserStatsHTML .= <<<EOL
            <h3>User Profile Details</h3>
            <table class="adminStatisticsTable">
                <tbody>
                    <tr>
                        <td>Crowd Type:</td>
                        <td class="userData">$crowdType</td>
                    </tr>
                    <tr>
                        <td>Affiliation:</td>
                        <td class="userData">{$userAccountMetadata['affiliation']}</td>
                    </tr>
                    <tr>
                        <td>Time Zone:</td>
                        <td class="userData">$timeZone</td>
                    </tr>
                    <tr>
                        <td>Last Logged In:</td>
                        <td class="userData">$formattedLastLoggedInTime</td>
                    </tr>
                    <tr>
                        <td>Account Created On:</td>
                        <td class="userData">$formattedAccoutCreatedOnTime</td>
                    </tr>
                </tbody>
            </table>

            <h3 id="classificationStatsHeader">Classification Statistics $queryTargetTitleText</h3>
EOL;

// START TO DETERMINE USER SPECIFIC CLASSIFICATION STATS
    $allUserClassificationsQuery = "SELECT COUNT(*)"
            . "FROM annotations a "
            . "WHERE user_id = :targetUserId $queryProjectAndClause";
    $allUserClassificationResult = run_prepared_query($DBH, $allUserClassificationsQuery, $userStatsParams);
    $classificationCount = $allUserClassificationResult->fetchColumn();

    if ($classificationCount > 0) {
        $classificationCount = number_format($classificationCount);

        $completeClassificationsQuery = "SELECT COUNT(*)"
                . "FROM annotations a "
                . "WHERE user_id = :targetUserId AND annotation_completed = 1 $queryProjectAndClause";
        $completeClassificationResult = run_prepared_query($DBH, $completeClassificationsQuery, $userStatsParams);
        $completeClassificationCount = $completeClassificationResult->fetchColumn();

        if ($completeClassificationCount > 0) {
            $completeClassificationsDetected = TRUE;
            $jsCompleteClassificationsPresent = "var completeClassificationsPresent = true;";
        } else {
            $completeClassificationsDetected = FALSE;
        }
        $completeClassificationCount = number_format($completeClassificationCount);
        if ($completeClassificationCount > 0) {
            $percentCompleteClassifications = ' (' . number_format(($completeClassificationCount / $classificationCount) * 100, 1) . '%)';
        } else {
            $percentCompleteClassifications = ' (0.0%)';
        }

        $incompleteClassificationsQuery = "SELECT COUNT(*)"
                . "FROM annotations a "
                . "WHERE user_id = :targetUserId AND annotation_completed = 0 AND user_match_id IS NOT NULL $queryProjectAndClause";
        $incompleteClassificationResult = run_prepared_query($DBH, $incompleteClassificationsQuery, $userStatsParams);
        $incompleteClassificationCount = number_format($incompleteClassificationResult->fetchColumn());
        if ($incompleteClassificationCount > 0) {
            $percentIncompleteClassifications = ' (' . number_format(($incompleteClassificationCount / $classificationCount) * 100, 1) . '%)';
        } else {
            $percentIncompleteClassifications = ' (0.0%)';
        }

        $unstartedClassificationsQuery = "SELECT COUNT(*)"
                . "FROM annotations a "
                . "WHERE user_id = :targetUserId AND user_match_id IS NULL $queryProjectAndClause";
        $unstartedClassificationResult = run_prepared_query($DBH, $unstartedClassificationsQuery, $userStatsParams);
        $unstartedClassificationCount = number_format($unstartedClassificationResult->fetchColumn());
        if ($unstartedClassificationCount > 0) {
            $percentUnstartedClassifications = ' (' . number_format(($unstartedClassificationCount / $classificationCount) * 100, 1) . '%)';
        } else {
            $percentUnstartedClassifications = ' (0.0%)';
        }

        if (empty($targetProjectName)) {
            $distinctPhotosQuery = "SELECT COUNT(DISTINCT image_id)"
                    . "FROM annotations "
                    . "WHERE user_id = :targetUserId AND annotation_completed = 1";
            $distinctPhotoResult = run_prepared_query($DBH, $distinctPhotosQuery, $userStatsParams);
            $distinctPhotoCount = number_format($distinctPhotoResult->fetchColumn());
        }



        $selectedUserStatsHTML .= <<<EOL
            <p>For project specific statistics select a project using the following list (optional).</p>
            <form method="get" autocomplete="off" action="#classificationStatsHeader">
                <div class="formFieldRow">
                    <label for="analyticsProjectSelection">Project: </label>
                    <select id="analyticsProjectSelection" class="formInputStyle" name="targetProjectId">
                        $projectSelectHTML
                    </select>
                    <input type="submit" class="clickableButton" value="Select Project">
                </div>
                <input type="hidden" name="targetUserId" value="$targetUserId" />
            </form>
            <table class="adminStatisticsTable">
                <tbody>
                    <tr title="This is the total number of classifications the user was shown either within iCoast as a whole or the selected project. This includes complete, incomplete, and unstarted classifications (broken down below).">
                        <td class="userData">$classificationCount</td>
                        <td>Total classifications by this user $queryTargetText</td>
                    </tr>
                    <tr title="This is the number of classifications the user completed either within iCoast as a whole or within the selected project.  The percentage is of the total classifications in iCoast as a whole or in the selected project.">
                        <td class="userData">$completeClassificationCount</td>
                        <td>Complete classifications by this user $queryTargetText $percentCompleteClassifications</td>
                    </tr>
                    <tr title="This is the number of classifications the user did not complete either within iCoast as a whole or the selected project. An incomplete classification is one in which the user was displayed and image and they selected a pre-event matching image. They may have completed some tasks but did not click the 'Done' button on the final task to indicate completion of the classification. This number excludes unstarted classifications (see below).  The percentage is of the total classifications in iCoast as a whole or in the selected project.">
                        <td class="userData">$incompleteClassificationCount</td>
                        <td>Incomplete classifications by this user $queryTargetText $percentIncompleteClassifications</td>
                    </tr>
                    <tr title="This is the number of classifications the user did not start either within iCoast as a whole or the selected project. An unstarted classification is one in which the user was displayed a post-event image but did not image match it to a pre-event image or complete any of the tasks. The percentage is of the total classifications in iCoast as a whole or in the selected project.">
                        <td class="userData">$unstartedClassificationCount</td>
                        <td>Unstarted classifications by this user $queryTargetText $percentUnstartedClassifications</td>
                    </tr>

EOL;
        if (isset($distinctPhotoCount)) {
            $selectedUserStatsHTML .= <<<EOL
                    <tr title="Some photos can be resused in other projects and therefore the user can classify the same photo twice. This number is the number of distinct photos the user has completed a classification for. Multiple classifications of one photo from seperate projects are counted only once.">
                        <td class="userData">$distinctPhotoCount</td>
                        <td>Distinct photos completed by this user in iCoast</td>
                    </tr>

EOL;
        }
        $selectedUserStatsHTML .= <<<EOL
                </tbody>
            </table>
            <input type="button" id="allUserClassificationDownload" class="clickableButton disabledClickableButton" title="This button will give you the option to save all classifications data for the selected project (filtered by user if selected) to a CSV file on your hard drive for further analysis with other tools. If this button is unavailable then you wither have not selected a specific project or there are no complete classifications to download for the chosen project."value="Download All User Specific Classifications For This Project In CSV Format" disabled>

EOL;



























        $userClassificationMappingQuery = "SELECT i.image_id, i.thumb_url, i.latitude, i.longitude, i.city, i.state, a.annotation_completed, a.user_match_id, a.project_id, p.name "
                . "FROM annotations a "
                . "LEFT JOIN images i ON a.image_id = i.image_id "
                . "LEFT JOIN projects p ON a.project_id = p.project_id "
                . "WHERE a.user_id = :targetUserId $queryProjectAndClause ";
        $userClassificationMappingResult = run_prepared_query($DBH, $userClassificationMappingQuery, $userStatsParams);
        $userClassificationMappingData = $userClassificationMappingResult->fetchAll(PDO::FETCH_ASSOC);
        $jsPhotoArray = json_encode($userClassificationMappingData);
        $jsPhotoArray = 'var photoArray = ' . $jsPhotoArray;
        $mapHTML = <<<EOL
        <h3>$mapTitle</h3>
        <div class="adminMapWrapper">
            <div id="userClassificationLocationMap" class="adminMap"></div>
                <div class="adminMapLegend">
                    <div class="adminMapLegendRow">
                      <p>ZOOM IN TO SEE<br>INDIVIDUAL PHOTOS</p>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/clusterLegendIcon.png" alt="Image of the map cluster symbol"
                            width="24" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Clustering of Photos</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/greenMarker.png" alt="Image of a green map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Complete Classification</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/yellowMarker.png" alt="Image of a yellow map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Incomplete Classification</p>
                      </div>
                    </div>
                    <div class="adminMapLegendRow">
                      <div class="adminMapLegendRowIcon">
                        <img src="images/system/redMarker.png" alt="Image of a red map marker pin"
                            width="13" height="24" title="">
                      </div>
                      <div class="adminMapLegendRowText">
                        <p>Unstarted Classification</p>
                      </div>
                    </div>
                </div>
            </div>
        </div>
EOL;

        $mapJavascript = <<<EOL
                var map = L.map('userClassificationLocationMap', {maxZoom: 16});
                L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
                }).addTo(map);
                L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);

                L.control.scale({
                    position: 'topright',
                    metric: false
                }).addTo(map);
                markers = L.markerClusterGroup({
                    disableClusteringAtZoom: 9,
                    maxClusterRadius: 60
                });
                var unstarted = L.icon({
                    iconUrl: 'images/system/redMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [0, -35]
                });
                var incomplete = L.icon({
                    iconUrl: 'images/system/yellowMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [0, -35]
                });
                var complete = L.icon({
                    iconUrl: 'images/system/greenMarker.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [0, -35]
                });
                $.each(photoArray, function(key, photo) {
                    if (photo.annotation_completed == 1) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: complete});
                    } else if (photo.annotation_completed == 0 && photo.user_match_id == null) {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: unstarted});
                    } else {
                        var marker = L.marker([photo.latitude, photo.longitude], {icon: incomplete});
                    }
                    marker.bindPopup('Image ID: <a href="photostats.php?photoId=' + photo.image_id + '">' + photo.image_id + '</a><br>'
                        + 'Location: ' + photo.city + ', ' + photo.state + '<br>'
                        + 'Classified through the <a href="classificationStats.php?targetProjectId=' + photo.project_id + '">' + photo.name + '</a> project<br>'
                        + '<a href="photostats.php?photoId=' + photo.image_id + '"><img class="mapMarkerImage" width="167" height="109" src="' + photo.thumb_url + '" /></a>', {closeOnClick: true});
                    markers.addLayer(marker);
                });
                map.fitBounds(markers.getBounds());
                markers.addTo(map);
EOL;
    } else {
        if (!empty($targetProjectName)) {
            $selectedUserStatsHTML .= <<<EOL
            <p>For project specific statistics select a project using the following list (optional).</p>
            <form method="get" autocomplete="off" action="#classificationStatsHeader">
                <div class="formFieldRow">
                    <label for="analyticsProjectSelection">Project: </label>
                    <select id="analyticsProjectSelection" class="formInputStyle" name="targetProjectId">
                        $projectSelectHTML
                    </select>
                    <input type="submit" class="clickableButton" value="Select Project">
                </div>
                <input type="hidden" name="targetUserId" value="$targetUserId" />
            </form>
                <p>This user has not classified any photos $queryTargetText at this time.</p>
EOL;
        } else {
            $selectedUserStatsHTML .= '<p>This user has not classified any photos in iCoast at this time.</p>';
        }
    }
} // END if(!isset($targetUserAccount) ELSE

if (!isset($targetUserAccount) || (isset($targetUserAccount) && $completeClassificationsDetected)) {

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Determine average annotation time


    $upperTimeLimitMins = 0;
    $jsUpperTimeLimit = '';

    if (isset($_GET['upperTimeLimit'])) {
        settype($_GET['upperTimeLimit'], 'integer');
        if (!empty($_GET['upperTimeLimit'])) {
            $upperTimeLimitMins = $_GET['upperTimeLimit'];
        }
    }

    if ($upperTimeLimitMins > 0 && $upperTimeLimitMins <= 100) {
        $maxX = $upperTimeLimitMins;
    } else {
        $maxX = 100;
    }
    $jsUpperTimeLimit = "var upperTimeLimit = $maxX;";

    if (isset($_GET['userYScaleLimit'])) {
        settype($_GET['userYScaleLimit'], 'integer');
        if (!empty($_GET['userYScaleLimit'])) {
            switch ($_GET['userYScaleLimit']) {
                case 5;
                case 10;
                case 15;
                case 25;
                case 50;
                case 100;
                case 250;
                case 500;
                case 1000;
                case 5000;
                case 10000;
                case 25000;
                case 50000;
                case 250000;
                case 1000000;
                    $maxY = $_GET['userYScaleLimit'];
            }
        }
    }

    $classificationCount = 0;
    $timeTotal = 0;
    $excessiveTimeCount = 0;
    $nonDisplayableClassificationCount = 0;
    $maxUnrestrictedClassificationTime = 0;
    $longestClassification = 0;
    $shortestClassification = 0;
    $durationFrequencyCount = array();
    $annotationTimeGraphHTML = '';

    for ($i = 1; $i <= $maxX; $i++) {
        $durationFrequencyCount[$i] = 0;
    }
    if (isset($targetUserId)) {
        $classificationTimeGraphHTML = "<h3 id=\"timeGraphHeader\">Time Taken For User To Complete Classifications $queryTargetTitleText</h3>";
    } else {
        $classificationTimeGraphHTML = '<h3 id="timeGraphHeader">Time Taken For All Users To Complete Classifications in iCoast</h3>';
    }

    $upperTimeLimitSelectHTML = '<option value="0">None</option>';
    for ($i = 10; $i <= 100; $i+= 10) {
        $upperTimeLimitSelectHTML .= '<option value="' . $i . '">' . $i . '</option>';
    }

    if (isset($_GET['upperTimeLimit'])) {
        $strReplaceSearchString = '="' . $_GET['upperTimeLimit'] . '">';
        $strReplaceReplaceString = '="' . $_GET['upperTimeLimit'] . '" selected>';
        $upperTimeLimitSelectHTML = str_replace($strReplaceSearchString, $strReplaceReplaceString, $upperTimeLimitSelectHTML);
    }

    $upperYScaleLimitOptionsHTML = <<<EOL
            <option value="0">Auto</option>
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="250">250</option>
            <option value="500">500</option>
            <option value="1000">1000</option>
            <option value="5000">5000</option>
            <option value="10000">10000</option>
            <option value="25000">25000</option>
            <option value="50000">50000</option>
            <option value="250000">250000</option>
            <option value="1000000">1000000</option>
EOL;
    if (isset($maxY)) {
        $strReplaceSearchString = '="' . $maxY . '">';
        $strReplaceReplaceString = '="' . $maxY . '" selected>';
        $upperYScaleLimitOptionsHTML = str_replace($strReplaceSearchString, $strReplaceReplaceString, $upperYScaleLimitOptionsHTML);
    }


    $avgTimeQuery = "SELECT initial_session_start_time, initial_session_end_time FROM annotations WHERE annotation_completed = 1 AND annotation_completed_under_revision = 0";
    if (isset($targetUserId)) {
        $avgTimeQuery .= " AND user_id = :userId";
        $avgTimeParams['userId'] = $targetUserId;
        $formattedTimeText = "has been spent by the user classifying photos in iCoast";
        $formattedAverageClassificationTimeText = "is the average time the user spent classifying photos in iCoast";
        $formattedLongestClassificationTimeText = "is the longest the user spent classifying a photo in iCoast";
        $formattedShortestClassificationTimeText = "is the shortest the user spent classifying a photo in iCoast";
        $noClassificationTextHTML = 'No classifications have been completed by this user within iCoast at this time.</p><p>No data is available to display.';
    }

    if (!empty($targetProjectName)) {
        $avgTimeQuery .= " AND project_id = :projectId";
        $avgTimeParams['projectId'] = $targetProjectId;
        $formattedTimeText = "has been spent by the user classifying photos $queryTargetText";
        $formattedAverageClassificationTimeText = "is the average time the user spent classifying photos $queryTargetText";
        $formattedLongestClassificationTimeText = "is the longest the user spent classifying a photo $queryTargetText";
        $formattedShortestClassificationTimeText = "is the shortest the user spent classifying a photo $queryTargetText";
        $noClassificationTextHTML = 'No classifications have been completed by this user in the $queryTargetText at this time.</p><p>No data is available to display.';
    }

    if (!isset($formattedTimeText)) {
        $formattedTimeText = "has been spent by all users classifying photos in iCoast";
        $formattedAverageClassificationTimeText = "is the average time spent by all users classifying photos in iCoast";
        $formattedLongestClassificationTimeText = "is the longest a user spent classifying a photo in iCoast";
        $formattedShortestClassificationTimeText = "is the shortest a user spent classifying a photo in iCoast";
        $noClassificationTextHTML = 'No classifications have been completed within iCoast at this time.</p><p>No data is available to display.';
    }

    if (!isset($avgTimeParams)) {
        $avgTimeParams = array();
    }

    $avgTimeResults = run_prepared_query($DBH, $avgTimeQuery, $avgTimeParams);
    while ($classification = $avgTimeResults->fetch(PDO::FETCH_ASSOC)) {

        $startTime = strtotime($classification['initial_session_start_time']);
        $endTime = strtotime($classification['initial_session_end_time']);
        $timeDelta = $endTime - $startTime;
        if ($timeDelta > $maxUnrestrictedClassificationTime) {
            $maxUnrestrictedClassificationTime = $timeDelta;
        }
        if ($upperTimeLimitMins == 0 && $timeDelta >= 6000) {
            $nonDisplayableClassificationCount++;
        }
        if ($upperTimeLimitMins == 0 || $timeDelta < ($upperTimeLimitMins * 60)) {
            $timeTotal += $timeDelta;
            $classificationCount++;
            if (ceil($timeDelta / 60) <= $maxX) {
                $durationFrequencyCount[ceil($timeDelta / 60)] ++;
            }
            if ($timeDelta > $longestClassification) {
                $longestClassification = $timeDelta;
            }
            if ($timeDelta < $shortestClassification || $shortestClassification == 0) {
                $shortestClassification = $timeDelta;
            }
        } else {
            $excessiveTimeCount++;
        }
    }



    if ($classificationCount > 0) {
        $averageTime = $timeTotal / $classificationCount;

        $formattedTimeTotal = convertSeconds($timeTotal);
        $formattedAverageClassificationTime .= convertSeconds($averageTime) . "<br>";
        $formattedLongestClassificationTime .= convertSeconds($longestClassification) . "<br>";
        $formattedShortestClassificationTime .= convertSeconds($shortestClassification) . "<br>";
        $formattedMaxUnrestrictedClassificationTime = ceil($maxUnrestrictedClassificationTime / 600) * 10;
        $formattedExcessiveTimeCount = number_format($excessiveTimeCount);
        $formattedNonDisplayableClassificationCount = number_format($nonDisplayableClassificationCount);


        if ($upperTimeLimitMins != 0) {
            $excessiveTimeCountHTML .= ''
                    . '<tr>'
                    . ' <td class="userData">' . $formattedExcessiveTimeCount . '</td><td>';
            if ($excessiveTimeCount == 1) {
                $excessiveTimeCountHTML .= 'classification was excluded from the calculations and time chart as it exceeded the specified time limit';
            } else {
                $excessiveTimeCountHTML .= 'classifications were excluded from the calculations and time chart as they exceeded the specified time limit';
            }
            $excessiveTimeCountHTML .= ''
                    . ' </td>'
                    . '</tr>';
        }

        if ($excessiveTimeCount > 0 && $maxUnrestrictedClassificationTime < 6000) {
            $suggestedCutOffTime = '<p>Enter ' . $formattedMaxUnrestrictedClassificationTime . ' minute(s) or more to include all available data.<p>';
        } else if ($excessiveTimeCount > 0 && $maxUnrestrictedClassificationTime > 6000) {
            $suggestedCutOffTime = '<p>Select "None" in the cut-off limit to include all classifications in the calculations below.<p>';
        }

        if ($nonDisplayableClassificationCount > 0) {
            $nonDisplayableCountHTML = ''
                    . '<tr>'
                    . ' <td class="userData">' . $formattedNonDisplayableClassificationCount . '</td>'
                    . '<td>';
            if ($nonDisplayableClassificationCount == 1) {
                $nonDisplayableCountHTML .= 'classification exceeded the displayable limit of the time chart and is not shown';
            } else {
                $nonDisplayableCountHTML .= 'classifications exceeded the displayable limit of the time chart and are not shown';
            }
            $nonDisplayableCountHTML .= ''
                    . '</td>'
                    . '<td></td>'
                    . '</tr>';
        } else {
            $nonDisplayableCountHTML = '';
        }

        $pixelsPerBarWrapperWidth = floor(880 / $maxX);
        $pixelsPerBarWidth = $pixelsPerBarWrapperWidth - 2;

        if ($maxX > 30) {
            $rangeOfXScaleMarks = ceil($maxX / 30);
            while (30 % $rangeOfXScaleMarks != 0) {
                $rangeOfXScaleMarks ++;
            }
            $numberOfXScaleMarks = $maxX / $rangeOfXScaleMarks;
            $xMarkWidth = $pixelsPerBarWrapperWidth * $rangeOfXScaleMarks;
        } else {
            $rangeOfXScaleMarks = 1;
            $xMarkWidth = $pixelsPerBarWrapperWidth;
        }

        if (!isset($maxY)) {
            $maxAnnotationTimeFrequency = max($durationFrequencyCount);
            if ($maxAnnotationTimeFrequency <= 5) {
                $maxY = 5;
            } else if ($maxAnnotationTimeFrequency <= 10) {
                $maxY = 10;
            } else if ($maxAnnotationTimeFrequency <= 15) {
                $maxY = 15;
            } else if ($maxAnnotationTimeFrequency <= 25) {
                $maxY = 25;
            } else if ($maxAnnotationTimeFrequency <= 50) {
                $maxY = 50;
            } else if ($maxAnnotationTimeFrequency <= 100) {
                $maxY = 100;
            } else if ($maxAnnotationTimeFrequency <= 250) {
                $maxY = 250;
            } else if ($maxAnnotationTimeFrequency <= 500) {
                $maxY = 500;
            } else if ($maxAnnotationTimeFrequency <= 1000) {
                $maxY = 1000;
            } else if ($maxAnnotationTimeFrequency <= 5000) {
                $maxY = 5000;
            } else if ($maxAnnotationTimeFrequency <= 10000) {
                $maxY = 10000;
            } else if ($maxAnnotationTimeFrequency <= 25000) {
                $maxY = 25000;
            } else if ($maxAnnotationTimeFrequency <= 50000) {
                $maxY = 50000;
            } else if ($maxAnnotationTimeFrequency <= 250000) {
                $maxY = 250000;
            } else {
                $maxY = 1000000;
            }
        }
//    $maxY = 1000000;
        $rangeOfYScaleMarks = $maxY / 5;
        $pixelsPerFrequencyCount = 250 / $maxY;

        $xScaleHTML = '';
        $yRangeX1000 = FALSE;
        $yScaleHTML = '';
        $yScaleTitleSuffix = '';
        $yScaleTitleStyling = '';


        for ($i = 1; $i <= $maxX; $i++) {
            $iMinus1 = $i - 1;
            if ($durationFrequencyCount[$i] > 0) {
                $barHeightInPx = floor($durationFrequencyCount[$i] * $pixelsPerFrequencyCount);
                $overflowColor = '';
                if ($barHeightInPx > 250) {
                    $overflowColor = ' timeGraphBarOverflow';
                    $barHeightInPx = 250;
                }

                $classificationTimeGraphContentHTML .= ''
                        . '<div class="timeGraphBarWrapper" '
                        . 'style="width: ' . $pixelsPerBarWrapperWidth . 'px" '
                        . 'title="' . $durationFrequencyCount[$i] . ' classification(s) between ' . $iMinus1 . ' and ' . $i . ' minute(s)">'
                        . ' <div class="timeGraphBar' . $overflowColor . '" '
                        . '     style="width: ' . $pixelsPerBarWidth . 'px; height: ' . $barHeightInPx . 'px">'
                        . ' </div>'
                        . '</div>';
            } else {
                $classificationTimeGraphContentHTML .= ''
                        . '<div class="timeGraphBarWrapper" '
                        . 'style="width: ' . $pixelsPerBarWrapperWidth . 'px"'
                        . 'title="' . $durationFrequencyCount[$i] . ' classification(s) between ' . $iMinus1 . ' and ' . $i . ' minute(s)">'
                        . '</div>';
            }
            if ($i % $rangeOfXScaleMarks == 0) {
                $xScaleHTML .= ''
                        . '<div class="xScaleDivision" '
                        . 'style="width: ' . $xMarkWidth . 'px">'
                        . ' <div class="xScaleMark"></div>'
                        . ' <div class="xScaleNumberWrapper">'
                        . $i
                        . ' </div>'
                        . '</div>';
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $yScaleValue = $i * $rangeOfYScaleMarks;
            if ($i == 1 && $yScaleValue > 200) {
                $yRangeX1000 = TRUE;
                $yScaleTitleSuffix = '(x 1000)';
                $yScaleTitleStyling = 'top: 270px';
            }
            if ($yRangeX1000) {
                $yScaleValue = $yScaleValue / 1000;
            }
            $yScaleDivisionPosition = (($i - 1) * 50) + 1;
            $yScaleHTML .= ''
                    . '<div class="yScaleDivision" '
                    . ' style="bottom:' . $yScaleDivisionPosition . 'px">'
                    . '     <div class="yScaleNumberWrapper">'
                    . "         $yScaleValue"
                    . '     </div>'
                    . '     <div class="yScaleMark"></div>'
                    . '</div>';
        }

        $classificationTimeGraphHTML .= <<<EOL
                    <form method="get" autocomplete="off" id="timeGraphControls" action="#timeGraphHeader">
                        <div class="formFieldRow">
                            <label for="timeGraphUpperLimit">Cut-off time limit for time calculations in minutes: </label>
                            <select id="timeGraphUpperLimit" name="upperTimeLimit" class="formInputStyle">
                                $upperTimeLimitSelectHTML
                            </select>
                        </div>
                        <div class="formFieldRow">
                            <label for="timeGraphYScaleLimit">Y-axis upper limit (max 1,000,000): </label>
                            <select id="timeGraphYScaleLimit" name="userYScaleLimit" class="formInputStyle">
                                $upperYScaleLimitOptionsHTML
                            </select>
                        </div>
EOL;

        if (isset($targetUserId)) {
            $classificationTimeGraphHTML .= '<input type="hidden" name="targetUserId" value="' . $targetUserId . '" />';
            if (!empty($targetProjectName)) {
                $classificationTimeGraphHTML .= '<input type="hidden" name="targetProjectId" value="' . $targetProjectId . '" />';
            }
        }
        $classificationTimeGraphHTML .= <<<EOL
                        <input type="submit" class="clickableButton" value="Change Time Calculation / Display Limits">
                    </form>
                <p>All time statistics are restricted by the cut-off time limit if specified above.<br>
                    Classifictions above this limit are not used in time calculations below.<p>
                $suggestedCutOffTime
                <table class="adminStatisticsTable">
                    <tbody>
                        $excessiveTimeCountHTML
                        $nonDisplayableCountHTML
                    </tbody>
                </table>
                    <table class="adminStatisticsTable">
                        <tbody>
                            <tr title="This is the total amount of time that has been spent classifying photos either by the specified user or all users and for the specified project or all projects in iCoast. The scope of the calculation is dependent on the filters and limits specified on this page. Only classifications that are complete and were completed in a single user session are included in the calculation.">
                                <td class="userData">
                                    $formattedTimeTotal
                                </td>
                                <td>
                                    $formattedTimeText
                                </td>
                            </tr>
                            <tr title="This is the average amount of time spent on a single classification either by the specified user or all users and for the specified project or all projects in iCoast. The scope of the calculation is dependent on the filters and limits specified on this page. Only classifications that are complete and were completed in a single user session are included in the calculation.">
                                <td class="userData">
                                    $formattedAverageClassificationTime
                                </td>
                                <td>
                                    $formattedAverageClassificationTimeText
                                </td>
                            </tr>
                            <tr title="This is the longest amount of time that has been spent classifying a single photo either by the specified user or all users and for the specified project or all projects in iCoast. The scope of the calculation is dependent on the filters and limits specified on this page. Only classifications that are complete and were completed in a single user session are included in the calculation.">
                                <td class="userData">
                                    $formattedLongestClassificationTime
                                </td>
                                <td>
                                    $formattedLongestClassificationTimeText
                                </td>
                            </tr>
                            <tr title="This is the shortest amount of time that has been spent classifying a single photo either by the specified user or all users and for the specified project or all projects in iCoast. The scope of the calculation is dependent on the filters and limits specified on this page. Only classifications that are complete and were completed in a single user session are included in the calculation.">
                                <td class="userData">
                                    $formattedShortestClassificationTime
                                </td>
                                <td>
                                    $formattedShortestClassificationTimeText
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div id = "classificationTimeGraphWrapper">
                        <div id = "ctgUpperWrapper">
                            <div id = "ctgYBorder">
                                <div id = "ctgYTitleWrapper">
                                    <div id = "ctgYTitle" style = "$yScaleTitleStyling">
                                        Number of Classifications $yScaleTitleSuffix
                                    </div>
                                </div>
                                <div id = "ctgYScaleWrapper">
                                    $yScaleHTML
                                </div>
                            </div>
                            <div id = "ctgContent">
                                $classificationTimeGraphContentHTML
                            </div>
                        </div>
                        <div id = "ctgLowerWrapper">
                            <div id = "ctgDeadSpace">
                                <div>0</div>
                                <div>0</div>
                            </div>
                            <div id = "ctgXBorder">
                                <div id = "ctgXScaleWrapper">
                                    $xScaleHTML
                                </div>
                                <div id = "ctgXTitleWrapper">
                                    Time In Minutes
                                </div>
                            </div>
                        </div>
                    </div>
                  <input type="button" id="TimeChartDataDownload" class="clickableButton" title="This button allows you to save the time chart data shown above (with filters applied) to a CSV file on your hard drive for further analysis with other tools."value="Download Time Chart Data In CSV Format">
                <p>Only complete classifications that were started and finished in the same session are shown in the Time chart, CSV File, and used in the calculation of the total and average classification time.</p>
EOL;
    } else {
        $classificationTimeGraphHTML .= $noClassificationTextHTML;
    }
}

$cssLinkArray[] = "http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css";
$cssLinkArray[] = "css/markerCluster.css";
$javaScriptLinkArray[] = "http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js";
$javaScriptLinkArray[] = "scripts/leafletMarkerCluster-min.js";

$javaScript = <<<EOL
            var userList = $userArray;
            $jsPhotoArray;
            $jsTargetUserId
            $jsTargetProjectId
            $jsCompleteClassificationsPresent
            $jsUpperTimeLimit

EOL;

$jQueryDocumentDotReadyCode = <<<EOL
                $('#analyticsUserSelection').empty();
                $('#analyticsUserSelection').append(userList[1]);
                $('#analyticsUserSelection').append(userList[2]);
                $('#analyticsUserSelection').append(userList[3]);
                $('#analyticsUserSelection').append(userList[4]);
                $('#analyticsUserSelection').append(userList[5]);
                $('#analyticsUserSelection').append(userList[6]);
                $('#userIdSubmit').addClass('disabledClickableButton').attr('disabled', '');
                var selectedUserValue = $('#analyticsUserSelection option[selected]').val();
                if (typeof (selectedUserValue) === 'undefined') {
                    $('#analyticsUserSelection').prop('selectedIndex', -1);
                }

                if (typeof (targetUserId) === 'undefined') {
                    $('#userIdClear').hide();
                }

                if (typeof (targetProjectId) !== 'undefined' && completeClassificationsPresent) {
                    $('#allUserClassificationDownload').removeClass('disabledClickableButton').removeAttr('disabled');
                }

                $('#analyticsUserAccountStartingLetter').change(function() {
                    var selectedLetterGroup = $(this).val();
                    if (selectedLetterGroup === '0') {
                        $('#analyticsUserSelection').empty();
                        $('#analyticsUserSelection').append(userList[1]);
                        $('#analyticsUserSelection').append(userList[2]);
                        $('#analyticsUserSelection').append(userList[3]);
                        $('#analyticsUserSelection').append(userList[4]);
                        $('#analyticsUserSelection').append(userList[5]);
                        $('#analyticsUserSelection').append(userList[6]);
                        $('#analyticsUserSelection').prop("selectedIndex", -1);
                        $('#analyticsUserSelection').removeClass('disabledClickableButton').removeAttr('disabled');
                        $('#userIdSubmit').addClass('disabledClickableButton').attr('disabled', '');
                    } else {
                        $('#analyticsUserSelection').empty();
                        if (typeof (userList[selectedLetterGroup]) != 'undefined') {
                            $('#analyticsUserSelection').append(userList[selectedLetterGroup]);
                            $('#analyticsUserSelection').prop("selectedIndex", -1);
                            $('#analyticsUserSelection').removeClass('disabledClickableButton').removeAttr('disabled');
                            $('#userIdSubmit').addClass('disabledClickableButton').attr('disabled', '');
                        } else {
                            $('#analyticsUserSelection, #userIdSubmit').addClass('disabledClickableButton').attr('disabled', '');
                        }
                    }
                });

                $('#analyticsUserSelection').change(function() {
                    var selectedUser = $(this).val();
                    if (typeof (selectedUser != 'undefined')) {
                        $('#userIdSubmit').removeClass('disabledClickableButton').removeAttr('disabled');

                    }
                    $('#propertyOrderTable tbody').empty();
                    $('#propertyOrderTable tbody').append(tagOrderInGroupData[selectedGroup]['tableHTML']);
                    $('#editTagOrder').empty();
                    $('#editTagOrder').append(tagOrderInGroupData[selectedGroup]['newOrderSelectHTML']);
                });

                $("#userSelectionForm").submit(function() {
                    $(this).find("select").filter('#analyticsUserAccountStartingLetter').remove();
                });

                $('#allUserClassificationDownload').click(function() {
                    if (typeof (targetProjectId) !== 'undefined') {
                        window.location = "ajax/csvGenerator.php?dataSource=allClassifications&targetProjectId=" + targetProjectId + "&targetUserId=" + targetUserId;
                    } else {
                        window.location = "ajax/csvGenerator.php?dataSource=classificationOverviewByUser";
                    }

                });

                $('#allUsersDownload').click(function() {
                    window.location = "ajax/csvGenerator.php?dataSource=allUsers";
                });

                $('#TimeChartDataDownload').click(function() {
                    var target = "ajax/csvGenerator.php?dataSource=timeChart";
                    if (typeof (targetProjectId) !== 'undefined' && typeof (targetUserId) !== 'undefined') {
                        target += "&targetProjectId=" + targetProjectId + "&targetUserId=" + targetUserId;
                    } else if (typeof (targetProjectId) !== 'undefined') {
                        target += "&targetProjectId=" + targetProjectId;
                    } else if (typeof (targetUserId) !== 'undefined') {
                        target += "&targetUserId=" + targetUserId;
                    }
                    if (typeof (upperTimeLimit) !== 'undefined') {
                        target += "&upperTimeLimit=" + upperTimeLimit;
                    }

                    window.location = target;
                });
                $mapJavascript;
EOL;
