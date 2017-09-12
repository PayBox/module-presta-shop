<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/platron.php');

var_dump('failure url');
die();

$errors = '';
$result = false;
$rb = new robokassa();

$id_cart = $_REQUEST['InvId'];
$amount = $_REQUEST['OutSum'];
$rb_password1 = Configuration::get('RB_PASSWORD1');
$rb_sign = md5("$amount:$id_cart:$rb_password1");

if(strtoupper($_REQUEST['SignatureValue'])==strtoupper($rb_sign)) {

    $rb->validateOrder($id_cart, _PS_OS_CANCELED_, $amount, $rb->displayName);
    echo $rb->getL('fail');
    Tools::redirectLink(__PS_BASE_URI__.'order.php');
}
?>