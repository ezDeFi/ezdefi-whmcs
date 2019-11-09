<?php

if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

use WHMCS\Module\Gateway\Ezpay\EzpayConfig;
use WHMCS\Module\Gateway\Ezpay\EzpayApi;


function ezpay_MetaData() {
	return array(
		'DisplayName' => 'Ezpay',
		'APIVersion' => '1.1'
	);
}

function ezpay_config() {
	return array(
		'FriendlyName' => array(
			'Type' => 'System',
			'Value' => 'EZPay',
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
		)
	);
}

function ezpay_link($params) {
    if(!isset($params) || empty($params)) {
        return;
    }

	$config = new EzpayConfig();
	$systemUrl = $config->getSystemUrl();
	$formUrl = $systemUrl . 'ezpaypayment.php';

    $form = '<form action="'.$formUrl.'" method="POST">';
    $form .= '<input type="hidden" name="amount" value="'. $params['amount'] .'"/>';
	$form .= '<input type="hidden" name="currency" value="'. $params['currency'] .'"/>';
	$form .= '<input type="hidden" name="uoid" value="'. $params['invoiceid'] .'"/>';
	$form .= '<input type="submit" value="'. $params['langpaynow'] .'"/>';
	$form .= '</form>';

	return $form;
}

add_hook('AdminAreaFooterOutput', 1, function($vars) {
    try {
		$gatewayModuleName = basename(__FILE__, '.php');
		$gatewayParams = getGatewayVariables($gatewayModuleName);
		if(isset($gatewayParams['token']) && ! empty($gatewayParams['token'])) {
			$gatewayParams['token'] = unserialize(base64_decode($gatewayParams['token']));
        }
	} catch (Exception $e) {
		return;
	}

	$config = new EzpayConfig();
	$systemUrl = $config->getSystemUrl();
	$ezpayConfigUrl = $systemUrl . 'ezpayajax.php';
	$data = array(
		'gateway_params' => $gatewayParams,
		'config_url' => $ezpayConfigUrl
	);
	ob_start(); ?>
        <link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezpay/css/select2.min.css'; ?>">
        <link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezpay/css/ezpay-admin.css'; ?>">
		<script type="application/json" id="ezpay-data"><?php echo json_encode( $data ); ?></script>
        <script src="<?php echo $systemUrl . '/modules/gateways/ezpay/js/select2.min.js'; ?>"></script>
        <script src="<?php echo $systemUrl . '/modules/gateways/ezpay/js/jquery.validate.min.js'; ?>"></script>
        <script src="<?php echo $systemUrl . '/modules/gateways/ezpay/js/jquery.blockUI.js'; ?>"></script>
        <script src="<?php echo $systemUrl . '/modules/gateways/ezpay/js/jquery-ui.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezpay/js/ezpay-admin.js'; ?>"></script>
	<?php return ob_get_clean();
});