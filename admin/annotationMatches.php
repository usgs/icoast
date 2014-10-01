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
$computerMatchCount = 0;
$userMatchCount = 0;
$failLinks = array();
$failNav = array();
$matchingPhotoArray = '';
$nonMatchingPhotoArray = '';

$matchesQuery = "SELECT a.image_id, a.user_match_id, m.pre_image_id, i.latitude, i.longitude "
        . "FROM annotations a "
        . "LEFT JOIN matches m ON a.image_id = m.post_image_id "
        . "LEFT JOIN images i ON a.image_id = i.image_id "
        . "WHERE a.user_id != 16 AND a.user_id != 2 AND a.user_id != 1 "
        . "AND annotation_completed = 1";
$matchesParams = array();
$matchesresult = run_prepared_query($DBH, $matchesQuery, $matchesParams);
while ($match = $matchesresult->fetch(PDO::FETCH_ASSOC)) {
    $imageId = $match['image_id'];
    $computerMatchId = $match['pre_image_id'];
    $userMatchId = $match['user_match_id'];
    $latitude = $match['latitude'];
    $longitude = $match['longitude'];

    if ($userMatchId == $computerMatchId) {
        $computerMatchCount++;
        $matchingPhotoArray .= "$computerMatchCount: {imageId: '$imageId', latitude: '$latitude', longitude: '$longitude'},";
    } else {
        $userMatchCount ++;
        $matchDelta = $match['user_match_id'] - $match['pre_image_id'];
        if ($matchDelta > 0) {
            $direction = 'left';
        } else {
            $direction = 'right';
        }
        $matchDelta = abs($matchDelta);
        $failLink = "<br><a href=\"http://coastal.er.usgs.gov/icoast/classification.php?projectId=1&imageId=" . $match['image_id'] . "\" target=\"_blank\">iCoast Link</a>";
        $failNav = "<br>The user selected an image <strong>$matchDelta image(s)</strong> to the $direction of the computer match.";

        $userMatchPhotoLocationQuery = "SELECT latitude, longitude FROM images where image_id = :userMatchId";
        $userMatchPhotoLocationParams['userMatchId'] = $userMatchId;
        $userMatchPhotoLocationResult = run_prepared_query($DBH, $userMatchPhotoLocationQuery, $userMatchPhotoLocationParams);
        $userMatchPhotoMetadata = $userMatchPhotoLocationResult->fetch(PDO::FETCH_ASSOC);
        $userMatchLatitude = $userMatchPhotoMetadata['latitude'];
        $userMatchLongitude = $userMatchPhotoMetadata['longitude'];

        $computerMatchPhotoLocationQuery = "SELECT latitude, longitude FROM images WHERE image_id = :computerMatchId";
        $computerMatchPhotoLocationParams['computerMatchId'] = $computerMatchId;
        $computerMatchPhotoLocationResult = run_prepared_query($DBH, $computerMatchPhotoLocationQuery, $computerMatchPhotoLocationParams);
        $computerMatchPhotoMetadata = $computerMatchPhotoLocationResult->fetch(PDO::FETCH_ASSOC);
        $computerMatchLatitude = $computerMatchPhotoMetadata['latitude'];
        $computerMatchLongitude = $computerMatchPhotoMetadata['longitude'];

        $nonMatchingPhotoArray .= "$userMatchCount: {photoImageId: '$imageId', photoLatitude: '$latitude', photoLongitude: '$longitude', computerMatchLatitude:'$computerMatchLatitude', computerMatchLongitude: '$computerMatchLongitude', userMatchLatitude:'$userMatchLatitude', userMatchLongitude: '$userMatchLongitude', failLink: '$failLink', failNav: '$failNav'},";
    }
}
$matchingPhotoArray = rtrim($matchingPhotoArray, ',');
$nonMatchingPhotoArray = rtrim($nonMatchingPhotoArray, ',');
?>

<!DOCTYPE html>
<html>
    <head>
        <title>iCoast Annotation Location</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css" />
        <!--<link rel="stylesheet" href="../css/markerCluster.css" />-->
        <style>
            #map {
                height: 768px;
                width: 1024px;
            }
        </style>
        <script src="http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js"></script>
        <!--<script src="../scripts/leafletMarkerCluster-min.js"></script>-->
        <script>
                    $(document).ready(function() {
            var matchingPhotoArray = {<?php print $matchingPhotoArray ?>};
                    var nonMatchingPhotoArray = {<?php print $nonMatchingPhotoArray ?>};
                    var map = L.map('map');
                    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
                    }).addTo(map);
                    L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
//                    L.tileLayer('http://basemap.nationalmap.gov/ArcGIS/rest/services/USGSImageryOnly/MapServer/tile/{z}/{y}/{x}').addTo(map);
                    L.control.scale({
                    position: 'topright',
                            metric: false
                    }).addTo(map);
                    var matchingMarkers = L.featureGroup();
                    var nonMatchingMarkers = L.featureGroup();
                    var markers = L.featureGroup();
                    var userLines = L.featureGroup();
                    var computerLines = L.featureGroup();
                    var nonMatchingIcon = L.icon({
                    iconUrl: '../images/system/redMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    var userPhoto = L.icon({
                    iconUrl: '../images/system/greenMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    var computerPhoto = L.icon({
                    iconUrl: '../images/system/yellowMarker.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                    });
                    $.each(matchingPhotoArray, function(key, photo) {
                    var markerLatLng = L.latLng(photo.latitude, photo.longitude);
                            var marker = L.marker(markerLatLng);
                            marker.bindPopup('Image ' + photo.imageId + '. User selected the computer match.');
                            matchingMarkers.addLayer(marker);
                            markers.addLayer(marker);
                    });
                    $.each(nonMatchingPhotoArray, function(key, photo) {
                    var photoLatLng = L.latLng(photo.photoLatitude, photo.photoLongitude);
                            var userLatLng = L.latLng(photo.userMatchLatitude, photo.userMatchLongitude);
                            var computerLatLng = L.latLng(photo.computerMatchLatitude, photo.computerMatchLongitude);
                            var distanceBetweenPhotos = Math.floor(userLatLng.distanceTo(computerLatLng) * 3.3);
//
                            var photoMarker = L.marker(photoLatLng, {icon: nonMatchingIcon});
                            photoMarker.bindPopup('Image ' + photo.photoImageId + photo.failLink + photo.failNav);
                            nonMatchingMarkers.addLayer(photoMarker);
                            markers.addLayer(photoMarker);
//
                            var userMarker = L.marker(userLatLng, {icon: userPhoto});
                            userMarker.bindPopup('User selected image location.<br>Photo is <strong>' + distanceBetweenPhotos + 'ft </strong>from the computer selected image.');
                            nonMatchingMarkers.addLayer(userMarker);
                            markers.addLayer(userMarker);
//
                            var computerMarker = L.marker(computerLatLng, {icon: computerPhoto});
                            computerMarker.bindPopup('Computer selected image location.<br>Photo is <strong>' + distanceBetweenPhotos + 'ft </strong>from the user selected image.');
                            nonMatchingMarkers.addLayer(computerMarker);
                            markers.addLayer(computerMarker);
                            var computerLine = L.polyline([photoLatLng, computerLatLng], {color: 'yellow'}).addTo(computerLines);
                            var userLine = L.polyline([photoLatLng, userLatLng], {color: 'green'}).addTo(userLines);
                    });
                    map.fitBounds(markers.getBounds());
                    matchingMarkers.addTo(map);
                    nonMatchingMarkers.addTo(map);
                    computerLines.addTo(map);
                    userLines.addTo(map);
            });
        </script>

    </head>
    <body>
        <div id="map"></div>
        <?php print $count ?>;
    </body>
</html>

