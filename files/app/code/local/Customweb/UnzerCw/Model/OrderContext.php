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

class Customweb_UnzerCw_Model_OrderContext extends Customweb_Payment_Authorization_OrderContext_AbstractDeprecated implements Customweb_Payment_Authorization_IOrderContext
{
	/**
	 * Transient fields
	 */
	private $orderCache = null;
	private $quoteCache = null;

	/**
	 * Fields beeing serialized
	 * 
	 */
	private $orderId = null;
	private $quoteId = null;
	private $isOrderAvailable = null;
	private $paymentMethod = null;
	private $storeId = null;
	private $languageCode = "en_US";
	private $currencyCode = null;
	private $useBaseCurrency = false;
	private $orderParameters = array();
	private $isNewSKUFormat = false;
	private $checkoutId = null;

	/**
	 *
	 * @param Customweb_UnzerCw_Model_Method $paymentMethod
	 * @param boolean $isOrderAvailable
	 */
	public function __construct(Customweb_UnzerCw_Model_Method $paymentMethod, $isOrderAvailable, $storeId = null, $quote = null)
	{
		$this->isOrderAvailable = $isOrderAvailable;
		$this->paymentMethod = $paymentMethod;
		if ($storeId != null) {
			$this->storeId = $storeId;
		} else {
			$this->storeId = Mage::app()->getStore()->getStoreId();
		}
		if ($quote != null) {
			$this->quoteCache = $quote;
		}
		$this->languageCode = Mage::app()->getLocale()->getLocaleCode();

		if ($paymentMethod->getPaymentMethodConfigurationValue('use_base_currency')) {
			$this->useBaseCurrency = true;
			$this->currencyCode = $this->getStore()->getBaseCurrencyCode();
		} else {
			$this->useBaseCurrency = false;
			$this->currencyCode = $this->getStore()->getCurrentCurrencyCode();
		}


		$session = Mage::getSingleton('core/session');
		$checkoutId = $session->getUnzerCwCheckoutId();
		if($checkoutId === null) {
			$checkoutId = Customweb_Core_Util_Rand::getUuid();
			$session->setUnzerCwCheckoutId($checkoutId);
		}
		$this->checkoutId = $checkoutId;

		$this->isNewSKUFormat = true;
	}

	public function __sleep()
	{
		return array(
			'isOrderAvailable',
			'paymentMethod',
			'orderId',
			'quoteId',
			'storeId',
			'languageCode',
			'currencyCode',
			'useBaseCurrency',
			'isNewSKUFormat',
			'checkoutId',
		);
	}

	/**
	 * @return boolean
	 */
	public function useBaseCurrency() {
		return $this->useBaseCurrency;
	}

	/**
	 * @return Mage_Core_Model_Store
	 */
	public function getStore() {
		return Mage::app()->getStore($this->storeId);
	}

	public static function fromOrder($order)
	{
		$orderContext = new Customweb_UnzerCw_Model_OrderContext($order->getPayment()
			->getMethodInstance(), true, $order->getStore()->getStoreId());
		$orderContext->setOrder($order);

		if ($orderContext->useBaseCurrency()) {
			$orderContext->currencyCode = $order->getBaseCurrencyCode();
		}
		else {
			$orderContext->currencyCode = $order->getOrderCurrencyCode();
		}
		return $orderContext;
	}

	public function getInvoiceItems()
	{
		return Mage::getModel('unzercw/invoiceItems')->getInvoiceItems($this->getOrderQuote(), $this->getStore()->getStoreId(), $this->useBaseCurrency());
	}

	public function getCustomerId()
	{
		return $this->getOrderQuote()
			->getCustomerId();
	}

	public function isNewCustomer()
	{
		$customerId = $this->getCustomerId();
		$orders = Mage::getResourceModel('sales/order_collection')->addFieldToSelect('*')
			->addFieldToFilter('customer_id', $customerId);

		foreach ($orders as $order) {
			if ($order->getState() == 'complete') {
				return 'existing';
			}
		}

		return 'new';
	}

	public function getCustomerRegistrationDate()
	{
		$customerId = $this->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);
		return new DateTime($customer->getCreatedAt());
	}

	public function getOrderAmountInDecimals()
	{
		if ($this->useBaseCurrency()) {
			return $this->getOrderQuote()->getBaseGrandTotal();
		} else {
			return $this->getOrderQuote()->getGrandTotal();
		}
	}

	public function getCurrencyCode()
	{
		if ($this->currencyCode === null) {
			return $this->getStore()->getCurrentCurrencyCode();
		}
		else {
			return $this->currencyCode;
		}
	}



	public function getShippingMethod()
	{
		$shippingAddress = $this->getOrderQuote()->getShippingAddress();
		if ($shippingAddress != null) {
			return $shippingAddress->getShippingMethod();
		}
	}

	public function getPaymentMethod()
	{
		return $this->paymentMethod;
	}

	public function getLanguage()
	{
		return new Customweb_Core_Language($this->languageCode);
	}

	public function getCustomerEMailAddress()
	{
		$customerId = $this->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);
		$customerMail = $customer->getEmail();
		if (empty($customerMail)) {
			return $this->getInternalBillingAddress()->getEmail();
		} else {
			return $customerMail;
		}
	}

	public function getBillingEMailAddress()
	{
		$billingEmail = $this->getInternalBillingAddress()
			->getEmail();
		if ($billingEmail != null) {
			return $this->getInternalBillingAddress()
				->getEmail();
		} else {
			return $this->getCustomerEMailAddress();
		}
	}

	public function getBillingGender()
	{
		$gender = $this->getOrderQuote()->getCustomerGender();

		$customerId = $this->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);

		if ($gender !== null) {
			$gender = $customer->getAttribute('gender')->getSource()->getOptionText($gender);
			return strtolower($gender);
		}

		if ($customer->getGender() !== null) {
			$gender = $customer->getAttribute('gender')->getSource()->getOptionText($customer->getGender());
			return strtolower($gender);
		}
	}

	public function getBillingSalutation()
	{
		return null;
	}

	public function getBillingFirstName()
	{
		return $this->getInternalBillingAddress()
			->getFirstname();
	}

	public function getBillingLastName()
	{
		return $this->getInternalBillingAddress()
			->getLastname();
	}

	public function getBillingStreet()
	{
		return implode(' ', $this->getInternalBillingAddress()
			->getStreet());
	}

	public function getBillingCity()
	{
		return $this->getInternalBillingAddress()
			->getCity();
	}

	public function getBillingPostCode()
	{
		return $this->getInternalBillingAddress()
			->getPostcode();
	}

	public function getBillingState()
	{
		return $this->getInternalBillingAddress()
			->getRegionCode();
	}

	public function getBillingCountryIsoCode()
	{
		return $this->getInternalBillingAddress()
			->getCountryId();
	}

	public function getBillingPhoneNumber()
	{
		return $this->getInternalBillingAddress()
			->getTelephone();
	}

	public function getBillingMobilePhoneNumber()
	{
		return null;
	}

	public function getBillingDateOfBirth()
	{
		$dob = $this->getOrderQuote()->getCustomerDob();

		if ($dob !== null) {
			return new DateTime($dob);
		}

		$customerId = $this->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);
		$dob = $customer->getDob();

		if ($dob !== null) {
			return new DateTime($dob);
		} else {
			return null;
		}
	}

	public function getBillingCommercialRegisterNumber()
	{
		return null;
	}

	public function getBillingCompanyName()
	{
		return $this->getInternalBillingAddress()
			->getCompany();
	}

	public function getBillingSalesTaxNumber()
	{
		return null;
	}

	public function getBillingSocialSecurityNumber()
	{
		return null;
	}

	public function getShippingEMailAddress()
	{
		$shippingEmail = $this->getInternalShippingAddress()
			->getEmail();
		if ($shippingEmail != null) {
			return $shippingEmail;
		} else {
			return $this->getBillingEMailAddress();
		}
	}

	public function getShippingGender()
	{
		return null;
	}

	public function getShippingSalutation()
	{
		return null;
	}

	public function getShippingFirstName()
	{
		return $this->getInternalShippingAddress()
			->getFirstname();
	}

	public function getShippingLastName()
	{
		return $this->getInternalShippingAddress()
			->getLastname();
	}

	public function getShippingStreet()
	{
		return implode(' ', $this->getInternalShippingAddress()
			->getStreet());
	}

	public function getShippingCity()
	{
		return $this->getInternalShippingAddress()
			->getCity();
	}

	public function getShippingPostCode()
	{
		return $this->getInternalShippingAddress()
			->getPostcode();
	}

	public function getShippingState()
	{
		return $this->getInternalShippingAddress()
			->getRegionCode();
	}

	public function getShippingCountryIsoCode()
	{
		return $this->getInternalShippingAddress()
			->getCountryId();
	}

	public function getShippingPhoneNumber()
	{
		return $this->getInternalShippingAddress()
			->getTelephone();
	}

	public function getShippingMobilePhoneNumber()
	{
		return null;
	}

	public function getShippingDateOfBirth()
	{
		return null;
	}

	public function getShippingCompanyName()
	{
		return $this->getInternalShippingAddress()
			->getCompany();
	}

	public function getShippingCommercialRegisterNumber()
	{
		return null;
	}

	public function getShippingSalesTaxNumber()
	{
		return null;
	}

	public function getShippingSocialSecurityNumber()
	{
		return null;
	}

	public function getOrderParameters()
	{
		return $this->orderParameters;
	}

	public function setOrderParameters(array $parameters) {
		$this->orderParameters = $parameters;
		return $this;
	}

	public function addOrderParameter($name, $value) {
		$this->orderParameters[$name] = $value;
		return $this;
	}

	public function getCheckoutId()
	{
		$checkoutId = $this->checkoutId;
		if($checkoutId === null){
			return $this->getQuote()->getId();
		}
		return $checkoutId;
	}

	public function isNewSKUFormat(){
		return $this->isNewSKUFormat;
	}

	private function getOrderQuote()
	{
		if ($this->isOrderAvailable) {
			return $this->getOrder();
		} else {
			return $this->getQuote();
		}
	}

	/**
	 * Returns the order from the checkout session
	 *
	 *  @return	  Mage_Sales_Model_Order
	 */
	private function getOrder()
	{
		if ($this->orderCache == null) {
			$order = Mage::getModel('sales/order');
			if ($this->orderId == null) {
				$session = Mage::getSingleton('checkout/session');
				$order->loadByIncrementId($session->getLastRealOrderId());
			} else {
				$order->load($this->orderId);

			}
			$this->setOrder($order);
		}
		return $this->orderCache;
	}

	private function setOrder($order)
	{
		$this->orderCache = $order;
		$this->orderId = $order->getId();
		$quote = Mage::getModel('sales/quote');
		$quote = $quote->setStoreId($order->getStoreId())
			->load((int) $order->getQuoteId());
		$this->setQuote($quote);
	}

	private function getQuote()
	{
		if ($this->quoteCache == null) {
			if ($this->quoteId == null) {
				$quote = Mage::getSingleton('checkout/session')->getQuote();
				if (!$quote->getId()) {
					$quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
				}
				$this->quoteId = $quote->getId();
				$this->quoteCache = $quote;
			} else {
				$quote = Mage::getModel('sales/quote');
				$quote->load($this->quoteId);
				$this->quoteCache = $quote;
			}

		}
		return $this->quoteCache;
	}

	private function setQuote($quote)
	{
		$this->quoteCache = $quote;
		$this->quoteId = $quote->getId();
	}

	private function getInternalBillingAddress()
	{
		return $this->getOrderQuote()
			->getBillingAddress();
	}

	private function getInternalShippingAddress()
	{
		$shippingAddress = $this->getOrderQuote()
			->getShippingAddress();
		if ($shippingAddress == null) {
			$shippingAddress = $this->getInternalBillingAddress();
		}
		return $shippingAddress;
	}
}
