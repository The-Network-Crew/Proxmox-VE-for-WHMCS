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

if (isset($_GET['pveticket']) && isset($_GET['host']) && isset($_GET['path']) && isset($_GET['vncticket'])) {
	// Take passed-in variables and re-assign for usage
	$pveticket = $_GET['pveticket'];
	$vncticket = $_GET['vncticket'];
	//$host = $_GET['host'];
	$host = "hostscape.eu/vncwebsocket/?";
	$path = $_GET['path'];


	// Get the requesting hostname/domain from the WHMCS-originated request
	$whmcsdomain = parse_url($_SERVER['HTTP_HOST']);
	// Now extract just the domain parts we need (FUTURE: capacity/option for multi-part TLDs)
	$domainonly = preg_replace("/^(.*?)\.(.*)$/","$2",$whmcsdomain['path']);
	$domainonly = 'hostscape.eu';
	// Set the cookie as Proxmox will be expecting it, so it is WHMCS to VNC without further login

	$domain = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
        setrawcookie('PVEAuthCookie', $pveticket, 0, '/', $domain, true, false);

	// Create the final noVNC URL with the re-encoded vncticket
	//$hostname = gethostbyaddr($host);
	$redirect_url = '/modules/servers/pvewhmcs/novnc/vnc.html?autoconnect=true&encrypt=true&host=' . $host . '&password=' . urlencode($vncticket) . '&path=' . urlencode($path);



	header('Location: ' . $redirect_url);
	exit;
} else {
	// Passed in values not present, exit
	echo 'Error: Missing required info to route your request. Please try again.';
	exit;
}
?>
