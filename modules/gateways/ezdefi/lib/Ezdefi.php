<?php

namespace WHMCS\Module\Gateway\Ezdefi;

class Ezdefi {
	private static $instance = null;

	protected $config;

	protected $api;

	public function __construct()
	{
		$this->db = new EzdefiDb();
		$this->api = new EzdefiApi();
	}

	public static function instance()
	{
		if(is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init()
	{
		$this->db->createAmountIdTable();
	}

	public function getMetaData()
	{
		return array(
			'DisplayName' => 'Ezdefi',
			'APIVersion' => '1.1'
		);
	}

	public function getConfig()
	{
		return array(
			'FriendlyName' => array(
				'Type' => 'System',
				'Value' => 'ezDefi',
			),
			'apiUrl' => array(
				'FriendlyName' => 'Gateway API Url',
				'Type' => 'text',
				'Default' => '',
				'Description' => 'Enter your gateway API Url',
			),
			'apiKey' => array(
				'FriendlyName' => 'Gateway API Key',
				'Type' => 'text',
				'Default' => '',
				'Description' => 'Enter your gateway API Key',
			),
			'simpleMethod' => array(
				'FriendlyName' => 'Simple method',
				'Type' => 'yesno',
				'Description' => 'Allow client to pay without using ezDefi wallet',
			),
			'ezdefiWallet' => array(
				'FriendlyName' => 'ezDefi Wallet',
				'Type' => 'yesno',
				'Description' => 'Allow client to pay using ezDefi wallet',
			),
			'variation' => array(
				'FriendlyName' => 'Acceptable Variation',
				'Type' => 'text',
				'Default' => '0.01',
				'Description' => 'Description',
			),
			'decimal' => array(
				'FriendlyName' => 'Decimals',
				'Type' => 'text',
				'Default' => '6',
				'Description' => 'Description',
			),
			'cronRecurrence' => array(
				'FriendlyName' => 'Clear AmountId Recurrence',
				'Type' => 'dropdown',
				'Options' => array(
					'daily' => 'Daily',
					'weekly' => 'Once a week',
					'monthly' => 'Monthly',
				),
				'Description' => 'Description',
			),
		);
	}

	public function getLink(array $params)
	{
		$systemUrl = $this->db->getSystemUrl();
		$formUrl = $systemUrl . 'ezdefipayment.php';

		$form = '<form action="'.$formUrl.'" method="POST">';
		$form .= '<input type="hidden" name="amount" value="'. $params['amount'] .'"/>';
		$form .= '<input type="hidden" name="currency" value="'. $params['currency'] .'"/>';
		$form .= '<input type="hidden" name="uoid" value="'. $params['invoiceid'] .'"/>';
		$form .= '<input type="submit" value="'. $params['langpaynow'] .'"/>';
		$form .= '</form>';

		return $form;
	}

	public function getAdminFooterOutput($gatewayParams)
	{
		$systemUrl = $this->db->getSystemUrl();
		$ezdefiConfigUrl = $systemUrl . 'ezdefiajax.php';
		$data = array(
			'gateway_params' => $gatewayParams,
			'config_url' => $ezdefiConfigUrl
		);
		ob_start(); ?>
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/select2.min.css'; ?>">
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/ezdefi-admin.css'; ?>">
		<script type="application/json" id="ezdefi-data"><?php echo json_encode( $data ); ?></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/select2.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.validate.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.blockUI.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery-ui.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/ezdefi-admin.js'; ?>"></script>
		<?php return ob_get_clean();
	}

	public function createEzdefiPayment($invoiceId, $symbol, $method)
    {
        $invoice = $this->db->getInvoice($invoiceId);

        $order_data = array(
	        'amount' => $invoice->total,
            'uoid' => $invoiceId,
            'currency' => $this->db->getDefaultCurrency()
        );

        $currency_data = $this->db->getCurrencyBySymbol($symbol);

        $amount_id = ($method === 'amount_id') ? true : false;

        $payment = $this->api->createPayment($order_data, $currency_data, $amount_id);

        return $this->renderPaymentHtml($payment);
    }

    public function renderPaymentHtml($payment)
    {
	    ob_start(); ?>
        <div class="ezdefi-payment">
            <?php $value = $payment['value'] / pow( 10, $payment['decimal'] ); ?>
            <p class="exchange">
                <span><?php echo $payment['originCurrency']; ?> <?php echo $payment['originValue']; ?></span>
                <img width="16" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAQAAAAAYLlVAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAAHdElNRQfjChgQMyxZjA7+AAACP0lEQVRo3u2YvWsUQRTAf8nFQs5LCEY0aCGIB1ErRVMoFpYGTGNlo2AnBxHlrLQJKVSwiV//gqCV4gemEGJhiBYXRAtBDIhICiUGL8GP3Fjs7rs5vN0o5M1LsW+a2XkDv9/MvF12t4B2dDDODqbVOan46zgaVKzwN3A4O4VuarGAo8EZC4VeXnoKJruQK+QKa12hI2VyFyUFhY08Ymfcd1S49feU7VSZ5DPL4qrXGpxuhW/iJj8DgJutTrGJ38vHoPCobUnwg9QN8HeTItzGNP2yF7M85D11lTvhLAPSn2CYpah7R5zmOUmnChrgsrf6p6xPhvfRiAe/slsNnoqHcRketsDDbDw8ZYPvlsR5CzwMSGpICT+WhYdBSR4Ov3p9gbGV8Hr3PEAPx6XvPXZC7sBm3qSvPoRApJCB71KB+jHHERbab34YAZjLSuoW4T+EuYBNHJXC32W+A2taYAN9lgJFHjDZfGsNHUWe4XC8VVHwirD9hBLPZcpM+mN0NQTaHUGR+xySq3vpj1Gd8FfvuKjCyDiC5OyjdklpkSeE0N+aCLF6gNGY8IuCBb4zfklxzFjg4ZRQRi3wB/guB1AOjV9HhUXh3Ibo87zEYw7KpFqUWPUoUWaIrXL9gf18iRSeGPyamGdPYlI2wL/zflPQx4+g8CWu0tN6OiNBwL/5xAQjXhWQFCFc4IqMvOYY3xSKcIHlrPQ5z/UVvSr3wQqRK+QKuYIfVU9hSuGt+L924ZoFvqmgji+kZl6wSI2qtsAfm/EoPAbFFD0AAAAldEVYdGRhdGU6Y3JlYXRlADIwMTktMTAtMjRUMTY6NTE6NDQrMDA6MDBiAik3AAAAJXRFWHRkYXRlOm1vZGlmeQAyMDE5LTEwLTI0VDE2OjUxOjQ0KzAwOjAwE1+RiwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAAASUVORK5CYII=" />
                <span class="currency"><?php echo $value . ' ' . $payment['currency']; ?></span>
            </p>
            <p>You have <span class="count-down" data-endtime="<?php echo $payment['expiredTime']; ?>"></span> to scan this QR Code</p>
            <p>
                <a href="<?php echo $payment['deepLink']; ?>">
                    <img class="qrcode" src="<?php echo $payment['qr']; ?>" />
                </a>
            </p>
            <?php if( $payment['amountId'] === true ) : ?>
                <p>
                    <strong>Address:</strong> <?php echo $payment['to']; ?><br/>
                    <strong>Amount:</strong> <?php echo $value; ?> <span class="currency"><?php echo $payment['currency']; ?></span><br/>
                </p>
                <p>You have to pay an exact amount so that you payment can be recognized.</p>
            <?php else : ?>
                <p>
                    <a href="">Download ezDeFi for IOS</a><br />
                    <a href="">Download ezDeFi for Android</a>
                </p>
            <?php endif; ?>
        </div>
	    <?php return ob_get_clean();
    }
}