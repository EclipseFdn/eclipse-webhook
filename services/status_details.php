<?php
  
  include('../lib/json_store.php');
  include('../lib/status_store.php');
  if (file_exists('../config/projects_local.php')) {
    include('../config/projects_local.php');
  } else {
    include('../config/projects.php');
  }

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
  <title>Pull Request Validation Details</title>
</head>
<body>
  <h2>Current status</h2>
  <?php
   
   $list_start = '<ul><li>';
   $list_end = '</li></ul>';
   $item_separator = '</li><li>';
   
   //format problems with corresponding users
   $parts = array();
   addStatus($messages['badCLAs'], $status->invalidCLA);
   addStatus($messages['unknownUsers'], $status->unknownCLA);
   addStatus($messages['badSignatures'], $status->invalidSignedOff);
   addStatus($messages['badSignatures'], $status->unknownSignedOff);
   
   //start output with a summary message
   $summary = count($parts)?$messages['failure']:$messages['success'];
   echo "<h3>" . $summary . "</h3>";
   
   //output details
   echo $list_start;
   echo(implode($item_separator, $parts));
   echo $list_end;
   
   function addStatus($title, $state) {
     global $parts, $list_start, $list_end, $item_separator;
     if ($state) {
       array_push($parts,
         $title .
         $list_start .
         implode($item_separator, $state) .
         $list_end
       );
     }
   }
  ?>
</body>
</html>
