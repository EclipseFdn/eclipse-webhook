<?php
/*******************************************************************************
* Copyright (c) 2015 Eclipse Foundation and others.
* All rights reserved. This program and the accompanying materials
* are made available under the terms of the Eclipse Public License v1.0
* which accompanies this distribution, and is available at
* http://www.eclipse.org/legal/epl-v10.html
*
* Contributors:
*    Denis Roy (Eclipse Foundation) - initial API and implementation
*    Zak James (zak.james@gmail.com)
*******************************************************************************/

# Basic functions for an Eclipse forge
class Eclipse extends Organization {

	private $objPMIjson;  ## See below for visual example
	private $teamList;
	private $users = array(
		'validCommitter' => array(),
		'validCLA' => array(),
		'invalidCLA' => array(),
		'validSignedOff' => array(),
		'invalidSignedOff' => array(),
		'unknownSignedOff' => array()
	);
	private $ldap_client;
	private $debug;
	
	
	function __construct($debug) {
		$this->debug = $debug;
		# Fetch list of Organization teams, the repos and users in each
		$client = new RestClient(GITHUB_ENDPOINT_URL);
		$this->logger = new Logger();
		$this->objPMIjson = $client->get(USER_SERVICE);
		$this->teamList = array();
		
		if (defined('LDAP_HOST')) {
			include_once('../lib/ldapclient.php');
			$this->ldap_client = new LDAPClient(LDAP_HOST, LDAP_DN);
		}
		
		if (is_object($this->objPMIjson)) {
			foreach(get_object_vars($this->objPMIjson) as $teamName => $repoUserObj) {
				if($this->debug) echo "In Eclipse obj. teamname is: $teamName \n";
				$team = new Team($teamName);
				if(is_object($repoUserObj)) {
					foreach($repoUserObj->repos as $repo) {
						if($this->debug) echo "In Eclipse obj. repo is: $repo \n";
						#Work out which GitHub org this repo belongs to by stripping the host part of the URL, and then splitting based on /
						$teamRepoOrg = explode("/",str_replace("https://github.com/","",$repo));
						$teamOrg = $team->getOrgName();
						#if there isn't currently an org for the current org and we have an org from the URL set the value.
						if ( $teamOrg === '' && $teamRepoOrg[0] !== '' ) {
							if($this->debug) echo "Setting org name to: $teamOrg($teamRepoOrg[0]) \n";
							$team->setOrgName($teamRepoOrg[0]);
							#teamnames are important and need to be updated based on the org name in order for follow on processing to find them
							#for 'sub' orgs(eclipse-ee4j) they should simply use the 'parent' org name for the first half of the team name
							$orgNameParts= explode("-",$teamRepoOrg[0]);
							if ( preg_match("/$orgNameParts[0]/",$teamName) !==1 ){
							  $teamName =preg_replace('/(.*)-(.*)/',"$orgNameParts[0]-$2",$teamName);
							  if($this->debug) echo "TeamName<>OrgName mismatch. Setting teamname to: $teamName \n";
							  $team->setTeamName($teamName);
							}
						} else if ( strcmp($teamRepoOrg[0],$teamOrg) !== 0 ) {
							#this repo is in another org, which should be a no-no within a single project.
							echo "[Error] $teamName has a repo in another org (got: $teamRepoOrg expected: $teamOrg.\n";
							continue;
						}
  						$team->addRepo($repo);
					}
					foreach($repoUserObj->users as $user) {
						$team->addCommitter($user);
						if($this->debug) echo "Adding $user to $teamName \n";
					}
				}
				else {
					echo "[Error] Team name $teamName does not have any users!\n";
					$this->logger->error("Team name $teamName does not have any users!");
				}
				array_push($this->teamList, $team);
			}
		}
		
		if($this->debug) $this->debug();
		
	}
	
	/** Validate Pull request
	 * 
	 * @param Obj $pullRequestJSON, represented as an object
	 * @param Obj $commitsJSON, represented as an object
	 * @param string $statusDetailKey unique identifier for logging
	 * @return boolean Pull request passes Eclipse validation
	 */
	public function validatePullRequest($pullRequestJSON, $commitsJSON, $statusDetailKey) {
		$rValue = false;

                $previous_authors = array();
		for ($i=0; $i < count($commitsJSON); $i++) {
			//According to the handbook(https://www.eclipse.org/projects/handbook/#resources-commit) we care about the author
			$author = $commitsJSON[$i]->commit->author;
			$gh_author = $commitsJSON[$i]->author;
			if (!in_array($author->email,$previous_authors)) {
				$previous_authors[] = $author->email;
				if($this->isCommitterOfRepo($author->email, $pullRequestJSON->repository->full_name)) {
					array_push($this->users['validCommitter'], $author->email);
				}
				else {
					# Check if the GitHUb login is a committer.  Could just be an email mismatch
					# We avoid looking up the email address of the GH Login earlier,
					# since the previous isCommitterOfRepo() may have succeeded without the LDAP hit
					# See: https://bugs.eclipse.org/bugs/show_bug.cgi?id=469140
					$gh_author_email = $this->ldap_client->getMailFromGithubLogin($gh_author->login);

					if($this->isCommitterOfRepo($gh_author_email, $pullRequestJSON->repository->full_name)) {
						array_push($this->users['validCommitter'], $author->email);
					}
					else {
						# Not a committer on the project -- check CLA and Signed-off-by
						$this->evaluateCLA($author, $gh_author);
						$this->evaluateSignature($commitsJSON[$i]->commit, $gh_author);
					}
				}
			}

			$pr_id = "PULL REQUEST:" . $pullRequestJSON->repository->full_name . ":" . $pullRequestJSON->number . " Key: " . $statusDetailKey . " ";
			// if there is no login, the user given in the git commit is not a valid github user
			$this->logger->info($pr_id . 'listed committer in commit: '.
					$commitsJSON[$i]->commit->author->name .
					' <'.$commitsJSON[$i]->commit->author->email.'>');

			//Signed-off-by is found in the commit message
			$this->logger->info($pr_id . 'commit message: '.$commitsJSON[$i]->commit->message);
		}

		if ((count($this->users['invalidSignedOff']) +
			count($this->users['unknownSignedOff']) +
			count($this->users['invalidCLA']) == 0) &&
				(count($this->users['validCommitter']) +
				count($this->users['validCLA']) +
				count($this->users['validSignedOff']) > 0)) {
			$rValue = true;
		}
		return $rValue;
	}

	function getCommitterLoginFromEMail($committerEmail) {
		$member->login = $this->ldap_client->getGithubLoginFromMail($user);
	}
	
	function getCLAStatusFromEMail($committerEmail) {
		return $this->ldap_client->isMemberOfGroup($committerEmail, "eclipsecla");
	}
	function getCLAStatusFromGHLogin($ghLogin) {
		$committerEmail = $this->ldap_client->getMailFromGithubLogin($ghLogin);
		return $this->ldap_client->isMemberOfGroup($committerEmail, "eclipsecla");
	}
	public function getUsers() {
		return $this->users;
	}
	

	/** Evaluate CLA status of committer
	 * 
	 * @param Obj $committer
	 * @param Obj $gh_committer
	 */
	private function evaluateCLA($committer, $gh_committer) {
		$email = $committer->email;
		$gh_login = $gh_committer->login; // should perhaps use the numeric ID instead
	
		$eclipse_cla_status = $this->getCLAStatusFromEMail($email);
		if ($eclipse_cla_status) {
			array_push($this->users['validCLA'], $email);
		} else {
			$eclipse_cla_status = $this->getCLAStatusFromGHLogin($gh_login);
			if ($eclipse_cla_status) {
				array_push($this->users['validCLA'], $gh_login);
			}
			else {
				array_push($this->users['invalidCLA'], $email);
			}
		}
	}
	
	/**
	* Function GithubClient::composeStatusMessage
	* @desc build the status description including specific users and faults
	* @desc messages come from config/projects.php
	*/
	public function composeStatusMessage() {
		global $messages;
		$parts = array();
	
		//list problems with corresponding users
		if (count($this->users['invalidCLA'])) {
			array_push($parts, $messages['badCLAs'] . " " . implode(', ', $this->users['invalidCLA']));
		}
		if (count($this->users['invalidSignedOff'])) {
			array_push($parts, $messages['badSignatures'] . " " . implode(', ', $this->users['invalidSignedOff']));
		}
		if (count($this->users['unknownSignedOff'])) {
			array_push($parts, $messages['badSignatures'] . " " . implode(', ', $this->users['unknownSignedOff']));
		}
		//add a summary message
		if (count($parts)) {
			array_unshift($parts, $messages['failure']);
		} elseif (count($this->users['validCommitter']) &&
				count($this->users['validCLA']) &&
				count($this->users['validSignedOff'])) {
			array_unshift($parts, $messages['success']);
		} else {
			array_unshift($parts, $messages['unknown']);
		}
		return implode("\n", $parts);
	}


	/**
	* Function evaluateSignature
	* @param object commit
	* @desc evaluate signature match in Signed-off-by against committer
	* @desc Signed-off-by is found in the commit message
	*/
	private function evaluateSignature($commit, $gh_committer) {
		$email = strtolower($commit->committer->email);
		$gh_login = $gh_committer->login;
	
		//look Signed-off-by pattern:
		$pattern = '/Signed-off-by:(.*)<(.*@.*)>$/m';
		//signature is only valid if it matches committer
		if (preg_match($pattern, $commit->message, $matches)) {
			if (strtolower($matches[2]) == $email) {
				array_push($this->users['validSignedOff'], $email);
			}
			elseif(trim($matches[1]) == $gh_login) {
				array_push($this->users['validSignedOff'], $gh_login);
			}
			else {
				array_push($this->users['invalidSignedOff'], $gh_login);
			}
		} else {
			//no Signed-off-by at all
			array_push($this->users['unknownSignedOff'], $email);
		}
	}
	

	/** Get Team object based on its name (eclipse-birt)
	 * 
	 * @param string $teamName
	 * @return Team object, or false if not found
	 * @author droy
	 * @since 2015-05-06
	 */
	public function getTeamByName($teamName) {
		$rValue = false;
		foreach ($this->teamList as $team) {
			if($team->getTeamName() == $teamName) {
				$rValue = $team;
				break;
			}
		}
		
		return $rValue;
	}

	/** Get team object based on the repo name (eclipse/birt)
	 * 
	 * @param string $repoName
	 * @return Team object, or false if not found
	 * @author droy
	 * @since 2015-05-06
	 */
	public function getTeamByRepoName($repoName) {
		$rValue = false;
		foreach ($this->teamList as $team) {
			# We are looking for $repoName (eclipse/birt) within the list of repo URLs (https://github.com/eclipse/birt).
			foreach($team->getRepoList() as $repo) {
				if($this->strEndsWith($repo, $repoName)) {
					$rValue = $team;
					break;
				}
			}
		}
		return $rValue;
	}
	
	/** Is Committer in a team
	 * 
	 * @param string $committerEMail
	 * @param string $teamName
	 * @return boolean
	 * @author droy
	 * @since 2015-05-06
	 */
	public function isCommitterInTeam($committerEMail, $teamName) {
		$rValue = false;
		$team = $this->getTeamByName($teamName);

		# Althought the email RFC states that email addresses
		# are case-sensitive let's not treat them as such
		$committerEMail = strtolower($committerEMail);

		if($team !== FALSE) {
			foreach ($team->getCommitterList() as $committer) {
				if(strtolower($committer) == $committerEMail) {
					$rValue = true;
					break;
				}
			}
		}
		return $rValue;
	}
	
	/** Is committer of a repo
	 * 
	 * @param string $committerEMail
	 * @param string $repoName
	 * @return boolean
	 * @author droy
	 * @since 2015-05-06
	 */
	public function isCommitterOfRepo($committerEMail, $repoName) {
		$rValue = false;

		# Althought the email RFC states that email addresses 
		# are case-sensitive let's not treat them as such
		$committerEMail = strtolower($committerEMail);

		$this->logger->info("Checking $repoName for [$committerEMail]");
		$team = $this->getTeamByRepoName($repoName);
		if($team !== FALSE) {
			foreach ($team->getCommitterList() as $committer) {
				if(strtolower($committer) == $committerEMail) {
					$rValue = true;
					break;
				}
			}
		}
		return $rValue;
	}
	
	/**
	 * Return list of teams (array of Team objects)
	 * @return multitype:
	 */
	public function getTeamList() {
		return $this->teamList;
	}
}


/*
PMI json sample output:

stdClass Object
 (
   [eclipse-birt] => stdClass Object
      (
            [repos] => Array
               (
                   [0] => https://github.com/eclipse/birt
               )
            [users] => Array
               (
                   [0] => someone@someone.com
               )
      )
)
*/
?>
