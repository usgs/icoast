<?php

//print "In mapUpdater<br>";
require_once('../includes/globalFunctions.php');
require_once('../includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

define("IMAGES_PER_MAP", 10);
$northernLimit = (is_numeric($_GET['north']) ? $_GET['north'] : null);
$southernLimit = (is_numeric($_GET['south']) ? $_GET['south'] : null);
$easternLimit = (is_numeric($_GET['east']) ? $_GET['east'] : null);
$westernLimit = (is_numeric($_GET['west']) ? $_GET['west'] : null);
$projectId = (is_numeric($_GET['projectId']) ? $_GET['projectId'] : null);
$userId = (is_numeric($_GET['userId']) ? $_GET['userId'] : null);
$currentImageId = (is_numeric($_GET['currentImageId']) ? $_GET['currentImageId'] : null);
$imagesToDisplay = Array();


$projectData =
    retrieve_entity_metadata($DBH,
                             $projectId,
                             'project');
if (!$projectData)
{
    exit;
}

$imagesInBoundsQuery = <<<MySQL
        SELECT 
            image_id, 
            filename, 
            latitude, 
            longitude, 
            feature, 
            city, 
            state, 
            collection_id, 
            position_in_collection
        FROM 
            images 
        WHERE 
            (latitude BETWEEN :southernLimit AND :northernLimit) AND
            (longitude BETWEEN :westernLimit AND :easternLimit) AND
            is_globally_disabled = 0 AND collection_id = {$projectData['post_collection_id']}
        ORDER BY 
            position_in_collection ASC
MySQL;
$imagesInBoundsParams = array(
    'southernLimit' => $southernLimit,
    'northernLimit' => $northernLimit,
    'westernLimit'  => $westernLimit,
    'easternLimit'  => $easternLimit
);

$STH =
    run_prepared_query($DBH,
                       $imagesInBoundsQuery,
                       $imagesInBoundsParams);
$imagesToDisplay = $STH->fetchAll(PDO::FETCH_ASSOC);

$annotatedImages = array();
$annotatedImagesQuery = <<<MySQL
    SELECT 
        image_id 
    FROM 
        annotations 
    WHERE 
        user_id = :userId AND 
        project_id = :projectId AND 
        annotation_completed = 1
MySQL;
$annotatedImagesParams = array(
    'userId'    => $userId,
    'projectId' => $projectId
);
$STH =
    run_prepared_query($DBH,
                       $annotatedImagesQuery,
                       $annotatedImagesParams);
$annotatedImagesResults = $STH->fetchAll(PDO::FETCH_ASSOC);

if (count($annotatedImagesResults) > 0)
{
    foreach ($annotatedImagesResults as $imageId)
    {
        $annotatedImages[] = $imageId['image_id'];
    }
}
//print 'Annotated Images';
//array_dump($annotatedImages);


$noImageMatchQuery = "SELECT post_image_id FROM matches WHERE " .
                     "post_collection_id = :postCollectionId AND pre_collection_id = :preCollectionId "
                     . "AND is_enabled = 0";
$noImageMatchParams = array(
    'postCollectionId' => $projectData['post_collection_id'],
    'preCollectionId'  => $projectData['pre_collection_id']
);
$STH =
    run_prepared_query($DBH,
                       $noImageMatchQuery,
                       $noImageMatchParams);
$noMatchImageResults = $STH->fetchAll(PDO::FETCH_ASSOC);
//$queryResult = run_database_query($query);
//print '$noMatchImageResults =' . count($noMatchImageResults);
$noMatchImageList = array();
if (count($noMatchImageResults) > 0)
{
    foreach ($noMatchImageResults as $imageMatchData)
    {
        $noMatchImageList[] = $imageMatchData['post_image_id'];
    }
}
//print 'No Match List';
//array_dump($noMatchImageList);

$userAssignedImageIdPool = null;
$userGroups =
    find_user_group_membership($DBH,
                               $userId,
                               $projectId,
                               true);
if ($userGroups)
{
//    print 'UserGroups <pre>';
//    print_r($userGroups);
//    print '</pre>';
    $imageGroups =
        find_assigned_image_groups($DBH,
                                   $userGroups,
                                   true);
    if ($imageGroups)
    {
//        print 'Image Groups <pre>';
//        print_r($imageGroups);
//        print '</pre>';
        $userAssignedImageIdPool =
            retrieve_image_id_pool($imageGroups,
                                   true,
                                   false);
//        print 'User Assigned Image Id Pool<pre>';
//        print_r($userAssignedImageIdPool);
//        print '</pre>';
        if (is_array($userAssignedImageIdPool) && count($userAssignedImageIdPool) > 0)
        {
//            print "Has Image Pool<br>";
            for ($i = 0; $i < count($userAssignedImageIdPool); $i++)
            {
//                print "$i: Checking for prior annotation" . $userAssignedImageIdPool[$i] . '<br>';
                if (has_user_annotated_image($DBH,
                                             $userAssignedImageIdPool[$i],
                                             $userId,
                                             $projectId) !== 0
                )
                {
//                    print 'REMOVING: user already annotated ' . $userAssignedImageIdPool[$i] . '<br>';
                    array_splice($userAssignedImageIdPool,
                                 $i,
                                 1);
                    $i--;
                }
            }
        }
    }
}


if (is_array($userAssignedImageIdPool) && count($userAssignedImageIdPool) > 0)
{
//    print "Has pool from assigned image groups<br>";
    $imagesToDisplayBackup = $imagesToDisplay;
    for ($i = 0; $i < count($imagesToDisplay); $i++)
    {
        if (!in_array($imagesToDisplay[$i]['image_id'],
                      $userAssignedImageIdPool)
        )
        {
//            echo '- REMOVING: Not in the assigned pool. ' .
//                 $imagesToDisplay[$i]['image_id'] .
//                 '<br>';
            array_splice($imagesToDisplay,
                         $i,
                         1);
            $i--;
        }
    }
    if (count($imagesToDisplay) == 0) {
//        print "Assigned images do not match the pool. Pool depleted. Restoring<br>";
        $imagesToDisplay = $imagesToDisplayBackup;
    } else {
//        print "At least one assigne dimage matched the pool.<br>";
        unset($imagesToDisplayBackup);
    }


    for ($i = 0; $i < count($imagesToDisplay); $i++)
    {

//        echo $i . ': ' . $imagesToDisplay[$i]['image_id'] . ' ';
        if (in_array($imagesToDisplay[$i]['image_id'],
                     $annotatedImages) ||
            in_array($imagesToDisplay[$i]['image_id'],
                     $noMatchImageList) ||
            $imagesToDisplay[$i]['image_id'] == $currentImageId
        )
        {
//            echo '- REMOVING: Already annotated, no match, current image or not in the assigned pool. ' .
//                 $imagesToDisplay[$i]['image_id'] .
//                 '<br>';
            array_splice($imagesToDisplay,
                         $i,
                         1);
            $i--;
        }
        else
        {
//            echo '- NO MATCH<br>';
        }
    }
}
else
{
    for ($i = 0; $i < count($imagesToDisplay); $i++)
    {

//        echo $i . ': ' . $imagesToDisplay[$i]['image_id'] . ' ';
        if (in_array($imagesToDisplay[$i]['image_id'],
                     $annotatedImages) ||
            in_array($imagesToDisplay[$i]['image_id'],
                     $noMatchImageList) ||
            $imagesToDisplay[$i]['image_id'] == $currentImageId
        )
        {
//            echo '- REMOVING: Already annotated, no match or current image. ' . $imagesToDisplay[$i]['image_id'] .
//                 '<br>';
            array_splice($imagesToDisplay,
                         $i,
                         1);
            $i--;
        }
        else
        {
//            echo '- KEEP: Not already annotated and has match.<br>';
        }
    }
}


for ($i = 0; $i < count($imagesToDisplay); $i++)
{
    $imagesToDisplay[$i]['image_url'] =
        "images/collections/{$imagesToDisplay[$i]['collection_id']}/main/{$imagesToDisplay[$i]['filename']}";
    $imagesToDisplay[$i]['location_string'] = build_image_location_string($imagesToDisplay[$i]);
    //  $imagesToDisplay[$i]['collation_number'] = $imagesPerMarker;
    array_splice($imagesToDisplay[$i],
                 4,
                 5);
}

echo json_encode($imagesToDisplay);



