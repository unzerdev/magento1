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

class Customweb_UnzerCw_Model_Transaction_Api extends Mage_Api_Model_Resource_Abstract
{

    public function info($transactionId, $attributes = null)
    {
    	$transaction = Mage::getModel('unzercw/transaction')->load($transactionId);
    	if (!$transaction->getId()) {
    		$this->_fault('not_exists');
    	}
    	return $this->prepareEntry($transaction, $attributes);
    }
    
    public function infoByPaymentId($paymentId, $attributes = null)
    {
    	$transaction = Mage::getModel('unzercw/transaction')->load($paymentId, 'payment_id');
    	if (!$transaction->getId()) {
    		$this->_fault('not_exists');
    	}
    	return $this->prepareEntry($transaction, $attributes);
    }

    public function items($filters = array())
    {
    	$collection = Mage::getModel('unzercw/transaction')->getCollection();

    	$apiHelper = Mage::helper('api');
    	if (method_exists($apiHelper, 'parseFilters')) {
	    	$filters = $apiHelper->parseFilters($filters);
	    	if (is_array($filters)) {
	    		try {
	    			foreach ($filters as $field => $value) {
	    				$collection->addFieldToFilter($field, $value);
	    			}
	    		} catch (Mage_Core_Exception $e) {
	    			$this->_fault('filters_invalid', $e->getMessage());
	    		}
	    	}
    	}

    	$data = array();
    	foreach ($collection as $transaction) {
    		$data[] = $this->prepareEntry($transaction);
    	}
		return $data;
    }

    protected function prepareEntry(Customweb_UnzerCw_Model_Transaction $transaction, $attributes = null)
    {
    	if (!is_null($attributes) && !is_array($attributes)) {
    		$attributes = array($attributes);
    	}
    	if (is_null($attributes)) {
    		$attributes = $this->getAvailableAttributes();
    	}
    	$attributes = array_intersect($attributes, $this->getAvailableAttributes());

    	if (is_string($transaction->getTransactionObject())) {
    		$transaction->setTransactionObject(Mage::helper('UnzerCw')->unserialize($transaction->getTransactionObject()));
    	}

    	$data = $transaction->toArray();
    	$data['data'] = array();
    	if ($transaction->getTransactionObject() instanceof Customweb_Payment_Authorization_ITransaction
    		&& is_array($transaction->getTransactionObject()->getTransactionData())) {
	    	foreach ($transaction->getTransactionObject()->getTransactionData() as $key => $value) {
	    		$data['data'][] = array('key' => $key, 'value' => utf8_encode($value));
	    	}
    	}
    	foreach ($data as $key => $value) {
    		if (!in_array($key, $attributes)) {
    			unset($data[$key]);
    		}
    	}
    	return $data;
    }

    protected function getAvailableAttributes()
    {
    	return array(
    		'transaction_id',
    		'transaction_external_id',
    		'order_id',
    		'order_payment_id',
    		'alias_for_display',
    		'alias_active',
    		'payment_method',
    		'authorization_type',
    		'customer_id',
    		'updated_on',
    		'created_on',
    		'payment_id',
    		'authorization_amount',
    		'authorization_status',
    		'paid',
    		'currency',
    		'data'
    	);
    }

}