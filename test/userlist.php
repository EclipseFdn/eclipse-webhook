<?php
switch ($_REQUEST['p']) {
  case 'pulls':
  echo file_get_contents('./example_userlist.json');
  break;
  default:
  echo file_get_contents('./example_userlist2.json');
  break;
};
?>