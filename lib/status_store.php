<?php
/*
 * class: StatusStore
 * desc: provides an implementation agnostic layer which instantiates a specific storage
 *       class and passes keys and values for persistant storage.
 */
class StatusStore
{
  private $store;
  
  function __construct($store)
  {
      $this->store = $store;
  }
  function __destruct()
  {
      $this->store = NULL;
  }
  
  function load($key) {
    return $this->store->load($key);
  }
  function save($key, $data) {
    return $this->store->save($key, $data);
  }
  function test($key) {
    return $this->store->test($key);
  }
}
?>
