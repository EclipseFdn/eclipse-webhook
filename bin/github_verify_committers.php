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
  exit('You must provide a Github access token environment variable to verify committers.');
}

//search for command line passed github organization to monitor
$options = getopt("o::", array('organization::'));
if ($options['organization']) {
  $github_organization = $options['organization'];
}
if ($options['o']) {
  $github_organization = $options['o'];
}
if ($github_organization == '') {
  exit("You must provide a Github organization as a target for committer verification in the configuration file, or on the command line as -o=[name] or --organization=[name]\n");
}

if (!count($github_projects)) {
  //if the projects list is empty, enumerate all organization repos
  error_log('no github repositories listed in config/projects.php, enumerating all repos.');
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
    error_log('repo list: '. print_r($github_projects, true));
  }
}

echo('Verifying '. count($github_projects). " projects\n");

//iterate over repos list, getting collaborators list
$collaborators = array();
$members = array();

for ($i=0; $i < count($github_projects); $i++) {
  $collaborators[$github_projects[$i]] = getGithubCollaborators($github_projects[$i]);
  $members[$github_projects[$i]] = getEclipseMembers($github_projects[$i]);
}
//TODO: compare/synchronize collaborators/members

echo "github repos\n";
var_dump($collaborators);
echo "eclipse projects\n";
var_dump($members);

/* return an array of eclipse foundation members given a project name */
function getEclipseMembers($project) {
  $members = array();
  //TODO get member list for project
  
  //DEBUG DATA
  $resultObj = json_decode('{
    "https://github.com/hooktesto/pulls": [
      {
        "email": "a@eclipse.org", 
        "gitHubId": 42
      }, 
      {
        "email": "b@gmail.com", 
        "gitHubId": 43
      }, 
      {
        "email": "c@eclipse.org", 
        "gitHubId": null
      }
      ]
    }'
  );
  //END DEBUG DATA
  
  if (is_object($resultObj)) {
    foreach(get_object_vars($resultObj) as $repo => $users) {
      if ($project == end(explode('/', $repo))) {
        $members = array();
        foreach($users as $user) {
          $members[] = $user->email;
        }
      }
    }
  }

  //TODO: map project to eclipse name and query eclipse ldap for members
  return $members;
}

/* return an array of collaborators given a repo */
function getGithubCollaborators($repository) {
  global $github_organization, $client;
  $repo_collaborators = array();
  
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'repos',
    $github_organization,
    $repository,
    'collaborators'
  ));
  $resultObj = $client->get($url);
  
  if (is_array($resultObj)) {
    for ($i=0; $i < count($resultObj); $i++) { 
      $login = $resultObj[$i]->login;
      $id = $resultObj[$i]->id;
      //TODO memoize users to avoid api calls
      $userRecord = getGithubUser($login);
      $collaborator = new stdClass();
      $collaborator->login = $login;
      $collaborator->github_id = $id;
      $collaborator->email = $userRecord->email;
      $repo_collaborators[] = $collaborator;
    }
  } else {
    echo "error fetching committers: $url\n";
  }
  
  return $repo_collaborators;
}

/* return details on a github collaborator */
function getGithubUser($login) {
  global $client;
  
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'users',
    $login
  ));
  $resultObj = $client->get($url);
  
  if ($resultObj) {
    return $resultObj;
  }
  echo "error fetching committer: $url\n";
  return NULL;
}

?>