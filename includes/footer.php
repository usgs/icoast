</body>
</html>



<?php
//////////
// => Close DB Connection
if (isset($dbc) AND !$dbc->connect_error) {
  $dbc->close();
  unset($dbc);
}
?>