<?php

/*  
    Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
    https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
    File: /modules/servers/pvewhmcs/novnc_router.php (VNC)

    Copyright (C) The Network Crew Pty Ltd (TNC) & Co.

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

// ---------------------------------------
// Boot WHMCS (session + database)
// ---------------------------------------
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/pvewhmcs.php';

use WHMCS\Database\Capsule;

// ---------------------------------------
// The link must be signed by pvewhmcs_noVNC(), which WHMCS only runs for
// users allowed to manage this service (incl. sub-user permissions)
// ---------------------------------------
$serviceid = isset($_GET['serviceid']) ? (int) $_GET['serviceid'] : 0;
$expires   = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
$sig       = isset($_GET['sig']) ? (string) $_GET['sig'] : '';
if ($expires < time() || !hash_equals(pvewhmcs_vnc_link_sig($serviceid, $expires), $sig)) {
    http_response_code(403);
    echo 'Error: This console link has expired. Please click noVNC in the Client Area again.';
    exit;
}

// ---------------------------------------
// ...and still be used by the owning client (or a WHMCS admin) in this session
// ---------------------------------------
$service   = Capsule::table('tblhosting')->where('id', $serviceid)->first();
$clientid  = (int) WHMCS\Session::get('uid');
$adminid   = (int) WHMCS\Session::get('adminid');
if (!$service || (!$adminid && (!$clientid || (int) $service->userid !== $clientid))) {
    http_response_code(403);
    echo 'Error: Please log in to WHMCS to open the console.';
    exit;
}
if (!$adminid && $service->domainstatus !== 'Active') {
    http_response_code(403);
    echo 'Error: Console is only available for Active services.';
    exit;
}

// ---------------------------------------
// Open the VNC session on PVE, get a one-time token for vncproxy.php
// ---------------------------------------
try {
    $vnc = pvewhmcs_vnc_prepare($serviceid);
} catch (Exception $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}

// ---------------------------------------
// Where the browser reaches vncproxy.php (Module Config > noVNC Proxy URL)
// ---------------------------------------
$proxy_url = (string) Capsule::table('mod_pvewhmcs')->where('id', '1')->value('config');
if ($proxy_url === '') {
    $proxy_url = 'wss://' . parse_url($CONFIG['SystemURL'], PHP_URL_HOST) . '/pvewhmcs-vnc';
}
$proxy   = parse_url($proxy_url);
$encrypt = (($proxy['scheme'] ?? 'wss') !== 'ws');
$port    = $proxy['port'] ?? ($encrypt ? 443 : 80);
$path    = ltrim($proxy['path'] ?? '', '/') . '?token=' . $vnc['token'];

// ---------------------------------------
// Redirect to noVNC (served by WHMCS). Settings go in the #fragment, which
// browsers never send to a server, so the VNC ticket and token stay out of
// access logs and Referer headers.
// ---------------------------------------
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('Location: ./novnc/vnc.html#autoconnect=true&resize=scale'
    . '&encrypt=' . ($encrypt ? 'true' : 'false')
    . '&host=' . rawurlencode($proxy['host'])
    . '&port=' . (int) $port
    . '&password=' . rawurlencode($vnc['password'])
    . '&path=' . rawurlencode($path));
exit;
