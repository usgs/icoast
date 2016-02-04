<?php

require_once('../includes/globalFunctions.php');
require_once('../includes/adminFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

ini_set('memory_limit', '64M');

$userData = authenticate_user($DBH, TRUE, TRUE, TRUE);

if (isset($_GET['collectionId'])) {
    $collectionId = $_GET['collectionId'];
    settype($collectionId, 'integer');

    $collection = retrieve_entity_metadata($DBH, $collectionId, 'collection');
    $collection['collectionDates'] = array();
    if (!empty($collection)) {

        $collectionImageCountQuery = "
            SELECT COUNT(*)
            FROM images
            WHERE collection_id = {$collection['collection_id']}
            AND is_globally_disabled = 0
        ";
        $collectionImageCountResult = run_prepared_query($DBH, $collectionImageCountQuery);
        $collection['imageCount'] = number_format($collectionImageCountResult->fetchColumn());

        $collectionDateLimitsQuery = "
                SELECT *
                FROM images
                WHERE collection_id = {$collection['collection_id']}
                    AND (
                            image_time =
                            (SELECT MIN(image_time)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']})
                        OR
                            image_time =
                            (SELECT MAX(image_time)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']})
                        )
                ORDER BY image_time ASC
            ";
        $collectionDateLimitsResult = run_prepared_query($DBH, $collectionDateLimitsQuery);
        $collectionDateLimits = $collectionDateLimitsResult->fetchAll(PDO::FETCH_ASSOC);
        $collection['startDate'] = utc_to_timezone($collectionDateLimits[0]['image_time'], 'd M Y', $collectionDateLimits[0]['longitude']);
        $collection['endDate'] = utc_to_timezone($collectionDateLimits[1]['image_time'], 'd M Y', $collectionDateLimits[1]['longitude']);

        $collectionGeoLimitsQuery = "
                SELECT *
                FROM images
                WHERE collection_id = {$collection['collection_id']}
                    AND (
                            position_in_collection =
                            (SELECT MIN(position_in_collection)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']})
                        OR
                            position_in_collection =
                            (SELECT MAX(position_in_collection)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']})
                        )
                ORDER BY position_in_collection ASC
            ";
        $collectionGeoLimitsResult = run_prepared_query($DBH, $collectionGeoLimitsQuery);
        $collectionGeoLimits = $collectionGeoLimitsResult->fetchAll(PDO::FETCH_ASSOC);
        $collection['startLocation'] = build_image_location_string($collectionGeoLimits[0], TRUE);
        $collection['endLocation'] = build_image_location_string($collectionGeoLimits[1], TRUE);

        $collectionCaptureDateQuery = "
            SELECT DISTINCT(cast(image_time as date)) as image_date
            FROM images
            WHERE collection_id = {$collection['collection_id']}
            ORDER BY image_date ASC";
        $collectionCaptureDateResult = run_prepared_query($DBH, $collectionCaptureDateQuery);
        $collectionDates = $collectionCaptureDateResult->fetchAll(PDO::FETCH_ASSOC);
        $collection['dateCount'] = count($collectionDates);
        foreach ($collectionDates as $collectionDate) {
            $imageDate = $collectionDate['image_date'];
            $dateLimitsQuery = "
                SELECT *
                FROM images
                WHERE collection_id = {$collection['collection_id']}
                    AND (
                            position_in_collection =
                            (SELECT MIN(position_in_collection)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']}
                                AND cast(image_time as date) = '$imageDate')
                        OR
                            position_in_collection =
                            (SELECT MAX(position_in_collection)
                            FROM images
                            WHERE collection_id = {$collection['collection_id']}
                                AND cast(image_time as date) = '$imageDate')
                        )
                ORDER BY position_in_collection ASC
            ";
            $dateLimitsResult = run_prepared_query($DBH, $dateLimitsQuery);
            $dateLimits = $dateLimitsResult->fetchAll(PDO::FETCH_ASSOC);
            $collection['collectionDates'][$imageDate]['formattedDate'] = utc_to_timezone($dateLimits[0]['image_time'], 'd M Y', $dateLimits[0]['longitude']);
            $collection['collectionDates'][$imageDate]['startLocation'] = build_image_location_string($dateLimits[0], TRUE);
            $collection['collectionDates'][$imageDate]['endLocation'] = build_image_location_string($dateLimits[1], TRUE);
            $collection['collectionDates'][$imageDate]['imagePreview'] = array();

            $imageQuery = "
                SELECT latitude, longitude
                FROM images
                WHERE collection_id = {$collection['collection_id']}
                AND cast(image_time as date) = '$imageDate'
                ORDER BY position_in_collection
            ";
            $imageResults = run_prepared_query($DBH, $imageQuery);
            $imagesOnDate = $imageResults->fetchAll(PDO::FETCH_NUM);
            $counter = 0;
            if (count($imagesOnDate) > 0) {
                $n = floor(count($imagesOnDate) / 10);
                if ($n == 0) {
                    $n = 1;
                }
                for ($i = 0; $i < count($imagesOnDate); $i = $i + $n) {
                    $collection['collectionDates'][$imageDate]['imagePreview'][] = array(
                        'longitude' => $imagesOnDate[$i][1],
                        'latitude' => $imagesOnDate[$i][0]
                    );
                    $counter++;
                }
            }
            $collection['collectionDates'][$imageDate]['imageCount'] = $counter;
        }


        print json_encode($collection);
    }
}