<?php
require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;
$numberOfProjects = 0;
$filtered = TRUE;
$pageName = "start";

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode']) || !isset($_POST['projectId'])) {
  header('Location: login.php');
  exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);


$projectId = $_POST['projectId'];
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
$newRandomImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
// Find post image metadata $postImageMetadata
if (!$newRandomImageMetadata = retrieve_entity_metadata($DBH, $newRandomImageId, 'image')) {
  //  Placeholder for error management
  exit("Image $newRandomImageId not found in Database");
}
$newRandomImageLatitude = $newRandomImageMetadata['latitude'];
$newRandomImageLongitude = $newRandomImageMetadata['longitude'];
$newRandomImageDisplayURL = "images/datasets/{$newRandomImageMetadata['dataset_id']}/main/{$newRandomImageMetadata['filename']}";

$bodyHTML = "";
$bodyHTML = <<<EOL
        <h1>Choose a Photo to Tag</h1>
        <p>Click the Random button to tag a random photo.<br>
          Click the Map button to select a photo in a particular location.</p>
        <div class="postNavButtonWrapper">
        <p>Random</p>
          <button class="formButton" type="button" id="randomButton"><img src="images/system/dice.png"></button>
        </div>
        <div class="postNavButtonWrapper">
          <p>Map</p>
          <button class="formButton" type="button" id="mapButton"><img src="images/system/map.png"></button>
        </div>


EOL;
?>

<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Start Screen</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="scripts/markerClusterPlus.js"></script>
    <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
    <link rel="stylesheet" href="css/icoast.css">
    <link rel="stylesheet" href="css/staticHeader.css">
  </head>
  <body id="body">
    <?php
    require("includes/header.php");
    ?>
    <div id="contentWrapper">

      <?php print $bodyHTML; ?>

    </div>
    <?php
    require("includes/mapNavigator.php");
    print $mapHTML;
    ?>

  </body>
</html>
<script>
<?php print $mapScript; ?>

  $(document).ready(function() {

<?php print $mapDocumentReadyScript; ?>

    $('#mapButton').click(function() {
      $('#mapWrapper').fadeToggle(400, function() {
        dynamicSizing();
        google.maps.event.trigger(icMap, "resize");
        icMap.setCenter(icCurrentImageLatLon);
        icMarkersShown = false;
        toggleMarkers();
        icCurrentImageMarker.setMap(icMap);
      });
    });
    $('#randomButton').click(function() {
      window.location.href = "classification.php?projectId=" + icProjectId + "&imageId=" + "<?php print $newRandomImageId ?>";
    });
  });

</script>
