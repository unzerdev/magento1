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
class Customweb_UnzerCw_ConfigunzercwController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed(){
		return Mage::getSingleton('admin/session')->isAllowed('system/unzercw');
	}

	/**
	 * Return an instance of the helper.
	 *
	 * @return Customweb_UnzerCw_Helper_Data
	 */
	protected function getHelper(){
		return Mage::helper('UnzerCw');
	}

	public function indexAction(){
		$this->updateStoreHierarchy();
		
		$forms = $this->getFormAdapter()->getForms();
		
		if (!empty($forms)){
			$currentForm = current($forms);
			$this->_redirect('*/*/view', array(
				'tab' => $currentForm->getMachineName(),
				'_current' => true 
			));
		}
		else {
			
			$this->loadLayout();
			
			$this->_title('Unzer');
			
			$this->_setActiveMenu('system/unzercw');
				
			$this->renderLayout();
		}
	}

	public function viewAction(){
		$this->updateStoreHierarchy();
		
		$this->loadLayout();
		
		$this->_title('Unzer');
		
		$this->_setActiveMenu('system/unzercw');
		
		$machineName = $this->getRequest()->getParam('tab');
		if (!empty($machineName)) {
			
			$form =  new Customweb_Payment_BackendOperation_Form($this->getCurrentForm());
			if ($form->isProcessable()) {
				$form->setTargetUrl($this->getUrl('*/*/save', array(
					'tab' => $form->getMachineName(),
					'_current' => true 
				)))->setRequestMethod(Customweb_IForm::REQUEST_METHOD_POST);
			}
			
			$this->_addContent($this->getLayout()->createBlock('unzercw/adminhtml_backendForm_form')->setForm($form));
		}
		
		$this->renderLayout();
	}

	public function saveAction(){
		$session = Mage::getSingleton('adminhtml/session');
		
		$this->updateStoreHierarchy();
		
		$form = $this->getCurrentForm();
		
		$params = $this->getRequest()->getParams();
		if (!isset($params['button'])) {
			$session->addError(Mage::helper('adminhtml')->__('No button returned.'));
		}
		$pressedButton = null;
		foreach ($form->getButtons() as $button) {
			if ($button->getMachineName() == $params['button']) {
				$pressedButton = $button;
			}
		}
		
		if ($pressedButton === null) {
			$session->addError(Mage::helper('adminhtml')->__('Could not find pressed button.'));
		}
		
		
		$this->getFormAdapter()->processForm($form, $pressedButton, $params);
		
		$session->addSuccess(Mage::helper('adminhtml')->__('The configuration has been saved.'));
		
		$this->_redirect('*/*/view', array(
			'_current' => true 
		));
	}

	private function updateStoreHierarchy(){
		$websiteCode = $this->getRequest()->getParam('website');
		$storeCode = $this->getRequest()->getParam('store');
		
		$storeHierarchy = null;
		$storeId = Mage::app()->getDefaultStoreView()->getId();
		if ($websiteCode != null || $storeCode != null) {
			$storeHierarchy = array();
			if ($websiteCode != null) {
				$website = Mage::getModel('core/website')->load($websiteCode);
				$storeHierarchy['website_' . $website->getId()] = $website->getName();
				$storeId = $website->getDefaultStore()->getId();
			}
			if ($storeCode != null) {
				$store = Mage::getModel('core/store')->load($storeCode);
				$storeHierarchy['store_' . $store->getId()] = $store->getName();
				$storeId = $store->getId();
			}
		}
		Customweb_UnzerCw_Model_ConfigurationAdapter::setStoreId($storeId);
		Customweb_UnzerCw_Model_ConfigurationAdapter::setStoreHierarchy($storeHierarchy);
	}

	/**
	 *
	 * @return Customweb_Payment_BackendOperation_Form_IAdapter
	 */
	private function getFormAdapter(){
		$container = Mage::helper('UnzerCw')->createContainer();
		return $container->getBean('Customweb_Payment_BackendOperation_Form_IAdapter');
	}

	private function getCurrentForm(){
		$machineName = $this->getRequest()->getParam('tab');
		
		foreach ($this->getFormAdapter()->getForms() as $form) {
			if ($form->getMachineName() == $machineName) {
				return $form;
			}
		}
		
		throw new Exception(Customweb_Core_String::_("Could not find form with form name '@name'.")->format(array(
			'@name' => $machineName 
		)));
	}
}
