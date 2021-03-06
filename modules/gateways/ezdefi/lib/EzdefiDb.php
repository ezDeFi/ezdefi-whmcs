<?php

namespace WHMCS\Module\Gateway\Ezdefi;

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/init.php';
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/includes/gatewayfunctions.php';

use WHMCS\Database\Capsule;

use \WHMCS\Module\GatewaySetting;

class EzdefiDb 
{
	public function getSystemUrl()
	{
		return Capsule::table('tblconfiguration')
				->where('setting', 'SystemURL')
				->value('value');
	}

	protected function getSettingValue($name)
	{
		$setting = GatewaySetting::where('gateway', '=', 'ezdefi')->where('setting', '=', $name)->first();

		if($setting->value) {
			return $setting->value;
		}

		return '';
	}

	public function getApiUrl()
	{
		return $this->getSettingValue('apiUrl');
	}

	public function getApiKey()
	{
		return $this->getSettingValue('apiKey');
	}

    public function getPublicKey()
    {
		return $this->getSettingValue('publicKey');
    }

    public function getVersion()
    {
		return $this->getSettingValue('version');
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

    public function upgradeDatabase($currentVersion, $newVersion)
    {
        try {
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'ALTER TABLE tblezdefiexceptions ADD confirmed TinyInt(1) DEFAULT 0, ADD is_show TinyInt(1) DEFAULT 1, ALTER explorer_url SET DEFAULT NULL;'
            );
            $statement->execute();
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
        }

        try {
            if(is_null($currentVersion)) {
                Capsule::table('tblpaymentgateways')->insert(
                    ['gateway' => 'ezdefi', 'setting' => 'version', 'value' => $newVersion]
                );
            } else {
                Capsule::table('tblpaymentgateways')->where('gateway', 'ezdefi')->where('setting', 'version')->update(
                    ['value' => $newVersion]
                );
            }
            return true;
        } catch (\Exception $e) {
            var_dump($e->getMessage());
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

	public function get_exception($exception_id)
    {
        return Capsule::table('tblezdefiexceptions')->where('id', $exception_id)->first();
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

	public function add_invoice_note($invoiceId, $note)
    {
        return Capsule::table('tblinvoices')->where('id', $invoiceId)->update(array(
            'notes' => $note
        ));
    }

	public function get_clients()
	{
		return Capsule::table('tblclients')->get();
	}
}