<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/** Resolve a service and its recovery snapshot before showing or saving onboot. */
function pvewhmcs_onboot_admin_context(array $params)
{
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    $serverId = isset($params['serverid']) ? (int) $params['serverid'] : 0;
    $serverHost = isset($params['serverip']) ? (string) $params['serverip'] : '';
    if ($serviceId <= 0 || $serverId <= 0 || $serverHost === '') {
        throw new RuntimeException('Service or server identity is missing.');
    }
    if (!Capsule::schema()->hasTable('mod_pvewhmcs_suspensions')) {
        throw new RuntimeException('Suspension database migration is not installed.');
    }
    $guest = Capsule::table('mod_pvewhmcs_vms')->where('id', '=', $serviceId)->first();
    $hosting = Capsule::table('tblhosting')->where('id', '=', $serviceId)->first();
    if (!$guest || !$hosting || !in_array($guest->vtype, array('qemu', 'lxc'), true)
        || (int) $guest->vmid <= 0 || (int) $hosting->server !== $serverId
        || (int) $hosting->userid !== (int) $guest->user_id) {
        throw new RuntimeException('Service ownership or guest linkage is invalid.');
    }
    $duplicate = Capsule::table('mod_pvewhmcs_vms')->where('vmid', '=', $guest->vmid)
        ->where('vtype', '=', $guest->vtype)->where('id', '!=', $serviceId)->first();
    if ($duplicate) {
        throw new RuntimeException('Guest ID is linked to another service.');
    }
    $snapshot = Capsule::table('mod_pvewhmcs_suspensions')->where('service_id', '=', $serviceId)->first();
    if ($snapshot && ((int) $snapshot->server_id !== $serverId || $snapshot->server_host !== $serverHost
        || (int) $snapshot->vmid !== (int) $guest->vmid || $snapshot->vtype !== $guest->vtype)) {
        throw new RuntimeException('Saved suspension identity does not match this guest.');
    }
    return array('guest' => $guest, 'hosting' => $hosting, 'snapshot' => $snapshot);
}

/** Read the current provider setting after validating the cluster guest. */
function pvewhmcs_onboot_admin_provider(array $params, $guest, $snapshot)
{
    $serverPort = !empty($params['serverport']) ? $params['serverport'] : 8006;
    $proxmox = new PVE2_API($params['serverip'], $params['serverusername'], 'pam',
        $params['serverpassword'], $serverPort);
    if (!$proxmox->login()) {
        throw new RuntimeException('Proxmox is unavailable.');
    }
    $node = pvewhmcs_suspension_find_node($proxmox, $guest);
    $basePath = '/nodes/' . rawurlencode($node) . '/' . $guest->vtype . '/' . (int) $guest->vmid;
    $config = $proxmox->get($basePath . '/config');
    if (!is_array($config)) {
        throw new RuntimeException('Unable to read the guest configuration.');
    }
    if ($snapshot && pvewhmcs_suspension_guest_identity($config, $guest->vtype) !== $snapshot->guest_identity) {
        throw new RuntimeException('Guest identity changed since suspension.');
    }
    $actual = 0;
    if (array_key_exists('onboot', $config)) {
        $raw = $config['onboot'];
        if ($raw === true || $raw === 1 || $raw === '1') {
            $actual = 1;
        } elseif ($raw !== false && $raw !== 0 && $raw !== '0') {
            throw new RuntimeException('Proxmox returned an invalid automatic-start value.');
        }
    }
    return array('api' => $proxmox, 'path' => $basePath, 'actual' => $actual);
}

/** Render a per-service checkbox and a separate read-only live value. */
function pvewhmcs_onboot_admin_fields(array $params)
{
    try {
        $context = pvewhmcs_onboot_admin_context($params);
        $snapshot = $context['snapshot'];
        $status = isset($context['hosting']->domainstatus) ? $context['hosting']->domainstatus : '';
        $actual = 'Unavailable';
        $providerAvailable = false;
        try {
            $provider = pvewhmcs_onboot_admin_provider($params, $context['guest'], $snapshot);
            $actual = $provider['actual'] ? 'Enabled' : 'Disabled';
            $providerAvailable = true;
        } catch (Throwable $error) {
            // A saved restore preference remains editable while the guest is suspended.
        }

        if ($snapshot) {
            $desired = (int) $snapshot->onboot;
            $editable = $desired === 0 || $desired === 1;
            $hint = 'Saved for the next Unsuspend. The guest remains at onboot=0 while suspended.';
        } else {
            $desired = $providerAvailable ? $provider['actual'] : 0;
            $editable = $status === 'Active' && $providerAvailable;
            if ($status === 'Suspended') {
                $hint = 'Legacy suspension has no saved settings; this field is read-only until unsuspended.';
            } elseif (!$providerAvailable) {
                $hint = 'Proxmox is unavailable or the guest cannot be verified; this field is read-only.';
            } else {
                $hint = 'Saving an active service updates Proxmox immediately.';
            }
        }
        if (!$editable) {
            return array('Start on boot' => htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'),
                'Current Proxmox onboot' => $actual);
        }
        $checked = $desired === 1 ? ' checked' : '';
        $field = '<input type="hidden" name="modulefields[0]" value="0">'
            . '<label><input type="checkbox" name="modulefields[0]" value="1"' . $checked
            . '> Enable start on node boot</label><br><small>'
            . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</small>';
        return array('Start on boot' => $field, 'Current Proxmox onboot' => $actual);
    } catch (RuntimeException $error) {
        return array('Start on boot' => 'Unavailable: '
            . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
    } catch (Throwable $error) {
        return array('Start on boot' => 'Unavailable: service or guest linkage could not be verified.');
    }
}

/** Save the desired value without weakening a currently suspended guest. */
function pvewhmcs_onboot_admin_save(array $params, $submitted)
{
    if (!is_scalar($submitted) || !in_array((string) $submitted, array('0', '1'), true)) {
        return 'Invalid Start on boot selection.';
    }
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    if ($serviceId <= 0) {
        return 'Service identity is missing.';
    }
    $lockName = 'pvewhmcs:suspend:' . $serviceId;
    $locked = false;
    try {
        $lock = Capsule::select('SELECT GET_LOCK(?, 0) AS acquired', array($lockName));
        if (!isset($lock[0]->acquired) || (int) $lock[0]->acquired !== 1) {
            throw new RuntimeException('Another guest operation is in progress.');
        }
        $locked = true;
        $context = pvewhmcs_onboot_admin_context($params);
        $desired = (int) $submitted;
        if ($context['snapshot']) {
            if ((int) $context['snapshot']->onboot !== $desired) {
                $updated = Capsule::table('mod_pvewhmcs_suspensions')->where('service_id', '=', $serviceId)
                    ->update(array('onboot' => $desired));
                if ($updated !== 1) {
                    throw new RuntimeException('Saved suspension preference could not be updated.');
                }
            }
            return 'success';
        }
        if (!isset($context['hosting']->domainstatus) || $context['hosting']->domainstatus !== 'Active') {
            throw new RuntimeException('Only active services can change the live setting.');
        }
        $provider = pvewhmcs_onboot_admin_provider($params, $context['guest'], null);
        if ($provider['actual'] !== $desired) {
            pvewhmcs_suspension_update_config($provider['api'], $provider['path'],
                array('onboot' => $desired), array('onboot' => $desired));
        }
        return 'success';
    } catch (RuntimeException $error) {
        return $error->getMessage();
    } catch (Throwable $error) {
        return 'Unable to save Start on boot; verify the service and Proxmox state.';
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
