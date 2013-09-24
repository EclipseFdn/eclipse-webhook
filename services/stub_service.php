<?php
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