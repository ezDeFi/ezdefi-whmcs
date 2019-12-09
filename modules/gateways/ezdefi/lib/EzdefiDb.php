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
				$table->increments('id');
				$table->integer('amount_key');
				$table->decimal('price', 18, 10);
				$table->decimal('amount_id', 18, 10);
				$table->string('currency');
				$table->timestamp('expired_time');
				$table->unique(['amount_id', 'currency']);
			});

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function createExceptionTable()
	{
		$hasTable = Capsule::schema()->hasTable('tblezdefiexceptions');

		if($hasTable) {
			return;
		}

		try {
			Capsule::schema()->create('tblezdefiexceptions', function($table) {
				$table->decimal('amount_id', 18, 10);
				$table->string('currency');
				$table->integer('order_id');
				$table->timestamp('created_at');
			});

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function addProcedure()
	{
		$pdo = Capsule::connection()->getPdo();
		$pdo->beginTransaction();

		try {
			$pdo->exec("
				CREATE PROCEDURE IF NOT EXISTS `ezdefi_generate_amount_id`(
		            IN value DECIMAl(18,10),
				    IN token VARCHAR(10),
				    IN decimal_number INT(2),
				    IN life_time INT(11),
				    OUT amount_id DECIMAL(18,10)
				)
				BEGIN
				    DECLARE unique_id INT(11) DEFAULT 0;
				    IF EXISTS (SELECT 1 FROM tblezdefiamountids WHERE `currency` = token AND `price` = value) THEN
				        SELECT MIN(t1.amount_key+1) INTO unique_id FROM tblezdefiamountids t1 LEFT JOIN tblezdefiamountids t2 ON t1.amount_key + 1 = t2.amount_key AND t2.price = value AND t2.currency = token AND t2.expired_time > NOW() WHERE t2.amount_key IS NULL;
				        IF((unique_id % 2) = 0) THEN
				            SET amount_id = value + ((unique_id / 2) / POW(10, decimal_number));
				        ELSE
				            SET amount_id = value - ((unique_id - (unique_id DIV 2)) / POW(10, decimal_number));
				        END IF;
				    ELSE
				        SET amount_id = value;
				    END IF;
				    INSERT INTO tblezdefiamountids (amount_key, price, amount_id, currency, expired_time) 
				        VALUES (unique_id, value, amount_id, token, NOW() + INTERVAL life_time SECOND)
	                    ON DUPLICATE KEY UPDATE `expired_time` = NOW() + INTERVAL life_time SECOND;
	                INSERT INTO tblezdefiexceptions (amount_id, currency) VALUES (amount_id, token);
				END
			");

			$pdo->commit();
		} catch (\Exception $e) {
			$pdo->rollBack();
		}
	}

	public function addScheduleEvents()
	{
		$pdo = Capsule::connection()->getPdo();
		$pdo->beginTransaction();

		try {
			$pdo->exec("
				CREATE EVENT IF NOT EXISTS ezdefi_clear_amount_table
				ON SCHEDULE EVERY 3 DAY
				DO
					DELETE FROM tblezdefiamountids;
			");

			$pdo->exec("
				CREATE EVENT IF NOT EXISTS ezdefi_clear_exception_table
				ON SCHEDULE EVERY 7 DAY
				DO
					DELETE FROM tblezdefiexceptions;
			");

			$pdo->commit();
		} catch (\Exception $e) {
			var_dump($e->getMessage());
			$pdo->rollback();
		}
	}

	public function generate_amount_id($price, $currency_data)
	{
		$decimal = $currency_data['decimal'];
		$life_time = $currency_data['lifetime'];
		$symbol = $currency_data['symbol'];

		$price = round( $price, $decimal );

		$pdo = Capsule::connection()->getPdo();
		$pdo->beginTransaction();

		try {
			$call = $pdo->prepare("
				CALL ezdefi_generate_amount_id(:price, :symbol, :decimal, :life_time, @amount_id)
			");

			$call->execute([
				':price' => $price,
				':symbol' => $symbol,
				':decimal' => $decimal,
				':life_time' => $life_time,
			]);

			$select = $pdo->prepare("SELECT @amount_id");

			$select->execute();

			$result = $select->fetchAll();

			$pdo->commit();
		} catch (\Exception $e) {
			$pdo->rollBack();
		}

		if( ! $result ) {
			return null;
		}

		$amount_id = floatval( $result[0]['@amount_id'] );

		$acceptable_variation = $this->get_acceptable_variation();

		$variation_percent = $acceptable_variation / 100;

		$min = floatval( $price - ( $price * $variation_percent ) );
		$max = floatval( $price + ( $price * $variation_percent ) );

		if( ( $amount_id < $min ) || ( $amount_id > $max ) ) {
			return null;
		}

		return $amount_id;
	}

	public function delete_amount_id_exception($amount_id, $currency)
	{
		return Capsule::table('tblezdefiexceptions')
		                     ->where('amount_id', $amount_id)
		                     ->where('currency', $currency)
		                     ->delete();
	}

	public function add_uoid_to_exception($amount_id, $currency, $uoid)
	{
		return Capsule::table('tblpaymentgateways')
						->where('amount_id', $amount_id)
						->where('currency', $currency)
						->update(['order_id' => $uoid]);
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

	public function get_exceptions()
	{
		return Capsule::table('tblezdefiexceptions')
						->get();

	}

	public function get_unpaid_invoices()
	{
		return Capsule::table('tblinvoices')
					->join('tblclients', function($join) {
						$join->on('tblinvoices.userid', '=', 'tblclients.id')->where('tblinvoices.status', '=', 'Unpaid');
					})
					->select('tblinvoices.id', 'tblinvoices.total', 'tblinvoices.date', 'tblclients.email')
					->get();
	}

	public function check_invoice_exist($invoiceId)
	{
		$count = Capsule::table('tblinvoices')
		                ->where('id', $invoiceId)
		                ->count();

		return ($count > 0) ? true : false;
	}

	public function update_invoice_status($invoiceId, $status)
	{
		return Capsule::table('tblinvoices')
					->where('id', $invoiceId)
					->update(['status' => $status]);
	}
}