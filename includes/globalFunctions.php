<?php

// -------------------------------------------------------------------------------------------------
/**
 * Function to encrypt user email using 256 bit AES encryption for database storage.
 *
 * @param string $value The string value to be encrypted
 * @return array On success returns an array containing the original value encrypted using 256bit AES
 * encryption in non-binary string format in the first index, and the encryption IV in non-binary
 * format in the second index.
 */
function mysql_aes_encrypt($value) {

  if (isset($GLOBALS['dbmsSalt'])) {
    global $dbmsSalt;
  } else {
    return FALSE;
  }

  $m = mcrypt_module_open('rijndael-256', '', 'cbc', '');
  $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($m), MCRYPT_DEV_RANDOM);
  mcrypt_generic_init($m, $dbmsSalt, $iv);
  $encryptedValue = mcrypt_generic($m, $value);
  mcrypt_generic_deinit($m);
  mcrypt_module_close($m);
  return array(base64_encode($encryptedValue), base64_encode($iv));
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to decrypt user email from 256 bit AES encryption for database retrival.
 *
 * @param string $encryptedValue The encrypted string value to be decrypted
 * @param string $IV The IV used during the encryption process.
 * @return string $decryptedValue On success returns the decrypted value from 256bit AES encypted
 * format.
 */
function mysql_aes_decrypt($encryptedValue, $iv) {

  if (isset($GLOBALS['dbmsSalt'])) {
    global $dbmsSalt;
  } else {
    return FALSE;
  }

  $value = base64_decode($encryptedValue);
  $iv = base64_decode($iv);
  $m = mcrypt_module_open('rijndael-256', '', 'cbc', '');
  mcrypt_generic_init($m, $dbmsSalt, $iv);
  $decryptedValue = mdecrypt_generic($m, $value);
  mcrypt_generic_deinit($m);
  mcrypt_module_close($m);
  return rtrim($decryptedValue);
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to mask a percentage of the user portion of an e-mail address.
 *
 * @param string $email The email address to be masked.
 * @param string $maskCharacter The character to be used to replaced masked letters.
 * @param integer $maskPercentage An integer between 0 and 100 indicating the percentage of the
 *  address to mask.
 * @return string A combination of the original domain and the masked user portion of an e-mail
 * address.
 */
function mask_email( $email, $maskCharacter="*", $maskPercentage=50 )
{

        list( $user, $domain ) = preg_split("/@/", $email );
        $len = strlen( $user );
        $mask_count = floor( $len * $maskPercentage /100 );
        $offset = floor( ( $len - $mask_count ) / 2 );
        $masked = substr( $user, 0, $offset )
                .str_repeat( $maskCharacter, $mask_count )
                .substr( $user, $mask_count+$offset );
        return( $masked.'@'.$domain );
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to run any supplied query against the database.
 *
 * @param string $query Query to be run against the database.
 * @return mysqli_result|boolean On success returns a mysqli_result object <b>OR</b><br>
 * On Failure returns boolean FALSE.
 */
function run_database_query($query) {
//print "<p><b>In run_database_query function.</b><br>Arguments:<br>$query</p>";
// Define required files and initial includes
  if (isset($GLOBALS['dbc'])) {
    global $dbc;
  } else {
    return FALSE;
  }
// Run the user specified query.
  if (!$queryResult = $dbc->query($query)) {
    print "Query failure.<br>Error Number:" . $dbc->errno . "<br>Error: " .
            $dbc->error . "<br>Query: $query";
 print "RETURNING: FALSE<br>";
    return FALSE;
  }
// print "RETURNING: Query Result For: $query<br>";
  return $queryResult;
}

// -------------------------------------------------------------------------------------------------
/**
 * Escapes any string into a SQL safe format.
 *
 * @param string $string The string to be escaped.
 * @return string|boolean On success returns a SQL safe string <b>OR</b><br>
 * On failure returns false.
 */
function escape_string($string) {
// print "<p><b>In where_in_string_builder function.</b><br>Arguments:<br>$string</p>";
// Define required files and initial includes
  if (isset($GLOBALS['dbc'])) {
    global $dbc;
  } else {
    return FALSE;
  }
// Define variables and PHP settings
// Escape the string
  if (is_string($string)) {
    $escapedString = $dbc->real_escape_string($string);
// print "RETURNING: $escapedString<br>";
    return $escapedString;
  }
// print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Builds a string value of correct format to be used in "WHERE column IN string" SQL queries.
 *  *
 * @param string|int|double|decimal|array $values A single value in string or numeric form or a 1D
 *  array where each element value is an individual search value to be concatenated.
 * @return string|boolean On success returns a formatted string "(1,2,3,4)" <b>OR</b><br>
 *  On failure returns boolean FALSE.
 */
function where_in_string_builder($values) {
  /* print "<p><b>In where_in_string_builder function.</b><br>Arguments:";
    if (is_array($values)) {
    print "<br>An array of values.</p>";
    print "<pre>";
    print_r($values);
    print "</pre></p>";
    } else {
    print "<br>$values</p>";
    } */
// Check validity of input.
  if (is_array($values) OR is_string($values) OR is_numeric($values)) {
// Builds a formatted string from an array of string or numeric values.
    if (is_array($values)) {
      $whereString = "(";
      foreach ($values as $id) {
        if (is_numeric($id)) {
          $whereString .= "$id,";
        } elseif (is_string($id)) {
          $id = escape_string($id);
          if (!$id) {
            return FALSE;
          }
          $whereString .= "'$id',";
        }
      }
      $whereString = substr_replace($whereString, ")", -1);
    } else {
// Builds a formatted string from a string
      if (is_string($values)) {
        $values = escape_string($values);
        if (!$values) {
          return FALSE;
        }
        $whereString = "('$values')";
      } else {
// Builds a formatted string from an numeric value.
        $whereString = "($values)";
      }
    }
//print "RETURNING: $whereString<br>";
    return $whereString;
  }
//print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/** Returns a pool of image id's that match a specified search criteria.
 *
 * Function to generate an array of image Id's pulled from the database based on supplied
 * search criteria and using flags (optional) to define if search is for an entire collection or an
 * image group. Results can filtered to ensure included id's are not globally disabled and
 * have a display image file.
 *
 * @param array|int $searchIds A 1D indexed array where each element value is an id of a dataset or
 * image group, or an integer holding the id of a single dataset or image group.
 * @param bool $imageGroupSearch Optional. Default = FALSE. True applies search id's to image
 * groups. False applies search id's to datasets.
 * @param bool $filtered Optional. Default = FALSE. True ensures a returned image_id is not
 * globally disabled and has display file. False ignores this check.
 * @return array|boolean On success returns a 1D indexed array where element values contain
 * image_ids.  <b>OR</b><br>On failure returns boolean FALSE.
 */
function retrieve_image_id_pool($searchIds, $imageGroupSearch = FALSE, $filtered = FALSE) {
  /* print "<p><b>In retrieve_image_ids function.</b><br>Arguments:<br><pre>";
    print_r($searchIds);
    print "</pre>$imageGroupSearch<br>$filtered</p>"; */
// Define PHP settings and Variables
  $imageIdsReturn = array();

// Build the search query
  $whereString = where_in_string_builder($searchIds);
  $imageIdQuery = "SELECT image_id FROM ";
  switch ($imageGroupSearch) {
    case FALSE: // $searchIds represent datasets to be queried in images table
      $imageIdQuery .= "images WHERE dataset_id IN $whereString";
      if ($filtered == TRUE) {
// Pool results should exclude disabled images or those without display images
        $imageIdQuery .= " AND is_globally_disabled = 0 AND has_display_file = 1";
      }
      break;
    case TRUE: // $searchIds represent image_group ids to be queried in image_groups table
      $imageIdQuery .= "image_groups WHERE image_group_id IN $whereString";
      if ($filtered == TRUE) {
// Pool results should exclude disabled images or those without display images
        $imageIdQuery .= " AND is_globally_disabled = 0 AND has_display_file = 1";
      }
      break;
    default: // Invalid input supplied
//print "RETURNING: FALSE<br>";
      return FALSE;
  }
  $imageIdResult = run_database_query($imageIdQuery);
  if ($imageIdResult) {
// Build the array to return.
    while ($imageId = $imageIdResult->fetch_assoc()) {
      $imageIdsReturn[] = $imageId['image_id'];
    }
// print "RETURNING: imageIdsReturn Array<br>";
    return $imageIdsReturn;
  }
// print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Returns image metadata based on a supplied imageId(s).
 *
 * Accepts a single image id or an array of ids and returns the metadata for the image.
 *
 * @param int|array $imageIds A single image id or an array of image ids in a 1D array where
 * element values contain image_ids.
 * @return array|boolean On success returns either a 2D array (where 1st dimension element values
 * hold an array for each image and 2nd dimension element values hold the image metadata) if
 * $imageIds value was an array, or a 1D array (where each element value holds image metadata) if
 * $imageIds was an integer.<b>OR</b><br>On failure returns FALSE.
 */
function retrieve_image_metadata($imageIds) {
  /* print "<p><b>In retreive_image_metadata function.</b><br>Arguments:";
    if (is_array($imageIds)) {
    print "<br>An array of values.</p>";
    print "<pre>";
    print_r($imageIds);
    print "</pre></p>";
    } else {
    print "<br>$imageIds</p>";
    } */
// Define PHP settings and Variables
  $imageDataReturn = array();

// Check validity of input data
  if (is_numeric($imageIds) OR is_array($imageIds)) {
// Build and run the query
    $imagesToQuery = where_in_string_builder($imageIds);
    $imageDataQuery = "SELECT * FROM images WHERE image_id IN $imagesToQuery";
    $imageDataResults = run_database_query($imageDataQuery);
    if ($imageDataResults) {
      if (is_numeric($imageIds)) {
        $imageDataReturn = $imageDataResults->fetch_assoc();
      } else {
        while ($result = $imageDataResults->fetch_assoc()) {
          $imageDataReturn[] = $result;
        }
      }
      /* print "RETURNING: <pre>";
        print_r($imageDataReturn);
        print '</pre>'; */
      return $imageDataReturn;
    }
  }
// print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Builds an image location string.
 *
 * Builds an image location string for inclusion in the image header string in the UI.
 *
 * @param array $imageMetadata A 1D associative array containing feature, city and state keys
 * derived from the image table of the iCoast DB.
 * @return string|boolean On success returns a formatted string <b>OR</b><br>
 * On failure returns boolean FALSE.
 */
function build_image_location_string($imageMetadata) {
  /* print "<p><b>In build_image_location_string function.</b><br>Arguments:<br><pre>";
    print_r($imageMetadata);
    print "</pre></p>"; */

// => Define includes, variables, constants, etc.
  $imageLocation = '';

  if (is_array($imageMetadata)) {
// If no feature data is available then skip inclusion.
    if (!empty($imageMetadata['feature'])) {
      $imageLocation .= $imageMetadata['feature'] . ', ';
    }
    $imageLocation .= $imageMetadata['city'] . ', ' . $imageMetadata['state'];
// print "RETURNING: $imageLocation<br>";
    return $imageLocation;
  }
// print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Function to find the dataset id's that comprise a given collection.
 *
 * Takes the database id of a specified collection and retrieves and returns a list of database
 * row id's for each dataset that makes up that collection.
 *
 * @param integer $collectionId The database id of the collection to be queried.
 * @return array|boolean If successful returns a 1D indexed array where each element value contains
 * a datset id. Returns FALSE on failure.
 */
function find_datasets_in_collection($collectionId) {
// print "<p><b>In find_datasets_in_collection function</b>.<br>Arguments:<br>$collectionId</p>";
  $datasets = array();
  if (is_numeric($collectionId)) {
    $datasetsQuery = "SELECT dataset_id FROM datasets WHERE collection_id = $collectionId";
    $datasetsResult = run_database_query($datasetsQuery);
    if ($datasetsResult) {
      while ($singleDataset = $datasetsResult->fetch_assoc()) {
        $datasets[] = $singleDataset['dataset_id'];
      }
      /* print "RETURNING: <pre>";
        print_r($datasets);
        print '</pre>'; */
      return $datasets;
    }
  }
// print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Returns a formatted time string adjusted for timezone and daylight savings time.
 *
 * Function that accepts a UTC time value (Unix timestamp or MYSQL formatted date/time string) and
 * if supplied adjusts the time for a specified longitudinal position accounting for timezone
 * and daylight savings changes and then returns a date/time string in the specified format.
 *
 * @param int|string $time Time value specified as either an integer representing time since Unix
 * epoch, or a string in standard MySQL DateTime format ('Y-m-d H:i:s')
 * @param int|double|decimal $longitude Value representing a specific longitudinal location to
 * which the time should be adjusted to.
 * @param string $format The desired format of the output string following formatting guidelines
 * given here: {@link http://us2.php.net/manual/en/function.date.php PHP Date formatting}.
 * @return string|boolean Returns a formatted date/time string on success <b>OR</b><br>
 * On failure returns boolean FALSE.
 */
function utc_to_timezone($time, $format, $longitude = NULL) {
// print "<p><b>In utc_to_timezone function.</b><br>Arguments:<br>$time<br>$longitude<br>$format</p>";
  // Define PHP settings, constants, and variables
  $error = false;
  // Validate the inputs
  if (is_null($time) OR is_null($format) OR !is_string($format)) {
    $error = true;
  }
  // Create DateTime Object
  if (!$error AND is_numeric($time)) {
    $timeObject = DateTime::createFromFormat('U', $time, new DateTimeZone('UTC'));
  } elseif (!$error AND is_string($time)) {
    $timeObject = DateTime::createFromFormat('Y-m-d H:i:s', $time, new DateTimeZone('UTC'));
  }
  if ($timeObject) {
    // if longitude is given set the timezone and adjust $timeObject for it)
    If (!is_null($longitude) && is_numeric($longitude)) {
      // Determine image timezone
      if ($longitude >= -85.388) {
        $timeZone = 'America/New_York';
      } elseif ($longitude < -85.388 AND $longitude >= -105) {
        $timeZone = 'America/Chicago';
      } elseif ($longitude < -105 AND $longitude >= -128) {
        $timeZone = "America/Los_Angeles";
      }
      if (isset($timeZone)) {
        $timeObject->setTimezone(new DateTimeZone($timeZone));
      }
    }
    //Format the time to the given format.
    $formattedTime = $timeObject->format($format);
    if ($formattedTime) {
      //print "RETURNING: $formattedTime<br>";
      return $formattedTime;
    }
  }
  //print "RETURNING: FALSE<br>";
  return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Returns metadata based on a supplied id(s) and entity name.
 *
 * Accepts a single  id or an array of ids and returns the metadata for the entity.
 *
 * @param int|array $ids A single integer id or an array of ids in a 1D array
 * where element values contain ids.
 * @param string $entity The name of the entity to be queried. ('image', 'dataset', 'collection').
 * @return array|boolean On success returns either a 2D array (where 1st dimension element values
 * hold an array for each entity and 2nd dimension element values hold the entity metadata) if
 * $ids value was an array, or a 1D array (where each element value holds image metadata) if
 * $ids was an integer.<b>OR</b><br>On failure returns FALSE.
 */
function retrieve_entity_metadata($ids, $entity) {
  /* print "<p><b>In retrieve_collection_metadata function.</b><br>Arguments:";
    print "<br>Entity: $entity";
    if (is_array($ids)) {
    print "<br>An array of values.</p>";
    print "<pre>";
    print_r($ids);
    print "</pre>";
    } else {
    print "<br>IDs: $ids</p>";
    } */
  // Define PHP settings and Variables
  $returnData = array();
  // Check validity of input data
  if (!is_null($ids) && (is_numeric($ids) || is_array($ids)) && !is_null($entity) &&
          is_string($entity)) {
    switch ($entity) {
      case 'dataset':
        $table = 'datasets';
        $column = 'dataset_id';
        break;
      case 'image':
        $table = 'images';
        $column = 'image_id';
        break;
      case 'collection';
        $table = 'collections';
        $column = 'collection_id';
        break;
      case 'project':
        $table = 'projects';
        $column = 'project_id';
        break;
      default:
        print "RETURNING: FALSE";
        return FALSE;
    }
    // Build and run the query
    $idsToQuery = where_in_string_builder($ids);
    $metadataQuery = "SELECT * FROM $table WHERE $column IN $idsToQuery";
    $metadataResults = run_database_query($metadataQuery);
    if ($metadataResults) {
      if (is_numeric($ids)) {
        $returnData = $metadataResults->fetch_assoc();
      } else {
        while ($result = $metadataResults->fetch_assoc()) {
          $returnData[] = $result;
        }
      }
      /* print "RETURNING: <pre>";
        print_r($imageDataReturn);
        print '</pre>'; */
      return $returnData;
    }
  }
  // print "RETURNING: FALSE<br>";
  return FALSE;
}
?>

