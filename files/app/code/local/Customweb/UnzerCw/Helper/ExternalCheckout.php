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

class Customweb_UnzerCw_Helper_ExternalCheckout extends Mage_Core_Helper_Abstract
{
	public function getDateAsString(DateTime $date)
	{
		$zendDate = new Zend_Date($date->format('r'), Zend_Date::RFC_2822);
		return $zendDate->toString(Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT));
	}

	public function isGenderRequired($quote)
	{
		$entityType = Mage::getSingleton('eav/config')->getEntityType('customer');
		$attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'gender');
		return $attribute->getIsRequired() && $quote->getCustomerGender() == 'undefined';
	}

	public function isDateOfBirthRequired($quote)
	{
		$entityType = Mage::getSingleton('eav/config')->getEntityType('customer');
		$attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'dob');
		return $attribute->getIsRequired() && $quote->getCustomerDob() == '1000-01-01 00:00:00';;
	}

	/**
	 * Validate customer data and set some its data for further usage in quote
	 * Will return either true or array with error messages
	 *
	 * @param Mage_Sales_Model_Quote $quote
	 * @param array $data
	 * @return true|array
	 */
	public function validateCustomerData($quote, array $data, $registerMethod)
	{
		/** @var $customerForm Mage_Customer_Model_Form */
		$customerForm = Mage::getModel('customer/form');
		$customerForm->setFormCode('customer_account_create');

		if ($quote->getCustomerId()) {
			$customer = $quote->getCustomer();
			$customerForm->setEntity($customer);
			$customerData = $quote->getCustomer()->getData();
		} else {
			/* @var $customer Mage_Customer_Model_Customer */
			$customer = Mage::getModel('customer/customer');
			$customerForm->setEntity($customer);
			$customerRequest = $customerForm->prepareRequest($data);
			$customerData = $customerForm->extractData($customerRequest);
		}

		if (empty($customerData['gender'])) {
			$customerData['gender'] = 'undefined';
		}
		if (empty($customerData['dob'])) {
			$customerData['dob'] = '1000-01-01';
		}

		$customerErrors = $customerForm->validateData($customerData);
		if ($customerErrors !== true) {
			return $customerErrors;
		}

		if ($quote->getCustomerId()) {
			return true;
		}

		$customerForm->compactData($customerData);

		if ($registerMethod == 'register') {
			// set customer password
			$customer->setPassword($customerRequest->getParam('customer_password'));
			$customer->setConfirmation($customerRequest->getParam('confirm_password'));
			$customer->setPasswordConfirmation($customerRequest->getParam('confirm_password'));
		} else {
			// spoof customer password for guest
			$password = $customer->generatePassword();
			$customer->setPassword($password);
			$customer->setConfirmation($password);
			$customer->setPasswordConfirmation($password);
			// set NOT LOGGED IN group id explicitly,
			// otherwise copyFieldset('customer_account', 'to_quote') will fill it with default group id value
			$customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
		}

		$result = $customer->validate();
		if (true !== $result && is_array($result)) {
			return $result;
		}

		if ($registerMethod == 'register') {
			// save customer encrypted password in quote
			$quote->setPasswordHash($customer->encryptPassword($customer->getPassword()));
		}

		// copy customer/guest email to address
		$quote->getBillingAddress()->setEmail($customer->getEmail());

		// copy customer data to quote
		Mage::helper('core')->copyFieldset('customer_account', 'to_quote', $customer, $quote);

		return true;
	}
}
