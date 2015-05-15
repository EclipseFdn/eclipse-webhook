<?php
/*******************************************************************************
* Copyright (c) 2013-2015 Eclipse Foundation and others.
* All rights reserved. This program and the accompanying materials
* are made available under the terms of the Eclipse Public License v1.0
* which accompanies this distribution, and is available at
* http://www.eclipse.org/legal/epl-v10.html
*
* Contributors:
*    Zak James (zak.james@gmail.com) - Initial implementation
*******************************************************************************/
/**
* MySQL store - provides functions for serializing validation and user details using db storage
*/
date_default_timezone_set('UTC');

class MySQLStore
{
  private $db;
  
  function __construct() {
    $this->db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DBNAME);

    if ($this->db->connect_errno) {
      echo "Failed to connect to MySQL: (" 
        . $this->db->connect_errno . ") " . $this->db->connect_error;
    }

    $create_table =
    'CREATE TABLE IF NOT EXISTS github
    (
        identifier VARCHAR(200) NOT NULL,
        json BLOB NOT NULL,
        created TIMESTAMP,
        PRIMARY KEY(identifier)
    )';

    // Create table
    $create_tbl = $this->db->query($create_table);
    if ($create_table) {
      # Do nothing
    }
    else {
      echo "[Error] MySQL store failed to create github table";  
    }
  }
  
  function __destruct() {
    $this->db->close();
  }
  
  public function load($key) {
    $sql = "SELECT json FROM github WHERE identifier = '$key'";
    $result = $this->db->query($sql);
    $row = $result->fetch_assoc();
    $result->free_result();
    if (!$row) {
      return NULL;
    }
    //error_log("[INFO][MySQLStore] loading mysql data: $sql\n");
    return json_decode($row['json']);
  }
  
  public function save($key, $data) {
    if (gettype($data) != 'string') {
      $data = json_encode($data);
    }
    $time = date("Y-m-d H:i:s");
    $sql = "INSERT INTO github VALUES('$key', '$data', '$time')";
    //error_log("[INFO][MySQLStore] storing data: $sql\n");
    $result = $this->db->query($sql);
    return $result;
  }
  
  public function test($key) {
    return $this->db->query("SELECT COUNT(identifier) from github where identifier='$key'");
  }
}
?>