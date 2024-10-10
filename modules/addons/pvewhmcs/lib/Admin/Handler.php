<?php

namespace WHMCS\Module\Addon\PVEWhmcs\Admin;

use WHMCS\Product\Product;
use WHMCS\Service\Service;
use WHMCS\User\Client;
use WHMCS\localAPI;
use Illuminate\Database\Capsule\Manager as Capsule;


/**
 * Copyright DeVeLab & DrawnCodes
 * Licensed under the Apache License, Version 2.0 (the "License");
 */
class Handler
{
    /**
     * @param $vars
     * @param $data
     * @return string
     */
    public function index($vars, $data): string
    {
        return "No action defined";
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addLxcPlan($vars, $data): void
    {
        try {
            Capsule::connection()->transaction(
                function ($connectionManager) use ($data) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    $connectionManager->table('mod_pvewhmcs_plans')->insert(
                        [
                            'title'      => $data['title'],
                            'vmtype'     => 'lxc',
                            'cores'      => $data['cores'],
                            'cpulimit'   => $data['cpulimit'],
                            'cpuunits'   => $data['cpuunits'],
                            'memory'     => $data['memory'],
                            'swap'       => $data['swap'],
                            'disk'       => $data['disk'],
                            'storage'    => $data['storage'],
                            'os_storage' => $data['os_storage'],
                            'diskio'     => $data['diskio'],
                            'bridge'     => $data['bridge'],
                            'vmbr'       => $data['vmbr'],
                            'netmodel'   => $data['netmodel'],
                            'vlanid'     => $data['vlanid'],
                            'netrate'    => $data['netrate'],
                            'bw'         => $data['bw'],
                            'ipv6'       => $data['ipv6'],
                            'onboot'     => $data['onboot'],
                        ]
                    );
                }
            );
            $_SESSION['pvewhmcs']['notification'] = [
                'title' => 'Success!',
                'message' => 'Saved the LXC Plan successfuly.',
                'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
            ];
            header("Location: ".$vars['modulelink']."&tab=vmplans&action=planlist");
        } catch (\Exception $e) {
            echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
        }
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addKvmPlan($vars, $data): void
    {
        try {
            Capsule::connection()->transaction(
                function ($connectionManager) use ($data) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    $connectionManager->table('mod_pvewhmcs_plans')->insert(
                        [
                            'title'      => $data['title'],
                            'vmtype'     => 'kvm',
                            'ostype'     => $data['ostype'],
                            'cpus'       => $data['cpus'],
                            'cpuemu'     => $data['cpuemu'],
                            'cores'      => $data['cores'],
                            'cpulimit'   => $data['cpulimit'],
                            'cpuunits'   => $data['cpuunits'],
                            'memory'     => $data['memory'],
                            'disk'       => $data['disk'],
                            'diskformat' => $data['diskformat'],
                            'diskcache'  => $data['diskcache'],
                            'disktype'   => $data['disktype'],
                            'storage'    => $data['storage'],
                            'diskio'     => $data['diskio'],
                            'netmode'    => $data['netmode'],
                            'bridge'     => $data['bridge'],
                            'vmbr'       => $data['vmbr'],
                            'netmodel'   => $data['netmodel'],
                            'vlanid'     => $data['vlanid'],
                            'netrate'    => $data['netrate'],
                            'bw'         => $data['bw'],
                            'ipv6'       => $data['ipv6'],
                            'kvm'        => $data['kvm'],
                            'onboot'     => $data['onboot'],
                        ]
                    );
                }
            );
            $_SESSION['pvewhmcs']['notification'] = [
                'title' => 'Success!',
                'message' => 'Saved the KVM Plan successfuly.',
                'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
            ];
            header("Location: ".$vars['modulelink']."&tab=vmplans&action=planlist");
        } catch (\Exception $e) {
            echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
        }
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function editplan($vars, $data): void
    {
        if (isset($data['id'])) {
            $id = (int) $data['id'];
        } else {
            $_SESSION['pvewhmcs']['infomsg']['title'] = 'LXC Plan failed.';
            $_SESSION['pvewhmcs']['infomsg']['message'] = 'There is no id selected.';
            header("Location: ".$vars['modulelink']."&tab=vmplans&action=planlist");
        }
        if ($data['vmtype'] == "lxc") {
            $this->updateLXCPlan($data);
        } else {
            $this->updateKVMPlan($data);
        }
        header("Location: ".$vars['modulelink']."&tab=vmplans&action=planlist");
    }

    /**
     * @param $data
     * @return void
     */
    private function updateLXCPlan($data): void
    {
        Capsule::table('mod_pvewhmcs_plans')
            ->where('id', (int) $data['id'])
            ->update(
                [
                    'title'      => $data['title'],
                    'vmtype'     => 'lxc',
                    'cores'      => $data['cores'],
                    'cpulimit'   => $data['cpulimit'],
                    'cpuunits'   => $data['cpuunits'],
                    'memory'     => $data['memory'],
                    'swap'       => $data['swap'],
                    'disk'       => $data['disk'],
                    'storage'    => $data['storage'],
                    'os_storage' => $data['os_storage'],
                    'diskio'     => $data['diskio'],
                    'bridge'     => $data['bridge'],
                    'vmbr'       => $data['vmbr'],
                    'netmodel'   => $data['netmodel'],
                    'vlanid'     => $data['vlanid'],
                    'netrate'    => $data['netrate'],
                    'bw'         => $data['bw'],
                    'ipv6'       => $data['ipv6'],
                    'onboot'     => $data['onboot'],
                ]
            );
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'Success!',
            'message' => 'Updated the LXC Plan successfully.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
    }

    private function updateKVMPlan($data): void
    {
        Capsule::table('mod_pvewhmcs_plans')
            ->where('id', $data['id'])
            ->update(
                [
                    'title'      => $data['title'],
                    'vmtype'     => 'kvm',
                    'ostype'     => $data['ostype'],
                    'cpus'       => $data['cpus'],
                    'cpuemu'     => $data['cpuemu'],
                    'cores'      => $data['cores'],
                    'cpulimit'   => $data['cpulimit'],
                    'cpuunits'   => $data['cpuunits'],
                    'memory'     => $data['memory'],
                    'disk'       => $data['disk'],
                    'diskformat' => $data['diskformat'],
                    'diskcache'  => $data['diskcache'],
                    'disktype'   => $data['disktype'],
                    'storage'    => $data['storage'],
                    'os_storage' => $data['os_storage'],
                    'diskio'     => $data['diskio'],
                    'netmode'    => $data['netmode'],
                    'bridge'     => $data['bridge'],
                    'vmbr'       => $data['vmbr'],
                    'netmodel'   => $data['netmodel'],
                    'vlanid'     => $data['vlanid'],
                    'netrate'    => $data['netrate'],
                    'bw'         => $data['bw'],
                    'ipv6'       => $data['ipv6'],
                    'kvm'        => $data['kvm'],
                    'onboot'     => $data['onboot'],
                ]
            );
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'Success!',
            'message' => 'Updated the KVM Plan successfuly.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addIpToPool($vars, $data): void
    {
        // TODO: move thos class name
        require_once(ROOTDIR.'/modules/addons/pvewhmcs/Ipv4/Subnet.php');
        // Load Gateway
        $ipPool = Capsule::table('mod_pvewhmcs_ip_pools')->where('id', '=', $data['pool_id'])->first();

        if ((strpos($data['ipblock'], '/')) != false) {
            $subnet = \Ipv4_Subnet::fromString($data['ipblock']);
            $ips = $subnet->getIterator();
            foreach ($ips as $ip) {
                if (!in_array($ip, [$ipPool->gateway])) {
                    Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
                        [
                            'pool_id'   => $data['pool_id'],
                            'ipaddress' => $ip,
                            'mask'      => $subnet->getNetmask(),
                        ]
                    );
                }
            }
        } else {
            if (!in_array($data['ipblock'], [$ipPool->gateway])) {
                Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
                    [
                        'pool_id'   => $data['pool_id'],
                        'ipaddress' => $data['ipblock'],
                        'mask'      => '255.255.255.255',
                    ]
                );
            }
        }
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'Success!',
            'message' => 'You can remove IP Addresses from the pool.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
        header("Location: ".$vars['modulelink']."&tab=ippools&action=listIps&id=".$data['pool_id']);

    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function newIpPool($vars, $data): void
    {
        try {
            Capsule::connection()->transaction(
                function ($connectionManager) use ($data) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    $connectionManager->table('mod_pvewhmcs_ip_pools')->insert(
                        [
                            'title'   => $data['title'],
                            'gateway' => $data['gateway'],
                        ]
                    );
                }
            );
            $_SESSION['pvewhmcs']['notification'] = [
                'title' => 'Success!',
                'message' => 'New IP Pool saved successfully.',
                'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
            ];
            header("Location: ".$vars['modulelink']."&tab=ippools&action=listIpPool");
        } catch (\Exception $e) {
            echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
        }
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function vncConfig($vars, $data): void
    {
        try {
            Capsule::connection()->transaction(
                function ($connectionManager) use ($data) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    $connectionManager->table('mod_pvewhmcs')->update(
                        [
                            'vnc_secret' => $data['vnc_secret'],
                            'debug_mode' => $data['debug_mode'],
                        ]
                    );
                }
            );
            $_SESSION['pvewhmcs']['notification'] = [
                'title' => 'Success!',
                'message' => 'New options have been successfully saved.',
                'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
            ];
            header("Location: ".$vars['modulelink']."&tab=settings&action=vncConfig");
        } catch (\Exception $e) {
            echo "Uh oh! That didn't work, but I was able to rollback. {$e->getMessage()}";
        }
    }
}