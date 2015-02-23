<?php
if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../lib/logger.php');

$logtester = new Logger();
$logtester->error('This is a test error');

$db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DBNAME);

if ($db->connect_errno) {
  echo "test failed to connect to MySQL: (" 
    . $db->connect_errno . ") " . $db->connect_error;
  echo "MySQL connect failed. Errors will be logged to stderror\n";
  exit();
}

$result = $db->query("SELECT COUNT(*) from githublog;");
if (mysqli_num_rows($result) == 1) {
  echo "MySQL logging appears to be properly configured.\n";
}
?>