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
ini_set('auto_detect_line_endings', true);
session_start();

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, true, true, true, true, false, false);
$userId = $userData['user_id'];
$maskedEmail = $userData['masked_email'];

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Page Variables
$errorArray = array();
$error = '';
$importFailureDetails = '';
$pageContentHTML = '';


$existingCollectionId = filter_input(INPUT_GET, 'collectionId', FILTER_VALIDATE_INT);
$collectionName = filter_input(INPUT_POST, 'collectionName');
$collectionDescription = filter_input(INPUT_POST, 'collectionDescription');
$continueImport = filter_input(INPUT_POST, 'continueImport', FILTER_VALIDATE_BOOLEAN);

if (isset($collectionName) ||
    isset($collectionDescription) ||
    (isset($_FILES['collectionCSVFile1']) && !empty($_FILES['collectionCSVFile1']['name']))
) {
  if (empty($collectionName)) {
    $errorArray[] = 'Collection Name';
  }
  if (is_null($_FILES['collectionCSVFile1']) ||
      (isset($_FILES['collectionCSVFile1']) && empty($_FILES['collectionCSVFile1']['name']))
  ) {
    $errorArray[] = 'CSV File 1';
  }
  if (count($errorArray > 0)) {
    $error = '<h3 class="error">Errors Detected</h3><p>';
    foreach ($errorArray as $errorItem) {
      $error .= '<span class="error">Field Error:</span> ' . $errorItem . ' must have a value.<br>';
    }
    $error = rtrim($error, '<br>');
    $error .= '</p>';
  }
}

if ((!$errorArray && !is_null($collectionDescription)) || $continueImport) {

  if (is_null($continueImport)) {

    // => Custom sort function to sort multidimensional array by latitude (ascending)
    function latitude_sort_ascending($x, $y) {
      return $x['latitude'] > $y['latitude'];
    }

    // => Custom sort function to sort multidimensional array by latitude (descending)
    function latitude_sort_descending($x, $y) {
      return $x['latitude'] < $y['latitude'];
    }

    // => Custom sort function to sort multidimensional array by longitude (ascending)
    function longitude_sort_ascending($x, $y) {
      return $x['longitude'] > $y['longitude'];
    }

    // => Custom sort function to sort multidimensional array by longitude (descending)
    function longitude_sort_descending($x, $y) {
      return $x['longitude'] < $y['longitude'];
    }

    session_unset();

    $monthArray = array(
        1 => 'january',
        2 => 'february',
        3 => 'march',
        4 => 'april',
        5 => 'may',
        6 => 'june',
        7 => 'july',
        8 => 'august',
        9 => 'september',
        10 => 'october',
        11 => 'november',
        12 => 'december'
    );
    $fileReferenceArray = array(
        'collectionCSVFile1',
        'collectionCSVFile2',
        'collectionCSVFile3',
        'collectionCSVFile4',
        'collectionCSVFile5'
    );
    $processedCSVFiles = array();

    $region1Array = array();
    $region2Array = array();
    $region3Array = array();
    $region4Array = array();
    $region5Array = array();
    $region6Array = array();
    $region7Array = array();
    $region8Array = array();
    $region9Array = array();
    $region10Array = array();
    $region11Array = array();
    $region12Array = array();
    $region13Array = array();
    $region14Array = array();
    $region15Array = array();
    $region16Array = array();
    $region17Array = array();
    $region18Array = array();
    $region19Array = array();
    $region20Array = array();
    $region21Array = array();
    $region22Array = array();
    $region23Array = array();
    $region24Array = array();
    $region25Array = array();
    $region26Array = array();
    $region27Array = array();
    $region28Array = array();
    $region29Array = array();
    $region30Array = array();
    $region31Array = array();
    $region32Array = array();
    $region33Array = array();
    $region34Array = array();
    $region35Array = array();
    $region36Array = array();
    $region37Array = array();
    $fileReadError = array();

    $importErrorCount = 0;

    foreach ($fileReferenceArray as $fileReference) {
      if (empty($_FILES[$fileReference]['name']) ||
          in_array($_FILES[$fileReference]['name'], $processedCSVFiles)
      ) {
        continue;
      }
      $processedCSVFiles[] = $_FILES[$fileReference]['name'];
      $rowCount = 0;
      $fp = fopen($_FILES[$fileReference]['tmp_name'], 'r');
      while (($csvRow = fgetcsv($fp)) && $importErrorCount <= 50) {
        // $csvRow[0] = CSV Field: Longitude
        // $csvRow[1] = CSV Field: Latitude
        // $csvRow[2] = CSV Field: UTC time of image capture (format example: 09h05m09s)
        // $csvRow[3] = CSV Field: Image filename
        // $csvRow[4] = CSV Field: Full image URL
        // $csvRow[5] = CSV Field: Full thumbnail URL
        // $csvRow[6] = CSV Field: UTC year of image capture (Y format eg. 2013)
        // $csvRow[7] = CSV Field: UTC month of image capture (F format eg. November)
        // $csvRow[8] = CSV Field: UTC Day of image capture (j format eg. 5)
        // Checks for expected number of fields

        $rowCount++;

        //Skip the header row
        if ($rowCount == 1) {
          continue;
        }

        // Check for missing fields.
        if (count($csvRow) < 9) {
          $fileReadError[$_FILES[$fileReference]['name']][] = array(
              'row' => $rowCount,
              'error' => 'The row contained less than the minimum number of expected columns. ' .
                         '9 expected, ' . count($csvRow) . ' detected.'
          );
          $importErrorCount++;
          continue;
        }

        // Check for empty fields
        $emptyFields = array();
        for ($i = 0; $i < 8; $i++) {
          if (empty($csvRow[$i])) {
            $emptyFields[] = $i;
          }
          if (count($emptyFields) > 0) {
            $columns = implode(', ', $emptyFields);
            $fileReadError[$_FILES[$fileReference]['name']][] = array(
                'row' => $rowCount,
                'error' => 'The row contained empty fields in columns ' . $columns . '. All ' .
                           'fields must contain data.'
            );
            $importErrorCount++;
            continue 2;
          }
        }

        $badData = array();
        if (!is_numeric($csvRow[0]) || ($csvRow[0] < -125 || $csvRow[0] > -66.94)) {
          $badData[] = 'longitude';
        }

        if (!is_numeric($csvRow[1]) || ($csvRow[1] > 48.5 || $csvRow[1] < 24.5)) {
          $badData[] = 'latitude';
        }

        if (preg_match('/^[0-2][0-9]h[0-5][0-9]m[0-5][0-9]s$/', $csvRow[2]) == false &&
            preg_match('/^[0-2][0-9]_[0-5][0-9]_[0-5][0-9]$/', $csvRow[2]) == false
        ) {
          $badData[] = 'time';
        }

        if (preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z]{3,4}$/', $csvRow[3]) == false) {
          $badData[] = 'filename';
        }

        if (preg_match('#^https?://[a-zA-Z0-9\./_]+\.[a-zA-Z]{3,4}$#', $csvRow[4]) == false) {
          $badData[] = 'fullUrl';
        }

        if (preg_match('#^https?://[a-zA-Z0-9\./_]+\.[a-zA-Z]{3,4}$#', $csvRow[5]) == false) {
          $badData[] = 'thumbUrl';
        }

        settype($csvRow[6], 'integer');
        if (empty($csvRow[6]) || ($csvRow[6] < 1970 || $csvRow[6] > date('Y'))) {
          $badData[] = 'year';
        }

        if (is_numeric($csvRow[7])) {
          settype($csvRow[7], 'integer');
        } else {
          $csvRow[7] = array_search(strtolower($csvRow[7]), $monthArray);
        }
        if (empty($csvRow[7]) || ($csvRow[7] < 1 || $csvRow[7] > 12)) {
          $badData[] = 'month';
        }

        settype($csvRow[8], 'integer');
        if (empty($csvRow[8]) || ($csvRow[8] < 0 || $csvRow[8] > 31)) {
          $badData[] = 'day';
        }

        if (count($badData) > 0) {
          $errorText = '';
          foreach ($badData as $column) {
            switch ($column) {
              case 'longitude':
                $errorText .= "Supplied column 1 (longitude) value was either non-numeric or was out of acceptable range (-125 to -66.94).<br>";
                break;
              case 'latitude':
                $errorText .= "Supplied column 2 (latitude) value was either non-numeric or was out of acceptable range (24.5 to 48.5).<br>";
                break;
              case 'time':
                $errorText .= "Supplied column 3 (time) value was incorrectly formatted (expected HH_MM_SS or HHhMMmSSs).<br>";
                break;
              case 'filename':
                $errorText .= "Supplied column 4 (filename) value is incorrectly formatted or contains invalid characters.<br>";
                break;
              case 'fulUrl':
                $errorText .= "Supplied column 5 (Full Image URL address) value was either incorrectly formatted or contains invalid characters.<br>";
                break;
              case 'thumbUrl':
                $errorText .= "Supplied column 6 (Thumbnail Image URL address) value was either incorrectly formatted or contains invalid characters.<br>";
                break;
              case 'year':
                $currentYear = date('Y');
                $errorText .= "Supplied column 7 (Year) value was either non-numeric or was out of acceptable range (1970 to $currentYear).<br>";
                break;
              case 'month':
                $errorText .= "Supplied column 8 (Month) value does not contain a valid numeric or textual month (numeric: 1 to 12, textual: January to December(not abbreviated)).<br>";
                break;
              case 'day':
                $errorText .= "Supplied column 9 (Day) value was either non-numeric or was out of acceptable range (1 - 31).<br>";
                break;
            }
          }
          $fileReadError[$_FILES[$fileReference]['name']][] = array(
              'row' => $rowCount,
              'error' => 'Invalid field values detected.<br>' . $errorText
          );
          $importErrorCount++;
        }

        // Process UTC time/date columns into timestamp.
        $charsToRemove = array('h', 'm', 's', '_');
        $csvRow[2] = str_replace($charsToRemove, ':', $csvRow[2]);
        $csvRow[2] = rtrim($csvRow[2], ':');
        if (strlen($csvRow[7]) == 1) {
          $csvRow[7] = '0' . $csvRow[7];
        }
        if (strlen($csvRow[8]) == 1) {
          $csvRow[8] = '0' . $csvRow[8];
        }
        $imageDateTime = $csvRow[6] . '-' . $csvRow[7] . '-' . $csvRow[8] . ' ' . $csvRow[2];
        $timestamp = strtotime($imageDateTime);
        if (!$timestamp) {
          $fileReadError[$_FILES[$fileReference]['name']][] = array(
              'row' => $rowCount,
              'error' => 'A valid timestamp could not be created from the date and time values ' .
                         'provided in columns 3, 7, 8, and 9.'
          );
          $importErrorCount++;
          continue;
        } else if ($timestamp > time()) {
          $fileReadError[$_FILES[$fileReference]['name']][] = array(
              'row' => $rowCount,
              'error' => 'The specified date time combination provided in columns 3, 7, 8, and 9 is ' .
                         'invalid as it is in the future.'
          );
          $importErrorCount++;
          continue;
        }

        //////////////////////////////////////////////////////////////////////////////////////////////////////////
        // => Build the array for this image
        // Add useful $csvRow fields as values in $image array.
        $image['longitude'] = $csvRow[0];
        $image['latitude'] = $csvRow[1];
        $image['filename'] = $csvRow[3];
        $image['imageURL'] = $csvRow[4];
        $image['thumbnailURL'] = $csvRow[5];
        $image['dateTime'] = $imageDateTime;
        $image['timestamp'] = $timestamp;


        // Determine the coastal region that the image resides in and place the image in the correct region array.
        if ($image['latitude'] >= 40.5 && $image['longitude'] >= -74.3) {
          // Preliminary filter for the region east and north of Staten Island, NY up to the US/Canada Border.
          if (($image['latitude'] >= 43.6 && $image['latitude'] < 44.83) && $image['longitude'] > -71.35) {
            // image in Northern New England from the US/Canada border to Portland ME.
            $image['region'] = 1;
            $region1Array[] = $image;
          } else if (($image['latitude'] >= 42.35 && $image['latitude'] < 43.6) && $image['longitude'] > -71.15) {
            // image in Northern New England from Portland ME to Boston MA.
            $image['region'] = 2;
            $region2Array[] = $image;
          } else if (($image['latitude'] >= 41.717 && $image['latitude'] < 42.35) &&
                     ($image['longitude'] >= -71.15 && $image['longitude'] < -70.4938)
          ) {
            // image in New England from Boston MA to Sandwich MA (Cape Cod Bay).
            $image['region'] = 3;
            $region3Array[] = $image;
          } else if (($image['latitude'] >= 41.717 && $image['latitude'] < 41.8076) &&
                     ($image['longitude'] >= -70.4938 && $image['longitude'] < -69.99)
          ) {
            // image in New England from Sandwich MA to Orleans MA (Cape Cod Bay).
            $image['region'] = 4;
            $region4Array[] = $image;
          } else if (($image['latitude'] >= 41.8076 && $image['latitude'] < 41.938) &&
                     ($image['longitude'] >= -70.0439 && $image['longitude'] < -69.99)
          ) {
            // image in New England from Orleans MA to Wellfleet MA (Cape Cod Bay).
            $image['region'] = 5;
            $region5Array[] = $image;
          } else if (($image['latitude'] >= 41.867 && $image['latitude'] < 41.938) &&
                     ($image['longitude'] >= -70.0684 && $image['longitude'] < -70.0439)
          ) {
            // image in New England from Wellfleet MA to Jeremy Point MA (Cape Cod Bay).
            $image['region'] = 6;
            $region6Array[] = $image;
          } else if (($image['latitude'] >= 41.867 && $image['latitude'] < 42.04) &&
                     ($image['longitude'] >= -70.1191 && $image['longitude'] < -70.0684)
          ) {
            // image in New England from Jeremy Point MA to North Truro MA(Cape Cod Bay).
            $image['region'] = 7;
            $region7Array[] = $image;
          } else if (($image['latitude'] >= 42.04 && $image['latitude'] < 42.0625) &&
                     ($image['longitude'] >= -70.1979 && $image['longitude'] < -70.1030)
          ) {
            // image in New England from North Truro MA to Provincetown MA (Cape Cod Bay).
            $image['region'] = 8;
            $region8Array[] = $image;
          } else if (($image['latitude'] >= 42.01 && $image['latitude'] < 42.04) &&
                     ($image['longitude'] >= -70.2753 && $image['longitude'] < -70.1562)
          ) {
            // Provincetown Harbor and South Herring Cove
            $image['region'] = 9;
            $region9Array[] = $image;
          } else if (($image['latitude'] >= 42.04 && $image['latitude'] < 42.1) &&
                     ($image['longitude'] >= -70.2753 && $image['longitude'] < -70.2132)
          ) {
            // North Herring Cove, Race Point and ProvinceTown Municipal Airport
            // image in New England from Provincetown Harbor MA to ProvinceTown Municipal Airport MA(Cape Cod Bay).
            $image['region'] = 10;
            $region10Array[] = $image;
          } else if (($image['latitude'] >= 42.0625 && $image['latitude'] < 42.1) &&
                     ($image['longitude'] >= -70.2132 && $image['longitude'] < -70.11)
          ) {
            // ProvinceTown Municipal Airport MA to to Pilgrim Lake MA (Cape Cod Bay).
            $image['region'] = 11;
            $region11Array[] = $image;
          } else if ((($image['latitude'] >= 42.047 && $image['latitude'] < 42.1) &&
                      ($image['longitude'] >= -70.11 && $image['longitude'] < -70.036)) ||
                     // Pilgrim Lake to North Truro AFS
                     (($image['latitude'] >= 41.95 && $image['latitude'] < 42.047) &&
                      ($image['longitude'] >= -70.075 && $image['longitude'] < -69.95)) ||
                     // North Truro AFS to Gull Pond (East North Truro)
                     (($image['latitude'] >= 41.555 && $image['latitude'] < 41.95) &&
                      ($image['longitude'] >= -69.99 && $image['longitude'] < -69.90)) ||
                     // Gull Pond (East North Truro) to Monomoy Island
                     (($image['latitude'] >= 41.53 && $image['latitude'] < 41.555) &&
                      ($image['longitude'] >= -70.0104 && $image['longitude'] < -69.97)) ||
                     // Mid Monomoy Island to South Monomoy Island
                     (($image['latitude'] >= 41.25 && $image['latitude'] < 41.4) &&
                      ($image['longitude'] >= -70.0502 && $image['longitude'] < -69.936))
            // image in East Nantucket Island
          ) {
            // image in New England from Pilgrim Lake MA (Cape Cod) to South East Nantucket Island
            $image['region'] = 12;
            $region12Array[] = $image;
          } else if ((($image['latitude'] >= 41.23 && $image['latitude'] < 41.25) &&
                      ($image['longitude'] >= -70.192 && $image['longitude'] < -69.936)) ||
                     // South East Nantucket Island
                     (($image['latitude'] >= 41.25 && $image['latitude'] < 41.345) &&
                      ($image['longitude'] >= -70.32 && $image['longitude'] < -70.13))
          ) {
            // South West Nantucket Island, Tuckernuck Island or Muskeget Island
            // Image is on South or West Nantucket Island, Tuckernuck Island or Muskeget Island
            $image['region'] = 14;
            $region14Array[] = $image;
          } else if (($image['latitude'] >= 41.3555 && $image['latitude'] < 41.4319) &&
                     ($image['longitude'] >= -70.463 && $image['longitude'] < -70.44)
          ) {
            //image in East Chappaquiddick Island
            $image['region'] = 15;
            $region15Array[] = $image;
          } else if (($image['latitude'] >= 41.2908 && $image['latitude'] < 41.3555) &&
                     ($image['longitude'] >= -70.858 && $image['longitude'] < -70.44)
          ) {
            // image in South Chappaquiddick Island or South Martha's Vineyard
            $image['region'] = 16;
            $region16Array[] = $image;
          } else if ((($image['latitude'] >= 41.555 && $image['latitude'] < 41.675) &&
                      ($image['longitude'] >= -70.465 && $image['longitude'] < -69.99)) ||
                     // Chatham MA to New Seabury MA
                     (($image['latitude'] >= 41.497 && $image['latitude'] < 41.571) &&
                      ($image['longitude'] >= -70.66 && $image['longitude'] < -70.465)) ||
                     // New Seabury MA to Woods Hole MA
                     (($image['latitude'] >= 41.48 && $image['latitude'] < 41.532) &&
                      ($image['longitude'] >= -70.747 && $image['longitude'] < -70.66)) ||
                     // Woods Hole MA to Mid Naushon Island
                     (($image['latitude'] >= 41.45 && $image['latitude'] < 41.48) &&
                      ($image['longitude'] >= -70.771 && $image['longitude'] < -70.747)) ||
                     // Mid Naushon Island
                     (($image['latitude'] >= 41.438 && $image['latitude'] < 41.459) &&
                      ($image['longitude'] >= -70.804 && $image['longitude'] < -70.771)) ||
                     // South Naushon Island
                     (($image['latitude'] >= 41.415 && $image['latitude'] < 41.447) &&
                      ($image['longitude'] >= -70.86 && $image['longitude'] < -70.804)) ||
                     // Pasque Island & East Nashawena Island
                     (($image['latitude'] >= 41.405 && $image['latitude'] < 41.426) &&
                      ($image['longitude'] >= -70.93 && $image['longitude'] < -70.86)) ||
                     // West Nashawena Island and East Cuttyhunk Island
                     (($image['latitude'] >= 41.4 && $image['latitude'] < 41.415) &&
                      ($image['longitude'] >= -70.950 && $image['longitude'] < -70.93))
            // West Cuttyhunk Island
          ) {
            // image in Southern New England from Chatham MA to Cuttyhunk Island MA
            $image['region'] = 17;
            $region17Array[] = $image;
          } else if ((($image['latitude'] >= 41.298 && $image['latitude'] < 41.514) &&
                      ($image['longitude'] >= -71.887 && $image['longitude'] < -70.983)) ||
                     // from Westport MA, to Napatree Point, RI
                     (($image['latitude'] >= 41.286 && $image['latitude'] < 41.292) &&
                      ($image['longitude'] >= -71.925 && $image['longitude'] < -71.920)) ||
                     // eastern tip of Fishers Island, NY
                     (($image['latitude'] >= 41.276 && $image['latitude'] < 41.292) &&
                      ($image['longitude'] >= -71.94 && $image['longitude'] < -71.925)) ||
                     // eastern Fishers Island, NY to Mid Fishers Island, NY
                     (($image['latitude'] >= 41.246 && $image['latitude'] < 41.281) &&
                      ($image['longitude'] >= -71.99 && $image['longitude'] < -71.94)) ||
                     // Mid Fishers Island, NY
                     (($image['latitude'] >= 41.246 && $image['latitude'] < 41.263) &&
                      ($image['longitude'] >= -72.040 && $image['longitude'] < -71.99))
            // Western Fishers Island, NY
          ) {
            // Southern MA and RI from Westport MA, to Napatree Point, RI including Fishers Island, NY
            $image['region'] = 18;
            $region18Array[] = $image;
          } else if (($image['latitude'] >= 41.154 && $image['latitude'] < 41.238) &&
                     ($image['longitude'] >= -71.577 && $image['longitude'] < -71.537)
          ) {
            // Eastern Block Island, RI
            $image['region'] = 19;
            $region19Array[] = $image;
          } else if ((($image['latitude'] >= 41.140 && $image['latitude'] < 41.154) &&
                      ($image['longitude'] >= -71.617 && $image['longitude'] < -71.537)) ||
                     // Southern Block Island, RI
                     (($image['latitude'] >= 41.03 && $image['latitude'] < 41.08) &&
                      ($image['longitude'] >= -71.905 && $image['longitude'] < -71.84)) ||
                     // eastern tip of Montauk, NY
                     (($image['latitude'] >= 41 && $image['latitude'] < 41.041) &&
                      ($image['longitude'] >= -71.995 && $image['longitude'] < -71.905)) ||
                     // Montauk, NY
                     (($image['latitude'] >= 40.98 && $image['latitude'] < 41.0145) &&
                      ($image['longitude'] >= -72.036 && $image['longitude'] < -71.995)) ||
                     // Montauk, NY to Nepeague NY
                     (($image['latitude'] >= 40.869 && $image['latitude'] < 40.998) &&
                      ($image['longitude'] >= -72.343 && $image['longitude'] < -72.036)) ||
                     // Nepeague NY to Southampton, NY
                     (($image['latitude'] >= 40.5 && $image['latitude'] < 40.887) &&
                      ($image['longitude'] >= -74.3 && $image['longitude'] < -72.343))
            // Southampton, NY to Staten Island
          ) {
            // Block Island, RI to Staten Island, NY
            $image['region'] = 20;
            $region20Array[] = $image;
          }
        } // END if ($image['latitude'] >= 40.5 && $image['longitude'] >= -74.3) Staten Island NY to US/Canda Border.
        else if (($image['latitude'] >= 37 && $image['latitude'] < 40.5) &&
                 $image['longitude'] > -76
        ) {
          // image is on Atlantic Coast between Sandy Hook NJ and Virginia Beach VA
          $image['region'] = 21;
          $region21Array[] = $image;
        } else if ((($image['latitude'] >= 35.6 && $image['latitude'] < 37) && $image['longitude'] > -76.03) ||
                   (($image['latitude'] >= 35.22 && $image['latitude'] < 35.6) && $image['longitude'] >= -75.525)
        ) {
          // image is on Atlantic Coast between Virginia Beach VA and Hatteras Island, NC
          $image['region'] = 22;
          $region22Array[] = $image;
        } else if (($image['latitude'] >= 32.1 && $image['latitude'] < 35.24) &&
                   ($image['longitude'] >= -81.5 && $image['longitude'] < -75.525)
        ) {
          // image is on Atlantic Coast between Hatteras Island, NC Hilton Head Island, SC
          $image['region'] = 23;
          $region23Array[] = $image;
        } else if ((($image['latitude'] >= 26 && $image['latitude'] < 32.1) && $image['longitude'] > -81.5) ||
                   (($image['latitude'] >= 25.12 && $image['latitude'] < 26) && $image['longitude'] > -80.43)
        ) {
          // image is on Atlantic Coast between Hilton Head Island, SC and Key Largo, FL
          $image['region'] = 24;
          $region24Array[] = $image;
        } else if (($image['longitude'] > -82.5 && $image['longitude'] < -80) && $image['latitude'] < 25.12) {
          // image is in the Florida Keys
          /*
           *
           *
           * TO DO. KEYS REFINEMENT
           *
           *
           */
          $image['region'] = 25;
          $region25Array[] = $image;
        } else if ((($image['latitude'] >= 25.12 && $image['latitude'] < 26) && ($image['longitude'] > -82 &&
                                                                                 $image['longitude'] < -81.09)) ||
                   (($image['longitude'] >= -84 && $image['longitude'] < -81.5) && $image['latitude'] >= 26)
        ) {
          // Preliminary filter for the region on Eastern Gulf Coast between Micmac Lagoon, FL and Apalachee Bay, FL
          if (($image['latitude'] >= 25.12 && $image['latitude'] < 26.364) &&
              ($image['longitude'] >= -81.89 && $image['longitude'] < -81.09)
          ) {
            // Micmac Lagoon, FL to Big Hickory Island, FL
            $image['region'] = 28;
            $region28Array[] = $image;
          } else if (($image['latitude'] >= 26.484 && $image['latitude'] < 26.364) &&
                     ($image['longitude'] >= -82.19 && $image['longitude'] < -81.860)
          ) {
            // Big Hickory Island, FL to West Sanibel Island, FL
            $image['region'] = 29;
            $region29Array[] = $image;
          } else if (($image['latitude'] >= 26.484) &&
                     ($image['longitude'] >= -84 && $image['longitude'] < -82.19)
          ) {
            // Sanibel Island, FL to Apalachee Bay, FL
            $image['region'] = 30;
            $region30Array[] = $image;
          }
        } // END Preliminary filter for the region on Eastern Gulf Coast between Micmac Lagoon, FL and Apalachee Bay, FL
        else if (($image['longitude'] >= -88.55 && $image['longitude'] < -84) ||
                 // Apalachee Bay, FL and Horn Island, MI
                 (($image['longitude'] >= -89.25 && $image['longitude'] < -88.55) &&
                  $image['latitude'] >= 30.15)
          // Horn Island, MI to Cat Island, MI
        ) {
          // image is in Northern Gulf Coast between Apalachee Bay, FL and Cat Island, MI
          $image['region'] = 31;
          $region31Array[] = $image;
        } else if (($image['longitude'] >= -89.25 && $image['longitude'] < -88.75) &&
                   $image['latitude'] < 30.15
        ) {
          // image is on Northwestern Gulf Coast between Breton Island, LA and Port Eads, LA
          $image['region'] = 32;
          $region32Array[] = $image;
        } else if ($image['longitude'] >= -94 && $image['longitude'] < -89.25) {
          // image is on Northwestern Gulf Coast between Port Eads, LA and Port Arthur TX (TX/LA Border)
          $image['region'] = 33;
          $region33Array[] = $image;
        } else if ($image['longitude'] >= -97 && $image['longitude'] < -94) {
          // image is on Northwestern Gulf Coast between Port Arthur TX (TX/LA Border) and Corpus Christi, TX
          $image['region'] = 34;
          $region34Array[] = $image;
        } else if (($image['longitude'] >= -110 && $image['longitude'] < -97) && $image['latitude'] >= 25.95) {
          // image is on Western Gulf Coast between Corpus Christi, TX and US/Mexico Border
          $image['region'] = 35;
          $region35Array[] = $image;
        } else if (($image['longitude'] >= -125 && $image['longitude'] < -117) &&
                   ($image['latitude'] >= 32.53 && $image['latitude'] < 40.5)
        ) {
          // image is on Pacific Coast between US/Mexico Border and Eureka CA
          $image['region'] = 36;
          $region36Array[] = $image;
        } else if (($image['longitude'] >= -125 && $image['longitude'] < -123) &&
                   ($image['latitude'] >= 40.5 && $image['latitude'] <= 48.5)
        ) {
          // image is on Pacific Coast between Eureka CA and US/Canada Border
          $image['region'] = 37;
          $region37Array[] = $image;
        } else {
          $fileReadError[$_FILES[$fileReference]['name']][] = array(
              'row' => $rowCount,
              'error' => 'The image does into appear to be in a valid location for use in ' .
                         'iCoast.'
          );
          $importErrorCount++;
          continue;
        }
      }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // => Sort the regional arrays by direction. Then merge all arrays in order into $imageArray. Add ID numbers.
    // => Result should be a directionally ordered list that goes clockwise around the US coast starting in ME.

    usort($region1Array, 'longitude_sort_descending');
    usort($region2Array, 'latitude_sort_descending');
    usort($region3Array, 'latitude_sort_descending');
    usort($region4Array, 'longitude_sort_ascending');
    usort($region5Array, 'latitude_sort_ascending');
    usort($region6Array, 'latitude_sort_descending');
    usort($region7Array, 'latitude_sort_ascending');
    usort($region8Array, 'longitude_sort_descending');
    usort($region8Array, 'longitude_sort_descending');
    usort($region9Array, 'longitude_sort_descending');
    usort($region10Array, 'latitude_sort_ascending');
    usort($region11Array, 'longitude_sort_ascending');
    usort($region12Array, 'latitude_sort_descending');
    usort($region14Array, 'longitude_sort_descending');
    usort($region15Array, 'latitude_sort_descending');
    usort($region16Array, 'longitude_sort_descending');
    usort($region17Array, 'longitude_sort_descending');
    usort($region18Array, 'longitude_sort_descending');
    usort($region19Array, 'latitude_sort_descending');
    usort($region20Array, 'longitude_sort_descending');
    usort($region21Array, 'latitude_sort_descending');
    usort($region22Array, 'latitude_sort_descending');
    usort($region23Array, 'longitude_sort_descending');
    usort($region24Array, 'latitude_sort_descending');
    // usort($region25Array, 'longitude_sort_descending');
    // usort($region26Array, 'longitude_sort_ascending');
    // usort($region27Array, 'longitude_sort_descending');
    usort($region28Array, 'latitude_sort_ascending');
    usort($region29Array, 'longitude_sort_descending');
    usort($region30Array, 'latitude_sort_ascending');
    usort($region31Array, 'longitude_sort_descending');
    usort($region32Array, 'latitude_sort_descending');
    usort($region33Array, 'longitude_sort_descending');
    usort($region34Array, 'longitude_sort_descending');
    usort($region35Array, 'latitude_sort_descending');
    usort($region37Array, 'latitude_sort_ascending');
    $imageArray = array_merge($region1Array, $region2Array, $region3Array, $region4Array, $region5Array, $region6Array, $region7Array, $region8Array, $region9Array, $region10Array, $region11Array, $region12Array, $region13Array, $region14Array, $region15Array, $region16Array, $region17Array, $region18Array, $region19Array, $region20Array, $region21Array, $region22Array, $region23Array, $region24Array, $region25Array, $region26Array, $region27Array, $region28Array, $region29Array, $region30Array, $region31Array, $region32Array, $region33Array, $region34Array, $region35Array, $region36Array, $region37Array);

    // Tidy up now unused regionArrays
    unset($region1Array, $region2Array, $region3Array, $region4Array, $region5Array, $region6Array, $region7Array, $region8Array, $region9Array, $region10Array, $region11Array, $region12Array, $region13Array, $region14Array, $region15Array, $region16Array, $region17Array, $region18Array, $region19Array, $region20Array, $region21Array, $region22Array, $region23Array, $region24Array, $region25Array, $region26Array, $region27Array, $region28Array, $region29Array, $region30Array, $region31Array, $region32Array, $region33Array, $region34Array, $region35Array, $region36Array, $region37Array);

    for ($i = 0; $i < count($imageArray); $i++) {
      $imageArray[$i]['locationSortOrder'] = $i;
    }
    $totalImages = count($imageArray);

    $_SESSION['imageArray'] = $imageArray;
    $_SESSION['totalImages'] = $totalImages;
    $_SESSION['collectionName'] = $collectionName;
    $_SESSION['collectionDescription'] = $collectionDescription;
    if ($importErrorCount > 0) {
      $importFailureDetailsTable = '';
      foreach ($fileReadError as $file => $fileErrors) {
        $importFailureDetailsTable .= '
            <h4 class="error">Erroring File: ' . $file . '</h4>
            <table class="adminStatisticsTable importErrorTable">
                <thead>
                    <tr>
                        <td>Row/Line</td>
                        <td>Error</td>
                    </tr>
                </thead>
                <tbody>';
        foreach ($fileErrors as $fileError) {
          // printArray($fileError);
          $importFailureDetailsTable .= '
                                <tr>
                                    <td>' . $fileError['row'] . '</td>
                                    <td>' . $fileError['error'] . '</td>
                                </tr>';
        }
        $importFailureDetailsTable .= '
            </tbody>
        </table>';
      }

      if ($importErrorCount > 50) {
        $error = '<p class="error">Import aborted. An excessive number of errors (greater than 50)
                    were detected during the import process. Details are provided at the foot of this page.</p>';
        $importFailureDetails = '
                    <h3>Aborted Import Errors</h3>
                    <p class="error">The following errors were detected during the import process and resulted in the
                    import being aborted. Ensure your CSV file matches the format of the downloadable template (above)
                    and that the data itself is also formatted correctly.</p>' .
                                $importFailureDetailsTable;
        session_unset();
        session_destroy();
      } else {
        $pageContentHTML = <<<HTML
                        <h1> iCoast {$collectionMetadata['name']} Collection Creator</h1>
                        <h2>Step 2 - $prePostTitleText-Event Collection Import</h2>
                        <p class="error">Errors were detected during the import process.
                                Details are provided below.</p>
                        <p>You may continue the import with the listed images excluded from the new collection or you
                            may choose to abort the import, resolve the errors, and try the import again.</p>
                        <form method="post" autocomplete="off">
                            <button type="submit" class="clickableButton enlargedClickableButton" name="continueImport" value="1">Ignore Errors and Continue Import</button>
                            <button type="submit" class="clickableButton enlargedClickableButton" name="abortImport" value="1">Abort Import</button>
                        </form>
                        $importFailureDetailsTable
HTML;
      }
    }
  }

  if ($continueImport || $importErrorCount == 0) {


    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // => Create the collection
    $importKey = md5(rand());

    $createCollectionQuery = '
            INSERT INTO import_collections (
                creator,
                name,
                description,
                total_images,
                import_key,
                session_id,
                import_start_timestamp
            )
            VALUES (
                :userId,
                :collectionName,
                :collectionDescription,
                :totalImages,
                :importKey,
                :sessionId,
                :timestamp)
        ';
    $createCollectionParams = array(
        'userId' => $userId,
        'collectionName' => $_SESSION['collectionName'],
        'collectionDescription' => $_SESSION['collectionDescription'],
        'totalImages' => $_SESSION['totalImages'],
        'importKey' => $importKey,
        'sessionId' => session_id(),
        'timestamp' => time()
    );
    $importCollectionId = run_prepared_query($DBH, $createCollectionQuery, $createCollectionParams, true);
    if ($importCollectionId) {
      unset($_SESSION['collectionName']);
      unset($_SESSION['collectionDescription']);
      unset($_SESSION['totalImages']);
      session_write_close();
      if (strcasecmp($_SERVER['HTTP_HOST'], 'localhost') === 0 || strcasecmp($_SERVER['HTTP_HOST'], 'igsafpesvm142') === 0) {
        $curlUrlHost = "http://localhost/icoast";
      } else if (strcasecmp($_SERVER['HTTP_HOST'], 'coastal.er.usgs.gov') === 0) {
        $curlUrlHost = "http://coastal.er.usgs.gov/icoast";
      } else {
        header('Location: collectionCreator.php');
        exit;
      }
      $curlUrl = $curlUrlHost . "/scripts/collectionImport.php";
      $curlPostParams = "user={$userData['user_id']}&checkCode={$userData['auth_check_code']}&importCollectionId={$importCollectionId}&importKey=$importKey";
      //print $curlPostParams;
      $c = curl_init();
      curl_setopt($c, CURLOPT_URL, $curlUrl);
      curl_setopt($c, CURLOPT_POSTFIELDS, $curlPostParams);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
      curl_setopt($c, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
      curl_setopt($c, CURLOPT_TIMEOUT, 1);
      curl_setopt($c, CURLOPT_FAILONERROR, true);
      $result = curl_exec($c);
//      if ($result === false) {
//        print '<br>cUrl Failed<br>';
//        print 'Error: ' . curl_error($c). '<br>';
//        print 'Error No: ' . curl_errno($c) . '<br>';
//        $info = curl_getinfo($c);
//        print 'Dump Start';
//        array_dump($info);
//        print 'Dump End';
//      } else {
//        print '<br>Result:<br>';
//        print $result . '<br>';
//        print "<a href='collectionImportProgress.php?collectionId=$importCollectionId'>
//collectionImportProgress.php?collectionId=$importCollectionId
//</a>";
//      }
      curl_close($c);
      header("Location: collectionImportProgress.php?collectionId=$importCollectionId");
      exit;

    } else {
      $error = '<p class="error">Initial database update failed. Import aborted. Please try again.</p>';
      session_unset();
      session_destroy();
    }
  }
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Build default page output
if (empty($pageContentHTML)) {

  if (isset($existingCollectionId)) {
    $existingCollectionMetadata = retrieve_entity_metadata($DBH, $existingCollectionId, 'importCollection');
    if ($existingCollectionMetadata &&
        $existingCollectionMetadata['creator'] == $userId &&
        (empty($existingCollectionMetadata['import_status_message']) ||
         $existingCollectionMetadata['import_status_message'] == 'Deleted' ||
         $existingCollectionMetadata['import_status_message'] == 'User Abort Request')
    ) {
      $collectionName = $existingCollectionMetadata['name'];
      $collectionDescription = $existingCollectionMetadata['description'];
      $deleteExistingCollectionQuery = <<<MYSQL
              DELETE FROM import_collections
              WHERE import_collection_id = :existingCollectionId
              LIMIT 1
MYSQL;
      $deleteExistingCollectionParams['existingCollectionId'] = $existingCollectionId;
      run_prepared_query($DBH, $deleteExistingCollectionQuery, $deleteExistingCollectionParams);
    }
  }

// Find details for form sticky fields
  if (is_null($collectionName)) {
    $collectionName = '';
  }
  if (is_null($collectionDescription)) {
    $collectionDescription = '';
  }
  $collectionName = htmlspecialchars($collectionName);
  $collectionDescription = htmlspecialchars($collectionDescription);

// Set the default page content
  $pageContentHTML = <<<HTML

                <h1> iCoast Collection Creator</h1>
                $error
                <p>Using specially formatted CSV (comma-separated value) files you can upload a new collection to iCoast
                    for use in future projects. Simply fill in the collection details below
                    and select the file(s) containing the CSV data.</p>
                <p>CSV files​ ​(hyyyymmdd_mapserver.csv)​ are normally supplied by <a title="Karen Morgan - E-mail: kmorgan@usgs.gov -
                    Phone: 727-502-8037" href="mailto:kmorgan@usgs.gov">Karen Morgan</a> and should follow the format as 
                    laid out in <a href="sampleCSV.csv">this sample</a>.</p> 
                <p>Images referenced using URL's in the CSV files should be high resolution and already hosted on an internet accessible server. For assistance with this contact
                    <a title="Jolene Gittens - E-mail: jgittens@usgs.gov - Phone: 727-502-8038" href="mailto:jgittens@usgs.gov">Jolene Gittens</a>.
                    or 
                    <a title="Karen Morgan - E-mail: kmorgan@usgs.gov - Phone: 727-502-8037" href="mailto:kmorgan@usgs.gov">Karen Morgan</a>.</p>
                <p class="error">Be aware that imports of new collections can take a very long time to process
                    (several hours) depending on the number of images involved.</p>
                <form id="newCollectionForm" method="post" autocomplete="off" enctype="multipart/form-data">
                        <input type="hidden" name="MAX_FILE_SIZE" value="4194304" />
                        <div class="formFieldRow">
                            <label for="collectionName" title="This is the text used to summarize  this collection within the iCoast Admin interface. It is used in all selection boxes where an admin can select a collection. Keep the text here simple and short. Using a storm name and date such as 'Post Hurricane Sandy Images 2012' is best. There is 50 character limit. This text is not publicly displayed.">Collection Name * :</label>
                            <input type="text" id="collectionName" class="formInputStyle" name="collectionName" maxlength="50" value="$collectionName" />
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionDescription" title="This text explains the details of the collection and may be more verbose than the title. 500 character limit. This text is not publicly displayed.">Collection Description:</label>
                            <textarea id="collectionDescription" class="formInputStyle" name="collectionDescription" maxlength="500">$collectionDescription</textarea>
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionCSVFile1" title="Select a properly formatted CSV file that contains the data for this new collection.">CSV File 1 * :</label>
                            <input type="file" id="collectionCSVFile1" class="formInputStyle csvFileFormInput" name="collectionCSVFile1" accept=".csv" />
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionCSVFile2" title="Select a properly formatted CSV file that contains the data for this new collection.">CSV File 2:</label>
                            <input type="file" id="collectionCSVFile2" class="formInputStyle csvFileFormInput" name="collectionCSVFile2" accept=".csv" />
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionCSVFile3" title="Select a properly formatted CSV file that contains the data for this new collection.">CSV File 3:</label>
                            <input type="file" id="collectionCSVFile3" class="formInputStyle csvFileFormInput" name="collectionCSVFile3" accept=".csv" />
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionCSVFile4" title="Select a properly formatted CSV file that contains the data for this new collection.">CSV File 4:</label>
                            <input type="file" id="collectionCSVFile4" class="formInputStyle csvFileFormInput" name="collectionCSVFile4" accept=".csv" />
                        </div>
                        <div class="formFieldRow">
                            <label for="collectionCSVFile5" title="Select a properly formatted CSV file that contains the data for this new collection.">CSV File 5:</label>
                            <input type="file" id="collectionCSVFile5" class="formInputStyle csvFileFormInput" name="collectionCSVFile5" accept=".csv" />
                        </div>
                        <p>* indicates a required field</p>
                        <button type="submit" id="uploadCollectionButton" class="clickableButton enlargedClickableButton">Upload Collection</button>
                </form>
                $importFailureDetails
HTML;

  $jQueryDocumentDotReadyCode = <<<JS

            $('#newCollectionForm').submit(function(){
                $('#uploadCollectionButton, #useExistingCollectionButton').addClass('disabledClickableButton').attr('disabled', 'disabled');
            });
JS;
}


$embeddedCSS = <<<CSS

    .adminStatisticsTable td {
        line-height: 1.2em !important
    }

    .importErrorTable tr td:first-of-type{
        width: 100px;
    }   
CSS;
