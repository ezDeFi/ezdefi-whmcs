<?php

namespace WHMCS\Module\Gateway\Ezpay;

use WHMCS\Database\Capsule;

class EzpayConfig {
	public function getSystemUrl()
	{
		return Capsule::table('tblconfiguration')
				->where('setting', 'SystemURL')
				->value('value');
	}

	public function getApiUrl()
	{
		return Capsule::table('tblpaymentgateways')
	              ->where('gateway', 'ezpay')
	              ->where('setting', 'apiUrl')
	              ->value('value');
	}

	public function getApiKey()
	{
		return Capsule::table('tblpaymentgateways')
				->where('gateway', 'ezpay')
				->where('setting', 'apiKey')
				->value('value');
	}

	public function getCurrency()
	{
		$data = Capsule::table('tblpaymentgateways')
		               ->where('gateway', 'ezpay')
		               ->where('setting', 'token')
		               ->value('value');

		return unserialize(base64_decode($data));
	}

	public function getCurrencyBySymbol($symbol)
	{
		$list_currency = $this->getCurrency();

		$index = array_search($symbol, array_column($list_currency, 'symbol'));

		$currency = $list_currency[$index];

		return $currency;
	}

	public function saveCurrency($data)
	{
		$data = base64_encode(serialize($data));

		try {
			Capsule::table( 'tblpaymentgateways' )
			       ->where( 'gateway', 'ezpay' )
			       ->where( 'setting', 'token' )
			       ->update(['value' => $data]);
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getInvoiceStatus($invoiceId)
	{
		return Capsule::table('tblinvoices')
					->where('id', $invoiceId)
					->value('status');
	}
}