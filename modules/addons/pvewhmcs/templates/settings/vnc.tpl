{extends file='layout.tpl'}

{block name='content'}
<form method="post" action="">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                VNC Secret <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small" name="vnc_secret"
                               value="{$config->vnc_secret}">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Password of "vnc"@"pve" user. Mandatory for VNC proxying. See the</span>
            </div>
        </li>
        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                Yes/No <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <span class="pxToggle {if $config->debug_mode == "1"}pxToggle_on{else}pxToggle_off{/if}" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                <span data-role="status"></span>
                <input type="hidden" name="debug_mode" value="1"/>
                </span>
                <span class="pxFieldRow_desc">Whether or not you want Debug Logging enabled (WHMCS Module Log for Debugging >> /admin/logs/module-log)</span>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <input type="submit" class="pxButton pxButton_primary" value="Save Changes" name="saveConfig" id="save_config">
            </div>
        </li>
    </ul>
</form>
{/block}