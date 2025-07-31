# SQL Statements for Updates (nav to DB first)

## v1.2.8 to v1.2.9

```
ALTER TABLE mod_pvewhmcs ADD COLUMN `start_vmid` int(10) DEFAULT 100;
ALTER TABLE mod_pvewhmcs_vms ADD COLUMN `vmid` int(10) DEFAULT NULL;
UPDATE mod_pvewhmcs_vms SET vmid = id WHERE vmid IS NULL;
```

## v1.2.7 to v1.2.8

```
ALTER TABLE mod_pvewhmcs_plans MODIFY COLUMN `vmbr` tinyint(1) unsigned DEFAULT NULL;
```

## v1.2.6 to v1.2.7

```
ALTER TABLE mod_pvewhmcs_plans ADD COLUMN `balloon` varchar(10) DEFAULT '0';
```

## v1.2.3 to v1.2.4

```
ALTER TABLE mod_pvewhmcs_vms ADD COLUMN `v6prefix` varchar(128) DEFAULT NULL;
ALTER TABLE mod_pvewhmcs_plans ADD COLUMN `ipv6` varchar(10) DEFAULT 'auto';
```

## v1.2.1 to v1.2.2

```
ALTER TABLE mod_pvewhmcs ADD COLUMN `debug_mode` tinyint(1) unsigned DEFAULT 0;
ALTER TABLE mod_pvewhmcs_plans ADD COLUMN `vlanid` varchar(10) DEFAULT NULL;
```
