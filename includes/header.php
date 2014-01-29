

<?php
$welcomeLink = '<a href="welcome.php"><div class="clickableButton postImageNavButton" title="iCoast home"><span class="icon-home"></span></div></a>';
$welcomeButton = '<div class="clickableButton postImageNavButton currentNavButton" title="iCoast home"><span class="icon-home"></span></div>';
$classifyLink = '<a href="classification.php"><div class="clickableButton postImageNavButton" title="Classify an image"><span class="icon-classify"></span></div></a>';
$classifyButton = '<div class="clickableButton postImageNavButton" title="Classify an image"><span class="icon-classify"></span></div>';
$aboutLink = '<a href="about.php"><div class="clickableButton postImageNavButton" title="About iCoast"><span class="icon-info"></span></div></a>';
$aboutButton = '<div class="clickableButton postImageNavButton" title="About iCoast"><span class="icon-info"></span></div>';
$helpLink = '<a href="help.php"><div class="clickableButton postImageNavButton" title="iCoast help"><span class="icon-help"></span></div></a>';
$helpButton = '<div class="clickableButton postImageNavButton" title="iCoast help"><span class="icon-help"></span></div>';
$profileLink = '<a href="welcome.php"><div class="clickableButton postImageNavButton" title="Your profile"><span class="icon-user"></span></div></a>';
$profileButton = '<div class="clickableButton postImageNavButton" title="Your profile"><span class="icon-user"></span></div>';
$signOutLink = '<a href="logout.php"><div class="clickableButton postImageNavButton"title="Sign out of iCoast."><span class="icon-logout" ></span></div></a>';
$signOutButton = '<div class="clickableButton postImageNavButton"title="Sign out of iCoast."><span class="icon-logout" ></span></div>';
$signInLink = '<a href="login.php"><div class="clickableButton postImageNavButton"title="Sign in to iCoast."><span class="icon-login" ></span></div></a>';
$signInButton = '<div class="clickableButton postImageNavButton"title="Sign in to iCoast."><span class="icon-login" ></span></div>';
?>
<div id="header">
  <div id="usgsColorBand">

    <div id="usgsIdentifier">
      <a href="http://www.usgs.gov">
        <img src="images/system/usgsIdentifier.jpg" alt="USGS - science for a changing world" title="U.S. Geological Survey Home Page" width="178" height="72" />
      </a>
    </div

  </div>

</div>

<div id="navCenteringWrapper">
  <div id="navHeader">
    <p id="appTitle">iCoast: Did the Coast Change?</p>
    <span class="icon-drop-down"></span>
  </div>
  <div id="navWrapper">
    <hr>
    <?php
    switch ($pageName) {
      case "welcome":
        print $classifyButton;
        print $helpLink;
        print $aboutLink;
        print $profileLink;
        print $signOutLink;
        break;
      case "classify":
        print $classifyButton;
        print $helpLink;
        print $aboutLink;
        print $profileLink;
        print $signOutLink;
        break;
      case "help":
        print $classifyLink;
        print $helpButton;
        print $aboutLink;
        print $profileLink;
        print $signOutLink;
        break;
      case "about":
        print $classifyLink;
        print $helpLink;
        print $aboutButton;
        print $profileLink;
        print $signOutLink;
        break;
      case "profile":
        print $classifyLink;
        print $helpLink;
        print $aboutLink;
        print $profileButton;
        print $signOutLink;
        break;
      case "signout":
        print $classifyLink;
        print $helpLink;
        print $aboutLink;
        print $profileLink;
        print $signInLink;
        break;
      case "signin":
        print $classifyLink;
        print $helpLink;
        print $aboutLink;
        print $profileLink;
        print $signInButton;
        break;
    }
    ?>
  </div>
</div>
</div>

<script>
  $(document).ready(function() {

    $('#navHeader').click(function() {
      $('#usgsColorBand').slideDown();
      $('#navWrapper').fadeIn();
    });
    $('#header').mouseleave(function() {
      $('#usgsColorBand').slideUp();
      $('#navWrapper').fadeOut();
    });
  });
</script>
