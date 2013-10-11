<?php
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
