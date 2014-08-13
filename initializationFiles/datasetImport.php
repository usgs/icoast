<?php
require('adminFunctions.php');

//////////
// => Custom sort function to sort miltidimensional array by timestamp
function date_sort($x, $y) {
    return $x[14] > $y[14];
}

//////////
// => Handle the dataset if it has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    //////////
    // => Development variables to be moved or replaced by release
    $dbmsServer = 'localhost';
    $dbmsUser = 'root';
    $dbmsPassword = '';
    $dbmsDatabase = "icoast";
    $dbc = new mysqli($dbmsServer, $dbmsUser, $dbmsPassword, $dbmsDatabase);
    $collectionId = $dbc->real_escape_string($_POST['collection']);
    $isDatasetEnabled = 1;
    // => Define variables and PHP settings
    ini_set('max_execution_time', 3600);
    Define('CONFIDENCE_THREASHOLD_IN_METERS', 55560); // 30 nm range on confidence
    Define('MAX_FEATURE_MATCH_RADIUS', 1600); // 1 mile range on features
    Define('MAX_POPULOUS_MATCH_RADIUS', 9650); // 6 mile range on populated city match
    $minLongitude = array();
    $maxLongitude = array();
    $minLatitude = array();
    $maxLatitude = array();
    $images = array();
    $numberOfDatasetsRows = 0;
    $name = $dbc->real_escape_string($_POST['name']);
    $description = $dbc->real_escape_string($_POST['description']);


    //////////
    // => Import CSV file, loop through data rows, process, format then insert $dataset array, sort
    // => by timestamp.
    $fp = fopen($_FILES['dataset']['tmp_name'], 'r');
    while ($csvRow = fgetcsv($fp)) {
	// Skip header row
	if ($csvRow[0] == "LONGITUDE") {
	    continue;
	}
	// Process UTC time columns into timestamp.
	$formattedDateTime = $csvRow[6] . ' ' . $csvRow[7] . ' ' . $csvRow[8] . ' ' .
		$csvRow[2];
	$imageTime = DateTime::createFromFormat('Y F j H_i_s', $formattedDateTime, new DateTimeZone('UTC'));
	$csvRow[13] = $imageTime->format('Y-m-d H:i:s');
	$csvRow[14] = $imageTime->getTimestamp();
	// Make imported string data safe for MySQL query inclusion.
	$csvRow[3] = $dbc->real_escape_string($csvRow[3]);
	$csvRow[4] = $dbc->real_escape_string($csvRow[4]);
	$csvRow[5] = $dbc->real_escape_string($csvRow[5]);

	// Determine if row contains the min/max values for latitude and longitude in dataset.
	// If so store the value and the row timestamp.
	if (empty($minLongitude)) {
	    $minLongitude = array($csvRow[14], $csvRow[0]);
	} else {
	    if ($csvRow[0] < $minLongitude[1]) {
		$minLongitude[0] = $csvRow[14];
		$minLongitude[1] = $csvRow[0];
	    }
	}
	if (empty($maxLongitude)) {
	    $maxLongitude = array($csvRow[14], $csvRow[0]);
	} else {
	    if ($csvRow[0] > $maxLongitude[1]) {
		$maxLongitude[0] = $csvRow[14];
		$maxLongitude[1] = $csvRow[0];
	    }
	}
	if (empty($minLatitude)) {
	    $minLatitude = array($csvRow[14], $csvRow[1]);
	} else {
	    if ($csvRow[1] < $minLatitude[1]) {
		$minLatitude[0] = $csvRow[14];
		$minLatitude[1] = $csvRow[1];
	    }
	}
	if (empty($maxLatitude)) {
	    $maxLatitude = array($csvRow[14], $csvRow[1]);
	} else {
	    if ($csvRow[1] > $maxLatitude[1]) {
		$maxLatitude[0] = $csvRow[14];
		$maxLatitude[1] = $csvRow[1];
	    }
	}

	// Reverse geocode the image using the geonames and geonames_counties tables.
	// First look for any features local to the image.
	$geonamesSelectQuery = "SELECT name, feature_code, county_code, state,
	    (6378137 * acos(cos(radians($csvRow[1])) * cos(radians(latitude))
		* cos(radians(longitude) - radians($csvRow[0]) ) + sin(radians($csvRow[1]))
		    * sin(radians(latitude)))) AS distance FROM geonames
		    WHERE feature_code NOT IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3')
		    HAVING distance < " . MAX_FEATURE_MATCH_RADIUS . " ORDER BY distance LIMIT 1;";
	if (!$geonamesSelectResults = $dbc->query($geonamesSelectQuery)) {
	    print $dbc->error . "<br />";
	    print '<br>' . $geonamesSelectQuery . '<br>';
	    exit;
	}
	// If a feature is found add it to the $csvRow array.
	if ($geonamesSelectResults->num_rows > 0) {
	    $feature = $geonamesSelectResults->fetch_assoc();
	    $csvRow[16] = $dbc->real_escape_string($feature['name']);
	    $csvRow[17] = $feature['feature_code'];
	    print "Feature Found: $csvRow[16], a $csvRow[17] at $csvRow[1], $csvRow[0]<br>";
	}
	// Next find the closest city to the image.
	$geonamesSelectQuery = "SELECT name, county_code, state,
	    (6378137 * acos(cos(radians($csvRow[1])) * cos(radians(latitude))
		* cos(radians(longitude) - radians($csvRow[0]) ) + sin(radians($csvRow[1]))
		    * sin(radians(latitude)))) AS distance FROM geonames
		    WHERE feature_code IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3') AND population = '1'
		    HAVING distance < " . MAX_POPULOUS_MATCH_RADIUS . " ORDER BY distance LIMIT 1;";
	if (!$geonamesSelectResults = $dbc->query($geonamesSelectQuery)) {
	    print $dbc->error . "<br />";
	    print '<br>' . $geonamesSelectQuery . '<br>';
	    exit;
	}
	// Add the nearest city to the $csvRow array.
	if ($geonamesSelectResults->num_rows > 0) {
	    $city = $geonamesSelectResults->fetch_assoc();
	    $geonamesCountiesSelectQuery = "SELECT county_name FROM geonames_counties
		WHERE state = '{$city['state']}' AND county_code = '{$city['county_code']}'";
	    if (!$geonamesCountiesSelectResults = $dbc->query($geonamesCountiesSelectQuery)) {
		print $dbc->error . "<br />";
		print '<br>' . $geonamesCountiesSelectQuery . '<br>';
		exit;
	    }
	    if ($geonamesCountiesSelectResults->num_rows > 0) {
		$county = $geonamesCountiesSelectResults->fetch_assoc();
		$csvRow[19] = $dbc->real_escape_string($county['county_name']);
	    }
	    $csvRow[18] = $dbc->real_escape_string($city['name']);
	    $csvRow[20] = $dbc->real_escape_string($city['state']);
	    print "City Found: $csvRow[18], $csvRow[19], $csvRow[20]  at $csvRow[1], $csvRow[0]<br><br>";
	} else {
	    // If no major city in range then find the closest minor city/neighborhood to the image.
	    $geonamesSelectQuery = "SELECT name, county_code, state,
	    (6378137 * acos(cos(radians($csvRow[1])) * cos(radians(latitude))
		* cos(radians(longitude) - radians($csvRow[0]) ) + sin(radians($csvRow[1]))
		    * sin(radians(latitude)))) AS distance FROM geonames
		    WHERE feature_code IN ('PPL', 'PPLA', 'PPLA2', 'PPLA3')
		    ORDER BY distance LIMIT 1;";
	    if (!$geonamesSelectResults = $dbc->query($geonamesSelectQuery)) {
		print $dbc->error . "<br />";
		print '<br>' . $geonamesSelectQuery . '<br>';
		exit;
	    }
	    // Add the nearest city to the $csvRow array.
	    if ($geonamesSelectResults->num_rows > 0) {
		$city = $geonamesSelectResults->fetch_assoc();
		$geonamesCountiesSelectQuery = "SELECT county_name FROM geonames_counties
		    WHERE state = '{$city['state']}' AND county_code = '{$city['county_code']}'";
		if (!$geonamesCountiesSelectResults = $dbc->query($geonamesCountiesSelectQuery)) {
		    print $dbc->error . "<br />";
		    print '<br>' . $geonamesCountiesSelectQuery . '<br>';
		    exit;
		}
		if ($geonamesCountiesSelectResults->num_rows > 0) {
		    $county = $geonamesCountiesSelectResults->fetch_assoc();
		    $csvRow[19] = $dbc->real_escape_string($county['county_name']);
		}
		$csvRow[18] = $dbc->real_escape_string($city['name']);
		$csvRow[20] = $dbc->real_escape_string($city['state']);
		print "Minor City / Neighborhood Found: $csvRow[18], $csvRow[19], $csvRow[20]  at $csvRow[1], $csvRow[0]<br><br>";
	    }
	}

	// Add $csvRow as value in $images multidimensional array.
	// $images[*][0] = CSV Field: Longitude
	// $images[*][1] = CSV Field: Latitude
	// $images[*][2] = CSV Field: UNUSED - UTC time of image capture (H_i_s format eg. 09_05_09)
	// $images[*][3] = CSV Field: Image filename
	// $images[*][4] = CSV Field: Full image URL
	// $images[*][5] = CSV Field: Full thumbnail URL
	// $images[*][6] = CSV Field: UNUSED - UTC year of image capture (Y format eg. 2013)
	// $images[*][7] = CSV Field: UNUSED - UTC month of image capture (F format eg. November)
	// $images[*][8] = CSV Field: UNUSED - UTC Day of image capture (j format eg. 5)
	// $images[*][9] = CSV Field: UNUSED - Pre Event Flag (either PRE or POST)
	// $images[*][10] = CSV Field: UNUSED - Event name
	// $images[*][11] = CSV Field: UNUSED - Geographical area of collection
	// $images[*][12] = CSV Field: UNUSED - US State of image area
	// $images[*][13] = Script Generated: MySQL formatted date/time (Y-m-d H:i:s format
	//  eg. 2013-08-27 13:51:45)
	// $images[*][14] = Script Generated: Unix timestamp of image capture
	// $images[*][15] = Script Generated: Image position in dataset
	// $images[*][16] = Script Generated: Name of a local feature (if any)
	// $images[*][17] = Script Generated: Code of local feature (if any)
	// $images[*][18] = Script Generated: Name of nearest city
	// $images[*][19] = Script Generated: Name of county
	// $images[*][20] = Script Generated: Name of state
	$images[] = $csvRow;
	// End WHILE csv get
    }
    // Sort images by date/time
    usort($images, 'date_sort');
    print "<h1>Finished Import GeoCoding</h1>";




    //////////
    // => Set image sequence numbers in $dataset array.
    $isClockwiseCostalProgression = TRUE; // Assume collection direction is clockwise
    $directionIndeterminate = TRUE; // Assume no clear direction
    $numberOfImagesRows = count($images);
    // Determine dataset area of coverage coverage
    $longitudeMidpoint = ($minLongitude[1] + $maxLongitude[1]) / 2;
    $latitudeMidpoint = ($minLatitude[1] + $maxLatitude[1]) / 2;
    // Determine dataset region
    if (($longitudeMidpoint >= -81.5 AND $latitudeMidpoint >= 26) OR
	    ($longitudeMidpoint >= -80.5 AND $latitudeMidpoint >= 25.5 AND
	    $latitudeMidpoint < 26)) {
	// Atlantic coast
	$regionId = 1;
	$directionIndeterminate = FALSE;
	// print 'Is Atlantic coast: \$regionId = $regionId<br />';
    } elseif (($longitudeMidpoint >= -84 AND $longitudeMidpoint < -81.5 AND
	    $latitudeMidpoint >= 26) OR ($longitudeMidpoint <= -81 AND
	    $latitudeMidpoint >= 25.5 AND $latitudeMidpoint < 26)) {
	// Eastern Gulf Coast (FL)
	$regionId = 3;
	$directionIndeterminate = FALSE;
	// print 'Is eastern Gulf of Mexico Coast (FL): \$regionId = $regionId<br />';
    } elseif ($longitudeMidpoint >= -94 AND $longitudeMidpoint < -84) {
	// Northern Gulf Coast
	$regionId = 4;
	$directionIndeterminate = FALSE;
	// print 'Is northern Gulf of Mexico Coast (FL, AL, LA, TX): \$regionId = $regionId<br />';
    } elseif ($longitudeMidpoint >= -110 AND $longitudeMidpoint < -94) {
	// Western Gulf Coast
	$regionId = 5;
	$directionIndeterminate = FALSE;
	// print 'Is western Gulf of Mexico Coast (TX): \$regionId = $regionId<br />';
    } elseif ($longitudeMidpoint >= -128 AND $longitudeMidpoint < -110) {
	// Pacific Coast (exc. Alaska)
	$regionId = 6;
	$directionIndeterminate = FALSE;
	// print 'Is Pacific coast (exc. Alaska): \$regionId = $regionId<br />';
    } else {
	// Florida Keys
	$regionId = 2;
	// print 'In The Keys!: \$regionId = $regionId<br />';
    }
    // Calculate number of degrees in 30 nautical miles at dataset latitude midpoint.
    $confidenceThreasholdInDegrees =
	    meters_to_degrees($latitudeMidpoint, CONFIDENCE_THREASHOLD_IN_METERS);
    // If direction is known use coastal orientation to set image sequence.
    if (!$directionIndeterminate AND $regionId != 4) { // N/S oriented coast (latitude)
	// Determine direction of image capture
	if ($maxLatitude[0] < $minLatitude[0]) {
	    // +ve delta indicates North to South direction
	    $deltaLatitude = $maxLatitude[1] - $minLatitude[1];
	} else {
	    // -ve delta indicates North to South direction
	    $deltaLatitude = $minLatitude[1] - $maxLatitude[1];
	}
	// Ensure dataset spans sufficient distance to have confidence in direction assumption
	if ($deltaLatitude < 0 - $confidenceThreasholdInDegrees['distInLat'] OR
		$deltaLatitude > $confidenceThreasholdInDegrees['distInLat']) {
	    // If going up (-ve difference) a east facing coast or down (+ve difference) a west
	    // facing coast then direction is anticlockwise.
	    if (($deltaLatitude > 0 AND ($regionId == 3 OR $regionId == 6)) OR
		    ($deltaLatitude < 0 AND ($regionId == 1 OR $regionId == 5))) {
		// print 'Is Anticlockwise<br />';
		$isClockwiseCostalProgression = FALSE;
	    }
	    // Set image sequence numbers
	    if ($isClockwiseCostalProgression) {
		for ($i = 0; $i < $numberOfImagesRows; $i++) {
		    $images[$i][15] = $i + 1;
		}
	    } else {
		for ($i = 0, $p = $numberOfImagesRows; $i < $numberOfImagesRows; $i++, $p--) {
		    $images[$i][15] = $p;
		}
	    }
	} else {
	    $directionIndeterminate = TRUE; // Insufficient distance spanned to determine direction
	    // print 'Not enough data!<br />';
	}
	// End IF $regioncode != 4
    } elseif (!$directionIndeterminate AND $regionId == 4) { // E/W oriented coast (longitude)
	// Determine direction of image capture
	if ($maxLongitude[0] < $minLongitude[0]) {
	    // +ve delta indicates East to West direction
	    $deltaLongitude = $maxLongitude[1] - $minLongitude[1];
	} else {
	    // ive delta indicates West to East direction
	    $deltaLongitude = $minLongitude[1] - $maxLongitude[1];
	}
	// Ensure dataset spans sufficient distance to have confidence in direction assumption
	if ($deltaLongitude < 0 - $confidenceThreasholdInDegrees['distInLon'] OR
		$deltaLongitude > $confidenceThreasholdInDegrees['distInLat']) {
	    // If going east (-ve difference) on the northern gulf coast direction is anticlockwise
	    if ($deltaLongitude < 0) {
		// print 'Is Anticlockwise<br />';
		$isClockwiseCostalProgression = FALSE;
	    }
	    // Set image sequence numbers
	    if ($isClockwiseCostalProgression) {
		for ($i = 0; $i < $numberOfImagesRows; $i++) {
		    $images[$i][15] = $i + 1;
		}
	    } else {
		for ($i = 0, $p = $numberOfImagesRows; $i < $numberOfImagesRows; $i++, $p--) {
		    $images[$i][15] = $p;
		}
	    }
	} else {
	    $directionIndeterminate = TRUE; // Insufficient distance spanned to determine direction
	    // print 'Not enough data!<br />';
	}
	// End ELSEIF $regioncode == 4
    }

    print "<h1>Finished building sequence numbers</h1>";

    //////////
    // => Determine dataset position in the established dataset region of the specified collection
    $positionInRegion = 1; // Assume the dataset is the first in the region
    // Query to pull data for existing datasets in matching region and collection as new import
    $datasetsSelectQuery = "SELECT dataset_id, position_in_region, lat_lon_position FROM datasets
	WHERE collection_id = $collectionId AND region_id = $regionId ORDER BY position_in_region";
    $datasetsQueryResult = $dbc->query($datasetsSelectQuery);
    // If other datasets exist in then determine the position in the region for the import
    if ($datasetsQueryResult->num_rows > 0) {
	$datasets = array();
	// Import found datasets into $datasets multidimensional array
	while ($datasetsRow = $datasetsQueryResult->fetch_assoc()) {
	    $datasets[$datasetsRow['position_in_region']] = array('id' =>
		$datasetsRow['dataset_id'], 'position' => $datasetsRow['lat_lon_position']);
	}
	$numberOfDatasetsRows = count($datasets);
	$positionInRegionFound = FALSE;
	// Find the new dataset's position within the existing datasets by comparing latitude or
	// longitude as required by costal configuration and incrementing in clockwise direction
	while (!$positionInRegionFound) {
	    if ($regionId == 1 OR $regionId == 5) { // East facing coast, increments south
		if (isset($datasets[$positionInRegion]['position']) AND
			$latitudeMidpoint < $datasets[$positionInRegion]['position']) {
		    $positionInRegion++;
		} else {
		    $positionInRegionFound = TRUE;
		}
	    } elseif ($regionId == 3 OR $regionId == 6) { // West facing coast, increments north
		if (isset($datasets[$positionInRegion]['position']) AND
			$latitudeMidpoint > $datasets[$positionInRegion]['position']) {
		    $positionInRegion++;
		} else {
		    $positionInRegionFound = TRUE;
		}
	    } elseif ($regionId == 4) { // South facing coast, increments west
		if (isset($datasets[$positionInRegion]['position']) AND
			$longitudeMidpoint < $datasets[$positionInRegion]['position']) {
		    $positionInRegion++;
		} else {
		    $positionInRegionFound = TRUE;
		}
	    }
	} // End WHILE !$positionInRegionFound
	// End IF $datasetsQueryResult->num_rows > 0
    }

    //////////
    // => Insert data into the datasets table
    // If the new dataset's position is nested among other existing datasets then build and
    // run an update query to increase the position_in_region for all higher positions making a
    // gap for the new data.
    if ($positionInRegion <= $numberOfDatasetsRows) {
	$rowsToUpdate = NULL;
	$datasetsUpdateQuery = 'UPDATE datasets SET position_in_region = CASE dataset_id';
	for ($i = $positionInRegion, $j = $i + 1; $i <= $numberOfDatasetsRows; $i++, $j++) {
	    $datasetsUpdateQuery .= ' WHEN ' . $datasets[$i]['id'] . " THEN '" . $j . "' ";
	    $rowsToUpdate .= $datasets[$i]['id'] . ',';
	}
	$rowsToUpdate = substr($rowsToUpdate, 0, strlen($rowsToUpdate) - 1);
	$datasetsUpdateQuery .= "END WHERE dataset_id IN ($rowsToUpdate);";
	$dbc->query($datasetsUpdateQuery);
    }
    // Build and run an Insert query to place the dataset metadata into the correct position in the
    // datasets table
    $datasetsInsertQuery = "INSERT INTO datasets (name, description, collection_id, is_enabled,
	region_id, position_in_region, rows_in_set, lat_lon_position) values ('$name',
	    '$description', $collectionId, $isDatasetEnabled, $regionId, $positionInRegion,
	    $numberOfImagesRows, ";
    // Use longitude for position data is on an E/W oriented coastline (Gulf Coast)
    if ($regionId != 4) {
	$datasetsInsertQuery .= $latitudeMidpoint . ');';
    } else {
	$datasetsInsertQuery .= $longitudeMidpoint . ');';
    }
    If (!$dbc->query($datasetsInsertQuery)) {
	print $dbc->error . "<br />";
	print '<br />Error updating datasets table with new dataset metadata.<br />' .
		$datasetsInsertQuery . '<br />';
	exit;
    }
    $datasetId = $dbc->insert_id;

    print "<h1>Finished determining dataset position and updating dataset table";

    //////////
    // => Insert data into the images table
    $imagesInsertQuery = "INSERT INTO images (filename, latitude, longitude, image_time,
		full_url, thumb_url, has_display_file, position_in_set, feature, feature_code,
		city, county, state, dataset_id) VALUES ";
    foreach ($images as $row) {
	$imagesInsertQuery .= "('" . $row[3] . "'";
	$imagesInsertQuery .= ", " . $row[1];
	$imagesInsertQuery .= ", " . $row[0];
	$imagesInsertQuery .= ", '" . $row[13] . "'";
	$imagesInsertQuery .= ", '" . $row[4] . "'";
	$imagesInsertQuery .= ", '" . $row[5] . "'";
	$imagesInsertQuery .= ", 0";
	$imagesInsertQuery .= ", " . $row[15];
	if (isset($row[16])) {
	    $imagesInsertQuery .= ", '" . $row[16] . "'";
	} else {
	    $imagesInsertQuery .= ", ''";
	}
	if (isset($row[17])) {
	    $imagesInsertQuery .= ", '" . $row[17] . "'";
	} else {
	    $imagesInsertQuery .= ", ''";
	}
	$imagesInsertQuery .= ", '" . $row[18] . "'";
	if (isset($row[19])) {
	    $imagesInsertQuery .= ", '" . $row[19] . "'";
	} else {
	    $imagesInsertQuery .= ", ''";
	}
	$imagesInsertQuery .= ", '" . $row[20] . "'";
	$imagesInsertQuery .= ", " . $datasetId . "),";
    }
    $imagesInsertQuery = substr_replace($imagesInsertQuery, ";", -1);
    If (!$dbc->query($imagesInsertQuery)) {
	print $dbc->error . "<br />";
	print '<br />Error updating images table with new dataset data.<br />' .
		$imagesInsertQuery . '<br />';
	exit;
    }

    print "<h1>Completed Import</h1>";

    //////////
    // => Close DB Connection - Move to footer.
    if (isset($dbc) AND !$dbc->connect_error) {
	$dbc->close();
    }



    //////////
    // => Debugging Output.
//    print '<h1>Dataset Overview</h1>';
//    print '$minLatitude = ' . $minLatitude[0] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $minLatitude[1] . '<br />';
//    print '$maxLatitude = ' . $maxLatitude[0] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $maxLatitude[1] . '<br />';
//    print '$minLongitude = ' . $minLongitude[0] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $minLongitude[1] . '<br />';
//    print '$maxLongitude = ' . $maxLongitude[0] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $maxLongitude[1] . '<br />';
//    print '$latitudeMidpoint = ' . $latitudeMidpoint . '<br />';
//    print '$longitudeMidpoint = ' . $longitudeMidpoint . '<br />';
//    if (isset($deltaLatitude))
//	print '$deltaLatitude = ' . $deltaLatitude . '<br />';
//    if (isset($deltaLongitude))
//	print '$deltaLongitude = ' . $deltaLongitude . '<br />';
//    print '$regionId = ' . $regionId;
//    print '$positionInRegion = ' . $positionInRegion  . '<br />';
//    print '$datasetId = ' . $datasetId  . '<br />';
//    print '$collection_id = ' . $collectionId . '<br />';
//    print '<h1>Line by Line Details</h1>';
//    foreach ($images as $value) {
//	print $value[13] . ' UTC&nbsp;&nbsp;&nbsp;&nbsp;' . $value[1] . '&nbsp;&nbsp;&nbsp;&nbsp;' .
//		$value[0] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $value[15] . '&nbsp;&nbsp;&nbsp;&nbsp;' .
//		$value[4] . '<br />';
//    }
//
} else { // End IF RequestMethod == POST
    ?>
        <!DOCTYPE html>
    <html>
        <head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    	<title>Dataset Import</title>
        </head>
        <body>
    	<form action="datasetImport.php" enctype="multipart/form-data" method="post">
    	    <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
    	    Select a CSV dataset file to import (Required): <br />
    	    <input type="file" name="dataset" accept="text/csv" /><br />
    	    Name (Required, Max 50 characters): <br />
    	    <input type="text" name="name" maxlength="50" /><br />
    	    Description (Required, Max 500 characters): <br />
    	    <textarea name="description" rows="4" cols="50" maxlength="500"></textarea><br />
    	    Dataset is (Required):<br />
    	    Pre: <input type="radio" name="collection" value="1" /><br />
    	    Post: <input type="radio" name="collection" value="2" /><br />
    	    <input type="submit" name="submit" value="Begin Import" />
    	</form>
        </body>
    </html>
<?php } // End ELSE RequestMethod == POST               ?>

