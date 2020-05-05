<?php

namespace WHMCS\Module\Gateway\Ezdefi;

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/includes/invoicefunctions.php';

class EzdefiAjax
{
	protected $db;

	protected $api;

	public function __construct()
	{
		$this->db = new EzdefiDb();
		$this->api = new EzdefiApi();
	}

	public function check_api_key($data)
	{
		if(!isset($data['api_url']) || !isset( $data['api_key'])) {
			die('false');
		}

		$api_url = $data['api_url'];
		$api_key = $data['api_key'];

		$api = new EzdefiApi($api_url, $api_key);

		$response = $api->checkApiKey();

		$response = json_decode($response, true);

		if($response['code'] != 1) {
			die('false');
		}

		die('true');
	}

    public function check_public_key($data)
    {
        if(!isset($data['api_url']) || !isset( $data['api_key']) || !isset( $data['public_key'])) {
            die('false');
        }

        $api_url = $data['api_url'];
        $api_key = $data['api_key'];
        $public_key = $data['public_key'];

        $api = new EzdefiApi($api_url, $api_key);
        $api->setPublicKey($public_key);

        $response = $api->getWebsiteConfig();

        $response = json_decode($response, true);

        if($response['code'] != 1) {
            die('false');
        }

        die('true');
    }

	public function create_payment($data)
	{
		if(!$this->validate_payment_data($data)) {
			return $this->json_error_response();
		}

		$uoid = $data['uoid'];

		$coin_id = $data['coin_id'];

		$website_coins = $this->api->getWebsiteCoins();

		if(is_null($website_coins)) {
			return $this->json_error_response();
		}

		$coin_data = array();

		foreach($website_coins as $website_coin) {
			if($website_coin['_id'] === $coin_id) {
				$coin_data = $website_coin;
				break;
			}
		}

		if(empty($coin_data)) {
			return $this->json_error_response();
		}

		$method = $data['method'];

		$payment = $this->create_ezdefi_payment($uoid, $coin_data, $method);

		if(!$payment) {
			return $this->json_error_response();
		}

		return $this->json_success_response($payment);
	}

	protected function validate_payment_data($data)
	{
		if(!isset($data['uoid']) || !isset($data['coin_id']) || !isset($data['method'])) {
			return false;
		}

		$uoid = $data['uoid'];
		$coin_id = $data['coin_id'];
		$method = $data['method'];

		if(empty($uoid) || empty($coin_id) || empty($method)) {
			return false;
		}

		return true;
	}

	public function create_ezdefi_payment($invoiceId, $coin_data, $method)
	{
		$invoice = $this->db->getInvoice($invoiceId);

		$currency = $this->db->get_client_currency($invoice->userid);

		$order_data = array(
			'amount' => $invoice->total,
			'uoid' => $invoiceId,
			'currency' => $currency
		);

		$amount_id = ($method === 'amount_id') ? true : false;

		$payment = $this->api->createPayment($order_data, $coin_data, $amount_id);

		$payment = json_decode($payment, true);

		if($payment['code'] == -1 || isset($payment['error']) || !isset($payment['data']) || empty($payment['data'])) {
			return false;
		}

		$payment = $payment['data'];

		if( $amount_id ) {
			$value = $payment['originValue'];
		} else {
			$value = $payment['value'] / pow( 10, $payment['decimal'] );
		}

		$data = array(
			'amount_id' => str_replace( ',', '', $value),
			'currency' => $coin_data['token']['symbol'],
			'order_id' => substr($payment['uoid'], 0, strpos($payment['uoid'],'-' )),
			'status' => 'not_paid',
			'payment_method' => ($amount_id) ? 'amount_id' : 'ezdefi_wallet',
		);

		$this->db->add_exception($data);

		return $this->renderPaymentHtml($payment, $order_data, $coin_data);
	}

	protected function renderPaymentHtml($payment, $order_data, $coin_data)
	{
		$total = $order_data['amount'];
		$discount = $coin_data['discount'];
		$total = $total * (number_format((100 - $discount) / 100, 8));
		$total = $this->convertNotation($total);
		ob_start(); ?>
		<div class="ezdefi-payment" data-paymentid="<?php echo $payment['_id']; ?>">
			<?php
                if((isset($payment['amountId']) && $payment['amountId'] === true)) {
                    $value = $payment['originValue'];
                } else {
                    $value = $payment['value'] / pow( 10, $payment['decimal']);
                }
                
                $value = $this->convertNotation($value);
            ?>
			<p class="exchange">
				<span><?php echo $order_data['currency']; ?> <?php echo $total; ?></span>
				<img width="16" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAQAAAAAYLlVAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAAHdElNRQfjChgQMyxZjA7+AAACP0lEQVRo3u2YvWsUQRTAf8nFQs5LCEY0aCGIB1ErRVMoFpYGTGNlo2AnBxHlrLQJKVSwiV//gqCV4gemEGJhiBYXRAtBDIhICiUGL8GP3Fjs7rs5vN0o5M1LsW+a2XkDv9/MvF12t4B2dDDODqbVOan46zgaVKzwN3A4O4VuarGAo8EZC4VeXnoKJruQK+QKa12hI2VyFyUFhY08Ymfcd1S49feU7VSZ5DPL4qrXGpxuhW/iJj8DgJutTrGJ38vHoPCobUnwg9QN8HeTItzGNP2yF7M85D11lTvhLAPSn2CYpah7R5zmOUmnChrgsrf6p6xPhvfRiAe/slsNnoqHcRketsDDbDw8ZYPvlsR5CzwMSGpICT+WhYdBSR4Ov3p9gbGV8Hr3PEAPx6XvPXZC7sBm3qSvPoRApJCB71KB+jHHERbab34YAZjLSuoW4T+EuYBNHJXC32W+A2taYAN9lgJFHjDZfGsNHUWe4XC8VVHwirD9hBLPZcpM+mN0NQTaHUGR+xySq3vpj1Gd8FfvuKjCyDiC5OyjdklpkSeE0N+aCLF6gNGY8IuCBb4zfklxzFjg4ZRQRi3wB/guB1AOjV9HhUXh3Ibo87zEYw7KpFqUWPUoUWaIrXL9gf18iRSeGPyamGdPYlI2wL/zflPQx4+g8CWu0tN6OiNBwL/5xAQjXhWQFCFc4IqMvOYY3xSKcIHlrPQ5z/UVvSr3wQqRK+QKuYIfVU9hSuGt+L924ZoFvqmgji+kZl6wSI2qtsAfm/EoPAbFFD0AAAAldEVYdGRhdGU6Y3JlYXRlADIwMTktMTAtMjRUMTY6NTE6NDQrMDA6MDBiAik3AAAAJXRFWHRkYXRlOm1vZGlmeQAyMDE5LTEwLTI0VDE2OjUxOjQ0KzAwOjAwE1+RiwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAAASUVORK5CYII=" />
				<span class="currency"><?php echo $value . ' ' . $payment['currency']; ?></span>
			</p>
			<p>You have <span class="count-down" data-endtime="<?php echo $payment['expiredTime']; ?>"></span> to scan this QR Code</p>
			<p>
				<?php
                    if( isset( $payment['amountId'] ) && $payment['amountId'] === true ) {
                        $deepLink = $payment['deepLink'];
                    } else {
                        $deepLink = 'ezdefi://' . $payment['deepLink'];
                    }
				?>
                <a class="qrcode <?php echo (time() > strtotime($payment['expiredTime'])) ? 'expired' : ''; ?>" href="<?php echo $deepLink; ?>" target="_blank">
                    <img class="main" src="<?php echo $payment['qr']; ?>" />
	                <?php if( isset( $payment['amountId'] ) && $payment['amountId'] === true ) : ?>
                        <img class="alt" style="display: none" src="<?php echo 'https://chart.googleapis.com/chart?cht=qr&chl='.$payment['to'].'&chs=200x200&chld=L|0'; ?>" alt="">
	                <?php endif; ?>
                </a>
			</p>
			<?php if(isset( $payment['amountId'] ) && $payment['amountId'] == true) : ?>
                <p class="receive-address">
                    <strong>Address:</strong>
                    <span class="copy-to-clipboard" data-clipboard-text="<?php echo $payment['to']; ?>" title="Copy to clipboard">
                        <span class="copy-content"><?php echo $payment['to']; ?></span>
                        <img src="<?php echo $this->db->getSystemUrl() .  '/assets/img/copy-icon.svg'; ?>" />
                    </span>
                </p>

                <p class="payment-amount">
                    <strong>Amount:</strong>
                    <span class="copy-to-clipboard" data-clipboard-text="<?php echo $value; ?>" title="Copy to clipboard">
                        <span class="copy-content"><?php echo $value; ?></span>
                        <span class="amount"><?php echo $payment['token']['symbol'] ?></span>
                        <img src="<?php echo $this->db->getSystemUrl() . '/assets/img/copy-icon.svg'; ?>" />
                    </span>
                </p>

                <div class="qrcode__info--main">
                    <p class="note">If you get error when scanning this QR Code, please use <a href="" class="changeQrcodeBtn">alternative QR Code</a></p>
                </div>

                <div class="qrcode__info--alt" style="display: none">
                    <p class="note">You have to pay exact amount so that your order can be handled properly.<br/></p>
                    <p class="note">If you have difficulty for sending exact amount, try <a href="" class="ezdefiEnableBtn">ezDeFi Wallet</a></p>
                    <p class="changeQrcode">
                        <a class="changeQrcodeBtn" href="">Use original QR Code</a>
                    </p>
                </div>
			<?php else : ?>
                <p class="app-link-list">
                    <a target="_blank" href="http://ezdefi.com/ios?utm=whmcs-download"><img src="<?php echo $this->db->getSystemUrl() . '/assets/img/ios-icon.png'; ?>" /></a>
                    <a target="_blank" href="http://ezdefi.com/android?utm=whmcs-download"><img src="<?php echo $this->db->getSystemUrl() . '/assets/img/android-icon.png'; ?>" /></a>
                </p>
			<?php endif; ?>
		</div>
		<?php return ob_get_clean();
	}

	protected function convertNotation($value)
	{
		$notation = explode('E', $value);

		if(count($notation) === 2){
			$exp = abs(end($notation)) + strlen($notation[0]);
			$decimal = number_format($value, $exp);
			$value = rtrim($decimal, '.0');
		}

		return $value;
	}

	public function check_invoice($data)
	{
		if(!$this->validate_invoice_data($data)) {
			return;
		}

		$invoiceId = $data['invoice_id'];

		$status = $this->check_invoice_status($invoiceId);

		return $status;
	}

	protected function validate_invoice_data($data)
	{
		if(!isset($data['invoice_id'])) {
			return false;
		}

		$invoiceId = $data['invoice_id'];

		if(empty($invoiceId)) {
			return false;
		}

		return true;
	}

	protected function check_invoice_status($invoiceId)
	{
		return $this->db->getInvoiceStatus($invoiceId);
	}

	public function get_exceptions()
    {
	    $default = array(
		    'amount_id' => '',
		    'currency' => '',
		    'order_id' => '',
		    'clientid' => '',
		    'payment_method' => '',
            'status' => '',
            'confirmed' => '',
            'type' => 'pending'
	    );

	    $params = array_merge($default, $_POST);

	    foreach($params as $column => $param) {
	        if(!in_array($column, array_keys($default))) {
	            unset($params[$column]);
            }
        }

	    $offset = 0;

	    $per_page = 15;

	    if(isset($_POST['page']) && $_POST['page'] > 1) {
		    $offset = $per_page * ($_POST['page'] - 1);
	    }

	    $current_page = (isset($_POST['page'])) ? (int) $_POST['page'] : 1;

        $data = $this->db->get_exceptions($params, $offset, $per_page);

        if(empty($data['data'])) {
        	$current_page = $current_page - 1;
	        $offset = $per_page * ($current_page - 1);
        	$data = $this->db->get_exceptions($params, $offset, $per_page);
        }

	    $data['per_page'] = $per_page;
	    $data['current_page'] = $current_page;
	    $data['last_page'] = ceil($data['total'] / $per_page);

        return $this->json_success_response($data);
    }

    public function get_clients()
    {
        $clients = $this->db->get_clients();

        return $this->json_success_response($clients);
    }

    public function get_unpaid_invoices($data)
    {
        if(isset($data['keyword']) && !empty($data['keyword'])) {
	        $unpaid_invoices = $this->db->get_unpaid_invoice($data['keyword']);
        } else {
	        $unpaid_invoices = $this->db->get_unpaid_invoices();
        }

        if(empty($unpaid_invoices)) {
	        return $this->json_success_response();
        }

	    $currency = $this->db->getDefaultCurrency();

	    foreach($unpaid_invoices as $invoice) {
	        $invoice->date = date('Y/m/d', strtotime($invoice->date));
		    $invoice->duedate = date('Y/m/d', strtotime($invoice->duedate));
		    $invoice->suffix = $currency['suffix'];
		    $invoice->prefix = $currency['prefix'];
	    }

	    return $this->json_success_response($unpaid_invoices);
    }

	public function assign_amount_id($data)
	{
		if(!isset($data['invoice_id']) || !isset($data['exception_id'])) {
			return $this->json_error_response();
		}

        $exception_id = $data['exception_id'];

		$old_invoice_id = ($data['old_invoice_id'] && !empty($data['old_invoice_id'])) ? $data['old_invoice_id'] : null;

		$invoice_id = $data['invoice_id'];

		$invoice = $this->db->get_invoice($invoice_id);

		if(!$invoice) {
			return $this->json_error_response($invoice_id);
		}

        addInvoicePayment(
            $invoice_id,
            '',
            $this->db->getInvoiceTotal($invoice_id),
            0,
            'ezdefi'
        );

        if( $old_invoice_id && $old_invoice_id != $invoice_id && $this->db->get_invoice($invoice_id) ) {
            $this->db->update_invoice_status($old_invoice_id, 'Unpaid');
        }

        $this->db->update_invoice_status($invoice_id, 'Unpaid');

        $this->db->update_exceptions(
            array( 'id' => (int) $exception_id ),
            array(
                'order_id' => $invoice_id,
                'confirmed' => 1
            )
        );

        $this->db->update_exceptions(
            array(
                'order_id' => $invoice_id,
                'explorer_url' => null
            ),
            array(
                'is_show' => 0
            )
        );

		return $this->json_success_response();
	}

	public function reverse_invoice($data)
	{
		if(!isset($data['invoice_id']) || !isset($data['exception_id'])) {
			return $this->json_error_response();
		}

        $exception_id = $data['exception_id'];

		$invoice_id = $data['invoice_id'];

		$invoice = $this->db->get_invoice($invoice_id);

		if(!$invoice) {
			return $this->json_error_response();
		}

		$this->db->update_invoice_status($invoice_id, 'Unpaid');

        $exception = $this->db->get_exception( $exception_id );

        if(is_null($exception->explorer_url) || empty($exception->explorer_url)) {
            $data_update = array(
                'confirmed' => 0
            );
        } else {
            $data_update = array(
                'confirmed' => 0,
                'order_id' => null,
            );
        }

        $this->db->update_exceptions(
            array( 'id' => (int) $exception_id ),
            $data_update
        );

        $this->db->update_exceptions(
            array(
                'order_id' => $invoice_id,
                'explorer_url' => null
            ),
            array(
                'is_show' => 1
            )
        );

		return $this->json_success_response();
	}

	public function delete_exception($data)
	{
		if(!isset($data['exception_id'])) {
		    return $this->json_error_response();
        }

		$this->db->delete_exception($data['exception_id']);
	}

	protected function json_response($code = 200, $data = '') {
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

	protected function json_success_response($data = '') {
		return $this->json_response(200, $data);
	}

	protected function json_error_response($data = '') {
		return $this->json_response(400, $data);
	}
}