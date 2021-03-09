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

class Customweb_UnzerCw_ExternalcheckoutController extends Mage_Core_Controller_Front_Action
{
	private $_context = null;

	public function loginAction()
	{
		$context = $this->getContext();
		if ($context == null || !$context->getId()) {
			$this->_redirect('checkout/cart');
			return;
		}

		if ($this->getCustomerSession()->isLoggedIn()) {
			$this->_redirectUrl($context->getAuthenticationSuccessUrl());
			return;
		}

		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
		$this->getLayout()->getBlock('content')->append(
			$this->getLayout()->createBlock('unzercw/externalCheckout_login')->setContext($context)
		);
		$this->renderLayout();
	}

	/**
	 * Register post action
	 */
	public function registerPostAction()
	{
		if (!$this->_validateFormKey()) {
			$this->_redirect('*/*/login');
			return;
		}

		$session = $this->getCustomerSession();
		$context = $this->getContext();

		if ($session->isLoggedIn()) {
			$this->_redirectUrl($context->getAuthenticationSuccessUrl());
			return;
		}

		if ($this->getRequest()->isPost()) {
			$registerMethod = $this->getRequest()->getPost('register_method');
			$context->setRegisterMethod($registerMethod);

			if ($registerMethod == 'register') {
				$register = $this->getRequest()->getPost('register');
				$data = array(
					'email' => $register['email'],
					'firstname' => $context->getBillingAddress()->getFirstName(),
					'lastname' => $context->getBillingAddress()->getLastName(),
					'customer_password' => $register['customer_password'],
					'confirm_password' => $register['confirm_password'],
				);
			} else {
				$guest = $this->getRequest()->getPost('guest');
				$email = $context->getAuthenticationEmailAddress();
				if (empty($email)) {
					$email = $guest['email'];
				}
				$data = array(
					'email' => $email,
					'firstname' => $context->getBillingAddress()->getFirstName(),
					'lastname' => $context->getBillingAddress()->getLastName(),
				);
			}

			$dateOfBirth = $context->getBillingAddress()->getDateOfBirth();
			if ($dateOfBirth != null) {
				$data['dob'] = Mage::helper('UnzerCw/externalCheckout')->getDateAsString($dateOfBirth);
			}
			$gender = $context->getBillingAddress()->getGender();
			if ($gender != null) {
				if ($gender == 'male') {
					$data['gender'] = 1;
				} elseif ($gender == 'female') {
					$data['gender'] = 2;
				}
			}

			$quote = $this->getQuote();

			if (true !== ($result = Mage::helper('UnzerCw/externalCheckout')->validateCustomerData($quote, $data, $registerMethod))) {
				if (!is_array($result)) {
					$result = array($result);
				}
				foreach ($result as $message) {
					$session->addError($message);
				}
				$this->_redirect('*/*/login');
				return;
			}

			if (!$quote->getCustomerId() && 'register' == $registerMethod) {
				if ($this->customerEmailExists($register['email'], Mage::app()->getWebsite()->getId())) {
					$session->addError(Mage::helper('checkout')->__('There is already a customer registered using this email address. Please login using this email address or enter a different email address to register your account.'));
					$this->_redirect('*/*/login');
					return;
				}
			}

			$quote->collectTotals();
			$quote->save();

			$context->updateFromQuote($quote);
			$context->save();

			$this->_redirectUrl($context->getAuthenticationSuccessUrl());
			return;
		}
		$this->_redirect('*/*/login');
	}

	/**
	 * Login post action
	 */
	public function loginPostAction()
	{
		if (!$this->_validateFormKey()) {
			$this->_redirect('*/*/login');
			return;
		}

		$session = $this->getCustomerSession();
		$context = $this->getContext();

		if ($session->isLoggedIn()) {
			$this->_redirectUrl($context->getAuthenticationSuccessUrl());
			return;
		}

		if ($this->getRequest()->isPost()) {
			$login = $this->getRequest()->getPost('login');
			if (!empty($login['username']) && !empty($login['password'])) {
				try {
					Mage::register('unzercw_external_checkout_login', true);
					$session->login($login['username'], $login['password']);
					Mage::unregister('unzercw_external_checkout_login');
				} catch (Mage_Core_Exception $e) {
					switch ($e->getCode()) {
						case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
							$value = $this->_getHelper('customer')->getEmailConfirmationUrl($login['username']);
							$message = $this->_getHelper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $value);
							break;
						case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
							$message = $e->getMessage();
							break;
						default:
							$message = $e->getMessage();
					}
					$session->addError($message);
					$session->setUsername($login['username']);
				} catch (Exception $e) {
					// Mage::helper('UnzerCw')->logException($e); // PA DSS violation: this exception log can disclose customer password
				}
			} else {
				$session->addError($this->__('Login and password are required.'));
			}
		}

		if ($session->isLoggedIn()) {
			$context->updateFromQuote($this->getQuote());
			$context->save();

			$this->_redirectUrl($context->getAuthenticationSuccessUrl());
		} else {
			$this->_redirect('*/*/login');
		}
	}

	/**
	 * Check if customer email exists
	 *
	 * @param string $email
	 * @param int $websiteId
	 * @return false|Mage_Customer_Model_Customer
	 */
	private function customerEmailExists($email, $websiteId = null)
	{
		$customer = Mage::getModel('customer/customer');
		if ($websiteId) {
			$customer->setWebsiteId($websiteId);
		}
		$customer->loadByEmail($email);
		if ($customer->getId()) {
			return $customer;
		}
		return false;
	}

	/**
	 * @return Mage_Sales_Model_Quote
	 */
	private function getQuote()
	{
		return Mage::getSingleton('checkout/session')->getQuote();
	}

	private function getCustomerSession()
	{
		return Mage::getSingleton('customer/session');
	}

	/**
	 * @return Customweb_UnzerCw_Model_ExternalCheckoutContext
	 */
	private function getContext()
	{
		if ($this->_context == null) {
			$this->_context = Mage::getModel('unzercw/externalCheckoutContext')->loadByQuote($this->getQuote());
		}
		return $this->_context;
	}
}