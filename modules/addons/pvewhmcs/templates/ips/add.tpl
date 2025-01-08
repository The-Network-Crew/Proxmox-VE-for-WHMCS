{extends file='layout.tpl'}
{block name='title'}Add IP{/block}
{block name='content'}
<form method="post" action="">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                IP Pool <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select id="" class="pxInput pxInput_small" name="pool_id">
                            {foreach from=$ip_pools item=pool}
                            <option value="{$pool->id}">{$pool->title}</option>
                            {/foreach}
                        </select>
                    </li>
                </ul>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                IP Block <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small" name="ipblock">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">IP Block with CIDR e.g. 172.16.255.230/27, for single IP address just don't use CIDR.</span>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <input type="submit" class="pxButton pxButton_primary" name="assignIP2pool" value="Save"/>
            </div>
        </li>
    </ul>
</form>
{/block}