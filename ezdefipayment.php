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

$ca->setPageTitle('Pay with cryptocurrency');

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('ezdefipayment.php', 'Pay with cryptocurrency');

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

$website_config = $api->getWebsiteConfig();

$website_config = json_decode($website_config, true)['data'];

$website_coins = $website_config['coins'];

$to = implode(',', array_map(function ( $coin ) {
	return $coin['token']['symbol'];
}, $website_coins ) );

$exchanges = $api->getTokenExchanges($amount, $currency, $to);

foreach ($website_coins as $i => $c) {
	$discount = (intval($c['discount']) > 0) ? $c['discount'] : 0;
	$index = array_search( $c['token']['symbol'], array_column($exchanges, 'token'));
	$amount = $exchanges[$index]['amount'];
	$amount = $amount - ($amount * ($discount / 100));
    $website_coins[$i]['price'] = number_format( $amount, 8 );
    $website_coins[$i]['json_data'] = array(
        '_id' => $c['_id'],
        'discount' => $c['discount'],
        'wallet_address' => $c['walletAddress'],
        'symbol' => $c['token']['symbol'],
        'decimal' => $c['decimal'],
        'block_confirmation' => $c['blockConfirmation'],
        'duration' => $c['expiration']
    );
}
$ca->assign('website_config', $website_config);
$ca->assign('coins', $website_coins);

$url_data = array(
	'ajaxUrl' => $config->getSystemUrl() . 'ezdefiajax.php',
	'clientArea' => $config->getSystemUrl() . 'clientarea.php',
	'cart' => $config->getSystemUrl() . 'cart.php'
);

$ca->assign('url_data', json_encode($url_data));

$ca->setTemplate('../ezdefi/qrcode');

$ca->output();