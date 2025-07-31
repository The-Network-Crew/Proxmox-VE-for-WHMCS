<?php

/*  
	Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
	https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
	File: /modules/addons/pvewhmcs/pvewhmcs.php (GUI Work)

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

// Pull in the WHMCS database handler Capsule for SQL
use Illuminate\Database\Capsule\Manager as Capsule;

// Define where the module operates in the Admin GUI
define( 'pvewhmcs_BASEURL', 'addonmodules.php?module=pvewhmcs' );

// DEP: Require the PHP API Class to interact with Proxmox VE
require_once('proxmox.php');

// CONFIG: Declare key options to the WHMCS Addon Module framework.
function pvewhmcs_config() {
	$configarray = array(
		"name" => "Proxmox VE for WHMCS",
		"description" => "Proxmox VE (Virtual Environment) & WHMCS, integrated & open-source! Provisioning & Management of VMs/CTs.".is_pvewhmcs_outdated(),
		"version" => "1.2.9",
		"author" => "The Network Crew Pty Ltd",
		'language' => 'English'
	);
	return $configarray;
}

// VERSION: also stored in repo/version (for update-available checker)
function pvewhmcs_version(){
	return "1.2.9";
}

// WHMCS MODULE: ACTIVATION of the ADDON MODULE
// This consists of importing the SQL structure, and then crudely returning yay or nay (needs improving)
function pvewhmcs_activate() {
	// Pull in the SQL structure (includes VNC/etc tweaks)
	$sql = file_get_contents(__DIR__ . '/db.sql');
	if (!$sql) {
		return array('status'=>'error','description'=>'The db.sql file was not found.');
	}
	// SQL file is good, let's proceed with pulling it in
	$err=false;
	$i=0;
	$query_array=explode(';',$sql) ;
	$query_count=count($query_array) ;
	// Iterate through the SQL commands to finalise init.
	foreach ( $query_array as $query) {
		if ($i<$query_count-1)
			if (!Capsule::statement($query.';'))
		$err=true;
		$i++ ;
	}
	// Return success or error.
	if (!$err)
		return array('status'=>'success','description'=>'Proxmox VE for WHMCS was installed successfully!');

	return array('status'=>'error','description'=>'Proxmox VE for WHMCS was not activated properly.');

}

// WHMCS MODULE: DEACTIVATION
function pvewhmcs_deactivate() {
	// Drop all module-related tables
	Capsule::statement('drop table mod_pvewhmcs_ip_addresses,mod_pvewhmcs_ip_pools,mod_pvewhmcs_plans,mod_pvewhmcs_vms,mod_pvewhmcs');
	// Return the assumed result (change?)
	return array('status'=>'success','description'=>'Proxmox VE for WHMCS successfully deactivated and all related tables deleted.');
}

// WHMCS MODULE: Upgrade
function pvewhmcs_upgrade($vars) {
	// This function gets passed the old ver once post-update, hence lt check
	$currentlyInstalledVersion = $vars['version'];
	// SQL Operations for v1.2.9 version
	if ($currentlyInstalledVersion < 1.2.9) {
		$schema = Capsule::schema();

		// Add the column "start_vmid" to the mod_pvewhmcs table
		if (!$schema->hasColumn('mod_pvewhmcs', 'start_vmid')) {
			$schema->table('mod_pvewhmcs', function ($table) {
				$table->integer('start_vmid')->default(100)->after('vnc_secret');
			});
		}

		// Add the column "vmid" to the mod_pvewhmcs_vms table
		if (!$schema->hasColumn('mod_pvewhmcs_vms', 'vmid')) {
			$schema->table('mod_pvewhmcs_vms', function ($table) {
				$table->integer('vmid')->default(0)->after('id');
			});
			// Populate ID into VMID for all previous guests
			Capsule::table('mod_pvewhmcs_vms')
				->where('vmid', 0)
				->update(['vmid' => Capsule::raw('id')]);
		}
	}
}

// UPDATE CHECKER: live vs repo
function is_pvewhmcs_outdated(){
	if(get_pvewhmcs_latest_version() > pvewhmcs_version()){
		return "<br><span style='float:right;'><b>Proxmox VE for WHMCS is outdated: <a style='color:red' href='https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases'>Download the new version!</a></span>";
	}
}

// UPDATE CHECKER: return latest ver
function get_pvewhmcs_latest_version(){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/The-Network-Crew/Proxmox-VE-for-WHMCS/master/version");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close ($ch);

	return str_replace("\n", "", $result);
}

// ADMIN MODULE GUI: output (HTML etc)
function pvewhmcs_output($vars) {
	$modulelink = $vars['modulelink'];

	// Check for update and report if available
	if (!empty(is_pvewhmcs_outdated())) {
		$_SESSION['pvewhmcs']['infomsg']['title']='Proxmox VE for WHMCS: New version available!' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Please visit the GitHub repository > Releases page. https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases' ;
	}
		
	// Print Messages to GUI before anything else
	if (isset($_SESSION['pvewhmcs']['infomsg'])) {
		echo '
		<div class="infobox">
		<strong>
		<span class="title">'.$_SESSION['pvewhmcs']['infomsg']['title'].'</span>
		</strong><br/>
		'.$_SESSION['pvewhmcs']['infomsg']['message'].'
		</div>
		' ;
		unset($_SESSION['pvewhmcs']) ;
	}

	// Set the active tab based on the GET parameter, default to 'vmplans'
	if (!isset($_GET['tab'])) {
    	$_GET['tab'] = 'vmplans';
	}

	// Start the HTML output for the Admin GUI
	echo '
	<div id="clienttabs">
	<ul class="nav nav-tabs admin-tabs">
	<li class="'.($_GET['tab']=="vmplans" ? "active" : "").'"><a id="tabLink1" data-toggle="tab" role="tab" href="#plans">VM/CT Plans</a></li>
	<li class="'.($_GET['tab']=="ippools" ? "active" : "").'"><a id="tabLink2" data-toggle="tab" role="tab" href="#ippools">IPv4 Pools</a></li>
	<li class="'.($_GET['tab']=="nodes" ? "active" : "").'"><a id="tabLink3" data-toggle="tab" role="tab" href="#nodes">Nodes / Cluster</a></li>
	<li class="'.($_GET['tab']=="actions" ? "active" : "").'"><a id="tabLink4" data-toggle="tab" role="tab" href="#actions">Actions / Logs</a></li>
	<li class="'.($_GET['tab']=="health" ? "active" : "").'"><a id="tabLink5" data-toggle="tab" role="tab" href="#health">Support / Health</a></li>
	<li class="'.($_GET['tab']=="config" ? "active" : "").'"><a id="tabLink6" data-toggle="tab" role="tab" href="#config">Module Config</a></li>
	</ul>
	</div>
	<div class="tab-content admin-tabs">
	' ;

	// Handle form submissions for saving or updating plans
	if (isset($_POST['addnewkvmplan']))
	{
		save_kvm_plan() ;
	}

	if (isset($_POST['updatekvmplan']))
	{
		update_kvm_plan() ;
	}
	if (isset($_POST['updatelxcplan']))
	{
		update_lxc_plan() ;
	}

	if (isset($_POST['addnewlxcplan']))
	{
		save_lxc_plan() ;
	}

	// VM/CT PLANS tab in ADMIN GUI
	echo '
	<div id="plans" class="tab-pane '.($_GET['tab']=="vmplans" ? "active" : "").'">
	<div class="btn-group" role="group" aria-label="...">
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=planlist">
	<i class="fa fa-list"></i>&nbsp; List: Guest Plans
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=add_kvm_plan">
	<i class="fa fa-plus-square"></i>&nbsp; Add: QEMU Plan
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=add_lxc_plan">
	<i class="fa fa-plus-square"></i>&nbsp; Add: LXC Plan
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=import_guest">
	<i class="fa fa-upload"></i>&nbsp; Import: Guest
	</a>
	</div>
	';

	// Handle actions based on the 'action' GET parameter
	if ($_GET['action']=='import_guest') {
		import_guest() ;
	}
	
	if ($_GET['action']=='add_kvm_plan') {
		kvm_plan_add() ;
	}

	if ($_GET['action']=='editplan') {
		if ($_GET['vmtype']=='kvm')
			kvm_plan_edit($_GET['id']) ;
		else
			lxc_plan_edit($_GET['id']) ;
	}

	if($_GET['action']=='removeplan') {
		remove_plan($_GET['id']) ;
	}


	if ($_GET['action']=='add_lxc_plan') {
		lxc_plan_add() ;
	}

	// List of VM/CT Plans
	if ($_GET['action']=='planlist') {
		echo '
		<table class="datatable" border="0" cellpadding="3" cellspacing="1" width="100%">
		<tbody>
		<tr>
		<th>ID</th>
		<th>Name</th>
		<th>Guest</th>
		<th>OS Type</th>
		<th>CPUs</th>
		<th>Cores</th>
		<th>RAM</th>
		<th>Balloon</th>
		<th>Swap</th>
		<th>Disk</th>
		<th>Disk Type</th>
		<th>Disk I/O</th>
		<th>PVE Store</th>
		<th>Net Mode</th>
		<th>Bridge</th>
		<th>NIC</th>
		<th>VLAN ID</th>
		<th>Net Rate</th>
		<th>Net BW</th>
		<th>IPv6</th>
		<th>Actions</th>
		</tr>';
		foreach (Capsule::table('mod_pvewhmcs_plans')->get() as $vm) {
			echo '<tr>';
			echo '<td>'.$vm->id . PHP_EOL .'</td>';
			echo '<td>'.$vm->title . PHP_EOL .'</td>';
			echo '<td>'.$vm->vmtype . PHP_EOL .'</td>';
			echo '<td>'.$vm->ostype . PHP_EOL .'</td>';
			echo '<td>'.$vm->cpus . PHP_EOL .'</td>';
			echo '<td>'.$vm->cores . PHP_EOL .'</td>';
			echo '<td>'.$vm->memory . PHP_EOL .'</td>';
			echo '<td>'.$vm->balloon . PHP_EOL .'</td>';
			echo '<td>'.$vm->swap . PHP_EOL .'</td>';
			echo '<td>'.$vm->disk . PHP_EOL .'</td>';
			echo '<td>'.$vm->disktype . PHP_EOL .'</td>';
			echo '<td>'.$vm->diskio . PHP_EOL .'</td>';
			echo '<td>'.$vm->storage . PHP_EOL .'</td>';
			echo '<td>'.$vm->netmode . PHP_EOL .'</td>';
			echo '<td>'.$vm->bridge.$vm->vmbr . PHP_EOL .'</td>';
			echo '<td>'.$vm->netmodel . PHP_EOL .'</td>';
			echo '<td>'.$vm->vlanid . PHP_EOL .'</td>';
			echo '<td>'.$vm->netrate . PHP_EOL .'</td>';
			echo '<td>'.$vm->bw . PHP_EOL .'</td>';
			echo '<td>'.$vm->ipv6 . PHP_EOL .'</td>';
			echo '<td>
			<a href="'.pvewhmcs_BASEURL.'&amp;tab=vmplans&amp;action=editplan&amp;id='.$vm->id.'&amp;vmtype='.$vm->vmtype.'"><img height="16" width="16" border="0" alt="Edit" src="images/edit.gif"></a>
			<a href="'.pvewhmcs_BASEURL.'&amp;tab=vmplans&amp;action=removeplan&amp;id='.$vm->id.'" onclick="return confirm(\'Plan will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>
			</td>' ;
			echo '</tr>' ;
		}
		echo '</tbody></table>';
	}
	echo '
	</div>
	';

	// IPv4 POOLS tab in ADMIN GUI
	echo '
	<div id="ippools" class="tab-pane '.($_GET['tab']=="ippools" ? "active" : "").'" >
	<div class="btn-group">
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=list_ip_pools">
	<i class="fa fa-list"></i>&nbsp; List: IPv4 Pools
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=newip">
	<i class="fa fa-plus"></i>&nbsp; Add: IPv4 to Pool
	</a>
	</div>
	';
	if ($_GET['action']=='list_ip_pools') {
		list_ip_pools() ;
	}
	if ($_GET['action']=='new_ip_pool') {
		add_ip_pool() ;
	}
	if ($_GET['action']=='newip') {
		add_ip_2_pool() ;
	}
	if (isset($_POST['newIPpool'])) {
		save_ip_pool() ;
	}
	if ($_GET['action']=='removeippool') {
		removeIpPool($_GET['id']) ;
	}
	if ($_GET['action']=='list_ips') {
		list_ips();
	}
	if ($_GET['action']=='removeip') {
		removeip($_GET['id'],$_GET['pool_id']);
	}
	echo'
	</div>
	';

	// NODES / CLUSTER tab in ADMIN GUI
	echo '<div id="nodes" class="tab-pane '.($_GET['tab']=="nodes" ? "active" : "").'" >' ;
	echo ('<strong><h2>PVE: /cluster/resources</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>PVE: Cluster Action Viewer</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>PVE: Failed Actions (emailed)</h2></strong>');
	echo ('Coming in v1.3.x<br><br>');
	echo ('<strong><a href=\'https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/milestones\' target=\'_blank\'>View the milestones/versions on GitHub</a></strong>');
	echo '</div>';

	// ACTIONS / LOGS tab in ADMIN GUI
	echo '<div id="actions" class="tab-pane '.($_GET['tab']=="actions" ? "active" : "").'" >' ;
	echo ('<strong><h2>WHMCS: Module Logging</h2></strong>');
	echo ('<u><a href=\'/admin/index.php?rp=/admin/logs/module-log\'>Click here</a></u><br>(Module Config > Debug Mode = ON)');
	echo ('<strong><h2>Module: Action History</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>Module: Failed Actions</h2></strong>');
	echo ('Coming in v1.3.x<br><strong><a href=\'https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/milestones\' target=\'_blank\'>View the milestones/versions on GitHub</a></strong>');
	echo '</div>';

	// SUPPORT / HEALTH tab in ADMIN GUI
	echo ('<div id="health" class="tab-pane '.($_GET['tab']=="health" ? "active" : "").'" >') ;
	echo ('<b>❤️ Proxmox for WHMCS is open-source and free to use & improve on!</b><br><a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank">https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/</a><br><br>');
	echo ('<b style="color:darkgreen;">Your 5-star review on WHMCS Marketplace will help the module grow!</b><br>*****: <a href="https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs" target="_blank">https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs</a><br><br>');
	echo ('<strong><h2>System Environment</h2></strong><b>Proxmox VE for WHMCS</b> v' . pvewhmcs_version() . ' (GitHub reports latest as <b>v' . get_pvewhmcs_latest_version() . '</b>)' . '<br><b>PHP</b> v' . phpversion() . ' running on <b>' . $_SERVER['SERVER_SOFTWARE'] . '</b> Web Server (' . $_SERVER['SERVER_NAME'] . ')<br><br>');
	echo ('<strong><h2>Issues: Common Causes</h2></strong>1. Save your Package (Plan/Pool)! (configproducts.php?action=edit&id=...#tab=3)<br>2. Where possible, we pass-through the exact error to WHMCS Admin. Check it for info!<br><br>');
	echo ('<strong><h2>Module Technical Support</h2></strong>Our README contains a wealth of information:<br><a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank">https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/</a><br>Please only raise an <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/new" target="_blank"><u>Issue</u></a> on GitHub - inc. logs - if you\'ve properly tried.<br>Help is not guaranteed (FOSS). We will need your assistance. <b>Thank you!</b><br><br>');
	echo '</div>';

	// Config Tab
	$config= Capsule::table('mod_pvewhmcs')->where('id', '=', '1')->get()[0];
	echo '<div id="config" class="tab-pane '.($_GET['tab']=="config" ? "active" : "").'" >' ;
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">VNC Secret</td>
	<td class="fieldarea">
	<input type="text" size="35" name="vnc_secret" id="vnc_secret" value="'.$config->vnc_secret.'"> Password of "vnc"@"pve" user. Mandatory for VNC proxying. See the <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/wiki" target="_blank">Wiki</a> for more info.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">VMID Start</td>
	<td class="fieldarea">
	<input type="text" size="35" name="start_vmid" id="start_vmid" value="'.$config->start_vmid.'"> Starting PVE VMID. Default is 100. Module will increment this until vacant VMID found.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Debug Mode</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="debug_mode" value="1" '. ($config->debug_mode=="1" ? "checked" : "").'> Whether or not you want Debug Logging enabled (WHMCS Module Log for Debugging >> /admin/logs/module-log)
	</label>
	</td>
	</tr>
	</table>
	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="save_config" id="save_config">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
	
	echo '</div>';

	echo '</div>'; 
	// End of tabbed content

	// Handle saving the configuration if the form was submitted
	if (isset($_POST['save_config'])) {
		save_config() ;
	}
}

// Import Guest sub-page handler (standalone, outside pvewhmcs_output)
// This function associates an existing PVE Guest in WHMCS as a new Client Service.
function import_guest() {
	$resultMsg = '';
	if (!empty($_POST['import_existing_guest'])) {
		$vmid = intval($_POST['import_vmid']);
		$userid = intval($_POST['import_clientid']);
		$productid = intval($_POST['import_productid']);
		$ipaddress = trim($_POST['import_ipv4']);
		$subnetmask = trim($_POST['import_subnet']);
		$gateway = trim($_POST['import_gateway']);
		$hostname = trim($_POST['import_hostname']);
		$vtype = ($_POST['import_vtype'] === 'lxc') ? 'lxc' : 'kvm';

		// Validate Client ID
		$client = Capsule::table('tblclients')->where('id', $userid)->where('status', 'Active')->first();
		if (!$client) {
			$resultMsg = '<div class="errorbox">No active WHMCS Client found with ID '.$userid.'</div>';
		} else {
			// Validate Product
			$product = Capsule::table('tblproducts')->where('id', $productid)->where('retired', 0)->first();
			if (!$product) {
				$resultMsg = '<div class="errorbox">No active WHMCS Product found with ID '.$productid.'</div>';
			} else {
				// Create WHMCS Service (Order)
				try {
					// First, get the first Server ID that matches the product's server group
					$serverRel = Capsule::table('tblservergroupsrel')->where('groupid', $product->servergroup)->first();
					$serverID = $serverRel ? $serverRel->serverid : 0;
					// Do the insertion to the tblhosting table
					$serviceID = Capsule::table('tblhosting')->insertGetId([
						'userid' => $userid,
						'packageid' => $productid,
						'regdate' => date('Y-m-d'),
						'domain' => $hostname,
						'paymentmethod' => 'banktransfer',
						'firstpaymentamount' => $product->paytype == 'free' ? 0 : $product->monthly,
						'amount' => $product->paytype == 'free' ? 0 : $product->monthly,
						'billingcycle' => 'Monthly',
						'nextduedate' => date('Y-m-d'),
						'nextinvoicedate' => date('Y-m-d'),
						'orderid' => 0,
						'domainstatus' => 'Active',
						'username' => 'root',
						'password' => '',
						'subscriptionid' => '',
						'promoid' => 0,
						'server' => $serverID,
						'dedicatedip' => $ipaddress,
						'assignedips' => $ipaddress,
						'ns1' => '',
						'ns2' => '',
						'diskusage' => 0,
						'disklimit' => 0,
						'bwusage' => 0,
						'bwlimit' => 0,
						'lastupdate' => date('Y-m-d H:i:s'),
						'suspendreason' => '',
						'overideautosuspend' => 0,
						'overidesuspenduntil' => '',
						'notes' => 'PVEWHMCS: Imported from Proxmox Guest VMID '.$vmid,
					]);
				} catch (Exception $e) {
					$resultMsg = '<div class="errorbox">Could not create WHMCS service: '.htmlspecialchars($e->getMessage()).'</div>';
					$serviceID = false;
				}
				if ($serviceID) {
					// Insert into module VMs table
					try {
						Capsule::table('mod_pvewhmcs_vms')->insert([
							'id' => $serviceID,
							'vmid' => $vmid,
							'user_id' => $userid,
							'vtype' => $vtype,
							'ipaddress' => $ipaddress,
							'subnetmask' => $subnetmask,
							'gateway' => $gateway,
							'created' => date('Y-m-d H:i:s'),
						]);
						$resultMsg = '<div class="successbox">Successfully imported PVE VMID '.$vmid.' (' . $vtype . ') as Service ' . $serviceID . ' (' . $product->name . ') for ' . $client->firstname . ' ' . $client->lastname . '. ' . $client->company . '</div>';
					} catch (Exception $e) {
						$resultMsg = '<div class="errorbox">Database error: '.htmlspecialchars($e->getMessage()).'</div>';
					}
				}
			}
		}
	}

	// Always show the form for easy further imports
	if (!empty($resultMsg)) echo $resultMsg;
	echo '<form method="post">';
	echo '<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">';
	echo '<tr><td class="fieldlabel">PVE VMID</td><td class="fieldarea"><input type="text" name="import_vmid" required></td></tr>';
	echo '<tr><td class="fieldlabel">Hostname</td><td class="fieldarea"><input type="text" name="import_hostname" required></td></tr>';

	// Active clients dropdown
	$clients = Capsule::table('tblclients')->where('status', 'Active')->orderBy('companyname')->orderBy('firstname')->orderBy('lastname')->get();
	echo '<tr><td class="fieldlabel">Target Client</td><td class="fieldarea"><select name="import_clientid" required>';
	foreach ($clients as $client) {
		$label = $client->id.' - '.($client->companyname ? $client->companyname.' - ' : '').$client->firstname.' '.$client->lastname;
		echo '<option value="'.$client->id.'">'.htmlspecialchars($label).'</option>';
	}
	echo '</select></td></tr>';
	
	// Product/Service dropdown (only Active products of Server type)
	$products = Capsule::table('tblproducts')->where('type', 'server')->where('retired', 0)->orderBy('name')->get();
	echo '<tr><td class="fieldlabel">Service</td><td class="fieldarea"><select name="import_productid" required>';
	foreach ($products as $product) {
		echo '<option value="'.$product->id.'">'.htmlspecialchars($product->name).'</option>';
	}
	echo '</select></td></tr>';
	
	// Guest Type dropdown
	echo '<tr><td class="fieldlabel">VM/CT</td><td class="fieldarea"><select name="import_vtype" required>';
	echo '<option value="kvm">(VM) QEMU</option>';
	echo '<option value="lxc">(CT) LXC</option>';
	echo '</select></td></tr>';
	
	// IPv4, Subnet, Gateway
	echo '<tr><td class="fieldlabel">IPv4</td><td class="fieldarea"><input type="text" name="import_ipv4" required></td></tr>';
	echo '<tr><td class="fieldlabel">Subnet</td><td class="fieldarea"><input type="text" name="import_subnet" required></td></tr>';
	echo '<tr><td class="fieldlabel">Gateway</td><td class="fieldarea"><input type="text" name="import_gateway" required></td></tr>';
	echo '</table>';
	echo '<div class="btn-container"><input type="submit" class="btn btn-primary" value="Import Guest" name="import_existing_guest" id="import_existing_guest"></div>';
	echo '</form>';
}

// MODULE CONFIG: Commit changes to the database
function save_config() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs')->update(
					[
						'vnc_secret' => $_POST['vnc_secret'],
						'start_vmid' => $_POST['start_vmid'],
						'debug_mode' => $_POST['debug_mode'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='Module Config saved.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='New options have been successfully saved.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=config");
	} catch (\Exception $e) {
		echo "Uh oh! That didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM: Add new KVM Plan
function kvm_plan_add() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">OS - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ostype">
	<option value="l26">Linux 6.x - 2.6 Kernel</option>
	<option value="l24">Linux 2.4 Kernel</option>
	<option value="solaris">Solaris Kernel</option>
	<option value="win11">Windows 11 / 2022</option>
	<option value="win10">Windows 10 / 2016 / 2019</option>
	<option value="win8">Windows 8.x / 2012 / 2012r2</option>
	<option value="win7">Windows 7 / 2008r2</option>
	<option value="wvista">Windows Vista / 2008</option>
	<option value="wxp">Windows XP / 2003</option>
	<option value="w2k">Windows 2000</option>
	<option value="other">Other</option>
	</select>
	Kernel type (Linux, Windows, etc).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Emulation</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="cpuemu">
	<option value="host">(Host) Host</option>
	<option value="kvm32">(QEMU) kvm32</option>
	<option value="kvm64">(QEMU) kvm64</option>
	<option value="max">(QEMU) Max</option>
	<option value="qemu32">(QEMU) qemu32</option>
	<option value="qemu64">(QEMU) qemu64</option>
	<option value="x86-64-v2">(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
	<option value="x86-64-v2-AES" selected="">(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
	<option value="x86-64-v3">(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
	<option value="x86-64-v4">(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
	<option value="486">(Intel) 486</option>
	<option value="Broadwell">(Intel) Broadwell</option>
	<option value="Broadwell-IBRS">(Intel) Broadwell-IBRS</option>
	<option value="Broadwell-noTSX">(Intel) Broadwell-noTSX</option>
	<option value="Broadwell-noTSX-IBRS">(Intel) Broadwell-noTSX-IBRS</option>
	<option value="Cascadelake-Server">(Intel) Cascadelake-Server</option>
	<option value="Cascadelake-Server-noTSX">(Intel) Cascadelake-Server-noTSX</option>
	<option value="Cascadelake-Server-v2">(Intel) Cascadelake-Server-v2</option>
	<option value="Cascadelake-Server-v4">(Intel) Cascadelake-Server-v4</option>
	<option value="Cascadelake-Server-v5">(Intel) Cascadelake-Server-v5</option>
	<option value="Conroe">(Intel) Conroe</option>
	<option value="Cooperlake">(Intel) Cooperlake</option>
	<option value="Cooperlake-v2">(Intel) Cooperlake-v2</option>
	<option value="Haswell">(Intel) Haswell</option>
	<option value="Haswell-IBRS">(Intel) Haswell-IBRS</option>
	<option value="Haswell-noTSX">(Intel) Haswell-noTSX</option>
	<option value="Haswell-noTSX-IBRS">(Intel) Haswell-noTSX-IBRS</option>
	<option value="Icelake-Client">(Intel) Icelake-Client</option>
	<option value="Icelake-Client-noTSX">(Intel) Icelake-Client-noTSX</option>
	<option value="Icelake-Server">(Intel) Icelake-Server</option>
	<option value="Icelake-Server-noTSX">(Intel) Icelake-Server-noTSX</option>
	<option value="Icelake-Server-v3">(Intel) Icelake-Server-v3</option>
	<option value="Icelake-Server-v4">(Intel) Icelake-Server-v4</option>
	<option value="Icelake-Server-v5">(Intel) Icelake-Server-v5</option>
	<option value="Icelake-Server-v6">(Intel) Icelake-Server-v6</option>
	<option value="IvyBridge">(Intel) IvyBridge</option>
	<option value="IvyBridge-IBRS">(Intel) IvyBridge-IBRS</option>
	<option value="KnightsMill">(Intel) KnightsMill</option>
	<option value="Nehalem">(Intel) Nehalem</option>
	<option value="Nehalem-IBRS">(Intel) Nehalem-IBRS</option>
	<option value="Penryn">(Intel) Penryn</option>
	<option value="SandyBridge">(Intel) SandyBridge</option>
	<option value="SandyBridge-IBRS">(Intel) SandyBridge-IBRS</option>
	<option value="SapphireRapids">(Intel) SapphireRapids</option>
	<option value="Skylake-Client">(Intel) Skylake-Client</option>
	<option value="Skylake-Client-IBRS">(Intel) Skylake-Client-IBRS</option>
	<option value="Skylake-Client-noTSX-IBRS">(Intel) Skylake-Client-noTSX-IBRS</option>
	<option value="Skylake-Client-v4">(Intel) Skylake-Client-v4</option>
	<option value="Skylake-Server">(Intel) Skylake-Server</option>
	<option value="Skylake-Server-IBRS">(Intel) Skylake-Server-IBRS</option>
	<option value="Skylake-Server-noTSX-IBRS">(Intel) Skylake-Server-noTSX-IBRS</option>
	<option value="Skylake-Server-v4">(Intel) Skylake-Server-v4</option>
	<option value="Skylake-Server-v5">(Intel) Skylake-Server-v5</option>
	<option value="Westmere">(Intel) Westmere</option>
	<option value="Westmere-IBRS">(Intel) Westmere-IBRS</option>
	<option value="pentium">(Intel) Pentium I</option>
	<option value="pentium2">(Intel) Pentium II</option>
	<option value="pentium3">(Intel) Pentium III</option>
	<option value="coreduo">(Intel) Core Duo</option>
	<option value="core2duo">(Intel) Core 2 Duo</option>
	<option value="athlon">(AMD) Athlon</option>
	<option value="phenom">(AMD) Phenom</option>
	<option value="EPYC">(AMD) EPYC</option>
	<option value="EPYC-IBPB">(AMD) EPYC-IBPB</option>
	<option value="EPYC-Milan">(AMD) EPYC-Milan</option>
	<option value="EPYC-Rome">(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2">(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3">(AMD) EPYC-v3</option>
	<option value="Opteron_G1">(AMD) Opteron_G1</option>
	<option value="Opteron_G2">(AMD) Opteron_G2</option>
	<option value="Opteron_G3">(AMD) Opteron_G3</option>
	<option value="Opteron_G4">(AMD) Opteron_G4</option>
	<option value="Opteron_G5">(AMD) Opteron_G5</option>
	</select>
	CPU emulation type. Default is x86-64 psABI v2-AES
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="1" required>
	The number of CPU Sockets. 1 - 4.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="1" required>
	The number of CPU Cores per socket. 1 - 32.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="0" required>
	Limit of CPU Usage. Note if the Server has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024. NOTE: Disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" value="2048" required>
	RAM space in Megabyte e.g 1024 = 1GB (default is 2GB)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Balloon</td>
	<td class="fieldarea">
	<input type="text" size="8" name="balloon" id="balloon" value="0" required>
	Balloon space in Megabyte e.g 1024 = 1GB (0 = disabled)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="10240" required>
	HDD/SSD storage space in Gigabyte e.g 1024 = 1TB (default is 10GB)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Format</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskformat">
	<option value="raw">Disk Image (raw)</option>
	<option selected="" value="qcow2">QEMU Image (qcow2)</option>
	<option value="vmdk">VMware Image (vmdk)</option>
	</select>
	Recommend "QEMU/qcow2" (so it can make Snapshots)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Cache</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskcache">
	<option selected="" value="">No Cache (Default)</option>
	<option value="directsync">Direct Sync</option>
	<option value="writethrough">Write Through</option>
	<option value="writeback">Write Back</option>
	<option value="unsafe">Write Back (Unsafe)</option>
	<option value="none">No Cache</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="disktype">
	<option selected="" value="virtio">Virtio</option>
	<option value="scsi">SCSI</option>
	<option value="sata">SATA</option>
	<option value="ide">IDE</option>
	</select>
	Virtio is the fastest option, then SCSI, then SATA, etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="0" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="local" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">NIC - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option selected="" value="e1000">Intel E1000 (Reliable)</option>
	<option value="virtio">VirtIO (Paravirtualized)</option>
	<option value="rtl8139">Realtek RTL8139</option>
	<option value="vmxnet3">VMware vmxnet3</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="0">
	Network Rate Limit in Megabit/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Bandwidth Limit in Gigabytes. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6 Conf.</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0">Off</option>
	<option value="auto">SLAAC</option>
	<option value="dhcp">DHCPv6</option>
	<option value="prefix">Prefix</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Mode</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmode">
	<option value="bridge">Bridge</option>
	<option value="nat">NAT</option>
	<option value="none">No Network</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="vmbr">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="0">
	Interface ID. PVE Bridge default is 0, for "vmbr0". PVE SDN, leave blank.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	Hardware Virt?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="kvm" value="1" checked> Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot VM?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" checked> Specifies whether a VM will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewkvmplan" id="addnewkvmplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Edit a KVM Plan
function kvm_plan_edit($id) {
	$plan= Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->get()[0];
	if (empty($plan)) {
		echo 'Plan Not found' ;
		return false ;
	}
	echo '<pre>' ;
		//print_r($plan) ;
	echo '</pre>' ;
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">OS - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ostype">
	<option value="l26" ' . ($plan->ostype == "l26" ? "selected" : "") . '>Linux 6.x - 2.6 Kernel</option>
	<option value="l24" ' . ($plan->ostype == "l24" ? "selected" : "") . '>Linux 2.4 Kernel</option>
	<option value="solaris" ' . ($plan->ostype == "solaris" ? "selected" : "") . '>Solaris Kernel</option>
	<option value="win11" ' . ($plan->ostype == "win11" ? "selected" : "") . '>Windows 11 / 2022</option>
	<option value="win10" ' . ($plan->ostype == "win10" ? "selected" : "") . '>Windows 10 / 2016 / 2019</option>
	<option value="win8" ' . ($plan->ostype == "win8" ? "selected" : "") . '>Windows 8.x / 2012 / 2012r2</option>
	<option value="win7" ' . ($plan->ostype == "win7" ? "selected" : "") . '>Windows 7 / 2008r2</option>
	<option value="wvista" ' . ($plan->ostype == "wvista" ? "selected" : "") . '>Windows Vista / 2008</option>
	<option value="wxp" ' . ($plan->ostype == "wxp" ? "selected" : "") . '>Windows XP / 2003</option>
	<option value="w2k" ' . ($plan->ostype == "w2k" ? "selected" : "") . '>Windows 2000</option>
	<option value="other" ' . ($plan->ostype == "other" ? "selected" : "") . '>Other</option>
	</select>
	Kernel type (Linux, Windows, etc).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Emulation</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="cpuemu">
	<option value="host" ' . ($plan->cpuemu == "host" ? "selected" : "") . '>Host</option>
	<option value="kvm32" ' . ($plan->cpuemu == "kvm32" ? "selected" : "") . '>(QEMU) kvm32</option>
	<option value="kvm64" ' . ($plan->cpuemu == "kvm64" ? "selected" : "") . '>(QEMU) kvm64</option>
	<option value="max" ' . ($plan->cpuemu == "max" ? "selected" : "") . '>(QEMU) Max</option>
	<option value="qemu32" ' . ($plan->cpuemu == "qemu32" ? "selected" : "") . '>(QEMU) qemu32</option>
	<option value="qemu64" ' . ($plan->cpuemu == "qemu64" ? "selected" : "") . '>(QEMU) qemu64</option>
	<option value="x86-64-v2" ' . ($plan->cpuemu == "x86-64-v2" ? "selected" : "") . '>(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
	<option value="x86-64-v2-AES" ' . ($plan->cpuemu == "x86-64-v2-AES" ? "selected" : "") . '>(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
	<option value="x86-64-v3" ' . ($plan->cpuemu == "x86-64-v3" ? "selected" : "") . '>(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
	<option value="x86-64-v4" ' . ($plan->cpuemu == "x86-64-v4" ? "selected" : "") . '>(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
	<option value="486" ' . ($plan->cpuemu == "486" ? "selected" : "") . '>(Intel) 486</option>
	<option value="Broadwell" ' . ($plan->cpuemu == "Broadwell" ? "selected" : "") . '>(Intel) Broadwell</option>
	<option value="Broadwell-IBRS" ' . ($plan->cpuemu == "Broadwell-IBRS" ? "selected" : "") . '>(Intel) Broadwell-IBRS</option>
	<option value="Broadwell-noTSX" ' . ($plan->cpuemu == "Broadwell-noTSX" ? "selected" : "") . '>(Intel) Broadwell-noTSX</option>
	<option value="Broadwell-noTSX-IBRS" ' . ($plan->cpuemu == "Broadwell-noTSX-IBRS" ? "selected" : "") . '>(Intel) Broadwell-noTSX-IBRS</option>
	<option value="Cascadelake-Server" ' . ($plan->cpuemu == "Cascadelake-Server" ? "selected" : "") . '>(Intel) Cascadelake-Server</option>
	<option value="Cascadelake-Server-noTSX" ' . ($plan->cpuemu == "Cascadelake-Server-noTSX" ? "selected" : "") . '>(Intel) Cascadelake-Server-noTSX</option>
	<option value="Cascadelake-Server-v2" ' . ($plan->cpuemu == "Cascadelake-Server-v2" ? "selected" : "") . '>(Intel) Cascadelake-Server V2</option>
	<option value="Cascadelake-Server-v4" ' . ($plan->cpuemu == "Cascadelake-Server-v4" ? "selected" : "") . '>(Intel) Cascadelake-Server V4</option>
	<option value="Cascadelake-Server-v5" ' . ($plan->cpuemu == "Cascadelake-Server-v5" ? "selected" : "") . '>(Intel) Cascadelake-Server V5</option>
	<option value="Conroe" ' . ($plan->cpuemu == "Conroe" ? "selected" : "") . '>(Intel) Conroe</option>
	<option value="Cooperlake" ' . ($plan->cpuemu == "Cooperlake" ? "selected" : "") . '>(Intel) Cooperlake</option>
	<option value="Cooperlake-v2" ' . ($plan->cpuemu == "Cooperlake-v2" ? "selected" : "") . '>(Intel) Cooperlake V2</option>
	<option value="Haswell" ' . ($plan->cpuemu == "Haswell" ? "selected" : "") . '>(Intel) Haswell</option>
	<option value="Haswell-IBRS" ' . ($plan->cpuemu == "Haswell-IBRS" ? "selected" : "") . '>(Intel) Haswell-IBRS</option>
	<option value="Haswell-noTSX" ' . ($plan->cpuemu == "Haswell-noTSX" ? "selected" : "") . '>(Intel) Haswell-noTSX</option>
	<option value="Haswell-noTSX-IBRS" ' . ($plan->cpuemu == "Haswell-noTSX-IBRS" ? "selected" : "") . '>(Intel) Haswell-noTSX-IBRS</option>
	<option value="Icelake-Client" ' . ($plan->cpuemu == "Icelake-Client" ? "selected" : "") . '>(Intel) Icelake-Client</option>
	<option value="Icelake-Client-noTSX" ' . ($plan->cpuemu == "Icelake-Client-noTSX" ? "selected" : "") . '>(Intel) Icelake-Client-noTSX</option>
	<option value="Icelake-Server" ' . ($plan->cpuemu == "Icelake-Server" ? "selected" : "") . '>(Intel) Icelake-Server</option>
	<option value="Icelake-Server-noTSX" ' . ($plan->cpuemu == "Icelake-Server-noTSX" ? "selected" : "") . '>(Intel) Icelake-Server-noTSX</option>
	<option value="Icelake-Server-v3" ' . ($plan->cpuemu == "Icelake-Server-v3" ? "selected" : "") . '>(Intel) Icelake-Server V3</option>
	<option value="Icelake-Server-v4" ' . ($plan->cpuemu == "Icelake-Server-v4" ? "selected" : "") . '>(Intel) Icelake-Server V4</option>
	<option value="Icelake-Server-v5" ' . ($plan->cpuemu == "Icelake-Server-v5" ? "selected" : "") . '>(Intel) Icelake-Server V5</option>
	<option value="Icelake-Server-v6" ' . ($plan->cpuemu == "Icelake-Server-v6" ? "selected" : "") . '>(Intel) Icelake-Server V6</option>
	<option value="IvyBridge" ' . ($plan->cpuemu == "IvyBridge" ? "selected" : "") . '>(Intel) IvyBridge</option>
	<option value="IvyBridge-IBRS" ' . ($plan->cpuemu == "IvyBridge-IBRS" ? "selected" : "") . '>(Intel) IvyBridge-IBRS</option>
	<option value="KnightsMill" ' . ($plan->cpuemu == "KnightsMill" ? "selected" : "") . '>(Intel) KnightsMill</option>
	<option value="Nehalem" ' . ($plan->cpuemu == "Nehalem" ? "selected" : "") . '>(Intel) Nehalem</option>
	<option value="Nehalem-IBRS" ' . ($plan->cpuemu == "Nehalem-IBRS" ? "selected" : "") . '>(Intel) Nehalem-IBRS</option>
	<option value="Penryn" ' . ($plan->cpuemu == "Penryn" ? "selected" : "") . '>(Intel) Penryn</option>
	<option value="SandyBridge" ' . ($plan->cpuemu == "SandyBridge" ? "selected" : "") . '>(Intel) SandyBridge</option>
	<option value="SandyBridge-IBRS" ' . ($plan->cpuemu == "SandyBridge-IBRS" ? "selected" : "") . '>(Intel) SandyBridge-IBRS</option>
	<option value="SapphireRapids" ' . ($plan->cpuemu == "SapphireRapids" ? "selected" : "") . '>(Intel) Sapphire Rapids</option>
	<option value="Skylake-Client" ' . ($plan->cpuemu == "Skylake-Client" ? "selected" : "") . '>(Intel) Skylake-Client</option>
	<option value="Skylake-Client-IBRS" ' . ($plan->cpuemu == "Skylake-Client-IBRS" ? "selected" : "") . '>(Intel) Skylake-Client-IBRS</option>
	<option value="Skylake-Client-noTSX-IBRS" ' . ($plan->cpuemu == "Skylake-Client-noTSX-IBRS" ? "selected" : "") . '>(Intel) Skylake-Client-noTSX-IBRS</option>
	<option value="Skylake-Client-v4" ' . ($plan->cpuemu == "Skylake-Client-v4" ? "selected" : "") . '>(Intel) Skylake-Client V4</option>
	<option value="Skylake-Server" ' . ($plan->cpuemu == "Skylake-Server" ? "selected" : "") . '>(Intel) Skylake-Server</option>
	<option value="Skylake-Server-IBRS" ' . ($plan->cpuemu == "Skylake-Server-IBRS" ? "selected" : "") . '>(Intel) Skylake-Server-IBRS</option>
	<option value="Skylake-Server-noTSX-IBRS" ' . ($plan->cpuemu == "Skylake-Server-noTSX-IBRS" ? "selected" : "") . '>(Intel) Skylake-Server-noTSX-IBRS</option>
	<option value="Skylake-Server-v4" ' . ($plan->cpuemu == "Skylake-Server-v4" ? "selected" : "") . '>(Intel) Skylake-Server V4</option>
	<option value="Skylake-Server-v5" ' . ($plan->cpuemu == "Skylake-Server-v5" ? "selected" : "") . '>(Intel) Skylake-Server V5</option>
	<option value="Westmere" ' . ($plan->cpuemu == "Westmere" ? "selected" : "") . '>(Intel) Westmere</option>
	<option value="Westmere-IBRS" ' . ($plan->cpuemu == "Westmere-IBRS" ? "selected" : "") . '>(Intel) Westmere-IBRS</option>
	<option value="pentium" ' . ($plan->cpuemu == "pentium" ? "selected" : "") . '>(Intel) Pentium I</option>
	<option value="pentium2" ' . ($plan->cpuemu == "pentium2" ? "selected" : "") . '>(Intel) Pentium II</option>
	<option value="pentium3" ' . ($plan->cpuemu == "pentium3" ? "selected" : "") . '>(Intel) Pentium III</option>
	<option value="coreduo" ' . ($plan->cpuemu == "coreduo" ? "selected" : "") . '>(Intel) Core Duo</option>
	<option value="core2duo" ' . ($plan->cpuemu == "core2duo" ? "selected" : "") . '>(Intel) Core 2 Duo</option>
	<option value="athlon" ' . ($plan->cpuemu == "athlon" ? "selected" : "") . '>(AMD) Athlon</option>
	<option value="phenom" ' . ($plan->cpuemu == "phenom" ? "selected" : "") . '>(AMD) Phenom</option>
	<option value="EPYC" ' . ($plan->cpuemu == "EPYC" ? "selected" : "") . '>(AMD) EPYC</option>
	<option value="EPYC-IBPB" ' . ($plan->cpuemu == "EPYC-IBPB" ? "selected" : "") . '>(AMD) EPYC-IBPB</option>
	<option value="EPYC-Milan" ' . ($plan->cpuemu == "EPYC-Milan" ? "selected" : "") . '>(AMD) EPYC-Milan</option>
	<option value="EPYC-Rome" ' . ($plan->cpuemu == "EPYC-Rome" ? "selected" : "") . '>(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2" ' . ($plan->cpuemu == "EPYC-Rome-v2" ? "selected" : "") . '>(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3" ' . ($plan->cpuemu == "EPYC-v3" ? "selected" : "") . '>(AMD) EPYC-v3</option>
	<option value="Opteron_G1" ' . ($plan->cpuemu == "Opteron_G1" ? "selected" : "") . '>(AMD) Opteron_G1</option>
	<option value="Opteron_G2" ' . ($plan->cpuemu == "Opteron_G2" ? "selected" : "") . '>(AMD) Opteron_G2</option>
	<option value="Opteron_G3" ' . ($plan->cpuemu == "Opteron_G3" ? "selected" : "") . '>(AMD) Opteron_G3</option>
	<option value="Opteron_G4" ' . ($plan->cpuemu == "Opteron_G4" ? "selected" : "") . '>(AMD) Opteron_G4</option>
	<option value="Opteron_G5" ' . ($plan->cpuemu == "Opteron_G5" ? "selected" : "") . '>(AMD) Opteron_G5</option>
	</select>
	CPU emulation type. Default is x86-64 psABI v2-AES
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="'.$plan->cpus.'" required>
	The number of CPU sockets. 1 - 4.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="'.$plan->cores.'" required>
	The number of CPU cores per socket. 1 - 32.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
	Limit of CPU usage. Note if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
	Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. NOTE: You can disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Balloon</td>
	<td class="fieldarea">
	<input type="text" size="8" name="balloon" id="balloon" required value="'.$plan->balloon.'">
	Balloon space in Megabyte e.g 1024 = 1GB (0 = disabled)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required value="'.$plan->disk.'">
	HDD/SSD storage space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Format</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskformat">
	<option value="raw" '. ($plan->diskformat=="raw" ? "selected" : "").'>Disk Image (raw)</option>
	<option value="qcow2" '. ($plan->diskformat=="qcow2" ? "selected" : "").'>QEMU image (qcow2)</option>
	<option value="vmdk" '. ($plan->diskformat=="vmdk" ? "selected" : "").'>VMware image (vmdk)</option>
	</select>
	Recommend "QEMU/qcow2 format" (to make Snapshots)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Cache</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskcache">
	<option value="" '. ($plan->diskcache=="" ? "selected" : "").'>No Cache (Default)</option>
	<option value="directsync" '. ($plan->diskcache=="directsync" ? "selected" : "").'>Direct Sync</option>
	<option value="writethrough" '. ($plan->diskcache=="writethrough" ? "selected" : "").'>Write Through</option>
	<option value="writeback" '. ($plan->diskcache=="writeback" ? "selected" : "").'>Write Back</option>
	<option value="unsafe" '. ($plan->diskcache=="unsafe" ? "selected" : "").'>Write Back (Unsafe)</option>
	<option value="none" '. ($plan->diskcache=="none" ? "selected" : "").'>No Cache</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="disktype">
	<option value="virtio" '. ($plan->disktype=="virtio" ? "selected" : "").'>Virtio</option>
	<option value="scsi" '. ($plan->disktype=="scsi" ? "selected" : "").'>SCSI</option>
	<option value="sata" '. ($plan->disktype=="sata" ? "selected" : "").'>SATA</option>
	<option value="ide" '. ($plan->disktype=="ide" ? "selected" : "").'>IDE</option>
	</select>
	Virtio is the fastest option, then SCSI, then SATA, etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" required value="'.$plan->diskio.'">
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" required value="'.$plan->storage.'">
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">NIC - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option value="e1000" '. ($plan->netmodel=="e1000" ? "selected" : "").'>Intel E1000 (Reliable)</option>
	<option value="virtio" '. ($plan->netmodel=="virtio" ? "selected" : "").'>VirtIO (Paravirt)</option>
	<option value="rtl8139" '. ($plan->netmodel=="rtl8139" ? "selected" : "").'>Realtek RTL8139</option>
	<option value="vmxnet3" '. ($plan->netmodel=="vmxnet3" ? "selected" : "").'>VMware vmxnet3</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="'.$plan->netrate.'">
	Network Rate Limit in Megabit. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="'.$plan->bw.'">
	Monthly Bandwidth Limit in Gigabyte. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6 Conf.</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0" '. ($plan->ipv6=="0" ? "selected" : "").'>Off</option>
	<option value="auto" '. ($plan->ipv6=="auto" ? "selected" : "").'>SLAAC</option>
	<option value="dhcp" '. ($plan->ipv6=="dhcp" ? "selected" : "").'>DHCPv6</option>
	<option value="prefix" '. ($plan->ipv6=="prefix" ? "selected" : "").'>Prefix</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Mode</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmode">
	<option value="bridge" '. ($plan->netmode=="bridge" ? "selected" : "").'>Bridge</option>
	<option value="nat" '. ($plan->netmode=="nat" ? "selected" : "").'>NAT</option>
	<option value="none" '. ($plan->netmode=="none" ? "selected" : "").'>No network</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="'.$plan->bridge.'">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
	Interface ID. PVE Bridge default is 0, for "vmbr0". PVE SDN, leave blank.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	Hardware Virt?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="kvm" value="1" '. ($plan->kvm=="1" ? "checked" : "").'> Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot VM?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a VM will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="updatekvmplan" id="saveeditedkvmplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Add an LXC Plan
function lxc_plan_add() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="1" required>
	Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required>
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap">
	Swap space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required>
	HDD/SSD storage space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="0" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="local" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="vmbr">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="0">
	Interface ID. PVE Bridge default is 0, for "vmbr0". PVE SDN, leave blank.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="0">
	Network Rate Limit in Megabit/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Data - Monthly</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Bandwidth Limit in Gigabytes. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6 Conf.</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0">Off</option>
	<option value="auto">SLAAC</option>
	<option value="dhcp">DHCPv6</option>
	<option value="prefix">Prefix</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot CT?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" checked> Specifies whether a CT will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewlxcplan" id="addnewlxcplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Edit an LXC Plan
function lxc_plan_edit($id) {
	$plan= Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->get()[0];
	if (empty($plan)) {
		echo 'Plan Not found' ;
		return false ;
	}
	echo '<pre>' ;
		//print_r($plan) ;
	echo '</pre>' ;

	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
	Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap" value="'.$plan->swap.'">
	Swap space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="'.$plan->disk.'" required>
	HDD/SSD storage space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="'.$plan->diskio.'" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="'.$plan->storage.'" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="'.$plan->bridge.'">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
	Interface ID. PVE Bridge default is 0, for "vmbr0". PVE SDN, leave blank.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="'.$plan->netrate.'">
	Network Rate Limit in Megabit/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="'.$plan->bw.'">
	Monthly Bandwidth Limit in Gigabytes. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6 Conf.</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0" '. ($plan->ipv6=="0" ? "selected" : "").'>Off</option>
	<option value="auto" '. ($plan->ipv6=="auto" ? "selected" : "").'>SLAAC</option>
	<option value="dhcp" '. ($plan->ipv6=="dhcp" ? "selected" : "").'>DHCPv6</option>
	<option value="prefix" '. ($plan->ipv6=="prefix" ? "selected" : "").'>Prefix</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot CT?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" value="1" name="onboot" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a CT will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="updatelxcplan" id="updatelxcplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM ACTION: Save KVM Plan
function save_kvm_plan() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_plans')->insert(
					[
						'title' => $_POST['title'],
						'vmtype' => 'kvm',
						'ostype' => $_POST['ostype'],
						'cpus' => $_POST['cpus'],
						'cpuemu' => $_POST['cpuemu'],
						'cores' => $_POST['cores'],
						'cpulimit' => $_POST['cpulimit'],
						'cpuunits' => $_POST['cpuunits'],
						'memory' => $_POST['memory'],
						'balloon' => $_POST['balloon'],
						'disk' => $_POST['disk'],
						'diskformat' => $_POST['diskformat'],
						'diskcache' => $_POST['diskcache'],
						'disktype' => $_POST['disktype'],
						'diskio' => $_POST['diskio'],
						'storage' => $_POST['storage'],
						'netmode' => $_POST['netmode'],
						'bridge' => $_POST['bridge'],
						'vmbr' => $_POST['vmbr'],
						'netmodel' => $_POST['netmodel'],
						'vlanid' => $_POST['vlanid'],
						'netrate' => $_POST['netrate'],
						'bw' => $_POST['bw'],
						'ipv6' => $_POST['ipv6'],
						'kvm' => $_POST['kvm'],
						'onboot' => $_POST['onboot'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='QEMU Plan added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Saved the QEMU Plan successfully.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM ACTION: Update KVM Plan
function update_kvm_plan() {
	Capsule::table('mod_pvewhmcs_plans')
	->where('id', $_GET['id'])
	->update(
		[
			'title' => $_POST['title'],
			'vmtype' => 'kvm',
			'ostype' => $_POST['ostype'],
			'cpus' => $_POST['cpus'],
			'cpuemu' => $_POST['cpuemu'],
			'cores' => $_POST['cores'],
			'cpulimit' => $_POST['cpulimit'],
			'cpuunits' => $_POST['cpuunits'],
			'memory' => $_POST['memory'],
			'balloon' => $_POST['balloon'],
			'disk' => $_POST['disk'],
			'diskformat' => $_POST['diskformat'],
			'diskcache' => $_POST['diskcache'],
			'disktype' => $_POST['disktype'],
			'diskio' => $_POST['diskio'],
			'storage' => $_POST['storage'],
			'netmode' => $_POST['netmode'],
			'bridge' => $_POST['bridge'],
			'vmbr' => $_POST['vmbr'],
			'netmodel' => $_POST['netmodel'],
			'vlanid' => $_POST['vlanid'],
			'netrate' => $_POST['netrate'],
			'bw' => $_POST['bw'],
			'ipv6' => $_POST['ipv6'],
			'kvm' => $_POST['kvm'],
			'onboot' => $_POST['onboot'],
		]
	);
	$_SESSION['pvewhmcs']['infomsg']['title']='QEMU Plan updated.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Updated the QEMU Plan successfully. (Updating plans will not alter existing VMs)' ;
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
}

// MODULE FORM ACTION: Remove Plan
function remove_plan($id) {
	Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->delete();
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	$_SESSION['pvewhmcs']['infomsg']['title']='Plan Deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Selected Item deleted successfully.' ;
}

// MODULE FORM ACTION: Save LXC Plan
function save_lxc_plan() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_plans')->insert(
					[
						'title' => $_POST['title'],
						'vmtype' => 'lxc',
						'cores' => $_POST['cores'],
						'cpulimit' => $_POST['cpulimit'],
						'cpuunits' => $_POST['cpuunits'],
						'memory' => $_POST['memory'],
						'swap' => $_POST['swap'],
						'disk' => $_POST['disk'],
						'diskio' => $_POST['diskio'],
						'storage' => $_POST['storage'],
						'bridge' => $_POST['bridge'],
						'vmbr' => $_POST['vmbr'],
						'netmodel' => $_POST['netmodel'],
						'vlanid' => $_POST['vlanid'],
						'netrate' => $_POST['netrate'],
						'bw' => $_POST['bw'],
						'ipv6' => $_POST['ipv6'],
						'onboot' => $_POST['onboot'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='New LXC Plan added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Saved the LXC Plan successfully.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM ACTION: Update LXC Plan
function update_lxc_plan() {
	Capsule::table('mod_pvewhmcs_plans')
	->where('id', $_GET['id'])
	->update(
		[
			'title' => $_POST['title'],
			'vmtype' => 'lxc',
			'cores' => $_POST['cores'],
			'cpulimit' => $_POST['cpulimit'],
			'cpuunits' => $_POST['cpuunits'],
			'memory' => $_POST['memory'],
			'swap' => $_POST['swap'],
			'disk' => $_POST['disk'],
			'diskio' => $_POST['diskio'],
			'storage' => $_POST['storage'],
			'bridge' => $_POST['bridge'],
			'vmbr' => $_POST['vmbr'],
			'netmodel' => $_POST['netmodel'],
			'vlanid' => $_POST['vlanid'],
			'netrate' => $_POST['netrate'],
			'bw' => $_POST['bw'],
			'ipv6' => $_POST['ipv6'],
			'onboot' => $_POST['onboot'],
		]
	);
	$_SESSION['pvewhmcs']['infomsg']['title']='LXC Plan updated.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Updated the LXC Plan successfully. (Updating plans will not alter existing CTs)' ;
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
}

// IP POOLS: List all Pools
function list_ip_pools() {
	echo '<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=new_ip_pool"><i class="fa fa-plus-square"></i>&nbsp; New IPv4 Pool</a>';
	echo '<table class="datatable"><tr><th>ID</th><th>Pool</th><th>Gateway</th><th>Action</th></tr>';
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $pool) {
		echo '<tr>';
		echo '<td>'.$pool->id . PHP_EOL .'</td>';
		echo '<td>'.$pool->title . PHP_EOL .'</td>';
		echo '<td>'.$pool->gateway . PHP_EOL .'</td>';
		echo '<td>
		<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=list_ips&amp;id='.$pool->id.'"><img height="16" width="16" border="0" alt="Info" src="images/edit.gif"></a>
		<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=removeippool&amp;id='.$pool->id.'" onclick="return confirm(\'Pool and all IPv4 Addresses assigned to it will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Remove" src="images/delete.gif"></a>
		</td>' ;
		echo '</tr>' ;
	}
	echo '</table>';
}

// IP POOL FORM: Add IP Pool
function add_ip_pool() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Pool Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	<td class="fieldlabel">IPv4 Gateway</td>
	<td class="fieldarea">
	<input type="text" size="25" name="gateway" id="gateway" required>
	Gateway address of the pool
	</td>
	</tr>
	</table>
	<input type="submit" class="btn btn-primary" name="newIPpool" value="Save"/>
	</form>
	';
}

// IP POOL FORM ACTION: Save Pool
function save_ip_pool() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_ip_pools')->insert(
					[
						'title' => $_POST['title'],
						'gateway' => $_POST['gateway'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='New IPv4 Pool added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='New IPv4 Pool saved successfully.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ip_pools");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// IP POOL FORM ACTION: Remove Pool
function removeIpPool($id) {
	Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $id)->delete();
	Capsule::table('mod_pvewhmcs_ip_pools')->where('id', '=', $id)->delete();

	header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ip_pools");
	$_SESSION['pvewhmcs']['infomsg']['title']='IPv4 Pool Deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Deleted the IPv4 Pool successfully.' ;
}

// IP POOL FORM ACTION: Add IP to Pool
function add_ip_2_pool() {
	require_once(ROOTDIR.'/modules/addons/pvewhmcs/Ipv4/Subnet.php');
	echo '<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">IPv4 Pool</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="pool_id">';
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $pool) {
		echo '<option value="'.$pool->id.'">'.$pool->title.'</option>';
		$gateways[]=$pool->gateway ;
	}
	echo '</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Address/Prefix</td>
	<td class="fieldarea">
	<input type="text" name="ipblock"/>
	IPv4 prefix with CIDR e.g. 172.16.255.230/27, or for single /32 address don\'t use CIDR
	</td>
	</tr>
	</table>
	<input type="submit" name="assignIP2pool" value="Add"/>
	</form>';
	if (isset($_POST['assignIP2pool'])) {
			// check if single IP address
		if ((strpos($_POST['ipblock'],'/'))!=false) {
			$subnet=Ipv4_Subnet::fromString($_POST['ipblock']);
			$ips = $subnet->getIterator();
			foreach($ips as $ip) {
				if (!in_array($ip, $gateways)) {
					Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
						[
							'pool_id' => $_POST['pool_id'],
							'ipaddress' => $ip,
							'mask' => $subnet->getNetmask(),
						]
					);
				}
			}
		}
		else {
			if (!in_array($_POST['ipblock'], $gateways)) {
				Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
					[
						'pool_id' => $_POST['pool_id'],
						'ipaddress' => $_POST['ipblock'],
						'mask' => '255.255.255.255',
					]
				);
			}
		}
		header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ips&id=".$_POST['pool_id']);
		$_SESSION['pvewhmcs']['infomsg']['title']='IPv4 Address/Blocks added to Pool.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='You can remove IPv4 Addresses from the pool.' ;
	}
}

// IP POOL FORM: List IPs in Pool
function list_ips() {
		//echo '<script>$(function() {$( "#dialog" ).dialog();});</script>' ;
		//echo '<div id="dialog">' ;
	echo '<table class="datatable"><tr><th>IPv4 Address</th><th>Subnet Mask</th><th>Action</th></tr>' ;
	foreach (Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $_GET['id'])->get() as $ip) {
		echo '<tr><td>'.$ip->ipaddress.'</td><td>'.$ip->mask.'</td><td>';
		if (count(Capsule::table('mod_pvewhmcs_vms')->where('ipaddress','=',$ip->ipaddress)->get())>0)
			echo 'is in use' ;
		else
			echo '<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=removeip&amp;pool_id='.$ip->pool_id.'&amp;id='.$ip->id.'" onclick="return confirm(\'IPv4 Address will be deleted from the pool, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>';
		echo '</td></tr>';
	}
	echo '</table>' ;

}

// IP POOL FORM ACTION: Remove IP from Pool
function removeip($id,$pool_id) {
	Capsule::table('mod_pvewhmcs_ip_addresses')->where('id', '=', $id)->delete();
	header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ips&id=".$pool_id);
	$_SESSION['pvewhmcs']['infomsg']['title']='IPv4 Address deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Deleted selected item successfully.' ;
}
?>