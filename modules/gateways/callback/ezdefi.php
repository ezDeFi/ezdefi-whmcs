<?php

require_once '../../../init.php';
require_once '../../../includes/gatewayfunctions.php';

use WHMCS\Module\Gateway\Ezdefi\Ezdefi;

$gatewayModuleName = 'ezdefi';

$gatewayParams = getGatewayVariables($gatewayModuleName);

if(!$gatewayParams['type']) {
	die('Gateway not active');
}

if(!isset($_GET['uoid']) || !isset($_GET['paymentid'])) {
	die('Something wrong happen. Please contact admin.');
}

$invoiceId = $_GET['uoid'];
$paymentId = $_GET['paymentid'];

if(empty($invoiceId) || empty($paymentId)) {
	die('Something wrong happen. Please contact admin.');
}

$ezdefi = Ezdefi::instance();
$ezdefi->callbackHandle($invoiceId, $paymentId);