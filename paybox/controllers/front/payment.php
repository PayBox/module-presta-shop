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

class PayboxPaymentModuleFrontController extends ModuleFrontController
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

        $lang_iso_code = $this->context->language->iso_code;
        $currency      = new Currency((int)($cart->id_currency));
        $currency_code = trim($currency->iso_code);
        $amount        = $cart->getOrderTotal(true, 3);
        $total         = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $arrOrderItems = $cart->getProducts();
        $strDescription = '';
        foreach($arrOrderItems as $arrItem){
            $strDescription .= $arrItem['name'];
            if($arrItem['cart_quantity'] > 1)
                $strDescription .= "*".$arrItem['cart_quantity'];
            $strDescription .= "; ";
        }

        $strRequestUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?fc=module&module=paybox&controller=callback';

        $paybox = new Paybox();

        $arrFields = array(
            'pg_merchant_id'        => $paybox->merchant_id,
            'pg_order_id'           => $cart->id,
            'pg_currency'           => $currency_code,
            'pg_amount'             => $amount,
            'pg_testing_mode'       => $paybox->test_mode,
            'pg_description'        => $strDescription,
            'pg_user_ip'            => $_SERVER['REMOTE_ADDR'],
            'pg_language'           => ($lang_iso_code == 'ru') ? 'ru': 'en',
            'pg_result_url'         => $strRequestUrl,
            'pg_success_url'        => $strRequestUrl,
            'pg_failure_url'        => 'http://'.$_SERVER['HTTP_HOST'].'/index.php?controller=history',
            'pg_request_method'     => 'GET',
            'cms_payment_module'    => 'PRESTASHOP 1.7',
            'pg_salt'               => rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
            'pg_user_email'         => $customer->email,
            'pg_user_contact_email' => $customer->email,
        );
        $arrFields['pg_sig'] = PG_Signature::make('payment.php', $arrFields, $paybox->secret_key);

        $query = http_build_query($arrFields);
        $action = 'https://api.paybox.money/payment.php?' . $query;

        $this->context->smarty->assign(array(
            'arrFields' => $arrFields,
            'action' => $action
        ));

      $this->setTemplate('module:paybox/views/templates/front/payment.tpl');

  }

}
