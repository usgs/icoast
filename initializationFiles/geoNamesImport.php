<?php
//////////
// => Handle the GeoNames file if it has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    //////////
    // => Define variables and PHP settings
    ini_set('max_execution_time', 180);
    ini_set('memory_limit', '512M');
    $rowsInQuery = 0;
    $featuresToImport = array('bay', 'hbr', 'inlt', 'swmp', 'prk', 'res', 'airb', 'airf', 'airp',
	'lthse', 'mar', 'bch', 'cape', 'isl', 'ppl', 'ppla', 'ppla2', 'ppla3');
    $prkNameFilterList = array('playground', 'museum', 'historic district', 'visitors center',
	'visitor center', 'recreation center', 'monument', 'camp', 'information', 'store',
	'headquarters');
    $pplNameFilterList = array('trailer park', 'mobile home park');
    // $count = 0; // Debugging counter.
    // => Development variables to be moved or replaced by release

    $dbmsServer = '';
    $dbmsUser = '';
    $dbmsPassword = '';
    $dbmsDatabase = "";

    $dbc = new mysqli($dbmsServer, $dbmsUser, $dbmsPassword, $dbmsDatabase);

    //////////
    // => Truncate the geonames table to prepare it for a fresh import.
    $geonamesTruncateQuery = "TRUNCATE TABLE geonames";
    If (!$dbc->query($geonamesTruncateQuery)) {
	print $dbc->error . "<br />";
	print $geonamesTruncateQuery . '<br>';
	exit;
    } else {
	// print '<h1>Table Truncated</h1>'; // Debugging output
    }

    //////////
    // => Open the geonames tab delimited text file database dump
    // http://download.geonames.org/export/dump/ for the US. Loop through each row. Check for
    // suitibility for project and import in bulk queries of 5000 rows each.
    $fp = fopen($_FILES['geonames']['tmp_name'], 'r');
    // Loop through the geonames text DB.
    while ($csvRow = fgetcsv($fp, 7000, "\t", "%")) {
	// $csvRow[2] = name of geographical point in plain ascii characters, varchar(200)
	// $csvRow[4] = latitude in decimal degrees (wgs84)
	// $csvRow[5] = longitude in decimal degrees (wgs84)
	// $csvRow[6] = feature class, see http://www.geonames.org/export/codes.html, char(1)
	// $csvRow[7] = feature code, see http://www.geonames.org/export/codes.html, varchar(10)
	// $csvRow[10] = state, varchar(20)
	// $csvRow[11] = county, see file admin2Codes.txt; varchar(80)
	// $csvRow[14] = population (8 byte int)
	// $csvRow[15] = System Generated: Population flag
	//		 (0 if csvRow[14] = 0, 1 if csvRow[14] > 0)
	//print $csvRow[2] . " in " . $csvRow[10] . ", feature code: " . $csvRow[7] . " in progress.<br>";
	// Skip over any rows with empty columns (except "county code" field)
	if (empty($csvRow[2]) OR empty($csvRow[4]) OR empty($csvRow[5]) OR
		empty($csvRow[6]) OR empty($csvRow[7]) OR empty($csvRow[10])) {
	    // print"<h1>Breaking Empty Field '$csvRow[2]' '$csvRow[4]' '$csvRow[5]' '$csvRow[6]'
	    //'$csvRow[7]' '$csvRow[10]'</h1>"; // Debugging output.
	    continue;
	}
	// Skip over all rows with Feature Classes of A, R, or U
	//If ($csvRow[6] == 'A' OR $csvRow[6] == 'R' OR $csvRow[6] == 'U') {
	If ($csvRow[6] == 'A' OR $csvRow[6] == 'R' OR $csvRow[6] == 'U' OR $csvRow[6] == 'V') {
	    // print "Skipping with continue $csvRow[6]<br>"; // Debugging Output
	    continue;
	}
	// Loop through the array of desired Feature Codes $featuresToImport and compare to
	// feature code of the row. Skip any row that doesn't match the desired list.
	foreach ($featuresToImport as $fcode) {
	    if (strcasecmp($csvRow[7], $fcode) != 0) {
		continue;
	    }
	    // Skip any rows containing historical names.
	    if (stripos($csvRow[2], "(historical)") !== FALSE) {
		continue;
	    }
	    // Skip over all rows whose feature code is "PRK" (park) and has a name that contains
	    // keywords specified in $prkNameFilterList
	    if ($csvRow[7] == 'PRK') {
		foreach ($prkNameFilterList as $nameFilter) {
		    if (stripos($csvRow[2], $nameFilter) !== FALSE) {
			continue 2;
		    }
		}
	    }
	    // Skip over all rows whose feature code is "PPL" (Populated Place) and has a name
	    //  that contains keywords specified in $pplNameFilterList
	    if ($csvRow[7] == 'PPL') {
		foreach ($pplNameFilterList as $nameFilter) {
		    if (stripos($csvRow[2], $nameFilter) !== FALSE) {
			continue 2;
		    }
		}
	    }
	    // Only process rows whose features fall within the specified boundaries (only coastal
	    // regions of the Continental US (exc. Alaska).
	    if (
		    ($csvRow[4] >= 35 AND $csvRow[5] >= -77 AND $csvRow[5] <= 0) OR
		    ($csvRow[4] >= 31 AND $csvRow[4] < 35 AND $csvRow[5] >= -82 AND $csvRow[5] <= 0) OR
		    ($csvRow[4] < 31 AND $csvRow[5] >= -100 AND $csvRow[5] <= 0) OR
		    ($csvRow[4] >= 37 AND $csvRow[5] <= -121 AND $csvRow[5] > -128) OR
		    ($csvRow[4] < 37 AND $csvRow[5] <= -117 AND $csvRow[5] > -128)
	    ) {
		// If a population number is given then set a flag
		if (empty($csvRow[14])) {
		    $csvRow[14] = 0;
		}
		if ($csvRow[14] > 0) {
		    $csvRow[15] = 1;
		} else {
		    $csvRow[15] = 0;
		}
		// If "County Code" is empty replace it with a 0.
		if (empty($csvRow[11])) {
		    $csvRow[11] = 0;
		}
		// Replace any double quotes with singles
		//$csvRow[2] = str_replace('"', "'", $csvRow[2]);
		$csvRow[2] = $dbc->real_escape_string($csvRow[2]);
		// If this is the first row in a new INSERT query build the start of the query.
		if ($rowsInQuery == 0) {
		    $geonamesInsertQuery = "INSERT INTO geonames (latitude, longitude,
			    feature_class, feature_code, name, population, county_code, state) VALUES ";
		}
		// Add row data to the query
		$rowsInQuery++;
		$geonamesInsertQuery .= "($csvRow[4], $csvRow[5], '$csvRow[6]', '$csvRow[7]',
		    '$csvRow[2]', $csvRow[15], $csvRow[11], '$csvRow[10]')";
		//print $csvRow[2] . " in " . $csvRow[10] . ", feature code: " . $csvRow[7] .
		//	" matches.<br>"; //Debugging output
		// If 1000 rows in the query, format it and send it to the geonames table, then
		// reset $rowsInQuery counter and $geonamesInsertQuery string. Else, add comma
		// ready to append next row to query.
		if ($rowsInQuery == 1000) {
		    $geonamesInsertQuery .= ";";
		    If (!$dbc->query($geonamesInsertQuery)) {
			print $dbc->error . "<br />";
			print '<br>' . $geonamesInsertQuery . '<br>';
			exit;
		    } else {
			// Print "<h1>Imported rows: " . $dbc->affected_rows . '</h1>'; Debugging
			// output
			$rowsInQuery = 0;
			$geonamesInsertQuery = NULL;
		    }
		} else {
		    $geonamesInsertQuery .= ", ";
		}
	    }
	    // Stop searching row for matches to any other feature codes
	    break;
	}
    }
    // Geonames text DB has been exhausted. Formate the query and send remaining rows in query
    // string to the geonames table.
    $geonamesInsertQuery = substr_replace($geonamesInsertQuery, ";", -2);
    If (!$dbc->query($geonamesInsertQuery)) {
	print $dbc->error . "<br />";
	print '<br>' . $geonamesInsertQuery . '<br>';
	exit;
    } else {
	// Print "<h1>Imported rows: " . $dbc->affected_rows . '</h1>'; // Debugging Output
    }
    // print "<h1>Complete. $count invalid fields.</h1>"; // Debugging output
} else {
    ?>
    <!DOCTYPE html>
    <html>
        <head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    	<title>Dataset Import</title>
        </head>
        <body>
    	<form action="geoNamesImport.php" enctype="multipart/form-data" method="post">
    	    <input type="file" name="geonames" /><br />
    	    <input type="submit" name="submit" value="Begin Import" />
    	</form>
        </body>
    </html>
    }
<?php } ?>
