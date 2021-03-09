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

/* @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

$globalSettings = array_map(function($path){
	return "'unzercw/general/{$path}'";
}, array(
	
));
if (!empty($globalSettings)) {
	$globalRows = $installer->_conn->fetchAll(
		"select * from {$installer->getTable('core_config_data')} where
			path in (" . implode(',', $globalSettings) . ")"
	);
} else {
	$globalRows = array();
}

$paymentMethodSettings = array_map(function($path){
	return "'payment/{$path}'";
}, array(
	
));
	if (!empty($paymentMethodSettings)) {
	$paymentMethodRows = $installer->_conn->fetchAll(
		"select * from {$installer->getTable('core_config_data')} where
			path in (" . implode(',', $paymentMethodSettings) . ")"
	);
} else {
	$paymentMethodRows = array();
}

$rows = array_merge($globalRows, $paymentMethodRows);

/* @var Mage_Core_Helper_Data $helper */
$helper = Mage::helper('core');
foreach ($rows as $row) {
	if (!empty($row['value'])) {
		$row['value'] = $helper->encrypt($row['value']);
		$installer->_conn->update($installer->getTable('core_config_data'), $row, 'config_id=' . $row['config_id']);
	}
}

$installer->endSetup();