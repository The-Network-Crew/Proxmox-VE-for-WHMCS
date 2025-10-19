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
// Clear any existing PVEAuthCookie first
// ---------------------------------------
setcookie('PVEAuthCookie', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => false,
    'samesite' => 'None',
]);

// ---------------------------------------
// Check required GET parameters
// ---------------------------------------
if (!isset($_GET['pveticket'], $_GET['host'], $_GET['path'], $_GET['vncticket'])) {
    echo 'Error: Missing required info to route your request. Please try again.';
    exit;
}

// ---------------------------------------
// Assign GET parameters
// ---------------------------------------
$pveticket  = $_GET['pveticket'];
$vncticket  = $_GET['vncticket'];
$host       = $_GET['host'];
$path       = $_GET['path'];

// ---------------------------------------
// Determine main domain for cookie
// ---------------------------------------
$hostParts  = explode('.', $_SERVER['HTTP_HOST']);
$mainDomain = implode('.', array_slice($hostParts, -2)); // example.com

// ---------------------------------------
// Set the PVEAuthCookie for Proxmox
// ---------------------------------------
setrawcookie('PVEAuthCookie', $pveticket, [
    'expires'  => 0,
    'path'     => '/',
    'domain'   => '.' . $mainDomain,
    'secure'   => true,
    'httponly' => false,
    'samesite' => 'None',
]);

// ---------------------------------------
// Build final noVNC URL
// ---------------------------------------
$hostname      = gethostbyaddr($host);
$redirect_url  = './novnc/vnc.html?autoconnect=true&encrypt=true'
               . '&host=' . $hostname
               . '&port=8006'
               . '&password=' . urlencode($vncticket)
               . '&path=' . urlencode($path);

// ---------------------------------------
// Redirect to noVNC
// ---------------------------------------
header('Location: ' . $redirect_url);
exit;

?>