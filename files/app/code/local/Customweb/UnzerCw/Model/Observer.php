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

class Customweb_UnzerCw_Model_Observer
{
	private $timeout = 0;

	public function initCart(Varien_Event_Observer $observer)
	{
		if (Mage::getStoreConfig('unzercw/general/cancel_existing_orders')) {
			$cart = $observer->getCart();
			$customer = Mage::getSingleton('customer/session')->getCustomer();

			$collection = Mage::getModel('sales/quote_item')->getCollection();
			$collection->getSelect()
				->reset(Zend_Db_Select::COLUMNS)
				->columns('product_id')
				->where('quote_id = ?', (int) $cart->getQuote()->getId());
			$productIds = $collection->getData();

			$orders = Mage::getResourceModel('sales/order_collection')
				->addAttributeToSelect('*')
				->addAttributeToFilter('customer_id', $customer->getId())
				->addAttributeToFilter('status', Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_PENDING)
				->load();

			if (count($orders) > 0 && count($productIds) > 0) {
				foreach ($productIds as $productId) {
					$product = Mage::getModel('catalog/product')->load($productId['product_id']);
					if (!$product->isSalable()) {
						foreach ($orders as $order) {
							$order->cancel();

							$order->setIsActive(0);
							$order->addStatusToHistory(Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_CANCELED, Mage::helper('UnzerCw')->__('Order cancelled, because the ordered products are not available.'));
							$order->save();
						}
						break;
					}
				}
			}
		}
	}

	public function placeOrder(Varien_Event_Observer $observer)
	{
		$order = $observer->getOrder();
		try {
			if (strpos($order->getPayment()->getMethodInstance()->getCode(), 'unzercw') === 0) {
				Mage::unregister('cw_order_id');
				Mage::register('cw_order_id', $order->getId());

				$result = new StdClass;
				$result->createTransaction = true;
				Mage::dispatchEvent('customweb_payment_place_order', array(
					'result' => $result,
					'order' => $order
				));

				if ($result->createTransaction && Mage::registry('cw_is_moto') == null && Mage::registry('cw_is_externalcheckout') == null) {
					$transaction = $order->getPayment()->getMethodInstance()->createTransaction($order);
					Mage::unregister('cstrxid');
					Mage::register('cstrxid', $transaction->getTransactionId());
				}
			}
		} catch (Exception $e) {
			Mage::helper('UnzerCw')->logException($e);
		}
	}

	public function saveOrder(Varien_Event_Observer $observer)
	{
		$order = $observer->getOrder();
		try {
			if (strpos($order->getPayment()->getMethodInstance()->getCode(), 'unzercw') === 0) {
				$sessionTransactionId = Mage::registry('cstrxid');
				if (Mage::registry('cw_is_moto') == null && !empty($sessionTransactionId)) {
					$transaction = Mage::helper('UnzerCw')->loadTransaction($sessionTransactionId);
					if ($transaction != null && $transaction->getId()) {
						$order->getPayment()->getMethodInstance()->updateTransaction($transaction, $order);
					}
				}
			}
		} catch (Exception $e) {
			Mage::helper('UnzerCw')->logException($e);
		}
	}

	public function capturePayment(Varien_Event_Observer $observer)
	{
		Mage::register('current_invoice', $observer->getEvent()->getInvoice(), true);
	}

	public function cancelOrder(Varien_Event_Observer $observer)
	{
		$order = $observer->getOrder();
		if (strpos($order->getPayment()->getMethodInstance()->getCode(), 'unzercw') === 0) {
			$order->addStatusHistoryComment(Mage::helper('UnzerCw')->__('Transaction cancelled successfully'));
		}
	}

	public function invoiceView(Varien_Event_Observer $observer)
	{
		$block = $observer->getBlock();
		$invoice = $observer->getInvoice();

		if (strpos($invoice->getOrder()->getPayment()->getMethodInstance()->getCode(), 'unzercw') === 0) {
			$transaction = Mage::helper('UnzerCw')->loadTransactionByOrder($invoice->getOrder()->getId());

			if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/capture')
					&& $invoice->canCapture()
					&& $transaction->getTransactionObject()->isPartialCapturePossible()
					&& Customweb_Util_Currency::compareAmount($invoice->getGrandTotal(), $transaction->getAuthorizationAmount(), $transaction->getCurrency()) != 0) {
				$block->addButton('capture_no_close', array(
					'label'     => Mage::helper('sales')->__("Capture (Don't Close)"),
					'class'     => 'save',
					'onclick'   => 'setLocation(\''.$block->getUrl('*/*/capture', array('invoice_id'=>$invoice->getId(), 'capture_no_close' => true)).'\')'
				));
			}

			if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/edit')
				&& $invoice->canCapture()
				&& $transaction->getTransactionObject()->isCapturePossible()
				&& $transaction->getTransactionObject()->isPartialCapturePossible()) {
				$block->addButton('edit', array(
					'label'     => Mage::helper('sales')->__('Edit'),
					'class'     => 'go',
					'onclick'   => 'setLocation(\''.$block->getUrl('*/editunzercw/index', array('invoice_id'=>$invoice->getId())).'\')'
				));
			}
		}
	}

	public function loadCustomerQuoteBefore(Varien_Event_Observer $observer)
	{
		if (Mage::registry('unzercw_external_checkout_login') === true) {
			$customerQuote = Mage::getModel('sales/quote')
				->setStoreId(Mage::app()->getStore()->getId())
				->loadByCustomer(Mage::getSingleton('customer/session')->getCustomerId());

			if ($customerQuote->getId() && Mage::getSingleton('checkout/session')->getQuoteId() && Mage::getSingleton('checkout/session')->getQuoteId() != $customerQuote->getId()) {
				$customerQuote->delete();
			}
		}
	}

	public function collectExternalCheckoutWidgets(Varien_Event_Observer $observer)
	{
		$event = $observer->getEvent();
		$widgets = $event->getWidgets();
		$widgets = array_merge($widgets, Mage::getModel('unzercw/externalCheckoutWidgets')->getWidgets());
		$observer->getEvent()->setWidgets($widgets);
	}

	public function registerTranslationResolver(Varien_Event_Observer $observer)
	{
		if (!Mage::registry('customweb_unzercw_transaction_resolver_registered')) {
			Customweb_I18n_Translation::getInstance()->addResolver(new Customweb_UnzerCw_Model_TranslationResolver());
			Mage::register('customweb_unzercw_transaction_resolver_registered', true);
		}
	}

	public function registerLoggingListener(Varien_Event_Observer $observer)
	{
		if (!Mage::registry('customweb_unzercw_logging_listener_registered')) {
			Customweb_Core_Logger_Factory::addListener(new Customweb_UnzerCw_Model_LoggingListener());
			Mage::register('customweb_unzercw_logging_listener_registered', true);
		}
	}

	public function migrateCustomerTransactions(Varien_Event_Observer $observer)
	{
		$event = $observer->getEvent();
		$customer = $event->getCustomer();
		Mage::helper('UnzerCw')->migrateCustomersTransactions($customer);
	}
}
