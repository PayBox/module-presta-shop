<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/platron.php');
require_once 'PG_Signature.php';

$platronDriver = new platron();
$arrRequest = array();
if(!empty($_POST)) 
	$arrRequest = $_POST;
else
	$arrRequest = $_GET;

$thisScriptName = PG_Signature::getOurScriptName();
if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $platronDriver->pl_secret_key))
	die("Wrong signature");

$arrStatuses = array(
	Configuration::get('PS_OS_CHEQUE') => 'ожидание платежа по квитанции',
	Configuration::get('PS_OS_PAYMENT') => 'Платеж принят',
	Configuration::get('PS_OS_PREPARATION') => 'В процессе подготовки',
	Configuration::get('PS_OS_SHIPPING') => 'Отправлено',
	Configuration::get('PS_OS_DELIVERED') => 'Доставлено',
	Configuration::get('PS_OS_CANCELED') => 'Отклонено',
	Configuration::get('PS_OS_REFUND') => 'Возврат денег',
	Configuration::get('PS_OS_ERROR') => 'Ошибка оплаты',
	Configuration::get('PS_OS_OUTOFSTOCK') => 'Данного товара нет на складе',
	Configuration::get('PS_OS_BANKWIRE') => 'В ожидании оплаты банком',
	Configuration::get('PS_OS_WS_PAYMENT') => 'Платеж принят',
);


$arrPendingStatuses = array(
	Configuration::get('PS_OS_CHEQUE'),
	Configuration::get('PS_OS_PREPARATION'),
	Configuration::get('PS_OS_BANKWIRE'),
);

$arrOkStatuses = array(
	Configuration::get('PS_OS_PAYMENT'),
	Configuration::get('PS_OS_WS_PAYMENT'),
);

$arrFailedStatuses = array(
	Configuration::get('PS_OS_ERROR'),
);

$id_order = Order::getOrderByCartId($arrRequest['pg_order_id']);
$order = new Order($id_order);

if(!isset($arrRequest['pg_result'])){
	$bCheckResult = 0;
	if(empty($order) || !in_array($order->current_state, $arrPendingStatuses))
		$error_desc = "Товар не доступен. Либо заказа нет, либо его статус " . $arrStatuses[$order->current_state];	
	elseif($arrRequest['pg_amount'] != $order->total_paid)
		$error_desc = "Неверная сумма";
	else
		$bCheckResult = 1;
	
	$arrResponse['pg_salt']              = $arrRequest['pg_salt']; // в ответе необходимо указывать тот же pg_salt, что и в запросе
	$arrResponse['pg_status']            = $bCheckResult ? 'ok' : 'error';
	$arrResponse['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
	$arrResponse['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResponse, $platronDriver->pl_secret_key);

	$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
	$objResponse->addChild('pg_salt', $arrResponse['pg_salt']);
	$objResponse->addChild('pg_status', $arrResponse['pg_status']);
	$objResponse->addChild('pg_error_description', $arrResponse['pg_error_description']);
	$objResponse->addChild('pg_sig', $arrResponse['pg_sig']);

}
else{
	$bResult = 0;
	if(empty($order) || !in_array($order->current_state, array_merge($arrPendingStatuses, $arrOkStatuses, $arrFailedStatuses)))
		$strResponseDescription = "Товар не доступен. Либо заказа нет, либо его статус " . $arrStatuses[$order->current_state];	
	elseif($arrRequest['pg_amount'] != $order->total_paid)
		$strResponseDescription = "Неверная сумма";
	else {
		$bResult = 1;
		$strResponseStatus = 'ok';
		$strResponseDescription = "Оплата принята";
		if ($arrRequest['pg_result'] == 1)
			// Установим статус оплачен
			$order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
		else
			// Установим отказ
			$order->setCurrentState(Configuration::get('PS_OS_ERROR'));
	}
	if(!$bResult)
		if($arrRequest['pg_can_reject'] == 1)
			$strResponseStatus = 'rejected';
		else
			$strResponseStatus = 'error';
	
	$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
	$objResponse->addChild('pg_salt', $arrRequest['pg_salt']); // в ответе необходимо указывать тот же pg_salt, что и в запросе
	$objResponse->addChild('pg_status', $strResponseStatus);
	$objResponse->addChild('pg_description', $strResponseDescription);
	$objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $platronDriver->pl_secret_key));
}

header("Content-type: text/xml");
echo $objResponse->asXML();
die();
?>
