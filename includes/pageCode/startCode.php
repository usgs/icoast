<?php

$cssLinkArray[] = 'css/leaflet.css';
$cssLinkArray[] = 'css/markerCluster.css';
$cssLinkArray[] = 'css/leafletGeoSearch.css';

$javaScriptLinkArray[] = 'scripts/leaflet.js';
$javaScriptLinkArray[] = 'scripts/leafletMarkerCluster-min.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearch.js';
$javaScriptLinkArray[] = 'scripts/leafletGeoSearchProvider.js';

$jQueryDocumentDotReadyCode = '';


require_once('includes/userFunctions.php');
require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH);
//$userData =
//    retrieve_entity_metadata($DBH,
//                             181,
//                             'user');
$userId = $userData['user_id'];

$filtered = true;

$requestedProjectId =
    filter_input(INPUT_GET,
                 'requestedProjectId',
                 FILTER_VALIDATE_INT);
$requestedProjectMetadata =
    retrieve_entity_metadata($DBH,
                             $requestedProjectId,
                             'project');
if ($requestedProjectMetadata &&
    $requestedProjectMetadata['is_complete'] == 1
)
{
//    print 'RequestedProject<br>';
    if ($requestedProjectMetadata['is_public'] == 0)
    {
//        print 'RequestedProject Not Public<br>';
        $groupProjectAccessQuery = <<<MySQL
            SELECT DISTINCT 
                ugm.project_id
            FROM 
                user_groups ug
            LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
            LEFT JOIN projects p ON ugm.project_id = p.project_id
            WHERE 
                ug.user_id = $userId AND
                ugm.is_enabled = 1 AND
                p.is_complete = 1 AND
                ugm.project_id = {$requestedProjectMetadata['project_id']}
MySQL;
        $groupProjectAccessResult = $DBH->query($groupProjectAccessQuery);
        $hasPreviewAccess = $groupProjectAccessResult->fetchColumn();
        if ($hasPreviewAccess)
        {
//            print 'RequestedProject Has Preview<br>';
            $targetProject = $requestedProjectId;
        }
    }
    else
    {
//        print 'RequestedProject is Public<br>';
        $targetProject = $requestedProjectId;
//        print"$targetProject<br>";
    }
} else {
    $targetProject = null;
}

$variableContent = '';
$focusedProjectReminder = '';
$allProjects = array();

$allProjectsQuery = <<<MySQL
SELECT 
	*
FROM 
(
	(
		SELECT 
			project_id, 
			name 
		FROM projects 
		WHERE 
			is_public = 1 AND 
			is_complete = 1 
	)
	UNION
	(
		SELECT DISTINCT 
			ugm.project_id,
			concat(p.name, ' (Preview)')
		FROM 
			user_groups ug
		LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
		LEFT JOIN projects p ON ugm.project_id = p.project_id
		WHERE 
			ug.user_id = $userId AND
			ugm.is_enabled = 1 AND
			p.is_complete = 1 
	)
) AS t
GROUP BY
	t.project_id
ORDER BY 
    t.project_id DESC
MySQL;

foreach ($DBH->query($allProjectsQuery) as $row)
{
    $allProjects[] = $row;
}


$numberOfProjects = count($allProjects);
if ($numberOfProjects > 1)
{

    $projectInFocusQuery = '
        SELECT home_page_project
        FROM system';
    $projectInFocusResult =
        run_prepared_query($DBH,
                           $projectInFocusQuery);
    $projectInFocus = $projectInFocusResult->fetchColumn();

    if (!$targetProject)
    {
//        print 'No target project<br>';
        $lastAnnotatedProjectQuery = <<<MySQL
            SELECT p.project_id, p.is_public
            FROM annotations a
            LEFT JOIN projects p ON p.project_id = a.project_id
            WHERE 
                a.user_id = :userId AND 
                a.annotation_completed = 1
            ORDER BY initial_session_end_time DESC 
            LIMIT 1
MySQL;

        $lastAnnotatedProjectParams['userId'] = $userId;
        $STH =
            run_prepared_query($DBH,
                               $lastAnnotatedProjectQuery,
                               $lastAnnotatedProjectParams);
        $targetProject = $STH->fetch(PDO::FETCH_ASSOC);
        if ($targetProject && !$targetProject['is_public'])
        {
//            print "Last annotated project is not public<br>";
            $groupProjectAccessQuery = <<<MySQL
                    SELECT DISTINCT ugm.project_id
                    FROM user_groups ug
                    LEFT JOIN user_group_metadata ugm ON ug.user_group_id = ugm.user_group_id
                    WHERE ug.user_id = :userId AND
                        ugm.is_enabled = 1
MySQL;
            $STH =
                run_prepared_query($DBH,
                                   $groupProjectAccessQuery,
                                   array('userId' => $userId));
            $groupAccess = false;
            while ($groupProjectAccessId = $STH->fetchColumn())
            {

                if ($groupProjectAccessId == $targetProject['project_id'])
                {
                    $groupAccess = true;
                }
            }
            if ($groupAccess)
            {
//                print "User has preview access<br>";
                $targetProject = $targetProject['project_id'];
            } else {
//                print "Preview access deneied<br>";
                $targetProject = false;
            }
        } else {
            $targetProject = $targetProject['project_id'];
        }
    }



//    print "Target Project Id = $targetProject<br>";
    if ($targetProject && ($targetProject != $projectInFocus))
    {
        $projectInFocusMetadata =
            retrieve_entity_metadata($DBH,
                                     $projectInFocus,
                                     'project');
        $focusedProjectReminder =
            "<p class=\"focusedProjectTextHighlight\">Don't forget to check out our current focused project, <a href=\"start.php?requestedProjectId=$projectInFocus\">{$projectInFocusMetadata['name']}</a>.</p>";
    }

    $projectSelectOptionHTML = "";
    if ($targetProject)
    {
        for ($i = 0; $i < $numberOfProjects; $i++)
        {
            if ($allProjects[$i]['project_id'] == $targetProject)
            {
                $projectId = $allProjects[$i]['project_id'];
                $projectName = $allProjects[$i]['name'];
                $projectSelectOptionHTML .= "<option value=\"$projectId\">$projectName</option>\r\n";
                unset($allProjects[$i]);
            }
        }
    }
    else
    {
        for ($i = 0; $i < $numberOfProjects; $i++)
        {
            if ($allProjects[$i]['project_id'] == $projectInFocus)
            {
                $projectId = $allProjects[$i]['project_id'];
                $projectName = $allProjects[$i]['name'];
                $projectSelectOptionHTML .= "<option value=\"$projectId\">$projectName</option>\r\n";
                unset($allProjects[$i]);
            }
        }
    }

    foreach ($allProjects as $project)
    {
        $id = $project['project_id'];
        $name = $project['name'];
        $projectSelectOptionHTML .= "<option value=\"$id\">$name</option>\r\n";
    }
    $projectSelectionHTML = <<<EOL

            <div>
                <span id="selectedProjectTitle">Current Project:</span>
                <select class="formInputStyle" id="projectSelect" name="projectId" title="Selecting a new project
                    from this list will cause iCoast to pick a new random image from the new project for you to tag.">
                  $projectSelectOptionHTML
                </select>
            </div>


EOL;
}
else
{
    if ($numberOfProjects == 1)
    {
        $projectId = $allProjects[0]['project_id'];
        $projectName = $allProjects[0]['name'];
        $projectSelectionHTML = <<<EOL
            <p id="selectedProjectTitle">Current Project: $projectName</p>

EOL;
    }
    else
    {
        $projectSelectionHTML = <<<EOL
      <h2>No Projects Available</h2>
      <p>At this time there are no projects available for annotation in iCoast.</p>
      <p>Please check back at a later date for exciting new coastal imagery.</p>
EOL;
    }
}

if ($numberOfProjects >= 1)
{
    $projectMetadata =
        retrieve_entity_metadata($DBH,
                                 $projectId,
                                 'project');
    $newRandomImageId =
        random_post_image_id_generator($DBH,
                                       $projectId,
                                       $filtered,
                                       $projectMetadata['post_collection_id'],
                                       $projectMetadata['pre_collection_id'],
                                       $userId,
                                       true);
    // Find post image metadata $postImageMetadata
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty')
    {
        $newRandomImageId =
            random_post_image_id_generator($DBH,
                                           $projectId,
                                           $filtered,
                                           $projectMetadata['post_collection_id'],
                                           $projectMetadata['pre_collection_id'],
                                           $userId);
    }
    if ($newRandomImageId == 'allPoolAnnotated' || $newRandomImageId == 'poolEmpty' || $newRandomImageId === false)
    {
        exit("An error was detected while generating a new image. $newRandomImageId");
    }
    if (!$newRandomImageMetadata =
        retrieve_entity_metadata($DBH,
                                 $newRandomImageId,
                                 'image')
    )
    {
        //  Placeholder for error management
        exit("Image $newRandomImageId not found in Database");
    }
    $newRandomImageLatitude = $newRandomImageMetadata['latitude'];
    $newRandomImageLongitude = $newRandomImageMetadata['longitude'];
    $newRandomImageLocation = build_image_location_string($newRandomImageMetadata);
    $newRandomImageDisplayURL =
        "images/collections/{$newRandomImageMetadata['collection_id']}/main/{$newRandomImageMetadata['filename']}";
    $newRandomImageAltTag = "An oblique image of the United States coastline taken near $newRandomImageLocation.";


    $variableContent = <<<EOL
$projectSelectionHTML
$focusedProjectReminder
<div id="randomPostImagePreviewWrapper">
    <p>Here is a random photo near<br><span id="projectName" class="captionTitle">$newRandomImageLocation</span></p>
    <div>
        <img src="$newRandomImageDisplayURL" alt="$newRandomImageAltTag" title="This random image has been
             chosen for you to tag next. Either accept the image, request a new random image, choose your
             own image from the map or switch projects (if available) using the buttons on the right."
             height="250" width="384">
    </div>
</div>



<div id="randomPostImageControls">


    <div class="singleNavButtonWrapper">
        <p>Tag This<br>Random Photo</p>
        <button class="clickableButton" type="button" id="tagButton"
                title="Using this button will load the classification page using the random image shown on the left.">
            <img src="images/system/checkmark.png" height="232" width="232" alt="Image of a dice indicating
                 that this button causes iCoast to randomly select an image to display">
        </button>
    </div>
    <div id="navOptionDivider">
        <p>OR</p>
    </div>


        <div id="stackedNavButtons">
            <p>Select an Option Below</p>
            <div class="stackedNavButtonWrapper">
                <label for="randomButton">
                    Find a New Random Photo
                </label>
                <button class="clickableButton" type="button" id="randomButton"
                        title="Using this button will cause iCoast to pick a new random image from your chosen
                            project for you to tag.">
                    <img src="images/system/dice.png" height="64" width="64" alt="Image of a dice indicating
                         that this button causes iCoast to randomly select an image to display">
                </button>
            </div>
            <div class="stackedNavButtonWrapper">
                <label for="mapButton">
                    Find a Photo from the Map
                </label>
                <button class="clickableButton" type="button" id="mapButton"
                        title="Using this button will cause iCoast to display a map of a section of the US coast from
                        which you can choose an image to tag.">
                    <img src="images/system/map.png" height="64" width="64" alt="Image of a map and push pin
                         indicating that this button causes iCoast to display a map from which you can choose an image
                         from your selected project to tag.">
                </button>
            </div>
        </div>
</div>

EOL;

    require("includes/mapNavigator.php");

    $variableContent .= $mapHTML;

    $javaScript = "$mapScript";

    $jQueryDocumentDotReadyCode .= <<<EOL
    $mapDocumentReadyScript

    if ($('#projectSelect').length) {
        $('#projectSelect').prop('selectedIndex', 0);
    }

EOL;
}
else
{
    $variableContent = $projectSelectionHTML;
}

$embeddedCSS = <<<EOL
        .focusedProjectTextHighlight {
            font-weight: bold;
        }
        
        #projectSelect {
            min-width: 200px;
            max-width: 350px;
        }
        
        #selectedProjectTitle {
            position: relative;
            top: 3px;
            display: inline-block;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .stackedNavButtonWrapper:first-of-type {
            margin-top: 40px;
        }
EOL;
        