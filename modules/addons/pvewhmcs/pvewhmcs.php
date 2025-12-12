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
		"version" => pvewhmcs_version(),
		"author" => "The Network Crew Pty Ltd",
		'language' => 'English'
	);
	return $configarray;
}

// VERSION: also stored in repo/version (for update-available checker)
function pvewhmcs_version(){
	return "1.3.1";
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
	// NOTE: Disabled/Deleted via #160
	// Capsule::statement('drop table mod_pvewhmcs_ip_addresses,mod_pvewhmcs_ip_pools,mod_pvewhmcs_plans,mod_pvewhmcs_vms,mod_pvewhmcs');
	
	// Return the assumed result (change?)
	return array('status'=>'success','description'=>'Proxmox VE for WHMCS successfully deactivated. Database tables/data retained.');
}

// WHMCS MODULE: Upgrade
function pvewhmcs_upgrade($vars) {
	// This function gets passed the old ver once post-update, hence lt check
	$currentlyInstalledVersion = $vars['version'];

	// SQL Operations for v1.2.9/10 version
	if (version_compare($currentlyInstalledVersion, '1.2.10', 'lt')) {
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

	// SQL Operations for v1.2.12 version
	if (version_compare($currentlyInstalledVersion, '1.2.12', 'lt')) {
		$schema = Capsule::schema();

		// Add the column "unpriv" to the mod_pvewhmcs_plans table
		if (!$schema->hasColumn('mod_pvewhmcs_plans', 'unpriv')) {
			$schema->table('mod_pvewhmcs_plans', function ($table) {
				$table->integer('unpriv')->default(0)->after('balloon');
			});
		}
	}

	// SQL Operations for v1.2.17 version
	if (version_compare($currentlyInstalledVersion, '1.2.17', 'lt')) {
	    try {
	        Capsule::schema()->table('mod_pvewhmcs_plans', function ($table) {
	            $table->smallInteger('cpus')->unsigned()->nullable()->change();
	            $table->smallInteger('cores')->unsigned()->nullable()->change();
	            $table->integer('memory')->unsigned()->default(0)->change();
	            $table->integer('swap')->unsigned()->nullable()->change();
	            $table->integer('disk')->unsigned()->nullable()->change();
	            $table->tinyInteger('vmbr')->unsigned()->nullable()->change();
	            $table->integer('netrate')->default(0)->change();
	            $table->integer('bw')->unsigned()->default(0)->change();
	            $table->integer('vlanid')->nullable()->change();
	            $table->integer('balloon')->default(0)->change();
	            $table->tinyInteger('unpriv')->unsigned()->default(0)->change();
	        });
	    } catch (\Throwable $e) {
	        // Debug logging (same style as ClientArea)
			if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
				logModuleCall(
					'pvewhmcs',
					__FUNCTION__,
					'Attempting v1.2.17 database upgrade failed.',
					$e->getMessage()
				);
			}
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

/**
 * Fetch RRD statistics from Proxmox with graceful error handling (Admin Area).
 * 
 * Proxmox RRD schema changed in PVE 9 from pve2-{type} to pve-{type}-9.0.
 * The ds parameter names (cpu, mem, netin, netout, etc.) remain valid.
 * 
 * RRD data may be unavailable when:
 *   - Node/VM was just created (RRD takes ~60s to populate)
 *   - RRD schema migration is incomplete on the PVE host
 *   - RRD files are corrupted or missing
 * 
 * @param PVE2_API $proxmox    The Proxmox API client instance
 * @param string   $path       The RRD API path (e.g., /nodes/{node}/rrd)
 * @param string   $timeframe  RRD timeframe: 'hour', 'day', 'week', 'month', 'year'
 * @param string   $ds         Data source(s): 'cpu', 'mem', 'netin,netout', etc.
 * @return string|null         Base64-encoded PNG image, or null if unavailable
 */
function pvewhmcs_addon_fetch_rrd($proxmox, $path, $timeframe, $ds) {
	$rrd_params = '?timeframe=' . $timeframe . '&ds=' . $ds . '&cf=AVERAGE';
	
	try {
		$rrd_data = $proxmox->get($path . $rrd_params);
		
		if (isset($rrd_data['image']) && !empty($rrd_data['image'])) {
			$image = utf8_decode($rrd_data['image']);
			return base64_encode($image);
		}
	} catch (Exception $e) {
		// RRD data unavailable - log if debug mode on
		if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
			logModuleCall(
				'pvewhmcs',
				'pvewhmcs_addon_fetch_rrd',
				'RRD fetch failed: ' . $path . ' (' . $ds . ', ' . $timeframe . ')',
				$e->getMessage()
			);
		}
	}
	
	return null;
}

// ADMIN MODULE GUI: output (HTML etc)
function pvewhmcs_output($vars) {
	$modulelink = $vars['modulelink'];

	// Check for update and report if available
	if (!empty(is_pvewhmcs_outdated())) {
		$_SESSION['pvewhmcs']['infomsg']['title']='Proxmox VE for WHMCS: New version available!' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases/latest" target="_blank">https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases/latest</a>' ;
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
    	$_GET['tab'] = 'nodes';
	}

	// Start the HTML output for the Admin GUI
	echo '
	<div id="clienttabs">
	<ul class="nav nav-tabs admin-tabs">
	<li class="'.($_GET['tab']=="nodes" ? "active" : "").'"><a id="tabLink1" data-toggle="tab" role="tab" href="#nodes">Nodes</a></li>
	<li class="'.($_GET['tab']=="guests" ? "active" : "").'"><a id="tabLink2" data-toggle="tab" role="tab" href="#guests">Guests</a></li>
	<li class="'.($_GET['tab']=="vmplans" ? "active" : "").'"><a id="tabLink3" data-toggle="tab" role="tab" href="#plans">Plans</a></li>
	<li class="'.($_GET['tab']=="ippools" ? "active" : "").'"><a id="tabLink4" data-toggle="tab" role="tab" href="#ippools">IPv4</a></li>
	<li class="'.($_GET['tab']=="actions" ? "active" : "").'"><a id="tabLink5" data-toggle="tab" role="tab" href="#actions">Actions</a></li>
	<li class="'.($_GET['tab']=="support" ? "active" : "").'"><a id="tabLink6" data-toggle="tab" role="tab" href="#support">Support</a></li>
	<li class="'.($_GET['tab']=="config" ? "active" : "").'"><a id="tabLink7" data-toggle="tab" role="tab" href="#config">Config</a></li>
	<li class="'.($_GET['tab']=="logs" ? "active" : "").'"><a id="tabLink8" data-toggle="tab" role="tab" href="#logs">Logs</a></li>
	</ul>
	</div>
	<style>
	.pve-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		background: #fff;
		font-size: 13px;
	}
	.pve-table thead th {
		background: #f8f8f8;
		color: #333;
		font-weight: 600;
		padding: 12px 15px;
		text-align: left;
		border-bottom: 2px solid #e0e0e0;
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
	.pve-table tbody tr {
		transition: background 0.15s ease;
	}
	.pve-table tbody tr:hover {
		background: #faf8fc;
	}
	.pve-table tbody tr:not(:last-child) td {
		border-bottom: 1px solid #eee;
	}
	.pve-table tbody td {
		padding: 10px 15px;
		color: #333;
		vertical-align: middle;
	}
	.pve-table code {
		background: #f4f0f7;
		padding: 2px 8px;
		border-radius: 3px;
		font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
		font-size: 12px;
		color: #5c3d7a;
		font-weight: 600;
	}
	</style>
	<div class="tab-content admin-tabs">';

	// Handle form submissions for saving or updating plans
	if (isset($_POST['plan_save_qemu']))
	{
		save_qemu_plan() ;
	}

	if (isset($_POST['plan_update_qemu']))
	{
		update_qemu_plan() ;
	}

	if (isset($_POST['plan_save_lxc']))
	{
		save_lxc_plan() ;
	}

	if (isset($_POST['plan_update_lxc']))
	{
		update_lxc_plan() ;
	}

	// NODES / GUESTS tab in ADMIN GUI
	echo '<div id="nodes" class="tab-pane '.($_GET['tab']=="nodes" ? "active" : "").'" >' ;

	// Fetch all enabled Servers that use pvewhmcs
	$servers = Capsule::table('tblservers')
		->where('type', '=', 'pvewhmcs')
		->where('disabled', '=', 0)
		->orderBy('id', 'asc')
		->get();

	// Catch no-servers case early
	if ($servers->isEmpty()) {
		echo '<div class="alert alert-warning">No enabled WHMCS servers found for module type <code>pvewhmcs</code>. Add/enable a server in <em>Setup &gt; Products/Services &gt; Servers</em>.</div>';
	} else {
		foreach ($servers as $srv) {
			// Decrypt server password (same approach as ClientArea)
			$api_data = array('password2' => $srv->password);
			$serverpassword = localAPI('DecryptPassword', $api_data);
			$serverip       = $srv->ipaddress;
			$serverusername = $srv->username;
			$serverlabel    = !empty($srv->name) ? $srv->name : ('Server #'.$srv->id);

			// Login + get cluster/resources
			$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
			if (!$proxmox->login()) {
				echo '<div class="alert alert-danger">Unable to log in to PVE API on '.htmlspecialchars($serverip).'. Check credentials / connectivity.</div>';
				continue;
			}

			$cluster_resources = $proxmox->get('/cluster/resources'); // returns nodes, qemu, lxc, storage, pools, etc.

			// Debug logging (same style as ClientArea)
			if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
				logModuleCall(
					'pvewhmcs',
					__FUNCTION__,
					'CLUSTER RESOURCES ['.$serverlabel.']:',
					json_encode($cluster_resources)
				);
			}

			if (!is_array($cluster_resources) || empty($cluster_resources)) {
				echo '<div class="alert alert-info">No resources returned.</div>';
				continue;
			}

			// Split resources
			$nodes = [];
			$guests = []; // qemu + lxc
			foreach ($cluster_resources as $res) {
				if (!isset($res['type'])) {
					continue;
				}
				if ($res['type'] === 'node') {
					$nodes[] = $res;
				} elseif ($res['type'] === 'qemu' || $res['type'] === 'lxc') {
					$guests[] = $res;
				}
			}

			// Count running guests
			$running_guests = array_filter($guests, function($g) {
				return isset($g['status']) && $g['status'] === 'running';
			});

			// ======== CLUSTER HEADER PANEL ========
			echo '<div class="panel panel-default" style="margin-bottom:20px;">';
			echo '<div class="panel-heading" style="background:#5c3d7a;color:#fff;">';
			echo '<h3 class="panel-title" style="margin:0;"><i class="fa fa-server"></i> '.htmlspecialchars($serverlabel).' <small style="color:#ccc;">('.htmlspecialchars($serverip).')</small></h3>';
			echo '</div>';
			echo '<div class="panel-body">';

			// -------- Per-Node Info with RRD Graphs --------
			foreach ($nodes as $n) {
				$n_name    = isset($n['node']) ? $n['node'] : '(node)';
				$n_status  = isset($n['status']) ? $n['status'] : 'unknown';
				$n_uptime  = isset($n['uptime']) ? time2format($n['uptime']) : '—';
				$n_version = $proxmox->get_version();
				$n_cpu_pct = isset($n['cpu']) ? round($n['cpu'] * 100, 1) : 0;
				$n_maxcpu  = isset($n['maxcpu']) ? $n['maxcpu'] : 0;
				$n_mem_pct = (isset($n['mem']) && isset($n['maxmem']) && $n['maxmem'] > 0)
					? round($n['mem'] * 100 / $n['maxmem'], 1)
					: 0;
				$n_mem_used = isset($n['mem']) ? round($n['mem'] / 1073741824, 1) : 0;
				$n_mem_max  = isset($n['maxmem']) ? round($n['maxmem'] / 1073741824, 1) : 0;

				$status_color = ($n_status === 'online') ? '#5cb85c' : '#d9534f';

				echo '<div style="border:1px solid #ddd;border-radius:4px;padding:15px;margin-bottom:15px;background:#fafafa;">';
				
				// Node Header Row
				echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">';
				echo '<div>';
				echo '<h4 style="margin:0 0 5px 0;"><i class="fa fa-cube" style="color:#5c3d7a;"></i> '.htmlspecialchars($n_name).'</h4>';
				echo '<span style="color:#555;font-size:12px;">PVE '.htmlspecialchars($n_version).' &bull; Uptime: '.htmlspecialchars($n_uptime).'</span>';
				echo '</div>';
				echo '<div style="text-align:right;">';
				echo '<span style="display:inline-block;padding:4px 12px;border-radius:3px;background:'.$status_color.';color:#fff;font-weight:bold;text-transform:uppercase;font-size:11px;">'.htmlspecialchars($n_status).'</span>';
				echo '</div>';
				echo '</div>';

				// Live Stats Row
				echo '<div style="display:flex;gap:20px;margin-bottom:15px;">';
				echo '<div style="flex:1;text-align:center;padding:10px;background:#fff;border-radius:4px;border:1px solid #eee;">';
				echo '<div style="font-size:24px;font-weight:bold;color:#5c3d7a;">'.$n_cpu_pct.'%</div>';
				echo '<div style="font-size:11px;color:#555;">CPU ('.$n_maxcpu.' cores)</div>';
				echo '</div>';
				echo '<div style="flex:1;text-align:center;padding:10px;background:#fff;border-radius:4px;border:1px solid #eee;">';
				echo '<div style="font-size:24px;font-weight:bold;color:#5c3d7a;">'.$n_mem_pct.'%</div>';
				echo '<div style="font-size:11px;color:#555;">RAM ('.$n_mem_used.'/'.$n_mem_max.' GB)</div>';
				echo '</div>';
				echo '</div>';

				// RRD Graphs Section
				$rrd_path = '/nodes/' . $n_name . '/rrd';
				$rrd_cpu = pvewhmcs_addon_fetch_rrd($proxmox, $rrd_path, 'hour', 'cpu');
				$rrd_mem = pvewhmcs_addon_fetch_rrd($proxmox, $rrd_path, 'hour', 'memused');
				$rrd_net = pvewhmcs_addon_fetch_rrd($proxmox, $rrd_path, 'hour', 'netin,netout');
				$rrd_io  = pvewhmcs_addon_fetch_rrd($proxmox, $rrd_path, 'hour', 'iowait');

				if ($rrd_cpu || $rrd_mem || $rrd_net || $rrd_io) {
					echo '<div style="margin-top:10px;">';
					echo '<div style="font-size:12px;color:#555;margin-bottom:8px;"><i class="fa fa-line-chart"></i> Performance Graphs (Last Hour)</div>';
					// Row 1: CPU and Memory
					echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">';
					if ($rrd_cpu) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,'.$rrd_cpu.'" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					if ($rrd_mem) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,'.$rrd_mem.'" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					echo '</div>';
					// Row 2: Network and I/O
					echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
					if ($rrd_net) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,'.$rrd_net.'" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					if ($rrd_io) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,'.$rrd_io.'" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					echo '</div>';
					echo '</div>';
				} else {
					echo '<div class="alert alert-warning" style="margin:10px 0 0 0;padding:10px;font-size:12px;">';
					echo '<i class="fa fa-exclamation-triangle"></i> RRD data unavailable. Please ask Support to upgrade RRD stored data from 2.x to 9.0 format on their Nodes.';
					echo '</div>';
				}

				echo '</div>'; // End node panel
			}

			echo '</div>'; // panel-body
			echo '</div>'; // panel
		}
	}
	echo '</div>';

	// ======== GUESTS TAB ========
	echo '<div id="guests" class="tab-pane '.($_GET['tab']=="guests" ? "active" : "").'" >';

	// Re-use servers data for guests tab
	$servers = Capsule::table('tblservers')
		->where('type', '=', 'pvewhmcs')
		->where('disabled', '=', 0)
		->orderBy('id', 'asc')
		->get();

	if ($servers->isEmpty()) {
		echo '<div class="alert alert-warning">No enabled WHMCS servers found for module type <code>pvewhmcs</code>.</div>';
	} else {
		foreach ($servers as $srv) {
			$api_data = array('password2' => $srv->password);
			$serverpassword = localAPI('DecryptPassword', $api_data);
			$serverip       = $srv->ipaddress;
			$serverusername = $srv->username;
			$serverlabel    = !empty($srv->name) ? $srv->name : ('Server #'.$srv->id);

			$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
			if (!$proxmox->login()) {
				echo '<div class="alert alert-danger">Unable to log in to PVE API on '.htmlspecialchars($serverip).'.</div>';
				continue;
			}

			$cluster_resources = $proxmox->get('/cluster/resources');
			if (!is_array($cluster_resources) || empty($cluster_resources)) {
				echo '<div class="alert alert-info">No resources returned.</div>';
				continue;
			}

			// Filter guests only
			$guests = [];
			foreach ($cluster_resources as $res) {
				if (isset($res['type']) && ($res['type'] === 'qemu' || $res['type'] === 'lxc')) {
					$guests[] = $res;
				}
			}

			$running_count = count(array_filter($guests, function($g) { return ($g['status'] ?? '') === 'running'; }));
			$stopped_count = count(array_filter($guests, function($g) { return ($g['status'] ?? '') === 'stopped'; }));

			echo '<div class="panel panel-default" style="margin-bottom:20px;">';
			echo '<div class="panel-heading" style="background:#5c3d7a;color:#fff;">';
			echo '<h3 class="panel-title" style="margin:0;"><i class="fa fa-desktop"></i> '.htmlspecialchars($serverlabel).' <small style="color:#ddd;">('.count($guests).' guests: '.$running_count.' running, '.$stopped_count.' stopped)</small></h3>';
			echo '</div>';
			echo '<div class="panel-body" style="padding:0;padding-top:8px;">';

			if (count($guests) > 0) {
				echo '<table class="pve-table">';
				echo '<thead><tr>
						<th>VMID</th>
						<th>Name</th>
						<th>Status</th>
						<th>Type</th>
						<th>Node</th>
						<th>CPU %</th>
						<th>RAM %</th>
						<th>Disk %</th>
						<th>Uptime</th>
					</tr></thead><tbody>';

				foreach ($guests as $g) {
					$g_node   = $g['node']  ?? '—';
					$g_type   = $g['type']  ?? '—';
					$g_vmid   = isset($g['vmid']) ? (int)$g['vmid'] : 0;
					$g_name   = $g['name']  ?? '';
					$g_status = $g['status'] ?? 'unknown';
					$g_uptime = isset($g['uptime']) ? time2format($g['uptime']) : '—';
					$g_cpu_pct = isset($g['cpu']) ? round($g['cpu'] * 100, 1) : 0;
					$g_mem_pct = (isset($g['maxmem']) && $g['maxmem'] > 0)
						? round(($g['mem'] ?? 0) * 100 / $g['maxmem'], 1)
						: 0;
					$g_dsk_pct = (isset($g['maxdisk']) && $g['maxdisk'] > 0)
						? round(($g['disk'] ?? 0) * 100 / $g['maxdisk'], 1)
						: 0;

					$type_icon = ($g_type === 'qemu') ? 'fa-desktop' : 'fa-cube';
					$status_color = ($g_status === 'running') ? '#5cb85c' : '#999';

					echo '<tr>';
					echo '<td><code>'.$g_vmid.'</code></td>';
					echo '<td><i class="fa '.$type_icon.'" style="color:#666;"></i> <strong>'.htmlspecialchars($g_name).'</strong></td>';
					echo '<td><span style="display:inline-block;padding:2px 8px;border-radius:3px;background:'.$status_color.';color:#fff;font-size:10px;text-transform:uppercase;">'.htmlspecialchars($g_status).'</span></td>';
					echo '<td><span style="text-transform:uppercase;font-size:10px;background:#eee;padding:2px 6px;border-radius:3px;">'.htmlspecialchars($g_type).'</span></td>';
					echo '<td>'.htmlspecialchars($g_node).'</td>';
					echo '<td>'.$g_cpu_pct.'%</td>';
					echo '<td>'.$g_mem_pct.'%</td>';
					echo '<td>'.$g_dsk_pct.'%</td>';
					echo '<td>'.htmlspecialchars($g_uptime).'</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<div class="alert alert-info" style="margin:15px;">No guests found on this cluster.</div>';
			}

			echo '</div>'; // panel-body
			echo '</div>'; // panel
		}
	}
	echo '</div>';

	// VM / CT PLANS tab in ADMIN GUI
	echo '
	<div id="plans" class="tab-pane '.($_GET['tab']=="vmplans" ? "active" : "").'">
	<div class="btn-group" role="group" aria-label="...">
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=planlist">
	<i class="fa fa-list"></i>&nbsp; List: Guest Plans
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=add_qemu_plan">
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
	
	if ($_GET['action']=='add_qemu_plan') {
		qemu_plan_add() ;
	}

	if ($_GET['action']=='editplan') {
		if ($_GET['vmtype']=='kvm')
			qemu_plan_edit($_GET['id']) ;
		else
			lxc_plan_edit($_GET['id']) ;
	}

	if($_GET['action']=='removeplan') {
		remove_plan($_GET['id']) ;
	}


	if ($_GET['action']=='add_lxc_plan') {
		lxc_plan_add() ;
	}

	// List of VM / CT Plans
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
		<th>Unpriv.</th>
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
			echo '<td>'.$vm->unpriv . PHP_EOL .'</td>';
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

	// ACTIONS tab in ADMIN GUI
	echo '<div id="actions" class="tab-pane '.($_GET['tab']=="actions" ? "active" : "").'" >' ;
	echo ('<strong><h2>Module: Action History</h2></strong>');
	echo ('Coming soon!<br><br>');
	echo ('<strong><h2>Module: Failed Actions</h2></strong>');
	echo ('Coming soon!<br><br>');
	echo '</div>';

	// SUPPORT tab in ADMIN GUI
	echo '<div id="support" class="tab-pane '.($_GET['tab']=="support" ? "active" : "").'" >';
	echo '
	<div style="max-width:800px;">
		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:25px;margin-bottom:20px;">
			<h3 style="margin:0 0 15px 0;color:#5c3d7a;font-weight:600;"><span style="font-size:24px;">&#9881;</span> System Environment</h3>
			<table style="width:100%;font-size:14px;">
				<tr><td style="padding:6px 0;width:180px;color:#666;">Module Version</td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . pvewhmcs_version() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;">Latest Available</td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . get_pvewhmcs_latest_version() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;">Web Server</td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">' . htmlspecialchars($_SERVER['SERVER_SOFTWARE']) . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;">PHP Version</td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . phpversion() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;">Server Name</td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">' . htmlspecialchars($_SERVER['SERVER_NAME']) . '</code></td></tr>
			</table>
		</div>
		
		<div style="background:#faf8fc;border:1px solid #e0d4e8;border-radius:8px;padding:25px;margin-bottom:20px;">
			<h3 style="margin:0 0 15px 0;color:#5c3d7a;font-weight:600;"><span style="font-size:24px;">&#9829;</span> Open Source</h3>
			<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#333;">PVEWHMCS is open-source and free to use &amp; improve on!</p>
			<p style="margin:0;">
				<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank" style="color:#5c3d7a;">&#10132; GitHub Repository</a>
			</p>
		</div>
		
		<div style="background:#f8fff8;border:1px solid #c3e6c3;border-radius:8px;padding:25px;margin-bottom:20px;">
			<h3 style="margin:0 0 15px 0;color:#2d7a2d;font-weight:600;"><span style="font-size:24px;">&#9733;</span> Leave a Review</h3>
			<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#333;">Your 5-star review on WHMCS Marketplace helps the module grow!</p>
			<p style="margin:0;">
				<a href="https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs" target="_blank" style="color:#2d7a2d;">&#9733;&#9733;&#9733;&#9733;&#9733; Rate on WHMCS Marketplace</a>
			</p>
		</div>
		
		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:25px;">
			<h3 style="margin:0 0 15px 0;color:#5c3d7a;font-weight:600;"><span style="font-size:24px;">&#9881;</span> Technical Support</h3>
			<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;">Our README contains a wealth of information. Please review it before raising issues.</p>
			<p style="margin:0 0 15px 0;">
				<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank" style="color:#5c3d7a;">&#10132; View Documentation</a>
			</p>
			<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;">Only raise a GitHub Issue &mdash; including logs &mdash; if you have properly tried to resolve it first.</p>
			<p style="margin:0 0 15px 0;">
				<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/new/choose" target="_blank" style="color:#5c3d7a;">&#10132; Open an Issue</a>
			</p>
			<p style="margin:0;padding:12px;background:#fff8f0;border-radius:6px;font-size:13px;color:#856404;border:1px solid #ffc107;">&#9888; Help is not guaranteed (FOSS). We will need your assistance to troubleshoot.</p>
		</div>
	</div>';
	echo '</div>';

	// Config Tab
	$config= Capsule::table('mod_pvewhmcs')->where('id', '=', '1')->get()[0];
	echo '<div id="config" class="tab-pane '.($_GET['tab']=="config" ? "active" : "").'" >' ;
	echo '
	<div style="max-width:800px;">
	<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:25px;">
	<h3 style="margin:0 0 20px 0;color:#5c3d7a;font-weight:600;">&#9881; Module Configuration</h3>
	<form method="post">
	<table style="width:100%;border-collapse:collapse;">
	<tr>
		<td style="padding:15px 0;border-bottom:1px solid #eee;width:160px;vertical-align:top;">
			<label style="font-weight:600;color:#333;">VNC Secret</label>
		</td>
		<td style="padding:15px 0;border-bottom:1px solid #eee;">
			<input type="text" style="width:100%;max-width:300px;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;" name="vnc_secret" id="vnc_secret" value="'.$config->vnc_secret.'">
			<p style="margin:8px 0 0 0;font-size:13px;color:#666;">Password for <code style="background:#f4f0f7;padding:2px 6px;border-radius:3px;color:#5c3d7a;">vnc@pve</code> user. Required for VNC proxying. <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank" style="color:#5c3d7a;"><u>View README</u></a></p>
		</td>
	</tr>
	<tr>
		<td style="padding:15px 0;border-bottom:1px solid #eee;vertical-align:top;">
			<label style="font-weight:600;color:#333;">VMID Start</label>
		</td>
		<td style="padding:15px 0;border-bottom:1px solid #eee;">
			<input type="text" style="width:100%;max-width:300px;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;" name="start_vmid" id="start_vmid" value="'.$config->start_vmid.'">
			<p style="margin:8px 0 0 0;font-size:13px;color:#666;">For Guests. Increments until a vacant VMID found. Default is <code style="background:#f4f0f7;padding:2px 6px;border-radius:3px;color:#5c3d7a;">100</code></p>
		</td>
	</tr>
	<tr>
		<td style="padding:15px 0;vertical-align:top;">
			<label style="font-weight:600;color:#333;">Debug Mode</label>
		</td>
		<td style="padding:15px 0;">
			<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
				<input type="checkbox" name="debug_mode" value="1" '. ($config->debug_mode=="1" ? "checked" : "").' style="width:18px;height:18px;">
				<span style="font-size:14px;color:#333;">Enable Debug Logging</span>
			</label>
			<p style="margin:8px 0 0 0;font-size:13px;color:#666;">Must also enable WHMCS Module Log. <a href="/admin/index.php?rp=/admin/logs/module-log" style="color:#5c3d7a;"><u>View Module Logs</u></a></p>
		</td>
	</tr>
	</table>
	<div style="margin-top:25px;padding-top:20px;border-top:1px solid #eee;">
		<input type="submit" style="background:#5c3d7a;color:#fff;border:none;padding:10px 24px;border-radius:4px;font-size:14px;font-weight:500;cursor:pointer;margin-right:10px;" value="Save Changes" name="save_config" id="save_config">
		<input type="reset" style="background:#f5f5f5;color:#333;border:1px solid #ddd;padding:10px 24px;border-radius:4px;font-size:14px;font-weight:500;cursor:pointer;" value="Cancel">
	</div>
	</form>
	</div>
	</div>
	';
	echo '</div>';

	// LOGS tab in ADMIN GUI
	echo '<div id="logs" class="tab-pane ' . (isset($_GET['tab']) && $_GET['tab'] === 'logs' ? 'active' : '') . '">';

	try {
	    // If a client exists already, reuse it; else initialise once from the first enabled pvewhmcs server
	    if (!isset($proxmox)) {
	        $srv = Capsule::table('tblservers')
	            ->where('type', 'pvewhmcs')
	            ->where('disabled', 0)
	            ->orderBy('id', 'asc')
	            ->first();

	        if (!$srv) {
	            throw new Exception('No enabled WHMCS server found for module type pvewhmcs.');
	        }

	        $dec = localAPI('DecryptPassword', ['password2' => $srv->password]);
	        $serverpassword = $dec['password'] ?? '';
	        if (!$serverpassword) {
	            throw new Exception('Could not decrypt Proxmox server password.');
	        }

	        $proxmox = new PVE2_API($srv->ipaddress, $srv->username, 'pam', $serverpassword);
	        if (!$proxmox->login()) {
	            throw new Exception('Login to Proxmox API failed.');
	        }
	    }

	    // Fetch recent cluster-wide tasks once
	    $limit = 150;
	    $tasks = $proxmox->get('/cluster/tasks', ['limit' => $limit]);

	    // Optional debug logging
	    if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
	        logModuleCall('pvewhmcs', 'ADMIN LOGS: /cluster/tasks', 'limit=' . $limit, json_encode($tasks));
	    }

	    if (!is_array($tasks) || empty($tasks)) {
	        echo '<div class="alert alert-info">No recent cluster tasks were returned.</div>';
	    } else {
	        // Sort newest first (defensive)
	        usort($tasks, function ($a, $b) {
	            return (intval($b['starttime'] ?? 0)) <=> (intval($a['starttime'] ?? 0));
	        });

	        echo '<table class="pve-table">';
	        echo '<thead><tr>
	                <th>Task</th>
	                <th>VMID</th>
	                <th>Status</th>
	                <th>Node</th>
	                <th>User</th>
	                <th>Duration</th>
	                <th>Start</th>
	                <th>End</th>
	              </tr></thead><tbody>';

	        foreach ($tasks as $t) {
	            $node   = $t['node'] ?? '—';
	            $type   = $t['type'] ?? '';
	            $user   = $t['user'] ?? '';
	            $upid   = $t['upid'] ?? '';

	            // Derive VMID:
	            // 1) Prefer numeric $t['id'] when available
	            // 2) Otherwise parse from UPID ("...:type:<vmid>:user@realm:")
	            $vmid = '—';
	            if (isset($t['id']) && preg_match('/^\d+$/', (string)$t['id'])) {
	                $vmid = (string)$t['id'];
	            } elseif (is_string($upid) && $upid !== '') {
	                // UPID format: UPID:node:pid:pstart:starttime:type:vmid:user@realm:
	                if (preg_match('/^UPID:[^:]*:[^:]*:[^:]*:[^:]*:[^:]*:([^:]*):/', $upid, $m)) {
	                    if ($m[1] !== '' && ctype_digit($m[1])) {
	                        $vmid = $m[1];
	                    }
	                }
	            }

	            $startTs = (int)($t['starttime'] ?? 0);
	            $endTs   = isset($t['endtime']) ? (int)$t['endtime'] : null;

	            $start = $startTs ? date('Y-m-d H:i:s', $startTs) : '—';
	            $end   = $endTs   ? date('Y-m-d H:i:s', $endTs)   : '—';

	            $durSec = $startTs ? (is_null($endTs) ? (time() - $startTs) : max(0, $endTs - $startTs)) : null;
	            $durH   = is_null($durSec)
	                ? '—'
	                : sprintf('%02d:%02d:%02d', intdiv($durSec, 3600), intdiv($durSec % 3600, 60), $durSec % 60);

	            $status = $t['status'] ?? (is_null($endTs) ? 'running' : '');
	            $badge  = ($status === 'OK')
	                ? '✅'
	                : ((preg_match('/(error|fail|aborted|unknown)/i', (string)$status)) ? '❌' : '⏳');

	            echo '<tr>';
	            echo '<td><code>' . htmlspecialchars($type) . '</code></td>';
	            echo '<td><code>' . htmlspecialchars($vmid) . '</code></td>';
	            echo '<td>' . $badge . ' ' . htmlspecialchars($status) . '</td>';
	            echo '<td>' . htmlspecialchars($node) . '</td>';
	            echo '<td>' . htmlspecialchars($user) . '</td>';
	            echo '<td>' . htmlspecialchars($durH) . '</td>';
	            echo '<td>' . htmlspecialchars($start) . '</td>';
	            echo '<td>' . htmlspecialchars($end) . '</td>';
	            echo '</tr>';
	        }
	        echo '</tbody></table>';
	        // Always close the tab-pane div
	    	echo '</div>';
	    }
	} catch (Throwable $e) {
	    echo '<div class="alert alert-danger">Could not retrieve PVE Cluster history: '
	        . htmlspecialchars($e->getMessage()) . '</div>';
	}
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
		$vtype = ($_POST['import_vtype'] === 'lxc') ? 'lxc' : 'qemu';

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
						'firstpaymentamount' => '0.00',
						'amount' => '0.00',
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
	echo '<tr><td class="fieldlabel">VM / CT</td><td class="fieldarea"><select name="import_vtype" required>';
	echo '<option value="qemu">(VM) QEMU</option>';
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

// MODULE FORM: Add new QEMU Plan
function qemu_plan_add() {
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
	Default is x86-64 psABI v2-AES.
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="1" required>
	The number of CPU Sockets (typically 1-4).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="1" required>
	The number of CPU Cores per Socket (1-N).
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
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024. Disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" value="2048" required>
	RAM capacity in Megabytes eg. 1024 = 1GB (default is 2GB)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Balloon</td>
	<td class="fieldarea">
	<input type="text" size="8" name="balloon" id="balloon" value="0" required>
	Balloon capacity in Megabytes eg. 1024 = 1GB (0 = disabled)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="10240" required>
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB (default is 10GB)
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
	Recommend "QEMU/qcow2" (supports Snapshots)
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
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - NIC Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option value="e1000">Intel E1000 (Stable but slower)</option>
	<option selected="" value="virtio">VirtIO (Paravirtualised)</option>
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
	<td class="fieldlabel">Network - IPv6</td>
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
	<input type="submit" class="btn btn-primary" value="Save Changes" name="plan_save_qemu" id="plan_save_qemu">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Edit a QEMU Plan
function qemu_plan_edit($id) {
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
	Default is x86-64 psABI v2-AES.
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="'.$plan->cpus.'" required>
	The number of CPU Sockets (typically 1-4).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="'.$plan->cores.'" required>
	The number of CPU Cores per Socket (1-N).
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
	Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. Disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
	RAM capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Balloon</td>
	<td class="fieldarea">
	<input type="text" size="8" name="balloon" id="balloon" required value="'.$plan->balloon.'">
	Balloon capacity in Megabytes eg. 1024 = 1GB (0 = disabled)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required value="'.$plan->disk.'">
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB
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
	Recommend "QEMU/qcow2 format" (supports Snapshots)
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
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - NIC Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option value="e1000" '. ($plan->netmodel=="e1000" ? "selected" : "").'>Intel E1000 (Stable but slower)</option>
	<option value="virtio" '. ($plan->netmodel=="virtio" ? "selected" : "").'>VirtIO (Paravirtualised)</option>
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
	<td class="fieldlabel">Network - IPv6</td>
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
	<input type="submit" class="btn btn-primary" value="Save Changes" name="plan_update_qemu" id="plan_update_qemu">
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
	Limit of CPU usage. Default is 1. If the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
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
	RAM capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap">
	Swap capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required>
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB
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
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
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
	<td class="fieldlabel">Network - IPv6</td>
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
	<tr>
	<td class="fieldlabel">
	Unpriv.
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="unpriv" value="0"> Specifies whether a CT will be unprivileged. (Recommended) Set at-create only.
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="plan_save_lxc" id="plan_save_lxc">
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
	Limit of CPU usage. Default is 1. If the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
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
	RAM capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap" value="'.$plan->swap.'">
	Swap capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="'.$plan->disk.'" required>
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB
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
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
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
	<td class="fieldlabel">Network - IPv6</td>
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
	<tr>
	<td class="fieldlabel">
	Unpriv.
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" value="1" name="unpriv" '. ($plan->unpriv=="1" ? "checked" : "").'> Specifies whether a CT will be unprivileged. (Recommended) Set at-create only.
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="plan_update_lxc" id="plan_update_lxc">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM ACTION: Save QEMU Plan
function save_qemu_plan() {
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

// MODULE FORM ACTION: Update QEMU Plan
function update_qemu_plan() {
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
						'unpriv' => $_POST['unpriv'],
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
			'unpriv' => $_POST['unpriv'],
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