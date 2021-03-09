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

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('unzercw_transaction')} (
	`transaction_id` int NOT NULL AUTO_INCREMENT,
	`transaction_external_id` varchar(255) DEFAULT NULL,
	`order_id` int DEFAULT NULL,
	`order_payment_id` int DEFAULT NULL,
	`alias_for_display` varchar(255) DEFAULT NULL,
	`alias_active` tinyint(1) DEFAULT 0,
	`payment_method` varchar(255) DEFAULT NULL,
	`transaction_object` longtext,
	`authorization_type` varchar(255) DEFAULT NULL,
	`customer_id` int DEFAULT NULL,
	`updated_on` datetime DEFAULT NULL,
	`created_on` datetime DEFAULT NULL,
	`payment_id` varchar(255) DEFAULT NULL,
	`updatable` tinyint(1) DEFAULT 0,
	`execute_update_on` datetime DEFAULT NULL,
	`authorization_amount` decimal(20,5) DEFAULT NULL,
	`authorization_status` varchar(255) DEFAULT NULL,
	`paid` tinyint(1) DEFAULT 1,
	`currency` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`transaction_id`),
	KEY `paymentId` (`payment_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();