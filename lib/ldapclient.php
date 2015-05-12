<?php
/*******************************************************************************
* Copyright (c) 2012-2015 Eclipse Foundation and others.
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
	
	/**
	 * Get Email address given a GitHub name
	 * @param string $gh
	 * @return string email, or false 
	 */
	public function getMailFromGithubID($gh) {
		$ds = $this->connect();
		if ($ds) {
			#  Perform a lookup.
			$sr = ldap_search($ds, $this->dn, "(employeeType=GITHUB:$gh)", array("mail"));
			$info = ldap_get_entries($ds, $sr);
			if($info["count"] > 0) {
				if(isset($info[0]["mail"])) {
					return $info[0]["mail"][0];
				}
			}
		}
		return FALSE;
	}
	
	/** Get uid from an email address
	 * 
	 * @param string $_mail
	 * @return mixed
	 * @author droy
	 * @since 2015-05-06
	 */
	private function getUIDFromMail($_mail) {
		$ds = $this->connect();
		if ($ds) {
			if(preg_match("/@/", $_mail)) {
				#  Perform a lookup.
				$sr = ldap_search($ds, $this->dn, "(mail=$_mail)", array("uid"));
				$info = ldap_get_entries($ds, $sr);
				if($info["count"] > 0) {
					return $info[0]["uid"][0];
				}
			}
		}
		return FALSE;
	}
	
	/** Get dn from an email address
	 *
	 * @param string $_mail
	 * @return mixed
	 * @author droy
	 * @since 2015-05-06
	 */
	private function getDNFromMail($_mail) {
		$ds = $this->connect();
		if ($ds) {
			if(preg_match("/@/", $_mail)) {
				#  Perform a lookup.
				$sr = ldap_search($ds, $this->dn, "(mail=$_mail)");
				$info = ldap_get_entries($ds, $sr);
				if($info["count"] > 0) {
					return $info[0]["dn"];
				}
			}
		}
		return FALSE;
	}
	
	/** Is Member of a group
	 * 
	 * @param string $mail
	 * @param string $group
	 * @return boolean
	 */
	public function isMemberOfGroup($mail, $group) {
		$ds = $this->connect();
		$rValue = FALSE;
		if ($ds) {
			if(preg_match("/@/", $mail)) {
				$dn = $this->getDNFromMail($mail);
				
				if($dn !== FALSE) {
					#  Perform a lookup.
					$group_dn = "cn=" . $group . ",ou=group," . $this->dn;

					$filter = "(member=" . $dn . ")";

					$sr = ldap_search($ds, $group_dn, $filter);
					$info = ldap_get_entries($ds, $sr);
					if ($info['count']) {
						$rValue = TRUE;
					}
				}
			}
			ldap_close($ds);
		}
		return $rValue;
	}
}
?>