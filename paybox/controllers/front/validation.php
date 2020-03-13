
<?php
/**
 *
 * Order Validation Controller
 *
 * @author PayboxMoney <support@paybox.money>
 * @copyright  2020 PayboxMoney
 * @license http://opensource.org/licenses/afl-3.0.php
 */

use PrestaShop\PrestaShop\Adapter\Entity\Customer;

require_once(_PS_MODULE_DIR_ . 'paybox/lib/PG_Signature.php'); // Base Controller

class PayboxValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Processa os dados enviados pelo formulário de pagamento
     */
    public function postProcess()
    {
        /**
         * Get current cart object from session
         */
        $cart = $this->context->cart;
        $authorized = false;

        /**
         * Verify if this module is enabled and if the cart has
         * a valid customer, delivery address and invoice address
         */
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /**
         * Verify if this payment module is authorized
         */
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'paybox') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        /** @var CustomerCore $customer */
        $customer = new Customer($cart->id_customer);

        /**
         * Check if this is a valid customer account
         */
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        Tools::redirect('index.php?fc=module&module=paybox&controller=payment');
    }
}
