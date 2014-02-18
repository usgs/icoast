<?php
//////////
// => Define required files and initial includes
require_once('includes/globalFunctions.php');
require_once('includes/userFunctions.php');
require $dbmsConnectionPath;
//////////
$filtered = TRUE;

//////////
// => No Image ID Page Redirect
// => If the page has been called without a random image id in the query string then generate
// => an image id and redirect back to the page with a string attached.
if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
  header('Location: login.php');
  exit;
}
$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];
$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);

$projectId = "";
if (empty($_POST['projectId']) && empty($_GET['projectId'])) {
  header("location: welcome.php?userType=existing");
  exit;
} else {
  if (!empty($_POST['projectId'])) {
    $projectId = $_POST['projectId'];
  } else {
    $projectId = $_GET['projectId'];
  }
  $validProjectQuery = "SELECT COUNT(*) FROM projects WHERE project_id = :projectId";
  $validProjectParams['projectId'] = $projectId;
  $STH = run_prepared_query($DBH, $validProjectQuery, $validProjectParams);
  $matchingProjectCount = $STH->fetchColumn();
  if ($matchingProjectCount == 0) {
    header("location: welcome.php?userType=existing");
    exit;
  }
}


if (empty($_GET['imageId'])) {
  $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
  $postImageId = random_post_image_id_generator($DBH, $projectId, $filtered, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $userId);
  header("location: classification.php?projectId=$projectId&imageId=$postImageId");
  exit();
}

// Define required files and initial includes
require_once('includes/classificationCode.php');
?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>iCoast: Photo Classification Page</title>

    <link rel='stylesheet' href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700'>
    <link rel="stylesheet" href="css/icoast.css">
    <link rel="stylesheet" href="css/dynamicHeader.css">
    <link rel="stylesheet" href="css/classification.css">
    <link rel="stylesheet" href="css/icoast_icons.css">
    <link rel="stylesheet" href="css/tipTip.css">
    <script src="scripts/markerClusterPlus.js"></script>
    <style type="text/css">
    </style>

    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="scripts/elevateZoom.js"></script>
    <script src="scripts/tipTip.js"></script>


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
      icProjectId = "";
//      function mapBoundaries() {
////        console.log("In boundaries");
//        var bounds = icMap.getBounds();
//        var boundaries = {
//          north: encodeURIComponent(bounds.getNorthEast().lat()),
//          east: encodeURIComponent(bounds.getNorthEast().lng()),
//          south: encodeURIComponent(bounds.getSouthWest().lat()),
//          west: encodeURIComponent(bounds.getSouthWest().lng()),
//          projectId: <?php print $projectId ?>,
//          userId: <?php print $userId ?>,
//          currentImageId: <?php print $postImageId ?>
//        };
//        return boundaries;
//      }

      function initializeMaps() {
        icCurrentImageLatLon = new google.maps.LatLng(<?php print $postImageLatitude . "," . $postImageLongitude ?>);
        var mapOptions = {
          center: icCurrentImageLatLon,
          zoom: 12,
          mapTypeId: google.maps.MapTypeId.HYBRID
        };
        icMap = new google.maps.Map(document.getElementById("mapInsert"),
                mapOptions);
//        var markers = [];
        var input = (document.getElementById('pac-input'));
        icMap.controls[google.maps.ControlPosition.TOP_LEFT].push(input);
//        var searchBox = new google.maps.places.SearchBox((input));
        // [START region_getplaces]
        // Listen for the event fired when the user selects an item from the
        // pick list. Retrieve the matching places for that item.
//        google.maps.event.addListener(searchBox, 'places_changed', function() {
//          var places = searchBox.getPlaces();
//
//          for (var i = 0, marker; marker = markers[i]; i++) {
//            marker.setMap(null);
//          }
//
//          // For each place, get the icon, place name, and location.
//          markers = [];
//          var bounds = new google.maps.LatLngBounds();
//          for (var i = 0, place; place = places[i]; i++) {
//            var image = {
//              url: place.icon,
//              size: new google.maps.Size(71, 71),
//              origin: new google.maps.Point(0, 0),
//              anchor: new google.maps.Point(17, 34),
//              scaledSize: new google.maps.Size(25, 25)
//            };
//
//            // Create a marker for each place.
//            var marker = new google.maps.Marker({
//              map: icMap,
//              icon: image,
//              title: place.name,
//              position: place.geometry.location
//            });
//
//            markers.push(marker);
//
//            bounds.extend(place.geometry.location);
//          }
//          icMap.fitBounds(bounds);
//          var zoom = icMap.getZoom();
//          console.log(zoom);
//          if (zoom > 13) {
//            icMap.setZoom(13);
//          }
//
//        });
        // [END region_getplaces]

        // Bias the SearchBox results towards places that are within the bounds of the
        // current map's viewport.
//        google.maps.event.addListener(icMap, 'bounds_changed', function() {
//          var bounds = icMap.getBounds();
//          searchBox.setBounds(bounds);
//        });


        var mapCurrentIcon = {
          size: new google.maps.Size(32, 37),
//          anchor: new google.maps.Point(-25, 37),
          url: 'images/system/photoCurrent.png'
        };
        icCurrentImageMarker = new google.maps.Marker({
          position: icCurrentImageLatLon,
//          map: icMap,
          animation: google.maps.Animation.DROP,
          icon: mapCurrentIcon,
//          icon: 'images/system/photoCurrent.png',
          clickable: false,
          map: icMap
//          title: 'Location of the currently displayed image. Taken near <?php print $markerToolTip; ?>'
        });
//        icCurrentImageMarker.setMap(icMap);
//        google.maps.event.addListener(icMap, 'idle', function() {
//          mapBoundsChanged();
//        });
      }

//      function mapBoundsChanged() {
////        console.log('mapBoundsChanged' + icMarkersShown);
//        if (icMarkersShown === true) {
//          if (icMarkerClusterer !== null) {
//            icMarkerClusterer.clearMarkers();
//          }
//
//          icBoundsChanged = true;
//          toggleMarkers();
//        }
//      }

//      function clearMarkers() {
//        if (icMarkersShown === true) {
//          icMarkerClusterer.clearMarkers()
//          icMarkersShown === false;
//        }
//      }

//      function toggleMarkers() {
//        if (icMarkersShown === false || icBoundsChanged === true) {
////          console.log('loading bar');
//          $('#mapLoadingBar').css('display', 'block');
//          $('#mapMarkerToggle').text("Hide Other Photos");
//          icMarkersShown = true;
//          icBoundsChanged = false;
//          var currentBoundaries = mapBoundaries();
//          $.getJSON('ajax/mapUpdater.php', currentBoundaries, function(ajaxMarkerData) {
//
//            icMarkers = new Array();
//            $.each(ajaxMarkerData, function(imageNo, imageData) {
//              var thisMarker = null;
//              var markerLatLng = new google.maps.LatLng(imageData.latitude, imageData.longitude);
//              var infoString = 'Image taken near: ' + imageData.location_string;
////              if (imageData.collation_number > 1) {
////                thisMarker = new google.maps.Marker({
//////                  map: icMap,
////                  position: markerLatLng,
////                  icon: 'images/system/multiplePhotos.png',
////                  title: imageData.collation_number + ' images. Near ' + imageData.location_string
//////                  collation: imageData.collation_number
////                });
////              } else {
//              thisMarker = new google.maps.Marker({
////                  map: icMap,
//                position: markerLatLng,
//                icon: 'images/system/photo.png',
//                title: 'Image taken near ' + imageData.location_string
////                  collation: imageData.collation_number
//
//              });
////              }
//              icMarkers.push(thisMarker);
//              google.maps.event.addListener(thisMarker, 'click', (function(marker) {
//                return function() {
//                  $('#mapSelectedImage').attr("src", imageData.image_url);
//                  icSelectedMapImage = "classification.php?projectId=" + icProjectId + "&imageId=" + imageData.image_id;
//                  $('#selectedMapImageHeader').text('Post-Storm Photo Selected on Map near ' + imageData.location_string);
//                  $('#selectedMapImage').css('display', 'block');
//                  $("#mapLoadImageButton").css('display', 'inline-block');
//                  dynamicSizing(icDisplayedTask);
//                  google.maps.event.trigger(icMap, "resize");
//                  for (var i = 0; i < icMarkers.length; i++) {
////                    if (icMarkers[i].collation > 1) {
////                      icMarkers[i].setIcon('images/system/multiplePhotos.png');
////                    } else {
//                    icMarkers[i].setIcon('images/system/photo.png');
////                    }
//                  }
////                  if (marker.collation > 1) {
////                    marker.setIcon('images/system/multiplePhotosSelected.png');
////                  } else {
//                  marker.setIcon('images/system/photoSelected.png');
////                  }
//                };
//              })(thisMarker));
//            });
//            if (icMarkerClusterer === null) {
//              var mcOptions = {
//                'gridSize': 60,
//                'minimumClusterSize': 2,
//                'maxZoom': 14,
//                'imagePath': 'images/system/m'
//              };
//              icMarkerClusterer = new MarkerClusterer(icMap, icMarkers, mcOptions);
////              google.maps.event.addListener(icMarkerClusterer, "clusteringend", function() {
////                console.log('removing bar');
//////                $('#mapLoadingBar').css('display', 'none');
////              });
//
//            } else {
////              console.log("Adding Markers");
//              icMarkerClusterer.addMarkers(icMarkers);
//            }
//            $('#mapLoadingBar').css('display', 'none');
////            icCurrentImageMarker.setZIndex(9999999);
//
//          });
//        } else {
////          clearMarkers();
//          icMarkersShown = false;
//          icMarkerClusterer.clearMarkers();
////          icMarkers = null;
//          $('#selectedMapImage').css('display', 'none');
//          $("#mapLoadImageButton").css('display', 'none');
//          $('#mapMarkerToggle').text("Show Other Photos");
//        }
//
//      }


      function hideLoader(isPost) {
        if (isPost) {
          $('#postLoadingBar').hide();
        } else {
          $('#preLoadingBar').hide();
        }
      }


      function dynamicSizing(icDisplayedTask) {
        // Resize annotation groups to window width
        var taskWidth = $('#task' + icDisplayedTask).width();
        var numberOfGroups = icTaskMap[icDisplayedTask];
        if (numberOfGroups > 0) {
          var groupWidth = (taskWidth / (numberOfGroups)) - 51;
        }
        var subGroups = document.getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
        for (var i = 0; i < subGroups.length; i++) {
          var childNumber = i + 2;
          if (!$('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
            var groupMinWidth = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
            if (groupWidth > groupMinWidth && subGroups[i].borderWidth === 0) {
              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(groupWidth);
            } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 60);
            } else {
              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 1);
            }
          }





        }
        for (var i = 0; i < 5; i++) {
          var childNumber = i + 1;
          if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').length !== 0) {
            if (!$('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
//              console.log("NO ForceWidth");
              var groupMinWidth = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
              var borderWidth = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('border-left-width').replace('px', '');
              if (groupWidth > groupMinWidth && parseInt(borderWidth) === 0) {
                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(groupWidth);
              } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 60);
              } else {
                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 1);
              }
            } else {
//              console.log("ForceWidth");
            }
          }

        }



        // Set group header heights to the same across the board
        $('.groupText, .subGroupText').show();
        $('.groupWrapper h2, .groupWrapper h3').height("");
        var subGroups = document.getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
        var maxHeaderHeight = 0;
        for (var i = 0; i < subGroups.length; i++) {
          var childNumber = i + 2;
          var headerHeight = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ') h3').height();
          if (headerHeight > maxHeaderHeight) {
            maxHeaderHeight = headerHeight;
          }
        }
        $('#task' + icDisplayedTask + ' h3').height(maxHeaderHeight);
        var maxHeaderHeight = 0;
        for (var i = 0; i < 5; i++) {
          var childNumber = i + 1;
          var headerHeight = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ') h2').height();
          if (headerHeight > maxHeaderHeight) {
            maxHeaderHeight = headerHeight;
          }
        }
        $('#task' + icDisplayedTask + ' h2').height(maxHeaderHeight);
        // Dynamically size the page;
        $('html').css('overflow', 'hidden');
        var windowHeight = $(window).height();
        var windowWidth = $(window).width();
        $('html').css('overflow', 'auto');
        if (windowWidth > 1650) {
          windowWidth = 1650;
        }
//        // Calculate image sizes for Map is shown
//        if ($('#mapWrapper').css('display') === 'block') {
//          var mapContentHeight = $('#mapContent').height();
//          var mapContentWidth = $('#mapContent').width();
//          var mapHeaderHeight = $('#mapHeader').innerHeight();
//          var mapColumnHeight = mapContentHeight - mapHeaderHeight;
////          console.log(mapColumnHeight);
//          $('.mapColumn').height(mapColumnHeight);
//          var mapCurrentImageHeaderHeight = $('#mapcurrentImageHeader').height() + 3;
////          var mapSelectedImageHeaderHeight = $('#mapSelectedImageHeader').height() + 3;
////          if (mapSelectedImageHeaderHeight == 3) {
//          var mapSelectedImageHeaderHeight = 29;
//////          }
////          var mapDisplayButtonHeight = $('#mapDisplayControls').height() + 10;
////          if (mapDisplayButtonHeight == 10) {
//          mapDisplayButtonHeight = 46;
////          }
//          var mapHeightPerImage = (mapColumnHeight - mapCurrentImageHeaderHeight -
//                  mapSelectedImageHeaderHeight - mapDisplayButtonHeight) / 2;
//          var mapMaxImageWidth = (mapHeightPerImage - 10) / 0.65;
////          console.log(mapCurrentImageHeaderHeight);
////          console.log(mapSelectedImageHeaderHeight);
////          console.log(mapDisplayButtonHeight);
////          console.log(mapHeightPerImage);
////          console.log(mapMaxImageWidth);
//          $('.mapImageWrapper').css('max-width', mapMaxImageWidth);
//          var leftMapColumnPadding = $('#leftMapColumn').innerWidth() - $('#leftMapColumn').width();
//          var mapImageWrapperPadding = $('.mapImageWrapper').innerWidth() - $('.mapImageWrapper').width();
//          var leftMapColumnWidth = Math.floor(mapMaxImageWidth + mapImageWrapperPadding);
//          $('#leftMapColumn').width(leftMapColumnWidth);
//          var mapRightColumnPaddingRight = $('#rightMapColumn').css('padding-right').replace("px", "");
//          var rightMapColumnWidth = Math.floor(mapContentWidth - leftMapColumnWidth -
//                  leftMapColumnPadding - mapRightColumnPaddingRight - 1);
//          $('#rightMapColumn').width(rightMapColumnWidth);
//          var mapControlsButtonHeight = $('#mapControls').height() + 10;
////          console.log(mapControlsButtonHeight);
//          var mapCanvasHeight = mapColumnHeight - mapControlsButtonHeight;
////          console.log(mapCanvasPaddingRight);
////          console.log(mapCanvasWidth);
//          $('#mapCanvas').height(mapCanvasHeight);
//        }




//        // Calculate and set the image column footer width and heights
//        var thumbnailsWidth = (windowHeight * 0.8);
//        $('#leftImageColumnFooter, #rightImageColumnFooter').height("");
//        var leftFooterHeight = ($('#leftImageColumnFooter').height()) + ($('#task' + icDisplayedTask + 'Header').innerHeight());
////        console.log('Left footer height: ' + leftFooterHeight);
//        var rightFooterHeight = (thumbnailsWidth * 0.24) * 0.68;
////        console.log('Right footer calculated height: ' + rightFooterHeight);
//        if (rightFooterHeight > 110) {
//          rightFooterHeight = 110;
////          console.log('Right footer overridden height: ' + rightFooterHeight);
//        }
//        var footerHeight;
//        if (leftFooterHeight > rightFooterHeight) {
////          console.log('left footer is greater than right');
//          $('.imageColumnFooter').height(leftFooterHeight);
//          footerHeight = leftFooterHeight;
//        } else {
////          console.log('right footer is greater than left');
//          $('.imageColumnFooter').height(rightFooterHeight);
//          footerHeight = rightFooterHeight;
//        }



        // Calculate and set an image size that stops body exceeding viewport height.
        if (windowHeight >= 600) {

          var headerHeight = $('.imageColumnHeader').outerHeight();
          var annotationHeight = $('#annotationWrapper').outerHeight();
//          var maxImageHeight = windowHeight - 25 - headerHeight - footerHeight - annotationHeight;
          var maxImageHeightByY = windowHeight - 37 - headerHeight - annotationHeight;
          var maxImageWidth = maxImageHeightByY / 0.65;
//          thumbnailsWidth += 'px';
//          maxImageHeight += 'px';
          maxImageWidth += 'px';
        } else {
          var maxImageWidth = "278px";
          var maxImageHeightByY = 181;
//          var thumbnailsWidth = "470px";
        }
        $('.imageColumnContent').css('max-width', maxImageWidth);
//        function setMapDivHeight() {
//          var height = $('.imageColumnContent').height();
////          console.log(height);
//          height += 'px';
//          $('#mapInsert').css('height', height);
//          if (icMap != null) {
//            icMap.setCenter(icCurrentImageLatLon);
//          }
//        }
//
//        window.setTimeout(setMapDivHeight, 100000);
        if (windowWidth >= 950) {
          var maxImageHeightByX = ((windowWidth * 0.43) - 10) * 0.65;
        } else {
          var maxImageHeightByX = 257;
        }
        if (maxImageHeightByY < maxImageHeightByX) {
          var mapInsertHeight = maxImageHeightByY + "px";
        } else {
          var mapInsertHeight = maxImageHeightByX + "px";
        }
//        console.log(mapInsertHeight);

//      } else {
//      var mapInsertHeight = "181px";
//      }
        $('#mapInsert').css('height', mapInsertHeight);
//          $('#rightImageColumnFooter').css('max-width', thumbnailsWidth);
//        $('#rightImageColumnFooter, #trackNavFooter').css('max-width', thumbnailsWidth);
//          $.removeData(image, 'elevateZoom');
        $('.zoomContainer').remove();
        $('#postImage').elevateZoom({
          scrollZoom: 'true',
          zoomType: 'lens',
          lensSize: 200,
          cursor: "crosshair",
          lensFadeIn: 400,
          lensFadeOut: 400,
          containLensZoom: 'true',
          scrollZoomIncrement: 0.2,
//          lensBorderColour: "#013251",
          onZoomedImageLoaded: function() {
            hideLoader(true);
          }
        });
        $('#preImage').elevateZoom({
          scrollZoom: 'true',
          zoomType: 'lens',
          lensSize: 200,
          cursor: "crosshair",
          lensFadeIn: 400,
          lensFadeOut: 400,
          containLensZoom: 'true',
          scrollZoomIncrement: 0.2,
          onZoomedImageLoaded: function() {
            hideLoader(false);
          }
        });
      } // End dynamicSizing

      $(document).ready(function() {
<?php
print $jsAnnotationNavButtons;
print $jsTaskMap;
print $jsProjectId;
?>

//        function hideLoader(isPost) {
//          if (isPost) {
//            $('#postLoadingBar').hide();
//          } else {
//            $('#preLoadingBar').hide();
//          }
//        }



//        function dynamicSizing(icDisplayedTask) {
//          // Resize annotation groups to window width
//          var taskWidth = $('#task' + icDisplayedTask).width();
//          var numberOfGroups = taskMap[icDisplayedTask];
//          if (numberOfGroups > 0) {
//            var groupWidth = (taskWidth / (numberOfGroups)) - 51;
//          }
//          var subGroups = document.getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
//          for (var i = 0; i < subGroups.length; i++) {
//            var childNumber = i + 2;
//            var groupMinWidth = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
//            if (groupWidth > groupMinWidth && subGroups[i].borderWidth === 0) {
//              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(groupWidth);
//            } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
//              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 60);
//            } else {
//              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 1);
//            }
//          }
//          for (var i = 0; i < 5; i++) {
//            var childNumber = i + 1;
//            if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').length !== 0) {
//              var groupMinWidth = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
//              var borderWidth = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('border-left-width').replace('px', '');
//              if (groupWidth > groupMinWidth && parseInt(borderWidth) === 0) {
//                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(groupWidth);
//              } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
//                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 60);
//              } else {
//                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(parseInt(groupMinWidth) + 1);
//              }
//            }
//
//          }
//
//
//
//          // Set group header heights to the same across the board
//          $('.groupText, .subGroupText').show();
//          $('.groupWrapper h2, .groupWrapper h3').height("");
//          var subGroups = document.getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
//          var maxHeaderHeight = 0;
//          for (var i = 0; i < subGroups.length; i++) {
//            var childNumber = i + 2;
//            var headerHeight = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ') h3').height();
//            if (headerHeight > maxHeaderHeight) {
//              maxHeaderHeight = headerHeight;
//            }
//          }
//          $('#task' + icDisplayedTask + ' h3').height(maxHeaderHeight);
//          var maxHeaderHeight = 0;
//          for (var i = 0; i < 5; i++) {
//            var childNumber = i + 1;
//            var headerHeight = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ') h2').height();
//            if (headerHeight > maxHeaderHeight) {
//              maxHeaderHeight = headerHeight;
//            }
//          }
//          $('#task' + icDisplayedTask + ' h2').height(maxHeaderHeight);
//
//          // Dynamically size the page;
//          $('html').css('overflow', 'hidden');
//          var windowHeight = $(window).height();
//          $('html').css('overflow', 'auto');
//
//
//
//          // Calculate image sizes for Map is shown
//          if ($('#mapWrapper').css('display') === 'block') {
//            var mapContentHeight = $('#mapContent').height();
//            var mapHeaderHeight = $('#mapHeader').innerHeight();
//            var mapColumnHeight = mapContentHeight - mapHeaderHeight;
//            console.log(mapColumnHeight);
//            $('.mapColumn').height(mapColumnHeight);
//
//            var mapCurrentImageHeaderHeight = $('#mapcurrentImageHeader').height() + 3;
//            var mapSelectedImageHeaderHeight = $('#mapSelectedImageHeader').height() + 3;
//            if (mapSelectedImageHeaderHeight == 3) {
//              mapSelectedImageHeaderHeight = 29;
//            }
//            var mapDisplayButtonHeight = $('#mapDisplayControls').height() + 10;
//            var mapHeightPerImage = (mapColumnHeight - mapCurrentImageHeaderHeight -
//                    mapSelectedImageHeaderHeight - mapDisplayButtonHeight) / 2;
//            var mapMaxImageWidth = (mapHeightPerImage - 10) / 0.65;
//            console.log(mapCurrentImageHeaderHeight);
//            console.log(mapSelectedImageHeaderHeight);
//            console.log(mapDisplayButtonHeight);
//            console.log(mapHeightPerImage);
//            console.log(mapMaxImageWidth);
//            $('.mapImageWrapper').css('max-width', mapMaxImageWidth);
//
//            var mapControlsButtonHeight = $('#mapControls').height() + 10;
//            console.log(mapControlsButtonHeight);
//            var mapCanvasHeight = mapColumnHeight - mapControlsButtonHeight;
//            console.log(mapCanvasHeight);
//            $('#mapCanvas').height(mapCanvasHeight);
//
//          }
//
//
//
//
//          // Calculate and set the image column footer width and heights
//          var thumbnailsWidth = (windowHeight * 0.8);
//          $('#leftImageColumnFooter, #rightImageColumnFooter').height("");
//          var leftFooterHeight = ($('#leftImageColumnFooter').height()) + ($('#task' + icDisplayedTask + 'Header').innerHeight());
////          console.log('Left footer height: ' + leftFooterHeight);
//          var rightFooterHeight = (thumbnailsWidth * 0.24) * 0.68;
////          console.log('Right footer calculated height: ' + rightFooterHeight);
//          if (rightFooterHeight > 110) {
//            rightFooterHeight = 110;
////            console.log('Right footer overridden height: ' + rightFooterHeight);
//          }
//          var footerHeight;
//          if (leftFooterHeight > rightFooterHeight) {
////            console.log('left footer is greater than right');
//            $('.imageColumnFooter').height(leftFooterHeight);
//            footerHeight = leftFooterHeight;
//          } else {
////            console.log('right footer is greater than left');
//            $('.imageColumnFooter').height(rightFooterHeight);
//            footerHeight = rightFooterHeight;
//          }
//
//
//
//          // Calculate and set an image size that stops body exceeding viewport height.
//
//          if (windowHeight >= 600) {
//            var headerHeight = $('.imageColumnHeader').outerHeight();
//            var annotationHeight = $('#annotationWrapper').outerHeight();
//            var maxImageHeight = windowHeight - 25 - headerHeight - footerHeight - annotationHeight;
//            var maxImageWidth = maxImageHeight / 0.65;
//            thumbnailsWidth += 'px';
//            maxImageWidth += 'px';
//          } else {
//            var maxImageWidth = "255px";
//            var thumbnailsWidth = "470px";
//          }
//          $('.imageColumnContent').css('max-width', maxImageWidth);
////          $('#rightImageColumnFooter').css('max-width', thumbnailsWidth);
//          $('#rightImageColumnFooter, #trackNavFooter').css('max-width', thumbnailsWidth);
////          $.removeData(image, 'elevateZoom');
//          $('.zoomContainer').remove();
//          $('#postImage').elevateZoom({
//            scrollZoom: 'true',
//            zoomType: 'lens',
//            lensSize: 200,
//            cursor: "crosshair",
//            lensFadeIn: 400,
//            lensFadeOut: 400,
//            containLensZoom: 'true',
//            scrollZoomIncrement: 0.2,
//            onZoomedImageLoaded: function() {
//              hideLoader(true);
//            }
//          });
//          $('#preImage').elevateZoom({
//            scrollZoom: 'true',
//            zoomType: 'lens',
//            lensSize: 200,
//            cursor: "crosshair",
//            lensFadeIn: 400,
//            lensFadeOut: 400,
//            containLensZoom: 'true',
//            scrollZoomIncrement: 0.2,
//            onZoomedImageLoaded: function() {
//              hideLoader(false);
//            }
//          });
//
//
//        } // End dynamicSizing


        function setMinGroupHeaderWidth(icDisplayedTask) {
          $('.groupText, .subGroupText').hide();
          var subGroups = document.getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
          for (var i = 0; i < subGroups.length; i++) {
            var forceWidthSetting = null;
            var childNumber = i + 2;
//            console.log($('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('width'));
            if ($('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
//              console.log('Saving forceWidth');
              forceWidthSetting = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
            }
            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('width', 'auto');
            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width', '');
            var subGroupWidth = $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width', subGroupWidth);
            if (forceWidthSetting !== null) {
//              console.log('Setting forceWidth');
              $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')').width(forceWidthSetting);
            }
          }

          for (var i = 0; i < 5; i++) {
            var forceWidthSetting = null;
            var childNumber = i + 1;
            if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').length !== 0) {
//            console.log($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('width'));
              if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
//              console.log('Saving forceWidth');
                forceWidthSetting = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width();
              }
              $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('width', 'auto');
              $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('min-width', '');
              var groupWidth = $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width();
//              console.log(groupWidth);
              $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').css('min-width', groupWidth);
              if (forceWidthSetting !== null) {
//              console.log('Setting forceWidth');
                $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')').width(forceWidthSetting);
              }
            }
          }
          dynamicSizing(icDisplayedTask);
        }
//
//        function verticallyCenterTagText() {
//          $('.tag').each(function() {
//            console.log(this);
//            var textHeight = ($(this).height() / 4) + 2.5;
//            textHeight += 'px';
//            console.log(textHeight);
//            $('p', this).css('margin-top', '-' + textHeight);
//          })
//        }
        var script = document.createElement("script");
        script.type = "text/javascript";
        script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
        document.body.appendChild(script);
        $('#task1Breadcrumb').addClass('currentTaskBreadcrumb');
        $('#task1BreadcrumbContent').css('display', 'inline');
        $('#task1Header').css('display', 'block');
        $('#task1').css('display', 'block');
        $('.clickableButton, #taskProgressTrackerWrapper, .thumbnail, .loadingBar').tipTip({maxWidth: '400px', defaultPosition: "right"});
        $('.postImageNavButton').tipTip({maxWidth: '400px', defaultPosition: "bottom"});
<?php print $tagJavaScriptString; ?>
        icDisplayedTask = 1;
        setMinGroupHeaderWidth(icDisplayedTask);
//        verticallyCenterTagText();

//        $('#mapLoad').click(function() {
//          $('#mapWrapper').fadeToggle(400, function() {
//            google.maps.event.trigger(icMap, "resize");
//            icMap.setCenter(icCurrentImageLatLon);
//            icMarkersShown = false;
//            toggleMarkers();
//            icCurrentImageMarker.setMap(icMap);
//          });
//          dynamicSizing(icDisplayedTask);
//        });
//        $('#navMinimized').mouseenter(function() {
//          $('#navHeader').slideDown();
//          $('.navContent').fadeIn();
//        });
//        $('#navHeader').mouseleave(function() {
//          $('.navContent').fadeOut();
//          $('#navHeader').slideUp();
//        });



//        $('#postNavigationHeader').click(function() {
//          $('#postNavigationWrapper').fadeIn();
//        });
//        $('#postNavigationWrapper').mouseleave(function() {
//          $('#postNavigationWrapper').fadeOut();
//        });
//        $('#preNavigationHeader').click(function() {
//          $('#preNavigationWrapper').fadeIn();
//        });
//        $('#preNavigationWrapper').mouseleave(function() {
//          $('#preNavigationWrapper').fadeOut();
//        });
//        $('#mapHide').click(function() {
//          icCurrentImageMarker.setMap();
//          $('#mapWrapper').fadeToggle();
//        });
//        $('#mapMarkerToggle').click(function() {
//          toggleMarkers();
//        });
        $('#centerMapButton').click(function() {
          icMap.setCenter(icCurrentImageLatLon);
        });
//        $('#mapLoadImageButton').click(function() {
//          window.location.href = icSelectedMapImage;
//        });

        var databaseAnnotationInitialization = 'loadEvent=True<?php print $annotationMetaDataQueryString ?>';
        console.log(databaseAnnotationInitialization);
        $.post('ajax/annotationLogger.php', databaseAnnotationInitialization);
        $(window).resize(function() {
          dynamicSizing(icDisplayedTask);
        });
        dynamicSizing(icDisplayedTask);
      }); // End loadScript



    </script>
  </head>
  <body id="body">
    <div id="wrapper">
      <!--      <div id="navMinimized">
            </div>-->

      <?php
      $pageName = "classify";
      require("includes/header.php");
      ?>
      <div id="images">






        <div id="imageColumnLeft" class="imageColumn">
          <div class="imageColumnContent" id="preImageContent">
            <img id="preLoadingBar" class="loadingBar" title="Loading Hi-Res Pre-storm photo and Zoom tool..." src="images/system/loading.gif">
            <img class="coastalImage" id="preImage"src="<?php print $preDisplayImageURL ?>" data-zoom-image="<?php print $preDetailedImageURL ?>">
          </div>

          <div class="imageColumnHeader">
            <?php print $preImageHeader;
            ?>
          </div>
        </div>
        <div id="imageColumnMiddle" class="imageColumn">
          <!--<input id="pac-input" class="controls" type="text" placeholder="Search Box">-->
          <div class="imageColumnContent">
            <div id="mapInsert">
            </div>
          </div>
        </div>




        <div id="imageColumnRight" class="imageColumn">
          <!--          <div id="postNavigationHeader">
                      <p>Post-Storm Coastal Navigator</p>
                      <span class="icon-drop-down rightDropArrow"></span>
                    </div>
                    <div id="postNavigationWrapper">
                      <hr>
                      <div id="coastalNavigationButtonWrapper">
          <?php print $postImageNavigationHtml;
          ?>
                      </div>
                    </div>-->
          <div class="imageColumnContent" id="postImageContent">
            <img id="postLoadingBar" class="loadingBar" title="Loading Hi-Res Pre-storm photo and Zoom tool..." src="images/system/loading.gif">
            <img class="coastalImage" id="postImage" src="<?php print $postDisplayImageURL ?>" data-zoom-image="<?php print $postDetailedImageURL ?>">
          </div>

          <div class="imageColumnHeader">
            <?php print $postImageHeader;
            ?>
          </div>
        </div>
      </div>








      <div id="annotationWrapper">
        <div id="preNavigationCenteringWrapper">
          <div id="preNavigationWrapper">
            <?php
            print "$previousThumbnailHtml";
            print "$currentThumbnailHtml";
            print "$nextThumbnailHtml";
            ?>
          </div>
        </div>
        <?php print $taskHtmlString
        ?>
        <div id="taskProgressTrackerWrapper" title="The TASK TRACKER lets you know which TASK you are currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS Task buttons at the bottom corners of the page.">
  <!--                  <p class="sectionHeader">Task Tracker</p>-->
          <div id="breadcrumbWrapper">
            <?php print $taskBreadcrumbs ?>
          </div>
        </div>
      </div>

    </div>




    <!--    <div id="mapWrapper">
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
                <p class="sectionHeader">Post-Storm Photo Currently Displayed</p>
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
                    <p>Current Post-Storm Photo</p>
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
        </div>-->
    <?php require('includes/footer.php');
    ?>