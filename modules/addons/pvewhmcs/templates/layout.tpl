<!-- Include Toastr CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
<link rel="stylesheet" type="text/css" href="/modules/addons/pvewhmcs/templates/css/main.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script type="text/javascript" src="/modules/addons/pvewhmcs/templates/js/custom.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
      rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>

{assign var="currentTAB" value="{$smarty.server.REQUEST_URI}"}

<div id="proxmox-addon">
    <header class="px-header">
        <div class="px-navbar__top">
            <a href="{$modulelink}" id="px-logo__brand">
                <img src="/modules/addons/pvewhmcs/img/logo.png" alt="logo" style="max-width: 150px;">
            </a>
        </div>
        <div class="px-navbar__nav">
            <ul class="px-navbar__list">

                <li class="px-navItem has-dropmenu {if strpos($currentTAB, 'vmplans')}is-active{/if}">
                    <a class='px-navItem__link' href="">
                        <div class="px-navItem__icon"><i class="fa-solid fa-server"></i></div>
                        <span class="px-navItem__text">Servers</span>
                    </a>
                    <ul class="px-navItem__secondary">
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=vmplans&amp;action=index">Servers List</a>
                        </li>
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=vmplans&amp;action=planlist">List VM Plan</a>
                        </li>
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=vmplans&amp;action=addKvmPlan">Add KVM Plan</a>
                        </li>
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=vmplans&amp;action=addLxcPlan">Add LXC Plan</a>
                        </li>
                    </ul>
                </li>

                <li class="px-navItem has-dropmenu {if strpos($currentTAB, 'ippools')}is-active{/if}">
                    <a class='px-navItem__link' href="">
                        <div class="px-navItem__icon"><i class="fa-solid fa-arrow-up-short-wide"></i></div>
                        <span class="px-navItem__text">IP Management</span>
                    </a>
                    <ul class="px-navItem__secondary">
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=ippools&amp;action=listIpPool">List IP Pools</a>
                        </li>
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=ippools&amp;action=addIpToPool">Add IP to Pool</a>
                        </li>
                        <li>
                            <a class="px-navItem__link"
                               href="{$modulelink}&amp;tab=ippools&amp;action=newIpPool">New IP Pool</a>
                        </li>
                    </ul>
                </li>

                <li class="px-navItem {if strpos($currentTAB, 'settings')}is-active{/if}">
                    <a class='px-navItem__link' href="{$modulelink}&amp;tab=settings&amp;action=vncConfig">
                        <div class="px-navItem__icon"><i class="fa-solid fa-gear"></i></div>
                        <span class="px-navItem__text">Settings</span>
                    </a>
                </li>

                <li class="px-navItem {if strpos($currentTAB, 'tasks')}is-active{/if}">
                    <a class='px-navItem__link' href="">
                        <div class="px-navItem__icon"><i class="fa-solid fa-list-check"></i></div>
                        <span class="px-navItem__text">Tasks</span>
                    </a>
                </li>

                <li class="px-navItem {if strpos($currentTAB, 'documentation')}is-active{/if}">
                    <a class='px-navItem__link' href="{$modulelink}&amp;tab=documentation&amp;action=documentation">
                        <div class="px-navItem__icon"><i class="fa-solid fa-folder"></i></div>
                        <span class="px-navItem__text">Documentation</span>
                    </a>
                </li>
            </ul>

        </div>
    </header>


    <div class="px-app__main">
        <div class="px-breadcrumb">
            <h1 class="px-breadcrumb__title">{block name='title'}{/block}</h1>
        </div>
        <div class="px-main-content">
            {block name='content'}{/block}
        </div>
    </div>
</div>

<script>
    {literal}
    function showToast(title, message, type) {
        toastr.options = {
            closeButton: true,
            debug: false,
            newestOnTop: false,
            progressBar: false,
            positionClass: 'toast-top-right',
            preventDuplicates: false,
            onclick: null,
            showDuration: '300',
            hideDuration: '1000',
            timeOut: '3000', // Duration the toast will stay visible
            extendedTimeOut: '1000',
            showEasing: 'swing',
            hideEasing: 'linear',
            showMethod: 'fadeIn',
            hideMethod: 'fadeOut'
        };
        const validTypes = ['success', 'info', 'warning', 'error'];
        if (!validTypes.includes(type)) {
            console.warn(`Invalid toast type: ${type}. Defaulting to 'info'.`);
            type = 'success'; // Default to 'info' if the type is invalid
        }

        // Display the toast notification
        toastr[type](message, title); // type can be 'success', 'info', 'warning', 'error'
    }
    {/literal}
</script>

{if isset($notification)}
    <script>
        showToast('{$notification.title}', '{$notification.message}', '{$notification.type}');
    </script>
{/if}