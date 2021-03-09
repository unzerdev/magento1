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

class Customweb_UnzerCw_Block_ExternalCheckout_Review extends Mage_Sales_Block_Items_Abstract
{
	private $_context;

	protected function _construct()
	{
		parent::_construct();
	}

	public function isAdditionalInformationRequired()
	{
		return Mage::helper('UnzerCw/externalCheckout')->isGenderRequired($this->getQuote())
			|| 	Mage::helper('UnzerCw/externalCheckout')->isDateOfBirthRequired($this->getQuote());
	}

	public function getItems()
	{
		return Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
	}

	public function getTotals()
	{
		return Mage::getSingleton('checkout/session')->getQuote()->getTotals();
	}

	public function getAgreements()
	{
		if (!$this->hasAgreements()) {
			if (!Mage::getStoreConfigFlag('checkout/options/enable_agreements')) {
				$agreements = array();
			} else {
				$agreements = Mage::getModel('checkout/agreement')->getCollection()
				->addStoreFilter(Mage::app()->getStore()->getId())
				->addFieldToFilter('is_active', 1);
			}
			$this->setAgreements($agreements);
		}
		return $this->getData('agreements');
	}

	public function getQuote()
	{
		return $this->getContext()->getQuote();
	}

	/**
	 * @return Customweb_UnzerCw_Model_ExternalCheckoutContext
	 */
	public function getContext()
	{
		return $this->_context;
	}

	public function setContext(Customweb_UnzerCw_Model_ExternalCheckoutContext $context)
	{
		$this->_context = $context;
		return $this;
	}
}
