{extends file='layout.tpl'}
{block name='title'}List IP{/block}
{block name='content'}
<div id="serverslist" class="dataTable">

    <div class="dataTable-top__search">
        <div id="pxSearch">
            <form action="">
                <button class="pxSearchSubmit" type="submit" aria-label="Search">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.5 17.5L13.875 13.875M15.8333 9.16667C15.8333 12.8486 12.8486 15.8333 9.16667 15.8333C5.48477 15.8333 2.5 12.8486 2.5 9.16667C2.5 5.48477 5.48477 2.5 9.16667 2.5C12.8486 2.5 15.8333 5.48477 15.8333 9.16667Z"
                              stroke="#939cb1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
                <input type="search" id="pxSearchField" placeholder="Search..." name="q" autocomplete="off"
                       aria-label="Search">
            </form>
        </div>
    </div>

    <div class="dataTables">
        <div class="px-table-container">
            <table width="100%" role="grid" class="px-table">
                <thead>
                <tr role="row">
                    <th name="ipaddress">IP Address</th>
                    <th name="subnetmask">Subnet Mask</th>
                    <th name="actions"></th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$ip_addresses item=ip}
                <tr actionid="{$ip->id|escape}" role="row">
                    <td name="ipaddress">{$ip->ipaddress}</td>
                    <td>{$ip->mask}</td>
                    <td name="actions">
                        <div class="dataTable_actions">
                            {if $ip->in_use}
                            is in use
                            {else}
                            <a href="{$modulelink}&amp;tab=ippools&amp;action=removeip&amp;pool_id={$ip->pool_id}&amp;id={$ip->id}"
                               onclick="return confirm('IP Address will be deleted from the pool, continue?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                            {/if}
                        </div>
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
        <div class="dataTables__footer">
            <!--<div class="dataTable_paginate">
                <a href="" data-table-page="prev" class="paginate-button">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <span><a class="paginate-button paginate-current">1</a></span>
                <a href="" data-table-page="next" class="paginate-button">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            <div class="dataTable_dt">
                <a href="" class="dataTable-dt__button is-active">10</a>
                <a href="" class="dataTable-dt__button">25</a>
                <a href="" class="dataTable-dt__button">All</a>
            </div>-->
        </div>
    </div>
</div>
{/block}