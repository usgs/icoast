<?php

$mapScript = <<<EOL
  icMap = null;
  icMarkers = null;
  icCurrentImageLatLon = null;
  icCurrentImageMarker = null;
  icBoundsChanged = false;
  icMarkersShown = false;
  icMarkerClusterer = null;
  icSelectedMapImage = "";
  icProjectId = $projectId;
  function mapBoundaries() {
    //        console.log("In boundaries");
    var bounds = icMap.getBounds();
    var boundaries = {
      north: encodeURIComponent(bounds.getNorthEast().lat()),
      east: encodeURIComponent(bounds.getNorthEast().lng()),
      south: encodeURIComponent(bounds.getSouthWest().lat()),
      west: encodeURIComponent(bounds.getSouthWest().lng()),
      projectId: $projectId,
      userId: $userId,
      currentImageId: $newRandomImageId
    };
    return boundaries;
  } //End function mapBoundaries




  function initializeMaps() {
    icCurrentImageLatLon = new google.maps.LatLng($newRandomImageLatitude, $newRandomImageLongitude);
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
              $('#mapSelectedImage').attr("alt", "An oblique image of the coastline taken near " + imageData.location_string);
              icSelectedMapImage = "classification.php?projectId=" + icProjectId + "&imageId=" + imageData.image_id;
              $('#selectedMapImageHeaderText').text('Post-Storm Photo Selected on Map near ' + imageData.location_string);
              $('#selectedMapImage').css('display', 'block');
//              $("#mapLoadImageButton").css('display', 'inline-block');
              dynamicSizing();
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
//      $("#mapLoadImageButton").css('display', 'none');
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
      var mapContentDivHeight = $('#mapContent').height();
      var mapContentDivWidth = $('#mapContent').width();
      var headerHeight = $('#mapWrapper h1').outerHeight(true);
      var mapColumnHeight = mapContentDivHeight - headerHeight;
      $('.mapColumn').height(mapColumnHeight);

      var mapControlsButtonHeight = $('#rightMapColumn button').outerHeight(true) + 10;
      var randomImageHeaderHeight = $('#randomImageHeader').outerHeight(true);
      var selectedImageHeaderHeight = 45;
      var maxMapImageHeight = (mapColumnHeight - randomImageHeaderHeight -
              selectedImageHeaderHeight - mapControlsButtonHeight - 5) / 2;
        console.log(mapColumnHeight);
        console.log(randomImageHeaderHeight);
        console.log(selectedImageHeaderHeight);
        console.log(mapControlsButtonHeight);
        console.log(maxMapImageHeight);

      var maxMapImageWidth = (maxMapImageHeight) / 0.65;
      $('#leftMapColumn').width(maxMapImageWidth);

      var mapColumnTotalMargin = (+$('.mapColumn').css('margin-left').replace("px", "")) +
        (+$('.mapColumn').css('margin-right').replace("px", ""));
      var rightMapColumnWidth = Math.floor(mapContentDivWidth - maxMapImageWidth -
              (2 * mapColumnTotalMargin ) - 1);
      $('#rightMapColumn').width(rightMapColumnWidth);


      var mapCanvasHeight = mapColumnHeight - mapControlsButtonHeight;
      $('#mapCanvas').height(mapCanvasHeight);
    }
  } // End function dynamicSizing
EOL;

$mapDocumentReadyScript = <<<EOL
    var script = document.createElement("script");
    script.type = "text/javascript";
    script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
    document.body.appendChild(script);

    $(window).resize(function() {
        dynamicSizing();
    });

    $('#mapHide').click(function() {
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
EOL;

$mapHTML = <<<EOL
  <div id="mapWrapper">
        <div id="mapContent">
            <h1>iCoast Map Navigator
              <button title="Click to exit from map view with no changes." id="mapHide" class="clickableButton">
                X
              </button>
            </h1>


          <div id="leftMapColumn" class="mapColumn">
            <div>
              <p id="randomImageHeader">Random Image Already Selected For You</p>
              <img src="$newRandomImageDisplayURL" title="This image has been randomly selected for you from
                  the database. If you do not want to tag this image then select another from the map on the
                  right and select the 'Choose this Photo To Tag' button to start tagging." width="800"
                  height="521" alt ="An oblique image of the $newRandomImageLocation coastline.">
            </div>
            <div id="selectedMapImage">
              <div id="selectedMapImageHeader">
                  <p id="selectedMapImageHeaderText"></p>
              </div>
                <img id="mapSelectedImage" src="" alt="" title="This is the photo you have selected on the map to the
                  right. Select the 'Choose this Photo To Tag' button below to start tagging." width="800"
                  height="521">
                      <button title="Click to load the selected image into iCoast for tagging." id="mapLoadImageButton" class="clickableButton">
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
                  <img src="images/system/clusterLegendIcon.png" alt="Image of the map cluster symbol"
                      width="24" height="24" title="">
                </div>
                <div class="mapLegendRowText">
                  <p>Clustering of Photos</p>
                </div>
              </div>
              <div class="mapLegendRow">
                <div class="mapLegendRowIcon">
                  <img src="images/system/photo.png" alt="Image of the post-storm photo map push pin"
                      width="20" height="24" title="">
                </div>
                <div class="mapLegendRowText">
                  <p>Post-Storm Photo</p>
                </div>
              </div>
              <div class="mapLegendRow">
                <div class="mapLegendRowIcon">
                  <img src="images/system/photoCurrent.png" alt="Image of the random photo map push pin"
                      width="20" height="24" title="">
                </div>
                <div class="mapLegendRowText">
                  <p>Randomly Selected Photo</p>
                </div>
              </div>
              <div class="mapLegendRow">
                <div class="mapLegendRowIcon">
                  <img src="images/system/photoSelected.png" alt="Image of the user selected photo map push pin"
                      width="20" height="24" title="">
                </div>
                <div class="mapLegendRowText">
                  <p>Selected Post-Storm Photo</p>
                </div>
              </div>
            </div>
              <button title="Click to jump the map to the location of the random image that has been already
                  selected for you." id="centerMapButton" class="clickableButton formButton">
                Jump To Current Photo
              </button>
              <button title="Click to show or hide other selectable images within the map boundaries." id="mapMarkerToggle" class="clickableButton formButton">
                Hide Other Photos
              </button>
          </div>
        </div>
      </div>
EOL;
