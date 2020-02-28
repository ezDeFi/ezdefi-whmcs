<link rel="stylesheet" href="{$BASE_PATH_CSS}/ezdefi-qrcode.css">
<div id="whmcs_ezdefi_qrcode">
    <script type="application/json" id="order-data">{$order_data}</script>
    <script type="application/json" id="url-data">{$url_data}</script>
    <div class="currency-select">
        {foreach $coins as $i => $c}
            <div class="currency-item__wrap">
                <div class="currency-item {($i eq 0) ? 'selected' : ''}" data-id="{$c['_id']}" data-symbol="{$c['token']['symbol']}">
                    <script type="application/json">
                        {$c['json_data']|@json_encode nofilter}
                    </script>
                    <div class="item__logo">
                        <img src="{$c['token']['logo']}" alt="">
                        {if $c['token']['desc'] neq '' }
                            <div class="item__desc">{$c['token']['desc']}</div>
                        {/if}
                    </div>
                    <div class="item__text">
                        <div class="item__price">
                            {$c['price']}
                        </div>
                        <div class="item__info">
                            <div class="item__symbol">
                                {$c['token']['symbol']}
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
    <div class="ezdefi-payment-tabs" style="display: none">
        <ul class="ezdefi-tabs-nav">
            {if $website_config['website']['payAnyWallet']}
                <li>
                    <a class="ezdefi-tabs-link" href="#amount_id" id="tab-amount_id">
                        <span class="large-screen">Pay with any crypto wallet</span>
                        <span class="small-screen">Any crypto wallet</span>
                    </a>
                </li>
            {/if}
            {if $website_config['website']['payEzdefiWallet']}
                <li>
                    <a class="ezdefi-tabs-link" href="#ezdefi_wallet" id="tab-ezdefi_wallet" style="background-image: url({$WEB_ROOT}/assets/img/ezdefi-icon.png)">
                        <span class="large-screen">Pay with ezDeFi wallet</span>
                        <span class="small-screen" style="background-image: url({$WEB_ROOT}/assets/img/ezdefi-icon.png)">ezDeFi wallet</span>
                    </a>
                </li>
            {/if}
        </ul>
        {if $website_config['website']['payAnyWallet']}
            <div id="amount_id" class="ezdefi-payment-panel"></div>
        {/if}
        {if $website_config['website']['payEzdefiWallet']}
            <div id="ezdefi_wallet" class="ezdefi-payment-panel"></div>
        {/if}
    </div>
</div>
<script src="{$BASE_PATH_JS}/clipboard.min.js"></script>
<script src="{$BASE_PATH_JS}/ezdefi-qrcode.js"></script>