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
 * @method int getTransactionId()
 * @method Customweb_UnzerCw_Model_Transaction setTransactionId(int $value)
 * @method string getTransactionExternalId()
 * @method Customweb_UnzerCw_Model_Transaction setTransactionExternalId(string $value)
 * @method int getOrderId()
 * @method Customweb_UnzerCw_Model_Transaction setOrderId(int $value)
 * @method int getOrderPaymentId()
 * @method Customweb_UnzerCw_Model_Transaction setOrderPaymentId(int $value)
 * @method string getAliasForDisplay()
 * @method Customweb_UnzerCw_Model_Transaction setAliasForDisplay(string $value)
 * @method boolean getAliasActive()
 * @method Customweb_UnzerCw_Model_Transaction setAliasActive(boolean $value)
 * @method string getPaymentMethod()
 * @method Customweb_UnzerCw_Model_Transaction setPaymentMethod(string $value)
 * @method Customweb_Payment_Authorization_ITransaction getTransactionObject()
 * @method Customweb_UnzerCw_Model_Transaction setTransactionObject(Customweb_Payment_Authorization_ITransaction $value)
 * @method string getAuthorizationType()
 * @method Customweb_UnzerCw_Model_Transaction setAuthorizationType(string $value)
 * @method int getCustomerId()
 * @method Customweb_UnzerCw_Model_Transaction setCustomerId(int $value)
 * @method string getUpdatedOn()
 * @method Customweb_UnzerCw_Model_Transaction setUpdatedOn(string $value)
 * @method string getCreatedOn()
 * @method Customweb_UnzerCw_Model_Transaction setCreatedOn(string $value)
 * @method string getPaymentId()
 * @method Customweb_UnzerCw_Model_Transaction setPaymentId(string $value)
 * @method boolean getUpdatable()
 * @method Customweb_UnzerCw_Model_Transaction setUpdatable(boolean $value)
 * @method string getExecuteUpdateOn()
 * @method Customweb_UnzerCw_Model_Transaction setExecuteUpdateOn(string $value)
 * @method float getAuthorizationAmount()
 * @method Customweb_UnzerCw_Model_Transaction setAuthorizationAmount(float $value)
 * @method string getAuthorizationStatus()
 * @method Customweb_UnzerCw_Model_Transaction setAuthorizationStatus(string $value)
 * @method boolean getPaid()
 * @method Customweb_UnzerCw_Model_Transaction setPaid(boolean $value)
 * @method string getCurrency()
 * @method Customweb_UnzerCw_Model_Transaction setCurrency(string $value)
 * @method int getVersionNumber();
 * @method Customweb_UnzerCw_Model_Transaction setVersionNumber(int $value)
 * @method boolean isLiveTransaction();
 * @method Customweb_UnzerCw_Model_Transaction setLiveTransaction(boolean $value)
 */
class Customweb_UnzerCw_Model_Transaction extends Mage_Core_Model_Abstract
{
	protected $_eventPrefix = 'customweb_transaction';
	protected $_eventObject = 'transaction';

	private $_order = null;

	private $ignoreStatus = false;

	private $authorizationStatusBefore = null;

	private $uncertain = false;

	/**
	 * @var Customweb_Core_ILogger
	 */
	private $logger;

	protected function _construct()
	{
		$this->logger = Customweb_Core_Logger_Factory::getLogger(get_class($this));
		$this->_init('unzercw/transaction');
	}

	protected function _afterLoad()
	{
		parent::_afterLoad();

		if (is_string($this->getTransactionObject())) {
			$this->setTransactionObject(Mage::helper('UnzerCw')->unserialize($this->getTransactionObject()));
			$orderId = $this->getOrderId();
			if (!empty($orderId)) {
				Customweb_UnzerCw_Model_ConfigurationAdapter::setStore($this->getOrder());
			}
			if ($this->getTransactionObject() instanceof Customweb_Payment_Authorization_ITransaction) {
				$this->uncertain = $this->getTransactionObject()->isAuthorizationUncertain();
			}
		}
	}

	public function save()
	{
		$this->_hasDataChanges = true;
		return parent::save();
	}

	public function saveIgnoreOrderStatus()
	{
		$this->ignoreStatus = true;
		$result = $this->save();
		$this->ignoreStatus = false;
		return $result;
	}

	protected function _beforeSave()
	{
		parent::_beforeSave();

		if ($this->isObjectNew()) {
			$this->setCreatedOn(date("Y-m-d H:i:s"));
		}

		$this->setUpdatedOn(date("Y-m-d H:i:s"));

		if ($this->getTransactionObject() !== null && $this->getTransactionObject() instanceof Customweb_Payment_Authorization_ITransaction) {
			$this->authorizationStatusBefore = $this->getAuthorizationStatus();
			$this->checkIfOrderStatusChanged();
			$this->setVersionNumber($this->getTransactionObject()->getVersionNumber());

			$aliasManagerActive = ($this->getTransactionObject()->getPaymentMethod()->getPaymentMethodConfigurationValue('alias_manager') == 'active');
			if ($aliasManagerActive){

				$aliasForDisplay = $this->getTransactionObject()->getAliasForDisplay();
				if (!empty($aliasForDisplay)){
					$this->setAliasForDisplay($aliasForDisplay);
				}

				// When the alias for display is empty and the alias was once set as active we deactivate it.
				$currentSetAlias = $this->getAliasForDisplay();
				if (empty($aliasForDisplay) && !empty($currentSetAlias)) {
					$this->setAliasActive(false);
				}
			}
			$this->setAuthorizationType($this->getTransactionObject()->getAuthorizationMethod());
			$this->setPaymentMethod($this->getTransactionObject()->getPaymentMethod()->getCode());
			$this->setPaymentId($this->getTransactionObject()->getPaymentId());
			$this->setAuthorizationAmount($this->getTransactionObject()->getAuthorizationAmount());
			$this->setCurrency($this->getTransactionObject()->getCurrencyCode());
			$executeUpdateOn = $this->getTransactionObject()->getUpdateExecutionDate();
			if ($executeUpdateOn instanceof DateTime) {
				$executeUpdateOn = $executeUpdateOn->format('Y-m-d H:i:s');
			}
			$this->setExecuteUpdateOn($executeUpdateOn);
			$this->setAuthorizationStatus($this->getTransactionObject()->getAuthorizationStatus());
			$this->setCustomerId($this->getTransactionObject()->getTransactionContext()->getOrderContext()->getCustomerId());
			$this->setPaid($this->getTransactionObject()->isPaid());
			$this->setTransactionExternalId($this->getTransactionObject()->getExternalTransactionId());

			// When the authorization flag switches to certain from uncertain, we may have to create the invoice.
			if ($this->uncertain !== $this->getTransactionObject()->isAuthorizationUncertain() && !$this->getTransactionObject()->isAuthorizationUncertain()) {
				/**
				 * @var $paymentMethod Customweb_UnzerCw_Model_Method
				 */
				$paymentMethod = $this->getTransactionObject()->getPaymentMethod();
				Mage::register('unzercw_update_transaction', $this);
				$paymentMethod->createInvoiceAfterUncertain($this);
				Mage::unregister('unzercw_update_transaction');
			}
			$this->uncertain = $this->getTransactionObject()->isAuthorizationUncertain();

			$this->setLiveTransaction($this->getTransactionObject()->isLiveTransaction());
		}

		if (!is_string($this->getTransactionObject())) {
			$this->setTransactionObject(Mage::helper('UnzerCw')->serialize($this->getTransactionObject()));
		}
	}

	protected function _afterSave()
	{
		if (is_string($this->getTransactionObject())) {
			$this->setTransactionObject(Mage::helper('UnzerCw')->unserialize($this->getTransactionObject()));
		}

		if ($this->getAliasActive() && $this->getAliasForDisplay() !== null) {
			$collection = Mage::getModel('unzercw/transaction')->getCollection()
				->addFieldToFilter('customer_id', array(
					'eq' => $this->getCustomerId()
				))
				->addFieldToFilter('payment_method', array(
					'eq' => $this->getPaymentMethod()
				))
				->addFieldToFilter('alias_active', array(
					'eq' => 1
				))
				->addFieldToFilter('alias_for_display', array(
					'eq' => $this->getAliasForDisplay()
				))
				->addFieldToFilter('transaction_id', array(
					'neq' => $this->getTransactionId()
				));
			foreach ($collection as $transaction) {
				if ($transaction->getTransactionId() < $this->getTransactionId()) {
					$transaction->setAliasActive(false)->save();
				}
			}
		}

		$this->checkIfAuthorizationIsRequired();
		$this->authorizationStatusBefore = null;

		parent::_afterSave();
	}

	/**
	 * Checks if the transaction must be authorized. In this case the method calls the method 'authorize()'.
	 *
	 * @param Customweb_Database_Entity_IManager $entityManager
	 */
	protected function checkIfAuthorizationIsRequired() {
		if ($this->getTransactionObject() !== null && $this->getTransactionObject()->isAuthorized() && $this->authorizationStatusBefore === Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_PENDING) {
			try {
				$this->logger->logInfo("Start authorization for transaction " . $this->getTransactionId());
				$this->authorize();
				$this->logger->logInfo("Finish authorization for transaction " . $this->getTransactionId());
			}
			catch(Exception $e) {
				$this->logger->logException($e);
				throw $e;
			}
		}
	}

	/**
	 * This method is invoked, when the authorization should be executed on the shop side.
	 *
	 * @param Customweb_Database_Entity_IManager $entityManager
	 */
	protected function authorize() {
		$order = $this->getOrder(false);
		$order->getPayment()->getMethodInstance()->processPayment($order, $this);
	}

	/**
	 * This method checks if the order status must be updated.
	 *
	 * @param Customweb_Database_Entity_IManager $entityManager
	 */
	protected function checkIfOrderStatusChanged() {
		if ($this->ignoreStatus) {
			return;
		}
		if ($this->getTransactionObject() !== null && $this->getTransactionObject() instanceof Customweb_Payment_Authorization_ITransaction) {
			$lastStatus = $this->getLastSetOrderStatusSettingKey();
			$currentStatus = $this->getTransactionObject()->getOrderStatusSettingKey();
			$method = $this->getTransactionObject()->getPaymentMethod();
			if ($currentStatus !== null && ($lastStatus === null || $lastStatus != $currentStatus) && $method->existsPaymentMethodConfigurationValue($currentStatus)) {
				$orderStatusId = $method->getPaymentMethodConfigurationValue($currentStatus);
				$this->updateOrderStatus($orderStatusId, $currentStatus);
			}
			$this->setLastSetOrderStatusSettingKey($currentStatus);
		}
	}

	/**
	 * This method is called whenever the order status has changed and the system has
	 * to change the order status.
	 */
	protected function updateOrderStatus($orderStatus, $orderStatusSettingKey) {
		try {
			$order = $this->getOrder(false);

			if ($order->getStatus() == $orderStatus) {
				return;
			}

			$notifyCustomer = false;
			if ($order->getStatus() == Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_PENDING) {
				$notifyCustomer = true;
			}

			$isCanceled = $order->isCanceled();

			$order->setStatus($orderStatus);
			$order->save();
			$order->addStatusToHistory($orderStatus, '', $notifyCustomer);
			$order->save();

			if ($isCanceled) {
				foreach ($order->getAllItems() as $item) {
					$item->setQtyCanceled(0);
					$item->save();
				}
			}

			Mage::dispatchEvent('unzercw_order_status', array(
				'order' => $order,
				'status' => $orderStatus
			));
		} catch (Exception $e) {}
	}

	/**
	 * @param boolean $cached
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder($cached = true)
	{
		if ($this->_order == null || !$cached) {
			$this->_order = Mage::getModel('sales/order')->load($this->getOrderId());
		}
		return $this->_order;
	}

	public function getExternalTransactionId() {
		return $this->getTransactionExternalId();
	}

	public function getTransactionObject() {
		$object = $this->getData('transaction_object');
		if ($object !== null && $object instanceof Customweb_Payment_Authorization_ITransaction
				&& $this->getVersionNumber() !== null){
			if(!method_exists($object, 'setVersionNumber')) {
				throw new Exception('setVersionNumber function is required on the transactionObject.');
			}
			$object->setVersionNumber($this->getVersionNumber());
		}
		return $object;
	}
}
