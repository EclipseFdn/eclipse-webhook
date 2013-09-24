CLA Service endpoint - designed to be triggered by web hook.
<?php
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
    $result = $this->api->processPullRequest($request);
    var_dump($result);
  }
}
?>

