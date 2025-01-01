{extends file='layout.tpl'}
{block name='title'}Plan Add KVM{/block}
{block name='content'}
<form method="post">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Plan Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" name="title" id="title" class="pxInput pxInput_small" required>
                    </li>
                </ul>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxFieldTitle">OS - Type</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="ostype">
                            <option value="l26">Linux 6.x - 2.6 Kernel</option>
                            <option value="l24">Linux 2.4 Kernel</option>
                            <option value="solaris">Solaris Kernel</option>
                            <option value="win11">Windows 11 / 2022</option>
                            <option value="win10">Windows 10 / 2016 / 2019</option>
                            <option value="win8">Windows 8.x / 2012 / 2012r2</option>
                            <option value="win7">Windows 7 / 2008r2</option>
                            <option value="wvista">Windows Vista / 2008</option>
                            <option value="wxp">Windows XP / 2003</option>
                            <option value="w2k">Windows 2000</option>
                            <option value="other">Other</option>
                        </select>
                        <span>Kernel type (Linux, Windows, etc).</span>
                    </li>
                </ul>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxFieldTitle">CPU - Emulation</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="cpuemu">
                            <option value="host">(Host) Host</option>
                            <option value="kvm32">(QEMU) kvm32</option>
                            <option value="kvm64">(QEMU) kvm64</option>
                            <option value="max">(QEMU) Max</option>
                            <option value="qemu32">(QEMU) qemu32</option>
                            <option value="qemu64">(QEMU) qemu64</option>
                            <option value="x86-64-v2">(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
                            <option value="x86-64-v2-AES" selected="">(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
                            <option value="x86-64-v3">(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
                            <option value="x86-64-v4">(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
                            <option value="486">(Intel) 486</option>
                            <option value="Broadwell">(Intel) Broadwell</option>
                            <option value="Broadwell-IBRS">(Intel) Broadwell-IBRS</option>
                            <option value="Broadwell-noTSX">(Intel) Broadwell-noTSX</option>
                            <option value="Broadwell-noTSX-IBRS">(Intel) Broadwell-noTSX-IBRS</option>
                            <option value="Cascadelake-Server">(Intel) Cascadelake-Server</option>
                            <option value="Cascadelake-Server-noTSX">(Intel) Cascadelake-Server-noTSX</option>
                            <option value="Cascadelake-Server-v2">(Intel) Cascadelake-Server-v2</option>
                            <option value="Cascadelake-Server-v4">(Intel) Cascadelake-Server-v4</option>
                            <option value="Cascadelake-Server-v5">(Intel) Cascadelake-Server-v5</option>
                            <option value="Conroe">(Intel) Conroe</option>
                            <option value="Cooperlake">(Intel) Cooperlake</option>
                            <option value="Cooperlake-v2">(Intel) Cooperlake-v2</option>
                            <option value="Haswell">(Intel) Haswell</option>
                            <option value="Haswell-IBRS">(Intel) Haswell-IBRS</option>
                            <option value="Haswell-noTSX">(Intel) Haswell-noTSX</option>
                            <option value="Haswell-noTSX-IBRS">(Intel) Haswell-noTSX-IBRS</option>
                            <option value="Icelake-Client">(Intel) Icelake-Client</option>
                            <option value="Icelake-Client-noTSX">(Intel) Icelake-Client-noTSX</option>
                            <option value="Icelake-Server">(Intel) Icelake-Server</option>
                            <option value="Icelake-Server-noTSX">(Intel) Icelake-Server-noTSX</option>
                            <option value="Icelake-Server-v3">(Intel) Icelake-Server-v3</option>
                            <option value="Icelake-Server-v4">(Intel) Icelake-Server-v4</option>
                            <option value="Icelake-Server-v5">(Intel) Icelake-Server-v5</option>
                            <option value="Icelake-Server-v6">(Intel) Icelake-Server-v6</option>
                            <option value="IvyBridge">(Intel) IvyBridge</option>
                            <option value="IvyBridge-IBRS">(Intel) IvyBridge-IBRS</option>
                            <option value="KnightsMill">(Intel) KnightsMill</option>
                            <option value="Nehalem">(Intel) Nehalem</option>
                            <option value="Nehalem-IBRS">(Intel) Nehalem-IBRS</option>
                            <option value="Penryn">(Intel) Penryn</option>
                            <option value="SandyBridge">(Intel) SandyBridge</option>
                            <option value="SandyBridge-IBRS">(Intel) SandyBridge-IBRS</option>
                            <option value="SapphireRapids">(Intel) SapphireRapids</option>
                            <option value="Skylake-Client">(Intel) Skylake-Client</option>
                            <option value="Skylake-Client-IBRS">(Intel) Skylake-Client-IBRS</option>
                            <option value="Skylake-Client-noTSX-IBRS">(Intel) Skylake-Client-noTSX-IBRS</option>
                            <option value="Skylake-Client-v4">(Intel) Skylake-Client-v4</option>
                            <option value="Skylake-Server">(Intel) Skylake-Server</option>
                            <option value="Skylake-Server-IBRS">(Intel) Skylake-Server-IBRS</option>
                            <option value="Skylake-Server-noTSX-IBRS">(Intel) Skylake-Server-noTSX-IBRS</option>
                            <option value="Skylake-Server-v4">(Intel) Skylake-Server-v4</option>
                            <option value="Skylake-Server-v5">(Intel) Skylake-Server-v5</option>
                            <option value="Westmere">(Intel) Westmere</option>
                            <option value="Westmere-IBRS">(Intel) Westmere-IBRS</option>
                            <option value="pentium">(Intel) Pentium I</option>
                            <option value="pentium2">(Intel) Pentium II</option>
                            <option value="pentium3">(Intel) Pentium III</option>
                            <option value="coreduo">(Intel) Core Duo</option>
                            <option value="core2duo">(Intel) Core 2 Duo</option>
                            <option value="athlon">(AMD) Athlon</option>
                            <option value="phenom">(AMD) Phenom</option>
                            <option value="EPYC">(AMD) EPYC</option>
                            <option value="EPYC-IBPB">(AMD) EPYC-IBPB</option>
                            <option value="EPYC-Milan">(AMD) EPYC-Milan</option>
                            <option value="EPYC-Rome">(AMD) EPYC-Rome</option>
                            <option value="EPYC-Rome-v2">(AMD) EPYC-Rome-v2</option>
                            <option value="EPYC-v3">(AMD) EPYC-v3</option>
                            <option value="Opteron_G1">(AMD) Opteron_G1</option>
                            <option value="Opteron_G2">(AMD) Opteron_G2</option>
                            <option value="Opteron_G3">(AMD) Opteron_G3</option>
                            <option value="Opteron_G4">(AMD) Opteron_G4</option>
                            <option value="Opteron_G5">(AMD) Opteron_G5</option>
                        </select>
                        <span>CPU emulation type. Default is x86-64 psABI v2-AES</span>
                    </li>
                </ul>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxFieldTitle">CPU - Sockets <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cpus" id="cpus" class="pxInput pxInput_small" value="1" min="1" max="4" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">The number of CPU Sockets. 1 - 4.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">CPU - Cores <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cores" id="cores" class="pxInput pxInput_small" value="1" min="1" max="256" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">The number of CPU Cores per socket. 1 - 256.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">CPU - Limit <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cpulimit" id="cpulimit" class="pxInput pxInput_small" value="0" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Limit of CPU usage. Default is 1. Value "0" indicates no CPU limit.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">CPU - Weighting</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cpuunits" id="cpuunits" class="pxInput pxInput_small" value="1024" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024. NOTE: Disable fair-scheduler by setting this to 0.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">RAM - Memory <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="memory" id="memory" class="pxInput pxInput_small" value="2048" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">RAM space in Megabyte e.g 1024 = 1GB (default is 2GB)</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">SSD/HDD - Disk <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="disk" id="disk" class="pxInput pxInput_small" value="10240" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Disk space in Gigabyte e.g 1024 = 1TB (default is 10GB)</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Disk - Format</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="diskformat">
                            <option value="raw">Disk Image (raw)</option>
                            <option selected="" value="qcow2">QEMU Image (qcow2)</option>
                            <option value="vmdk">VMware Image (vmdk)</option>
                        </select>
                    </li>
                </ul>
                <span>Recommend "QEMU/qcow2" (so it can make Snapshots)</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Disk - Cache</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="diskcache">
                            <option selected="" value="">No Cache (Default)</option>
                            <option value="directsync">Direct Sync</option>
                            <option value="writethrough">Write Through</option>
                            <option value="writeback">Write Back</option>
                            <option value="unsafe">Write Back (Unsafe)</option>
                            <option value="none">No Cache</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Disk - Type</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="disktype">
                            <option selected="" value="virtio">Virtio</option>
                            <option value="scsi">SCSI</option>
                            <option value="sata">SATA</option>
                            <option value="ide">IDE</option>
                        </select>
                    </li>
                </ul>
                <span>Virtio is the fastest option, then SCSI, then SATA, etc.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">PVE Store - Name <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" size="8" name="storage" id="storage" class="pxInput pxInput_small" value="local" required>
                    </li>
                </ul>
                <span>Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">PVE Template Store - Location <span class="pxFieldRow_required noText">Required</span></div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" size="8" name="os_storage" id="os_storage" class="pxInput pxInput_small" value="local" required>
                    </li>
                </ul>
                <span>Name of VM/CT Template Storage on Proxmox VE hypervisor. local/local-lvm/etc.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">I/O - Throttling</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="diskio" id="diskio" class="pxInput pxInput_small" value="0" required>
                    </li>
                </ul>
                <span>Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">NIC - Type</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="netmodel">
                            <option selected="" value="e1000">Intel E1000 (Reliable)</option>
                            <option value="virtio">VirtIO (Paravirtualized)</option>
                            <option value="rtl8139">Realtek RTL8139</option>
                            <option value="vmxnet3">VMware vmxnet3</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Network - Rate</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="netrate" id="netrate" class="pxInput pxInput_small">
                    </li>
                </ul>
                <span>Network Rate Limit in Megabit/Second, Blank means unlimited.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Network - BW Limit</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="bw" id="bw" class="pxInput pxInput_small">
                    </li>
                </ul>
                <span>Monthly Bandwidth Limit in Gigabytes, Blank means unlimited.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Network - IPv6 Conf.</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="ipv6">
                            <option value="0">Off</option>
                            <option value="auto">SLAAC</option>
                            <option value="dhcp">DHCPv6</option>
                            <option value="prefix">Prefix</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Network - Mode</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select class="pxInput pxInput_small" name="netmode">
                            <option value="bridge">Bridge</option>
                            <option value="nat">NAT</option>
                            <option value="none">No Network</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Bridge - Interface</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" size="8" name="bridge" id="bridge" class="pxInput pxInput_small" value="vmbr">
                    </li>
                </ul>
                <span>Bridge interface name. Proxmox default bridge name is "vmbr".</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Bridge - Int. ID</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="vmbr" id="vmbr" class="pxInput pxInput_small" value="0">
                    </li>
                </ul>
                <span>Bridge interface number. Proxmox default bridge (vmbr) number is 0, it means "vmbr0".</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">Trunk - VLAN ID</div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="vlanid" id="vlanid" class="pxInput pxInput_small">
                    </li>
                </ul>
                <span>VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), leave blank for untagged.</span>
            </div>
        </li>

        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                Hardware Virt?
            </div>
            <div class="pxFieldContent">
                    <span class="pxToggle pxToggle_on" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                        <span data-role="status"></span>
                        <input type="hidden" name="kvm" value="1" checked>
                    </span>
                <span class="pxFieldRow_desc">Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)</span>
            </div>
        </li>
        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                On-boot VM?
            </div>
            <div class="pxFieldContent">
                    <span class="pxToggle pxToggle_on" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                        <span data-role="status"></span>
                        <input type="hidden" name="onboot" value="1" checked>
                    </span>
                <span class="pxFieldRow_desc">Specifies whether a VM will be started during hypervisor boot-up. (Recommended)</span>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <input type="submit" class="pxButton pxButton_primary" value="Create Plan" class="pxButton">
            </div>
        </li>
    </ul>
</form>
{/block}