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

class Customweb_UnzerCw_Model_Api2_Transaction_Rest extends Customweb_UnzerCw_Model_Api2_Transaction
{
	protected function _retrieve()
	{
		$transaction = $this->_loadTransactionById($this->getRequest()->getParam('id'));
		return $this->prepareEntry($transaction);
	}

	protected function _retrieveCollection()
	{
		$data = array();
		foreach ($this->_getCollectionForRetrieve() as $transaction) {
			$data[] = $this->prepareEntry($transaction);
		}
		return $data;
	}

	protected function _loadTransactionById($id)
	{
		$transaction = Mage::getModel('unzercw/transaction')->load($id);
		if (!$transaction->getId()) {
			$this->_critical(self::RESOURCE_NOT_FOUND);
		}
		return $transaction;
	}

	protected function _getCollectionForRetrieve()
	{
		$collection = Mage::getResourceModel('unzercw/transaction_collection');
		$this->_applyCollectionModifiers($collection);
		return $collection;
	}

	protected function prepareEntry(Customweb_UnzerCw_Model_Transaction $transaction)
	{
		$attributes = array_keys($this->getAvailableAttributes($this->getUserType(), Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_READ));

		if (is_string($transaction->getTransactionObject())) {
			$transaction->setTransactionObject(Mage::helper('UnzerCw')->unserialize($transaction->getTransactionObject()));
		}

		$data = $transaction->toArray();
		$data['data'] = array();
		if ($transaction->getTransactionObject() instanceof Customweb_Payment_Authorization_ITransaction
			&& is_array($transaction->getTransactionObject()->getTransactionData())) {
			$data['data'] = $transaction->getTransactionObject()->getTransactionData();
		}
		foreach ($data as $key => $value) {
			if (!in_array($key, $attributes)) {
				unset($data[$key]);
			}
		}
		return $data;
	}
}