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

/**
 * @author Simon Schurter
 * @Bean
 */
class Customweb_UnzerCw_Model_UpdateHandler implements Customweb_Payment_Update_IHandler
{
	public function getContainer() {
		return Mage::helper('UnzerCw')->createContainer();
	}

	public function log($message, $type) {
		if ($type == Customweb_Payment_Update_IHandler::LOG_TYPE_INFO) {
			$level = Zend_Log::INFO;
		} elseif ($type == Customweb_Payment_Update_IHandler::LOG_TYPE_ERROR) {
			$level = Zend_Log::ERR;
		} else {
			$level = null;
		}
		Mage::helper('UnzerCw')->log($message, $level);
	}

	public function getScheduledTransactionIds() {
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_read');
		$query = 'SELECT transaction_id FROM ' . $resource->getTableName('unzercw/transaction')
			. ' WHERE execute_update_on IS NOT NULL AND execute_update_on < NOW() LIMIT 0,' . (int)$this->getMaxNumberOfTransaction();
		$result = $readConnection->fetchAll($query);

		$transactionIds = array();
		foreach ($result as $entry) {
			$transactionIds[] = $entry['transaction_id'];
		}
		return $transactionIds;
	}

	/**
	 * Returns the maximal number of transaction, which should be loaded to process in one update iteration.
	 *
	 * @return number
	 */
	protected function getMaxNumberOfTransaction() {
		return 100;
	}
}