<?php
/**
* Base class for logging
* - abstracts db vs system logging. 
*/
date_default_timezone_set('UTC');

class Logger
{
  private $logtable = "githublog";
  
  private $db;
  function __construct()
  {
    $this->db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DBNAME);

    if ($this->db->connect_errno) {
      error_log("Logger failed to connect to MySQL: (" 
        . $this->db->connect_errno . ") " . $this->db->connect_error);
      error_log("Errors will be sent to stderror");
      $this->db = null;
      return;
    }
    
    $create_table =
    'CREATE TABLE IF NOT EXISTS githublog
    (
        identifier INT auto_increment primary key,
        message VARCHAR(2048),
        level VARCHAR(10),
        created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )';

    // Create table
    $create_tbl = $this->db->query($create_table);
    if ($create_tbl) {
      //error_log("[Info][MySQLStore] Github log table ok");
    } else {
      error_log("[Error] MySQL store failed to create github table for log messages");  
    }
  }
  
  function __destruct() {
    $this->db->close();
  }
  
  function error($message) {
    $this->insert($message, 'ERROR');
  }
  
  function info($message) {
    $this->insert($message, 'INFO');
  }
  
  function insert($message, $level) {
    if ($this->db) {
      $message = $this->db->real_escape_string($message);
      $this->db->query("INSERT into $this->logtable (message, level) VALUES ('$message', '$level')");
    } else {
      error_log($message);
    }
  } 
}
?>