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

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('unzercw_storage')} (
	`key_id` bigint(20) NOT NULL AUTO_INCREMENT,
	`key_space` varchar(165) DEFAULT NULL,
	`key_name` varchar(165) DEFAULT NULL,
	`key_value` longtext,
	UNIQUE KEY `keyName_keySpace` (`key_name`,`key_space`),
	PRIMARY KEY (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();