<?php
class Customweb_UnzerCw_Block_Adminhtml_Sales_Order_Invoice_Edit_Form extends Mage_Adminhtml_Block_Sales_Order_Abstract
{
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

	protected function _prepareLayout()
	{
		return parent::_prepareLayout();
	}

	public function getSaveUrl()
	{
		return $this->getUrl('*/*/save', array('invoice_id' => $this->getInvoice()->getId()));
	}
}