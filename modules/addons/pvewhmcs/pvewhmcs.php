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
	return "1.3.5";
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
			if (!Capsule::statement($query . ';'))
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
		return "<br><span style='float:right;'><b>Proxmox VE for WHMCS is outdated: <a style='color:red' href='https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases/latest'>Download the new version!</a></span>";
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
		<span class="title">' . $_SESSION['pvewhmcs']['infomsg']['title'] . '</span>
		</strong><br/>
		' . $_SESSION['pvewhmcs']['infomsg']['message'] . '
		</div>
		';
		unset($_SESSION['pvewhmcs']);
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
	<li class="'.($_GET['tab']=="sync" ? "active" : "").'"><a id="tabLink9" data-toggle="tab" role="tab" href="#sync">Sync</a></li>
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
		foreach ($servers as $pve) {
			// Decrypt server password (same approach as ClientArea)
			$api_data = array('password2' => $pve->password);
			$serverpassword = localAPI('DecryptPassword', $api_data);
			$serverip       = $pve->ipaddress;
			$serverusername = $pve->username;
			$serverlabel    = !empty($pve->name) ? $pve->name : ('Server #' . $pve->id);

			// Login + get cluster/resources
			$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
			if (!$proxmox->login()) {
				echo '<div class="alert alert-danger">Unable to log in to PVE API on ' . htmlspecialchars($serverip) . '. Check credentials, connectivity & configurations.</div><center><img src="../modules/addons/pvewhmcs/img/forbidden.png"><br><a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS" target="_blank"><img src="../modules/addons/pvewhmcs/img/logo-stacked.png" style="max-height:150px;"></a></center>';
				continue;
			}

			$cluster_resources = $proxmox->get('/cluster/resources'); // returns nodes, qemu, lxc, storage, pools, etc.

			// Debug logging (same style as ClientArea)
			if (Capsule::table('mod_pvewhmcs')->where('id', '1')->value('debug_mode') == 1) {
				logModuleCall(
					'pvewhmcs',
					__FUNCTION__,
					'CLUSTER RESOURCES [' . $serverlabel . ']:',
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
			foreach ($cluster_resources as $resource) {
				if (!isset($resource['type'])) {
					continue;
				}
				if ($resource['type'] === 'node') {
					$nodes[] = $resource;
				} elseif ($resource['type'] === 'qemu' || $resource['type'] === 'lxc') {
					$guests[] = $resource;
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
				echo '<h4 style="margin:0 0 5px 0;"><i class="fa fa-cube" style="color:#5c3d7a;"></i> <strong>' . htmlspecialchars($n_name) . '</strong> [<code>v' . htmlspecialchars($n_version) . '</code>]</h4>';
				echo '<span style="color:#555;font-size:12px;"><strong>Last Booted:</strong> <code>' . htmlspecialchars($n_uptime) . '</code></span>';
				echo '</div>';
				echo '<div style="text-align:right;">';
				echo '<span style="display:inline-block;padding:4px 12px;border-radius:3px;background:' . $status_color . ';color:#fff;font-weight:bold;text-transform:uppercase;font-size:11px;">' . htmlspecialchars($n_status) . '</span>';
				echo '</div>';
				echo '</div>';

				// Live Stats Row
				echo '<div style="display:flex;gap:20px;margin-bottom:15px;">';
				echo '<div style="flex:1;text-align:center;padding:10px;background:#fff;border-radius:4px;border:1px solid #eee;">';
				echo '<div style="font-size:24px;font-weight:bold;color:#5c3d7a;">CPU: <code>' . $n_cpu_pct . '%</code></div>';
				echo '<div style="font-size:11px;color:#555;"><strong>' . $n_maxcpu . ' Cores</strong></div>';
				echo '</div>';
				echo '<div style="flex:1;text-align:center;padding:10px;background:#fff;border-radius:4px;border:1px solid #eee;">';
				echo '<div style="font-size:24px;font-weight:bold;color:#5c3d7a;">RAM: <code>' . $n_mem_pct . '%</code></div>';
				echo '<div style="font-size:11px;color:#555;"><strong>' . $n_mem_used . ' of ' . $n_mem_max . 'GB</strong></div>';
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
					echo '<div style="font-size:12px;color:#555;margin-bottom:8px;"><i class="fa fa-line-chart"></i> <strong>Performance Graphs</strong> (Last Hour)</div>';
					// Row 1: CPU and Memory
					echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">';
					if ($rrd_cpu) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,' . $rrd_cpu . '" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					if ($rrd_mem) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,' . $rrd_mem . '" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					echo '</div>';
					// Row 2: Network and I/O
					echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
					if ($rrd_net) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,' . $rrd_net . '" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
					}
					if ($rrd_io) {
						echo '<div style="flex:1;min-width:45%;"><img src="data:image/png;base64,' . $rrd_io . '" style="width:100%;border-radius:3px;border:1px solid #ddd;"/></div>';
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
		foreach ($servers as $pve) {
			$api_data = array('password2' => $pve->password);
			$serverpassword = localAPI('DecryptPassword', $api_data);
			$serverip       = $pve->ipaddress;
			$serverusername = $pve->username;
			$serverlabel    = !empty($pve->name) ? $pve->name : ('Server #' . $pve->id);

			$proxmox = new PVE2_API($serverip, $serverusername, "pam", $serverpassword['password']);
			if (!$proxmox->login()) {
				echo '<div class="alert alert-danger">Unable to log in to PVE API on ' . htmlspecialchars($serverip) . '. Check credentials, connectivity & configurations.</div><center><img src="../modules/addons/pvewhmcs/img/forbidden.png"><br><a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS" target="_blank"><img src="../modules/addons/pvewhmcs/img/logo-stacked.png" style="max-height:150px;"></a></center>';
				continue;
			}

			$cluster_resources = $proxmox->get('/cluster/resources');
			if (!is_array($cluster_resources) || empty($cluster_resources)) {
				echo '<div class="alert alert-info">No resources returned.</div>';
				continue;
			}

			// Filter guests only
			$guests = [];
			foreach ($cluster_resources as $resource) {
				if (isset($resource['type']) && ($resource['type'] === 'qemu' || $resource['type'] === 'lxc')) {
					$guests[] = $resource;
				}
			}

			$running_count = count(array_filter($guests, function($g) { return ($g['status'] ?? '') === 'running'; }));
			$stopped_count = count(array_filter($guests, function($g) { return ($g['status'] ?? '') === 'stopped'; }));

			echo '<div class="panel panel-default" style="margin-bottom:20px;">';
			echo '<div class="panel-heading" style="background:#5c3d7a;color:#fff;">';
			echo '<h3 class="panel-title" style="margin:0;"><i class="fa fa-desktop"></i> ' . htmlspecialchars($serverlabel) . ' <small style="color:#ddd;">(' . count($guests) . ' guests: ' . $running_count . ' running, ' . $stopped_count . ' stopped)</small></h3>';
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
					echo '<td><code>' . $g_vmid . '</code></td>';
					echo '<td><i class="fa ' . $type_icon . '" style="color:#666;"></i> <strong>' . htmlspecialchars($g_name) . '</strong></td>';
					echo '<td><span style="display:inline-block;padding:2px 8px;border-radius:3px;background:' . $status_color . ';color:#fff;font-size:10px;text-transform:uppercase;">' . htmlspecialchars($g_status) . '</span></td>';
					echo '<td><span style="text-transform:uppercase;font-size:10px;background:#eee;padding:2px 6px;border-radius:3px;">' . htmlspecialchars($g_type) . '</span></td>';
					echo '<td>' . htmlspecialchars($g_node) . '</td>';
					echo '<td>' . $g_cpu_pct . '%</td>';
					echo '<td>' . $g_mem_pct . '%</td>';
					echo '<td>' . $g_dsk_pct . '%</td>';
					echo '<td>' . htmlspecialchars($g_uptime) . '</td>';
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
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=sync">
	<i class="fa fa-link"></i>&nbsp; Link: Service
	</a>
	</div>
	';

	// Handle actions based on the 'action' GET parameter
	if ($_GET['action']=='import_guest') {
		import_guest() ;
	}

	if ($_GET['action']=='link_service') {
		link_service() ;
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
			echo '<td>' . $vm->id . '</td>';
			echo '<td>' . $vm->title . '</td>';
			echo '<td>' . $vm->vmtype . '</td>';
			echo '<td>' . $vm->ostype . '</td>';
			echo '<td>' . $vm->cpus . '</td>';
			echo '<td>' . $vm->cores . '</td>';
			echo '<td>' . $vm->memory . '</td>';
			echo '<td>' . $vm->balloon . '</td>';
			echo '<td>' . $vm->swap . '</td>';
			echo '<td>' . $vm->disk . '</td>';
			echo '<td>' . $vm->disktype . '</td>';
			echo '<td>' . $vm->diskio . '</td>';
			echo '<td>' . $vm->storage . '</td>';
			echo '<td>' . $vm->netmode . '</td>';
			echo '<td>' . $vm->bridge . $vm->vmbr . '</td>';
			echo '<td>' . $vm->netmodel . '</td>';
			echo '<td>' . $vm->vlanid . '</td>';
			echo '<td>' . $vm->netrate . '</td>';
			echo '<td>' . $vm->bw . '</td>';
			echo '<td>' . $vm->ipv6 . '</td>';
			echo '<td>' . $vm->unpriv . '</td>';
			echo '<td>
			<a href="' . pvewhmcs_BASEURL . '&amp;tab=vmplans&amp;action=editplan&amp;id=' . $vm->id . '&amp;vmtype=' . $vm->vmtype . '"><img height="16" width="16" border="0" alt="Edit" src="images/edit.gif"></a>
			<a href="' . pvewhmcs_BASEURL . '&amp;tab=vmplans&amp;action=removeplan&amp;id=' . $vm->id . '" onclick="return confirm(\'Plan will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>
			</td>';
			echo '</tr>';
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
				<tr><td style="padding:6px 0;color:#666;width:150px;"><strong>Module Version</strong></td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . pvewhmcs_version() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;"><strong>Latest Version</strong></td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . get_pvewhmcs_latest_version() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;"><strong>Web Server</strong></td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">' . htmlspecialchars($_SERVER['SERVER_SOFTWARE']) . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;"><strong>PHP Version</strong></td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">v' . phpversion() . '</code></td></tr>
				<tr><td style="padding:6px 0;color:#666;"><strong>Server Name</strong></td><td style="padding:6px 0;"><code style="background:#f4f0f7;padding:3px 8px;border-radius:3px;color:#5c3d7a;">' . htmlspecialchars($_SERVER['SERVER_NAME']) . '</code></td></tr>
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
		<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS" target="_blank"><img src="../modules/addons/pvewhmcs/img/logo-stacked.png" style="max-height:150px;"></a>
	</div>';
	echo '</div>';

	// Config Tab
	$config= Capsule::table('mod_pvewhmcs')->where('id', '=', '1')->get()[0];
	echo '<div id="config" class="tab-pane '.($_GET['tab']=="config" ? "active" : "").'" >' ;
	echo '
	<div style="max-width:800px;">
	<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:25px;">
	<h3 style="margin:0 0 20px 0;color:#5c3d7a;font-weight:600;"><span style="font-size:24px;">&#9881;</span> Module Configuration</h3>
	<form method="post">
	<table style="width:100%;border-collapse:collapse;">
	<tr>
		<td style="padding:15px 0;border-bottom:1px solid #eee;width:150px;vertical-align:top;">
			<label style="font-weight:600;color:#333;">VNC Secret</label>
		</td>
		<td style="padding:15px 0;border-bottom:1px solid #eee;">
			<input type="text" style="width:100%;max-width:300px;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;" name="vnc_secret" id="vnc_secret" value="' . $config->vnc_secret . '">
			<p style="margin:8px 0 0 0;font-size:13px;color:#666;">Password for <code style="background:#f4f0f7;padding:2px 6px;border-radius:3px;color:#5c3d7a;">vnc@pve</code> user. Required for VNC proxying. <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank" style="color:#5c3d7a;"><u>View README</u></a></p>
		</td>
	</tr>
	<tr>
		<td style="padding:15px 0;border-bottom:1px solid #eee;vertical-align:top;">
			<label style="font-weight:600;color:#333;">VMID Start</label>
		</td>
		<td style="padding:15px 0;border-bottom:1px solid #eee;">
			<input type="text" style="width:100%;max-width:300px;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;" name="start_vmid" id="start_vmid" value="' . $config->start_vmid . '">
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
	<a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS" target="_blank"><img src="../modules/addons/pvewhmcs/img/logo-stacked.png" style="max-height:150px;"></a>
	</div>
	';
	echo '</div>';

	// LOGS tab in ADMIN GUI
	echo '<div id="logs" class="tab-pane ' . (isset($_GET['tab']) && $_GET['tab'] === 'logs' ? 'active' : '') . '">';

	try {
	    // If a client exists already, reuse it; else initialise once from the first enabled pvewhmcs server
	    if (!isset($proxmox)) {
	        $pve = Capsule::table('tblservers')
	            ->where('type', 'pvewhmcs')
	            ->where('disabled', 0)
	            ->orderBy('id', 'asc')
	            ->first();

	        if (!$pve) {
	            throw new Exception('No enabled WHMCS server found for module type pvewhmcs.');
	        }

	        $dec = localAPI('DecryptPassword', ['password2' => $pve->password]);
	        $serverpassword = $dec['password'] ?? '';
	        if (!$serverpassword) {
	            throw new Exception('Could not decrypt Proxmox server password.');
	        }

	        $proxmox = new PVE2_API($pve->ipaddress, $pve->username, "pam", $serverpassword);
	        if (!$proxmox->login()) {
	            throw new Exception('Unable to log in to PVE API on ' . htmlspecialchars($pve->ipaddress) . '. Check credentials, connectivity & configurations.');
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
	    }
	} catch (Throwable $e) {
	    echo '<div class="alert alert-danger">Could not retrieve PVE Cluster history: '
	        . htmlspecialchars($e->getMessage()) . '</div>';
	}
	echo '</div>'; // closes logs tab

	// SYNC tab in ADMIN GUI
	echo '<div id="sync" class="tab-pane '.($_GET['tab']=="sync" ? "active" : "").'" >' ;
	if (isset($_GET['tab']) && $_GET['tab'] === 'sync') {
		pvewhmcs_sync_page();
	}
	echo '</div></div>'; // closes sync tab and tab-content
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
			$resultMsg = '<div class="errorbox">No active WHMCS Client found with ID ' . $userid . '</div>';
		} else {
			// Validate Product
			$product = Capsule::table('tblproducts')->where('id', $productid)->where('retired', 0)->first();
			if (!$product) {
				$resultMsg = '<div class="errorbox">No active WHMCS Product found with ID ' . $productid . '</div>';
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
						'notes' => 'PVEWHMCS: Imported from Proxmox Guest VMID ' . $vmid,
					]);
				} catch (Exception $e) {
					$resultMsg = '<div class="errorbox">Could not create WHMCS service: ' . htmlspecialchars($e->getMessage()) . '</div>';
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
						$resultMsg = '<div class="successbox">Successfully imported PVE VMID ' . $vmid . ' (' . $vtype . ') as Service ' . $serviceID . ' (' . $product->name . ') for ' . $client->firstname . ' ' . $client->lastname . '. ' . $client->company . '</div>';
					} catch (Exception $e) {
						$resultMsg = '<div class="errorbox">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
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
		$label = $client->id . ' - ' . ($client->companyname ? $client->companyname . ' - ' : '') . $client->firstname . ' ' . $client->lastname;
		echo '<option value="' . $client->id . '">' . htmlspecialchars($label) . '</option>';
	}
	echo '</select></td></tr>';

	// Product/Service dropdown (only Active products of Server type)
	$products = Capsule::table('tblproducts')->where('type', 'server')->where('retired', 0)->orderBy('name')->get();
	echo '<tr><td class="fieldlabel">Service</td><td class="fieldarea"><select name="import_productid" required>';
	foreach ($products as $product) {
		echo '<option value="' . $product->id . '">' . htmlspecialchars($product->name) . '</option>';
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

// Link Guest/Service sub-page handler (standalone)
// Kept as a safe landing page for bookmarks created before the Sync tab replaced this form.
function link_service() {
	echo '<div class="alert alert-info">Service mapping has moved to the Sync tab, where the service and live Proxmox guest are validated before any database change.</div>';
	echo '<div class="btn-container"><a class="btn btn-primary" href="' . pvewhmcs_BASEURL . '&amp;tab=sync">Open Sync</a></div>';
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
	<option value="win11">Windows 11 / 2022 / 2025</option>
	<option value="win10">Windows 10 / 2016 / 2019</option>
	<option value="win8">Windows 8 / 2012 / 2012r2</option>
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
	<option value="EPYC-Milan-v2">(AMD) EPYC-Milan-v2</option>
	<option value="EPYC-Rome">(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2">(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3">(AMD) EPYC-v3</option>
	<option value="Opteron_G1">(AMD) Opteron_G1</option>
	<option value="Opteron_G2">(AMD) Opteron_G2</option>
	<option value="Opteron_G3">(AMD) Opteron_G3</option>
	<option value="Opteron_G4">(AMD) Opteron_G4</option>
	<option value="Opteron_G5">(AMD) Opteron_G5</option>
	</select>
	Host is best. Read the <a href="https://pve.proxmox.com/pve-docs/pve-admin-guide.html#_qemu_vcpu_list" target="_blank" style="color:#5c3d7a;"><u>Docs</u></a>.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="1" required>
	The number of CPU Sockets (typically 1-4). Governed by your physical Server.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="1" required>
	The number of CPU Cores per Socket (1-N). Guest Compute = allocated Sockets * Cores.
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
	<option selected="" value="none">No Cache</option>
	<option value="directsync">Direct Sync</option>
	<option value="writethrough">Write Through</option>
	<option value="writeback">Write Back</option>
	<option value="unsafe">Write Back (Unsafe)</option>
	</select>
	Before overriding the default, read &amp; understand the <a href="https://pve.proxmox.com/wiki/Performance_Tweaks#Disk_Cache" target="_blank" style="color:#5c3d7a;"><u>Docs</u></a>.
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
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access for Guests.
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
	<option selected="" value="virtio">VirtIO (Paravirtualised)</option>
	<option value="e1000">Intel E1000 (Stable)</option>
	<option value="rtl8139">Realtek RTL8139</option>
	<option value="vmxnet3">VMware vmxnet3</option>
	</select>
	Recommend VirtIO, unless you need others.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="0">
	Network Rate Limit in Megabits/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Data Transfer Cap in Gigabytes. Blank for unlimited.
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
	SLAAC & DHCPv6 working. Prefix in future.
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
	Bridge, NAT or disconnect (no link) the Guest.
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
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="' . $plan->title . '">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">OS - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ostype">
	<option value="l26" ' . ($plan->ostype == "l26" ? "selected" : "") . '>Linux 6.x - 2.6 Kernel</option>
	<option value="l24" ' . ($plan->ostype == "l24" ? "selected" : "") . '>Linux 2.4 Kernel</option>
	<option value="solaris" ' . ($plan->ostype == "solaris" ? "selected" : "") . '>Solaris Kernel</option>
	<option value="win11" ' . ($plan->ostype == "win11" ? "selected" : "") . '>Windows 11 / 2022 / 2025</option>
	<option value="win10" ' . ($plan->ostype == "win10" ? "selected" : "") . '>Windows 10 / 2016 / 2019</option>
	<option value="win8" ' . ($plan->ostype == "win8" ? "selected" : "") . '>Windows 8 / 2012 / 2012r2</option>
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
	<option value="EPYC-Milan-v2" ' . ($plan->cpuemu == "EPYC-Milan-v2" ? "selected" : "") . '>(AMD) EPYC-Milan-v2</option>
	<option value="EPYC-Rome" ' . ($plan->cpuemu == "EPYC-Rome" ? "selected" : "") . '>(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2" ' . ($plan->cpuemu == "EPYC-Rome-v2" ? "selected" : "") . '>(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3" ' . ($plan->cpuemu == "EPYC-v3" ? "selected" : "") . '>(AMD) EPYC-v3</option>
	<option value="Opteron_G1" ' . ($plan->cpuemu == "Opteron_G1" ? "selected" : "") . '>(AMD) Opteron_G1</option>
	<option value="Opteron_G2" ' . ($plan->cpuemu == "Opteron_G2" ? "selected" : "") . '>(AMD) Opteron_G2</option>
	<option value="Opteron_G3" ' . ($plan->cpuemu == "Opteron_G3" ? "selected" : "") . '>(AMD) Opteron_G3</option>
	<option value="Opteron_G4" ' . ($plan->cpuemu == "Opteron_G4" ? "selected" : "") . '>(AMD) Opteron_G4</option>
	<option value="Opteron_G5" ' . ($plan->cpuemu == "Opteron_G5" ? "selected" : "") . '>(AMD) Opteron_G5</option>
	</select>
	Host is best. Read the <a href="https://pve.proxmox.com/pve-docs/pve-admin-guide.html#_qemu_vcpu_list" target="_blank" style="color:#5c3d7a;"><u>Docs</u></a>.
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="' . $plan->cpus . '" required>
	The number of CPU Sockets (typically 1-4). Governed by your physical Server.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="' . $plan->cores . '" required>
	The number of CPU Cores per Socket (1-N). Guest Compute = allocated Sockets * Cores.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="' . $plan->cpulimit . '" required>
	Limit of CPU usage. Note if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="' . $plan->cpuunits . '" required>
	Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. Disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="' . $plan->memory . '">
	RAM capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Balloon</td>
	<td class="fieldarea">
	<input type="text" size="8" name="balloon" id="balloon" required value="' . $plan->balloon . '">
	Balloon capacity in Megabytes eg. 1024 = 1GB (0 = disabled)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required value="' . $plan->disk . '">
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Format</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskformat">
	<option value="raw" ' . ($plan->diskformat == "raw" ? "selected" : "") . '>Disk Image (raw)</option>
	<option value="qcow2" ' . ($plan->diskformat == "qcow2" ? "selected" : "") . '>QEMU image (qcow2)</option>
	<option value="vmdk" ' . ($plan->diskformat == "vmdk" ? "selected" : "") . '>VMware image (vmdk)</option>
	</select>
	Recommend "QEMU/qcow2 format" (supports Snapshots)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Cache</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskcache">
	<option value="none" ' . ($plan->diskcache == "none" ? "selected" : "") . '>No Cache</option>
	<option value="directsync" ' . ($plan->diskcache == "directsync" ? "selected" : "") . '>Direct Sync</option>
	<option value="writethrough" ' . ($plan->diskcache == "writethrough" ? "selected" : "") . '>Write Through</option>
	<option value="writeback" ' . ($plan->diskcache == "writeback" ? "selected" : "") . '>Write Back</option>
	<option value="unsafe" ' . ($plan->diskcache == "unsafe" ? "selected" : "") . '>Write Back (Unsafe)</option>
	</select>
	Before overriding the default, read &amp; understand the <a href="https://pve.proxmox.com/wiki/Performance_Tweaks#Disk_Cache" target="_blank" style="color:#5c3d7a;"><u>Docs</u></a>.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="disktype">
	<option value="virtio" ' . ($plan->disktype == "virtio" ? "selected" : "") . '>Virtio</option>
	<option value="scsi" ' . ($plan->disktype == "scsi" ? "selected" : "") . '>SCSI</option>
	<option value="sata" ' . ($plan->disktype == "sata" ? "selected" : "") . '>SATA</option>
	<option value="ide" ' . ($plan->disktype == "ide" ? "selected" : "") . '>IDE</option>
	</select>
	Virtio is the fastest option, then SCSI, then SATA, etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" required value="' . $plan->diskio . '">
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access for Guests.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" required value="' . $plan->storage . '">
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - NIC Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option value="virtio" ' . ($plan->netmodel == "virtio" ? "selected" : "") . '>VirtIO (Paravirtualised)</option>
	<option value="e1000" ' . ($plan->netmodel == "e1000" ? "selected" : "") . '>Intel E1000 (Stable)</option>
	<option value="rtl8139" ' . ($plan->netmodel == "rtl8139" ? "selected" : "") . '>Realtek RTL8139</option>
	<option value="vmxnet3" ' . ($plan->netmodel == "vmxnet3" ? "selected" : "") . '>VMware vmxnet3</option>
	</select>
	Recommend VirtIO, unless you need others.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="' . $plan->netrate . '">
	Network Rate Limit in Megabits/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="' . $plan->bw . '">
	Monthly Data Transfer Cap in Gigabytes. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0" ' . ($plan->ipv6 == "0" ? "selected" : "") . '>Off</option>
	<option value="auto" ' . ($plan->ipv6 == "auto" ? "selected" : "") . '>SLAAC</option>
	<option value="dhcp" ' . ($plan->ipv6 == "dhcp" ? "selected" : "") . '>DHCPv6</option>
	<option value="prefix" ' . ($plan->ipv6 == "prefix" ? "selected" : "") . '>Prefix</option>
	</select>
	SLAAC & DHCPv6 working. Prefix in future.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Mode</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmode">
	<option value="bridge" ' . ($plan->netmode == "bridge" ? "selected" : "") . '>Bridge</option>
	<option value="nat" ' . ($plan->netmode == "nat" ? "selected" : "") . '>NAT</option>
	<option value="none" ' . ($plan->netmode == "none" ? "selected" : "") . '>No network</option>
	</select>
	Bridge, NAT or disconnect (no link) the Guest.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="' . $plan->bridge . '">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="' . $plan->vmbr . '">
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
	<input type="checkbox" name="kvm" value="1" ' . ($plan->kvm == "1" ? "checked" : "") . '> Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot VM?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" ' . ($plan->onboot == "1" ? "checked" : "") . '> Specifies whether a VM will be started during hypervisor boot-up. (Recommended)
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
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access for Guests.
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
	Network Rate Limit in Megabits/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Data Transfer Cap in Gigabytes. Blank for unlimited.
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
	SLAAC & DHCPv6 working. Prefix in future.
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
	<input type="checkbox" name="unpriv" value="0"> Specifies whether a CT will be unprivileged. (Recommended) <strong>Set at-create only!</strong>
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
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="' . $plan->title . '">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="' . $plan->cpulimit . '" required>
	Limit of CPU usage. Default is 1. If the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="' . $plan->cpuunits . '" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="' . $plan->memory . '">
	RAM capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap" value="' . $plan->swap . '">
	Swap capacity in Megabytes eg. 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Capacity</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="' . $plan->disk . '" required>
	HDD/SSD storage in Gigabytes eg. 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - I/O Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="' . $plan->diskio . '" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access for Guests.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="' . $plan->storage . '" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. <code>local/local-lvm/etc</code>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="' . $plan->bridge . '">
	Network / Bridge / NIC name. PVE default bridge prefix is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Bridge/NIC ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="' . $plan->vmbr . '">
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
	<input type="text" size="8" name="netrate" id="netrate" value="' . $plan->netrate . '">
	Network Rate Limit in Megabits/Second. Zero for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Cap</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="' . $plan->bw . '">
	Monthly Data Transfer Cap in Gigabytes. Blank for unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - IPv6</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ipv6">
	<option value="0" ' . ($plan->ipv6 == "0" ? "selected" : "") . '>Off</option>
	<option value="auto" ' . ($plan->ipv6 == "auto" ? "selected" : "") . '>SLAAC</option>
	<option value="dhcp" ' . ($plan->ipv6 == "dhcp" ? "selected" : "") . '>DHCPv6</option>
	<option value="prefix" ' . ($plan->ipv6 == "prefix" ? "selected" : "") . '>Prefix</option>
	</select>
	SLAAC & DHCPv6 working. Prefix in future.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot CT?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" value="1" name="onboot" ' . ($plan->onboot == "1" ? "checked" : "") . '> Specifies whether a CT will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	Unpriv.
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" value="1" name="unpriv" ' . ($plan->unpriv == "1" ? "checked" : "") . '> Specifies whether a CT will be unprivileged. (Recommended) <strong>Set at-create only!</strong>
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
	echo '<a class="btn btn-default" href="' . pvewhmcs_BASEURL . '&amp;tab=ippools&amp;action=new_ip_pool"><i class="fa fa-plus-square"></i>&nbsp; New IPv4 Pool</a>';
	echo '<table class="datatable"><tr><th>ID</th><th>Pool</th><th>Gateway</th><th>Action</th></tr>';
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $pool) {
		echo '<tr>';
		echo '<td>' . $pool->id . '</td>';
		echo '<td>' . $pool->title . '</td>';
		echo '<td>' . $pool->gateway . '</td>';
		echo '<td>
		<a href="' . pvewhmcs_BASEURL . '&amp;tab=ippools&amp;action=list_ips&amp;id=' . $pool->id . '"><img height="16" width="16" border="0" alt="Info" src="images/edit.gif"></a>
		<a href="' . pvewhmcs_BASEURL . '&amp;tab=ippools&amp;action=removeippool&amp;id=' . $pool->id . '" onclick="return confirm(\'Pool and all IPv4 Addresses assigned to it will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Remove" src="images/delete.gif"></a>
		</td>';
		echo '</tr>';
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
		echo '<option value="' . $pool->id . '">' . $pool->title . '</option>';
		$gateways[] = $pool->gateway;
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
		header("Location: " . pvewhmcs_BASEURL . "&tab=ippools&action=list_ips&id=" . $_POST['pool_id']);
		$_SESSION['pvewhmcs']['infomsg']['title'] = 'IPv4 Address/Blocks added to Pool.';
		$_SESSION['pvewhmcs']['infomsg']['message'] = 'You can remove IPv4 Addresses from the pool.';
	}
}

// IP POOL FORM: List IPs in Pool
function list_ips() {
    // Determine the WHMCS Admin Directory URL for the link
    $adminUrl = 'clientsservices.php';

    echo '<table class="datatable">
            <tr>
                <th>IPv4 Address</th>
                <th>Subnet Mask</th>
                <th>Action</th>
            </tr>';

    // Loop through IPs in the pool
    foreach (Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $_GET['id'])->get() as $ip) {

        // Query tblhosting to see if this IP is currently "occupied"
        // Occupied = assigned to a service that is Active, Suspended, or Completed
        $service = Capsule::table('tblhosting')
            ->where('dedicatedip', '=', $ip->ipaddress)
            ->whereIn('domainstatus', ['Active', 'Suspended', 'Completed'])
            ->first();

        echo '<tr>
                <td>' . $ip->ipaddress . '</td>
                <td>' . $ip->mask . '</td>
                <td>';

        if ($service) {
            // IP is in use: Create a link to the related service
            $serviceLink = $adminUrl . '?userid=' . $service->userid . '&id=' . $service->id;
            echo 'In use: <a href="' . $serviceLink . '" target="_blank">Service #' . $service->id . '</a>';
        } else {
            // IP is free (not in tblhosting OR status is Terminated/Cancelled/Fraud/Pending)
            echo '<a href="' . pvewhmcs_BASEURL . '&amp;tab=ippools&amp;action=removeip&amp;pool_id=' . $ip->pool_id . '&amp;id=' . $ip->id . '" onclick="return confirm(\'IPv4 Address will be deleted from the pool, continue?\')">
                    <img height="16" width="16" border="0" alt="Edit" src="images/delete.gif">
                  </a>';
        }

        echo '</td></tr>';
    }
    echo '</table>';
}

// IP POOL FORM ACTION: Remove IP from Pool
function removeip($id, $pool_id) {
	Capsule::table('mod_pvewhmcs_ip_addresses')->where('id', '=', $id)->delete();
	header("Location: " . pvewhmcs_BASEURL . "&tab=ippools&action=list_ips&id=" . $pool_id);
	$_SESSION['pvewhmcs']['infomsg']['title'] = 'IPv4 Address deleted.';
	$_SESSION['pvewhmcs']['infomsg']['message'] = 'Deleted selected item successfully.';
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

// Helper: Extracts IPv4 network metadata from Proxmox guest configuration.
function pvewhmcs_parse_vm_network($network_config) {
	$network = [
		'ip' => '',
		'subnet' => '',
		'gateway' => '',
	];

	if (!is_string($network_config) || $network_config === '') {
		return $network;
	}

	if (preg_match('/(?:^|,)ip=([^,\s]+)/', $network_config, $ip_matches)) {
		$ip_value = trim($ip_matches[1]);
		if (strtolower($ip_value) !== 'dhcp') {
			$ip_parts = explode('/', $ip_value, 2);
			if (filter_var($ip_parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$network['ip'] = $ip_parts[0];
				if (isset($ip_parts[1]) && ctype_digit($ip_parts[1])) {
					$prefix = intval($ip_parts[1]);
					if ($prefix >= 0 && $prefix <= 32) {
						$network['subnet'] = (string) $prefix;
					}
				}
			}
		}
	}

	if (preg_match('/(?:^|,)gw=([^,\s]+)/', $network_config, $gateway_matches)) {
		$gateway = trim($gateway_matches[1]);
		if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$network['gateway'] = $gateway;
		}
	}

	return $network;
}

function pvewhmcs_get_vm_network_from_proxmox($proxmox, $node, $vtype, $vmid) {
	static $network_cache = [];
	$cache_key = spl_object_hash($proxmox) . ':' . $node . ':' . $vtype . ':' . intval($vmid);
	if (isset($network_cache[$cache_key])) {
		return $network_cache[$cache_key];
	}

	try {
		$config = $proxmox->get('/nodes/' . $node . '/' . $vtype . '/' . $vmid . '/config');
		if (!is_array($config)) {
			$network_cache[$cache_key] = ['ip' => '', 'subnet' => '', 'gateway' => ''];
			return $network_cache[$cache_key];
		}

		$network_config = ($vtype === 'qemu')
			? ($config['ipconfig0'] ?? '')
			: ($config['net0'] ?? '');

		$network_cache[$cache_key] = pvewhmcs_parse_vm_network($network_config);
		return $network_cache[$cache_key];
	} catch (Throwable $e) {
		$network_cache[$cache_key] = ['ip' => '', 'subnet' => '', 'gateway' => ''];
		return $network_cache[$cache_key];
	}
}

function pvewhmcs_get_vm_ip_from_proxmox($proxmox, $node, $vtype, $vmid) {
	$network = pvewhmcs_get_vm_network_from_proxmox($proxmox, $node, $vtype, $vmid);
	return $network['ip'];
}

// Build a stable identity for the currently visible Proxmox cluster from its node set.
function pvewhmcs_get_cluster_identity($cluster_resources) {
	if (!is_array($cluster_resources)) {
		throw new RuntimeException('Unable to determine the Proxmox cluster identity.');
	}

	$nodes = [];
	foreach ($cluster_resources as $resource) {
		if (($resource['type'] ?? '') !== 'node') {
			continue;
		}

		$node = trim((string) ($resource['node'] ?? ''));
		if ($node !== '') {
			$nodes[] = strtolower($node);
		}
	}

	$nodes = array_values(array_unique($nodes));
	sort($nodes, SORT_STRING);
	if (empty($nodes)) {
		throw new RuntimeException('Unable to determine the Proxmox cluster identity.');
	}

	return hash('sha256', implode('|', $nodes));
}

function pvewhmcs_acquire_vmid_lock($cluster_identity, $vmid) {
	$lock_name = 'pvewhmcs:' . substr($cluster_identity, 0, 40) . ':' . intval($vmid);
	$rows = Capsule::connection()->select('SELECT GET_LOCK(?, 5) AS acquired', [$lock_name]);
	$acquired = isset($rows[0]->acquired) ? intval($rows[0]->acquired) : 0;
	if ($acquired !== 1) {
		throw new RuntimeException('Another mapping action is already processing this VMID.');
	}

	return $lock_name;
}

function pvewhmcs_release_vmid_lock($lock_name) {
	if ($lock_name === null || $lock_name === '') {
		return;
	}

	try {
		Capsule::connection()->select('SELECT RELEASE_LOCK(?)', [$lock_name]);
	} catch (Throwable $e) {
		logModuleCall('pvewhmcs', 'sync_release_lock', ['lock' => $lock_name], $e->getMessage());
	}
}

function pvewhmcs_get_server_cluster_identity($server) {
	$api_data = ['password2' => $server->password];
	$serverpassword = localAPI('DecryptPassword', $api_data);
	$serverport = !empty($server->port) ? $server->port : 8006;
	$proxmox = new PVE2_API(
		$server->ipaddress,
		$server->username,
		'pam',
		$serverpassword['password'],
		$serverport
	);

	if (!$proxmox->login()) {
		throw new RuntimeException('Unable to verify the cluster identity for an existing VMID mapping.');
	}

	return pvewhmcs_get_cluster_identity($proxmox->get('/cluster/resources'));
}

function pvewhmcs_find_vmid_owner_on_cluster($selected_server_id, $serviceid, $vmid, $cluster_identity) {
	static $server_cluster_identities = [];

	$owners = Capsule::table('mod_pvewhmcs_vms as vm')
		->join('tblhosting as hosting', 'hosting.id', '=', 'vm.id')
		->where('vm.vmid', $vmid)
		->where('vm.id', '!=', $serviceid)
		->select('vm.id', 'hosting.server')
		->get();

	foreach ($owners as $owner) {
		$owner_server_id = intval($owner->server);
		if ($owner_server_id === intval($selected_server_id)) {
			return $owner;
		}

		if (!isset($server_cluster_identities[$owner_server_id])) {
			$owner_server = Capsule::table('tblservers')
				->where('id', $owner_server_id)
				->where('type', 'pvewhmcs')
				->first();
			if (!$owner_server) {
				throw new RuntimeException('Unable to verify the cluster identity for an existing VMID mapping.');
			}

			$server_cluster_identities[$owner_server_id] = pvewhmcs_get_server_cluster_identity($owner_server);
		}

		if (hash_equals($cluster_identity, $server_cluster_identities[$owner_server_id])) {
			return $owner;
		}
	}

	return null;
}

// GUI ACTION: Renders the Proxmox to WHMCS Service Alignment & Sync page.
function pvewhmcs_sync_page() {
	$action_result = '';
	$focus_service_id = 0;

	$servers = Capsule::table('tblservers')
		->where('type', '=', 'pvewhmcs')
		->where('disabled', '=', 0)
		->orderBy('id', 'asc')
		->get();

	if ($servers->isEmpty()) {
		echo '<div class="alert alert-warning">No enabled WHMCS servers found for module type <code>pvewhmcs</code>.</div>';
		return;
	}

	$selected_server_id = isset($_REQUEST['pve_server_id'])
		? intval($_REQUEST['pve_server_id'])
		: intval($servers[0]->id);

	$pve = $servers->first(function ($server) use ($selected_server_id) {
		return intval($server->id) === $selected_server_id;
	});

	if (!$pve) {
		echo '<div class="alert alert-danger">The selected Proxmox server is unavailable or disabled.</div>';
		return;
	}

	if (isset($_POST['pve_sync_action'])) {
		check_token();
	}

	$serverip = $pve->ipaddress;
	$serverusername = $pve->username;
	$serverport = !empty($pve->port) ? $pve->port : 8006;

	try {
		$api_data = ['password2' => $pve->password];
		$serverpassword = localAPI('DecryptPassword', $api_data);
		$proxmox = new PVE2_API($serverip, $serverusername, 'pam', $serverpassword['password'], $serverport);
		if (!$proxmox->login()) {
			echo '<div class="alert alert-danger">Unable to log in to the selected Proxmox server. Check its credentials and connection.</div>';
			return;
		}

		$cluster_resources = $proxmox->get('/cluster/resources');
		if (!is_array($cluster_resources)) {
			echo '<div class="alert alert-danger">Failed to retrieve resources from the selected Proxmox server.</div>';
			return;
		}
	} catch (Throwable $e) {
		logModuleCall('pvewhmcs', 'sync_connection', ['server_id' => $selected_server_id], $e->getMessage());
		echo '<div class="alert alert-danger">Unable to retrieve resources from the selected Proxmox server.</div>';
		return;
	}

	$pve_guests = [];
	foreach ($cluster_resources as $resource) {
		if (isset($resource['type']) && in_array($resource['type'], ['qemu', 'lxc'], true)) {
			$pve_guests[intval($resource['vmid'])] = $resource;
		}
	}

	if (isset($_POST['pve_sync_action'])) {
		$action = trim((string) $_POST['pve_sync_action']);
		$serviceid = intval($_POST['service_id'] ?? 0);
		$vmid = intval($_POST['vmid'] ?? 0);
		$match_mode = ($_POST['match_mode'] ?? '') === 'auto' ? 'auto' : 'manual';
		$focus_service_id = $serviceid;
		$mapping_lock_name = null;

		try {
			if (!in_array($action, ['link', 'sync', 'unlink'], true)) {
				throw new InvalidArgumentException('Unsupported synchronization action.');
			}

			$service = Capsule::table('tblhosting')
				->where('id', $serviceid)
				->where('server', $selected_server_id)
				->first();

			if (!$service) {
				throw new InvalidArgumentException('The selected service does not belong to this Proxmox server.');
			}

			$existing_mapping = Capsule::table('mod_pvewhmcs_vms')
				->where('id', $serviceid)
				->first();

			if ($action === 'unlink') {
				if (!$existing_mapping) {
					throw new InvalidArgumentException('This service is not currently mapped.');
				}

				Capsule::table('mod_pvewhmcs_vms')->where('id', $serviceid)->delete();
				$action_result = '<div class="alert alert-success" role="status">Removed the mapping for Service #' . $serviceid . '. The Proxmox guest was not changed.</div>';
			} else {
				if ($vmid < 100 || !isset($pve_guests[$vmid])) {
					throw new InvalidArgumentException('The selected Proxmox guest does not exist on this server.');
				}

				$guest = $pve_guests[$vmid];
				$network = pvewhmcs_get_vm_network_from_proxmox(
					$proxmox,
					$guest['node'],
					$guest['type'],
					$vmid
				);

				$cluster_identity = pvewhmcs_get_cluster_identity($cluster_resources);
				$mapping_lock_name = pvewhmcs_acquire_vmid_lock($cluster_identity, $vmid);
				$existing_mapping = Capsule::table('mod_pvewhmcs_vms')
					->where('id', $serviceid)
					->first();
				$vmid_owner = pvewhmcs_find_vmid_owner_on_cluster(
					$selected_server_id,
					$serviceid,
					$vmid,
					$cluster_identity
				);

				if ($vmid_owner) {
					throw new InvalidArgumentException('This VMID is already mapped to Service #' . intval($vmid_owner->id) . ' on this Proxmox cluster.');
				}

				if ($action === 'link') {
					if ($existing_mapping) {
						throw new InvalidArgumentException('This service already has a guest mapping.');
					}

					if ($match_mode === 'auto') {
						$custom_vmids = Capsule::table('tblcustomfieldsvalues as values')
							->join('tblcustomfields as fields', 'fields.id', '=', 'values.fieldid')
							->where('values.relid', $serviceid)
							->where(function ($query) {
								$query->where('fields.fieldname', 'LIKE', '%vmid%')
									->orWhere('fields.fieldname', 'LIKE', '%vpsid%');
							})
							->pluck('values.value')
							->toArray();
						$custom_vmids = array_map('intval', $custom_vmids);

						if (!in_array($vmid, $custom_vmids, true)) {
							throw new InvalidArgumentException('The auto-match is no longer supported by the service custom field. Refresh the analysis before linking.');
						}

						if (!empty($service->domain) && !empty($guest['name']) && strcasecmp(trim($service->domain), trim($guest['name'])) !== 0) {
							throw new InvalidArgumentException('The Proxmox guest name no longer matches the service domain. Use a reviewed manual link if this difference is intentional.');
						}

						if ($network['ip'] !== '' && !empty($service->dedicatedip) && $network['ip'] !== trim($service->dedicatedip)) {
							throw new InvalidArgumentException('The Proxmox guest IP no longer matches the service dedicated IP. Review the discrepancy before linking.');
						}
					}

					$ipaddress = $network['ip'] ?: trim((string) $service->dedicatedip);
					Capsule::connection()->transaction(function () use ($serviceid, $service, $guest, $vmid, $network, $ipaddress) {
						Capsule::table('mod_pvewhmcs_vms')->insert([
							'id' => $serviceid,
							'vmid' => $vmid,
							'user_id' => $service->userid,
							'vtype' => $guest['type'],
							'ipaddress' => $ipaddress,
							'subnetmask' => $network['subnet'],
							'gateway' => $network['gateway'],
							'created' => date('Y-m-d H:i:s'),
						]);

						if ($network['ip'] !== '') {
							Capsule::table('tblhosting')
								->where('id', $serviceid)
								->update(['dedicatedip' => $network['ip']]);
						}
					});

					$action_result = '<div class="alert alert-success" role="status">Linked Service #' . $serviceid . ' to VMID ' . $vmid . ' on Node ' . htmlspecialchars($guest['node']) . '.</div>';
				} else {
					if (!$existing_mapping) {
						throw new InvalidArgumentException('This service has no mapping to synchronize.');
					}

					$ipaddress = $network['ip'] ?: $existing_mapping->ipaddress;
					$subnetmask = $network['subnet'] ?: $existing_mapping->subnetmask;
					$gateway = $network['gateway'] ?: $existing_mapping->gateway;
					Capsule::connection()->transaction(function () use ($serviceid, $guest, $vmid, $network, $ipaddress, $subnetmask, $gateway) {
						Capsule::table('mod_pvewhmcs_vms')
							->where('id', $serviceid)
							->update([
								'vmid' => $vmid,
								'vtype' => $guest['type'],
								'ipaddress' => $ipaddress,
								'subnetmask' => $subnetmask,
								'gateway' => $gateway,
							]);

						if ($network['ip'] !== '') {
							Capsule::table('tblhosting')
								->where('id', $serviceid)
								->update(['dedicatedip' => $network['ip']]);
						}
					});

					$action_result = '<div class="alert alert-success" role="status">Synchronized the mapping for Service #' . $serviceid . ' from the current Proxmox guest configuration.</div>';
				}
			}
		} catch (InvalidArgumentException $e) {
			$action_result = '<div class="alert alert-warning" role="alert">' . htmlspecialchars($e->getMessage()) . '</div>';
		} catch (Throwable $e) {
			logModuleCall('pvewhmcs', 'sync_' . $action, ['service_id' => $serviceid, 'vmid' => $vmid], $e->getMessage());
			$action_result = '<div class="alert alert-danger" role="alert">The mapping action failed. No partial database change was kept.</div>';
		} finally {
			pvewhmcs_release_vmid_lock($mapping_lock_name);
		}
	}

	$csrf_token = generate_token('plain');

	echo '<div class="pve-sync-heading">
		<div>
			<h2>Guest mapping</h2>
			<p>Review WHMCS services against live Proxmox guests. Mapping actions change the module database only; they do not start, stop, or delete a guest.</p>
		</div>
		<form method="get" action="" class="form-inline pve-server-selector">
			<input type="hidden" name="module" value="pvewhmcs">
			<input type="hidden" name="tab" value="sync">
			<label for="pve-server-id">Proxmox server</label>
			<select id="pve-server-id" name="pve_server_id" class="form-control">';
	foreach ($servers as $server) {
		$selected = (intval($server->id) === $selected_server_id) ? ' selected' : '';
		echo '<option value="' . intval($server->id) . '"' . $selected . '>' . htmlspecialchars($server->name) . '</option>';
	}
	echo '</select>
			<button type="submit" class="btn btn-primary">Analyze server</button>
		</form>
	</div>';

	if ($action_result !== '') {
		echo $action_result;
	}

	// Fetch all WHMCS Services assigned to this server with client and product info in ONE query
	$whmcs_services = Capsule::table('tblhosting')
		->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
		->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
		->where('tblhosting.server', $selected_server_id)
		->select(
			'tblhosting.id',
			'tblhosting.userid',
			'tblhosting.domain',
			'tblhosting.username',
			'tblhosting.dedicatedip',
			'tblhosting.domainstatus',
			'tblhosting.regdate',
			'tblproducts.name as product_name',
			'tblclients.firstname',
			'tblclients.lastname',
			'tblclients.companyname'
		)
		->get()
		->keyBy('id');

	// Fetch all custom fields for VMID / VPSID
	$vmid_custom_fields = Capsule::table('tblcustomfields')
		->where(function($query) {
			$query->where('fieldname', 'LIKE', '%vmid%')
			      ->orWhere('fieldname', 'LIKE', '%vpsid%');
		})
		->pluck('id');

	$service_custom_vmids = [];
	if ($vmid_custom_fields->isNotEmpty()) {
		$service_custom_vmids = Capsule::table('tblcustomfieldsvalues')
			->whereIn('fieldid', $vmid_custom_fields)
			->pluck('value', 'relid')
			->toArray();
	}

	// Fetch existing mappings from mod_pvewhmcs_vms
	$mappings = Capsule::table('mod_pvewhmcs_vms')
		->get()
		->keyBy('id')
		->toArray();

	// 4. Align and Match Datasets
	$aligned_rows = [];
	$mapped_service_ids = [];
	$mapped_vmids = [];

	// First, process all existing mappings from database
	foreach ($mappings as $srv_id => $map) {
		$srv = $whmcs_services->get($srv_id);
		if (!$srv) {
			continue;
		}

		$mapped_service_ids[] = $srv_id;
		$vmid = intval($map->vmid);
		$mapped_vmids[] = $vmid;

		$pve_vm = isset($pve_guests[$vmid]) ? $pve_guests[$vmid] : null;
		$has_discrepancy = false;
		$discrepancy_reasons = [];
		$pve_ip = '';
		$pve_network = ['ip' => '', 'subnet' => '', 'gateway' => ''];

		if ($pve_vm) {
			$pve_network = pvewhmcs_get_vm_network_from_proxmox($proxmox, $pve_vm['node'], $pve_vm['type'], $vmid);
			$pve_ip = $pve_network['ip'];
			if (empty($pve_ip)) {
				$pve_ip = $map->ipaddress; // Fallback to DB mapping IP
			}

			// 1. IP Check
			if ($srv->dedicatedip !== $pve_ip) {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "IP mismatch: WHMCS has '{$srv->dedicatedip}', Proxmox has '{$pve_ip}'";
			}

			// 2. Status Check
			if ($srv->domainstatus === 'Active' && $pve_vm['status'] !== 'running') {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "State warning: WHMCS active but VM is stopped";
			} elseif ($srv->domainstatus === 'Suspended' && $pve_vm['status'] === 'running') {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "CRITICAL: Service suspended but VM is running";
			} elseif (in_array($srv->domainstatus, ['Terminated', 'Cancelled']) && $pve_vm) {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "CRITICAL: Service is {$srv->domainstatus} but VM still exists";
			}

			// 3. Name Check
			if (strtolower(trim($srv->domain)) !== strtolower(trim($pve_vm['name']))) {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "Name mismatch: WHMCS domain '{$srv->domain}', Proxmox name '{$pve_vm['name']}'";
			}

			$custom_vmid = isset($service_custom_vmids[$srv_id]) ? intval($service_custom_vmids[$srv_id]) : 0;
			if ($custom_vmid && $custom_vmid !== $vmid) {
				$has_discrepancy = true;
				$discrepancy_reasons[] = "Custom field VMID {$custom_vmid} does not match mapped VMID {$vmid}";
			}
		} else {
			$has_discrepancy = true;
			$discrepancy_reasons[] = "CRITICAL: VMID $vmid not found in Proxmox cluster";
		}

		$aligned_rows[] = [
			'type' => $has_discrepancy ? 'discrepancy' : 'match',
			'service' => $srv,
			'mapping' => $map,
			'pve_vm' => $pve_vm ? array_merge($pve_vm, [
				'ip' => $pve_ip,
				'subnet' => $pve_network['subnet'] ?: $map->subnetmask,
				'gateway' => $pve_network['gateway'] ?: $map->gateway,
			]) : null,
			'custom_vmid' => isset($service_custom_vmids[$srv_id]) ? $service_custom_vmids[$srv_id] : '',
			'reasons' => $discrepancy_reasons
		];
	}

	// Process unmapped WHMCS Services (assigned to this server, but not in mappings)
	foreach ($whmcs_services as $srv_id => $srv) {
		if (in_array($srv_id, $mapped_service_ids)) {
			continue;
		}

		// Look for any automatic match in Proxmox
		$auto_match_vmid = null;
		$custom_vmid = isset($service_custom_vmids[$srv_id]) ? intval($service_custom_vmids[$srv_id]) : 0;

		if ($custom_vmid && isset($pve_guests[$custom_vmid]) && !in_array($custom_vmid, $mapped_vmids)) {
			$auto_match_vmid = $custom_vmid;
			$mapped_vmids[] = $auto_match_vmid;
		}

		$pve_vm = null;
		if ($auto_match_vmid) {
			$pve_vm = $pve_guests[$auto_match_vmid];
			$pve_network = pvewhmcs_get_vm_network_from_proxmox($proxmox, $pve_vm['node'], $pve_vm['type'], $auto_match_vmid);
			$pve_vm = array_merge($pve_vm, $pve_network);
		}

		$aligned_rows[] = [
			'type' => 'unmapped_service',
			'service' => $srv,
			'mapping' => null,
			'pve_vm' => $pve_vm,
			'custom_vmid' => $custom_vmid,
			'reasons' => []
		];
	}

	// Process unmapped Proxmox VMs
	foreach ($pve_guests as $vmid => $guest) {
		if (in_array($vmid, $mapped_vmids)) {
			continue;
		}

		// Do not issue one configuration request per orphaned guest just to render the list.
		// A manual link re-fetches and validates the selected guest configuration on submit.
		$guest = array_merge($guest, ['ip' => '', 'subnet' => '', 'gateway' => '']);

		$aligned_rows[] = [
			'type' => 'unmapped_vm',
			'service' => null,
			'mapping' => null,
			'pve_vm' => $guest,
			'custom_vmid' => '',
			'reasons' => []
		];
	}

	// 5. Render a compact, searchable reconciliation view.
	$type_counts = [
		'match' => 0,
		'discrepancy' => 0,
		'unmapped_service' => 0,
		'unmapped_vm' => 0,
	];
	foreach ($aligned_rows as $aligned_row) {
		if (isset($type_counts[$aligned_row['type']])) {
			$type_counts[$aligned_row['type']]++;
		}
	}
	$attention_count = $type_counts['discrepancy'] + $type_counts['unmapped_service'] + $type_counts['unmapped_vm'];
	$default_filter = $focus_service_id ? 'all' : 'attention';
	$initial_search = $focus_service_id ? (string) $focus_service_id : '';

	echo '
	<style>
	.pve-sync-heading {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		gap: 20px;
		margin: 18px 0;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	}
	.pve-sync-heading h2 {
		margin: 0 0 4px;
		font-size: 20px;
		font-weight: 650;
		color: #263240;
	}
	.pve-sync-heading p {
		max-width: 72ch;
		margin: 0;
		color: #52606d;
		font-size: 13px;
	}
	.pve-server-selector {
		display: flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
	}
	.pve-server-selector label {
		margin: 0;
		font-size: 12px;
		font-weight: 600;
		color: #39495a;
	}
	.pve-server-selector .form-control {
		min-width: 170px;
	}
	.pve-sync-toolbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 10px;
		background: #f7f8fa;
		border: 1px solid #d9dee5;
		border-radius: 8px 8px 0 0;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	}
	.pve-sync-filters {
		display: flex;
		align-items: center;
		gap: 4px;
		flex-wrap: wrap;
	}
	.pve-filter-button {
		appearance: none;
		background: transparent;
		border: 1px solid transparent;
		border-radius: 5px;
		color: #45566c;
		cursor: pointer;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.4;
		padding: 6px 9px;
		transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
	}
	.pve-filter-button:hover {
		background: #eef1f5;
	}
	.pve-filter-button[aria-pressed="true"] {
		background: #4a2f63;
		border-color: #4a2f63;
		color: #fff;
	}
	.pve-filter-count {
		display: inline-block;
		min-width: 20px;
		margin-left: 4px;
		padding: 1px 5px;
		border-radius: 10px;
		background: rgba(35, 48, 65, 0.09);
		font-variant-numeric: tabular-nums;
		text-align: center;
	}
	.pve-filter-button[aria-pressed="true"] .pve-filter-count {
		background: rgba(255, 255, 255, 0.2);
	}
	.pve-sync-search {
		position: relative;
		min-width: min(320px, 100%);
	}
	.pve-sync-search i {
		position: absolute;
		left: 10px;
		top: 9px;
		color: #66788a;
		pointer-events: none;
	}
	.pve-sync-search input {
		width: 100%;
		height: 34px;
		padding: 6px 10px 6px 31px;
		border: 1px solid #b8c2cc;
		border-radius: 5px;
		background: #fff;
		color: #243447;
		font-size: 12px;
	}
	.pve-sync-search input:focus,
	.pve-filter-button:focus {
		outline: 2px solid #6f4a8e;
		outline-offset: 2px;
	}
	.pve-sync-container {
		max-height: 70vh;
		overflow: auto;
		border: 1px solid #d9dee5;
		border-top: 0;
		border-radius: 0 0 8px 8px;
		background: #fff;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	}
	.pve-table-sync {
		width: 100%;
		min-width: 980px;
		border-collapse: collapse;
		background: #fff;
		font-size: 13px;
	}
	.pve-table-sync thead th {
		position: sticky;
		top: 0;
		z-index: 2;
		background: #eef1f5;
		color: #405064;
		font-weight: 650;
		padding: 10px 12px;
		text-align: left;
		font-size: 11px;
		border-bottom: 1px solid #cbd3dc;
	}
	.pve-table-sync tbody tr {
		background: #fff;
	}
	.pve-table-sync tbody tr:hover {
		background: #f6f8fb;
	}
	.pve-table-sync tbody tr.pve-row-discrepancy {
		background: #fffbeb;
	}
	.pve-table-sync tbody tr.pve-row-unmapped-service {
		background: #fff5f5;
	}
	.pve-table-sync tbody tr.pve-row-orphaned-vm {
		background: #f5f8fb;
	}
	.pve-table-sync tbody td {
		padding: 12px;
		vertical-align: middle;
		border-bottom: 1px solid #e3e7ec;
	}
	.pve-entity-card {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.pve-entity-title {
		font-size: 13px;
		color: #0f172a;
	}
	.pve-entity-meta {
		display: flex;
		gap: 12px;
		font-size: 11px;
		color: #64748b;
	}
	.pve-entity-meta i {
		margin-right: 3px;
		color: #94a3b8;
	}
	.pve-entity-specs {
		display: flex;
		gap: 10px;
		font-size: 11px;
		color: #475569;
		background: #f8fafc;
		padding: 4px 8px;
		border-radius: 6px;
		width: max-content;
		border: 1px solid #f1f5f9;
	}
	.pve-entity-specs i {
		color: #64748b;
		margin-right: 3px;
	}
	.pve-ip-badge {
		background: #f1f5f9;
		color: #334155;
		padding: 3px 8px;
		border-radius: 6px;
		font-size: 11px;
		font-family: monospace;
		font-weight: 600;
		border: 1px solid #e2e8f0;
		display: inline-block;
	}
	.pve-ip-badge.mismatch {
		background: #fee2e2;
		color: #991b1b;
		border: 1px solid #fca5a5;
	}
	.pve-status-badge {
		padding: 3px 8px;
		border-radius: 6px;
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		display: inline-block;
		margin-left: 5px;
	}
	.pve-status-badge.status-running {
		background: #dcfce7;
		color: #15803d;
	}
	.pve-status-badge.status-stopped {
		background: #f1f5f9;
		color: #475569;
	}
	.pve-badge-status {
		padding: 6px 12px;
		border-radius: 20px;
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		display: inline-block;
		text-align: center;
		letter-spacing: 0.5px;
	}
	.pve-badge-status.match {
		background: #dcfce7;
		color: #15803d;
	}
	.pve-badge-status.discrepancy {
		background: #fef9c3;
		color: #713f12;
	}
	.pve-badge-status.unmapped_service {
		background: #fee2e2;
		color: #991b1b;
	}
	.pve-badge-status.unmapped_vm {
		background: #f3f4f6;
		color: #374151;
	}
	.pve-reasons-list {
		margin-top: 8px;
		padding: 8px 12px;
		background: #fffbeb;
		border: 1px solid #fde68a;
		border-radius: 8px;
		color: #b45309;
		font-size: 11px;
		text-align: left;
		max-width: 280px;
	}
	.pve-reasons-list div {
		margin-bottom: 4px;
	}
	.pve-reasons-list div:last-child {
		margin-bottom: 0;
	}
	.pve-placeholder-empty {
		border: 2px dashed #cbd5e1;
		border-radius: 8px;
		padding: 15px;
		text-align: center;
		color: #94a3b8;
		font-style: italic;
		font-size: 12px;
		background: #f8fafc;
	}
	.pve-table-sync select.form-control, .pve-table-sync input.form-control {
		height: 30px;
		font-size: 11px;
		border-radius: 6px;
		border: 1px solid #cbd5e1;
		padding: 2px 8px;
		width: 170px;
		background: #fff;
		margin-bottom: 5px;
		display: inline-block;
	}
	.pve-table-sync select.form-control:focus, .pve-table-sync input.form-control:focus {
		border-color: #4a2f63;
		outline: 0;
		box-shadow: 0 0 0 2px rgba(74, 47, 99, 0.1);
	}
	.pve-table-sync .btn-xs {
		padding: 5px 10px;
		font-size: 11px;
		font-weight: 600;
		border-radius: 6px;
		width: 100%;
		max-width: 170px;
		transition: all 0.15s ease;
		display: inline-block;
		text-align: center;
	}
	.pve-table-sync .btn-primary {
		background: #4a2f63;
		border-color: #4a2f63;
		color: #fff;
	}
	.pve-table-sync .btn-primary:hover {
		background: #3b254f;
		border-color: #3b254f;
		color: #fff;
	}
	.pve-table-sync .btn-success {
		background: #10b981;
		border-color: #10b981;
		color: #fff;
	}
	.pve-table-sync .btn-success:hover {
		background: #059669;
		border-color: #059669;
		color: #fff;
	}
	.pve-table-sync .btn-warning {
		background: #f59e0b;
		border-color: #f59e0b;
		color: #fff;
	}
	.pve-table-sync .btn-warning:hover {
		background: #d97706;
		border-color: #d97706;
		color: #fff;
	}
	.pve-table-sync .btn-default {
		background: #f1f5f9;
		border-color: #e2e8f0;
		color: #475569;
	}
	.pve-table-sync .btn-default:hover {
		background: #e2e8f0;
		border-color: #cbd5e1;
		color: #1e293b;
	}
	.pve-sync-no-results {
		display: none;
		padding: 30px;
		border: 1px solid #d9dee5;
		border-top: 0;
		border-radius: 0 0 8px 8px;
		background: #fff;
		color: #52606d;
		text-align: center;
	}
	@media (max-width: 980px) {
		.pve-sync-heading,
		.pve-sync-toolbar {
			align-items: stretch;
			flex-direction: column;
		}
		.pve-server-selector {
			align-items: stretch;
			flex-wrap: wrap;
		}
		.pve-sync-search {
			min-width: 100%;
		}
	}
	@media (prefers-reduced-motion: reduce) {
		.pve-filter-button {
			transition: none;
		}
	}
	</style>
	<div class="pve-sync-toolbar">
		<div class="pve-sync-filters" role="group" aria-label="Filter mapping records">
			<button type="button" class="pve-filter-button" data-filter="attention" aria-pressed="' . ($default_filter === 'attention' ? 'true' : 'false') . '">Needs attention <span class="pve-filter-count">' . intval($attention_count) . '</span></button>
			<button type="button" class="pve-filter-button" data-filter="unmapped_service" aria-pressed="false">Unmapped services <span class="pve-filter-count">' . intval($type_counts['unmapped_service']) . '</span></button>
			<button type="button" class="pve-filter-button" data-filter="discrepancy" aria-pressed="false">Discrepancies <span class="pve-filter-count">' . intval($type_counts['discrepancy']) . '</span></button>
			<button type="button" class="pve-filter-button" data-filter="unmapped_vm" aria-pressed="false">Orphaned guests <span class="pve-filter-count">' . intval($type_counts['unmapped_vm']) . '</span></button>
			<button type="button" class="pve-filter-button" data-filter="match" aria-pressed="false">Mapped <span class="pve-filter-count">' . intval($type_counts['match']) . '</span></button>
			<button type="button" class="pve-filter-button" data-filter="all" aria-pressed="' . ($default_filter === 'all' ? 'true' : 'false') . '">All <span class="pve-filter-count">' . intval(count($aligned_rows)) . '</span></button>
		</div>
		<label class="pve-sync-search">
			<span class="sr-only">Search by service ID, VMID, domain, IP, or client</span>
			<i class="fa fa-search" aria-hidden="true"></i>
			<input id="pve-sync-search" type="search" value="' . htmlspecialchars($initial_search) . '" placeholder="Search service ID, VMID, domain, or IP">
		</label>
	</div>
	<div class="pve-sync-container">
	<table class="pve-table-sync">';

	echo '<thead>
		<tr>
			<th style="width: 38%;">Proxmox Guest (Module/Server)</th>
			<th style="width: 22%; text-align: center;">Status Alignment</th>
			<th style="width: 25%;">WHMCS Service (Database)</th>
			<th style="width: 15%; text-align: center;">Sync/Actions</th>
		</tr>
	</thead>
	<tbody>';

	// Helper to extract unmapped items for actions dropdowns
	$unmapped_hosting = [];
	foreach ($whmcs_services as $srv_id => $srv) {
		if (!in_array($srv_id, $mapped_service_ids)) {
			$unmapped_hosting[$srv_id] = $srv;
		}
	}

	$unmapped_pve_vms = [];
	foreach ($pve_guests as $vmid => $guest) {
		if (!in_array($vmid, $mapped_vmids)) {
			$unmapped_pve_vms[$vmid] = $guest;
		}
	}

	$common_form_fields = '
		<input type="hidden" name="token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '">
		<input type="hidden" name="pve_server_id" value="' . intval($selected_server_id) . '">';

	foreach ($aligned_rows as $row) {
		$row_class = '';
		$badge = '';
		$actions_html = '';
		$reasons_html = '';

		if ($row['type'] === 'match') {
			$row_class = 'pve-row-match';
			$badge = '<span class="pve-badge-status match">Mapped (OK)</span>';

			$actions_html = '
			<form method="post" style="margin:0;display:inline-block;" onsubmit="return confirm(\'Remove this database mapping? The Proxmox guest will not be changed.\');">
				' . $common_form_fields . '
				<input type="hidden" name="pve_sync_action" value="unlink">
				<input type="hidden" name="service_id" value="' . intval($row['service']->id) . '">
				<button type="submit" class="btn btn-xs btn-default">Unlink mapping</button>
			</form>';
		} elseif ($row['type'] === 'discrepancy') {
			$row_class = 'pve-row-discrepancy';
			$badge = '<span class="pve-badge-status discrepancy">Discrepancy</span>';

			$reasons_str = '';
			foreach ($row['reasons'] as $reason) {
				$reasons_str .= '<div>• ' . htmlspecialchars($reason) . '</div>';
			}
			$reasons_html = '<div class="pve-reasons-list">' . $reasons_str . '</div>';

			if ($row['pve_vm']) {
				$actions_html .= '
				<form method="post" style="margin:0 0 5px;display:inline-block;" onsubmit="return confirm(\'Replace the stored mapping details with the current Proxmox guest configuration?\');">
					' . $common_form_fields . '
					<input type="hidden" name="pve_sync_action" value="sync">
					<input type="hidden" name="service_id" value="' . intval($row['service']->id) . '">
					<input type="hidden" name="vmid" value="' . intval($row['pve_vm']['vmid']) . '">
					<button type="submit" class="btn btn-xs btn-warning">Sync from Proxmox</button>
				</form>';
			}

			$actions_html .= '
			<form method="post" style="margin:0;display:inline-block;" onsubmit="return confirm(\'Remove this database mapping? The Proxmox guest will not be changed.\');">
				' . $common_form_fields . '
				<input type="hidden" name="pve_sync_action" value="unlink">
				<input type="hidden" name="service_id" value="' . intval($row['service']->id) . '">
				<button type="submit" class="btn btn-xs btn-default">Unlink mapping</button>
			</form>';
		} elseif ($row['type'] === 'unmapped_service') {
			$row_class = 'pve-row-unmapped-service';
			$badge = '<span class="pve-badge-status unmapped_service">Unmapped</span>';

			if ($row['pve_vm']) {
				$actions_html = '
				<form method="post" style="margin:0;display:inline-block;" onsubmit="return confirm(\'Link Service #' . intval($row['service']->id) . ' to VMID ' . intval($row['pve_vm']['vmid']) . '?\');">
					' . $common_form_fields . '
					<input type="hidden" name="pve_sync_action" value="link">
					<input type="hidden" name="service_id" value="' . intval($row['service']->id) . '">
					<input type="hidden" name="vmid" value="' . intval($row['pve_vm']['vmid']) . '">
					<input type="hidden" name="match_mode" value="auto">
					<button type="submit" class="btn btn-xs btn-success">Link VMID ' . intval($row['pve_vm']['vmid']) . '</button>
				</form>';
			} else {
				if (count($unmapped_pve_vms) > 0) {
					$actions_html = '
					<form method="post" style="margin:0;display:flex;gap:5px;flex-direction:column;align-items:center;" onsubmit="return confirm(\'Link this service to the selected Proxmox guest?\');">
						' . $common_form_fields . '
						<input type="hidden" name="pve_sync_action" value="link">
						<input type="hidden" name="service_id" value="' . intval($row['service']->id) . '">
						<input type="hidden" name="match_mode" value="manual">
						<label class="sr-only" for="vmid-for-service-' . intval($row['service']->id) . '">Proxmox guest</label>
						<select id="vmid-for-service-' . intval($row['service']->id) . '" name="vmid" class="form-control" required>';
					foreach ($unmapped_pve_vms as $vmid => $guest) {
						$actions_html .= '<option value="' . intval($vmid) . '">VMID ' . intval($vmid) . ' (' . htmlspecialchars($guest['name']) . ')</option>';
					}
					$actions_html .= '</select>
						<button type="submit" class="btn btn-xs btn-primary">Link selected guest</button>
					</form>';
				} else {
					$actions_html = '<span style="color:#666;font-size:11px;">No unmapped VMs</span>';
				}
			}
		} elseif ($row['type'] === 'unmapped_vm') {
			$row_class = 'pve-row-orphaned-vm';
			$badge = '<span class="pve-badge-status unmapped_vm">Orphaned VM</span>';

			if (count($unmapped_hosting) > 0) {
				$actions_html = '
				<form method="post" style="margin:0;display:flex;gap:5px;flex-direction:column;align-items:center;" onsubmit="return confirm(\'Link this Proxmox guest to the selected WHMCS service?\');">
					' . $common_form_fields . '
					<input type="hidden" name="pve_sync_action" value="link">
					<input type="hidden" name="vmid" value="' . intval($row['pve_vm']['vmid']) . '">
					<input type="hidden" name="match_mode" value="manual">
					<label class="sr-only" for="service-for-vmid-' . intval($row['pve_vm']['vmid']) . '">WHMCS service</label>
					<select id="service-for-vmid-' . intval($row['pve_vm']['vmid']) . '" name="service_id" class="form-control" required>';
				foreach ($unmapped_hosting as $srv_id => $srv) {
					$cli_name = trim($srv->firstname . ' ' . $srv->lastname);
					$actions_html .= '<option value="' . intval($srv_id) . '">Service #' . intval($srv_id) . ' (' . htmlspecialchars($cli_name) . ')</option>';
				}
				$actions_html .= '</select>
					<button type="submit" class="btn btn-xs btn-primary">Link selected service</button>
				</form>';
			} else {
				$actions_html = '<span style="color:#666;font-size:11px;">No unmapped services</span>';
			}
		}

		$search_parts = [$row['type']];
		if ($row['pve_vm']) {
			$search_parts = array_merge($search_parts, [
				$row['pve_vm']['vmid'] ?? '',
				$row['pve_vm']['name'] ?? '',
				$row['pve_vm']['node'] ?? '',
				$row['pve_vm']['ip'] ?? '',
			]);
		}
		if ($row['service']) {
			$search_parts = array_merge($search_parts, [
				$row['service']->id,
				$row['service']->domain,
				$row['service']->username,
				$row['service']->dedicatedip,
				$row['service']->firstname,
				$row['service']->lastname,
				$row['service']->companyname,
				$row['service']->product_name,
			]);
		}
		$row_search = strtolower(implode(' ', array_filter($search_parts, function ($part) {
			return $part !== null && $part !== '';
		})));

		echo '<tr class="pve-sync-row ' . $row_class . '" data-type="' . $row['type'] . '" data-search="' . htmlspecialchars($row_search, ENT_QUOTES, 'UTF-8') . '">';

		// Proxmox side column
		echo '<td>';
		if ($row['pve_vm']) {
			$cores = $row['pve_vm']['maxcpu'] ?? 0;
			$ram = isset($row['pve_vm']['maxmem']) ? round($row['pve_vm']['maxmem'] / 1024 / 1024) . ' MB' : '—';
			$disk = isset($row['pve_vm']['maxdisk']) ? round($row['pve_vm']['maxdisk'] / 1024 / 1024 / 1024) . ' GB' : '—';
			$pve_specs = "{$cores} Cores / {$ram} / {$disk}";

			// IP highlight check
			$ip_mismatch_class = '';
			if ($row['service'] && $row['service']->dedicatedip !== $row['pve_vm']['ip']) {
				$ip_mismatch_class = ' mismatch';
			}
			$status_badge_class = ($row['pve_vm']['status'] === 'running') ? 'status-running' : 'status-stopped';
			$status_display = ($row['pve_vm']['status'] === 'running') ? 'Running' : 'Stopped';

			echo '<div class="pve-entity-card">
				<div class="pve-entity-title"><strong>[' . $row['pve_vm']['vmid'] . '] ' . htmlspecialchars($row['pve_vm']['name']) . '</strong></div>
				<div class="pve-entity-meta">
					<span><i class="fa fa-server"></i> Node: ' . htmlspecialchars($row['pve_vm']['node']) . '</span>
					<span><i class="fa fa-info-circle"></i> Type: ' . htmlspecialchars($row['pve_vm']['type']) . '</span>
				</div>
				<div class="pve-entity-specs">
					<span><i class="fa fa-microchip"></i> ' . $cores . ' CPU</span>
					<span><i class="fa fa-memory"></i> ' . $ram . '</span>
					<span><i class="fa fa-hdd"></i> ' . $disk . '</span>
				</div>
				<div style="margin-top: 4px;">
					<span class="pve-ip-badge' . $ip_mismatch_class . '"><i class="fa fa-globe"></i> ' . htmlspecialchars($row['pve_vm']['ip'] ?: '—') . '</span>
					<span class="pve-status-badge ' . $status_badge_class . '">' . $status_display . '</span>
				</div>
			</div>';
		} else {
			echo '<div class="pve-placeholder-empty">No VM found on Proxmox</div>';
		}
		echo '</td>';

		// Status Alignment column
		echo '<td style="text-align:center;vertical-align:middle;">';
		echo $badge;
		if ($row['type'] === 'discrepancy' && !empty($reasons_html)) {
			echo $reasons_html;
		}
		echo '</td>';

		// WHMCS side column
		echo '<td>';
		if ($row['service']) {
			$client_name = trim($row['service']->firstname . ' ' . $row['service']->lastname);
			if (empty($client_name)) {
				$client_name = 'Client #' . $row['service']->userid;
			}
			if (!empty($row['service']->companyname)) {
				$client_name .= ' (' . $row['service']->companyname . ')';
			}

			$status_colors = [
				'Active' => 'active',
				'Suspended' => 'suspended',
				'Terminated' => 'terminated',
				'Cancelled' => 'cancelled',
				'Pending' => 'pending'
			];
			$srv_status_class = isset($status_colors[$row['service']->domainstatus]) ? $status_colors[$row['service']->domainstatus] : 'cancelled';

			// IP highlight check
			$ip_mismatch_class = '';
			if ($row['pve_vm'] && $row['service']->dedicatedip !== $row['pve_vm']['ip']) {
				$ip_mismatch_class = ' mismatch';
			}

			echo '<div class="pve-entity-card">
				<div class="pve-entity-title">
					<a href="clientsservices.php?id=' . $row['service']->id . '" target="_blank" style="font-weight:700;color:#4a2f63;margin-right:5px;">#' . $row['service']->id . '</a>
					<strong>' . htmlspecialchars($row['service']->domain ?: '(No domain)') . '</strong>
				</div>
				<div class="pve-entity-meta">
					<span><i class="fa fa-user"></i> ' . htmlspecialchars($client_name) . '</span>
					<span><i class="fa fa-tag"></i> ' . htmlspecialchars($row['service']->product_name) . '</span>
				</div>
				<div style="margin-top: 4px;">
					<span class="pve-ip-badge' . $ip_mismatch_class . '"><i class="fa fa-globe"></i> ' . htmlspecialchars($row['service']->dedicatedip ?: '—') . '</span>
					<span class="pve-status-badge srv-status-badge ' . $srv_status_class . '">' . htmlspecialchars($row['service']->domainstatus) . '</span>
				</div>
			</div>';
		} else {
			echo '<div class="pve-placeholder-empty">No active WHMCS service linked</div>';
		}
		echo '</td>';

		// Action column
		echo '<td style="text-align:center;vertical-align:middle;padding:12px 10px;">';
		echo $actions_html;
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table></div>
	<div class="pve-sync-no-results" role="status">No records match the current filter and search.</div>';

	// Apply the status filter and text search without reloading the analysis.
	echo '
	<script>
	jQuery(document).ready(function($) {
		var $rows = $(".pve-sync-row");
		var $container = $(".pve-sync-container");
		var $emptyState = $(".pve-sync-no-results");

		function applyFilters() {
			var filter = $(".pve-filter-button[aria-pressed=\'true\']").data("filter") || "attention";
			var query = $.trim($("#pve-sync-search").val().toLowerCase());
			var visibleCount = 0;

			$rows.each(function() {
				var $row = $(this);
				var type = $row.data("type");
				var typeMatches = filter === "all"
					|| (filter === "attention" && type !== "match")
					|| type === filter;
				var searchMatches = query === "" || String($row.data("search") || "").indexOf(query) !== -1;
				var visible = typeMatches && searchMatches;

				$row.toggle(visible);
				if (visible) {
					visibleCount++;
				}
			});

			$container.toggle(visibleCount > 0);
			$emptyState.toggle(visibleCount === 0);
		}

		$(".pve-filter-button").on("click", function() {
			$(".pve-filter-button").attr("aria-pressed", "false");
			$(this).attr("aria-pressed", "true");
			applyFilters();
		});

		$("#pve-sync-search").on("input", applyFilters);
		applyFilters();
	});
	</script>';
}
?>
