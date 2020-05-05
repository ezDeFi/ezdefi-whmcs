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

    public function getPublicKey()
    {
        return Capsule::table('tblpaymentgateways')
                      ->where('gateway', 'ezdefi')
                      ->where('setting', 'publicKey')
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
		              ->where('setting', 'amountId')
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

	public function getInvoiceTotal($invoiceId)
	{
		return Capsule::table('tblinvoices')
					->where('id', $invoiceId)
					->value('total');
	}

	public function getInvoiceStatus($invoiceId)
	{
		return Capsule::table('tblinvoices')
					->where('id', $invoiceId)
					->value('status');
	}

	public function getDefaultCurrency()
	{
		$default = Capsule::table('tblcurrencies')
		              ->where('default', 1)
		              ->first();

		$prefix = $default->prefix;
		$suffix = $default->suffix;
		$code = $default->code;

		return array(
			'prefix' => $prefix,
			'suffix' => $suffix,
			'code' => $code
		);
	}

	public function get_client_currency($client_id)
	{
		return Capsule::table('tblclients')->join('tblcurrencies', function($join) use($client_id) {
			$join->on('tblclients.currency', '=', 'tblcurrencies.id')->where('tblclients.id', '=', $client_id);
		})->value('code');
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
				$table->decimal('price', 60, 30);
				$table->decimal('amount_id', 60, 30);
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
				$table->increments('id');
				$table->decimal('amount_id', 60, 30);
				$table->string('currency');
				$table->integer('order_id')->nullable();
				$table->string('status')->nullable();
				$table->string('payment_method')->nullable();
				$table->string('explorer_url')->nullable()->default(null);
				$table->tinyInteger('confirmed')->default(0);
				$table->tinyInteger('is_show')->default(1);
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
		            IN value DECIMAl(60,30),
				    IN token VARCHAR(10),
				    IN decimal_number INT(2),
				    IN life_time INT(11),
				    OUT amount_id DECIMAL(60,30)
				)
				BEGIN
				    DECLARE unique_id INT(11) DEFAULT 0;
				    IF EXISTS (SELECT 1 FROM tblezdefiamountids WHERE `currency` = token AND `price` = value) THEN
				        IF EXISTS (SELECT 1 FROM tblezdefiamountids WHERE `currency` = token AND `price` = value AND `amount_key` = 0 AND `expired_time` > NOW()) THEN
					        SELECT MIN(t1.amount_key+1) INTO unique_id FROM tblezdefiamountids t1 LEFT JOIN tblezdefiamountids t2 ON t1.amount_key + 1 = t2.amount_key AND t2.price = value AND t2.currency = token AND t2.expired_time > NOW() WHERE t2.amount_key IS NULL;
					        IF((unique_id % 2) = 0) THEN
					            SET amount_id = value + ((unique_id / 2) / POW(10, decimal_number));
					        ELSE
					            SET amount_id = value - ((unique_id - (unique_id DIV 2)) / POW(10, decimal_number));
					        END IF;
			            ELSE
			                SET amount_id = value;
			            END IF;
				    ELSE
				        SET amount_id = value;
				    END IF;
				    INSERT INTO tblezdefiamountids (amount_key, price, amount_id, currency, expired_time) 
				        VALUES (unique_id, value, amount_id, token, NOW() + INTERVAL life_time SECOND + INTERVAL 10 SECOND)
	                    ON DUPLICATE KEY UPDATE `expired_time` = NOW() + INTERVAL life_time SECOND + INTERVAL 10 SECOND;
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
				CREATE EVENT IF NOT EXISTS ezdefi_clear_exception_table
				ON SCHEDULE EVERY 7 DAY
				DO
					DELETE FROM tblezdefiexceptions;
			");

			$pdo->commit();
		} catch (\Exception $e) {
			$pdo->rollback();
		}
	}

	public function get_invoice($id)
	{
		return Capsule::table('tblinvoices')->find($id);
	}

	public function get_unpaid_invoices()
	{
		return Capsule::table('tblinvoices')
					->join('tblclients', function($join) {
						$join->on('tblinvoices.userid', '=', 'tblclients.id')->where('tblinvoices.status', '=', 'Unpaid');
					})
					->select('tblinvoices.id', 'tblinvoices.total', 'tblinvoices.date', 'tblinvoices.duedate', 'tblclients.firstname', 'tblclients.lastname')
					->orderBy('tblinvoices.date', 'desc')
					->get();
	}

	public function get_unpaid_invoice($invoiceId)
	{
		return Capsule::table('tblinvoices')
		              ->join('tblclients', function($join) use ($invoiceId) {
			              $join->on('tblinvoices.userid', '=', 'tblclients.id')->where('tblinvoices.status', '=', 'Unpaid')->where('tblinvoices.id', '=', $invoiceId);
		              })
		              ->select('tblinvoices.id', 'tblinvoices.total', 'tblinvoices.date', 'tblinvoices.duedate', 'tblclients.firstname', 'tblclients.lastname')
		              ->orderBy('tblinvoices.date', 'desc')
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
		$sql = Capsule::table('tblinvoices')->where('id', $invoiceId);

		if($status === 'Paid') {
			return $sql->update([
				'status' => $status,
				'datepaid' => date('Y-m-d H:i:s')
			]);
		}

		return $sql->update([
			'status' => $status,
			'datepaid' => null
		]);
	}

	public function add_exception($data)
	{
		return Capsule::table('tblezdefiexceptions')->insert($data);
	}

	public function get_exceptions($params = array(), $offset, $per_page)
	{
		$sql = Capsule::table('tblezdefiexceptions')
			->leftJoin('tblinvoices', 'tblezdefiexceptions.order_id', '=', 'tblinvoices.id')
			->leftJoin('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
			->select('tblezdefiexceptions.*', 'tblclients.firstname', 'tblclients.lastname', 'tblclients.id as clientid');

        foreach($params as $column => $param) {
            if($column === 'type') {
                switch ($params['type']) {
                    case 'pending' :
                        $sql->where('tblezdefiexceptions.confirmed', '=', 0)->whereNotNull('tblezdefiexceptions.explorer_url');
                        break;
                    case 'confirmed' :
                        $sql->where('tblezdefiexceptions.confirmed', '=', 1);
                        break;
                    case 'archived' :
                        $sql->where('tblezdefiexceptions.confirmed', '=', 0)->whereNull('tblezdefiexceptions.explorer_url')->where('tblezdefiexceptions.is_show', '=', 1);
                        break;
                }
            }  elseif (!empty($param)) {
                switch ($column) {
                    case 'clientid':
                        $sql = $sql->where('tblclients.id', '=', $param);
                        break;
                    case 'amount_id':
                        $amount_id = $params['amount_id'];
                        $sql = $sql->where('amount_id', 'rlike', '^'.$amount_id);
                        break;
                    default :
                        $sql = $sql->where("tblezdefiexceptions.$column", '=', "$param");
                        break;
                }
            }
        }

		$sql = $sql->orderBy('tblezdefiexceptions.id', 'desc');

		$data = array();

		$data['total'] = $sql->count();

		$data['data'] = $sql->offset($offset)->limit($per_page)->get();

		return $data;
	}

	public function delete_exception($exception_id)
	{
		return Capsule::table('tblezdefiexceptions')->where('id', $exception_id)->delete();
	}

	public function delete_exceptions($wheres = array())
    {
        $sql = Capsule::table('tblezdefiexceptions');

        if(empty($wheres)) {
            return;
        }

        foreach($wheres as $column => $value)  {
            if(!empty( $value )) {
                $type = gettype($value);
                switch ($type) {
                    case 'NULL' :
                        $sql->whereNull($column);
                        break;
                    default :
                        $sql->where($column, $value);
                        break;
                }
            }
        }

        return $sql->delete();
    }

	public function update_exceptions($wheres = array(), $data = array(), $limit = null)
	{
		$sql = Capsule::table('tblezdefiexceptions');

		if(empty($data) || empty($wheres)) {
			return;
		}

        foreach($wheres as $column => $value)  {
            if(!empty( $value )) {
                $type = gettype($value);
                switch ($type) {
                    case 'NULL' :
                        $sql->whereNull($column);
                        break;
                    default :
                        $sql->where($column, $value);
                        break;
                }
            }
        }

        if(is_numeric($limit)) {
            $sql->orderBy('id', 'desc')->limit($limit);
        }

		return $sql->update($data);
	}

	public function get_clients()
	{
		return Capsule::table('tblclients')->get();
	}
}