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
 *
 * @Bean
 */
class Customweb_UnzerCw_Model_BackendOperation_CaptureAdapter implements Customweb_Payment_BackendOperation_Adapter_Shop_ICapture
{
	public function capture(Customweb_Payment_Authorization_ITransaction $transaction)
	{
		$transactionModel = $transaction->getTransactionContext()->getTransactionModel();
		$transactionModel->setTransactionObject($transaction);
		if (Mage::registry('unzercw_update_transaction') != null) {
			Mage::unregister('unzercw_update_transaction');
		}
		Mage::register('unzercw_update_transaction', $transactionModel);
		$order = $transactionModel->getOrder();
		$invoices = $order->getInvoiceCollection();
		if ($invoices->count() == 1) {
			$invoice = $invoices->getFirstItem();
			if ($invoice->canCapture()) {
				$invoice->capture();

				Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();
			}
		}
		Mage::unregister('unzercw_update_transaction');
	}

	public function partialCapture(Customweb_Payment_Authorization_ITransaction $transaction, $items, $close)
	{
		$this->capture($transaction);
	}
}
