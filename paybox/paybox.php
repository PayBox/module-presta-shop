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
*  @author BeGateway <techsupport@ecomcharge.com>
*  @copyright  2018 eComCharge
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


class Paybox extends PaymentModule
{

    private $_html = '';
    private $_postErrors = array();
    public $merchant_id;
    public $secret_key;
    public $test_mode;

  /**
 * predefined test account
 *
 * @var array
 */
  protected $presets = array(
    'test' => array(
      'merchant_id' => '500148',
      'secret_key' => 'ziPedApn2YJfYUjm',
    )
  );

  public function __construct()
  {
    $this->name = 'paybox';
    $this->tab = 'payments_gateways';
    $this->version = '1.7.6';
    $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    $this->author = 'payboxMoney';
    $this->controllers = array('validation');
    $this->need_instance = 1;

    $this->currencies      = true;
    $this->currencies_mode = 'checkbox';
    $this->bootstrap       = true;
    $this->display         = true;

    $config = Configuration::getMultiple(array('PAYBOX_MERCHANT_ID', 'PAYBOX_SECRET_KEY', 'PAYBOX_TEST_MODE'));
    if (isset($config['PAYBOX_MERCHANT_ID']))
      $this->merchant_id = $config['PAYBOX_MERCHANT_ID'];
    if (isset($config['PAYBOX_SECRET_KEY']))
      $this->secret_key = $config['PAYBOX_SECRET_KEY'];
    if (isset($config['PAYBOX_TEST_MODE']))
      $this->test_mode = $config['PAYBOX_TEST_MODE'];

    parent::__construct();

    $this->displayName = $this->l('PayboxMoney');
    $this->description = $this->l('Accept payments with PayBox');
    $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

    if (!count(Currency::checkPaymentCurrencies($this->id))) {
      $this->warning = $this->l('No currency has been set for this module.');
    }
  }

  public function install()
  {
    if (Shop::isFeatureActive()) {
        Shop::setContext(Shop::CONTEXT_ALL);
    }

    if (extension_loaded('curl') == false) {
      $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
      return false;
    }

    $language_code = $this->context->language->iso_code;

    Module::updateTranslationsAfterInstall(false);

    Configuration::updateValue('PAYBOX_ACTIVE_MODE', false);
    Configuration::updateValue('PAYBOX_MERCHANT_ID', $this->presets['test']['merchant_id']);
    Configuration::updateValue('PAYBOX_SECRET_KEY', $this->presets['test']['secret_key']);
    Configuration::updateValue('PAYBOX_TEST_MODE', true);

    // payment titles
    foreach (Language::getLanguages() as $language) {
        if (Tools::strtolower($language['iso_code']) == 'ru') {
            Configuration::updateValue('PAYBOX_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Оплатить онлайн банковской картой через PayboxMoney');
        } else {
            Configuration::updateValue('PAYBOX_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Pay by credit card by PayboxMoney');
        }
    }

    $ow_status = Configuration::get('PAYBOX_STATE_WAITING');
    if ($ow_status === false)
    {
      $orderState = new OrderState();
    }
    else {
      $orderState = new OrderState((int)$ow_status);
    }

    $orderState->name = array();

    foreach (Language::getLanguages() as $language) {
      if (Tools::strtolower($language['iso_code']) == 'ru') {
        $orderState->name[$language['id_lang']] = 'Ожидание завершения оплаты';
      } else {
        $orderState->name[$language['id_lang']] = 'Awaiting for payment';
      }
    }

    $orderState->send_email  = false;
    $orderState->color       = '#4169E1';
    $orderState->hidden      = false;
    $orderState->module_name = 'paybox';
    $orderState->delivery    = false;
    $orderState->logable     = false;
    $orderState->invoice     = false;
    $orderState->unremovable = true;
    $orderState->save();

    Configuration::updateValue('PAYBOX_STATE_WAITING', (int)$orderState->id);

    copy(_PS_MODULE_DIR_ . 'paybox/views/img/logo.svg', _PS_IMG_DIR_ .'os/'.(int)$orderState->id.'.gif');

    return parent::install() &&
      $this->registerHook('backOfficeHeader') &&
      $this->registerHook('payment') &&
      $this->registerHook('paymentOptions') &&
      $this->registerHook('displayPaymentReturn');
  }

  public function uninstall()
  {
    Configuration::deleteByName('PAYBOX_ACTIVE_MODE');
    Configuration::deleteByName('PAYBOX_MERCHANT_ID');
    Configuration::deleteByName('PAYBOX_SECRET_KEY');
    Configuration::deleteByName('PAYBOX_TEST_MODE');

    $orderStateId = Configuration::get('PAYBOX_STATE_WAITING');
    if ($orderStateId) {
      $orderState     = new OrderState();
      $orderState->id = $orderStateId;
      $orderState->delete();
      unlink(_PS_IMG_DIR_ .'os/'.(int)$orderState->id.'.gif');
    }

    Configuration::deleteByName('PAYBOX_STATE_WAITING');

    // payment titles
    foreach (Language::getLanguages() as $language) {
      Configuration::deleteByName('PAYBOX_TITLE_CREDIT_CARD_'. $language['iso_code']);
    }

    return $this->unregisterHook('backOfficeHeader') &&
      $this->unregisterHook('paymentOptions' ) &&
      $this->unregisterHook('displayPaymentReturn') &&
      $this->unregisterHook('payment') &&
      parent::uninstall();
  }

    protected function _postValidation()
    {
        if (Tools::isSubmit('submitPayboxModule')) {
            if (!Tools::getValue('PAYBOX_MERCHANT_ID')) {
                $this->_postErrors[] = $this->l('Merchant ID are required.');
            } elseif (!Tools::getValue('PAYBOX_SECRET_KEY')) {
                $this->_postErrors[] = $this->l('Merchant Key is required.');
            }
        }
    }

  /**
   * Load the configuration form
   */
  public function getContent()
  {
      /**
       * If values have been submitted in the form, process.
       */
      if (((bool)Tools::isSubmit('submitPayboxModule')) == true) {
          $this->_postValidation();
          if (!count($this->_postErrors)) {
              $this->_postProcess();
          } else {
              foreach ($this->_postErrors as $err) {
                  $this->_html .= $this->displayError($err);
              }
          }
      }

      $this->context->smarty->assign('module_dir', $this->_path);
      $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

      $this->_html .= $output;
      $this->_html .= $this->renderForm();

      return $this->_html;
  }

  /**
   * Save form data.
   */
  protected function _postProcess()
  {
      $form_values = $this->getConfigFieldsValues();

      foreach (array_keys($form_values) as $key) {
          $value = Tools::getValue($key);
          Configuration::updateValue($key, trim($value));
      }

      foreach (Language::getLanguages() as $language) {
          if (Tools::strtolower($language['iso_code']) == 'ru') {
              Configuration::updateValue('PAYBOX_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Оплатить онлайн банковской картой через PayboxMoney');
          } else {
              Configuration::updateValue('PAYBOX_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Pay by credit card by PayboxMoney');
          }
      }

      $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
  }

  public function getConfigForm()
  {
    return array(
      'form' => array(
        'legend' => array(
          'title' => $this->l('Settings'),
          'icon'  => 'icon-cogs'
        ),
        'input'  => array(
            array(
                'type'    => 'switch',
                'label'   => $this->l('Active'),
                'name'    => 'PAYBOX_ACTIVE_MODE',
                'is_bool' => true,
                'values'  => array(
                    array(
                        'id'    => 'active_on',
                        'value' => true,
                        'label' => $this->l('Enabled')
                    ),
                    array(
                        'id'    => 'active_off',
                        'value' => false,
                        'label' => $this->l('Disabled')
                    )
                ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Merchant ID'),
                'name' => 'PAYBOX_MERCHANT_ID',
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Merchant Key'),
                'name' => 'PAYBOX_SECRET_KEY',
                'required' => true,
            ),
          array(
            'type' => 'switch',
            'label' => $this->l('Test mode'),
            'name' => 'PAYBOX_TEST_MODE',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Test')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Live')
              )
            )
          ),
          array(
            'col'  => 8,
            'type' => 'html',
            'name' => '<hr>',
          )
        ),
        'submit' => array(
          'title' => $this->l('Save')
        )
      )
    );
  }

  public function renderForm()
  {

    $helper = new HelperForm();

    $helper->show_toolbar             = false;
		$helper->table                    = $this->table;
    $helper->module                   = $this;
    $helper->default_form_language    = $this->context->language->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
		$helper->identifier               = $this->identifier;
		$helper->submit_action            = 'submitPayboxModule';

    $helper->currentIndex  = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token         = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages'    => $this->context->controller->getLanguages(),
			'id_language'  => $this->context->language->id
		);

		return $helper->generateForm(array($this->getConfigForm()));
  }

  public function getConfigFieldsValues()
    {
        $id_lang = $this->context->language->iso_code;
        return array(
            'PAYBOX_ACTIVE_MODE' => Tools::getValue('PAYBOX_ACTIVE_MODE', Configuration::get('PAYBOX_ACTIVE_MODE', false)),
            'PAYBOX_MERCHANT_ID' => Tools::getValue('PAYBOX_MERCHANT_ID', Configuration::get('PAYBOX_MERCHANT_ID', $this->presets['test']['merchant_id'])),
            'PAYBOX_SECRET_KEY' => Tools::getValue('PAYBOX_SECRET_KEY', Configuration::get('PAYBOX_SECRET_KEY', $this->presets['test']['secret_key'])),
            'PAYBOX_TEST_MODE' => Tools::getValue('PAYBOX_TEST_MODE', Configuration::get('PAYBOX_TEST_MODE', true)),
            'PAYBOX_TITLE_CREDIT_CARD_'. $id_lang => Tools::getValue('PAYBOX_TITLE_CREDIT_CARD_'. $id_lang, Configuration::get('PAYBOX_TITLE_CREDIT_CARD_'. $id_lang)),
        );
    }

  public function hookPaymentOptions($params)
  {
      /**
     * Verify if this module is active
     */
      if (!$this->active) {
          return;
      }

      if (false == Configuration::get('PAYBOX_ACTIVE_MODE', false)) {
          return array();
      }

      $this->smarty->assign('module_dir', $this->_path);

      $id_lang = $this->context->language->iso_code;

      /**
       * Form action URL. The form data will be sent to the
       * validation controller when the user finishes
       * the order process.
       */
      $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);

      /**
       * Assign the url form action to the template var $action
       */
      $this->smarty->assign(['action' => $formAction]);

      /**
       *  Load form template to be displayed in the checkout step
       */
      $paymentForm = $this->fetch('module:paybox/views/templates/hook/payment_options.tpl');

      /**
       * Create a PaymentOption object containing the necessary data
       * to display this module in the checkout
       */
      $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
      $newOption->setModuleName($this->displayName)
          ->setCallToActionText($this->displayName)
          ->setAction($formAction)
          ->setForm($paymentForm)
          ->setCallToActionText(Configuration::get('PAYBOX_TITLE_CREDIT_CARD_'. $id_lang))
          ->setLogo(Media::getMediaPath(
              _PS_MODULE_DIR_ . $this->name . '/views/img/logo.svg'
          ));

      $payment_options = array(
          $newOption
      );

      return $payment_options;
  }

  public function hookBackOfficeHeader()
  {
    if (Tools::getValue('configure') == $this->name) {
      $this->context->controller->addJS($this->_path . 'views/js/back.js');
    }
  }

  public function hookDisplayPaymentReturn($params)
  {
    if ($this->active == false) {
        return false;
    }
    /** @var order $order */
    $order = $params['order'];
    $currency = new Currency($order->id_currency);

    if (strcasecmp($order->module, 'paybox') != 0) {
        return false;
    }

    if (Tools::getValue('status') != 'failed' && $order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
        $this->smarty->assign('status', 'ok');
    }

    $this->smarty->assign(
        array(
            'id_order'  => $order->id,
            'reference' => $order->reference,
            'params'    => $params,
            'total'     => Tools::displayPrice($order->getOrdersTotalPaid(), $currency, false),
        )
    );

    return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
  }

  public function init_paybox() {
    \BeGateway\Settings::$checkoutBase = 'https://api.paybox.money/payment.php';
    \BeGateway\Settings::$shopId  = trim(Configuration::get('PAYBOX_MERCHANT_ID'));
    \BeGateway\Settings::$shopKey = trim(Configuration::get('PAYBOX_SECRET_KEY'));
  }
}
