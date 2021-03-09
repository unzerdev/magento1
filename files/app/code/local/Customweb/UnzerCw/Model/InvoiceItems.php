<?php

/**
 *  * You are allowed to use this API in your web application.
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
 * @category Customweb
 * @package Customweb_UnzerCw
 *
 */
class Customweb_UnzerCw_Model_InvoiceItems {

	public function getInvoiceItems($order, $storeId, $useBaseCurrency = false){
		$resultItems = array();
		$orderItems = $order->getItemsCollection();

		foreach ($orderItems as $item) {
			if ($item->getParentItemId() != null && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
				continue;
			}
			if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $item->getParentItemId() == null) {
				$product = Mage::getModel('catalog/product')->load($item->getProductId());
				if($product->getPriceType() != Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED){
					continue;
				}
			}

			$productItem = $this->getProductItem($item, $useBaseCurrency);
			if ($productItem) {
				$resultItems[] = $productItem;
			}

			$discountItem = $this->getDiscountItem($item, $useBaseCurrency);
			if ($discountItem) {
				$resultItems[] = $discountItem;
			}
		}

		$surchargeItem = $this->getFoomanSurchargeItem($order, $useBaseCurrency);
		if ($surchargeItem) {
			$resultItems[] = $surchargeItem;
		}

		$shippingItem = $this->getOrderShippingItem($order, $useBaseCurrency);
		if ($shippingItem) {
			$resultItems[] = $shippingItem;
		}

		$giftCardItem = $this->getMX2GiftCards($order, $useBaseCurrency);
		if($giftCardItem){
			$resultItems[] = $giftCardItem;
		}

		$resultItems = Customweb_Util_Invoice::ensureUniqueSku($resultItems);

		$event = new StdClass();
		$event->items = array();

		if ($order instanceof Mage_Sales_Model_Order) {
			Mage::dispatchEvent('customweb_collect_order_items',
					array(
						'order' => $order,
						'useBaseCurrency' => $useBaseCurrency,
						'result' => $event
					));
		}
		elseif ($order instanceof Mage_Sales_Model_Quote) {
			Mage::dispatchEvent('customweb_collect_quote_items',
					array(
						'quote' => $order,
						'useBaseCurrency' => $useBaseCurrency,
						'result' => $event
					));
		}

		foreach ($event->items as $item) {
			$resultItems[] = new Customweb_Payment_Authorization_DefaultInvoiceItem($item['sku'], $item['name'], $item['taxRate'],
					$item['amountIncludingTax'], $item['quantity'], $item['type']);
		}

		if ($useBaseCurrency) {
			$orderAmountInDecimals = $order->getBaseGrandTotal();
			$currency = Mage::app()->getStore($storeId)->getBaseCurrencyCode();
		}
		else {
			$orderAmountInDecimals = $order->getGrandTotal();
			$currency = Mage::app()->getStore($storeId)->getCurrentCurrencyCode();
		}

		return Customweb_Util_Invoice::cleanupLineItems($resultItems, $orderAmountInDecimals, $currency);
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $item
	 * @return Customweb_Payment_Authorization_IInvoiceItem
	 */
	public function getProductItem($item, $useBaseCurrency = false){
		if ($item instanceof Mage_Sales_Model_Order_Item || $item instanceof Mage_Sales_Model_Quote_Item) {
			$orderItem = $item;
		}
		else {
			$orderItem = $item->getOrderItem();
		}
		$sku = $item->getSku();
		$name = $item->getName();
		$taxRate = $orderItem->getTaxPercent();
		$quantity = $item->getQty();
		if (!$quantity) {
			$quantity = $orderItem->getQtyOrdered();
		}
		if ($useBaseCurrency) {
			$amountIncludingTax = $item->getBaseRowTotalInclTax();
		}
		else {
			$amountIncludingTax = $item->getRowTotalInclTax();
		}
		$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_PRODUCT;

		$invoiceItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $name, (double) $taxRate, (double) $amountIncludingTax,
				(double) $quantity, $type);
		return $invoiceItem;
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $item
	 * @return Customweb_Payment_Authorization_IInvoiceItem
	 */
	public function getDiscountItem($item, $useBaseCurrency = false){
		if ($item instanceof Mage_Sales_Model_Order_Item || $item instanceof Mage_Sales_Model_Quote_Item) {
			$orderItem = $item;
		}
		else {
			$orderItem = $item->getOrderItem();
		}

		$discountTax = 0;
		if (Mage::helper('tax')->applyTaxAfterDiscount()) {
			$discountTax = $orderItem->getTaxPercent();
		}

		if ($useBaseCurrency) {
			$discountAmount = $item->getBaseDiscountAmount();
		}
		else {
			$discountAmount = $item->getDiscountAmount();
		}
		if ($discountAmount != 0) {
			$sku = $item->getSku().'-discount';
			$name = Mage::helper('UnzerCw')->__("Discount");
			$taxRate = $discountTax;
			$quantity = $item->getQty();
			if (!$quantity) {
				$quantity = $orderItem->getQtyOrdered();
			}
			$amount = (double) abs($discountAmount);
			if(Mage::helper('tax')->priceIncludesTax()){
				$amountIncludingTax = $amount;
			}
			else{
				$amountIncludingTax = $amount * ($taxRate / 100 + 1);
			}
			$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT;

			$discountItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $name, (double) $taxRate, (double) $amountIncludingTax,
					(double) $quantity, $type);
			return $discountItem;
		}
		return null;
	}

	/**
	 *
	 * @param Mage_Sales_Model_Order_Invoice $item
	 * @return Customweb_Payment_Authorization_IInvoiceItem
	 */
	public function getShippingItem($invoice, $useBaseCurrency = false){
		$order = $invoice->getOrder();
		// Check if we need to add shipping 
		if ($invoice->getShippingAmount() > 0) {
			$sku = 'shipping';
			$shippingTaxRate = $this->getShippingTaxRate($order);
			if ($useBaseCurrency) {
				$shippingCostExclTax = $invoice->getBaseShippingAmount();
				$shippingTax = $invoice->getBaseShippingTaxAmount();
			}
			else {
				$shippingCostExclTax = $invoice->getShippingAmount();
				$shippingTax = $invoice->getShippingTaxAmount();
			}
			$shippingCostIncTax = $shippingCostExclTax + $shippingTax;
			$shippingName = $order->getShippingDescription();
			$quantity = 1;
			$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_SHIPPING;

			$shippingItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $shippingName, (double) $shippingTaxRate,
					(double) $shippingCostIncTax, (double) $quantity, $type);
			return $shippingItem;
		}
		return null;
	}

	public function getShippingTaxRate($order){
		$taxCalculationModel = Mage::getSingleton('tax/calculation');
		$store = $order->getStore();

		$classId = Mage::getModel('customer/group')->getTaxClassId($order->getCustomerGroupId());
		$request = $taxCalculationModel->getRateRequest($order->getShippingAddress(), $order->getBillingAddress(), $classId, $store);
		$shippingTaxClass = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, $store);

		$rate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass));
		return $rate;
	}

	public function getFoomanSurchargeItem($order, $useBaseCurrency = false){
		if ($useBaseCurrency) {
			$surchargeAmount = $order->getBaseFoomanSurchargeAmount();
			if ($order->getBaseFoomanSurchargeTaxAmount() > 0) {
				$surchargeAmount += $order->getBaseFoomanSurchargeTaxAmount();
			}
		}
		else {
			$surchargeAmount = $order->getFoomanSurchargeAmount();
			if ($order->getFoomanSurchargeTaxAmount() > 0) {
				$surchargeAmount += $order->getFoomanSurchargeTaxAmount();
			}
		}
		if ($surchargeAmount != 0) {
			$sku = 'surcharge';
			$surchargeName = $order->getFoomanSurchargeDescription();
			$surchargeTaxRate = 0;
			$storeId = $order->getStoreId();
			$quantity = 1;
			$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_FEE;

			$surchargeTaxClass = Mage::getStoreConfig('tax/classes/surcharge_tax_class', $storeId);
			if ($surchargeTaxClass) {
				$taxCalculationModel = Mage::getSingleton('tax/calculation');
				$classId = Mage::getModel('customer/group')->getTaxClassId($order->getCustomerGroupId());
				$request = $taxCalculationModel->getRateRequest($order->getShippingAddress(), $order->getBillingAddress(), $classId, $storeId);
				$request->setStore($order->getStore());
				if ($rate = $taxCalculationModel->getRate($request->setProductClassId($surchargeTaxClass))) {
					$surchargeTaxRate = $rate;
				}
			}

			$surchargeItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $surchargeName, (double) $surchargeTaxRate,
					(double) $surchargeAmount, (double) $quantity, $type);
			return $surchargeItem;
		}
		return null;
	}

	public function getMX2GiftCards($order,  $useBaseCurrency = false){
		if(!Mage::helper('core')->isModuleEnabled('MX2_Giftcard')){
			return null;
		}
		if ($useBaseCurrency) {
			$giftCardAmount = $order->getBaseGiftCardsAmount();
		}
		else {
			$giftCardAmount = $order->getGiftCardsAmount();
		}
		if ($giftCardAmount != 0) {
			$sku = 'giftcard';
			$name = Mage::helper('UnzerCw')->__("Giftcard");
			$taxRate = 0;
			$quantity = 1;
			$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT;



			$giftCardItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $name, (double) $taxRate,
					(double) $giftCardAmount, (double) $quantity, $type);
			return $giftCardItem;
		}
		return null;
	}

	public function getAdjustmentItem(array $items, $orderAmount, $currency){
		$totalAmount = 0;
		foreach ($items as $item) {
			if ($item->getType() == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT) {
				$totalAmount -= $item->getAmountIncludingTax();
			}
			else {
				$totalAmount += $item->getAmountIncludingTax();
			}
		}

		if (Customweb_Util_Currency::compareAmount($totalAmount, $orderAmount, $currency) > 0) {
			return new Customweb_Payment_Authorization_DefaultInvoiceItem('adjustment_disount', 'Adjustment Discount', 0,
					(double) ($totalAmount - $orderAmount), 1, Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT);
		}
		elseif (Customweb_Util_Currency::compareAmount($totalAmount, $orderAmount, $currency) < 0) {
			return new Customweb_Payment_Authorization_DefaultInvoiceItem('adjustment_fee', 'Adjustment Fee', 0, (double) ($orderAmount - $totalAmount),
					1, Customweb_Payment_Authorization_IInvoiceItem::TYPE_FEE);
		}
		return null;
	}

	/**
	 *
	 * @return Customweb_Payment_Authorization_IInvoiceItem
	 */
	public function getOrderShippingItem($order, $useBaseCurrency = false){
		if ($order instanceof Mage_Sales_Model_Quote) {
			$shippingInfo = $order->getShippingAddress();
		}
		else {
			$shippingInfo = $order;
		}

		// Check if we need to add shipping 
		if ($shippingInfo->getShippingAmount() > 0) {
			$sku = 'shipping';
			$shippingTaxRate = $this->getShippingTaxRate($order);
			if ($useBaseCurrency) {
				$shippingCostExclTax = $shippingInfo->getBaseShippingAmount();
				$shippingTax = $shippingInfo->getBaseShippingTaxAmount();
			}
			else {
				$shippingCostExclTax = $shippingInfo->getShippingAmount();
				$shippingTax = $shippingInfo->getShippingTaxAmount();
			}
			$shippingCostIncTax = $shippingCostExclTax + $shippingTax;
			$shippingName = $shippingInfo->getShippingDescription();
			$quantity = 1;
			$type = Customweb_Payment_Authorization_IInvoiceItem::TYPE_SHIPPING;

			$shippingItem = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $shippingName, (double) $shippingTaxRate,
					(double) $shippingCostIncTax, (double) $quantity, $type);
			return $shippingItem;
		}
		return null;
	}
}