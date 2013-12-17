<?php
/*
 * command line webhook installer for gitub repos
 */

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../lib/restclient.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);

if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to install webhooks.');
}

//search for command line passed github organization to monitor
$options = getopt("o::", array('organization::'));
if (isset($options['organization'])) {
  $github_organization = $options['organization'];
}
if (isset($options['o'])) {
  $github_organization = $options['o'];
}
if ($github_organization == '') {
  exit("USAGE: You must provide a Github organization as a target for webhook installation in the configuration file, or on the command line as -o=[name] or --organization=[name]\n");
}

if (!count($github_projects)) {
  //if the projects list is empty, enumerate all organization repos
  error_log('INFO: no github repos listed in config/projects.php, enumerating all.');
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'orgs',
    $github_organization,
    'repos'
  ));

  $result = $client->get($url);
  if (is_array($result)) {
    for ($i=0; $i < count($result); $i++) { 
      $github_projects[] = $result[$i]->name;
    }
    error_log("REPO LIST: \n". implode("\n",$github_projects) . "\n");
  }
}

//create payload required for github hook post
//see http://developer.github.com/v3/repos/hooks/#create-a-hook
$payload = new stdClass();
$payload->name = 'web';
$payload->active = true;
$payload->events = $github_hook_add_events;
$payload->config = new stdClass();
$payload->config->content_type = "form";
$payload->config->url = WEBHOOK_SERVICE_URL;

echo('INFO: Processing '. count($github_projects). " web hooks\n");

//iterate over repos list, posting payload via curl
for ($i=0; $i < count($github_projects); $i++) { 

  $patchUrl = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'repos',
    $github_organization,
    $github_projects[$i],
    'hooks'
  ));
  $resultObj = $client->post($patchUrl, $payload);
  echo "\n";
  //report success or error
  if ($resultObj) {
    if (isset($resultObj->url)) {
      echo('INFO: installed webhook: ' . $resultObj->url . "\n");
    } else {
      if ($resultObj->message == "Validation Failed") {
        echo("WARNING: a new hook for " . $github_projects[$i] . " was not installed\n");
        if (count($resultObj->errors)) {
          echo 'WARNING: github reports '. $resultObj->errors[0]->message ."\n";
        }
        //get the existing hook
        $resultObj = $client->get($patchUrl);
        foreach ($resultObj as $hook) {
          echo 'INFO: existing hook: ' . $hook->url . "\n";
          if ($hook->config->url && $hook->config->url == WEBHOOK_SERVICE_URL) {
            //check if events are ok and hook is active before repairing
            if ($hook->events == $github_hook_add_events && 
                $hook->active) {
              echo "INFO: existing hook is properly configured.\n";
            } else {
              echo "WARNING: hook exists but is improperly configured. Reconfiguring...\n";
              $patchResultObj = $client->patch($hook->url, $payload);
              if ($patchResultObj && !($patchResultObj->message == "Validation Failed")) {
                echo "INFO: misconfigured hook successfully patched.\n";
              }
            }
          }
        }
      } else {
        echo('ERROR: encountered github api error: ' . $resultObj->message . "\n");
      }
    }
  } else {
    echo('ERROR: encountered an error processing ' . $patchUrl . "\n" );
  }
}

?>