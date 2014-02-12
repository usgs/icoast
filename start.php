<?php
require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;
$numberOfProjects = 0;
$filtered = TRUE;

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
$postImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
// Find post image metadata $postImageMetadata
if (!$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image')) {
  //  Placeholder for error management
  exit("Image $postImageId not found in Database");
}
$postImageLatitude = $postImageMetadata['latitude'];
$postImageLongitude = $postImageMetadata['longitude'];
$postDisplayImageURL = "images/datasets/{$postImageMetadata['dataset_id']}/main/{$postImageMetadata['filename']}";

$bodyHTML = "";
$bodyHTML = <<<EOL
        <h1>iCoast - Did the Coast Change?</h1>
        <h2>Choose your next photo location.</h2>
        <p>A selection of coastal photos are available for you to annotate and a random photo has already been selected for you.
          You have the option to either use this random photo or to pick one from a map.</p>
          <p>Select a button below to make your choice.</p>
        <div class="buttonWrapper">
        <p>Random</p>
          <button class="formButton" type="button" id="randomButton"><img src="images/system/dice.png"></button>
        </div>
        <div class="buttonWrapper">
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
    <link rel="stylesheet" href="css/start2.css">
  </head>
  <body id="wrapper">
    <div id="startWrapper">

      <?php print $bodyHTML; ?>

    </div>





    <div id="mapWrapper">
      <div id="mapContent">
        <div>
          <h1 id="mapHeader">iCoast Map Navigator
            <button title="Click to exit from map view with no changes." id="mapHide" class="clickableButton">
              X
            </button>
          </h1>

        </div>


        <div id="leftMapColumn" class="mapColumn">

          <div id="mapcurrentImageHeader">
            <p class="sectionHeader">Random Image Already Selected For You</p>
          </div>
          <div class="mapImageWrapper" id="mapCurrentImage">
            <img class="mapImage"  src="<?php print $postDisplayImageURL ?>">
          </div>

          <div id="selectedMapImage">
            <div id="mapSelectedImageHeader">
              <p class="sectionHeader" id="selectedMapImageHeader"></p>
            </div>
            <div class="mapImageWrapper" >
              <img class="mapImage" id="mapSelectedImage" src="">
            </div>
          </div>
          <div id="mapDisplayControls">
            <button title="Click to load the selected image into iCoast for annotation." id="mapLoadImageButton" class="clickableButton">
              Choose this Photo to Tag
            </button>
          </div>
        </div>
        <div id="rightMapColumn" class="mapColumn">
          <input id="pac-input" class="controls" type="text" placeholder="Search Box">
          <div id="mapCanvas">
          </div>
          <img id="mapLoadingBar" class="loadingBar" title="Loading other available image data..." src="images/system/loading.gif">

          <div id="mapLegend">
            <div class="mapLegendRow">
              <p id="mapInstruction">ZOOM-IN TO SELECT A POST-STORM PHOTO</p>
            </div>
            <div class="mapLegendRow">
              <div class="mapLegendRowIcon">
                <img src="images/system/clusterLegendIcon.png" width="24" height="24">
              </div>
              <div class="mapLegendRowText">
                <p>Clustering of Photos</p>
              </div>
            </div>
            <div class="mapLegendRow">
              <div class="mapLegendRowIcon">
                <img src="images/system/photo.png" width="20" height="24">
              </div>
              <div class="mapLegendRowText">
                <p>Post-Storm Photo</p>
              </div>
            </div>
            <div class="mapLegendRow">
              <div class="mapLegendRowIcon">
                <img src="images/system/photoCurrent.png" width="20" height="24">
              </div>
              <div class="mapLegendRowText">
                <p>Randomly Selected Photo</p>
              </div>
            </div>
            <div class="mapLegendRow">
              <div class="mapLegendRowIcon">
                <img src="images/system/photoSelected.png" width="20" height="24">
              </div>
              <div class="mapLegendRowText">
                <p>Selected Post-Storm Photo</p>
              </div>
            </div>
          </div>
          <div id="mapControls">
            <button title="Click to jump the map to the currently displayed image lovation." id="centerMapButton" class="clickableButton">
              Jump To Current Photo
            </button>
            <button title="Click to show or hide other selectable images within the map boundaries." id="mapMarkerToggle" class="clickableButton">
              Hide Other Photos
            </button>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
<script>
  icDisplayedTask = 1;
  icMap = null;
  icMarkers = null;
  icCurrentImageLatLon = null;
  icCurrentImageMarker = null;
  icBoundsChanged = false;
  icMarkersShown = false;
  icMarkerClusterer = null;
  icSelectedMapImage = "";
  icProjectId = <?php print $projectId ?>;
  function mapBoundaries() {
//        console.log("In boundaries");
    var bounds = icMap.getBounds();
    var boundaries = {
      north: encodeURIComponent(bounds.getNorthEast().lat()),
      east: encodeURIComponent(bounds.getNorthEast().lng()),
      south: encodeURIComponent(bounds.getSouthWest().lat()),
      west: encodeURIComponent(bounds.getSouthWest().lng()),
      projectId: <?php print $projectId ?>,
      userId: <?php print $userId ?>,
      currentImageId: <?php print $postImageId ?>
    };
    return boundaries;
  } //End function mapBoundaries




  function initializeMaps() {
    icCurrentImageLatLon = new google.maps.LatLng(<?php print $postImageLatitude . "," . $postImageLongitude ?>);
    var mapOptions = {
      center: icCurrentImageLatLon,
      zoom: 10,
      mapTypeId: google.maps.MapTypeId.HYBRID
    };
    icMap = new google.maps.Map(document.getElementById("mapCanvas"),
            mapOptions);





    var markers = [];
    var input = (document.getElementById('pac-input'));
    icMap.controls[google.maps.ControlPosition.TOP_LEFT].push(input);
    var searchBox = new google.maps.places.SearchBox((input));
    // [START region_getplaces]
    // Listen for the event fired when the user selects an item from the
    // pick list. Retrieve the matching places for that item.
    google.maps.event.addListener(searchBox, 'places_changed', function() {
      var places = searchBox.getPlaces();
      for (var i = 0, marker; marker = markers[i]; i++) {
        marker.setMap(null);
      }
      // For each place, get the icon, place name, and location.
      markers = [];
      var bounds = new google.maps.LatLngBounds();
      for (var i = 0, place; place = places[i]; i++) {
        var image = {
          url: place.icon,
          size: new google.maps.Size(71, 71),
          origin: new google.maps.Point(0, 0),
          anchor: new google.maps.Point(17, 34),
          scaledSize: new google.maps.Size(25, 25)
        };

        // Create a marker for each place.
        var marker = new google.maps.Marker({
          map: icMap,
          icon: image,
          title: place.name,
          position: place.geometry.location
        });

        markers.push(marker);

        bounds.extend(place.geometry.location);
      }
      icMap.fitBounds(bounds);
      var zoom = icMap.getZoom();
      console.log(zoom);
      if (zoom > 13) {
        icMap.setZoom(13);
      }
    });

    // [END region_getplaces]

    // Bias the SearchBox results towards places that are within the bounds of the
    // current map's viewport.
    google.maps.event.addListener(icMap, 'bounds_changed', function() {
      var bounds = icMap.getBounds();
      searchBox.setBounds(bounds);
    });



    var mapCurrentIcon = {
      size: new google.maps.Size(32, 37),
      anchor: new google.maps.Point(-25, 37),
      url: 'images/system/photoCurrent.png'
    };
    icCurrentImageMarker = new google.maps.Marker({
      position: icCurrentImageLatLon,
      //          map: icMap,
      animation: google.maps.Animation.DROP,
      icon: mapCurrentIcon,
      //          icon: 'images/system/photoCurrent.png',
      clickable: false
              //          title: 'Location of the currently displayed image. Taken near <?php print $markerToolTip; ?>'
    });
    google.maps.event.addListener(icMap, 'idle', function() {
      mapBoundsChanged();
    });
  } // End function initializeMaps



  function mapBoundsChanged() {
//        console.log('mapBoundsChanged' + icMarkersShown);
    if (icMarkersShown === true) {
      if (icMarkerClusterer !== null) {
        icMarkerClusterer.clearMarkers();
      }

      icBoundsChanged = true;
      toggleMarkers();
    }
  } // End funciton mapBoundsChanged

//      function clearMarkers() {
//        if (icMarkersShown === true) {
//          icMarkerClusterer.clearMarkers()
//          icMarkersShown === false;
//        }
//      }

  function toggleMarkers() {
    if (icMarkersShown === false || icBoundsChanged === true) {
//          console.log('loading bar');
      $('#mapLoadingBar').css('display', 'block');
      $('#mapMarkerToggle').text("Hide Other Photos");

      icMarkersShown = true;
      icBoundsChanged = false;
      var currentBoundaries = mapBoundaries();
      $.getJSON('ajax/mapUpdater.php', currentBoundaries, function(ajaxMarkerData) {

        icMarkers = new Array();
        $.each(ajaxMarkerData, function(imageNo, imageData) {
          var thisMarker = null;
          var markerLatLng = new google.maps.LatLng(imageData.latitude, imageData.longitude);
          var infoString = 'Image taken near: ' + imageData.location_string;
//              if (imageData.collation_number > 1) {
//                thisMarker = new google.maps.Marker({
////                  map: icMap,
//                  position: markerLatLng,
//                  icon: 'images/system/multiplePhotos.png',
//                  title: imageData.collation_number + ' images. Near ' + imageData.location_string
////                  collation: imageData.collation_number
//                });
//              } else {
          thisMarker = new google.maps.Marker({
//                  map: icMap,
            position: markerLatLng,
            icon: 'images/system/photo.png',
            title: 'Image taken near ' + imageData.location_string
//                  collation: imageData.collation_number

          });
//              }
          icMarkers.push(thisMarker);
          google.maps.event.addListener(thisMarker, 'click', (function(marker) {
            return function() {
              $('#mapSelectedImage').attr("src", imageData.image_url);
              icSelectedMapImage = "classification.php?projectId=" + icProjectId + "&imageId=" + imageData.image_id;
              $('#selectedMapImageHeader').text('Post-Storm Photo Selected on Map near ' + imageData.location_string);
              $('#selectedMapImage').css('display', 'block');
              $("#mapLoadImageButton").css('display', 'inline-block');
              dynamicSizing(icDisplayedTask);
              google.maps.event.trigger(icMap, "resize");
              for (var i = 0; i < icMarkers.length; i++) {
//                    if (icMarkers[i].collation > 1) {
//                      icMarkers[i].setIcon('images/system/multiplePhotos.png');
//                    } else {
                icMarkers[i].setIcon('images/system/photo.png');
//                    }
              }
//                  if (marker.collation > 1) {
//                    marker.setIcon('images/system/multiplePhotosSelected.png');
//                  } else {
              marker.setIcon('images/system/photoSelected.png');
//                  }
            };
          })(thisMarker));
        });
        if (icMarkerClusterer === null) {
          var mcOptions = {
            'gridSize': 60,
            'minimumClusterSize': 2,
            'maxZoom': 14,
            'imagePath': 'images/system/m'
          };
          icMarkerClusterer = new MarkerClusterer(icMap, icMarkers, mcOptions);
//              google.maps.event.addListener(icMarkerClusterer, "clusteringend", function() {
//                console.log('removing bar');
////                $('#mapLoadingBar').css('display', 'none');
//              });

        } else {
//              console.log("Adding Markers");
          icMarkerClusterer.addMarkers(icMarkers);
        }
        $('#mapLoadingBar').css('display', 'none');
//            icCurrentImageMarker.setZIndex(9999999);

      });
    } else {
//          clearMarkers();
      icMarkersShown = false;
      icMarkerClusterer.clearMarkers();
//          icMarkers = null;
      $('#selectedMapImage').css('display', 'none');
      $("#mapLoadImageButton").css('display', 'none');
      $('#mapMarkerToggle').text("Show Other Photos");
    }

  } // End function toggleMarkers


  function hideLoader(isPost) {
    if (isPost) {
      $('#postLoadingBar').hide();
    } else {
      $('#preLoadingBar').hide();
    }
  } // End funtion hideLoader

  function dynamicSizing() {
    // Calculate image sizes for Map is shown
    if ($('#mapWrapper').css('display') === 'block') {
      var mapContentHeight = $('#mapContent').height();
      var mapContentWidth = $('#mapContent').width();
      var mapHeaderHeight = $('#mapHeader').innerHeight();
      var mapColumnHeight = mapContentHeight - mapHeaderHeight;
//          console.log(mapColumnHeight);
      $('.mapColumn').height(mapColumnHeight);
      var mapCurrentImageHeaderHeight = $('#mapcurrentImageHeader').height() + 3;
//          var mapSelectedImageHeaderHeight = $('#mapSelectedImageHeader').height() + 3;
//          if (mapSelectedImageHeaderHeight == 3) {
      var mapSelectedImageHeaderHeight = 29;
////          }
//          var mapDisplayButtonHeight = $('#mapDisplayControls').height() + 10;
//          if (mapDisplayButtonHeight == 10) {
      mapDisplayButtonHeight = 46;
//          }
      var mapHeightPerImage = (mapColumnHeight - mapCurrentImageHeaderHeight -
              mapSelectedImageHeaderHeight - mapDisplayButtonHeight) / 2;
      var mapMaxImageWidth = (mapHeightPerImage - 10) / 0.65;
//          console.log(mapCurrentImageHeaderHeight);
//          console.log(mapSelectedImageHeaderHeight);
//          console.log(mapDisplayButtonHeight);
//          console.log(mapHeightPerImage);
//          console.log(mapMaxImageWidth);
      $('.mapImageWrapper').css('max-width', mapMaxImageWidth);
      var leftMapColumnPadding = $('#leftMapColumn').innerWidth() - $('#leftMapColumn').width();
      var mapImageWrapperPadding = $('.mapImageWrapper').innerWidth() - $('.mapImageWrapper').width();
      var leftMapColumnWidth = Math.floor(mapMaxImageWidth + mapImageWrapperPadding);
      $('#leftMapColumn').width(leftMapColumnWidth);
      var mapRightColumnPaddingRight = $('#rightMapColumn').css('padding-right').replace("px", "");
      var rightMapColumnWidth = Math.floor(mapContentWidth - leftMapColumnWidth -
              leftMapColumnPadding - mapRightColumnPaddingRight - 1);
      $('#rightMapColumn').width(rightMapColumnWidth);
      var mapControlsButtonHeight = $('#mapControls').height() + 10;
//          console.log(mapControlsButtonHeight);
      var mapCanvasHeight = mapColumnHeight - mapControlsButtonHeight;
//          console.log(mapCanvasPaddingRight);
//          console.log(mapCanvasWidth);
      $('#mapCanvas').height(mapCanvasHeight);
    }
  } // End function dynamicSizing

  $(document).ready(function() {
    $(window).resize(function() {
      dynamicSizing(icDisplayedTask);
    });
    var script = document.createElement("script");
    script.type = "text/javascript";
    script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
    document.body.appendChild(script);
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
    dynamicSizing();
    $('#mapHide').click(function() {
      console.log("Map close button CLicked");
      icCurrentImageMarker.setMap();
      $('#mapWrapper').fadeToggle();
    });
    $('#mapMarkerToggle').click(function() {
      toggleMarkers();
    });
    $('#centerMapButton').click(function() {
      icMap.setCenter(icCurrentImageLatLon);
    });
    $('#mapLoadImageButton').click(function() {
      window.location.href = icSelectedMapImage;
    });
    $('#randomButton').click(function() {
      window.location.href = "classification.php?projectId=" + icProjectId + "&imageId=" + "<?php print $postImageId ?>";
    });
  });

</script>
