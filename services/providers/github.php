<?php
/**
* Github model - provides functions for interacting with GitHub API
*/

include_once('../lib/restclient.php');

class GithubClient extends RestClient
{
  private $users;
      
  public function get($url) {
    $json = ($this->curl_get($url));
    return json_decode($json);
  }
  public function post($url, $data) {
    $url = $this->buildUrl($path);
    return json_decode($json);
  }
  public function patch($url, $data) {
  }
  
  /*
   * function: GithubClient::processPullRequest
   * desc: retrieves all referenced commits and checks authors for CLA.
   *       also checks that Signed-off-by header is present and matches committer.
   */
  public function processPullRequest($request) {
    //get repo commits
    $json = json_decode($request);
    error_log('handling pull request from '.$json->repository->full_name);
    $commits_url = $json->pull_request->url . '/commits';
    error_log('commits url '.$commits_url);

    //get commits
    $commits = $this->get($commits_url);
    error_log('number of commits: ' . count($commits));

    //walk authors, testing CLA and Signed-off-by
    $this->users = array(
      'validCLA' => array(),
      'invalidCLA' => array(),
      'validSignedOff' => array(),
      'invalidSignedOff' => array()
    );
    
    for ($i=0; $i < count($commits); $i++) { 
      error_log('found committer: '.$commits[$i]->committer->login);
      
      //if there is no login, the user given in the git commit is not a valid github user
      error_log('listed committer in commit: '.
        $commits[$i]->commit->committer->name .
        '<'.$commits[$i]->commit->committer->email.'>');
      
      //Signed-off-by is found in the commit message
      error_log('commit message: '.$commits[$i]->commit->message);
      $email = $commits[$i]->commit->committer->email;
      //TODO: evaluate signed-off-by against email

      //evaluate CLA status
      $eclipse_cla_status = $this->curl_get($_SERVER['CLA_SERVICE']) . $email;
      if ($eclipse_cla_status == 'TRUE') {
        array_push($this->users['validCLA'], $email);
      } else {
        array_push($this->users['invalidCLA'], $email);        
      }
    }
    var_dump($this->users);
    //TODO: get statuses (so we don't interfere with others)
    //TODO: set status
    //TODO: close pull request?
    //TODO: email pull request originator
  }
   
  public function getPath($path) {
    $json = $this->get($this->endPoint . $path);
    return $json;
  }
  public function getPullRequestCommits($repo, $pullNumber) {
    $url = $this->buildURL(array('repos', OWNER, $repo, 'pulls', $pullNumber));
    $json = $this->get($url);
    return $json;
  }
  public function getCommitAuthors($repo, $commitSHA) {
    $url = $this->buildURL(array('repos', OWNER, $repo, 'commits', $commitSHA));
    $json = $this->get($url);
    return $json;
  }
  
  public function setCommitStatus($repo, $commitSHA, $state, $message) {
  }
}

?>
