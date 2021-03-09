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

class Customweb_UnzerCw_Model_FormRenderer extends Customweb_Form_Renderer
{
	public function __construct()
	{
		$this->setControlCssClass('input-box');
		$this->setControlCssClassResolver(Mage::getModel('unzercw/controlCssClassResolver'));

		if (!function_exists('cw_resource_loader')) {
			function cw_resource_loader($relativeClassName, $resourceFile){
				$path = Mage::getBaseDir('lib') . '/' . str_replace('_', '/', $relativeClassName) . '/' . $resourceFile;
				if (file_exists($path)) {
					return file_get_contents($path);
				}
			}
			Customweb_Core_Util_Class::registerResourceResolver('cw_resource_loader');
		}
	}

	public function renderElementGroupPrefix(Customweb_Form_IElementGroup $elementGroup)
	{
		return '<div class="fieldset">';
	}
	
	public function renderElementGroupPostfix(Customweb_Form_IElementGroup $elementGroup)
	{
		return '</ul></div>';
	}

	public function renderElementGroupTitle(Customweb_Form_IElementGroup $elementGroup)
	{
		$output = '';
		$title = $elementGroup->getTitle();
		if (! empty($title)) {
			$cssClass = $this->getCssClassPrefix() . $this->getElementGroupTitleCssClass();
			$output .= '<h2 class="legend" class="' . $cssClass . '">' . $title . '</h2>';
		}
		$output .= '<ul class="form-list">';
		return $output;
	}

	public function renderElementPrefix(Customweb_Form_IElement $element)
	{
		$classes = $this->getCssClassPrefix() . $this->getElementCssClass();
		$classes .= ' ' . $this->getCssClassPrefix() . $element->getElementIntention()
			->getCssClass();

		$errorMessage = $element->getErrorMessage();
		if (!empty($errorMessage)) {
			$classes .= ' ' . $this->getCssClassPrefix() . $this->getElementErrorCssClass();
		}

		return '<li class="' . $classes . '" id="' . $element->getElementId() . '">';
	}

	public function renderElementPostfix(Customweb_Form_IElement $element)
	{
		return '</li>';
	}

	public function renderElementLabel(Customweb_Form_IElement $element)
	{
		$cssClasses = $this->getCssClassPrefix() . $this->getElementLabelCssClass();
		$for = '';
		if ($element->getControl() != null && $element->getControl()->getControlId() !== null && $element->getControl()->getControlId() != '') {
			$for = $element->getControl()->getControlId();
		}
		$label = $element->getLabel();
		if ($element->isRequired()) {
			$label .= $this->renderRequiredTag($element);
			$cssClasses .= ' required';
		}
	
		return $this->renderLabel($for, $label, $cssClasses);
	}
	
	/**
	 * @param Customweb_Form_IElement $element
	 * @return string
	 */
	protected function renderRequiredTag(Customweb_Form_IElement $element)
	{
		return '<em>*</em>';
	}
	
	public function renderControlPrefix(Customweb_Form_Control_IControl $control, $controlTypeClass)
	{
		$classes = $this->getCssClassPrefix() . $this->getControlCssClass() . ' ' . $this->getCssClassPrefix() . $controlTypeClass;
		if ($control instanceof Customweb_Form_Control_SingleCheckbox
			|| $control instanceof Customweb_Form_Control_MultiCheckbox
			|| $control instanceof Customweb_Form_Control_Radio) {
				$classes .= ' control';
			}
	
			return '<div class="' . $classes . '" id="' . $control->getControlId() . '-wrapper">';
	}

	protected function renderButtons(array $buttons, $jsFunctionPostfix = '')
	{
		$output = '<div class="buttons-set">';
		foreach ($buttons as $button) {
			$output .= $this->renderButton($button, $jsFunctionPostfix);
		}
		$output .= '</div>';
	
		return $output;
	}

	public function renderButton(Customweb_Form_IButton $button, $jsFunctionPostfix = '')
	{
		$postfix = $jsFunctionPostfix;
		if ($this->getNamespacePrefix() !== NULL) {
			$postfix = $this->getNamespacePrefix().$postfix;
		}
		$validation = 'onclick="cwValidationRequired'.$postfix.' = false; return true;"';
		if($button->isJSValidationExecuted()){
			$validation = 'onclick="cwValidationRequired'.$postfix.' = true; return true;"';
		}
		
		return '<button type="submit" name="button[' . $button->getMachineName() . ']" title="' . $button->getTitle() . '" id="' . $button->getId() . '" class="button ' . $this->getButtonClass() . '" '.$validation.'><span><span>' . $button->getTitle() . '</span></span></button>';
	}
}
