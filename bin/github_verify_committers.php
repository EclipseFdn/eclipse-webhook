<?php
/*
 * command line webhook installer for gitub repos
 * TODO: refactor this to inherit a base class that abstracts the 2nd service
 */

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../lib/restclient.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);
$userCache = array();

if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to verify committers.');
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
  }
}

echo('Verifying '. count($github_projects). " projects\n");

//iterate over repos list, getting collaborators list
$collaborators = array();
$members = array();

for ($i=0; $i < count($github_projects); $i++) {
  $repoName = $github_projects[$i];
  $githubResult = getGithubCollaborators($repoName);
  $eclipseResult = getEclipseMembers($repoName);

  echo "\nchecking $repoName...\n";
  echo (isset($messages['missing_members']) ? $messages['missing_members'] :
              'Github repo collaborators missing from eclipse project: ');
  echo "\n";
  $unknowns = compare($githubResult, $eclipseResult);
  //var_dump($unknowns);
  
  foreach ($unknowns as $person) {
    echo $person->email . "\n";
  }
}

/* return an array of eclipse foundation members given a project name */
function getEclipseMembers($project) {
  $members = array();
  //TODO get real member list for project
  
  //DEBUG DATA
  $resultObj = json_decode('{
    "https://github.com/hooktesto/pulls": [
      {
        "email": "a@eclipse.org", 
        "gitHubId": 42
      }, 
      {
        "email": "b@gmail.com", 
        "gitHubId": 468272
      },
      {
        "email": "c@eclipse.org", 
        "gitHubId": null
      },
      {
        "email": "d@eclipse.org", 
        "gitHubId": 249841
      }
      ],
      "https://github.com/hooktesto/testpulls": [
        {
          "email": "a@eclipse.org", 
          "gitHubId": 42
        }, 
        {
          "email": "b@gmail.com", 
          "gitHubId": 41
        }, 
        {
          "email": "c@eclipse.org", 
          "gitHubId": null
        },
        {
          "email": "d@eclipse.org", 
          "gitHubId": 249841
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
          $user->gitHubId = intval($user->gitHubId);
          $members[] = $user;
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
      $userRecord = getGithubUser($login);

      $collaborator = new stdClass();
      $collaborator->login = $login;
      $collaborator->gitHubId = intval($id);
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
  global $client, $userCache;
  
  if(isset($userCache[$login])) {
    return $userCache[$login];
  }
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'users',
    $login
  ));
  $resultObj = $client->get($url);
  
  if ($resultObj) {
    //memoize so we don't have to check again
    $userCache[$login] = $resultObj;
    return $resultObj;
  }
  echo "error fetching committer: $url\n";
  return NULL;
}

/* check for discrepancies between services */
function compare($collaborators, $members) {
  return array_udiff($collaborators, $members, function ($collaborator, $member) {
    if ($collaborator->gitHubId == $member->gitHubId) {
      //echo 'matched '.$member->email.' using githubid' ."\n";
      return 0;
    }
    //TODO: if we match on email address, store the gh id locally so we can 
    //TODO: handle a change in address on the github side in the future.
    return strcmp($collaborator->email, $member->email);
  });
}

?>