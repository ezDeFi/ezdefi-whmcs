<?php

require_once '../../../init.php';
require_once '../../../includes/gatewayfunctions.php';

use WHMCS\Module\Gateway\Ezdefi\Ezdefi;

$gatewayModuleName = 'ezdefi';

$gatewayParams = getGatewayVariables($gatewayModuleName);

if(!$gatewayParams['type']) {
	die('Gateway not active');
}

if(empty($_GET)) {
	die('Something wrong happen. Please contact admin.');
}

$ezdefi = Ezdefi::instance();
$ezdefi->callbackHandle($_GET);

die();