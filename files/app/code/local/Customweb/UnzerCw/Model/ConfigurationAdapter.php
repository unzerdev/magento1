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
class Customweb_UnzerCw_Model_ConfigurationAdapter implements Customweb_Payment_IConfigurationAdapter
{
	private static $storeId = null;

	private static $storeHierarchy = array();

	/**
	 * Config values are dependent on the store, so whenever an order
	 * is available update store information.
	 * @param Mage_Sales_Model_Order $order
	 */
	public static function setStore($order)
	{
		if ($order != null && $order->getStoreId()) {
			self::$storeId = $order->getStoreId();
		}
	}

	/**
	 * Config values are dependent on the store, so whenever a store id
	 * is available update store information.
	 * @param int $storeId
	 */
	public static function setStoreId($storeId)
	{
		self::$storeId = $storeId;
	}

	/**
	 * @return int
	 */
	public static function getStoreId()
	{
		if (self::$storeId == null) {
			self::setStoreId(Mage::app()->getStore()->getId());
		}
		return self::$storeId;
	}

	public function getConfigurationValue($key, $language = null)
	{
		$multiSelectKeys = array(
		);
		$fileKeys = array(
		);
		$rs = Mage::getStoreConfig('unzercw/general/' . $key, self::$storeId);

		if (isset($multiSelectKeys[$key])) {
			return empty($rs) ? array() : explode(',', $rs);
		} elseif (isset($fileKeys[$key])) {
			$defaultValue = $fileKeys[$key];
			if (empty($rs) || $defaultValue == $rs) {
				return Mage::helper('UnzerCw')->getAssetResolver()->resolveAssetStream($defaultValue);
			} else {
				return new Customweb_Core_Stream_Input_File(Mage::getBaseDir('media') . '/unzercw/setting/default/' . $key . '/' . $rs);
			}
		} elseif (in_array($key, array(
			
		))) {
			return Mage::helper('core')->decrypt($rs);
		} else {
			return $rs;
		}
	}

	public function existsConfiguration($key, $language = null)
	{
		return null != Mage::getStoreConfig('unzercw/general/' . $key, self::$storeId);
	}

	public function getDefaultTemplateUrl()
	{
		$url = Mage::getUrl('UnzerCw/checkout/template', array(
			'_secure' => true,
			'_store' => self::$storeId,
			'_store_to_url' => true
		));
		return $url;
	}

	public function getLanguages($currentStore = false) {
		if ($currentStore) {
			return null;
		}

		$languages = array();
		$stores = Mage::app()->getStores();
		foreach ($stores as $store) {
			$locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store->getId());
			if (!$locale) {
				$locale = Mage_Core_Model_Locale::DEFAULT_LOCALE;
			}
			$languages[$locale] = new Customweb_Core_Language($locale);
		}
		return $languages;
	}

	public static function setStoreHierarchy($storeHierarchy) {
		self::$storeHierarchy = $storeHierarchy;
	}

	public function getStoreHierarchy() {
		if (self::$storeHierarchy !== null && empty(self::$storeHierarchy)) {
			if (self::$storeId == null) {
				$store = Mage::app()->getStore();
			} else {
				$store = Mage::getModel('core/store')->load(self::$storeId);
			}
			$website = $store->getWebsite();

			self::$storeHierarchy = array(
				'website_'.$website->getId() => $website->getName(),
				'store_'.$store->getId() => $store->getName()
			);
		}
		return self::$storeHierarchy;
	}

	public function useDefaultValue(Customweb_Form_IElement $element, array $formData) {
		$controlName = implode('_', $element->getControl()->getControlNameAsArray());
		return (isset($formData['default'][$controlName]) && $formData['default'][$controlName] == 'default');
	}

	public function getOrderStatus() {
		$orderStatus = array();
		$statusCollection = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
		foreach ($statusCollection as $status) {
			$orderStatus[$status['status']] = Mage::helper('sales')->__($status['label']);
		}
		return $orderStatus;
	}

}
