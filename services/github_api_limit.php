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
*******************************************************************************/


/**
* Github rate limit checker - provides feedback on request limit and reset time for Github API
*/

if (file_exists('../config/projects_local.php')) {
  include_once('../config/projects_local.php');
} else {
  include_once('../config/projects.php');
}
if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to determine api rate limit status.');
}
include_once('./providers/github.php');

$client = new GitHubClient(GITHUB_ENDPOINT_URL);
$result = json_decode($client->getraw($client->buildURL(array("rate_limit")), true));
date_default_timezone_set('UTC');

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
  "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<meta http-equiv="refresh" content="15">

<style>
#content-box {
  border-style: dashed;
  font-family: sans-serif;
  margin: 10px;
}

#content-box dd {
  font-size: 24pt;
}
#content-box dt {
  font-size: 36pt;
  margin: 10px;
}
</style>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Github API rate limit status</title>
</head>
<body>
<div id="content-box" title="Status">
  <dl>
    <dd>
      <span>Core API hourly request limit:</span>
    </dd>
    <dt>
      <span><?php echo($result->resources->core->limit)?></span>
    </dt>
    <dd>
      <span>remaining:</span>
    </dd>
    <dt>
      <span><?php echo($result->resources->core->remaining)?></span>
    </dt>
    <dd>
      <span>next reset:</span>
    </dd>
    <dt>
      <span><?php if ($result->resources->core->limit == $result->resources->core->remaining) {echo 'No api calls made in the last hour';} else { echo(date("D M j G:i:s T", $result->resources->core->reset) . " (".date("i\ms\s", $result->resources->core->reset - time()). " from now)");}?></span>
    </dt>
  </dl>
</div>
<div id="content-box" title="Search API Status">
  <dl>
    <dd>
      <span>Search API 60 second request limit:</span>
    </dd>
    <dt>
      <span><?php echo($result->resources->search->limit)?></span>
    </dt>
    <dd>
      <span>remaining:</span>
    </dd>
    <dt>
      <span><?php echo($result->resources->search->remaining)?></span>
    </dt>
    <dd>
      <span>next reset:</span>
    </dd>
    <dt>
      <span><?php if ($result->resources->search->limit == $result->resources->search->remaining) {echo 'No search api calls made in the last minute';} else { echo(date("G:i:s T", $result->resources->search->reset) . " (".date("s\s", $result->resources->search->reset - time()). " from now)");}?></span>
    </dt>
  </dl>
</div>
</body>
</html>
