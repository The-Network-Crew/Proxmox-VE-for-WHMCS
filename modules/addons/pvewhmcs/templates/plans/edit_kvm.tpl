{extends file='layout.tpl'}
{block name='title'}Plan Edit KVM{/block}
{block name='content'}.
<form method="post">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Plan Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input value="{$plan->title}" type="text" name="title" id="title" class="pxInput pxInput_small" required>
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
                            <option value="l26" {if $plan->ostype == "l26"}selected{/if}>Linux 6.x - 2.6 Kernel</option>
                            <option value="l24" {if $plan->ostype == "l24"}selected{/if}>Linux 2.4 Kernel</option>
                            <option value="solaris" {if $plan->ostype == "solaris"}selected{/if}>Solaris Kernel</option>
                            <option value="win11" {if $plan->ostype == "win11"}selected{/if}>Windows 11 / 2022</option>
                            <option value="win10" {if $plan->ostype == "win10"}selected{/if}>Windows 10 / 2016 / 2019</option>
                            <option value="win8" {if $plan->ostype == "win8"}selected{/if}>Windows 8.x / 2012 / 2012r2</option>
                            <option value="win7" {if $plan->ostype == "win7"}selected{/if}>Windows 7 / 2008r2</option>
                            <option value="wvista" {if $plan->ostype == "wvista"}selected{/if}>Windows Vista / 2008</option>
                            <option value="wxp" {if $plan->ostype == "wxp"}selected{/if}>Windows XP / 2003</option>
                            <option value="w2k" {if $plan->ostype == "w2k"}selected{/if}>Windows 2000</option>
                            <option value="other" {if $plan->ostype == "other"}selected{/if}>Other</option>
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
                            <option value="host" {if $plan->cpuemu == "host"}selected{/if}>Host</option>
                            <option value="kvm32" {if $plan->cpuemu == "kvm32"}selected{/if}>(QEMU) kvm32</option>
                            <option value="kvm64" {if $plan->cpuemu == "kvm64"}selected{/if}>(QEMU) kvm64</option>
                            <option value="max" {if $plan->cpuemu == "max"}selected{/if}>(QEMU) Max</option>
                            <option value="qemu32" {if $plan->cpuemu == "qemu32"}selected{/if}>(QEMU) qemu32</option>
                            <option value="qemu64" {if $plan->cpuemu == "qemu64"}selected{/if}>(QEMU) qemu64</option>
                            <option value="x86-64-v2" {if $plan->cpuemu == "x86-64-v2"}selected{/if}>(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
                            <option value="x86-64-v2-AES" {if $plan->cpuemu == "x86-64-v2-AES"}selected{/if}>(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
                            <option value="x86-64-v3" {if $plan->cpuemu == "x86-64-v3"}selected{/if}>(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
                            <option value="x86-64-v4" {if $plan->cpuemu == "x86-64-v4"}selected{/if}>(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
                            <option value="486" {if $plan->cpuemu == "486"}selected{/if}>(Intel) 486</option>
                            <option value="Broadwell" {if $plan->cpuemu == "Broadwell"}selected{/if}>(Intel) Broadwell</option>
                            <option value="Broadwell-IBRS" {if $plan->cpuemu == "Broadwell-IBRS"}selected{/if}>(Intel) Broadwell-IBRS</option>
                            <option value="Broadwell-noTSX" {if $plan->cpuemu == "Broadwell-noTSX"}selected{/if}>(Intel) Broadwell-noTSX</option>
                            <option value="Broadwell-noTSX-IBRS" {if $plan->cpuemu == "Broadwell-noTSX-IBRS"}selected{/if}>(Intel) Broadwell-noTSX-IBRS</option>
                            <option value="Cascadelake-Server" {if $plan->cpuemu == "Cascadelake-Server"}selected{/if}>(Intel) Cascadelake-Server</option>
                            <option value="Cascadelake-Server-noTSX" {if $plan->cpuemu == "Cascadelake-Server-noTSX"}selected{/if}>(Intel) Cascadelake-Server-noTSX</option>
                            <option value="Cascadelake-Server-v2" {if $plan->cpuemu == "Cascadelake-Server-v2"}selected{/if}>(Intel) Cascadelake-Server V2</option>
                            <option value="Cascadelake-Server-v4" {if $plan->cpuemu == "Cascadelake-Server-v4"}selected{/if}>(Intel) Cascadelake-Server V4</option>
                            <option value="Cascadelake-Server-v5" {if $plan->cpuemu == "Cascadelake-Server-v5"}selected{/if}>(Intel) Cascadelake-Server V5</option>
                            <option value="Conroe" {if $plan->cpuemu == "Conroe"}selected{/if}>(Intel) Conroe</option>
                            <option value="Cooperlake" {if $plan->cpuemu == "Cooperlake"}selected{/if}>(Intel) Cooperlake</option>
                            <option value="Cooperlake-v2" {if $plan->cpuemu == "Cooperlake-v2"}selected{/if}>(Intel) Cooperlake V2</option>
                            <option value="Haswell" {if $plan->cpuemu == "Haswell"}selected{/if}>(Intel) Haswell</option>
                            <option value="Haswell-IBRS" {if $plan->cpuemu == "Haswell-IBRS"}selected{/if}>(Intel) Haswell-IBRS</option>
                            <option value="Haswell-noTSX" {if $plan->cpuemu == "Haswell-noTSX"}selected{/if}>(Intel) Haswell-noTSX</option>
                            <option value="Haswell-noTSX-IBRS" {if $plan->cpuemu == "Haswell-noTSX-IBRS"}selected{/if}>(Intel) Haswell-noTSX-IBRS</option>
                            <option value="Icelake-Client" {if $plan->cpuemu == "Icelake-Client"}selected{/if}>(Intel) Icelake-Client</option>
                            <option value="Icelake-Client-noTSX" {if $plan->cpuemu == "Icelake-Client-noTSX"}selected{/if}>(Intel) Icelake-Client-noTSX</option>
                            <option value="Icelake-Server" {if $plan->cpuemu == "Icelake-Server"}selected{/if}>(Intel) Icelake-Server</option>
                            <option value="Icelake-Server-noTSX" {if $plan->cpuemu == "Icelake-Server-noTSX"}selected{/if}>(Intel) Icelake-Server-noTSX</option>
                            <option value="Icelake-Server-v3" {if $plan->cpuemu == "Icelake-Server-v3"}selected{/if}>(Intel) Icelake-Server V3</option>
                            <option value="Icelake-Server-v4" {if $plan->cpuemu == "Icelake-Server-v4"}selected{/if}>(Intel) Icelake-Server V4</option>
                            <option value="Icelake-Server-v5" {if $plan->cpuemu == "Icelake-Server-v5"}selected{/if}>(Intel) Icelake-Server V5</option>
                            <option value="Icelake-Server-v6" {if $plan->cpuemu == "Icelake-Server-v6"}selected{/if}>(Intel) Icelake-Server V6</option>
                            <option value="Jasperlake" {if $plan->cpuemu == "Jasperlake"}selected{/if}>(Intel) Jasperlake</option>
                            <option value="KabyLake" {if $plan->cpuemu == "KabyLake"}selected{/if}>(Intel) KabyLake</option>
                            <option value="KabyLake-IBRS" {if $plan->cpuemu == "KabyLake-IBRS"}selected{/if}>(Intel) KabyLake-IBRS</option>
                            <option value="KabyLake-noTSX" {if $plan->cpuemu == "KabyLake-noTSX"}selected{/if}>(Intel) KabyLake-noTSX</option>
                            <option value="KabyLake-noTSX-IBRS" {if $plan->cpuemu == "KabyLake-noTSX-IBRS"}selected{/if}>(Intel) KabyLake-noTSX-IBRS</option>
                            <option value="Lakes" {if $plan->cpuemu == "Lakes"}selected{/if}>(Intel) Lakes</option>
                            <option value="Lakes-noTSX" {if $plan->cpuemu == "Lakes-noTSX"}selected{/if}>(Intel) Lakes-noTSX</option>
                            <option value="Micoarch" {if $plan->cpuemu == "Micoarch"}selected{/if}>(Intel) Micoarch</option>
                            <option value="Micoarch-v2" {if $plan->cpuemu == "Micoarch-v2"}selected{/if}>(Intel) Micoarch V2</option>
                            <option value="Nehalem" {if $plan->cpuemu == "Nehalem"}selected{/if}>(Intel) Nehalem</option>
                            <option value="Nehalem-IBRS" {if $plan->cpuemu == "Nehalem-IBRS"}selected{/if}>(Intel) Nehalem-IBRS</option>
                            <option value="Pineview" {if $plan->cpuemu == "Pineview"}selected{/if}>(Intel) Pineview</option>
                            <option value="Skylake-Server" {if $plan->cpuemu == "Skylake-Server"}selected{/if}>(Intel) Skylake-Server</option>
                            <option value="Skylake-Server-noTSX" {if $plan->cpuemu == "Skylake-Server-noTSX"}selected{/if}>(Intel) Skylake-Server-noTSX</option>
                            <option value="Skylake-Server-v2" {if $plan->cpuemu == "Skylake-Server-v2"}selected{/if}>(Intel) Skylake-Server V2</option>
                            <option value="Skylake-Server-v4" {if $plan->cpuemu == "Skylake-Server-v4"}selected{/if}>(Intel) Skylake-Server V4</option>
                            <option value="Skylake-Server-v5" {if $plan->cpuemu == "Skylake-Server-v5"}selected{/if}>(Intel) Skylake-Server V5</option>
                            <option value="Skylake-Server-v6" {if $plan->cpuemu == "Skylake-Server-v6"}selected{/if}>(Intel) Skylake-Server V6</option>
                            <option value="Skylake-Server-v7" {if $plan->cpuemu == "Skylake-Server-v7"}selected{/if}>(Intel) Skylake-Server V7</option>
                            <option value="Sandybridge" {if $plan->cpuemu == "Sandybridge"}selected{/if}>(Intel) Sandybridge</option>
                            <option value="Sandybridge-IBRS" {if $plan->cpuemu == "Sandybridge-IBRS"}selected{/if}>(Intel) Sandybridge-IBRS</option>
                            <option value="Sandybridge-noTSX" {if $plan->cpuemu == "Sandybridge-noTSX"}selected{/if}>(Intel) Sandybridge-noTSX</option>
                            <option value="Sandybridge-noTSX-IBRS" {if $plan->cpuemu == "Sandybridge-noTSX-IBRS"}selected{/if}>(Intel) Sandybridge-noTSX-IBRS</option>
                            <option value="Server" {if $plan->cpuemu == "Server"}selected{/if}>(Intel) Server</option>
                            <option value="Server-IBRS" {if $plan->cpuemu == "Server-IBRS"}selected{/if}>(Intel) Server-IBRS</option>
                            <option value="Server-noTSX" {if $plan->cpuemu == "Server-noTSX"}selected{/if}>(Intel) Server-noTSX</option>
                            <option value="Server-noTSX-IBRS" {if $plan->cpuemu == "Server-noTSX-IBRS"}selected{/if}>(Intel) Server-noTSX-IBRS</option>
                            <option value="Server-WS" {if $plan->cpuemu == "Server-WS"}selected{/if}>(Intel) Server-WS</option>
                            <option value="Silverlake" {if $plan->cpuemu == "Silverlake"}selected{/if}>(Intel) Silverlake</option>
                            <option value="Skylake-Client" {if $plan->cpuemu == "Skylake-Client"}selected{/if}>(Intel) Skylake-Client</option>
                            <option value="SkyLake-Client-noTSX" {if $plan->cpuemu == "SkyLake-Client-noTSX"}selected{/if}>(Intel) Skylake-Client-noTSX</option>
                            <option value="SkyLake-Client-v2" {if $plan->cpuemu == "SkyLake-Client-v2"}selected{/if}>(Intel) Skylake-Client V2</option>
                            <option value="SkyLake-Client-v3" {if $plan->cpuemu == "SkyLake-Client-v3"}selected{/if}>(Intel) Skylake-Client V3</option>
                            <option value="SkyLake-Client-v4" {if $plan->cpuemu == "SkyLake-Client-v4"}selected{/if}>(Intel) Skylake-Client V4</option>
                            <option value="Snowlake" {if $plan->cpuemu == "Snowlake"}selected{/if}>(Intel) Snowlake</option>
                            <option value="Snowlake-noTSX" {if $plan->cpuemu == "Snowlake-noTSX"}selected{/if}>(Intel) Snowlake-noTSX</option>
                            <option value="Snowlake-v2" {if $plan->cpuemu == "Snowlake-v2"}selected{/if}>(Intel) Snowlake V2</option>
                            <option value="Snowlake-v3" {if $plan->cpuemu == "Snowlake-v3"}selected{/if}>(Intel) Snowlake V3</option>
                            <option value="Tock" {if $plan->cpuemu == "Tock"}selected{/if}>(Intel) Tock</option>
                            <option value="Westmere" {if $plan->cpuemu == "Westmere"}selected{/if}>(Intel) Westmere</option>
                            <option value="Westmere-IBRS" {if $plan->cpuemu == "Westmere-IBRS"}selected{/if}>(Intel) Westmere-IBRS</option>
                            <option value="Westmere-noTSX" {if $plan->cpuemu == "Westmere-noTSX"}selected{/if}>(Intel) Westmere-noTSX</option>
                            <option value="Westmere-noTSX-IBRS" {if $plan->cpuemu == "Westmere-noTSX-IBRS"}selected{/if}>(Intel) Westmere-noTSX-IBRS</option>
                            <option value="other" {if $plan->cpuemu == "other"}selected{/if}>Other</option>
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
                        <input type="number" name="cpus" id="cpus" class="pxInput pxInput_small" value="{$plan->cpus}" min="1" max="4" required>
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
                        <input type="number" name="cores" id="cores" class="pxInput pxInput_small" value="{$plan->cores}" min="1" max="256" required>
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
                        <input type="number" name="cpulimit" id="cpulimit" class="pxInput pxInput_small" value="{$plan->cpulimit}" required>
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
                        <input type="number" name="cpuunits" id="cpuunits" class="pxInput pxInput_small" value="{$plan->cpuunits}" required>
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
                        <input type="number" name="memory" id="memory" class="pxInput pxInput_small" value="{$plan->memory}" required>
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
                        <input type="number" name="disk" id="disk" class="pxInput pxInput_small" value="{$plan->disk}" required>
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
                            <option value="raw" {if $plan->diskformat == "raw"}selected{/if}>Disk Image (raw)</option>
                            <option value="qcow2" {if $plan->diskformat == "qcow2"}selected{/if}>QEMU image (qcow2)</option>
                            <option value="vmdk" {if $plan->diskformat == "vmdk"}selected{/if}>VMware image (vmdk)</option>
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
                            <option value="" {if $plan->diskcache == ""}selected{/if}>No Cache (Default)</option>
                            <option value="directsync" {if $plan->diskcache == "directsync"}selected{/if}>Direct Sync</option>
                            <option value="writethrough" {if $plan->diskcache == "writethrough"}selected{/if}>Write Through</option>
                            <option value="writeback" {if $plan->diskcache == "writeback"}selected{/if}>Write Back</option>
                            <option value="unsafe" {if $plan->diskcache == "unsafe"}selected{/if}>Write Back (Unsafe)</option>
                            <option value="none" {if $plan->diskcache == "none"}selected{/if}>No Cache</option>
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
                            <option value="virtio" {if $plan->disktype == "virtio"}selected{/if}>Virtio</option>
                            <option value="scsi" {if $plan->disktype == "scsi"}selected{/if}>SCSI</option>
                            <option value="sata" {if $plan->disktype == "sata"}selected{/if}>SATA</option>
                            <option value="ide" {if $plan->disktype == "ide"}selected{/if}>IDE</option>
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
                        <input type="text" size="8" name="storage" id="storage" class="pxInput pxInput_small" value="{$plan->storage}" required>
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
                        <input type="text" size="8" name="os_storage" id="os_storage" class="pxInput pxInput_small" value="{$plan->os_storage}" required>
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
                        <input type="number" name="diskio" id="diskio" class="pxInput pxInput_small" value="{$plan->diskio}" required>
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
                            <option value="e1000" {if $plan->netmodel == "e1000"}selected{/if}>Intel E1000 (Reliable)</option>
                            <option value="virtio" {if $plan->netmodel == "virtio"}selected{/if}>VirtIO (Paravirt)</option>
                            <option value="rtl8139" {if $plan->netmodel == "rtl8139"}selected{/if}>Realtek RTL8139</option>
                            <option value="vmxnet3" {if $plan->netmodel == "vmxnet3"}selected{/if}>VMware vmxnet3</option>
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
                        <input type="number" name="netrate" id="netrate" class="pxInput pxInput_small" value="{$plan->netrate}">
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
                        <input type="number" name="bw" id="bw" class="pxInput pxInput_small" value="{$plan->bw}">
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
                            <option value="0" {if $plan->ipv6 == "0"}selected{/if}>Off</option>
                            <option value="auto" {if $plan->ipv6 == "auto"}selected{/if}>SLAAC</option>
                            <option value="dhcp" {if $plan->ipv6 == "dhcp"}selected{/if}>DHCPv6</option>
                            <option value="prefix" {if $plan->ipv6 == "prefix"}selected{/if}>Prefix</option>
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
                            <option value="bridge" {if $plan->netmode == "bridge"}selected{/if}>Bridge</option>
                            <option value="nat" {if $plan->netmode == "nat"}selected{/if}>NAT</option>
                            <option value="none" {if $plan->netmode == "none"}selected{/if}>No network</option>
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
                        <input type="text" size="8" name="bridge" id="bridge" class="pxInput pxInput_small" value="{$plan->bridge}">
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
                        <input type="number" name="vmbr" id="vmbr" class="pxInput pxInput_small" value="{$plan->vmbr}">
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
                        <input type="number" name="vlanid" id="vlanid" class="pxInput pxInput_small" value="{$plan->vlanid}">
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
                    <span class="pxToggle {if $plan->kvm == "1"}pxToggle_on{else}pxToggle_off{/if}" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                        <span data-role="status"></span>
                        <input type="hidden" name="kvm" value="{$plan->kvm}" checked>
                    </span>
                <span class="pxFieldRow_desc">Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)</span>
            </div>
        </li>
        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                On-boot VM?
            </div>
            <div class="pxFieldContent">
                    <span class="pxToggle {if $plan->onboot == "1"}pxToggle_on{else}pxToggle_off{/if}" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                        <span data-role="status"></span>
                        <input type="hidden" name="onboot" value="{$plan->onboot}" checked>
                    </span>
                <span class="pxFieldRow_desc">Specifies whether a VM will be started during hypervisor boot-up. (Recommended)</span>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <input type="submit" class="pxButton pxButton_primary" value="Update Plan" class="pxButton">
            </div>
        </li>
    </ul>
</form>
{/block}