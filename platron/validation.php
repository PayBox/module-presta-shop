<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/platron.php');
require_once 'PG_Signature.php';

$arrRequest = $_POST;
$secure_cart = explode('_', $arrRequest['pg_order_id']);
$cart = new Cart((int)($secure_cart[0]));
$customer = new Customer((int)$cart->id_customer);

$platron = new platron();

if(empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], 'payment.php', $arrRequest, $platron->pl_secret_key)){
	$platron->validateOrder((int)($secure_cart[0]), Configuration::get('PS_OS_ERROR'), 0, $platron->displayName, 'Wrong signature', array(), NULL, false, $customer->secure_key);
	Tools::redirect($arrRequest['pg_failure_url']);
}
else {
	$platron->validateOrder((int)($secure_cart[0]), Configuration::get('PS_OS_BANKWIRE'), (float)($arrRequest['pg_amount']), $platron->displayName, 'Wait for pay', array(), NULL, false, $customer->secure_key);

	echo "<form action='https://api.paybox.money/payment.php' method='POST' id='platron_payment_form'>";
	foreach ($_POST as $key => $field)
	{
		echo "<input type='hidden' value='$field' name='$key'>";
		$params[] = $key."=".$field;
	}
	echo "
		<script type='text/javascript'>
			setTimeout(function () {
			document.getElementById('platron_payment_form').submit();
			}, 10);
		</script>";
}
