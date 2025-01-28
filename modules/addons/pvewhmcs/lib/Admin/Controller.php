<?php

namespace WHMCS\Module\Addon\PVEWhmcs\Admin;

use WHMCS\Product\Product;
use WHMCS\Service\Service;
use WHMCS\User\Client;
use WHMCS\localAPI;
use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\PVEWhmcs\pveApi;

/**
 * Copyright DeVeLab & DrawnCodes
 * Licensed under the Apache License, Version 2.0 (the "License");
 */
class Controller {


    public function documentation($vars, $data): void
    {
        $modulelink = $vars['modulelink'];
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('documentation.tpl');
    }

    public function health($vars, $data): void
    {
        $modulelink = $vars['modulelink'];
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('health.tpl');
    }
    /**
     * Index Page
     * @param $vars
     * @param $data
     */
    public function index($vars, $data)
    {
        $getServers = Capsule::table('tblservers')->where("type", "pvewhmcs")->get();

        // Get Count on each server active or in use
        if($getServers != []) {
            foreach($getServers as $key => $value) {
                $getServers[$key]->activeClients = Capsule::table("tblhosting")->where("server", $value->id)->where("domainstatus", "active")->count();
            }
        }

        $modulelink = $vars['modulelink'];
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign("servers", $getServers);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('index.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function planlist($vars, $data): void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $getPlanList = Capsule::table('mod_pvewhmcs_plans')->get();
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign("vm_plans", $getPlanList);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('plans/list.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addKvmPlan($vars, $data): void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule

        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);

        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('plans/add_kvm.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addLxcPlan($vars, $data): void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule

        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);

        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('plans/add_lxc.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return string|void
     */
    public function editplan($vars, $data)
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        if(isset($_GET['id'])) {
            $plan = Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $_GET['id'])->first();
            if (empty($plan)) {
                return 'Plan Not found';
            }
        }
        if(isset($_GET['vmtype']) && $_GET['vmtype'] == "lxc") {
            $template = "plans/edit_lxc.tpl";
        } else {
            $template = "plans/edit_kvm.tpl";
        }
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign("plan", $plan);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display($template);
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function removePlan($vars, $data): void
    {
        Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $_REQUEST['id'])->delete();
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'Plan Deleted!',
            'message' => 'Selected Item deleted successfuly.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
        header("Location: ".$vars['modulelink']."&tab=vmplans&action=planlist");
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function listIpPool($vars, $data): void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $pool = Capsule::table('mod_pvewhmcs_ip_pools')->get();
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign("pool", $pool);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('ippools/list.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function listIps($vars, $data): void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule

        // Fetch IP addresses from the database
        $ip_addresses = Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $_GET['id'])->get();

        // Prepare the in_use status for each IP address
        foreach ($ip_addresses as $ip) {
            $ip->in_use = (count(Capsule::table('mod_pvewhmcs_vms')->where('ipaddress', '=', $ip->ipaddress)->get()) > 0);
        }
        // Assign to Smarty
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign('ip_addresses', $ip_addresses);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('ips/list.tpl');
    }

    /**
     * @param $vars
     * @param $data
     * @return void
     */
    public function addIpToPool($vars, $data) : void
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        // Fetch IP pools from the database
        $ip_pools = Capsule::table('mod_pvewhmcs_ip_pools')->get();
        $gateways = []; // Initialize the gateways array

        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign('ip_pools', $ip_pools);
        $smarty->assign('gateways', $gateways);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('ips/add.tpl');
    }

    public function newIpPool($vars, $data)
    {
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('ippools/add.tpl');
    }

    public function removeIpPool($vars, $data)
    {
        Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $_GET['id'])->delete();
        Capsule::table('mod_pvewhmcs_ip_pools')->where('id', '=', $_GET['id'])->delete();
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'IP Pool Deleted!',
            'message' => 'Deleted the IP Pool successfully.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
        header("Location: ".$vars['modulelink']."&tab=ippools&action=listIpPool");
    }

    public function removeIp($vars, $data)
    {
        Capsule::table('mod_pvewhmcs_ip_addresses')->where('id', '=', $_GET['id'])->delete();
        $_SESSION['pvewhmcs']['notification'] = [
            'title' => 'IP Address deleted!',
            'message' => 'Deleted selected item successfuly.',
            'type' => 'success' // can be 'success', 'info', 'warning', or 'error'
        ];
        header("Location: ".$vars['modulelink']."&tab=ippools&action=listIps&id=".$_GET['pool_id']);
    }

    public function vncConfig($vars, $data)
    {
        $config = Capsule::table('mod_pvewhmcs')->where('id', '=', '1')->first();

        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $smarty = $vars['smarty'];
        $smarty->assign("modulelink", $modulelink);
        $smarty->assign("config", $config);
        $smarty->caching = false;
        $smarty->compile_dir = $GLOBALS['templates_compiledir'];
        $smarty->display('settings/vnc.tpl');
    }
}