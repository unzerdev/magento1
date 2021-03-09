<?php
class Customweb_UnzerCw_Block_Adminhtml_Sales_Order_Invoice_View extends Mage_Adminhtml_Block_Sales_Order_Invoice_View
{
	public function __construct()
	{
		parent::__construct();

		Mage::dispatchEvent('adminhtml_sales_order_invoice_view_construct', array('block' => $this, 'invoice' => $this->getInvoice()));
	}
}