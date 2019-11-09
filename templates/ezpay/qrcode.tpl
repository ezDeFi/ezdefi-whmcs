<link rel="stylesheet" href="{$BASE_PATH_CSS}/ezpay-qrcode.css">
<div id="whmcs_ezpay_qrcode">
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
    <div class="ezpay-payment">
        <div class="loading"></div>
    </div>
    <button class="submitBtn">Confirm</button>
</div>
<script src="{$BASE_PATH_JS}/jquery.blockUI.js"></script>
<script src="{$BASE_PATH_JS}/ezpay-qrcode.js"></script>