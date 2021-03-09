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
class Customweb_UnzerCw_Block_Checkout extends Mage_Core_Block_Template {

	protected function _construct(){
		parent::_construct();
		$this->setTemplate('customweb/unzercw/checkout.phtml');
	}

	public function getPaymentMethods(){
		$paymentMethods = array();
		$payments = Mage::getSingleton('payment/config')->getActiveMethods();
		foreach ($payments as $paymentCode => $paymentModel) {
			if (preg_match("/unzercw/i", $paymentCode)) {
				$paymentMethods[] = $paymentCode;
			}
		}
		if (!empty($paymentMethods)) {
			if ($this->getRequest()->getParam('loadFailed') == 'true') {
				Mage::getSingleton('checkout/session')->setStepData('billing', 'allow', true)->setStepData('billing', 'complete', true)->setStepData(
						'shipping', 'allow', true)->setStepData('shipping', 'complete', true)->setStepData('shipping_method', 'allow', true)->setStepData(
						'shipping_method', 'complete', true)->setStepData('payment', 'allow', true);
			}
		}
		return $paymentMethods;
	}

	public function getHiddenFieldsUrl(){
		return Mage::getUrl('UnzerCw/process/getHiddenFields', array(
			'_secure' => true 
		));
	}

	public function getVisibleFieldsUrl(){
		return Mage::getUrl('UnzerCw/process/getVisibleFields', array(
			'_secure' => true 
		));
	}

	public function getAuthorizeUrl(){
		return Mage::getUrl('UnzerCw/process/authorize', array(
			'_secure' => true 
		));
	}

	public function getJavascriptUrl(){
		return Mage::getUrl('UnzerCw/process/ajax', array(
			'_secure' => true 
		));
	}

	public function getSaveShippingMethodUrl(){
		return Mage::getUrl('UnzerCw/onepage/saveShippingMethod', array(
			'_secure' => true 
		));
	}

	public function isPreload(){
		$payments = Mage::getSingleton('payment/config')->getActiveMethods();
		if (!empty($payments)) {
			return true;
		}
		else {
			return false;
		}
	}

	public function getPreloadUrl(){
		if (version_compare(Mage::getVersion(), '1.8', '>=')) {
			return Mage::getUrl('UnzerCw/process/preloadOnepage', array(
				'_secure' => true 
			));
		}
		else {
			return false;
		}
	}
}