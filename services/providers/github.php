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

/**
* Github model - provides functions for interacting with GitHub API
*/
if (file_exists('../config/projects_local.php')) {
  include_once('../config/projects_local.php');
} else {
  include_once('../config/projects.php');
}
include_once('../lib/restclient.php');
include_once('../lib/mysql_store.php');
include_once('../lib/json_store.php');
include_once('../lib/status_store.php');
include_once('../lib/organization/organization.php');


class GithubClient extends RestClient
{
  private $users;
  private $statusDetailsKey;
  private $organization;

  /*
   * function: GithubClient::processRequest
   * @param string $request - json payload
   * @desc: dispatch request to appropriate handler.
   */
  public function processRequest($request) {
    $event = $_SERVER['HTTP_X_GITHUB_EVENT'];
    switch ($event) {
      case 'pull_request':
        $this->processPullRequest($request);
        break;
      case 'status':
        $this->processStatus($request);
        break;
      default:
      $this->logger->error('received unhandled github event: ' . $event);
        break;
    }
  }
  /*
   * function: GithubClient::processPullRequest
   * @param string $request - json payload
   * @desc: retrieves all referenced commits and checks authors for CLA.
   *       also checks that Signed-off-by header is present and matches committer.
   */
  public function processPullRequest($request) {
    global $github_organization;


    $json = json_decode(stripslashes($request));
    if ($json->action == 'closed') { return; }

    $this->statusDetailsKey = uniqid();

    # Create an organization object that will process rules specific to the organization
    $this->organization = OrganizationFactory::build($github_organization, DEBUG_MODE);

    # fabricate ID for this transaction for logging purposes
    $pr_id = "PULL REQUEST:" . $json->repository->full_name . ":" . $json->number . " Key: " . $this->statusDetailsKey . " ";
    $this->logger->info($pr_id . 'NEW ' . $json->pull_request->html_url . ' ' . $json->action);


    $commits_url = $json->pull_request->url . '/commits';
    $statuses_url = $json->repository->statuses_url;
    $comments_url = $json->pull_request->comments_url;

    //get commits for this PR
    $commits = $this->get($commits_url);
    $this->logger->info($pr_id . 'commits url '.$commits_url . ' number of commits: ' . count($commits));

    # process organization-specific rules for PR
    $pullRequestState = "failure";
    if($this->organization->validatePullRequest($json, $commits, $this->statusDetailsKey)) {
        $pullRequestState = "success";
    }

	# create a response message for the web service, and send it back to the browser. This is helpful 
	# for debugging and replaying PR payloads
    $pullRequestMessage = $this->organization->composeStatusMessage();
    echo $pullRequestMessage;

	# fetch the committer buckets from the organization, so we can add status
    $this->users = $this->organization->getUsers();

    //get statuses (so we can provide history of 3rd party statuses)
    $status_history = $this->getCommitStatusHistory($statuses_url, end($commits));
    $this->users['StatusHistory'] = $status_history;
    $this->users['StatusDetailKey'] = $this->statusDetailsKey;

    //persist the status locally so it can be accessed at the github details url
    $this->storeStatus();

    //apply a new status to the pull request, targetting last commit.
    $result = $this->setCommitStatus($statuses_url, end($commits), $pullRequestState, $pullRequestMessage);

    //send mail to any configured addresses if the validation is unsuccessful
    if($pullRequestState == "failure") {
      $senderRecord = $this->getGithubUser($json->sender->login);
      $to = array();
      if ($senderRecord && isset($senderRecord->email)) {
        $to[] = $senderRecord->email;
      }
      $this->emailNotification($to, $pullRequestMessage, $json);
    }

    //add a comment to the pr with a link to an associated bug
    //bug 462471 - link bugs from bug numbers
    if ($json->action == 'opened') {
      $title = $json->pull_request->title;
      $organization = '';
      # todo: $json->repository->organization doesn't seem to exist
      if ($json->repository && $json->repository->organization) {
        $organization = $json->repository->organization;
      }
      $pullRequestComment = $this->addBugLinkComment($comments_url, $title, $organization);
    }
    $this->callHooks('pull_request', $json);
    //TODO: close pull request?
  }
  /*
   * function: GithubClient::processStatus
   * @param string $request - json payload
   * @desc: determines if the status event was generated by this service.
   *        if not, it revalidates users, sets status and includes status 
   *        history in the details report.
   */
  public function processStatus($request) {
    $json = json_decode(stripslashes($request));
    $this->logger->error('processing repo status update with target_url:' . $json->target_url);
    if(stripos(WEBHOOK_SERVICE_URL, $json->target_url) === FALSE) {
      //third party must have set status
      //TODO: get the pull request and re-evaluate
      //TODO: set a new status and add third party status history to details
    }
    //do nothing, status is already set.
  }
  /*
   * function: GithubClient::emailNotification
   * @param array $to - email recipients
   * @param string $message - email body
   * @param object $json - pull request payload
   * @desc: sends email to the pull request originator with information about the failure
   */
  public function emailNotification($to, $message, $json) {
    $recipients = implode(',', $to);
    //ensure there is a recipient
    if ($recipients == '') {
      $recipients = ADMIN_EMAIL;
    }
    
    //TODO: move email strings to config
    
    $historyDetail = $this->users['StatusHistory'];
    $historyMessage = '';
    if (is_array($historyDetail) && count($historyDetail)) {
      $historyMessage = "\n\nExternal Service Status history: \n";
      $items = array();
      foreach($historyDetail as $item) {
        $items[] = "Description: ".$item['description']."\n" .
                   "State: ".$item['state']."\n" .
                   "Date: ".$item['created_at']."\n" .
                   "Details: ". $item['target_url'] ."\n";
      }
      $historyMessage .= implode("\n", $items);
    }

    $message = 'There was a problem validating pull request ' .
                $json->pull_request->html_url . "\r\n\n" .
                $message .
                $historyMessage;

    $subject = '[Eclipse-Github][Validation Error] '. $json->repository->full_name;
    $headers = 'From: noreply@eclipse.org' . "\r\n" .
               'Cc: ' . ADMIN_EMAIL . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    mail($recipients, $subject, $message, $headers);
  }



  /*
   * Function GithubClient::storeStatus
   * @desc keep a record of the status to use in the details url on github
   */
  private function storeStatus() {
    $store = null;
    if (defined('MYSQL_DBNAME')) {
      $store = new MySQLStore();  
    } else {
      $store = new JSONStore();
    }
    $provider = new StatusStore($store);


    return $provider->save($this->statusDetailsKey, $this->users); 
  }

  /*
   * Function GithubClient::setCommitStatus
   * @param object commit - target commit for status
   * @param string state - the state to apply [success, failure, pending]
   * @param string message - comments to explain the status
   * @desc POSTs the status message and appearance on github 
   */
  private function setCommitStatus($url, $commit, $state, $message) {
    $url = str_replace('{sha}', $commit->sha, $url);
    $this->logger->error('pull request status update url: '. $url);

    //create a details url for the status message
    $service_url_parts = explode('/', WEBHOOK_SERVICE_URL);
    array_pop($service_url_parts);
    array_push($service_url_parts, 'status_details.php?id=' . $this->statusDetailsKey);
    $details_url = implode('/', $service_url_parts);

    //create payload required for github status post
    //see http://developer.github.com/v3/repos/statuses/#create-a-status
    $payload = new stdClass();
    $payload->state = $state;
    $payload->target_url = $details_url;
    $payload->context = 'ip-validation';

    //TODO: handle github description limit of 140 chars gracefully
    if (strlen($message) < 140) {
      $payload->description = $message;
    } else {
      $payload->description = substr($message, 0, 137) . '...';
    }

    return $this->post($url, $payload);
  }

  /*
   * Function GithubClient::getCommitStatusHistory
   * @param object commit - commit to query for status
   * @desc GETs the status messages
   */
  private function getCommitStatusHistory($url, $commit) {
    $result = array();
    $url = str_replace('{sha}', $commit->sha, $url);
    $json = $this->get($url);

    for ($i=0; $i < count($json); $i++) {
      $status = $json[$i];

      //record only 3rd party statuses, which won't match our details url
      $service_url_parts = explode('/', WEBHOOK_SERVICE_URL);
      array_pop($service_url_parts);
      if (stripos($status->target_url, implode('/', $service_url_parts)) !== 0) {
        $result[] = array(
          "url" => $status->url,
          "created_at" => $status->created_at,
          "description" => $status->description,
          "state" => $status->state,
          "target_url" => $status->target_url
        );
      }
    }
    return $result;
  }

  /*
   * Function GithubClient::addBugLinkComment
   * @param string title - pr title to parse for bug reference
   * @param string organization - the bug tracker's organization 
   * @desc POSTs a comment to the pull request containing a link to
   *       a bug reference
   */
  private function addBugLinkComment($url, $title, $organization) {
    $orgName = ($organization == '')?'eclipse':$organization; 
    $this->logger->info('pull request comment url: '. $url);
    $this->logger->info("looking for bug reference in: $title");

    //match ~ Bug: xxx or [xxx]
    $re = "/[Bb]ug:?\s*#?(\d{6,})|\[(\d{6,})\]/";
    $matches = array();
    if (preg_match($re, $title, $matches) && count($matches) > 1) {
      //bug: match will be matches[1], [xxx] match will be matches[2]
      $nBug = count($matches) == 3 ? $matches[2]:$matches[1];
      $link = "https://bugs.$orgName.org/bugs/show_bug.cgi?id=$nBug";

      //create payload required for github comment post
      //see https://developer.github.com/v3/issues/comments/#create-a-comment
      $payload = new stdClass();
      $payload->body = "Issue tracker reference:\n". $link;

      return $this->post($url, $payload);
    };

    return false;
  }

  /*
   * Function GithubClient::callHooks
   * @param string event - the event type used to determine which hooks to call
   * @desc generically passes pr to scripts in the hooks directory based on action
   *       This is designed to be used for service specific actions.
   *       see bug: 462471
   */
  private function callHooks($event, $json) {
    $hookName = str_replace(array('/','\\','.'),'', $event.'_'.$json->action);
    $fileName = "./providers/hooks/$hookName.php";
    $functionName = $hookName.'_hook';
    if (file_exists($fileName)) {
      include($fileName);
      if (is_callable($functionName)) {
        $this->logger->info("invoking custom hook function: $functionName");
        call_user_func($functionName, $json);
      }
    }
  }

  /*
   * Function GithubClient::getGithubUser
   * @param string login - github login to query
   * @desc GETs the complete user record
   */
  private function getGithubUser($login) {
    $url = implode('/', array(
      GITHUB_ENDPOINT_URL,
      'users',
      $login
    ));
    $resultObj = $this->get($url);

    if ($resultObj) {
      return $resultObj;
    }
    return NULL;
  }

  /**
   * Fetch results in a paginated fashion.  See: https://developer.github.com/v3/#pagination
   * @param String $url
   * @return Array of JSON encoded objects
   * @since 2015-05-15
   * @author droy
   */
  public function get($url) {

    $rValue = array();

    $page = 1;
    $per_page = 100; # TODO: put this in the config!
    $morepages = true;

    while($morepages) {
      # TODO: API docs recommend against creating own pagination URLs, use Links header instead
      if(strpos($url, "?") === false) {
        $thisurl = $url . "?";
      }
      else {
        $thisurl = $url . "&";
      }
      $thisurl .= "page=$page&per_page=$per_page";
      $json = $this->curl_get($thisurl);
      $objJSON = json_decode($json);
      $morepages = count($objJSON) == $per_page;
      $rValue = array_merge($rValue, $objJSON);
      $page++;
    }
    return $rValue;
  }

  /**
   * Fetch results in a raw fashion -- json, no pages.  See: https://developer.github.com/v3/#pagination
   * @param String $url
   * @param bool Not Paged. If true, do not add page and per_page parameters
   * @return String JSON string
   * @since 2016-01-14
   * @author droy
   */
  public function getraw($url, $notpaged=false) {

    $rValue = "";

    $thisurl = $url;
    if(! $notpaged) {
      $per_page = 100; # TODO: put this in the config!

      if(strpos($url, "?") === false) {
        $thisurl = $url . "?";
      }
      else {
        $thisurl = $url . "&";
      }
      $thisurl .= "per_page=$per_page";
    }
    $json = $this->curl_get($thisurl);
    return $json;
  }

  /**
   * Return the URL to the next page, if any
   * @return string URL
   * @author droy
   * @since 2016-01-14
   */
  public function getNextPageLink() {
    $rValue = "";
    if(isset($this->response_headers)) {
      if(strlen($this->response_headers) > 0) {
        $link_string = $this->getResponseHeaders("Link");
        if(preg_match("/<(.*)>; rel=\"next\"/", $link_string, $matches)) {
          $rValue = $matches[1];
        }
      }
    }
    return $rValue;
  }
}

?>
