<?php
  
  include('../lib/json_store.php');
  include('../lib/status_store.php');

  $key = $_REQUEST['id'];
  
  $json_store = new JsonStore();
  $provider = new StatusStore($json_store);
  
  //TODO: perform key validation so we don't just load anything
  if ($provider->test($key)) {
    $status = $provider->load($key);
  } else {
    $status = "No details available.";
  }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
  "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Pull Requests Validation Details</title>
</head>
<body>
  <H3>Current status</H3>
  <?php
   if (is_array($status)) {
     echo(implode('<BR>',$status));
   } else {
     echo $status;
   }
  ?>
</body>
</html>
