# SQL Statements for Updates (nav to DB first)

## Durable guest suspension (existing installations)

Before deploying the matching server-module code, back up the WHMCS database and
the two module directories. Apply this idempotent migration to the WHMCS database:

```sql
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_suspensions` (
  `service_id` int(10) unsigned NOT NULL,
  `server_id` int(10) unsigned NOT NULL,
  `server_host` varchar(255) NOT NULL,
  `vmid` int(10) unsigned NOT NULL,
  `vtype` varchar(8) NOT NULL,
  `guest_identity` char(64) NOT NULL,
  `onboot` tinyint(1) unsigned NOT NULL,
  `networks` longtext NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`service_id`)
);
```

The module refuses suspend/unsuspend commands if the table is absent. No version
bump is assumed, so the addon upgrade callback does not create this table.
Validate first with a dedicated QEMU/LXC test guest, including retry and restore.

Rollback: stop new suspend/unsuspend commands, restore the previous module files,
and manually restore any guests represented by rows in this table before removing
it. A row contains a guest fingerprint, its original `onboot` value and full `netN` strings.
Do not drop the table while rows exist; it is the only automated restoration
record. Once all rows are reconciled and verified, the table can be removed with
`DROP TABLE mod_pvewhmcs_suspensions;` if desired.

## v1.2.14 & onwards...

> [!NOTE]  
> As we transition to auto-updating, you can interpret manual queries in the `pvewhmcs_upgrade` function.
> 
> It is located in the /modules/addons/pvewhmcs/pvewhmcs.php file near-ish the top. Thank you.

## v1.2.10 to v1.2.12

```
ALTER TABLE mod_pvewhmcs_plans ADD COLUMN `unpriv` int(1) unsigned DEFAULT 0;
```

## v1.2.8 to v1.2.10

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
