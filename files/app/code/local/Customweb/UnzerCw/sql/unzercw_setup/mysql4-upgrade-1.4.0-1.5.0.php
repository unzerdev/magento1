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

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
	$installer->getTable('unzercw_transaction'),
	'version_number',
	'int NOT NULL DEFAULT 1'
);

$installer->getConnection()->addColumn(
	$installer->getTable('unzercw_transaction'),
	'live_transaction',
	'tinyint(1) DEFAULT 0'
);

$installer->getConnection()->addColumn(
	$installer->getTable('unzercw_customer_context'),
	'version_number',
	'int NOT NULL DEFAULT 1'
);

$installer->getConnection()->addColumn(
	$installer->getTable('unzercw_external_checkout_context'),
	'version_number',
	'int NOT NULL DEFAULT 1'
);

$installer->endSetup();