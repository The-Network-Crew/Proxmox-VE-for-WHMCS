<?php

/*  
    Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
    https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
    File: /modules/addons/pvewhmcs/hooks.php (WHMCS Hooks)

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

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function pvewhmcs_hook_login($vars)
{
    // Your code goes here
}

// Define Client Login Hook Call
add_hook("ClientLogin", 1, "pvewhmcs_hook_login");

function pvewhmcs_hook_logout($vars)
{
    // Your code goes here
}

// Define Client Logout Hook Call
add_hook("ClientLogout", 1, "pvewhmcs_hook_logout");
