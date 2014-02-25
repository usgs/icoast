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
            icCurrentImageLatLon = null;
            icCurrentImageMarker = null;
            //      icProjectId = "";

            function initializeMaps() {
                icCurrentImageLatLon = new google.maps.LatLng
                        (<?php print $postImageLatitude . "," . $postImageLongitude ?>);
                var mapOptions = {
                    center: icCurrentImageLatLon,
                    zoom: 12,
                    mapTypeId: google.maps.MapTypeId.HYBRID
                };
                icMap = new google.maps.Map(document.getElementById("mapInsert"),
                        mapOptions);
                var input = (document.getElementById('pac-input'));
                icMap.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

                var mapCurrentIcon = {
                    size: new google.maps.Size(32, 37),
                    url: 'images/system/photoCurrent.png'
                };
                icCurrentImageMarker = new google.maps.Marker({
                    position: icCurrentImageLatLon,
                    animation: google.maps.Animation.DROP,
                    icon: mapCurrentIcon,
                    clickable: false,
                    map: icMap
                });
            }

            function hideLoader(isPost) {
                if (isPost) {
                    $('#postImageZoomLoadingIndicator').hide();
                } else {
                    $('#preImageZoomLoadingIndicator').hide();
                }
            }

            function dynamicSizing(icDisplayedTask) {


                // Resize annotation groups to window width
                var taskWidth = $('#task' + icDisplayedTask).width();
                var numberOfGroups = icTaskMap[icDisplayedTask];
                if (numberOfGroups > 0) {
                    var groupWidth = (taskWidth / (numberOfGroups)) - 51;
                }
                var subGroups = document
                        .getElementById('task' + icDisplayedTask).getElementsByClassName('annotationSubgroup');
                for (var i = 0; i < subGroups.length; i++) {
                    var childNumber = i + 2;
                    if (!$('#task' + icDisplayedTask +
                            ' .annotationSubgroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
                        var groupMinWidth = $('#task' + icDisplayedTask +
                                ' .annotationSubgroup:nth-child(' + childNumber + ')').css('min-width').replace('px', '');
                        if (groupWidth > groupMinWidth && subGroups[i].borderWidth === 0) {
                            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                    .width(groupWidth);
                        } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
                            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                    .width(parseInt(groupMinWidth) + 60);
                        } else {
                            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                    .width(parseInt(groupMinWidth) + 1);
                        }
                    }
                }
                for (var i = 0; i < 5; i++) {
                    var childNumber = i + 1;
                    if ($('#task' + icDisplayedTask +
                            ' .annotationGroup:nth-child(' + childNumber + ')').length !== 0) {
                        if (!$('#task' + icDisplayedTask +
                                ' .annotationGroup:nth-child(' + childNumber + ')').hasClass('forceWidth')) {
                            var groupMinWidth = $('#task' + icDisplayedTask +
                                    ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .css('min-width').replace('px', '');
                            var borderWidth =
                                    $('#task' + icDisplayedTask +
                                            ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .css('border-left-width').replace('px', '');
                            if (groupWidth > groupMinWidth && parseInt(borderWidth) === 0) {
                                $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')').width(groupWidth);
                            } else if (groupWidth >= (parseInt(groupMinWidth) + 60)) {
                                $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')')
                                        .width(parseInt(groupMinWidth) + 60);
                            } else {
                                $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')')
                                        .width(parseInt(groupMinWidth) + 1);
                            }
                        }
                    }
                }


                // Set group header heights to the same across the board
                $('.groupText, .subGroupText').show();
                $('.groupWrapper h2, .groupWrapper h3').height("");
                var subGroups = document.getElementById('task' + icDisplayedTask)
                        .getElementsByClassName('annotationSubgroup');
                var maxHeaderHeight = 0;
                for (var i = 0; i < subGroups.length; i++) {
                    var childNumber = i + 2;
                    var headerHeight = $('#task' + icDisplayedTask +
                            ' .annotationSubgroup:nth-child(' + childNumber + ') h3').height();
                    if (headerHeight > maxHeaderHeight) {
                        maxHeaderHeight = headerHeight;
                    }
                }
                $('#task' + icDisplayedTask + ' h3').height(maxHeaderHeight);
                var maxHeaderHeight = 0;
                for (var i = 0; i < 5; i++) {
                    var childNumber = i + 1;
                    var headerHeight = $('#task' + icDisplayedTask +
                            ' .annotationGroup:nth-child(' + childNumber + ') h2').height();
                    if (headerHeight > maxHeaderHeight) {
                        maxHeaderHeight = headerHeight;
                    }
                }
                $('#task' + icDisplayedTask + ' h2').height(maxHeaderHeight);


                // Determine size the page;
                $('html').css('overflow', 'hidden');
                var bodyHeight = $(body).height();
                var bodyWidth = $(body).width();
                $('html').css('overflow', 'auto');


                // Calculate and set an image size that stops body exceeding viewport height.
                var headerHeight = $('.imageColumnTitle').outerHeight();
                var annotationHeight = $('#annotationWrapper').outerHeight();
                // 25px from header, 10px from imageColumnContent padding, 1 to account for browser pixel rounding
                var maxImageHeightByY = bodyHeight - 25 - 10 - 1 - headerHeight - annotationHeight
                console.log(headerHeight);
                console.log(annotationHeight);
                console.log(maxImageHeightByY);
                var maxImageWidth = maxImageHeightByY / 0.652;
                if (maxImageWidth >= (bodyWidth * 0.43) - 10) {
                    maxImageWidth = (bodyWidth * 0.43) - 15;
                    maxImageHeightByY = maxImageWidth * 0.652;
                }
                maxImageWidth = Math.floor(maxImageWidth);
                maxImageWidth += 'px';

                $('.imageColumnContent').css('max-width', maxImageWidth);

                var maxImageHeightByX = (((bodyWidth * 0.43) - 15) * 0.65) - 1;
                if (maxImageHeightByY < maxImageHeightByX) {
                    var mapInsertHeight = maxImageHeightByY;
                } else {
                    var mapInsertHeight = maxImageHeightByX;
                }
                if (mapInsertHeight > 521) {
                    mapInsertHeight = 521;
                }
                mapInsertHeight = Math.floor(mapInsertHeight);
                mapInsertHeight += "px";
                $('#mapInsert').css('height', mapInsertHeight);

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

                function setMinGroupHeaderWidth(icDisplayedTask) {
                    $('.groupText, .subGroupText').hide();
                    var subGroups = document.getElementById('task' + icDisplayedTask)
                            .getElementsByClassName('annotationSubgroup');
                    for (var i = 0; i < subGroups.length; i++) {
                        var forceWidthSetting = null;
                        var childNumber = i + 2;
                        if ($('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .hasClass('forceWidth')) {
                            forceWidthSetting = $('#task' + icDisplayedTask +
                                    ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
                        }
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .css('width', 'auto');
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .css('min-width', '');
                        var subGroupWidth = $('#task' + icDisplayedTask +
                                ' .annotationSubgroup:nth-child(' + childNumber + ')').width();
                        $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                .css('min-width', subGroupWidth);
                        if (forceWidthSetting !== null) {
                            $('#task' + icDisplayedTask + ' .annotationSubgroup:nth-child(' + childNumber + ')')
                                    .width(forceWidthSetting);
                        }
                    }
                    for (var i = 0; i < 5; i++) {
                        var forceWidthSetting = null;
                        var childNumber = i + 1;
                        if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                                .length !== 0) {
                            if ($('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .hasClass('forceWidth')) {
                                forceWidthSetting = $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')').width();
                            }
                            $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .css('width', 'auto');
                            $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .css('min-width', '');
                            var groupWidth = $('#task' + icDisplayedTask +
                                    ' .annotationGroup:nth-child(' + childNumber + ')').width();
                            $('#task' + icDisplayedTask + ' .annotationGroup:nth-child(' + childNumber + ')')
                                    .css('min-width', groupWidth);
                            if (forceWidthSetting !== null) {
                                $('#task' + icDisplayedTask +
                                        ' .annotationGroup:nth-child(' + childNumber + ')').width(forceWidthSetting);
                            }
                        }
                    }
                    dynamicSizing(icDisplayedTask);
                }

                var script = document.createElement("script");
                script.type = "text/javascript";
                script.src = "https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=initializeMaps";
                document.body.appendChild(script);
                $('#progressTrackerItem1').addClass('currentProgressTrackerItem');
                $('#progressTrackerItem1Content').css('display', 'inline');
                $('#task1Header').css('display', 'block');
                $('#task1').css('display', 'block');
                $('.clickableButton, #taskProgressTrackerWrapper, .thumbnail, .zoomLoadingIndicator')
                        .tipTip({maxWidth: '400px', defaultPosition: "right"});
                $('.postImageNavButton').tipTip({maxWidth: '400px', defaultPosition: "bottom"});
<?php print $tagJavaScriptString; ?>
                icDisplayedTask = 1;
                setMinGroupHeaderWidth(icDisplayedTask);

                $('#centerMapButton').click(function() {
                    icMap.setCenter(icCurrentImageLatLon);
                });

                var databaseAnnotationInitialization = 'loadEvent=True<?php print $annotationMetaDataQueryString ?>';
                console.log(databaseAnnotationInitialization);
                $.post('ajax/annotationLogger.php', databaseAnnotationInitialization);
                $(window).resize(function() {
                    dynamicSizing(icDisplayedTask);
                    icMap.setCenter(icCurrentImageLatLon);
                });
                dynamicSizing(icDisplayedTask);
            }); // End document.ready()

        </script>
    </head>

    <body id="body">
        <?php
        $pageName = "classify";
        require("includes/header.php");
        ?>
        <div id="classificationWrapper">
            <div id="images">
                <div class="imageColumn">
                    <div class="imageColumnContent">
                        <img id="preImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                             src="images/system/loading.gif"
                             title="Loading high resolution zoom tool..." />
                        <img id="preImage" src="<?php print $preDisplayImageURL ?>"
                             data-zoom-image="<?php print $preDetailedImageURL ?>" />
                    </div>
                    <div class="imageColumnTitle">
                        <?php print $preImageTitle; ?>
                    </div>
                </div>

                <div class="imageColumn">
                    <div class="imageColumnContent">
                        <div id="mapInsert"></div>
                    </div>
                </div>

                <div class="imageColumn">
                    <div class="imageColumnContent">
                        <img id="postImageZoomLoadingIndicator" class="zoomLoadingIndicator"
                             src="images/system/loading.gif"
                             title="Loading high resolution zoom tool..." />
                        <img id="postImage" src="<?php print $postDisplayImageURL ?>"
                             data-zoom-image="<?php print $postDetailedImageURL ?>" />
                    </div>
                    <div class="imageColumnTitle">
                        <?php print $postImageTitle; ?>
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
                <?php print $taskHtmlString; ?>
                <div id="progressTrackerCenteringWrapper" title="The TASK TRACKER lets you know which TASK you are
                     currently working on. You can also navigate between the tasks using the NEXT and PREVIOUS
                     Task buttons at the bottom corners of the page.">
                    <div id="progressTrackerItemWrapper">
                        <?php print $progressTrackerItems ?>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>