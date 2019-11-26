<?php

namespace WHMCS\Module\Gateway\Ezdefi;

use WHMCS\Database\Capsule;

class EzdefiDb {
	public function getSystemUrl()
	{
		return Capsule::table('tblconfiguration')
				->where('setting', 'SystemURL')
				->value('value');
	}

	public function getApiUrl()
	{
		return Capsule::table('tblpaymentgateways')
	              ->where('gateway', 'ezdefi')
	              ->where('setting', 'apiUrl')
	              ->value('value');
	}

	public function getApiKey()
	{
		return Capsule::table('tblpaymentgateways')
				->where('gateway', 'ezdefi')
				->where('setting', 'apiKey')
				->value('value');
	}

	public function getCurrency()
	{
		$data = Capsule::table('tblpaymentgateways')
		               ->where('gateway', 'ezdefi')
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

		$saved = Capsule::table( 'tblpaymentgateways' )->where( 'gateway', 'ezdefi' )->where( 'setting', 'token' )->get();

		if($saved) {
			return Capsule::table( 'tblpaymentgateways' )->where( 'gateway', 'ezdefi' )->where( 'setting', 'token' )->update(['value' => $data]);
		}

		return Capsule::table( 'tblpaymentgateways' )->insert([
			'gateway' => 'ezdefi',
			'setting' => 'token',
			'value' => $data
		]);
	}

	public function getPaymentMethod()
	{
		$payment_method = array();

		$simple_method = Capsule::table('tblpaymentgateways')
		              ->where('gateway', 'ezdefi')
		              ->where('setting', 'simpleMethod')
		              ->value('value');

		if($simple_method === 'on') {
			$payment_method[] = 'amount_id';
		}

		$ezdefi_wallet = Capsule::table('tblpaymentgateways')
                       ->where('gateway', 'ezdefi')
                       ->where('setting', 'ezdefiWallet')
                       ->value('value');

		if($ezdefi_wallet === 'on') {
			$payment_method[] = 'ezdefi_wallet';
		}

		return $payment_method;
	}

	public function getInvoice($invoiceId)
	{
		return Capsule::table('tblinvoices')
		              ->where('id', $invoiceId)
		              ->first();
	}

	public function getInvoiceStatus($invoiceId)
	{
		return Capsule::table('tblinvoices')
					->where('id', $invoiceId)
					->value('status');
	}

	public function getDefaultCurrency()
	{
		return Capsule::table('tblcurrencies')
		              ->where('default', 1)
		              ->value('code');
	}

	public function createAmountIdTable()
	{
		$hasTable = Capsule::schema()->hasTable('tblezdefiamountids');

		if($hasTable) {
			return;
		}

		try {
			Capsule::schema()->create('tblezdefiamountids', function($table) {
				$table->decimal('price', 10, 2);
				$table->decimal('amount_id', 18, 10);
				$table->integer('amount_decimal');
				$table->boolean('amount_valid');
				$table->string('currency');
				$table->timestamp('created_at');
				$table->primary(['amount_id', 'currency']);
			});

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function generate_amount_id($price, $currency)
	{
		$amount_decimal = $this->get_amount_decimals();

		$amount_ids = $this->get_amount_ids($price, $amount_decimal, $currency);

		$one_unit = 1 / pow(10, $amount_decimal );

		if( empty( $amount_ids ) ) {
			$amount_id = $price;
			return $this->save_amount_id($price, $amount_id, $amount_decimal, $currency);
		}

		$valid_index = array_search('1', array_column($amount_ids, 'amount_valid'));

		if($valid_index !== false) {
			$amount_id = floatval($amount_ids[$valid_index]['amount_id']);
			$this->set_amount_id_invalid($amount_id, $currency);
			return $amount_id;
		}

		if(count( $amount_ids ) === 1) {
			$amount_id = $price + $one_unit;
			return $this->save_amount_id($price, $amount_id, $amount_decimal, $currency);
		}

		$counts = array_count_values(array_column($amount_ids, 'amount_abs'));

		$abs = null;

		foreach($counts as $amount_abs => $count) {
			if(floatval($amount_abs) > 0 && $count < 2) {
				$abs = $amount_abs;
				break;
			}
		}

		if( ! $abs ) {
			$id = end($amount_ids)['amount_id'] + $one_unit;
			return $this->save_amount_id($price, $id, $amount_decimal, $currency);
		}

		$index = array_search($abs, array_column($amount_ids, 'amount_abs'));

		$amount_id = $amount_ids[$index];

		if($amount_id['amount_id'] > $amount_id['price']) {
			$id = $amount_id['price'] - $abs;
			return $this->save_amount_id($price, $id, $amount_decimal, $currency);
		} else {
			$id = $amount_id['price'] + $abs;
			return $this->save_amount_id($price, $id, $amount_decimal, $currency);
		}

		return false;
	}

	public function get_amount_decimals()
	{
		return Capsule::table('tblpaymentgateways')
		              ->where('gateway', 'ezdefi')
		              ->where('setting', 'decimal')
		              ->value('value');
	}

	public function get_acceptable_variation()
	{
		return Capsule::table('tblpaymentgateways')
		              ->where('gateway', 'ezdefi')
		              ->where('setting', 'variation')
		              ->value('value');
	}

	public function get_amount_ids($price, $amount_decimal, $currency)
	{
		$amount_ids = Capsule::table('tblezdefiamountids')
					->select(Capsule::raw('price, amount_id, amount_decimal, amount_valid, currency, ABS(amount_id - price) as amount_abs'))
					->where('price', $price)
					->where('amount_decimal', $amount_decimal)
					->where('currency', $currency)
					->orderBy('amount_abs')->get();

		return json_decode(json_encode($amount_ids), true);
	}

	protected function save_amount_id( $price, $amount_id, $amount_decimal, $currency )
	{
		$acceptable_variation = $this->get_acceptable_variation();

		$variation_percent = $acceptable_variation / 100;
		$min = floatval( $price - ( $price * $variation_percent ) );
		$max = floatval( $price + ( $price * $variation_percent ) );

		if( ( $amount_id < $min ) || ( $amount_id > $max ) ) {
			return false;
		}

		$result = Capsule::table('tblezdefiamountids')->insert(
			[
				'price' => $price,
				'amount_id' => $amount_id,
				'amount_decimal' => $amount_decimal,
				'amount_valid' => 0,
				'currency' => $currency,
				'created_at' => date('Y-m-d H:i:s')
			]
		);

		if(!$result) {
			return false;
		}

		return floatval($amount_id);
	}

	public function set_amount_id_invalid($amount_id, $currency)
	{
		return Capsule::table('tblezdefiamountids')
		              ->where('amount_id', $amount_id)
		              ->where('currency', $currency)
		              ->update(['amount_valid' => 0]);
	}

	public function set_amount_id_valid($amount_id, $currency)
	{
		return Capsule::table('tblezdefiamountids')
	                ->where('amount_id', $amount_id)
					->where('currency', $currency)
					->update(['amount_valid' => 1]);
	}

	public function delete_old_amount_id()
	{
		$date = new \DateTime();
		$date->modify('-1 day');
		$date = $date->format('Y-m-d H:i:s');

		return Capsule::table('tblezdefiamountids')->where('created_at', '<=', $date)->delete();
	}
}