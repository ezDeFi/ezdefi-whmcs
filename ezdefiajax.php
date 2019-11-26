<?php

require_once 'init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Gateway\Ezdefi\Ezdefi;
use WHMCS\Module\Gateway\Ezdefi\EzdefiDb;
use WHMCS\Module\Gateway\Ezdefi\EzdefiApi;

function json_response($code = 200, $data = '') {
	header_remove();
	http_response_code($code);
	header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
	header('Content-Type: application/json');
	$status = array(
		200 => '200 OK',
		400 => '400 Bad Request'
	);

	header('Status: '.$status[$code]);

	if($code < 300) {
		return json_encode(array(
			'status' => 200,
			'data' => $data
		));
	}

	return json_encode(array(
		'status' => 400,
		'message' => $data
	));
}

function json_success_response($data = '') {
	return json_response(200, $data);
}

function json_error_response($data = '') {
	return json_response(400, $data);
}

$ezpay = new Ezdefi();

if(!isset($_POST['action'])) {
	return;
}

if($_POST['action'] === 'save_currency') {
	if(!isset($_POST['currency'])) {
		echo json_error_response();
		exit;
	}

	$data = $_POST['currency'];

	if(!is_array($data) || empty($data)) {
		echo json_error_response();
		exit;
	}

	$save = $ezpay->saveCurrencyConfig($data);

	if($save) {
		echo json_success_response();
		exit;
	}

	echo json_error_response();
	exit;
}

if($_POST['action'] === 'get_token') {
	if(!isset($_POST['keyword']) || !isset($_POST['api_url'])) {
		return;
	}

	$api_url = $_POST['api_url'];

	if(empty($api_url)) {
		echo json_error_response();
		exit;
	}

	$keyword = $_POST['keyword'];

	$token = $ezpay->getToken($api_url, $keyword);

	echo json_encode($token);
	exit;
}

if($_POST['action'] === 'check_wallet') {
	if(!isset($_POST['address']) || !isset($_POST['apiUrl']) || !isset($_POST['apiKey'])) {
		echo 'false';
	}

	$check = $ezpay->checkWalletAddress($_POST);

	echo $check;
	exit;
}

if($_POST['action'] === 'create_payment') {
	if(!isset($_POST['uoid']) || !isset($_POST['symbol']) || !isset($_POST['method'])) {
		echo json_error_response();
		exit();
	}

	$uoid = $_POST['uoid'];
	$symbol = $_POST['symbol'];
	$method = $_POST['method'];

	if(empty($uoid) || empty($symbol) || empty($method)) {
		echo json_error_response();
		exit();
	}

	$payment = $ezpay->createEzdefiPayment($uoid, $symbol, $method);

	if(!$payment) {
		echo json_error_response();
		exit();
	}

	echo json_success_response($payment);
	exit();
}

if($_POST['action'] === 'check_invoice') {
	if(!isset($_POST['invoice_id'])) {
		die();
	}

	$invoiceId = $_POST['invoice_id'];

	if(empty($invoiceId)) {
		die();
	}

	$status = $ezpay->checkInvoice($invoiceId);

	die($status);
}

if($_POST['action'] === 'payment_timeout') {
	if(!isset($_POST['paymentid'])) {
		die();
	}

	$paymentid = $_POST['paymentid'];

	if(empty($invoiceId)) {
		die();
	}

	$payment = $ezpay->set_amount_id_valid($paymentid);

	die(json_encode($payment));
}