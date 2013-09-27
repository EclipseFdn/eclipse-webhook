<?php
/**
* Github model - provides functions for interacting with GitHub API
*/
if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include_once('../lib/restclient.php');
include_once('../lib/json_store.php');
include_once('../lib/status_store.php');

class GithubClient extends RestClient
{
  private $users;
  private $statusDetailsKey;
  
  /*rest utility functions
   *TODO: move to base class
   */
  public function get($url) {
    $json = ($this->curl_get($url));
    return json_decode($json);
  }
  public function post($url, $data) {
    $json = ($this->curl_post($url, json_encode($data)));
    return json_decode($json);
  }
  public function patch($url, $data) {
  }
  
  /*
   * function: GithubClient::processPullRequest
   * @param string $request - json payload
   * @desc: retrieves all referenced commits and checks authors for CLA.
   *       also checks that Signed-off-by header is present and matches committer.
   */
  public function processPullRequest($request) {
    //get repo commits
    $json = json_decode($request);
    error_log('handling pull request from '.$json->repository->full_name);

    $commits_url = $json->pull_request->url . '/commits';
    $statuses_url = $json->repository->statuses_url;
    error_log('commits url '.$commits_url);
    error_log('statuses url '.$statuses_url);
    
    //get commits
    $commits = $this->get($commits_url);
    error_log('number of commits: ' . count($commits));

    //walk authors, testing CLA and Signed-off-by
    $this->users = array(
      'validCLA' => array(),
      'invalidCLA' => array(),
      'unknownCLA' => array(),
      'validSignedOff' => array(),
      'invalidSignedOff' => array(),
      'unknownSignedOff' => array()
    );
    
    for ($i=0; $i < count($commits); $i++) { 
      error_log('found committer: '.$commits[$i]->committer->login);
      
      //TODO: evaluate author as well or instead?
      $this->evaluateCLA($commits[$i]->commit->committer);
      $this->evaluateSignature($commits[$i]->commit);
      
      //if there is no login, the user given in the git commit is not a valid github user
      error_log('listed committer in commit: '.
        $commits[$i]->commit->committer->name .
        '<'.$commits[$i]->commit->committer->email.'>');
      
      //Signed-off-by is found in the commit message
      error_log('commit message: '.$commits[$i]->commit->message);      
    }
    var_dump($this->users);
    
    //see if any problems were found, make suitable message
    $pullRequestState = $this->getPullRequestState();
    $pullRequestMessage = $this->composeStatusMessage();
    
    //apply a new status to the pull request, targetting last commit.
    $result = $this->setCommitStatus($statuses_url, end($commits), $pullRequestState, $pullRequestMessage);
    
    //TODO: get statuses (so we don't interfere with others)
    //TODO: close pull request?
    //TODO: email pull request originator
  }
  /*
   * Function GithubClient::evaluateCLA
   * @param object committer - github user who made the commit
   * @desc evaluate CLA status against external service  
   */
  private function evaluateCLA($committer) {
    $email = $committer->email;
    $eclipse_cla_status = $this->curl_get($_SERVER['CLA_SERVICE']) . $email;
    if ($eclipse_cla_status == 'TRUE') {
      array_push($this->users['validCLA'], $email);
    } elseif ($eclipse_cla_status == 'FALSE') {
      array_push($this->users['invalidCLA'], $email);        
    } else {
      array_push($this->users['unknownCLA'], $email);
    }
  }
  
  /*
   * Function GithubClient::evaluateSignature
   * @param object commit
   * @desc evaluate signature match in Signed-off-by against committer
     @desc Signed-off-by is found in the commit message 
   */
  private function evaluateSignature($commit) {
    $email = $commit->committer->email;
    //look Signed-off-by pattern:
    $pattern = '/^Signed-off-by:.*<(.*@.*)>$/';
    //signature is only valid if it matches committer
    if (preg_match($pattern, $commit->message, $matches)) {
      if (count($matches) == 2) {
        if ($matches[1] == $email) {
          //matches committer
          array_push($this->users['validSignedOff'], $email);
        } else {
          //matched pattern but isn't the committer email
          array_push($this->users['invalidSignedOff'], $email);
        }
      }
      //matched pattern but there is more than one
      array_push($this->users['invalidSignedOff'], $email);
    } else {
      //no Signed-off-by at all
      array_push($this->users['unknownSignedOff'], $email);
    }
  }
  
  /*
   * Function GithubClient::getPullRequestState
   * @desc find the state for the entire message.
   * @return string expected by github status api
   */
  private function getPullRequestState() {
    if (count($this->users['invalidSignedOff']) +
        count($this->users['unknownSignedOff']) +
        count($this->users['invalidCLA']) +
        count($this->users['unknownCLA']) == 0) {
          return 'success';
    }
    return 'failure';
  }
  
  /*
   * Function GithubClient::composeStatusMessage
   * @desc build the status description including specific users and faults
   * @desc messages come from config/projects.php
   */
  private function composeStatusMessage() {
    global $messages;
    $parts = array();
    
    //list problems with corresponding users
    //TODO: figure out a way around github 140 char max. description
    if (count($this->users['invalidCLA'])) {
      array_push($parts, $messages['badCLAs'] . implode(', ', $this->users['invalidCLA']));
    }
    if (count($this->users['unknownCLA'])) {
      array_push($parts, $messages['unknownUsers'] . implode(', ', $this->users['unknownCLA']));
    }
    if (count($this->users['invalidSignedOff'])) {
      array_push($parts, $messages['badSignatures'] . implode(', ', $this->users['invalidSignedOff']));
    }
    if (count($this->users['unknownSignedOff'])) {
      array_push($parts, $messages['badSignatures'] . implode(', ', $this->users['unknownSignedOff']));
    }
    //add a summary message
    if (count($parts)) {
      array_unshift($parts, $messages['failure']);
    } else {
      array_unshift($parts, $messages['success']);
    }
    
    //save everything into status store so it can be provided at status 'details' url
    $json_store = new JsonStore();
    $provider = new StatusStore($json_store);
  
    $this->statusDetailsKey = uniqid();
    $status = $provider->save($this->statusDetailsKey, $parts);
    
    return implode("\n", $parts);
  }
  
  public function getPath($path) {
    $json = $this->get($this->endPoint . $path);
    return $json;
  }
  /*
   * Function GithubClient::setCommitStatus
   * @param object commit - target commit for status
   * @param string state - the state to apply [success, failure, pending]
   * @param string message - comments to explain the status
   * @desc POSTs the status message and appearance on github 
   */
  public function setCommitStatus($url, $commit, $state, $message) {
    $url = str_replace('{sha}', $commit->sha, $url);
    error_log('pull request status update url: '. $url);
    
    //create a details url for the status message
    $service_url_parts = explode('/', WEBHOOK_SERVICE_URL);
    array_pop($service_url_parts);
    array_push($service_url_parts, 'status_details.php?id=' . $this->statusDetailsKey);
    $details_url = implode('/', $service_url_parts);
    
    //create payload required for github status post
    //see http://developer.github.com/v3/repos/statuses/#create-a-status
    $payload = null;
    $payload->state = $state;
    $payload->target_url = $details_url;
    
    //TODO: handle github description limit of 140 chars gracefully
    if (strlen($message) < 140) {
      $payload->description = $message;
    } else {
      $payload->description = substr($message, 0, 137) . '...';
    }
    
    return $this->post($url, $payload);
  }
}

?>
