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
 *
 */
class Customweb_UnzerCw_OnepageController extends Mage_Checkout_Controller_Action {

	/**
	 *
	 * @return Customweb_UnzerCw_OnepageController
	 */
	public function preDispatch(){
		parent::preDispatch();
		$this->_preDispatchValidateCustomer();
		
		$checkoutSessionQuote = Mage::getSingleton('checkout/session')->getQuote();
		if ($checkoutSessionQuote->getIsMultiShipping()) {
			$checkoutSessionQuote->setIsMultiShipping(false);
			$checkoutSessionQuote->removeAllAddresses();
		}
		
		return $this;
	}

	/**
	 * Send Ajax redirect response
	 *
	 * @return Customweb_UnzerCw_OnepageController
	 */
	protected function _ajaxRedirectResponse(){
		$this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired')->setHeader('Login-Required', 'true')->sendResponse();
		return $this;
	}

	/**
	 * Validate ajax request and redirect on failure
	 *
	 * @return bool
	 */
	protected function _expireAjax(){
		if (!$this->getOnepage()->getQuote()->hasItems() || $this->getOnepage()->getQuote()->getHasError() ||
				 $this->getOnepage()->getQuote()->getIsMultiShipping()) {
			$this->_ajaxRedirectResponse();
			return true;
		}
		$action = $this->getRequest()->getActionName();
		if (Mage::getSingleton('checkout/session')->getCartWasUpdated(true) && !in_array($action, array(
			'index',
			'progress' 
		))) {
			$this->_ajaxRedirectResponse();
			return true;
		}
		
		return false;
	}

	/**
	 * Get one page checkout model
	 *
	 * @return Mage_Checkout_Model_Type_Onepage
	 */
	public function getOnepage(){
		return Mage::getSingleton('checkout/type_onepage');
	}

	/**
	 * Retrieve availale payment methods
	 *
	 * @return array
	 */
	protected function _getMethods(){
		$block = $this->getLayout()->createBlock('payment/form_container', '', array(
			'quote' => $this->getOnepage()->getQuote() 
		));
		return $block->getMethods();
	}

	/**
	 * Get payment method step html
	 *
	 * @return string
	 */
	protected function _getPaymentMethodsHtml(){
		$layout = $this->getLayout();
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_paymentmethod');
		$layout->generateXml();
		$layout->generateBlocks();
		foreach($layout->getAllBlocks() as $block){
			if($block instanceof Customweb_UnzerCw_Block_Form){
				$block->disableJavascript();
			}
		}
		$output = $layout->getOutput();
		return $output;
	}

	/**
	 * Get payment method step javascript
	 *
	 * @return string
	 */
	protected function _getPaymentMethodsJavaScript(){
		$javaScript = '';
		foreach ($this->_getMethods() as $_method) {
			if (method_exists($_method, 'generateFormJavaScript')) {
				$javaScript .= $_method->generateFormJavaScript(array(
					'alias_id' => 'new' 
				)) . "\n";
			}
		}
		return $javaScript;
	}

	/**
	 * Shipping method save action
	 */
	public function saveShippingMethodAction(){
		if ($this->_expireAjax()) {
			return;
		}
		if ($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost('shipping_method', '');
			$result = $this->getOnepage()->saveShippingMethod($data);
			/*
			 * $result will have erro data if shipping method is empty
			 */
			if (!$result) {
				Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', 
						array(
							'request' => $this->getRequest(),
							'quote' => $this->getOnepage()->getQuote() 
						));
				$this->getOnepage()->getQuote()->collectTotals();
				$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
				
				$result['goto_section'] = 'payment';
				$result['update_section'] = array(
					'name' => 'payment-method',
					'html' => $this->_getPaymentMethodsHtml(),
					'js' => $this->_getPaymentMethodsJavaScript() 
				);
			}
			$this->getOnepage()->getQuote()->collectTotals()->save();
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		}
	}
}