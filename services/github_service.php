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
*    Denis Roy (Eclipse Foundation)
*******************************************************************************/

/*
 * module: github_service
 * desc: a specific endpoint for the webhook that handles the payload and dispatches
 *       to the github class.
 *.
 */

# error_reporting(E_ALL);
# ini_set('display_errors', 1);

include_once('./providers/github.php');
include_once('./cla_service.php');
if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token to use this service');
}
$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
error_log('X-Github-Event: '. $event);
if (isset($_REQUEST['payload'])) {
  $request = $_REQUEST['payload'];  
}

//while testing, process a sample payload
if (!isset($request)) {
  error_log('received github hook without payload');
  $request = file_get_contents('../test/example_payload.json');
} else {
  file_put_contents(tempnam(sys_get_temp_dir(), 'payload.json'), $request);
}

//if there is no appropriate payload, exit.
//TODO: validate the payload type
if (!$request) {
  exit('Github service called without correct payload');
}

$provider = new GithubClient('https://api.github.com/');
$service = new CLAService($provider);

$service->process($request);

?>
