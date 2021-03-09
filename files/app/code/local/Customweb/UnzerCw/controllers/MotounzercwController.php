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

class Customweb_UnzerCw_MotounzercwController extends Mage_Adminhtml_Controller_Action
{

	protected function _isAllowed()
	{
		return Mage::getSingleton('admin/session')->isAllowed('sales/unzercw/unzercw_moto');
	}

	/**
	 * @return Customweb_UnzerCw_Model_Transaction
	 */
	protected function getTransaction()
	{
		if (Mage::registry('cw_transaction') == null || !Mage::registry('cw_transaction')->getId()) {
			$transaction = null;

			$transactionId = $this->getRequest()->getParam('transaction_id');
			$shopTransactionId = $this->getRequest()->getParam('cstrxid');
			$externalTransactionId = $this->getRequest()->getParam('cw_transaction_id');
			$registryTransactionId = Mage::registry('cstrxid');

			if (!empty($transactionId)) {
				$transaction = $this->getHelper()->loadTransaction($transactionId);
			} elseif (!empty($shopTransactionId)) {
				$transaction = $this->getHelper()->loadTransaction($shopTransactionId);
			} elseif (!empty($externalTransactionId)) {
				$transaction = $this->getHelper()->loadTransactionByExternalId($externalTransactionId);
			} elseif (!empty($registryTransactionId)) {
				$transaction = $this->getHelper()->loadTransactionByExternalId($registryTransactionId);
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
	protected function getHelper()
	{
		return Mage::helper('UnzerCw');
	}

	public function processAction()
	{
		$this->loadLayout();

		$this->getLayout()
			->getBlock('head')
			->addCss('css/unzercw.css')
			->addJs('customweb/unzercw/moto.js');
		$this->getLayout()
			->getBlock('content')
			->append($this->getLayout()
				->createBlock('unzercw/moto'));

		$this->renderLayout();
	}

	public function successAction()
	{
		$transaction = $this->getTransaction();
		$transaction->getOrder()->getPayment()->getMethodInstance()->success($transaction, $_REQUEST);

		Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Successful payment authorization.'));
		$url = Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order/view', array(
				'order_id' => $transaction->getOrder()->getId()
			));
		header('Location: ' . $url);
		exit;
	}

	public function failAction()
	{
		$transaction = $this->getTransaction();
		$transaction->getOrder()->getPayment()->getMethodInstance()->fail($transaction, $_REQUEST);

		Mage::getSingleton('adminhtml/session')->addError($this->__('Failed payment authorization.'));

		$url = Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order_create/reorder', array(
				'order_id' => $transaction->getOrder()->getId()
			));
		header('Location: ' . $url);
		exit;
	}

	public function cancelAction()
	{
		$transaction = $this->getTransaction();
		$transaction->getTransactionObject()->setAuthorizationFailed('The payment was cancelled by the merchant.');
		$transaction->save();

		$transaction->getOrder()->getPayment()->getMethodInstance()->fail($transaction, $_REQUEST);

		$url = Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order_create/reorder', array(
			'order_id' => $transaction->getOrder()->getId()
		));
		header('Location: ' . $url);
		exit;
	}

}
