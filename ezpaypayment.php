<?php

define('CLIENTAREA', true);
require_once 'init.php';

use WHMCS\ClientArea;
use WHMCS\Module\Gateway\Ezpay\EzpayConfig;

$config = new EzpayConfig();

if(!isset($_POST) || empty($_POST)){
	header("Location: " . $config->getSystemUrl() . 'cart.php');
	die();
}

$ca = new ClientArea();

$ca->setPageTitle('Ezpay QRCode');

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('ezpaypayment.php', 'Ezpay Payment Gateway');

$ca->initPage();

$currency_config = $config->getCurrency();

$ca->assign('currency', $currency_config);

$amount = $_POST['amount'];
$currency = $_POST['currency'];
$uoid = $_POST['uoid'];

$order_data = array(
	'amount' => $amount,
	'currency' => $currency,
	'uoid' => $uoid
);

$ca->assign('order_data', json_encode($order_data));

$url_data = array(
	'ajaxUrl' => $config->getSystemUrl() . 'ezpayajax.php',
	'clientArea' => $config->getSystemUrl() . 'clientarea.php',
	'cart' => $config->getSystemUrl() . 'cart.php'
);

$ca->assign('url_data', json_encode($url_data));

$ca->setTemplate('../ezpay/qrcode');

$ca->output();