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
 * command line webhook installer for gitub repos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Pagination";

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../services/providers/github.php');

$client = new GithubClient(GITHUB_ENDPOINT_URL);

$url = implode('/', array(
				GITHUB_ENDPOINT_URL,
				'orgs',
				$github_organization,
				'teams'
		));
$json = $client->getd($url);
print_r($json);
echo "\nJSON objects: " . count($json);
?>