CREATE TABLE IF NOT EXISTS `mod_pvewhmcs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config` varchar(255),
  `vnc_secret` varchar(255),
  `start_vmid` int(10) unsigned DEFAULT 100,
  `debug_mode` tinyint(1) unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
);
INSERT IGNORE INTO `mod_pvewhmcs` (`id`, `config`, `vnc_secret`, `debug_mode`) VALUES	(1, NULL, NULL, 0);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_ip_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pool_id` int(11) NOT NULL DEFAULT 0,
  `ipaddress` varchar(255) NOT NULL DEFAULT '0',
  `mask` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ipaddress` (`ipaddress`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_ip_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `gateway` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_iso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `storage` varchar(20) NOT NULL DEFAULT 'local',
  `iso_name` varchar(255) NOT NULL,
  `nodes` int(11) DEFAULT '0',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auth_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `service` int(11) NOT NULL DEFAULT '0',
  `timestamp` datetime NOT NULL,
  `node_id` int(11) NOT NULL DEFAULT '0',
  `target_id` int(11) NOT NULL DEFAULT '0',
  `level` varchar(10) NOT NULL,
  `type` text NOT NULL,
  `action` text NOT NULL,
  `request` text NOT NULL,
  `response` text NOT NULL,
  `raw` text NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pve_id` int(11) NOT NULL,
  `pve_name` varchar(255) NOT NULL,
  `whmcs_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ip` varchar(100) NOT NULL,
  `pve_ver` varchar(20) NOT NULL DEFAULT '9.0.0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `max_guests` int(5) NOT NULL DEFAULT '20',
  `health` text DEFAULT NULL,
  `load_avg` varchar(100) DEFAULT NULL,
  `load_crs` varchar(255) DEFAULT NULL,
  `notifiers` text DEFAULT NULL,
  `resources` text DEFAULT NULL,
  `supports` varchar(255) NOT NULL DEFAULT 'vm,ct',
  `templates` varchar(1000) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `vmtype` varchar(8) NOT NULL,
  `ostype` varchar(8) DEFAULT NULL,
  `cpus` smallint(4) unsigned DEFAULT NULL,
  `cpuemu` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
  `cores` smallint(4) unsigned DEFAULT NULL,
  `cpulimit` smallint(5) unsigned DEFAULT NULL,
  `cpuunits` smallint(5) unsigned DEFAULT NULL,
  `memory` int(10) unsigned NOT NULL,
  `swap` int(10) unsigned DEFAULT NULL,
  `disk` int(10) unsigned DEFAULT NULL,
  `diskformat` varchar(10) DEFAULT NULL,
  `diskcache` varchar(20) DEFAULT NULL,
  `disktype` varchar(20) DEFAULT NULL,
  `storage` varchar(20) DEFAULT 'local',
  `diskio` varchar(20) DEFAULT '0',
  `netmode` varchar(10) DEFAULT NULL,
  `bridge` varchar(20) NOT NULL DEFAULT 'vmbr',
  `vmbr` tinyint(3) unsigned DEFAULT NULL,
  `netmodel` varchar(10) DEFAULT NULL,
  `netrate` int(10) DEFAULT '0',
  `firewall` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `bw` int(10) unsigned DEFAULT 0,
  `kvm` tinyint(1) unsigned DEFAULT 0,
  `onboot` tinyint(1) unsigned DEFAULT 0,
  `vlanid` int(10) DEFAULT NULL,
  `ipv6` varchar(10) DEFAULT 'auto',
  `balloon` int(10) DEFAULT '0',
  `unpriv` tinyint(1) unsigned DEFAULT 0,
  `ssh-keys` varchar(100) DEFAULT '',
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_ssh_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `ssh_key` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tpl_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `guest` varchar(8) NOT NULL DEFAULT 'vm',
  `ostype` varchar(8) DEFAULT NULL,
  `storage` varchar(20) DEFAULT 'local',
  `template` varchar(255) DEFAULT NULL,
  `nodes` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_vms` (
  `id` int(10) unsigned NOT NULL,
  `vmid` int(10) unsigned DEFAULT NULL,
  `node_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vtype` varchar(255) NOT NULL,
  `ipaddress` varchar(255) NOT NULL,
  `subnetmask` varchar(255) NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `created` datetime DEFAULT NULL,
  `v6prefix` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
);