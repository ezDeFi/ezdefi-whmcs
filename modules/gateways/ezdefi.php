<?php

if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

use WHMCS\Module\Gateway\Ezdefi\Ezdefi;

function getInstance() {
    return Ezdefi::instance();
}

function ezdefi_MetaData() {
	return getInstance()->getMetaData();
}

function ezdefi_config() {
	return getInstance()->getConfig();
}

function ezdefi_link($params) {
	if(!isset($params) || empty($params)) {
		return;
	}

	return getInstance()->getLink($params);
}

add_hook('AdminAreaFooterOutput', 1, function($vars) {
    try {
		$gatewayModuleName = basename(__FILE__, '.php');
		$gatewayParams = getGatewayVariables($gatewayModuleName);
		if(isset($gatewayParams['token']) && ! empty($gatewayParams['token'])) {
			$gatewayParams['token'] = unserialize(base64_decode($gatewayParams['token']));
        } else {
			$gatewayParams['token'] = '';
		}
	} catch (Exception $e) {
		return;
	}

	return getInstance()->getAdminFooterOutput($gatewayParams);
});
getInstance()->init();