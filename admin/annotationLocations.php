<?php
require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    print "No Cookie Data<br>Please login to iCoast first.";
//    header('Location: index.php');
    exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
if (!$userData) {
    print "Failed iCoast Authentication<br>Please logout and then back in to iCoast.";
    exit;
}
$authCheckCode = generate_cookie_credentials($DBH, $userId);

if ($userData['account_type'] != 4) {
    print "Insufficient Permissions<br>Access Denied.";
//    header('Location: index.php');
    exit;
}

$count = 0;
$jsPhotoArray = '';

$locationQuery = "SELECT i.image_id, i.latitude, i.longitude, i.city, i.state "
        . "FROM annotations a "
        . "LEFT JOIN images i ON a.image_id = i.image_id "
        . "WHERE a.user_id != 16 AND a.user_id != 2 AND a.user_id != 1 "
        . "AND annotation_completed = 1 "
        . "ORDER BY i.state, i.city";
$locationParams = array();
$locationRresult = run_prepared_query($DBH, $locationQuery, $locationParams);
while ($annotaion = $locationRresult->fetch(PDO::FETCH_ASSOC)) {
    $imageId = $annotaion['image_id'];
    $latitude = $annotaion['latitude'];
    $longitude = $annotaion['longitude'];
    $city = $annotaion['city'];
    $state = $annotaion['state'];
    $count++;
    $jsPhotoArray .= "$count: {imageId: '$imageId', latitude: '$latitude', longitude: '$longitude', state: '$state', city: '$city'},";
}
$jsPhotoArray = rtrim($jsPhotoArray, ',');

print $count;
?>
<!DOCTYPE html>
<html>
    <head>
        <title>iCoast Annotation Location</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css" />
        <link rel="stylesheet" href="../css/markerCluster.css" />
        <style>
            #map {
                height: 768px;
                width: 1024px;
            }
        </style>
        <script src="http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js"></script>
        <script src="../scripts/leafletMarkerCluster-min.js"></script>
        <script>
            $(document).ready(function() {
                var photoArray = {<?php print $jsPhotoArray ?>};
                var map = L.map('map');
                L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
                }).addTo(map);
                L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);

                L.control.scale({
                    position: 'topright',
                    metric: false
                }).addTo(map);
                markers = L.markerClusterGroup({
                    disableClusteringAtZoom: 15,
                    maxClusterRadius: 60
                });
                $.each(photoArray, function(key, photo) {
                    console.log(key);
                    var marker = L.marker([photo.latitude, photo.longitude]);
                    marker.bindPopup('Image ' + photo.imageId + ' taken near: ' + photo.state + ', ' + photo.city);
                    markers.addLayer(marker);
                });
                map.fitBounds(markers.getBounds());
                markers.addTo(map);
            });
        </script>

    </head>
    <body>
        <div id="map"></div>
        <?php print $count ?>;
    </body>
</html>

