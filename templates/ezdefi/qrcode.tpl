<link rel="stylesheet" href="{$BASE_PATH_CSS}/ezdefi-qrcode.css">
<div id="whmcs_ezdefi_qrcode">
    <script type="application/json" id="order-data">{$order_data}</script>
    <script type="application/json" id="url-data">{$url_data}</script>
    <div class="currency-select">
        {foreach $currency as $i => $c}
            <div class="currency-item__wrap">
                <div class="currency-item {($i eq 0) ? 'selected' : ''}" data-symbol="{$c['symbol']}" >
                    <div class="item__logo">
                        <img src="{$c['logo']}" alt="">
                        {if $c['desc'] neq '' }
                            <div class="item__desc">{$c['desc']}</div>
                        {/if}
                    </div>
                    <div class="item__text">
                        <div class="item__price">
                            {$c['price']}
                        </div>
                        <div class="item__info">
                            <div class="item__symbol">
                                {$c['symbol']}
                            </div>
                            <div class="item__discount">
                                - {( intval($c['discount']) > 0) ? $c['discount'] : 0}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/foreach}
    </div>
    <div class="whmcs-ezdefi-loader"></div>
    <div class="ezdefi-payment-tabs">
        <ul>
            {foreach $payment_method as $method}
                <li>
                    {if $method === 'amount_id'}
                        <a href="#{$method}" id="tab-{$method}">
                            <span class="large-screen">Pay with any crypto wallet</span>
                            <span class="small-screen">Any crypto wallet</span>
                        </a>
                    {elseif $method === 'ezdefi_wallet'}
                        <a href="#{$method}" id="tab-{$method}" style="background-image: url({$WEB_ROOT}/assets/img/ezdefi-icon.png)">
                            <span class="large-screen">Pay with ezDeFi wallet</span>
                            <span class="small-screen" style="background-image: url({$WEB_ROOT}/assets/img/ezdefi-icon.png)">ezDeFi wallet</span>
                        </a>
                    {/if}
                </li>
            {/foreach}
        </ul>
        {foreach $payment_method as $method}
            <div id="{$method}" class="ezdefi-payment-panel"></div>
        {/foreach}
    </div>
</div>
<script src="{$BASE_PATH_JS}/clipboard.min.js"></script>
<script src="{$BASE_PATH_JS}/ezdefi-lib.js"></script>
<script src="{$BASE_PATH_JS}/ezdefi-qrcode.js"></script>