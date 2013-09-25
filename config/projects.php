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
$github_hook_add_events = array('pull_request', 'status');
/*
 * an array of github repo events to ignore - usually just 'push' as that is the only default event
 */
$github_hook_remove_events = array('push');

?>
