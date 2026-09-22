<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/** Return the original network adapters without changing their provider strings. */
function pvewhmcs_suspension_networks(array $config)
{
    $networks = array();
    foreach ($config as $key => $value) {
        if (preg_match('/^net[0-9]+$/', (string) $key)) {
            if (!is_string($value)) {
                throw new RuntimeException('Unexpected network adapter configuration.');
            }
            $networks[$key] = $value;
        }
    }
    ksort($networks);
    return $networks;
}

/** Preserve every adapter option while forcing its virtual link down. */
function pvewhmcs_suspension_isolate_network($network)
{
    if (preg_match('/(^|,)link_down=[^,]*/', $network)) {
        return preg_replace('/(^|,)link_down=[^,]*/', '$1link_down=1', $network);
    }
    return $network . ',link_down=1';
}

/** Select a stable disk or firmware marker to detect guest ID reuse on retries. */
function pvewhmcs_suspension_guest_identity(array $config, $type)
{
    if ($type === 'lxc' && isset($config['rootfs']) && is_string($config['rootfs'])) {
        return hash('sha256', 'rootfs:' . explode(',', $config['rootfs'], 2)[0]);
    }
    if ($type === 'qemu') {
        if (isset($config['smbios1']) && preg_match('/(?:^|,)uuid=([^,]+)/', $config['smbios1'], $matches)) {
            return hash('sha256', 'uuid:' . $matches[1]);
        }
        $disks = array();
        foreach ($config as $key => $value) {
            if (preg_match('/^(scsi|virtio|sata|ide)[0-9]+$/', (string) $key) && is_string($value)
                && strpos($value, 'media=cdrom') === false) {
                $disks[$key] = $value;
            }
        }
        ksort($disks);
        foreach ($disks as $key => $value) {
            $volume = explode(',', $value, 2)[0];
            if ($volume !== '' && $volume !== 'none') {
                return hash('sha256', $key . ':' . $volume);
            }
        }
    }
    throw new RuntimeException('Guest has no stable identity marker for safe suspension.');
}

/** Read a provider response as a confirmed guest state. */
function pvewhmcs_suspension_status($proxmox, $basePath)
{
    $status = $proxmox->get($basePath . '/status/current');
    if (!is_array($status) || !isset($status['status']) || !in_array($status['status'], array('running', 'stopped'), true)) {
        throw new RuntimeException('Unable to verify guest power state.');
    }
    return $status['status'];
}

/** Wait for a provider power task and check its final exit status. */
function pvewhmcs_suspension_wait_task($proxmox, $node, $taskId)
{
    if (!is_string($taskId) || strpos($taskId, 'UPID:') !== 0) {
        throw new RuntimeException('Proxmox did not return a power task ID.');
    }
    $path = '/nodes/' . rawurlencode($node) . '/tasks/' . rawurlencode($taskId) . '/status';
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $task = $proxmox->get($path);
        if (!is_array($task) || !isset($task['status'])) {
            throw new RuntimeException('Unable to read Proxmox power task status.');
        }
        if ($task['status'] === 'stopped') {
            if (isset($task['exitstatus']) && $task['exitstatus'] === 'OK') {
                return;
            }
            throw new RuntimeException('Proxmox power task failed.');
        }
        sleep(1);
    }
    throw new RuntimeException('Timed out waiting for the Proxmox power task.');
}

/** Confirm one exact cluster guest and reject HA-managed resources. */
function pvewhmcs_suspension_find_node($proxmox, $guest)
{
    $resources = $proxmox->get('/cluster/resources');
    if (!is_array($resources)) {
        throw new RuntimeException('Unable to read cluster guest inventory.');
    }
    $matches = array();
    foreach ($resources as $resource) {
        if (is_array($resource) && isset($resource['type'], $resource['vmid'], $resource['node'])
            && $resource['type'] === $guest->vtype && (int) $resource['vmid'] === (int) $guest->vmid) {
            $matches[] = $resource['node'];
        }
    }
    if (count($matches) !== 1 || !preg_match('/^[A-Za-z0-9_.-]+$/', $matches[0])) {
        throw new RuntimeException('Guest identity is missing or ambiguous in the cluster.');
    }
    $haResources = $proxmox->get('/cluster/ha/resources');
    if (!is_array($haResources)) {
        throw new RuntimeException('Unable to verify high-availability management.');
    }
    $haId = ($guest->vtype === 'qemu' ? 'vm:' : 'ct:') . $guest->vmid;
    foreach ($haResources as $resource) {
        if (is_array($resource) && isset($resource['sid']) && $resource['sid'] === $haId) {
            throw new RuntimeException('HA-managed guests cannot be safely suspended by this module.');
        }
    }
    return $matches[0];
}

/** Apply a config update and require the provider to report it back. */
function pvewhmcs_suspension_update_config($proxmox, $basePath, array $changes, array $expected)
{
    if ($changes && $proxmox->put($basePath . '/config', $changes) !== true) {
        throw new RuntimeException('Proxmox rejected the guest configuration update.');
    }
    $config = $proxmox->get($basePath . '/config');
    if (!is_array($config)) {
        throw new RuntimeException('Unable to verify guest configuration.');
    }
    foreach ($expected as $key => $value) {
        if ($key === 'onboot' && (int) $value === 0 && empty($config['onboot'])) {
            continue;
        }
        if (!array_key_exists($key, $config) || (string) $config[$key] !== (string) $value) {
            throw new RuntimeException('Guest configuration update was not confirmed.');
        }
    }
}

/** Execute a recoverable suspension or restoration for one WHMCS service. */
function pvewhmcs_suspension_command(array $params, $restore)
{
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    $serverId = isset($params['serverid']) ? (int) $params['serverid'] : 0;
    $serverHost = isset($params['serverip']) ? (string) $params['serverip'] : '';
    if ($serviceId <= 0 || $serverId <= 0 || $serverHost === '') {
        return 'Error performing action. Missing service or server identity.';
    }
    $lockName = 'pvewhmcs:suspend:' . $serviceId;
    $locked = false;
    try {
        if (!Capsule::schema()->hasTable('mod_pvewhmcs_suspensions')) {
            throw new RuntimeException('Suspension database migration is not installed.');
        }
        $lock = Capsule::select('SELECT GET_LOCK(?, 0) AS acquired', array($lockName));
        if (!isset($lock[0]->acquired) || (int) $lock[0]->acquired !== 1) {
            throw new RuntimeException('Another suspension command is running for this service.');
        }
        $locked = true;
        $guest = Capsule::table('mod_pvewhmcs_vms')->where('id', '=', $serviceId)->first();
        if (!$guest || !in_array($guest->vtype, array('qemu', 'lxc'), true) || (int) $guest->vmid <= 0) {
            throw new RuntimeException('Unable to find a valid guest linked to this service.');
        }
        $hosting = Capsule::table('tblhosting')->where('id', '=', $serviceId)->first();
        if (!$hosting || (int) $hosting->server !== $serverId || (int) $hosting->userid !== (int) $guest->user_id) {
            throw new RuntimeException('Service ownership or server assignment does not match the guest.');
        }
        $duplicate = Capsule::table('mod_pvewhmcs_vms')->where('vmid', '=', $guest->vmid)
            ->where('vtype', '=', $guest->vtype)->where('id', '!=', $serviceId)->first();
        if ($duplicate) {
            throw new RuntimeException('Guest ID is linked to another service.');
        }

        $snapshot = Capsule::table('mod_pvewhmcs_suspensions')->where('service_id', '=', $serviceId)->first();
        $legacyRestore = $restore && !$snapshot && isset($hosting->domainstatus)
            && $hosting->domainstatus === 'Suspended';
        if ($restore && !$snapshot && !$legacyRestore) {
            throw new RuntimeException('No suspension snapshot exists for this service.');
        }
        if ($snapshot && ((int) $snapshot->vmid !== (int) $guest->vmid || $snapshot->vtype !== $guest->vtype
            || (int) $snapshot->server_id !== $serverId || $snapshot->server_host !== $serverHost)) {
            throw new RuntimeException('Saved suspension identity does not match the current guest.');
        }

        $serverPort = !empty($params['serverport']) ? $params['serverport'] : 8006;
        $proxmox = new PVE2_API($serverHost, $params['serverusername'], 'pam', $params['serverpassword'], $serverPort);
        if (!$proxmox->login()) {
            throw new RuntimeException('Unable to log in to Proxmox.');
        }
        $node = pvewhmcs_suspension_find_node($proxmox, $guest);
        $basePath = '/nodes/' . rawurlencode($node) . '/' . $guest->vtype . '/' . (int) $guest->vmid;
        if ($legacyRestore) {
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'running') {
                $taskId = $proxmox->post($basePath . '/status/start', array());
                pvewhmcs_suspension_wait_task($proxmox, $node, $taskId);
            }
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'running') {
                throw new RuntimeException('Legacy guest is not running after unsuspension.');
            }
            return 'success';
        }
        $config = $proxmox->get($basePath . '/config');
        if (!is_array($config)) {
            throw new RuntimeException('Unable to read guest configuration.');
        }
        foreach (array_keys($config) as $key) {
            if ($guest->vtype === 'qemu' && preg_match('/^hostpci[0-9]+$/', (string) $key)) {
                throw new RuntimeException('PCI passthrough guest cannot be safely network-isolated.');
            }
        }
        $guestIdentity = pvewhmcs_suspension_guest_identity($config, $guest->vtype);
        if ($snapshot && $snapshot->guest_identity !== $guestIdentity) {
            throw new RuntimeException('Guest identity changed since suspension; manual review is required.');
        }

        if (!$restore && !$snapshot) {
            $networks = pvewhmcs_suspension_networks($config);
            $snapshotData = array(
                'service_id' => $serviceId,
                'server_id' => $serverId,
                'server_host' => $serverHost,
                'vmid' => (int) $guest->vmid,
                'vtype' => $guest->vtype,
                'guest_identity' => $guestIdentity,
                'onboot' => !empty($config['onboot']) ? 1 : 0,
                'networks' => json_encode($networks),
                'created' => date('Y-m-d H:i:s'),
            );
            if ($snapshotData['networks'] === false) {
                throw new RuntimeException('Unable to save the original network configuration.');
            }
            Capsule::table('mod_pvewhmcs_suspensions')->insert($snapshotData);
            $snapshot = (object) $snapshotData;
        }

        $originalNetworks = json_decode($snapshot->networks, true);
        if (!is_array($originalNetworks) || array_keys($originalNetworks) !== array_keys(pvewhmcs_suspension_networks($config))) {
            throw new RuntimeException('Network adapters changed since the suspension snapshot; manual review is required.');
        }
        foreach ($originalNetworks as $key => $value) {
            if (!preg_match('/^net[0-9]+$/', (string) $key) || !is_string($value)) {
                throw new RuntimeException('Saved network configuration is invalid.');
            }
            if ($config[$key] !== $value && $config[$key] !== pvewhmcs_suspension_isolate_network($value)) {
                throw new RuntimeException('Network adapter settings changed since suspension; manual review is required.');
            }
        }
        if ((int) $snapshot->onboot !== 0 && (int) $snapshot->onboot !== 1) {
            throw new RuntimeException('Saved automatic-start value is invalid.');
        }

        if (!$restore) {
            $changes = array('onboot' => 0);
            $expected = array('onboot' => 0);
            foreach ($originalNetworks as $key => $value) {
                $isolated = pvewhmcs_suspension_isolate_network($value);
                $changes[$key] = $isolated;
                $expected[$key] = $isolated;
            }
            pvewhmcs_suspension_update_config($proxmox, $basePath, $changes, $expected);
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'stopped') {
                $taskId = $proxmox->post($basePath . '/status/stop', array());
                pvewhmcs_suspension_wait_task($proxmox, $node, $taskId);
            }
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'stopped') {
                throw new RuntimeException('Guest is not stopped after suspension.');
            }
            return 'success';
        }

        try {
            pvewhmcs_suspension_update_config($proxmox, $basePath, $originalNetworks, $originalNetworks);
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'running') {
                $taskId = $proxmox->post($basePath . '/status/start', array());
                pvewhmcs_suspension_wait_task($proxmox, $node, $taskId);
            }
            if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'running') {
                throw new RuntimeException('Guest is not running after unsuspension.');
            }
            pvewhmcs_suspension_update_config($proxmox, $basePath,
                array('onboot' => (int) $snapshot->onboot), array('onboot' => (int) $snapshot->onboot));
            $deleted = Capsule::table('mod_pvewhmcs_suspensions')->where('service_id', '=', $serviceId)->delete();
            if ($deleted !== 1) {
                throw new RuntimeException('Suspension snapshot could not be cleared.');
            }
            return 'success';
        } catch (Throwable $restoreError) {
            $isolated = array('onboot' => 0);
            foreach ($originalNetworks as $key => $value) {
                $isolated[$key] = pvewhmcs_suspension_isolate_network($value);
            }
            try {
                pvewhmcs_suspension_update_config($proxmox, $basePath, $isolated, $isolated);
                if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'stopped') {
                    $taskId = $proxmox->post($basePath . '/status/stop', array());
                    pvewhmcs_suspension_wait_task($proxmox, $node, $taskId);
                }
                if (pvewhmcs_suspension_status($proxmox, $basePath) !== 'stopped') {
                    throw new RuntimeException('Guest did not stop during rollback.');
                }
            } catch (Throwable $rollbackError) {
                throw new RuntimeException('Unsuspension failed and automatic rollback was incomplete; manual intervention is required.');
            }
            throw new RuntimeException('Unsuspension failed; guest was returned to the suspended state.');
        }
    } catch (RuntimeException $error) {
        return 'Error performing action. ' . $error->getMessage();
    } catch (Throwable $error) {
        return 'Error performing action. Suspension provider or database request failed; check the saved snapshot before retrying.';
    } finally {
        if ($locked) {
            try {
                Capsule::select('SELECT RELEASE_LOCK(?) AS released', array($lockName));
            } catch (Throwable $error) {
                // The connection closing will also release its advisory lock.
            }
        }
    }
}
