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

class Customweb_UnzerCw_Block_Adminhtml_Customer_Alias extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
	protected function _construct()
    {
        parent::_construct();
        $this->setId('unzercw_customer_aliases');
        $this->setDefaultSort('alias_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setSkipGenerateContent(true);
    }

	/**
	 * Prepare grid collection object
	 *
	 * @return Customweb_UnzerCw_Block_Adminhtml_Customer_Alias
	 */
	protected function _prepareCollection()
	{
		$collection = Mage::getResourceModel('unzercw/transaction_collection')
			->addFieldToFilter('customer_id', Mage::registry('current_customer')->getId())
			->addFieldToFilter('alias_active', array(
				'eq' => 1
			))
			->addFieldToFilter('alias_for_display', array(
				'neq' => 'NULL'
			));

		$this->setCollection($collection);
		return parent::_prepareCollection();
	}

	/**
	 * Prepare grid columns
	 *
	 * @return Customweb_UnzerCw_Block_Adminhtml_Customer_Alias
	 */
	protected function _prepareColumns()
	{
		$this->addColumn('alias_for_display', array(
				'header' => Mage::helper('UnzerCw')->__('Alias For Display'),
				'width' => '250px',
				'type' => 'text',
				'index' => 'alias_for_display',
			));

		$this->addColumn('payment_method', array(
				'header' => Mage::helper('UnzerCw')->__('Payment Method'),
				'type' => 'text',
				'index' => 'payment_method',
				'renderer' => 'Customweb_UnzerCw_Block_Adminhtml_Customer_Alias_PaymentMethod'
			));

		$this->addColumn('action', array(
			'header' => Mage::helper('UnzerCw')->__('Action'),
			'width' => '50px',
			'type' => 'action',
			'getter' => 'getTransactionId',
			'actions' => array(
				array(
					'caption' => Mage::helper('UnzerCw')->__('Delete'),
					'url' => array(
						'base' => 'adminhtml/aliasunzercw/delete'
					),
					'field' => 'transaction_id'
				)
			),
			'filter' => false,
			'sortable' => false,
			'index' => 'stores',
			'is_system' => true,
		));

		return parent::_prepareColumns();
	}

	public function getRowUrl($row)
	{
		return;
	}

	public function getGridUrl()
	{
		return $this->getTabUrl();
	}

	/**
	 * ######################## TAB settings #################################
	 */

	public function getTabUrl()
	{
		return $this->getUrl('adminhtml/aliasunzercw/grid', array(
			'id' => Mage::registry('current_customer')->getId(),
			'_current' => true
		));
	}

	public function getTabClass()
	{
		return 'ajax';
	}

	public function getTabLabel()
	{
		return Mage::helper('UnzerCw')->__('UnzerCw Aliases');
	}

	public function getTabTitle()
	{
		return Mage::helper('UnzerCw')->__('UnzerCw Aliases');
	}

	public function canShowTab()
	{
		return true;
	}

	public function isHidden()
	{
		return false;
	}
}