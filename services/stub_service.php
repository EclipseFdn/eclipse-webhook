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
 * module: stub_service
 * desc: a specific endpoint for the webhook that handles the payload and dispatches
 *       to a provider class. This is an empty module and requires an implementation.
 *.
 */

include_once('./providers/stub.php');
include_once('./cla_service.php');

$request = $_REQUEST['payload'];

$provider = new StubClient('https://example.com/');
$service = new CLAService($provider);

//if there is no appropriate payload, exit.
if (!$request) {
  exit('Stub service called without payload');
}

$service->process($request);

?>