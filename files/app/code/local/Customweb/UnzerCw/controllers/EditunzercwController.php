<?php
class Customweb_UnzerCw_EditunzercwController extends Mage_Adminhtml_Controller_Sales_Invoice
{
    /**
     * Get requested items qty's from request
     */
    protected function _getItemQtys()
    {
        $data = $this->getRequest()->getParam('invoice');
        if (isset($data['items'])) {
            $qtys = $data['items'];
        } else {
            $qtys = array();
        }
        return $qtys;
    }

    /**
     * Initialize invoice model instance
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _initInvoice($update = false)
    {
        $this->_title($this->__('Sales'))->_title($this->__('Invoices'));

        $invoice = false;
        $itemsToInvoice = 0;
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        if ($invoiceId) {
            $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
            if (!$invoice->getId()) {
                $this->_getSession()->addError($this->__('The invoice no longer exists.'));
                return false;
            }
        } else {
        	$this->_getSession()->addError($this->__('The invoice could not be found.'));
        	return false;
        }

        Mage::register('current_invoice', $invoice);
        return $invoice;
    }

    /**
     * Invoice create page
     */
    public function indexAction()
    {
        $invoice = $this->_initInvoice();
        if ($invoice) {
            $this->_title($this->__('Edit Invoice %s', $invoice->getIncrementId()));

            if ($comment = Mage::getSingleton('adminhtml/session')->getCommentText(true)) {
                $invoice->setCommentText($comment);
            }

            $this->loadLayout()
                ->_setActiveMenu('sales/order')
                ->renderLayout();
        } else {
            $this->_redirect('*/sales_order/view', array('order_id'=>$this->getRequest()->getParam('order_id')));
        }
    }

    /**
     * Update items qty action
     */
    public function updateQtyAction()
    {
        try {
            $invoice = $this->_initInvoice(true);
        	$this->_changeInvoice($invoice);

            $this->loadLayout();
            $response = $this->getLayout()->getBlock('order_items')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = array(
                'error'     => true,
                'message'   => $e->getMessage()
            );
            $response = Mage::helper('core')->jsonEncode($response);
        } catch (Exception $e) {
            $response = array(
                'error'     => true,
                'message'   => $this->__('Cannot update item quantity.')
            );
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Save invoice
     * We can save only new invoice. Existing invoices are not editable
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost('invoice');

        try {
            $invoice = $this->_initInvoice();
            if ($invoice) {
				$this->_changeInvoice($invoice);

				$transactionSave = Mage::getModel('core/resource_transaction');

				foreach ($invoice->getAllItems() as $item) {
					if ($item->getQty() == 0) {
						$item->delete();
					} else {
						$transactionSave->addObject($item);
						//$transactionSave->addObject($item->getOrderItem());
					}
				}

                $transactionSave->addObject($invoice);
                $transactionSave->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->_getSession()->addSuccess($this->__('The invoice has been changed.'));

                $this->_redirect('*/sales_invoice/view', array('invoice_id' => $invoice->getId()));
            } else {
                $this->_redirect('*/*/index', array('invoice_id' => $invoice->getId()));
            }
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to save the invoice.'));
            Mage::helper('UnzerCw')->logException($e);
        }
        $this->_redirect('*/*/index', array('invoice_id' => $invoice->getId()));
    }

    protected function _changeInvoice($invoice)
    {
    	if ($invoice->getShippingAmount() > 0) {
   	 		$invoice->setShowShippingItem(true);
    	}

    	$savedQtys = $this->_getItemQtys();
    	$baseDiscountAmountItemsBefore = 0;
    	$discountAmountItemsBefore = 0;
    	foreach ($invoice->getAllItems() as $item) {
    		$orderItem = $item->getOrderItem();
    		if ($orderItem->getParentItemId() != null && $orderItem->getParentItem()
    			->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
    			continue;
    		}
    		if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $orderItem->getParentItemId() == null) {
    			continue;
    		}

    		$baseDiscountAmountItemsBefore += $item->getBaseDiscountAmount();
    		$discountAmountItemsBefore += $item->getDiscountAmount();

    		$qtyBefore = $item->getQty();
    		$qty = $savedQtys[$item->getId()];
    		if ($qty >= $qtyBefore) {
    			continue;
    		}
    		if ($item->getOrderItem()->getIsQtyDecimal()) {
    			$qty = (float) $qty;
    		} else {
    			$qty = (int) $qty;
    		}
    		$qty = $qty > 0 ? $qty : 0;
    		$item->setData('qty', $qty);

    		//$item->getOrderItem()->setQtyInvoiced($item->getOrderItem()->getQtyInvoiced() - ($qtyBefore - $qty));

    		$item->setBaseRowTotal($invoice->roundPrice($item->getBasePrice() * $qty, 'base'));
    		$item->setRowTotal($invoice->roundPrice($item->getPrice() * $qty));

    		$item->setBaseRowTotalInclTax($invoice->roundPrice($item->getBasePriceInclTax() * $qty, 'base'));
    		$item->setRowTotalInclTax($invoice->roundPrice($item->getPriceInclTax() * $qty));

    		$item->setBaseTaxAmount($invoice->roundPrice($item->getBaseTaxAmount() / $qtyBefore * $qty, 'base'));
    		$item->setTaxAmount($invoice->roundPrice($item->getTaxAmount() / $qtyBefore * $qty));

    		$item->setBaseDiscountAmount($invoice->roundPrice($item->getBaseDiscountAmount() / $qtyBefore * $qty, 'base'));
    		$item->setDiscountAmount($invoice->roundPrice($item->getDiscountAmount() / $qtyBefore * $qty));
    	}

    	if ($savedQtys['shipping'] == 0) {
    		$invoice->setBaseShippingAmount(0);
    		$invoice->setShippingAmount(0);
    		$invoice->setBaseShippingTaxAmount(0);
    		$invoice->setShippingTaxAmount(0);
    		$invoice->setBaseShippingInclTax(0);
    		$invoice->setShippingInclTax(0);
    	}

    	$baseTaxAmount = 0;
    	$taxAmount = 0;
    	$baseSubtotal = 0;
    	$subtotal = 0;
    	$baseDiscountAmount = 0;
    	$discountAmount = 0;
    	foreach ($invoice->getAllItems() as $item) {
    		$orderItem = $item->getOrderItem();
    		if ($orderItem->getParentItemId() != null && $orderItem->getParentItem()
    			->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
    			continue;
    		}
    		if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $orderItem->getParentItemId() == null) {
    			continue;
    		}

    		$baseTaxAmount += $item->getBaseTaxAmount();
    		$taxAmount += $item->getTaxAmount();
    		$baseSubtotal += $item->getBaseRowTotal();
    		$subtotal += $item->getRowTotal();
    		$baseDiscountAmount += $item->getBaseDiscountAmount();
    		$discountAmount += $item->getDiscountAmount();
    	}

    	$invoice->setBaseTaxAmount($baseTaxAmount + $invoice->getBaseShippingTaxAmount());
    	$invoice->setTaxAmount($taxAmount + $invoice->getShippingTaxAmount());
    	$invoice->setBaseSubtotal($baseSubtotal);
    	$invoice->setSubtotal($subtotal);
    	$invoice->setBaseSubtotalInclTax($baseSubtotal + $baseTaxAmount);
    	$invoice->setSubtotalInclTax($subtotal + $taxAmount);
    	$invoice->setBaseDiscountAmount($invoice->getBaseDiscountAmount() - $baseDiscountAmountItemsBefore + $baseDiscountAmount);
    	$invoice->setDiscountAmount($invoice->getDiscountAmount() - $discountAmountItemsBefore + $discountAmount);
    	$invoice->setBaseGrandTotal($baseSubtotal + $baseTaxAmount - $invoice->getBaseDiscountAmount() + $invoice->getBaseShippingInclTax());
    	$invoice->setGrandTotal($subtotal + $taxAmount - $invoice->getDiscountAmount() + $invoice->getShippingInclTax());
    }

}
