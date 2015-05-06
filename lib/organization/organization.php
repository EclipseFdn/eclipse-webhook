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
include_once('../lib/mysql_store.php');
include_once('../lib/json_store.php');
include_once('../lib/status_store.php');
include_once('../lib/logger.php');


class OrganizationFactory {
	public static function build($organization) {
		$class = ucfirst($organization);
		$classfile = '../lib/organization/' . $organization . '.php';
		if(file_exists($classfile)) {
			include ($classfile);
		}
		if(class_exists($organization)) {
			return new $class();
		}
		else {
			throw new Exception("Invalid organization: " . $organization);
		}
	}
}

class Organization {
	private $teamList;
	private $logger;
}
?>