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

class Customweb_UnzerCw_ProcessController extends Customweb_UnzerCw_Controller_Action
{
	public function processAction()
	{
		$container = Mage::helper('UnzerCw')->createContainer();
		$packages = array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		);
		$adapter = Mage::getModel('unzercw/endpointAdapter');

		$dispatcher = new Customweb_Payment_Endpoint_Dispatcher($adapter, $container, $packages);
		$response = $dispatcher->invokeControllerAction(Customweb_Core_Http_ContextRequest::getInstance(), 'process', 'index');
		$wrapper = new Customweb_Core_Http_Response($response);
		$wrapper->send();
		die();
	}

	public function getHiddenFieldsAction()
	{
		$transaction = $this->getTransactionFromSession();
		$javaScriptObjectString = $transaction->getOrder()->getPayment()->getMethodInstance()->generateHiddenFormParameters($transaction);

		echo $javaScriptObjectString;
		exit;
	}

	public function ajaxAction()
	{
		$transaction = $this->getTransactionFromSession();
		$javaScriptObjectString = $transaction->getOrder()->getPayment()->getMethodInstance()->generateJavascriptForAjax($transaction);

		echo $javaScriptObjectString;
		exit;
	}

	/**
	 * This action is needed for hidden and server authorization.
	 */
	public function dummyAction()
	{
		if ($this->getRequest()->isXmlHttpRequest()) {
			$jsonObject = array();
			$jsonObject['success'] = true;
			echo json_encode($jsonObject);
			return;
		}

		$this->loadLayout();

		$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');

		$this->getLayout()->getBlock('content')->append(
			$this->getLayout()->createBlock('unzercw/dummy')
		);

		$this->renderLayout();
	}

	private function getActivPaymentMethods($code)
	{
		$payments = Mage::getSingleton('payment/config')->getActiveMethods();
		foreach ($payments as $paymentCode => $paymentModel) {
			if($code == $paymentCode && $paymentModel instanceof Customweb_UnzerCw_Model_Method){
				return $paymentModel;
			}
		}
		return null;
	}

	public function getVisibleFieldsAction()
	{
		$payment = $this->getActivPaymentMethods($_REQUEST['payment_method']);
		if($payment != null){
			$html = $payment->generateVisibleFormFields($_REQUEST);
			$javascript = $payment->generateFormJavaScript($_REQUEST);
		}
		else{
			$this->getHelper()->log("UnzerCw : ProcessController::getVisibleFieldsAction() Could not find payment method '" . $_REQUEST['payment_method']);
			$html = Mage::helper("UnzerCw")->__("Technical issue: This payment methods is not available at the moment.");
		}

		$result = array(
			'html' => $html,
			'js' => $javascript
		);
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}

	public function failAction()
	{
		$transaction = $this->getTransaction();
		$transaction->getOrder()->getPayment()->getMethodInstance()->fail($transaction, $_REQUEST);

		$redirectUrl = $this->getHelper()->getFailUrl($transaction);

		session_write_close();

		header_remove('Set-Cookie');
		header('Location: ' . $redirectUrl);
		die();
	}

	public function ppRedirectAction()
	{
		try{
			$transaction = $this->getTransactionFromRequest();
			$transaction->getOrder()->getPayment()->getMethodInstance()->redirectToPaymentPage($transaction, $_REQUEST);
		}
		catch(Exception $e){
			$this->loadLayout();
			$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
			$this->getLayout()->getBlock('content')->append(
					$this->getLayout()->createBlock('unzercw/expired')
					);
			$this->renderLayout();
		}
	}

	public function authorizeAction()
	{
		$transaction = $this->getTransactionFromSession();
		$response = $transaction->getOrder()->getPayment()->getMethodInstance()->processServerAuthorization($transaction, $_REQUEST);
		$transaction->save();
		$wrapper = new Customweb_Core_Http_Response($response);
		$wrapper->send();
		die();
	}

	public function successAction()
	{
		$transaction = $this->getTransaction();
		$redirectUrl = $this->getHelper()->waitForNotification($transaction);
		header_remove('Set-Cookie');
		header('Location: ' . $redirectUrl);
		exit;
	}

	public function motoFailAction()
	{
		$transaction = $this->getTransaction();
		header_remove('Set-Cookie');
		header('Location: ' . $transaction->getTransactionObject()->getTransactionContext()->getRealBackendFailedUrl() . '?cstrxid=' . $_REQUEST['cstrxid']);
		exit;
	}

	public function motoSuccessAction()
	{
		$transaction = $this->getTransaction();
		header_remove('Set-Cookie');
		header('Location: ' . $transaction->getTransactionObject()->getTransactionContext()->getRealBackendSuccessUrl() . '?cstrxid=' . $_REQUEST['cstrxid']);
		exit;
	}

	public function motoCancelAction()
	{
		$transaction = $this->getTransaction();
		header_remove('Set-Cookie');
		header('Location: ' . $transaction->getTransactionObject()->getTransactionContext()->getRealBackendCancelUrl() . '?cstrxid=' . $_REQUEST['cstrxid']);
		exit;
	}

	public function preloadOnepageAction()
	{
		$layout = Mage::getModel('core/layout');
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_paymentmethod');
		$layout->generateXml();
		$layout->generateBlocks();
		$paymentMethodsHtml = $layout->getOutput();

		$layout = Mage::getModel('core/layout');
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_shippingmethod');
		$layout->generateXml();
		$layout->generateBlocks();
		$shippingMethodsHtml = $layout->getOutput();

		$result = array();
		$result['update_section'] = array(
			array(
				'name' => 'payment-method',
				'html' => $paymentMethodsHtml
			),
			array(
				'name' => 'shipping-method',
				'html' => $shippingMethodsHtml
			)
		);
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
}