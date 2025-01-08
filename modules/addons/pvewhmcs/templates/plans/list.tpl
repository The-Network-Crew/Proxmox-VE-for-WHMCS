{extends file='layout.tpl'}
{block name='title'}Plan List LXC/KVM{/block}
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
                    <th name="id">ID</th>
                    <th name="name" aria-sort="none" class="sorting_asc">Name</th>
                    <th name="vmtype">VM Type</th>
                    <th name="ostype">OS Type</th>
                    <th name="cpus">CPUs</th>
                    <th name="cores">Cores</th>
                    <th name="ram">RAM</th>
                    <th name="balloon">Balloon</th>
                    <th name="swap">Swap</th>
                    <th name="disk">Disk</th>
                    <th name="disktype">Disk Type</th>
                    <th name="pvestore">PVE Store</th>
                    <th name="pveosstorage">PVE OS Storage</th>
                    <th name="iocap">I/O Cap</th>
                    <th name="netmode">Net Mode</th>
                    <th name="bridge">Bridge</th>
                    <th name="nicmodel">NIC Model</th>
                    <th name="vlanid">VLAN ID</th>
                    <th name="rate">Rate</th>
                    <th name="bw">BW</th>
                    <th name="ipv6">IPv6</th>
                    <th name="actions"></th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$vm_plans item=vm}
                <tr actionid="{$vm->id|escape}" role="row">
                    <td name="id">{$vm->id|escape}</td>
                    <td>{$vm->title|escape}</td>
                    <td>{$vm->vmtype|escape}</td>
                    <td>{$vm->ostype|escape}</td>
                    <td>{$vm->cpus|escape}</td>
                    <td>{$vm->cores|escape}</td>
                    <td>{$vm->memory|escape}</td>
                    <td>{$vm->balloon|escape}</td>
                    <td>{$vm->swap|escape}</td>
                    <td>{$vm->disk|escape}</td>
                    <td>{$vm->disktype|escape}</td>
                    <td>{$vm->storage|escape}</td>
                    <td>{$vm->os_storage|escape}</td>
                    <td>{$vm->diskio|escape}</td>
                    <td>{$vm->netmode|escape}</td>
                    <td>{$vm->bridge|escape}{$vm->vmbr|escape}</td>
                    <td>{$vm->netmodel|escape}</td>
                    <td>{$vm->vlanid|escape}</td>
                    <td>{$vm->netrate|escape}</td>
                    <td>{$vm->bw|escape}</td>
                    <td>{$vm->ipv6|escape}</td>
                    <td>
                        <div class="dataTable_actions">
                            <a href="{$modulelink|escape:'html'}&amp;tab=vmplans&amp;action=editplan&amp;id={$vm->id}&amp;vmtype={$vm->vmtype}">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                            <a href="{$modulelink|escape:'html'}&amp;tab=vmplans&amp;action=removePlan&amp;id={$vm->id}"
                               onclick="return confirm('Plan will be deleted, continue?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
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