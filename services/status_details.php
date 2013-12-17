<?php
  
  if (file_exists('../config/projects_local.php')) {
    include('../config/projects_local.php');
  } else {
    include('../config/projects.php');
  }
  include('../lib/mysql_store.php');
  include('../lib/json_store.php');
  include('../lib/status_store.php');
  
  $key = $_REQUEST['id'];
  
  $store = null;
  if (defined('MYSQL_DBNAME')) {
    $store = new MySQLStore();  
  } else {
    $store = new JSONStore();
  }
  $provider = new StatusStore($store);
  
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
<style>
body {
  font-family: sans-serif;
}
.history {
  color: white;
  border-radius: 5px;
  margin-top: 15px;
  padding: 5px;
}
ul {
  list-style-type: none;
}
.failure {
  background-color: #BD2C01;
}
.error {
  background-color: #BD2C01;
}
.success {
  background-color: #6BC644;
}
</style>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Pull Request Validation Details</title>
</head>
<body>
  <h2>Current status</h2>
  <?php
   
   $list_start = '<ul><li>';
   $list_end = '</li></ul><br>';
   $item_separator = '</li><li>';
   
   //format problems with corresponding users
   $parts = array();
   addStatus($messages['badCLAs'], $status->invalidCLA);
   addStatus($messages['unknownUsers'], $status->unknownCLA);
   addStatus($messages['badSignatures'], $status->invalidSignedOff);
   addStatus($messages['badSignatures'], $status->unknownSignedOff);
   
   //format status history
   $history = array();
   if ($status->StatusHistory) {
     foreach ($status->StatusHistory as $value) {
       addStatusHistory($value);
     }
   }
   
   //START OUTPUT with a summary message. Success only on 
   $summary = $messages['unknown'] || 'Validation status unavailable. Contact support.';
   if (count($parts)) {
     $summary = $messages['failure'];
   } elseif (($status->validCLA && $status->validSignedOff) &&
             (count($status->validCLA) && count($status->validSignedOff))) {
     $summary = $messages['success'];
   }
   echo "<h3>" . $summary . "</h3>";
   
   //output details
   echo $list_start;
   echo(implode($item_separator, $parts));
   echo $list_end;

   //output history set by other services
   if (count($history)) {
     echo "<h3>External Service Status History</h3>";
     echo $list_start;
     echo(implode($item_separator, $history));
     echo $list_end;
   }
   
   //END OUTPUT
   
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
   function addStatusHistory($item) {
     global $history, $list_start, $list_end, $item_separator;
     array_push($history,
       "<div class='history ".$item->state."'>" . $item->state ." - ". $item->created_at."</div>" .
       $item->description .
      " <a href=" . $item->target_url ."> details</a>"
     );
   }

  ?>
</body>
</html>
