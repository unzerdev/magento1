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

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('unzercw_alias_data')} (
	`alias_id` INT NOT NULL AUTO_INCREMENT,
	`customer_id` INT NOT NULL ,
	`order_id` INT NOT NULL ,
	`alias_for_display` VARCHAR(50) NOT NULL ,
	`payment_method` VARCHAR(255) NOT NULL ,
	PRIMARY KEY ( `alias_id` )
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('unzercw_customer_context')} (
	`customer_id` INT NOT NULL,
	`context` TEXT ,
	PRIMARY KEY ( `customer_id` )
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();