{extends file='layout.tpl'}
{block name='title'}Plan LXC Edit{/block}
{block name='content'}
<form action="" method="post">
    <ul class="pxForm">
        <!-- Plan Title -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Plan Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" name="title" id="title" class="pxInput pxInput_small" required
                               value="{$plan->title}">
                    </li>
                </ul>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                CPU - Cores <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cores" id="cores" class="pxInput pxInput_small" min="1" max="256" value="{$plan->cores}" required>
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Limit of CPU Cores usage. Default is 1. Value "0" indicates unlimited cores.</span>
            </div>
        </li>
        <!-- CPU Limit -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                CPU - Limit <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cpulimit" id="cpulimit" class="pxInput pxInput_small" required
                               value="{$plan->cpulimit}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Limit of CPU usage. Default is 1. Value "0" indicates no CPU limit.</span>
            </div>
        </li>

        <!-- CPU Weighting -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                CPU - Weighting <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="cpuunits" id="cpuunits" class="pxInput pxInput_small" required
                               value="{$plan->cpuunits}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Number relative to weights of all other running VMs. Recommended 1024.</span>
            </div>
        </li>

        <!-- RAM Memory -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                RAM - Memory <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="memory" id="memory" class="pxInput pxInput_small" required
                               value="{$plan->memory}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">RAM space in Megabytes, e.g., 1024 = 1GB.</span>
            </div>
        </li>

        <!-- Swap Space -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Swap - Space
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="swap" id="swap" class="pxInput pxInput_small"
                               value="{$plan->swap}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Swap space in Megabytes, e.g., 1024 = 1GB.</span>
            </div>
        </li>

        <!-- SSD/HDD Disk -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                SSD/HDD - Disk <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="disk" id="disk" class="pxInput pxInput_small" required
                               value="{$plan->disk}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Disk space in Gigabytes, e.g., 1024 = 1TB.</span>
            </div>
        </li>

        <!-- PVE Store Name -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                PVE Store - Name <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" name="storage" id="storage" class="pxInput pxInput_small" required
                               value="{$plan->storage}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Name of VM/CT Storage on Proxmox VE hypervisor (e.g., local).</span>
            </div>
        </li>

        <!-- PVE Template Store -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                PVE Template Store <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" name="os_storage" id="os_storage" class="pxInput pxInput_small" required
                               value="{$plan->os_storage}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Location of VM/CT Template Storage on Proxmox VE hypervisor.</span>
            </div>
        </li>

        <!-- I/O Throttling -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                I/O - Throttling <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="diskio" id="diskio" class="pxInput pxInput_small" required
                               value="{$plan->diskio}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Limit of Disk I/O in KiB/s. 0 for unrestricted access.</span>
            </div>
        </li>

        <!-- Bridge Interface -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Bridge - Interface
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" name="bridge" id="bridge" class="pxInput pxInput_small"
                               value="{$plan->bridge}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Proxmox default bridge name is "vmbr".</span>
            </div>
        </li>

        <!-- Bridge Interface ID -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Bridge - Int. ID
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="vmbr" id="vmbr" class="pxInput pxInput_small"
                               value="{$plan->vmbr}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Proxmox default bridge (vmbr) number is 0 (vmbr0).</span>
            </div>
        </li>

        <!-- Trunk VLAN ID -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Trunk - VLAN ID
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="vlanid" id="vlanid" class="pxInput pxInput_small"
                               value="{$plan->vlanid}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">VLAN ID for Plan Services. Leave blank for untagged.</span>
            </div>
        </li>

        <!-- Network Rate -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Network - Rate
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" name="netrate" id="netrate" class="pxInput pxInput_small"
                               value="{$plan->netrate}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Network Rate Limit in Megabit/Second. Blank means unlimited.</span>
            </div>
        </li>
        <!-- Network IPv6 Configuration -->
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Network - IPv6 Conf.
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select name="ipv6" class="pxInput pxInput_small">
                            <option value="0" {if $plan->ipv6 == "0"}selected{/if}>Off</option>
                            <option value="auto" {if $plan->ipv6 == "auto"}selected{/if}>SLAAC</option>
                            <option value="dhcp" {if $plan->ipv6 == "dhcp"}selected{/if}>DHCPv6</option>
                            <option value="prefix" {if $plan->ipv6 == "prefix"}selected{/if}>Prefix</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>

        <!-- On-boot CT -->
        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                On-boot CT?
            </div>
            <div class="pxFieldContent">
                <span class="pxToggle {if $plan->onboot == 1}pxToggle_on{else}pxToggle_off{/if}" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                <span data-role="status"></span>
                <input type="hidden" name="onboot" value="{$plan->onboot}" checked>
                </span>
                <span class="pxFieldRow_desc">Specifies whether a CT will be started during hypervisor boot-up. (Recommended)</span>
            </div>
        </li>

        <!-- Submit Button -->
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <button type="submit" class="pxButton pxButton_primary">Update Plan</button>
            </div>
        </li>
    </ul>
</form>
{/block}