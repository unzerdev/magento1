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
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setContextId(int $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setState(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setFailedErrorMessage(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setCartUrl(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setDefaultCheckoutUrl(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setOrderAmountInDecimals(double $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setCurrencyCode(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setLanguageCode(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setCustomerEmailAddress(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setCustomerId(int $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setTransactionId(int $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setShippingMethodName(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setPaymentMethodMachineName(string $value)
 * @method string getCreatedOn()
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setCreatedOn(string $value)
 * @method string getUpdatedOn()
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setUpdatedOn(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setSecurityToken(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setSecurityTokenExpiryDate(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setAuthenticationSuccessUrl(string $value)
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setAuthenticationEmailAddress(string $value)
 * @method int getQuoteId()
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setQuoteId(int $value)
 * @method string getRegisterMethod()
 * @method Customweb_UnzerCw_Model_ExternalCheckoutContext setRegisterMethod(string $value)
 * 
 */
class Customweb_UnzerCw_Model_ExternalCheckoutContext extends Mage_Core_Model_Abstract implements Customweb_Payment_ExternalCheckout_IContext 
{
	/**
	 *
	 * @var Customweb_Payment_Authorization_IInvoiceItem[]
	 */
	private $invoiceItems = array();
	
	/**
	 *
	 * @var Customweb_Payment_Authorization_OrderContext_IAddress
	 */
	private $shippingAddress = null;
	
	/**
	 *
	 * @var Customweb_Payment_Authorization_OrderContext_IAddress
	 */
	private $billingAddress = null;
	
	/**
	 *
	 * @var Customweb_Payment_Authorization_IPaymentMethod
	 */
	private $paymentMethod = null;
	
	/**
	 * 
	 * @var array
	 */
	private $providerData = array();
	
	/**
	 * 
	 * @var Mage_Sales_Model_Quote
	 */
	private $quote = null;
	
	protected function _construct()
	{
		$this->_init('unzercw/externalcheckoutcontext');
	}
	
	protected function _afterLoad()
	{
		parent::_afterLoad();
		
		$this->invoiceItems = Mage::helper('UnzerCw')->unserialize($this->getData('invoice_items'));
		$this->shippingAddress = Mage::helper('UnzerCw')->unserialize($this->getData('shipping_address'));
		$this->billingAddress = Mage::helper('UnzerCw')->unserialize($this->getData('billing_address'));
		$this->providerData = Mage::helper('UnzerCw')->unserialize($this->getData('provider_data'));
		
		if ($this->getData('state') == null) {
			$this->setData('state', self::STATE_PENDING);
		}
	}
	
	protected function _beforeSave()
	{
		parent::_beforeSave();
		
		if ($this->isObjectNew()) {
			$this->setCreatedOn(date("Y-m-d H:i:s"));
		}
		$this->setUpdatedOn(date("Y-m-d H:i:s"));
		
		$this->setData('invoice_items', Mage::helper('UnzerCw')->serialize($this->invoiceItems));
		$this->setData('shipping_address', Mage::helper('UnzerCw')->serialize($this->shippingAddress));
		$this->setData('billing_address', Mage::helper('UnzerCw')->serialize($this->billingAddress));
		$this->setData('provider_data', Mage::helper('UnzerCw')->serialize($this->providerData));
		
		if (is_object($this->paymentMethod)) {
			$this->setData('payment_method_machine_name', $this->paymentMethod->getPaymentMethodName());
		}
	}
	
	public function loadByQuote(Mage_Sales_Model_Quote $quote) {
		$this->load($quote->getId(), 'quote_id');
		return $this;
	}
	
	public function updateFromQuote(Mage_Sales_Model_Quote $quote) {
		$id = $this->getContextId();
		if (empty($id)) {
			throw new Exception("Before the context can be updated with a quote, the context must be stored in the database.");
		}
		$this->setQuoteId($quote->getId());
		
		$this->setLanguageCode(Mage::app()->getLocale()->getLocaleCode());
		
		$this->setCartUrl(Mage::getUrl('checkout/cart', array(
			'_secure' => true
		)));
		$this->setDefaultCheckoutUrl(Mage::getUrl('checkout/onepage', array(
			'_secure' => true
		)));
		
		$this->setInvoiceItems($this->collectInvoiceItems($quote));
		$this->setCurrencyCode($this->collectCurrency());
		
		$this->setCustomerId($quote->getCustomerId());
		$this->setCustomerEmailAddress($quote->getCustomerEmail());
	}
	
	public function hasBasketChanged(Mage_Sales_Model_Quote $quote, Customweb_Core_Http_IRequest $request){
		$parameters = $request->getParameters();
		if (!isset($parameters['external-checkout-context-updated-on']) || $parameters['external-checkout-context-updated-on'] != $this->getUpdatedOn()
			|| $this->collectCurrency() != $this->getCurrencyCode()
			|| $this->compareLineItems($this->collectInvoiceItems($quote), $this->getInvoiceItems(), $this->getCurrencyCode())) {
			return true;
		} else {
			return false;
		}
	}
	
	private function collectInvoiceItems(Mage_Sales_Model_Quote $quote){
		return Mage::getModel('unzercw/invoiceItems')->getInvoiceItems($quote, $quote->getStore()->getStoreId(), $this->useBaseCurrency());
	}
	
	/**
	 * 
	 * @param Customweb_Payment_Authorization_IInvoiceItem[] $originals
	 * @param Customweb_Payment_Authorization_IInvoiceItem[] $others
	 */
	private function compareLineItems(array $originals, array $others, $currency){
		if(count($originals) != count($others)){
			return false;
		}
		foreach($originals as $key => $original){
			
			$other = $others[$key];
			if($original->getSku() != $other->getSku()){
				return false;
			}
			if($original->getName() != $other->getName()){
				return false;
			}
			if(number_format($original->getTaxRate(), 4) != number_format($other->getTaxRate(), 4)){
				return false;
			}
			if(number_format($original->getQuantity(), 6) != number_format($other->getQuantity(), 6)){
				return false;
			}
			if(!Customweb_Util_Currency::compareAmount($original->getAmountIncludingTax(), $other->getAmountIncludingTax(), $currency)){
				return false;
			}
			if($original->isShippingRequired() != $other->isShippingRequired()){
				return false;
			}
			if($original->getType() != $other->getType()){
				return false;
			}
		}
		return true;	
	}
	
	private function collectCurrency(){
		if ($this->useBaseCurrency()) {
			return Mage::app()->getStore()->getBaseCurrencyCode();
		} else {
			return Mage::app()->getStore()->getCurrentCurrencyCode();
		}
	}
	
	private function useBaseCurrency(){
		$paymentMethod = $this->getPaymentMethod();
		if (is_object($paymentMethod)) {
			return (boolean) $paymentMethod->getPaymentMethodConfigurationValue('use_base_currency');
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * @return Mage_Sales_Model_Quote
	 */
	public function getQuote() {
		if ($this->quote == null) {
			$this->quote = Mage::getModel('sales/quote')->load($this->getQuoteId());
		}
		return $this->quote;
	}
	
	public function getContextId() {
		return $this->getData('context_id');
	}

	public function getState() {
		return $this->getData('state');
	}

	public function getFailedErrorMessage() {
		return $this->getData('failed_error_message');
	}

	public function getCartUrl() {
		return $this->getData('cart_url');
	}

	public function getDefaultCheckoutUrl() {
		return $this->getData('default_checkout_url');
	}

	public function getInvoiceItems() {
		return $this->invoiceItems;
	}
	
	public function setInvoiceItems($value) {
		if (is_string($value)) {
			$this->invoiceItems = Mage::helper('UnzerCw')->unserialize($value);
		} else {
			$this->invoiceItems = $value;
		}
		$this->setData('order_amount_in_decimals', Customweb_Util_Invoice::getTotalAmountIncludingTax($this->invoiceItems));
	}
	
	public function addInvoiceItem(Customweb_Payment_Authorization_IInvoiceItem $item){
		$this->invoiceItems[] = $item;
		$this->setData('order_amount_in_decimals', Customweb_Util_Invoice::getTotalAmountIncludingTax($this->invoiceItems));
		return $this;
	}

	public function getOrderAmountInDecimals() {
		return $this->getData('order_amount_in_decimals');
	}

	public function getCurrencyCode() {
		return $this->getData('currency_code');
	}

	public function getLanguage() {
		return new Customweb_Core_Language($this->getData('language_code'));
	}
	
	public function getLanguageCode() {
		return $this->getData('language_code');
	}

	public function getCustomerEmailAddress() {
		return $this->getData('customer_email_address');
	}

	public function getShippingAddress() {
		return $this->shippingAddress;
	}
	
	public function setShippingAddress($value) {
		if (is_string($value)) {
			$this->shippingAddress = Mage::helper('UnzerCw')->unserialize($value);
		} else {
			$this->shippingAddress = $value;
		}
	}

	public function getBillingAddress() {
		return $this->billingAddress;
	}
	
	public function setBillingAddress($value) {
		if (is_string($value)) {
			$this->billingAddress = Mage::helper('UnzerCw')->unserialize($value);
		} else {
			$this->billingAddress = $value;
		}
	}

	public function getShippingMethodName() {
		return $this->getData('shipping_method_name');
	}

	public function getProviderData() {
		return $this->providerData;
	}
	
	public function setProviderData($value) {
		if (is_string($value)) {
			$this->providerData = Mage::helper('UnzerCw')->unserialize($value);
		} else {
			$this->providerData = $value;
		}
	}

	public function getCustomerId() {
		return $this->getData('customer_id');
	}
	
	public function getTransactionId() {
		return $this->getData('transaction_id');
	}

	public function getPaymentMethod() {
		$paymentMethodMachineName = $this->getPaymentMethodMachineName();
		if ($this->paymentMethod == null && !empty($paymentMethodMachineName)) {
			$this->paymentMethod = Mage::helper('payment')->getMethodInstance('unzercw_' . $paymentMethodMachineName);
		}
		return $this->paymentMethod;
	}
	
	public function setPaymentMethod($paymentMethod){
		$this->paymentMethod = $paymentMethod;
		return $this;
	}
	
	public function getPaymentMethodMachineName() {
		return $this->getData('payment_method_machine_name');
	}
	
	public function getSecurityToken() {
		return $this->getData('security_token');
	}
	
	public function getSecurityTokenExpiryDate() {
		return $this->getData('security_token_expiry_date');
	}
	
	public function getAuthenticationSuccessUrl(){
		return $this->getData('authentication_success_url');
	}
	
	public function getAuthenticationEmailAddress(){
		return $this->getData('authentication_email_address');
	}

}