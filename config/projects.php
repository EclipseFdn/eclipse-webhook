<?php
/*
 * Configuration information for github organization.
 */

/*
* github api location
*/
define('GITHUB_ENDPOINT_URL', 'https://api.github.com');
/*
* service endpoint location
*/
define('WEBHOOK_SERVICE_URL', 'http://example.com/eclipse-webhook/services/github_service.php');
/*
* service assistance location, link provided when validation fails
*/
define('VALIDATION_HELP_URL', 'http://example.com/cla_policy.php');
/*
* your organization name (e.g. 'eclipse')
*/
$github_organization = '';
/*
 * an array of github repos to monitor - repo name only
 */
$github_projects = array();
/*
 * an array of github repo events to monitor
 */
$github_hook_add_events = array('pull_request','status');
/*
 * an array of github repo events to ignore - usually just 'push' as that is the only default event
 */
$github_hook_remove_events = array('push');
/*
 * an array of messages used in composing pull_request status comments
 */
$messages = array(
  'success' => 'All committers passed Eclipse CLA and Signed-off-by validation.',
  'failure' => 'The pull request did not pass Eclipse validation.',
  'badCLA' => 'The following user does not have a valid CLA: ',
  'badCLAs' => 'The following users do not have valid CLAs: ',
  'badSignature' => 'There is a problem with the Signed-off-by footer for ',
  'badSignatures' => 'The following users have invalid Signed-off-by footers: ',
  'unknownUser' => 'The following user does not have an Eclipse account: ',
  'unknownUsers' => 'The following users do not have Eclipse accounts: ',
);
?>
