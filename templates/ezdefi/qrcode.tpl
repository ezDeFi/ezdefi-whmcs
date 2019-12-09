<link rel="stylesheet" href="{$BASE_PATH_CSS}/ezdefi-qrcode.css">
<div id="whmcs_ezdefi_qrcode">
    <script type="application/json" id="order-data">{$order_data}</script>
    <script type="application/json" id="url-data">{$url_data}</script>
    <div class="selected-currency">
        <div class="left">
            <div class="logo">
                <img class="logo" src="{$currency[0]['logo']}" alt="">
            </div>
            <div class="text">
                <span class="symbol">{$currency[0]['symbol']}</span>/<span class="name">{$currency[0]['name']}</span><br/>
                <span class="desc">{$currency[0]['desc']}</span>
            </div>
        </div>
        <div>
            <a href="" class="changeBtn">Change</a>
        </div>
    </div>
    <div class="currency-select">
        {foreach item=c from=$currency}
            <div class="currency-item">
            <input type="radio" name="currency" id="{$c['symbol']}">
            <label for="{$c['symbol']}">
                <div class="left">
                    <img class="logo" src="{$c['logo']}" alt="">
                    <span class="symbol">{$c['symbol']}</span>
                </div>
                <div class="right">
                    <span class="name">{$c['name']}</span>
                    <span class="discount">Discount: {if intval($c['discount']) gt 0}{$c['discount']}{else}0{/if}%</span>
                    <span class="more">
                        {if isset($c['desc']) and $c['desc'] ne ''}
                        <span class="tooltip desc">{$c['desc']}</span>
                        {/if}
                    </span>
                </div>
            </label>
        </div>
        {/foreach}
    </div>
    <div class="ezdefi-payment-tabs">
        <ul>
            {foreach $payment_method as $method}
                <li>
                    {if $method === 'amount_id'}
                        <a href="#{$method}" id="tab-{$method}">
                            <span>Simple method</span>
                        </a>
                    {elseif $method === 'ezdefi_wallet'}
                        <a href="#{$method}" id="tab-{$method}" style="background-image: url({$WEB_ROOT}/assets/img/ezdefi-icon.png)">
                            <span>Pay with ezPay wallet</span>
                        </a>
                    {/if}
                </li>
            {/foreach}
        </ul>
        {foreach $payment_method as $method}
            <div id="{$method}" class="ezdefi-payment-panel"></div>
        {/foreach}
    </div>
    <button class="submitBtn">Confirm</button>
</div>
<script src="{$BASE_PATH_JS}/jquery.blockUI.js"></script>
<script src="{$BASE_PATH_JS}/jquery-ui.min.js"></script>
<script src="{$BASE_PATH_JS}/ezdefi-qrcode.js"></script>