<?php

define('CLIENTAREA', true);
require_once 'init.php';

use WHMCS\ClientArea;
use WHMCS\Module\Gateway\Ezdefi\EzdefiDb;

$config = new EzdefiDb();

if(!isset($_POST) || empty($_POST)){
	header("Location: " . $config->getSystemUrl() . 'cart.php');
	die();
}

$ca = new ClientArea();

$ca->setPageTitle('Ezpay QRCode');

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('ezdefipayment.php', 'ezDefi Payment Gateway');

$ca->initPage();

$currency_config = $config->getCurrency();

$ca->assign('currency', $currency_config);

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

$url_data = array(
	'ajaxUrl' => $config->getSystemUrl() . 'ezdefiajax.php',
	'clientArea' => $config->getSystemUrl() . 'clientarea.php',
	'cart' => $config->getSystemUrl() . 'cart.php'
);

$ca->assign('url_data', json_encode($url_data));

$ca->setTemplate('../ezdefi/qrcode');

$ca->output();