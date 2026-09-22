<?php

namespace Illuminate\Database\Capsule {
    class Manager {
        public static $rows = array();
        public static $hasTable = true;

        public static function schema() { return new TestSchema(); }
        public static function table($name) { return new TestQuery($name); }
        public static function select($sql, $bindings) {
            return array((object) array(strpos($sql, 'GET_LOCK') !== false ? 'acquired' : 'released' => 1));
        }
    }

    class TestSchema {
        public function hasTable($name) { return Manager::$hasTable; }
    }

    class TestQuery {
        private $table;
        private $filters = array();
        public function __construct($table) { $this->table = $table; }
        public function where($key, $operator, $value = null) {
            $this->filters[] = array($key, $operator, $value);
            return $this;
        }
        private function matches($row) {
            foreach ($this->filters as $filter) {
                list($key, $operator, $value) = $filter;
                if ($operator === '=' && (string) $row[$key] !== (string) $value) return false;
                if ($operator === '!=' && (string) $row[$key] === (string) $value) return false;
            }
            return true;
        }
        public function first() {
            foreach (Manager::$rows[$this->table] as $row) {
                if ($this->matches($row)) return (object) $row;
            }
            return null;
        }
        public function insert($row) {
            Manager::$rows[$this->table][] = $row;
            return true;
        }
        public function delete() {
            $before = count(Manager::$rows[$this->table]);
            Manager::$rows[$this->table] = array_values(array_filter(Manager::$rows[$this->table], function ($row) {
                return !$this->matches($row);
            }));
            return $before - count(Manager::$rows[$this->table]);
        }
        public function update($changes) {
            $updated = 0;
            foreach (Manager::$rows[$this->table] as &$row) {
                if ($this->matches($row)) {
                    $row = array_merge($row, $changes);
                    $updated++;
                }
            }
            unset($row);
            return $updated;
        }
    }
}

namespace {
    class PVE2_API {
        public static $config = array();
        public static $power = 'running';
        public static $type = 'qemu';
        public static $ha = array();
        public static $failPut = false;
        public static $failOnbootRestore = false;
        public static $failTask = false;
        public static $loginOk = true;
        public function __construct($host, $user, $realm, $password, $port) {}
        public function login() { return self::$loginOk; }
        public function get($path) {
            if ($path === '/cluster/resources') {
                return array(array('type' => self::$type, 'vmid' => 9001, 'node' => 'pve-test'));
            }
            if ($path === '/cluster/ha/resources') return self::$ha;
            if (strpos($path, '/tasks/') !== false) {
                return array('status' => 'stopped', 'exitstatus' => self::$failTask ? 'ERROR' : 'OK');
            }
            if (substr($path, -15) === '/status/current') return array('status' => self::$power);
            if (substr($path, -7) === '/config') return self::$config;
            throw new \RuntimeException('Unexpected provider read: ' . $path);
        }
        public function put($path, $changes) {
            if (self::$failPut) return false;
            if (self::$failOnbootRestore && isset($changes['onboot']) && (int) $changes['onboot'] === 1) return false;
            self::$config = array_merge(self::$config, $changes);
            return true;
        }
        public function post($path, $params) {
            if (self::$failTask) return 'UPID:test';
            self::$power = substr($path, -5) === '/stop' ? 'stopped' : 'running';
            return 'UPID:test';
        }
    }

    require_once __DIR__ . '/../modules/servers/pvewhmcs/suspension.php';
    require_once __DIR__ . '/../modules/servers/pvewhmcs/onboot.php';

    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }
    function reset_case($type) {
        \Illuminate\Database\Capsule\Manager::$rows = array(
            'mod_pvewhmcs_vms' => array(array('id' => 10001, 'vmid' => 9001, 'vtype' => $type, 'user_id' => 20001)),
            'tblhosting' => array(array('id' => 10001, 'server' => 7, 'userid' => 20001, 'domainstatus' => 'Active')),
            'mod_pvewhmcs_suspensions' => array(),
        );
        \Illuminate\Database\Capsule\Manager::$hasTable = true;
        PVE2_API::$config = array('onboot' => 1, 'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0', 'net1' => 'virtio=AA:BB:CC:DD:EE:00,bridge=vmbr1,link_down=0');
        PVE2_API::$config[$type === 'lxc' ? 'rootfs' : 'scsi0'] = 'local-lvm:vm-9001-disk-0,size=16G';
        PVE2_API::$power = 'running';
        PVE2_API::$type = $type;
        PVE2_API::$ha = array();
        PVE2_API::$failPut = false;
        PVE2_API::$failOnbootRestore = false;
        PVE2_API::$failTask = false;
        PVE2_API::$loginOk = true;
    }
    $params = array('serviceid' => 10001, 'serverid' => 7, 'serverip' => '192.0.2.10',
        'serverusername' => 'test', 'serverpassword' => 'not-a-secret', 'serverport' => 8006);

    foreach (array('qemu', 'lxc') as $type) {
        reset_case($type);
        $original = PVE2_API::$config;
        check(pvewhmcs_suspension_command($params, false) === 'success', $type . ' suspension failed');
        check(PVE2_API::$power === 'stopped' && PVE2_API::$config['onboot'] === 0, 'Guest can restart');
        check(strpos(PVE2_API::$config['net0'], 'link_down=1') !== false, 'First network not isolated');
        check(substr(PVE2_API::$config['net1'], -11) === 'link_down=1', 'Second network not isolated');
        check(pvewhmcs_suspension_command($params, false) === 'success', 'Retry suspension failed');
        check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 1, 'Snapshot overwritten');
        check(pvewhmcs_suspension_command($params, true) === 'success', $type . ' restoration failed');
        check(PVE2_API::$power === 'running' && PVE2_API::$config === $original, 'Settings not restored');
        check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 0, 'Snapshot not cleared');
    }

    reset_case('qemu');
    PVE2_API::$ha = array(array('sid' => 'vm:9001'));
    check(strpos(pvewhmcs_suspension_command($params, false), 'HA-managed') !== false, 'HA guard failed');
    check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 0, 'HA wrote snapshot');

    reset_case('qemu');
    PVE2_API::$config['hostpci0'] = '0000:01:00.0';
    check(strpos(pvewhmcs_suspension_command($params, false), 'PCI passthrough') !== false,
        'Passthrough guard failed');

    reset_case('qemu');
    PVE2_API::$failPut = true;
    check(pvewhmcs_suspension_command($params, false) !== 'success', 'Failed config reported success');
    check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 1, 'Failure lost snapshot');

    reset_case('qemu');
    check(pvewhmcs_suspension_command($params, false) === 'success', 'Rollback setup failed');
    PVE2_API::$failOnbootRestore = true;
    check(strpos(pvewhmcs_suspension_command($params, true), 'returned to the suspended state') !== false,
        'Failed restoration did not report rollback');
    check(PVE2_API::$power === 'stopped' && PVE2_API::$config['onboot'] === 0,
        'Failed restoration left guest running');
    check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 1,
        'Rollback lost snapshot');
    PVE2_API::$failOnbootRestore = false;
    check(pvewhmcs_suspension_command($params, true) === 'success', 'Restoration retry failed');

    reset_case('qemu');
    PVE2_API::$config['onboot'] = 0;
    PVE2_API::$config['net1'] = 'virtio=AA:BB:CC:DD:EE:00,bridge=vmbr1,link_down=1';
    $original = PVE2_API::$config;
    check(pvewhmcs_suspension_command($params, false) === 'success', 'Initially disabled settings failed');
    check(pvewhmcs_suspension_command($params, true) === 'success', 'Initially disabled settings did not restore');
    check(PVE2_API::$config === $original, 'Existing isolation or boot choice changed');

    reset_case('qemu');
    PVE2_API::$failTask = true;
    check(pvewhmcs_suspension_command($params, false) !== 'success', 'Failed power task reported success');
    check(count(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions']) === 1, 'Power failure lost snapshot');
    PVE2_API::$failTask = false;
    check(pvewhmcs_suspension_command($params, false) === 'success', 'Power task retry failed');

    PVE2_API::$config['scsi0'] = 'local-lvm:vm-9001-disk-1,size=16G';
    check(strpos(pvewhmcs_suspension_command($params, true), 'Guest identity changed') !== false,
        'Reused guest ID was not rejected');

    reset_case('lxc');
    unset(PVE2_API::$config['net0'], PVE2_API::$config['net1']);
    check(pvewhmcs_suspension_command($params, false) === 'success', 'Guest without networks failed');
    check(pvewhmcs_suspension_command($params, true) === 'success', 'Guest without networks could not restore');

    reset_case('qemu');
    check(strpos(pvewhmcs_suspension_command($params, true), 'No suspension snapshot') !== false,
        'Unsuspend without snapshot started guest');

    reset_case('qemu');
    \Illuminate\Database\Capsule\Manager::$rows['tblhosting'][0]['domainstatus'] = 'Suspended';
    PVE2_API::$power = 'stopped';
    $original = PVE2_API::$config;
    check(pvewhmcs_suspension_command($params, true) === 'success', 'Legacy unsuspend failed');
    check(PVE2_API::$power === 'running' && PVE2_API::$config === $original, 'Legacy settings changed');

    reset_case('qemu');
    \Illuminate\Database\Capsule\Manager::$hasTable = false;
    check(strpos(pvewhmcs_suspension_command($params, false), 'migration') !== false, 'Schema guard failed');

    reset_case('qemu');
    $fields = pvewhmcs_onboot_admin_fields($params);
    check(strpos($fields['Start on boot'], 'checked') !== false, 'Active checkbox does not reflect live value');
    check($fields['Current Proxmox onboot'] === 'Enabled', 'Live value is not displayed');
    check(pvewhmcs_onboot_admin_save($params, '0') === 'success' && PVE2_API::$config['onboot'] === 0,
        'Active checkbox did not update Proxmox');
    check(pvewhmcs_onboot_admin_save($params, '1') === 'success' && PVE2_API::$config['onboot'] === 1,
        'Active checkbox could not re-enable onboot');

    reset_case('qemu');
    check(pvewhmcs_suspension_command($params, false) === 'success', 'Suspended field setup failed');
    \Illuminate\Database\Capsule\Manager::$rows['tblhosting'][0]['domainstatus'] = 'Suspended';
    $fields = pvewhmcs_onboot_admin_fields($params);
    check(strpos($fields['Start on boot'], 'checked') !== false, 'Suspended checkbox lost saved preference');
    check($fields['Current Proxmox onboot'] === 'Disabled', 'Suspended actual state is not displayed');
    check(pvewhmcs_onboot_admin_save($params, '0') === 'success', 'Suspended preference could not be disabled');
    check(PVE2_API::$config['onboot'] === 0, 'Suspended checkbox changed live state');
    check(pvewhmcs_onboot_admin_save($params, '1') === 'success', 'Suspended preference could not be enabled');
    check(\Illuminate\Database\Capsule\Manager::$rows['mod_pvewhmcs_suspensions'][0]['onboot'] === 1,
        'Suspended preference was not saved');
    check(PVE2_API::$config['onboot'] === 0, 'Suspended guest was re-enabled early');
    check(pvewhmcs_suspension_command($params, true) === 'success' && PVE2_API::$config['onboot'] === 1,
        'Unsuspend did not apply the per-service preference');

    reset_case('qemu');
    \Illuminate\Database\Capsule\Manager::$rows['tblhosting'][0]['domainstatus'] = 'Suspended';
    $fields = pvewhmcs_onboot_admin_fields($params);
    check(strpos($fields['Start on boot'], 'modulefields') === false, 'Legacy suspended service is editable');
    check(pvewhmcs_onboot_admin_save($params, '1') !== 'success', 'Legacy suspended service accepted a preference');

    reset_case('qemu');
    PVE2_API::$loginOk = false;
    $fields = pvewhmcs_onboot_admin_fields($params);
    check(strpos($fields['Start on boot'], 'modulefields') === false, 'Unavailable guest is editable');
    check(pvewhmcs_onboot_admin_save($params, '1') !== 'success', 'Unavailable guest reported save success');
    check(pvewhmcs_onboot_admin_save($params, 'invalid') !== 'success', 'Invalid checkbox was accepted');

    print "Suspension tests passed\n";
}
