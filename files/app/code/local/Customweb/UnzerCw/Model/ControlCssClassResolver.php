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

class Customweb_UnzerCw_Model_ControlCssClassResolver implements Customweb_Form_IControlCssClassResolver
{
	public function resolveClass(Customweb_Form_Control_IControl $control, Customweb_Form_IElement $element)
	{
		if ($control instanceof Customweb_Form_Control_TextInput
			|| $control instanceof Customweb_Form_Control_Password) {
			$css = "input-text";
		} elseif ($control instanceof Customweb_Form_Control_Textarea) {
			$css = "textarea";
		} elseif ($control instanceof Customweb_Form_Control_Select) {
			$css = "select";
		} elseif ($control instanceof Customweb_Form_Control_MultiSelect) {
			$css = "select multiselect";
		} elseif ($control instanceof Customweb_Form_Control_SingleCheckbox
			|| $control instanceof Customweb_Form_Control_MultiCheckbox) {
			$css = "checkbox";
		} elseif ($control instanceof Customweb_Form_Control_Radio) {
			$css = "radio";
		} else {
			$css = "";
		}

		return $css;
	}
}