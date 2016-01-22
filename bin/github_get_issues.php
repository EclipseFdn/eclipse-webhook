<?php
/*******************************************************************************
* Copyright (c) 2013-2016 Eclipse Foundation and others.
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
 * command line Issues backup routine 
 *
 *
 * @desc: Creates a backup copy GitHub issues
 */
//set a long execution time since we may have to sleep to wait for api limit reset
ini_set('max_execution_time', '600');

if (file_exists('../config/projects_local.php')) {
  include_once('../config/projects_local.php');
} else {
  include_once('../config/projects.php');
}
include_once('../services/providers/github.php');
include_once('../lib/mysql_store.php');
include_once('../lib/json_store.php');
include_once('../lib/status_store.php');
include_once('../lib/logger.php');
include_once('../lib/organization/organization.php');

$client = new GithubClient(GITHUB_ENDPOINT_URL);
$logger = new Logger();

$store = null;
if (defined('MYSQL_DBNAME')) {
        $store = new MySQLStore();
} else {
        $store = new JSONStore();
}

$ldap_client = null;
if (defined('LDAP_HOST')) {
        include_once('../lib/ldapclient.php');
        $ldap_client = new LDAPClient(LDAP_HOST, LDAP_DN);
}
$provider = new StatusStore($store);

if (!defined('GITHUB_TOKEN')) {
        exit('You must provide a Github access token environment variable to verify committers.');
}

global $github_organization, $github_issues_basedir;
$org_github = OrganizationFactory::build("github", DEBUG_MODE); # debug on or off

foreach($org_github->getTeamList() as $org_forge_team) {
  echo "Looping through team [" . $org_forge_team->getTeamName() . "]\n";

  $team_basedir = $github_issues_basedir . "/" . $org_forge_team->getTeamName();
  if (!is_dir($team_basedir)){
    if (!mkdir($team_basedir, 0700)) {
      die("Failed to create Team dir $team_basedir...");
    }
  }

  foreach($org_forge_team->getRepoList() as $repoUrl) {
    $repoName = $org_github->getRepoName($repoUrl);
    echo "--> Found repo [" . $repoName . "]\n";
    if($repoName == "") {
      echo "    Missing Github repo: [$repoUrl]\n";
    }
    else {
      $repoFriendlyName = str_replace("/", ".", $repoName);
      echo "--> Repo friendly name: " . $repoFriendlyName . "\n";

      $repo_basedir = $team_basedir . "/" . $repoFriendlyName;
      if (!is_dir($repo_basedir)){
        if (!mkdir($repo_basedir, 0700)) {
          die("Failed to create Repo dir $repo_basedir...");
        }
      }


      # Start the backup process for this repo
      $date = "";
      $page = 1;

      # fetch a timestamp in the repo dir to pull in new issues since last run
      if(file_exists($repo_basedir . "/timestamp.txt")) {
        $fp = fopen($repo_basedir . "/timestamp.txt", "r");
        $data = fread($fp, 64);
        fclose($fp);

        $stuff = explode("\n", $data);
        $date = $stuff[0];
        $stuff = explode(" ", $stuff[1]);
        $page = $stuff[1];
      }

      if($date != "") {
        $date = "&since=" . $date;
      }


      $url = implode('/', array(
             GITHUB_ENDPOINT_URL,
             'repos',
             $repoName,
            'issues?direction=asc&state=all' . $date ));

       $morepages = true;
       while($morepages) {
         echo "-->" .  $url . "\n";
         $json =  $client->getraw($url);
         if(strlen($json) > 10) {
           # echo "Remaining hits: " . $client->getResponseHeaders("X-RateLimit-Remaining") . "\n";
           $gz = gzopen($repo_basedir . "/issues.$page.json.gz", "w9");
           gzwrite($gz, $json);
           gzclose($gz);

           $url = $client->getNextPageLink();
           $page++;
           if ($url == "") {
             $morepages = false;
           }
        }
        else {
          $morepages = false;
        }
      }

      # Write status info
      $fp = fopen($repo_basedir . "/timestamp.txt", "w");
      fwrite($fp, date("c") . "\n");
      fwrite($fp, "page " . $page);
      fclose($fp);
    }
  }
}
?>