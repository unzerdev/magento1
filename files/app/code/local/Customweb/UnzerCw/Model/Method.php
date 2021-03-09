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

// Load our custom translation resolver
new Customweb_UnzerCw_Model_TranslationResolver();

abstract class Customweb_UnzerCw_Model_Method extends Mage_Payment_Model_Method_Abstract implements
		Customweb_Payment_Authorization_IPaymentMethod {
	protected $_code = 'unzercw';
	protected $_formBlockType = 'unzercw/form';
	protected $_infoBlockType = 'unzercw/info';

	/**
	 * Order statuses 
	 */
	const UNZERCW_STATUS_PENDING = 'pending_unzercw';
	const UNZERCW_STATUS_CANCELED = 'canceled_unzercw';
	const STATE_CANCELLED = 'canceled';
	const STATE_PENDING = 'pending_payment';
	const STATE_PROCESSING = 'processing';
	protected $authorizationAdapter = null;
	protected $orderContext = null;
	protected $helper = null;
	protected static $formFields = array();

	/**
	 * Standard payment method flags
	 */
	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = true;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	protected $_isInitializeNeeded = true;
	protected $paymentMethodName = "genericpaymentmethod";
	protected $authorizationMethodName = null;
	protected static $ignoreFeatures = false;

	public function __construct(){
		parent::__construct();
	}

	public function __sleep(){
		return array(
			'_code',
			'_formBlockType',
			'_isGateway',
			'_canAuthorize',
			'_canCapture',
			'_canCapturePartial',
			'_canRefund',
			'_canRefundInvoicePartial',
			'_canVoid',
			'_canUseInternal',
			'_canUseCheckout',
			'_canUseForMultishipping',
			'paymentMethodName',
			'authorizationMethodName'
		);
	}

	public function canCapture(){
		return $this->_canCapture && $this->isFeatureSupported("Capturing");
	}

	public function canCapturePartial(){
		return $this->_canCapturePartial && $this->isFeatureSupported("Capturing");
	}

	public function canRefund(){
		return $this->_canRefund && $this->isFeatureSupported("Refund");
	}

	public function canRefundPartialPerInvoice(){
		return $this->_canRefundInvoicePartial && $this->isFeatureSupported("Refund");
	}

	public function canUseInternal(){
		return $this->_canUseInternal && $this->isFeatureSupported("Moto");
	}

	protected function setOrderStatus(&$order, $status, $logEntry = null){
		try {
			if ($order->getStatus() == $status) {
				return;
			}

			$order->setStatus($status);
			$order->save();
			$order->addStatusToHistory($status, $logEntry);
			$order->save();

			Mage::dispatchEvent('unzercw_order_status', array(
				'order' => $order,
				'status' => $status
			));
		}
		catch (Exception $e) {
		}
		return $this;
	}

	/**
	 * Check whether payment method can be used
	 *
	 * @param Mage_Sales_Model_Quote|null $quote
	 * @return bool
	 */
	public function isAvailable($quote = null){
		$isAvailable = parent::isAvailable($quote);

		if ($isAvailable) {
			$allowedCurrencies = $this->getPaymentMethodConfigurationValue('Currency');
			if ($quote !== null && !empty($allowedCurrencies)) {
				$isAvailable = (in_array($quote->getQuoteCurrencyCode(), $allowedCurrencies));
			}
		}

		if ($isAvailable && $quote !== null && $quote->getUnzerCwExternalCheckout() !== true) {
			$paymentContext = $this->getHelper()->getPaymentCustomerContext();
			try {
				$orderContext = $this->getOrderContext(false, $quote);
				$this->getAuthorizationAdapter(false)->preValidate($orderContext, $paymentContext);
				$isAvailable = true;
			}
			catch (Exception $e) {
				$isAvailable = false;
			}
			$paymentContext->persist();
		}

		return $isAvailable;
	}

	/**
	 * This method is called during order creation to initialize the payment.
	 *
	 * @param string $paymentAction
	 * @param Varien_Object $stateObject
	 * @return Customweb_UnzerCw_Model_Method
	 */
	public function initialize($paymentAction, $stateObject){
		$stateObject->setStatus(self::UNZERCW_STATUS_PENDING);
		$stateObject->setState(self::STATE_PENDING);
		$stateObject->setIsNotified(false);
		return $this;
	}

	public function assignData($data){
		// Call parent assignData
		parent::assignData($data);

		// Save payment form data
		$info = $this->getInfoInstance();
		$info->unsAdditionalInformation();

		if (isset($_REQUEST[$this->getCode()])) {
			$formData = $_REQUEST[$this->getCode()];
			foreach ($formData as $key => $value) {
				$info->setAdditionalInformation($key, $value);
			}
		}

		return $this;
	}

	/**
	 * Validate after this payment method has been chosen, wheter this is valid.
	 *
	 * @return boolean
	 */
	
	public function validate(){
		if (false) {
			throw new Exception(
					$this->getHelper()->__(
							'We experienced a problem with your sellxed payment extension. For more information, please visit the configuration page of the plugin.'));
		}

		if ($this->getPaymentMethodConfigurationValue('authorizationMethod') == 'HiddenAuthorization' ||
				 $this->getPaymentMethodConfigurationValue('authorizationMethod') == 'ServerAuthorization') {
			return $this;
		}

		$paymentContext = $this->getHelper()->getPaymentCustomerContext();
		try {
			$orderContext = $this->getOrderContext(false);
			$parameters = array();
			if (isset($_REQUEST[$this->getCode()])) {
				$parameters = $_REQUEST[$this->getCode()];
			}
			$this->getAuthorizationAdapter(false)->validate($orderContext, $paymentContext, $parameters);
		}
		catch (Exception $e) {
			$paymentContext->persist();
			$this->getHelper()->log($this->getCode() . ' Method::validate() Validation failed because "' . $e->getMessage() . '"');
			Mage::throwException($e->getMessage());
		}
		$paymentContext->persist();

		return $this;
	}
	


	/**
	 * This overwrites the default behavior that retrieves the payment action from
	 * the magento configuration.
	 * We need this value to be set to none of the existing
	 * actions, which normally is the case. As other installed payment modules might overwrite
	 * these defaults, we ignore the configuration value here.
	 * 
	 *
	 * @return String
	 */
	public function getConfigPaymentAction(){
		return 'nothing';
	}

	/**
	 * Returns the module description
	 *
	 * @return string
	 */
	public function getDescription(){
		if ($this->moduleDescription == null) {
			$this->moduleDescription = $this->getPaymentMethodConfigurationValue('description');
		}

		return $this->moduleDescription;
	}

	/**
	 * Update transaction ids for further processing
	 * If no transactions were set before invoking, may generate an "offline" transaction id
	 *
	 * @param string $type
	 * @param Mage_Sales_Model_Order_Payment_Transaction $transactionBasedOn
	 */
	protected function _generateTransactionId($payment, $type, $transactionBasedOn = false){
		if (!$payment->getParentTransactionId() && $transactionBasedOn) {
			$payment->setParentTransactionId($transactionBasedOn->getTxnId());
		}
		// generate transaction id for an offline action or payment method that didn't set it
		if (($parentTxnId = $payment->getParentTransactionId())) {
			$transactionId = "{$parentTxnId}-{$type}";

			$collection = Mage::getModel('sales/order_payment_transaction')->getCollection()->setOrderFilter($payment->getOrder())->addPaymentIdFilter(
					$payment->getId())->addTxnTypeFilter($type);
			if ($collection->count() > 0) {
				$transactionId .= '-' . ($collection->count() + 1);
			}

			$payment->setTransactionId($transactionId);
		}
	}

	/**
	 * Send authorize request to gateway
	 *
	 * @param Varien_Object $payment
	 * @param float $amount
	 * @return Customweb_UnzerCw_Model_Method
	 */
	public function authorize(Varien_Object $payment, $amount){
		$this->getHelper()->log('Authorize was called');
		Mage::throwException('Authorization was called inapropriately.');
		return $this;
	}

	/**
	 * Process the authorization for server.
	 *
	 * @param Customweb_Payment_Authorization_ITransaction $transaction
	 * @param array $parameters
	 * @return string
	 */
	public function processServerAuthorization(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		if (!($adapter instanceof Customweb_Payment_Authorization_Server_IAdapter)) {
			Mage::throwException('The adapter is not of type server.');
		}
		return $adapter->processAuthorization($transaction->getTransactionObject(), $parameters);
	}

	/**
	 * Legacy support for Discounts
	 *
	 * This function changes the the SKU of discount items to the legacy format.
	 * This is necessary to capture / refund transaction which were started with the old format
	 *
	 * @param Customweb_Payment_Authorization_IInvoiceItem[] $lineItems The line items to change
	 * @return Customweb_Payment_Authorization_IInvoiceItem[]
	 */
	private function fixLegacyDiscountItemSKU(array $lineItems){
		foreach ($lineItems as $key => $item) {
			//Check if discount line items ends with new SKU
			if ($item->getType() == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT &&
					 substr($item->getSku(), -strlen('-discount')) === '-discount') {
				$lineItems[$key] = new Customweb_Payment_Authorization_DefaultInvoiceItem('discount', $item->getName(), $item->getTaxRate(),
						$item->getAmountIncludingTax(), $item->getQuantity(), $item->getType());
			}
		}
		$lineItems = Customweb_Util_Invoice::ensureUniqueSku($lineItems);
		return $lineItems;
	}

	/**
	 * Cancel payment abstract method
	 *
	 * @param Varien_Object $payment
	 *
	 * @return Customweb_UnzerCw_Model_Method
	 */
	public function cancel(Varien_Object $payment){
		
		try {
			if (Mage::registry('unzercw_update_transaction') != null) {
				$transaction = Mage::registry('unzercw_update_transaction');
			}
			else {
				$transaction = $this->getHelper()->loadTransactionByPayment($payment->getId());
			}
			$order = $payment->getOrder();
			Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);
			$adapter = $this->getHelper()->createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICancel');
			if ($transaction->getTransactionObject()->isCancelPossible()) {
				$adapter->cancel($transaction->getTransactionObject());
				$transaction->save();
			}

			if (!$transaction->getTransactionObject()->isCancelled() && $order->getStatus() != self::UNZERCW_STATUS_PENDING) {
				$this->getHelper()->log(
						"Method::cancel() Cancellation of payment is not possible" .
								 print_r($transaction->getTransactionObject()->getErrorMessages(), true));
				Mage::throwException($this->getHelper()->__('Canceling of this payment not possible!'));
			}

			if ($transaction->getTransactionObject()->isCancelled()) {
				$authTransaction = $payment->getAuthorizationTransaction();
				if ($authTransaction) {
					$parentTxnId = $authTransaction->getTxnId();
					if (method_exists($payment, 'lookupTransaction') && $payment->lookupTransaction($parentTxnId . '-void') === false) {
						$payment->setParentTransactionId($parentTxnId)->setIsTransactionClosed(true);
						$cancels = $transaction->getTransactionObject()->getCancels();
						$cancel = end($cancels);
						$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
								array(
									'TRANSACTIONID' => $transaction->getTransactionObject()->getPaymentId(),
									'CANCELID' => $cancel->getCancelId(),
									'STATUS' => $cancel->getStatus()
								));
						$this->_generateTransactionId($payment, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
						$paymentTransaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
						$paymentTransaction->save();
					}
				}
			}

			return $this;
		}
		catch (Exception $e) {
			$this->getHelper()->log('Method::cancel() ' . $e->getMessage());
			Mage::getSingleton('core/session')->addError($e->getMessage());
			throw $e;
		}
		
		
	}

	/**
	 * Refund money
	 *
	 * @param Varien_Object $payment
	 * @param float $amount
	 *
	 * @return Customweb_UnzerCw_Model_Method
	 */
	public function refund(Varien_Object $payment, $amount){
		
		try {
			if (Mage::registry('unzercw_update_transaction') != null) {
				$transaction = Mage::registry('unzercw_update_transaction');
			}
			else {
				$transaction = $this->getHelper()->loadTransactionByPayment($payment->getId());
			}
			$orderContext = $transaction->getTransactionObject()->getTransactionContext()->getOrderContext();

			$order = $payment->getOrder();
			Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);
			$adapter = $this->getHelper()->createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_IRefund');

			$refunded = false;
			if ($transaction->getTransactionObject()->isRefundPossible() && Mage::registry('cw_unzercw_refund_update') !== true) {
				$isPartialRefund = $amount !=
						 $transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getOrderAmountInDecimals();
				if ($isPartialRefund) {
					if ($transaction->getTransactionObject()->isPartialRefundPossible()) {
						$creditmemo = $payment->getCreditmemo();
						if ($orderContext->useBaseCurrency()) {
							$amount = $creditmemo->getBaseGrandTotal();
						}
						else {
							$amount = $creditmemo->getGrandTotal();
						}

						$items = array();
						if ($creditmemo instanceof Mage_Sales_Model_Order_Creditmemo) {
							$items = $this->getInvoiceItems($creditmemo, $amount, $orderContext->getCurrencyCode(), $orderContext->useBaseCurrency());
							if (!$orderContext->isNewSKUFormat()) {
								$items = $this->fixLegacyDiscountItemSKU($items);
							}
							if (empty($items)) {
								$items = $transaction->getTransactionObject()->getNonRefundedLineItems();
							}
						}
						else {
							$items = $transaction->getTransactionObject()->getNonRefundedLineItems();
						}

						$items = Customweb_Util_Invoice::getItemsByReductionAmount($items, $amount, $orderContext->getCurrencyCode());
						$adapter->partialRefund($transaction->getTransactionObject(), $items, false);
					}
					else {
						$this->getHelper()->log(
								"Method::refund() : No partial refund possible. Transaction log : " .
										 print_r($transaction->getTransactionObject()->getErrorMessages(), true));
						Mage::throwException($this->getHelper()->__('Partial refund not possible. You may retry with the total transaction amount.'));
					}
				}
				else {
					$adapter->refund($transaction->getTransactionObject());
				}

				$transaction->save();

				$refunded = true;
			}

			if ($refunded || Mage::registry('cw_unzercw_refund_update') === true) {
				$payment->setIsTransactionClosed(true);
				$refunds = $transaction->getTransactionObject()->getRefunds();
				$refund = end($refunds);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						array(
							'TRANSACTIONID' => $transaction->getTransactionObject()->getPaymentId(),
							'REFUNDID' => $refund->getRefundId(),
							'AMOUNT' => $refund->getAmount(),
							'AMT' => -$refund->getAmount(),
							'STATUS' => $refund->getStatus()
						));
				$this->_generateTransactionId($payment, Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);

				$payment->getCreditmemo()->setUnzercwRefundId($refund->getRefundId());

				return $this;
			}
			else {
				//$this->getHelper()->log("Method::refund() : No refund possible. Transaction log : " . print_r($transaction->getTransactionObject()->getErrorMessages(), true));
				Mage::throwException($this->getHelper()->__('No refund possible.'));
			}
		}
		catch (Exception $e) {
			$transaction->save();
			$this->getHelper()->log("Exception in Method::refund() : " . $e->getMessage());
			Mage::getSingleton('core/session')->addError($e->getMessage());
			throw $e;
		}
		
		
	}

	/**
	 * The transaction is captured with the specified amount.
	 *
	 * @param Varien_Object $payment The payment object containing all the payment specific data for the order.
	 * @param float $amount The amount to capture. This may be less than the amount of the order but not more.
	 * @return Customweb_UnzerCw_Model_Method
	 */
	public function capture(Varien_Object $payment, $amount){
		
		$invoice = Mage::registry('current_invoice');
		try {
			if (Mage::registry('unzercw_update_transaction') != null) {
				$transaction = Mage::registry('unzercw_update_transaction');
			}
			else {
				$transaction = $this->getHelper()->loadTransactionByPayment($payment->getId());
			}
			$orderContext = $transaction->getTransactionObject()->getTransactionContext()->getOrderContext();

			$order = $payment->getOrder();
			Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);

			$adapter = $this->getHelper()->createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICapture');
			$isNoClose = false;

			if ($transaction->getTransactionObject()->isCapturePossible()) {
				if ($transaction->getTransactionObject()->isPartialCapturePossible()) {
					if ($orderContext->useBaseCurrency()) {
						$amount = $invoice->getBaseGrandTotal();
					}
					else {
						$amount = $invoice->getGrandTotal();
					}

					$isNoClose = $this->isCaptureNoClose();
					$items = array();
					if ($invoice instanceof Mage_Sales_Model_Order_Invoice) {
						$items = $this->getInvoiceItems($invoice, $amount, $orderContext->getCurrencyCode(), $orderContext->useBaseCurrency());
						if (!$orderContext->isNewSKUFormat()) {
							$items = $this->fixLegacyDiscountItemSKU($items);
						}
						if (empty($items)) {
							$items = $transaction->getTransactionObject()->getUncapturedLineItems();
						}
					}
					else {
						$items = $transaction->getTransactionObject()->getUncapturedLineItems();
					}
					$items = Customweb_Util_Invoice::getItemsByReductionAmount($items, $amount, $orderContext->getCurrencyCode());
					$adapter->partialCapture($transaction->getTransactionObject(), $items, !$isNoClose);
				}
				else {
					$isNoClose = false;
					$adapter->capture($transaction->getTransactionObject());
				}
				$transaction->save();
			}

			if ($transaction->getTransactionObject()->isCaptured()) {
				$payment->setShouldCloseParentTransaction(!$isNoClose);
				$payment->setIsTransactionClosed(false);
				$captures = $transaction->getTransactionObject()->getCaptures();
				$capture = end($captures);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						array(
							'TRANSACTIONID' => $transaction->getTransactionObject()->getPaymentId(),
							'CAPTUREID' => $capture->getCaptureId(),
							'AMOUNT' => $capture->getAmount(),
							'AMT' => $capture->getAmount(),
							'STATUS' => $capture->getStatus()
						));
				$this->_generateTransactionId($payment, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
				return $this;
			}
			else {
				$this->getHelper()->log(
						"Capture failed. Transaction error messages : " . print_r($transaction->getTransactionObject()->getErrorMessages(), true));
				$messages = $transaction->getTransactionObject()->getErrorMessages();
				Mage::throwException($this->getHelper()->__('The invoice could not be captured and processed. Reason: ') . end($messages));
			}

		}
		catch (Exception $e) {
			$this->getHelper()->log("Exception in Method::capture() : " . $e->getMessage());
			Mage::getSingleton('core/session')->addError($e->getMessage());
			throw $e;
		}
		
		
	}

	/**
	 *
	 * @return boolean
	 */
	private function isCaptureNoClose(){
		if (Mage::app()->getRequest()->getParam('capture_no_close')) {
			return true;
		}
		$invoice = Mage::app()->getRequest()->getParam('invoice');
		if (is_array($invoice) && isset($invoice['capture_no_close']) && $invoice['capture_no_close']) {
			return true;
		}
		return false;
	}

	public function processInvoice($invoice, $payment){
		parent::processInvoice($invoice, $payment);

		// Make sure the order is in the desired state
		if (Mage::registry('unzercw_update_transaction') != null) {
			$transaction = Mage::registry('unzercw_update_transaction');
		}
		else {
			$transaction = $this->getHelper()->loadTransactionByPayment($payment->getId());
		}
		$order = $payment->getOrder();
		$this->setOrderStatus($order, $transaction->getTransactionObject()->getOrderStatus());
	}

	protected function getInvoiceItems($invoice, $amount, $currency, $useBaseCurrency = false){
		/* @var $invoice Mage_Sales_Model_Order_Invoice */
		$resultItems = array();
		$items = $invoice->getAllItems();
		$invoiceItems = Mage::getModel('unzercw/invoiceItems');

		foreach ($items as $item) {
			$orderItem = $item->getOrderItem();
			if ($orderItem->getParentItemId() != null && $orderItem->getParentItem()->getProductType() ==
					 Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
				continue;
			}
			if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $orderItem->getParentItemId() == null) {
				$product = Mage::getModel('catalog/product')->load($item->getProductId());
				if($product->getPriceType() != Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED){
					continue;
				}
			}

			$productItem = $invoiceItems->getProductItem($item, $useBaseCurrency);
			if ($productItem) {
				$resultItems[] = $productItem;
			}

			$discountItem = $invoiceItems->getDiscountItem($item, $useBaseCurrency);
			if ($discountItem) {
				$resultItems[] = $discountItem;
			}
		}

		$surchargeItem = $invoiceItems->getFoomanSurchargeItem($invoice->getOrder(), $useBaseCurrency);
		if ($surchargeItem) {
			$resultItems[] = $surchargeItem;
		}

		$shippingItem = $invoiceItems->getShippingItem($invoice, $useBaseCurrency);
		if ($shippingItem) {
			$resultItems[] = $shippingItem;
		}

		$giftCardItem = $invoiceItems->getMX2GiftCards($invoice->getOrder(), $useBaseCurrency);
		if($giftCardItem){
			$resultItems[] = $giftCardItem;
		}

		$resultItems = Customweb_Util_Invoice::ensureUniqueSku($resultItems);

		$event = new StdClass();
		$event->items = array();
		Mage::dispatchEvent('customweb_collect_invoice_items', array(
			'invoice' => $invoice,
			'result' => $event
		));

		foreach ($event->items as $item) {
			$resultItems[] = new Customweb_Payment_Authorization_DefaultInvoiceItem($item['sku'], $item['name'], $item['taxRate'],
					$item['amountIncludingTax'], $item['quantity'], $item['type']);
		}

		if (empty($resultItems) || Customweb_Util_Currency::compareAmount(Customweb_Util_Invoice::getTotalAmountIncludingTax($resultItems), $amount, $currency) < 0) {
			return array();
		} else {
			return Customweb_Util_Invoice::getItemsByReductionAmount($resultItems, $amount, $currency);
		}
	}

	/**
	 * This method does post processing of successfull payments.
	 * Order and quote states are
	 * set and and invoice is generated (and captured) according to the settings in the backend.
	 * 
	 * Attention: This method is called from within redirectAction (access to checkout session)
	 * and from within processAction (no access to checkout session)
	 *
	 * @param Mage_Sales_Model_Order $order
	 * @param string $transactionId
	 */
	public function processPayment(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction){
		if ($order->getId()) {
			Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);
			Mage::getSingleton('core/session')->unsetUnzerCwTransactionId();
			if ($transaction->getTransactionObject()->isAuthorized()) {
				$transaction->saveIgnoreOrderStatus();
				$order->setState(self::STATE_PROCESSING);
				$order->save();

				Mage::dispatchEvent('unzercw_payment_success', array(
					'order' => $order
				));

				$quote = Mage::getModel('sales/quote');
				$quote->load($order->getQuoteId());

				$quote->setIsActive(1);

				if ($transaction->getTransactionObject()->isAuthorizationUncertain()) {
					$order->addStatusHistoryComment(
							$this->getHelper()->__('Authorization is uncertain.') . "\n<br>PaymentId:" .
									 $transaction->getTransactionObject()->getPaymentId());
				}
				else {
					$order->addStatusHistoryComment(
							$this->getHelper()->__('Customer successfully returned from Unzer') . "\n<br>PaymentId:" .
									 $transaction->getTransactionObject()->getPaymentId());
				}
				$order->save();
				$this->createAuthPaymentTransaction($order, $transaction);
				if ($this->createInvoice($order, $transaction)) {
					$this->getHelper()->log("Method::processPayment(): Invoice was created successfully.");
					$order->addStatusHistoryComment($this->getHelper()->__('Invoice was created successfully'));
					$order->save();

					Mage::dispatchEvent('unzercw_invoice_created', array(
						'order' => $order
					));
				}

				$quote->setIsActive(0);
				$quote->save();

				if ($transaction->getTransactionObject()->getTransactionContext()->isSendOrderEmail()) {
					$order->sendNewOrderEmail();
				}

				$this->getHelper()->log("Method::processPayment(): Payment processed successfully.");
			}
			else {
				$this->getHelper()->log("Method::processPayment(): Transaction is not authorized.");
			}

			$order->save();
		}
		else {
			$this->getHelper()->log("Method::processPayment(): Order is not available.");
		}
	}

	/**
	 * This method creates the invoice when the invoice was not created during the authorization.
	 *
	 * This method is called when the transaction switches from uncertain to certain. The method does also
	 * trigger the capture if the payment method is configured to do so.
	 *
	 * @param Customweb_UnzerCw_Model_Transaction $transaction
	 */
	public function createInvoiceAfterUncertain(Customweb_UnzerCw_Model_Transaction $transaction){
		$order = $transaction->getOrder();
		if ($this->createInvoice($order, $transaction)) {
			$this->getHelper()->log("Method::createInvoiceAfterUncertain(): Invoice was created successfully.");
			$order->addStatusHistoryComment($this->getHelper()->__('Invoice was created successfully'));
			$order->save();

			Mage::dispatchEvent('unzercw_invoice_created', array(
				'order' => $order
			));
		}
	}

	protected function createAuthPaymentTransaction(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction){
		$payment = $order->getPayment();

		$paymentId = $transaction->getTransactionObject()->getPaymentId();
		if ($paymentId === null) {
			throw new Exception("No paymentId provided.");
		}

		$payment->setTransactionId($paymentId)->setParentTransactionId(null)->setIsTransactionClosed(false);
		$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
				array(
					'TRANSACTIONID' => $transaction->getTransactionObject()->getPaymentId(),
					'AMOUNT' => $transaction->getTransactionObject()->getAuthorizationAmount(),
					'AMT' => $transaction->getTransactionObject()->getAuthorizationAmount(),
					'CURRENCY' => $transaction->getTransactionObject()->getCurrencyCode(),
					'AUTHORIZATIONMETHOD' => $transaction->getTransactionObject()->getAuthorizationMethod()
				));
		$paymentTransaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
		$paymentTransaction->save();

		$payment->setTransactionId(null);
	}

	/**
	 * Depending on the settings we create an invoice and capture it.
	 *
	 * @param Mage_Sales_Model_Order $order
	 * @param boolean Returns true if an invoice has been created.
	 */
	public function createInvoice(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction){
		$result = false;
		self::$ignoreFeatures = true;
		try {
			$isSettlementAfterOrder = $this->getPaymentMethodConfigurationValue('settlement');
			$isCaptureAfterOrder = $transaction->getTransactionObject()->isCaptured();

			$this->getHelper()->log("Payment config: settlement='" . $isSettlementAfterOrder . "' capturing='" . $isCaptureAfterOrder . "'");

			if ($isSettlementAfterOrder == 'settlement_direct') {
				// Do not create multiple invoices
				if (!$order->hasInvoices()) {
					$invoice = $order->prepareInvoice();
					if ($isCaptureAfterOrder) {
						$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
					}

					$invoice->register();
					$invoice->setTransactionId($transaction->getTransactionObject()->getPaymentId());

					$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
					$transactionSave->save();

					if ($this->getConfigData('invoice_send_email')) {
						$invoice->sendEmail();
					}

					$result = true;
				}
			}
		}
		catch (Exception $e) {
			$this->getHelper()->log("Create Invoice: " . $e->getMessage());
		}
		self::$ignoreFeatures = false;
		return $result;
	}

	/**
	 * Return Order Place Redirect URL
	 * 
	 *
	 * @return string Order Redirect URL
	 */
	public function getOrderPlaceRedirectUrl(){
		$adapter = $this->getAuthorizationAdapter(true);

		$quote = Mage::getModel('checkout/cart')->getQuote();
		$quote->setIsActive(true);
		$quote->save();

		$transactionId = Mage::registry('cstrxid');
		$secretHash = $this->getHelper()->hash($transactionId);

		if ($adapter instanceof Customweb_Payment_Authorization_PaymentPage_IAdapter) {
			return Mage::getUrl('UnzerCw/process/ppRedirect',
					array(
						'transaction_id' => $transactionId,
						'secret' => $secretHash,
						'_secure' => true
					));
		}
		elseif ($adapter instanceof Customweb_Payment_Authorization_Iframe_IAdapter ||
				 $adapter instanceof Customweb_Payment_Authorization_Widget_IAdapter) {
			return Mage::getUrl('UnzerCw/checkout/pay',
					array(
						'transaction_id' => $transactionId,
						'secret' => $secretHash,
						'_secure' => true
					));
		}
		else {
			return Mage::getUrl('UnzerCw/process/dummy', array(
				'_secure' => true
			));
		}
	}

	public function generateHiddenFormParameters(Customweb_UnzerCw_Model_Transaction $transaction){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		$hiddenFields = $adapter->getHiddenFormFields($transaction->getTransactionObject());
		$actionUrl = $adapter->getFormActionUrl($transaction->getTransactionObject());
		$transaction->save();

		$jsonObject = array();
		$jsonObject['cstrxid'] = $transaction->getTransactionObject()->getTransactionContext()->getTransactionId();
		$jsonObject['actionUrl'] = $actionUrl;
		$jsonObject['fields'] = $hiddenFields;
		return json_encode($jsonObject);
	}

	private function loadFailedTransaction(){
		$failedTransactionId = Mage::getSingleton('checkout/session')->getData('failed_transaction_' . $this->getCode());
		Mage::getSingleton('checkout/session')->setData('failed_transaction_' . $this->getCode(), "");
		if ($failedTransactionId > 0) {
			$this->getHelper()->log("Load failed transaction" . $failedTransactionId);
			$failedTransaction = $this->getHelper()->loadTransaction($failedTransactionId);
			$currentCustomerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
			if ($failedTransaction->getCustomerId() == $currentCustomerId) {
				return $failedTransaction->getTransactionObject();
			}
		}
		return null;
	}

	protected function getFormFields(array $parameters){
		if (!isset(self::$formFields[$this->getCode()])) {
			$adapter = $this->getAuthorizationAdapter(false);
			$customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
			$failedTransaction = $this->loadFailedTransaction();
			$paymentCustomerContext = $this->getHelper()->getPaymentCustomerContext($customerId);

			$formFields = array();
			if (!empty($parameters['alias_id']) && $parameters['alias_id'] != 'new') {
				$aliasTransactionId = $parameters['alias_id'];
				$aliasTransaction = $this->getHelper()->loadTransaction($aliasTransactionId);
				if ($aliasTransaction->getCustomerId() == $customerId) {
					Mage::getSingleton('checkout/session')->setAliasId($aliasTransactionId);

					$formFields = $adapter->getVisibleFormFields($this->getOrderContext(false), $aliasTransaction->getTransactionObject(),
							$failedTransaction, $paymentCustomerContext);
				}
			}
			else {
				Mage::getSingleton('checkout/session')->setAliasId('new');
				if ($this->getPaymentMethodConfigurationValue('alias_manager') == 'active') {
					$formFields = $adapter->getVisibleFormFields($this->getOrderContext(false), 'new', $failedTransaction, $paymentCustomerContext);
				}
				else {
					$formFields = $adapter->getVisibleFormFields($this->getOrderContext(false), null, $failedTransaction, $paymentCustomerContext);
				}
			}
			$paymentCustomerContext->persist();
			self::$formFields[$this->getCode()] = $formFields;
		}

		return self::$formFields[$this->getCode()];
	}

	public function generateVisibleFormFields(array $parameters){
		return $this->getFormRenderer()->renderElementsWithoutJavaScript($this->getFormFields($parameters));
	}

	public function generateFormJavaScript(array $parameters){
		return $this->getFormRenderer()->renderElementsJavaScript($this->getFormFields($parameters));
	}

	public function getFormRenderer(){
		$renderer = new Customweb_UnzerCw_Model_FormRenderer();
		$renderer->setNameSpacePrefix($this->getCode());
		return $renderer;
	}

	public function generateJavascriptForAjax(Customweb_UnzerCw_Model_Transaction $transaction){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);

		if ($adapter instanceof Customweb_Payment_Authorization_Ajax_IAdapter) {
			$fileUrl = $adapter->getAjaxFileUrl($transaction->getTransactionObject());
			$function = $adapter->getJavaScriptCallbackFunction($transaction->getTransactionObject());

			$transaction->save();

			if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
				$data = array(
					'error' => 'yes',
					'error_message' => $this->getErrorMessageToDisplay($transaction)
				);
				return $this->generateJson($data);
			}
			$data = array(
				'error' => 'no',
				'javascriptUrl' => $fileUrl,
				'callbackFunction' => $function
			);
			return $this->generateJson($data);
		}
		else {
			$data = array(
				'error' => 'yes',
				'error_message' => $this->getHelper()->__("Ajax authorization not supported.")
			);
			return $this->generateJson($data);
		}
	}

	public function isPaymentFailed(Mage_Sales_Model_Order $order){
		$transaction = $this->getHelper()->loadTransactionByOrder($order->getId());
		return $transaction->getTransactionObject()->isAuthorizationFailed();
	}

	public function isPaymentAuthorized(Mage_Sales_Model_Order $order){
		$transaction = $this->getHelper()->loadTransactionByOrder($order->getId());
		return $transaction->getTransactionObject()->isAuthorized();
	}

	public function fail(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$order = $transaction->getOrder();

		Mage::dispatchEvent('unzercw_payment_fail', array(
			'order' => $order
		));

		$session = Mage::getSingleton('checkout/session');
		if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT &&
				 $session->getData('failed_transaction_' . $this->getCode()) != $transaction->getTransactionId()) {
			$session->setData('failed_transaction_' . $this->getCode(), $transaction->getTransactionId());
			$this->getHelper()->log(
					"Set failed transaction " . 'failed_transaction_' . $this->getCode() . " to id " . $transaction->getTransactionId());
			$message = $this->getErrorMessageToDisplay($transaction);

			Mage::getSingleton('core/session')->addError($message);

			$order->registerCancellation($message);
			//$order->setIsActive(0);
			$this->setOrderStatus($order, Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_CANCELED,
					$this->getHelper()->__('Payment could not be authorized. Cancelling order. Reason : ') . $message);

			$this->restoreUsedCoupons($order);

			$quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

			if ($quote->getId()) {
				$quote->setIsActive(1)->setReservedOrderId(NULL)->save();
				$session->replaceQuote($quote);
			}

			$session->unsLastRealOrderId();

			Mage::getSingleton('checkout/session')->replaceQuote($quote);
		}
	}


	public function restoreUsedCoupons(Mage_Sales_Model_Order $order){
		$ruleIds = explode(',', $order->getAppliedRuleIds());
		$ruleIds = array_unique($ruleIds);
		$ruleCustomer = null;
		$customerId = $order->getCustomerId();
		foreach ($ruleIds as $ruleId) {
			if (!$ruleId) {
				continue;
			}
			$rule = Mage::getModel('salesrule/rule');
			$rule->load($ruleId);
			if ($rule->getId()) {
				if($rule->getTimesUsed() > 0){
					$rule->setTimesUsed($rule->getTimesUsed() -1);
					$rule->save();
				}

				if ($customerId) {
					$ruleCustomer = Mage::getModel('salesrule/rule_customer');
					$ruleCustomer->loadByCustomerRule($customerId, $ruleId);

					if ($ruleCustomer->getId()) {
						if($ruleCustomer->getTimesUsed() > 0){
							$ruleCustomer->setTimesUsed($ruleCustomer->getTimesUsed() -1);
							$ruleCustomer->save();
						}
					}
				}
			}
		}
		$coupon = Mage::getModel('salesrule/coupon');
		$coupon->load($order->getCouponCode(), 'code');
		if ($coupon->getId()) {
			if($coupon->getTimesUsed() > 0) {
				$coupon->setTimesUsed($coupon->getTimesUsed() -1);
				$coupon->save();
			}
			if ($customerId) {
				$couponUsage = new Varien_Object();
				$couponId = $coupon->getId();
				Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon($couponUsage, $customerId, $couponId);
				$resource = Mage::getSingleton('core/resource');

				$writeConnection = $resource->getConnection('core_write');
				$tableName = $resource->getTableName('salesrule_coupon_usage');

				$query = "UPDATE {$tableName} SET times_used = times_used-1 WHERE coupon_id = {$couponId} AND customer_id = {$customerId} AND times_used > 0";
				$writeConnection->query($query);

				$queryDelete = "DELETE FROM {$tableName} WHERE coupon_id = {$couponId} AND customer_id = {$customerId} AND times_used = 0";
				$writeConnection->query($queryDelete);

			}
		}
	}

	public function success(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$quote = Mage::getModel('sales/quote');
		$quote->load($transaction->getOrder()->getQuoteId());

		$quote->setIsActive(0);
		$quote->save();
	}

	public function pullUpdate(Customweb_UnzerCw_Model_Transaction $transaction){
		
	}

	public function redirectToPaymentPage(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$info = $this->getInfoInstance();
		$additionalFormData = $info->getAdditionalInformation();
		$parameters = array_merge($additionalFormData, $parameters);

		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		$isHeaderRedirect = $adapter->isHeaderRedirectionSupported($transaction->getTransactionObject(), $parameters);
		if ($isHeaderRedirect) {
			$url = $adapter->getRedirectionUrl($transaction->getTransactionObject(), $parameters);
			$transaction->save();
			header('Location: ' . $url);
			exit();
		}
		else {
			$html = $this->getFormHtml($adapter, $transaction, true, '', $parameters);
			echo $html;
			exit();
		}
	}

	public function getFormHtml($adapter, Customweb_UnzerCw_Model_Transaction $transaction, $javaScript = true, $additionalFields = "", $formData = null){
		if ($formData === null) {
			$formData = $_REQUEST;
		}

		$formActionUrl = $adapter->getFormActionUrl($transaction->getTransactionObject(), $formData);
		$hiddenFields = $adapter->getParameters($transaction->getTransactionObject(), $formData);
		$transaction->save();
		$html = '
			<form action="' . $formActionUrl . '" method="POST" name="process_form">';

		$html .= Customweb_Util_Html::buildHiddenInputFields($hiddenFields);

		$html .= $additionalFields;

		$html .= '<button id="redirectButton" title="' . $this->getHelper()->__("Continue") . '" type="submit" class="scalable go" style="">
				<span>
					<span>
						<span>' . $this->getHelper()->__("Continue") . '</span>
					</span>
				</span>
			</button>
		</form>';
		if ($javaScript) {
			$html .= '<script type="text/javascript">
			window.onload = function(){
				document.getElementById("redirectButton").disabled = true;
				document.process_form.submit();
			};
			</script>';
		}

		return $html;
	}

	public function startMotoAuthorization(Mage_Sales_Model_Order $order, array $parameters){
		Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);
		$adapter = $this->getHelper()->getAuthorizationAdapter(Customweb_Payment_Authorization_Moto_IAdapter::AUTHORIZATION_METHOD_NAME);
		$orderContext = Customweb_UnzerCw_Model_OrderContext::fromOrder($order);

		if ($adapter->isAuthorizationMethodSupported($orderContext)) {
			$paymentCustomerContext = $this->getHelper()->getPaymentCustomerContext($order->getCustomerId());
			try {
				$adapter->validate($orderContext, $paymentCustomerContext, $parameters);
				$transaction = $this->createTransaction($order, true);
				$html = $this->generateMotoForm($order, $transaction, $parameters);
				return $html;
			}
			catch (Exception $e) {
				$this->getHelper()->log($this->getCode() . ' Method::startMotoAuthorization() Failed because "' . $e->getMessage() . '"');
			}
			$paymentCustomerContext->persist();
		}
	}

	public function generateMotoForm(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction, $parameters){
		$adapter = $this->getHelper()->getAuthorizationAdapter(Customweb_Payment_Authorization_Moto_IAdapter::AUTHORIZATION_METHOD_NAME);
		$failedTransaction = $this->loadFailedTransaction();
		$paymentCustomerContext = $this->getHelper()->getPaymentCustomerContext($order->getCustomerId());

		$formFields = $adapter->getVisibleFormFields($transaction->getTransactionObject()->getTransactionContext()->getOrderContext(), null,
				$failedTransaction, $paymentCustomerContext);
		$formActionUrl = $adapter->getFormActionUrl($transaction->getTransactionObject(), $parameters);
		$hiddenFields = $adapter->getParameters($transaction->getTransactionObject(), $parameters);

		$cancelUrl = Mage::getUrl('UnzerCw/process/motoCancel', array(
			'_secure' => true,
			'_store' => $order->getStoreId()
		));
		$secretHash = $this->getHelper()->hash($transaction->getTransactionId());
		$hiddenFields['cancelUrl'] = $cancelUrl . '?cstrxid=' . $transaction->getTransactionId() . '&secret=' . $secretHash;

		$paymentCustomerContext->persist();
		$transaction->save();

		$form = new Customweb_Payment_BackendOperation_Form();
		$form->setMachineName('moto_form');
		$form->setTargetUrl($formActionUrl);
		$form->setRequestMethod('POST');
		$form->setTitle($this->getHelper()->__('Mail order/telephone order authorization'));
		foreach ($formFields as $formField) {
			$form->addElement($formField);
		}
		$form->addElement(
				new Customweb_Form_HiddenElement(
						new Customweb_Form_Control_HiddenHtml('hidddenFields', Customweb_Util_Html::buildHiddenInputFields($hiddenFields))));

		$cancelButton = new Customweb_Form_Button();
		$cancelButton->setMachineName('cancel');
		$cancelButton->setTitle($this->getHelper()->__('Cancel'));
		$cancelButton->setType(Customweb_Form_IButton::TYPE_CANCEL);
		$cancelButton->setJSValidationExecuted(false);
		$form->addButton($cancelButton);

		$button = new Customweb_Form_Button();
		$button->setMachineName('submit');
		$button->setTitle($this->getHelper()->__('Continue'));
		$form->addButton($button);

		$renderer = new Customweb_UnzerCw_Model_BackendFormRenderer();
		$renderer->setShowScope(false);
		$renderer->setShowAllElements(true);
		$result = $renderer->renderForm($form);
		return $result;
	}

	public function getIFrameUrl(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		$url = "";

		if ($adapter instanceof Customweb_Payment_Authorization_Iframe_IAdapter) {
			$url = $adapter->getIframeUrl($transaction->getTransactionObject(), $parameters);
		}

		$transaction->save();
		return $url;
	}

	public function getIFrameHeight(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		$height = 1000;

		if ($adapter instanceof Customweb_Payment_Authorization_Iframe_IAdapter) {
			$height = $adapter->getIframeHeight($transaction->getTransactionObject(), $parameters);
		}

		$transaction->save();
		return $height;
	}

	public function getWidgetHtml(Customweb_UnzerCw_Model_Transaction $transaction, array $parameters){
		$adapter = $this->getAuthorizationAdapter(true, $transaction);
		$url = "";

		if ($adapter instanceof Customweb_Payment_Authorization_Widget_IAdapter) {
			$url = $adapter->getWidgetHTML($transaction->getTransactionObject(), $parameters);
		}

		$transaction->save();
		return $url;
	}

	public function getPaymentMethodName(){
		return $this->paymentMethodName;
	}

	public function getPaymentMethodDisplayName(){
		return $this->getPaymentMethodConfigurationValue('title');
	}

	public function getPaymentMethodConfigurationValue($key, $languageCode = null){
		$result = null;
		$rs = Mage::getStoreConfig('payment/' . $this->getCode() . '/' . $key, Customweb_UnzerCw_Model_ConfigurationAdapter::getStoreId());

		$multiSelectKeys = $this->getMultiSelectKeys();
		$fileKeys = $this->getFileKeys();

		if (isset($multiSelectKeys[$key]) || in_array($key, array(
			'Currency',
			'specificcountry'
		))) {
			$result = empty($rs) ? array() : explode(',', $rs);
		}
		elseif (isset($fileKeys[$key])) {
			$defaultValue = $fileKeys[$key];
			if (empty($rs) || $defaultValue == $rs) {
				return Mage::helper('UnzerCw')->getAssetResolver()->resolveAssetStream($defaultValue);
			}
			else {
				return new Customweb_Core_Stream_Input_File(
						Mage::getBaseDir('media') . '/unzercw/setting/' . $this->getPaymentMethodName() . '/' . $key . '/' . $rs);
			}
		}
		elseif (in_array($this->getCode() . '/' . $key, array(
			
		))) {
			$result = Mage::helper('core')->decrypt($rs);
		}
		else {
			$result = $rs;
		}

		return $result;
	}

	public function existsPaymentMethodConfigurationValue($key, $languageCode = null){
		// Make sure the order status is not set on cancel. This does not really work in magento.
		if ($key == 'status_cancelled') {
			return false;
		}
		return null != Mage::getStoreConfig('payment/' . $this->getCode() . '/' . $key,
				Customweb_UnzerCw_Model_ConfigurationAdapter::getStoreId());
	}

	abstract protected function getMultiSelectKeys();

	abstract protected function getFileKeys();

	abstract protected function getNotSupportedFeatures();

	public function isFeatureSupported($feature){
		return array_search($feature, $this->getNotSupportedFeatures()) === false || self::$ignoreFeatures;
	}

	/**
	 * 
	 *
	 * @return NULL
	 */
	public function getAuthorizationAdapter($isOrderAvailable, Customweb_UnzerCw_Model_Transaction $transaction = null){
		if (Mage::registry('customweb_subscription_create_recurring_order') === true) {
			return $this->getHelper()->getAuthorizationAdapter(Customweb_Payment_Authorization_Recurring_IAdapter::AUTHORIZATION_METHOD_NAME);
		}

		if ($this->authorizationAdapter == null) {
			if ($this->authorizationMethodName != null) {
				$this->authorizationAdapter = $this->getHelper()->getAuthorizationAdapter($this->authorizationMethodName);
			}
			else {
				if ($isOrderAvailable == true) {
					$order = Mage::getModel('sales/order');
					$order->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
					Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($order);
				}

				if ($transaction && $transaction !== null && $transaction->getTransactionObject() !== null &&
						 $transaction->getTransactionObject()->getTransactionContext()->isMotoTransaction()) {
					$this->authorizationAdapter = $this->getHelper()->getAuthorizationAdapter(
							Customweb_Payment_Authorization_Moto_IAdapter::AUTHORIZATION_METHOD_NAME);
				}
				else {
					$this->authorizationAdapter = $this->getHelper()->getAuthorizationAdapterByContext($this->getOrderContext($isOrderAvailable));
				}

				$this->authorizationMethodName = $this->authorizationAdapter->getAuthorizationMethodName();
			}
		}
		return $this->authorizationAdapter;
	}

	public function getOrderContext($isOrderAvailable, $quote = null){
		if ($this->orderContext == null) {
			$orderContext = new Customweb_UnzerCw_Model_OrderContext($this, $isOrderAvailable, null, $quote);

			$result = new StdClass();
			$result->orderContext = $orderContext;
			Mage::dispatchEvent('customweb_payment_create_order_context', array(
				'result' => $result,
				'payment_method' => $this
			));

			$this->orderContext = $result->orderContext;
		}

		return $this->orderContext;
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order $order
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	public function createTransaction(Mage_Sales_Model_Order $order, $moto = false){
		$transaction = Mage::getModel('unzercw/transaction');
		$transaction->save();

		Mage::getSingleton('core/session')->setUnzerCwTransactionId($transaction->getId());

		return $this->updateTransaction($transaction, $order, $moto);
	}

	/**
	 *
	 * @param Customweb_UnzerCw_Model_Transaction $transaction
	 * @param Mage_Sales_Model_Order $order
	 * @param boolean $moto
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	public function updateTransaction(Customweb_UnzerCw_Model_Transaction $transaction, Mage_Sales_Model_Order $order, $moto = false){
		if (!$transaction->getOrderId() && $order->getId()) {
			$transaction->setOrderId($order->getId());
			$transaction->setOrderPaymentId($order->getPayment()->getId());
			$transaction->setAliasActive($this->getPaymentMethodConfigurationValue('alias_manager') == 'active');
			$transaction->save();

			$result = new StdClass();
			$result->authorizationMethod = null;
			Mage::dispatchEvent('customweb_payment_update_transcation',
					array(
						'result' => $result,
						'transaction' => $transaction,
						'order' => $order
					));

			if ($result->authorizationMethod != null) {
				$transactionContext = $this->getTransactionContext($order, $transaction);
				$adapter = $this->getHelper()->getAuthorizationAdapter($result->authorizationMethod);
			}
			elseif ($moto) {
				$transactionContext = $this->getMotoTransactionContext($order, $transaction);
				$adapter = $this->getHelper()->getAuthorizationAdapter(Customweb_Payment_Authorization_Moto_IAdapter::AUTHORIZATION_METHOD_NAME);
			}
			else {
				$transactionContext = $this->getTransactionContext($order, $transaction);
				$adapter = $this->getAuthorizationAdapter(true);
			}

			$session = Mage::getSingleton('core/session');
			$session->unsUnzerCwCheckoutId();
			// TODO implement failed transaction recovery (second param)
			$transactionObject = $adapter->createTransaction($transactionContext, null);

			$transaction->setTransactionObject($transactionObject);
			$transaction->save();
		}
		return $transaction;
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order $order
	 * @param Customweb_UnzerCw_Model_Transaction $transaction
	 * @param string $backendSuccessUrl
	 * @param string $backendFailUrl
	 * @param string $backendCancelUrl
	 * @return Customweb_UnzerCw_Model_TransactionContext
	 */
	public function getTransactionContext(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction, $backendSuccessUrl = '', $backendFailUrl = '', $backendCancelUrl = ''){
		$aliasTransactionId = Mage::getSingleton('checkout/session')->getAliasId();
		$storeId = $order->getStore()->getId();
		$orderContext = Customweb_UnzerCw_Model_OrderContext::fromOrder($order);
		$transactionContext = new Customweb_UnzerCw_Model_TransactionContext($orderContext, $order->getIncrementId(),
				$transaction->getTransactionId(), $this->getHelper()->getPaymentCustomerContext(), $aliasTransactionId, $backendSuccessUrl,
				$backendFailUrl, $backendCancelUrl, $storeId);

		$result = new StdClass();
		$result->transactionContext = $transactionContext;
		Mage::dispatchEvent('customweb_payment_create_transcation_context', array(
			'result' => $result,
			'order' => $order
		));

		return $result->transactionContext;
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order $order
	 * @param Customweb_UnzerCw_Model_Transaction $transaction
	 * @return Customweb_UnzerCw_Model_TransactionContext
	 */
	public function getMotoTransactionContext(Mage_Sales_Model_Order $order, Customweb_UnzerCw_Model_Transaction $transaction){
		$storeId = $order->getStore()->getId();
		$orderContext = Customweb_UnzerCw_Model_OrderContext::fromOrder($order);

		$backendSuccessUrl = Mage::getModel('adminhtml/url')->getUrl('adminhtml/motounzercw/success',
				array(
					'order_id' => $order->getId()
				));
		$backendFailUrl = Mage::getModel('adminhtml/url')->getUrl('adminhtml/motounzercw/fail',
				array(
					'order_id' => $order->getId()
				));
		$backendCancelUrl = Mage::getModel('adminhtml/url')->getUrl('adminhtml/motounzercw/cancel',
				array(
					'order_id' => $order->getId()
				));

		$transactionContext = new Customweb_UnzerCw_Model_TransactionContext($orderContext, $order->getIncrementId(),
				$transaction->getId(), $this->getHelper()->getPaymentCustomerContext(), null, $backendSuccessUrl, $backendFailUrl, $backendCancelUrl, $storeId);
		$transactionContext->setMotoTransaction(true);

		$result = new StdClass();
		$result->transactionContext = $transactionContext;
		Mage::dispatchEvent('customweb_payment_create_transcation_context', array(
			'result' => $result,
			'order' => $order
		));

		return $result->transactionContext;
	}

	/**
	 *
	 * @return Customweb_UnzerCw_Helper_Data
	 */
	public function getHelper(){
		if ($this->helper == null) {
			$this->helper = Mage::helper('UnzerCw');
		}
		return $this->helper;
	}

	/**
	 *
	 * @param Customweb_Payment_Authorization_ITransaction $transaction
	 * @return string Error message or null
	 */
	protected function getErrorMessageToDisplay(Customweb_UnzerCw_Model_Transaction $transaction){
		$errorMessages = $transaction->getTransactionObject()->getErrorMessages();
		$messageToDisplay = (string) end($errorMessages);
		reset($errorMessages);
		if (empty($messageToDisplay)) {
			$messageToDisplay = $this->getHelper()->__('There has been a problem during the processing of your payment.');
		}
		else {
			$messageToDisplay = $this->getHelper()->__($messageToDisplay);
		}
		return nl2br($messageToDisplay);
	}

	public function loadAliasForCustomer(){
		$result = array();

		if (Mage::helper('customer')->isLoggedIn()) {
			$customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();

			$collection = Mage::getModel('unzercw/transaction')->getCollection()->addFieldToFilter('customer_id',
					array(
						'eq' => $customerId
					))->addFieldToFilter('payment_method', array(
				'eq' => $this->getCode()
			))->addFieldToFilter('alias_active', array(
				'eq' => 1
			))->addFieldToFilter('alias_for_display', array(
				'neq' => 'NULL'
			));

			foreach ($collection as $alias) {
				$result[$alias->getTransactionId()] = $alias->getAliasForDisplay();
			}
		}
		return $result;
	}

	public function loadAlias($aliasTransactionId){
		return $this->getHelper()->loadTransaction($aliasTransactionId);
	}

	/**
	 * Generates a JSON object from an array.
	 * Array values are writen as strings "value" to the JSON
	 * object with the exceptions of values with a key that ends on "Function" those are stored
	 * directly.
	 *
	 * @param array $array | string
	 * @return string
	 */
	protected function generateJson($array){
		if (!is_array($array)) {
			return $array;
		}
		else {
			$first = true;
			$result = '{';
			foreach ($array as $key => $value) {
				if (!$first) {
					$result .= ',';
				}
				$delim = '"';
				if (substr($key, -strlen('Function')) === 'Function') {
					$delim = ' ';
				}
				$result .= $key . ': ' . $delim . $this->generateJson($value) . $delim;
				$first = false;
			}

			$result .= '}';
			return $result;
		}
	}
}
