<?php
/*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PayboxMoney <support@paybox.money>
*  @copyright  2020 PayboxMoney
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

require_once(_PS_MODULE_DIR_ . 'paybox/paybox.php');
require_once(_PS_MODULE_DIR_ . 'paybox/lib/PG_Signature.php');

class PayboxCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

  public function initContent()
  {
    parent::initContent();

      $cart = $this->context->cart;

      /** @var CustomerCore $customer */
      $customer = new Customer($cart->id_customer);

      /**
       * Check if this is a valid customer account
       */
      if (!Validate::isLoadedObject($customer)) {
          Tools::redirect('index.php?controller=order&step=1');
      }

      $arrRequest = array();
      if(!empty($_POST))
          $arrRequest = $_POST;
      else
          $arrRequest = $_GET;

      $paybox = new Paybox();

      $thisScriptName = PG_Signature::getOurScriptName();
      if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $paybox->secret_key))
          die("Wrong signature");

      /**
       * Place the order
       */
      if (isset($arrRequest['pg_payment_id'])) {

          $arrFields = [
              "pg_merchant_id" => $paybox->merchant_id,
              "pg_payment_id" => $arrRequest['pg_payment_id'],
              "pg_order_id" => $arrRequest['pg_order_id'],
              "pg_salt" => rand(21,43433),
          ];
          $arrFields['pg_sig'] = PG_Signature::make('get_status.php', $arrFields, $paybox->secret_key);
          $json = json_encode($arrFields);

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL,"https://api.paybox.money/get_status.php?" . http_build_query($arrFields));
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          $curlResult = curl_exec($ch);
          $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close ($ch);
          $result = json_decode(json_encode(@simplexml_load_string($curlResult)), true);

          if ($http_code == 200) {
              if ($result['pg_transaction_status'] == 'ok') {
                  $transactionStatus = Configuration::get('PS_OS_PAYMENT');
              } else {
                  $transactionStatus = Configuration::get('PS_OS_ERROR');
              }

              $this->module->validateOrder(
                  (int)$this->context->cart->id,
                  $transactionStatus,
                  (float)$this->context->cart->getOrderTotal(true, Cart::BOTH),
                  $this->module->displayName,
                  null,
                  null,
                  (int)$this->context->currency->id,
                  false,
                  $customer->secure_key
              );
          }
      }

      /**
       * Redirect the customer to the order confirmation page
       */
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

  }

}
