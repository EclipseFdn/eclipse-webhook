External git repository validation web hook
==============
Provides a web service which can be registered with hosted repositories
to perform post-processing triggered by various repo events.

* validation of pull request committers against a CLA service
* validation of Signed-off-by footers in commit messages
* pull request status updates and maintenance

Quick Start
--------------
1. Expose the ```services``` directory via your web server
1. Duplicate the configuration file: ```cp config/projects.php config/projects_local.php``` and customize the target.
 * in your Github account settings, click 'Applications' and generate a personal access token. Copy the token into the GITHUB_TOKEN define.
 * customize the validation url so it points at your service.
 * add your organization name under ```$github_organization```
 * optionally add repository names under ```$github_projects```. If you leave this empty, all of the organization's repos will be monitored.
 * customize the service url to point at your domain.
1. run ```bin/github_install_hooks.php``` from a shell to install hooks pointing to this service endpoint (i.e. github_service.php)
1. Test the service by forking an organization repo, making a change and creating a pull request against the original.
 * Results of verification will show up in the pull request comments.
 * Follow the details link in the comment for complete information.
 * Access github_api_limit.php to get a picture of your Github API use and reset time.

How it works
------------
A web hook is registered with the external Git host pointing back to this service. On each pull request creation or update event, the web service is notified and, using the api, walks the list of committers, checking their credentials. If validation fails, the pull request is modified to show that there is a problem and provide links and details concerning how the issues can be addressed.
