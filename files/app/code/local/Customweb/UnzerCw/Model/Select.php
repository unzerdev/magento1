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

class Customweb_UnzerCw_Model_Select extends Customweb_Form_Control_Select
{
	private $onChangeEvent = null;

	/**
	 * Constructor
	 *
	 * @param string $controlName Control Name
	 * @param array $options A key / value map. Where the key is the submitted value and the value is the label of the option.
	 * @param string $defaultValue The preselected option
	 */
	public function __construct($controlName, $options, $defaultValue = '', $onChangeEvent = 'javascript:void(0)')
	{
		parent::__construct($controlName, $options, $defaultValue);
		$this->onChangeEvent = $onChangeEvent;
	}

	/**
	 * (non-PHPdoc)
	 * @see Customweb_Form_Control_Abstract::renderContent()
	 */
	public function renderContent(Customweb_Form_IRenderer $renderer)
	{
		$result = '<select name="' . $this->getControlName() . '" id="' . $this->getControlId() . '" onchange="' . $this->onChangeEvent . '" class="' . $this->getCssClass() . '">';
		foreach ($this->getOptions() as $key => $label) {
			$result .= '<option value="' . $key . '"';
			if ($this->getDefaultValue() == $key) {
				$result .= ' selected="selected" ';
			}
			$result .= '>' . $label . '</option>';
		}
		$result .= '</select>';
		return $result;
	}

}
