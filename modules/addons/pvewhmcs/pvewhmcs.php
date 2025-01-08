<?php
/**
 * Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
 * https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
 * File: /modules/addons/pvewhmcs/pvewhmcs.php (GUI Work)
 *
 * Copyright (C) The Network Crew Pty Ltd (TNC) & Co. & DeVeLab
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Pull in the WHMCS database handler Capsule for SQL
use Illuminate\Database\Capsule\Manager as Capsule;

use WHMCS\Module\Addon\PVEWhmcs\Admin\AdminDispatcher;


// CONFIG: Declare key options to the WHMCS Addon Module framework.
function pvewhmcs_config() {
	$configarray = array(
		"name" => "Proxmox VE for WHMCS",
		"description" => "Proxmox VE (Virtual Environment) & WHMCS, integrated & open-source! Provisioning & Management of VMs/CTs.".is_pvewhmcs_outdated(),
		"version" => "1.2.7",
		"author" => "The Network Crew Pty Ltd",
		'language' => 'English'
	);
	return $configarray;
}

// VERSION: also stored in repo/version (for update-available checker)
function pvewhmcs_version(){
    return "1.2.7";
}

// WHMCS MODULE: ACTIVATION of the ADDON MODULE
// This consists of importing the SQL structure, and then crudely returning yay or nay (needs improving)
function pvewhmcs_activate()
{
    $sql = file_get_contents(__DIR__.'/db.sql');

    if (!$sql) {
        return ['status' => 'error', 'description' => 'The db.sql file was not found.'];
    }

    // Split SQL queries and initialize error tracking
    $queries = array_filter(array_map('trim', explode(';', $sql))); // Trim and remove empty queries
    $hasError = false;
    // Execute each query in the array
    foreach ($queries as $query) {
        if (!Capsule::statement($query.';')) { // Add semicolon for valid query execution
            $hasError = true;
        }
    }

    if ($hasError) {
        return ['status' => 'error', 'description' => 'One or more SQL queries failed.'];
    }
    return ['status' => 'success', 'description' => 'Database initialized successfully.'];

}

// WHMCS MODULE: DEACTIVATION
function pvewhmcs_deactivate()
{
    $tables = [
        'mod_pvewhmcs_ip_addresses',
        'mod_pvewhmcs_ip_pools',
        'mod_pvewhmcs_plans',
        'mod_pvewhmcs_vms',
        'mod_pvewhmcs'
    ];
    $hasError = false;
    foreach ($tables as $table) {
        $query = "DROP TABLE IF EXISTS $table";

        if (!Capsule::statement($query)) {
            $hasError = true;
            break; // Stop on the first error
        }
    }
    // Return the result
    if ($hasError) {
        return ['status' => 'error', 'description' => 'Failed to drop one or more tables related to Proxmox VE for WHMCS.'];
    }
    return ['status' => 'success', 'description' => 'Proxmox VE for WHMCS successfully deactivated and all related tables deleted.'];
}

// UPDATE CHECKER: live vs repo
function is_pvewhmcs_outdated()
{
    if (get_pvewhmcs_latest_version() > pvewhmcs_version()) {
        return "<br><span style='float:right;'><b>Proxmox VE for WHMCS is outdated: <a style='color:red' href='https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases'>Download the new version!</a></span>";
    }
}

// UPDATE CHECKER: return latest ver
function get_pvewhmcs_latest_version()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/The-Network-Crew/Proxmox-VE-for-WHMCS/master/version");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return str_replace("\n", "", $result);
}

// ADMIN MODULE GUI: output (HTML etc)
function pvewhmcs_output($vars)
{
    $smarty = new Smarty();
    $smarty->caching = 0;
    $smarty->setTemplateDir($_SERVER['DOCUMENT_ROOT'].'/modules/addons/pvewhmcs/templates');
    $smarty->allow_html = true;
    $smarty->auto_literal = false;
    $vars['smarty'] = $smarty;
    if (!empty(is_pvewhmcs_outdated())) {
        $smarty->assign("notification", [
            'title'   => 'Proxmox VE for WHMCS: New version available!',
            'message' => 'Please visit the GitHub repository > Releases page. https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases.',
            'type'    => 'success' // can be 'success', 'info', 'warning', or 'error'
        ]);
    }
    if (isset($_SESSION['pvewhmcs']['notification'])) {
        $smarty->assign("notification", $_SESSION['pvewhmcs']['notification']);
        unset($_SESSION['pvewhmcs']);
    }

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    $dispatcher = new AdminDispatcher();
    // If it's a POST request, pass POST data to the dispatcher
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_REQUEST; // Collect the POST / GET data
        $response = $dispatcher->dispatchPOST($action, $vars, $postData); // Pass POST data to dispatcher
    } else {
        // Handle GET requests
        $response = $dispatcher->dispatchGET($action, $vars, []);
    }
    echo $response;

}