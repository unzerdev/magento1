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

class Customweb_UnzerCw_Block_ExternalCheckout_ShippingMethods extends Mage_Core_Block_Template
{
	private $_context;
	
	private $_shippingRates = null;
	
	private $_address;
	
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('customweb/unzercw/external_checkout/shipping-methods.phtml');
	}
	
	public function getShippingRates()
	{
		if ($this->_shippingRates == null) {
			$this->getAddress()->collectShippingRates()->save();
			$this->_shippingRates = $this->getAddress()->getGroupedAllShippingRates();
		}
		return $this->_shippingRates;
	}
	
	public function getAddress()
	{
		if (empty($this->_address)) {
			$this->_address = $this->getContext()->getQuote()->getShippingAddress();
		}
		return $this->_address;
	}
	
	public function getCarrierName($carrierCode)
	{
		if ($name = Mage::getStoreConfig('carriers/'.$carrierCode.'/title')) {
			return $name;
		}
		return $carrierCode;
	}
	
	public function getAddressShippingMethod()
	{
		return $this->getAddress()->getShippingMethod();
	}
	
	public function getShippingPrice($price, $flag)
	{
		return $this->getContext()->getQuote()->getStore()->convertPrice(Mage::helper('tax')->getShippingPrice($price, $flag, $this->getAddress()), true);
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
	
	/**
	 * 
	 * @return Mage_Sales_Model_Quote
	 */
	public function getQuote()
	{
		return $this->getContext()->getQuote();
	}
}
