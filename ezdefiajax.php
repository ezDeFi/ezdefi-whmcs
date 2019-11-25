<?php

require_once 'init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Gateway\Ezdefi\Ezdefi;
use WHMCS\Module\Gateway\Ezdefi\EzdefiDb;
use WHMCS\Module\Gateway\Ezdefi\EzdefiApi;

$ezpay = new Ezdefi();

if(!isset($_POST['action'])) {
	return;
}

if($_POST['action'] === 'save_currency') {
	$data = $_POST['currency'];

	$config = new EzdefiDb();
	$response = $config->saveCurrency($data);

	echo $response;
}

if($_POST['action'] === 'get_token') {
	if(!isset($_POST['keyword']) || !isset($_POST['api_url'])) {
		return;
	}

	$keyword = $_POST['keyword'];

	$api_url = $_POST['api_url'];

	$api = new EzdefiApi($api_url);

	$response = $api->getToken($keyword);

	echo json_encode($response);
}

if($_POST['action'] === 'check_wallet') {
	$address = $_POST['address'];
	$apiUrl = $_POST['apiUrl'];
	$apiKey = $_POST['apiKey'];

	$api = new EzdefiApi($apiUrl, $apiKey);

	$response = $api->getListWallet();

	$response = json_decode($response, true);

	$list_wallet = $response['data'];

	$key = array_search( $address, array_column( $list_wallet, 'address' ) );

	if($key !== false) {
		$status = $list_wallet[$key]['status'];

		if($status === 'ACTIVE') {
			echo 'true';
		}
	} else {
		echo 'false';
	}
}

if($_POST['action'] === 'create_payment') {
	$uoid = $_POST['uoid'];
	$symbol = $_POST['symbol'];
	$method = $_POST['method'];

	$payment = $ezpay->createEzpayPayment($uoid, $symbol, $method);

	echo $payment;
}

if($_POST['action'] === 'check_invoice') {
	$invoiceId = $_POST['invoice_id'];

	$config = new EzdefiDb();
	$invoiceStatus = $config->getInvoiceStatus($invoiceId);

	echo $invoiceStatus;
}