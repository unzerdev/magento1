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
class Customweb_UnzerCw_Model_ExternalCheckoutCron {
	
	/**
	 * @Cron()
	 */
	public function cleanUp() {
		$maxEndtime = Customweb_Core_Util_System::getScriptExecutionEndTime() - 4;
		
		// Remove all contexts which are not changed in the last 2 days and the state is not completed.
		$collection = Mage::getModel('unzercw/externalCheckoutContext')->getCollection()
			->addFieldToFilter('updated_on', array('to' => new Zend_Db_Expr('NOW() - INTERVAL 2 DAY')))
			->addFieldToFilter('state', array('neq' => 'completed'))
			->setCurPage(1)
			->setPageSize(40);
		foreach ($collection as $entity) {
			if ($maxEndtime > time()) {
				$entity->delete();
			}
			else {
				break;
			}
		}
	}
	
}