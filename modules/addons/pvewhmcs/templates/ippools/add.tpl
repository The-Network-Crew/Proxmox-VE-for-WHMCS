{extends file='layout.tpl'}
{block name='title'}Add Pool IP{/block}
{block name='content'}
<form method="post">
    <ul class="pxForm">
        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Pool Title <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small" name="title" required>
                    </li>
                </ul>
            </div>
        </li>

        <li class="pxFieldRow">
            <div class="pxFieldTitle">
                Gateway <span class="pxFieldRow_required noText">Required</span>
            </div>
            <div class="pxFieldContent">
                <ul class="pxFieldInputs">
                    <li class="pxFieldInput">
                        <input type="text" class="pxInput pxInput_small" name="gateway">
                    </li>
                </ul>
                <span class="pxFieldRow_desc">Gateway address of the pool</span>
            </div>
        </li>
        <li class="pxFieldRow">
            <div class="pxField_submit">
                <input type="submit" class="pxButton pxButton_primary" name="newIPpool" value="Save"/>
            </div>
        </li>
    </ul>
</form>
{/block}