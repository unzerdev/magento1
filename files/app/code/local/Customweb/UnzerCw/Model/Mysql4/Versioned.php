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

abstract class Customweb_UnzerCw_Model_Mysql4_Versioned extends Mage_Core_Model_Mysql4_Abstract
{
	/**
	 * Main table version number field name
	 *
	 * @var string
	 */
	protected $_versionNumberFieldName = 'version_number';

	/**
	 * Get version number key field name
	 *
	 * @return string
	 */
	public function getVersionNumberFieldName()
	{
		if (empty($this->_versionNumberFieldName)) {
			Mage::throwException(Mage::helper('core')->__('Empty version number field name'));
		}
		return $this->_versionNumberFieldName;
	}

	public function save(Mage_Core_Model_Abstract $object)
	{
		if ($object->isDeleted()) {
			return $this->delete($object);
		}

		$this->_serializeFields($object);
		$this->_beforeSave($object);
		$this->_checkUnique($object);
		if (!is_null($object->getId()) && (!$this->_useIsObjectNew || !$object->isObjectNew())) {
			$condition = $this->_getWriteAdapter()->quoteInto($this->getIdFieldName().'=?', $object->getId());

			$nextVersion = null;
			$currentVersion = $object->getData($this->getVersionNumberFieldName());
			if($currentVersion !== null){
				$nextVersion = $currentVersion + 1;
				$condition .= $this->_getWriteAdapter()->quoteInto(' AND '.$this->getVersionNumberFieldName().'=?', $currentVersion);
			} else {
				$nextVersion = 1;
			}

			/**
			 * Not auto increment primary key support
			 */
			if ($this->_isPkAutoIncrement) {
				$data = $this->_prepareDataForSave($object);
				$data[$this->getVersionNumberFieldName()] = $nextVersion;
				unset($data[$this->getIdFieldName()]);
				$rowAffected = $this->_getWriteAdapter()->update($this->getMainTable(), $data, $condition);
				if($rowAffected == 0) {
					throw new Customweb_UnzerCw_Model_OptimisticLockingException(get_class($object), $object->getId());
				}
				$object->setVersionNumber($nextVersion);
			} else {
                $select = $this->_getWriteAdapter()->select()
                    ->from($this->getMainTable(), array($this->getIdFieldName()))
                    ->where($condition);
                if ($this->_getWriteAdapter()->fetchOne($select) !== false) {
                    $data = $this->_prepareDataForSave($object);
                    unset($data[$this->getIdFieldName()]);
                    $data[$this->getVersionNumberFieldName()] = $nextVersion;
                    if (!empty($data)) {
                        $rowAffected = $this->_getWriteAdapter()->update($this->getMainTable(), $data, $condition);
                        if($rowAffected == 0) {
                        	throw new Customweb_UnzerCw_Model_OptimisticLockingException(get_class($object), $object->getId());
                        }
                        $object->setData($this->getVersionNumberFieldName(), $nextVersion);
                    }
                } else {
                	$object->setData($this->getVersionNumberFieldName(), 1);
                    $this->_getWriteAdapter()->insert($this->getMainTable(), $this->_prepareDataForSave($object));
                }
            }
		} else {
			$object->setData($this->getVersionNumberFieldName(), 1);
			$bind = $this->_prepareDataForSave($object);
			if ($this->_isPkAutoIncrement) {
				unset($bind[$this->getIdFieldName()]);
			}
			$this->_getWriteAdapter()->insert($this->getMainTable(), $bind);

			$object->setId($this->_getWriteAdapter()->lastInsertId($this->getMainTable()));

			if ($this->_useIsObjectNew) {
				$object->isObjectNew(false);
			}
		}

		$this->unserializeFields($object);
		$this->_afterSave($object);

		return $this;
	}
}
