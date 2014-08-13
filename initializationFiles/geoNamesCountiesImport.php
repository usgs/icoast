<?php
//////////
// => Handle the GeoNames County file if it has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    //////////
    // => Define variables and PHP settings
    $usCoastalStates = array('AL', 'CA', 'CT', 'DE', 'FL', 'GA', 'LA', 'ME', 'MD', 'MA',
	'MS', 'NH', 'NJ', 'NY', 'NC', 'OR', 'RI', 'SC', 'TX', 'VA', 'WA');

    // => Development variables to be moved or replaced by release
    $dbmsServer = 'localhost';
    $dbmsUser = 'root';
    $dbmsPassword = '';
    $dbmsDatabase = "icoast";
    $dbc = new mysqli($dbmsServer, $dbmsUser, $dbmsPassword, $dbmsDatabase);

    //////////
    // => Truncate the geonames table to prepare it for a fresh import.
    $geonamesTruncateQuery = "TRUNCATE TABLE geonames_counties";
    If (!$dbc->query($geonamesTruncateQuery)) {
	print $dbc->error . "<br />";
	print $geonamesTruncateQuery . '<br>';
	exit;
    } else {
	// print '<h1>Table Truncated</h1>'; // Debugging Output
    }

    //////////
    // => Open the geonames tab delimited text file database dump of County Codes (admin2Codes.txt
    // at http://download.geonames.org/export/dump/). Loop through each row. Check for
    // relevance and import in bulk using a single query.
    $fp = fopen($_FILES['geonamesCounties']['tmp_name'], 'r');
    // Start query string.
    $geonamesCountiesInsertQuery = "INSERT INTO geonames_counties (state, county_code,
			    county_name) VALUES ";
    // Loop through the txt file row by row.
    while ($csvRow = fgetcsv($fp, 500, "\t")) {
	// $csvRow[0] = CSV File: Concatenated country, state, and county codes
	// $csvRow[2] = CSV File: ASCII Name
	// Check row for relevance by examining first 2 chars of column 1. Skip is not "US"
	If (strpos($csvRow[0], 'US') !== 0) {
	    //if ()
	    continue;
	}
	// Explode concatenated first row into array $splitConcatenation.
	$splitConcatenation = explode('.', $csvRow[0]);
	// $splitConcatenation[1] = State Abbreviation
	// $splitConcatenation[2] = County Code
	// Loop through the array of US Costal States $usCoastalStates and compare to
	// State in the row. If match then add the row data to the INSERT query.
	foreach ($usCoastalStates as $state) {
	    if (strcasecmp($splitConcatenation[1], $state) == 0) {
		// print $csvRow[0] . ' becomes => ' . $splitConcatenation[1] . ": " .
		// $splitConcatenation[2] . " = " . $csvRow[2] . "<br>"; // Debugging Output
		$geonamesCountiesInsertQuery .= "('$splitConcatenation[1]',
			$splitConcatenation[2], " . '"' . $csvRow[2] . '"), ';
		// State match was found, stop querying row for the remaining states.
		break;
	    }
	}
    }
    // No more rows in test file. Format query and send to geonames_counties DB
    $geonamesCountiesInsertQuery = substr_replace($geonamesCountiesInsertQuery, ";", -2);
    If (!$dbc->query($geonamesCountiesInsertQuery)) {
	print $dbc->error . "<br />";
	print '<br>' . $geonamesCountiesInsertQuery . '<br>';
    } else {
	// Print "<h1>Imported rows: " . $dbc->affected_rows . '</h1>'; // Debugging Output.
    }
} else {
    ?>
        <!DOCTYPE html>
    <html>
        <head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    	<title>Dataset Import</title>
        </head>
        <body>
    	<form action="geoNamesCountiesImport.php" enctype="multipart/form-data" method="post">
    	    <input type="file" name="geonamesCounties" /><br />
    	    <input type="submit" name="submit" value="Begin Import" />
    	</form>
        </body>
    </html>
    }
<?php }
?>
