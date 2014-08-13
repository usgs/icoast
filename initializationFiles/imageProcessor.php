<?php

require_once("includes/imageProcessorCode.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  if (is_numeric($_POST['dataset'])) {
    $datasetId = $_POST['dataset'];
  } else {
    $datasetId = 0;
  }
  if (is_numeric($_POST['collection'])) {
    $collectionId = $_POST['collection'];
  } else {
    $collectionId = 0;
  }

  // Validate and process the user selections.
  if ($collectionId > 0 AND $datasetId > 0) {
    print "<p>Please select only a dataset OR a collection. Not both.<p>";
    print '<a href="">Make selection again</a>';
    exit;
  } elseif ($collectionId == 0 AND $datasetId == 0) {
    print "<p>You must select either a dataset or a collection to process.<p>";
    print '<a href="">Make selection again</a>';
    exit;
  }
  process_images($datasetId, $collectionId);
} else {
  // Begin the form HTML
  print <<<EOT
    <!DOCTYPE html>
    <html>
        <head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    	<title>Image Processor</title>
        </head>
        <body>
    	<form method="post">
    	    Dataset to process:<br>
EOT;
  // Build form selection for available datasets
  build_list_box(TRUE);
  print "<br>\nOR<br>\nCollection to process:<br>\n";
  // Build form selection for available datasets
  build_list_box(FALSE);
  print <<<EOT
	    <br>
	<input type="submit" value="Submit">
    	</form>
        </body>
    </html>
EOT;
}
?>
