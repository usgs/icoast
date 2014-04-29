<?php

$mapScript = <<<EOL
  var map = null;
  var bounds;
  var markers = null;
  var currentImageLatLon = null;
  var currentImageMarker = null;
  var markersShown = false;
  var markerClusterer = null;
  var selectedMapImage = "";
  var projectId = $projectId;
  var userId = $userId;
  var randomImageId = $newRandomImageId
  var randomImageLatitude = $newRandomImageLatitude;
  var randomImageLongitude = $newRandomImageLongitude;
  var randomImageLocation;
  var randomImageDisplayURL;

  var photoIcon = L.icon({
      iconSize: [32, 37],
      iconAnchor: [16, 37],
      iconUrl: 'images/system/photo.png',
      popupAnchor: [16, 18]
  });
  var currentIcon = L.icon({
    iconSize: [32, 37],
    iconAnchor: [16, 37],
    iconUrl: 'images/system/photoCurrent.png',
    popupAnchor: [16, 18]
  });

   var selectedIcon = L.icon({
    iconSize: [32, 37],
    iconAnchor: [16, 37],
    iconUrl: 'images/system/photoSelected.png',
    popupAnchor: [16, 18]
  });



  function initializeMaps() {
    currentImageLatLon = L.latLng(randomImageLatitude, randomImageLongitude);
    map = L.map("mapCanvas", {maxZoom: 18}).setView(currentImageLatLon, 11);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles via ESRI. &copy; Esri, DigitalGlobe, GeoEye, i-cubed, USDA, USGS, AEX, Getmapping, Aerogrid, IGN, IGP, swisstopo, and the GIS User Community'
        }).addTo(map);
        L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}').addTo(map);
        L.control.scale({
            position: 'topright',
            metric: false
        }).addTo(map);

   currentImageMarker = L.marker(currentImageLatLon,
    {
        clickable: false,
        icon: currentIcon
    }).addTo(map);

    new L.Control.GeoSearch({
        provider: new L.GeoSearch.Provider.Esri()
    }).addTo(map);

    bounds = map.getBounds();

    markerControl(true);

    map.on('moveend', function() {
      bounds = map.getBounds();
      if (!bounds.contains(currentImageLatLon) && map.hasLayer(currentImageMarker)) {
        map.removeLayer(currentImageMarker);
      } else if (bounds.contains(currentImageLatLon) && !map.hasLayer(currentImageMarker)) {
        currentImageMarker.addTo(map);
      }
      markerControl(false, true);
    });


  } // End function initializeMaps


   function markerControl(toggleMarkers, boundsChanged) {

    toggleMarkers = typeof toggleMarkers !== 'undefined' ? toggleMarkers : true;
    boundsChanged = typeof boundsChanged !== 'undefined' ? boundsChanged : false;

    if ((markersShown === false && toggleMarkers === true) || (markersShown === true && boundsChanged === true)) {
        $('#mapLoadingBar').css('display', 'block');

        if (toggleMarkers === true) {
            $('#mapMarkerToggle').text("Hide Other Photos");
            markersShown = true;
        }

        var boundaries = {
          north: encodeURIComponent(bounds.getNorthEast().lat),
          east: encodeURIComponent(bounds.getNorthEast().lng),
          south: encodeURIComponent(bounds.getSouthWest().lat),
          west: encodeURIComponent(bounds.getSouthWest().lng),
          projectId: projectId,
          userId: userId,
          currentImageId: randomImageId
        };

        var currentBoundaries = boundaries;

        $.getJSON('ajax/mapUpdater.php', currentBoundaries, function(ajaxMarkerData) {
            if (markers === null) {
                markers = L.markerClusterGroup({
                    disableClusteringAtZoom: 15,
                    maxClusterRadius: 120
                });
            } else {
                markers.clearLayers();
            }

            $.each(ajaxMarkerData, function(imageNo, imageData) {
                var markerLatLng = L.latLng(imageData.latitude, imageData.longitude);
                var infoString = 'Image taken near: ' + imageData.location_string;
                var markerPopup = L.popup({offset: L.point(0,-40), closeButton: false, autoPan :false}).setContent(infoString).setLatLng(markerLatLng);

                var thisMarker = L.marker(markerLatLng, {icon: photoIcon});
                thisMarker.on('mouseover', function() {
                    map.openPopup(markerPopup);
                });
                thisMarker.on('mouseout', function() {
                    map.closePopup(markerPopup);
                });

                thisMarker.on('click', function() {
                    $('#mapRandomButtonControlWrapper').hide();
                    $('#mapSelectedImage').attr("src", imageData.image_url);
                    $('#mapSelectedImage').attr("alt", "An oblique image of the coastline taken near " + imageData.location_string);
                    selectedMapImage = "classification.php?projectId=" + projectId + "&imageId=" + imageData.image_id;
                    $('#selectedMapImageHeaderText').text('Post-Storm Photo Selected on Map near ' + imageData.location_string);
                    $('#selectedMapImage').css('display', 'block');
                    dynamicSizing();
                    map.invalidateSize(false);

                    markers.eachLayer(function (layer) {
                        layer.setIcon(photoIcon);
                    });
                  thisMarker.setIcon(selectedIcon);
                });

                markers.addLayer(thisMarker);
            });
            map.addLayer(markers);
            $('#mapLoadingBar').css('display', 'none');

        });

    } else if (markersShown === true && toggleMarkers === true) {
        markersShown = false;
        markers.clearLayers();
        $('#selectedMapImage').css('display', 'none');
        $('#mapRandomButtonControlWrapper').show();
        $('#mapMarkerToggle').text("Show Other Photos");
    }

} // End function markerControl







    function hideLoader(isPost) {
        if (isPost) {
          $('#postLoadingBar').hide();
        } else {
          $('#preLoadingBar').hide();
        }
    } // End funtion hideLoader

    function processRandomImageChange (updatedRandomImageData) {
      var projectName = updatedRandomImageData.newProjectName;
      randomImageId = updatedRandomImageData.newRandomImageId;
      randomImageLatitude = updatedRandomImageData.newRandomImageLatitude;
      randomImageLongitude = updatedRandomImageData.newRandomImageLongitude;
      randomImageLocation = updatedRandomImageData.newRandomImageLocation;
      randomImageDisplayURL = updatedRandomImageData.newRandomImageDisplayURL;

      $('#currentProjectText').text('Current Project: ' + projectName);

      $('#projectName').text(randomImageLocation);
      $('#randomPostImagePreviewWrapper img').attr({
        src: randomImageDisplayURL,
        alt: 'An oblique image of the United States coastline taken near ' + randomImageLocation + '.'
      });

      $('#randomImageHeader').text('Random photo selected for you near ' + randomImageLocation);
      $('#mapRandomImageDisplay').attr({
        src: randomImageDisplayURL,
        alt: 'An oblique image of the United States coastline taken near ' + randomImageLocation + '.'
      });

      currentImageLatLon = L.latLng(randomImageLatitude, randomImageLongitude);
      currentImageMarker.setLatLng(currentImageLatLon).update();
      map.setView(currentImageLatLon, 11);
    }


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

      var randomControlButtonSize = (maxMapImageWidth/2) - 58;
    $('#mapRandomButtonControlWrapper img').attr({
        height: randomControlButtonSize,
        width: randomControlButtonSize
    });


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
    initializeMaps();

    $(window).resize(function() {
        dynamicSizing();
    });

    $('#mapMarkerToggle').click(function() {
      markerControl();
    });

    $('#centerMapButton').click(function() {
      map.setView(currentImageLatLon, 11);
      $('#selectedMapImage').hide();
      $('#mapRandomButtonControlWrapper').show();
    });

    $('#mapLoadImageButton').click(function() {
      window.location.href = selectedMapImage;
    });

    $('#mapHide').click(function() {
      $('#mapWrapper').fadeToggle();
    });

    $('#randomButton, #mapRandomButton').click(function() {
        var newProjectData = {
            projectId: projectId,
            userId: userId
        };
        $.getJSON('ajax/projectChanger.php', newProjectData, processRandomImageChange);
    });

    $('#projectSelect').change(function() {
        projectId = $('#projectSelect option:selected').val();
        var newProjectData = {
            projectId: projectId,
            userId: userId
        };
        $.getJSON('ajax/projectChanger.php', newProjectData, processRandomImageChange);
    });

    $('#mapButton').click(function() {
      $('#mapWrapper').fadeToggle(400, function() {
        dynamicSizing();
        map.invalidateSize(false);
        map.setView(currentImageLatLon, 11);
      });
    });


    $('#tagButton, #mapTagButton').click(function() {
      window.location.href = "classification.php?projectId=" + projectId + "&imageId=" + randomImageId;
    });

EOL;

$mapHTML = <<<EOL
  <div id="mapWrapper">
        <div id="mapContent">
            <h1>USGS iCoast Map Navigator
              <button title="Click to exit from map view with no changes." id="mapHide" class="clickableButton">
                X
              </button>
            </h1>


          <div id="leftMapColumn" class="mapColumn">
            <div>
              <p id="randomImageHeader">Random photo selected for you near $newRandomImageLocation</p>
              <img id="mapRandomImageDisplay" src="$newRandomImageDisplayURL" title="This image has been randomly selected for you from
                  the database. If you do not want to tag this image then select another from the map on the
                  right and select the 'Choose this Photo To Tag' button to start tagging." width="800"
                  height="521" alt ="An oblique image of the United States coastline taken near $newRandomImageLocation.">
            </div>

            <div id="mapRandomButtonControlWrapper">
                <div>
                    <label for="mapTagButton">
                        Tag This Random Photo
                    </label>
                    <button class="clickableButton" type="button" id="mapTagButton"
                            title="Using this button will load the classification page using the random image shown on the left.">
                        <img src="images/system/checkmark.png" height="64" width="64" alt="Image of a checkmark
                            indicating that this button causes iCoast to load the chosen image for tagging.">
                    </button>
                </div>
                <div>
                    <label for="mapRandomButton">
                        New Random Photo
                    </label>
                    <button class="clickableButton" type="button" id="mapRandomButton"
                        title="Using this button will cause iCoast to pick a new random image from your chosen
                            project for you to tag.">
                    <img src="images/system/dice.png" height="64" width="64" alt="Image of a dice indicating
                         that this button causes iCoast to randomly select an image to display">
                    </button>
                </div>
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
            <div id="mapCanvas">
            </div>
            <img id="mapLoadingBar" class="loadingBar" title="Loading other available image data..."
                src="images/system/loading.gif" alt="A spinning icon indicatin page content is loading.">
            <div id="mapLegend">
              <div class="mapLegendRow">
                <p id="mapInstruction">ZOOM-IN TO SELECT A<br>POST-STORM PHOTO</p>
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
              <button title="Click to reset the map to the default zoom and location of the randomly selected photo."
                  id="centerMapButton" class="clickableButton formButton">
                Reset the Map
              </button>
              <button title="Click to show or hide other selectable images within the map boundaries." id="mapMarkerToggle" class="clickableButton formButton">
                Hide Other Photos
              </button>
          </div>
        </div>
      </div>
EOL;
