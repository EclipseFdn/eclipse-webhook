<?php
/*
 * Configuration information for github organization.
 */

/*
 * github api location
 */
define('GITHUB_ENDPOINT_URL', 'https://api.github.com');

/*
 * a github personal access token used to make api requests
 */
define('GITHUB_TOKEN', 'please_provide_a_valid_github_personal_access_token');

/*
 * service endpoint location
 */
define('WEBHOOK_SERVICE_URL', 'http://example.com/eclipse-webhook/services/github_service.php');

/*
 * URL for the CLA validation service
 */
define('CLA_SERVICE', 'http://example.com/api/cla/validate/');

/*
 * default link provided if no validation details can be generated
 * should link to general help information on cla policy.
 */
define('VALIDATION_HELP_URL', 'http://example.com/cla_policy.php');

/*
 * default email which gets CCed on validation failures
 */
define('ADMIN_EMAIL', 'noreply@example.com');

/*
 * location for transient files needed for file-based details store
 * N.B. this is only used by the file-based json_store
 */
define('TMP_FILE_LOCATION', '/tmp');

/*
 * your github organization name (e.g. 'eclipse')
 */
$github_organization = '';

/*
 * an array of github repos to monitor - repo name only
 * N.B. if array is empty, all repos in the organization will be monitored.
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
  'unknown' => 'There was a problem with validation. Contact support.',
  'badCLA' => 'The following user does not have a valid CLA: ',
  'badCLAs' => 'The following users do not have valid CLAs: ',
  'badSignature' => 'There is a problem with the Signed-off-by footer for ',
  'badSignatures' => 'The following users have invalid Signed-off-by footers: ',
  'unknownUser' => 'The following user does not have an Eclipse account: ',
  'unknownUsers' => 'The following users do not have Eclipse accounts: ',
);
?>
