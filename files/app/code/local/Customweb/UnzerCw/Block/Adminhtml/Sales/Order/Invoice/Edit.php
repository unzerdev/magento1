<?php
class Customweb_UnzerCw_Block_Adminhtml_Sales_Order_Invoice_Edit  extends Mage_Adminhtml_Block_Widget_Form_Container
{
	public function __construct()
    {
        $this->_objectId = 'order_id';
        $this->_controller = 'customweb_unzercw_invoice';
        $this->_mode = 'edit';

        parent::__construct();

        $this->_removeButton('save');
        $this->_removeButton('delete');
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
     * Retrieve text for header
     *
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Edit Invoice #%s', $this->getInvoice()->getIncrementId());
    }

    /**
     * Retrieve back url
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/sales_invoice/view', array('invoice_id'=>$this->getInvoice()->getId()));
    }
}