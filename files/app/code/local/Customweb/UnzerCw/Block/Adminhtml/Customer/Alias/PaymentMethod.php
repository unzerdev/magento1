<?php
/**
  * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 */

/**
 * @category   Customweb
 * @package    Customweb_Isr
 * @author     customweb GmbH (info@customweb.com)
 */
class Customweb_UnzerCw_Block_Adminhtml_Customer_Alias_PaymentMethod extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{
	public function _getValue(Varien_Object $row)
	{
		$paymentMethod = Mage::helper('payment')->getMethodInstance($row->payment_method);
		return $paymentMethod->getPaymentMethodDisplayName();
	}
}
