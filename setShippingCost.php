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

require_once(__DIR__ . '/../../config/config.inc.php');
require_once(__DIR__ . '/../../init.php');

$module_name = 'statsprofitmargin';

$token = pSQL(Tools::encrypt($module_name . '/setShippingCost.php'));
$token_url = pSQL(Tools::getValue('token'));

if ($token != $token_url || !Module::isInstalled($module_name)) {
    exit;
}

$module = Module::getInstanceByName($module_name);

if ($module->active) {
	$id_order = Tools::getValue('id_order');
    $shipping_cost = Tools::getValue('shipping_cost');

    if (isset($id_order) && isset($shipping_cost)) {
        $module->setShippingCost((int)$id_order, (float)$shipping_cost);
    }
}
