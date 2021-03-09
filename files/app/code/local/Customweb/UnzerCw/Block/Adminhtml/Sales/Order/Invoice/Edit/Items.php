<?php
class Customweb_UnzerCw_Block_Adminhtml_Sales_Order_Invoice_Edit_Items extends Mage_Adminhtml_Block_Sales_Items_Abstract
{
	protected $_disableSubmitButton = false;

	/**
	 * Prepare child blocks
	 *
	 * @return Mage_Adminhtml_Block_Sales_Order_Invoice_Create_Items
	 */
	protected function _beforeToHtml()
	{
		$onclick = "submitAndReloadArea($('invoice_item_container'),'".$this->getUpdateUrl()."')";
		$this->setChild(
				'update_button',
				$this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
					'class'     => 'update-button',
					'label'     => Mage::helper('sales')->__("Update Qty's"),
					'onclick'   => $onclick,
				))
		);
		$this->_disableSubmitButton = true;
		$_submitButtonClass = ' disabled';
		foreach ($this->getInvoice()->getAllItems() as $item) {
			/**
			 * @see bug #14839
			 */
			if ($item->getQty()/* || $this->getSource()->getData('base_grand_total')*/) {
				$this->_disableSubmitButton = false;
				$_submitButtonClass = '';
				break;
			}
		}
		$_saveLabel = Mage::helper('sales')->__('Save');
		$this->setChild(
				'save_button',
				$this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
					'label'     => $_saveLabel,
					'class'     => 'save submit-button' . $_submitButtonClass,
					'onclick'   => 'disableElements(\'submit-button\');$(\'edit_form\').submit()',
					'disabled'  => $this->_disableSubmitButton
				))
		);

		return parent::_prepareLayout();
	}

	/**
	 * Get is submit button disabled or not
	 *
	 * @return boolean
	 */
	public function getDisableSubmitButton()
	{
		return $this->_disableSubmitButton;
	}

	/**
	 * Retrieve invoice order
	 *
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
	{
		return $this->getInvoice()->getOrder();
	}

	/**
	 * Retrieve source
	 *
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	public function getSource()
	{
		return $this->getInvoice();
	}

	/**
	 * Retrieve invoice model instance
	 *
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	public function getInvoice()
	{
		return Mage::registry('current_invoice');
	}

	/**
	 * Retrieve order totals block settings
	 *
	 * @return array
	 */
	public function getOrderTotalData()
	{
		return array();
	}

	/**
	 * Retrieve order totalbar block data
	 *
	 * @return array
	 */
	public function getOrderTotalbarData()
	{
		$totalbarData = array();
		$this->setPriceDataObject($this->getInvoice()->getOrder());
		$totalbarData[] = array(Mage::helper('sales')->__('Paid Amount'), $this->displayPriceAttribute('amount_paid'), false);
		$totalbarData[] = array(Mage::helper('sales')->__('Refund Amount'), $this->displayPriceAttribute('amount_refunded'), false);
		$totalbarData[] = array(Mage::helper('sales')->__('Shipping Amount'), $this->displayPriceAttribute('shipping_captured'), false);
		$totalbarData[] = array(Mage::helper('sales')->__('Shipping Refund'), $this->displayPriceAttribute('shipping_refunded'), false);
		$totalbarData[] = array(Mage::helper('sales')->__('Order Grand Total'), $this->displayPriceAttribute('grand_total'), true);

		return $totalbarData;
	}

	public function formatPrice($price)
	{
		return $this->getInvoice()->getOrder()->formatPrice($price);
	}

	public function getUpdateButtonHtml()
	{
		return $this->getChildHtml('update_button');
	}

	public function getUpdateUrl()
	{
		return $this->getUrl('*/*/updateQty', array('invoice_id'=>$this->getInvoice()->getId()));
	}

	public function canEditQty()
	{
		return true;
	}

	/**
	 * Check if capture operation is allowed in ACL
	 * @return bool
	 */
	public function isCaptureAllowed()
	{
		return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/capture');
	}

	/**
	 * Check if invoice can be captured
	 * @return bool
	 */
	public function canCapture()
	{
		return $this->getInvoice()->canCapture();
	}

	public function displayShippingTaxAmount()
	{
		$invoice = $this->getInvoice();
		$tax       = $invoice->getShippingTaxAmount();
		$baseTax   = $invoice->getBaseShippingTaxAmount();
		return $this->displayPrices($baseTax, $tax, false, ' ');
	}

	/**
	 * Retrieve subtotal price excluding tax html formated content
	 *
	 * @param Varien_Object $item
	 * @return string
	 */
	public function displayShippingPriceExclTax()
	{
		$invoice = $this->getInvoice();
		$shipping       = $invoice->getShippingAmount();
		$baseShipping   = $invoice->getBaseShippingAmount();
		return $this->displayPrices($baseShipping, $shipping, false, ' ');
	}

	/**
	 * Retrieve subtotal price include tax html formated content
	 *
	 * @param Varien_Object $item
	 * @return string
	 */
	public function displayShippingPriceInclTax()
	{
		$invoice = $this->getInvoice();
		$shipping = $invoice->getShippingInclTax();
		if ($shipping) {
			$baseShipping = $invoice->getBaseShippingInclTax();
		} else {
			$shipping       = $invoice->getShippingAmount() + $invoice->getShippingTaxAmount();
			$baseShipping   = $invoice->getBaseShippingAmount() + $invoice->getBaseShippingTaxAmount();
		}
		return $this->displayPrices($baseShipping, $shipping, false, ' ');
	}
}