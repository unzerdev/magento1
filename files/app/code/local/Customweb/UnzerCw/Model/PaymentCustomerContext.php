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

class Customweb_UnzerCw_Model_PaymentCustomerContext implements Customweb_Payment_Authorization_IPaymentCustomerContext
{
	private static $cache = array();

	private $context = null;
	private $customerId = null;

	public static function getByCustomerId($customerId) {
		if (!isset(self::$cache[$customerId])) {
			self::$cache[$customerId] = new self($customerId);
		}
		return self::$cache[$customerId];
	}

	private function __construct($customerId)
	{
		$this->customerId = $customerId;
	}

	public function getMap()
	{
		return $this->getContext()
			->getMap();
	}

	public function updateMap(array $update)
	{
		return $this->getContext()
			->updateMap($update);
	}

	public function persist()
	{
		if ($this->customerId > 0) {
			$lastException = null;
			for ($i = 0; $i < 10; $i++) {
				try {
					$loadedContextMap = $this->getContextMapFromDatabase();
					$updatedMap = array();
					if ($loadedContextMap !== null) {
						$updatedMap = $this->getContext()
							->applyUpdatesOnMapAndReset($loadedContextMap);
					} else {
						$updatedMap = $this->getContext()
							->getMap();
					}
					$this->updateContextMapInDatabase($updatedMap);
					return $this;
				} catch (Customweb_UnzerCw_Model_OptimisticLockingException $e) {
					// Try again.
					$lastException = $e;
				}
			}
			throw $lastException;
		}
	}

	public function __sleep()
	{
		$this->persist();
		return array(
			'customerId'
		);
	}

	public function __wakeup()
	{
	}

	/**
	 * @return Customweb_Payment_Authorization_DefaultPaymentCustomerContext
	 */
	protected function getContext()
	{
		if ($this->context === null) {
			if ($this->customerId > 0) {
				$map = $this->getContextMapFromDatabase();
				if ($map === null) {
					$map = array();
				}
				$this->context = new Customweb_Payment_Authorization_DefaultPaymentCustomerContext($map);
			} else {
				$this->context = new Customweb_Payment_Authorization_DefaultPaymentCustomerContext(array());
			}
		}
		return $this->context;
	}

	/**
	 * @return array
	 */
	protected function getContextMapFromDatabase()
	{
		$customerContext = Mage::getModel('unzercw/customercontext')->load($this->customerId);
		if ($customerContext->getId() && $customerContext->getContext()) {
			$result = Mage::helper('UnzerCw')->unserialize($customerContext->getContext());

			if ($result instanceof Customweb_Payment_Authorization_IPaymentCustomerContext) {
				return $result->getMap();
			} else {
				return $result;
			}
		} else {
			return null;
		}
	}

	protected function updateContextMapInDatabase(array $map)
	{
		$customerContext = Mage::getModel('unzercw/customercontext')->load($this->customerId);
		if (!$customerContext->getId()) {
			$customerContext = Mage::getModel('unzercw/customercontext');
			$customerContext->setId($this->customerId);
		}

		$customerContext->setContext(Mage::helper('UnzerCw')->serialize($map));
		$customerContext->save();
	}
}
