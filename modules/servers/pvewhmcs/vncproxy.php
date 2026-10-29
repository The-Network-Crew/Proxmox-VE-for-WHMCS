<?php

/*
	Proxmox VE for WHMCS - Addon/Server Modules for WHMCS (& PVE)
	https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
	File: /modules/servers/pvewhmcs/vncproxy.php (noVNC WebSocket proxy)

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

/*
	Runs on the WHMCS server (CLI, e.g. via systemd). Browsers connect here,
	never to PVE. Each connection must carry a one-time ?token= created by
	novnc_router.php; the proxy then opens the VNC WebSocket on PVE with the
	root API ticket stored for that token and relays bytes both ways.

	Usage:
	  php vncproxy.php                                  (listen 127.0.0.1:8100, put nginx/Apache in front)
	  php vncproxy.php --listen=0.0.0.0:8100 --cert=/path/fullchain.pem --key=/path/privkey.pem   (own TLS port)
*/

if (PHP_SAPI !== 'cli') {
	exit;
}

$opts = getopt('', array('listen:', 'cert:', 'key:', 'config:'));
$listen = $opts['listen'] ?? '127.0.0.1:8100';
$config_file = $opts['config'] ?? __DIR__ . '/../../../configuration.php';

// DB credentials from WHMCS configuration.php
if (!is_readable($config_file)) {
	fwrite(STDERR, "Cannot read WHMCS configuration: {$config_file}\n");
	exit(1);
}
include $config_file;

// Server socket: plain (behind a reverse proxy) or TLS (own public port)
if (!empty($opts['cert'])) {
	$context = stream_context_create(array('ssl' => array(
		'local_cert' => $opts['cert'],
		'local_pk' => $opts['key'] ?? $opts['cert'],
		'verify_peer' => false,
	)));
	$server = stream_socket_server('tls://' . $listen, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
} else {
	$server = stream_socket_server('tcp://' . $listen, $errno, $errstr);
}
if (!$server) {
	fwrite(STDERR, "Cannot listen on {$listen}: {$errstr}\n");
	exit(1);
}
vncproxy_log("Listening on {$listen}" . (!empty($opts['cert']) ? ' (TLS)' : ''));

$pending = array(); // socket id => ['sock' => client, 'buf' => handshake bytes, 'since' => time]
$peers = array();   // socket id => peer socket (both directions)
$socks = array();   // socket id => socket

while (true) {
	$read = array($server);
	foreach ($pending as $p) {
		$read[] = $p['sock'];
	}
	foreach ($socks as $s) {
		$read[] = $s;
	}
	$write = $except = null;
	if (@stream_select($read, $write, $except, 5) === false) {
		continue;
	}

	foreach ($read as $sock) {
		// New browser connection
		if ($sock === $server) {
			$client = @stream_socket_accept($server, 5);
			if ($client) {
				stream_set_blocking($client, false);
				$pending[(int) $client] = array('sock' => $client, 'buf' => '', 'since' => time());
				// TLS may already hold the request, which select() would not report
				vncproxy_handshake($client);
			}
			continue;
		}

		$id = (int) $sock;
		if (isset($pending[$id])) {
			vncproxy_handshake($sock);
		} elseif (isset($peers[$id])) {
			vncproxy_relay($sock);
		}
	}

	// Drop browsers that never finished the handshake
	foreach ($pending as $id => $p) {
		if (time() - $p['since'] > 15) {
			@fclose($p['sock']);
			unset($pending[$id]);
		}
	}
}

/**
 * Read the browser's WebSocket handshake; once complete, pair it with PVE.
 */
function vncproxy_handshake($sock) {
	global $pending, $peers, $socks;
	$id = (int) $sock;
	while (($chunk = fread($sock, 8192)) !== false && $chunk !== '') {
		$pending[$id]['buf'] .= $chunk;
	}
	$buf = $pending[$id]['buf'];

	if (strpos($buf, "\r\n\r\n") !== false) {
		unset($pending[$id]);
		$pve = vncproxy_open($sock, $buf);
		if (!$pve) {
			fclose($sock);
			return;
		}
		$peers[$id] = $pve;
		$peers[(int) $pve] = $sock;
		$socks[$id] = $sock;
		$socks[(int) $pve] = $pve;
		// Flush anything already buffered on either side
		vncproxy_relay($pve);
		vncproxy_relay($sock);
	} elseif (feof($sock) || strlen($buf) > 16384) {
		fclose($sock);
		unset($pending[$id]);
	}
}

/**
 * Relay everything readable on $sock to its peer; close both on EOF/error.
 * Reads until empty so no TLS-buffered data is left behind.
 */
function vncproxy_relay($sock) {
	global $peers, $socks;
	$id = (int) $sock;
	if (!isset($peers[$id])) {
		return;
	}
	$peer = $peers[$id];
	$ok = true;
	while (($chunk = fread($sock, 65536)) !== false && $chunk !== '') {
		if (!vncproxy_write($peer, $chunk)) {
			$ok = false;
			break;
		}
	}
	if (!$ok || feof($sock)) {
		foreach (array($sock, $peer) as $s) {
			unset($peers[(int) $s], $socks[(int) $s]);
			@fclose($s);
		}
	}
}

/**
 * Validate the browser handshake + token, connect to PVE, finish both handshakes.
 *
 * @return resource|false PVE socket, or false (the browser got an HTTP error)
 */
function vncproxy_open($client, $request) {
	$lines = explode("\r\n", substr($request, 0, strpos($request, "\r\n\r\n")));
	$headers = array();
	foreach (array_slice($lines, 1) as $line) {
		$parts = explode(':', $line, 2);
		if (count($parts) == 2) {
			$headers[strtolower(trim($parts[0]))] = trim($parts[1]);
		}
	}
	$target = explode(' ', $lines[0])[1] ?? '';
	parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
	$token = (string) ($query['token'] ?? '');

	if (empty($headers['sec-websocket-key']) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
		vncproxy_reject($client, '400 Bad Request');
		return false;
	}

	// One-time token: read and delete
	$row = vncproxy_take_token($token);
	if (!$row) {
		vncproxy_reject($client, '403 Forbidden');
		vncproxy_log('Rejected unknown/expired token');
		return false;
	}

	// WebSocket to PVE, authenticated with the root API ticket
	$host = filter_var($row['pve_host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $row['pve_host'] . ']' : $row['pve_host'];
	$context = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
	$pve = @stream_socket_client('tls://' . $host . ':' . $row['pve_port'], $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
	if (!$pve) {
		vncproxy_reject($client, '502 Bad Gateway');
		vncproxy_log("PVE connect failed: {$errstr}");
		return false;
	}
	stream_set_timeout($pve, 10);
	fwrite($pve, "GET {$row['path']} HTTP/1.1\r\n"
		. "Host: {$host}:{$row['pve_port']}\r\n"
		. "Upgrade: websocket\r\n"
		. "Connection: Upgrade\r\n"
		. "Sec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\n"
		. "Sec-WebSocket-Version: 13\r\n"
		. "Sec-WebSocket-Protocol: binary\r\n"
		. "Cookie: PVEAuthCookie=" . rawurlencode($row['pveticket']) . "\r\n\r\n");
	$response = '';
	while (strpos($response, "\r\n\r\n") === false && strlen($response) < 16384) {
		$chunk = fread($pve, 4096);
		if ($chunk === '' || $chunk === false) {
			break;
		}
		$response .= $chunk;
	}
	if (!preg_match('#^HTTP/1\.[01] 101#', $response)) {
		vncproxy_reject($client, '502 Bad Gateway');
		vncproxy_log('PVE refused WebSocket: ' . strtok($response, "\r\n"));
		fclose($pve);
		return false;
	}
	// Bytes PVE already sent after its handshake
	$leftover = (string) substr($response, strpos($response, "\r\n\r\n") + 4);

	// Accept the browser. Frames are relayed as-is: browser frames are already
	// masked (as PVE expects from a client), PVE frames are unmasked (as the
	// browser expects from a server). No extensions are negotiated.
	$accept = base64_encode(sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
	$reply = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n";
	$protocols = array_map('trim', explode(',', $headers['sec-websocket-protocol'] ?? ''));
	if (in_array('binary', $protocols)) {
		$reply .= "Sec-WebSocket-Protocol: binary\r\n";
	}
	vncproxy_write($client, $reply . "\r\n" . $leftover);

	stream_set_blocking($pve, false);
	vncproxy_log("Console opened: {$row['pve_host']} " . strtok($row['path'], '?'));
	return $pve;
}

/**
 * Fetch and delete a non-expired token row (fresh DB connection each time).
 */
function vncproxy_take_token($token) {
	global $db_host, $db_port, $db_username, $db_password, $db_name;
	try {
		$dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4' . (!empty($db_port) ? ';port=' . $db_port : '');
		$pdo = new PDO($dsn, $db_username, $db_password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$stmt = $pdo->prepare('SELECT * FROM mod_pvewhmcs_vnc_tokens WHERE token = ? AND expires > NOW()');
		$stmt->execute(array($token));
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$pdo->prepare('DELETE FROM mod_pvewhmcs_vnc_tokens WHERE token = ?')->execute(array($token));
		return $row ?: null;
	} catch (Exception $e) {
		vncproxy_log('DB error: ' . $e->getMessage());
		return null;
	}
}

// Write everything, waiting while the (non-blocking) socket is full
function vncproxy_write($sock, $data) {
	$stalls = 0;
	while ($data !== '') {
		$n = @fwrite($sock, $data);
		if ($n === false) {
			return false;
		}
		if ($n === 0) {
			$r = $e = null;
			$w = array($sock);
			if (++$stalls > 20 || !stream_select($r, $w, $e, 1)) {
				return false;
			}
			continue;
		}
		$stalls = 0;
		$data = (string) substr($data, $n);
	}
	return true;
}

function vncproxy_reject($client, $status) {
	@fwrite($client, "HTTP/1.1 {$status}\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
}

function vncproxy_log($msg) {
	fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . $msg . "\n");
}
