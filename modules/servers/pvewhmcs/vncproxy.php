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

// Server socket: plain (behind a reverse proxy) or TLS (own public port).
// TLS mode still listens on tcp:// and runs each TLS handshake non-blocking
// (see vncproxy_handshake), so a client that never finishes it cannot stall others.
$tls = !empty($opts['cert']);
$context = stream_context_create($tls ? array('ssl' => array(
	'local_cert' => $opts['cert'],
	'local_pk' => $opts['key'] ?? $opts['cert'],
	'verify_peer' => false,
)) : array());
$server = stream_socket_server('tcp://' . $listen, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (!$server) {
	fwrite(STDERR, "Cannot listen on {$listen}: {$errstr}\n");
	exit(1);
}
stream_set_blocking($server, false);
vncproxy_log("Listening on {$listen}" . ($tls ? ' (TLS)' : ''));

const VNCPROXY_MAX_BUFFER = 1048576;   // per-socket output buffer before we stop reading its peer
const VNCPROXY_MAX_PENDING = 200;      // unauthenticated connections, total
const VNCPROXY_MAX_PENDING_IP = 10;    // ...per client IP (not applied to loopback = reverse proxy)
const VNCPROXY_HANDSHAKE_TIMEOUT = 10; // seconds to finish TLS + WebSocket handshake

$pending = array(); // socket id => ['sock', 'buf', 'since', 'ip', 'tls' => TLS handshake still to do]
$peers = array();   // socket id => peer socket (both directions)
$socks = array();   // socket id => socket (established)
$out = array();     // socket id => bytes waiting to be written to it

while (true) {
	$read = array($server);
	$write = array();
	foreach ($pending as $p) {
		$read[] = $p['sock'];
	}
	foreach ($socks as $id => $s) {
		// Backpressure: only read a socket while its peer can take more
		if (strlen($out[(int) $peers[$id]]) < VNCPROXY_MAX_BUFFER) {
			$read[] = $s;
		}
		if ($out[$id] !== '') {
			$write[] = $s;
		}
	}
	$except = null;
	if (@stream_select($read, $write, $except, 1) === false) {
		usleep(100000);
		continue;
	}

	foreach ($write as $sock) {
		$id = (int) $sock;
		if (isset($socks[$id]) && vncproxy_flush($sock) && isset($peers[$id])) {
			// Room again: pull anything the peer has buffered (TLS keeps data select() can't see)
			vncproxy_relay($peers[$id]);
		}
	}

	foreach ($read as $sock) {
		if ($sock === $server) {
			vncproxy_accept($server);
			continue;
		}
		$id = (int) $sock;
		if (isset($pending[$id])) {
			vncproxy_handshake($sock);
		} elseif (isset($socks[$id])) {
			vncproxy_relay($sock);
		}
	}

	// Drop clients that never finished the handshake
	foreach ($pending as $id => $p) {
		if (time() - $p['since'] > VNCPROXY_HANDSHAKE_TIMEOUT) {
			@fclose($p['sock']);
			unset($pending[$id]);
		}
	}
}

/**
 * Accept new connections, enforcing the pending-connection caps.
 */
function vncproxy_accept($server) {
	global $pending, $tls;
	while ($client = @stream_socket_accept($server, 0, $peername)) {
		$ip = preg_replace('/:\d+$/', '', (string) $peername);
		$same_ip = 0;
		foreach ($pending as $p) {
			$same_ip += ($p['ip'] === $ip) ? 1 : 0;
		}
		$loopback = ($ip === '127.0.0.1' || $ip === '[::1]' || $ip === '::1');
		if (count($pending) >= VNCPROXY_MAX_PENDING || (!$loopback && $same_ip >= VNCPROXY_MAX_PENDING_IP)) {
			fclose($client);
			continue;
		}
		stream_set_blocking($client, false);
		$pending[(int) $client] = array('sock' => $client, 'buf' => '', 'since' => time(), 'ip' => $ip, 'tls' => $tls);
		vncproxy_handshake($client);
	}
}

/**
 * Advance a pending connection: TLS handshake (non-blocking), then read the
 * WebSocket request; once complete, pair it with PVE.
 */
function vncproxy_handshake($sock) {
	global $pending, $peers, $socks, $out;
	$id = (int) $sock;
	if ($pending[$id]['tls']) {
		$done = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
		if ($done === 0) {
			return; // needs more data from the client
		}
		if ($done !== true) {
			fclose($sock);
			unset($pending[$id]);
			return;
		}
		$pending[$id]['tls'] = false;
	}
	while (($chunk = fread($sock, 8192)) !== false && $chunk !== '') {
		$pending[$id]['buf'] .= $chunk;
		if (strlen($pending[$id]['buf']) > 16384) {
			fclose($sock);
			unset($pending[$id]);
			return;
		}
	}
	$buf = $pending[$id]['buf'];

	if (strpos($buf, "\r\n\r\n") !== false) {
		unset($pending[$id]);
		$opened = vncproxy_open($sock, $buf);
		if (!$opened) {
			fclose($sock);
			return;
		}
		$pve = $opened['pve'];
		$peers[$id] = $pve;
		$peers[(int) $pve] = $sock;
		$socks[$id] = $sock;
		$socks[(int) $pve] = $pve;
		$out[$id] = $opened['to_client'];
		$out[(int) $pve] = '';
		vncproxy_flush($sock);
		// Pull anything already buffered on either side
		vncproxy_relay($pve);
		vncproxy_relay($sock);
	} elseif (feof($sock)) {
		fclose($sock);
		unset($pending[$id]);
	}
}

/**
 * Move readable bytes from $sock into its peer's output buffer (up to the cap),
 * then try to write them. Closes the pair on EOF or error.
 */
function vncproxy_relay($sock) {
	global $peers, $socks, $out;
	$id = (int) $sock;
	if (!isset($socks[$id])) {
		return;
	}
	$peer = $peers[$id];
	$pid = (int) $peer;
	while (strlen($out[$pid]) < VNCPROXY_MAX_BUFFER) {
		$chunk = fread($sock, 65536);
		if ($chunk === false || $chunk === '') {
			break;
		}
		$out[$pid] .= $chunk;
	}
	if (feof($sock)) {
		vncproxy_close($sock);
		return;
	}
	vncproxy_flush($peer);
}

/**
 * Write as much of the socket's output buffer as it accepts right now.
 *
 * @return bool false if the pair was closed
 */
function vncproxy_flush($sock) {
	global $out;
	$id = (int) $sock;
	if (!isset($out[$id]) || $out[$id] === '') {
		return true;
	}
	$n = @fwrite($sock, $out[$id]);
	if ($n === false) {
		vncproxy_close($sock);
		return false;
	}
	$out[$id] = (string) substr($out[$id], $n);
	return true;
}

function vncproxy_close($sock) {
	global $peers, $socks, $out;
	$id = (int) $sock;
	$pair = array($sock);
	if (isset($peers[$id])) {
		$pair[] = $peers[$id];
	}
	foreach ($pair as $s) {
		unset($peers[(int) $s], $socks[(int) $s], $out[(int) $s]);
		@fclose($s);
	}
}

/**
 * Validate the browser handshake + token, connect to PVE, finish its handshake.
 *
 * @return array|false ['pve' => PVE socket, 'to_client' => bytes for the browser],
 *                     or false (the browser got an HTTP error)
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

	// One-time token (looked up by its SHA-256), payload decrypted and bound to the row
	$row = vncproxy_take_token(hash('sha256', $token));
	if (!$row) {
		vncproxy_reject($client, '403 Forbidden');
		vncproxy_log('Rejected unknown/expired/used token');
		return false;
	}

	// WebSocket to PVE, authenticated with the root API ticket
	$host = filter_var($row['pve_host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $row['pve_host'] . ']' : $row['pve_host'];
	$context = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
	$pve = @stream_socket_client('tls://' . $host . ':' . $row['pve_port'], $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
	if (!$pve) {
		vncproxy_reject($client, '502 Bad Gateway');
		vncproxy_log("PVE connect failed: {$errstr}");
		return false;
	}
	stream_set_timeout($pve, 5);
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

	stream_set_blocking($pve, false);
	vncproxy_log("Console opened: {$row['pve_host']} " . strtok($row['path'], '?'));
	return array('pve' => $pve, 'to_client' => $reply . "\r\n" . $leftover);
}

/**
 * Fetch a valid, unused token row and decrypt its payload. The row is kept
 * (secrets wiped) until it expires, so the router's rate limit still counts it.
 *
 * @return array|null ['pve_host', 'pve_port', 'path', 'pveticket']
 */
function vncproxy_take_token($token_hash) {
	global $db_host, $db_port, $db_username, $db_password, $db_name;
	try {
		$dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4' . (!empty($db_port) ? ';port=' . $db_port : '');
		$pdo = new PDO($dsn, $db_username, $db_password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5));
		$stmt = $pdo->prepare("SELECT * FROM mod_pvewhmcs_vnc_tokens WHERE token_hash = ? AND expires > NOW() AND payload <> ''");
		$stmt->execute(array($token_hash));
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$pdo->prepare("UPDATE mod_pvewhmcs_vnc_tokens SET payload = '' WHERE token_hash = ?")->execute(array($token_hash));
		$pdo->exec('DELETE FROM mod_pvewhmcs_vnc_tokens WHERE expires < NOW()');
		if (!$row) {
			return null;
		}
		$payload = json_decode((string) vncproxy_decrypt($row['payload'], $token_hash . '|' . $row['pve_host'] . '|' . $row['pve_port']), true);
		if (empty($payload['path']) || empty($payload['pveticket'])) {
			return null;
		}
		return array('pve_host' => $row['pve_host'], 'pve_port' => (int) $row['pve_port'], 'path' => $payload['path'], 'pveticket' => $payload['pveticket']);
	} catch (Exception $e) {
		vncproxy_log('DB error: ' . $e->getMessage());
		return null;
	}
}

/**
 * Decrypt a payload written by pvewhmcs_vnc_encrypt() (same key derivation, same AAD).
 *
 * @return string|false
 */
function vncproxy_decrypt($stored, $aad) {
	global $cc_encryption_hash;
	$raw = base64_decode((string) $stored, true);
	if ($raw === false || strlen($raw) < 29) {
		return false;
	}
	$key = hash('sha256', 'pvewhmcs-vnc|' . $cc_encryption_hash, true);
	return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $aad);
}

function vncproxy_reject($client, $status) {
	@fwrite($client, "HTTP/1.1 {$status}\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
}

function vncproxy_log($msg) {
	fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . $msg . "\n");
}
