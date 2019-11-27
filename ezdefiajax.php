<?php

require_once 'init.php';

use WHMCS\Module\Gateway\Ezdefi\EzdefiAjax;

$ajax = new EzdefiAjax();

$data = $_POST;

if(!isset($data['action'])) {
	exit;
}

$action = $_POST['action'];

if (method_exists($ajax, $action)) {
	echo $ajax->{$action}($data);
	exit;
} else {
	exit;
}