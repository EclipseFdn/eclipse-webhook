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
* Stub model - provides functions for interacting with some other API
*/

include_once('../lib/restclient.php');

class StubClient extends RestClient
{
  public function processRequest($request) {
    error_log('Stub service has no implemenation.');
  }
}
