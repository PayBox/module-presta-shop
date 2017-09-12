<?php
require_once 'PG_Signature.php';

class platron extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();
    public $pl_merchant_id;
    public $pl_secret_key;
    public $pl_lifetime;
    public $pl_testmode;

    public function __construct()
    {
        $this->name = 'platron';        
        $this->tab = 'Payment';
        $this->version = 1.0;
        $this->author = 'Platron';
        
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        
        $config = Configuration::getMultiple(array('PL_MERCHANT_ID', 'PL_SECRET_KEY', 'PL_LIFETIME', 'PL_TESTMODE'));    
        if (isset($config['PL_MERCHANT_ID']))
            $this->pl_merchant_id = $config['PL_MERCHANT_ID'];
        if (isset($config['PL_SECRET_KEY']))
            $this->pl_secret_key = $config['PL_SECRET_KEY'];
        if (isset($config['PL_LIFETIME']))
            $this->pl_lifetime = $config['PL_LIFETIME'];
        if (isset($config['PL_TESTMODE']))
            $this->pl_testmode = $config['PL_TESTMODE'];
            
        parent::__construct();
        
        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->displayName = 'PayBox';
        $this->description = $this->l('Accept payments with PayBox');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');
    }        

    public function install()
    {        
        if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn'))
            return false;
        
        Configuration::updateValue('PL_MERCHANT_ID', '');
        Configuration::updateValue('PL_SECRET_KEY', '');
        Configuration::updateValue('PL_LIFETIME', '');
        Configuration::updateValue('PL_TESTMODE', '1');
        
        return true;
    }
    
    public function uninstall()
    {
        Configuration::deleteByName('PL_MERCHANT_ID');
        Configuration::deleteByName('PL_SECRET_KEY');
        Configuration::deleteByName('PL_LIFETIME');
        Configuration::deleteByName('PL_TESTMODE');
        
        parent::uninstall();
    }
    
    private function _postValidation()
    {
        if (isset($_POST['btnSubmit']))
        {
            if (empty($_POST['pl_merchant_id']))
                $this->_postErrors[] = $this->l('Merchant ID is required');
            elseif (empty($_POST['pl_secret_key']))
                $this->_postErrors[] = $this->l('Secret key is required');
        }
    }

    private function _postProcess()
    {
        if (isset($_POST['btnSubmit']))
        {
            if(!isset($_POST['pl_testmode']))
                $_POST['pl_testmode'] = 0;
            
            Configuration::updateValue('PL_MERCHANT_ID', $_POST['pl_merchant_id']);
            Configuration::updateValue('PL_SECRET_KEY', $_POST['pl_secret_key']);
            Configuration::updateValue('PL_LIFETIME', $_POST['pl_lifetime']);
            Configuration::updateValue('PL_TESTMODE', $_POST['pl_testmode']);
        }
        $this->_html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('OK').'" /> '.$this->l('Settings updated').'</div>';
    }
    
    private function _displayRb()
    {
        $this->_html .= '<img src="../modules/platron/paybox.png" style="float:left; margin-right:15px;"><b>'.$this->l('This module allows you to accept payments by PayBox.').'</b><br /><br />
        '.$this->l('You need to register on the site').' <a href="https://paybox.kz/join.php" target="blank">paybox.kz</a> <br /><br /><br />';
    }
    
    private function _displayForm()
    {
        $bTestMode = htmlentities(Tools::getValue('pl_testmode', $this->pl_testmode), ENT_COMPAT, 'UTF-8');     
        $checked = '';
        if($bTestMode)
            $checked = 'checked="checked"';
        
        $this->_html .=
        '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
            <fieldset>
            <legend><img src="../img/admin/contact.gif" />'.$this->l('Contact details').'</legend>
                <table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
                    <tr><td colspan="2">'.$this->l('Please specify required data').'.<br /><br /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Merchant ID').'</td><td><input type="text" name="pl_merchant_id" value="'.htmlentities(Tools::getValue('pl_merchant_id', $this->pl_merchant_id), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Secret key').'</td><td><input type="text" name="pl_secret_key" value="'.htmlentities(Tools::getValue('pl_secret_key', $this->pl_secret_key), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Lifetime').'</td><td><input type="text" name="pl_lifetime" value="'.htmlentities(Tools::getValue('pl_lifetime', $this->pl_lifetime), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Testmode').'</td>
                        <td>
                            <input type="checkbox" name="pl_testmode" value="1" '.$checked.'/>
                        </td>
                    </tr>
                    <tr><td colspan="2" align="center"><br /><input class="button" name="btnSubmit" value="'.$this->l('Update settings').'" type="submit" /></td></tr>
                </table>
            </fieldset>
        </form>';
    }

    public function getContent()
    {
        $this->_html = '<h2>'.$this->displayName.'</h2>';

        if (!empty($_POST))
        {
            $this->_postValidation();
            if (!sizeof($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors AS $err)
                    $this->_html .= '<div class="alert error">'. $err .'</div>';
        }
        else
            $this->_html .= '<br />';

        $this->_displayRb();
        $this->_displayForm();

        return $this->_html;
    }
    
    public function hookPayment($params)
    {
        global $smarty;

        $cookie = $this->context->cookie;
        $customer = new Customer((int)$cookie->id_customer);
        $nTotalPrice = $params['cart']->getOrderTotal(true, 3);
        
        $arrOrderItems = $params['cart']->getProducts();
        foreach($arrOrderItems as $arrItem){
            $strDescription .= $arrItem['name'];
            if(!empty($arrItem['attributes_small']))
                $strDescription .= " ".$arrItem['attributes_small'];
            if($arrItem['cart_quantity'] > 1)
                $strDescription .= "*".$arrItem['cart_quantity'];
            $strDescription .= "; ";
        }
        
        $objLang = new LanguageCore($cookie->id_lang);
        $strRequestUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/modules/platron/callback.php';
        
        $arrFields = array(
            'pg_merchant_id'        => $this->pl_merchant_id,
            'pg_order_id'           => $params['cart']->id,
            'pg_currency'           => $this->getCurrency()->iso_code,
            'pg_amount'             => $nTotalPrice,
            'pg_lifetime'           => $this->pl_lifetime?$this->pl_lifetime*60:0,
            'pg_testing_mode'       => $this->pl_testmode,
            'pg_description'        => $strDescription,
            'pg_user_ip'            => $_SERVER['REMOTE_ADDR'],
            'pg_language'           => ($objLang->iso_code == 'ru') ? 'ru': 'en',
            'pg_check_url'          => $strRequestUrl,
            'pg_result_url'         => $strRequestUrl,
            'pg_success_url'        => 'http://'.$_SERVER['HTTP_HOST'].'/order-history',
            'pg_failure_url'        => 'http://'.$_SERVER['HTTP_HOST'].'/order-history',
            'pg_request_method'     => 'GET',
            'cms_payment_module'    => 'PRESTASHOP',
            'pg_salt'               => rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
            'pg_user_email'         => $cookie->email,
            'pg_user_contact_email' => $cookie->email,
        );
        $arrFields['pg_sig'] = PG_Signature::make('payment.php', $arrFields, $this->pl_secret_key);
        
        $smarty->assign('arrFields', $arrFields);

        return $this->display(__FILE__, 'platron.tpl');
    }
    
    public function getL($key)
    {
        $translations = array(
            'success'=> 'PayBox transaction is carried out successfully.',
            'fail'=> 'PayBox transaction is refused.'
        );
        return $translations[$key];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
            return ;

        return $this->display(__FILE__, 'confirmation.tpl');
    }
    
}

?>
