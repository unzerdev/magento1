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
 *
 * @category	Customweb
 * @package		Customweb_UnzerCw
 * 
 */

class Customweb_UnzerCw_Block_Adminhtml_Sales_Order_Invoice_Create extends Mage_Adminhtml_Block_Template
{
	public function canCaptureNoClose()
	{
		$invoice = Mage::registry('current_invoice');
		if (!($invoice instanceof Mage_Sales_Model_Order_Invoice)) {
			return false;
		}
		if (!($invoice->getOrder()->getPayment()->getMethodInstance() instanceof Customweb_UnzerCw_Model_Method)) {
			return false;
		}
		$transaction = Mage::helper('UnzerCw')->loadTransactionByOrder($invoice->getOrderId());
		if (!($transaction instanceof Customweb_UnzerCw_Model_Transaction) || !$transaction->getId()) {
			return false;
		}
		if (!$transaction->getTransactionObject()->isPartialCapturePossible()) {
			return false;
		}
		return true;
	}
}