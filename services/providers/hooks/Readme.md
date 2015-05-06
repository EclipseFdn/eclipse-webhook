Site-specific hooks
===================
This directory is designed to contain site-specific utilities called by the generic hook functionality implemented by providers.

If a file with the format ``<event type>_<action>.php`` is present, it will be automatically included and a function named ``<event type>_<action>_hook(<payload>)`` is called. Other files are ignored.
  
As an example, when a pull request 'opened' action is received from github, the file ``pull_request_opened.php`` would be opened and a function named pull_request_opened_hook would be called with the json payload object as the only argument.