{*
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
*}

<div class="panel">
	<h3><i class="icon icon-calculator"></i> {l s='Profit margin calculator' mod='statsprofitmargin'}</h3>
	<p>
		{l s='The net profit is calculated substracting costs from revenue, where revenue is he total amount paid by the customer, and costs are the sum of' mod='statsprofitmargin'}:
	</p>
		<ul>
			<li>{l s='Products cost' mod='statsprofitmargin'} {l s='(tax incl.)' mod='statsprofitmargin'}</li>
			<li>{l s='Equivalence surcharge' mod='statsprofitmargin'}</li>
			<li>{l s='Shipping cost' mod='statsprofitmargin'} {l s='(tax incl.)' mod='statsprofitmargin'} *</li>
			<li>{l s='Payment fee' mod='statsprofitmargin'}</li>
		</ul>
	<p>
		{l s='And profit margin is calculated with the following formula' mod='statsprofitmargin'}: 
		<code>{l s='Profit margin' mod='statsprofitmargin'} = ({l s='Profit' mod='statsprofitmargin'} / {l s='Revenue' mod='statsprofitmargin'}) * 100</code>
	</p>
	<p>
		* {l s='Shipping cost will be synced with' mod='statsprofitmargin'} <a href="https://addons.prestashop.com/en/shipping-costs/22591-packlink-pro-shipping.html" target="_blank" rel="noopener noreferrer">{l s='Packlink Module' mod='statsprofitmargin'}</a> {l s='if available' mod='statsprofitmargin'}.
	</p>
</div>
