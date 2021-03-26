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
        $this->version = '0.6';
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
        include(__DIR__ . '/sql/install.php');

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

        include(__DIR__ . '/sql/uninstall.php');

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

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of the module.
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
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of the form.
     * @TODO: Discounts by brand
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

        $config_form['form']['input'][] = array( // @TODO: Default shipping cost for each carrier
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
    
        foreach ($this->getAvailablePaymentModules() as $payment_module) {
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
        $sql = new DbQuery();
        $sql->select('DISTINCT t.id_tax, tl.name');
        $sql->from('tax_lang', 'tl');
        $sql->innerJoin('tax', 't', 't.id_tax = tl.id_tax');
        $sql->innerJoin('tax_rule', 'tr', 'tr.id_tax = t.id_tax');
        $sql->innerJoin('country', 'c', 'c.id_country = tr.id_country');
        $sql->innerJoin('tax_rules_group', 'trg', 'trg.id_tax_rules_group = tr.id_tax_rules_group');
        $sql->where('t.active = 1 AND trg.active = 1 AND c.active = 1');

        $taxes = Db::getInstance()->executeS($sql);

        return $taxes;
    }

    protected function getAvailablePaymentModules() {
        $sql = new DbQuery();
        $sql->select('m.id_module, o.payment AS name');
        $sql->from('module', 'm');
        $sql->innerJoin('orders', 'o', 'o.module = m.name');
        $sql->where('m.active = 1');
        $sql->groupBy('m.id_module');

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
    * Add the CSS & JS files that will be loaded in the BO.
    */
    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->context->controller->php_self === 'AdminOrders') {
            $this->context->controller->addCSS($this->_path . 'views/css/profitmargin.css');
            $this->context->controller->addJS($this->_path . 'views/js/profitmargin.js');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return $this->render($this->getModuleTemplatePath() . 'profitmargin_tab.html.twig');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $shipping_cost_url = $this->_path . 'setShippingCost.php?token=' . Tools::encrypt($this->name . '/setShippingCost.php');

        $params['shipping_cost_url'] = $shipping_cost_url;
        $params['shipping_cost'] = $this->getShippingCost((int)$params['id_order']);

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

    /** 
     * If available, get the profit & margin from the DB
     * otherwise calculate it 
     */
    protected function getProfitMargin(int $id_order)
    {
        $db = Db::getInstance();
        
        $sql = new DbQuery();
        $sql->select('profit, margin');
        $sql->from('order_profit_margin');
        $sql->where('id_order = ' . (int)$id_order);

        $profit_margin = $db->getRow($sql);

        if ($profit_margin === false) {
            $profit_margin = $this->calculateProfitMargin($id_order);
            
            if ($this->getPacklinkShippingStatus($id_order) != 'pending') {
                $db->insert('order_profit_margin', $profit_margin);
            }
        }

        return $profit_margin;
    }

    /**
     * profit = revenue - costs
     * margin = (profit / revenue) * 100
     */
    protected function calculateProfitMargin(int $id_order) 
    {
        $db = Db::getInstance();

        $sql = new DbQuery();
        $sql->select('o.total_paid, m.id_module');
        $sql->from('orders', 'o');
        $sql->innerJoin('module', 'm', 'm.name = o.module');
        $sql->where('o.id_order =' . (int)$id_order);

        if ($order_general_info = $db->getRow($sql)) {
            $revenue = $order_general_info['total_paid'];
            $id_payment_module = $order_general_info['id_module'];
        }

        $sql = new DbQuery();
        $sql->select('od.original_wholesale_price, od.product_quantity, t.id_tax, t.rate');
        $sql->from('order_detail', 'od');
        $sql->innerJoin('tax', 't', 't.id_tax = od.id_tax_rules_group');
        $sql->where('od.id_order =' . (int)$id_order);

        $products_cost = 0; // Sum of all products cost at wholesale price, taxes included
        $equivalence_surcharge = 0; // Sum of surcharges for each product
        $equivalence_surcharge_enabled = Configuration::get('PROFITMARGIN_EQUIVALENCE_SURCHARGE_ENABLED', false); // TODO: If disabled, calculate profit & margin accordingly (without taxes)

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
        $shipping_cost = $this->getShippingCost($id_order);
        
        $costs = $products_cost + $equivalence_surcharge + $payment_fee + $shipping_cost;

        $profit_margin['id_order'] = $id_order;
        $profit_margin['profit'] = $revenue - $costs;
        $profit_margin['margin'] = ($profit_margin['profit'] / $revenue) * 100;

        return $profit_margin;
    }


    protected function updateProfitMargin(int $id_order) : void
    {
        $profit_margin = $this->calculateProfitMargin((int)$id_order);
        Db::getInstance()->update('order_profit_margin', array(
            'profit' => $profit_margin['profit'],
            'margin' => $profit_margin['margin']
        ), 'id_order = ' . (int)$id_order, 1);
    }

    protected function getPaymentFee(string $id_payment_module, float $revenue) : float 
    {
        $payment_fee_base = Configuration::get("PROFITMARGIN_PAYMENT_FEE_BASE_$id_payment_module");
        $payment_fee_percentage = Configuration::get("PROFITMARGIN_PAYMENT_FEE_PERCENTAGE_$id_payment_module");
        
        $payment_fee = $payment_fee_base + $revenue * ($payment_fee_percentage/100);

        return $payment_fee;
    }

    /**
     * If available, get the shipping cost from the DB
     * otherwise, check for Packlink module
     * otherwise return the default shipping cost
     */
    protected function getShippingCost(int $id_order) : float
    {
        $sql = new DbQuery();
        $sql->select('shipping_cost');
        $sql->from('order_profit_margin');
        $sql->where('id_order = ' . (int)$id_order);
        $shipping_cost = Db::getInstance()->getValue($sql);

        if ($shipping_cost === false || $shipping_cost === null) {
            $shipping_cost = $this->getPacklinkShippingCost($id_order);

            if ($shipping_cost === null) {
                $shipping_cost = Configuration::get('PROFITMARGIN_DEFAULT_SHIPPING_COST', 0);
            }
        }

        return $shipping_cost;
    }

    protected function getPacklinkShippingStatus(int $id_order) : ?string 
    {
        $status = null;

        $shipmentDetailsService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService::CLASS_NAME
        );

        if (isset($shipmentDetailsService)) {
            $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$id_order);
            
            if (isset($shipmentDetails)) {
                $status = $shipmentDetails->getStatus();
            }
        }

        return $status;
    }

    protected function getPacklinkShippingCost(int $id_order) : ?float 
    {
        /** @var \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService::CLASS_NAME
        );

        if (isset($shipmentDetailsService)) {
            $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$id_order);
        }

        if (isset($shipmentDetails) && $shipmentDetails->getStatus() != 'pending') {
            $tax_rate = $this->getTaxRate(Configuration::get('PROFITMARGIN_SHIPPING_COST_TAX', 0));
            $shipping_cost = $shipmentDetails->getShippingCost() * (1 + $tax_rate/100);
        } else {
            $shipping_cost = null;
        }
        
        return $shipping_cost;
    }

    protected function getTaxRate(int $id_tax) : float 
    {
        $sql = new DbQuery();
        $sql->select('rate');
        $sql->from('tax');
        $sql->where('id_tax =' . (int)$id_tax);

        $tax_rate = Db::getInstance()->getValue($sql);
        
        return $tax_rate ? $tax_rate : 0;        
    }

    protected function getEquivalenceSurcharge(float $wholesale_price, int $id_tax) : float 
    {
        $equivalence_surcharge_percentage = Configuration::get("PROFITMARGIN_EQUIVALENCE_SURCHARGE_$id_tax", 0);
        $equivalence_surcharge = $wholesale_price * $equivalence_surcharge_percentage/100;

        return $equivalence_surcharge;
    }

    public function setShippingCost(int $id_order, float $shipping_cost) : bool
    {
        $db = Db::getInstance();

        $sql = new DbQuery();
        $sql->select('1');
        $sql->from('order_profit_margin');
        $sql->where('id_order = ' . (int)$id_order);

        if ($db->getValue($sql)) { // Check if id_order already exists
            $result = $db->update('order_profit_margin', array(
                'shipping_cost' => (float)$shipping_cost
            ), 'id_order = ' . (int)$id_order, 1);
        } else {
            $result = $db->insert('order_profit_margin', array(
                'id_order' => (int)$id_order,
                'profit' => null,
                'margin' => null,
                'shipping_cost' => (float)$shipping_cost
            ), true);
        }

        $this->updateProfitMargin($id_order);

        return $result;
    }

}
