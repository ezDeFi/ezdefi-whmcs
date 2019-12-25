<?php

define('CLIENTAREA', true);
require_once 'init.php';

use WHMCS\ClientArea;
use WHMCS\Module\Gateway\Ezdefi\EzdefiDb;
use WHMCS\Module\Gateway\Ezdefi\EzdefiApi;

$api = new EzdefiApi();
$config = new EzdefiDb();

if(!isset($_POST) || empty($_POST)){
	header("Location: " . $config->getSystemUrl() . 'cart.php');
	die();
}

$ca = new ClientArea();

$ca->setPageTitle('ezDeFi QRCode');

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('ezdefipayment.php', 'ezDefi Payment Gateway');

$ca->initPage();

$payment_method = $config->getPaymentMethod();

$ca->assign('payment_method', $payment_method);

$amount = $_POST['amount'];
$currency = $_POST['currency'];
$uoid = $_POST['uoid'];

$order_data = array(
	'amount' => $amount,
	'currency' => $currency,
	'uoid' => $uoid
);

$ca->assign('order_data', json_encode($order_data));

$currency_config = $config->getCurrency();

$to = implode(',', array_map(function ( $currency ) {
	return $currency['symbol'];
}, $currency_config ) );

$exchanges = $api->getTokenExchanges($amount, $currency, $to);

foreach ($currency_config as $i => $c) {
	$discount = (intval($c['discount']) > 0) ? $c['discount'] : 0;
	$index = array_search( $c['symbol'], array_column($exchanges, 'token'));
	$amount = $exchanges[$index]['amount'];
	$amount = $amount - ($amount * ($discount / 100));
	$currency_config[$i]['price'] = number_format( $amount, 8 );
}

$ca->assign('currency', $currency_config);

$url_data = array(
	'ajaxUrl' => $config->getSystemUrl() . 'ezdefiajax.php',
	'clientArea' => $config->getSystemUrl() . 'clientarea.php',
	'cart' => $config->getSystemUrl() . 'cart.php'
);

$ca->assign('url_data', json_encode($url_data));

$ca->setTemplate('../ezdefi/qrcode');

$ca->output();