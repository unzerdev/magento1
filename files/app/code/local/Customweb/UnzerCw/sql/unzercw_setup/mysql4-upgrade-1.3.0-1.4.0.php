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

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('unzercw_external_checkout_context')} (
	context_id bigint(20) NOT NULL AUTO_INCREMENT,
	state varchar (255) ,
	failed_error_message varchar (255) ,
	cart_url varchar (255) ,
	default_checkout_url varchar (255) ,
	invoice_items LONGTEXT ,
	order_amount_in_decimals decimal (20,5) ,
	currency_code varchar (255) ,
	language_code varchar (255) ,
	customer_email_address varchar (255) ,
	customer_id varchar (255) ,
	transaction_id int (11) ,
	shipping_address LONGTEXT ,
	billing_address LONGTEXT ,
	shipping_method_name varchar (255) ,
	payment_method_machine_name varchar (255) ,
	provider_data LONGTEXT ,
	created_on datetime ,
	updated_on datetime ,
	security_token varchar (255) ,
	security_token_expiry_date datetime NULL DEFAULT NULL,
	authentication_success_url varchar(512) NULL DEFAULT NULL,
	authentication_email_address varchar (255) NULL DEFAULT NULL,
	quote_id int (10) NULL DEFAULT NULL,
	register_method varchar (255) NULL DEFAULT NULL,
	PRIMARY KEY (context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();