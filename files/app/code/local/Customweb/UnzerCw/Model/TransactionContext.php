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

class Customweb_UnzerCw_Model_TransactionContext implements Customweb_Payment_Authorization_ITransactionContext, Customweb_Payment_Authorization_PaymentPage_ITransactionContext, Customweb_Payment_Authorization_Hidden_ITransactionContext, Customweb_Payment_Authorization_Iframe_ITransactionContext, Customweb_Payment_Authorization_Server_ITransactionContext, Customweb_Payment_Authorization_Moto_ITransactionContext, Customweb_Payment_Authorization_Ajax_ITransactionContext, Customweb_Payment_Authorization_Widget_ITransactionContext, Customweb_Payment_Authorization_IUpdateTransactionContext
{

	/**
	 * @var Customweb_Payment_Authorization_IOrderContext
	 */
	private $orderContext;
	private $processUrl = null;
	private $orderId;
	private $transactionId;
	private $customerContext;
	private $capturingMode = null;
	private $aliasTransactionId = null;
	private $isMotoTransaction = false;
	private $backendSuccessUrl = "";
	private $backendFailUrl = "";
	private $backendCancelUrl = "";
	private $storeId = null;
	private $successUrl = null;
	private $failedUrl = null;
	private $sendOrderEmail = true;

	private $aliasTransactionObject = null;

	public function __construct($orderContext, $orderId, $transactionId, $customerContext, $aliasTransactionId, $backendSuccessUrl, $backendFailUrl, $backendCancelUrl, $storeId = null)
	{
		$this->orderContext = $orderContext;
		$this->orderId = $orderId;
		$this->transactionId = $transactionId;
		$this->customerContext = $customerContext;
		$this->backendSuccessUrl = $backendSuccessUrl;
		$this->backendFailUrl = $backendFailUrl;
		$this->backendCancelUrl = $backendCancelUrl;
		$this->storeId = $storeId;
		$this->aliasTransactionId = $aliasTransactionId;
		$this->successUrl = $this->appendNonTrackingParameter(Mage::getUrl('UnzerCw/process/success', $this->getUrlParameters()));
		$this->failedUrl = $this->appendNonTrackingParameter(Mage::getUrl('UnzerCw/process/fail', $this->getUrlParameters()));
	}

	public function __sleep() {
		return array('orderContext', 'processUrl', 'orderId', 'transactionId', 'customerContext', 'capturingMode', 'aliasTransactionId', 'isMotoTransaction',
			'backendSuccessUrl', 'backendFailUrl', 'backendCancelUrl', 'storeId', 'successUrl', 'failedUrl', 'sendOrderEmail');
	}

	public function getOrderContext()
	{
		return $this->orderContext;
	}

	public function getTransactionModel()
	{
		return Mage::helper('UnzerCw')->loadTransaction($this->transactionId);
	}

	public function getTransactionId()
	{
		return $this->transactionId;
	}

	public function getOrderId()
	{
		return $this->orderId;
	}

	public function isOrderIdUnique()
	{
		return true;
	}

	public function getCapturingMode()
	{
		return $this->capturingMode;
	}

	public function getAlias()
	{
		if ($this->getOrderContext()->getPaymentMethod()->getPaymentMethodConfigurationValue('alias_manager') !== 'active') {
			return null;
		}

		if (empty($this->aliasTransactionId) || $this->aliasTransactionId == 'new') {
			return $this->aliasTransactionId = 'new';
		}

		if ($this->aliasTransactionObject === null) {
			$transaction = Mage::helper('UnzerCw')->loadTransaction($this->aliasTransactionId);
			if ($this->getOrderContext()->getCustomerId() == $transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getCustomerId()) {
				$this->aliasTransactionObject = $transaction->getTransactionObject();
			}
			else {
				$this->aliasTransactionId = 'new';
			}
		}

		return $this->aliasTransactionObject;
	}

	public function getCustomParameters()
	{
		$secretHash = Mage::helper('UnzerCw')->hash($this->getTransactionId());
		return array(
			'cstrxid' => $this->getTransactionId(),
			'secret' => $secretHash
		);
	}

	public function getSuccessUrl()
	{
		return $this->successUrl;
	}

	public function getFailedUrl()
	{
		return $this->failedUrl;
	}

	public function getPaymentCustomerContext()
	{
		return $this->customerContext;
	}

	public function getNotificationUrl()
	{
		return $this->getProcessUrl();
	}

	public function getUpdateUrl()
	{
		return Mage::getUrl('UnzerCw/process/update', array(
			'_secure' => true,
			'_store' => $this->getOrderContext()->getStore()->getId()
		));
	}

	public function getRealBackendSuccessUrl()
	{
		return $this->backendSuccessUrl;
	}

	/**
	 *
	 * @return string
	 */
	public function getRealBackendFailedUrl()
	{
		return $this->backendFailUrl;
	}

	/**
	 *
	 * @return string
	 */
	public function getRealBackendCancelUrl()
	{
		return $this->backendCancelUrl;
	}

	public function getBackendSuccessUrl()
	{
		return Mage::getUrl('UnzerCw/process/motoSuccess', array(
			'_secure' => true,
			'_store' => $this->getOrderContext()->getStore()->getId()
		));
	}

	public function getBackendFailedUrl()
	{
		return Mage::getUrl('UnzerCw/process/motoFail', array(
			'_secure' => true,
			'_store' => $this->getOrderContext()->getStore()->getId()
		));
	}

	public function createRecurringAlias()
	{
		return false;
	}

	public function getHiddenAuthorizationProcessingUrl()
	{
		return $this->getProcessUrl();
	}

	private function getUrlParameters() {
		$params = array(
			'_secure' => true,
			'_nosid' => true
		);
		if ($this->storeId !== null) {
			$params['_store'] = $this->storeId;
		}
		return $params;
	}

	private function appendNonTrackingParameter($url) {
		return Customweb_Util_Url::appendParameters($url, array('utm_nooverride' => '1'));
	}

	private function getProcessUrl()
	{
		if ($this->processUrl == null) {
			$this->processUrl = $this->appendNonTrackingParameter(Mage::getUrl('UnzerCw/process/process', $this->getUrlParameters()));
		}
		return $this->processUrl;
	}

	public function getIframeBreakOutUrl()
	{
		return Mage::getUrl('UnzerCw/checkout/breakout', $this->getUrlParameters());
	}

	/**
	 * @return boolean
	 */
	public function isMotoTransaction()
	{
		return $this->isMotoTransaction;
	}

	/**
	 * @param boolean $flag
	 */
	public function setMotoTransaction($flag)
	{
		$this->isMotoTransaction = $flag;
	}

	public function getJavaScriptSuccessCallbackFunction()
	{
		return "function(url){window.location = url;}";
	}

	public function getJavaScriptFailedCallbackFunction()
	{
		return "function(url){window.location = url;}";
	}

	public function isSendOrderEmail()
	{
		return $this->sendOrderEmail;
	}
}
