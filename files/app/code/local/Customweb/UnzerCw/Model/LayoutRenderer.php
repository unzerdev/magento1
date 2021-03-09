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

/**
 * @author Simon Schurter
 * @Bean
 */
class Customweb_UnzerCw_Model_LayoutRenderer extends Customweb_Mvc_Layout_Renderer
{
	public function render(Customweb_Mvc_Layout_IRenderContext $context) {
		$cache = Mage::app()->getCacheInstance();
		$cache->banUse('layout');
		
		$layout = Mage::getSingleton('core/layout');
		$layout->getUpdate()->load(array('default'));
		$layout->generateXml();
		$layout->generateBlocks();
		$layout->getBlock('root')->setTemplate('page/1column.phtml');
		$layout->getBlock('head')->setTitle($context->getTitle());
		$layout->getBlock('head')->addCss('customweb/unzercw/updates.css');
		
		foreach ($context->getJavaScriptFiles() as $javascriptFile) {
			$layout->getBlock('head')->append($layout->createBlock('core/text')->setText('<script type="text/javascript" src="' . $javascriptFile . '"></script>'));
		}
		foreach ($context->getCssFiles() as $cssFile) {
			$layout->getBlock('head')->append($layout->createBlock('core/text')->setText('<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />'));
		}
		
		$mainContentBlock = $layout->createBlock('core/text')->setText($context->getMainContent());
		$layout->getBlock('content')->append($mainContentBlock);
		return $layout->getOutput();
	}
}