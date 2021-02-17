<?php
/**
* 2007-2021 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatsProfitMargin extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'statsprofitmargin';
        $this->tab = 'administration';
        $this->version = '0.1';
        $this->author = 'Rafa Soler';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Profit margin calculator');
        $this->description = $this->l('Displays a column with the profit margin for each order in the "Orders" tab.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook('displayAdminOrderTabLink') &&
            $this->registerHook('displayAdminOrderTabContent') &&
            $this->registerHook('actionOrderGridDefinitionModifier') &&
            $this->registerHook('actionOrderGridPresenterModifier');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED');
        Configuration::deleteByName('PROFITMARGIN_DEFAULT_SHIPPING_COST');
        Configuration::deleteByName('PROFITMARGIN_SHIPPING_COST_TAX');

        foreach ($this->getAvailableTaxes() as $tax) {
            Configuration::deleteByName("PROFITMARGIN_EQUIVALENCE_SURCHARGE_{$tax['id_tax']}");
        }

        foreach ($this->getAvailablePaymentModules() as $payment_module) {
            Configuration::deleteByName("PROFITMARGIN_PAYMENT_FEE_BASE_{$payment_module['id_module']}");
            Configuration::deleteByName("PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_{$payment_module['id_module']}");
        }

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitStatsProfitMarginModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitStatsProfitMarginModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $config_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Equivalence surcharge'),
                        'name' => 'PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable equivalence surcharge when calculating profit'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );


        $available_taxes = $this->getAvailableTaxes();

        foreach ($available_taxes as $tax) {
            $config_form['form']['input'][] = array(
                'type' => 'text',
                'name' => "PROFITMARGIN_EQUIVALENCE_SURCHARGE_{$tax['id_tax']}",
                'label' => $this->l($tax['name']),
                'desc' => $this->l('Equivalence surcharge'),
                'suffix' => '%', 
                'col' => 1,
            );
        }

        $config_form['form']['input'][] = array(
            'type' => 'text',
            'name' => 'PROFITMARGIN_DEFAULT_SHIPPING_COST',
            'label' => $this->l('Default shipping cost'),
            'suffix' => '€', 
            'col' => 1,
        );

        $config_form['form']['input'][] = array(
            'type' => 'radio',
            'name' => 'PROFITMARGIN_SHIPPING_COST_TAX',
            'label' => $this->l('Shipping cost tax'),
            'values' => array()
        );
        
        foreach ($available_taxes as $tax) {
            $shipping_cost_tax = &$config_form['form']['input'];
            $shipping_cost_tax[array_key_last($shipping_cost_tax)]['values'][] = array(
                'id' => "tax_{$tax['id_tax']}",
                'value' => $tax['id_tax'],
                'label' => $this->l($tax['name'])
            );
        }

        
        $available_payment_modules = $this->getAvailablePaymentModules();

        foreach ($available_payment_modules as $payment_module) {
            $config_form['form']['input'][] = array(
                'type' => 'text',
                'name' => "PROFITMARGIN_PAYMENT_FEE_BASE_{$payment_module['id_module']}",
                'label' => $this->l($payment_module['name']),
                'desc' => $this->l('Base fee'),
                'suffix' => '€', 
                'col' => 1,
            );
            $config_form['form']['input'][] = array(
                'type' => 'text',
                'name' => "PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_{$payment_module['id_module']}",
                'desc' => $this->l('Percentage fee'),
                'suffix' => '%',
                'col' => 1,
            );
        }

        return $config_form;
    }

    protected function getAvailableTaxes() {
        $sql = 'SELECT DISTINCT t.`id_tax`, tl.`name`
                FROM `' . _DB_PREFIX_ . 'tax_lang` tl
                 INNER JOIN `' . _DB_PREFIX_ . 'tax` t
                    ON t.`id_tax`=tl.`id_tax`
                 INNER JOIN `' . _DB_PREFIX_ . 'tax_rule` tr
                    ON tr.`id_tax`=t.`id_tax`
                 INNER JOIN `' . _DB_PREFIX_ . 'country` c 
                    ON c.`id_country`=tr.`id_country`
                 INNER JOIN `' . _DB_PREFIX_ . 'tax_rules_group` trg
                    ON trg.`id_tax_rules_group`=tr.`id_tax_rules_group`
                WHERE t.`active`=1 AND trg.active=1 AND c.`active`=1';

        $taxes = Db::getInstance()->executeS($sql);

        return $taxes;
    }

    protected function getAvailablePaymentModules() {
        $sql = 'SELECT m.`id_module`, o.`payment` AS `name`
                FROM `' . _DB_PREFIX_ . 'module` m 
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o 
                    ON o.`module`=m.`name` 
                WHERE m.`active`=1 
                GROUP BY m.`id_module`';

        $payment_modules = Db::getInstance()->executeS($sql);

        return $payment_modules;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $config_form_values = array(
            'PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED' => Configuration::get('PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED', false),
            'PROFITMARGIN_DEFAULT_SHIPPING_COST' => Configuration::get('PROFITMARGIN_DEFAULT_SHIPPING_COST', null),
            'PROFITMARGIN_SHIPPING_COST_TAX' => Configuration::get('PROFITMARGIN_SHIPPING_COST_TAX', 0),
        );

        foreach ($this->getAvailableTaxes() as $tax) {
            $config_form_values["PROFITMARGIN_EQUIVALENCE_SURCHARGE_{$tax['id_tax']}"] = Configuration::get("PROFITMARGIN_EQUIVALENCE_SURCHARGE_{$tax['id_tax']}", 0);
        }

        foreach ($this->getAvailablePaymentModules() as $payment_module) {
            $config_form_values["PROFITMARGIN_PAYMENT_FEE_BASE_{$payment_module['id_module']}"] = Configuration::get("PROFITMARGIN_PAYMENT_FEE_BASE_{$payment_module['id_module']}", 0);
            $config_form_values["PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_{$payment_module['id_module']}"] = Configuration::get("PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_{$payment_module['id_module']}", 0);
        }
        
        return $config_form_values;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS files that will be loaded in the BO.
    */
    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->context->controller->php_self === 'AdminOrders') {
            $this->context->controller->addCSS($this->_path . 'views/css/profitmargin.css');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return $this->render($this->getModuleTemplatePath() . 'profitmargin_tab.html.twig');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return $this->render(
            $this->getModuleTemplatePath() . 'profitmargin_content.html.twig', 
            $params
        );
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        /** @var \PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface $definition */
        $definition = $params['definition'];

        /** @var \PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection */
        $columns = $definition->getColumns();

        $profitmarginColumn = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn('profitmargin');
        $profitmarginColumn->setName($this->l('Profit'))
            ->setOptions(array(
                'actions' => (new \PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection()),
            ));

        $columns->addAfter('total_paid_tax_incl', $profitmarginColumn);
        $definition->setColumns($columns);
    }

    public function hookActionOrderGridPresenterModifier($params)
    {
        $records = $params['presented_grid']['data']['records']->all();
        
        foreach ($records as &$record) {
            $profit_margin = $this->getProfitMargin((int)$record['id_order']);
            $record['profitmargin'] = $profit_margin;
        }

        $params['presented_grid']['data']['records'] = new \PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection($records);
    }

    /**
     * Render a twig template.
     * 
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function render(string $template, $params = array()) : string
    {
        /** @var Twig_Environment $twig */
        $twig = $this->get('twig');

        return $twig->render($template, $params);
    }

    /**
     * Get path to this module's template directory
     */
    private function getModuleTemplatePath()
    {
        return sprintf('@Modules/%s/views/templates/admin/', $this->name);
    }

    protected function getProfitMargin(int $id_order)
    {
        $db = Db::getInstance();
        $sql = 'SELECT `profit`, `profit_margin` AS `margin`
                FROM `' . _DB_PREFIX_ . 'order_profit_margin`
                WHERE `id_order`=' . pSQL($id_order);

        $profit_margin = $db->getRow($sql);

        if ($profit_margin === false) {
            $profit_margin = $this->calculateProfitMargin($id_order);
            /*$db->insert('order_profit_margin', array(
                'id_order'	=> (int)$id_order,
                'profit'	=> $profit,
            ));*/
        }

        return $profit_margin;
    }

    protected function calculateProfitMargin(int $id_order) 
    {
        $db = Db::getInstance();

        $sql = 'SELECT o.`total_paid`, m.`id_module`
                FROM `' . _DB_PREFIX_ . 'orders` o
                INNER JOIN `' . _DB_PREFIX_ . 'module` m
                    ON m.`name`=o.`module`
                WHERE id_order=' . pSQL($id_order);

        if ($order_general_info = $db->getRow($sql)) {
            $revenue = $order_general_info['total_paid'];
            $id_payment_module = $order_general_info['id_module'];
        }

        $sql = 'SELECT od.`original_wholesale_price`, od.`product_quantity` , t.`id_tax`, t.`rate`
                FROM `' . _DB_PREFIX_ . 'order_detail` od
                INNER JOIN `' . _DB_PREFIX_ . 'tax` t
                    ON t.id_tax=od.id_tax_rules_group
                WHERE od.id_order=' . pSQL($id_order);

        $products_cost = 0;
        $equivalence_surcharge = 0;
        $equivalence_surcharge_enabled = Configuration::get('PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED', false);

        if ($order_product_details = $db->executeS($sql)) {
            foreach ($order_product_details as $product) {
                $wholesale_price = $product['original_wholesale_price'];
                $tax_rate = $product['rate'] ;
                $quantity = $product['product_quantity'];

                $products_cost += $wholesale_price * (1 + $tax_rate/100) * $quantity;
                
                if ($equivalence_surcharge_enabled) {
                    $equivalence_surcharge += $quantity * $this->getEquivalenceSurcharge($wholesale_price, $product['id_tax']);
                }
            }
        }

        $payment_fee = $this->getPaymentFee($id_payment_module, $revenue);
        $shipping_cost = $this->getShippingCost($id_order, Configuration::get('PROFITMARGIN_SHIPPING_COST_TAX', 0));
        
        $costs = $products_cost + $equivalence_surcharge + $payment_fee + $shipping_cost;

        $profit_margin['profit'] = $revenue - $costs;
        $profit_margin['margin'] = ($profit_margin['profit'] / $revenue) * 100;
        
        return $profit_margin;
    }

    protected function getPaymentFee(string $id_payment_module, float $revenue) : float 
    {
        $payment_fee_base = Configuration::get("PROFITMARGIN_PAYMENT_FEE_BASE_$id_payment_module");
        $payment_fee_percentage = Configuration::get("PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_$id_payment_module");
        
        $payment_fee = $payment_fee_base + $revenue * ($payment_fee_percentage/100);

        return $payment_fee;
    }

    protected function getShippingCost(int $id_order, int $id_tax) : float
    {
        /** @var \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService::CLASS_NAME
        );

        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$id_order);

        if (isset($shipmentDetails)) {
            $tax_rate = $this->getTaxRate($id_tax);
            $shipping_cost = $shipmentDetails->getShippingCost() * (1 + $tax_rate/100);
        } else {
            $shipping_cost = Configuration::get('PROFITMARGIN_DEFAULT_SHIPPING_COST', 0);
        }

        return $shipping_cost;
    }

    protected function getTaxRate(int $id_tax) : float 
    {
        $sql = 'SELECT rate
                FROM `' . _DB_PREFIX_ . 'tax`
                WHERE `id_tax`=' . pSQL($id_tax);

        $tax_rate = Db::getInstance()->getValue($sql);
        
        return $tax_rate ? $tax_rate : 0;        
    }

    protected function getEquivalenceSurcharge(float $wholesale_price, int $id_tax) : float 
    {
        $equivalence_surcharge_percentage = Configuration::get("PROFITMARGIN_EQUIVALENCE_SURCHARGE_$id_tax", 0);
        $equivalence_surcharge = $wholesale_price * $equivalence_surcharge_percentage/100;

        return $equivalence_surcharge;
    }

}
