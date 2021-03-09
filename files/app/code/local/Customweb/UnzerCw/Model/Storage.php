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
 *
 * @Bean
 */
class Customweb_UnzerCw_Model_Storage extends Mage_Core_Model_Abstract implements Customweb_Storage_IBackend
{
	protected $_eventPrefix = 'customweb_storage';
	protected $_eventObject = 'storage';
	
	protected function _construct()
	{
		$this->_init('unzercw/storage');
	}

	public function lock($space, $key, $type) {
		$this->_getResource()->beginTransaction();

		$entity = $this->loadByKeyAndSpace($space, $key);

		if ($entity == null || !$entity->getId()) {
			$this->write($space, $key, null);
			$entity = $this->loadByKeyAndSpace($space, $key);
			//throw new Exception("Before you can lock a key, you must create it.");
		}

		if ($type == self::EXCLUSIVE_LOCK) {
			// When we write the entity back to the database, we force that the
			// row is exclusivly locked. We need to write the whole entity (all fields) otherwise
			// we may lock only certain fields.
			$entity->save();
		}
	}

	public function unlock($space, $key) {
		$this->_getResource()->commit();
	}

	public function read($space, $key) {
		$entity = $this->loadByKeyAndSpace($space, $key);
		if ($entity != null && $entity->getId()) {
			return Customweb_Core_Util_Serialization::unserialize($entity->getKeyValue());
		} else {
			return null;
		}
	}

	public function write($space, $key, $value) {
		$entity = $this->loadByKeyAndSpace($space, $key);
		if ($entity == null) {
			$entity = new self();
		}
		$entity->setKeySpace($space)->setKeyName($key)->setKeyValue(Customweb_Core_Util_Serialization::serialize($value));
		$entity->save();
	}

	public function remove($space, $key) {
		$entity = $this->loadByKeyAndSpace($space, $key);
		if ($entity != null && $entity->getId()) {
			$entity->delete();
		}
	}

	public function loadByKeyAndSpace($space, $key){
		$collection = $this->getCollection()
			->addFieldToFilter('key_space', $space)
			->addFieldToFilter('key_name', $key);
		if ($collection->count() == 0) {
			return null;
		}
		return $collection->getFirstItem();
	}
}