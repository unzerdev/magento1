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

/**
 *
 * @author Simon Schurter
 */
class Customweb_UnzerCw_Model_TransactionCleanUpCron {
	
	/**
	 * @Cron()
	 */
	public function cleanUp() {
		$maxEndtime = Customweb_Core_Util_System::getScriptExecutionEndTime() - 4;
		
		// Remove all failed transactions after 2 months.
		$failedCollection = Mage::getModel('unzercw/transaction')->getCollection()
			->addFieldToFilter('updated_on', array('to' => new Zend_Db_Expr('NOW() - INTERVAL 2 MONTH')))
			->addFieldToFilter('authorization_status', array('eq' => Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_FAILED))
			->setCurPage(1)
			->setPageSize(40);
		foreach ($failedCollection as $entity) {
			if ($maxEndtime > time()) {
				$entity->delete();
			}
			else {
				break;
			}
		}
		//Remove all pending transaction 6 month after last update
		$pendingCollection = Mage::getModel('unzercw/transaction')->getCollection()
			->addFieldToFilter('updated_on', array('to' => new Zend_Db_Expr('NOW() - INTERVAL 6 MONTH')))
			->addFieldToFilter('authorization_status', array('eq' => Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_PENDING))
			->setCurPage(1)
			->setPageSize(40);
		foreach ($pendingCollection as $entity) {
			if ($maxEndtime > time()) {
				$entity->delete();
			}
			else {
				break;
			}
		}
		//Remove all transaction with no status after 1 month
		$noStatusCollection = Mage::getModel('unzercw/transaction')->getCollection()
			->addFieldToFilter('updated_on', array('to' => new Zend_Db_Expr('NOW() - INTERVAL 1 MONTH')))
			->addFieldToFilter('authorization_status', array('eq' => ''))
			->setCurPage(1)
			->setPageSize(40);
		foreach ($noStatusCollection as $entity) {
			if ($maxEndtime > time()) {
				$entity->delete();
			}
			else {
				break;
			}
		}
	}
	
}