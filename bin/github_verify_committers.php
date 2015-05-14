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
 * command line project sync for gitub repo teams
 *
 *
 * @desc: checks for or creates a github team for each Eclipse project, then
 *        checks or attaches the github repo to the team and verifies that team
 *        members list matches Eclipse. Github users are added/removed as needed.
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
include_once('../lib/logger.php');
include_once('../lib/organization/organization.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);
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

global $github_organization;

//search for command line passed github organization to monitor
$options = getopt("dh", array('dry-run', 'help'));
if (isset($options['help']) || isset($options['h'])) {
	exit('usage: php '.__FILE__."\n\t-d (dry-run - no github changes)\n\t-h (help - this message)\n");
}
if (isset($options['dry-run']) || isset($options['d'])) {
	$dry_run = true;
	echo("Dry run mode active -- no changes will be made to Github.\n");
}
if ($github_organization == '') {
	exit("You must provide a Github organization as a target for committer verification in the configuration file.\n");
}

# create an organization for org-specific rules and functions
$org_forge = OrganizationFactory::build($github_organization, true);
$org_github = OrganizationFactory::build("github", true); # debug on or off

foreach($org_forge->getTeamList() as $org_forge_team) {
	echo "Looping through team [" . $org_forge_team->getTeamName() . "]\n";

	$org_github_team = $org_github->getTeamByName($org_forge_team->getTeamName());
	if($org_github_team === FALSE) {
		echo "Missing Github team: [" . $org_forge_team->getTeamName() . "]\n";

		# copy team, but clear GitHub committer list since it doesn't exist
		$org_github_team = $org_forge_team;
		$org_github_team->clearCommitterList();
		if(!$dry_run) {
			$org_github->addTeam($org_github_team);
		}
	}
	
	foreach($org_forge_team->getRepoList() as $repoUrl) {
		$repoName = $org_github->getRepoName($repoUrl);
		echo "    Looping through repo [" . $repoName . "]\n";
		if(!$org_github->teamHasRepoUrl($org_github_team, $repoUrl)) {
			echo "    Missing Github repo: [$repoUrl]\n";

			$url = implode('/', array(GITHUB_ENDPOINT_URL, 'teams',	$org_github_team->getTeamID(), 'repos', $repoName));
			if(!$dry_run) {
				$repoCreated = $client->put($url);
				print_r($repoCreated);
			}
			# TODO: handle repo creation error
		}
	}

	//compare membership lists
	$githubResult = $org_github_team->getCommitterList();
	$eclipseResult = $org_forge_team->getCommitterList();


	/* echo "Github members: \n";
	print_r($githubResult);
	echo "Eclipse members: \n";
	print_r($eclipseResult);  
	*/

	echo "\n[Info] checking $repoName...\n";
	$toBeRemoved = compare($githubResult, $eclipseResult);
	$toBeAdded = compare($eclipseResult, $githubResult);

	# report discrepancies
	if (count($toBeRemoved)) {
		echo "\n[Info] ";
		echo (isset($messages['missing_team_members']) ? $messages['missing_team_members'] :
		'Github repo team members missing from ' . $github_organization . ' project (to be removed): ');  
		echo "\n";
	}
	foreach ($toBeRemoved as $email) {
		$gh_login = $org_github->getGithubLoginFromEMail($email);
		if($gh_login) {
			echo ("[Info] removing $email from team: " . $org_github_team->getTeamName() . " [" . $org_github_team->getTeamID() . "]: ");
			if (!$dry_run) {
				$removeResult = removeGithubTeamMember($gh_login, $org_github_team->getTeamID());
				echo isset($removeResult->http_code) ? $removeResult->http_code : (isset($removeResult->state) ? $removeResult->state : $removeResult->message);
			}
			echo "\n";
		}
		else {
			echo ("[Info] cannot remove $email from team: " . $org_github_team->getTeamName() . " - no Github Login!\n");
		}
	}

	if (count($toBeAdded)) {
		echo "\n[Info] ";
		echo (isset($messages['missing_members']) ? $messages['missing_members'] :
		$github_organization . ' project members missing from Github repo team (to be added): ');
		echo "\n";

		foreach ($toBeAdded as $email) {
			$gh_login = $org_github->getGithubLoginFromEMail($email);
			if($gh_login) {
				echo ("[Info] inviting $email ($gh_login) to team: " . $org_github_team->getTeamName() . " [" . $org_github_team->getTeamID() . "]: ");
				if (!$dry_run) {
					$addResult = addGithubTeamMember($gh_login, $org_github_team->getTeamID());
					echo isset($addResult->http_code) ? $addResult->http_code : (isset($addResult->state) ? $addResult->state : $addResult->message);
					# TODO: warn user that they have a pending invitation?
					# Lots of people seem to miss the GitHub invitation
					# https://developer.github.com/v3/orgs/teams/#get-team-membership
				}
				echo "\n";
			}
			else {
				echo ("[Info] cannot add $email to team: " . $org_github_team->getTeamName() . " - no Github Login attached to $github_organization account!\n");
			}
		}
	}
	echo "\n";
}


/* remove an unknown github collaborator from the team */
function removeGithubTeamMember($login, $teamId) {
	global $client, $logger;

	$url = implode('/', array(
		GITHUB_ENDPOINT_URL,
		'teams',
		$teamId,
		'members',
		$login
	));
	return $client->delete($url);
}

/* add a github user to a team */
function addGithubTeamMember($login, $teamId) {
	global $client, $logger;

	$url = implode('/', array(
		GITHUB_ENDPOINT_URL,
		'teams',
		$teamId,
		'memberships',
		$login
	));
	return $client->put($url);
}

/* check for discrepancies between services */
function compare($groupA, $groupB) {
	return array_udiff($groupA, $groupB, 'compare_members');
}

function compare_members($a, $b) {
	# If we use LDAP, only consider the login... otherwise, hope the GitHub 
	# users have exposed their email address.
	if (defined('LDAP_HOST')) {
		# return (strcasecmp($a->login, $b->login));
		return (strcasecmp($a, $b));
	}
	else {
		return (strcasecmp($a->email, $b->email));
	}
}

?>