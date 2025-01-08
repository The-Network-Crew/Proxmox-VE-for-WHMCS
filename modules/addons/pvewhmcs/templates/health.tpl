{extends file='layout.tpl'}
{block name='title'}Support / Health{/block}
{block name='content'}
<div class="px-widget__list px-spacing-bottom">
    <div class="px-widgetList__row">
        <p><strong><h2>System Environment</h2></strong></p>
        <p>
            <b>Proxmox VE for WHMCS</b> v{$pvewhmcs_version}
                                        (GitHub reports latest as <b>v{$get_pvewhmcs_latest_version}</b>)
        </p>
        <p>
            <b>PHP</b> v{phpversion()} running on
            <b>{$smarty.server.SERVER_SOFTWARE}</b> Web Server
                       ({$smarty.server.SERVER_NAME})
        </p>
        <p><strong><h2>Updates & Codebase</h2></strong></p>
        <p>
            <b>Proxmox for WHMCS is open-source and free to use & improve on! ❤️</b>
        </p>
        <p>
            Repo:
            <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank">
                https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/
            </a>
        </p>
        <p><strong><h2>Product & Reviewing</h2></strong></p>
        <p>
            <b style="color:darkgreen;">Your 5-star review on WHMCS Marketplace will help the module grow!</b>
        </p>
        <p>
            *****:
            <a href="https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs" target="_blank">
                https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs
            </a>
        </p>
        <p><strong><h2>Issues: Common Causes</h2></strong></p>
        <p>
            1. <b>WHMCS needs to have &gt;100 Services, else it is an illegal Proxmox VMID.</b>
        </p>
        <p>
            2. Save your Package (Plan/Pool)! (configproducts.php?action=edit&id=...#tab=3)
        </p>
        <p>
            3. Where possible, we pass-through the exact error to WHMCS Admin. Check it for info!
        </p>
        <p><strong><h2>Module Technical Support</h2></strong></p>
        <p>
            Please raise an
            <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/new" target="_blank">
                <u>Issue</u>
            </a> on GitHub - include logs, steps to reproduce, etc.
        </p>
        <p>
            Help is not guaranteed (FOSS). We will need your assistance. <b>Thank you.</b>
        </p>
    </div>
</div>

{/block}