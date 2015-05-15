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
 * command line webhook installer for gitub repos
 */

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../lib/restclient.php');
include_once('../lib/organization/organization.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);

if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to install webhooks.');
}


$org_github = OrganizationFactory::build("github", DEBUG_MODE); # debug on or off
$repolist = $org_github->getAllRepos();

//create payload required for github hook post
//see http://developer.github.com/v3/repos/hooks/#create-a-hook
$payload = new stdClass();
$payload->name = 'web';
$payload->active = true;
$payload->events = $github_hook_add_events;
$payload->config = new stdClass();
$payload->config->content_type = "form";
$payload->config->url = WEBHOOK_SERVICE_URL;

echo('INFO: Processing '. count($repolist). " repositories.\n");

//iterate over repos list, posting payload via curl
foreach ($repolist as $repoUrl) {

  $repoName = $org_github->getRepoName($repoUrl);
  $patchUrl = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'repos',
    $repoName,
    'hooks'
  ));
  $resultObj = $client->post($patchUrl, $payload);
  echo "INFO: checking to [" . $patchUrl . "]\n";
  //report success or error
  if ($resultObj) {
    if (isset($resultObj->url)) {
      echo('    INFO: installed webhook: ' . $resultObj->url . "\n");
    } else {
      if ($resultObj->message == "Validation Failed") {
        echo("    WARNING: a new hook for $repoName was not installed\n");
        if (count($resultObj->errors)) {
          echo '    WARNING: github reports '. $resultObj->errors[0]->message ."\n";
        }
        //get the existing hook
        $resultObj = $client->get($patchUrl);
        foreach ($resultObj as $hook) {
          echo '    INFO: existing hook: ' . $hook->url . "\n";
          if (isset($hook->config->url)) {
            if($hook->config->url == WEBHOOK_SERVICE_URL) {
              //check if events are ok and hook is active before repairing
              if ($hook->events == $github_hook_add_events && $hook->active) {
                echo "        INFO: existing hook is properly configured.\n";
              } else {
                echo "        WARNING: hook exists but is improperly configured. Reconfiguring...\n";
                $patchResultObj = $client->patch($hook->url, $payload);
                if ($patchResultObj && !($patchResultObj->message == "Validation Failed")) {
                  echo "        INFO: misconfigured hook successfully patched.\n";
                }
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