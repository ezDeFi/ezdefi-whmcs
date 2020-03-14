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

    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    public function getPublicKey()
    {
        if( empty( $this->publicKey ) ) {
            $publicKey = $this->db->getPublicKey();
            $this->setPublicKey($publicKey);
        }

        return $this->publicKey;
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

	public function checkApiKey()
	{
		$response = $this->call('user/show', 'get');

		return $response;
	}

    public function getWebsiteConfig()
    {
        $public_key = $this->getPublicKey();

        return $this->call("website/$public_key");
    }

    public function getWebsiteCoins()
    {
        $website_config = $this->getWebsiteConfig();

        if(is_null( $website_config)) {
            return null;
        }

        $website_config = json_decode($website_config, true)['data'];

        return $website_config['coins'];
    }

	public function getToken($keyword = '')
	{
		$response = $this->call('token/list', 'get', array(
			'keyword' => $keyword,
			'domain' => $_SERVER['SERVER_NAME'],
			'platform' => 'whmcs'
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

	public function createPayment($order_data, $coin_data, $amountId = false)
	{
		$subtotal = $order_data['amount'];
		$discount = $coin_data['discount'];
		$value = $subtotal * (number_format((100 - $discount) / 100, 8));

		if($amountId) {
		    $rate = $this->getTokenExchange($order_data['currency'], $coin_data['token']['symbol']);

		    if(!$rate) {
		        return false;
            }

            $value = round($value * $rate, $coin_data['decimal']);
		}

		$uoid = intval($order_data['uoid']);

		if( $amountId ) {
			$uoid = $uoid . '-1';
		} else {
			$uoid = $uoid . '-0';
		}

		$data = [
			'uoid' => $uoid,
			'to' => $coin_data['walletAddress'],
			'value' => $value,
            'safedist' => $coin_data['blockConfirmation'],
            'duration' => $coin_data['expiration'] * 60,
            'callback' => $this->db->getSystemUrl() . '/modules/gateways/callback/ezdefi.php',
            'coinId' => $coin_data['_id']
		];

		if($amountId) {
			$data['amountId'] = true;
			$data['currency'] = $coin_data['token']['symbol'] . ':' . $coin_data['token']['symbol'];
		} else {
			$data['currency'] = $order_data['currency'] . ':' . $coin_data['token']['symbol'];
		}

		$response = $this->call('payment/create', 'post', $data);

		return $response;
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