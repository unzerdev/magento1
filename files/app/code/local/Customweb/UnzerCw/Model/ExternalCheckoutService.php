<?php

/**
 *  * You are allowed to use this API in your web application.
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
 * @category Customweb
 * @package Customweb_UnzerCw
 *
 */

/**
 *
 * @author Simon Schurter
 * @Bean
 */
class Customweb_UnzerCw_Model_ExternalCheckoutService implements Customweb_Payment_ExternalCheckout_ICheckoutService {

	/**
	 *
	 * @var Customweb_DependencyInjection_IContainer
	 */
	private $container;

	/**
	 *
	 * @var Customweb_Payment_ExternalCheckout_IProviderService
	 */
	private $providerService;

	/**
	 *
	 * @var Customweb_Payment_ITransactionHandler
	 */
	private $transactionHandler;

	/**
	 * Constructor
	 *
	 * @param Customweb_DependencyInjection_IContainer $container
	 */
	public function __construct(Customweb_DependencyInjection_IContainer $container){
		$this->container = $container;
		$this->providerService = $container->getBean('Customweb_Payment_ExternalCheckout_IProviderService');
		$this->transactionHandler = $container->getBean('Customweb_Payment_ITransactionHandler');
	}

	public function loadContext($contextId, $cache = true){
		return Mage::getModel('unzercw/externalCheckoutContext')->load($contextId);
	}

	public function createSecurityToken(Customweb_Payment_ExternalCheckout_IContext $context){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		$token = Customweb_Core_Util_Rand::getUuid();

		if($context->getSecurityToken() == null){
			$context->setSecurityToken($token);
			$context->setSecurityTokenExpiryDate(Customweb_Core_DateTime::_()->addHours(4)->format("Y-m-d H:i:s"));
			$context->save();
		}
		return $context->getSecurityToken();
	}

	public function checkSecurityTokenValidity(Customweb_Payment_ExternalCheckout_IContext $context, $token){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		if ($context->getSecurityToken() === null || $context->getSecurityToken() !== $token) {
			throw new Customweb_Payment_Exception_ExternalCheckoutInvalidTokenException();
		}
		$expiryDate = $context->getSecurityTokenExpiryDate();
		if (!empty($expiryDate)) {
			$expiryDate = new Customweb_Core_DateTime(DateTime::createFromFormat("Y-m-d H:i:s", $expiryDate));
			if ($expiryDate->getTimestamp() > time()) {
				return;
			}
		}
		throw new Customweb_Payment_Exception_ExternalCheckoutTokenExpiredException();

	}

	public function markContextAsFailed(Customweb_Payment_ExternalCheckout_IContext $context, $message){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		if ($context->getState() == Customweb_Payment_ExternalCheckout_IContext::STATE_COMPLETED) {
			throw new Exception("The external checkout context cannot be set to state FAILED, while the context is already in state COMPLETED.");
		}
		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_FAILED);
		$context->setFailedErrorMessage($message);
		$context->save();

		Mage::getSingleton('core/session')->addError($message);
	}

	public function updateProviderData(Customweb_Payment_ExternalCheckout_IContext $context, array $data){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$context->setProviderData($data);
		$this->refreshContext($context);
		$context->save();
	}

	public function authenticate(Customweb_Payment_ExternalCheckout_IContext $context, $emailAddress, $successUrl){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		if ($context->getBillingAddress() === null) {
			$billingAddress = new Customweb_Payment_Authorization_OrderContext_Address_Default();
			$billingAddress->setFirstName('First')->setLastName('Last')->setCity('unknown')->setStreet('unknown 1')->setCountryIsoCode('DE')->setPostCode(
					'10000');
			$context->setBillingAddress($billingAddress);
		}

		$this->redirectOnEmptyBasket();

		$quote = $context->getQuote();

		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			$context->updateFromQuote($quote);
			$context->save();
			return Customweb_Core_Http_Response::redirect($successUrl);
		}

		if ($quote->isAllowedGuestCheckout() && Mage::getStoreConfig('unzercw/general/external_checkout_account_creation') == 'skip_selection' && !empty($emailAddress)) {
			$customerData = array(
				'email' => $emailAddress,
				'firstname' => $context->getBillingAddress()->getFirstName(),
				'lastname' => $context->getBillingAddress()->getLastName(),
			);
			$dateOfBirth = $context->getBillingAddress()->getDateOfBirth();
			if ($dateOfBirth != null) {
				$customerData['dob'] = Mage::helper('UnzerCw/externalCheckout')->getDateAsString($dateOfBirth);
			}
			$gender = $context->getBillingAddress()->getGender();
			if ($gender != null) {
				if ($gender == 'male') {
					$customerData['gender'] = 1;
				} elseif ($gender == 'female') {
					$customerData['gender'] = 2;
				}
			}
			$result = Mage::helper('UnzerCw/externalCheckout')->validateCustomerData($quote, $customerData, 'guest');
			if ($result !== true) {
				throw new Exception(implode(', ', $result));
			}
			$quote->collectTotals();
			$quote->save();
			$context->setRegisterMethod('guest');
			$context->updateFromQuote($quote);
			$context->save();
			return Customweb_Core_Http_Response::redirect($successUrl);
		}

		$context->setAuthenticationEmailAddress($emailAddress);
		$context->setAuthenticationSuccessUrl($successUrl);
		$context->save();

		return Customweb_Core_Http_Response::redirect(Mage::getUrl('UnzerCw/Externalcheckout/login', array(
			'_secure' => true
		)));
	}

	public function updateCustomerEmailAddress(Customweb_Payment_ExternalCheckout_IContext $context, $emailAddress){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$context->setCustomerEmailAddress($emailAddress);
		$this->refreshContext($context);
		$this->updateUserSessionWithCurrentUser($context);
		$context->save();
	}

	public function updateShippingAddress(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Payment_Authorization_OrderContext_IAddress $address){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->checkAddress($address);
		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$context->setShippingAddress($address);
		$this->refreshContext($context);
		$context->save();
	}

	public function updateBillingAddress(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Payment_Authorization_OrderContext_IAddress $address){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->checkAddress($address);
		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$context->setBillingAddress($address);
		$this->refreshContext($context);
		$this->updateUserSessionWithCurrentUser($context);
		$context->save();
	}

	public function renderShippingMethodSelectionPane(Customweb_Payment_ExternalCheckout_IContext $context, $errorMessages){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		if (!empty($errorMessages)) {
			Mage::getSingleton('core/session')->addError($errorMessages);
		}

		$block = Mage::getSingleton('core/layout')->createBlock('unzercw/externalCheckout_shippingMethods')->setContext($context);
		return $block->toHtml();
	}

	public function updateShippingMethod(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$this->updateShippingMethodOnContext($context, $request);
		$context->setShippingMethodName($this->extractShippingName($context, $request));
		$this->refreshContext($context);
		$context->save();
	}

	public function getPossiblePaymentMethods(Customweb_Payment_ExternalCheckout_IContext $context){
		$activePaymentMethods = Mage::getSingleton('payment/config')->getAllMethods();
		$modulePaymentMethods = array();
		foreach ($activePaymentMethods as $paymentMethodCode => $paymentMethod) {
			if (strpos($paymentMethodCode, 'unzercw') === 0) {
				$modulePaymentMethods[] = $paymentMethod;
			}
		}
		return $modulePaymentMethods;
	}

	public function updatePaymentMethod(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Payment_Authorization_IPaymentMethod $method){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_PENDING);
		$context->setPaymentMethod($method);
		$context->save();
		$this->refreshContext($context);
		$context->save();
	}

	public function renderReviewPane(Customweb_Payment_ExternalCheckout_IContext $context, $renderConfirmationFormElements, $errorMessage){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		if (!empty($errorMessage)) {
			Mage::getSingleton('core/session')->addError($errorMessage);
		}

		$layout = Mage::getSingleton('core/layout');
		$update = $layout->getUpdate();
		$update->load('unzercw_external_checkout_review');
		$layout->generateXml();
		$layout->generateBlocks();
		$layout->getBlock('root')->setContext($context)->setRenderConfirmationFormElements($renderConfirmationFormElements);
		$output = $layout->getOutput();
		return $output;
	}

	public function validateReviewForm(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		$parameters = $request->getParameters();
		$helper = Mage::helper('UnzerCw');

		$quote = $context->getQuote();
		if ($context->hasBasketChanged($quote, $request)) {
			$context->updateFromQuote($quote);
			$context->save();
			throw new Exception(Mage::helper('UnzerCw')->__('The shopping cart has been altered!'));
		}

		if ($context->getShippingMethodName() == null) {
			throw new Exception($helper->__('Please select a shipping method before sending the order.'));
		}

		$requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
		if ($requiredAgreements) {
			if (empty($parameters['agreement']) || !is_array($parameters['agreement'])) {
				$parameters['agreement'] = array();
			}
			$postedAgreements = array_keys($parameters['agreement']);
			$diff = array_diff($requiredAgreements, $postedAgreements);
			if ($diff) {
				throw new Exception($helper->__('Please agree to all the terms and conditions before placing the order.'));
			}
		}
	}

	public function renderAdditionalFormElements(Customweb_Payment_ExternalCheckout_IContext $context, $errorMessage){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		if (!empty($errorMessage)) {
			Mage::getSingleton('core/session')->addError($errorMessage);
		}

		$block = Mage::getSingleton('core/layout')->createBlock('unzercw/externalCheckout_additionalInformation')->setContext($context);
		return $block->toHtml();
	}

	public function processAdditionalFormElements(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		$parameters = $request->getParameters();
		$helper = Mage::helper('UnzerCw');

		$update = false;

		if (Mage::helper('UnzerCw/externalCheckout')->isGenderRequired($context->getQuote())) {
			if (!isset($parameters['gender']) || empty($parameters['gender'])) {
				throw new Exception($helper->__('The gender is required.'));
			} else {
				$context->getQuote()->setCustomerGender($parameters['gender']);
				if ($context->getQuote()->getCustomerId() != null) {
					$context->getQuote()->getCustomer()->setGender($parameters['gender']);
				}
				$update = true;
			}
		}

		if (Mage::helper('UnzerCw/externalCheckout')->isDateOfBirthRequired($context->getQuote())) {
			if ((!isset($parameters['day']) || empty($parameters['day']))
					|| (!isset($parameters['month']) || empty($parameters['month']))
					|| (!isset($parameters['year']) || empty($parameters['year']))) {
				throw new Exception($helper->__('The date of birth is required.'));
			} else {
				$dob = $parameters['year'] . '-' . $parameters['month'] . '-' . $parameters['day'] . ' 00:00:00';
				$context->getQuote()->setCustomerDob($dob);
				if ($context->getQuote()->getCustomerId() != null) {
					$context->getQuote()->getCustomer()->setDob($dob);
				}
				$update = true;
			}
		}

		if ($update) {
			$context->getQuote()->save();
			if ($context->getQuote()->getCustomerId() != null) {
				$context->getQuote()->getCustomer()->save();
			}
		}
	}

	public function createOrder(Customweb_Payment_ExternalCheckout_IContext $context){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}
		if ($context->getState() == Customweb_Payment_ExternalCheckout_IContext::STATE_COMPLETED) {
			$transcationId = $context->getTransactionId();
			if (empty($transcationId)) {
				throw new Exception("Invalid state. The context can not be in state COMPLETED without transaction id set.");
			}
			return $this->getTransactionHandler()->findTransactionByTransactionId($transcationId);
		}
		else if ($context->getState() == Customweb_Payment_ExternalCheckout_IContext::STATE_FAILED) {
			throw new Exception("A failed context cannot be completed.");
		}

		$this->checkContextCompleteness($context);
		$this->getTransactionHandler()->beginTransaction();
		try {
			$transactionContext = $this->createTransactionContextFromContext($context);
			$transactionObject = $this->getProviderService()->createTransaction($transactionContext, $context);
			$this->getTransactionHandler()->persistTransactionObject($transactionObject);
			$context->setTransactionId($transactionObject->getTransactionContext()->getTransactionId());
			$context->setState(Customweb_Payment_ExternalCheckout_IContext::STATE_COMPLETED);
			$context->save();
			$this->getTransactionHandler()->commitTransaction();
			return $transactionObject;
		}
		catch (Exception $e) {
			$this->getTransactionHandler()->rollbackTransaction();
			Mage::helper('UnzerCw')->logException($e);
			throw $e;
		}
	}

	private function checkContextCompleteness(Customweb_Payment_ExternalCheckout_IContext $context){
		Customweb_Core_Assert::notNull($context->getBillingAddress(), "The context must contain a billing address, before it can be COMPLETED.");
		Customweb_Core_Assert::notNull($context->getShippingAddress(),
				"The context must contain a shipping address, before it can be COMPLETED. You may use the billing address when no shipping address is present.");
		Customweb_Core_Assert::hasLength($context->getShippingMethodName(),
				"The context must contain a shipping method name, before it can be COMPLETED.");
		Customweb_Core_Assert::notNull($context->getBillingAddress(), "The context must contain a billing address, before it can be COMPLETED.");
		Customweb_Core_Assert::hasSize($context->getInvoiceItems(), "At least one line item must be added before it can be COMPLETED.");
		Customweb_Core_Assert::hasLength($context->getCustomerEmailAddress(),
				"The context must contain an e-mail address before it can be COMPLETED.");
	}

	private final function checkAddress(Customweb_Payment_Authorization_OrderContext_IAddress $address) {
		Customweb_Core_Assert::hasLength($address->getFirstName(), "The address must contain a firstname.");
		Customweb_Core_Assert::hasLength($address->getLastName(), "The address must contain a lastname.");
		Customweb_Core_Assert::hasLength($address->getStreet(), "The address must contain a street.");
		Customweb_Core_Assert::hasLength($address->getPostCode(), "The address must contain a post code.");
		Customweb_Core_Assert::hasLength($address->getCountryIsoCode(), "The address must contain a country.");
		Customweb_Core_Assert::hasLength($address->getCity(), "The address must contain a city.");
	}

	private function updateShippingMethodOnContext(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		$parameters = $request->getParameters();
		$shippingMethod = $parameters['shipping_method'];

		$quote = $context->getQuote();
		$quote->getShippingAddress()->setShippingMethod($shippingMethod);
		$quote->setTotalsCollectedFlag(false)->collectTotals()->save();
	}

	private function extractShippingName(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$quoteId = $context->getQuoteId();
		if (!empty($quoteId)) {
			return $context->getQuote()->getShippingAddress()->getShippingDescription();
		}
		else {
			return null;
		}
	}

	private function createTransactionContextFromContext(Customweb_Payment_ExternalCheckout_IContext $context){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$this->redirectOnEmptyBasket();

		$quote = $context->getQuote();
		$quote->collectTotals()->save();

		$isNewCustomer = false;
		switch ($context->getRegisterMethod()) {
			case 'guest':
				$this->prepareGuestQuote($context);
				break;
			case 'register':
				$this->prepareNewCustomerQuote($context);
				$isNewCustomer = true;
				break;
			default:
				$this->prepareCustomerQuote($context);
				break;
		}

		$quote->getBillingAddress()->setShouldIgnoreValidation(true);
		$quote->getShippingAddress()->setShouldIgnoreValidation(true);

		try {
			Mage::register('cw_is_externalcheckout', true);
			$service = Mage::getModel('sales/service_quote', $quote);
			$service->submitAll();
			Mage::unregister('cw_is_externalcheckout');
		} catch (Exception $e) {
			Mage::unregister('cw_is_externalcheckout');
			throw $e;
		}

		if ($isNewCustomer) {
			try {
				$this->involveNewCustomer($context);
			}
			catch (Exception $e) {
				Mage::helper('UnzerCw')->logException($e);
			}
		}

		Mage::getSingleton('checkout/session')->setLastQuoteId($quote->getId())->setLastSuccessQuoteId($quote->getId())->clearHelperData();

		$order = $service->getOrder();

		Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())->setRedirectUrl(false)->setLastRealOrderId($order->getIncrementId());

		$transaction = Mage::getModel('unzercw/transaction');
		$transaction->setOrderId($order->getId());
		$transaction->setOrderPaymentId($order->getPayment()->getId());
		$transaction->save();

		return $context->getPaymentMethod()->getTransactionContext($order, $transaction);
	}

	private function prepareGuestQuote(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		$quote = $context->getQuote();
		$quote->setCustomerId(null)->setCustomerEmail($context->getCustomerEmailAddress())->setCustomerIsGuest(true)->setCustomerGroupId(
				Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
	}

	private function prepareNewCustomerQuote(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		$quote = $context->getQuote();
		$billing = $quote->getBillingAddress();
		$shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

		$customer = $quote->getCustomer();
		$customerBilling = $billing->exportCustomerAddress();
		$customer->addAddress($customerBilling);
		$billing->setCustomerAddress($customerBilling);
		$customerBilling->setIsDefaultBilling(true);
		if ($shipping && !$shipping->getSameAsBilling()) {
			$customerShipping = $shipping->exportCustomerAddress();
			$customer->addAddress($customerShipping);
			$shipping->setCustomerAddress($customerShipping);
			$customerShipping->setIsDefaultShipping(true);
		}
		else {
			$customerBilling->setIsDefaultShipping(true);
		}

		Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
		$customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
		$quote->setCustomer($customer)->setCustomerId(true);
	}

	private function prepareCustomerQuote(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		$quote = $context->getQuote();
		$billing = $quote->getBillingAddress();
		$shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

		$customer = Mage::getSingleton('customer/session')->getCustomer();
		if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
			$customerBilling = $billing->exportCustomerAddress();
			$customer->addAddress($customerBilling);
			$billing->setCustomerAddress($customerBilling);
		}
		if ($shipping && !$shipping->getSameAsBilling() && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())) {
			$customerShipping = $shipping->exportCustomerAddress();
			$customer->addAddress($customerShipping);
			$shipping->setCustomerAddress($customerShipping);
		}

		if (isset($customerBilling) && !$customer->getDefaultBilling()) {
			$customerBilling->setIsDefaultBilling(true);
		}
		if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
			$customerShipping->setIsDefaultShipping(true);
		}
		else if (isset($customerBilling) && !$customer->getDefaultShipping()) {
			$customerBilling->setIsDefaultShipping(true);
		}
		$quote->setCustomer($customer);
	}

	private function involveNewCustomer(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		$customer = $context->getQuote()->getCustomer();
		Mage::getSingleton('customer/session')->loginById($customer->getId());
	}

	private function refreshContext(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		$quote = $context->getQuote();

		if (!$quote->isVirtual() && $context->getShippingAddress() !== null &&
				 $context->getShippingAddress() instanceof Customweb_Payment_Authorization_OrderContext_IAddress) {
			$this->updateAddress($quote->getShippingAddress(), $context->getShippingAddress());
		}

		if ($context->getBillingAddress() !== null && $context->getBillingAddress() instanceof Customweb_Payment_Authorization_OrderContext_IAddress) {
			$this->updateAddress($quote->getBillingAddress(), $context->getBillingAddress());

// 			$dateOfBirth = $context->getBillingAddress()->getDateOfBirth();
// 			if ($dateOfBirth != null && $dateOfBirth instanceof DateTime) {
// 				$quote->setCustomerDob($dateOfBirth->format('Y-m-d H:i:s'));
// 			}

// 			$gender = $context->getBillingAddress()->getGender();
// 			if ($gender == 'male') {
// 				$quote->setCustomerGender(1);
// 			}
// 			elseif ($gender == 'female') {
// 				$quote->setCustomerGender(2);
// 			}
// 			else {
// 				$quote->setCustomerGender(null);
// 			}
		}

		$customerId = $context->getCustomerId();
		if (!empty($customerId)) {
			$quote->setCustomer(Mage::getModel('customer/customer')->load($customerId))->setCustomerId($customerId);
		}
		else {
			$quote->setCustomerId(null);
		}

		$customerEmailAddress = $context->getCustomerEmailAddress();
		if (!empty($customerEmailAddress)) {
			$quote->setCustomerEmail($customerEmailAddress);
		}
		else {
			$quote->setCustomerEmail(null);
		}

		$paymentMethod = $context->getPaymentMethodMachineName();
		if (!empty($paymentMethod)) {
			if ($quote->isVirtual()) {
				$quote->getBillingAddress()->setPaymentMethod($paymentMethod);
			}
			else {
				$quote->getShippingAddress()->setPaymentMethod($paymentMethod);
			}

			$data = array(
				'method' => 'unzercw_' . $paymentMethod,
				'checks' => 4 | 1 | 2 | 32 | 128,
			);

			$quote->setUnzerCwExternalCheckout(true);
			$payment = $quote->getPayment();
			$payment->importData($data);
		}

		if (!$quote->isVirtual() && $quote->getShippingAddress()) {
			$quote->getShippingAddress()->setCollectShippingRates(true);
		}

		if ($quote->getShippingAddress()->getShippingMethod() == null) {
			$address = $quote->getShippingAddress();
			$address->collectShippingRates()->save();
			$shippingRates = $address->getGroupedAllShippingRates();
			if (count($shippingRates) == 1) {
				$rates = current($shippingRates);
				if (count($rates) == 1) {
					$quote->getShippingAddress()->setShippingMethod(current($rates)->getCode());
				}
			}
		}

		$quote->setTotalsCollectedFlag(false)->collectTotals()->save();

		if ($quote->isVirtual()) {
			$context->setShippingMethodName(Mage::helper('UnzerCw')->__('No shipping method needed.'));
		} else {
			$context->setShippingMethodName($quote->getShippingAddress()->getShippingDescription());
		}

		$context->updateFromQuote($quote);

		$allowCountries = explode(',', (string)Mage::getStoreConfig('general/country/allow'));
		if ($context->getBillingAddress() !== null && $context->getBillingAddress() instanceof Customweb_Payment_Authorization_OrderContext_IAddress) {
			if (!in_array($context->getBillingAddress()->getCountryIsoCode(), $allowCountries)) {
				throw new Exception(Mage::helper('UnzerCw')->__('It is not possible to checkout in your country.'));
			}
		}
		if ($context->getShippingAddress() !== null && $context->getShippingAddress() instanceof Customweb_Payment_Authorization_OrderContext_IAddress) {
			if (!in_array($context->getShippingAddress()->getCountryIsoCode(), $allowCountries)) {
				throw new Exception(Mage::helper('UnzerCw')->__('It is not possible to checkout in your country.'));
			}
		}
	}

	private function updateAddress(Mage_Sales_Model_Quote_Address $target, Customweb_Payment_Authorization_OrderContext_IAddress $source){
		$target->setEmail($source->getEMailAddress())->setFirstname($source->getFirstName())->setLastname($source->getLastName())->setCompany(
				$source->getCompanyName())->setCity($source->getCity())->setPostcode($source->getPostCode())->setTelephone($source->getPhoneNumber())->setStreet(
				$source->getStreet())->setCountryId($source->getCountryIsoCode())->setRegion($source->getState());

		$region = Mage::getModel('directory/region')->loadByCode($source->getState(), $source->getCountryIsoCode());
		if ($region != null && $region->getId()) {
			$target->setRegion($region->getName())->setRegionId($region->getId());
		}
	}

	private function updateUserSessionWithCurrentUser(Customweb_UnzerCw_Model_ExternalCheckoutContext $context){
		if (!($context instanceof Customweb_UnzerCw_Model_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('Customweb_UnzerCw_Model_ExternalCheckoutContext');
		}

		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			$quote = $context->getQuote();
			$context->updateFromQuote($quote);
			$context->save();
			return;
		}

		$customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId());

		$customerId = $context->getCustomerId();
		$customerEmail = $context->getCustomerEmailAddress();
		if (!empty($customerId)) {
			$customer->load($customerId);
		}
		elseif (!empty($customerEmail)) {
			$customer->loadByEmail($customerEmail);
		}
		else {
			return;
		}

		$quote = $context->getQuote();
		if ($customer->getId()) {
			Mage::register('unzercw_external_checkout_login', true);
			Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
			$quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER)->save();
			Mage::unregister('unzercw_external_checkout_login');
		}
		elseif ($context->getBillingAddress() !== null) {
			$quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST)->setCustomerId(null)->setCustomerEmail(
					$context->getCustomerEmailAddress())->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)->save();
			$context->setRegisterMethod('guest');
		}
	}

	/**
	 *
	 * @return Customweb_Payment_ExternalCheckout_IProviderService
	 */
	private function getProviderService(){
		return $this->providerService;
	}

	/**
	 *
	 * @return Customweb_Payment_ITransactionHandler
	 */
	private function getTransactionHandler(){
		return $this->transactionHandler;
	}

	private function redirectOnEmptyBasket()
	{
		if (!Mage::getSingleton('checkout/cart')->getItemsCount()) {
			header("Location: " . Mage::getUrl('checkout/cart', array(
				'_secure' => true
			)));
			die();
		}
	}
}