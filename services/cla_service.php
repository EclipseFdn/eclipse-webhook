CLA Service endpoint - designed to be triggered by web hook.
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
 * class: CLAService
 * desc: provides a service agnostic layer which instantiates a specific service
 *       provider and passes web hook payload for processing.
 */
class CLAService
{
  private $api;
  
  function __construct($provider)
  {
      $this->api = $provider;
  }
  
  function process($request) {
    $result = $this->api->processRequest($request);
  }
}
?>

