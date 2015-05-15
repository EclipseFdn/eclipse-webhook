<?php
/*******************************************************************************
* Copyright (c) 2015 Eclipse Foundation and others.
* All rights reserved. This program and the accompanying materials
* are made available under the terms of the Eclipse Public License v1.0
* which accompanies this distribution, and is available at
* http://www.eclipse.org/legal/epl-v10.html
*
* Contributors:
*    Denis Roy (Eclipse Foundation)- initial API and implementation
*******************************************************************************/

# Basic functions for an Organization
if (file_exists('../config/projects_local.php')) {
	include_once('../config/projects_local.php');
} else {
	include_once('../config/projects.php');
}
include_once('../lib/restclient.php');
include_once('../lib/logger.php');
include_once('../lib/organization/team.php');


class OrganizationFactory {
	public static function build($organization, $debug=FALSE) {
		$class = ucfirst($organization);
		$classfile = '../lib/organization/' . $organization . '.php';
		if(file_exists($classfile)) {
			include ($classfile);
		}
		if(class_exists($organization)) {
			return new $class($debug);
		}
		else {
			throw new Exception("Invalid organization: " . $organization);
		}
	}
}

class Organization {
	private $teamList;
	private $logger;
	private $debug;
	
	# buckets used for classifying committers who pass/fail validation
	private $users = array(
		// 'validCommitter' => array(),
		// 'validCLA' => array(),
		// 'invalidCLA' => array(),
	);
	
	function __construct($debug) {
		$this->debug = $debug;
	}

	/**
	 * Define organization-specific rules 
	 * @param Obj $pullRequestJSON represented as an object
	 * @param Obj $commitsJSON represented as an object
	 * @param string $statusDetailKey unique identifier for logging
	 * @return boolean
	 */
	public function validatePullRequest($pullRequestJSON, $commitsJSON, $statusDetailKey) {
		return true;
	}
	
	/**
	 * Function composeStatusMessage
	 * @desc build the status description including specific users and faults
	 * @desc messages come from config/projects.php
	 */
	public function composeStatusMessage() {
		return "All is well.";
	}
	
	
	/**
	 * Does a string(haystack) end with a string (needle) ? 
	 * @param string $haystack String to search in
	 * @param string $needle String to search for
	 * @return boolean
	 */
	public function strEndsWith($haystack, $needle) {
		return $needle != "" && (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}

	public function debug() {
		echo "Calling Debug. Dumping object contents of object.\n";
		print_r($this);
	}
}
?>