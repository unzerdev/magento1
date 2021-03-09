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
abstract class Customweb_UnzerCw_Controller_Action extends Mage_Core_Controller_Front_Action {

	/**
	 *
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	protected function getTransaction(){
		if (Mage::registry('cw_transaction') == null || !Mage::registry('cw_transaction')->getId()) {
			$transaction = null;

			$transactionId = $this->getRequest()->getParam('transaction_id');
			$shopTransactionId = $this->getRequest()->getParam('cstrxid');
			$externalTransactionId = $this->getRequest()->getParam('cw_transaction_id');
			$registryTransactionId = Mage::registry('cstrxid');

			/* @var Customweb_UnzerCw_Model_Transaction $transaction */
			if (!empty($shopTransactionId)) {
				$transaction = $this->getHelper()->loadTransaction($shopTransactionId);
			}
			elseif (!empty($externalTransactionId)) {
				$transaction = $this->getHelper()->loadTransactionByExternalId($externalTransactionId);
			}
			elseif (!empty($transactionId)) {
				$transaction = $this->getHelper()->loadTransaction($transactionId);
			}
			elseif (!empty($registryTransactionId)) {
				$transaction = $this->getHelper()->loadTransactionByExternalId($registryTransactionId);
			}

			if ($transaction == null || !$transaction->getId()) {
				Mage::throwException("Transaction was not found.");
			}

			if (!$this->getHelper()->validateHash($this->getRequest()->getParam('secret'), $transaction->getId(), 500)) {
				Mage::throwException('Transaction was not found.');
			}

			Mage::register('cw_transaction', $transaction);
		}

		return Mage::registry('cw_transaction');
	}

	/**
	 *
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	protected function getTransactionFromSession(){
		if (Mage::registry('cw_transaction') == null || !Mage::registry('cw_transaction')->getId()) {
			$transaction = null;

			$sessionTransactionId = Mage::getSingleton('core/session')->getUnzerCwTransactionId();

			if (!empty($sessionTransactionId)) {
				$transaction = $this->getHelper()->loadTransaction($sessionTransactionId);
			}

			if ($transaction == null || !$transaction->getId()) {
				Mage::throwException("Transaction was not found.");
			}

			Mage::register('cw_transaction', $transaction);
		}

		return Mage::registry('cw_transaction');
	}

	/**
	 *
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	protected function getTransactionFromRequest(){
		if (Mage::registry('cw_transaction') == null || !Mage::registry('cw_transaction')->getId()) {
			$transaction = null;

			$transactionId = $this->getRequest()->getParam('transaction_id');

			if (!$this->getHelper()->validateHash($this->getRequest()->getParam('secret'), $transactionId)) {
				Mage::throwException('Invalid secret.');
			}

			if (!empty($transactionId)) {
				$transaction = $this->getHelper()->loadTransaction($transactionId);
			}

			if ($transaction == null || !$transaction->getId()) {
				Mage::throwException("Transaction was not found.");
			}

			Mage::register('cw_transaction', $transaction);
		}

		return Mage::registry('cw_transaction');
	}

	/**
	 * Return an instance of the helper.
	 *
	 * @return Customweb_UnzerCw_Helper_Data
	 */
	protected function getHelper(){
		return Mage::helper('UnzerCw');
	}
}