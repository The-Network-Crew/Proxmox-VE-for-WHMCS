<?php

/*

Proxmox VE APIv2 (PVE2) Client - PHP Class
https://github.com/CpuID/pve2-api-php-client/

Copyright (c) Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

class PVE2_Exception extends RuntimeException {}

class PVE2_API {
	protected $hostname;
	protected $username;
	protected $realm;
	protected $password;
	protected $port;
	protected $verify_ssl;

	protected $login_ticket = null;
	protected $login_ticket_timestamp = null;
	protected $cluster_node_list = null;

	public function __construct ($hostname, $username, $realm, $password, $port = 8006, $verify_ssl = false) {
		if (empty($hostname) || empty($username) || empty($realm) || empty($password) || empty($port)) {
			throw new PVE2_Exception("PVE2 API: Hostname/Username/Realm/Password/Port required for PVE2_API object constructor.", 1);
		}
		// Check hostname resolves.
		if (gethostbyname($hostname) == $hostname && !filter_var($hostname, FILTER_VALIDATE_IP)) {
			throw new PVE2_Exception("PVE2 API: Cannot resolve {$hostname}.", 2);
		}
		// Check port is between 1 and 65535.
		if (!filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
			throw new PVE2_Exception("PVE2 API: Port must be an integer between 1 and 65535.", 6);
		}
		// Check that verify_ssl is boolean.
		if (!is_bool($verify_ssl)) {
			throw new PVE2_Exception("PVE2 API: verify_ssl must be boolean.", 7);
		}

		$this->hostname   = $hostname;
		$this->username   = $username;
		$this->realm      = $realm;
		$this->password   = $password;
		$this->port       = $port;
		$this->verify_ssl = $verify_ssl;
	}

	/*
	 * bool login ()
	 * Performs login to PVE Server using JSON API, and obtains Access Ticket.
	 */
	public function login () {
		// Prepare login variables.
		$login_postfields = array();
		$login_postfields['username'] = $this->username;
		$login_postfields['password'] = $this->password;
		$login_postfields['realm'] = $this->realm;

		$login_postfields_string = http_build_query($login_postfields);
		unset($login_postfields);

		// Perform login request.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json/access/ticket");
		curl_setopt($prox_ch, CURLOPT_POST, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $login_postfields_string);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl);

		$login_ticket = curl_exec($prox_ch);
		$login_request_info = curl_getinfo($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);
		unset($login_postfields_string);

		if (!$login_ticket) {
			// SSL negotiation failed or connection timed out
			$this->login_ticket_timestamp = null;
			return false;
		}

		$login_ticket_data = json_decode($login_ticket, true);
		if ($login_ticket_data == null || $login_ticket_data['data'] == null) {
			// Login failed.
			// Just to be safe, set this to null again.
			$this->login_ticket_timestamp = null;
			if ($login_request_info['ssl_verify_result'] == 1) {
				throw new PVE2_Exception("PVE2 API: Invalid SSL cert on {$this->hostname} - check that the hostname is correct, and that it appears in the server certificate's SAN list. Alternatively set the verify_ssl flag to false if you are using internal self-signed certs (ensure you are aware of the security risks before doing so).", 4);
			}
			return false;
		} else {
			// Login success.
			$this->login_ticket = $login_ticket_data['data'];
			// We store a UNIX timestamp of when the ticket was generated here,
			// so we can identify when we need a new one expiration-wise later
			// on...
			$this->login_ticket_timestamp = time();
			$this->reload_node_list();
			return true;
		}
	}

	# Sets the PVEAuthCookie
	# Attetion, after using this the user is logged into the web interface aswell!
	# Use with care, and DO NOT use with root, it may harm your system
	public function setCookie() {
		if (!$this->check_login_ticket()) {
			throw new PVE2_Exception("PVE2 API: Not logged into Proxmox. No login Access Ticket found or Ticket expired.", 3);
		}

		setrawcookie("PVEAuthCookie", $this->login_ticket['ticket'], 0, "/");
	}

	# Gets the PVE Access Ticket
	public function getTicket() {
		if ($this->login_ticket['ticket']) {
			return $this->login_ticket['ticket'];
		} else {
			return false;
		}
	}

	/*
	 * bool check_login_ticket ()
	 * Checks if the login ticket is valid still, returns false if not.
	 * Method of checking is purely by age of ticket right now...
	 */
	protected function check_login_ticket () {
		if ($this->login_ticket == null) {
			// Just to be safe, set this to null again.
			$this->login_ticket_timestamp = null;
			return false;
		}
		if ($this->login_ticket_timestamp >= (time() + 7200)) {
			// Reset login ticket object values.
			$this->login_ticket = null;
			$this->login_ticket_timestamp = null;
			return false;
		} else {
			return true;
		}
	}

	/*
	 * object action (string action_path, string http_method[, array put_post_parameters])
	 * This method is responsible for the general cURL requests to the JSON API,
	 * and sits behind the abstraction layer methods get/put/post/delete etc.
	 */
	private function action ($action_path, $http_method, $put_post_parameters = null) {
		// Check if we have a prefixed / on the path, if not add one.
		if (substr($action_path, 0, 1) != "/") {
			$action_path = "/" . $action_path;
		}

		if (!$this->check_login_ticket()) {
			throw new PVE2_Exception("PVE2 API: Not logged into Proxmox. No login Access Ticket found or Ticket expired.", 3);
		}

		// Prepare cURL resource.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json{$action_path}");

		$put_post_http_headers = array();
		$put_post_http_headers[] = "CSRFPreventionToken: {$this->login_ticket['CSRFPreventionToken']}";
		// Lets decide what type of action we are taking...
		switch ($http_method) {
			case "GET":
				// Nothing extra to do.
				break;
			case "PUT":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "PUT");

				// Set "POST" data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "POST":
				curl_setopt($prox_ch, CURLOPT_POST, true);

				// Set POST data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "DELETE":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				// No "POST" data required, the delete destination is specified in the URL.

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			default:
				throw new PVE2_Exception("PVE2 API: Error - Invalid HTTP Method specified.", 5);
				return false;
		}

		curl_setopt($prox_ch, CURLOPT_HEADER, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_COOKIE, "PVEAuthCookie=" . $this->login_ticket['ticket']);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, false);

		$action_response = curl_exec($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);

		$split_action_response = explode("\r\n\r\n", $action_response, 2);
		$header_response = $split_action_response[0];
		$body_response = $split_action_response[1];
		$action_response_array = json_decode($body_response, true);

		$action_response_export = var_export($action_response_array, true);
		// error_log("----------------------------------------------\n" .
		//	"FULL RESPONSE:\n\n{$action_response}\n\nEND FULL RESPONSE\n\n" .
		//	"Headers:\n\n{$header_response}\n\nEnd Headers\n\n" .
		//	"Data:\n\n{$body_response}\n\nEnd Data\n\n" .
		//	"RESPONSE ARRAY:\n\n{$action_response_export}\n\nEND RESPONSE ARRAY\n" .
		//	"----------------------------------------------");

		unset($action_response);
		unset($action_response_export);

		// Parse response, confirm HTTP response code etc.
		$split_headers = explode("\r\n", $header_response);
		if (substr($split_headers[0], 0, 9) == "HTTP/1.1 ") {
			$split_http_response_line = explode(" ", $split_headers[0]);
			if ($split_http_response_line[1] == "200") {
				if ($http_method == "PUT") {
					return true;
				} else {
					return $action_response_array['data'];
				}
			} else {
				throw new PVE2_Exception("PVE2 API: This API Request Failed.\n" .
					"HTTP CODE: {$split_http_response_line[1]},\n" .
					"HTTP ERROR: {$split_headers[0]},\n" . 
					"REPLY INFO: {$body_response}");
				return false;
			}
		} else {
			throw new PVE2_Exception("PVE2 API: Error - Invalid HTTP Response.\n" . var_export($split_headers, true));
			return false;
		}

		if (!empty($action_response_array['data'])) {
			return $action_response_array['data'];
		} else {
			throw new PVE2_Exception("PVE2 API: \$action_response_array['data'] is empty. Returning false.\n" .
				var_export($action_response_array['data'], true));
			return false;
		}
	}

	/*
	 * array reload_node_list ()
	 * Returns the list of node names as provided by /api2/json/nodes.
	 * We need this for future get/post/put/delete calls.
	 * ie. $this->get("nodes/XXX/status"); where XXX is one of the values from this return array.
	 */
	public function reload_node_list () {
		$node_list = $this->get("/nodes");
		if (count($node_list) > 0) {
			$nodes_array = array();
			foreach ($node_list as $node) {
				$nodes_array[] = $node['node'];
			}
			$this->cluster_node_list = $nodes_array;
			return true;
		} else {
			error_log("PVE2 API: Empty list of Nodes returned in this Cluster.");
			return false;
		}
	}

	/*
	 * array get_node_list ()
	 *
	 */
	public function get_node_list () {
		// We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->cluster_node_list;
	}

	/*
	 * bool|int get_next_vmid ()
	 * Get Last VMID from a Cluster or a Node
	 * returns a VMID, or false if not found.
	 */
	public function get_next_vmid () {
		$vmid = $this->get("/cluster/nextid");
		if ($vmid == null) {
			return false;
		} else {
			return $vmid;
		}
	}

	/*
	 * array get_vms ()
	 * Get List of all vms
	 */
	public function get_vms () {
		$node_list = $this->get_node_list();
		$result=[];
		if (count($node_list) > 0) {
			foreach ($node_list as $node_name) {
				$vms_list = $this->get("nodes/" . $node_name . "/qemu/");
				if (count($vms_list) > 0) {
					$key_values = array_column($vms_list, 'vmid'); 
					array_multisort($key_values, SORT_ASC, $vms_list);
					foreach($vms_list as &$row) {
						$row[node] = $node_name;
					}
					$result = array_merge($result, $vms_list);
				}
				if (count($result) > 0) {
					$this->$cluster_vms_list = $result;
					return $this->$cluster_vms_list;
				} else {
					error_log("PVE2 API: Empty list of VMs returned in this Cluster.");
					return false;
				}
			}
		} else {
			error_log("PVE2 API: Empty list of Nodes returned in this Cluster.");
			return false;
		}
	}
	
	/*
	 * bool|int start_vm ($node,$vmid)
	 * Start specific vm
	 */
	public function start_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$parameters = array(
				"vmid" => $vmid,
				"node" => $node,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/status/start";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Started VM " . $vmid . "");
				return true;
			} else {
				error_log("PVE2 API: Error starting VM " . $vmid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}
	
	/*
	 * bool|int shutdown_vm ($node,$vmid)
	 * Gracefully shutdown specific vm
	 */
	public function shutdown_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$parameters = array(
				"vmid" => $vmid,
				"node" => $node,
				"timeout" => 60,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/status/shutdown";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Shutdown VM " . $vmid . "");
				return true;
			} else {
				error_log("PVE2 API: Error shutting down VM " . $vmid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}

	/*
	 * bool|int stop_vm ($node,$vmid)
	 * Force stop specific vm
	 */
	public function stop_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$parameters = array(
				"vmid" => $vmid,
				"node" => $node,
				"timeout" => 60,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/status/stop";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Stopped VM " . $vmid . "");
				return true;
			} else {
				error_log("PVE2 API: Error stopping VM " . $vmid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}
	
	/*
	 * bool|int resume_vm ($node,$vmid)
	 * Resume from suspend specific vm
	 */
	public function resume_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$parameters = array(
				"vmid" => $vmid,
				"node" => $node,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/status/resume";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Resumed VM " . $vmid . "");
				return true;
			} else {
				error_log("PVE2 API: Error resuming VM " . $vmid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}
	
	/*
	 * bool|int suspend_vm ($node,$vmid)
	 * Suspend specific vm
	 */
	public function suspend_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$parameters = array(
				"vmid" => $vmid,
                		"node" => $node,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/status/suspend";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Suspended VM " . $vmid . "");
				return true;
			} else {
				error_log("PVE2 API: Error suspending VM " . $vmid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}
	
	/*
	 * bool|int clone_vm ($node,$vmid)
	 * Create fullclone of vm
	 */
	public function clone_vm ($node,$vmid) {
		if(isset($vmid) && isset($node)){
			$lastid = $this->get_next_vmid();
			$parameters = array(
				"vmid" => $vmid,
				"node" => $node,
				"newid" => $lastid,
				"full" => true,
			);
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/clone";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Cloned VM " . $vmid . " to " . $lastid . "");
				return true;
			} else {
				error_log("PVE2 API: Error cloning VM " . $vmid . " to " . $lastid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}

	/*
	 * bool|int snapshot_vm ($node,$vmid,$snapname = NULL)
	 * Create snapshot of vm
	 */	
	public function snapshot_vm ($node,$vmid,$snapname = NULL) {
		if(isset($vmid) && isset($node)){
			$lastid = $this->get_next_vmid();
			if (is_null($snapname)){
				$parameters = array(
					"vmid" => $vmid,
					"node" => $node,
					"vmstate" => true,
				);
			} else {
				$parameters = array(
					"vmid" => $vmid,
					"node" => $node,
					"vmstate" => true,
					"snapname" => $snapname,
				);
			}
			$url = "/nodes/" . $node . "/qemu/" . $vmid . "/snapshot";
			$post = $this->post($url,$parameters);
			if ($post) {
				error_log("PVE2 API: Snapshotted VM " . $vmid . " to " . $lastid . "");
				return true;
			} else {
				error_log("PVE2 API: Error snapshotting VM " . $vmid . " to " . $lastid . "");
				return false;
			}
		} else {
			error_log("PVE2 API: No VM or Node valid");
			return false;
		}
	}
	
	/*
	 * bool|string get_version ()
	 * Return the version and minor revision of Proxmox Server
	 */
	public function get_version () {
		$version = $this->get("/version");
		if ($version == null) {
			return false;
		} else {
			return $version['version'];
		}
	}

	/*
	 * object/array? get (string action_path)
	 */
	public function get ($action_path) {
		return $this->action($action_path, "GET");
	}

	/*
	 * bool put (string action_path, array parameters)
	 */
	public function put ($action_path, $parameters) {
		return $this->action($action_path, "PUT", $parameters);
	}

	/*
	 * bool post (string action_path, array parameters)
	 */
	public function post ($action_path, $parameters) {
		return $this->action($action_path, "POST", $parameters);
	}

	/*
	 * bool delete (string action_path)
	 */
	public function delete ($action_path) {
		return $this->action($action_path, "DELETE");
	}

	// Logout not required, PVEAuthCookie tokens have a 2 hour lifetime.
}

?>