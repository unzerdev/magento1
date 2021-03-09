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

class Customweb_UnzerCw_Model_MotoObserver
{
	/**
	 * This event is called when the MoTo order is saved. The event is fired after the controller action 'save' is executed.
	 * The event is used to redirect the customer to the payment interface.
	 */
	public function postOrderSave(Varien_Event_Observer $observer)
	{
		$controller = $observer->getControllerAction();
		$message = Mage::getSingleton('adminhtml/session')->getMessages()->getLastAddedMessage();
		if ($message == null) {
			return;
		}
		$messageType = $message->getType();
		$redirectLocation = $this->getRedirectionUrl($controller);

		Mage::getSingleton('adminhtml/session')->setCustomwebRedirectUrl($redirectLocation);

		$orderId = $this->extractOrderId($redirectLocation);

		if ($orderId) {
			$order = Mage::getModel('sales/order')->load($orderId);
			$payment = $order->getPayment();
			if (strpos($payment->getMethodInstance()
				->getCode(), 'unzercw') !== false) {
				Mage::helper('UnzerCw')->log("Codes match.");
				if ($messageType == 'success') {
					$order->addStatusToHistory(Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_PENDING, Mage::helper('UnzerCw')->__('Payment is pending at UnzerCw'));
					$order->save();
					Mage::helper('UnzerCw')->log("Redirect to Moto authorization");

					Mage::getSingleton('adminhtml/session')->setIsUrlNotice($controller->getFlag('', Mage_Adminhtml_Controller_Action::FLAG_IS_URLS_CHECKED));
					$controller->getResponse()
						->setRedirect(Mage::helper('adminhtml')->getUrl('*/motounzercw/process', array(
								'order_id' => $order->getId()
							)));
				}
			}
		}
	}

	/**
	 * This event is fired during the processing of the order. We prevent here the sending of
	 * the mail and set the order status.
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function beforeOrderSave(Varien_Event_Observer $observer)
	{
		try {
			// Prevent the sending of the e-mail
			if (isset($_POST['payment']['method'])) {
				$paymentCode = $_POST['payment']['method'];
				if (strpos($paymentCode, 'unzercw') !== false) {
					Mage::register('cw_is_moto', true);
					$_POST['order']['send_confirmation'] = '0';
				}
			}
		} catch (Exception $e) {}
	}

	protected function extractOrderId($redirectionUrl)
	{
		preg_match("/order_id\/(\d*)\//i", $redirectionUrl, $match);
		return $match[1];
	}

	protected function getRedirectionUrl(Mage_Core_Controller_Varien_Action $controller)
	{
		$headers = $controller->getResponse()
			->getHeaders();
		$redirectLocation = "";

		foreach ($headers as $item) {
			if ($item['name'] == 'Location') {
				$redirectLocation = $item['value'];
			}
		}
		return $redirectLocation;
	}
}
