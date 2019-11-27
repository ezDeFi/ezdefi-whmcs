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
		$subtotal = intval($order_data['amount']);
		$discount = intval($currency_data['discount']);
		$value = $subtotal - ($subtotal * ($discount / 100));

		if( $amountId ) {
			$value = $this->db->generate_amount_id( $value, $currency_data['symbol'] );
		}

		if( ! $value ) {
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
			'currency' => $order_data['currency'] . ':' . $currency_data['symbol'],
			'safedist' => ( isset( $currency_data['block_confirm'] ) ) ? $currency_data['block_confirm'] : '',
			'duration' => ( isset( $currency_data['lifetime'] ) ) ? $currency_data['lifetime'] : '',
			'callback' => $this->db->getSystemUrl() . '/modules/gateways/callback/ezdefi.php',
//			'callback' => 'http://4d70fbed.ngrok.io/modules/gateways/callback/ezdefi.php',
		];

		if($amountId) {
			$data['amountId'] = true;
		}

		$response = $this->call('payment/create', 'post', $data);

		return $response;
	}
}