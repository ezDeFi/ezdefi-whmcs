<?php

namespace WHMCS\Module\Gateway\Ezdefi;

class EzdefiApi {
	protected $apiUrl;

	protected $apiKey;

	protected $db;

	public function __construct($apiUrl = '', $apiKey = '') {
		$this->apiUrl = $apiUrl;
		$this->apiKey = $apiKey;

		$this->db = new EzdefiDb();
	}

	public function setApiUrl( $apiUrl )
	{
		$this->apiUrl = $apiUrl;
	}

	public function getApiUrl()
	{
		if( empty( $this->apiUrl ) ) {
			$apiUrl = $this->db->getApiUrl();
			$this->setApiUrl($apiUrl);
		}

		return $this->apiUrl;
	}

	public function setApiKey($apiKey)
	{
		$this->apiKey = $apiKey;
	}

	public function getApiKey()
	{
		if( empty( $this->apiKey ) ) {
			$apiKey = $this->db->getApiKey();
			$this->setApiKey($apiKey);
		}

		return $this->apiKey;
	}

	public function buildPath($path)
	{
		return rtrim($this->getApiUrl(), '/') . '/' . $path;
	}

	public function call($path, $method = 'GET', $data = [])
	{
		$url = $this->buildPath($path);
		$method = strtolower($method);

		$curl = curl_init();
		switch ($method) {
			case 'post' :
				curl_setopt($curl, CURLOPT_POST, 1);
				if ($data)
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				break;
			default :
				if ($data)
					$url = sprintf("%s?%s", $url, http_build_query($data));
		}
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'api-key: ' . $this->getApiKey(),
		));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$result = curl_exec($curl);
		curl_close($curl);

		return $result;
	}

	public function getToken($keyword = '')
	{
		$response = $this->call('token/list', 'get', array(
			'keyword' => $keyword
		));

		return $response;
	}

	public function getListWallet()
	{
		$response = $this->call('user/list_wallet', 'get', array() );

		return $response;
	}

	public function getPayment( $paymentid )
	{
		$response = $this->call('payment/get', 'get', array(
			'paymentid' => $paymentid
		) );

		return $response;
	}

	public function createPayment($order_data, $currency_data, $amountId = false)
	{
		$subtotal = $order_data['amount'];
		$discount = $currency_data['discount'];
		$value = $subtotal - ($subtotal * ($discount / 100));

		if($amountId) {
			$value = $this->generateAmountId($order_data['currency'], $value, $currency_data );
		}

		if(!$value) {
			return false;
		}

		$uoid = intval($order_data['uoid']);

		if( $amountId ) {
			$uoid = $uoid . '-1';
		} else {
			$uoid = $uoid . '-0';
		}

		$data = [
			'uoid' => $uoid,
			'to' => $currency_data['wallet'],
			'value' => $value,
			'safedist' => ( isset( $currency_data['block_confirm'] ) ) ? $currency_data['block_confirm'] : '',
			'duration' => ( isset( $currency_data['lifetime'] ) ) ? $currency_data['lifetime'] : '',
//			'callback' => $this->db->getSystemUrl() . '/modules/gateways/callback/ezdefi.php',
			'callback' => 'http://d86cc091.ngrok.io/callback',
		];

		if($amountId) {
			$data['amountId'] = true;
			$data['currency'] = $currency_data['symbol'] . ':' . $currency_data['symbol'];
		} else {
			$data['currency'] = $order_data['currency'] . ':' . $currency_data['symbol'];
		}

		$response = $this->call('payment/create', 'post', $data);

		return $response;
	}

	public function generateAmountId($fiat, $value, $currency_data)
	{
		$rate = $this->getTokenExchange($fiat, $currency_data['symbol']);

		if(!$rate) {
			return null;
		}

		$value = $value * $rate;

		$value = $this->db->generate_amount_id($value, $currency_data);

		return $value;
	}

	public function getTokenExchange($fiat, $token)
	{
		$response = $this->call( 'token/exchange/' . $fiat . ':' . $token, 'get' );

		$response = json_decode( $response, true );

		if( $response['code'] === -1 ) {
			return null;
		}

		return $response['data'];
	}

	public function getTokenExchanges($value, $from, $to)
	{
		$url = "token/exchanges?amount=$value&from=$from&to=$to";

		$response = $this->call( $url, 'get' );

		$response = json_decode( $response, true );

		if( $response['code'] < 0 ) {
			return null;
		}

		return $response['data'];
	}

	public function getTransaction($id)
	{
		$response = $this->call('transaction/get', 'get', array(
			'id' => $id
		));

		$response = json_decode($response, true);

		if( $response['code'] < 0 ) {
			return null;
		}

		return $response['data'];
	}
}