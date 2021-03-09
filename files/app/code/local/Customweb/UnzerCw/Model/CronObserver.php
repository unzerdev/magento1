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

class Customweb_UnzerCw_Model_CronObserver
{
	public function executeCron()
	{
		try {
			$this->cancelOrders();

			$packages = array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		);
			$packages[] = 'Customweb_UnzerCw_Model';
			$packages[] = 'Customweb_Payment_Update_ScheduledProcessor';
			$cronProcessor = new Customweb_Cron_Processor(Mage::helper('UnzerCw')->createContainer(), $packages);
			$cronProcessor->run();
		} catch (Exception $e) {
			Mage::helper('UnzerCw')->logException($e);
		}
	}

	private function cancelOrders()
	{
		$orders = Mage::getResourceModel('sales/order_collection')
			->addAttributeToSelect('*')
			->addAttributeToFilter('status', Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_PENDING)
			->load();
		if (count($orders) > 0) {
			$timeout = (int) Mage::getConfig()->getNode('default/unzercw/general/cancel_pending_orders');
			if (empty($timeout)) {
				$timeout = 7200;
			}
			$absoluteTime = time() - $timeout;
			foreach ($orders as $order) {
				try {
					$orderUpdated = strtotime($order->getUpdatedAt());
					if ($absoluteTime >= $orderUpdated) {
						$order->cancel();
						$order->setIsActive(0);
						$order->addStatusToHistory(Customweb_UnzerCw_Model_Method::UNZERCW_STATUS_CANCELED, Mage::helper('UnzerCw')->__('Order cancelled, because the customer was too long in the payment process of Unzer.'));
						$order->save();
						$order->getPayment()->getMethodInstance()->restoreUsedCoupons($order);

					}
				} catch (Exception $e) {}
			}
		}
	}
}