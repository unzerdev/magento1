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

class Customweb_UnzerCw_Helper_Data extends Mage_Core_Helper_Abstract
{
	const HASH_SEPARATOR = ':';

	private static $container = null;

	public function log($message, $level = null, $file = '', $forceLog = false) {
		if (Mage::getStoreConfig('unzercw/general/debug_log') == '1') {
			Mage::log($message, $level, $file, $forceLog);
		}
	}

	public function logException(Exception $e) {
		if (Mage::getStoreConfig('unzercw/general/debug_log') == '1') {
			Mage::logException($e);
		}
	}

	public function hash($data) {
		$timestamp = time();
		$salt = (string)Mage::getConfig()->getNode('global/crypt/key');
		return dechex($timestamp) . self::HASH_SEPARATOR . sha1($data . $timestamp . $salt);
	}

	public function validateHash($hash, $data, $validity = 120) {
		$timestamp = hexdec(substr($hash, 0, strpos($hash, self::HASH_SEPARATOR)));
		$salt = (string)Mage::getConfig()->getNode('global/crypt/key');
		$calculatedHash = dechex($timestamp) . self::HASH_SEPARATOR . sha1($data . $timestamp . $salt);
		if ($calculatedHash != $hash) {
			return false;
		}
		if (time() > $timestamp + $validity) {
			return false;
		}
		return true;
	}

	/**
	 * @return Customweb_DependencyInjection_Container_Default
	 */
	public function createContainer() {
		if (self::$container === null) {
			if (!function_exists('cw_class_loader')) {
				function cw_class_loader($className) {
					return Varien_Autoload::instance()->autoload($className);
				}
				Customweb_Core_Util_Class::registerClassLoader('cw_class_loader');
			}

			$packages = array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		);
			$packages[] = 'Customweb_UnzerCw_Model_';
			$packages[] = 'Customweb_Mvc_Template_Php_Renderer';
			$packages[] = 'Customweb_Payment_SettingHandler';

			$provider = new Customweb_DependencyInjection_Bean_Provider_Editable(new Customweb_DependencyInjection_Bean_Provider_Annotation(
					$packages
			));
			$provider->addObject(Customweb_Core_Http_ContextRequest::getInstance())
				->addObject($this->getAssetResolver());
			self::$container = new Customweb_DependencyInjection_Container_Default($provider);
		}

		return self::$container;
	}

	public function getAssetResolver() {
		return new Customweb_Asset_Resolver_Composite(array(
			Mage::getModel('unzercw/asset_skinResolver'),
			Mage::getModel('unzercw/asset_jsResolver'),
			Mage::getModel('unzercw/asset_templateResolver'),
			new Customweb_Asset_Resolver_Simple(Mage::getBaseDir('media') . '/customweb/unzercw/assets/', Mage::getBaseUrl('media') . '/customweb/unzercw/assets/')
		));
	}

	protected function getAuthorizationAdapterFactory() {
		$container = $this->createContainer();
		$factory = $container->getBean('Customweb_Payment_Authorization_IAdapterFactory');

		if (!($factory instanceof Customweb_Payment_Authorization_IAdapterFactory)) {
			throw new Exception("The payment api has to provide a class which implements 'Customweb_Payment_Authorization_IAdapterFactory' as a bean.");
		}

		return $factory;
	}

	public function getAuthorizationAdapter($authorizationMethodName) {
		return $this->getAuthorizationAdapterFactory()->getAuthorizationAdapterByName($authorizationMethodName);
	}

	public function getAuthorizationAdapterByContext(Customweb_Payment_Authorization_IOrderContext $orderContext) {
		return $this->getAuthorizationAdapterFactory()->getAuthorizationAdapterByContext($orderContext);
	}

	/**
	 * This function serialize the given object to store it in the database.
	 *
	 * @param Object $object
	 * @return String A base64 representation of the object
	 */
	public function serialize($object)
	{
		return base64_encode(serialize($object));
	}

	/**
	 * @param string $string
	 * @return Customweb_Payment_Authorization_ITransaction
	 */
	public function unserialize($string)
	{

		// Detect if it is base 64 decoded
		if (!strstr($string, ':')) {
			$string = base64_decode($string);
		}

		set_error_handler(array(
			$this,
			'unserializationErrorHandler'
		));
		try {
			$object = unserialize($string);
		} catch (Exception $e) {
			// Give a second try with UTF-8 Decoding (legacy code)
			$object = unserialize(utf8_decode($string));
		}
		restore_error_handler();
		return $object;
	}

	public function unserializationErrorHandler($errno, $errstr, $errfile, $errline)
	{
		throw new Exception($errstr);
	}

	public function loadTransactionByPayment($orderPaymentId)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($orderPaymentId, 'order_payment_id');
		if ($transaction !== null && $transaction->getId()) {
			return $transaction;
		}

		$order = Mage::getModel('sales/order_payment')->load($orderPaymentId)->getOrder();
		$transaction = $this->migrateTransaction($order);
		if ($transaction !== null && $transaction->getId()) {
			return $transaction;
		}

		throw new Exception('The transaction could not have been loaded.');
	}

	public function loadTransactionByOrder($orderId)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($orderId, 'order_id');
		if ($transaction !== null && $transaction->getId()) {
			return $transaction;
		}

		$order = Mage::getModel('sales/order')->load($orderId);
		$transaction = $this->migrateTransaction($order);
		if ($transaction !== null && $transaction->getId()) {
			return $transaction;
		}

		throw new Exception('The transaction could not have been loaded.');
	}

	public function loadTransaction($transactionId)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($transactionId);
		if ($transaction === null || !$transaction->getId()) {
			return null;
		}
		return $transaction;
	}

	public function loadTransactionByExternalId($transactionId)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($transactionId, 'transaction_external_id');
		if ($transaction === null || !$transaction->getId()) {
			return null;
		}
		return $transaction;
	}

	public function loadTransactionByPaymentId($paymentId)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($paymentId, 'payment_id');
		if ($transaction === null || !$transaction->getId()) {
			return null;
		}
		return $transaction;
	}

	public function migrateCustomersTransactions(Mage_Customer_Model_Customer $customer)
	{
		if ($customer->getData('unzercw_migrated')) {
			return;
		}

		$payments = Mage::getModel('sales/order_payment')->getCollection()
			->join(
					array('order' => 'sales/order'),
					'main_table.parent_id=order.entity_id',
					array('customer_id' => 'order.customer_id')
			)
			->addFieldToFilter('customer_id', $customer->getId())
			->addFieldToFilter('additional_data', array('notnull' => true))
			->addFieldToFilter('method', array('like' => 'unzercw_%'));

		foreach ($payments as $payment) {
			try {
				$this->loadTransactionByOrder($payment->getParentId());
			} catch(Exception $e) {}
		}

		$connection = Mage::getSingleton('core/resource')->getConnection('core_write');
		$query = 'UPDATE ' . Mage::getSingleton('core/resource')->getTableName('customer_entity')
			. ' SET unzercw_migrated = 1, created_at = ' . $connection->quote(Varien_Date::formatDate($customer->getCreatedAt()))
			. ' WHERE entity_id = ' . (int)$customer->getId();
		$connection->query($query);
	}

	protected function migrateTransaction(Mage_Sales_Model_Order $order)
	{
		if ($order !== null && $order->getPayment() !== false) {
			$additionalData = $order->getPayment()->getAdditionalData();
			if (!empty($additionalData)) {
				$transactionObject = $this->unserialize($additionalData);
				$transaction = Mage::getModel('unzercw/transaction');
				$transaction->setOrderId($order->getId());
				$transaction->setOrderPaymentId($order->getPayment()->getId());
				$transaction->setTransactionObject($transactionObject);

				$alias = Mage::getModel('unzercw/aliasdata')->load($order->getId(), 'order_id');
				if ($alias !== null && $alias->getAliasId()) {
					$transaction->setAliasActive(true);
				} else {
					$transaction->setAliasActive(false);
				}

				$transaction->saveIgnoreOrderStatus();

				return $transaction;
			}
		}
	}

	public function loadOrderByTransactionId($transactionId)
	{
		$transaction = Mage::getModel('sales/order_payment_transaction')->loadByTxnId($transactionId);
		return $transaction->getOrder();
	}

	/**
	 * Retrieves the stored customer payment context for the given customer or for the current
	 * customer if no customer id is given.
	 *
	 * @param string $customerId
	 * @return Customweb_Payment_Authorization_IPaymentCustomerContext
	 */
	public function getPaymentCustomerContext($customerId = null)
	{
		$id = ($customerId != null) ? $customerId : $this->getCurrentCustomerId();

		return Customweb_UnzerCw_Model_PaymentCustomerContext::getByCustomerId($id);
	}

	private function getCurrentCustomerId()
	{
		return Mage::getSingleton('customer/session')->getCustomer()->getId();
	}

	public function getConfigurationValue($key)
	{
		$configAdapter = new Customweb_UnzerCw_Model_ConfigurationAdapter();
		return $configAdapter->getConfigurationValue($key);
	}

	public function setConfigurationStoreId($storeId)
	{
		Customweb_UnzerCw_Model_ConfigurationAdapter::setStoreId($storeId);
	}

	public function isAliasManagerActive()
	{
		return $this->getConfigurationValue('alias_manager') != 'inactive';
	}

	public function getTooltip($block, $message)
	{
		static $includeJavascript = true;
		static $idCount = 0;
		$idCount++;
		$html = "";
		$message = str_replace("'", "\'", $message);
		$toolTipId = "tooltip" . $idCount;
		if ($includeJavascript) {
			$includeJavascript = false;
			$html .= "<script language=\"javascript\" type=\"text/javascript\" >
					function showTooltip(div, desc)
					{
					 div.style.display = 'inline';
					 div.style.position = 'absolute';
					 div.style.width = '300px';
					 div.style.backgroundColor = '#EFFCF0';
					 div.style.border = 'solid 1px black';
					 div.style.padding = '10px';
					 div.innerHTML = '<div style=\"padding-left:10; padding-right:5 width: 300px;\">' + desc + '</div>';
					}

					function hideTooltip(div)
					{
					 div.style.display = 'none';
					}
					</script>";
		}
		$html .= "<img onMouseOut=\"hideTooltip(" . $toolTipId . ")\" onMouseOver=\"showTooltip(" . $toolTipId . ", '" . $message . "')\" src=\"" . $block->getSkinUrl('images/fam_help.gif') . "\" width=\"16\" height=\"16\" border=\"0\">
				<div style=\"display:none\" id=\"" . $toolTipId . "\"></div>";

		return $html;
	}

	public function getSuccessUrl($transaction)
	{
		$result = new StdClass;
		$result->url = Mage::getUrl('checkout/onepage/success', array('_secure' => true));
		Mage::dispatchEvent('customweb_success_redirection', array(
			'result' => $result,
			'transaction' => $transaction
		));
		return $result->url;
	}

	public function getFailUrl($transaction)
	{
		$frontentId =  'checkout/onepage/';
		$parameters = array();

		// If the onestep checkout module is enabled redirect there 
		if(Mage::helper('core')->isModuleEnabled('Idev_OneStepCheckout') && Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
			$frontentId =  'onestepcheckout';
		}

		// If the firecheckout module is enabled redirect there
		if(Mage::helper('core')->isModuleEnabled('TM_FireCheckout') && Mage::getStoreConfig('firecheckout/general/enabled')) {
			$frontentId =  'firecheckout';
		}

		// If the magestore onestep checkout module is enabled redirect there
		if(Mage::helper('core')->isModuleEnabled('Magestore_Onestepcheckout') && Mage::getStoreConfig('onestepcheckout/general/active')) {
			$frontentId =  'onestepcheckout';
		}

		if ($transaction->getOrder()->getCustomerId() != null) {
			$customer = Mage::getModel('customer/customer')->load($transaction->getOrder()->getCustomerId());
			if ($customer->getConfirmation() && $customer->isConfirmationRequired()) {
				// Cannot move to the checkout payment step directly as the customer account is not confirmed yet.
			} else {
				$parameters['loadFailed'] = 'true';
			}
		}

		$redirectionUrl = Customweb_Util_Url::appendParameters(
			Mage::getUrl($frontentId, array('_secure' => true)),
			$parameters
		);

		$result = new StdClass;
		$result->url = $redirectionUrl;
		Mage::dispatchEvent('customweb_failure_redirection', array(
			'result' => $result,
			'transaction' => $transaction
		));

		return $result->url;
	}

	/**
	 * @param string $transactionId
	 * @return string
	 */
	public function waitForNotification($transaction)
	{
		if (Mage::getStoreConfig('unzercw/general/wait_for_success') != '1') {
			$transaction->getOrder()->getPayment()->getMethodInstance()->success($transaction, $_REQUEST);
			return $this->getSuccessUrl($transaction);
		}

		$transactionId = $transaction->getId();

		$maxTime = min(array(Customweb_Util_System::getMaxExecutionTime() - 4, 30));
		$startTime = microtime(true);
		while(true){
			if (microtime(true) - $startTime >= $maxTime) {
				break;
			}

			$transaction = Mage::getModel('unzercw/transaction')->load($transactionId);
			if ($transaction != null && $transaction->getId() && $transaction->getTransactionObject() != null) {
				if ($transaction->getTransactionObject()->isAuthorized()) {
					$transaction->getOrder()->getPayment()->getMethodInstance()->success($transaction, $_REQUEST);
					return $this->getSuccessUrl($transaction);
				}
				if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
					$errorMessages = $transaction->getTransactionObject()->getErrorMessages();
					$messageToDisplay = nl2br(end($errorMessages));
					reset($errorMessages);

					$transaction->getOrder()->getPayment()->getMethodInstance()->fail($transaction, $_REQUEST);
					return $this->getFailUrl($transaction);
				}
			}
			sleep(2);
		}

		$transaction->getOrder()->getPayment()->getMethodInstance()->success($transaction, $_REQUEST);
		Mage::getSingleton('core/session')->addError($this->__('There has been a problem during the processing of your payment. Please contact the shop owner to make sure your order was placed successfully.'));
		return $this->getSuccessUrl($transaction);
	}

	public function getStatusStates($status)
	{
		$states = array();
		$collection = Mage::getResourceModel('sales/order_status_collection');
		$collection->joinStates();
		$collection->getSelect()->where('state_table.status=?', $status);
		foreach ($collection as $state) {
			$states[] = $state;
		}
		return $states;
	}
}
