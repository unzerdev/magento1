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
class Customweb_UnzerCw_Model_TranslationResolver implements Customweb_I18n_ITranslationResolver {

	public function getTranslation($string){
		$string = $this->cleanTranslationString($string);
		$result = Mage::helper('UnzerCw')->__($string);
		if ($result == $string) {
			return null;
		} else {
			return $result;
		}
	}

	private function cleanTranslationString($string){
		$string = str_replace("\\\"", "\"\"", $string);
		$string = str_replace("\n", " ", $string);
		$string = str_replace("\t", " ", $string);
		$string = preg_replace("/[ ]+/", " ", $string);
		return $string;
	}
}