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

if (version_compare(Mage::getVersion(), '1.5', '>='))
{
	try {
		$data = array();
		$data[] = array(
				'status'    => 'canceled_unzercw',
				'label'     => 'Canceled Unzer'
		);
		$data[] = array(
				'status'    => 'pending_unzercw',
				'label'     => 'Pending Unzer'
		);

		$statusTable        = $installer->getTable('sales/order_status');
		$installer->getConnection()->insertArray($statusTable, array('status', 'label'), $data);


		$data = array();
		$data[] = array(
				'status'    => 'canceled_unzercw',
				'state'     => Customweb_UnzerCw_Model_Method::STATE_CANCELLED,
				'is_default'=> 0
		);

		$data[] = array(
				'status'    => 'pending_unzercw',
				'state'     => Customweb_UnzerCw_Model_Method::STATE_PENDING,
				'is_default'=> 0
		);

		$statusStateTable   = $installer->getTable('sales/order_status_state');
		$installer->getConnection()->insertArray(
				$statusStateTable,
				array('status', 'state', 'is_default'),
				$data
		);

	}
	catch(Exception $e) {}
}

$installer->endSetup();