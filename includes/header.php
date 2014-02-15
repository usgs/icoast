<?php
switch ($pageName) {
  case "welcome":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li id="activePageLink">Home</li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
    break;
  case "classify":
  case "start":
  case "complete":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
    break;
  case "help":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li id="activePageLink">Help</li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
    break;
  case "about":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li class="missingPageLink">Help</a></li>
        <li id="activePageLink">About</li>
        <li class="missingPageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
    break;
  case "profile":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li id="activePageLink">Profile</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
EOL;
    break;
  case "logout":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
        <li id="activePageLink">Logout</a></li>
      </ul>
EOL;
    break;
  case "login":
  case "registration":
    $mainNavHTML = <<<EOL
      <ul id="mainHeaderNavigation">
        <li><a href="welcome.php">Home</a></li>
        <li class="missingPageLink">Help</a></li>
        <li><a href="about.php">About</a></li>
        <li class="missingPageLink">Profile</li>
      </ul>
EOL;
    break;
}
?>
    <script>

      var pageName = '<?php print $pageName ?>';

      $(document).ready(function() {
        if (pageName === 'classify') {
          $('#usgsColorBand').click(function() {

            $('#usgsColorBand').animate({
              top: "0px"
            }, 500, "swing");

            $('#usgsColorBand img').animate({
              left: "350px"
            }, 500, "swing");

            $('#usgsIdentifier').animate({
              width: "350px"
            }, 500, "swing");

            $('#usgsIdentifier a').show(0, function() {
              $('#usgsIdentifier a').animate({
                opacity: 1
              }, 500, "swing");
            });

            $('#appTitle').animate({
              left: "190px",
              top: "7px",
              margin: "0 0 0 0",
              fontSize: "48px",
              lineHeight: "48px"
            }, 500, "swing");

            $('#appSubtitle').animate({
              left: "190px",
              top: "52px"
            }, 500, "swing");

            $('#mainHeaderNavigation li').animate({
              opacity: 1
            }, 500, "swing");




          }); // End header click (expand) function.


          $('#usgsColorBand').mouseleave(function() {

            $('#usgsColorBand').animate({
              top: "-47px"
            }, 500, "swing");

            $('#usgsColorBand > img').animate({
              left: "252px"
            }, 500, "swing");

            $('#usgsIdentifier').animate({
              width: "252px"
            }, 500, "swing");

            $('#usgsIdentifier a').animate({
              opacity: 0
            }, 500, "swing", function() {
              $('#usgsIdentifier a').hide(0);
            });

            $('#appTitle').animate({
              left: "0px",
              top: "47px",
              margin: "0 0 0 15",
              fontSize: "25px",
              lineHeight: "25px"
            }, 500, "swing");

            $('#appSubtitle').animate({
              left: "97px",
              top: "56px"
            }, 500, "swing");

            $('#mainHeaderNavigation li').animate({
              opacity: 0
            }, 500, "swing");

          }); // End header mouseleave (collapse) function.
        }

      }); // End Document Ready
    </script>


    <div id="usgsColorBand">
      <div id="usgsIdentifier">
        <a href="http://www.usgs.gov">
          <img src="images/system/usgsIdentifier.jpg" alt="USGS - science for a changing world" title="U.S. Geological Survey Home Page" width="178" height="72" />
        </a>
      </div>
      <img src="images/system/hurricaneBanner.jpg" />
      <p id="appTitle">iCoast</p>
      <p id="appSubtitle">did the coast change?</p>
      <?php print $mainNavHTML ?>
    </div>

