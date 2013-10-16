<?php
/**
* Github rate limit checker - provides feedback on request limit and reset time for Github API
*/
if (!isset($_SERVER['TOKEN'])) {
  exit('You must provide a Github access token environment variable to determine api rate limit status.');
}

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include_once('./providers/github.php');

$client = new GitHubClient("https://api.github.com");
$result = $client->get($client->buildURL(array("rate_limit")));

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
      <span>request limit:</span>
    </dd>
    <dt>
      <span><?php echo($result->rate->limit)?></span>
    </dt>
    <dd>
      <span>remaining:</span>
    </dd>
    <dt>
      <span><?php echo($result->rate->remaining)?></span>
    </dt>
    <dd>
      <span>next reset:</span>
    </dd>
    <dt>
      <span><?php echo(date("D M j G:i:s T", $result->rate->reset) . " (".date("i\ms\s", $result->rate->reset - time()))?> from now)</span>
    </dt>
  </dl>
</div>
</body>
</html>
