# Proxmox VE for WHMCS (Module) Provision & Manage

**Salvation, a free and open-source solution for beloved PVE!** If you love it, REVIEW & SHARE IT! ❤️

    TNC Dev are looking for a co-developer to assist with finishing the project overhaul.
    If you have proven and public git-logged experience, or similar, please say g'day.
    
    Please note: We are only looking for high-quality applicants with spare time.
    As it stands, we won't have much spare dev time for PVEWHMCS in early 2025.

<img alt="Logo for the Proxmox VE for WHMCS module" src="zLOGO.png">

- Configure VM/CT plans with custom CPU/RAM/VLAN/On-boot/Bandwidth/etc
- Automatically Provision VMs & CTs in **Proxmox VE** from **WHMCS** easily
- Allow clients to view/manage VMs using the WHMCS Client Area
- Create/Suspend/Unsuspend/Terminate via WHMCS Admin Area
- Statistics/Graphing is available in the Client Area for services :)
- Leverage the power of QEMU & LXC with PVE's convenience

Repo: https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/

## ❤️ RTFM: Read the Manual & Review the Module!

**Please read the entire README.md file before getting started with Proxmox VE for WHMCS.** Thanks!

We're pretty much done overhauling the Module to suit our needs at The Network Crew Pty Ltd & Merlot Digital.

> **Please review the module!** https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs#reviews
> 
> _If you want it to remain free and fabulous, it could use a moment of your time in reviewing it._ **Thanks!**

## 🎯 MODULE: System Requirements (PVE/WHMCS)

New Biz: Fresh Installations/Businesses using WHMCS need to take note of the Service ID < 100 case.

**SID >100:** The WHMCS Service ID requirement is CRITICAL, as Proxmox reserves VMIDs <100 (system). 

- (WHMCS) v8.x.x stable (HTTPS)
- (WHMCS) **Service ID above 100**
- (PHP) v8.x.x (latest stable version)
- (PHP) max_execution_time = 300
- (Proxmox) VE v8.x (current)
- (Proxmox) 2 users (API/VNC)

_If you don't have enough services (of any status) in WHMCS (DB: tblhosting.id), create enough dummy/test entries to reach Service ID 101+._ **Else you're likely to see an error which explains this:** `HTTP/1.1 400 Parameter verification failed. (invalid format - value does not look like a valid VM ID)`

## ✅ MODULE: Installation & Configuration

**DON'T SKIP ANY PART OF THIS README.md - please don't raise pointless Issues - thank you!**

Firstly, you need to upload, activate and make the WHMCS Module available to Administrators.

Once you've done all of that, in order to get the module working properly, you need to:

0. Proxmox VE > Create an additional VNC-only user, per instructions below
1. WHMCS Admin > Config > Servers > Add your PVE host/s (user: root; IP: PVE's)
2. WHMCS Admin > Addons > Proxmox VE for WHMCS > Module Config > VNC Secret (see below)
3. WHMCS Admin > Addons > Proxmox VE for WHMCS > Add KVM/LXC Plan/s
4. WHMCS Admin > Addons > Proxmox VE for WHMCS > Add an IP Pool
5. WHMCS Admin > Config > Products/Services > New Service (create offering)
6. " " > Newly-added Service > Tab 3 > **SAVE** (links Module Plan to WHMCS Service type)

## 🥽 noVNC: Console Tunnel (Client Area)

After forking the module, we considered how to improve security of Console Tunneling via WHMCS. We decided to implement a routing method which uses a secondary user in Proxmox VE with very restrictive permissions. This is due to be re-built again to further enhance security.

### To offer VNC via WHMCS Client Area

1. Install & configure the module properly
2. Follow the PVE User Requirement info below
3. Public IPv4 for PVE (or proxy to private)
4. PVE and WHMCS on the same Domain Name*
5. Have valid PTR/rDNS for the PVE Address

noVNC has been overhauled. It isn't guaranteed, nor the project at all. :-)

- Note #1 = You must use different Subdomains on the same Domain Name, for the cookie (anti-CSRF).
- Note #2 = If your Domain Name has a 2-part TLD (ie. co.uk) then you will need to fork & amend `novnc_router.php` - ideally we/someone will optimise this to better cater to all formats.

## 👥 PVE: User Requirements (API & VNC)

**You must have a root account to use the Module at all.** Configured via WHMCS > Servers.

Additionally, to improve security, for VNC you must also have a Restricted User. Configured in the _Module_.

### Creating the VNC user within PVE

1. Create User Group "VNC" via PVE > ` Datacenter / Permissions / Group`
2. Create new User "vnc" > `Datacenter / Permissions / Users` - Group: "VNC", Realm: pve
3. Create new Role -> `Datacenter / Permissions / Roles` - Name: "VNC", Privileges: VM.Console (only)
4. Permit VNC Access -> `Datacenter / Permissions / Add Group Permissions` - Group: "VNC", Role: "VNC"
5. WHMCS > Modules > Proxmox VE for WHMCS > Module Config > VNC Secret = 'vnc' password (PVE) you set

> Do NOT set less restrictive permissions. The above is designed for hypervisor security.
> 
> However, if you wish for proper security, wait for VNC to be further improved.

## ⚙️ VM/CT PLANS: Setting everything up

These steps explain the unique requirements per-option.

Custom Fields: Values need to go in Name & Select Options.

> **Unsure?** Consult the zMANUAL-PVE4.pdf _legacy_ manual file.

### VM Option 1: KVM, using PVE Template VM

Firstly, create the Template in PVE. You need its unique PVE ID.

Use that ID in the Custom Field `KVMTemplate`, as in `ID|Name`.

> Note: `Name` is what's displayed in the WHMCS Client Area.

### VM Option 2: KVM, WHMCS Plan + PVE ISO

Firstly, create the Plan in WHMCS Module. Then, WHMCS Config > Services.

> Under the Service, you need to add a Custom Field `ISO` with the full location.

### CT Option: LXC, using PVE Template File

Firstly, store the Template in PVE. You need its unique File Name.

> Use that full file name in the Custom Field `Template`, as in:<br>
> `ubuntu-99.99-standard_amd64.tar.gz|Ubuntu 99`

Then make a 2nd Custom Field `Password` for the CT's root user.

## 🌐 IPv4/v6: Networking (IP Pools)

Please make sure you create an IP Pool with sufficient scope/size to be able to deploy addresses within it to your guest VMs and CTs. Else it won't be able to create a Service for you.

**Private IPs for PVE Hosts:** Note that VNC may be problematic without work due to the strict requirements introduced in Proxmox v8.0 (strict same-site attribute).

### IPv6: SLAAC default, 2nd vNIC

Per The-Network-Crew/Proxmox-VE-for-WHMCS#33 there's SLAAC/DHCP/off available (2x vNICs) (May 2024).

You can of course add different config via PVE/`pvesh` manually, if you need to specify a prefix.

## 💅 FEATURES: PVE v8.x bling

There are new features deployed into Proxmox VE upstream in the v8 branch which are exciting and should be added to this module.

### Proxmox v8.0

1. Create, manage and assign resource mappings for PCI and USB devices for use in virtual machines (VMs) via API and web UI. 
2. (DONE) Add virtual machine CPU models based on the x86-64 psABI Micro-Architecture Levels and use the widely supported x86-64-v2-AES as default for new VMs created via the web UI. 

### Proxmox v8.1

1. Secure Boot support.
2. Software Defined Networking (SDN).
3. New flexible notification system (SMTP & Gotify).
4. MAC Organizationally Unique Identifier (OUI) BC:24:11: prefix!

### Proxmox 8.2

1. Import Wizard for Guests.
2. Unattended PVE Install (answer file).
3. Backup Fleecing (local disk as data block buffer).
4. Firewall Preview (based on nftables).

PVE Roadmap: https://pve.proxmox.com/wiki/Roadmap

## 🤬 ABUSE: Zero Tolerance (ZT)

This module has been overhauled and remains functionally-OK but not thoroughly tested nor reviewed.

Your support and assistance is always welcomed per the spirit of FOSS (Free Open-source Software)!

If you cannot accept this, do not download nor use the code. Complaints, nasty reviews, and similar behaviour is against the spirit of FOSS and will not be tolerated. 

**Be grateful & considerate - thank you!**

## 🆘 HELP: Best-effort Support

**Before raising a GitHub Issue, please check:**

1. The Wiki.
2. The README.md.
3. Open GitHub Issues on the repo.
4. HTTP, PHP, WHMCS & debug logs (see below).
5. PVE logs; best practices; network; etc.
6. Read the errors. Do they explain it?

> Help: Including logs, details, steps to reproduce, etc, please raise a **GitHub Issue**.
>
> Logs: We work to ensure that Proxmox VE for WHMCS passes through error details to you.

### Issues/etc raised must include:

#### Logging & Debug Logging

- (Logs: PHP) `error_log` contents
- (Logs: WHMCS) Module Debug Logging*
- (Logs: Config) WHMCS Display/Log Errors = ON
- (Logs: PVE) Logs from Proxmox Host (`pveproxy` etc)

#### Other Support Requirements

- (Visibility) Screenshots of the issue
- (Configs) WHMCS/PHP/Module/Proxmox/etc
- (Reproduction) `pvesh` etc variants of failing calls
- (Network) Proof WHMCS Server can talk to PVE OK
- (PEBKAC) _PROOF THAT YOU'VE FOLLOWED THIS README!_

The more info/context you provide up-front, the quicker & easier it will be!

\* Debug: Also enable Debug Logging in Proxmox VE for WHMCS > Settings, as needed.

**Please note that this is FOSS and Support is not guaranteed at all.**

**If you don't read, listen or actively try, no help is given.**

## 🔄 UPDATING: Patching the Module

WHMCS Admin > Addon Modules > Proxmox VE for WHMCS > Support/Health shows updates.

You can download the new version and upload it over the top, then run any needed SQL ops.

Please consult the **UPDATE-SQL.md** file, open your WHMCS DB & run the statements. Then you're done.

## 🖥️ INC: Libraries & Dependencies

- (MIT) PHP Client for PVE2 API (Dec 5th, 2022) https://github.com/CpuID/pve2-api-php-client
- (GPLv2) TigerVNC VncViewer.jar (v1.14.0 in repo) https://sourceforge.net/projects/tigervnc/files/stable/
- (MPLv2) noVNC HTML5 Viewer (v1.5.0 in repo) https://github.com/novnc/noVNC
- (GPLv3) SPICE HTML5 Viewer (v0.3 in repo) https://gitlab.freedesktop.org/spice/spice-html5
- (MIT) IPv4/SN Validation (August 2012) https://github.com/tapmodo/php-ipv4/

## 📄 DIY: Documentation & Resources

- Proxmox API: https://pve.proxmox.com/pve-docs/api-viewer/
- TigerVNC: https://github.com/TigerVNC/tigervnc/wiki
- noVNC: https://github.com/novnc/noVNC/wiki
- WHMCS: https://developers.whmcs.com/
- x86-64-ABI: https://gitlab.com/x86-psABIs/x86-64-ABI/-/jobs/artifacts/master/raw/x86-64-ABI/abi.pdf?job=build

## 🎉 FOSS: Contributions & Open-source

If you'd like to contribute to the Module, please open a Pull on GitHub >> The-Network-Crew/Proxmox-VE-for-WHMCS

The original module was written in 2 months by @cybercoder for sale online in 2016, though didn't sell any copies so they kindly open-sourced it and removed the licensing requirement.

We would like to thank @cybercoder and @WaldperlachFabi for their original contributions and troubleshooting assistance respectively. 

Thank you to psyborg® for the module's logo design! We love it.

FOSS is only possible thanks to dedicated individuals!

## Usage License (GPLv3) & Links to TNC & Co.

_**This module is licensed under the GNU General Public License (GPL) v3.0.**_

GPLv3: https://www.gnu.org/licenses/gpl-3.0.txt (by the Free Software Foundation)

### Corporate Sites: TNC & Merlot Digital

**The Network Crew Pty Ltd** :: https://tnc.works

**Merlot Digital** :: https://merlot.digital

### Support: Best-effort via GitHub Issues

Browse issues, raise a new one: **GitHub Issues**
