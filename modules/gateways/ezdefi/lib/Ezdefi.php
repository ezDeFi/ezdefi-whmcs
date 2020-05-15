<?php

namespace WHMCS\Module\Gateway\Ezdefi;

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/includes/invoicefunctions.php';

class Ezdefi {
	private static $instance = null;

	protected $config;

	protected $api;

	protected $db;

	protected $ajax;

	protected $version = '2.0.0';

	public function __construct()
	{
		$this->db = new EzdefiDb();
		$this->api = new EzdefiApi();
		$this->ajax = new EzdefiAjax();
	}

	public static function instance()
	{
		if(is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init()
	{
		$this->db->createExceptionTable();
		$this->db->addScheduleEvents();

        $currentVersion = $this->db->getVersion();

        if(is_null($currentVersion) || version_compare($currentVersion, $this->version) < 0) {
            $this->db->upgradeDatabase($currentVersion, $this->version);
        }
	}

	public function getMetaData()
	{
		return array(
			'DisplayName' => 'Pay with cryptocurrencies',
			'APIVersion' => '1.1'
		);
	}

	public function getConfig()
	{
		return array(
			'FriendlyName' => array(
				'Type' => 'System',
				'Value' => 'ezDeFi',
			),
			'apiUrl' => array(
				'FriendlyName' => 'Gateway API Url',
				'Type' => 'text',
				'Default' => 'https://merchant-api.ezdefi.com/api',
				'Description' => 'Enter your gateway API Url',
			),
			'apiKey' => array(
				'FriendlyName' => 'Gateway API Key',
				'Type' => 'text',
				'Description' => sprintf('<a style="text-decoration: underline" target="_blank" href="%s">Register to get API Key</a>', 'https://merchant.ezdefi.com/register?utm_source=whmcs-download' ),
			),
            'publicKey' => array(
                'FriendlyName' => 'Website ID',
                'Type' => 'text',
                'Description' => 'Enter your website\'s ID',
            ),
		);
	}

	public function getLink(array $params)
	{
		$systemUrl = $this->db->getSystemUrl();
		$formUrl = $systemUrl . 'ezdefipayment.php';

		$form = '<form action="'.$formUrl.'" method="POST">';
		$form .= '<input type="hidden" name="amount" value="'. $params['amount'] .'"/>';
		$form .= '<input type="hidden" name="currency" value="'. $params['currency'] .'"/>';
		$form .= '<input type="hidden" name="uoid" value="'. $params['invoiceid'] .'"/>';
		$form .= '<input type="submit" value="'. $params['langpaynow'] .'"/>';
		$form .= '</form>';

		return $form;
	}

	public function getAdminFooterOutput($gatewayParams)
	{
		$systemUrl = $this->db->getSystemUrl();
		$ezdefiConfigUrl = $systemUrl . 'ezdefiajax.php';
		$ezdefiAdminUrl = $systemUrl . 'admin/';
		$data = array(
			'gateway_params' => $gatewayParams,
			'config_url' => $ezdefiConfigUrl,
			'admin_url' => $ezdefiAdminUrl,
            'system_url' => $systemUrl
		);
		ob_start(); ?>
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/select2.min.css'; ?>">
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/ezdefi-admin.css'; ?>">
        <link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/ezdefi-exception.css'; ?>">
		<script type="application/json" id="ezdefi-data"><?php echo json_encode( $data ); ?></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/select2.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.validate.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.blockUI.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery-ui.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/ezdefi-admin.js'; ?>"></script>
        <script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/ezdefi-exception.js'; ?>"></script>
		<?php return ob_get_clean();
	}

    public function callbackHandle($data)
    {
	    if(isset($data['uoid'] ) && isset($data['paymentid'])) {
		    $invoiceId = $data['uoid'];
		    $paymentId = $data['paymentid'];

		    return $this->process_payment_callback($invoiceId, $paymentId);
	    }

	    if(
		    isset($data['value']) && isset($data['explorerUrl']) &&
		    isset($data['currency']) && isset($data['id']) &&
		    isset($data['decimal'])
	    ) {
		    $value = $data['value'];
		    $decimal = $data['decimal'];
		    $value = $value / pow(10, $decimal);
		    $explorerUrl = $data['explorerUrl'];
		    $currency = $data['currency'];
		    $id = $data['id'];

		    return $this->process_transaction_callback($value, $explorerUrl, $currency, $id);
	    }

	    die();
    }

    protected function process_transaction_callback($value, $explorerUrl, $currency, $id)
    {
	    $transaction = $this->api->getTransaction($id);

	    if(!$transaction || $transaction['status'] != 'ACCEPTED') {
		    die();
	    }

	    $data = array(
		    'amount_id' => $this->sanitize_float_value($value),
		    'currency' => $currency,
		    'explorer_url' => $explorerUrl,
	    );

	    $this->db->add_exception($data);
    }

    protected function process_payment_callback($invoiceId, $paymentId)
    {
	    $invoiceId = substr( $invoiceId, 0, strpos( $invoiceId,'-' ) );
	    $invoiceId = checkCbInvoiceID($invoiceId, 'ezdefi');

	    checkCbTransID($paymentId);

	    $response = $this->api->getPayment($paymentId);

	    $payment = json_decode($response, true);

	    if($payment['code'] == -1 && isset($payment['error'])) {
		    die();
	    }

	    $payment = $payment['data'];

	    $status = $payment['status'];

	    if(isset($payment['amountId']) && $payment['amountId'] === true) {
	        $payment_method = 'amount_id';
		    $amount_id = $payment['originValue'];
	    } else {
            $payment_method = 'ezdefi_wallet';
		    $amount_id = $payment['value'] / pow( 10, $payment['decimal'] );
	    }

        $explorer_url = $payment['explorer']['tx'] . $payment['transactionHash'];

        if($status != 'DONE' && $status != 'EXPIRED_DONE') {
            die();
        }

	    logTransaction('ezdefi', $_GET, $status);

	    if($status === 'DONE') {
		    addInvoicePayment(
			    $invoiceId,
			    $paymentId,
                $this->db->getInvoiceTotal($invoiceId),
			    0,
			    'ezdefi'
		    );

		    $this->db->add_invoice_note($invoiceId, "Explorer URL: $explorer_url");

            if( $payment_method === 'ezdefi_wallet' ) {
                $this->db->delete_exceptions( array(
                    'order_id' => $invoiceId
                ) );
                die();
            }
	    }

        $this->db->update_exceptions(
            array(
                'order_id' => $invoiceId,
                'payment_method' => $payment_method,
            ),
            array(
                'amount_id' => $this->sanitize_float_value( $amount_id ),
                'currency' => $payment['token']['symbol'],
                'status' => strtolower($status),
                'explorer_url' => $explorer_url
            ),
            1
        );

        $this->db->delete_exceptions(array(
            'order_id' => $invoiceId,
            'explorer_url' => null,
        ));

	    die();
    }

	protected function sanitize_float_value( $value )
	{
		$notation = explode('E', $value);

		if(count($notation) === 2){
			$exp = abs(end($notation)) + strlen($notation[0]);
			$decimal = number_format($value, $exp);
			$value = rtrim($decimal, '.0');
		}

		return str_replace( ',', '', $value );
	}
}