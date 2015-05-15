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
