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
$port       = $_GET['port'];

// ---------------------------------------
// Resolve the PVE hostname (the browser must reach PVE by name, with a valid TLS cert)
// ---------------------------------------
$hostname = filter_var($host, FILTER_VALIDATE_IP) ? gethostbyaddr($host) : $host;
if (!preg_match('/^[A-Za-z0-9.-]+$/', $hostname) || !ctype_digit((string) $port)) {
    echo 'Error: Invalid PVE host or port.';
    exit;
}

// ---------------------------------------
// Cookie domain = shared parent domain of WHMCS and PVE
// e.g. billing.example.co.id + pve1.example.co.id => .example.co.id
// (works for 2-part TLDs like .co.id / .co.uk)
// ---------------------------------------
$whmcsParts = array_reverse(explode('.', strtolower(explode(':', $_SERVER['HTTP_HOST'])[0])));
$pveParts   = array_reverse(explode('.', strtolower($hostname)));
$common     = array();
foreach ($whmcsParts as $i => $label) {
    if (!isset($pveParts[$i]) || $pveParts[$i] !== $label) {
        break;
    }
    $common[] = $label;
}
if (count($common) < 2) {
    echo 'Error: WHMCS (' . htmlspecialchars($_SERVER['HTTP_HOST']) . ') and PVE (' . htmlspecialchars($hostname) . ') must be subdomains of the same domain for noVNC.';
    exit;
}
$mainDomain = implode('.', array_reverse($common));

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
$redirect_url  = './novnc/vnc.html?autoconnect=true&encrypt=true'
               . '&host=' . $hostname
               . '&port=' . $port
               . '&password=' . urlencode($vncticket)
               . '&path=' . urlencode($path);

// ---------------------------------------
// Redirect to noVNC
// ---------------------------------------
header('Location: ' . $redirect_url);
exit;

?>