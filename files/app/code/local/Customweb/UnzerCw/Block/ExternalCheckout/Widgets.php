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

class Customweb_UnzerCw_Block_ExternalCheckout_Widgets extends Mage_Core_Block_Template
{
	private static $_widgets = null;

    /**
     * Whether the block should be eventually rendered
     *
     * @var bool
     */
    private $_shouldRender = true;

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        $result = parent::_beforeToHtml();
        
        $quote = $this->getQuote();

        // validate minimum quote amount and validate quote for zero grandtotal
        if (null !== $quote && (!$quote->validateMinimumAmount()
            || (!$quote->getGrandTotal() && !$quote->hasNominalItems()))) {
            $this->_shouldRender = false;
            return $result;
        }

        return $result;
    }

    /**
     * Render the block if needed
     *
     * @return string
     */
    protected function _toHtml()
    {
    	$widgets = $this->getWidgets();
        if (!$this->_shouldRender || empty($widgets)) {
            return '';
        }
        return parent::_toHtml();
    }
    
    public function getQuote() {
    	return Mage::getSingleton('checkout/session')->getQuote();
    }
    
    public function getWidgets()
    {
    	if (self::$_widgets == null) {
			self::$_widgets = Mage::getModel('unzercw/externalCheckoutWidgets')->getAllWidgets();
    	}
    	return self::$_widgets;
    }
}
