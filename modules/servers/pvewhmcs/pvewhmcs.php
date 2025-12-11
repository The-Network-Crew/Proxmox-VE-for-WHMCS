<?php

/*
	Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
	https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
	File: /modules/servers/pvewhmcs/pvewhmcs.php (PVE Work)

	Copyright (C) The Network Crew Pty Ltd (TNC) & Co.
	For other Contributors to PVEWHMCS, see CONTRIBUTORS.md

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

// DEP: Proxmox API Class - make sure we can access via PVE via API
if (file_exists('../modules/addons/pvewhmcs/proxmox.php'))
	require_once('../modules/addons/pvewhmcs/proxmox.php');
else
	require_once(ROOTDIR . '/modules/addons/pvewhmcs/proxmox.php');

// Import SQL Connectivity (WHMCS)
use Illuminate\Database\Capsule\Manager as Capsule;

// Prepare to source Guest type
global $guest;

// Fix the Server Test showing "Pvewhmcs" instead of pretty name
// ref: https://developers.whmcs.com/provisioning-modules/meta-data-params/
function pvewhmcs_MetaData() {
    return array(
        'DisplayName' => 'Proxmox VE',
        'APIVersion' => '1.1',
        'RequiresServer' => 'true',
	);
}

/**
 * AdminLink: show a direct link to the Proxmox UI on :8006.
 * Falls back to server IP if hostname is empty.
 */
function pvewhmcs_AdminLink(array $params) {
    $host = $params['serverhostname'] ?: $params['serverip'];
    if (!$host) {
        // Nothing to link to – return the module page as a safe fallback
        return '<a href="addonmodules.php?module=pvewhmcs">Module Config</a>';
    }

    $url  = 'https://' . $host . ':8006';
    return '<form action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" method="get" target="_blank">
                <input type="submit" value="Log in to PVE" class="btn btn-sm btn-default" />
            </form>';
}

// WHMCS CONFIG > SERVICES/PRODUCTS > Their Service > Tab #3 (Plan/Pool)
function pvewhmcs_ConfigOptions() {
	// Retrieve PVE for WHMCS Cluster
	$server=Capsule::table('tblservers')->where('type', '=', 'pvewhmcs')->get()[0] ;

	// Retrieve Plans
	foreach (Capsule::table('mod_pvewhmcs_plans')->get() as $plan) {
		$plans[$plan->id] = '(' . $plan->vmtype . ')&nbsp;' . $plan->title ;
	}

	// Retrieve IP Pools
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $ippool) {
		$ippools[$ippool->id] = $ippool->title ;
	}
	
	/*
	$proxmox = new PVE2_API($server->ipaddress, $server->username, "pam", pvewhmcs_get_whmcs_server_password($server->password));
	if ($proxmox->login()) {
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);

		$storage_contents = $proxmox->get('/nodes/'.$first_node.'/storage/local/content') ;

		foreach ($storage_contents as $storage_content) {
			if ($storage_content['content']=='vztmpl') {
				$templates[$storage_content['volid']]=explode('.',explode('/',$storage_content['volid'])[1])[0] ;
			}
		}
	}
	*/
	
	// OPTIONS FOR THE QEMU/LXC PACKAGE; ties WHMCS PRODUCT to MODULE PLAN/POOL
	// Ref: https://developers.whmcs.com/provisioning-modules/config-options/
	// SQL/Param: configoption1 configoption2
	$configarray = array(
		"Plan" => array(
			"FriendlyName" => "PVE Plan",
			"Type" => "dropdown",
			'Options' => $plans ,
			"Description" => "(QEMU/LXC) Plan Name"
		),
		"IPPool" => array(
			"FriendlyName" => "IPv4 Pool",
			"Type" => "dropdown",
			'Options'=> $ippools,
			"Description" => "(IPv4) Allocation Pool"
		),
	);

	// Deliver the options back into WHMCS
	return $configarray;
}

// PVE API FUNCTION: Create the Service on the Hypervisor
function pvewhmcs_CreateAccount($params) {
	// Make sure "WHMCS Admin > Products/Services > Proxmox-based Service -> Plan + Pool" are set. Else, fail early. (Issue #36)
	if (!isset($params['configoption1'], $params['configoption2'])) {
		throw new Exception("PVEWHMCS Error: Missing Config. Service/Product WHMCS Config not saved (Plan/Pool not assigned to WHMCS Service type). Check Support/Health tab in Module Config for info. Quick and easy fix.");
	}
	if (empty($params['configoption1'])) {
		throw new Exception("PVEWHMCS Error: Missing Config. Service/Product WHMCS Config not saved (Plan/Pool not assigned to WHMCS Service type). Check Support/Health tab in Module Config for info. Quick and easy fix.");
	}
	if (empty($params['configoption2'])) {
		throw new Exception("PVEWHMCS Error: Missing Config. Service/Product WHMCS Config not saved (Plan/Pool not assigned to WHMCS Service type). Check Support/Health tab in Module Config for info. Quick and easy fix.");
	}

	// Retrieve Plan from table
	$plan = Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $params['configoption1'])->get()[0];

	// PVE Host - Connection Info
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];

	// Prepare the service config array
	$vm_settings = array();

	// Select an IP Address from Pool
	$ip = Capsule::select('select ipaddress,mask,gateway from mod_pvewhmcs_ip_addresses i INNER JOIN mod_pvewhmcs_ip_pools p on (i.pool_id=p.id and p.id=' . $params['configoption2'] . ') where  i.ipaddress not in(select ipaddress from mod_pvewhmcs_vms) limit 1')[0];

	// Get the starting VMID from the config options
	$vmid = Capsule::table('mod_pvewhmcs')->where('id', '1')->value('start_vmid');

	////////////////////
	// CREATE IF QEMU //
	////////////////////
	if (!empty($params['customfields']['KVMTemplate'])) {
		// QEMU TEMPLATE - CREATION LOGIC
		$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
		if ($proxmox->login()) {
			// Get first node name.
			$nodes = $proxmox->get_node_list();
			$first_node = $nodes[0];
			unset($nodes);
			// Find the next available VMID by checking if the VMID exists either for QEMU or LXC
			$vmid = pvewhmcs_find_next_available_vmid($proxmox, $first_node, $vmid);
			$vm_settings['newid'] = $vmid;
			$vm_settings['name'] = "vps" . $params["serviceid"] . "-cus" . $params['clientsdetails']['userid'];
			$vm_settings['full'] = true;
			// QEMU TEMPLATE - Conduct the VM CLONE from Template to Machine
			$logrequest = '/nodes/' . $first_node . '/qemu/' . $params['customfields']['KVMTemplate'] . '/clone' . $vm_settings;
			$response = $proxmox->post('/nodes/' . $first_node . '/qemu/' . $params['customfields']['KVMTemplate'] . '/clone', $vm_settings);

			// DEBUG - Log the request parameters before it's fired
			if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
				logModuleCall(
					'pvewhmcs',
					__FUNCTION__,
					$logrequest,
					json_decode($response)
				);
			}

			// Extract UPID from the response (Proxmox returns colon-delimited string)
			if (strpos($response, 'UPID:') === 0) {
				$upid = trim($response); // Extract the entire UPID including "UPID:"

				// Poll for task completion
				$max_retries = 10;  // Total retries (avoid infinite loop)
				$retry_interval = 15;  // Delay in seconds between retries
				$completed = false;  // Starting - not complete until done

				for ($i = 0; $i < $max_retries; $i++) {
					// Check task status
					$task_status = $proxmox->get('/nodes/' . $first_node . '/tasks/' . $upid . '/status');

					if (isset($task_status['status']) && $task_status['status'] === 'stopped') {
						// Task is completed, now check exit status
						if (isset($task_status['exitstatus']) && $task_status['exitstatus'] === 'OK') {
							$completed = true;
							break;
						} else {
							// Task stopped, but failed with an exit status
							throw new Exception("Proxmox Error: Task failed with exit status: " . $task_status['exitstatus']);
						}
					} elseif ($task_status['status'] === 'running') {
						// Task is still running, wait and retry
						sleep($retry_interval);
					} else {
						// Unexpected task status
						throw new Exception("Proxmox Error: Unexpected task status: " . json_encode($task_status));
					}
				}

				if (!$completed) {
					throw new Exception("Proxmox Error: Task did not complete in time. Adjust ~/modules/servers/pvewhmcs/pvewhmcs.php >> max_retries option (2 locations).");
				}

				// Task is completed, now update the database with VM details.
				Capsule::table('mod_pvewhmcs_vms')->insert(
					[
						'id' => $params['serviceid'],
						'vmid' => $vmid,
						'user_id' => $params['clientsdetails']['userid'],
						'vtype' => 'qemu',
						'ipaddress' => $ip->ipaddress,
						'subnetmask' => $ip->mask,
						'gateway' => $ip->gateway,
						'created' => date("Y-m-d H:i:s"),
						'v6prefix' => $plan->ipv6,
					]
				);
				// ISSUE #32 relates - amend post-clone to ensure excludes-disk amendments are all done, too.
				$cloned_tweaks['memory'] = $plan->memory;
				$cloned_tweaks['ostype'] = $plan->ostype;
				$cloned_tweaks['sockets'] = $plan->cpus;
				$cloned_tweaks['cores'] = $plan->cores;
				$cloned_tweaks['cpu'] = $plan->cpuemu;
				$cloned_tweaks['kvm'] = $plan->kvm;
				$cloned_tweaks['onboot'] = $plan->onboot;
				$amendment = $proxmox->post('/nodes/' . $first_node . '/qemu/' . $vm_settings['newid'] . '/config', $cloned_tweaks);
				return true;
			} else {
				throw new Exception("Proxmox Error: Failed to initiate clone. Response: " . json_encode($response));
			}
		} else {
			throw new Exception("Proxmox Error: PVE API login failed. Please check your credentials.");
		}
		/////////////////////////////////////////////////
		// PREPARE SETTINGS FOR QEMU/LXC EVENTUALITIES //
		/////////////////////////////////////////////////
	} else {
		// No longer inheriting WHMCS Service ID, so //
		// $vm_settings['vmid'] = $params["serviceid"];
		if ($plan->vmtype == 'lxc') {
			///////////////////////////
			// LXC: Preparation Work //
			///////////////////////////
			$vm_settings['ostemplate'] = $params['customfields']['Template'];
			$vm_settings['swap'] = $plan->swap;
			$vm_settings['rootfs'] = $plan->storage . ':' . $plan->disk;
			$vm_settings['bwlimit'] = $plan->diskio;
			$vm_settings['nameserver'] = '1.1.1.1 1.0.0.1';
			$vm_settings['net0'] = 'name=eth0,bridge=' . $plan->bridge . $plan->vmbr . ',ip=' . $ip->ipaddress . '/' . mask2cidr($ip->mask) . ',gw=' . $ip->gateway . ',rate=' . $plan->netrate;
			if (!empty($plan->ipv6) && $plan->ipv6 != '0') {
				// Standard prep for the 2nd int.
				$vm_settings['net1'] = 'name=eth1,bridge=' . $plan->bridge . $plan->vmbr . ',rate=' . $plan->netrate;
				switch ($plan->ipv6) {
					case 'auto':
						// Pass in auto, triggering SLAAC
						$vm_settings['nameserver'] .= ' 2606:4700:4700::1111 2606:4700:4700::1001';
						$vm_settings['net1'] .= ',ip6=auto';
						break;
					case 'dhcp':
						// DHCP for IPv6 option
						$vm_settings['nameserver'] .= ' 2606:4700:4700::1111 2606:4700:4700::1001';
						$vm_settings['net1'] .= ',ip6=dhcp';
						break;
					case 'prefix':
						// Future development
						break;
					default:
						break;
				}
				if (!empty($plan->vlanid)) {
					$vm_settings['net1'] .= ',tag=' . $plan->vlanid;
				}
			}
			if (!empty($plan->vlanid)) {
				$vm_settings['net0'] .= ',tag=' . $plan->vlanid;
			}
			$vm_settings['onboot'] = $plan->onboot;
			$vm_settings['unprivileged'] = $plan->unpriv;
			$vm_settings['password'] = $params['customfields']['Password'];
		} else {
			////////////////////////////
			// QEMU: Preparation Work //
			////////////////////////////
			$vm_settings['ostype'] = $plan->ostype;
			$vm_settings['sockets'] = $plan->cpus;
			$vm_settings['cores'] = $plan->cores;
			$vm_settings['cpu'] = $plan->cpuemu;
			$vm_settings['nameserver'] = '1.1.1.1 1.0.0.1';
			$vm_settings['ipconfig0'] = 'ip=' . $ip->ipaddress . '/' . mask2cidr($ip->mask) . ',gw=' . $ip->gateway;
			if (!empty($plan->ipv6) && $plan->ipv6 != '0') {
				switch ($plan->ipv6) {
					case 'auto':
						// Pass in auto, triggering SLAAC
						$vm_settings['nameserver'] .= ' 2606:4700:4700::1111 2606:4700:4700::1001';
						$vm_settings['ipconfig1'] = 'ip6=auto';
						break;
					case 'dhcp':
						// DHCP for IPv6 option
						$vm_settings['nameserver'] .= ' 2606:4700:4700::1111 2606:4700:4700::1001';
						$vm_settings['ipconfig1'] = 'ip6=dhcp';
						break;
					case 'prefix':
						// Future development
						break;
					default:
						break;
				}
			}
			$vm_settings['kvm'] = $plan->kvm;
			$vm_settings['onboot'] = $plan->onboot;

			$vm_settings[$plan->disktype . '0'] = $plan->storage . ':' . $plan->disk . ',format=' . $plan->diskformat;
			if (!empty($plan->diskcache)) {
				$vm_settings[$plan->disktype . '0'] .= ',cache=' . $plan->diskcache;
			}
			$vm_settings['bwlimit'] = $plan->diskio;

			// ISO: Attach file to the guest
			if (isset($params['customfields']['ISO'])) {
				$vm_settings['ide2'] = 'local:iso/' . $params['customfields']['ISO'] . ',media=cdrom';
			}

			// NET: Config specifics for guest networking
			if ($plan->netmode != 'none') {
				$vm_settings['net0'] = $plan->netmodel;
				if ($plan->netmode == 'bridge') {
					$vm_settings['net0'] .= ',bridge=' . $plan->bridge . $plan->vmbr;
				}
				$vm_settings['net0'] .= ',firewall=' . $plan->firewall;
				if (!empty($plan->netrate)) {
					$vm_settings['net0'] .= ',rate=' . $plan->netrate;
				}
				if (!empty($plan->vlanid)) {
					$vm_settings['net0'] .= ',tag=' . $plan->vlanid;
				}
				// IPv6: Same configs for second interface
				if (isset($vm_settings['ipconfig1'])) {
					$vm_settings['net1'] = $plan->netmodel;
					if ($plan->netmode == 'bridge') {
						$vm_settings['net1'] .= ',bridge=' . $plan->bridge . $plan->vmbr;
					}
					$vm_settings['net1'] .= ',firewall=' . $plan->firewall;
					if (!empty($plan->netrate)) {
						$vm_settings['net1'] .= ',rate=' . $plan->netrate;
					}
					if (!empty($plan->vlanid)) {
						$vm_settings['net1'] .= ',tag=' . $plan->vlanid;
					}
				}
			}
		}

		$vm_settings['cpuunits'] = $plan->cpuunits;
		$vm_settings['cpulimit'] = $plan->cpulimit;
		$vm_settings['memory'] = $plan->memory;

		////////////////////////////////////////////////////
		// CREATION: Attempt to Create Guest via PVE2 API //
		////////////////////////////////////////////////////
		try {
			$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);

			if ($proxmox->login()) {
				// Get first node name.
				$nodes = $proxmox->get_node_list();
				$first_node = $nodes[0];
				unset($nodes);

				// Find the next available VMID by checking if the VMID exists either for QEMU or LXC
				$vmid = pvewhmcs_find_next_available_vmid($proxmox, $first_node, $vmid);
				$vm_settings['vmid'] = $vmid;

				if ($plan->vmtype == 'kvm') {
					$v = 'qemu';
				} else {
					$v = 'lxc';
				}

				// ACTION - Fire the attempt to create
				$logrequest = '/nodes/' . $first_node . '/' . $v . $vm_settings;
				$response = $proxmox->post('/nodes/' . $first_node . '/' . $v, $vm_settings);

				// DEBUG - Log the request parameters after it's fired
				if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
					logModuleCall(
						'pvewhmcs',
						__FUNCTION__,
						$logrequest,
						json_decode($response)
					);
				}

				// Extract UPID from the response (Proxmox returns colon-delimited string)
				if (strpos($response, 'UPID:') === 0) {
					$upid = trim($response); // Extract the entire UPID including "UPID:"

					// Poll for task completion
					$max_retries = 10;  // Total retries (avoid infinite loop)
					$retry_interval = 15;  // Number of seconds between retries
					$completed = false;

					for ($i = 0; $i < $max_retries; $i++) {
						// Check task status
						$task_status = $proxmox->get('/nodes/' . $first_node . '/tasks/' . $upid . '/status');

						if (isset($task_status['status']) && $task_status['status'] === 'stopped') {
							// Task is completed, now check exit status
							if (isset($task_status['exitstatus']) && $task_status['exitstatus'] === 'OK') {
								$completed = true;
								break;
							} else {
								// Task stopped, but failed with an exit status
								throw new Exception("Proxmox Error: Task failed with exit status: " . $task_status['exitstatus']);
							}
						} elseif ($task_status['status'] === 'running') {
							// Task is still running, wait and retry
							sleep($retry_interval);
						} else {
							// Unexpected task status
							throw new Exception("Proxmox Error: Unexpected task status: " . json_encode($task_status));
						}
					}

					if (!$completed) {
						throw new Exception("Proxmox Error: Task did not complete in time. Adjust ~/modules/servers/pvewhmcs/pvewhmcs.php >> max_retries option (2 locations).");
					}

					// Task is completed, now update the database with VM details.
					Capsule::table('mod_pvewhmcs_vms')->insert(
						[
							'id' => $params['serviceid'],
							'vmid' => $vmid,
							'user_id' => $params['clientsdetails']['userid'],
							'vtype' => $v,
							'ipaddress' => $ip->ipaddress,
							'subnetmask' => $ip->mask,
							'gateway' => $ip->gateway,
							'created' => date("Y-m-d H:i:s"),
							'v6prefix' => $plan->ipv6,
						]
					);
					return true;
				} else {
					throw new Exception("Proxmox Error: Failed to initiate creation. Response: " . json_encode($response));
				}
			} else {
				throw new Exception("Proxmox Error: PVE API login failed. Please check your credentials.");
			}
		} catch (PVE2_Exception $e) {
			// Record the error in WHMCS's module log.
			if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
				logModuleCall(
					'pvewhmcs',
					__FUNCTION__,
					$params,
					$e->getMessage() . $e->getTraceAsString()
				);
			}
			return $e->getMessage();
		}
		unset($vm_settings);
	}
}

/**
 * Find the next free VMID cluster-wide by probing /cluster/nextid.
 * Returns the first VMID >= $start_vmid for which /cluster/nextid?vmid=X returns X.
 *
 * @param PVE2_API $proxmox  Proxmox API client
 * @param string   $node     Ignored (VMIDs are cluster-wide)
 * @param int      $start_vmid Starting VMID from Module config
 * @return int
 * @throws Exception on unexpected API errors or if cap exceeded
 */
function pvewhmcs_find_next_available_vmid($proxmox, $node, $start_vmid) {
	$max_attempts = 1000; // Safety cap to avoid infinite loops
	$vmid = (int) $start_vmid; // Starting with configured VMID

    for ($i = 0; $i < $max_attempts; $i++, $vmid++) {
        try {
            // Proxmox API: is this VMID vacant?
            // If vacant, servers echo it back in 'data'.
            $resp = $proxmox->get('/cluster/nextid', ['vmid' => $vmid]);

            // Different PHP clients return either ['data' => '123'] or just '123'
            $data = (is_array($resp) && array_key_exists('data', $resp)) ? $resp['data'] : $resp;

            if ((int) $data === $vmid) {
                return $vmid; // confirmed vacant
            }

            // If the API returns a *different* number here, we ignore it and keep probing
            continue;

        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());

            // Occupied case looks like: "400 Parameter verification failed. vmid: VM 106 already exists"
            if (strpos($msg, 'already exists') !== false || strpos($msg, 'parameter verification failed') !== false) {
                continue; // try the next VMID
            }

            // Any other error is unexpected; surface it.
            throw $e;
        }
    }

    throw new Exception("Unable to find a free VMID starting at {$start_vmid} after {$max_attempts} attempts");
}

// PVE API FUNCTION, ADMIN: Test Connection with Proxmox node
function pvewhmcs_TestConnection(array $params) {
	try {
		// Call the service's connection test function
		$serverip = $params["serverip"];
		$serverusername = $params["serverusername"];
		$serverpassword = $params["serverpassword"];
		$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);

		// Set success if login succeeded
		if ($proxmox->login()) {
			$success = true;
			$errorMsg = '';
		}
	} catch (Exception $e) {
		// Record the error in WHMCS's module log
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$params,
			$e->getMessage(),
			$e->getTraceAsString()
		);
		// Set the error message as a failure
		$success = false;
		$errorMsg = $e->getMessage(); 
	}
	// Return success or error, and info
	return array(
		'success' => $success,
		'error' => $errorMsg,
	);
}

// PVE API FUNCTION, ADMIN: Suspend a Service on the hypervisor
function pvewhmcs_SuspendAccount(array $params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	
	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// Log and fire request
		$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/stop';
		$response = $proxmox->post($logrequest, $pve_cmdparam);
	}

	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// PVE API FUNCTION, ADMIN: Unsuspend a Service on the hypervisor
function pvewhmcs_UnsuspendAccount(array $params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	
	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// Log and fire request
		$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/start';
		$response = $proxmox->post($logrequest, $pve_cmdparam);
	}

	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// PVE API FUNCTION, ADMIN: Terminate a Service on the hypervisor
function pvewhmcs_TerminateAccount(array $params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()){
		// Find virtual machine type
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id', '=', $params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// Stop the service if it is not already stopped
		$guest_specific = $proxmox->get('/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/current');
		if ($guest_specific['status'] != 'stopped') {
			$proxmox->post('/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/stop', $pve_cmdparam);
			sleep(30);
		}
		$delete_response = $proxmox->delete('/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid,array('skiplock'=>1));
		if ($delete_response) {
			// Delete entry from module table once service terminated in PVE
			Capsule::table('mod_pvewhmcs_vms')->where('id', '=', $params['serviceid'])->delete();
			return "success";
		} else {
			$response_message = isset($delete_response['errors']) ? json_encode($delete_response['errors']) : "Unknown Error, consider using Debug Mode.";
			return "Error terminating account: {$response_message}";
		}
	} else {
		return "Error terminating account. Couldn't login to PVE.";
	}
}

// GENERAL CLASS: WHMCS Decrypter
class pvewhmcs_hash_encryption {
	/**
	 * Hashed value of the user provided encryption key
	 * @var string
	 **/
	var $hash_key;
	
	/**
	 * String length of hashed values using the current algorithm
         * @var int
	 **/
	var $hash_length;
	
	/**
	 * Switch base64 enconding on / off
         * @var bool    true = use base64, false = binary output / input
	 **/
	var $base64;
	
	/**
	 * Secret value added to randomize output and protect the user provided key
         * @var string  Change this value to add more randomness to your encryption
	 **/
	var $salt = 'Change this to any secret value you like. "d41d8cd98f00b204e9800998ecf8427e" might be a good example.';

	/**
	 * Constructor method
	 *
	 * Used to set key for encryption and decryption.
     * @param       string  $key    Your secret key used for encryption and decryption
     * @param       bool   $base64 Enable base64 en- / decoding
	 * @return mixed
	 */
	function pvewhmcs_hash_encryption($key, $base64 = true) {

		global $cc_encryption_hash;

		// Toggle base64 usage on / off
		$this->base64 = $base64;

		// Instead of using the key directly we compress it using a hash function
		$this->hash_key = $this->_hash($key);

		// Remember length of hashvalues for later use
		$this->hash_length = strlen($this->hash_key);
	}

	/**
	 * Method used for encryption
         * @param       string  $string Message to be encrypted
         * @return string       Encrypted message
	 */
	function encrypt($string) {
		$iv = $this->_generate_iv();

		// Clear output
		$out = '';

		// First block of output is ($this->hash_hey XOR IV)
		for($c=0;$c < $this->hash_length;$c++) {
			$out .= chr(ord($iv[$c]) ^ ord($this->hash_key[$c]));
		}

		// Use IV as first key
		$key = $iv;
		$c = 0;

		// Go through input string
		while($c < strlen($string)) {
			// If we have used all characters of the current key we switch to a new one
			if(($c != 0) and ($c % $this->hash_length == 0)) {
				// New key is the hash of current key and last block of plaintext
				$key = $this->_hash($key . substr($string,$c - $this->hash_length,$this->hash_length));
			}
			// Generate output by xor-ing input and key character for character
			$out .= chr(ord($key[$c % $this->hash_length]) ^ ord($string[$c]));
			$c++;
		}
		// Apply base64 encoding if necessary
		if($this->base64) $out = base64_encode($out);
		return $out;
	}

	/**
	 * Method used for decryption
         * @param       string  $string Message to be decrypted
         * @return string       Decrypted message
	 */
	function decrypt($string) {
		// Apply base64 decoding if necessary
		if($this->base64) $string = base64_decode($string);

		// Extract encrypted IV from input
		$tmp_iv = substr($string,0,$this->hash_length);

		// Extract encrypted message from input
		$string = substr($string,$this->hash_length,strlen($string) - $this->hash_length);
		$iv = $out = '';

		// Regenerate IV by xor-ing encrypted IV from block 1 and $this->hashed_key
		// Mathematics: (IV XOR KeY) XOR Key = IV
		for($c=0;$c < $this->hash_length;$c++)
		{
			$iv .= chr(ord($tmp_iv[$c]) ^ ord($this->hash_key[$c]));
		}
		// Use IV as key for decrypting the first block cyphertext
		$key = $iv;
		$c = 0;

		// Loop through the whole input string
		while($c < strlen($string)) {
			// If we have used all characters of the current key we switch to a new one
			if(($c != 0) and ($c % $this->hash_length == 0)) {
				// New key is the hash of current key and last block of plaintext
				$key = $this->_hash($key . substr($out,$c - $this->hash_length,$this->hash_length));
			}
			// Generate output by xor-ing input and key character for character
			$out .= chr(ord($key[$c % $this->hash_length]) ^ ord($string[$c]));
			$c++;
		}
		return $out;
	}

	/**
	 * Hashfunction used for encryption
	 *
	 * This class hashes any given string using the best available hash algorithm.
	 * Currently support for md5 and sha1 is provided. In theory even crc32 could be used
	 * but I don't recommend this.
	 *
         * @access      private
         * @param       string  $string Message to hashed
         * @return string       Hash value of input message
	 */
	function _hash($string) {
		// Use sha1() if possible, php versions >= 4.3.0 and 5
		if(function_exists('sha1')) {
			$hash = sha1($string);
		} else {
			// Fall back to md5(), php versions 3, 4, 5
			$hash = md5($string);
		}
		$out ='';
		// Convert hexadecimal hash value to binary string
		for($c=0;$c<strlen($hash);$c+=2) {
			$out .= $this->_hex2chr($hash[$c] . $hash[$c+1]);
		}
		return $out;
	}

	/**
	 * Generate a random string to initialize encryption
	 *
	 * This method will return a random binary string IV ( = initialization vector).
	 * The randomness of this string is one of the crucial points of this algorithm as it
	 * is the basis of encryption. The encrypted IV will be added to the encrypted message
	 * to make decryption possible. The transmitted IV will be encoded using the user provided key.
	 *
         * @todo        Add more random sources.
         * @access      private
         * @see function        pvewhmcs_hash_encryption
         * @return string       Binary pseudo random string
	 **/
	function _generate_iv() {
		// Initialize pseudo random generator
		srand ((double)microtime()*1000000);

		// Collect random data.
		// Add as many "pseudo" random sources as you can find.
		// Possible sources: Memory usage, diskusage, file and directory content...
		$iv  = $this->salt;
		$iv .= rand(0,getrandmax());
		// Changed to serialize as the second parameter to print_r is not available in php prior to version 4.4
		$iv .= serialize($GLOBALS);
		return $this->_hash($iv);
	}

	/**
	 * Convert hexadecimal value to a binary string
	 *
	 * This method converts any given hexadecimal number between 00 and ff to the corresponding ASCII char
	 *
         * @access      private
         * @param       string  Hexadecimal number between 00 and ff
         * @return      string  Character representation of input value
	 **/
	function _hex2chr($num) {
		return chr(hexdec($num));
	}
}

// GENERAL FUNCTION: Server PW from WHMCS DB
function pvewhmcs_get_whmcs_server_password($enc_pass){
	global $cc_encryption_hash;
	// Include WHMCS database configuration file
	include_once(dirname(dirname(dirname(dirname(__FILE__)))).'/configuration.php');
	$key1 = md5 (md5 ($cc_encryption_hash));
	$key2 = md5 ($cc_encryption_hash);
	$key = $key1.$key2;
	$hasher = new pvewhmcs_hash_encryption($key);
	return $hasher->decrypt($enc_pass);
}

// MODULE BUTTONS: Admin Interface button regos
function pvewhmcs_AdminCustomButtonArray() {
	$buttonarray = array(
		"Start" => "vmStart",
		"Reboot" => "vmReboot",
		"Soft Stop" => "vmShutdown",
		"Hard Stop" => "vmStop",
	);
	return $buttonarray;
}

// MODULE BUTTONS: Client Interface button regos
function pvewhmcs_ClientAreaCustomButtonArray() {
	$buttonarray = array(
		"<i class='fa fa-2x fa-flag-checkered'></i> Start" => "vmStart",
		"<i class='fa fa-2x fa-sync'></i> Reboot" => "vmReboot",
		"<i class='fa fa-2x fa-power-off'></i> Power Off" => "vmShutdown",
		"<i class='fa fa-2x fa-stop'></i>  Hard Stop" => "vmStop",
		"<i class='fa fa-2x fa-chart-bar'></i>  Statistics" => "vmStat",
		"<i class='fa fa-2x fa-search'></i>  Check Status" => "vmCheck",
		"<img src='./modules/servers/pvewhmcs/img/novnc.png'/> noVNC (HTML5)" => "noVNC",
	);
	return $buttonarray;
}

/**
 * Fetch RRD statistics from Proxmox with graceful error handling.
 *
 * Proxmox RRD schema changed in PVE 9 from pve2-{type} to pve-{type}-9.0.
 * The ds parameter names (cpu, mem, netin, netout, diskread, diskwrite) remain valid
 * across both old and new schemas - verified in pve-cluster/src/pmxcfs/status.c.
 *
 * RRD data may be unavailable when:
 *   - VM/CT was just created (RRD takes ~60s to populate)
 *   - RRD schema migration is incomplete on the PVE host
 *   - RRD files are corrupted or missing
 *
 * Refs:
 *   - Issue #162: https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/162
 *   - PVE RRD schema: https://github.com/proxmox/pve-cluster/blob/master/src/pmxcfs/status.c
 *   - Schema change: https://www.mail-archive.com/pve-devel@lists.proxmox.com/msg28317.html
 *
 * @param PVE2_API $proxmox    The Proxmox API client instance
 * @param string   $node       The Proxmox node name
 * @param string   $vtype      Guest type: 'qemu' or 'lxc'
 * @param int      $vmid       The VM/CT ID
 * @param string   $timeframe  RRD timeframe: 'day', 'week', 'month', 'year'
 * @param string   $ds         Data source(s): 'cpu', 'mem', 'netin,netout', 'diskread,diskwrite'
 * @return string|null         Base64-encoded PNG image, or null if unavailable
 */
function pvewhmcs_fetch_rrd_stat($proxmox, $node, $vtype, $vmid, $timeframe, $ds) {
	// Build the API path and query params for RRD image
	$rrd_path = '/nodes/' . $node . '/' . $vtype . '/' . $vmid . '/rrd';
	$rrd_params = '?timeframe=' . $timeframe . '&ds=' . $ds . '&cf=AVERAGE';
	
	try {
		// Attempt to fetch RRD graph image from PVE API
		$vm_rrd = $proxmox->get($rrd_path . $rrd_params);
		
		// Check if we got a valid response with image data
		if (isset($vm_rrd['image']) && !empty($vm_rrd['image'])) {
			// Decode and re-encode the image data for template use
			$image = utf8_decode($vm_rrd['image']);
			return base64_encode($image);
		}
	} catch (Exception $e) {
		// RRD data unavailable - this is normal for new VMs or during migration.
		// Log if debug mode is on, but don't crash the Client Area.
		if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
			logModuleCall(
				'pvewhmcs',
				'pvewhmcs_fetch_rrd_stat',
				'RRD fetch failed for ' . $vtype . '/' . $vmid . ' (' . $ds . ', ' . $timeframe . ')',
				$e->getMessage()
			);
		}
	}
	
	// Return null when RRD data is unavailable
	return null;
}

// OUTPUT: Module output to the Client Area
function pvewhmcs_ClientArea($params) {
	// Retrieve virtual machine info from table mod_pvewhmcs_vms
	$guest=Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->get()[0] ;
	
	// Gather access credentials for PVE, as these are no longer passed for Client Area
	$pveservice=Capsule::table('tblhosting')->find($params['serviceid']) ;
	$pveserver=Capsule::table('tblservers')->where('id','=',$pveservice->server)->get()[0] ;

	// Get IP and User for Hypervisor
	$serverip = $pveserver->ipaddress;
	$serverusername = $pveserver->username;
	// Password access is different in Client Area, so retrieve and decrypt
	$api_data = array(
		'password2' => $pveserver->password,
	);
	$serverpassword = localAPI('DecryptPassword', $api_data);

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		// Where node lives ? 
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			throw new Exception(
			"PVEWHMCS Error: Unable to determine node for VMID {$guest->vmid} (Service #{$params['serviceid']})."
			);
		}

		# Get and set VM variables
		$vm_config = $proxmox->get('/nodes/'.$guest_node.'/'.$guest->vtype.'/'.$guest->vmid .'/config') ;
		$cluster_resources = $proxmox->get('/cluster/resources');
		$vm_status = null;
		// DEBUG - Log the /cluster/resources and /config for the VM/CT, if enabled
		$cluster_encoded = json_encode($cluster_resources);
		$vmspecs_encoded = json_encode($vm_config);
		if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
			logModuleCall(
				'pvewhmcs',
				__FUNCTION__,
				'CLUSTER INFO: ' . $cluster_encoded,
				'GUEST CONFIG (Service #' . $params['serviceid'] . ' / PVE ID #' . $guest->vmid . ' / Client #' . $params['clientsdetails']['userid'] . '): ' . $vmspecs_encoded
			);
		}

		# Loop through data, find ID
		$vm_status = null;
		foreach ($cluster_resources as $vm) {
			// Using vmid directly, from Module Table against API Response (ignoring Service ID now)
			if ($vm['vmid'] == $guest->vmid && $vm['type'] == $guest->vtype) {
				$vm_status = $vm;
				break;
			}

			// If the vmid is not found, check against serviceid (<v1.2.9 case)
			if ($vm['vmid'] == $params['serviceid'] && $vm['type'] == $guest->vtype) {
				$vm_status = $vm;
				break;
			}
		}

		# Retrieve & set usage data appropriately
		if ($vm_status !== null) {
			$vm_status['uptime'] = time2format($vm_status['uptime']);
			$vm_status['cpu'] = round($vm_status['cpu'] * 100, 2);

			$vm_status['diskusepercent'] = intval($vm_status['disk'] * 100 / $vm_status['maxdisk']);
			$vm_status['memusepercent'] = intval($vm_status['mem'] * 100 / $vm_status['maxmem']);

			if ($guest->vtype == 'lxc') {
				// Check on swap before setting graph value
				$ct_specific = $proxmox->get('/nodes/'.$guest_node.'/lxc/'.$guest->vmid.'/status/current');
				if ($ct_specific['maxswap'] != 0) {
					$vm_status['swapusepercent'] = intval($ct_specific['swap'] * 100 / $ct_specific['maxswap']);
				}
			} else {
				// Fall back to 0% usage to satisfy chart requirement
				$vm_status['swapusepercent'] = 0;
			}
		} else {
	    		// Handle the VM not found in the cluster resources (Optional)
			echo "VM/CT not found in Cluster Resources.";
		}

		// ----------------------------------------------------------------
		// Fetch RRD statistics graphs from Proxmox.
		// Uses pvewhmcs_fetch_rrd_stat() for graceful error handling.
		// RRD data may be unavailable for new VMs or during PVE migration.
		// ----------------------------------------------------------------

		// CPU usage statistics (day/week/month/year)
		$vm_statistics['cpu']['year']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'year', 'cpu');
		$vm_statistics['cpu']['month'] = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'month', 'cpu');
		$vm_statistics['cpu']['week']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'week', 'cpu');
		$vm_statistics['cpu']['day']   = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'day', 'cpu');

		// Memory usage statistics (day/week/month/year)
		$vm_statistics['mem']['year']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'year', 'mem');
		$vm_statistics['mem']['month'] = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'month', 'mem');
		$vm_statistics['mem']['week']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'week', 'mem');
		$vm_statistics['mem']['day']   = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'day', 'mem');

		// Network I/O statistics (day/week/month/year)
		$vm_statistics['netinout']['year']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'year', 'netin,netout');
		$vm_statistics['netinout']['month'] = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'month', 'netin,netout');
		$vm_statistics['netinout']['week']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'week', 'netin,netout');
		$vm_statistics['netinout']['day']   = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'day', 'netin,netout');

		// Disk I/O statistics (day/week/month/year)
		$vm_statistics['diskrw']['year']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'year', 'diskread,diskwrite');
		$vm_statistics['diskrw']['month'] = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'month', 'diskread,diskwrite');
		$vm_statistics['diskrw']['week']  = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'week', 'diskread,diskwrite');
		$vm_statistics['diskrw']['day']   = pvewhmcs_fetch_rrd_stat($proxmox, $guest_node, $guest->vtype, $guest->vmid, 'day', 'diskread,diskwrite');

		$vm_config['vtype'] = $guest->vtype ;
		$vm_config['ipv4'] = $guest->ipaddress ;
		$vm_config['netmask4'] = $guest->subnetmask ;
		$vm_config['gateway4'] = $guest->gateway ;
		$vm_config['created'] = $guest->created ;
		$vm_config['v6prefix'] = $guest->v6prefix ;
	}
	else {
		echo '<center><strong>Error: Unable to gather data from Hypervisor.<br>Please contact Tech Support!</strong></center>';
		exit;
	}

	return array(
		'templatefile' => 'clientarea',
		'vars' => array(
			'params' => $params,
			'vm_config' => $vm_config,
			'vm_status' => $vm_status,
			'vm_statistics' => $vm_statistics,
			'vm_vncproxy' => $vm_vncproxy,
		),
	);
}

// OUTPUT: VM Statistics/Graphs render to Client Area
function pvewhmcs_vmStat($params) {
	return true;
}

// VNC: Console access to VM/CT via noVNC
function pvewhmcs_noVNC($params) {
	// Check if VNC Secret is configured in Module Config, fail early if not. (#27)
	if (strlen(Capsule::table('mod_pvewhmcs')->where('id', '1')->value('vnc_secret'))<15) {
		throw new Exception("PVEWHMCS Error: VNC Secret in Module Config either not set or not long enough. Recommend 20+ characters for security.");
	}
	
	// Get login credentials then make the Proxmox connection attempt.
	$serverip = $params["serverip"];
	$serverusername = 'vnc';
	$serverpassword = Capsule::table('mod_pvewhmcs')->where('id', '1')->value('vnc_secret');
	
	$proxmox = new PVE2_API($serverip, $serverusername, "pve", $serverpassword);
	if ($proxmox->login()) {
		// Early prep work
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return 'Failed to prepare noVNC. Unable to determine node.';
		}
		$vm_vncproxy = $proxmox->post('/nodes/'.$guest_node.'/'.$guest->vtype.'/'.$guest->vmid .'/vncproxy', array( 'websocket' => '1' )) ;

		// Get both tickets prepared
		$pveticket = $proxmox->getTicket();
		$vncticket = $vm_vncproxy['ticket'];
		// $path should only contain the actual path without any query parameters
		$path = 'api2/json/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/vncwebsocket?port=' . $vm_vncproxy['port'] . '&vncticket=' . urlencode($vncticket);
		// Construct the noVNC Router URL with the path already prepared now
		$url = '/modules/servers/pvewhmcs/novnc_router.php?host=' . $serverip . '&pveticket=' . urlencode($pveticket) . '&path=' . urlencode($path) . '&vncticket=' . urlencode($vncticket);
		// Build and deliver the noVNC Router hyperlink for access
		$vncreply = '<center style="background-color: green;"><strong style="color: white;">Console (noVNC) successfully prepared!<br><a href="'.$url.'" target="_blanK" style="color: Khaki;"><u>Click here to launch noVNC.</u></a></strong></center>' ;
		return $vncreply;
	} else {
		$vncreply = 'Failed to prepare noVNC. Please contact Technical Support.';
		return $vncreply;
	}
}

// VNC: Console access to VM/CT via SPICE
function pvewhmcs_SPICE($params) {
	// Check if VNC Secret is configured in Module Config, fail early if not. (#27)
	if (strlen(Capsule::table('mod_pvewhmcs')->where('id', '1')->value('vnc_secret'))<15) {
		throw new Exception("PVEWHMCS Error: VNC Secret in Module Config either not set or not long enough. Recommend 20+ characters for security.");
	}
	
	// Get login credentials then make the Proxmox connection attempt.
	$serverip = $params["serverip"];
	$serverusername = 'vnc';
	$serverpassword = Capsule::table('mod_pvewhmcs')->where('id', '1')->value('vnc_secret');
	
	$proxmox = new PVE2_API($serverip, $serverusername, "pve", $serverpassword);
	if ($proxmox->login()) {
		// Early prep work
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return 'Failed to prepare SPICE. Unable to determine node.';
		}
		$vm_vncproxy = $proxmox->post('/nodes/'.$guest_node.'/'.$guest->vtype.'/'.$guest->vmid .'/vncproxy', array( 'websocket' => '1' )) ;

		// Get both tickets prepared
		$pveticket = $proxmox->getTicket();
		$vncticket = $vm_vncproxy['ticket'];
		// $path should only contain the actual path without any query parameters
		$path = 'api2/json/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/vncwebsocket?port=' . $vm_vncproxy['port'] . '&vncticket=' . urlencode($vncticket);
		// Construct the SPICE Router URL with the path already prepared now
		$url = '/modules/servers/pvewhmcs/spice_router.php?host=' . $serverip . '&pveticket=' . urlencode($pveticket) . '&path=' . urlencode($path) . '&vncticket=' . urlencode($vncticket);
		// Build and deliver the SPICE Router hyperlink for access
		$vncreply = '<center style="background-color: green;"><strong>Console (SPICE) successfully prepared.<br><a href="'.$url.'" target="_blanK" style="color: Khaki;"><u>Click here</u></a> to launch SPICE.</strong></center>' ;
		return $vncreply;
	} else {
		$vncreply = 'Failed to prepare SPICE. Please contact Technical Support.';
		return $vncreply;
	}
}

// PVE API FUNCTION, CLIENT/ADMIN: Start the VM/CT
function pvewhmcs_vmStart($params) {
	// Gather access credentials for PVE, as these are no longer passed for Client Area
	$pveservice = Capsule::table('tblhosting')->find($params['serviceid']) ;
	$pveserver = Capsule::table('tblservers')->where('id','=',$pveservice->server)->get()[0] ;
	$serverip = $pveserver->ipaddress;
	$serverusername = $pveserver->username;

	$api_data = array(
		'password2' => $pveserver->password,
	);
	$serverpassword = localAPI('DecryptPassword', $api_data);

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/start';
		$response = $proxmox->post($logrequest, $pve_cmdparam);
	}
	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// PVE API FUNCTION, CLIENT/ADMIN: Reboot the VM/CT
function pvewhmcs_vmReboot($params) {
	// Gather access credentials for PVE, as these are no longer passed for Client Area
	$pveservice = Capsule::table('tblhosting')->find($params['serviceid']) ;
	$pveserver = Capsule::table('tblservers')->where('id','=',$pveservice->server)->get()[0] ;
	$serverip = $pveserver->ipaddress;
	$serverusername = $pveserver->username;

	$api_data = array(
		'password2' => $pveserver->password,
	);
	$serverpassword = localAPI('DecryptPassword', $api_data);

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// Check status before doing anything
		$guest_specific = $proxmox->get('/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/current');
		if ($guest_specific['status'] == 'stopped') {
			// START if Stopped
			$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/start';
			$response = $proxmox->post($logrequest, $pve_cmdparam);
		} else {
			// REBOOT if Started
			$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/reboot';
			$response = $proxmox->post($logrequest, $pve_cmdparam);
		}
	}

	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// PVE API FUNCTION, CLIENT/ADMIN: Shutdown the VM/CT
function pvewhmcs_vmShutdown($params) {
	// Gather access credentials for PVE, as these are no longer passed for Client Area
	$pveservice = Capsule::table('tblhosting')->find($params['serviceid']) ;
	$pveserver = Capsule::table('tblservers')->where('id','=',$pveservice->server)->get()[0] ;
	
	$serverip = $pveserver->ipaddress;
	$serverusername = $pveserver->username;

	$api_data = array(
		'password2' => $pveserver->password,
	);
	$serverpassword = localAPI('DecryptPassword', $api_data);

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// $pve_cmdparam['timeout'] = '60';
		$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/shutdown';
		$response = $proxmox->post($logrequest, $pve_cmdparam);
	}

	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// PVE API FUNCTION, CLIENT/ADMIN: Stop the VM/CT
function pvewhmcs_vmStop($params) {
	// Gather access credentials for PVE, as these are no longer passed for Client Area
	$pveservice = Capsule::table('tblhosting')->find($params['serviceid']) ;
	$pveserver = Capsule::table('tblservers')->where('id','=',$pveservice->server)->get()[0] ;
	$serverip = $pveserver->ipaddress;
	$serverusername = $pveserver->username;

	$api_data = array(
		'password2' => $pveserver->password,
	);
	$serverpassword = localAPI('DecryptPassword', $api_data);

	$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
	if ($proxmox->login()) {
		$guest = Capsule::table('mod_pvewhmcs_vms')->where('id','=',$params['serviceid'])->first();
		if ($guest === null) {
			return "Error performing action. Unable to find guest linked to Service ID ({$params['serviceid']})";
		}
		$guest_node = pvewhmcs_find_guest_node($proxmox, $guest, $params['serviceid']);
		if (empty($guest_node)) {
			return "Error performing action. Unable to determine node for VMID {$guest->vmid}.";
		}
		$pve_cmdparam = array();
		// $pve_cmdparam['timeout'] = '60';
		$logrequest = '/nodes/' . $guest_node . '/' . $guest->vtype . '/' . $guest->vmid . '/status/stop';
		$response = $proxmox->post($logrequest, $pve_cmdparam);
	}

	// DEBUG - Log the request parameters before it's fired
	if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
		logModuleCall(
			'pvewhmcs',
			__FUNCTION__,
			$logrequest,
			json_encode($response)
		);
	}
	// Return success only if no errors returned by PVE
	if (isset($response) && !isset($response['errors'])) {
		return "success";
	} else {
		// Handle the case where there are errors
		$response_message = isset($response['errors']) ? json_encode($response['errors']) : "Unknown Error, consider using Debug Mode.";
		return "Error performing action. " . $response_message;
	}
}

// CLIENT AREA: REFRESH TO CHECK STATUS ON-CLICK
function pvewhmcs_vmCheck($params) {
	return "success";
}

// NETWORKING FUNCTION: Convert subnet mask to CIDR
function mask2cidr($mask){
	$long = ip2long($mask);
	$base = ip2long('255.255.255.255');
	return 32-log(($long ^ $base)+1,2);
}




/**
 * Locate the Proxmox node that hosts a given VM/CT.
 *
 * @param PVE2_API $proxmox
 * @param object   $guest   Row from mod_pvewhmcs_vms (expects ->vmid, ->vtype)
 * @param int      $serviceId WHMCS service ID (compatibility)
 * @return string|null
 */
function pvewhmcs_find_guest_node(PVE2_API $proxmox, $guest, $serviceId)
{
    // 1) Where guest lives ? //
    $cluster_resources = $proxmox->get('/cluster/resources');

    if (is_array($cluster_resources)) {
        foreach ($cluster_resources as $res) {
            if (!isset($res['type'], $res['vmid'], $res['node'])) {
                continue;
            }

            // match vmid + type
            if ($res['vmid'] == $guest->vmid && $res['type'] === $guest->vtype) {
                return $res['node'];
            }

            // Legacy fallback (<1.2.9): vmid == serviceid
            if ($res['vmid'] == $serviceId && $res['type'] === $guest->vtype) {
                return $res['node'];
            }
        }
    }

    // 2) Fallback old behavior
    $nodes = $proxmox->get_node_list();
    if (is_array($nodes) && !empty($nodes)) {
        return $nodes[0];
    }

    return null;
}





function bytes2format($bytes, $precision = 2, $_1024 = true) {
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$bytes = max( $bytes, 0 );
	$pow = floor( ($bytes ? log( $bytes ) : 0) / log( ($_1024 ? 1024 : 1000) ) );
	$pow = min( $pow, count( $units ) - 1 );
	$bytes /= pow( ($_1024 ? 1024 : 1000), $pow );
	return round( $bytes, $precision ) . ' ' . $units[$pow];
}

function time2format($s) {
	$d = intval( $s / 86400 );
	if ($d < '10') {
		$d = '0' . $d;
	}
	$s -= $d * 86400;
	$h = intval( $s / 3600 );
	if ($h < '10') {
		$h = '0' . $h;
	}
	$s -= $h * 3600;
	$m = intval( $s / 60 );
	if ($m < '10') {
		$m = '0' . $m;
	}
	$s -= $m * 60;
	if ($s < '10') {
		$s = '0' . $s;
	}
	if ($d) {
		$str = $d . ' days ';
	}
	if ($h) {
		$str .= $h . ':';
	}
	if ($m) {
		$str .= $m . ':';
	}
	if ($s) {
		$str .= $s . '';
	}
	return $str;
}
?>