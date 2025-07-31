# Proxmox VE for WHMCS (Module) Provision & Manage

<img alt="Logo for the Proxmox VE for WHMCS module" src="zLOGO.png">

**Salvation, a free and open-source solution for beloved PVE!** If you love it, REVIEW & SHARE IT! ❤️

    TNC Dev are looking for a co-developer to assist with finishing the project overhaul.
    If you have proven and public git-logged experience, or similar, please say g'day.
    
    Please note: We are only looking for high-quality applicants with spare time.
    As it stands, we won't have much spare dev time for PVEWHMCS in early 2025.

- Configure VM/CT plans with custom CPU/RAM/VLAN/On-boot/Bandwidth/etc
- Automatically Provision VMs & CTs in **Proxmox VE** from **WHMCS** easily
- Allow clients to view/manage VMs using the WHMCS Client Area
- Create/Suspend/Unsuspend/Terminate via WHMCS Admin Area
- Statistics/Graphing is available in the Client Area for services :)
- Leverage the power of QEMU & LXC with PVE's convenience
- Import existing VM/CT Guest from Proxmox into WHMCS
- Specify PVE VMID start & integrate to your schema

Repo: https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/

# ❤️ RTFM: Read the Manual & Review the Module!

**Please read the entire README.md file before getting started with Proxmox VE for WHMCS.** Thanks!

We're pretty much done overhauling the Module to suit our needs at The Network Crew Pty Ltd & Merlot Digital.

> **Please review the module!** https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs#reviews
> 
> _If you want it to remain free and fabulous, it could use a moment of your time in reviewing it._ **Thanks!**

# 🎯 MODULE: System Requirements (PVE/WHMCS)

- **(WHMCS)** v8.x.x stable (HTTPS)
- **(WHMCS)** **Service ID above 100**
- **(NET)** WAN Access: WHMCS to PVE
- **(VNC)** Special Requirements: PTR, etc.
- **(PHP)** v8.x.x (latest stable version)
- **(PHP)** max_execution_time = 300
- **(Proxmox)** 2 users (API & VNC)
- **(Proxmox)** VE v8.x.x (current)

# ✅ MODULE: Installation & Configuration

> [!WARNING]
> **DON'T SKIP ANY PART OF THIS README.md - please don't raise pointless Issues - thank you!**

## 📋 1. PREP: Upload & Configure the Module

Check the System Requirements above, and resolve any blockers to using Proxmox VE for WHMCS.

### 👥 PVE: User x2 Requirement (API & VNC users)

#### Credentials: root account for each PVE host

**You must have a root account to use the Module at all.** Configured via WHMCS > Servers.

This is configured in the pam realm. We plan to allow selection in v1.2.9.

#### Credentials: VNC user for Console Access only

Additionally, to improve security, for VNC you must also have a Restricted User. 

Configured in the _Module_ as detailed below, once you've added/restricted it in PVE.

### 🏃‍♂️ INSTALL: Getting ready to use the Module!

First up, get the basics sorted out:

0. Upload the Module to your WHMCS installation, ensuring correct permissions/ownership.
1. Activate it via WHMCS > Addon Modules > Proxmox VE for WHMCS > Activate.
2. In the same spot post-activation, expose the Module to Administrators.
3. Make sure your Proxmox host has a valid SSL Certificate installed.
4. Ensure you've TCP/8006 connectivity between WHMCS & PVE.

Once you've done all of that, in order to get the module working properly, you need to:

0. Proxmox VE > Create an additional VNC-only user, per instructions below
1. WHMCS Admin > Config > Servers > Add (Advanced) > PVE Host/s (User: `root`; IPv4: `PVE's`; no port suffix!)
2. WHMCS Admin > Addons > Proxmox VE for WHMCS > Module Config > VNC Secret (see below)
3. WHMCS Admin > Addons > Proxmox VE for WHMCS > Add KVM/LXC Plan/s
4. WHMCS Admin > Addons > Proxmox VE for WHMCS > Add an IPv4 Pool
5. WHMCS Admin > Config > Products/Services > New Service (create offering)
6. " " > Newly-added Service > Tab 3 > **SAVE** (links Module Plan to WHMCS Service type)

## 🥽 2. noVNC: Console Tunnel (Client Area)

After forking the module, we considered how to improve security of Console Tunneling via WHMCS. We decided to implement a routing method which uses a secondary user in Proxmox VE with very restrictive permissions. 

This is due to be re-built again to further enhance security.

### How to offer VNC via WHMCS Client Area

1. Install & configure the module properly
2. Follow the PVE User Requirement info below
3. Public IPv4 for PVE (or proxy to private)
4. PVE and WHMCS on the same Domain Name*
5. Have valid PTR/rDNS for the PVE Address

If proxying, that is your responsibility to diagnose.

Else, PVE must be WAN-accessible and all other configs/reqs satisfied.

### Creating the VNC User within Proxmox VE

1. Create User Group "VNC" via PVE > ` Datacenter / Permissions / Group`
2. Create new User "vnc" > `Datacenter / Permissions / Users` - Group: "VNC", Realm: pve
3. Create new Role -> `Datacenter / Permissions / Roles` - Name: "VNC", Privileges: VM.Console (only)
4. Permit VNC Access -> `Datacenter / Permissions / Add Group Permissions` - Group: "VNC", Role: "VNC"
5. WHMCS > Modules > Proxmox VE for WHMCS > Module Config > VNC Secret = 'vnc' password (PVE) you set

> [!CAUTION]
> Do NOT set less restrictive permissions. The above is designed for interim security.
> 
> **However, if you wish for proper security: wait for VNC to be further improved.**

### Important info about Console Access

noVNC has been overhauled. It isn't guaranteed, nor the project at all. :-)

- Note #1 = You must use different Subdomains on the same Domain Name, for the cookie (anti-CSRF).
- Note #2 = If your Domain Name has a 2-part TLD (ie. co.uk) then you will need to fork & amend `novnc_router.php` - ideally we/someone will optimise this to better cater to all formats.

## 🌐 3. Networking: IPv4 Pools, IPv6, vmbr/SDN

### IPv4: Pool required for assignment

Please make sure you create an IP Pool with sufficient scope/size to be able to deploy addresses within it to your guest VMs and CTs. Else it won't be able to create a Service for you.

**Private IPs for PVE Hosts:** Note that VNC may be problematic without work due to the strict requirements introduced in Proxmox v8.0 (strict same-site attribute).

### IPv6: SLAAC default, 2nd vNIC

Per The-Network-Crew/Proxmox-VE-for-WHMCS#33 there's SLAAC/DHCP/off available (2x vNICs) (May 2024).

You can of course add different config via PVE/`pvesh` manually, if you need to specify a prefix.

### vmbr / SDN: Config type

This depends on your configuration on the PVE Host/s - bridge (vmbr0 etc) or software-defined (SDN).

**If normal (bridged)** - use `vmbr` as the Network, then use `0` as the Interface ID - this makes up `vmbr0`.

**If SDN (Software Defined Network)** - use SDN Name for Network, leave Interface ID blank (= no suffix).

## ⚙️ 4. VM/CT PLANS: Setting everything up

These steps explain the unique requirements for QEMU & LXC guests.

**Custom Fields:** Values need to go in Name & Select Options.<br>
This needs configuring for each `WHMCS Admin > Products & Services` entry.

### VM Option 1: QEMU, PVE Template VM Clone

Firstly, create the Template VM in PVE. You need its unique PVE ID.

Use that ID in the Custom Field `KVMTemplate`, as in `ID|Name`.

> Note: `ID` is the Unique ID that your Template VM has in PVE.<br>
> Note: `Name` is what will be displayed to your Clients in WHMCS.

### VM Option 2: QEMU, WHMCS Plan + PVE ISO

Firstly, create the Plan in WHMCS Module. Then, WHMCS Config > Services.

> Under the Service, you need to add a Custom Field `ISO` with the full location.<br>
> This ISO must be located on the PVE Host, and not on the WHMCS installation side.

### CT Option 1: LXC, PVE Template File

Firstly, store the Template in PVE. You need its storage, folder & File Name.

> Use that prefixed file name in the Custom Field `Template`, as in:<br>
> `local:vztmpl/ubuntu-99.99-standard_amd64.tar.gz|Ubuntu 99`

### VM/CT Import/Associate Existing Guest

You can associate an existing PVE Guest through the WHMCS Module too, like this:

<img alt="Importing GUI for linking to existing PVE Guest" src="zVMIDimport.png">

#### ZFS etc: Comfigure to suit isolated TPL

- `local` is the name of the file-system that you have the Template on
- `vztmpl` is the directoty name per convention, with the ISO within
- `ubuntu-99.99-...` etc is the Template file name, exactly as-is

ie. If using ZFS for Templates, substitute local with volume name.

#### Password: Configure the CT's root user

Make a 2nd Custom Field `Password` for the CT's root user.

## 🔄 5. PATCH: Updating the Module

**Check:** `WHMCS Admin > Addon Modules > Proxmox VE for WHMCS > Support/Health`

You should download any new version & upload it over the top, then login to WHMCS Admin.

### SQL: Keeping your DB up-to-date

> [!IMPORTANT]  
> Since v1.2.9, logging into WHMCS Admin & opening the module should run any needed SQL Ops.
> 
> v1.2.8 & below, consult the **UPDATE-SQL.md** file, open your SQL DB & run statements. 

Then you're done with each update! :-)

## 🆘 6. HELP: Best-effort Support

### Before raising a GitHub Issue, please check:

1. The Wiki.
2. The README.md.
3. Open GitHub Issues on the repo.
4. HTTP, PHP, WHMCS & debug logs (see below).
5. PVE logs; best practices; network; etc.
6. Read the errors. Do they explain it?

### Issues/etc raised must include:

**Logs:** We work to ensure that Proxmox VE for WHMCS passes through error details to you.

#### All Logs & Debug Logging too

- **(Logs: PHP)** `error_log` contents
- **(Logs: WHMCS)** Module Debug Logging*
- **(Logs: Config)** WHMCS Display/Log Errors = ON
- **(Logs: PVE)** Logs from Proxmox Host/s (`pveproxy` etc)

#### Other Requirements for Support

- **(Visibility)** Screenshots of the issue
- **(Configs)** WHMCS/PHP/Module/Proxmox/etc
- **(Reproduction)** `pvesh` etc variants of failing calls
- **(Network)** Proof WHMCS Server can talk to PVE OK
- **(PEBKAC)** _PROOF THAT YOU'VE FOLLOWED THIS README!_

The more info/context you provide up-front, the quicker & easier it will be!

\* Debug: Also enable Debug Logging in Proxmox VE for WHMCS > Settings, as needed.

> [!TIP]
> **Please note that this is FOSS and Support is not guaranteed at all.**<br>
> 
> **If you don't read, listen or actively try, no help is given.**

https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/new/choose

# 💅 FEATURES: Upcoming PVE bling

There are new features deployed into PVE upstream which are exciting and may be integrated.

**PVE Roadmap:** https://pve.proxmox.com/wiki/Roadmap

### Proxmox v9.0 beta

1. VM snapshots on thick LVM, snapshots as volume chains
2. Fabrics for software networking (SDN) Open/OSPF/Ceph/VPN
3. Major upgrade to Debian Trixie (testing status in 2025)

### Proxmox v8.4

1. Live migrate with mediated devices.
2. Support for external backup providers.
3. Host dir's, share with guests (virtiofs).

### Proxmox v8.3

1. Software-defined Networking/Firewall.
2. Better guest importing from OVA/OVF.
3. Webhook target for system alerting.
4. Better change detection for PBS.

### Proxmox v8.2

1. Import Wizard for Guests.
2. Unattended PVE Install (answer file).
3. Backup Fleecing (local disk as data block buffer).
4. Firewall Preview (based on nftables).

### Proxmox v8.1

1. Secure Boot support.
2. Software Defined Networking (SDN).
3. New flexible notification system (SMTP & Gotify).
4. MAC Organizationally Unique Identifier (OUI) BC:24:11: prefix!

### Proxmox v8.0

1. Create, manage and assign resource mappings for PCI and USB devices for use in virtual machines (VMs) via API and web UI. 
2. (DONE) Add virtual machine CPU models based on the x86-64 psABI Micro-Architecture Levels and use the widely supported x86-64-v2-AES as default for new VMs created via the web UI. 

### Proxmox 7.x

1. Cross-cluster guest migrations
2. Cluster Resource Scheduling (CRS) launched
3. Re-balance CRS on fresh start-up, not just  recovery
4. CRM into HA Manager, as a node maintenance switch 
5. Proxmox Offline Mirror (POM) launched

# 🖥️ INC: Libraries & Dependencies

- **(MIT)** PHP Client for PVE2 API (Dec 5th, 2022) https://github.com/CpuID/pve2-api-php-client
- **(GPLv2)** TigerVNC VncViewer.jar (v1.15.0 in repo) https://sourceforge.net/projects/tigervnc/files/stable/
- **(MPLv2)** noVNC HTML5 Viewer (v1.6.0 in repo) https://github.com/novnc/noVNC
- **(GPLv3)** SPICE HTML5 Viewer (v0.3 in repo) https://gitlab.freedesktop.org/spice/spice-html5
- **(MIT)** IPv4/SN Validation (August 2012) https://github.com/tapmodo/php-ipv4/

# 📄 DIY: Documentation & Resources

- **(Proxmox API)** https://pve.proxmox.com/pve-docs/api-viewer/
- **(TigerVNC)** https://github.com/TigerVNC/tigervnc/wiki
- **(noVNC)** https://github.com/novnc/noVNC/wiki
- **(WHMCS)** https://developers.whmcs.com/
- **(x86-64-ABI)** https://gitlab.com/x86-psABIs/x86-64-ABI/-/jobs/artifacts/master/raw/x86-64-ABI/abi.pdf?job=build

# 🤬 ABUSE: Zero Tolerance (ZT)

This module has been overhauled and remains functionally-OK but not thoroughly tested nor reviewed.

Your support and assistance is always welcomed per the spirit of FOSS (Free Open-source Software)!

If you cannot accept this, do not download nor use the code. Complaints, nasty reviews, and similar behaviour is against the spirit of FOSS and will not be tolerated. 

**Be grateful & considerate - thank you!**

# 🎉 FOSS: Contributions & Open-source

If you'd like to contribute to the Module, please open a Pull on GitHub >> The-Network-Crew/Proxmox-VE-for-WHMCS

The original module was written in 2 months by @cybercoder for sale online in 2016, though didn't sell any copies so they kindly open-sourced it and removed the licensing requirement.

We would like to thank @cybercoder and @WaldperlachFabi for their original contributions and troubleshooting assistance respectively. 

**Thank you to psyborg® for the module's logo design! We love it.**

FOSS is only possible thanks to dedicated people around the world!

**See CONTRIBUTORS.md for those who've made PVEWHMCS possible.**

# TNC & GPL

**The Network Crew Pty Ltd** :: https://tnc.works

**🍷 Merlot Digital** :: https://merlot.digital

**AS138521** :: Australian family owned

> [!NOTE]
> _**This module is licensed under the GNU General Public License (GPL) v3.0.**_
> 
> GPLv3: https://www.gnu.org/licenses/gpl-3.0.txt (by the Free Software Foundation)
