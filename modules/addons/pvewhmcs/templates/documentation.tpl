{extends file='layout.tpl'}

{block name='content'}
<div class="px-widget__list px-spacing-bottom">
    <div class="px-widgetList__row">
        <p>The cron job for Proxmox :</p>
        <pre>php -q cron.php</pre>
    </div>
</div>
<div class="px-widget px-widget__horizontal px-spacing-bottom">
    <h3 class="px-widget__title">General</h3>
    <div class="px-widget__inner">
        <form action="">
            <div class="pxForm_group">
                <label for="test" class="pxLabel">Minimum VMID</label>
                <input type="text" name="test" class="pxInput">
            </div>
            <div class="pxForm_group">
                <label for="test" class="pxLabel">Minimum VMID</label>
                <input type="text" name="test" class="pxInput">
            </div>
        </form>
    </div>
</div>
<form action="">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small">
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Test <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small">
                    </li>
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small">
                    </li>
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Lorem ipsum dolor sit amet consectetur adipisicing elit. Eum, unde.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_verySmall">
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Test with Input Numbers <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="number" class="pxInput pxInput_verySmall">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Commodi assumenda ullam, quae dolorum aliquam magni debitis id non, veniam obcaecati cumque laboriosam dicta omnis consequatur itaque eveniet minus quod nulla porro veritatis numquam quis! Molestias sit, nulla id ea quos ut non impedit eligendi nihil, facilis placeat quo fugiat eum!</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_verySmall">
                    </li>
                </ul>
                <span class="pxFieldRow_warning"><i class="fa-solid fa-triangle-exclamation"></i> Lorem ipsum dolor sit amet consectetur, adipisicing elit. Inventore dolor excepturi maxime laboriosam, a facilis?</span>
                <span class="pxFieldRow_danger"><i class="fa-solid fa-triangle-exclamation"></i> Lorem ipsum dolor sit amet consectetur, adipisicing elit. Inventore dolor excepturi maxime laboriosam, a facilis?</span>
                <span class="pxFieldRow_success"><i class="fa-solid fa-triangle-exclamation"></i> Lorem ipsum dolor sit amet consectetur, adipisicing elit. Inventore dolor excepturi maxime laboriosam, a facilis?</span>
                <span class="pxFieldRow_normal"><i class="fa-solid fa-triangle-exclamation"></i> Lorem ipsum dolor sit amet consectetur, adipisicing elit. Inventore dolor excepturi maxime laboriosam, a facilis?</span>
            </div>
        </li>

        <li class="pxFieldRow pxFieldRow_yesNo">
            <div class="pxFieldTitle">
                Yes/No <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                    <span class="pxToggle pxToggle_off" id="toggleinput" tabindex="0" role="switch" aria-checked="true">
                        <span data-role="status"></span>
	                    <input type="hidden" name="nameOfInput" value="1"/>
                    </span>
                <span class="pxFieldRow_desc">Lorem ipsum dolor sit amet consectetur adipisicing elit. Doloribus, expedita.</span>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Select Box <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <select name="" id="" class="pxInput pxInput_small">
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                        </select>
                        <select name="" id="" class="pxInput pxInput_verySmall">
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                        </select>
                    </li>
                    <li class="pxFieldInput">
                        <select name="" id="" class="pxInput pxInput_verySmall">
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                            <option value="Any Value">Any Value</option>
                        </select>
                    </li>
                </ul>
            </div>
        </li>
    </ul>
</form>

{/block}