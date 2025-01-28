{extends file='layout.tpl'}
{block name='title'}Servers{/block}
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
                    <th name="ipaddress">Name</th>
                    <th name="subnetmask">IP Address</th>
                    <th name="subnetmask">Active Accounts</th>
                    <th name="subnetmask">RAM Assigned / Total / Free</th>
                    <th name="subnetmask">Suspended</th>
                    <th name="subnetmask">Status</th>
                    <th name="subnetmask">VM Range</th>
                    <th name="actions"></th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$servers item=server}
                <tr actionid="{$server->id|escape}" role="row">
                    <td>{$server->name|escape}</td>
                    <td>{$server->ipaddress|escape}</td>
                    <td>{$server->activeClients} / {$server->maxaccounts}</td>
                    <td>0 B / 0 B / 0 B</td>
                    <td>0 B / 0 B</td>
                    <td>{if $server->disabled == 0}
                        <span class="px-label px-label-success">
										<i class="fa-regular fa-circle-check"></i> Enabled
									</span>
                        {else}
                        <span class="px-label px-label-danger">
										<i class="fa-solid fa-ban"></i> Disabled
									</span>
                        {/if}
                    </td>
                    <td>-</td>
                    <td name="actions">
                        <div class="dataTable_actions">
                            <a href="{\WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl()}/configservers.php?action=manage&id={$server->id}">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
        <div class="dataTables__footer">
            <div class="dataTable_paginate">
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
            </div>
        </div>
    </div>
</div>
{/block}