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

//require_once 'Customweb/Payment/ITransactionHandler.php';
//require_once 'Customweb/Payment/Exception/OptimisticLockingException.php';


/**
 * @Bean
 */
class Customweb_UnzerCw_Model_TransactionHandler extends Mage_Core_Model_Abstract implements Customweb_Payment_ITransactionHandler
{
	private $cache = array(
		'transaction_id' => array(),
		'payment_id' => array(),
		'transaction_external_id' => array(),
		'order_id' => array()
	);

	protected function _construct()
	{
		$this->_init('unzercw/transaction');
	}

	public function isTransactionRunning() {
		return false;
	}

	public function beginTransaction() {
		$this->_getResource()->beginTransaction();
	}

	public function commitTransaction() {
		$this->_getResource()->commit();
	}

	public function rollbackTransaction() {
		$this->_getResource()->rollBack();
	}

	public function findTransactionByTransactionExternalId($transactionId, $useCache = true) {
		return $this->findTransactionEntityByTransactionExternalId($transactionId, $useCache)->getTransactionObject();
	}

	public function findTransactionByPaymentId($paymentId, $useCache = true) {
		$transaction = $this->loadTransaction($paymentId, 'payment_id', $useCache);
		if ($transaction == null) {
			throw new Exception("Transaction could not be loaded by payment id.");
		} else {
			return $transaction->getTransactionObject();
		}
	}

	public function findTransactionByTransactionId($transactionId, $useCache = true) {
		return $this->findTransactionEntityByTransactionId($transactionId, $useCache)->getTransactionObject();
	}

	public function findTransactionsByOrderId($orderId, $useCache = true) {
		$transaction = $this->loadTransaction($orderId, 'order_id', $useCache);
		if ($transaction == null) {
			return array();
		} else {
			return array($orderId => $transaction->getTransactionObject());
		}
	}

	public function persistTransactionObject(Customweb_Payment_Authorization_ITransaction $transaction) {
		$transaction = $this->findTransactionEntityByTransactionId($transaction->getTransactionId())->setTransactionObject($transaction);
		try {
			$transaction->save();
		} catch(Customweb_UnzerCw_Model_OptimisticLockingException $e) {
			throw new Customweb_Payment_Exception_OptimisticLockingException($transaction->getTransactionId());
		}
	}

	/**
	 * @param string $transactionId
	 * @throws Exception
	 * @return Customweb_Payment_Entity_AbstractTransaction
	 */
	private function findTransactionEntityByTransactionId($transactionId, $useCache = true) {
		$transaction = $this->loadTransaction($transactionId, 'transaction_id', $useCache);
		if ($transaction == null) {
			throw new Exception("Transaction could not be loaded by transaction id.");
		} else {
			return $transaction;
		}
	}
	
	/**
	 * @param string $transactionId
	 * @throws Exception
	 * @return Customweb_Payment_Entity_AbstractTransaction
	 */
	private function findTransactionEntityByTransactionExternalId($transactionId, $useCache = true) {
		$transaction = $this->loadTransaction($transactionId, 'transaction_external_id', $useCache);
		if ($transaction == null) {
			throw new Exception("Transaction could not be loaded by external transaction id.");
		} else {
			return $transaction;
		}
	}

	/**
	 * @param mixed $value
	 * @param string $field
	 * @param boolean $useCache
	 * @return Customweb_UnzerCw_Model_Transaction|null
	 */
	private function loadTransaction($value, $field, $useCache) {
		if ($useCache && isset($this->cache[$field]) && isset($this->cache[$field][$value]) && $this->cache[$field][$value] != null) {
			return $this->cache[$field][$value];
		}
		$transaction = Mage::getModel('unzercw/transaction')->load($value, $field);
		if ($transaction === null || !$transaction->getId()) {
			return null;
		}
		$this->writeCache($transaction);
		return $transaction;
	}

	private function writeCache($transaction) {
		foreach (array_keys($this->cache) as $field) {
			$this->cache[$field][$transaction->getData($field)] = $transaction;
		}
	}
}