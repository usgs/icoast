<?php
require 'includes/globalFunctions.php';
require 'includes/userFunctions.php';
require $dbmsConnectionPath;
$numberOfProjects = 0;
$filtered = TRUE;

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
  header('Location: login.php');
  exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode);
$authCheckCode = generate_cookie_credentials($DBH, $userId);


$projectId = $_POST['projectId'];
$postImageId = $_POST['imageId'];
$projectId = 1;
$postImageId = 13210;
$projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
if (!$postImageMetadata = retrieve_entity_metadata($DBH, $postImageId, 'image')) {
  //  Placeholder for error management
  exit("Image $postImageId not found in Database");
}
$postImageLatitude = $postImageMetadata['latitude'];
$postImageLongitude = $postImageMetadata['longitude'];
$postDisplayImageURL = "images/datasets/{$postImageMetadata['dataset_id']}/main/{$postImageMetadata['filename']}";


$lastAnnotationQuery = "SELECT COUNT(*) "
        . "FROM annotations WHERE user_id = :userId AND annotation_completed = 1 "
        . "ORDER BY initial_session_end_time DESC";
$lastAnnotationParams['userId'] = $userId;
$STH = run_prepared_query($DBH, $lastAnnotationQuery, $lastAnnotationParams);
$numberOfAnnotations = $STH->fetchColumn();
if ($numberOfAnnotations == 0) {
  header('Location: welcome.php');
}

if ($numberOfAnnotations != $userData['completed_annotation_count']) {
  $setAnnotationCountQuery = "UPDATE users SET completed_annotation_count = :numberOfAnnotations WHERE user_id = :userId";
  $setAnnotationCountParams = array(
      'userId' => $userId,
      'numberOfAnnotations' => $numberOfAnnotations,
  );
  $STH = run_prepared_query($DBH, $setAnnotationCountQuery, $setAnnotationCountParams);
  if ($STH->rowCount() == 0) {
    //  Placeholder for error management
    print 'User Annotation Count Update Error: Update did not complete sucessfully.';
    exit;
  }
}
$ordinalNumberOfAnnotations = ordinal_suffix($numberOfAnnotations);

//--------------------------------------------------------------------------------------------------
// Determine if the user has already annotated the displayed image
$lastAnnotationQuery = "SELECT * FROM annotations WHERE user_id = :userId AND "
        . "project_id = :projectId AND image_id = :postImageId";
$$lastAnnotationParams = array(
    'userId' => $userId,
    'projectId' => $projectId,
    'postImageId' => $postImageId
);
$STH = run_prepared_query($DBH, $lastAnnotationQuery, $$lastAnnotationParams);
$lastAnnotation = $STH->fetch(PDO::FETCH_ASSOC);


//--------------------------------------------------------------------------------------------------
// Build an array of the tasks.
$taskMetadataQuery = "SELECT * from task_metadata WHERE project_id = :projectId
  ORDER BY order_in_project";
$taskMetadataParams['projectId'] = $projectId;
$STH = run_prepared_query($DBH, $taskMetadataQuery, $taskMetadataParams);
$taskMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//$taskMetadataResults = run_database_query($taskMetadataQuery);
if (count($taskMetadata) > 0) {
//  $taskMetadata = $taskMetadataResults->fetch_all(MYSQLI_ASSOC);
  foreach ($taskMetadata as $task) {
    if ($task['is_enabled'] == 0) {
      continue;
    }
    $taskId = $task['task_id'];
    $annotations[$taskId] = array();

    $taskContentsQuery = "SELECT tag_group_id FROM task_contents WHERE task_id = :taskId
      ORDER BY order_in_task";
    $taskContentsParams['taskId'] = $taskId;
    $STH = run_prepared_query($DBH, $taskContentsQuery, $taskContentsParams);
    $taskContents = $STH->fetchAll(PDO::FETCH_ASSOC);
//    $taskContentsResult = run_database_query($taskContentsQuery);
    if (count($taskContents) > 0) {
//      $taskContents = $taskContentsResult->fetch_all(MYSQLI_ASSOC);
      foreach ($taskContents as $tagGroupIdArray) {
        $tagGroupId = $tagGroupIdArray['tag_group_id'];
        $tagGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE tag_group_id = :tagGroupId
            AND project_id = :projectId";
        $tagGroupMetadataParams = array(
            'tagGroupId' => $tagGroupId,
            'projectId' => $projectId
        );
        $STH = run_prepared_query($DBH, $tagGroupMetadataQuery, $tagGroupMetadataParams);
        $tagGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//        $tagGroupMetadataResults = run_database_query($tagGroupMetadataQuery);
        if (count($tagGroupMetadata) == 1) {
//          $tagGroupMetadata = $tagGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
          if ($tagGroupMetadata[0]['is_enabled'] == 0) {
            continue;
          }
          $annotations[$taskId][$tagGroupId] = array(
              'summaryText' => $tagGroupMetadata[0]['summary_text'],
          );



          if ($tagGroupMetadata[0]['contains_groups'] == 1) {
            $groupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :tagGroupId ORDER BY order_in_group";
            $groupContentsParams['tagGroupId'] = $tagGroupId;
            $STH = run_prepared_query($DBH, $groupContentsQuery, $groupContentsParams);
            $groupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
//            $groupContentsResult = run_database_query($groupContentsQuery);
            if (count($groupContents) > 0) {
//              $groupContents = $groupContentsResult->fetch_all(MYSQLI_ASSOC);
              foreach ($groupContents as $groupContentsArray) {
                $groupGroupId = $groupContentsArray['tag_id'];
                $groupGroupMetadataQuery = "SELECT * from tag_group_metadata WHERE
                  tag_group_id = :groupGroupId AND project_id = :projectId";
                $groupGroupMetadataParams = array(
                    'groupGroupId' => $groupGroupId,
                    'projectId' => $projectId
                );
                $STH = run_prepared_query($DBH, $groupGroupMetadataQuery, $groupGroupMetadataParams);
                $groupGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//                $groupGroupMetadataResults = run_database_query($groupGroupMetadataQuery);
                if (count($groupGroupMetadata) == 1) {
//                  $groupGroupMetadata = $groupGroupMetadataResults->fetch_all(MYSQLI_ASSOC);
                  if ($groupGroupMetadata[0]['is_enabled'] == 0) {
                    continue;
                  }
                  $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId] = array(
                      'summaryText' => $tagGroupMetadata[0]['summary_text'],
                      'tags' => array()
                  );
                  $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
                    tag_group_id = :groupGroupId ORDER BY order_in_group";
                  $tagGroupContentsParams = array();
                  $tagGroupContentsParams['groupGroupId'] = $groupGroupId;
                  $STH = run_prepared_query($DBH, $tagGroupContentsQuery, $tagGroupContentsParams);
                  $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
//                  $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
                  if (count($tagGroupContents) > 0) {
//                    $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
                    foreach ($tagGroupContents as $tagIdArray) {
                      $tagId = $tagIdArray['tag_id'];
                      $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                        project_id = :projectId";
                      $tagMetadataParams = array();
                      $tagMetadataParams = array(
                          'tagId' => $tagId,
                          'projectId' => $projectId
                      );
                      $STH = run_prepared_query($DBH, $tagMetadataQuery, $tagMetadataParams);
                      $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//                      $tagMetadataResult = run_database_query($tagMetadataQuery);
                      if (count($tagMetadata) == 1) {
//                        $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
                        if ($tagMetadata[0]['is_enabled'] == 0) {
                          continue;
                        }
                        $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                ['tags'][$tagId] = array(
                            'id' => $tagMetadata[0]['tag_id'],
                            'text' => $tagMetadata[0]['display_text'],
                            'userSelected' => FALSE
                        );
                        if ($existingAnnotation) {
                          if ($tagMetadata[0]['is_comment_box'] == TRUE) {
                            if (array_key_exists($tagId, $existingComments)) {
                              $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                      ['tags'][$tagId]['userSelected'] = TRUE;
                              $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                      ['tags'][$tagId]['userComment'] = $existingComments[$tagId];
                            }
                          } else {
                            $key = array_search($tagId, $existingTags);
                            if ($key !== FALSE) {
                              $annotations[$taskId]['groups'][$tagGroupId]['groups'][$groupGroupId]
                                      ['tags'][$tagId]['userSelected'] = TRUE;
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          } else {
            $tagGroupContentsQuery = "SELECT tag_id FROM tag_group_contents WHERE
          tag_group_id = :tagGroupId ORDER BY order_in_group";
            $tagGroupContentsParams = array();
            $tagGroupContentsParams['tagGroupId'] = $tagGroupId;
            $STH = run_prepared_query($DBH, $tagGroupContentsQuery, $tagGroupContentsParams);
            $tagGroupContents = $STH->fetchAll(PDO::FETCH_ASSOC);
//            $tagGroupContentsResult = run_database_query($tagGroupContentsQuery);
            if (count($tagGroupContents) > 0) {
//              $tagGroupContents = $tagGroupContentsResult->fetch_all(MYSQLI_ASSOC);
              foreach ($tagGroupContents as $tagIdArray) {
                $tagId = $tagIdArray['tag_id'];
                $tagMetadataQuery = "SELECT * FROM tags WHERE tag_id = :tagId AND
                project_id = :projectId";
                $tagMetadataParams = array();
                $tagMetadataParams = array(
                    'tagId' => $tagId,
                    'projectId' => $projectId
                );
                $STH = run_prepared_query($DBH, $tagMetadataQuery, $tagMetadataParams);
                $tagMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
//                $tagMetadataResult = run_database_query($tagMetadataQuery);
                if (count($tagMetadata) == 1) {
//                  $tagMetadata = $tagMetadataResult->fetch_all(MYSQLI_ASSOC);
                  if ($tagMetadata[0]['is_enabled'] == 0) {
                    continue;
                  }
                  $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId] = array(
                      'id' => $tagMetadata[0]['tag_id'],
                      'text' => $tagMetadata[0]['display_text'],
                      'userSelected' => FALSE
                  );
                  if ($existingAnnotation) {
                    if ($tagMetadata[0]['is_comment_box'] == TRUE) {
                      if (array_key_exists($tagId, $existingComments)) {
                        $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                ['userSelected'] = TRUE;
                        $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                ['userComment'] = $existingComments[$tagId];
                      }
                    } else {
                      $key = array_search($tagId, $existingTags);
                      if ($key !== FALSE) {
                        $annotations[$taskId]['groups'][$tagGroupId]['tags'][$tagId]
                                ['userSelected'] = TRUE;
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}





$bodyHTML = "<h1>iCoast - Did the Coast Change?</h1>";
$bodyHTML = <<<EOL
        <div id="annotationSummaryWrapper">
         <h2>Annotation Complete</h2>
        <p>Congratulations on finsihing your $ordinalNumberOfAnnotations complete photo annotation in iCoast<br>
           Details of your last annotation are below.</p>
        <img src="$postDisplayImageURL" id="annotatedImage" />
        </div>
        <div id="chooseNextImageWrapper">
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
        <div>


EOL;
?>

<!DOCTYPE html>
<html>
  <head>
    <title>USGS iCoast: Annotation Complete</title>
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
