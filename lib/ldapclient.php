<?php
/*******************************************************************************
 * Copyright (c) 2012-2014 Eclipse Foundation and others.
* All rights reserved. This program and the accompanying materials
* are made available under the terms of the Eclipse Public License v1.0
* which accompanies this distribution, and is available at
* http://www.eclipse.org/legal/epl-v10.html
*
* Contributors:
*    Denis Roy (Eclipse Foundation)- initial API and implementation
*******************************************************************************/

class LDAPClient {
	private $host, $dn;

	function __construct($host, $dn) {
		$this->host = $host;
		$this->dn = $dn;
	}
	
	private function connect() {
		$ds = ldap_connect($this->host);
		ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		
		return $ds;
	}
	 
	#This function performs a look up of a given email address
	public function getGithubIDFromMail($mail) {
		$ds = $this->connect();
		if ($ds) {
			if(preg_match("/@/", $mail)) {
				#  Perform a lookup.
				$sr = ldap_search($ds, $this->dn, "(mail=$mail)", array("employeeType"));
				$info = ldap_get_entries($ds, $sr);
				if($info["count"] > 0) {
					if(isset($info[0]["employeetype"])) {
						foreach ($info[0]["employeetype"] as $et) {
							# $et contains GITHIB:id  or BITBUCKET:id
							$id = explode(":", $et);
							if($id[0] == "GITHUB") {
								return $id[1];
								last;
							}
						}
					}
				}
			}
		}
		return FALSE;
	}
}
?>