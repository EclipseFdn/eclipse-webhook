<?php
/**
* JSON store - provides functions for serializing validation details using file storage
*/
define('FILE_PREFIX', '/github_pr_status_');

class JsonStore
{
  public function load($key) {
    return json_decode(file_get_contents(sys_get_temp_dir() . FILE_PREFIX . $key .'.json'));
  }
  
  public function save($key, $data) {
    return file_put_contents(sys_get_temp_dir() . FILE_PREFIX . $key . '.json', json_encode($data));
  }
  
  public function test($key) {
    return (file_exists(sys_get_temp_dir() . FILE_PREFIX . $key .'.json'));
  }
}
