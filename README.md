External git repository validation web hook
==============
Provides a web service which can be registered with hosted repositories
to perform post-processing triggered by various repo events.

* validation of pull request committers against a CLA service
* validation of Signed-off-by footers in commit messages
* pull request status updates and maintenance

Quick Start
--------------
1. add Github repository names to ```config/projects.php``` for each repo in the organization.
2. run ```bin/github_install_hooks.php``` from a shell to install hooks pointing to this service (e.g. github_service.php)
3. Expose the ```services``` directory via your web server and set up these environment variables in your apache config for your organization:
**TOKEN - personal access token generated for the organization owner
**CLA_SERVICE - service endpoint for testing CLA status (e.g. http://projects.eclipse.org/api/cla/validate/)
3. Test the service by forking an organization repo, making a change and creating a pull request against the original.
**Results of verification will show up in the pull request comments.
**You can check your apache error log for validation information.

How it works
------------
A web hook is registered with the external git host pointing back to this service. On each pull request creation or update event, the web service is notified and, using the api, walks the list of committers, checking their credentials. If validation fails, the pull request is modified to show that there is a problem and provide links and details concerning how the issues can be addressed.
