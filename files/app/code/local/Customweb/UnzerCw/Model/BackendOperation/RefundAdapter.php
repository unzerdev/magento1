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
class Customweb_UnzerCw_Model_BackendOperation_RefundAdapter implements Customweb_Payment_BackendOperation_Adapter_Shop_IRefund
{
	public function refund(Customweb_Payment_Authorization_ITransaction $transaction)
	{
		$transactionModel = $transaction->getTransactionContext()->getTransactionModel();
		$transactionModel->setTransactionObject($transaction);
		if (Mage::registry('unzercw_update_transaction') != null) {
			Mage::unregister('unzercw_update_transaction');
		}
		Mage::register('unzercw_update_transaction', $transactionModel);
		$order = $transactionModel->getOrder();
		try {
			$data = array();
			$orderItems = $order->getAllItems();
			foreach ($orderItems as $item) {
				if (!isset($data[$item->getId()])) {
					$data['qtys'][$item->getId()] = $item->getQtyOrdered();
				} else {
					$data['qtys'][$item->getId()] += $item->getQtyOrdered();
				}
			}

			$data['shipping_amount'] = 'all';

			$creditmemo = $this->createCreditmemo($order, $data);
		} catch (Exception $e) {
			Mage::helper('UnzerCw')->logException($e);
		}
		Mage::unregister('unzercw_update_transaction');
	}

	public function partialRefund(Customweb_Payment_Authorization_ITransaction $transaction, $items, $close)
	{
		$transactionModel = $transaction->getTransactionContext()->getTransactionModel();
		$transactionModel->setTransactionObject($transaction);
		if (Mage::registry('unzercw_update_transaction') != null) {
			Mage::unregister('unzercw_update_transaction');
		}
		Mage::register('unzercw_update_transaction', $transactionModel);
		$order = $transactionModel->getOrder();
		try {
			$data = array();
			$orderItems = $order->getItemsCollection();
			$totalRefund = 0;
			$totalOrder = 0;
			foreach ($items as $item) {
				switch ($item->getType()) {
					case Customweb_Payment_Authorization_IInvoiceItem::TYPE_PRODUCT:
						$orderItem = $orderItems->getItemByColumnValue('sku', $item->getSku());
						if (!isset($data['qtys'][$orderItem->getId()])) {
							$data['qtys'][$orderItem->getId()] = $item->getQuantity();
						} else {
							$data['qtys'][$orderItem->getId()] += $item->getQuantity();
						}
						$totalRefund += $item->getAmountIncludingTax();
						$totalOrder += $orderItem->getRowTotalInclTax();
						break;
					case Customweb_Payment_Authorization_IInvoiceItem::TYPE_SHIPPING:
						if (!isset($data['shipping_amount'])) {
							$data['shipping_amount'] = $order->getShippingAmount();
						} else {
							$data['shipping_amount'] += $order->getShippingAmount();
						}
						$totalRefund += $item->getAmountIncludingTax();
						$totalOrder += $order->getShippingAmount() + $order->getShippingTaxAmount();
						break;
				}
			}

			if ($totalOrder > $totalRefund) {
				$data['adjustment_negative'] = $totalOrder - $totalRefund;
			}

			$this->createCreditmemo($order, $data);
		} catch (Exception $e) {
			Mage::helper('UnzerCw')->logException($e);
		}
		Mage::unregister('unzercw_update_transaction');
	}

	protected function createCreditmemo(Mage_Sales_Model_Order $order, $data)
	{
		$invoices = $order->getInvoiceCollection();
		if ($invoices->count() == 1) {
			$invoice = $invoices->getFirstItem();
		}

		if (!$invoice->getId()) {
			throw new Exception('Invoice could not be loaded.');
		}

		if (!$order->canCreditmemo()) {
			throw new Exception('Creditmemo cannot be created for order.');
		}

		if ($data['shipping_amount'] == 'all') {
			$data['shipping_amount'] = $invoice->getShippingAmount();
		}

		$service = Mage::getModel('sales/service_order', $order);
		$creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);

		foreach ($creditmemo->getAllItems() as $creditmemoItem) {
			$orderItem = $creditmemoItem->getOrderItem();
			$parentId = $orderItem->getParentItemId();
			$creditmemoItem->setBackToStock(true);
		}

		if ($creditmemo) {
			if (($creditmemo->getGrandTotal() <=0) && (!$creditmemo->getAllowZeroGrandTotal())) {
				Mage::throwException(
					Mage::helper('UnzerCw')->__("Credit memo's total must be positive.")
				);
			}

			Mage::register('cw_unzercw_refund_update', true);

			$creditmemo->setRefundRequested(true);

			$creditmemo->register();

			$transactionSave = Mage::getModel('core/resource_transaction')
				->addObject($creditmemo)
				->addObject($creditmemo->getOrder());
			if ($creditmemo->getInvoice()) {
				$transactionSave->addObject($creditmemo->getInvoice());
			}
			$transactionSave->save();
		}
	}
}
