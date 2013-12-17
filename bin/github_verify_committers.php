<?php
/*
 * command line eclipse project sync for gitub repo teams
 *
 * TODO: refactor this to inherit a base class that abstracts the 2nd service
 *
 * @desc: checks for or creates a github team for each Eclipse project, then
 *        checks or attaches the github repo to the team and verifies that team
 *        members list matches Eclipse. Github users are added/removed as needed.
 *        A dual-keyed (email and github login) cache attemps to reduce number of
 *        github lookups needed across projects.
 */
//set a long execution time since we may have to sleep to wait for api limit reset
ini_set('max_execution_time', '600');

if (file_exists('../config/projects_local.php')) {
  include_once('../config/projects_local.php');
} else {
  include_once('../config/projects.php');
}
include_once('../lib/restclient.php');
include_once('../lib/mysql_store.php');
include_once('../lib/json_store.php');
include_once('../lib/status_store.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);

//Github record caching and persistence
$userCache = array();
$store = null;
if (defined('MYSQL_DBNAME')) {
  $store = new MySQLStore();  
} else {
  $store = new JSONStore();
}
$provider = new StatusStore($store);

if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to verify committers.');
}

//search for command line passed github organization to monitor
$options = getopt("o::dh", array('organization::', 'dry-run', 'help'));
if (isset($options['help']) || isset($options['h'])) {
  exit('usage: php '.__FILE__."\n\t-o=[Github organization] (may be set in config file)\n\t-d (dry-run - no github changes)\n\t-h (help - this message)\n");
}
if (isset($options['organization'])) {
  $github_organization = $options['organization'];
}
if (isset($options['o'])) {
  $github_organization = $options['o'];
}
if (isset($options['dry-run']) || isset($options['d'])) {
  $dry_run = true;
  echo("Dry run mode active -- no changes will be made to Github.\n");
}
if ($github_organization == '') {
  exit("You must provide a Github organization as a target for committer verification in the configuration file, or on the command line as -o=[name] or --organization=[name]\n");
}

if (!count($github_projects)) {
  //if the projects list is empty, enumerate all organization repos
  error_log('[Info] no github repositories listed in config/projects.php, enumerating all repos.');
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
$nProjects = count($github_projects);
$suffix = $nProjects == 1?'':'s';
echo("[Info] verifying $nProjects project$suffix\n");

//iterate over repos list, getting collaborators list
$collaborators = array();
$members = array();

for ($i=0; $i < count($github_projects); $i++) {
  $repoName = $github_projects[$i];
  
  //test for team with format matching eclipse members returned format
  $team = getTeam($repoName);
  if (isset($team)) {
    //test for repo within team
    $teamHasRepo = false;
    $repos = $client->get($team->repositories_url);
    if (is_array($repos)) {
      foreach($repos as $repo) {
        if ($repo->name == $repoName) {
          $teamHasRepo = true;
          echo "[Info] found existing repo '$repoName' associated with team.\n";
        }
      }
    }
    if (!$teamHasRepo) {
      //add repo to team
      $url = implode('/', array(
        GITHUB_ENDPOINT_URL,
        'teams',
        $team->id,
        'repos',
        $github_organization,
        $repoName
      ));
      $repoCreated = $client->put($url);
      //TODO: handle repo creation error
    }
    
    //compare membership lists
    $githubResult = getGithubTeamMembers($team->id);
    $eclipseResult = getEclipseMembers($repoName);

    echo "\n[Info] checking $github_organization/$repoName...\n";
    $toBeRemoved = compare($githubResult, $eclipseResult);
    $toBeAdded = compare($eclipseResult, $githubResult);
      
    //report discrepancies
    if (count($toBeRemoved)) {
      echo "\n[Info] ";
      echo (isset($messages['missing_team_members']) ? $messages['missing_team_
        members'] :
      'Github repo team members missing from eclipse project (to be removed): ');  
      echo "\n";
    } 
    foreach ($toBeRemoved as $person) {
      $email = $person->email;
      echo ('[Info] remove ' . $email !== ''?$email:"login: ".$person->login) . "\n";
      if (!$dry_run) {
        if (!isset($person->login)) {
          //try searching github
          $person = findGithubUser($person->email);
        }
        if ($person && $person->login) {
          $removeResult = removeGithubTeamMember($person->login, $team->id);
          if ($removeResult && $removeResult->http_code == 204) {
            echo "[Info] user removed.\n";
          } else {
            echo ("[Error] unable to remove user. Github response: ".
               isset($removeResult->http_code)?$removeResult->http_code:'unknown'. "\n");
          }
        } else {
          echo "[Error] could not remove unknown github user ".$email."\n";
        }
      }
    }
    if (count($toBeAdded)) {
      echo "\n[Info] ";
      echo (isset($messages['missing_members']) ? $messages['missing_members'] :
      'Eclipse project members missing from Github repo team (to be added): ');
      echo "\n";
      
      foreach ($toBeAdded as $person) {
        $email = $person->email;
        echo "[Info] add $email\n";
        if (!$dry_run) {
          if (!isset($person->login)) {
            //try searching github
            echo "[Info] searching for Github user ".$email."\n";
            $person = findGithubUser($email);
          }
          if ($person && $person->login) {
            $addResult = addGithubTeamMember($person->login, $team->id);
          } else {
            echo "[Info] could not add. User unknown to Github: ".$email."\n";
          }
        }
      }
    } 
    echo "\n";
    
  } else {
    echo "[Error] unable to set up team for $repoName\n";
  }
}


/* get a github team, given an organization and repo. */
/* create a team if none exists */
function getTeam($project) {
  global $github_organization, $client;
  $teamName = $github_organization . '.org-rt.' . $project;
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'orgs',
    $github_organization,
    'teams'
  ));
  $resultObj = $client->get($url);
  
  $teamUrl;
  if (is_array($resultObj)) {
    for ($i=0; $i < count($resultObj); $i++) { 
      if ($resultObj[$i]->name == $teamName) {
        $teamUrl = $resultObj[$i]->url;
        return $client->get($teamUrl);
      };
    }
  } else {
    echo "[Error] failed fetching teams: $url\n";
  }
  //no existing team, create one
  $payload = new stdClass();
  $payload->name = $teamName;
  $payload->permission = "push";
  $payload->repo_names = array($github_organization . '/' . $project);
  $resultObj = $client->post($url, $payload);
  if ($resultObj) {
    return $resultObj;
  } else {
    echo "[Error] failed creating team: $teamUrl\n";
  }
  
  
}

/* return an array of eclipse foundation members given a project name */
function getEclipseMembers($project) {
  global $client;
  $members = array();
  
  $url = implode('/', array(
    USER_SERVICE,
    "$project"
  ));

  $resultObj = $client->get($url);
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

/* return an array of github team members */
function getGithubTeamMembers($teamId) {
  global $github_organization, $client;
  $members  = array();
  
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'teams',
    $teamId,
    'members'
  ));
  $resultObj = $client->get($url);
  
  if (is_array($resultObj)) {
    for ($i=0; $i < count($resultObj); $i++) { 
      $login = $resultObj[$i]->login;
      $id = $resultObj[$i]->id;
      $userRecord = getGithubUser($login);

      $member = new stdClass();
      $member->login = $login;
      $member->gitHubId = intval($id);
      $member->email = isset($userRecord->email)?$userRecord->email:'';
      
      $members[] = $member;
    }
  } else {
    echo "[Error] fetching team members: $url\n";
  }
  
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
    echo "[ERROR] fetching committers: $url\n";
  }
  
  return $repo_collaborators;
}

/* remove an unknown github collaborator from the team */
function removeGithubTeamMember($login, $teamId) {
  global $client, $userCache;

  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'teams',
    $teamId,
    'members',
    $login
  ));
  $resultObj = $client->delete($url);
  if ($resultObj && ($resultObj->http_code == 204)) {
    return $resultObj;
  }
  echo "[ERROR] removing team member: $url\n";
  return NULL;
}

/* add a github user to a team */
function addGithubTeamMember($login, $teamId) {
  global $client;

  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'teams',
    $teamId,
    'members',
    $login
  ));
  $resultObj = $client->put($url);
  
  if ($resultObj && ($resultObj->http_code == 204)) {
    return $resultObj;
  }
  echo "[ERROR] adding team member: $url\n";
  return NULL;
}

/* return details on a github user by searching on email address.*/
/* if possible return a cached result to avoid search api hit */
function findGithubUser($email) {
  global $client, $userCache, $store;
  if (isset($userCache[$email])) {
    return $userCache[$email];
  }
  $json = $store->load($email);
  if ($json) {
    return $json;
  }
  //search on github
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'search',
    'users?q='.$email.'+in:email'
  ));
  $resultObj = $client->get($url);
  //only accept if there is no ambiguity (1 hit)
  if ($resultObj->total_count == 1) {
    //get the user so we return and cache the full record:
    //the search return is missing some fields
    return getGithubUser($resultObj->items[0]->login);
  }
  return NULL;
}
/* return details on a github collaborator */
/* this function also uses and sets the cache to avoid */
/* multiple lookups for the same user */
function getGithubUser($login) {
  global $client, $userCache, $store;
  
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
    //memoize and store so we don't have to check again
    $userCache[$login] = $resultObj;
    if (isset($resultObj->email)) {
      $userCache[$resultObj->email] = $resultObj;
      if (!$store->load($resultObj->email)) {
        $store->save($resultObj->email, json_encode($resultObj));
      }
    }
    return $resultObj;
  }
  echo "error fetching committer: $url\n";
  return NULL;
}


/* check for discrepancies between services */
function compare($groupA, $groupB) {
  return array_udiff($groupA, $groupB, 'compare_members');
}

function compare_members($a, $b) {
  if ($a->gitHubId == $b->gitHubId) {      
    //echo 'matched '.$b->email.' using githubid' ."\n";
    return 0;
  }
  return strcmp($a->email, $b->email);
}

?>
