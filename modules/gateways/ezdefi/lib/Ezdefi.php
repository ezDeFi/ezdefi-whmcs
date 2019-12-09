<?php

namespace WHMCS\Module\Gateway\Ezdefi;

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '\includes\invoicefunctions.php';

class Ezdefi {
	private static $instance = null;

	protected $config;

	protected $api;

	public function __construct()
	{
		$this->db = new EzdefiDb();
		$this->api = new EzdefiApi();
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
		$this->db->createAmountIdTable();
		$this->db->createExceptionTable();
		$this->db->addProcedure();
		$this->db->addScheduleEvents();
	}

	public function getMetaData()
	{
		return array(
			'DisplayName' => 'Ezdefi',
			'APIVersion' => '1.1'
		);
	}

	public function getConfig()
	{
		return array(
			'FriendlyName' => array(
				'Type' => 'System',
				'Value' => 'ezDefi',
			),
			'apiUrl' => array(
				'FriendlyName' => 'Gateway API Url',
				'Type' => 'text',
				'Default' => '',
				'Description' => 'Enter your gateway API Url',
			),
			'apiKey' => array(
				'FriendlyName' => 'Gateway API Key',
				'Type' => 'text',
				'Default' => '',
				'Description' => 'Enter your gateway API Key',
			),
			'simpleMethod' => array(
				'FriendlyName' => 'Simple method',
				'Type' => 'yesno',
				'Description' => 'Allow client to pay without using ezDefi wallet',
			),
			'ezdefiWallet' => array(
				'FriendlyName' => 'ezDefi Wallet',
				'Type' => 'yesno',
				'Description' => 'Allow client to pay using ezDefi wallet',
			),
			'variation' => array(
				'FriendlyName' => 'Acceptable Variation',
				'Type' => 'text',
				'Default' => '0.01',
				'Description' => 'Description',
			)
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
		$unpaid_invoices = $this->db->get_unpaid_invoices();
		$currency = $this->db->getDefaultCurrency();
		foreach($unpaid_invoices as $invoice) {
		    $invoice->currency = $currency;
        }
		$data = array(
			'gateway_params' => $gatewayParams,
			'config_url' => $ezdefiConfigUrl,
            'unpaid_invoices' => $unpaid_invoices
		);
		ob_start(); ?>
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/select2.min.css'; ?>">
		<link rel="stylesheet" href="<?php echo $systemUrl . '/modules/gateways/ezdefi/css/ezdefi-admin.css'; ?>">
		<script type="application/json" id="ezdefi-data"><?php echo json_encode( $data ); ?></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/select2.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.validate.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery.blockUI.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/jquery-ui.min.js'; ?>"></script>
		<script src="<?php echo $systemUrl . '/modules/gateways/ezdefi/js/ezdefi-admin.js'; ?>"></script>
		<?php return ob_get_clean();
	}

    public function callbackHandle($invoiceId, $paymentId)
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

	    $amount_id = $payment['value'] / pow( 10, $payment['decimal'] );

	    $currency = $payment['currency'];

	    logTransaction('ezdefi', $_GET, $status);

	    if($status === 'DONE') {
		    $paymentFee = 0;
		    $symbol = $payment['symbol'];
		    $currency = $this->db->getCurrencyBySymbol($symbol);
		    $paymentAmount = $payment['originValue'];
		    $paymentAmount = $paymentAmount / (100 - $currency['discount']) * 100;

		    addInvoicePayment(
			    $invoiceId,
			    $paymentId,
			    $paymentAmount,
			    $paymentFee,
			    'ezdefi'
		    );

		    $this->db->delete_amount_id_exception($amount_id, $currency['symbol']);
	    } elseif($status === 'EXPIRED_DONE') {
		    $this->db->add_uoid_to_exception($amount_id, $currency, $invoiceId);
	    }
    }
}